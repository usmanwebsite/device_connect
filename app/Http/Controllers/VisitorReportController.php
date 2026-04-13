<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\MenuService;
use App\Models\DeviceAccessLog;
use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\VendorLocation;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VisitorReportExport;

class VisitorReportController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    // public function index()
    // {
    //     $angularMenu = $this->menuService->getFilteredAngularMenu();
        
    //     $visitors = $this->getVisitorsFromDeviceLogs();

    //     $filterData = $this->getFilterData($visitors);
        
    //     return view('reports.visitor_report', compact('visitors', 'angularMenu','filterData'));
    // }

    public function index(Request $request)
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        
        // Get filtered visitors based on request
        $visitors = $this->getFilteredVisitors($request);
        
        // Get unique values for filter dropdowns
        $filterData = $this->getFilterData($visitors);
        
        return view('reports.visitor_report', compact('visitors', 'angularMenu', 'filterData'));
    }

    private function getFilteredVisitors(Request $request)
    {
        $allVisitors = $this->getVisitorsFromDeviceLogs();
        
        // Apply filters
        if ($request->filled('ic_passport')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return stripos($visitor['ic_passport'], $request->ic_passport) !== false;
            });
        }
        
        if ($request->filled('name')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return stripos($visitor['name'], $request->name) !== false;
            });
        }
        
        if ($request->filled('contact_no')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return stripos($visitor['contact_no'], $request->contact_no) !== false;
            });
        }
        
        if ($request->filled('purpose')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return stripos($visitor['purpose'], $request->purpose) !== false;
            });
        }

        // Status filter - Commented for client confirmation
        if ($request->filled('status')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return $visitor['status'] === $request->status;
            });
        }
        
        // DateTime From filter - Compare with both date and time
        if ($request->filled('datetime_from')) {
            $datetimeFrom = Carbon::parse($request->datetime_from);
            $allVisitors = array_filter($allVisitors, function($visitor) use ($datetimeFrom) {
                // Combine date_of_visit and time_in to create full datetime
                $visitDateTime = Carbon::parse($visitor['date_of_visit'] . ' ' . ($visitor['time_in'] ?? '00:00:00'));
                return $visitDateTime >= $datetimeFrom;
            });
        }
        
        // DateTime To filter
        if ($request->filled('datetime_to')) {
            $datetimeTo = Carbon::parse($request->datetime_to);
            $allVisitors = array_filter($allVisitors, function($visitor) use ($datetimeTo) {
                $visitDateTime = Carbon::parse($visitor['date_of_visit'] . ' ' . ($visitor['time_in'] ?? '00:00:00'));
                return $visitDateTime <= $datetimeTo;
            });
        }
        
        return array_values($allVisitors);
    }

    private function getFilterData($visitors)
    {
        $purposes = array_unique(array_column($visitors, 'purpose'));
        $companies = array_unique(array_column($visitors, 'company_name'));
        $statuses = ['Active', 'Completed', 'Scheduled'];
        
        return [
            'purposes' => array_values(array_filter($purposes)),
            'companies' => array_values(array_filter($companies)),
            'statuses' => $statuses
        ];
    }
   
    
    // public function export(Request $request)
    // {
    //     $type = $request->get('type', 'excel');
    //     $visitors = $this->getVisitorsFromDeviceLogs();
        
    //     if ($type === 'excel') {
    //         return Excel::download(new VisitorReportExport($visitors), 'visitor-report-' . date('Y-m-d') . '.xlsx');
    //     }
        
    //     return back()->with('success', 'Export completed successfully');
    // }
    public function export(Request $request)
{
    $type = $request->get('type', 'excel');
    
    // Get filtered visitors for export
    $visitors = $this->getFilteredVisitors($request);
    
    if ($type === 'excel') {
        return Excel::download(new VisitorReportExport($visitors), 'visitor-report-' . date('Y-m-d') . '.xlsx');
    }
    
    return back()->with('success', 'Export completed successfully');
}

