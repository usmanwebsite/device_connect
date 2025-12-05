<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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

    public function index()
    {
        try {
            // getFilteredAngularMenu() use karen jo Java API call karega
            $angularMenu = $this->menuService->getFilteredAngularMenu();
            
            // ✅ Java API se user access data le aayein
            $userAccessData = $this->menuService->getUserAccessData();
            
            // Agar userAccessData milta hai to use karein
            $todayAppointmentCount = 0;
            $upcomingAppointments = [];
            $todayAppointments = [];
            
            if ($userAccessData && isset($userAccessData['today_appointment_count'])) {
                $todayAppointmentCount = $userAccessData['today_appointment_count'];
                $todayAppointments = $userAccessData['today_appointments'] ?? [];
                $upcomingAppointments = $userAccessData['upcoming_appointments'] ?? [];
            }
            
            // ✅ POINT 1: Currently On-Site - Sirf device_access_logs se data
            $visitorsOnSite = DeviceAccessLog::where('access_granted', 1)->get();
            
            // ✅ Pehle hi sabhi visitors ke liye Java API data fetch karein
            $visitorsOnSite = $this->getVisitorsOnSiteWithJavaData($visitorsOnSite);
            
            $criticalAlert = $this->getCriticalSecurityAlert();
            // ✅ Active Security Alerts - COUNT ONLY
            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->count();

            // ✅ DYNAMIC GRAPH DATA: Device access logs se hourly data lein
            $hourlyTrafficData = $this->getHourlyTrafficData();

            // ✅ POINT 2: Visitor Overstay Alerts - SABHI device_access_logs users ke liye check karein
            $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            $visitorOverstayAlerts = $this->getAllVisitorOverstayAlerts($allDeviceUsers);
            $visitorOverstayCount = count($visitorOverstayAlerts);

            // ✅ Denied Access Logs - ALL TIME - COUNT ONLY
            $deniedAccessCount = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->count();

            // ✅ For old Recent Alerts section (Collection - for ->take() method)
            $deniedAccessLogs = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ For new modals (enriched data - array)
            $enrichedDeniedAccessLogs = $this->getEnrichedDeniedAccessLogs($deniedAccessLogs);

            $criticalAlertDetails = null;
            if ($criticalAlert) {
                $criticalAlertDetails = $this->getEnrichedDeniedAccessLogs(
                    DeviceAccessLog::where('id', $criticalAlert['log_id'])->get()
                );
            }

            // ✅ For new modals - enriched overstay alerts
            $enrichedOverstayAlerts = $this->getEnrichedOverstayAlerts($visitorOverstayAlerts);
            
            // ✅ ✅ ✅ NEW: Calculate Check-outs Today Count
            $checkOutsTodayCount = $this->getCheckoutsTodayCount();
            
            // ✅ ✅ ✅ NEW: Get Check-outs Today Data for Modal
            $checkoutsTodayModalData = $this->getCheckoutsTodayModalData();

            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayCount',     // ✅ Count for cards
                'deniedAccessCount',        // ✅ Count for cards
                'deniedAccessLogs',         // ✅ For old Recent Alerts section (Collection)
                'visitorOverstayAlerts',    // ✅ For old Recent Alerts section
                'enrichedDeniedAccessLogs', // ✅ For new modals (array)
                'enrichedOverstayAlerts',   // ✅ For new modals
                'checkOutsTodayCount',       // ✅ NEW: Check-outs Today count
                'checkoutsTodayModalData',    // ✅ NEW: Check-outs Today modal data
                'criticalAlert',
                'criticalAlertDetails'
            ));
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            
            // Default values in case of error
            $angularMenu = [];
            $todayAppointmentCount = 0;
            $visitorsOnSite = [];
            $todayAppointments = [];
            $upcomingAppointments = [];
            $activeSecurityAlertsCount = 0;
            $hourlyTrafficData = $this->getDefaultHourlyTrafficData();
            $visitorOverstayCount = 0;
            $deniedAccessCount = 0;
            $checkOutsTodayCount = 0; // ✅ Default value
            $checkoutsTodayModalData = []; // ✅ Default empty array
            $deniedAccessLogs = collect(); // ✅ Empty collection
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
                'checkOutsTodayCount', // ✅ Include in error case too
                'checkoutsTodayModalData', // ✅ Include in error case too
                'deniedAccessLogs',
                'visitorOverstayAlerts',
                'enrichedDeniedAccessLogs',
                'enrichedOverstayAlerts',
                'criticalAlert',
                'criticalAlertDetails'
            ));
        }
    }


        // ✅ ✅ ✅ NEW METHOD: Get Critical Security Alert
    private function getCriticalSecurityAlert()
    {
        try {
            // Pehla unacknowledged denied access log lein (latest wala)
            $alert = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$alert) {
                return null;
            }

            // Java API se visitor details lein
            $visitorDetails = $this->getVisitorDetailsForAlert($alert->staff_no);
            
            // Time ago calculate karein
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
                'incident_type' => 'Unauthorized Access Attempt' // ✅ Static
            ];

        } catch (\Exception $e) {
            Log::error('Error getting critical alert: ' . $e->getMessage());
            return null;
        }
    }

    // ✅ NEW METHOD: Get Visitor Details for Alert
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

    // ✅ ✅ ✅ NEW METHOD: Acknowledge Alert (AJAX endpoint ke liye)
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
            
            // ✅ Acknowledge update karein (1 set karein)
            $alert->acknowledge = 1;
            $alert->save();
            
            Log::info("Alert acknowledged: ID {$alertId}, Staff No: {$alert->staff_no}");
            
            // Next available alert check karein
            $nextAlert = $this->getCriticalSecurityAlert();
            
            return response()->json([
                'success' => true,
                'message' => 'Alert acknowledged successfully',
                'next_alert' => $nextAlert,
                'has_next' => $nextAlert ? true : false
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error acknowledging alert: ' . $e->getMessage());
            
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
                    // ✅ Sirf acknowledge column update karein
                    $alert->acknowledge = 1;
                    $alert->save();
                    Log::info("Alert hidden and acknowledged: ID {$alertId}");
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

    public function getCriticalAlertDetails(Request $request)
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
            
            // Enriched data lein
            $enrichedData = $this->getEnrichedDeniedAccessLogs(collect([$alert]));
            
            if (empty($enrichedData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No details found for this alert'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'alert' => $enrichedData[0]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting critical alert details: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error getting alert details'
            ], 500);
        }
    }


    // ✅ ✅ ✅ NEW METHOD: Get Next Alert (Without Acknowledging)
    public function getNextAlert(Request $request)
    {
        try {
            $currentAlertId = $request->input('current_alert_id');
            
            // Mark current alert as acknowledged if provided
            if ($currentAlertId) {
                $currentAlert = DeviceAccessLog::find($currentAlertId);
                if ($currentAlert) {
                    $currentAlert->acknowledge = 1;
                    $currentAlert->save();
                }
            }
            
            // Next alert get karein
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

            // 1. Find Turnstile locations
            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);

            Log::info('Turnstile locations found:', $turnstileLocations->toArray());

            if ($turnstileLocations->isEmpty()) {
                Log::info('No Turnstile locations found.');
                return 0;
            }

            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');

            // 2. device_location_assigns → check_out devices
            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_out')
                ->get(['id', 'device_id', 'location_id', 'is_type']);

            Log::info('Device location assigns found:', $deviceLocationAssigns->toArray());

            if ($deviceLocationAssigns->isEmpty()) {
                Log::info('No device assigns found with check_out.');
                return 0;
            }

            // 3. Get actual device_ids from device_connections
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

            // 4. Count access logs for today with location match
            $today = now()->format('Y-m-d');

            Log::info("Checking logs for date: {$today}");
            Log::info('Matching location names: ', ['locations' => $turnstileLocationNames->implode(', ')]);

            // Sample logs with BOTH matches
            $sampleLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
                ->whereIn('location_name', $turnstileLocationNames)
                ->whereDate('created_at', $today)
                ->take(5)
                ->get(['id', 'device_id', 'location_name', 'staff_no', 'created_at']);

            Log::info('Sample access logs (with location match):', $sampleLogs->toArray());

            // Actual count
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


    // ✅ NEW METHOD: Enriched Denied Access Logs with Java API Data
    private function getEnrichedDeniedAccessLogs($deniedAccessLogs)
    {
        $enrichedLogs = [];

        foreach ($deniedAccessLogs as $log) {
            try {
                $javaApiResponse = $this->callJavaVendorApi($log->staff_no);

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
                            'fullName' => 'N/A',
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
                        'fullName' => 'N/A',
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

    // ✅ NAYA METHOD: Sabhi visitors ke liye Java API data ek sath fetch karein
    private function getVisitorsOnSiteWithJavaData($visitors)
    {
        foreach ($visitors as &$visitor) {
            try {
                $javaApiResponse = $this->callJavaVendorApi($visitor['staff_no']);

                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $data = $javaApiResponse['data'];

                    // fullName fix
                    $visitor['full_name'] = $data['fullName'] 
                    ?? $data['name'] 
                    ?? 'N/A';

                    // host fix
                    $visitor['person_visited'] = $data['personVisited']
                    ?? $data['visitedPerson']
                    ?? 'N/A';

                } else {
                    $visitor['full_name'] = 'N/A';
                    $visitor['person_visited'] = 'N/A';
                }
            } catch (\Exception $e) {
                Log::error('Java API error for staff_no ' . $visitor['staff_no'] . ': ' . $e->getMessage());
                $visitor['full_name'] = 'N/A';
                $visitor['person_visited'] = 'N/A';
            }
        }

        return $visitors;
    }

    // ✅ UPDATED METHOD: SABHI device_access_logs users ke liye overstay check karein
private function getAllVisitorOverstayAlerts($allDeviceUsers)
{
    $overstayAlerts = [];
    $currentTime = now();

    foreach ($allDeviceUsers as $user) {
        try {
            if (empty($user['location_name'])) {
                continue; // Skip if location_name is empty
            }

            $javaApiResponse = $this->callJavaVendorApi($user['staff_no']);
            
            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                $visitorData = $javaApiResponse['data'];
                
                Log::info('Java API Response for ' . $user['staff_no'] . ': ', $javaApiResponse);
                
                // ✅ Check karein ke dateOfVisitTo current time se zyada hai ya nahi
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
                            'current_time' => $currentTime->format('d M Y h:i A'), // Full date time format
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

    // ✅ NEW METHOD: Enriched Overstay Alerts for modal
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

    // ✅ Naya method: Java Vendor API call ke liye
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IkE0SnBqekdyIiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJNYXN0ZXJQYXRoIiwiVlBDTCIsIlJQUE0iLCJteVBQIiwiQ05DVlBSTCIsIkxDSW5mIiwiTUxPR0lOIiwiQ1BSTGVnIiwiQ05DVlBBTCIsIlJvbGUiLCJWUiIsIkNQUkxEQSIsIlBQR0kiLCJDcG5QIiwiTlNDUiIsIkJSQ29uZiIsIkNQUkxEUiIsIkNQUkxEVSIsIkRJbmYiLCJCSVJMIiwiUlBQUyIsIkNOQ1ZQQ0wiLCJCSUNMIiwiUFBJTCIsIlBQT1dJRXgiLCJDUEFMREEiLCJSUkNvbmYiLCJWUEludkwiLCJMQ2xhc3MiLCJWUFJlakwiLCJCSVJMQXBwciIsIlJQQlIiLCJQUFN1c0wiLCJDUFJEQXBwIiwiQ1BBTERVIiwiQ05DVlBSZWpMRGVsIiwiQ1BBTERSIiwiQVBQQ29uZiIsIkNQQUwiLCJteVZQIiwiQlR5cGUiLCJDaENvbSIsIlZpblR5cGUiLCJkYXNoMSIsIkRFU0luZiIsIkNQUlNPIiwiQ1BSTCIsIkNQUkgiLCJDTkNWUENsb0xEZWwiLCJSVlNTIiwiU0xDSW5mIiwiQ1BDTCIsIm15Q05DVlAiLCJTUFAiLCJDUFJMRURSIiwiTFZDSW5mIiwiQ1BSTEVEVSIsIlBQUmVqTCIsIkNhdGVJbmYiLCJDTkNWUFJlakwiLCJtVlJQIiwiVXNlciIsIkJDUkxBcHByIiwiTVZUIiwiU1BQRFQiLCJMSW5mIiwiQ1BSTEVEQSIsIlBQUEwiLCJTdGF0ZUluZiIsIlBQQUhUQyIsIlBQT1dJIiwiUkNQMiIsIlBQRVRDIiwiQ1RQIl0sInJvbGUiOlsiU1VQRVIgQURNSU4iXSwiY3JlYXRlZCI6MTc2NDkxOTA0OTQzNSwiZGlzcGxheU5hbWUiOiJTdXBlciBBZG1pbiIsImV4cCI6MTc2NTAwNTQ0OX0.rHVS-IvaZdmEfxfjRuB-CpwWP9tNIgZwxQh-Ly10NzAz2RC-73DaDGcHfuCwTNy2VHwY3WHMhjdqSWURBL3d0w'; // Aapka Java token
            
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(10)
              ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . $staffNo);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Java API error for staff_no ' . $staffNo . ': ' . $response->status());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Java API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
            return null;
        }
    }

    // ✅ Naya method: Hourly traffic data ke liye
    private function getHourlyTrafficData()
    {
        try {
            // Aaj ke din ke liye data lein
            $today = now()->format('Y-m-d');
            
            // ✅ Pehle aaj ke saare successful access logs lein
            $todayAccessLogs = DeviceAccessLog::whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at')
                ->get();
            
            // ✅ 24 hours ke liye array prepare karein
            $cumulativeData = [];
            $labels = [];
            
            for ($i = 0; $i < 24; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
                
                $labels[] = $timeLabel;
                
                // ✅ Current hour tak ke saare active visitors count karein
                $currentHourEnd = Carbon::createFromFormat('Y-m-d H', $today . ' ' . $i);
                
                // ✅ Us hour tak jitne bhi visitors check-in kar chuke hain, woh cumulative count
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

    // ✅ Default data agar koi error ho
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

            // Validate dates
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

            // Get graph data based on date range
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
            // Agar fromDate aur toDate same hai, toh hourly data show karein
            if ($fromDate === $toDate) {
                return $this->getHourlyDataForSingleDay($fromDate);
            }
            
            // Agar different dates hain, toh daily data show karein
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
        
        // 24 hours ke liye data prepare karein
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
            
            $labels[] = $timeLabel;
            
            // Current hour ke liye count karein
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
            
            // Current date ke liye count karein
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

    // ✅ ✅ ✅ NEW METHOD: Get Dynamic Check-outs Today Data for Modal
    private function getCheckoutsTodayModalData()
    {
        try {
            Log::info('=== Starting getCheckoutsTodayModalData ===');
            
            $today = now()->format('Y-m-d');
            $checkoutRecords = [];

            // Step 1: Get all Turnstile locations from vendor_locations
            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);
            
            Log::info('Turnstile locations found:', $turnstileLocations->toArray());
            
            if ($turnstileLocations->isEmpty()) {
                Log::info('No Turnstile locations found.');
                return [];
            }
            
            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');

            // Step 2: Get device_location_assigns records with check_out type for these locations
            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_out')
                ->get(['id', 'device_id', 'location_id', 'is_type']);
            
            Log::info('Device location assigns (check_out) found:', $deviceLocationAssigns->toArray());
            
            if ($deviceLocationAssigns->isEmpty()) {
                Log::info('No device_location_assigns with check_out type found.');
                return [];
            }
            
            // Step 3: Get device_connections for these device_ids
            $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');
            
            $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
                ->get(['id', 'device_id']);
            
            Log::info('Device connections found:', $deviceConnections->toArray());
            
            if ($deviceConnections->isEmpty()) {
                Log::info('No device connections found.');
                return [];
            }
            
            $actualDeviceIds = $deviceConnections->pluck('device_id');
            
            // Condition 2: location_name matches Turnstile locations
            $todayCheckoutLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
                ->whereIn('location_name', $turnstileLocationNames)
                ->whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get();
            
            Log::info('Today checkout logs found (after both checks): ' . $todayCheckoutLogs->count());
            
            // Step 5: Process each checkout record
            foreach ($todayCheckoutLogs as $checkoutLog) {
                try {
                    $visitorDetails = $this->getVisitorDetailsForCheckout($checkoutLog);
                    
                    // ✅ NEW: Use complex logic for check-in time
                    $checkInLog = $this->getCheckinLogForStaffNo($checkoutLog->staff_no, $today);
                    
                    // Calculate duration
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

    // ✅ NAYA METHOD: Get check-in log using your complex logic
    private function getCheckinLogForStaffNo($staffNo, $date)
    {
        try {
            // 1. Get Turnstile locations
            $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
                ->get(['id', 'name']);
            
            if ($turnstileLocations->isEmpty()) {
                return null;
            }
            
            $turnstileLocationIds = $turnstileLocations->pluck('id');
            $turnstileLocationNames = $turnstileLocations->pluck('name');
            
            // 2. Get device_location_assigns with check_in type
            $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
                ->where('is_type', 'check_in')
                ->get(['id', 'device_id', 'location_id']);
            
            if ($deviceLocationAssigns->isEmpty()) {
                return null;
            }
            // 3. Get device_connections for these device_ids
            $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');
            
            $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
                ->get(['id', 'device_id']);
            
            if ($deviceConnections->isEmpty()) {
                return null;
            }
            
            $actualDeviceIds = $deviceConnections->pluck('device_id');
            
            // 4. Now get the device_access_logs that match ALL conditions:
            $checkinLog = DeviceAccessLog::where('staff_no', $staffNo)
                ->whereIn('device_id', $actualDeviceIds)  // ✅ From check_in devices
                ->whereIn('location_name', $turnstileLocationNames)  // ✅ Turnstile locations
                ->whereDate('created_at', $date)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'asc')  // ✅ Earliest check-in
                ->first();
            
            return $checkinLog;
            
        } catch (\Exception $e) {
            Log::error('Error in getCheckinLogForStaffNo: ' . $e->getMessage());
            return null;
        }
    }

    // ✅ HELPER METHOD: Get visitor details for checkout
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
    // ✅ HELPER METHOD: Calculate checkout duration
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

