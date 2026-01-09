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

    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $visitors = $this->getVisitorsFromDeviceLogs();
        
        return view('reports.visitor_report', compact('visitors', 'angularMenu'));
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'excel');
        $visitors = $this->getVisitorsFromDeviceLogs();
        
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

                    // Latest log
                    $latestLog = $logs->first();
                    $earliestLog = $logs->last();

                    // ✅ Time In Logic
                    $timeIn = null;
                    foreach ($logs as $log) {
                        $logLocationName = $log->location_name;

                        if (!$logLocationName) continue;

                        $vendorLocation = VendorLocation::where('name', $logLocationName)->first();
                        if (!$vendorLocation) continue;

                        $deviceAssign = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                            ->where('is_type', 'check_in')
                            ->first();

                        if (!$deviceAssign) continue;

                        $timeIn = $log->created_at->format('H:i:s');
                        break;
                    }

                    $dateOfVisit = $earliestLog ? $earliestLog->created_at->format('Y-m-d') : 'N/A';

                    // ✅ Time Out Logic
                    $timeOut = null;
                    foreach ($logs as $log) {
                        $logLocationName = $log->location_name;

                        if (!$logLocationName) continue;

                        $vendorLocation = VendorLocation::where('name', $logLocationName)->first();
                        if (!$vendorLocation) continue;

                        $deviceAssign = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                            ->where('is_type', 'check_out')
                            ->first();

                        if (!$deviceAssign) continue;

                        $timeOut = $log->created_at->format('H:i:s');
                        break;
                    }

                    // ✅ ✅✅ NEW LOGIC: Current Location with Turnstile Check
                    $currentLocation = $latestLog->location_name ?? 'N/A';
                    
                    if ($latestLog && $latestLog->location_name && $latestLog->device_id) {
                        // Step 1: Check if location_name contains "Turnstile" or similar
                        $locationName = $latestLog->location_name;
                        $isTurnstile = stripos($locationName, 'Turnstile') !== false || 
                                       stripos($locationName, 'Main Gate') !== false ||
                                       stripos($locationName, 'Entrance') !== false;
                        
                        if ($isTurnstile) {
                            // Step 2: Get VendorLocation ID by name
                            $vendorLocation = VendorLocation::where('name', 'like', '%' . $locationName . '%')->first();
                            
                            if ($vendorLocation) {
                                $locationId = $vendorLocation->id;
                                
                                // Step 3: Get DeviceConnection ID by device_id
                                $deviceConnection = DeviceConnection::where('device_id', $latestLog->device_id)->first();
                                
                                if ($deviceConnection) {
                                    $deviceId = $deviceConnection->id;
                                    
                                    // Step 4: Check in device_location_assigns
                                    $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceId)
                                        ->where('location_id', $locationId)
                                        ->first();
                                    
                                    if ($deviceLocationAssign) {
                                        // Step 5: Check is_type
                                        if ($deviceLocationAssign->is_type == 'check_in') {
                                            $currentLocation = 'Turnstile';
                                        } elseif ($deviceLocationAssign->is_type == 'check_out') {
                                            $currentLocation = '--';
                                        }
                                    } else {
                                        // Agar match na mile toh alternative check
                                        Log::info("Device location assign not found for device_id: {$deviceId}, location_id: {$locationId}");
                                        $currentLocation = 'Turnstile (Check Pending)';
                                    }
                                } else {
                                    Log::info("Device connection not found for device_id: {$latestLog->device_id}");
                                }
                            } else {
                                Log::info("Vendor location not found for name: {$locationName}");
                            }
                        }
                    }

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
                        'current_location' => $currentLocation, // ✅ Updated
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

    // ✅ Optimized function for current location check
    private function getCurrentLocationWithTurnstileCheck($latestLog)
    {
        try {
            if (!$latestLog || !$latestLog->location_name || !$latestLog->device_id) {
                return $latestLog->location_name ?? 'N/A';
            }

            $locationName = $latestLog->location_name;
            
            // Step 1: Check if this is a Turnstile location
            $isTurnstile = $this->isTurnstileLocation($locationName);
            
            if (!$isTurnstile) {
                return $locationName;
            }
            
            // Step 2: Get VendorLocation ID
            $vendorLocation = VendorLocation::where('name', 'like', '%' . $locationName . '%')->first();
            if (!$vendorLocation) {
                Log::warning("VendorLocation not found for: {$locationName}");
                return $locationName;
            }
            
            // Step 3: Get DeviceConnection
            $deviceConnection = DeviceConnection::where('device_id', $latestLog->device_id)->first();
            if (!$deviceConnection) {
                Log::warning("DeviceConnection not found for device_id: {$latestLog->device_id}");
                return $locationName;
            }
            
            // Step 4: Check DeviceLocationAssign
            $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)
                ->where('location_id', $vendorLocation->id)
                ->first();
            
            if (!$deviceLocationAssign) {
                Log::warning("DeviceLocationAssign not found for device_id: {$deviceConnection->id}, location_id: {$vendorLocation->id}");
                return $locationName;
            }
            
            // Step 5: Return based on is_type
            if ($deviceLocationAssign->is_type == 'check_in') {
                return 'Turnstile';
            } elseif ($deviceLocationAssign->is_type == 'check_out') {
                return '--';
            } else {
                return $locationName;
            }
            
        } catch (\Exception $e) {
            Log::error('Error in getCurrentLocationWithTurnstileCheck: ' . $e->getMessage());
            return $latestLog->location_name ?? 'N/A';
        }
    }
    
    // ✅ Helper function to check if location is Turnstile
    private function isTurnstileLocation($locationName)
    {
        if (!$locationName) {
            return false;
        }
        
        $turnstileKeywords = [
            'turnstile',
            'main gate', 
            'entrance',
            'exit',
            'gate',
            'access control'
        ];
        
        $locationLower = strtolower($locationName);
        
        foreach ($turnstileKeywords as $keyword) {
            if (stripos($locationLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
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
    private function calculateDurationFromFirstEntry($logs)
    {
        try {
            if ($logs->isEmpty()) {
                return 'N/A';
            }

            $firstLog = $logs->last();
            $from = Carbon::parse($firstLog->created_at);
            $to = now();

            $diffInMinutes = $to->diffInMinutes($from);
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;

            return $hours . ' hours ' . $minutes . ' minutes';
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
            
            Log::info('Calling Java Vendor API for staff_no: ' . $staffNo);
            
            $response = Http::timeout(15)
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

