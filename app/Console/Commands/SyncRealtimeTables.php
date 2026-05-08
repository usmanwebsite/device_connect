<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SyncRealtimeTables extends Command
{
    protected $signature = 'sync:realtime';
    protected $description = 'Sync device_location_assigns, visitor_types, paths every 2 minutes (incremental upsert)';

    private array $tables = ['paths', 'visitor_types', 'device_location_assigns'];

    public function handle(): void
    {
        // Order matters: paths first because visitor_types has path_id FK
        $tables = ['paths', 'visitor_types', 'device_location_assigns'];

        foreach ($tables as $table) {
            $this->info("Syncing: {$table}");

            $rows = DB::connection('mysql_second')   // SOURCE  = live
                ->table($table)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();

            if (empty($rows)) {
                $this->warn("  No records found in {$table}, skipping.");
                continue;
            }

            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0;');  // DEST = local

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::connection('mysql')
                    ->table($table)
                    ->upsert(
                        $chunk,
                        ['id'],                                          // unique key
                        array_diff(array_keys($chunk[0]), ['id'])        // columns to update
                    );
            }

            DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->info("  ✅ Synced " . count($rows) . " records.");
        }

        $this->info('Done.');
    }
}

