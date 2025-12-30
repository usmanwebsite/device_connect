<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;
use App\Models\DeviceAccessLog;
use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\VendorLocation;

class DashboardController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index(Request $request, MenuService $menuService)
    {
        try {
            Log::info('=== Dashboard Accessed ===');

            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            
            $angularMenu = [];
            if ($token) {
                try {
                    $angularMenu = $menuService->getFilteredAngularMenuWithToken($token);

                    // ✅ User ID session mein save karna
                    $userAccessData = $menuService->getUserAccessData();
                    if ($userAccessData && isset($userAccessData['user_id'])) {
                        session()->put('java_user_id', $userAccessData['user_id']);
                        Log::info('User ID saved in dashboard session:', ['user_id' => $userAccessData['user_id']]);
                    }
                    
                    if (empty($angularMenu) || (is_array($angularMenu) && count($angularMenu) === 0)) {
                        Log::warning('Empty angularMenu returned. Redirecting user.');
                        return redirect()->away(config('app.angular_url'))
                            ->with('error', 'Session expired. Please login again.');
                    }
                    
                    Log::info('angularmeniu', ['angularMenu' => $angularMenu]);
                    
                } catch (\Exception $e) {
                    Log::error('Menu error: ' . $e->getMessage());
                    $angularMenu = [];
                    return redirect()->away(config('app.angular_url'))
                        ->with('error', 'Session expired. Please login again.');
                }
            } else {
                Log::error('NO TOKEN FOUND IN DASHBOARD!');
                return redirect()->away(config('app.angular_url'))
                    ->with('error', 'Session expired. Please login again.');
            }
            
            Log::info('Angular Menu Structure:');
            
            // ✅ User access data ko dobara get karein (agar nahi mila toh)
            $userAccessData = $this->menuService->getUserAccessData();

            $todayAppointmentCount = 0;
            $upcomingAppointments = [];
            $todayAppointments = [];
            
            if ($userAccessData && isset($userAccessData['today_appointment_count'])) {
                $todayAppointmentCount = $userAccessData['today_appointment_count'];
                $todayAppointments = $userAccessData['today_appointments'] ?? [];
                $upcomingAppointments = $userAccessData['upcoming_appointments'] ?? [];
            }
            
            // ✅ Get visitors on site
            $visitorsOnSite = $this->getCurrentVisitorsOnSite();
            
            $criticalAlert = $this->getCriticalSecurityAlert();

            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->count();

            $hourlyTrafficData = $this->getHourlyTrafficData();

            $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            $visitorOverstayAlerts = $this->getAllVisitorOverstayAlerts($allDeviceUsers);
            $visitorOverstayCount = count($visitorOverstayAlerts);

            $deniedAccessCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->count();

            $deniedAccessLogs = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            $enrichedDeniedAccessLogs = $this->getEnrichedDeniedAccessLogs($deniedAccessLogs);

            $criticalAlertDetails = null;
            if ($criticalAlert) {
                $criticalAlertDetails = $this->getEnrichedDeniedAccessLogs(
                    DeviceAccessLog::where('id', $criticalAlert['log_id'])->get()
                );
            }

            $enrichedOverstayAlerts = $this->getEnrichedOverstayAlerts($visitorOverstayAlerts);

            $checkOutsTodayCount = $this->getCheckoutsTodayCount();

            $checkoutsTodayModalData = $this->getCheckoutsTodayModalData();

            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayCount',    
                'deniedAccessCount',      
                'deniedAccessLogs',        
                'visitorOverstayAlerts',    
                'enrichedDeniedAccessLogs', 
                'enrichedOverstayAlerts',   
                'checkOutsTodayCount',       
                'checkoutsTodayModalData',    
                'criticalAlert',
                'criticalAlertDetails'
            ));
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            
            $angularMenu = [];
            $todayAppointmentCount = 0;
            $visitorsOnSite = [];
            $todayAppointments = [];
            $upcomingAppointments = [];
            $activeSecurityAlertsCount = 0;
            $hourlyTrafficData = $this->getDefaultHourlyTrafficData();
            $visitorOverstayCount = 0;
            $deniedAccessCount = 0;
            $checkOutsTodayCount = 0; 
            $checkoutsTodayModalData = []; 
            $deniedAccessLogs = collect(); 
            $visitorOverstayAlerts = [];
            $enrichedDeniedAccessLogs = [];
            $enrichedOverstayAlerts = [];
            $criticalAlert = []; 
            $criticalAlertDetails = [];
            
            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayCount',
                'deniedAccessCount',
                'checkOutsTodayCount',
                'checkoutsTodayModalData', 
                'deniedAccessLogs',
                'visitorOverstayAlerts',
                'enrichedDeniedAccessLogs',
                'enrichedOverstayAlerts',
                'criticalAlert',
                'criticalAlertDetails'
            ));
        }
    }

    private function getCurrentVisitorsOnSite()
    {
    try {
        Log::info('=== Starting getCurrentVisitorsOnSite ===');
        
        // Step 1: Get ALL access logs (not just Turnstile)
        $allAccessLogs = DeviceAccessLog::where('access_granted', 1)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'staff_no', 'device_id', 'location_name', 'created_at']);
        
        Log::info('All access logs found: ' . $allAccessLogs->count());
        
        // Step 2: Get device connections
        $deviceIds = $allAccessLogs->pluck('device_id')->unique();  // <-- CHANGE HERE
        $deviceConnections = DeviceConnection::whereIn('device_id', $deviceIds)
            ->get(['id', 'device_id']);
        
        Log::info('Device connections found: ' . $deviceConnections->count());
        
        // Step 3: Get vendor locations
        $locationNames = $allAccessLogs->pluck('location_name')->unique();  // <-- CHANGE HERE
        $vendorLocations = VendorLocation::whereIn('name', $locationNames)
            ->get(['id', 'name']);
        
        Log::info('Vendor locations found: ' . $vendorLocations->count());
        
        // Step 4: Get device location assigns
        $deviceConnectionIds = $deviceConnections->pluck('id');
        $locationIds = $vendorLocations->pluck('id');
        
        $deviceLocationAssigns = DeviceLocationAssign::whereIn('device_id', $deviceConnectionIds)
            ->whereIn('location_id', $locationIds)
            ->get(['id', 'device_id', 'location_id', 'is_type', 'created_at']);
        
        Log::info('Device location assigns found: ' . $deviceLocationAssigns->count());
        
        // Step 5: Group logs by staff_no
        $groupedLogs = $allAccessLogs->groupBy('staff_no');  // <-- CHANGE HERE
        
        $currentVisitors = [];
        
        foreach ($groupedLogs as $staffNo => $logs) {
            // Step 6: For each staff_no, get the latest check_in and check_out
            $latestCheckIn = null;
            $latestCheckOut = null;
            
            foreach ($logs as $log) {
                // Find device connection
                $deviceConnection = $deviceConnections->firstWhere('device_id', $log->device_id);
                if (!$deviceConnection) continue;
                
                // Find vendor location
                $vendorLocation = $vendorLocations->firstWhere('name', $log->location_name);
                if (!$vendorLocation) continue;
                
                // Find device location assign
                $deviceLocationAssign = $deviceLocationAssigns
                    ->where('device_id', $deviceConnection->id)
                    ->where('location_id', $vendorLocation->id)
                    ->first();
                
                if (!$deviceLocationAssign) continue;
                
                // Step 7: Check is_type and update latest
                if ($deviceLocationAssign->is_type === 'check_in') {
                    if (!$latestCheckIn || $log->created_at > $latestCheckIn->log->created_at) {
                        $latestCheckIn = (object) [
                            'log' => $log,
                            'device_location_assign' => $deviceLocationAssign
                        ];
                    }
                } elseif ($deviceLocationAssign->is_type === 'check_out') {
                    if (!$latestCheckOut || $log->created_at > $latestCheckOut->log->created_at) {
                        $latestCheckOut = (object) [
                            'log' => $log,
                            'device_location_assign' => $deviceLocationAssign
                        ];
                    }
                }
            }
            
            // Step 8: Determine if visitor is currently on-site
            $isCurrentlyOnSite = false;
            $currentLog = null;
            
            if ($latestCheckIn && !$latestCheckOut) {
                // Only check_in exists, no check_out
                $isCurrentlyOnSite = true;
                $currentLog = $latestCheckIn->log;
            } elseif ($latestCheckIn && $latestCheckOut) {
                // Both check_in and check_out exist
                if ($latestCheckIn->log->created_at > $latestCheckOut->log->created_at) {
                    // Latest check_in is after latest check_out
                    $isCurrentlyOnSite = true;
                    $currentLog = $latestCheckIn->log;
                } else {
                    // Latest check_out is after check_in
                    $isCurrentlyOnSite = false;
                }
            }
            
            // Step 9: If currently on-site, add to list
            if ($isCurrentlyOnSite && $currentLog) {
                // Get visitor details from Java API
                $visitorDetails = $this->getVisitorDetailsForAlert($staffNo);
                
                $currentVisitors[] = [
                    'staff_no' => $staffNo,
                    'full_name' => $visitorDetails['fullName'] ?? 'N/A',
                    'person_visited' => $visitorDetails['personVisited'] ?? 'N/A',
                    'location_name' => $currentLog->location_name,
                    'created_at' => $currentLog->created_at,
                    'device_id' => $currentLog->device_id,
                    'log_id' => $currentLog->id
                ];
                
                Log::info("Visitor {$staffNo} is currently on-site at {$currentLog->location_name}");
            }
        }
        
        // Step 10: Remove duplicates (just in case)
        $uniqueVisitors = collect($currentVisitors)->unique('staff_no')->values()->all();
        
        Log::info('Total unique visitors on-site: ' . count($uniqueVisitors));
        Log::info('=== End getCurrentVisitorsOnSite ===');
        
        return $uniqueVisitors;
        
    } catch (\Exception $e) {
        Log::error('Error in getCurrentVisitorsOnSite: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return [];
    }
}

    // Rest of the controller methods remain the same...
    private function getCriticalSecurityAlert()
    {
        try {
            $alert = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$alert) {
                return null;
            }

            $visitorDetails = $this->getVisitorDetailsForAlert($alert->staff_no);

            $createdAt = Carbon::parse($alert->created_at);
            $timeAgo = $createdAt->diffForHumans();

            return [
                'log_id' => $alert->id,
                'staff_no' => $alert->staff_no,
                'location' => $alert->location_name ?? 'Unknown Location',
                'created_at' => $createdAt->format('h:i A'),
                'time_ago' => $timeAgo,
                'reason' => $alert->reason ?? 'Other Reason',
                'visitor_name' => $visitorDetails['fullName'] ?? 'Unknown Visitor',
                'incident_type' => 'Unauthorized Access Attempt' 
            ];

        } catch (\Exception $e) {
            Log::error('Error getting critical alert: ' . $e->getMessage());
            return null;
        }
    }

    private function getVisitorDetailsForAlert($staffNo)
    {
        try {
            $javaApiResponse = $this->callJavaVendorApi($staffNo);
            
            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                return $javaApiResponse['data'];
            }
            
            return [
                'fullName' => 'Unknown Visitor',
                'personVisited' => 'N/A'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting visitor details for alert: ' . $e->getMessage());
            return [
                'fullName' => 'Unknown Visitor',
                'personVisited' => 'N/A'
            ];
        }
    }

    public function acknowledgeAlert(Request $request)
    {
        try {
            $alertId = $request->input('alert_id');
            
            $alert = DeviceAccessLog::find($alertId);
            
            if (!$alert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alert not found'
                ], 404);
            }

            // ✅ GET CURRENT USER ID FROM SESSION
            $currentUserId = session()->get('java_user_id');
            
            if (!$currentUserId) {
                // Agar session mein nahi hai toh Java API se fetch karo
                $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
                if ($token) {
                    $menuService = new MenuService();
                    $userAccessData = $menuService->fetchUserAccessFromJavaBackendWithToken($token);
                    $currentUserId = $userAccessData['user_id'] ?? null;
                    
                    // Save to session for next time
                    if ($currentUserId) {
                        session()->put('java_user_id', $currentUserId);
                    }
                }
            }

            Log::info('Acknowledging alert with user ID:', [
                'alert_id' => $alertId,
                'user_id' => $currentUserId,
                'staff_no' => $alert->staff_no
            ]);

            // ✅ UPDATE BOTH acknowledge AND acknowledge_by
            $alert->acknowledge = 1;
            $alert->acknowledge_by = $currentUserId; // ✅ Yahan save kar rahe hain
            $alert->save();
            
            Log::info("Alert acknowledged: ID {$alertId}, Staff No: {$alert->staff_no}, Acknowledged By: {$currentUserId}");

            $nextAlert = $this->getCriticalSecurityAlert();
            
            return response()->json([
                'success' => true,
                'message' => 'Alert acknowledged successfully',
                'next_alert' => $nextAlert,
                'has_next' => $nextAlert ? true : false,
                'acknowledged_by' => $currentUserId // ✅ Frontend ko bhi bhej sakte hain
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error acknowledging alert: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error acknowledging alert: ' . $e->getMessage()
            ], 500);
        }
    }

    public function hideCriticalAlert(Request $request)
    {
        try {
            $alertId = $request->input('alert_id');
            
            if ($alertId) {
                $alert = DeviceAccessLog::find($alertId);
                if ($alert) {
                    // ✅ GET CURRENT USER ID FROM SESSION
                    $currentUserId = session()->get('java_user_id');
                    
                    $alert->acknowledge = 1;
                    $alert->acknowledge_by = $currentUserId; // ✅ Yahan bhi save karein
                    $alert->save();
                    
                    Log::info("Alert hidden and acknowledged: ID {$alertId}, Acknowledged By: {$currentUserId}");
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Alert hidden successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error hiding critical alert: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error hiding alert'
            ], 500);
        }
    }

    // public function getCriticalAlertDetails(Request $request)
    // {
    //     // dd('hello');
    //     try {
    //         $alertId = $request->input('alert_id');
            
    //         $alert = DeviceAccessLog::find($alertId);
            
    //         if (!$alert) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Alert not found'
    //             ], 404);
    //         }
            
    //         // ✅ FIX: Directly get visitor details
    //         $javaApiResponse = $this->callJavaVendorApi($alert->staff_no);
            
    //         Log::info('Java API Response for alert: ', ['response' => $javaApiResponse]);
            
    //         if ($javaApiResponse && isset($javaApiResponse['data'])) {
    //             $visitorData = $javaApiResponse['data'];
                
    //             return response()->json([
    //                 'success' => true,
    //                 'alert' => [
    //                     'log' => [
    //                         'id' => $alert->id,
    //                         'staff_no' => $alert->staff_no,
    //                         'location_name' => $alert->location_name ?? 'Unknown Location',
    //                         'reason' => $alert->reason ?? 'Other Reason',
    //                         'created_at' => $alert->created_at
    //                     ],
    //                     'visitor_details' => [
    //                         'fullName' => $visitorData['fullName'] ?? 'N/A',
    //                         'personVisited' => $visitorData['personVisited'] ?? 'N/A',
    //                         'contactNo' => $visitorData['contactNo'] ?? 'N/A',
    //                         'icNo' => $visitorData['icNo'] ?? 'N/A',
    //                         'sex' => $visitorData['sex'] ?? 'N/A',
    //                         'dateOfVisitFrom' => $visitorData['dateOfVisitFrom'] ?? 'N/A',
    //                         'dateOfVisitTo' => $visitorData['dateOfVisitTo'] ?? 'N/A'
    //                     ]
    //                 ]
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Could not fetch visitor details'
    //             ], 404);
    //         }
            
    //     } catch (\Exception $e) {
    //         Log::error('Error getting critical alert details: ' . $e->getMessage());
            
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error getting alert details'
    //         ], 500);
    //     }
    // }

    public function getNextAlert(Request $request)
    {
        try {
            $currentAlertId = $request->input('current_alert_id');

            if ($currentAlertId) {
                $currentAlert = DeviceAccessLog::find($currentAlertId);
                if ($currentAlert) {
                    $currentAlert->acknowledge = 1;
                    $currentAlert->save();
                }
            }

            $nextAlert = $this->getCriticalSecurityAlert();
            
            return response()->json([
                'success' => true,
                'next_alert' => $nextAlert,
                'has_next' => $nextAlert ? true : false
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting next alert: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error getting next alert'
            ], 500);
        }
    }

    public function refreshDashboardCounts()
    {
        try {
            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->count();

            $deniedAccessCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->count();

            return response()->json([
                'success' => true,
                'activeSecurityAlertsCount' => $activeSecurityAlertsCount,
                'deniedAccessCount' => $deniedAccessCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing dashboard counts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing counts'
            ], 500);
        }
    }

    private function getCheckoutsTodayCount()
    {
        try {
            Log::info('=== Starting getCheckoutsTodayCount (With Location Match) ===');

            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);

            Log::info('Turnstile locations found:', $turnstileLocations->toArray());

            if ($turnstileLocations->isEmpty()) {
                Log::info('No Turnstile locations found.');
                return 0;
            }

            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');

            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_out')
                ->get(['id', 'device_id', 'location_id', 'is_type']);

            Log::info('Device location assigns found:', $deviceLocationAssigns->toArray());

            if ($deviceLocationAssigns->isEmpty()) {
                Log::info('No device assigns found with check_out.');
                return 0;
            }

            $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');

            $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
                ->get(['id', 'device_id']);

            Log::info('Device connections found:', $deviceConnections->toArray());

            if ($deviceConnections->isEmpty()) {
                Log::info('No device connections found.');
                return 0;
            }

            $actualDeviceIds = $deviceConnections->pluck('device_id');

            Log::info('Actual device IDs: ', ['ids' => $actualDeviceIds->implode(', ')]);

            $today = now()->format('Y-m-d');

            Log::info("Checking logs for date: {$today}");
            Log::info('Matching location names: ', ['locations' => $turnstileLocationNames->implode(', ')]);

            $sampleLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
                ->whereIn('location_name', $turnstileLocationNames)
                ->whereDate('created_at', $today)
                ->take(5)
                ->get(['id', 'device_id', 'location_name', 'staff_no', 'created_at']);

            Log::info('Sample access logs (with location match):', $sampleLogs->toArray());

            $checkoutsCount = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
                ->whereIn('location_name', $turnstileLocationNames)
                ->whereDate('created_at', $today)
                ->count();

            Log::info("Checkouts today count (with location filter): {$checkoutsCount}");
            Log::info('=== End getCheckoutsTodayCount ===');

            return $checkoutsCount;

        } catch (\Exception $e) {
            Log::error('Error calculating checkout count: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 0;
        }
    }

