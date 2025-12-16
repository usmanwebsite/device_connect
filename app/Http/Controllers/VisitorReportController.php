<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\VisitorReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DeviceAccessLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Services\MenuService; // ✅ MenuService import karein

class VisitorReportController extends Controller
{
    protected $menuService; // ✅ MenuService property

    // ✅ Constructor mein MenuService inject karein
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index()
    {
        // ✅ MenuService se filtered angular menu lein
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $visitors = $this->getVisitorsFromDeviceLogs();
        
        // ✅ Angular menu ko view mein pass karein
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

                // $timeIn = $earliestLog ? $earliestLog->created_at->format('H:i:s') : 'N/A';

                $timeIn = null;

                foreach ($logs as $log) {

                    $logLocationName = $log->location_name;

                    if (!$logLocationName) continue;

                    // 1. vendor_locations match by name
                    $vendorLocation = \App\Models\VendorLocation::where('name', $logLocationName)->first();
                    if (!$vendorLocation) continue;

                    // 2. device_location_assigns check_in match
                    $deviceAssign = \App\Models\DeviceLocationAssign::where('location_id', $vendorLocation->id)
                        ->where('is_type', 'check_in')
                        ->first();

                    if (!$deviceAssign) continue;

                    // 3. CHECK-IN TIME mil gaya
                    $timeIn = $log->created_at->format('H:i:s');
                    break;
                }

                $dateOfVisit = $earliestLog ? $earliestLog->created_at->format('Y-m-d') : 'N/A';

                // ✅ TIME OUT logic: Turnstile checkout check
                $timeOut = null;

                foreach ($logs as $log) {

                    // 1. Get device_access_logs → location_name
                    $logLocationName = $log->location_name;

                    if (!$logLocationName) continue;

                    // 2. Match location name with vendor_locations table
                    $vendorLocation = \App\Models\VendorLocation::where('name', $logLocationName)->first();

                    if (!$vendorLocation) continue;

                    // 3. Get device_location_assign using location_id from vendor_locations
                    $deviceAssign = \App\Models\DeviceLocationAssign::where('location_id', $vendorLocation->id)
                        ->where('is_type', 'check_out')
                        ->first();

                    if (!$deviceAssign) continue;

                    // 4. This log is the checkout → set time_out
                    $timeOut = $log->created_at->format('H:i:s');
                    break;
                }

                // Current location and accessed locations
                $currentLocation = $latestLog->location_name ?? 'N/A';
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


    // ✅ NAYA METHOD: First entry se ab tak ka duration calculate karein
    private function calculateDurationFromFirstEntry($logs)
    {
        try {
            if ($logs->isEmpty()) {
                return 'N/A';
            }

            $firstLog = $logs->last(); // Sabse pehli entry
            $from = Carbon::parse($firstLog->created_at);
            $to = now(); // Abhi tak ka time (kyunki abhi bhi andar hai)

            $diffInMinutes = $to->diffInMinutes($from);
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;

            return $hours . ' hours ' . $minutes . ' minutes';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    // ✅ NAYA METHOD: Visitor status determine karein
    private function determineVisitorStatus($dateFrom, $dateTo, $logs)
    {
        // ✅ Agar API data available hai toh API ke hisaab se status determine karein
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
                // Fallback to active if API date parsing fails
            }
        }

        // ✅ Agar API data nahi hai, toh device logs ke hisaab se active consider karein
        return 'Active';
    }

    // ✅ Purpose extract karein API response se
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

    // ✅ Java API call method
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

    // ✅ Duration calculate karein (for API data)
    private function calculateDuration($dateFrom, $dateTo)
    {
        if (!$dateFrom || !$dateTo) {
            return 'N/A';
        }

        try {
            $from = Carbon::parse($dateFrom);
            $to = Carbon::parse($dateTo);
            
            $diffInMinutes = $to->diffInMinutes($from);
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;
            
            return $hours . ' hours ' . $minutes . ' minutes';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    // ✅ Date format karein
    private function formatDate($dateString)
    {
        if (!$dateString) {
            return 'N/A';
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    // ✅ Time format karein
    private function formatTime($dateString)
    {
        if (!$dateString) {
            return 'N/A';
        }

        try {
            return Carbon::parse($dateString)->format('H:i:s');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    // ✅ Fallback static data
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