<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\DeviceAccessLog;
use App\Models\VendorLocation;
use Carbon\Carbon;

class VisitorInfoByDoorController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    /**
     * Main page to show dropdown and results
     */
    public function index()
    {
        try {
            // Get filtered angular menu
            $angularMenu = $this->menuService->getFilteredAngularMenu();
            
            // Get all vendor locations for dropdown
            $locations = VendorLocation::orderBy('name', 'asc')->get(['id', 'name']);
            
            return view('visitorInfoByDoor.visitorInfoByDoor', compact('angularMenu', 'locations'));
            
        } catch (\Exception $e) {
            Log::error('VisitorInfoByDoorController index error: ' . $e->getMessage());
            
            $angularMenu = [];
            $locations = [];
            
            return view('visitorInfoByDoor.visitorInfoByDoor', compact('angularMenu', 'locations'));
        }
    }

    /**
     * Fetch visitor data by selected location and date
     */
    public function getVisitorsByLocation(Request $request)
    {
        try {
            $locationName = $request->input('location_name');
            $selectedDate = $request->input('selected_date');
            
            if (!$locationName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a location'
                ], 400);
            }

            if (!$selectedDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a date'
                ], 400);
            }

            // Parse the selected date
            $date = Carbon::parse($selectedDate)->format('Y-m-d');
            
            // Fetch access logs for the selected location and date
            $accessLogs = DeviceAccessLog::where('location_name', $locationName)
                ->where('access_granted', 1)
                ->whereDate('created_at', $date)
                ->orderBy('created_at', 'desc')
                ->get();

            $visitors = [];
            $uniqueStaffNos = [];

            foreach ($accessLogs as $log) {
                try {
                    // Skip if we already processed this staff_no for this location and date
                    if (in_array($log->staff_no, $uniqueStaffNos)) {
                        continue;
                    }

                    $uniqueStaffNos[] = $log->staff_no;

                    // Call Java API for visitor details
                    $javaApiResponse = $this->callJavaVendorApi($log->staff_no);

                    if ($javaApiResponse && isset($javaApiResponse['data'])) {
                        $visitorData = $javaApiResponse['data'];
                        
                        // Get the latest check-in time for this staff_no at this location on selected date
                        $latestCheckIn = DeviceAccessLog::where('staff_no', $log->staff_no)
                            ->where('location_name', $locationName)
                            ->where('access_granted', 1)
                            ->whereDate('created_at', $date)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        $visitors[] = [
                            'staff_no' => $log->staff_no,
                            'visitor_name' => $visitorData['fullName'] ?? 'N/A',
                            'person_visited' => $visitorData['personVisited'] ?? 'N/A',
                            'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                            'ic_no' => $visitorData['icNo'] ?? 'N/A',
                            'sex' => $visitorData['sex'] ?? 'N/A',
                            'date_of_visit_from' => isset($visitorData['dateOfVisitFrom']) 
                                ? Carbon::parse($visitorData['dateOfVisitFrom'])->format('d M Y h:i A') 
                                : 'N/A',
                            'date_of_visit_to' => isset($visitorData['dateOfVisitTo']) 
                                ? Carbon::parse($visitorData['dateOfVisitTo'])->format('d M Y h:i A') 
                                : 'N/A',
                            'check_in_time' => $latestCheckIn ? $latestCheckIn->created_at->format('d M Y h:i A') : 'N/A',
                            'device_id' => $log->device_id ?? 'N/A',
                            'location_name' => $log->location_name ?? 'N/A',
                            'access_granted' => $log->access_granted ? 'Yes' : 'No',
                            'log_id' => $log->id
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing log for staff_no ' . $log->staff_no . ': ' . $e->getMessage());
                    continue;
                }
            }

            return response()->json([
                'success' => true,
                'visitors' => $visitors,
                'count' => count($visitors),
                'location' => $locationName,
                'date' => Carbon::parse($selectedDate)->format('d M Y'),
                'timestamp' => now()->format('d M Y h:i A')
            ]);

        } catch (\Exception $e) {
            Log::error('VisitorInfoByDoorController getVisitorsByLocation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching visitor data: ' . $e->getMessage(),
                'visitors' => []
            ], 500);
        }
    }

    /**
     * Get detailed visitor information for modal/view
     */
    public function getVisitorDetails(Request $request)
    {
        try {
            $staffNo = $request->input('staff_no');
            
            if (!$staffNo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff number is required'
                ], 400);
            }

            // Call Java API for detailed visitor information
            $javaApiResponse = $this->callJavaVendorApi($staffNo);

            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                $visitorData = $javaApiResponse['data'];
                
                // Get all access logs for this staff_no (currently commented out)
                /*
                $accessLogs = DeviceAccessLog::where('staff_no', $staffNo)
                    ->orderBy('created_at', 'desc')
                    ->get(['id', 'device_id', 'location_name', 'access_granted', 'created_at', 'reason']);

                $formattedLogs = [];
                foreach ($accessLogs as $log) {
                    $formattedLogs[] = [
                        'device_id' => $log->device_id,
                        'location_name' => $log->location_name,
                        'access_granted' => $log->access_granted ? 'Yes' : 'No',
                        'access_time' => $log->created_at->format('d M Y h:i A'),
                        'reason' => $log->reason ?? 'N/A'
                    ];
                }
                */

                return response()->json([
                    'success' => true,
                    'visitor' => [
                        'staff_no' => $staffNo,
                        'full_name' => $visitorData['fullName'] ?? 'N/A',
                        'person_visited' => $visitorData['personVisited'] ?? 'N/A',
                        'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                        'ic_no' => $visitorData['icNo'] ?? 'N/A',
                        'sex' => $visitorData['sex'] ?? 'N/A',
                        'date_of_visit_from' => isset($visitorData['dateOfVisitFrom']) 
                            ? Carbon::parse($visitorData['dateOfVisitFrom'])->format('d M Y h:i A') 
                            : 'N/A',
                        'date_of_visit_to' => isset($visitorData['dateOfVisitTo']) 
                            ? Carbon::parse($visitorData['dateOfVisitTo'])->format('d M Y h:i A') 
                            : 'N/A',
                        'email' => $visitorData['email'] ?? 'N/A',
                        'company_name' => $visitorData['companyName'] ?? 'N/A',
                        'purpose_of_visit' => $visitorData['purposeOfVisit'] ?? 'N/A'
                    ],
                    'access_logs' => [], // Empty array for now (commented out)
                    'total_logs' => 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor details not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('VisitorInfoByDoorController getVisitorDetails error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching visitor details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export visitor data to CSV with date filter
     */
    public function exportVisitors(Request $request)
    {
        try {
            $locationName = $request->input('location_name');
            $selectedDate = $request->input('selected_date');
            
            if (!$locationName) {
                return redirect()->back()->with('error', 'Please select a location first.');
            }

            if (!$selectedDate) {
                return redirect()->back()->with('error', 'Please select a date.');
            }

            $date = Carbon::parse($selectedDate)->format('Y-m-d');

            // Fetch access logs for the selected location and date
            $accessLogs = DeviceAccessLog::where('location_name', $locationName)
                ->where('access_granted', 1)
                ->whereDate('created_at', $date)
                ->orderBy('created_at', 'desc')
                ->get();

            $visitors = [];
            $uniqueStaffNos = [];

            foreach ($accessLogs as $log) {
                if (in_array($log->staff_no, $uniqueStaffNos)) {
                    continue;
                }

                $uniqueStaffNos[] = $log->staff_no;

                // Call Java API for visitor details
                $javaApiResponse = $this->callJavaVendorApi($log->staff_no);

                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $visitorData = $javaApiResponse['data'];
                    
                    $visitors[] = [
                        'Staff No' => $log->staff_no,
                        'Visitor Name' => $visitorData['fullName'] ?? 'N/A',
                        'Person Visited' => $visitorData['personVisited'] ?? 'N/A',
                        'Contact No' => $visitorData['contactNo'] ?? 'N/A',
                        'IC No' => $visitorData['icNo'] ?? 'N/A',
                        'Visit Date From' => isset($visitorData['dateOfVisitFrom']) 
                            ? Carbon::parse($visitorData['dateOfVisitFrom'])->format('d M Y h:i A') 
                            : 'N/A',
                        'Visit Date To' => isset($visitorData['dateOfVisitTo']) 
                            ? Carbon::parse($visitorData['dateOfVisitTo'])->format('d M Y h:i A') 
                            : 'N/A',
                        'Location' => $log->location_name ?? 'N/A',
                        'Device ID' => $log->device_id ?? 'N/A',
                        'Check-in Time' => $log->created_at->format('d M Y h:i A'),
                        'Access Granted' => $log->access_granted ? 'Yes' : 'No'
                    ];
                }
            }

            // Generate CSV
            $filename = 'visitor_info_' . str_replace(' ', '_', $locationName) . '_' . $date . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($visitors) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fwrite($file, "\xEF\xBB\xBF");
                
                // Headers
                if (!empty($visitors)) {
                    fputcsv($file, array_keys($visitors[0]));
                }
                
                // Data
                foreach ($visitors as $visitor) {
                    fputcsv($file, $visitor);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('VisitorInfoByDoorController exportVisitors error: ' . $e->getMessage());
            
            return redirect()->back()->with('error', 'Error exporting data: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to call Java API (same as in DashboardController)
     */
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token'); 
            
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

