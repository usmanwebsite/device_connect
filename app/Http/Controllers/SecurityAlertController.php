<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceAccessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use App\Services\MenuService;
use Carbon\Carbon;

class SecurityAlertController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Get Java token from session
     */
    private function getJavaToken()
    {
        // First try to get from session
        $token = Session::get('java_token');
        
        if ($token) {
            Log::info('Using Java token from session', [
                'token_length' => strlen($token),
                'username' => Session::get('java_username')
            ]);
            return $token;
        }
        
        // Fallback to environment token (for development/backup)
        $envToken = env('JAVA_API_TOKEN');
        
        if ($envToken) {
            Log::warning('Using Java token from environment (session token not found)');
            return $envToken;
        }
        
        Log::error('No Java token available in session or environment');
        throw new \Exception('Java authentication token not available. Please login again.');
    }

    // Java API Call Method - Updated to use session token
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = $this->getJavaToken();
            
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(10)
              ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . $staffNo);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Java API error for staff_no ' . $staffNo . ': ' . $response->status());
                
                // If token expired (401), clear session
                if ($response->status() == 401) {
                    Session::flush();
                    throw new \Exception('Java token expired. Please login again.');
                }
                
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Java API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
            
            // If token error, re-throw to trigger login redirect
            if (strpos($e->getMessage(), 'token') !== false || 
                strpos($e->getMessage(), 'login') !== false) {
                throw $e;
            }
            
            return null;
        }
    }

    // ... rest of your existing methods remain the same ...
    
    public function index()
    {
        // Check if we have Java token
        try {
            $this->getJavaToken();
        } catch (\Exception $e) {
            return redirect('/')->with('error', $e->getMessage());
        }
        
        // Get current user info from session
        $javaUsername = Session::get('java_username');
        $javaDisplayName = Session::get('java_display_name');
        
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $totalIncidents = DB::table('device_access_logs')->where('access_granted', 0)->count();
        
        $unauthorizedAccess = DB::table('device_access_logs')
            ->where('access_granted', 0)
            ->count();
        
        $activeSecurity = DB::table('device_access_logs')
            ->where('access_granted', 0)
            ->where('acknowledge', 0)
            ->count();
        
        // Static data for Unresolved High-Severity
        $unresolvedHighSeverity = 3;

        $alerts = [
            [
                "id" => 1,
                "code" => "INC-724",
                "title" => "Forced Entry",
                "severity" => "Critical",
                "location" => "Gate 4",
                "time" => "1 min ago"
            ],
            [
                "id" => 2,
                "code" => "INC-723",
                "title" => "Unauthorized Access",
                "severity" => "High",
                "location" => "Building B",
                "time" => "2 min ago"
            ],
            [
                "id" => 3,
                "code" => "INC-722",
                "title" => "System Anomaly",
                "severity" => "Medium",
                "location" => "Server Room",
                "time" => "15 min ago"
            ]
        ];

        return view(
            'securityAlerts&Incident.securityAlert&Incident',
            compact(
                'alerts',
                'totalIncidents',
                'unauthorizedAccess',
                'activeSecurity',
                'unresolvedHighSeverity',
                'angularMenu',
                'javaUsername',
                'javaDisplayName'
            )
        );
    }


    public function getDetails($id)
    {
        if ($id == 2) {
            return $this->getUnauthorizedAccessDetails();
        }
        
        $details = [
            ["time" => "10:01 AM", "event" => "Detected", "user" => "System"],
            ["time" => "10:03 AM", "event" => "Verified", "user" => "Security Team"],
            ["time" => "10:05 AM", "event" => "Action Taken", "user" => "Admin"],
            ["time" => "12:05 AM", "event" => "Action Taken", "user" => "Staff"]
        ];

        return response()->json($details);
    }

    private function getUnauthorizedAccessDetails()
    {
        try {
            $unauthorizedLogs = DeviceAccessLog::where('access_granted', 0)
                ->orderBy('created_at', 'desc')
                ->take(50) 
                ->get()
                ->groupBy('staff_no');
            
            $details = [];
            
            foreach ($unauthorizedLogs as $staffNo => $logs) {
                if (empty($staffNo)) {
                    continue;
                }
                
                $javaApiResponse = $this->callJavaVendorApi($staffNo);
                $visitorData = $javaApiResponse['data'] ?? null;
                
                if (!$visitorData) {
                    continue;
                }
                
                $latestLog = $logs->first();
                
                $details[] = [
                    'time' => $latestLog->created_at->format('Y-m-d h:i A'),
                    'event' => 'Unauthorized Access Attempt',
                    'staff_no' => $visitorData['staffNo'] ?? $staffNo,
                    'full_name' => $visitorData['fullName'] ?? 'N/A',
                    'ic_no' => $visitorData['icNo'] ?? 'N/A',
                    'company_name' => $visitorData['companyName'] ?? 'N/A',
                    'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                    'person_visited' => $visitorData['personVisited'] ?? 'N/A',
                    'reason' => $visitorData['reason'] ?? 'N/A',
                    'location' => $latestLog->location_name ?? 'N/A'
                ];
                
                if (count($details) >= 15) {
                    break;
                }
            }
            
            if (empty($details)) {
                $details = [
                    [
                        'time' => 'N/A',
                        'event' => 'No unauthorized access attempts found',
                        'staff_no' => 'N/A',
                        'full_name' => 'N/A',
                        'ic_no' => 'N/A',
                        'company_name' => 'N/A',
                        'contact_no' => 'N/A',
                        'person_visited' => 'N/A',
                        'reason' => 'N/A',
                        'location' => 'N/A'
                    ]
                ];
            }
            
            return response()->json($details);
            
        } catch (\Exception $e) {
            Log::error('Error in getUnauthorizedAccessDetails: ' . $e->getMessage());
            
            return response()->json([
                [
                    'time' => 'Error',
                    'event' => 'Failed to load data',
                    'staff_no' => 'N/A',
                    'full_name' => 'N/A',
                    'ic_no' => 'N/A',
                    'company_name' => 'N/A',
                    'contact_no' => 'N/A',
                    'person_visited' => 'N/A',
                    'reason' => 'N/A',
                    'location' => 'N/A'
                ]
            ]);
        }
    }

}

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use App\Models\DeviceAccessLog;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Http;
// use App\Services\MenuService;
// use Carbon\Carbon;

