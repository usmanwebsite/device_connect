<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\MenuService;
use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\VendorLocation;

class VisitorDetailsController extends Controller
{
    protected $menuService;
    protected $javaBaseUrl;
    
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;

        $this->javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
        
        // Check agar .env me value set nahi hai to fallback
        if (!$this->javaBaseUrl) {
            $this->javaBaseUrl = 'http://127.0.0.1:8080'; // Default fallback
        }
        
        Log::info('Java Base URL set to: ' . $this->javaBaseUrl);
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
        // dd($request->all());
        $request->validate([
            'search_term' => 'required|string|min:1',
            'search_type' => 'required|string|in:auto,staffNo,icNo'
        ]);

        $searchTerm = $request->input('search_term');
        $searchType = $request->input('search_type');
        
        try {
            // Call Java API based on search type
            $response = $this->callJavaApi($searchTerm, $searchType);
            
            if ($response && isset($response['status']) && $response['status'] === 'success') {
                $data = $response['data'] ?? [];
                
                // Check if data is single object or array
                if (!is_array($data) || isset($data['staffNo'])) {
                    // Single object - convert to array
                    $data = [$data];
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $response['message'] ?? 'Visitor details retrieved successfully',
                    'data' => $data
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => $response['message'] ?? 'Visitor not found with the provided search term'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error in visitor search: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching visitor details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Call Java API based on search type
     */
    private function callJavaApi($searchTerm, $searchType)
    {
        try {
            $javaBaseUrl = $this->javaBaseUrl;
            
            $params = [];
            if ($searchType === 'staffNo') {
                $params['staffNo'] = $searchTerm;
            } elseif ($searchType === 'icNo') {
                $params['icNo'] = $searchTerm;
            } else {
                $params['staffNo'] = $searchTerm;
                $params['icNo'] = $searchTerm;
            }
            
            Log::info('Calling Java API: ' . $javaBaseUrl . '/api/vendorpass/get-visitor-details-by-icno-or-staffno');
            Log::info('With params: ', $params);
            
            $response = Http::timeout(30)
                ->retry(2, 100)
                ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details-by-icno-or-staffno', $params);
            
            Log::info('Java API Response Status: ' . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                Log::info('Java API Success: ' . json_encode($data));
                return $data;
            }
            
            Log::error('Java API Error: ' . $response->body());
            return null;
            
        } catch (\Exception $e) {
            Log::error('Java API Exception: ' . $e->getMessage());
            return null;
        }
    }

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
        'staff_no' => 'nullable|string',
        'ic_no' => 'required|string'
    ]);

    try {

    

        $staffNo = $request->input('staff_no');
        $icNo = $request->input('ic_no');
        
        Log::info("Getting chronology for: StaffNo=$staffNo, ICNo=$icNo");
        
        // 1. Get all access logs for this visitor
        $accessLogs = $this->getAccessLogs($icNo);
        Log::info("Total access logs: " . $accessLogs->count());
        
        // 2. Get location timeline
        $locationTimeline = $this->getLocationTimeline($accessLogs);
        Log::info("Location timeline items: " . count($locationTimeline));
        
        // 3. Get visit dates
        $visitDates = $this->getVisitDates($icNo);
        Log::info("Visit dates found: " . json_encode($visitDates));
        
        // 4. Group logs by visit session with normalized keys
        $logsByVisitDate = $this->groupLogsByVisitSession($accessLogs, $icNo);
        
        // 5. Group timeline by visit session with normalized keys
        $timelineByVisitDate = $this->groupTimelineByVisitSession($locationTimeline, $icNo);
        
        // 6. Also ensure dates array has normalized keys
        $normalizedDates = array_map([$this, 'normalizeDateKey'], $visitDates);
        
        // 7. Get other data
        $visitSessions = $this->getVisitSessions($icNo);
        $currentStatus = $this->getCurrentStatus($icNo);
        $totalTimeSpent = $this->calculateTotalTimeFromSessions($icNo);
        $turnstileInfo = $this->getTurnstileInfo($icNo);
        $summary = $this->generateSummary($icNo, $accessLogs);
        $timeSinceLastCheckIn = $this->calculateTimeSinceLastCheckIn($icNo);
        
        // 8. Return response with normalized keys
        return response()->json([
            'success' => true,
            'data' => [
                'dates' => array_values(array_unique($normalizedDates)), // Unique normalized dates
                'logs_by_date' => $logsByVisitDate,
                'timeline_by_date' => $timelineByVisitDate,
                'visit_sessions' => $visitSessions,
                'current_status' => $currentStatus,
                'total_time_spent' => $totalTimeSpent,
                'turnstile_info' => $turnstileInfo,
                'summary' => $summary,
                'time_since_last_checkin' => $timeSinceLastCheckIn,
                'all_access_logs' => $accessLogs,
                'all_location_timeline' => $locationTimeline
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error fetching chronology: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Error fetching chronology data: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Get all access logs for visitor (ordered by latest first)
 */
private function getAccessLogs($icNo)
{
    // StaffNo ki jagah IC No se search karein
    $logs = DB::table('device_access_logs as dal')
        ->where('dal.staff_no', $icNo) // Yahan IC No use ho raha hai
        ->orderBy('dal.created_at', 'desc')
        ->select(
            'dal.id',
            'dal.staff_no',
            'dal.access_granted',
            'dal.location_name',
            'dal.device_id',
            'dal.acknowledge',
            'dal.created_at',
            DB::raw('(SELECT location_name FROM device_access_logs WHERE staff_no = dal.staff_no AND created_at > dal.created_at ORDER BY created_at ASC LIMIT 1) as next_location'),
            DB::raw('(SELECT created_at FROM device_access_logs WHERE staff_no = dal.staff_no AND created_at > dal.created_at ORDER BY created_at ASC LIMIT 1) as next_time'),
            DB::raw('(SELECT location_name FROM device_access_logs WHERE staff_no = dal.staff_no AND created_at < dal.created_at ORDER BY created_at DESC LIMIT 1) as previous_location'),
            DB::raw('(SELECT created_at FROM device_access_logs WHERE staff_no = dal.staff_no AND created_at < dal.created_at ORDER BY created_at DESC LIMIT 1) as previous_time')
        )
        ->get();
    
    Log::info("getAccessLogs: Found " . $logs->count() . " logs for IC Number: $icNo");
    
    // Debug ke liye logs print karein
    foreach ($logs as $log) {
        Log::info("Log ID: {$log->id}, Date: " . date('d-M-Y', strtotime($log->created_at)) . 
                 ", Location: {$log->location_name}, Staff/IC No: {$log->staff_no}");
    }
    
    return $logs;
}

/**
 * Get location timeline with durations (adjusted for descending order)
 */
private function getLocationTimeline($accessLogs)
{
    $timeline = [];
    
    // Sort access logs in ascending order for timeline calculation
    $sortedLogs = $accessLogs->sortBy('created_at')->values();
    
    foreach ($sortedLogs as $index => $log) {
        if ($index === 0) continue;
        
        $previousLog = $sortedLogs[$index - 1];
        
        // Calculate time spent at previous location
        $timeSpent = strtotime($log->created_at) - strtotime($previousLog->created_at);
        $hours = floor($timeSpent / 3600);
        $minutes = floor(($timeSpent % 3600) / 60);
        $seconds = $timeSpent % 60;
        
        $timeline[] = [
            'from_location' => $previousLog->location_name,
            'to_location' => $log->location_name,
            'entry_time' => $previousLog->created_at,
            'exit_time' => $log->created_at,
            'time_spent' => [
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'total_seconds' => $timeSpent
            ],
            'access_granted' => $log->access_granted
        ];
    }
    
    return $timeline;
}

    // private function getCurrentStatus($staffNo)
    // {
    //     // Get the last access log
    //     $lastLog = DB::table('device_access_logs')
    //         ->where('staff_no', $staffNo)
    //         ->orderBy('created_at', 'desc')
    //         ->first();
            
    //     if (!$lastLog) {
    //         return [
    //             'status' => 'unknown',
    //             'message' => 'No access logs found'
    //         ];
    //     }
        
    //     // First, try to get device type from vendor_locations based on location name
    //     $locationType = $this->getLocationTypeFromName($lastLog->location_name);
        
    //     if ($locationType === 'check_in') {
    //         return [
    //             'status' => 'in_building',
    //             'last_location' => $lastLog->location_name,
    //             'last_access_time' => $lastLog->created_at,
    //             'message' => 'Visitor is currently in the building'
    //         ];
    //     } elseif ($locationType === 'check_out') {
    //         return [
    //             'status' => 'out_of_building',
    //             'last_location' => $lastLog->location_name,
    //             'last_access_time' => $lastLog->created_at,
    //             'message' => 'Visitor has exited the building'
    //         ];
    //     }
        
    //     // If location type not found in database, try to determine from location name
    //     $locationName = strtolower($lastLog->location_name);
        
    //     if (strpos($locationName, 'entry') !== false || 
    //         strpos($locationName, 'enter') !== false || 
    //         strpos($locationName, 'in') !== false ||
    //         strpos($locationName, 'checkin') !== false) {
            
    //         return [
    //             'status' => 'in_building',
    //             'last_location' => $lastLog->location_name,
    //             'last_access_time' => $lastLog->created_at,
    //             'message' => 'Visitor is currently in the building (based on location name)'
    //         ];
    //     } elseif (strpos($locationName, 'exit') !== false || 
    //               strpos($locationName, 'out') !== false ||
    //               strpos($locationName, 'checkout') !== false) {
            
    //         return [
    //             'status' => 'out_of_building',
    //             'last_location' => $lastLog->location_name,
    //             'last_access_time' => $lastLog->created_at,
    //             'message' => 'Visitor has exited the building (based on location name)'
    //         ];
    //     }
        
    //     // Default: if last access was granted, assume in building
    //     if ($lastLog->access_granted == 1) {
    //         return [
    //             'status' => 'in_building',
    //             'last_location' => $lastLog->location_name,
    //             'last_access_time' => $lastLog->created_at,
    //             'message' => 'Visitor is in the building (last access granted)'
    //         ];
    //     }
        
    //     return [
    //         'status' => 'unknown',
    //         'last_location' => $lastLog->location_name,
    //         'last_access_time' => $lastLog->created_at,
    //         'message' => 'Could not determine current status'
    //     ];
    // }
    
    /**
     * Helper method to get location type from vendor_locations
     */
    private function getLocationTypeFromName($locationName)
    {
        // Try to find the location in vendor_locations table
        $location = VendorLocation::where('name', 'like', '%' . $locationName . '%')
            ->orWhere('name', $locationName)
            ->first();
        
        if ($location) {
            // Check if there's a device_location_assign for this location
            $deviceAssign = DeviceLocationAssign::where('location_id', $location->id)
                ->first();
                
            if ($deviceAssign) {
                return $deviceAssign->is_type;
            }
        }
        
        return null;
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
    private function generateSummary($staffNo, $accessLogs)
    {
        $uniqueLocations = $accessLogs->pluck('location_name')->unique()->values();
        
        $firstLog = $accessLogs->first();
        $lastLog = $accessLogs->last();
        
        return [
            'total_visits' => count($accessLogs),
            'unique_locations_visited' => count($uniqueLocations),
            'locations_list' => $uniqueLocations,
            'first_visit' => $firstLog ? $firstLog->created_at : null,
            'last_visit' => $lastLog ? $lastLog->created_at : null,
            'successful_accesses' => $accessLogs->where('access_granted', 1)->count(),
            'failed_accesses' => $accessLogs->where('access_granted', 0)->count(),
            'acknowledged_logs' => $accessLogs->where('acknowledge', 1)->count()
        ];
    }

    /**
     * Get chronology from Java API (if available)
     */
    // public function getChronology(Request $request)
    // {
    //     return $this->getVisitorChronology($request);
    // }

    public function getChronology(Request $request)
    {
        $staffNo = $request->input('staff_no');
        $icNo = $request->input('ic_no');
        
        // ✅ IMPORTANT: IC No ko staff_no se match karna hai
        $searchTerm = $icNo ?: $staffNo;
        
        if (!$searchTerm) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide IC No or Staff No'
            ]);
        }
        
        try {
            // ✅ Step 1: Database se logs fetch karein - IC No ke according
            $accessLogs = DB::table('device_access_logs')
                ->where('staff_no', $searchTerm) // staff_no column mein IC No hai
                ->where('access_granted', 1) // Sirf successful accesses
                ->orderBy('created_at', 'asc')
                ->get();
            
            // ✅ Step 2: Unique dates extract karein
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
            
            // ✅ Step 3: Timeline generate karein har date ke liye
            foreach ($dates as $date) {
                $dateLogs = $logsByDate[$date] ?? [];
                $timelineByDate[$date] = $this->generateTimelineForDate($dateLogs);
            }
            
            // ✅ Step 4: Summary calculate karein
            $summary = [
                'total_visits' => $accessLogs->count(),
                'successful_accesses' => $accessLogs->where('access_granted', 1)->count(),
                'unique_dates' => count($dates)
            ];
            
            // ✅ Step 5: Current status determine karein
            $currentStatus = $this->getCurrentStatus($searchTerm);
            
            // ✅ Step 6: Total time spent calculate karein
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
        
        // Sort logs by time
        $sortedLogs = collect($logs)->sortBy('created_at');
        
        for ($i = 1; $i < $sortedLogs->count(); $i++) {
            $currentLog = $sortedLogs->get($i);
            $previousLog = $sortedLogs->get($i - 1);
            
            // Check if both logs are valid
            if (!$currentLog || !$previousLog) {
                continue;
            }
            
            // Calculate time difference
            $timeSpent = \Carbon\Carbon::parse($currentLog->created_at)
                ->diff(\Carbon\Carbon::parse($previousLog->created_at));
            
            // Always create timeline item regardless of time gap
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
        
        // اگر 2 گھنٹے سے زیادہ کا gap ہے تو نیا session شروع کریں
        if ($timeGap > 120) {
            // یہاں صرف اس pair کو skip کریں، لیکن next iteration جاری رکھیں
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
            ->where('access_granted', 1)
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
        
        $sortedLogs = $logs->sortBy('created_at');
        $totalSeconds = 0;
        
        for ($i = 1; $i < $sortedLogs->count(); $i++) {
            $current = $sortedLogs->get($i);
            $previous = $sortedLogs->get($i - 1);
            
            // Calculate time difference between consecutive logs
            $timeDiff = \Carbon\Carbon::parse($current->created_at)
                ->diffInSeconds(\Carbon\Carbon::parse($previous->created_at));
            
            // Only add if reasonable time gap (less than 4 hours)
            if ($timeDiff < 14400) { // 4 hours
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

}