private function getVisitorsFromDeviceLogs()
{
    try {
        $deviceLogs = DeviceAccessLog::where('access_granted', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        $groupedLogs = $deviceLogs->groupBy('staff_no');

        $visitors = [];
        $serialNo = 1;

        foreach ($groupedLogs as $staffNo => $logs) {
            try {
                $javaApiResponse = $this->callJavaVendorApi($staffNo);
                $visitorData = $javaApiResponse['data'] ?? null;

                $latestLog = $logs->first();  
                $earliestLog = $logs->last(); 

                $timeIn = null;
                foreach ($logs->reverse() as $log) { 
                    if (!$log->location_name) continue;
                    
                    $vendorLocation = VendorLocation::where('name', $log->location_name)->first();
                    if (!$vendorLocation) continue;
                    
                    $deviceAssign = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                        ->where('is_type', 'check_in')
                        ->first();
                    
                    if ($deviceAssign) {
                        $timeIn = $log->created_at->format('H:i:s');
                        break;
                    }
                }

                $dateOfVisit = $earliestLog ? $earliestLog->created_at->format('Y-m-d') : 'N/A';

                $timeOut = null;
                foreach ($logs as $log) { // Recent scan se shuru karo
                    if (!$log->location_name) continue;
                    
                    $vendorLocation = VendorLocation::where('name', $log->location_name)->first();
                    if (!$vendorLocation) continue;
                    
                    $deviceAssign = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                        ->where('is_type', 'check_out')
                        ->first();
                    
                    if ($deviceAssign) {
                        $timeOut = $log->created_at->format('H:i:s');
                        break;
                    }
                }

                $currentLocation = $this->getCurrentLocationBasedOnLatestScan($logs);
                
                // Accessed locations
                $accessedLocations = $logs->pluck('location_name')->unique()->filter()->implode(', ');

                $purpose = $this->extractPurposeFromApi($visitorData ?? []);

                $status = $this->determineVisitorStatus(
                    $visitorData['dateOfVisitFrom'] ?? null,
                    $visitorData['dateOfVisitTo'] ?? null,
                    $logs
                );

                // Duration calculate karo
                $duration = $this->calculateDuration($timeIn, $timeOut);

                $visitors[] = [
                    'no' => $serialNo++,
                    'ic_passport' => $visitorData['icNo'] ?? 'N/A',
                    'name' => $visitorData['fullName'] ?? 'N/A',
                    'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                    'company_name' => $visitorData['companyName'] ?? 'N/A',
                    'date_of_visit' => $dateOfVisit,
                    'time_in' => $timeIn ?? 'N/A',
                    'time_out' => $timeOut ?? 'N/A',
                    'purpose' => $purpose,
                    'host_name' => $visitorData['personVisited'] ?? 'N/A',
                    'current_location' => $currentLocation, // ✅ Yeh ab sahi hoga
                    'location_accessed' => $accessedLocations ?: 'N/A',
                    'duration' => $duration,
                    'status' => $status
                ];

            } catch (\Exception $e) {
                Log::error('Error processing visitor ' . $staffNo . ': ' . $e->getMessage());
                continue;
            }
        }

        return $visitors;

    } catch (\Exception $e) {
        Log::error('Error fetching visitors from device logs: ' . $e->getMessage());
        return $this->getStaticVisitors();
    }
}

private function getCurrentLocationBasedOnLatestScan($logs)
{
    if ($logs->isEmpty()) {
        return 'N/A';
    }
    
    // SIRF last (most recent) scan dekho
    $latestLog = $logs->first();
    $locationName = $latestLog->location_name ?? '';
    
    if (!$locationName) {
        return 'N/A';
    }
    
    $isGate =strtoupper($locationName) === "Turnstile";
    
    if (!$isGate) {
        return $locationName;
    }
    
    try {
        $vendorLocation = VendorLocation::where('name', 'like', '%' . $locationName . '%')->first();
        if (!$vendorLocation) {
            return $locationName; 
        }
        
        $deviceConnection = DeviceConnection::where('device_id', $latestLog->device_id)->first();
        if (!$deviceConnection) {
            return $locationName;
        }
        
        // DeviceLocationAssign find karo
        $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)
            ->where('location_id', $vendorLocation->id)
            ->first();
        
        if (!$deviceLocationAssign) {
            return $locationName;
        }
        
        // is_type ke hisaab se return karo
        if ($deviceLocationAssign->is_type == 'check_in') {
            return 'Turnstile (Entry)'; // Gate se andar aaya hai
        } elseif ($deviceLocationAssign->is_type == 'check_out') {
            return 'Out'; // Gate se bahar gaya hai
        } else {
            return $locationName;
        }
        
    } catch (\Exception $e) {
        Log::error('Error in getCurrentLocationBasedOnLatestScan: ' . $e->getMessage());
        return $locationName;
    }
}

    // ✅ UPDATED: Main function with optimized current location logic
    private function getVisitorsFromDeviceLogsOptimized()
    {
        try {
            $deviceLogs = DeviceAccessLog::where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get();

            $groupedLogs = $deviceLogs->groupBy('staff_no');

            $visitors = [];
            $serialNo = 1;

            foreach ($groupedLogs as $staffNo => $logs) {
                try {
                    $javaApiResponse = $this->callJavaVendorApi($staffNo);
                    $visitorData = $javaApiResponse['data'] ?? null;

                    $latestLog = $logs->first();
                    $earliestLog = $logs->last();

                    // ✅ Time In
                    $timeIn = $this->getTimeForType($logs, 'check_in');
                    
                    // ✅ Time Out
                    $timeOut = $this->getTimeForType($logs, 'check_out');
                    
                    // ✅ Current Location with Turnstile check
                    $currentLocation = $this->getCurrentLocationWithTurnstileCheck($latestLog);

                    $dateOfVisit = $earliestLog ? $earliestLog->created_at->format('Y-m-d') : 'N/A';

                    // Accessed locations
                    $accessedLocations = $logs->pluck('location_name')->unique()->filter()->implode(', ');

                    $purpose = $this->extractPurposeFromApi($visitorData ?? []);

                    $status = $this->determineVisitorStatus(
                        $visitorData['dateOfVisitFrom'] ?? null,
                        $visitorData['dateOfVisitTo'] ?? null,
                        $logs
                    );

                    $visitors[] = [
                        'no' => $serialNo++,
                        'ic_passport' => $visitorData['icNo'] ?? 'N/A',
                        'name' => $visitorData['fullName'] ?? 'N/A',
                        'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                        'company_name' => $visitorData['companyName'] ?? 'N/A',
                        'date_of_visit' => $dateOfVisit,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut ?? 'N/A',
                        'purpose' => $purpose,
                        'host_name' => $visitorData['personVisited'] ?? 'N/A',
                        'current_location' => $currentLocation,
                        'location_accessed' => $accessedLocations ?: 'N/A',
                        'duration' => $this->calculateDurationFromFirstEntry($logs),
                        'status' => $status
                    ];

                } catch (\Exception $e) {
                    Log::error('Error processing visitor ' . $staffNo . ': ' . $e->getMessage());
                    continue;
                }
            }

            return $visitors;

        } catch (\Exception $e) {
            Log::error('Error fetching visitors from device logs: ' . $e->getMessage());
            return $this->getStaticVisitors();
        }
    }
    
    // ✅ Helper function to get time based on check type
    private function getTimeForType($logs, $checkType)
    {
        foreach ($logs as $log) {
            if (!$log->location_name) continue;
            
            $vendorLocation = VendorLocation::where('name', $log->location_name)->first();
            if (!$vendorLocation) continue;
            
            $deviceAssign = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                ->where('is_type', $checkType)
                ->first();
            
            if ($deviceAssign) {
                return $log->created_at->format('H:i:s');
            }
        }
        
        return null;
    }

    // Rest of the methods remain the same...
