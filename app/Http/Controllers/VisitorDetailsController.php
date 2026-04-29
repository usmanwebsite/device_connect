<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\MenuService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\VendorLocation;
use Carbon\Carbon;

class VisitorDetailsController extends Controller
{
    protected $menuService;
    protected $javaBaseUrl;
    const MALAYSIA_TIMEZONE = 'Asia/Kuala_Lumpur';
    
    public function __construct(MenuService $menuService, Request $request)
    {
        $this->menuService = $menuService;

        $host = $request->getHost();
        $scheme = $request->getScheme();
        
        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'localhost:8000'])) {
            $this->javaBaseUrl = 'http://127.0.0.1:8080';
        } else {
            $scheme = $request->getScheme(); // http ya https
            $this->javaBaseUrl = $scheme . '://' . $host . ':8080';
        }
        $this->javaBaseUrl = $scheme . '://' . $host . ':8080';
        //dd($this->javaBaseUrl);
        Log::info('Java Base URL (dynamic): ' . $this->javaBaseUrl);
    }

    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $javaBaseUrl = $this->javaBaseUrl;
        
        return view('visitorDetails&Chronology.visitorDetails&Chronology', compact('angularMenu'));
    }

public function search(Request $request)
{
    $request->validate([
        'search_term' => 'required|string|min:1',
    ]);

    $searchTerm = $request->input('search_term');
    $searchType = $request->input('search_type');

    try {
        $response = $this->callJavaApi($searchTerm, $searchType);
        
        Log::info('Java API Response in Controller:', ['response' => $response]);

        $data = [];
        
        if ($response && isset($response['status']) && $response['status'] === 'OK') {

            if (isset($response['data'])) {
                $data = $response['data'];
                
                if (is_string($data)) {
                    Log::warning('Data is string, attempting to decode');
                    $decodedData = json_decode($data, true);
                    if ($decodedData !== null) {
                        $data = $decodedData;
                    } else {
                        $data = [['info' => $data]];
                    }
                }
            } 
            else {
                $data = $response;
            }
        } 
        elseif (is_array($response) && !isset($response['status'])) {
            $data = $response;
        }
        
        Log::info('Processed data for display:', ['data' => $data, 'count' => count($data)]);
        
        if (!empty($data)) {
            if (!is_array($data) || isset($data['staffNo']) || isset($data['icNo'])) {
                $data = [$data];
            }
            
            foreach ($data as &$visitor) {
                $identifier = $visitor['icNo'] ?? $visitor['staffNo'] ?? 
                              ($visitor['ic_no'] ?? $visitor['staff_no'] ?? '');
                
                if ($identifier) {
                    $visitData = $this->getVisitTimings($identifier);
                    $visitor['visitFrom'] = $visitData['visitFrom'];
                    $visitor['visitTo'] = $visitData['visitTo'];
                    $visitor['visitDuration'] = $visitData['duration'];
                    $visitor['isCurrentlyInBuilding'] = $visitData['isCurrentlyInBuilding'];
                } else {
                    $visitor['visitFrom'] = null;
                    $visitor['visitTo'] = null;
                    $visitor['visitDuration'] = 'No access logs found';
                    $visitor['isCurrentlyInBuilding'] = false;
                }
            }

            return response()->json([
                'status' => 'OK',
                'message' => 'Visitor details retrieved successfully',
                'data' => $data,
                'count' => count($data)
            ]);
        }

        // If no data found, return error
        return response()->json([
            'status' => 'error',
            'message' => $response['message'] ?? 'Visitor not found',
            'data' => null
        ], 404);

    } catch (\Exception $e) {
        Log::error('Error in visitor search: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());

        return response()->json([
            'status' => 'error',
            'message' => 'Error fetching visitor details: ' . $e->getMessage(),
            'data' => null
        ], 500);
    }
}

private function callJavaApi($searchTerm, $searchType)
{
    try {
        $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
        
        // Get token from session
        $token = session()->get('java_backend_token')
               ?? session()->get('java_auth_token');
        
        if (!$token) {
            Log::error('No authentication token found in session');
            return [
                'status' => 'error',
                'message' => 'Authentication token not found. Please login again.',
                'data' => null
            ];
        }
        
        // Build URL based on search type
        if ($searchType === 'icNo') {
            $url = $javaBaseUrl . "/api/vendorpass/get-visitor-details?icNo=" . urlencode($searchTerm);
        } else {
            $url = $javaBaseUrl . "/api/vendorpass/get-visitor-details?staffNo=" . urlencode($searchTerm);
        }

        Log::info('Calling Java API:', [
            'url' => $url,
            'searchTerm' => $searchTerm,
            'searchType' => $searchType
        ]);

        // Make API call with authentication token
        $response = Http::withHeaders([
            'x-auth-token' => $token,
            'Accept' => 'application/json',
        ])->timeout(30)->get($url);
        
        Log::info('Java API Response Status:', ['status' => $response->status()]);
        Log::info('Java API Response Headers:', $response->headers());
        
        if ($response->successful()) {
            $content = $response->body();
            Log::info('Java API Raw Response:', ['body' => $content]);
            
            // Try to decode JSON
            $data = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                Log::info('Java API JSON decoded successfully');
                Log::info('Decoded data type:', ['type' => gettype($data)]);
                Log::info('Decoded data structure:', $data);
                
                return $data;
            } else {
                Log::error('JSON decode error: ' . json_last_error_msg());
                Log::error('Raw content: ' . substr($content, 0, 500));
                
                // If it's not JSON, return as is
                return [
                    'status' => 'success',
                    'data' => $content,
                    'message' => 'Response received (non-JSON)'
                ];
            }
        }

        Log::error('Java API Error:', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return [
            'status' => 'error',
            'message' => 'API request failed with status: ' . $response->status(),
            'data' => null
        ];

    } catch (\Exception $e) {
        Log::error('Java API Call Exception: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return [
            'status' => 'error',
            'message' => 'Error connecting to Java API: ' . $e->getMessage(),
            'data' => null
        ];
    }
}

private function isTurnstileCheckIn($locationName, $logTime, $staffNo)
{
    try {
        Log::info("=== TURNSTILE CHECK START ===");
        Log::info("Parameters: StaffNo=$staffNo, Location=$locationName, Time=$logTime");
        
        // 🔥 FIX: Use whereRaw for exact time match (avoid milliseconds/format issues)
        $logRecord = DB::table('device_access_logs')
            ->where('staff_no', $staffNo)
            ->where('location_name', $locationName)
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') = ?", [date('Y-m-d H:i:s', strtotime($logTime))])
            ->first();
        
        if (!$logRecord) {
            Log::warning("❌ Log record not found for time: $logTime");
            Log::info("=== TURNSTILE CHECK END (false) ===");
            return false;
        }
        
        Log::info("✅ Log record found with ID: " . $logRecord->id . ", device_id: " . ($logRecord->device_id ?? 'NULL'));
        
        if (empty($logRecord->device_id)) {
            Log::warning("❌ device_id is empty in log record");
            Log::info("=== TURNSTILE CHECK END (false) ===");
            return false;
        }
        
        // 2. DEVICE CONNECTION
        $deviceConnection = DB::table('device_connections')
            ->where('device_id', $logRecord->device_id)
            ->first();
        
        if (!$deviceConnection) {
            Log::warning("❌ No device connection found for device_id: " . $logRecord->device_id);
            Log::info("=== TURNSTILE CHECK END (false) ===");
            return false;
        }
        
        Log::info("✅ Device connection found: ID=" . $deviceConnection->id);
        
        // 3. VENDOR LOCATION - 🔥 FIX: Better search
        $location = DB::table('vendor_locations')
            ->where('name', 'LIKE', '%13.TURNSTILE%')
            ->orWhere('name', 'LIKE', '%Turnstile%')
            ->first();
        
        if (!$location) {
            Log::warning("❌ Turnstile location not found in vendor_locations");
            Log::info("=== TURNSTILE CHECK END (false) ===");
            return false;
        }
        
        Log::info("✅ Location found: ID=" . $location->id . ", Name=" . $location->name);
        
        // 4. DEVICE LOCATION ASSIGN
        $deviceLocationAssign = DB::table('device_location_assigns')
            ->where('location_id', $location->id)
            ->where('device_id', $deviceConnection->id)
            ->first();
        
        if ($deviceLocationAssign) {
            Log::info("✅ Device Location Assign found: Type=" . $deviceLocationAssign->is_type);
            
            if ($deviceLocationAssign->is_type === 'check_in') {
                Log::info("✓ This is CHECK-IN turnstile");
                Log::info("=== TURNSTILE CHECK END (true) ===");
                return true;
            } elseif ($deviceLocationAssign->is_type === 'check_out') {
                Log::info("✗ This is CHECK-OUT turnstile");
                Log::info("=== TURNSTILE CHECK END (false) ===");
                return false;
            }
        } else {
            Log::warning("❌ No device_location_assign found");
            
            // 🔥 FALLBACK: Agar assign nahi mila, toh kisi bi device se check karo
            $anyCheckOut = DB::table('device_location_assigns')
                ->where('location_id', $location->id)
                ->where('is_type', 'check_out')
                ->exists();
            
            if ($anyCheckOut) {
                Log::info("⚠ FALLBACK: check_out exists for this location, assuming CHECK-OUT");
                return false;
            }
        }
        
        Log::info("=== TURNSTILE CHECK END (false - default) ===");
        return false;
        
    } catch (\Exception $e) {
        Log::error('❌ Error in isTurnstileCheckIn: ' . $e->getMessage());
        Log::info("=== TURNSTILE CHECK END (false - exception) ===");
        return false;
    }
}

private function getVisitSessions($staffNo)
{
    Log::info("Getting visit sessions for staff: $staffNo");
    
    $turnstileLogs = DB::table('device_access_logs')
        ->where('staff_no', $staffNo)
        ->where('location_name', 'like', '%13.TURNSTILE%')
        ->where('access_granted', 1)
        ->orderBy('created_at', 'asc')
        ->select('id', 'created_at', 'location_name', 'device_id', 'access_granted')
        ->get();
    
    Log::info("Total turnstile logs found: " . $turnstileLogs->count());
    
    $visitSessions = [];
    $currentSession = null;
    
    foreach ($turnstileLogs as $log) {
        $isCheckIn = $this->isTurnstileCheckIn($log->location_name, $log->created_at, $staffNo);
        
        $malaysiaTime = Carbon::parse($log->created_at)->setTimezone(self::MALAYSIA_TIMEZONE);

        if ($isCheckIn && $currentSession === null) {
            $currentSession = [
                'check_in_time' => $log->created_at,
                'check_in_log_id' => $log->id,
                'check_in_device' => $log->device_id,
                'check_in_location' => $log->location_name,
                'check_out_time' => null,
                'check_out_log_id' => null,
                'check_out_device' => null,
                'check_out_location' => null,
                'visit_date' => $malaysiaTime->format('Y-m-d'),
                'formatted_date' => $malaysiaTime->format('d-M-Y')
            ];
            Log::info("Started new session at: " . $log->created_at);
            
        } elseif (!$isCheckIn && $currentSession !== null) {
            $currentSession['check_out_time'] = $log->created_at;
            $currentSession['check_out_log_id'] = $log->id;
            $currentSession['check_out_device'] = $log->device_id;
            $currentSession['check_out_location'] = $log->location_name;
            
            $visitSessions[] = $currentSession;
            Log::info("Ended session at: " . $log->created_at . 
                     " (Started at: " . $currentSession['check_in_time'] . ")");
            
            $currentSession = null;
        }
    }
    
    if ($currentSession !== null) {
        $currentSession['check_out_time'] = null; 
        $visitSessions[] = $currentSession;
        Log::info("Open session (still in building) started at: " . $currentSession['check_in_time']);
    }
    
    Log::info("Total visit sessions found: " . count($visitSessions));
    return $visitSessions;
}

// public function getVisitorChronology(Request $request)
// {
//     $request->validate([
//         'ic_no' => 'required|string'
//     ]);

//     try {
//         $icNo = $request->input('ic_no');
        
//         // ✅ NAYA: Sirf basic logs lo
//         $accessLogs = DB::table('device_access_logs')
//             ->where('staff_no', $icNo)
//             ->orderBy('created_at', 'asc') // Ascending order
//             ->get();
        
//         if ($accessLogs->isEmpty()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'No access logs found'
//             ]);
//         }
        
//         // ✅ NAYA: Simple timeline banao (NO TIME FILTER)
//         $locationTimeline = [];
        
//         for ($i = 1; $i < count($accessLogs); $i++) {
//             $currentLog = $accessLogs[$i];
//             $previousLog = $accessLogs[$i - 1];
            
//             $timeSpent = strtotime($currentLog->created_at) 
//                        - strtotime($previousLog->created_at);
            
//             $locationTimeline[] = [
//                 'from_location' => $previousLog->location_name ?? 'Unknown',
//                 'to_location' => $currentLog->location_name ?? 'Unknown',
//                 'entry_time' => $previousLog->created_at,
//                 'exit_time' => $currentLog->created_at,
//                 'time_spent' => $this->formatTime($timeSpent),
//                 'access_granted' => $currentLog->access_granted ?? 1
//             ];
//         }
        
//         // ✅ NAYA: Simple date-wise grouping
//         $visitDates = [];
//         $logsByDate = [];
//         $timelineByDate = [];
        
//         foreach ($accessLogs as $log) {
//             $dateKey = date('d-M-Y', strtotime($log->created_at));
            
//             if (!in_array($dateKey, $visitDates)) {
//                 $visitDates[] = $dateKey;
//             }
            
//             $logsByDate[$dateKey][] = $log;
//         }
        
//         // ✅ NAYA: Timeline ko bhi date-wise group karo
//         foreach ($locationTimeline as $item) {
//             $dateKey = date('d-M-Y', strtotime($item['entry_time']));
//             $timelineByDate[$dateKey][] = $item;
//         }
        
//         // Dates ko latest first karo
//         usort($visitDates, function($a, $b) {
//             return strtotime($b) - strtotime($a);
//         });
        
//         // ✅ NAYA: Yeh 3 fields ADD karo
//         $currentStatus = $this->getCurrentStatus($icNo);
//         $totalTimeSpent = $this->calculateTotalTimeSpent($accessLogs);
//         $summary = $this->generateSummary($icNo, $accessLogs);
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'dates' => $visitDates,
//                 'logs_by_date' => $logsByDate,
//                 'timeline_by_date' => $timelineByDate,
//                 'current_status' => $currentStatus,  
//                 'total_time_spent' => $totalTimeSpent, 
//                 'summary' => $summary, 
//                 'all_access_logs' => $accessLogs,
//                 'all_location_timeline' => $locationTimeline
//             ]
//         ]);
        
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Error: ' . $e->getMessage()
//         ]);
//     }
// }

// public function getVisitorChronology(Request $request)
// {
//     $request->validate([
//         'ic_no' => 'required|string'
//     ]);

//     try {
//         $icNo = $request->input('ic_no');
        
//         $accessLogs = DB::table('device_access_logs')
//             ->where('staff_no', $icNo)
//             ->orderBy('created_at', 'asc')
//             ->get();
        
//         if ($accessLogs->isEmpty()) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'No access logs found'
//             ]);
//         }

//         Log::info("TURNSTILE DEBUG: Total logs found: " . $accessLogs->count());
        
//         // ✅ FIXED: TURNSTILE TYPE DETECTION - BETTER APPROACH
//         $turnstileTypes = [];
//         foreach ($accessLogs as $log) {
//             // Check if location contains Turnstile (case insensitive)
//             $isTurnstile = stripos($log->location_name ?? '', 'turnstile') !== false;
            
//             if ($isTurnstile) {
//                 // 🔥 CRITICAL FIX: Directly check device_location_assigns
//                 $deviceLocationAssign = DB::table('device_location_assigns as dla')
//                     ->join('device_connections as dc', 'dc.id', '=', 'dla.device_id')
//                     ->join('vendor_locations as vl', 'vl.id', '=', 'dla.location_id')
//                     ->where('dc.device_id', $log->device_id)
//                     ->where('vl.name', 'LIKE', '%Turnstile%')
//                     ->first();
                
//                 if ($deviceLocationAssign) {
//                     $isCheckIn = ($deviceLocationAssign->is_type === 'check_in');
//                     $turnstileTypes[$log->id] = $isCheckIn;
//                     Log::info("TURNSTILE DEBUG: Log ID {$log->id} => " . ($isCheckIn ? 'IN' : 'OUT') . " (from device_location_assigns)");
//                 } else {
//                     // Fallback: Agar assign nahi mila, toh check karo ki kya check_out exists
//                     $checkOutExists = DB::table('device_location_assigns as dla')
//                         ->join('vendor_locations as vl', 'vl.id', '=', 'dla.location_id')
//                         ->where('vl.name', 'LIKE', '%Turnstile%')
//                         ->where('dla.is_type', 'check_out')
//                         ->exists();
                    
//                     // Agar check_out exists, toh isko OUT maano, warna IN
//                     $turnstileTypes[$log->id] = !$checkOutExists;
//                     Log::info("TURNSTILE DEBUG: Log ID {$log->id} => " . (!$checkOutExists ? 'IN' : 'OUT') . " (fallback)");
//                 }
//             }
//         }
        
//         Log::info("TURNSTILE DEBUG: Final turnstileTypes: " . json_encode($turnstileTypes));
        
//         // Timeline banao
//         $locationTimeline = [];
        
//         for ($i = 1; $i < count($accessLogs); $i++) {
//             $currentLog = $accessLogs[$i];
//             $previousLog = $accessLogs[$i - 1];
            
//             $timeSpent = strtotime($currentLog->created_at) - strtotime($previousLog->created_at);
            
//             // FROM location check
//             $fromIsCheckIn = null;
//             if (stripos($previousLog->location_name ?? '', 'turnstile') !== false) {
//                 $fromIsCheckIn = $turnstileTypes[$previousLog->id] ?? false;
//             }
            
//             // TO location check
//             $toIsCheckIn = null;
//             if (stripos($currentLog->location_name ?? '', 'turnstile') !== false) {
//                 $toIsCheckIn = $turnstileTypes[$currentLog->id] ?? false;
//             }
            
//             $locationTimeline[] = [
//                 'from_location' => $previousLog->location_name ?? 'Unknown',
//                 'to_location' => $currentLog->location_name ?? 'Unknown',
//                 'entry_time' => $previousLog->created_at,
//                 'exit_time' => $currentLog->created_at,
//                 'time_spent' => $this->formatTime($timeSpent),
//                 'access_granted' => $currentLog->access_granted ?? 1,
//                 'from_is_check_in' => $fromIsCheckIn,
//                 'to_is_check_in' => $toIsCheckIn
//             ];
//         }

        
//         // Debug: Check if values are set
//         foreach ($locationTimeline as $index => $item) {
//             if ($item['from_is_check_in'] !== null || $item['to_is_check_in'] !== null) {
//                 Log::info("TURNSTILE DEBUG: Timeline item $index has values - FROM: " . 
//                          ($item['from_is_check_in'] === null ? 'null' : 
//                           ($item['from_is_check_in'] ? 'true' : 'false')) . 
//                          ", TO: " . ($item['to_is_check_in'] === null ? 'null' : 
//                           ($item['to_is_check_in'] ? 'true' : 'false')));
//             }
//         }
        
//         // Date-wise grouping (rest of your code remains same)
//         $visitDates = [];
//         $logsByDate = [];
//         $timelineByDate = [];
        
//         foreach ($accessLogs as $log) {
//             $dateKey = date('d-M-Y', strtotime($log->created_at));
//             if (!in_array($dateKey, $visitDates)) {
//                 $visitDates[] = $dateKey;
//             }
//             $logsByDate[$dateKey][] = $log;
//         }
        
//         foreach ($locationTimeline as $item) {
//             $dateKey = date('d-M-Y', strtotime($item['entry_time']));
//             $timelineByDate[$dateKey][] = $item;
//         }
        
//         usort($visitDates, function($a, $b) {
//             return strtotime($b) - strtotime($a);
//         });
        
//         $currentStatus = $this->getCurrentStatus($icNo);
//         $totalTimeSpent = $this->calculateTotalTimeSpent($accessLogs);
//         $summary = $this->generateSummary($icNo, $accessLogs);
        
//         return response()->json([
//             'success' => true,
//             'data' => [
//                 'dates' => $visitDates,
//                 'logs_by_date' => $logsByDate,
//                 'timeline_by_date' => $timelineByDate,
//                 'current_status' => $currentStatus,  
//                 'total_time_spent' => $totalTimeSpent, 
//                 'summary' => $summary, 
//                 'all_access_logs' => $accessLogs,
//                 'all_location_timeline' => $locationTimeline
//             ]
//         ]);
        
//     } catch (\Exception $e) {
//         Log::error('Error in getVisitorChronology: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'message' => 'Error: ' . $e->getMessage()
//         ]);
//     }
// }

/**
 * Get visitor chronology with timestamps in Malaysia timezone
 */
public function getVisitorChronology(Request $request)
{
    $request->validate([
        'ic_no' => 'required|string'
    ]);

    try {
        $icNo = $request->input('ic_no');
        
        // 1. Fetch all access logs for this visitor
        $accessLogs = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->orderBy('created_at', 'asc')
            ->get();
        
        if ($accessLogs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No access logs found'
            ]);
        }

        Log::info("LOCATION TYPE DEBUG: Total logs found: " . $accessLogs->count());
        
        // 2. Convert ALL timestamps to Malaysia timezone (Asia/Kuala_Lumpur)
        foreach ($accessLogs as $log) {
            $log->created_at = Carbon::parse($log->created_at)
                ->setTimezone(self::MALAYSIA_TIMEZONE)
                ->toDateTimeString();  // format: "2026-04-28 20:21:25"
        }
        
        // 3. Detect location type (IN/OUT) for each log based on device_location_assigns
        $locationTypes = [];
        foreach ($accessLogs as $log) {
            $locationType = null; // null = unknown, true = check_in, false = check_out
            
            // Get location type from device_location_assigns via device_connections
            if ($log->device_id) {
                $deviceConnection = DB::table('device_connections')
                    ->where('device_id', $log->device_id)
                    ->first();
                
                if ($deviceConnection) {
                    $deviceLocationAssign = DB::table('device_location_assigns')
                        ->where('device_id', $deviceConnection->id)
                        ->first();
                    
                    if ($deviceLocationAssign) {
                        $locationType = ($deviceLocationAssign->is_type === 'check_in');
                        Log::info("LOCATION TYPE DEBUG: Log ID {$log->id} => " . ($locationType ? 'IN' : 'OUT') . " (from device_location_assigns)");
                    }
                }
            }
            
            // Fallback: try by location name if device_id method failed
            if ($locationType === null && $log->location_name) {
                $location = DB::table('vendor_locations')
                    ->where('name', $log->location_name)
                    ->orWhere('name', 'like', '%' . $log->location_name . '%')
                    ->first();
                
                if ($location) {
                    $deviceLocationAssign = DB::table('device_location_assigns')
                        ->where('location_id', $location->id)
                        ->first();
                    
                    if ($deviceLocationAssign) {
                        $locationType = ($deviceLocationAssign->is_type === 'check_in');
                        Log::info("LOCATION TYPE DEBUG: Log ID {$log->id} => " . ($locationType ? 'IN' : 'OUT') . " (by location name fallback)");
                    }
                }
            }
            
            $locationTypes[$log->id] = $locationType;
        }
        
        Log::info("LOCATION TYPE DEBUG: Final locationTypes: " . json_encode($locationTypes));
        
        // 4. Build location timeline (movement between consecutive logs)
        $locationTimeline = [];
        
        for ($i = 1; $i < count($accessLogs); $i++) {
            $currentLog = $accessLogs[$i];
            $previousLog = $accessLogs[$i - 1];
            
            // Calculate time spent between the two logs
            $timeSpent = strtotime($currentLog->created_at) - strtotime($previousLog->created_at);
            
            // Determine IN/OUT for FROM and TO locations
            $fromIsCheckIn = $locationTypes[$previousLog->id] ?? null;
            $toIsCheckIn   = $locationTypes[$currentLog->id] ?? null;
            
            $locationTimeline[] = [
                'from_location'      => $previousLog->location_name ?? 'Unknown',
                'to_location'        => $currentLog->location_name ?? 'Unknown',
                'entry_time'         => $previousLog->created_at,  // already Malaysia time
                'exit_time'          => $currentLog->created_at,    // already Malaysia time
                'time_spent'         => $this->formatTime($timeSpent),
                'access_granted'     => $currentLog->access_granted ?? 1,
                'from_is_check_in'   => $fromIsCheckIn,
                'to_is_check_in'     => $toIsCheckIn
            ];
        }
        
        // 5. Group logs and timeline by date (using Malaysia date from converted timestamps)
        $visitDates = [];
        $logsByDate = [];
        $timelineByDate = [];
        
        foreach ($accessLogs as $log) {
            $dateKey = Carbon::parse($log->created_at)->format('d-M-Y');
            if (!in_array($dateKey, $visitDates)) {
                $visitDates[] = $dateKey;
            }
            $logsByDate[$dateKey][] = $log;
        }
        
        foreach ($locationTimeline as $item) {
            $dateKey = Carbon::parse($item['entry_time'])->format('d-M-Y');
            $timelineByDate[$dateKey][] = $item;
        }
        
        // Latest date first
        usort($visitDates, function($a, $b) {
            return strtotime($b) - strtotime($a);
        });
        
        // 6. Additional summary data
        $currentStatus   = $this->getCurrentStatus($icNo);
        $totalTimeSpent  = $this->calculateTotalTimeSpent($accessLogs);
        $summary         = $this->generateSummary($icNo, $accessLogs);
        
        return response()->json([
            'success' => true,
            'data' => [
                'dates'                => $visitDates,
                'logs_by_date'         => $logsByDate,
                'timeline_by_date'     => $timelineByDate,
                'current_status'       => $currentStatus,
                'total_time_spent'     => $totalTimeSpent,
                'summary'              => $summary,
                'all_access_logs'      => $accessLogs,
                'all_location_timeline'=> $locationTimeline
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in getVisitorChronology: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}



private function formatTime($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'total_seconds' => $seconds,
        'formatted' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds)
    ];
}


private function formatSeconds($seconds)
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

private function calculateTotalTimeFromSessions($staffNo)
{
    $visitSessions = $this->getVisitSessions($staffNo);
    $totalSeconds = 0;
    
    Log::info("Calculating total time from " . count($visitSessions) . " sessions");
    
    foreach ($visitSessions as $session) {
        $checkInTime = strtotime($session['check_in_time']);
        $checkOutTime = $session['check_out_time'] ? 
            strtotime($session['check_out_time']) : time();
        
        $sessionSeconds = $checkOutTime - $checkInTime;
        
        if ($sessionSeconds > 0) {
            $totalSeconds += $sessionSeconds;
            
            Log::info("Session: " . date('Y-m-d H:i:s', $checkInTime) . 
                     " to " . ($session['check_out_time'] ? 
                               date('Y-m-d H:i:s', $checkOutTime) : 'NOW') . 
                     " = " . $this->formatSeconds($sessionSeconds));
        } else {
            Log::warning("Invalid session time: " . $sessionSeconds . " seconds");
        }
    }
    
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    
    return [
        'total_seconds' => $totalSeconds,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'formatted' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
        'total_sessions' => count($visitSessions)
    ];
}

     
private function generateSummary($icNo, $accessLogs)
{
    $uniqueLocations = collect($accessLogs)->pluck('location_name')->unique()->values();
    
    $firstLog = collect($accessLogs)->first();
    $lastLog = collect($accessLogs)->last();
    
    return [
        'total_visits' => count($accessLogs),
        'unique_locations_visited' => count($uniqueLocations),
        'locations_list' => $uniqueLocations,
        // 'first_visit' => $firstLog ? $firstLog->created_at : null,
        // 'last_visit' => $lastLog ? $lastLog->created_at : null,
        'first_visit' => $firstLog ? Carbon::parse($firstLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE)->toDateTimeString() : null,
        'last_visit' => $lastLog ? Carbon::parse($lastLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE)->toDateTimeString() : null,
        'successful_accesses' => collect($accessLogs)->where('access_granted', 1)->count(),
        'failed_accesses' => collect($accessLogs)->where('access_granted', 0)->count(),
        'unique_dates' => count(array_unique(
            collect($accessLogs)->map(function($log) {
                return date('d-M-Y', strtotime($log->created_at));
            })->toArray()
        ))
    ];
}

    public function getChronology(Request $request)
    {
        $staffNo = $request->input('staff_no');
        $icNo = $request->input('ic_no');
        
        $searchTerm = $icNo ?: $staffNo;
        
        if (!$searchTerm) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide IC No or Staff No'
            ]);
        }
        
        try {
            $accessLogs = DB::table('device_access_logs')
                ->where('staff_no', $searchTerm) 
                ->where('access_granted', 1) 
                ->orderBy('created_at', 'asc')
                ->get();

            $dates = [];
            $logsByDate = [];
            $timelineByDate = [];
            
            foreach ($accessLogs as $log) {
                $date = \Carbon\Carbon::parse($log->created_at)->format('d-M-Y');
                
                if (!in_array($date, $dates)) {
                    $dates[] = $date;
                }
                
                // Group logs by date
                $logsByDate[$date][] = $log;
            }
            
            foreach ($dates as $date) {
                $dateLogs = $logsByDate[$date] ?? [];
                $timelineByDate[$date] = $this->generateTimelineForDate($dateLogs);
            }
            
            $summary = [
                'total_visits' => $accessLogs->count(),
                'successful_accesses' => $accessLogs->where('access_granted', 1)->count(),
                'unique_dates' => count($dates)
            ];
            
            $currentStatus = $this->getCurrentStatus($searchTerm);
            
            $totalTimeSpent = $this->calculateTotalTimeSpent($accessLogs);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'dates' => $dates,
                    'summary' => $summary,
                    'current_status' => $currentStatus,
                    'total_time_spent' => $totalTimeSpent,
                    'logs_by_date' => $logsByDate,
                    'timeline_by_date' => $timelineByDate,
                    'all_access_logs' => $accessLogs,
                    'all_location_timeline' => $this->generateCompleteTimeline($accessLogs)
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching chronology: ' . $e->getMessage()
            ]);
        }
    }

    private function generateTimelineForDate($logs)
    {
        $timeline = [];
        
        $sortedLogs = collect($logs)->sortBy('created_at');
        
        for ($i = 1; $i < $sortedLogs->count(); $i++) {
            $currentLog = $sortedLogs->get($i);
            $previousLog = $sortedLogs->get($i - 1);
            
            // Check if both logs are valid
            if (!$currentLog || !$previousLog) {
                continue;
            }
            
            $timeSpent = \Carbon\Carbon::parse($currentLog->created_at)
                ->diff(\Carbon\Carbon::parse($previousLog->created_at));
            
            $timeline[] = [
                'from_location' => $previousLog->location_name,
                'to_location' => $currentLog->location_name,
                'entry_time' => $previousLog->created_at,
                'exit_time' => $currentLog->created_at,
                'time_spent' => [
                    'hours' => $timeSpent->h,
                    'minutes' => $timeSpent->i,
                    'seconds' => $timeSpent->s,
                    'total_seconds' => $timeSpent->h * 3600 + $timeSpent->i * 60 + $timeSpent->s
                ],
                'access_granted' => $currentLog->access_granted
            ];
        }
        
        return $timeline;
    }

