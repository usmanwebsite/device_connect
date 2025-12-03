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
            
            // ✅ Active Security Alerts - COUNT ONLY
            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)->count();

            // ✅ DYNAMIC GRAPH DATA: Device access logs se hourly data lein
            $hourlyTrafficData = $this->getHourlyTrafficData();

            // ✅ POINT 2: Visitor Overstay Alerts - SABHI device_access_logs users ke liye check karein
            $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            $visitorOverstayAlerts = $this->getAllVisitorOverstayAlerts($allDeviceUsers);
            $visitorOverstayCount = count($visitorOverstayAlerts);

            // ✅ Denied Access Logs - ALL TIME - COUNT ONLY
            $deniedAccessCount = DeviceAccessLog::where('access_granted', 0)->count();

            // ✅ For old Recent Alerts section (Collection - for ->take() method)
            $deniedAccessLogs = DeviceAccessLog::where('access_granted', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ For new modals (enriched data - array)
            $enrichedDeniedAccessLogs = $this->getEnrichedDeniedAccessLogs($deniedAccessLogs);

            // ✅ For new modals - enriched overstay alerts
            $enrichedOverstayAlerts = $this->getEnrichedOverstayAlerts($visitorOverstayAlerts);
            
            // ✅ ✅ ✅ NEW: Calculate Check-outs Today Count
            $checkOutsTodayCount = $this->getCheckoutsTodayCount();

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
                'checkOutsTodayCount'       // ✅ NEW: Check-outs Today count
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
            $deniedAccessLogs = collect(); // ✅ Empty collection
            $visitorOverstayAlerts = [];
            $enrichedDeniedAccessLogs = [];
            $enrichedOverstayAlerts = [];
            
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
                'deniedAccessLogs',
                'visitorOverstayAlerts',
                'enrichedDeniedAccessLogs',
                'enrichedOverstayAlerts'
            ));
        }
    }

    // ✅ ✅ ✅ NEW METHOD: Calculate Check-outs Today Count
    // private function getCheckoutsTodayCount()
    // {
    //     try {
    //         Log::info('=== Starting getCheckoutsTodayCount ===');
            
    //         // Step 1: Find all Turnstile locations in vendor_locations table
    //         $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
    //             ->get(['id', 'name']);
            
    //         Log::info('Turnstile locations found:', $turnstileLocations->toArray());
            
    //         if ($turnstileLocations->isEmpty()) {
    //             Log::info('No Turnstile locations found in vendor_locations table.');
    //             return 0;
    //         }
            
    //         $turnstileLocationIds = $turnstileLocations->pluck('id');
            
    //         // Step 2: Find device_location_assigns records for these locations with is_type = check_out
    //         $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
    //             ->where('is_type', 'check_out') 
    //             ->get(['id', 'device_id', 'location_id', 'is_type']);
            
    //         Log::info('Device location assigns found:', $deviceLocationAssigns->toArray());
            
    //         if ($deviceLocationAssigns->isEmpty()) {
    //             Log::info('No device_location_assigns records found for Turnstile locations with is_type = check_out.');
    //             return 0;
    //         }
            
    //         $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');
            
    //         // Step 3: Get actual device_ids from device_connections table
    //         $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
    //             ->get(['id', 'device_id']);
            
    //         Log::info('Device connections found:', $deviceConnections->toArray());
            
    //         if ($deviceConnections->isEmpty()) {
    //             Log::info('No actual device IDs found in device_connections table.');
    //             return 0;
    //         }
            
    //         $actualDeviceIds = $deviceConnections->pluck('device_id');
            
    //         Log::info('Actual device IDs for checkout devices: ' . $actualDeviceIds->implode(', '));
            
    //         // Step 4: Count device_access_logs for today from these devices
    //         $today = now()->format('Y-m-d');
            
    //         Log::info("Looking for logs from date: {$today}");
            
    //         // First, let's see what we're finding
    //         $sampleLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
    //             ->whereDate('created_at', $today)
    //             ->where('access_granted', 1)
    //             ->take(5)
    //             ->get(['id', 'device_id', 'staff_no', 'created_at']);
            
    //         Log::info('Sample access logs found:', $sampleLogs->toArray());
            
    //         $checkoutsCount = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
    //             ->whereDate('created_at', $today)
    //             // ->where('access_granted', 1)
    //             ->count();
            
    //         Log::info("Checkouts today count: {$checkoutsCount}");
    //         Log::info('=== End getCheckoutsTodayCount ===');
            
    //         return $checkoutsCount;
            
    //     } catch (\Exception $e) {
    //         Log::error('Error calculating checkouts today count: ' . $e->getMessage());
    //         Log::error('Stack trace: ' . $e->getTraceAsString());
    //         return 0;
    //     }
    // }

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

            // ✅ Java API call karein staff_no ke liye
            $javaApiResponse = $this->callJavaVendorApi($user['staff_no']);
            
            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                $visitorData = $javaApiResponse['data'];
                
                // ✅ DEBUG: Log the API response for checking
                Log::info('Java API Response for ' . $user['staff_no'] . ': ', $javaApiResponse);
                
                // ✅ Check karein ke dateOfVisitTo current time se zyada hai ya nahi
                if (isset($visitorData['dateOfVisitTo'])) {
                    $dateOfVisitTo = \Carbon\Carbon::parse($visitorData['dateOfVisitTo']);
                    
                    // ✅ IMPORTANT: Sirf dateOfVisitTo ko current time se compare karein
                    // dateOfVisitFrom ki date condition REMOVE kar di
                    if ($currentTime->greaterThan($dateOfVisitTo)) {
                        // ✅ Agar current time dateOfVisitTo se zyada hai, toh overstay alert banayein
                        $overstayMinutes = $currentTime->diffInMinutes($dateOfVisitTo);
                        $overstayHours = floor($overstayMinutes / 60);
                        $remainingMinutes = $overstayMinutes % 60;
                        
                        $overstayAlerts[] = [
                            'visitor_name' => $visitorData['fullName'] ?? 'N/A',
                            'staff_no' => $user['staff_no'],
                            'expected_end_time' => $dateOfVisitTo->format('d M Y h:i A'), // Full date time format
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
            $token = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IjI1bkp1UzFPIiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJNYXN0ZXJQYXRoIiwiVlBDTCIsIlJQUE0iLCJteVBQIiwiQ05DVlBSTCIsIkxDSW5mIiwiTUxPR0lOIiwiQ1BSTGVnIiwiQ05DVlBBTCIsIlJvbGUiLCJWUiIsIkNQUkxEQSIsIlBQR0kiLCJDcG5QIiwiTlNDUiIsIkJSQ29uZiIsIkNQUkxEUiIsIkNQUkxEVSIsIkRJbmYiLCJCSVJMIiwiUlBQUyIsIkNOQ1ZQQ0wiLCJCSUNMIiwiUFBJTCIsIlBQT1dJRXgiLCJDUEFMREEiLCJSUkNvbmYiLCJWUEludkwiLCJMQ2xhc3MiLCJWUFJlakwiLCJCSVJMQXBwciIsIlJQQlIiLCJQUFN1c0wiLCJDUFJEQXBwIiwiQ1BBTERVIiwiQ05DVlBSZWpMRGVsIiwiQ1BBTERSIiwiQVBQQ29uZiIsIkNQQUwiLCJteVZQIiwiQlR5cGUiLCJDaENvbSIsIlZpblR5cGUiLCJkYXNoMSIsIkRFU0luZiIsIkNQUlNPIiwiQ1BSTCIsIkNQUkgiLCJDTkNWUENsb0xEZWwiLCJSVlNTIiwiU0xDSW5mIiwiQ1BDTCIsIm15Q05DVlAiLCJTUFAiLCJDUFJMRURSIiwiTFZDSW5mIiwiQ1BSTEVEVSIsIlBQUmVqTCIsIkNhdGVJbmYiLCJDTkNWUFJlakwiLCJtVlJQIiwiVXNlciIsIkJDUkxBcHByIiwiU1BQRFQiLCJMSW5mIiwiQ1BSTEVEQSIsIlBQUEwiLCJTdGF0ZUluZiIsIlBQQUhUQyIsIlBQT1dJIiwiUkNQMiIsIlBQRVRDIiwiQ1RQIl0sInJvbGUiOlsiU1VQRVIgQURNSU4iXSwiY3JlYXRlZCI6MTc2NDc1MDk4Njc0MiwiZGlzcGxheU5hbWUiOiJTdXBlciBBZG1pbiIsImV4cCI6MTc2NDgzNzM4Nn0.AuU4FU6iXRWGAV2adt-aXlD4xbJnRd_EWVtqFikvf-SXCvXyrCqdL4n4t6w9imuZEFuqpK51YbWFY2fzYKJH7A'; // Aapka Java token
            
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
}