// class SecurityAlertController extends Controller
// {

//     protected $menuService;

//     public function __construct(MenuService $menuService)
//     {
//         $this->menuService = $menuService;
//     }


//     public function index()
//     {
//         $angularMenu = $this->menuService->getFilteredAngularMenu();
//         $totalIncidents = DB::table('device_access_logs')->where('access_granted', 0)->count();
        
//         $unauthorizedAccess = DB::table('device_access_logs')
//             ->where('access_granted', 0)
//             ->count();
        
//         $activeSecurity = DB::table('device_access_logs')
//             ->where('access_granted', 0)
//             ->where('acknowledge', 0)
//             ->count();
        
//         $unresolvedHighSeverity = 3;

//         $alerts = [
//             [
//                 "id" => 1,
//                 "code" => "INC-724",
//                 "title" => "Forced Entry",
//                 "severity" => "Critical",
//                 "location" => "Gate 4",
//                 "time" => "1 min ago"
//             ],
//             [
//                 "id" => 2,
//                 "code" => "INC-723",
//                 "title" => "Unauthorized Access",
//                 "severity" => "High",
//                 "location" => "Building B",
//                 "time" => "2 min ago"
//             ],
//             [
//                 "id" => 3,
//                 "code" => "INC-722",
//                 "title" => "System Anomaly",
//                 "severity" => "Medium",
//                 "location" => "Server Room",
//                 "time" => "15 min ago"
//             ]
//         ];

//         return view(
//             'securityAlerts&Incident.securityAlert&Incident',
//             compact(
//                 'alerts',
//                 'totalIncidents',
//                 'unauthorizedAccess',
//                 'activeSecurity',
//                 'unresolvedHighSeverity',
//                 'angularMenu'
//             )
//         );
//     }

//     public function getDetails($id)
//     {
//         if ($id == 2) {
//             return $this->getUnauthorizedAccessDetails();
//         }
        
//         $details = [
//             ["time" => "10:01 AM", "event" => "Detected", "user" => "System"],
//             ["time" => "10:03 AM", "event" => "Verified", "user" => "Security Team"],
//             ["time" => "10:05 AM", "event" => "Action Taken", "user" => "Admin"],
//             ["time" => "12:05 AM", "event" => "Action Taken", "user" => "Staff"]
//         ];

//         return response()->json($details);
//     }

//     private function getUnauthorizedAccessDetails()
//     {
//         try {
//             $unauthorizedLogs = DeviceAccessLog::where('access_granted', 0)
//                 ->orderBy('created_at', 'desc')
//                 ->take(50) 
//                 ->get()
//                 ->groupBy('staff_no');
            
//             $details = [];
            
//             foreach ($unauthorizedLogs as $staffNo => $logs) {
//                 if (empty($staffNo)) {
//                     continue;
//                 }
                
//                 $javaApiResponse = $this->callJavaVendorApi($staffNo);
//                 $visitorData = $javaApiResponse['data'] ?? null;
                
//                 if (!$visitorData) {
//                     continue;
//                 }
                
//                 $latestLog = $logs->first();
                
//                 $details[] = [
//                     'time' => $latestLog->created_at->format('Y-m-d h:i A'),
//                     'event' => 'Unauthorized Access Attempt',
//                     'staff_no' => $visitorData['staffNo'] ?? $staffNo,
//                     'full_name' => $visitorData['fullName'] ?? 'N/A',
//                     'ic_no' => $visitorData['icNo'] ?? 'N/A',
//                     'company_name' => $visitorData['companyName'] ?? 'N/A',
//                     'contact_no' => $visitorData['contactNo'] ?? 'N/A',
//                     'person_visited' => $visitorData['personVisited'] ?? 'N/A',
//                     'reason' => $visitorData['reason'] ?? 'N/A',
//                     'location' => $latestLog->location_name ?? 'N/A'
//                 ];
                