private function generateCompleteTimeline($allLogs)
{
    $timeline = [];
    
    $sortedLogs = collect($allLogs)->sortBy('created_at');
    
    // Pehle saare turnstile logs ke types identify karo
    $turnstileTypes = [];
    foreach ($sortedLogs as $log) {
        if (strpos(strtolower($log->location_name ?? ''), '13.TURNSTILE') !== false) {
            $turnstileTypes[$log->id] = $this->isTurnstileCheckIn(
                $log->location_name, 
                $log->created_at, 
                $log->staff_no
            );
        }
    }
    
    for ($i = 1; $i < $sortedLogs->count(); $i++) {
        $currentLog = $sortedLogs->get($i);
        $previousLog = $sortedLogs->get($i - 1);
        
        if (!$currentLog || !$previousLog) {
            continue;
        }
        
        // Calculate time gap
        $timeGap = \Carbon\Carbon::parse($currentLog->created_at)
            ->diffInMinutes(\Carbon\Carbon::parse($previousLog->created_at));
        
        if ($timeGap > 120) {
            continue;
        }
        
        $timeSpent = \Carbon\Carbon::parse($currentLog->created_at)
            ->diff(\Carbon\Carbon::parse($previousLog->created_at));
        
        // ✅ Check turnstile types
        $fromIsCheckIn = null;
        if (strpos(strtolower($previousLog->location_name ?? ''), '13.TURNSTILE') !== false) {
            $fromIsCheckIn = $turnstileTypes[$previousLog->id] ?? null;
        }
        
        $toIsCheckIn = null;
        if (strpos(strtolower($currentLog->location_name ?? ''), '13.TURNSTILE') !== false) {
            $toIsCheckIn = $turnstileTypes[$currentLog->id] ?? null;
        }
        
        $timeline[] = [
            'from_location' => $previousLog->location_name,
            'to_location' => $currentLog->location_name,
            'entry_time' => $previousLog->created_at,
            'exit_time' => $currentLog->created_at,
            'time_spent' => [
                'hours' => $timeSpent->h,
                'minutes' => $timeSpent->i,
                'seconds' => $timeSpent->s
            ],
            'access_granted' => $currentLog->access_granted,
            'time_gap_minutes' => $timeGap,
            // ✅ NEW: Turnstile type flags
            'from_is_check_in' => $fromIsCheckIn,
            'to_is_check_in' => $toIsCheckIn
        ];
    }
    
    return $timeline;
}