private function getEnrichedDeniedAccessLogs($deniedAccessLogs)
{
    $enrichedLogs = [];

    foreach ($deniedAccessLogs as $log) {
        Log::info('Processing denied access log ID: ' . $log->id);
        Log::info('Staff No from DB: ' . $log->staff_no);
        
        try {
            // Temporary test - use a fixed staffNo for testing
            $testStaffNo = 'TESTUSER123'; // Your test staff number
            $javaApiResponse = $this->callJavaVendorApi($testStaffNo);
            
            Log::info('API Response for ' . $testStaffNo . ': ', ['response' => $javaApiResponse]);

            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                $visitorData = $javaApiResponse['data'];
                
                $enrichedLogs[] = [
                    'log' => $log,
                    'visitor_details' => [
                        'fullName' => $visitorData['fullName'] ?? 'N/A',
                        'personVisited' => $visitorData['personVisited'] ?? 'N/A',
                        'contactNo' => $visitorData['contactNo'] ?? 'N/A',
                        'icNo' => $visitorData['icNo'] ?? 'N/A',
                        'sex' => $visitorData['sex'] ?? 'N/A',
                        'dateOfVisitFrom' => $visitorData['dateOfVisitFrom'] ?? 'N/A',
                        'dateOfVisitTo' => $visitorData['dateOfVisitTo'] ?? 'N/A'
                    ]
                ];
            } else {
                $enrichedLogs[] = [
                    'log' => $log,
                    'visitor_details' => [
                        'fullName' => 'N/A - API Failed',
                        'personVisited' => 'N/A',
                        'contactNo' => 'N/A',
                        'icNo' => 'N/A',
                        'sex' => 'N/A',
                        'dateOfVisitFrom' => 'N/A',
                        'dateOfVisitTo' => 'N/A'
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error('Java API error for staff_no ' . $log->staff_no . ' in denied access: ' . $e->getMessage());
            $enrichedLogs[] = [
                'log' => $log,
                'visitor_details' => [
                    'fullName' => 'N/A - Exception',
                    'personVisited' => 'N/A',
                    'contactNo' => 'N/A',
                    'icNo' => 'N/A',
                    'sex' => 'N/A',
                    'dateOfVisitFrom' => 'N/A',
                    'dateOfVisitTo' => 'N/A'
                ]
            ];
        }
    }

    return $enrichedLogs;
}

    private function getAllVisitorOverstayAlerts($allDeviceUsers)
    {
        $overstayAlerts = [];
        $currentTime = now();

        foreach ($allDeviceUsers as $user) {
            try {
                if (empty($user['location_name'])) {
                    continue; 
                }

                $javaApiResponse = $this->callJavaVendorApi($user['staff_no']);
                
                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $visitorData = $javaApiResponse['data'];
                    
                    Log::info('Java API Response for ' . $user['staff_no'] . ': ', $javaApiResponse);
                    
                    if (isset($visitorData['dateOfVisitTo'])) {
                        $dateOfVisitTo = \Carbon\Carbon::parse($visitorData['dateOfVisitTo']);
                        
                        if ($currentTime->greaterThan($dateOfVisitTo)) {
                            $overstayMinutes = $currentTime->diffInMinutes($dateOfVisitTo);
                            $overstayHours = floor($overstayMinutes / 60);
                            $remainingMinutes = $overstayMinutes % 60;
                            
                            $overstayAlerts[] = [
                                'visitor_name' => $visitorData['fullName'] ?? 'N/A',
                                'staff_no' => $user['staff_no'],
                                'expected_end_time' => $dateOfVisitTo->format('d M Y h:i A'), 
                                'current_time' => $currentTime->format('d M Y h:i A'), 
                                'check_in_time' => \Carbon\Carbon::parse($user['created_at'])->format('d M Y h:i A'),
                                'location' => $user['location_name'] ?? 'N/A',
                                'overstay_minutes' => $overstayMinutes,
                                'overstay_duration' => $overstayHours . ' hours ' . $remainingMinutes . ' minutes',
                                'host' => $visitorData['personVisited'] ?? 'N/A',
                                'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                                'ic_no' => $visitorData['icNo'] ?? 'N/A',
                                'date_of_visit_from' => isset($visitorData['dateOfVisitFrom']) ? \Carbon\Carbon::parse($visitorData['dateOfVisitFrom'])->format('d M Y h:i A') : 'N/A',
                                'date_of_visit_to' => $dateOfVisitTo->format('d M Y h:i A')
                            ];
                            
                            Log::info('Overstay detected for ' . $user['staff_no'] . ': ' . $overstayHours . ' hours ' . $remainingMinutes . ' minutes');
                        } else {
                            Log::info('No overstay for ' . $user['staff_no'] . ' - Visit ends at: ' . $dateOfVisitTo->format('d M Y h:i A') . ', Current: ' . $currentTime->format('d M Y h:i A'));
                        }
                    } else {
                        Log::warning('dateOfVisitTo not found for staff_no: ' . $user['staff_no']);
                    }
                } else {
                    Log::warning('Java API response failed for staff_no: ' . $user['staff_no']);
                }
            } catch (\Exception $e) {
                Log::error('Error checking overstay for staff_no ' . $user['staff_no'] . ': ' . $e->getMessage());
                continue;
            }
        }

        Log::info('Total overstay alerts found: ' . count($overstayAlerts));
        return $overstayAlerts;
    }

    private function getEnrichedOverstayAlerts($overstayAlerts)
    {
        $enrichedAlerts = [];

        foreach ($overstayAlerts as $alert) {
            try {
                $javaApiResponse = $this->callJavaVendorApi($alert['staff_no']);

                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $visitorData = $javaApiResponse['data'];
                    
                    $enrichedAlerts[] = [
                        'visitor_name' => $visitorData['fullName'] ?? $alert['visitor_name'],
                        'staff_no' => $alert['staff_no'],
                        'host' => $visitorData['personVisited'] ?? $alert['host'],
                        'location' => $alert['location'],
                        'check_in_time' => $alert['check_in_time'],
                        'expected_end_time' => $alert['expected_end_time'],
                        'current_time' => $alert['current_time'],
                        'overstay_duration' => $alert['overstay_duration'],
                        'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                        'ic_no' => $visitorData['icNo'] ?? 'N/A'
                    ];
                } else {
                    $enrichedAlerts[] = $alert;
                }
            } catch (\Exception $e) {
                Log::error('Java API error for overstay staff_no ' . $alert['staff_no'] . ': ' . $e->getMessage());
                $enrichedAlerts[] = $alert;
            }
        }

        return $enrichedAlerts;
    }