//                 if (count($details) >= 15) {
//                     break;
//                 }
//             }
            
//             if (empty($details)) {
//                 $details = [
//                     [
//                         'time' => 'N/A',
//                         'event' => 'No unauthorized access attempts found',
//                         'staff_no' => 'N/A',
//                         'full_name' => 'N/A',
//                         'ic_no' => 'N/A',
//                         'company_name' => 'N/A',
//                         'contact_no' => 'N/A',
//                         'person_visited' => 'N/A',
//                         'reason' => 'N/A',
//                         'location' => 'N/A'
//                     ]
//                 ];
//             }
            
//             return response()->json($details);
            
//         } catch (\Exception $e) {
//             Log::error('Error in getUnauthorizedAccessDetails: ' . $e->getMessage());
            
//             return response()->json([
//                 [
//                     'time' => 'Error',
//                     'event' => 'Failed to load data',
//                     'staff_no' => 'N/A',
//                     'full_name' => 'N/A',
//                     'ic_no' => 'N/A',
//                     'company_name' => 'N/A',
//                     'contact_no' => 'N/A',
//                     'person_visited' => 'N/A',
//                     'reason' => 'N/A',
//                     'location' => 'N/A'
//                 ]
//             ]);
//         }
//     }

//     private function callJavaVendorApi($staffNo)
//     {
//         try {
//             $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
//             $token = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IjI1M29rRUttIiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJNYXN0ZXJQYXRoIiwiVmlzaXRvckQmQyIsIlZQQ0wiLCJSUFBNIiwibXlQUCIsIkNOQ1ZQUkwiLCJMQ0luZiIsIk1MT0dJTiIsIkNQUkxlZyIsIkNOQ1ZQQUwiLCJSb2xlIiwiVlIiLCJDUFJMREEiLCJQUEdJIiwiQ3BuUCIsIk5TQ1IiLCJCUkNvbmYiLCJDUFJMRFIiLCJDUFJMRFUiLCJESW5mIiwiQklSTCIsIlJQUFMiLCJDTkNWUENMIiwiQklDTCIsIlBQSUwiLCJQUE9XSUV4IiwiQ1BBTERBIiwiUlJDb25mIiwiVlBJbnZMIiwiTENsYXNzIiwiVlBSZWpMIiwiQklSTEFwcHIiLCJSUEJSIiwiUFBTdXNMIiwiQ1BSREFwcCIsIkNQQUxEVSIsIkNOQ1ZQUmVqTERlbCIsIkNQQUxEUiIsIkFQUENvbmYiLCJDUEFMIiwibXlWUCIsIkJUeXBlIiwiQ2hDb20iLCJWaW5UeXBlIiwiZGFzaDEiLCJERVNJbmYiLCJDUFJTTyIsIkNQUkwiLCJDUFJIIiwiQ05DVlBDbG9MRGVsIiwiUlZTUyIsIlNMQ0luZiIsIkNQQ0wiLCJteUNOQ1ZQIiwiU1BQIiwiQ1BSTEVEUiIsIkxWQ0luZiIsIkNQUkxFRFUiLCJQUFJlakwiLCJDYXRlSW5mIiwiQ05DVlBSZWpMIiwibVZSUCIsIlVzZXIiLCJCQ1JMQXBwciIsIk1WVCIsIlNQUERUIiwiTEluZiIsIkNQUkxFREEiLCJQUFBMIiwiU3RhdGVJbmYiLCJQUEFIVEMiLCJQUE9XSSIsIlJDUDIiLCJQUEVUQyIsIkNUUCJdLCJyb2xlIjpbIlNVUEVSIEFETUlOIl0sImNyZWF0ZWQiOjE3NjUyNjI2NjkxMzksImRpc3BsYXlOYW1lIjoiU3VwZXIgQWRtaW4iLCJleHAiOjE3NjUzNDkwNjl9.ZHTD6Q5XQsM-8k1Vu9-WIrtvYG2LZs5xIMYs7L8AVpRbRm_p4AkCb69bSOcP65FrN4buo_IMostSb2fg8BuHPg';
            
//             $response = Http::withHeaders([
//                 'x-auth-token' => $token,
//                 'Accept' => 'application/json',
//             ])->timeout(10)
//               ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . $staffNo);

//             if ($response->successful()) {
//                 return $response->json();
//             } else {
//                 Log::error('Java API error for staff_no ' . $staffNo . ': ' . $response->status());
//                 return null;
//             }
//         } catch (\Exception $e) {
//             Log::error('Java API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
//             return null;
//         }
//     }
// }