private function getCurrentStatus($icNo)
{
    $lastLog = DB::table('device_access_logs')
        ->where('staff_no', $icNo)
        ->orderBy('created_at', 'desc')
        ->first();

    if (!$lastLog) {
        return [
            'status' => 'never_visited',
            'message' => 'Never visited the building'
        ];
    }

    // Check if the last location is the exit turnstile (case‑insensitive)
    if (stripos($lastLog->location_name, '13.TURNSTILE') !== false) {
        return [
            'status' => 'out_of_building',
            'message' => 'Currently out of building'
        ];
    }

    // Any other location means the visitor is still inside
    return [
        'status' => 'in_building',
        'message' => 'Currently in building (last seen at: ' . $lastLog->location_name . ')'
    ];
}

private function calculateTotalTimeSpent($logs)
{
    if ($logs->count() < 2) {
        return [
            'total_seconds' => 0,
            'formatted' => '0h 0m 0s'
        ];
    }
    
    $sortedLogs = collect($logs)->sortBy('created_at');
    $totalSeconds = 0;
    
    for ($i = 1; $i < $sortedLogs->count(); $i++) {
        $current = $sortedLogs->get($i);
        $previous = $sortedLogs->get($i - 1);
        
        $timeDiff = \Carbon\Carbon::parse($current->created_at)
            ->diffInSeconds(\Carbon\Carbon::parse($previous->created_at));
        
        if ($timeDiff < 14400) { 
            $totalSeconds += $timeDiff;
        }
    }
    
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    
    return [
        'total_seconds' => $totalSeconds,
        'formatted' => "{$hours}h {$minutes}m {$seconds}s"
    ];
}


