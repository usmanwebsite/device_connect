<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'db:sync 
                            {--incremental : Sync only recent data (last 24 hours)}
                            {--date= : Sync data from specific date (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)}
                            {--upsert : Use UPSERT for updates (insert or update)}
                            {--skip-truncate : Skip truncating tables in full sync}
                            {--tables=* : Sync specific tables only}
                            {--force : Force sync even if errors occur}
                            {--full : Force full sync (truncate and insert all)}';
    
    protected $description = 'Sync data from device_connect to sink_device_connect - Default: Incremental + UPSERT';

    private $tablesConfig = [
        'users' => [
            'sync_method' => 'auto', // auto will detect best method
        ],
        'security_alert_priorities' => [
            'sync_method' => 'insert_only', // Static data
        ],
        'paths' => [
            'sync_method' => 'insert_only',
        ],
        'visitor_types' => [
            'sync_method' => 'auto', // ✅ CHANGED from 'full' to 'auto'
        ],
        'locations' => [
            'sync_method' => 'auto',
        ],
        'vendor_locations' => [
            'sync_method' => 'auto',
        ],
        'ip_ranges' => [
            'sync_method' => 'insert_only',
        ],
        'device_connections' => [
            'sync_method' => 'insert_only',
        ],
        'device_commands' => [
            'sync_method' => 'incremental', // Only new inserts
        ],
        'device_location_assigns' => [
            'sync_method' => 'auto',
        ],
        'device_access_logs' => [
            'sync_method' => 'incremental', // Only new logs
        ],
    ];

    private $syncFromDate;
    private $startTime;
    private $syncType;
    private $useUpsert;
    private $forceMode;

        public function handle()
    {
        $this->startTime = microtime(true);
        
        // Determine sync mode
        $this->determineSyncMode();
        
        $this->syncFromDate = $this->parseSyncDate();
        $this->forceMode = $this->option('force');
        $skipTruncate = $this->option('skip-truncate');
        $specificTables = $this->option('tables');

        // Display header
        $this->showHeader();
        
        // Log start
        $this->logSyncStart();

        // Determine which tables to sync
        $tablesToSync = $this->getTablesToSync($specificTables);
        
        if (empty($tablesToSync)) {
            $this->error('❌ No tables to sync. Check your table configuration.');
            return 1;
        }

        // Process tables
        $results = $this->processTables($tablesToSync, $skipTruncate);
        
        // Show summary
        $this->showSummary($results);

        return $results['errorCount'] > 0 && !$this->forceMode ? 1 : 0;
    }

    /**
     * Determine sync mode based on options
     * ✅ DEFAULT: incremental + upsert
     */
    private function determineSyncMode()
    {
        $hasAnyOption = $this->option('incremental') || 
                       $this->option('full') || 
                       $this->option('date') || 
                       $this->option('upsert');

        if (!$hasAnyOption) {
            // ✅ SMART DEFAULT: Auto mode - each table uses best method
            $this->syncType = 'smart';
            $this->useUpsert = true;
            $this->info("ℹ️  Using SMART SYNC: Auto-detect best method per table");
        } else {
            // User specified options
            if ($this->option('full')) {
                $this->syncType = 'full';
                $this->useUpsert = $this->option('upsert');
            } else {
                $this->syncType = $this->option('incremental') ? 'incremental' : 'full';
                $this->useUpsert = $this->option('upsert');
            }
        }
    }

    /**
     * Parse and validate sync date
     */
    private function parseSyncDate()
    {
        $date = $this->option('date');
        
        if ($date) {
            if (!strtotime($date)) {
                $this->error("❌ Invalid date format: {$date}. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS");
                exit(1);
            }
            return $date;
        }
        
        // Default: last 24 hours for incremental/smart, all time for full
        return ($this->syncType === 'full') 
            ? '1970-01-01 00:00:00'
            : now()->subDay()->format('Y-m-d H:i:s');
    }

    /**
     * Display header information
     */
    private function showHeader()
    {
        $this->info(str_repeat('═', 70));
        $this->info('🚀 DATABASE SYNCHRONIZATION');
        $this->info(str_repeat('═', 70));
        $this->info("📋 Mode: " . strtoupper($this->syncType));
        $this->info("🔄 UPSERT: " . ($this->useUpsert ? '✅ ENABLED' : '❌ DISABLED'));
        $this->info("📅 Sync From: {$this->syncFromDate}");
        $this->info("⏰ Started: " . now()->format('Y-m-d H:i:s'));
        $this->info("🔧 Force Mode: " . ($this->forceMode ? '✅ ON' : '❌ OFF'));
        $this->info(str_repeat('═', 70) . "\n");
    }

    /**
     * Log sync start
     */
    private function logSyncStart()
    {
        Log::channel('sync')->info('🔄 SYNC STARTED', [
            'type' => $this->syncType,
            'upsert' => $this->useUpsert,
            'date' => $this->syncFromDate,
            'memory_initial' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
        ]);
    }

    /**
     * Get list of tables to sync
     */
    private function getTablesToSync($specificTables)
    {
        if (!empty($specificTables)) {
            $filteredTables = [];
            foreach ($specificTables as $table) {
                if (isset($this->tablesConfig[$table])) {
                    $filteredTables[$table] = $this->tablesConfig[$table];
                } else {
                    $this->warn("⚠️  Table '{$table}' not found in configuration.");
                }
            }
            return $filteredTables;
        }
        
        return $this->tablesConfig;
    }

    /**
     * Process all tables
     */
    private function processTables($tablesToSync, $skipTruncate)
    {
        $successCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $totalRecords = 0;
        $tableResults = [];

        foreach ($tablesToSync as $tableName => $config) {
            $this->newLine();
            $tableStart = microtime(true);
            
            try {
                // Determine the BEST sync method for this table
                $result = $this->determineBestSyncMethod($tableName, $config, $skipTruncate);
                
                $tableTime = round(microtime(true) - $tableStart, 2);
                
                if (is_array($result)) {
                    $successCount++;
                    $totalRecords += $result['records'] ?? 0;
                    
                    $tableResults[$tableName] = [
                        'status' => 'success',
                        'records' => $result['records'] ?? 0,
                        'time' => $tableTime,
                        'details' => $result['details'] ?? '',
                        'method' => $result['method'] ?? 'unknown'
                    ];
                    
                    $statusIcon = '✅';
                } elseif ($result === null) {
                    $skippedCount++;
                    $tableResults[$tableName] = [
                        'status' => 'skipped',
                        'records' => 0,
                        'time' => $tableTime
                    ];
                    $statusIcon = '⏭️';
                } else {
                    $errorCount++;
                    $tableResults[$tableName] = [
                        'status' => 'error',
                        'records' => 0,
                        'time' => $tableTime,
                        'error' => 'Sync failed'
                    ];
                    $statusIcon = '❌';
                    
                    if (!$this->forceMode) {
                        $this->error("   ⚠️  Stopping due to error (use --force to continue)");
                        break;
                    }
                }
                
                $method = $tableResults[$tableName]['method'] ?? 'sync';
                $this->line("{$statusIcon} {$tableName} ({$method}) - {$tableTime}s");
                
            } catch (\Exception $e) {
                $errorCount++;
                $tableTime = round(microtime(true) - $tableStart, 2);
                $tableResults[$tableName] = [
                    'status' => 'error',
                    'records' => 0,
                    'time' => $tableTime,
                    'error' => $e->getMessage()
                ];
                
                $this->error("   ❌ Unexpected error: " . $e->getMessage());
                
                if (!$this->forceMode) {
                    $this->error("   ⚠️  Stopping due to error (use --force to continue)");
                    break;
                }
            }
        }

        return [
            'successCount' => $successCount,
            'skippedCount' => $skippedCount,
            'errorCount' => $errorCount,
            'totalRecords' => $totalRecords,
            'tableResults' => $tableResults
        ];
    }

        private function determineBestSyncMethod($tableName, $config, $skipTruncate)
    {
        // Check if table exists
        if (!Schema::connection('mysql')->hasTable($tableName)) {
            $this->warn("   📭 Table '{$tableName}' not found in source");
            return null;
        }

        // Create table if needed
        if (!Schema::connection('mysql_second')->hasTable($tableName)) {
            if (!$this->createDestinationTable($tableName)) {
                throw new \Exception("Failed to create table structure");
            }
        }

        // Get table configuration
        $configMethod = $config['sync_method'] ?? 'auto';
        
        // If user forced full sync
        if ($this->syncType === 'full') {
            return [
                'records' => $this->syncTableFull($tableName, $skipTruncate)['records'] ?? 0,
                'method' => 'full'
            ];
        }

        // Auto-detect best method
        if ($configMethod === 'auto' || $this->syncType === 'smart') {
            return $this->autoDetectSyncMethod($tableName);
        }
        
        // Use configured method
        switch ($configMethod) {
            case 'insert_only':
                return [
                    'records' => $this->syncTableInsertOnly($tableName)['records'] ?? 0,
                    'method' => 'insert_only'
                ];
            case 'incremental':
                return [
                    'records' => $this->syncTableIncrementalSmart($tableName)['records'] ?? 0,
                    'method' => 'incremental'
                ];
            case 'upsert':
                return [
                    'records' => $this->syncTableUpsertSmart($tableName)['records'] ?? 0,
                    'method' => 'upsert'
                ];
            default:
                return $this->autoDetectSyncMethod($tableName);
        }
    }


        private function syncTableUpsertSmart($tableName)
    {
        $this->line("⚡ SMART UPSERT: {$tableName}");

        // Get primary key
        $primaryKey = $this->getPrimaryKey($tableName);
        if (!$primaryKey) {
            $this->warn("   ⚠️  No primary key found, falling back to incremental");
            return $this->syncTableIncrementalSmart($tableName);
        }

        // Try updated_at first, then created_at
        $dateColumn = Schema::connection('mysql')->hasColumn($tableName, 'updated_at') 
            ? 'updated_at' 
            : (Schema::connection('mysql')->hasColumn($tableName, 'created_at') 
                ? 'created_at' 
                : null);

        if (!$dateColumn) {
            $this->warn("   ⚠️  No date column, using insert only");
            return $this->syncTableInsertOnly($tableName);
        }

        // Get changed records
        $changedRecords = DB::connection('mysql')
            ->table($tableName)
            ->where($dateColumn, '>=', $this->syncFromDate)
            ->get();

        $changedCount = $changedRecords->count();
        
        if ($changedCount === 0) {
            $this->info("   ✅ No changes since " . date('Y-m-d H:i', strtotime($this->syncFromDate)));
            return ['records' => 0, 'details' => 'no changes'];
        }

        $this->info("   📊 Changed records: {$changedCount} (using {$dateColumn})");

        // Prepare data for UPSERT
        $dataToUpsert = [];
        foreach ($changedRecords as $row) {
            $dataToUpsert[] = (array)$row;
        }

        // Get columns for update
        $allColumns = Schema::connection('mysql')->getColumnListing($tableName);
        $updateColumns = array_diff($allColumns, [$primaryKey]);

        // Perform UPSERT in chunks
        $chunks = array_chunk($dataToUpsert, 200);
        $totalUpserted = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $affected = DB::connection('mysql_second')
                    ->table($tableName)
                    ->upsert($chunk, [$primaryKey], $updateColumns);
                
                $totalUpserted += $affected;
                $this->line("   Chunk " . ($chunkIndex + 1) . ": {$affected} records affected");
                
            } catch (\Exception $e) {
                $this->warn("   ⚠️  UPSERT failed: " . $e->getMessage());
                $this->warn("   Trying manual UPSERT...");
                $totalUpserted += $this->manualUpsert($tableName, $chunk, $primaryKey);
            }
        }

        $this->info("   ✅ UPSERT completed: {$totalUpserted} records affected");
        
        return [
            'records' => $totalUpserted,
            'details' => "smart upsert using {$dateColumn}"
        ];
    }



        private function autoDetectSyncMethod($tableName)
    {
        $this->line("🔍 Auto-detecting best method for: {$tableName}");
        
        // Check table structure
        $hasPrimaryKey = $this->getPrimaryKey($tableName);
        $hasUpdatedAt = Schema::connection('mysql')->hasColumn($tableName, 'updated_at');
        $hasCreatedAt = Schema::connection('mysql')->hasColumn($tableName, 'created_at');
        
        $this->info("   📊 Structure: PK=" . ($hasPrimaryKey ? 'Yes' : 'No') . 
                   ", updated_at=" . ($hasUpdatedAt ? 'Yes' : 'No') . 
                   ", created_at=" . ($hasCreatedAt ? 'Yes' : 'No'));

        // Decision logic
        if ($hasPrimaryKey && $hasUpdatedAt) {
            $this->info("   ✅ Using UPSERT (has PK and updated_at)");
            return [
                'records' => $this->syncTableUpsertSmart($tableName)['records'] ?? 0,
                'method' => 'upsert_auto',
                'details' => 'auto-detected: PK+updated_at'
            ];
        }
        elseif ($hasCreatedAt) {
            $this->info("   ✅ Using Incremental (has created_at)");
            return [
                'records' => $this->syncTableIncrementalSmart($tableName)['records'] ?? 0,
                'method' => 'incremental_auto',
                'details' => 'auto-detected: created_at'
            ];
        }
        else {
            $this->info("   ✅ Using Insert Only (no date columns)");
            return [
                'records' => $this->syncTableInsertOnly($tableName)['records'] ?? 0,
                'method' => 'insert_only_auto',
                'details' => 'auto-detected: no date columns'
            ];
        }
    }


        private function syncTableIncrementalSmart($tableName)
    {
        $this->line("📅 SMART INCREMENTAL: {$tableName}");

        // Try updated_at first, then created_at
        $dateColumn = Schema::connection('mysql')->hasColumn($tableName, 'updated_at') 
            ? 'updated_at' 
            : (Schema::connection('mysql')->hasColumn($tableName, 'created_at') 
                ? 'created_at' 
                : null);

        if (!$dateColumn) {
            $this->warn("   ⚠️  No date column found, using insert only");
            return $this->syncTableInsertOnly($tableName);
        }

        $this->info("   📅 Using date column: {$dateColumn}");

        // Get new records
        $newRecords = DB::connection('mysql')
            ->table($tableName)
            ->where($dateColumn, '>=', $this->syncFromDate)
            ->get();

        $newRecordCount = $newRecords->count();
        
        if ($newRecordCount === 0) {
            $this->info("   ✅ No new records since " . date('Y-m-d H:i', strtotime($this->syncFromDate)));
            return ['records' => 0, 'details' => 'no changes'];
        }

        $this->info("   📊 New records: {$newRecordCount}");

        // Insert new records
        $dataToInsert = [];
        foreach ($newRecords as $row) {
            $dataToInsert[] = (array)$row;
        }

        $chunks = array_chunk($dataToInsert, 500);
        $insertedCount = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::connection('mysql_second')
                    ->table($tableName)
                    ->insertOrIgnore($chunk);
                
                $insertedCount += count($chunk);
                $this->line("   Chunk " . ($chunkIndex + 1) . ": " . count($chunk) . " records");
                
            } catch (\Exception $e) {
                // Insert one by one
                foreach ($chunk as $record) {
                    try {
                        DB::connection('mysql_second')
                            ->table($tableName)
                            ->insertOrIgnore($record);
                        $insertedCount++;
                    } catch (\Exception $ex) {
                        // Skip duplicates
                    }
                }
            }
        }

        $this->info("   ✅ Inserted: {$insertedCount} new records");
        
        return [
            'records' => $insertedCount,
            'details' => "smart incremental using {$dateColumn}"
        ];
    }

    /**
     * FULL SYNC - Complete table sync
     */
    private function syncTableFull($tableName, $skipTruncate = false)
    {
        $this->line("🔄 FULL SYNC: {$tableName}");

        // Get total records
        $totalRecords = DB::connection('mysql')
            ->table($tableName)
            ->count();

        if ($totalRecords === 0) {
            $this->info("   📭 No data in source table");
            return ['records' => 0, 'details' => 'empty'];
        }

        $this->info("   📊 Source records: {$totalRecords}");

        // Prepare for sync
        DB::connection('mysql_second')->statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Truncate or delete based on option
        if (!$skipTruncate) {
            DB::connection('mysql_second')->table($tableName)->truncate();
            $this->info("   ♻️  Destination table truncated");
        } else {
            $this->info("   📝 Appending to existing data");
        }

        // Sync with progress bar for large tables
        $batchSize = 1000;
        $offset = 0;
        $insertedCount = 0;
        
        if ($totalRecords > 10000) {
            $progressBar = $this->output->createProgressBar(ceil($totalRecords / $batchSize));
            $progressBar->setFormat("   %current%/%max% [%bar%] %percent:3s%% %message%");
            $progressBar->setMessage("Starting...");
        }

        while ($offset < $totalRecords) {
            $batch = DB::connection('mysql')
                ->table($tableName)
                ->skip($offset)
                ->take($batchSize)
                ->get();

            if ($batch->isEmpty()) break;

            $dataToInsert = [];
            foreach ($batch as $row) {
                $dataToInsert[] = (array)$row;
            }

            try {
                DB::connection('mysql_second')
                    ->table($tableName)
                    ->insert($dataToInsert);
                
                $insertedCount += count($dataToInsert);
                
                if (isset($progressBar)) {
                    $progressBar->setMessage("Inserted: {$insertedCount}/{$totalRecords}");
                    $progressBar->advance();
                }
                
            } catch (\Exception $e) {
                $this->warn("   ⚠️  Batch insert failed, retrying individually...");
                
                // Try inserting one by one
                foreach ($dataToInsert as $record) {
                    try {
                        DB::connection('mysql_second')
                            ->table($tableName)
                            ->insertOrIgnore($record);
                        $insertedCount++;
                    } catch (\Exception $ex) {
                        // Skip problematic records
                    }
                }
            }

            $offset += $batchSize;
        }

        if (isset($progressBar)) {
            $progressBar->finish();
            $this->newLine();
        }

        DB::connection('mysql_second')->statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("   ✅ Inserted: {$insertedCount} records");
        
        if ($insertedCount < $totalRecords) {
            $this->warn("   ⚠️  Some records skipped: {$insertedCount}/{$totalRecords}");
        }

        return [
            'records' => $insertedCount,
            'details' => "full sync"
        ];
    }

    /**
     * INCREMENTAL SYNC - Only new records
     */
    private function syncTableIncremental($tableName, $config)
    {
        $this->line("📅 INCREMENTAL SYNC: {$tableName}");

        // Determine date column
        $dateColumn = $config['date_column'] ?? 'created_at';
        if (!Schema::connection('mysql')->hasColumn($tableName, $dateColumn)) {
            $this->warn("   ⚠️  No '{$dateColumn}' column, using insert only");
            return $this->syncTableInsertOnly($tableName);
        }

        // Get new records
        $newRecords = DB::connection('mysql')
            ->table($tableName)
            ->where($dateColumn, '>=', $this->syncFromDate)
            ->get();

        $newRecordCount = $newRecords->count();
        
        if ($newRecordCount === 0) {
            $this->info("   ✅ No new records since " . date('Y-m-d H:i', strtotime($this->syncFromDate)));
            return ['records' => 0, 'details' => 'no changes'];
        }

        $this->info("   📊 New records: {$newRecordCount}");

        // Insert new records
        $dataToInsert = [];
        foreach ($newRecords as $row) {
            $dataToInsert[] = (array)$row;
        }

        $chunks = array_chunk($dataToInsert, 500);
        $insertedCount = 0;
        $duplicateCount = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                // Try bulk insert first
                $result = DB::connection('mysql_second')
                    ->table($tableName)
                    ->insertOrIgnore($chunk);
                
                $insertedCount += count($chunk);
                $this->line("   Chunk " . ($chunkIndex + 1) . ": " . count($chunk) . " records");
                
            } catch (\Exception $e) {
                // Insert one by one
                foreach ($chunk as $record) {
                    try {
                        DB::connection('mysql_second')
                            ->table($tableName)
                            ->insertOrIgnore($record);
                        $insertedCount++;
                    } catch (\Exception $ex) {
                        $duplicateCount++;
                    }
                }
            }
        }

        if ($duplicateCount > 0) {
            $this->info("   ⚠️  Duplicates skipped: {$duplicateCount}");
        }

        $this->info("   ✅ Inserted: {$insertedCount} new records");
        
        return [
            'records' => $insertedCount,
            'details' => "incremental sync"
        ];
    }

    /**
     * INSERT ONLY - For tables without date columns
     */
    private function syncTableInsertOnly($tableName)
    {
        $this->line("📝 INSERT ONLY: {$tableName}");

        // Get all records
        $allRecords = DB::connection('mysql')
            ->table($tableName)
            ->get();

        $totalRecords = $allRecords->count();
        
        if ($totalRecords === 0) {
            $this->info("   📭 No data in source table");
            return ['records' => 0, 'details' => 'empty'];
        }

        $this->info("   📊 Total records: {$totalRecords}");

        // Insert records
        $dataToInsert = [];
        foreach ($allRecords as $row) {
            $dataToInsert[] = (array)$row;
        }

        $chunks = array_chunk($dataToInsert, 500);
        $insertedCount = 0;
        $duplicateCount = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::connection('mysql_second')
                    ->table($tableName)
                    ->insertOrIgnore($chunk);
                
                $insertedCount += count($chunk);
                $this->line("   Chunk " . ($chunkIndex + 1) . ": " . count($chunk) . " records");
                
            } catch (\Exception $e) {
                // Insert one by one
                foreach ($chunk as $record) {
                    try {
                        DB::connection('mysql_second')
                            ->table($tableName)
                            ->insertOrIgnore($record);
                        $insertedCount++;
                    } catch (\Exception $ex) {
                        $duplicateCount++;
                    }
                }
            }
        }

        if ($duplicateCount > 0) {
            $this->info("   ⚠️  Duplicates skipped: {$duplicateCount}");
        }

        $this->info("   ✅ Inserted: {$insertedCount} records");
        
        return [
            'records' => $insertedCount,
            'details' => "insert only"
        ];
    }
    /**
     * Manual UPSERT for compatibility
     */
    private function manualUpsert($tableName, $records, $primaryKey)
    {
        $affected = 0;
        
        foreach ($records as $record) {
            $idValue = $record[$primaryKey] ?? null;
            
            if (!$idValue) {
                // Insert without ID
                try {
                    DB::connection('mysql_second')
                        ->table($tableName)
                        ->insert($record);
                    $affected++;
                } catch (\Exception $e) {
                    // Skip
                }
                continue;
            }

            // Check if exists
            $exists = DB::connection('mysql_second')
                ->table($tableName)
                ->where($primaryKey, $idValue)
                ->exists();

            try {
                if ($exists) {
                    // Update
                    DB::connection('mysql_second')
                        ->table($tableName)
                        ->where($primaryKey, $idValue)
                        ->update($record);
                } else {
                    // Insert
                    DB::connection('mysql_second')
                        ->table($tableName)
                        ->insert($record);
                }
                $affected++;
            } catch (\Exception $e) {
                // Skip problematic records
            }
        }
        
        return $affected;
    }

    /**
     * Get primary key column
     */
    private function getPrimaryKey($tableName)
    {
        try {
            $result = DB::connection('mysql')
                ->select("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            
            if (!empty($result)) {
                return $result[0]->Column_name;
            }
            
            // Try common primary key names
            $commonKeys = ['id', 'uuid', 'uid', 'ID', 'Id'];
            $columns = Schema::connection('mysql')->getColumnListing($tableName);
            
            foreach ($commonKeys as $key) {
                if (in_array($key, $columns)) {
                    return $key;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create table in destination database
     */
    private function createDestinationTable($tableName)
    {
        try {
            $result = DB::connection('mysql')
                ->select("SHOW CREATE TABLE `{$tableName}`");
            
            if (empty($result)) {
                $this->error("   ❌ Could not get table structure for '{$tableName}'");
                return false;
            }

            $createTableSql = $result[0]->{'Create Table'};

            // Remove AUTO_INCREMENT from CREATE TABLE to avoid conflicts
            $createTableSql = preg_replace('/AUTO_INCREMENT=\d+/', '', $createTableSql);

            DB::connection('mysql_second')->statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::connection('mysql_second')->statement($createTableSql);
            DB::connection('mysql_second')->statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->info("   📋 Table structure created successfully");
            return true;

        } catch (\Exception $e) {
            DB::connection('mysql_second')->statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("   ❌ Failed to create table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Show detailed summary
     */
    private function showSummary($results)
    {
        $totalTime = round(microtime(true) - $this->startTime, 2);
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        $this->newLine(2);
        $this->info(str_repeat('═', 70));
        $this->info('📊 SYNCHRONIZATION SUMMARY');
        $this->info(str_repeat('═', 70));
        $this->info("⏱️  Total Time: {$totalTime} seconds");
        $this->info("💾 Peak Memory: {$memoryPeak} MB");
        $this->info("📈 Total Records Processed: " . number_format($results['totalRecords']));
        $this->info(str_repeat('─', 70));
        $this->info("✅ Successful Tables: {$results['successCount']}");
        $this->info("⏭️  Skipped Tables: {$results['skippedCount']}");
        $this->info("❌ Failed Tables: {$results['errorCount']}");
        $this->info(str_repeat('═', 70));

        // Show table-wise details with methods
        if ($results['successCount'] > 0) {
            $this->newLine();
            $this->info('📋 Table Details (Method Used):');
            $this->info(str_repeat('─', 70));
            
            foreach ($results['tableResults'] as $tableName => $result) {
                $statusIcon = match($result['status']) {
                    'success' => '✅',
                    'skipped' => '⏭️',
                    'error' => '❌',
                    default => '❓'
                };
                
                $method = $result['method'] ?? 'sync';
                $details = "{$statusIcon} {$tableName} [{$method}] - ";
                
                if ($result['status'] === 'success') {
                    $details .= number_format($result['records']) . " records in {$result['time']}s";
                    if (!empty($result['details'])) {
                        $details .= " ({$result['details']})";
                    }
                } elseif ($result['status'] === 'error') {
                    $details .= "Failed in {$result['time']}s";
                    if (!empty($result['error'])) {
                        $details .= " - {$result['error']}";
                    }
                } else {
                    $details .= "Skipped";
                }
                
                $this->line($details);
            }
            $this->info(str_repeat('─', 70));
        }

        // Final status
        $this->newLine();
        if ($results['errorCount'] > 0 && !$this->forceMode) {
            $this->error('❌ SYNCHRONIZATION FAILED');
            $this->error('   Some tables failed to sync. Check logs for details.');
        } elseif ($results['errorCount'] > 0) {
            $this->warn('⚠️  SYNCHRONIZATION COMPLETED WITH ERRORS');
            $this->warn('   Some tables failed, but operation completed due to --force flag.');
        } else {
            $this->info('🎉 SYNCHRONIZATION COMPLETED SUCCESSFULLY!');
        }

        // Log summary
        Log::channel('sync')->info('🔄 SYNC COMPLETED', [
            'type' => $this->syncType,
            'upsert' => $this->useUpsert,
            'total_time' => $totalTime,
            'memory_peak_mb' => $memoryPeak,
            'total_records' => $results['totalRecords'],
            'success_tables' => $results['successCount'],
            'skipped_tables' => $results['skippedCount'],
            'failed_tables' => $results['errorCount']
        ]);
    }
}
