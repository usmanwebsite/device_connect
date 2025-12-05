<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceAccessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\MenuService;
use Carbon\Carbon;

class SecurityAlertController extends Controller
{

    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }


    public function index()
    {
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
                'angularMenu'
            )
        );
    }

    public function getDetails($id)
    {
        // Check if this is the Unauthorized Access incident (id = 2)
        if ($id == 2) {
            // Return dynamic unauthorized access data with API response details
            return $this->getUnauthorizedAccessDetails();
        }
        
        // For other incidents, return static data
        $details = [
            ["time" => "10:01 AM", "event" => "Detected", "user" => "System"],
            ["time" => "10:03 AM", "event" => "Verified", "user" => "Security Team"],
            ["time" => "10:05 AM", "event" => "Action Taken", "user" => "Admin"],
            ["time" => "12:05 AM", "event" => "Action Taken", "user" => "Staff"]
        ];

        return response()->json($details);
    }

    // Get unauthorized access details with Java API response (ALL TIME DATA)
    private function getUnauthorizedAccessDetails()
    {
        try {
            // Get ALL unauthorized access logs (NO 24H restriction)
            $unauthorizedLogs = DeviceAccessLog::where('access_granted', 0)
                ->orderBy('created_at', 'desc')
                ->take(50) // Increased limit for overall data
                ->get()
                ->groupBy('staff_no');
            
            $details = [];
            
            foreach ($unauthorizedLogs as $staffNo => $logs) {
                if (empty($staffNo)) {
                    continue;
                }
                
                // Call Java API for each staff_no
                $javaApiResponse = $this->callJavaVendorApi($staffNo);
                $visitorData = $javaApiResponse['data'] ?? null;
                
                if (!$visitorData) {
                    continue;
                }
                
                $latestLog = $logs->first();
                
                // Extract 5-6 important fields from API response
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
                
                // Limit to 15 records for display
                if (count($details) >= 15) {
                    break;
                }
            }
            
            // If no data found, return a message
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
            
            // Return error message
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

    // Java API Call Method
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJzdXBlcmFkbWluIiwiYXV0aEtleSI6IkE0SnBqekdyIiwiY29tcGFueUlkIjoic3VwZXJhZG1pbiIsImFjY2VzcyI6WyJQUEFIVENEIiwiUFBBSFRDRSIsIlZQUmVxTCIsIlJUeXBlIiwiQnJyQ29uZiIsIlZQQ2xvTERlbCIsIlBQQUwiLCJDcG5JbmYiLCJSUEJSRXgiLCJDUENMVkEiLCJQUEFIVENNIiwiVlBQTCIsIlBQUkwiLCJDVENvbmYiLCJCQ1JMIiwiQk5hbWUiLCJXSExDb25mIiwiUFBHSUV4IiwiUkNQIiwiUlBQTUciLCJCSUNMUmVsIiwiUFBDTCIsIkJDQ0xSZWwiLCJWUEFMIiwiY1ZBIiwiUFBFVENNIiwiUFBVIiwiUFBFVENFIiwiUFBFVENEIiwiVlBSTCIsIkNpdHlJbmYiLCJNR0lPIiwiQ1BSTEUiLCJzVlAiLCJWUFJlakxEZWwiLCJCQ0NMIiwiUFBTTCIsIkNJbmYiLCJNYXN0ZXJQYXRoIiwiVlBDTCIsIlJQUE0iLCJteVBQIiwiQ05DVlBSTCIsIkxDSW5mIiwiTUxPR0lOIiwiQ1BSTGVnIiwiQ05DVlBBTCIsIlJvbGUiLCJWUiIsIkNQUkxEQSIsIlBQR0kiLCJDcG5QIiwiTlNDUiIsIkJSQ29uZiIsIkNQUkxEUiIsIkNQUkxEVSIsIkRJbmYiLCJCSVJMIiwiUlBQUyIsIkNOQ1ZQQ0wiLCJCSUNMIiwiUFBJTCIsIlBQT1dJRXgiLCJDUEFMREEiLCJSUkNvbmYiLCJWUEludkwiLCJMQ2xhc3MiLCJWUFJlakwiLCJCSVJMQXBwciIsIlJQQlIiLCJQUFN1c0wiLCJDUFJEQXBwIiwiQ1BBTERVIiwiQ05DVlBSZWpMRGVsIiwiQ1BBTERSIiwiQVBQQ29uZiIsIkNQQUwiLCJteVZQIiwiQlR5cGUiLCJDaENvbSIsIlZpblR5cGUiLCJkYXNoMSIsIkRFU0luZiIsIkNQUlNPIiwiQ1BSTCIsIkNQUkgiLCJDTkNWUENsb0xEZWwiLCJSVlNTIiwiU0xDSW5mIiwiQ1BDTCIsIm15Q05DVlAiLCJTUFAiLCJDUFJMRURSIiwiTFZDSW5mIiwiQ1BSTEVEVSIsIlBQUmVqTCIsIkNhdGVJbmYiLCJDTkNWUFJlakwiLCJtVlJQIiwiVXNlciIsIkJDUkxBcHByIiwiTVZUIiwiU1BQRFQiLCJMSW5mIiwiQ1BSTEVEQSIsIlBQUEwiLCJTdGF0ZUluZiIsIlBQQUhUQyIsIlBQT1dJIiwiUkNQMiIsIlBQRVRDIiwiQ1RQIl0sInJvbGUiOlsiU1VQRVIgQURNSU4iXSwiY3JlYXRlZCI6MTc2NDkxOTA0OTQzNSwiZGlzcGxheU5hbWUiOiJTdXBlciBBZG1iIG4iLCJleHAiOjE3NjUwMDU0NDl9.rHVS-IvaZdmEfxfjRuB-CpwWP9tNIgZwxQh-Ly10NzAz2RC-73DaDGcHfuCwTNy2VHwY3WHMhjdqSWURBL3d0w';
            
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
}