// private function getVisitTimings($identifier)
// {
//     try {
//         Log::info("Fetching visit timings for identifier: $identifier");
        
//         // ✅ CHANGE 1: Get ALL access logs for first/last access
//         $allAccessLogs = DB::table('device_access_logs')
//             ->where('staff_no', $identifier)
//             ->where('access_granted', 1)
//             ->orderBy('created_at', 'asc')
//             ->get(['id', 'created_at', 'location_name', 'device_id']);
        
//         Log::info("Total access logs found: " . $allAccessLogs->count());
        
//         if ($allAccessLogs->isEmpty()) {
//             return [
//                 'visitFrom' => null,
//                 'visitTo' => null,
//                 'duration' => 'No visits found',
//                 'isCurrentlyInBuilding' => false,
//                 'visitSessions' => []
//             ];
//         }
        
//         $firstLog = $allAccessLogs->first();
//         $lastLog = $allAccessLogs->last();
        
//         // $visitFrom = $firstLog->created_at;
//         // $visitTo = $lastLog->created_at;
        
//         // Convert UTC to Malaysia timezone
//         $visitFrom = Carbon::parse($firstLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE);
//         $visitTo = Carbon::parse($lastLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE);

//         // Calculate duration
//         $durationSeconds = strtotime($visitTo) - strtotime($visitFrom);
//         $durationFormatted = $this->formatDuration($durationSeconds);
        
