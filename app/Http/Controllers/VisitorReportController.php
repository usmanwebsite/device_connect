<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
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
    const MALAYSIA_TIMEZONE = 'Asia/Kuala_Lumpur';
    const CACHE_DURATION = 300; // 5 minutes
    const LOGIN_URL = 'https://mnrvms-isk.com.my/#/pages/login';

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
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
        
        Log::error('No Java token available in session');
        throw new \Exception('Java authentication token not available. Please login again.');
    }

    /**
     * Check if token is valid by making a test API call
     */
    private function isTokenValid($token)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
            
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(5)->get($javaBaseUrl . '/api/vendorpass/validate-token');
            
            if ($response->status() === 401) {
                return false;
            }
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function index(Request $request)
    {
        try {
            $token = $this->getJavaToken();
            
            // Validate token before proceeding
            if (!$this->isTokenValid($token)) {
                Log::warning('Token validation failed, redirecting to login');
                $this->redirectToLogin();
                return;
            }
        } catch (\Exception $e) {
            return redirect(self::LOGIN_URL)->with('error', $e->getMessage());
        }
        
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        
        $visitors = $this->getFilteredVisitors($request);
        
        $filterData = $this->getFilterData($visitors);
        
        return view('reports.visitor_report', compact('visitors', 'angularMenu', 'filterData'));
    }

    private function getFilteredVisitors(Request $request)
    {
        $allVisitors = $this->getVisitorsFromDeviceLogs();
        
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

        if ($request->filled('status')) {
            $allVisitors = array_filter($allVisitors, function($visitor) use ($request) {
                return $visitor['status'] === $request->status;
            });
        }
        
        if ($request->filled('datetime_from')) {
            try {
                $datetimeFrom = Carbon::parse($request->datetime_from);
                $allVisitors = array_filter($allVisitors, function($visitor) use ($datetimeFrom) {
                    $timeIn = $visitor['time_in'] ?? '00:00:00';
                    if ($timeIn === 'N/A' || empty($timeIn)) {
                        $timeIn = '00:00:00';
                    }
                    $visitDateTime = Carbon::parse($visitor['date_of_visit'] . ' ' . $timeIn);
                    return $visitDateTime >= $datetimeFrom;
                });
            } catch (\Exception $e) {
                Log::error('Error parsing datetime_from: ' . $e->getMessage());
            }
        }

        if ($request->filled('datetime_to')) {
            try {
                $datetimeTo = Carbon::parse($request->datetime_to);
                $allVisitors = array_filter($allVisitors, function($visitor) use ($datetimeTo) {
                    $timeIn = $visitor['time_in'] ?? '00:00:00';
                    if ($timeIn === 'N/A' || empty($timeIn)) {
                        $timeIn = '00:00:00';
                    }
                    $visitDateTime = Carbon::parse($visitor['date_of_visit'] . ' ' . $timeIn);
                    return $visitDateTime <= $datetimeTo;
                });
            } catch (\Exception $e) {
                Log::error('Error parsing datetime_to: ' . $e->getMessage());
            }
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
   
    public function export(Request $request)
    {
        $type = $request->get('type', 'excel');
        $visitors = $this->getFilteredVisitors($request);
        
        if ($type === 'excel') {
            return Excel::download(new VisitorReportExport($visitors), 'visitor-report-' . date('Y-m-d') . '.xlsx');
        }
        
        return back()->with('success', 'Export completed successfully');
    }

    /**
     * Main function - Same logic as old code, only optimized
     */
    private function getVisitorsFromDeviceLogs()
    {
        // Check token before using cache
        try {
            $token = $this->getJavaToken();
            if (!$this->isTokenValid($token)) {
                $this->redirectToLogin();
                return [];
            }
        } catch (\Exception $e) {
            $this->redirectToLogin();
            return [];
        }
        
        $cacheKey = 'visitor_report_data_' . md5(serialize(request()->all()));
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function() {
            return $this->processVisitorData();
        });
    }

    /**
     * Process visitor data - EXACT same logic as old code
     */
    private function processVisitorData()
    {
        try {
            $deviceLogs = DeviceAccessLog::where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get();
 
            if ($deviceLogs->isEmpty()) {
                return $this->getStaticVisitors();
            }
 
            $staffNumbers = $deviceLogs->pluck('staff_no')->unique()->values()->toArray();
            
            $visitorDataMap = $this->getVisitorDataBulk($staffNumbers);
            
            $groupedByStaff = $deviceLogs->groupBy('staff_no');
            
            $visitors = [];
            $serialNo = 1;
            
            foreach ($groupedByStaff as $staffNo => $logs) {
                try {
                    $visitorData = $visitorDataMap[$staffNo] ?? null;
                    
                    if (empty($visitorData)) {
                        $apiResponse = $this->callJavaVendorApi($staffNo);
                        $visitorData = $apiResponse['data'] ?? null;
                    }
                    
                    $icNumber = $staffNo;
                    if (!empty($visitorData['icNo'])) {
                        $icNumber = $visitorData['icNo'];
                    }
                    
                    $sortedLogs = $logs->sortBy('created_at');
                    $visitSessions = $this->identifyVisitSessions($sortedLogs);
                    
                    foreach ($visitSessions as $session) {
                        $timeIn      = $session['time_in'];
                        $timeOut     = $session['time_out'];
                        $dateOfVisit = $session['date_of_visit'];
                        
                        $currentLocation    = $this->getCurrentLocationBasedOnLatestScan($session['logs']);
                        $accessedLocations  = $session['logs']->pluck('location_name')->unique()->filter()->implode(', ');
                        $purpose            = $this->extractPurposeFromApi($visitorData ?? []);
                        $duration           = $this->calculateDuration($timeIn, $timeOut);
                        
                        $status = $this->determineVisitorStatusForVisit(
                            $visitorData['dateOfVisitFrom'] ?? null,
                            $visitorData['dateOfVisitTo'] ?? null,
                            $dateOfVisit,
                            $timeOut
                        );
                        
                        $visitors[] = [
                            'no'               => $serialNo++,
                            'ic_passport'      => $icNumber,
                            'name'             => $visitorData['fullName']    ?? 'N/A',
                            'contact_no'       => $visitorData['contactNo']   ?? 'N/A',
                            'company_name'     => $visitorData['companyName'] ?? 'N/A',
                            'date_of_visit'    => $dateOfVisit,
                            'time_in'          => $timeIn  ?? 'N/A',
                            'time_out'         => $timeOut ?? 'N/A',
                            'purpose'          => $purpose,
                            'host_name'        => $visitorData['personVisited'] ?? 'N/A',
                            'current_location' => $currentLocation,
                            'location_accessed'=> $accessedLocations ?: 'N/A',
                            'duration'         => $duration,
                            'status'           => $status,
                        ];
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Error processing visitor ' . $staffNo . ': ' . $e->getMessage());
                    continue;
                }
            }
            
            usort($visitors, fn($a, $b) =>
                strtotime($b['date_of_visit']) - strtotime($a['date_of_visit'])
            );
            
            foreach ($visitors as $i => $v) {
                $visitors[$i]['no'] = $i + 1;
            }
            
            return $visitors;
            
        } catch (\Exception $e) {
            Log::error('Error fetching visitors: ' . $e->getMessage());
            return $this->getStaticVisitors();
        }
    }

    /**
     * Identify visit sessions - EXACT SAME LOGIC as old code
     */
    private function identifyVisitSessions($logs)
    {
        $sessions = [];
        $currentSession = null;
        
        $logsArray = $logs->values()->all();
        $totalLogs = count($logsArray);
        
        for ($i = 0; $i < $totalLogs; $i++) {
            $log = $logsArray[$i];
            
            if (!$log->location_name) {
                if ($log->access_granted == 1) {
                    if (!$currentSession) {
                        $currentSession = [
                            'logs' => collect([$log]),
                            'time_in' => Carbon::parse($log->created_at)
                                ->setTimezone(self::MALAYSIA_TIMEZONE)
                                ->format('H:i:s'),
                            'date_of_visit' => Carbon::parse($log->created_at)
                                ->setTimezone(self::MALAYSIA_TIMEZONE)
                                ->format('Y-m-d'),
                            'time_out' => null
                        ];
                    } else {
                        $currentSession['logs']->push($log);
                    }
                }
                continue;
            }
            
            $vendorLocation = VendorLocation::where('name', $log->location_name)->first();
            
            if (!$vendorLocation) {
                if ($currentSession) {
                    $currentSession['logs']->push($log);
                } else {
                    $logDate = Carbon::parse($log->created_at)
                        ->setTimezone(self::MALAYSIA_TIMEZONE)
                        ->format('Y-m-d');
                        
                    $currentSession = [
                        'logs' => collect([$log]),
                        'time_in' => Carbon::parse($log->created_at)
                            ->setTimezone(self::MALAYSIA_TIMEZONE)
                            ->format('H:i:s'),
                        'date_of_visit' => $logDate,
                        'time_out' => null
                    ];
                }
                continue;
            }
            
            $deviceId = $this->getInternalDeviceId($log->device_id);
            
            $query = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                ->where('is_type', 'check_in');
            if ($deviceId) {
                $query->where('device_id', $deviceId);
            }
            $isCheckIn = $query->first();
            
            $queryOut = DeviceLocationAssign::where('location_id', $vendorLocation->id)
                ->where('is_type', 'check_out');
            if ($deviceId) {
                $queryOut->where('device_id', $deviceId);
            }
            $isCheckOut = $queryOut->first();
            
            $logDate = Carbon::parse($log->created_at)
                ->setTimezone(self::MALAYSIA_TIMEZONE)
                ->format('Y-m-d');
            
            if ($isCheckIn) {
                if ($currentSession && $currentSession['date_of_visit'] !== $logDate) {
                    $sessions[] = $currentSession;
                    $currentSession = null;
                }
                
                if (!$currentSession) {
                    $currentSession = [
                        'logs' => collect([$log]),
                        'time_in' => Carbon::parse($log->created_at)
                            ->setTimezone(self::MALAYSIA_TIMEZONE)
                            ->format('H:i:s'),
                        'date_of_visit' => $logDate,
                        'time_out' => null
                    ];
                } else {
                    $currentSession['logs']->push($log);
                }
            } elseif ($isCheckOut && $currentSession) {
                $currentSession['time_out'] = Carbon::parse($log->created_at)
                    ->setTimezone(self::MALAYSIA_TIMEZONE)
                    ->format('H:i:s');
                $currentSession['logs']->push($log);
                $sessions[] = $currentSession;
                $currentSession = null;
            } elseif ($currentSession) {
                $currentSession['logs']->push($log);
            } else {
                $sessions[] = [
                    'logs' => collect([$log]),
                    'time_in' => Carbon::parse($log->created_at)
                        ->setTimezone(self::MALAYSIA_TIMEZONE)
                        ->format('H:i:s'),
                    'date_of_visit' => $logDate,
                    'time_out' => null
                ];
            }
        }
        
        if ($currentSession !== null && $currentSession['time_in'] !== null) {
            $sessions[] = $currentSession;
        }
        
        usort($sessions, function($a, $b) {
            $dateA = $a['date_of_visit'] !== 'N/A' ? strtotime($a['date_of_visit']) : 0;
            $dateB = $b['date_of_visit'] !== 'N/A' ? strtotime($b['date_of_visit']) : 0;
            return $dateB - $dateA;
        });
        
        return $sessions;
    }

    /**
     * Get current location based on latest scan - EXACT SAME as old code
     */
    private function getCurrentLocationBasedOnLatestScan($logs)
    {
        if ($logs->isEmpty()) return 'N/A';
        
        $latestLog = $logs->first();
        $locationName = $latestLog->location_name ?? '';
        $serialNumber = $latestLog->device_id ?? '';
        
        if (!$locationName) return 'N/A';
        
        $vendorLocation = VendorLocation::where('name', $locationName)->first();
        if (!$vendorLocation) return $locationName;
        
        $deviceId = $this->getInternalDeviceId($serialNumber);
        $query = DeviceLocationAssign::where('location_id', $vendorLocation->id);
        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }
        $deviceAssign = $query->first();
        
        if ($deviceAssign && $deviceAssign->is_type === 'check_out') {
            return 'Out';
        }
        
        return $locationName;
    }

    /**
     * Calculate duration - EXACT SAME as old code
     */
    private function calculateDuration($timeIn, $timeOut)
    {
        try {
            if (!$timeIn || $timeIn == 'N/A') {
                return 'N/A';
            }
            
            if ($timeOut && $timeOut != 'N/A') {
                $start = Carbon::parse($timeIn, self::MALAYSIA_TIMEZONE);
                $end = Carbon::parse($timeOut, self::MALAYSIA_TIMEZONE);
                $diffInMinutes = $end->diffInMinutes($start);
            } else {
                $start = Carbon::parse($timeIn, self::MALAYSIA_TIMEZONE);
                $now = Carbon::now(self::MALAYSIA_TIMEZONE);
                $diffInMinutes = $now->diffInMinutes($start);
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

    /**
     * Determine visitor status for a specific visit - EXACT SAME as old code
     */
    private function determineVisitorStatusForVisit($dateFrom, $dateTo, $visitDate, $timeOut)
    {
        if ($timeOut && $timeOut != 'N/A') {
            return 'Completed';
        }
        
        if ($dateFrom && $dateTo) {
            try {
                $now = Carbon::now(self::MALAYSIA_TIMEZONE);
                $visitDateTime = Carbon::parse($visitDate, self::MALAYSIA_TIMEZONE);
                
                $from = Carbon::parse($dateFrom, self::MALAYSIA_TIMEZONE);
                $to = Carbon::parse($dateTo, self::MALAYSIA_TIMEZONE);
                
                if ($visitDateTime->gt($to)) {
                    return 'Completed';
                } elseif ($now->between($from, $to) && $visitDateTime->isToday()) {
                    return 'Active';
                } elseif ($visitDateTime->lt($from)) {
                    return 'Scheduled';
                }
            } catch (\Exception $e) {
                // fallback
            }
        }
        
        if ($timeOut && $timeOut != 'N/A') {
            return 'Completed';
        }
        
        return 'Active';
    }

    /**
     * Extract purpose from API data - EXACT SAME as old code
     */
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

    /**
     * Bulk fetch visitor data from Java API with caching - OPTIMIZATION
     */
    private function getVisitorDataBulk($staffNumbers)
    {
        if (empty($staffNumbers)) return [];
 
        $cacheKey = 'visitor_data_bulk_' . md5(serialize($staffNumbers));
 
        return Cache::remember($cacheKey, self::CACHE_DURATION, function() use ($staffNumbers) {
            try {
                $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
                $token = $this->getJavaToken();
 
                if (!$token) {
                    Log::warning('[JavaAPI] No token — falling back to individual calls');
                    $this->redirectToLogin();
                    return [];
                }
 
                // Try bulk endpoint
                $response = Http::withHeaders([
                    'x-auth-token' => $token,
                    'Accept'       => 'application/json',
                ])->timeout(30)->post(
                    $javaBaseUrl . '/api/vendorpass/get-visitor-details-bulk',
                    ['icNumbers' => $staffNumbers]
                );
 
                if ($response->status() === 401) {
                    Log::warning('[JavaAPI] 401 on bulk — token expired');
                    $this->redirectToLogin();
                    return [];
                }
 
                if ($response->successful()) {
                    $body = $response->json();
                    $list = $body['data'] ?? [];
 
                    if (!empty($list) && is_array($list)) {
                        $map = [];
                        foreach ($list as $item) {
                            if (!empty($item['icNo'])) {
                                $map[$item['icNo']] = $item;
                            }
                        }
 
                        Log::info('[JavaAPI] Bulk success — ' . count($map) . ' records keyed by icNo');
                        return $map;
                    }
                }
 
                Log::warning('[JavaAPI] Bulk returned empty — falling back to individual');
                return $this->getVisitorDataIndividual($staffNumbers);
 
            } catch (\Exception $e) {
                Log::error('[JavaAPI] Bulk exception: ' . $e->getMessage());
                return $this->getVisitorDataIndividual($staffNumbers);
            }
        });
    }

    /**
     * Fallback: Get visitor data individually
     */
    private function getVisitorDataIndividual($staffNumbers)
    {
        $result = [];
 
        foreach (array_chunk($staffNumbers, 10) as $chunk) {
            foreach ($chunk as $staffNo) {
                try {
                    $response = $this->callJavaVendorApi($staffNo);
                    if (!empty($response['data'])) {
                        $result[$staffNo] = $response['data'];
                    }
                } catch (\Exception $e) {
                    Log::error('[JavaAPI] Individual call failed for ' . $staffNo . ': ' . $e->getMessage());
                }
            }
        }
 
        return $result;
    }

    /**
     * Call Java Vendor API for a single staff
     */
    private function callJavaVendorApi($staffNo)
    {
        try {
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
            $token = $this->getJavaToken();
            
            if (!$token) {
                Log::warning('Java API token not found for staff: ' . $staffNo);
                $this->redirectToLogin();
                return null;
            }

            Log::info('=== VisitorReportController Java API Call Debug ===');
            Log::info('Staff No: ' . $staffNo);
            Log::info('Java Base URL: ' . $javaBaseUrl);
            Log::info('Token exists: ' . ($token ? 'Yes' : 'No'));

            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(15)->retry(2, 100)->get($javaBaseUrl . '/api/vendorpass/get-visitor-details', [
                'icNo' => $staffNo
            ]);
            
            Log::info('Response Status: ' . $response->status());
            
            if ($response->status() === 401) {
                Log::warning('Java API token expired for staff: ' . $staffNo);
                $this->redirectToLogin();
                return null;
            }
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Check if Java API returned ERROR status
                if (isset($data['status']) && $data['status'] === 'ERROR') {
                    Log::error('Java API returned ERROR status: ' . ($data['message'] ?? 'Unknown error'));
                    
                    // Check for session related errors
                    $errorMessage = strtolower($data['message'] ?? '');
                    $sessionErrors = ['expired', 'invalid', 'unauthorized', 'token'];
                    
                    foreach ($sessionErrors as $error) {
                        if (str_contains($errorMessage, $error)) {
                            Log::error('Session expired detected in API response');
                            $this->redirectToLogin();
                            return null;
                        }
                    }
                    
                    return null;
                }
                
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

    /**
     * Redirect to login page
     */
    private function redirectToLogin()
    {
        // Clear session token
        session()->forget('java_backend_token');
        session()->forget('java_auth_token');
        session()->forget('java_username');
        session()->forget('java_display_name');
        
        // Clear cache to prevent stale data
        Cache::flush();
        
        // Flash message
        session()->flash('error', 'Your session has expired. Please login again.');
        
        // Redirect to login page
        if (request()->ajax() || request()->wantsJson()) {
            // For AJAX requests
            abort(401, 'Session expired');
        } else {
            // For normal requests
            Redirect::away(self::LOGIN_URL)->send();
            exit;
        }
    }

    /**
     * Get internal device ID from serial number with caching - OPTIMIZATION
     */
    private function getInternalDeviceId($serialNumber)
    {
        if (!$serialNumber) return null;
        
        $cacheKey = 'device_id_' . md5($serialNumber);
        return Cache::remember($cacheKey, 3600, function() use ($serialNumber) {
            $device = DeviceConnection::where('device_id', $serialNumber)->first();
            return $device ? $device->id : null;
        });
    }

    /**
     * Get static visitors for fallback
     */
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

