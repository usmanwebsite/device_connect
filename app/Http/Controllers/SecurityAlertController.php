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
    protected $javaBaseUrl;
    protected $APP_URL;
    protected $domain;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;

        $this->domain = request()->getHost();
        $this->APP_URL  = env('JAVA_BACKEND_URL','http://localhost');
        $this->javaBaseUrl = 'http://' . $this->domain . ':8080';
    }

    /**
     * Get Java token from session
     */
    private function getJavaToken()
    {

        $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
        
        if ($token) {
            Log::info('Using Java token from session', [
                'token_length' => strlen($token),
                'username' => Session::get('java_username')
            ]);
            return $token;
        }
        
        // $envToken = env('JAVA_API_TOKEN');
        $envToken = session()->get('java_backend_token') ?? session()->get('java_auth_token');
        
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
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token'); 
            
            Log::info('=== SecurityAlertController Java API Call Debug ===');
            Log::info('Staff No: ' . $staffNo);
            Log::info('Java Base URL: ' . $javaBaseUrl);
            Log::info('Token exists: ' . ($token ? 'Yes' : 'No'));
            
            if (!$token) {
                Log::error('Java API Token missing in session!');
                return ['status' => 'ERROR', 'message' => 'Token missing'];
            }
            
            $url = $javaBaseUrl . '/api/vendorpass/get-visitor-details?icNo=' . urlencode($staffNo);
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
                
                // ✅ Check if Java API returned ERROR status
                if (isset($data['status']) && $data['status'] === 'ERROR') {
                    Log::error('Java API returned ERROR status: ' . ($data['message'] ?? 'Unknown error'));
                    return ['status' => 'ERROR', 'message' => $data['message'] ?? 'Token expired'];
                }
                
                return $data;
            } else {
                Log::error('Java API error: ' . $response->status());
                
                // ✅ Check if 401 Unauthorized
                if ($response->status() == 401) {
                    Log::error('Java API returned 401 Unauthorized');
                    return ['status' => 'ERROR', 'message' => 'Unauthorized'];
                }
                
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Java API exception: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    
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

        $totalIncidents = ""; // Hide count

        $unresolvedHighSeverity = ""; // Hide count

        $activeSecurity = $this->getOverstayVisitorsCount();
        
        $unauthorizedAccess = DB::table('device_access_logs')
            ->where('access_granted', 0)
            ->count();
        
        $activeSecurity = DB::table('device_access_logs')
            ->where('access_granted', 0)
            ->where('acknowledge', 0)
            ->count();
        
        // Static data for Unresolved High-Severity
        $unresolvedHighSeverity = '';

        $alerts = [
            [
                "id" => 1,
                "code" => "INC-724",
                "title" => "Forced Entry",
                "severity" => "Critical",
                "location" => "Gate 4",
            ],
            [
                "id" => 2,
                "code" => "INC-723",
                "title" => "Unauthorized Access",
                "severity" => "High",
                "location" => "Building B",
            ],
            [
                "id" => 3,
                "code" => "INC-722",
                "title" => "Visitor Overstay",
                "severity" => "Medium",
                "location" => "Server Room",
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


    private function getOverstayVisitorsCount()
    {
        try {
            $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            $overstayAlerts = $this->getUnacknowledgedOverstayAlerts($allDeviceUsers);
            return count($overstayAlerts);
        } catch (\Exception $e) {
            Log::error('Error getting overstay count: ' . $e->getMessage());
            return 0;
        }
    }

    private function getUnacknowledgedOverstayAlerts($allDeviceUsers = null)
    {
        try {
            if (!$allDeviceUsers) {
                $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            }
            
            $currentTime = now();
            $overstayAlerts = [];
            
            foreach ($allDeviceUsers as $user) {
                try {
                    if (empty($user['location_name'])) {
                        continue;
                    }
                    
                    // Skip if already acknowledged
                    if ($user->overstay_acknowledge == 1 || $user->overstay_acknowledge === true) {
                        continue;
                    }
                    
                    // Get API data
                    $javaApiResponse = $this->callJavaVendorApi($user['staff_no']);
                    
                    if ($javaApiResponse && isset($javaApiResponse['data'])) {
                        $visitorData = $javaApiResponse['data'];
                        
                        if (isset($visitorData['dateOfVisitTo'])) {
                            $dateOfVisitTo = Carbon::parse($visitorData['dateOfVisitTo']);
                            
                            if ($currentTime->greaterThan($dateOfVisitTo)) {
                                $overstayAlerts[] = $user;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking overstay for staff_no ' . $user['staff_no'] . ': ' . $e->getMessage());
                    continue;
                }
            }
            
            return $overstayAlerts;
            
        } catch (\Exception $e) {
            Log::error('Error getting unacknowledged overstay alerts: ' . $e->getMessage());
            return [];
        }
    }


    public function getDetails($id)
    {
        if ($id == 1) {
            return $this->getForcedEntryDetails();
        }
        if ($id == 2) {
            return $this->getUnauthorizedAccessDetails();
        }
        if ($id == 3) {
            return $this->getOverstayVisitorsDetails();
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
        // ✅ FIRST check if session is valid
        if (!session()->has('java_backend_token') && !session()->has('java_auth_token')) {
            Log::error('Java token not found in session');
            
            return response()->json([
                'error' => 401,
                'message' => 'Session expired. Please login again.',
                'redirect' => true
            ], 401);
        }
        
        $unauthorizedLogs = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('staff_no');
        
        $details = [];
        
        foreach ($unauthorizedLogs as $staffNo => $logs) {
            if (empty($staffNo)) {
                continue;
            }
            
            $javaApiResponse = $this->callJavaVendorApi($staffNo);
            
            // ✅ Check if Java API returned session error
            if (isset($javaApiResponse['status']) && $javaApiResponse['status'] === 'ERROR') {
                Log::error('Java API returned ERROR, invalidating session');
                
                // Clear only Java-related sessions
                session()->forget(['java_backend_token', 'java_auth_token', 'java_username', 'java_display_name']);
                
                return response()->json([
                    'error' => 401,
                    'message' => 'Session expired. Please login again.',
                    'redirect' => true
                ], 401);
            }
            
            if (!$javaApiResponse || !isset($javaApiResponse['data'])) {
                continue;
            }
            
            $visitorData = $javaApiResponse['data'];
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

private function getForcedEntryDetails()
{
    try {
        // ✅ FIRST check if session is valid (same as unauthorized access)
        if (!session()->has('java_backend_token') && !session()->has('java_auth_token')) {
            Log::error('Java token not found in session - Forced Entry');
            
            return response()->json([
                'error' => 401,
                'message' => 'Session expired. Please login again.',
                'redirect' => true
            ], 401);
        }
        
        $forcedEntryLogs = DeviceAccessLog::where('acknowledge', 1)
            ->where('access_granted', 0) 
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('staff_no');
        
        $details = [];
        
        foreach ($forcedEntryLogs as $staffNo => $logs) {
            if (empty($staffNo)) {
                continue;
            }
            
            $javaApiResponse = $this->callJavaVendorApi($staffNo);

            // ✅ Check if Java API returned session error (same as unauthorized)
            if (isset($javaApiResponse['status']) && $javaApiResponse['status'] === 'ERROR') {
                Log::error('Java API returned ERROR in forced entry, invalidating session');
                
                // Clear only Java-related sessions
                session()->forget(['java_backend_token', 'java_auth_token', 'java_username', 'java_display_name']);
                
                return response()->json([
                    'error' => 401,
                    'message' => 'Session expired. Please login again.',
                    'redirect' => true
                ], 401);
            }

            $visitorData = $javaApiResponse['data'] ?? null;
            
            if (!$visitorData) {
                continue;
            }
            
            $latestLog = $logs->first();
            
            $details[] = [
                'time' => $latestLog->created_at->format('Y-m-d h:i A'),
                'event' => 'Forced Entry Attempt',
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
                    'event' => 'No forced entry attempts found',
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
        Log::error('Error in getForcedEntryDetails: ' . $e->getMessage());
        
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

private function getOverstayVisitorsDetails()
{
    try {
        // ✅ FIRST check if session is valid (same as unauthorized access)
        if (!session()->has('java_backend_token') && !session()->has('java_auth_token')) {
            Log::error('Java token not found in session - Overstay Visitors');
            
            return response()->json([
                'error' => 401,
                'message' => 'Session expired. Please login again.',
                'redirect' => true
            ], 401);
        }
        
        $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
        $overstayAlerts = $this->getUnacknowledgedOverstayAlerts($allDeviceUsers);
        
        $details = [];
        
        foreach ($overstayAlerts as $alert) {
            $javaApiResponse = $this->callJavaVendorApi($alert['staff_no']);

            // ✅ Check if Java API returned session error (same as unauthorized)
            if (isset($javaApiResponse['status']) && $javaApiResponse['status'] === 'ERROR') {
                Log::error('Java API returned ERROR in overstay visitors, invalidating session');
                
                // Clear only Java-related sessions
                session()->forget(['java_backend_token', 'java_auth_token', 'java_username', 'java_display_name']);
                
                return response()->json([
                    'error' => 401,
                    'message' => 'Session expired. Please login again.',
                    'redirect' => true
                ], 401);
            }

            $visitorData = $javaApiResponse['data'] ?? null;
            
            if (!$visitorData) {
                continue;
            }
            
            $currentTime = now();
            $dateOfVisitTo = Carbon::parse($visitorData['dateOfVisitTo']);
            
            // Overstay duration calculate karein
            $overstayMinutes = $currentTime->diffInMinutes($dateOfVisitTo);
            $overstayHours = floor($overstayMinutes / 60);
            $remainingMinutes = $overstayMinutes % 60;
            
            $details[] = [
                'time' => Carbon::parse($alert['created_at'])->format('Y-m-d h:i A'),
                'event' => 'Visitor Overstay',
                'staff_no' => $alert['staff_no'],
                'full_name' => $visitorData['fullName'] ?? 'N/A',
                'ic_no' => $visitorData['icNo'] ?? 'N/A',
                'company_name' => $visitorData['companyName'] ?? 'N/A',
                'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                'person_visited' => $visitorData['personVisited'] ?? 'N/A',
                'reason' => 'Overstay: ' . $overstayHours . 'h ' . $remainingMinutes . 'm',
                'location' => $alert['location_name'] ?? 'N/A',
                'check_in_time' => Carbon::parse($alert['created_at'])->format('h:i A'),
                'expected_end_time' => $dateOfVisitTo->format('d M Y h:i A'),
                'overstay_duration' => $overstayHours . ' hours ' . $remainingMinutes . ' minutes'
            ];
            
            if (count($details) >= 15) {
                break;
            }
        }
        
        if (empty($details)) {
            $details = [
                [
                    'time' => 'N/A',
                    'event' => 'No overstay visitors found',
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
        Log::error('Error in getOverstayVisitorsDetails: ' . $e->getMessage());
        
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