//         $turnstileLogs = DB::table('device_access_logs')
//             ->where('staff_no', $identifier)
//             ->where('location_name', 'like', '%13.TURNSTILE%')
//             ->where('access_granted', 1)
//             ->orderBy('created_at', 'desc')
//             ->get(['id', 'created_at', 'location_name', 'device_id']);
        
//         $isCurrentlyInBuilding = false;
        
//         if ($turnstileLogs->isNotEmpty()) {
//             $lastTurnstileLog = $turnstileLogs->first();
//             $isCheckIn = $this->isTurnstileCheckIn(
//                 $lastTurnstileLog->location_name, 
//                 $lastTurnstileLog->created_at, 
//                 $identifier
//             );
            
//             // If last turnstile was check-in, visitor is still in building
//             $isCurrentlyInBuilding = $isCheckIn;
            
//             Log::info("Last turnstile log: " . $lastTurnstileLog->created_at . 
//                      ", isCheckIn: " . ($isCheckIn ? 'YES' : 'NO') . 
//                      ", Currently in building: " . ($isCurrentlyInBuilding ? 'YES' : 'NO'));
//         } else {
//             // No turnstile logs found
//             Log::info("No turnstile logs found for $identifier");
//         }
        
//         $result = [
//             'visitFrom' => $visitFrom,
//             'visitTo' => $visitTo,
//             'duration' => $durationFormatted,
//             'isCurrentlyInBuilding' => $isCurrentlyInBuilding,
//             'firstLocation' => $firstLog->location_name,
//             'lastLocation' => $lastLog->location_name,
//             'totalLogs' => $allAccessLogs->count(),
//             'firstLogTime' => $visitFrom,
//             'lastLogTime' => $visitTo
//         ];
        