private function callJavaVendorApi($staffNo)
{
    try {
        $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
        $token = session()->get('java_backend_token') ?? session()->get('java_auth_token'); 
        
        Log::info('=== Java API Call Debug ===');
        Log::info('Staff No: ' . $staffNo);
        Log::info('Java Base URL: ' . $javaBaseUrl);
        Log::info('Token exists: ' . ($token ? 'Yes' : 'No'));
        
        if (!$token) {
            Log::error('Java API Token missing in session!');
            return null;
        }
        
        $url = $javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . urlencode($staffNo);
        Log::info('Full URL: ' . $url);
        
        $response = Http::withHeaders([
            'x-auth-token' => $token,
            'Accept' => 'application/json',
        ])->timeout(10)
          ->get($url);

        Log::info('Response Status: ' . $response->status());
        Log::info('Response Body: ' . $response->body());
        
        if ($response->successful()) {
            $data = $response->json();
            Log::info('API Response Data: ', $data);
            return $data;
        } else {
            Log::error('Java API error: ' . $response->status());
            Log::error('Error body: ' . $response->body());
            return null;
        }
    } catch (\Exception $e) {
        Log::error('Java API exception: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return null;
    }
}

    private function getHourlyTrafficData()
    {
        try {
            $today = now()->format('Y-m-d');

            $todayAccessLogs = DeviceAccessLog::whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at')
                ->get();

            $cumulativeData = [];
            $labels = [];
            
            for ($i = 0; $i < 24; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
                
                $labels[] = $timeLabel;

                $currentHourEnd = Carbon::createFromFormat('Y-m-d H', $today . ' ' . $i);

                $cumulativeCount = $todayAccessLogs
                    ->filter(function ($log) use ($currentHourEnd) {
                        return Carbon::parse($log->created_at)->lte($currentHourEnd);
                    })
                    ->count();
                
                $cumulativeData[] = $cumulativeCount;
            }
            
            return [
                'labels' => $labels,
                'data' => $cumulativeData
            ];
            
        } catch (\Exception $e) {
            Log::error('Hourly traffic data error: ' . $e->getMessage());
            return $this->getDefaultHourlyTrafficData();
        }
    }

    private function getDefaultHourlyTrafficData()
    {
        $labels = ['8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM'];
        $data = [10, 30, 25, 40, 20, 35, 45, 30, 25, 15];
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    public function getGraphData(Request $request)
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'From date and to date are required'
                ], 400);
            }

            if (Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
                return response()->json([
                    'success' => false,
                    'message' => 'From date cannot be greater than to date'
                ], 400);
            }

            $graphData = $this->getHourlyTrafficDataByDateRange($fromDate, $toDate);

            return response()->json([
                'success' => true,
                'labels' => $graphData['labels'],
                'data' => $graphData['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Graph data error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading graph data'
            ], 500);
        }
    }

    private function getHourlyTrafficDataByDateRange($fromDate, $toDate)
    {
        try {
            if ($fromDate === $toDate) {
                return $this->getHourlyDataForSingleDay($fromDate);
            }

            return $this->getDailyDataForDateRange($fromDate, $toDate);
            
        } catch (\Exception $e) {
            Log::error('Hourly traffic data by range error: ' . $e->getMessage());
            return $this->getDefaultGraphData();
        }
    }

    private function getHourlyDataForSingleDay($date)
    {
        $accessLogs = DeviceAccessLog::whereDate('created_at', $date)
            ->where('access_granted', 1)
            ->orderBy('created_at')
            ->get();
        
        $hourlyData = [];
        $labels = [];
        
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
            
            $labels[] = $timeLabel;
            
            $hourStart = Carbon::createFromFormat('Y-m-d H', $date . ' ' . $i);
            $hourEnd = $hourStart->copy()->addHour();
            
            $hourCount = $accessLogs
                ->filter(function ($log) use ($hourStart, $hourEnd) {
                    $logTime = Carbon::parse($log->created_at);
                    return $logTime->between($hourStart, $hourEnd);
                })
                ->count();
            
            $hourlyData[] = $hourCount;
        }
        
        return [
            'labels' => $labels,
            'data' => $hourlyData
        ];
    }
    
    private function getDailyDataForDateRange($fromDate, $toDate)
    {
        $accessLogs = DeviceAccessLog::whereBetween('created_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59'
            ])
            ->where('access_granted', 1)
            ->orderBy('created_at')
            ->get();
        
        $dailyData = [];
        $labels = [];
        
        $currentDate = Carbon::parse($fromDate);
        $endDate = Carbon::parse($toDate);
        
        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            $label = $currentDate->format('M d');
            
            $labels[] = $label;
            
            $dayCount = $accessLogs
                ->filter(function ($log) use ($dateString) {
                    return Carbon::parse($log->created_at)->format('Y-m-d') === $dateString;
                })
                ->count();
            
            $dailyData[] = $dayCount;
            $currentDate->addDay();
        }
        
        return [
            'labels' => $labels,
            'data' => $dailyData
        ];
    }

    private function getDefaultGraphData()
    {
        $labels = ['8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM'];
        $data = [10, 30, 25, 40, 20, 35, 45, 30, 25, 15];
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    public function getCheckoutsTodayModalDataAjax(Request $request)
    {
        try {
            $checkoutsData = $this->getCheckoutsTodayModalData();
            
            return response()->json([
                'success' => true,
                'data' => $checkoutsData,
                'count' => count($checkoutsData),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('AJAX Error in getCheckoutsTodayModalData: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading check-outs data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    private function getCheckoutsTodayModalData()
    {
        try {
            Log::info('=== Starting getCheckoutsTodayModalData ===');
            
            $today = now()->format('Y-m-d');
            $checkoutRecords = [];

            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);
            
            Log::info('Turnstile locations found:', $turnstileLocations->toArray());
            
            if ($turnstileLocations->isEmpty()) {
                Log::info('No Turnstile locations found.');
                return [];
            }
            
            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');

            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_out')
                ->get(['id', 'device_id', 'location_id', 'is_type']);
            
            Log::info('Device location assigns (check_out) found:', $deviceLocationAssigns->toArray());
            
            if ($deviceLocationAssigns->isEmpty()) {
                Log::info('No device_location_assigns with check_out type found.');
                return [];
            }

            $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');
            
            $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
                ->get(['id', 'device_id']);
            
            Log::info('Device connections found:', $deviceConnections->toArray());
            
            if ($deviceConnections->isEmpty()) {
                Log::info('No device connections found.');
                return [];
            }
            
            $actualDeviceIds = $deviceConnections->pluck('device_id');
            
            $todayCheckoutLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
                ->whereIn('location_name', $turnstileLocationNames)
                ->whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get();
            
            Log::info('Today checkout logs found (after both checks): ' . $todayCheckoutLogs->count());
            
            foreach ($todayCheckoutLogs as $checkoutLog) {
                try {
                    $visitorDetails = $this->getVisitorDetailsForCheckout($checkoutLog);
                    
                    $checkInLog = $this->getCheckinLogForStaffNo($checkoutLog->staff_no, $today);
                    
                    $duration = $this->calculateCheckoutDuration($checkInLog, $checkoutLog);
                    
                    $checkoutRecords[] = [
                        'visitor_name' => $visitorDetails['fullName'] ?? 'N/A',
                        'host' => $visitorDetails['personVisited'] ?? 'N/A',
                        'check_in_time' => $checkInLog ? $checkInLog->created_at->format('h:i A') : 'N/A',
                        'check_out_time' => $checkoutLog->created_at->format('h:i A'),
                        'duration' => $duration,
                        'staff_no' => $checkoutLog->staff_no,
                        'location' => $checkoutLog->location_name ?? 'N/A',
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing checkout record: ' . $e->getMessage());
                    continue;
                }
            }
            
            Log::info('Total checkout records processed: ' . count($checkoutRecords));
            return $checkoutRecords;
            
        } catch (\Exception $e) {
            Log::error('Error in getCheckoutsTodayModalData: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    private function getCheckinLogForStaffNo($staffNo, $date)
    {
        try {
            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);
            
            if ($turnstileLocations->isEmpty()) {
                return null;
            }
            
            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');
            
            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_in')
                ->get(['id', 'device_id', 'location_id']);
            
            if ($deviceLocationAssigns->isEmpty()) {
                return null;
            }
            $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');
            
            $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
                ->get(['id', 'device_id']);
            
            if ($deviceConnections->isEmpty()) {
                return null;
            }
            
            $actualDeviceIds = $deviceConnections->pluck('device_id');
            
            $checkinLog = DeviceAccessLog::where('staff_no', $staffNo)
                ->whereIn('device_id', $actualDeviceIds)  
                ->whereIn('location_name', $turnstileLocationNames) 
                ->whereDate('created_at', $date)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'asc')  
                ->first();
            
            return $checkinLog;
            
        } catch (\Exception $e) {
            Log::error('Error in getCheckinLogForStaffNo: ' . $e->getMessage());
            return null;
        }
    }

    private function getVisitorDetailsForCheckout($checkoutLog)
    {
        try {
            $javaApiResponse = $this->callJavaVendorApi($checkoutLog->staff_no);
            
            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                return $javaApiResponse['data'];
            }
            
            return [
                'fullName' => 'N/A',
                'personVisited' => 'N/A'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting visitor details for checkout: ' . $e->getMessage());
            return [
                'fullName' => 'N/A',
                'personVisited' => 'N/A'
            ];
        }
    }

    private function calculateCheckoutDuration($checkInLog, $checkoutLog)
    {
        if (!$checkInLog) {
            return 'N/A';
        }
        
        try {
            $checkInTime = Carbon::parse($checkInLog->created_at);
            $checkOutTime = Carbon::parse($checkoutLog->created_at);
            
            $diffInMinutes = $checkOutTime->diffInMinutes($checkInTime);
            
            if ($diffInMinutes < 60) {
                return $diffInMinutes . ' minutes';
            }
            
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;
            
            if ($minutes == 0) {
                return $hours . ' hours';
            }
            
            return $hours . ' hours ' . $minutes . ' minutes';
            
        } catch (\Exception $e) {
            Log::error('Error calculating duration: ' . $e->getMessage());
            return 'N/A';
        }
    }
}

