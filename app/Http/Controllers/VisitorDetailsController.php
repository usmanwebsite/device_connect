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

class VisitorDetailsController extends Controller
{
    protected $menuService;
    protected $javaBaseUrl;
    
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

    /**
     * Display the visitor details page
     */
    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $javaBaseUrl = $this->javaBaseUrl;
        
        return view('visitorDetails&Chronology.visitorDetails&Chronology', compact('angularMenu'));
    }

    /**
     * Search visitor by staffNo or icNo using new Java API
     */

public function search(Request $request)
{
    $request->validate([
        'search_term' => 'required|string|min:1',
    ]);

    $searchTerm = $request->input('search_term');
    $searchType = $request->input('search_type');

    try {
        $response = $this->callJavaApi($searchTerm, $searchType);
        
        // Debug logging
        Log::info('Java API Response in Controller:', ['response' => $response]);

        // Check if response has data - مختلف scenarios کو handle کریں
        $data = [];
        
        if ($response && isset($response['status']) && $response['status'] === 'OK') {
            // Case 1: Response میں data key موجود ہے
            if (isset($response['data'])) {
                $data = $response['data'];
                
                // Check if data is string, convert to array
                if (is_string($data)) {
                    Log::warning('Data is string, attempting to decode');
                    $decodedData = json_decode($data, true);
                    if ($decodedData !== null) {
                        $data = $decodedData;
                    } else {
                        // If it's a simple string, wrap it in array
                        $data = [['info' => $data]];
                    }
                }
            } 
            // Case 2: Response ہی data ہے
            else {
                $data = $response;
            }
        } 
        // Case 3: Response میں data array کی صورت میں براہ راست موجود ہے
        elseif (is_array($response) && !isset($response['status'])) {
            $data = $response;
        }
        
        Log::info('Processed data for display:', ['data' => $data, 'count' => count($data)]);
        
        // اگر ڈیٹا مل گیا ہے
        if (!empty($data)) {
            // Convert single object to array if needed
            if (!is_array($data) || isset($data['staffNo']) || isset($data['icNo'])) {
                $data = [$data];
            }
            
            // ہر وزیٹر کے لیے VisitFrom اور VisitTo ڈیٹا fetch کریں
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


// private function callJavaApi($searchTerm, $searchType)
// {
//     try {
//         $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
        
//         // Get token from session
//         $token = session()->get('java_backend_token')
//                ?? session()->get('java_auth_token');
        
//         if (!$token) {
//             Log::error('No authentication token found in session');
//             return [
//                 'status' => 'error',
//                 'message' => 'Authentication token not found. Please login again.',
//                 'data' => null
//             ];
//         }
        
//         // Build URL based on search type
//         if ($searchType === 'icNo') {
//             $url = $javaBaseUrl . "/api/vendorpass/get-visitor-details?icNo=" . urlencode($searchTerm);
//         } else {
//             $url = $javaBaseUrl . "/api/vendorpass/get-visitor-details?staffNo=" . urlencode($searchTerm);
//         }

//         Log::info('Calling Java API:', [
//             'url' => $url,
//             'searchTerm' => $searchTerm,
//             'searchType' => $searchType
//         ]);

//         // Make API call with authentication token
//         $response = Http::withHeaders([
//             'x-auth-token' => $token,
//             'Accept' => 'application/json',
//         ])->timeout(30)->get($url);
        
//         Log::info('Java API Response Status:', ['status' => $response->status()]);
        
//         if ($response->successful()) {
//             $data = $response->json();
            
//             Log::info('Java API Response Data:', [
//                 'status' => $data['status'] ?? 'not_set',
//                 'message' => $data['message'] ?? 'not_set',
//                 'has_data' => isset($data['data']),
//                 'data_count' => is_array($data['data'] ?? null) ? count($data['data']) : 'N/A'
//             ]);

//             return $data;
//         }

//         Log::error('Java API Error:', [
//             'status' => $response->status(),
//             'body' => $response->body()
//         ]);

//         return [
//             'status' => 'error',
//             'message' => 'API request failed with status: ' . $response->status(),
//             'data' => null
//         ];

//     } catch (\Exception $e) {
//         Log::error('Java API Call Exception: ' . $e->getMessage());
        
//         return [
//             'status' => 'error',
//             'message' => 'Error connecting to Java API: ' . $e->getMessage(),
//             'data' => null
//         ];
//     }
// }


/**
 * Call Java API based on search type
 */
// private function callJavaApi($searchTerm, $searchType)
// {
//     try {
//         // ✅ Yeh wala javaBaseUrl use karein
//         $javaBaseUrl = $this->javaBaseUrl;
//         $cardStatus = false;
        
//         // ✅ Debug log add karein
//         Log::info('=== VisitorDetailsController - Java API Call ===');
//         Log::info('Java Base URL being used: ' . $javaBaseUrl);
//         Log::info('Search Term: ' . $searchTerm);
//         Log::info('Search Type: ' . $searchType);
        
//         $params = [];
//         if ($searchType === 'staffNo') {
//             $params['staffNo'] = $searchTerm;
//         } elseif ($searchType === 'icNo') {
//             $params['icNo'] = $searchTerm;
//         } else {
//             $params['staffNo'] = $searchTerm;
//             $params['icNo'] = $searchTerm;
//         }
        
//         $fullUrl = $javaBaseUrl . '/api/vendorpass/get-visitor-details-by-icno-or-staffno';
//         Log::info('Full Java API URL: ' . $fullUrl);
//         Log::info('With params: ', $params);
        
//         // ✅ Session se token lein agar available ho
//         $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
        
//         $headers = [
//             'Accept' => 'application/json',
//             'Content-Type' => 'application/json'
//         ];
        
//         if ($token) {
//             $headers['x-auth-token'] = $token;
//             Log::info('Token added to headers');
//         }
        
//         Log::info('Making Java API call...');
        
//         $response = Http::withHeaders($headers)
//             ->timeout(30)
//             ->retry(2, 100)
//             ->get($fullUrl, $params);
        
//         Log::info('Java API Response Status: ' . $response->status());
//         Log::info('Java API Response Body: ' . $response->body());
        
//         if ($response->successful()) {
//             $data = $response->json();
//             Log::info('Java API Success: Data received');
//             return $data;
//         }
        
//         Log::error('Java API Error: ' . $response->body());
//         return null;
        
//     } catch (\Exception $e) {
//         Log::error('Java API Exception: ' . $e->getMessage());
//         Log::error('Stack trace: ' . $e->getTraceAsString());
//         return null;
//     }
// }

private function isTurnstileCheckIn($locationName, $logTime, $staffNo)
{
    try {
        Log::info("Checking turnstile type for: StaffNo=$staffNo, Location=$locationName, Time=$logTime");
        
        // 1. First get the log record from device_access_logs
        $logRecord = DB::table('device_access_logs')
            ->where('staff_no', $staffNo)
            ->where('location_name', $locationName)
            ->where('created_at', $logTime)
            ->first();
        
        if (!$logRecord) {
            Log::warning("Log record not found for: $staffNo, $locationName, $logTime");
            return false;
        }
        
        // 2. Check if log has device_id column
        if (!isset($logRecord->device_id)) {
            Log::warning("device_id column not found in log record");
            return false;
        }
        
        $deviceIdFromLog = $logRecord->device_id;
        Log::info("Found device_id in log: $deviceIdFromLog");
        
        // 3. Try to get location from vendor_locations
        $location = DB::table('vendor_locations')
            ->where('name', 'like', '%Turnstile%')
            ->orWhere('name', 'like', '%' . $locationName . '%')
            ->first();
        
        if (!$location) {
            Log::warning("Location not found in vendor_locations for: $locationName");
            return false;
        }
        
        Log::info("Found location: ID=" . $location->id . ", Name=" . $location->name);
        
        // 4. Get device_connection by device_id (this is the device serial number)
        $deviceConnection = DB::table('device_connections')
            ->where('device_id', $deviceIdFromLog) // This matches 008825038133, 008825038134
            ->first();
        
        if (!$deviceConnection) {
            Log::warning("No device connection found for device_id: $deviceIdFromLog");
            return false;
        }
        
        Log::info("Found device connection: ID=" . $deviceConnection->id . ", DeviceID=" . $deviceConnection->device_id);
        
        // 5. Check device_location_assigns for is_type
        $deviceLocationAssign = DB::table('device_location_assigns')
            ->where('location_id', $location->id)
            ->where('device_id', $deviceConnection->id) // This uses the id from device_connections table
            ->first();
        
        if ($deviceLocationAssign) {
            Log::info("Found device_location_assign: LocationID=" . $location->id . 
                     ", DeviceID=" . $deviceConnection->id . 
                     ", Type=" . $deviceLocationAssign->is_type);
            
            if ($deviceLocationAssign->is_type === 'check_in') {
                Log::info("This is a CHECK-IN turnstile");
                return true;
            } elseif ($deviceLocationAssign->is_type === 'check_out') {
                Log::info("This is a CHECK-OUT turnstile");
                return false;
            }
        }
        
        Log::warning("No device_location_assign found for location: $locationName and device_id: $deviceIdFromLog");
        return false;
        
    } catch (\Exception $e) {
        Log::error('Error checking turnstile type: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return false;
    }
}

private function getVisitSessions($staffNo)
{
    Log::info("Getting visit sessions for staff: $staffNo");
    
    // Get all turnstile logs in chronological order
    $turnstileLogs = DB::table('device_access_logs')
        ->where('staff_no', $staffNo)
        ->where('location_name', 'like', '%Turnstile%')
        ->where('access_granted', 1)
        ->orderBy('created_at', 'asc')
        ->select('id', 'created_at', 'location_name', 'device_id', 'access_granted')
        ->get();
    
    Log::info("Total turnstile logs found: " . $turnstileLogs->count());
    
    $visitSessions = [];
    $currentSession = null;
    
    // Process logs to create visit sessions
    foreach ($turnstileLogs as $log) {
        $isCheckIn = $this->isTurnstileCheckIn($log->location_name, $log->created_at, $staffNo);
        
        if ($isCheckIn && $currentSession === null) {
            // Start a new session
            $currentSession = [
                'check_in_time' => $log->created_at,
                'check_in_log_id' => $log->id,
                'check_in_device' => $log->device_id,
                'check_in_location' => $log->location_name,
                'check_out_time' => null,
                'check_out_log_id' => null,
                'check_out_device' => null,
                'check_out_location' => null,
                'visit_date' => date('Y-m-d', strtotime($log->created_at)),
                'formatted_date' => date('d-M-Y', strtotime($log->created_at))
            ];
            Log::info("Started new session at: " . $log->created_at);
            
        } elseif (!$isCheckIn && $currentSession !== null) {
            // End current session
            $currentSession['check_out_time'] = $log->created_at;
            $currentSession['check_out_log_id'] = $log->id;
            $currentSession['check_out_device'] = $log->device_id;
            $currentSession['check_out_location'] = $log->location_name;
            
            // Add session to list
            $visitSessions[] = $currentSession;
            Log::info("Ended session at: " . $log->created_at . 
                     " (Started at: " . $currentSession['check_in_time'] . ")");
            
            // Reset for next session
            $currentSession = null;
        }
    }
    
    // If still in building (no check_out found)
    if ($currentSession !== null) {
        $currentSession['check_out_time'] = null; // Still in building
        $visitSessions[] = $currentSession;
        Log::info("Open session (still in building) started at: " . $currentSession['check_in_time']);
    }
    
    Log::info("Total visit sessions found: " . count($visitSessions));
    return $visitSessions;
}

/**
 * Get dates for visit sessions (latest first)
 */
/**
 * Get dates for visit sessions (latest first)
 */
private function getVisitDates($icNo)
{
    // First get all logs for this IC number
    $logs = DB::table('device_access_logs')
        ->where('staff_no', $icNo) // Remember, staff_no column stores IC number
        ->where('access_granted', 1)
        ->orderBy('created_at', 'desc')
        ->get();
    
    $visitDates = [];
    
    // Extract unique dates from all logs
    foreach ($logs as $log) {
        $dateKey = date('Y-m-d', strtotime($log->created_at));
        $formattedDate = date('d-M-Y', strtotime($log->created_at));
        
        if (!isset($visitDates[$dateKey])) {
            $visitDates[$dateKey] = $formattedDate;
        }
    }
    
    // Sort in descending order (latest first)
    krsort($visitDates);
    
    Log::info("Extracted " . count($visitDates) . " unique dates for IC: $icNo");
    
    return array_values($visitDates);
}

/**
 * Group all logs by visit session
 */
private function groupLogsByVisitSession($accessLogs, $staffNo)
{
    $visitSessions = $this->getVisitSessions($staffNo);
    $groupedLogs = [];
    
    foreach ($visitSessions as $session) {
        $sessionStart = $session['check_in_time'];
        $sessionEnd = $session['check_out_time'];
        $formattedDate = $session['formatted_date'];
        
        // Normalize the date key
        $dateKey = $this->normalizeDateKey($formattedDate);
        
        $sessionLogs = [];
        
        foreach ($accessLogs as $log) {
            $logTime = $log->created_at;
            
            // Check if log falls within this session
            if ($logTime >= $sessionStart) {
                if ($sessionEnd === null || $logTime <= $sessionEnd) {
                    $sessionLogs[] = $log;
                }
            }
        }
        
        if (!empty($sessionLogs)) {
            // Use normalized key
            $groupedLogs[$dateKey] = $sessionLogs;
            Log::info("Session $formattedDate (key: $dateKey) has " . count($sessionLogs) . " logs");
        }
    }
    
    return $groupedLogs;
}

private function normalizeDateKey($dateString)
{
    // Try to parse the date
    try {
        $date = \Carbon\Carbon::createFromFormat('d-M-Y', $dateString);
        return $date->format('d-M-Y'); // Ensure consistent format
    } catch (\Exception $e) {
        // If parsing fails, try alternative formats
        try {
            $date = \Carbon\Carbon::parse($dateString);
            return $date->format('d-M-Y');
        } catch (\Exception $e) {
            // Return original if all parsing fails
            return $dateString;
        }
    }
}

/**
 * Group timeline by visit session
 */
private function groupTimelineByVisitSession($locationTimeline, $staffNo)
{
    $visitSessions = $this->getVisitSessions($staffNo);
    $groupedTimeline = [];
    
    foreach ($visitSessions as $session) {
        $sessionStart = $session['check_in_time'];
        $sessionEnd = $session['check_out_time'];
        $formattedDate = $session['formatted_date'];
        
        // Normalize the date key
        $dateKey = $this->normalizeDateKey($formattedDate);
        
        $sessionTimeline = [];
        
        foreach ($locationTimeline as $item) {
            $itemTime = $item['entry_time'];
            
            // Check if timeline item falls within this session
            if ($itemTime >= $sessionStart) {
                if ($sessionEnd === null || $itemTime <= $sessionEnd) {
                    $sessionTimeline[] = $item;
                }
            }
        }
        
        if (!empty($sessionTimeline)) {
            // Use normalized key
            $groupedTimeline[$dateKey] = $sessionTimeline;
        }
    }
    
    return $groupedTimeline;
}

/**
 * Improved determineTurnstileType function with better logic
 */
private function determineTurnstileType($logId, $staffNo)
{
    try {
        $logRecord = DB::table('device_access_logs')
            ->where('id', $logId)
            ->where('staff_no', $staffNo)
            ->first();
        
        if (!$logRecord) return null;
        
        $deviceId = $logRecord->device_id;
        $locationName = $logRecord->location_name;
        $logTime = $logRecord->created_at;
        
        // Method 1: Check device_location_assigns
        if ($deviceId) {
            $deviceConnection = DB::table('device_connections')
                ->where('device_id', $deviceId)
                ->first();
            
            if ($deviceConnection) {
                $location = DB::table('vendor_locations')
                    ->where('name', 'like', '%Turnstile%')
                    ->orWhere('name', 'like', '%' . $locationName . '%')
                    ->first();
                
                if ($location) {
                    $deviceLocationAssign = DB::table('device_location_assigns')
                        ->where('location_id', $location->id)
                        ->where('device_id', $deviceConnection->id)
                        ->first();
                    
                    if ($deviceLocationAssign) {
                        Log::info("Device assign found: Type = " . $deviceLocationAssign->is_type);
                        return $deviceLocationAssign->is_type === 'check_in';
                    }
                }
            }
        }
        
        // Method 2: Check location name pattern
        $lowerLocation = strtolower($locationName);
        $checkInPatterns = ['checkin', 'entry', 'enter', 'in', 'entrance'];
        $checkOutPatterns = ['checkout', 'exit', 'out', 'egress'];
        
        foreach ($checkInPatterns as $pattern) {
            if (strpos($lowerLocation, $pattern) !== false) {
                Log::info("Location name indicates check_in: $locationName");
                return true;
            }
        }
        
        foreach ($checkOutPatterns as $pattern) {
            if (strpos($lowerLocation, $pattern) !== false) {
                Log::info("Location name indicates check_out: $locationName");
                return false;
            }
        }
        
        // Method 3: Time-based with session logic
        return $this->determineBySessionLogic($logRecord, $staffNo);
        
    } catch (\Exception $e) {
        Log::error('Error in determineTurnstileType: ' . $e->getMessage());
        return null;
    }
}

/**
 * Determine turnstile type based on session logic
 */
private function determineBySessionLogic($logRecord, $staffNo)
{
    $logTime = $logRecord->created_at;
    $date = date('Y-m-d', strtotime($logTime));
    $deviceId = $logRecord->device_id;
    
    // Get all turnstile logs for same device on same day
    $sameDayLogs = DB::table('device_access_logs')
        ->where('staff_no', $staffNo)
        ->where('device_id', $deviceId)
        ->whereDate('created_at', $date)
        ->where('location_name', 'like', '%Turnstile%')
        ->where('access_granted', 1)
        ->orderBy('created_at', 'asc')
        ->get();
    
    if ($sameDayLogs->isEmpty()) {
        Log::info("No other turnstile logs today, assuming check_in");
        return true;
    }
    
    // Find this log in sequence
    $position = 0;
    foreach ($sameDayLogs as $index => $log) {
        if ($log->id == $logRecord->id) {
            $position = $index + 1;
            break;
        }
    }
    
    // Odd positions are check_in, even positions are check_out
    if ($position % 2 == 1) {
        Log::info("Position $position (odd) in sequence - Assuming CHECK-IN");
        return true;
    } else {
        Log::info("Position $position (even) in sequence - Assuming CHECK-OUT");
        return false;
    }
}

/**
 * Updated getVisitorChronology function
 */

public function getVisitorChronology(Request $request)
{
    $request->validate([
        'ic_no' => 'required|string'
    ]);

    try {
        $icNo = $request->input('ic_no');
        
        // ✅ NAYA: Sirf basic logs lo
        $accessLogs = DB::table('device_access_logs')
            ->where('staff_no', $icNo)
            ->orderBy('created_at', 'asc') // Ascending order
            ->get();
        
        if ($accessLogs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No access logs found'
            ]);
        }
        
        // ✅ NAYA: Simple timeline banao (NO TIME FILTER)
        $locationTimeline = [];
        
        for ($i = 1; $i < count($accessLogs); $i++) {
            $currentLog = $accessLogs[$i];
            $previousLog = $accessLogs[$i - 1];
            
            $timeSpent = strtotime($currentLog->created_at) 
                       - strtotime($previousLog->created_at);
            
            $locationTimeline[] = [
                'from_location' => $previousLog->location_name ?? 'Unknown',
                'to_location' => $currentLog->location_name ?? 'Unknown',
                'entry_time' => $previousLog->created_at,
                'exit_time' => $currentLog->created_at,
                'time_spent' => $this->formatTime($timeSpent),
                'access_granted' => $currentLog->access_granted ?? 1
            ];
        }
        
        // ✅ NAYA: Simple date-wise grouping
        $visitDates = [];
        $logsByDate = [];
        $timelineByDate = [];
        
        foreach ($accessLogs as $log) {
            $dateKey = date('d-M-Y', strtotime($log->created_at));
            
            if (!in_array($dateKey, $visitDates)) {
                $visitDates[] = $dateKey;
            }
            
            $logsByDate[$dateKey][] = $log;
        }
        
        // ✅ NAYA: Timeline ko bhi date-wise group karo
        foreach ($locationTimeline as $item) {
            $dateKey = date('d-M-Y', strtotime($item['entry_time']));
            $timelineByDate[$dateKey][] = $item;
        }
        
        // Dates ko latest first karo
        usort($visitDates, function($a, $b) {
            return strtotime($b) - strtotime($a);
        });
        
        // ✅ NAYA: Yeh 3 fields ADD karo
        $currentStatus = $this->getCurrentStatus($icNo);
        $totalTimeSpent = $this->calculateTotalTimeSpent($accessLogs);
        $summary = $this->generateSummary($icNo, $accessLogs);
        
        return response()->json([
            'success' => true,
            'data' => [
                'dates' => $visitDates,
                'logs_by_date' => $logsByDate,
                'timeline_by_date' => $timelineByDate,
                'current_status' => $currentStatus,  
                'total_time_spent' => $totalTimeSpent, 
                'summary' => $summary, 
                'all_access_logs' => $accessLogs,
                'all_location_timeline' => $locationTimeline
            ]
        ]);
        
    } catch (\Exception $e) {
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



private function calculateTimeSinceLastCheckIn($staffNo)
{
    // Get the last check-in time
    $lastCheckIn = DB::table('device_access_logs')
        ->where('staff_no', $staffNo)
        ->where('location_name', 'like', '%Turnstile%')
        ->where('access_granted', 1)
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (!$lastCheckIn) {
        return [
            'total_seconds' => 0,
            'formatted' => '00:00:00',
            'message' => 'No check-in found'
        ];
    }
    
    // Determine if last turnstile was check-in or check-out
    $isCheckIn = $this->determineTurnstileType(
        $lastCheckIn->id, 
        $lastCheckIn->staff_no
    );
    
    if ($isCheckIn) {
        // Visitor is still in building
        $checkInTime = strtotime($lastCheckIn->created_at);
        $currentTime = time();
        $totalSeconds = $currentTime - $checkInTime;
        
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        
        return [
            'total_seconds' => $totalSeconds,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'formatted' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
            'last_check_in' => $lastCheckIn->created_at,
            'current_status' => 'IN_BUILDING',
            'message' => 'Visitor is still in building since ' . 
                        date('d-M-Y H:i:s', $checkInTime)
        ];
    } else {
        // Visitor has checked out
        return [
            'total_seconds' => 0,
            'formatted' => '00:00:00',
            'last_check_out' => $lastCheckIn->created_at,
            'current_status' => 'OUT_OF_BUILDING',
            'message' => 'Visitor has checked out'
        ];
    }
}




    /**
     * Get Turnstile information
     * FIXED: Simplified without device_name column
     */
private function getTurnstileInfo($staffNo)
{
    // Get all Turnstile access logs with device_id
    $accessLogs = DB::table('device_access_logs')
        ->where('staff_no', $staffNo)
        ->where('location_name', 'like', '%Turnstile%')
        ->orderBy('created_at', 'asc')
        ->select('created_at', 'location_name', 'device_id', 'access_granted')
        ->get();
        
    Log::info("getTurnstileInfo: Found " . $accessLogs->count() . " turnstile logs");
    
    $entries = [];
    $exits = [];
    
    foreach ($accessLogs as $log) {
        Log::info("Processing turnstile log: " . $log->location_name . " at " . $log->created_at . 
                 " with device_id: " . ($log->device_id ?? 'NULL'));
        
        // Check if this is check_in or check_out
        $isCheckIn = $this->isTurnstileCheckIn($log->location_name, $log->created_at, $staffNo);
        
        Log::info("Turnstile check for log: " . $log->location_name . " at " . $log->created_at . 
                 " - isCheckIn: " . ($isCheckIn ? 'YES' : 'NO'));
        
        if ($log->access_granted == 1) {
            if ($isCheckIn) {
                $entries[] = [
                    'time' => $log->created_at,
                    'location' => $log->location_name,
                    'type' => 'entry',
                    'access_granted' => $log->access_granted,
                    'date' => date('d-M-Y', strtotime($log->created_at)),
                    'device_id' => $log->device_id
                ];
                Log::info("Added ENTRY: " . $log->created_at . " with device_id: " . $log->device_id);
            } else {
                $exits[] = [
                    'time' => $log->created_at,
                    'location' => $log->location_name,
                    'type' => 'exit',
                    'access_granted' => $log->access_granted,
                    'date' => date('d-M-Y', strtotime($log->created_at)),
                    'device_id' => $log->device_id
                ];
                Log::info("Added EXIT: " . $log->created_at . " with device_id: " . $log->device_id);
            }
        }
    }
    
    Log::info("Total entries: " . count($entries) . ", Total exits: " . count($exits));
    
    return [
        'entries' => $entries,
        'exits' => $exits,
        'total_entries' => count($entries),
        'total_exits' => count($exits),
        'currently_in' => count($entries) > count($exits)
    ];
}

    /**
     * Generate summary of visitor's chronology
     */
     
private function generateSummary($icNo, $accessLogs)
{
    $uniqueLocations = collect($accessLogs)->pluck('location_name')->unique()->values();
    
    $firstLog = collect($accessLogs)->first();
    $lastLog = collect($accessLogs)->last();
    
    return [
        'total_visits' => count($accessLogs),
        'unique_locations_visited' => count($uniqueLocations),
        'locations_list' => $uniqueLocations,
        'first_visit' => $firstLog ? $firstLog->created_at : null,
        'last_visit' => $lastLog ? $lastLog->created_at : null,
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
            'time_gap_minutes' => $timeGap // ڈیبگنگ کے لیے
        ];
    }
    
    return $timeline;
}

   private function getCurrentStatus($icNo)
{
    // Check last access log
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
    
    $lastLocation = strtolower($lastLog->location_name ?? '');
    $isInBuilding = ($lastLocation === 'turnstile') ? false : true;
    
    if ($isInBuilding) {
        return [
            'status' => 'in_building',
            'message' => 'Currently in building (last seen at: ' . $lastLog->location_name . ')'
        ];
    } else {
        return [
            'status' => 'out_of_building',
            'message' => 'Currently out of building'
        ];
    }
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


private function getVisitTimings($identifier)
{
    try {
        Log::info("Fetching visit timings for identifier: $identifier");
        
        // ✅ CHANGE 1: Get ALL access logs for first/last access
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
        
        // ✅ Get FIRST and LAST access from ALL logs
        $firstLog = $allAccessLogs->first();
        $lastLog = $allAccessLogs->last();
        
        $visitFrom = $firstLog->created_at;
        $visitTo = $lastLog->created_at;
        
        // Calculate duration
        $durationSeconds = strtotime($visitTo) - strtotime($visitFrom);
        $durationFormatted = $this->formatDuration($durationSeconds);
        
        // ✅ CHANGE 2: Check turnstile status for "currently in building"
        // Get turnstile logs separately
        $turnstileLogs = DB::table('device_access_logs')
            ->where('staff_no', $identifier)
            ->where('location_name', 'like', '%13.TURNSTILE%')
            ->where('access_granted', 1)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'created_at', 'location_name', 'device_id']);
        
        $isCurrentlyInBuilding = false;
        
        if ($turnstileLogs->isNotEmpty()) {
            $lastTurnstileLog = $turnstileLogs->first();
            $isCheckIn = $this->isTurnstileCheckIn(
                $lastTurnstileLog->location_name, 
                $lastTurnstileLog->created_at, 
                $identifier
            );
            
            // If last turnstile was check-in, visitor is still in building
            $isCurrentlyInBuilding = $isCheckIn;
            
            Log::info("Last turnstile log: " . $lastTurnstileLog->created_at . 
                     ", isCheckIn: " . ($isCheckIn ? 'YES' : 'NO') . 
                     ", Currently in building: " . ($isCurrentlyInBuilding ? 'YES' : 'NO'));
        } else {
            // No turnstile logs found
            Log::info("No turnstile logs found for $identifier");
        }
        
        $result = [
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
        
        Log::info("Visit timings result: ", $result);
        return $result;
        
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