//         Log::info("Visit timings result: ", $result);
//         return $result;
        
//     } catch (\Exception $e) {
//         Log::error('Error in getVisitTimings: ' . $e->getMessage());
//         return [
//             'visitFrom' => null,
//             'visitTo' => null,
//             'duration' => 'Error fetching data',
//             'isCurrentlyInBuilding' => false,
//             'visitSessions' => []
//         ];
//     }
// }

private function getVisitTimings($identifier)
{
    try {
        Log::info("Fetching visit timings for identifier: $identifier");
        
        // Get ALL access logs (successful + failed? Only successful for timing)
        $allAccessLogs = DB::table('device_access_logs')
            ->where('staff_no', $identifier)
            ->where('access_granted', 1)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'created_at', 'location_name', 'device_id']);
        
        Log::info("Total access logs found: " . $allAccessLogs->count());
        
        if ($allAccessLogs->isEmpty()) {
            return [
                'visitFrom' => null,
                'visitTo' => null,
                'duration' => 'No visits found',
                'isCurrentlyInBuilding' => false,
                'visitSessions' => []
            ];
        }
        
        $firstLog = $allAccessLogs->first();
        $lastLog = $allAccessLogs->last();
        
        // Convert to Malaysia timezone
        $visitFrom = Carbon::parse($firstLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE);
        $visitTo   = Carbon::parse($lastLog->created_at)->setTimezone(self::MALAYSIA_TIMEZONE);
        
        // Duration = first scan to last scan
        $durationSeconds = strtotime($visitTo) - strtotime($visitFrom);
        $durationFormatted = $this->formatDuration($durationSeconds);
        
        // ✅ FIX: Determine if visitor is still inside using the SAME rule as getCurrentStatus()
        // If the last location is "13.TURNSTILE" (exit turnstile) → OUT, otherwise IN.
        $isCurrentlyInBuilding = true;
        if (stripos($lastLog->location_name, '13.TURNSTILE') !== false) {
            $isCurrentlyInBuilding = false;
        }
        
        Log::info("Visit timings result: lastLocation={$lastLog->location_name}, isCurrentlyInBuilding=" . ($isCurrentlyInBuilding ? 'YES' : 'NO'));
        
        return [
            'visitFrom' => $visitFrom,
            'visitTo' => $visitTo,
            'duration' => $durationFormatted,
            'isCurrentlyInBuilding' => $isCurrentlyInBuilding,
            'firstLocation' => $firstLog->location_name,
            'lastLocation' => $lastLog->location_name,
            'totalLogs' => $allAccessLogs->count(),
            'firstLogTime' => $visitFrom,
            'lastLogTime' => $visitTo
        ];
        
    } catch (\Exception $e) {
        Log::error('Error in getVisitTimings: ' . $e->getMessage());
        return [
            'visitFrom' => null,
            'visitTo' => null,
            'duration' => 'Error fetching data',
            'isCurrentlyInBuilding' => false,
            'visitSessions' => []
        ];
    }
}