private function calculateDuration($timeIn, $timeOut)
{
    try {
        if (!$timeIn || $timeIn == 'N/A') {
            return 'N/A';
        }
        
        if ($timeOut && $timeOut != 'N/A') {
            // Agar time_out hai, toh time_in se time_out tak ka duration
            $start = Carbon::parse($timeIn);
            $end = Carbon::parse($timeOut);
            $diffInMinutes = $end->diffInMinutes($start);
        } else {
            // Agar time_out nahi hai, toh time_in se abhi tak ka duration
            $start = Carbon::parse($timeIn);
            $diffInMinutes = now()->diffInMinutes($start);
        }
        
        $hours = floor($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;
        
        if ($hours > 0) {
            return $hours . ' hours ' . $minutes . ' minutes';
        } else {
            return $minutes . ' minutes';
        }
        
    } catch (\Exception $e) {
        return 'N/A';
    }
}
    private function determineVisitorStatus($dateFrom, $dateTo, $logs)
    {
        if ($dateFrom && $dateTo) {
            try {
                $now = now();
                $from = Carbon::parse($dateFrom);
                $to = Carbon::parse($dateTo);

                if ($now->gt($to)) {
                    return 'Completed';
                } elseif ($now->between($from, $to)) {
                    return 'Active';
                } else {
                    return 'Scheduled';
                }
            } catch (\Exception $e) {
                // Fallback
            }
        }

        return 'Active';
    }

    private function extractPurposeFromApi($visitorData)
    {
        $possiblePurposeFields = [
            'purpose',
            'purposeOfVisit', 
            'visitPurpose',
            'reason',
            'visitReason',
            'businessPurpose',
            'visitObjective'
        ];
        
        foreach ($possiblePurposeFields as $field) {
            if (isset($visitorData[$field]) && !empty($visitorData[$field])) {
                return $visitorData[$field];
            }
        }
        
        if (isset($visitorData['personVisited']) && !empty($visitorData['personVisited'])) {
            return 'Meeting with ' . $visitorData['personVisited'];
        }
        
        return 'Business Visit';
    }

    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
            $token = session()->get('java_backend_token')
               ?? session()->get('java_auth_token');
            Log::info('Calling Java Vendor API for staff_no: ' . $staffNo);

            $headers = [
                'x-auth-token' => $token,
                'Accept'       => 'application/json',
            ];
            
        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->retry(2, 100)
            ->get($javaBaseUrl . '/api/vendorpass/get-visitor-details', [
                'icNo' => $staffNo
            ]);

            
            Log::info('Java Vendor API Response Status: ' . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                Log::info('Java Vendor API Success for ' . $staffNo . ': Data received');
                return $data;
            } else {
                Log::error('Java Vendor API error for staff_no ' . $staffNo . ': HTTP ' . $response->status());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Java Vendor API exception for staff_no ' . $staffNo . ': ' . $e->getMessage());
            return null;
        }
    }

    private function getStaticVisitors()
    {
        return [
            [
                'no' => 1,
                'ic_passport' => '901231-14-1234',
                'name' => 'Ahmad bin Ali',
                'contact_no' => '012-3456789',
                'company_name' => 'ABC Sdn Bhd',
                'date_of_visit' => '2024-01-15',
                'time_in' => '09:15:00',
                'time_out' => null,
                'purpose' => 'Business Meeting',
                'host_name' => 'Siti Nurhaliza',
                'current_location' => 'Main Lobby',
                'location_accessed' => 'Main Gate, Security Check, Main Lobby',
                'duration' => '3 hours 15 minutes',
                'status' => 'Active'
            ]
        ];
    }
}

