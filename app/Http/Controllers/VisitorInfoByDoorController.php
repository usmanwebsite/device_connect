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
            Log::info('🔍 VisitorInfoByDoor - Request Data:', $request->all());
            
            $locationName = $request->input('location_name');
            $selectedDate = $request->input('selected_date');
            
            Log::info('📌 Location:', ['name' => $locationName]);
            Log::info('📅 Date:', ['selected' => $selectedDate]);

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
            Log::info('📅 Parsed Date:', ['date' => $date]);
            
            // ✅ DEBUG: Check all available locations
            $allLocations = DeviceAccessLog::distinct()->pluck('location_name');
            Log::info('📍 Available locations in DeviceAccessLog:', $allLocations->toArray());
            
            // ✅ DEBUG: Check location match
            $exactMatch = DeviceAccessLog::where('location_name', $locationName)->exists();
            Log::info('🔍 Location exact match:', ['location' => $locationName, 'exists' => $exactMatch]);
            
            // Check for similar locations
            $similarLocations = DeviceAccessLog::where('location_name', 'like', '%' . $locationName . '%')->distinct()->pluck('location_name');
            Log::info('🔍 Similar locations:', $similarLocations->toArray());

            // Fetch access logs for the selected location and date
            $accessLogs = DeviceAccessLog::where('location_name', $locationName)
                ->where('access_granted', 1)
                ->whereDate('created_at', $date)
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('📊 Access logs found:', [
                'location' => $locationName,
                'date' => $date,
                'count' => $accessLogs->count(),
                'query' => "WHERE location_name = '$locationName' AND DATE(created_at) = '$date' AND access_granted = 1"
            ]);

            // If no exact match, try case-insensitive or partial match
            if ($accessLogs->count() === 0) {
                Log::info('🔄 Trying case-insensitive search...');
                
                // Try case-insensitive search
                $accessLogs = DeviceAccessLog::whereRaw('LOWER(location_name) = ?', [strtolower($locationName)])
                    ->where('access_granted', 1)
                    ->whereDate('created_at', $date)
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
                Log::info('📊 Case-insensitive access logs:', ['count' => $accessLogs->count()]);
            }

            // If still no results, try searching without access_granted filter
            if ($accessLogs->count() === 0) {
                Log::info('🔄 Trying without access_granted filter...');
                
                $accessLogs = DeviceAccessLog::where('location_name', $locationName)
                    ->whereDate('created_at', $date)
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
                Log::info('📊 Access logs (all access types):', ['count' => $accessLogs->count()]);
            }

            // If still no results, try with LIKE operator
            if ($accessLogs->count() === 0) {
                Log::info('🔄 Trying with LIKE operator...');
                
                $accessLogs = DeviceAccessLog::where('location_name', 'like', '%' . $locationName . '%')
                    ->whereDate('created_at', $date)
                    ->orderBy('access_granted', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
                Log::info('📊 Access logs (LIKE search):', ['count' => $accessLogs->count()]);
                
                if ($accessLogs->count() > 0) {
                    Log::info('🔍 Found locations with LIKE:', $accessLogs->pluck('location_name')->unique()->toArray());
                }
            }

            $visitors = [];
            $uniqueStaffNos = [];

            foreach ($accessLogs as $log) {
                try {
                    Log::info('📝 Processing log:', [
                        'id' => $log->id,
                        'staff_no' => $log->staff_no,
                        'location' => $log->location_name,
                        'created_at' => $log->created_at,
                        'access_granted' => $log->access_granted
                    ]);

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
                            'check_in_time' => $latestCheckIn
                            ? Carbon::createFromFormat(
                                'Y-m-d H:i:s',
                                $latestCheckIn->created_at->format('Y-m-d H:i:s'),
                                'Asia/Karachi' // 👈 SOURCE timezone (DB)
                            )
                            ->setTimezone('Asia/Kuala_Lumpur') // 👈 TARGET timezone
                            ->format('d M Y h:i A')
                            : 'N/A',
                            'device_id' => $log->device_id ?? 'N/A',
                            'location_name' => $log->location_name ?? 'N/A',
                            'access_granted' => $log->access_granted ? 'Yes' : 'No',
                            'log_id' => $log->id
                        ];
                        
                        Log::info('✅ Added visitor:', ['staff_no' => $log->staff_no]);
                    } else {
                        Log::warning('⚠️ Java API failed for staff_no:', ['staff_no' => $log->staff_no]);
                        
                        // Fallback: Add basic info
                        $latestCheckIn = DeviceAccessLog::where('staff_no', $log->staff_no)
                            ->where('location_name', $locationName)
                            ->whereDate('created_at', $date)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        $visitors[] = [
                            'staff_no' => $log->staff_no,
                            'visitor_name' => 'Unknown',
                            'person_visited' => 'N/A',
                            'contact_no' => 'N/A',
                            'ic_no' => 'N/A',
                            'sex' => 'N/A',
                            'date_of_visit_from' => 'N/A',
                            'date_of_visit_to' => 'N/A',
                            'check_in_time' => $latestCheckIn
                                ? Carbon::createFromFormat(
                                    'Y-m-d H:i:s',
                                    $latestCheckIn->created_at->format('Y-m-d H:i:s'),
                                    'Asia/Karachi' // 👈 SOURCE timezone (DB)
                                )
                                ->setTimezone('Asia/Kuala_Lumpur') // 👈 TARGET timezone
                                ->format('d M Y h:i A')
                                : 'N/A',
                            'device_id' => $log->device_id ?? 'N/A',
                            'location_name' => $log->location_name ?? 'N/A',
                            'access_granted' => $log->access_granted ? 'Yes' : 'No',
                            'log_id' => $log->id
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Error processing log: ' . $e->getMessage());
                    continue;
                }
            }

            Log::info('🎯 Final result:', [
                'visitors_count' => count($visitors),
                'location' => $locationName,
                'date' => $date
            ]);
            
            return response()->json([
                'success' => true,
                'visitors' => $visitors,
                'count' => count($visitors),
                'location' => $locationName,
                'date' => Carbon::parse($selectedDate)->format('d M Y'),
                'timestamp' => now()->format('d M Y h:i A'),
                'debug' => [ // ✅ Add debug info for testing
                    'location_in_request' => $locationName,
                    'date_in_request' => $selectedDate,
                    'parsed_date' => $date,
                    'access_logs_count' => $accessLogs->count(),
                    'unique_staff_nos' => $uniqueStaffNos
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('💥 VisitorInfoByDoorController error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
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
    // private function callJavaVendorApi($staffNo)
    // {
    //     try {
    //         $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
    //         $token = session()->get('java_backend_token') ?? session()->get('java_auth_token'); 
            
    //         $response = Http::withHeaders([
    //             'x-auth-token' => $token,
    //             'Accept' => 'application/json',
    //         ])->timeout(10)
    //           ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details?staffNo=' . $staffNo);

    //         if ($response->successful()) {
    //             return $response->json();
    //         } else {
    //             Log::error('Java API error for staff_no ' . $staffNo . ': ' . $response->status());
    //             return null;
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Java API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
    //         return null;
    //     }
    // }
    private function callJavaVendorApi($staffNo)
{
    try {
        $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
        $url = $javaBaseUrl . '/api/vendorpass/get-visitor-details?icNo=' . urlencode($staffNo);
        
        Log::info('🌐 Calling Java API (without token):', ['url' => $url]);
        
        // Option 1: Try without token
        $response = Http::timeout(10)->get($url);
        
        // Option 2: If fails, try with token
        if (!$response->successful()) {
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            if ($token) {
                Log::info('🔄 Retrying with token...');
                $response = Http::withHeaders(['x-auth-token' => $token])
                    ->timeout(10)
                    ->get($url);
            }
        }
        
        Log::info('📡 Response Status:', ['status' => $response->status()]);
        
        if ($response->successful()) {
            $data = $response->json();
            Log::info('✅ API Success:', [
                'has_data' => isset($data['data']),
                'message' => $data['message'] ?? 'No message'
            ]);
            return $data;
        } else {
            Log::error('❌ API Failed:', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        }
    } catch (\Exception $e) {
        Log::error('💥 Exception:', ['message' => $e->getMessage()]);
        return null;
    }
}
}