private function formatDuration($seconds)
{
    if ($seconds <= 0) return '0 seconds';
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    $parts = [];
    if ($hours > 0) $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    if ($seconds > 0) $parts[] = $seconds . ' second' . ($seconds > 1 ? 's' : '');
    
    return implode(' ', $parts);
}


    public function downloadChronologyPdf(Request $request)
    {
        try {
            $pdfData = json_decode($request->input('pdf_data'), true);
            
            if (!$pdfData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PDF data'
                ], 400);
            }
            
            $visitorInfo = $pdfData['visitor'] ?? [];
            $chronologyData = $pdfData['chronology'] ?? [];
            $downloadType = $pdfData['downloadType'] ?? 'full';
            $selectedDate = $pdfData['selectedDate'] ?? null;
            
            $filename = 'chronology_' . ($visitorInfo['icNo'] ?? 'unknown') . '_' . date('Ymd_His') . '.pdf';

            // Group full timeline date-wise
            if (isset($chronologyData['all_location_timeline'])) {
                $grouped = collect($chronologyData['all_location_timeline'])
                    ->sortBy('entry_time')
                    ->groupBy(function ($item) {
                        return \Carbon\Carbon::parse($item['entry_time'])->format('d-M-Y');
                    })
                    ->toArray();

                $chronologyData['grouped_timeline'] = $grouped;
            }

            $data = [
                'visitor' => $visitorInfo,
                'chronology' => $chronologyData,
                'downloadType' => $downloadType,
                'selectedDate' => $selectedDate,
                'generatedAt' => $pdfData['generatedAt'] ?? now()->toDateTimeString()
            ];
            
            $pdf = Pdf::loadView('pdf.visitor_chronology', $data);
            
            $pdf->setPaper('A4', 'landscape');
            
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('PDF generation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error generating PDF: ' . $e->getMessage()
            ], 500);
        }
    }

}

