<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;
use App\Models\DeviceAccessLog;
use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\SecurityAlertPriority;
use App\Models\VendorLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index(Request $request)
    {
        try {
            Log::info('=== Dashboard Accessed ===');
            
            $perPage = 10; // Items per page

            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            
            $angularMenu = [];
            if ($token) {
                try {
                    $angularMenu = $this->menuService->getFilteredAngularMenuWithToken($token);

                    $userAccessData = $this->menuService->getUserAccessData();
                    if ($userAccessData && isset($userAccessData['user_id'])) {
                        session()->put('java_user_id', $userAccessData['user_id']);
                        Log::info('User ID saved in dashboard session:', ['user_id' => $userAccessData['user_id']]);
                    }
                    
                    if (empty($angularMenu) || (is_array($angularMenu) && count($angularMenu) === 0)) {
                        Log::warning('Empty angularMenu returned. Redirecting user.');
                        return redirect()->away(config('app.angular_url'))
                            ->with('error', 'Session expired. Please login again.');
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Menu error: ' . $e->getMessage());
                    $angularMenu = [];
                    return redirect()->away(config('app.angular_url'))
                        ->with('error', 'Session expired. Please login again.');
                }
            } else {
                Log::error('NO TOKEN FOUND IN DASHBOARD!');
                return redirect()->away(config('app.angular_url'))
                    ->with('error', 'Session expired. Please login again.');
            }
            
            $userAccessData = $this->menuService->getUserAccessData();

            $todayAppointmentCount = 0;
            $upcomingAppointments = [];
            $todayAppointments = [];
            
            if ($userAccessData && isset($userAccessData['today_appointment_count'])) {
                $todayAppointments = collect($userAccessData['today_appointments'] ?? [])
                    ->unique('staff_no')
                    ->values()
                    ->toArray();
                
                $todayAppointmentCount = count($todayAppointments);
                $upcomingAppointments = $userAccessData['upcoming_appointments'] ?? [];
            }
            
            // ✅ FIX: Single source of truth
            $visitorsOnSite = $this->getCurrentVisitorsOnSite();
            $criticalAlert = $this->getCriticalSecurityAlertWithPriority();
            
            $twentyFourHoursAgo = Carbon::now()->subHours(24);
            $deniedAccessCount24h = $this->getDeniedAccessCount24h();

            // ===============================
            // ✅ 1. ACTIVE SECURITY ALERTS PAGINATION
            // ===============================
            $activeSecurityAlerts = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'active_alerts_page');

            $activeSecurityAlertsCount = $activeSecurityAlerts->total(); // Total count from pagination

            $hourlyTrafficData = $this->getHourlyTrafficData();

            // ===============================
            // ✅ 2. VISITOR OVERSTAY PAGINATION
            // ===============================
            $visitorOverstayAlerts = $this->getUnacknowledgedOverstayAlerts();
            $visitorOverstayCount = count($visitorOverstayAlerts);
            
            // Convert array to pagination
            $overstayPage = $request->get('overstay_page', 1);
            $overstayOffset = ($overstayPage - 1) * $perPage;
            $paginatedOverstayAlerts = array_slice($visitorOverstayAlerts, $overstayOffset, $perPage);

            // ===============================
            // ✅ 3. DENIED ACCESS (24 HOURS) PAGINATION
            // ===============================
            $deniedAccessCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->whereRaw('created_at >= CURDATE()')
                ->count();

            $deniedAccessLogs24h = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->whereRaw('created_at >= CURDATE()')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'denied_access_page');
            
            // ===============================
            // ✅ 4. UPCOMING APPOINTMENTS PAGINATION
            // ===============================
            $upcomingPage = $request->get('upcoming_page', 1);
            $upcomingOffset = ($upcomingPage - 1) * $perPage;
            $paginatedUpcomingAppointments = array_slice($upcomingAppointments, $upcomingOffset, $perPage);
            
            // ===============================
            // ✅ 5. TODAY'S CHECKOUTS
            // ===============================
            $checkoutsTodayAllData = $this->getCheckoutsTodayModalData(false); // All data
            $checkOutsTodayCount = count($checkoutsTodayAllData);
            
            // Convert array to pagination
            $checkoutsPage = $request->get('checkouts_page', 1);
            $checkoutsOffset = ($checkoutsPage - 1) * $perPage;
            $paginatedCheckoutsData = array_slice($checkoutsTodayAllData, $checkoutsOffset, $perPage);

            // ===============================
            // ✅ ENRICH ALL DATA
            // ===============================
            $enrichedActiveSecurityAlerts = $this->getEnrichedDeniedAccessLogs($activeSecurityAlerts);
            $enrichedDeniedAccessLogs24h = $this->getEnrichedDeniedAccessLogs($deniedAccessLogs24h);
            $enrichedOverstayAlerts = $this->getEnrichedOverstayAlerts($paginatedOverstayAlerts);

            // ✅ FIX: Keep backward compatibility for existing blade code
            $enrichedDeniedAccessLogs = $enrichedDeniedAccessLogs24h; // Alias for backward compatibility

            return view('dashboard', compact(
                'angularMenu', 
                'todayAppointmentCount', 
                'visitorsOnSite',
                'todayAppointments',
                'upcomingAppointments',
                'paginatedUpcomingAppointments', // ✅ Paginated upcoming appointments
                'activeSecurityAlerts', // ✅ Paginated object for Active Security Alerts
                'enrichedActiveSecurityAlerts', // ✅ Enriched data for Active Security Alerts modal
                'activeSecurityAlertsCount',
                'hourlyTrafficData',
                'visitorOverstayCount',   
                'deniedAccessCount24h',
                'deniedAccessCount',      
                'deniedAccessLogs24h', // ✅ Paginated denied access (24 hours)
                'enrichedDeniedAccessLogs24h', // ✅ Enriched data for denied access modal
                'enrichedDeniedAccessLogs', // ✅ For backward compatibility (Access Denied Modal)
                'visitorOverstayAlerts',    
                'paginatedOverstayAlerts', // ✅ Paginated overstay alerts
                'enrichedOverstayAlerts',   // ✅ Enriched paginated overstay alerts
                'checkOutsTodayCount',
                'paginatedCheckoutsData', // ✅ Paginated checkouts data
                'checkoutsTodayAllData', // ✅ Keep all data for count
                'criticalAlert',
                'perPage', // ✅ Pass perPage for pagination calculations
                'request'
            ));
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return view('dashboard', [
                'angularMenu' => [],
                'todayAppointmentCount' => 0,
                'visitorsOnSite' => [],
                'todayAppointments' => [],
                'upcomingAppointments' => [],
                'paginatedUpcomingAppointments' => [],
                'activeSecurityAlerts' => collect(),
                'enrichedActiveSecurityAlerts' => [],
                'activeSecurityAlertsCount' => 0,
                'hourlyTrafficData' => $this->getDefaultHourlyTrafficData(),
                'visitorOverstayCount' => 0,
                'deniedAccessCount' => 0,
                'deniedAccessCount24h' => 0,
                'checkOutsTodayCount' => 0,
                'deniedAccessLogs24h' => collect(),
                'enrichedDeniedAccessLogs24h' => [],
                'enrichedDeniedAccessLogs' => [], // ✅ Add this
                'visitorOverstayAlerts' => [],
                'paginatedOverstayAlerts' => [],
                'enrichedOverstayAlerts' => [],
                'paginatedCheckoutsData' => [],
                'checkoutsTodayAllData' => [],
                'criticalAlert' => null,
                'perPage' => 10
            ]);
        }
    }

private function getCriticalSecurityAlertWithPriority()
{
    try {
        Log::info('=== Starting getCriticalSecurityAlertWithPriority ===');
        
        // Step 1: Get priority settings
        $accessDeniedPriority = SecurityAlertPriority::where('security_alert', 'Access Denied Incidents')->first();
        $visitorOverstayPriority = SecurityAlertPriority::where('security_alert', 'Visitor Overstay Alerts')->first();
        
        Log::info('Priority Settings:', [
            'Access Denied' => $accessDeniedPriority ? $accessDeniedPriority->priority : 'low',
            'Visitor Overstay' => $visitorOverstayPriority ? $visitorOverstayPriority->priority : 'low'
        ]);
        
        // Step 2: Get ALL alerts (both high and low priority)
        $allAlerts = collect();
        
        // Get Access Denied alerts with their priority
        $accessDeniedAlerts = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)            
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get();


        foreach ($accessDeniedAlerts as $alert) {
            $priority = $accessDeniedPriority ? strtolower($accessDeniedPriority->priority) : 'low';
            
            // ✅ FIX: Handle empty staff_no case
            $visitorDetails = $this->getVisitorDetailsForAlert($alert->staff_no ?? $alert->card_no ?? '');
            // dd($visitorDetails);
            $processedLocation = $this->processTurnstileLocationForAlert($alert);
            
            $allAlerts->push([
                'type' => 'access_denied',
                'alert_type' => 'access_denied',
                'priority' => $priority,
                'priority_weight' => $priority == 'high' ? 3 : ($priority == 'medium' ? 2 : 1),
                'created_at' => $alert->created_at,
                'log_id' => $alert->id,
                'data' => $alert,
                'display_location' => $processedLocation,
                'original_location' => $alert->location_name ?? 'Unknown Location',
                'card_no' => $alert->card_no ?? null,
                'staff_no' => $alert->staff_no ?? null,
                'visitor_name' => $visitorDetails['fullName'] ?? 'Unknown Visitor' // ✅ Add visitor name here
            ]);
        }
        // Get Visitor Overstay alerts with their priority
        $overstayAlerts = $this->getUnacknowledgedOverstayAlerts();
        
        foreach ($overstayAlerts as $alert) {
            $priority = $visitorOverstayPriority ? strtolower($visitorOverstayPriority->priority) : 'low';
            
            $processedLocation = $this->processTurnstileLocationForOverstay($alert);
            
            $allAlerts->push([
                'type' => 'visitor_overstay',
                'alert_type' => 'visitor_overstay',
                'priority' => $priority,
                'priority_weight' => $priority == 'high' ? 3 : ($priority == 'medium' ? 2 : 1),
                'created_at' => Carbon::parse($alert['check_in_time']),
                'log_id' => $alert['log_id'] ?? $alert['staff_no'] . '_overstay',
                'data' => $alert,
                'display_location' => $processedLocation,
                'original_location' => $alert['original_location'] ?? ($alert['location'] ?? 'Unknown Location'),
                'card_no' => $alert['card_no'] ?? null,
                'staff_no' => $alert['staff_no'] ?? null
            ]);
        }
        
        Log::info('Total alerts found: ' . $allAlerts->count());
        
        // Step 3: Sort alerts by priority (high > medium > low) and then by created_at (latest first)
        $sortedAlerts = $allAlerts->sortByDesc(function($alert) {
            return [$alert['priority_weight'], $alert['created_at']->timestamp];
        });
        
        // Step 4: Return the top alert
        if ($sortedAlerts->isNotEmpty()) {
            $topAlert = $sortedAlerts->first();
            
            Log::info('Top alert selected:', [
                'type' => $topAlert['type'],
                'priority' => $topAlert['priority'],
                'staff_no' => $topAlert['staff_no'] ?? 'EMPTY',
                'card_no' => $topAlert['card_no'] ?? 'EMPTY',
                'created_at' => $topAlert['created_at']->format('Y-m-d H:i:s')
            ]);
            
            if ($topAlert['type'] === 'access_denied') {
                $alert = $topAlert['data'];
                
                // ✅ Use visitor_name from the alert array we added above
                $visitorName = $topAlert['visitor_name'] ?? 'Unknown Visitor';
                
                $createdAt = Carbon::parse($alert->created_at);
                $timeAgo = $createdAt->diffForHumans();
                
                return [
                    'log_id' => $alert->id,
                    'alert_type' => 'access_denied',
                    'staff_no' => $alert->staff_no ?? '', // ✅ Can be empty
                    'card_no' => $alert->card_no ?? '',   // ✅ Use card_no when staff_no is empty
                    'location' => $topAlert['display_location'],
                    'original_location' => $topAlert['original_location'],
                    'created_at' => $createdAt->format('Y-m-d h:i A'),
                    'time_ago' => $timeAgo,
                    'malaysia_time' => Carbon::now('Asia/Kuala_Lumpur')->format('Y-m-d h:i A'), 
                    'reason' => $alert->reason ?? 'Other Reason',
                    'visitor_name' => $visitorName, // ✅ Already fetched above
                    'incident_type' => 'Unauthorized Access Attempt',
                    'priority' => $topAlert['priority']
                ];
            } else {
                // Visitor Overstay alert
                $alert = $topAlert['data'];

                $currentTime = Carbon::now('Asia/Kuala_Lumpur'); 
                
                return [
                    'log_id' => $alert['log_id'] ?? $alert['staff_no'] . '_overstay',
                    'alert_type' => 'visitor_overstay',
                    'staff_no' => $alert['staff_no'] ?? '', // ✅ Can be empty
                    'card_no' => $alert['card_no'] ?? '',   // ✅ Use card_no when staff_no is empty
                    'location' => $topAlert['display_location'],
                    'original_location' => $topAlert['original_location'],
                    'created_at' => Carbon::parse($alert['check_in_time'])->format('Y-m-d h:i A'),
                    'malaysia_time' => $currentTime->format('Y-m-d h:i A'), 
                    'time_ago' => Carbon::parse($alert['check_in_time'])->diffForHumans(), 
                    'visitor_name' => $alert['visitor_name'],
                    'incident_type' => 'Visitor Overstay Alert',
                    'priority' => $topAlert['priority'],
                    'overstay_details' => [
                        'check_in_time' => $alert['check_in_time'],
                        'expected_end_time' => $alert['expected_end_time'],
                        'current_time' => $alert['current_time'],
                        'overstay_duration' => $alert['overstay_duration'],
                        'host' => $alert['host'],
                        'date_of_visit_from' => $alert['date_of_visit_from'] ?? null,
                        'date_of_visit_to' => $alert['date_of_visit_to'] ?? null
                    ]
                ];
            }
        }
        
        Log::info('No alerts found');
        return null;
        
    } catch (\Exception $e) {
        Log::error('Error getting critical alert with priority: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return null;
    }
}

public function getSecurityAlertsData(Request $request)
{
    try {
        Log::info('=== Security Alerts DataTable AJAX Request ===');
        
        // DataTable parameters
        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';
        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';
        
        // Column names mapping (Actions column remove कर दिया)
        $columns = [
            0 => 'id',           // #
            1 => 'visitor_name', // Visitor Name
            2 => 'contact_no',   // Contact No
            3 => 'ic_no',        // IC No
            4 => 'host',         // Host
            5 => 'location',     // Location
            6 => 'reason',       // Reason
            7 => 'created_at',   // Date & Time
            // 8 => 'actions'    // REMOVED
        ];
        
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
        
        // Base query
        $query = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0);
        
        // Apply search filter
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $q->where('staff_no', 'like', '%' . $searchValue . '%')
                  ->orWhere('card_no', 'like', '%' . $searchValue . '%')
                  ->orWhere('location_name', 'like', '%' . $searchValue . '%')
                  ->orWhere('reason', 'like', '%' . $searchValue . '%');
            });
        }
        
        // Get total records
        $totalRecords = $query->count();
        
        // Apply ordering and pagination
        $query->orderBy($orderColumn, $orderDir)
              ->skip($start)
              ->take($length);
        
        $logs = $query->get();
        
        // Prepare data for DataTable (actions column remove कर दिया)
        $data = [];
        foreach ($logs as $index => $log) {
            $visitorDetails = $this->getVisitorDetailsForAlert($log->staff_no);
            
            $data[] = [
                'DT_RowIndex' => $start + $index + 1,
                'visitor_name' => $visitorDetails['fullName'] ?? 'N/A',
                'contact_no' => $visitorDetails['contactNo'] ?? 'N/A',
                'ic_no' => $visitorDetails['icNo'] ?? 'N/A',
                'host' => $visitorDetails['personVisited'] ?? 'N/A',
                'location' => $log->location_name ?? 'Unknown Location',
                'reason' => $log->reason ?: 'Other Reason',
                'date_time' => Carbon::parse($log->created_at)->format('d M Y h:i A'),
                // 'actions' => '<button class="btn btn-sm btn-info view-details" data-id="'.$log->id.'"><i class="fas fa-eye"></i> View</button>' // REMOVED
            ];
        }
        
        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in getSecurityAlertsData: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'draw' => intval($request->get('draw', 1)),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $e->getMessage()
        ], 500);
    }
}


public function getAccessDeniedIncidentsAjax(Request $request)
{
    try {
        $perPage = 10;
        $page = $request->get('page', 1);
        
        Log::info('=== Access Denied Incidents AJAX Request START ===', [
            'page' => $page,
            'url' => $request->fullUrl()
        ]);
        
        // Get denied access logs for last 24 hours
        $deniedAccessLogs24h = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->whereRaw('created_at >= CURDATE()')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        // Enrich the logs with visitor details
        $enrichedLogs = $this->getEnrichedDeniedAccessLogs($deniedAccessLogs24h);
        
        // Generate HTML for table rows
        $html = '';
        $startNumber = ($page - 1) * $perPage + 1;
        
        foreach ($enrichedLogs as $index => $enrichedLog) {
            $html .= '
            <tr>
                <td>' . ($startNumber + $index) . '</td>
                <td>' . ($enrichedLog['visitor_details']['fullName'] ?? 'N/A') . '</td>
                <td>' . ($enrichedLog['visitor_details']['contactNo'] ?? 'N/A') . '</td>
                <td>' . ($enrichedLog['visitor_details']['icNo'] ?? 'N/A') . '</td>
                <td>' . ($enrichedLog['visitor_details']['personVisited'] ?? 'N/A') . '</td>
                <td>' . ($enrichedLog['log']->location_name ?? 'Unknown Location') . '</td>
                <td>' . ($enrichedLog['log']->reason ?: 'Other Reason') . '</td>
                <td>' . Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A') . '</td>
            </tr>';
        }
        
        if (empty($html)) {
            $html = '<tr><td colspan="8" class="text-center">No access denied incidents found for last 24 hours.</td></tr>';
        }
        
        // ✅ SMART PAGINATION GENERATE KAREIN
        $pagination = $this->generateSmartPagination(
            $deniedAccessLogs24h->currentPage(),
            $deniedAccessLogs24h->lastPage()
        );
        
        $responseData = [
            'success' => true,
            'html' => $html,
            'pagination' => $pagination,
            'total' => $deniedAccessLogs24h->total(),
            'current_page' => $deniedAccessLogs24h->currentPage(),
            'per_page' => $perPage,
            'last_page' => $deniedAccessLogs24h->lastPage()
        ];
        
        Log::info('=== Access Denied Incidents AJAX Request END ===');
        
        return response()->json($responseData);
        
    } catch (\Exception $e) {
        Log::error('Error in getAccessDeniedIncidentsAjax: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Server Error: ' . $e->getMessage(),
            'html' => '',
            'pagination' => ''
        ], 500);
    }
}

// ✅ NEW: Smart Pagination Generation Method
private function generateSmartPagination($currentPage, $totalPages, $maxVisible = 7)
{
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link access-denied-pagination-link" href="#" data-page="' . ($currentPage - 1) . '" aria-label="Previous">';
        $html .= '<span aria-hidden="true">&laquo;</span>';
        $html .= '</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-label="Previous"><span aria-hidden="true">&laquo;</span></span>';
        $html .= '</li>';
    }
    
    // Always show first page
    if ($currentPage == 1) {
        $html .= '<li class="page-item active"><span class="page-link">1</span></li>';
    } else {
        $html .= '<li class="page-item"><a class="page-link access-denied-pagination-link" href="#" data-page="1">1</a></li>';
    }
    
    // Calculate start and end for middle pages
    $start = max(2, $currentPage - 2);
    $end = min($totalPages - 1, $currentPage + 2);
    
    // Show ellipsis after first page if needed
    if ($start > 2) {
        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    
    // Show middle pages
    for ($i = $start; $i <= $end; $i++) {
        if ($i > 1 && $i < $totalPages) {
            if ($i == $currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link access-denied-pagination-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
            }
        }
    }
    
    // Show ellipsis before last page if needed
    if ($end < $totalPages - 1) {
        $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    
    // Show last page if totalPages > 1
    if ($totalPages > 1) {
        if ($currentPage == $totalPages) {
            $html .= '<li class="page-item active"><span class="page-link">' . $totalPages . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link access-denied-pagination-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link access-denied-pagination-link" href="#" data-page="' . ($currentPage + 1) . '" aria-label="Next">';
        $html .= '<span aria-hidden="true">&raquo;</span>';
        $html .= '</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-label="Next"><span aria-hidden="true">&raquo;</span></span>';
        $html .= '</li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}


// ✅ NEW: Process Turnstile location for Access Denied alerts
private function processTurnstileLocationForAlert($alert)
{
    try {
        $location = $alert->location_name ?? 'Unknown Location';
        
        // Check if location contains "Turnstile"
        if (strpos($location, 'Turnstile') === false) {
            return $location;
        }
        
        // Get device connection
        $deviceConnection = DeviceConnection::where('device_id', $alert->device_id)->first();
        if (!$deviceConnection) {
            return $location;
        }
        
        // Get vendor location
        $vendorLocation = VendorLocation::where('name', $location)->first();
        if (!$vendorLocation) {
            return $location;
        }
        
        // Get device location assign
        $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)
            ->where('location_id', $vendorLocation->id)
            ->first();
        
        if (!$deviceLocationAssign) {
            return $location;
        }
        
        // Check is_type
        if ($deviceLocationAssign->is_type === 'check_in') {
            return 'Turnstile In';
        } elseif ($deviceLocationAssign->is_type === 'check_out') {
            return 'Out';
        }
        
        return $location;
        
    } catch (\Exception $e) {
        Log::error('Error processing turnstile location for alert: ' . $e->getMessage());
        return $alert->location_name ?? 'Unknown Location';
    }
}

// ✅ NEW: Process Turnstile location for Overstay alerts
private function processTurnstileLocationForOverstay($alert)
{
    try {
        $location = $alert['location'] ?? 'Unknown Location';
        
        // Check if location contains "Turnstile"
        if (strpos($location, 'Turnstile') === false) {
            return $location;
        }
        
        // Get device connection
        $deviceConnection = DeviceConnection::where('device_id', $alert['device_id'])->first();
        if (!$deviceConnection) {
            return $location;
        }
        
        // Get vendor location
        $vendorLocation = VendorLocation::where('name', $location)->first();
        if (!$vendorLocation) {
            return $location;
        }
        
        // Get device location assign
        $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)
            ->where('location_id', $vendorLocation->id)
            ->first();
        
        if (!$deviceLocationAssign) {
            return $location;
        }
        
        // Check is_type
        if ($deviceLocationAssign->is_type === 'check_in') {
            return 'Turnstile In';
        } elseif ($deviceLocationAssign->is_type === 'check_out') {
            return 'Out';
        }
        
        return $location;
        
    } catch (\Exception $e) {
        Log::error('Error processing turnstile location for overstay: ' . $e->getMessage());
        return $alert['location'] ?? 'Unknown Location';
    }
}

private function getUnacknowledgedOverstayAlerts($allDeviceUsers = null)
{
    try {
// dd('345678');
    $allDeviceUsers = DB::table('device_access_logs as v')
        ->join(
            DB::raw('(
                SELECT staff_no, MAX(created_at) AS last_access
                FROM device_access_logs
                WHERE created_at >= NOW() - INTERVAL 2 DAY AND access_granted=1
                GROUP BY staff_no
            ) last'),
            function ($join) {
                $join->on('v.staff_no', '=', 'last.staff_no')
                     ->on('v.created_at', '=', 'last.last_access');
            }
        )

        ->join('device_connections as dc', 'dc.device_id', '=', 'v.device_id')

        ->join('device_location_assigns as dal', 'dal.device_id', '=', 'dc.id')

        ->where('v.location_name', '!=', '13. TURNSTILE')
        ->whereIn('dal.is_type', ['check_out'])
        ->limit(12)

        ->select([
            'v.*'
        ])
        ->get();
        // dd($allDeviceUsers);
        
        $currentTime = now();
        $overstayAlerts = [];
        
        foreach ($allDeviceUsers as $user) {
            // dd($user);
            try {
                if (empty($user->location_name)) {
                    continue;
                }
                
                // Skip if already acknowledged
                if ($user->overstay_acknowledge == 1 || $user->overstay_acknowledge === true) {
                    continue;
                }
                
                // Get API data
                $javaApiResponse = $this->callJavaVendorApi($user->staff_no);
                
                if ($javaApiResponse && isset($javaApiResponse['data'])) {
                    $visitorData = $javaApiResponse['data'];
                    
                    if (isset($visitorData['dateOfVisitTo'])) {
                        $dateOfVisitTo = Carbon::parse($visitorData['dateOfVisitTo']);
                        
                        if ($currentTime->greaterThan($dateOfVisitTo)) {
                            $dateOfVisitFrom = isset($visitorData['dateOfVisitFrom']) 
                                ? Carbon::parse($visitorData['dateOfVisitFrom']) 
                                : null;
                            
                            $overstayMinutes = $currentTime->diffInMinutes($dateOfVisitTo);
                            $overstayHours = floor($overstayMinutes / 60);
                            $remainingMinutes = $overstayMinutes % 60;
                            
                            // ✅ Process Turnstile location
                            $processedLocation = $this->processTurnstileLocationForAlert($user);
                            
                            $overstayAlerts[] = [
                                'visitor_name' => $visitorData['fullName'] ?? 'N/A',
                                'staff_no' => $user->staff_no,
                                'card_no' => $user->card_no ?? null,
                                'expected_end_time' => $dateOfVisitTo->format('d M Y h:i A'),
                                'current_time' => $currentTime->format('d M Y h:i A'),
                                'check_in_time' => Carbon::parse($user->created_at)->format('d M Y h:i A'),
                                'location' => $processedLocation,
                                'original_location' => $user->location_name ?? 'Unknown Location',
                                'overstay_minutes' => $overstayMinutes,
                                'overstay_duration' => $overstayHours . ' hours ' . $remainingMinutes . ' minutes',
                                'host' => $visitorData['personVisited'] ?? 'N/A',
                                'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                                'ic_no' => $visitorData['icNo'] ?? 'N/A',
                                'device_id' => $user->device_id,
                                'date_of_visit_from' => $dateOfVisitFrom ? $dateOfVisitFrom->format('Y-m-d H:i:s') : null,
                                'date_of_visit_to' => $dateOfVisitTo->format('Y-m-d H:i:s'),
                                'log_id' => $user->id
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // dd($e->getMessage());
                Log::error('Error checking overstay for staff_no ' . $user->staff_no . ': ' . $e->getMessage());
                continue;

            }
        }
        
        return $overstayAlerts;
        
    } catch (\Exception $e) {
        // dd($e->getMessage());
        Log::error('Error getting unacknowledged overstay alerts: ' . $e->getMessage());
        return [];
    }
}

    private function getCurrentVisitorsOnSite($paginate = false, $perPage = 10)
    {
        try {
            Log::info('=== Starting getCurrentVisitorsOnSite ===');
            
            // Step 1: Pehle sabhi check_in logs lein
            $allAccessLogs = DeviceAccessLog::where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'staff_no', 'device_id', 'location_name', 'created_at']);
            
            Log::info('All access logs found: ' . $allAccessLogs->count());
            
            // Step 2: Device connections
            $deviceIds = $allAccessLogs->pluck('device_id')->unique();
            $deviceConnections = DeviceConnection::whereIn('device_id', $deviceIds)
                ->get(['id', 'device_id']);
            
            // Step 3: Vendor locations
            $locationNames = $allAccessLogs->pluck('location_name')->unique();
            $vendorLocations = VendorLocation::whereIn('name', $locationNames)
                ->get(['id', 'name']);
            
            // Step 4: Device location assigns
            $deviceConnectionIds = $deviceConnections->pluck('id');
            $locationIds = $vendorLocations->pluck('id');
            
            $deviceLocationAssigns = DeviceLocationAssign::whereIn('device_id', $deviceConnectionIds)
                ->whereIn('location_id', $locationIds)
                ->get(['id', 'device_id', 'location_id', 'is_type']);
            
            // Step 5: Track har staff_no ke latest check_in aur check_out
            $visitorStatus = [];
            
            foreach ($allAccessLogs as $log) {
                $staffNo = $log->staff_no;
                
                // Device connection find karein
                $deviceConnection = $deviceConnections->firstWhere('device_id', $log->device_id);
                if (!$deviceConnection) continue;
                
                // Vendor location find karein
                $vendorLocation = $vendorLocations->firstWhere('name', $log->location_name);
                if (!$vendorLocation) continue;
                
                // Device location assign find karein
                $deviceLocationAssign = $deviceLocationAssigns
                    ->where('device_id', $deviceConnection->id)
                    ->where('location_id', $vendorLocation->id)
                    ->first();
                
                if (!$deviceLocationAssign) continue;
                
                // Check is_type
                if ($deviceLocationAssign->is_type === 'check_in') {
                    // Agar latest check_in hai ya pehli baar check_in hai
                    if (!isset($visitorStatus[$staffNo]) || 
                        $log->created_at > $visitorStatus[$staffNo]['last_check_in']) {
                        $visitorStatus[$staffNo] = [
                            'last_check_in' => $log->created_at,
                            'last_check_in_log' => $log,
                            'has_check_out' => false,
                            'last_check_out' => null
                        ];
                    }
                } elseif ($deviceLocationAssign->is_type === 'check_out') {
                    // Check_out record update karein
                    if (!isset($visitorStatus[$staffNo])) {
                        $visitorStatus[$staffNo] = [
                            'last_check_in' => null,
                            'last_check_in_log' => null,
                            'has_check_out' => true,
                            'last_check_out' => $log->created_at
                        ];
                    } else {
                        // Agar check_in ke baad check_out aaya hai
                        if ($log->created_at > $visitorStatus[$staffNo]['last_check_in']) {
                            $visitorStatus[$staffNo]['has_check_out'] = true;
                            $visitorStatus[$staffNo]['last_check_out'] = $log->created_at;
                        }
                    }
                }
            }
            
            // Step 6: Currently on-site visitors identify karein
            $currentVisitors = [];
            
            foreach ($visitorStatus as $staffNo => $status) {
                $isCurrentlyOnSite = false;
                
                if ($status['last_check_in_log']) {
                    if (!$status['has_check_out']) {
                        $isCurrentlyOnSite = true;
                    } elseif ($status['last_check_out'] && 
                            $status['last_check_in'] > $status['last_check_out']) {
                        $isCurrentlyOnSite = true;
                    }
                }
                
                if ($isCurrentlyOnSite && $status['last_check_in_log']) {
                    // Get visitor details
                    $visitorDetails = $this->getVisitorDetailsForAlert($staffNo);
                    
                    $currentVisitors[] = [
                        'staff_no' => $staffNo,
                        'full_name' => $visitorDetails['fullName'] ?? 'N/A',
                        'person_visited' => $visitorDetails['personVisited'] ?? 'N/A',
                        'location_name' => $status['last_check_in_log']->location_name,
                        'created_at' => $status['last_check_in'],
                        'device_id' => $status['last_check_in_log']->device_id,
                        'log_id' => $status['last_check_in_log']->id
                    ];
                    
                    Log::info("Visitor {$staffNo} is currently on-site at {$status['last_check_in_log']->location_name}");
                }
            }
            
            Log::info('Total visitors on-site: ' . count($currentVisitors));
            Log::info('=== End getCurrentVisitorsOnSite ===');

                    // Return paginated or all results
            if ($paginate) {
                $currentVisitors = collect($currentVisitors);
                return $currentVisitors->forPage(request()->get('visitors_page', 1), $perPage)->values()->toArray();
            }
            
            return $currentVisitors;
            
        } catch (\Exception $e) {
            Log::error('Error in getCurrentVisitorsOnSite: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }


    // Rest of the controller methods remain the same...
    private function getCriticalSecurityAlert()
    {
        try {
            $alert = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$alert) {
                return null;
            }

            $visitorDetails = $this->getVisitorDetailsForAlert($alert->staff_no);

            $createdAt = Carbon::parse($alert->created_at);
            $timeAgo = $createdAt->diffForHumans();

            return [
                'log_id' => $alert->id,
                'staff_no' => $alert->staff_no,
                'location' => $alert->location_name ?? 'Unknown Location',
                'created_at' => $createdAt->format('h:i A'),
                'time_ago' => $timeAgo,
                'reason' => $alert->reason ?? 'Other Reason',
                'visitor_name' => $visitorDetails['fullName'] ?? 'Unknown Visitor',
                'incident_type' => 'Unauthorized Access Attempt' 
            ];

        } catch (\Exception $e) {
            Log::error('Error getting critical alert: ' . $e->getMessage());
            return null;
        }
    }

    private function getVisitorDetailsForAlert($staffNo)
    {
        $cacheKey = "visitor_details_{$staffNo}";
    $cacheTtl = 300; // 5 minutes

    try {
        return Cache::remember($cacheKey, $cacheTtl, function () use ($staffNo) {

            $javaApiResponse = $this->callJavaVendorApi($staffNo);

            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                return $javaApiResponse['data'];
            }

            return [
                'fullName' => 'Unknown Visitor',
                'personVisited' => 'N/A'
            ];
        });

    } catch (\Exception $e) {
        Log::error('Error getting visitor details for alert', [
            'staffNo' => $staffNo,
            'error'   => $e->getMessage()
        ]);

        return [
            'fullName' => 'Unknown Visitor',
            'personVisited' => 'N/A'
        ];
    }
    }

public function acknowledgeAlert(Request $request)
{
    try {
        $alertId = $request->input('alert_id');
        $alertType = $request->input('alert_type', 'access_denied');
        
        Log::info('Acknowledging alert:', [
            'alert_id' => $alertId,
            'alert_type' => $alertType,
            'all_request_data' => $request->all()
        ]);
        
        $currentUserId = session()->get('java_user_id');
        
        if (!$currentUserId) {
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            if ($token) {
                $menuService = new MenuService();
                $userAccessData = $menuService->fetchUserAccessFromJavaBackendWithToken($token);
                $currentUserId = $userAccessData['user_id'] ?? null;
                
                if ($currentUserId) {
                    session()->put('java_user_id', $currentUserId);
                }
            }
        }

        if ($alertType == 'access_denied') {
            // Handle Access Denied alert
            $alert = DeviceAccessLog::find($alertId);
            
            if (!$alert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alert not found'
                ], 404);
            }

            // ✅ UPDATED: Match by alert_id OR card_no (whichever is available)
            $updateQuery = DeviceAccessLog::query();
            
            // First, try to update by alert_id (most accurate)
            $updateQuery->where('id', $alertId);
            
            // If card_no is available, use it as additional filter
            if (!empty($alert->card_no)) {
                // Also update other logs with same card_no at same location
                $updateQuery->orWhere(function($query) use ($alert) {
                    $query->where('card_no', $alert->card_no)
                          ->where('location_name', $alert->location_name ?? '')
                          ->where('access_granted', 0)
                          ->where('acknowledge', 0);
                });
            }
            
            // If staff_no is available (but might be empty), use it too
            if (!empty($alert->staff_no)) {
                $updateQuery->orWhere(function($query) use ($alert) {
                    $query->where('staff_no', $alert->staff_no)
                          ->where('location_name', $alert->location_name ?? '')
                          ->where('access_granted', 0)
                          ->where('acknowledge', 0);
                });
            }
            
            $affectedRows = $updateQuery->update([
                'acknowledge' => true,
                'acknowledge_by' => $currentUserId,
                'updated_at' => now()
            ]);
            
            Log::info("Access Denied alert acknowledged: ID {$alertId}, Affected Rows: {$affectedRows}");
            
        } else if ($alertType == 'visitor_overstay') {
            Log::info('Processing overstay acknowledgment with specific visit criteria');
            
            $staffNo = $request->input('staff_no');
            $cardNo = $request->input('card_no');
            $originalLocation = $request->input('original_location');
            $dateOfVisitFrom = $request->input('date_of_visit_from');
            
            Log::info('Request data for overstay acknowledgment:', [
                'staff_no' => $staffNo,
                'card_no' => $cardNo,
                'original_location' => $originalLocation,
                'date_of_visit_from' => $dateOfVisitFrom
            ]);
            
            // ✅ UPDATED: For overstay, use card_no as primary if staff_no is empty
            $query = DeviceAccessLog::query();
            
            if (!empty($staffNo)) {
                $query->where('staff_no', $staffNo);
            } elseif (!empty($cardNo)) {
                // If staff_no is empty, use card_no
                $query->where('card_no', $cardNo);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Both staff_no and card_no are empty for overstay acknowledgment'
                ], 400);
            }
            
            // Add location filter
            if (!empty($originalLocation)) {
                $query->where('location_name', $originalLocation);
            }
            
            // Date condition: created_at greater than dateOfVisitFrom
            if ($dateOfVisitFrom) {
                try {
                    $parsedFrom = Carbon::parse($dateOfVisitFrom);
                    $query->where('created_at', '>', $parsedFrom);
                    Log::info('Filtering logs with created_at > ' . $parsedFrom->format('Y-m-d H:i:s'));
                } catch (\Exception $e) {
                    Log::error('Error parsing dateOfVisitFrom: ' . $e->getMessage());
                }
            }
            
            $logs = $query->get();
            
            Log::info('Found logs to update for overstay:', [
                'count' => $logs->count(),
                'log_ids' => $logs->pluck('id')->toArray()
            ]);
            
            $updatedCount = 0;
            foreach ($logs as $log) {
                $log->overstay_acknowledge = true;
                $log->acknowledge_by = $currentUserId;
                $log->save();
                $updatedCount++;
                
                Log::info("Updated log ID: {$log->id}, Card No: {$log->card_no}, Overstay Acknowledge: {$log->overstay_acknowledge}");
            }
            
            Log::info("Visitor Overstay alert acknowledged. Updated {$updatedCount} records.");
        }

        // Get next alert
        $nextAlert = $this->getCriticalSecurityAlertWithPriority();
        
        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'next_alert' => $nextAlert,
            'has_next' => $nextAlert ? true : false
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error acknowledging alert: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Error acknowledging alert: ' . $e->getMessage()
        ], 500);
    }
}

    public function hideCriticalAlert(Request $request)
    {
        try {
            $alertId = $request->input('alert_id');
            
            if ($alertId) {
                $alert = DeviceAccessLog::find($alertId);
                if ($alert) {
                    // ✅ GET CURRENT USER ID FROM SESSION
                    $currentUserId = session()->get('java_user_id');
                    
                    $alert->acknowledge = 1;
                    $alert->acknowledge_by = $currentUserId; // ✅ Yahan bhi save karein
                    $alert->save();
                    
                    Log::info("Alert hidden and acknowledged: ID {$alertId}, Acknowledged By: {$currentUserId}");
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Alert hidden successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error hiding critical alert: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error hiding alert'
            ], 500);
        }
    }

    public function getNextAlert(Request $request)
    {
        try {
            $currentAlertId = $request->input('current_alert_id');

            if ($currentAlertId) {
                $currentAlert = DeviceAccessLog::find($currentAlertId);
                if ($currentAlert) {
                    $currentAlert->acknowledge = 1;
                    $currentAlert->save();
                }
            }

            $nextAlert = $this->getCriticalSecurityAlert();
            
            return response()->json([
                'success' => true,
                'next_alert' => $nextAlert,
                'has_next' => $nextAlert ? true : false
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting next alert: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error getting next alert'
            ], 500);
        }
    }

    public function refreshDashboardCounts()
    {
        try {
            $twentyFourHoursAgo = Carbon::now()->subHours(24);            
            
            // ✅ 1. Access Denied Count - LAST 24 HOURS ONLY
            $deniedAccessCount24h = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->count();

            // ✅ 2. Active Security Alerts - OVERALL COUNT (NO TIME LIMIT)
            $activeSecurityAlertsCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->count();

            // ✅ 3. Visitor Overstay Count - NO TIME FILTER
            $allDeviceUsers = DeviceAccessLog::where('access_granted', 1)->get();
            $visitorOverstayAlerts = $this->getUnacknowledgedOverstayAlerts($allDeviceUsers);
            $visitorOverstayCount = count($visitorOverstayAlerts);

            Log::info('Refreshed dashboard counts:', [
                'deniedAccessCount24h' => $deniedAccessCount24h,
                'activeSecurityAlertsCount' => $activeSecurityAlertsCount,
                'visitorOverstayCount' => $visitorOverstayCount,
                'twentyFourHoursAgo' => $twentyFourHoursAgo->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'success' => true,
                'activeSecurityAlertsCount' => $activeSecurityAlertsCount,
                'deniedAccessCount24h' => $deniedAccessCount24h,
                'visitorOverstayCount' => $visitorOverstayCount,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing dashboard counts: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing counts'
            ], 500);
        }
    }

    // private function getCheckoutsTodayCount()
    // {
    //     try {
    //         Log::info('=== Starting getCheckoutsTodayCount (With Location Match) ===');

    //         $turnstileLocations = VendorLocation::where('name', 'like', '%Turnstile%')
    //             ->get(['id', 'name']);

    //         Log::info('Turnstile locations found:', $turnstileLocations->toArray());

    //         if ($turnstileLocations->isEmpty()) {
    //             Log::info('No Turnstile locations found.');
    //             return 0;
    //         }

    //         $turnstileLocationIds = $turnstileLocations->pluck('id');
    //         $turnstileLocationNames = $turnstileLocations->pluck('name');

    //         $deviceLocationAssigns = DeviceLocationAssign::whereIn('location_id', $turnstileLocationIds)
    //             ->where('is_type', 'check_out')
    //             ->get(['id', 'device_id', 'location_id', 'is_type']);

    //         Log::info('Device location assigns found:', $deviceLocationAssigns->toArray());

    //         if ($deviceLocationAssigns->isEmpty()) {
    //             Log::info('No device assigns found with check_out.');
    //             return 0;
    //         }

    //         $deviceConnectionIds = $deviceLocationAssigns->pluck('device_id');

    //         $deviceConnections = DeviceConnection::whereIn('id', $deviceConnectionIds)
    //             ->get(['id', 'device_id']);

    //         Log::info('Device connections found:', $deviceConnections->toArray());

    //         if ($deviceConnections->isEmpty()) {
    //             Log::info('No device connections found.');
    //             return 0;
    //         }

    //         $actualDeviceIds = $deviceConnections->pluck('device_id');

    //         Log::info('Actual device IDs: ', ['ids' => $actualDeviceIds->implode(', ')]);

    //         $today = now()->format('Y-m-d');

    //         Log::info("Checking logs for date: {$today}");
    //         Log::info('Matching location names: ', ['locations' => $turnstileLocationNames->implode(', ')]);

    //         $sampleLogs = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
    //             ->whereIn('location_name', $turnstileLocationNames)
    //             ->whereDate('created_at', $today)
    //             ->take(5)
    //             ->get(['id', 'device_id', 'location_name', 'staff_no', 'created_at']);

    //         Log::info('Sample access logs (with location match):', $sampleLogs->toArray());

    //         $checkoutsCount = DeviceAccessLog::whereIn('device_id', $actualDeviceIds)
    //             ->whereIn('location_name', $turnstileLocationNames)
    //             ->whereDate('created_at', $today)
    //             ->count();

    //         Log::info("Checkouts today count (with location filter): {$checkoutsCount}");
    //         Log::info('=== End getCheckoutsTodayCount ===');

    //         return $checkoutsCount;

    //     } catch (\Exception $e) {
    //         Log::error('Error calculating checkout count: ' . $e->getMessage());
    //         Log::error('Stack trace: ' . $e->getTraceAsString());
    //         return 0;
    //     }
    // }
    private function getCheckoutsTodayCount()
{
    try {
        // Instead of complex query, use the modal data function
        $checkoutsData = $this->getCheckoutsTodayModalData();
        return count($checkoutsData);
    } catch (\Exception $e) {
        Log::error('Error calculating checkout count: ' . $e->getMessage());
        return 0;
    }
}

private function getEnrichedDeniedAccessLogs($deniedAccessLogs)
{
    $enrichedLogs = [];

    // Agar $deniedAccessLogs pagination object hai toh items() use karein
    $logs = $deniedAccessLogs instanceof \Illuminate\Pagination\LengthAwarePaginator 
            ? $deniedAccessLogs->items() 
            : $deniedAccessLogs;

    foreach ($logs as $log) {
        Log::info('Processing denied access log ID: ' . $log->id);
        
        try {
            // ✅ Dynamic approach: Pehle ic_no try karein, phir staff_no
            $identifier = null;
            
            if (!empty($log->ic_no)) {
                $identifier = $log->ic_no;
                Log::info('Using IC No as identifier: ' . $identifier);
            } elseif (!empty($log->staff_no)) {
                $identifier = $log->staff_no;
                Log::info('Using Staff No as identifier: ' . $identifier);
            } else {
                Log::warning('Both IC No and Staff No are empty for log ID: ' . $log->id);
                $enrichedLogs[] = [
                    'log' => $log,
                    'visitor_details' => [
                        'fullName' => 'N/A - No Identifier',
                        'personVisited' => 'N/A',
                        'contactNo' => 'N/A',
                        'icNo' => 'N/A',
                        'sex' => 'N/A',
                        'dateOfVisitFrom' => 'N/A',
                        'dateOfVisitTo' => 'N/A'
                    ]
                ];
                continue;
            }
            
            // ✅ Java API call with dynamic identifier
            $javaApiResponse = $this->callJavaVendorApi($identifier);
            
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
                        'fullName' => 'N/A - API Failed',
                        'personVisited' => 'N/A',
                        'contactNo' => 'N/A',
                        'icNo' => $identifier,
                        'sex' => 'N/A',
                        'dateOfVisitFrom' => 'N/A',
                        'dateOfVisitTo' => 'N/A'
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::error('Java API error for log ID ' . $log->id . ' in denied access: ' . $e->getMessage());
            $enrichedLogs[] = [
                'log' => $log,
                'visitor_details' => [
                    'fullName' => 'N/A - Exception',
                    'personVisited' => 'N/A',
                    'contactNo' => 'N/A',
                    'icNo' => $log->ic_no ?? $log->staff_no ?? 'N/A',
                    'sex' => 'N/A',
                    'dateOfVisitFrom' => 'N/A',
                    'dateOfVisitTo' => 'N/A'
                ]
            ];
        }
    }

    return $enrichedLogs;
}


private function getEnrichedOverstayAlerts($overstayAlerts)
{
    $enrichedAlerts = [];

    foreach ($overstayAlerts as $alert) {
        try {
            $javaApiResponse = $this->callJavaVendorApi($alert['staff_no']);

            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                $visitorData = $javaApiResponse['data'];
                
                // ✅ Use the already processed location from alert
                $displayLocation = $alert['location'] ?? ($alert['original_location'] ?? 'N/A');
                
                $enrichedAlerts[] = [
                    'visitor_name' => $visitorData['fullName'] ?? $alert['visitor_name'],
                    'staff_no' => $alert['staff_no'],
                    'host' => $visitorData['personVisited'] ?? $alert['host'],
                    'location' => $displayLocation, // ✅ Use processed location
                    'original_location' => $alert['original_location'] ?? 'N/A',
                    'check_in_time' => $alert['check_in_time'],
                    'expected_end_time' => $alert['expected_end_time'],
                    'current_time' => $alert['current_time'],
                    'overstay_duration' => $alert['overstay_duration'],
                    'contact_no' => $visitorData['contactNo'] ?? 'N/A',
                    'ic_no' => $visitorData['icNo'] ?? 'N/A',
                    'turnstile_type' => $alert['turnstile_type'] ?? null // ✅ Add turnstile type
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

private function callJavaVendorApi($staffNo)
{
    try {
        $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
        $token = session()->get('java_backend_token') ?? session()->get('java_auth_token'); 
        
        Log::info('=== Java API Call Debug ===');
        Log::info('Staff No: ' . $staffNo);
        Log::info('Java Base URL: ' . $javaBaseUrl);
        Log::info('Token exists: ' . ($token ? 'Yes' : 'No'));
        
        if (!$token) {
            Log::error('Java API Token missing in session!');
            return null;
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
            return $data;
        } else {
            Log::error('Java API error: ' . $response->status());
            Log::error('Error body: ' . $response->body());
            return null;
        }
    } catch (\Exception $e) {
        Log::error('Java API exception: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return null;
    }
}

    private function getHourlyTrafficData()
    {
        try {
            $today = now()->format('Y-m-d');

            $todayAccessLogs = DeviceAccessLog::whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at')
                ->get();

            $cumulativeData = [];
            $labels = [];
            
            for ($i = 0; $i < 24; $i++) {
                $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
                
                $labels[] = $timeLabel;

                $currentHourEnd = Carbon::createFromFormat('Y-m-d H', $today . ' ' . $i);

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
            if ($fromDate === $toDate) {
                return $this->getHourlyDataForSingleDay($fromDate);
            }

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
        
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $timeLabel = $i < 12 ? "{$hour} AM" : ($i == 12 ? "12 PM" : ($i - 12) . " PM");
            
            $labels[] = $timeLabel;
            
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

    public function getVisitorsOnSitePaginated(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            
            $visitors = $this->getCurrentVisitorsOnSite(false); // All data
            
            // Manual pagination
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($visitors, $offset, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $paginatedData,
                'total' => count($visitors),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil(count($visitors) / $perPage)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getCheckoutsTodayModalDataAjax(Request $request)
    {
        try {
            $checkoutsData = $this->getCheckoutsTodayModalData();
            
            return response()->json([
                'success' => true,
                'data' => $checkoutsData,
                'count' => count($checkoutsData),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('AJAX Error in getCheckoutsTodayModalData: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading check-outs data: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    private function getCheckoutsTodayModalData($paginate = false, $perPage = 10)
    {
        try {
            Log::info('=== Starting getCheckoutsTodayModalData (Updated Logic) ===');
            
            $today = now()->format('Y-m-d');
            $checkoutRecords = [];

            // Sabhi logs le lo jisme access granted hai
            $todayCheckoutLogs = DeviceAccessLog::whereDate('created_at', $today)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'desc')
                ->get();
            
            Log::info('Total today checkout logs found: ' . $todayCheckoutLogs->count());
            
            foreach ($todayCheckoutLogs as $checkoutLog) {
                try {
                    // Step 1: Get location details from vendor_locations
                    $vendorLocation = VendorLocation::where('name', $checkoutLog->location_name)->first();
                    
                    if (!$vendorLocation) {
                        Log::info("Vendor location not found for: " . $checkoutLog->location_name);
                        continue;
                    }
                    
                    $locationId = $vendorLocation->id;
                    
                    // Step 2: Get device connection details
                    $deviceConnection = DeviceConnection::where('device_id', $checkoutLog->device_id)->first();
                    
                    if (!$deviceConnection) {
                        Log::info("Device connection not found for device_id: " . $checkoutLog->device_id);
                        continue;
                    }
                    
                    $deviceConnectionId = $deviceConnection->id;
                    
                    // Step 3: Check device_location_assigns
                    $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnectionId)
                        ->where('location_id', $locationId)
                        ->first();
                    
                    $displayLocation = $checkoutLog->location_name; // Default
                    
                    if ($deviceLocationAssign) {
                        // Step 4: Check is_type and update display location
                        if ($deviceLocationAssign->is_type === 'check_in') {
                            $displayLocation = 'Turnstile';
                        } elseif ($deviceLocationAssign->is_type === 'check_out') {
                            $displayLocation = 'Out';
                        }
                    }
                    
                    // Sirf check_out type wale hi show karein
                    if ($displayLocation !== 'Out') {
                        continue;
                    }
                    
                    // Visitor details get karein
                    $visitorDetails = $this->getVisitorDetailsForCheckout($checkoutLog);
                    
                    // Check-in log find karein (same staff_no ke liye)
                    $checkInLog = $this->getCheckinLogForStaffNo($checkoutLog->staff_no, $today);
                    
                    // Duration calculate karein
                    $duration = $this->calculateCheckoutDuration($checkInLog, $checkoutLog);
                    
                    $checkoutRecords[] = [
                        'visitor_name' => $visitorDetails['fullName'] ?? 'N/A',
                        'host' => $visitorDetails['personVisited'] ?? 'N/A',
                        'check_in_time' => $checkInLog ? $checkInLog->created_at->format('h:i A') : 'N/A',
                        'check_out_time' => $checkoutLog->created_at->format('h:i A'),
                        'duration' => $duration,
                        'staff_no' => $checkoutLog->staff_no,
                        'location' => $displayLocation,
                        'original_location' => $checkoutLog->location_name,
                        'device_id' => $checkoutLog->device_id,
                    ];
                    
                    Log::info("Processed checkout for staff_no: {$checkoutLog->staff_no}, Display Location: {$displayLocation}");
                    
                } catch (\Exception $e) {
                    Log::error('Error processing checkout record: ' . $e->getMessage());
                    continue;
                }
            }
            
            Log::info('Total filtered checkout records (only "Out" type): ' . count($checkoutRecords));

            if ($paginate) {
                $checkoutRecords = collect($checkoutRecords);
                return $checkoutRecords->forPage(request()->get('checkouts_page', 1), $perPage)->values()->toArray();
            }

            return $checkoutRecords;
            
        } catch (\Exception $e) {
            Log::error('Error in getCheckoutsTodayModalData: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }



    private function getPaginatedTodayAppointments($perPage = 10)
    {
        try {
            $userAccessData = $this->menuService->getUserAccessData();
            
            if ($userAccessData && isset($userAccessData['today_appointments'])) {
                $todayAppointments = collect($userAccessData['today_appointments'] ?? [])
                    ->unique('staff_no')
                    ->values()
                    ->toArray();
                
                // Paginate manually
                $page = request()->get('today_page', 1);
                $offset = ($page - 1) * $perPage;
                return array_slice($todayAppointments, $offset, $perPage);
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Error getting paginated today appointments: ' . $e->getMessage());
            return [];
        }
    }

    private function getCheckinLogForStaffNo($staffNo, $date)
    {
        try {
            Log::info("Looking for check-in log for staff_no: {$staffNo}");
            
            // Sabhi logs le lo
            $allLogs = DeviceAccessLog::where('staff_no', $staffNo)
                ->whereDate('created_at', $date)
                ->where('access_granted', 1)
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($allLogs as $log) {
                // Same logic use karein location process ke liye
                $vendorLocation = VendorLocation::where('name', $log->location_name)->first();
                
                if (!$vendorLocation) {
                    continue;
                }
                
                $deviceConnection = DeviceConnection::where('device_id', $log->device_id)->first();
                
                if (!$deviceConnection) {
                    continue;
                }
                
                $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)
                    ->where('location_id', $vendorLocation->id)
                    ->first();
                
                if ($deviceLocationAssign && $deviceLocationAssign->is_type === 'check_in') {
                    Log::info("Found check-in log ID: {$log->id} for staff_no: {$staffNo}");
                    return $log;
                }
            }
            
            Log::info("No check-in log found for staff_no: {$staffNo}");
            return null;
            
        } catch (\Exception $e) {
            Log::error('Error in getCheckinLogForStaffNo: ' . $e->getMessage());
            return null;
        }
    }

private function getDeniedAccessCount24h()
{
    try {
        return DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->whereRaw('created_at >= CURDATE()')
            ->orderBy('created_at', 'desc')
            ->count();
            // dd($count->toSql()); 
    } catch (\Exception $e) {
        Log::error('Error in getDeniedAccessCount24h: ' . $e->getMessage());
        return 0;
    }
}


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
        
        // Get visitor details from API
        $visitorDetails = $this->getVisitorDetailsForAlert($staffNo);
        
        // Get check-in information
        $checkInLog = DeviceAccessLog::where('staff_no', $staffNo)
            ->where('access_granted', 1)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $duration = 'N/A';
        if ($checkInLog) {
            $checkInTime = Carbon::parse($checkInLog->created_at);
            $currentTime = now();
            $diffMinutes = $currentTime->diffInMinutes($checkInTime);
            
            if ($diffMinutes < 60) {
                $duration = $diffMinutes . ' minutes';
            } else {
                $hours = floor($diffMinutes / 60);
                $minutes = $diffMinutes % 60;
                $duration = $hours . ' hours ' . $minutes . ' minutes';
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'fullName' => $visitorDetails['fullName'] ?? 'N/A',
                'contactNo' => $visitorDetails['contactNo'] ?? 'N/A',
                'icNo' => $visitorDetails['icNo'] ?? 'N/A',
                'personVisited' => $visitorDetails['personVisited'] ?? 'N/A',
                'dateOfVisitFrom' => isset($visitorDetails['dateOfVisitFrom']) ? 
                    Carbon::parse($visitorDetails['dateOfVisitFrom'])->format('d M Y h:i A') : 'N/A',
                'dateOfVisitTo' => isset($visitorDetails['dateOfVisitTo']) ? 
                    Carbon::parse($visitorDetails['dateOfVisitTo'])->format('d M Y h:i A') : 'N/A',
                'check_in_time' => $checkInLog ? 
                    Carbon::parse($checkInLog->created_at)->format('d M Y h:i A') : 'N/A',
                'location' => $checkInLog->location_name ?? 'N/A',
                'duration' => $duration
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error getting visitor details: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error loading visitor details'
        ], 500);
    }
}

    public function refreshOnSiteData()
    {
        try {
            $visitorsOnSite = $this->getCurrentVisitorsOnSite();
            
            $twentyFourHoursAgo = Carbon::now()->subHours(24);
            $debugDeniedCount = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->count();
                
            // Log::info('Refresh On-Site Debug:', [
            //     'visitors_count' => count($visitorsOnSite),
            //     'denied_access_24h' => $debugDeniedCount,
            //     'time_range' => [
            //         'from' => $twentyFourHoursAgo->format('Y-m-d H:i:s'),
            //         'to' => now()->format('Y-m-d H:i:s')
            //     ]
            // ]);
            return response()->json([
                'success' => true,
                'visitors' => $visitorsOnSite,
                'count' => count($visitorsOnSite),
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing on-site data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing data'
            ], 500);
        }
    }


    public function refreshDeniedAccessCount()
    {
        try {
            $twentyFourHoursAgo = Carbon::now()->subHours(24);
            
            $count = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->count();
                
            // Debug: Check what records are being counted
            $records = DeviceAccessLog::where('access_granted', 0)
                ->where('acknowledge', 0)
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->select('id', 'staff_no', 'card_no', 'created_at', 'updated_at', 'access_granted', 'acknowledge')
                ->get();
                
            // Log::info('Denied Access Count Refresh:', [
            //     'count' => $count,
            //     'records_found' => $records->toArray(),
            //     'time_range' => [
            //         'from' => $twentyFourHoursAgo->format('Y-m-d H:i:s'),
            //         'to' => now()->format('Y-m-d H:i:s')
            //     ]
            // ]);
            
            return response()->json([
                'success' => true,
                'deniedAccessCount24h' => $count,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing denied access count: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing count'
            ], 500);
        }
    }

    private function getVisitorDetailsForCheckout($checkoutLog)
    {
        try {
            $javaApiResponse = $this->callJavaVendorApi($checkoutLog->staff_no);
            
            if ($javaApiResponse && isset($javaApiResponse['data'])) {
                return $javaApiResponse['data'];
            }
            
            return [
                'fullName' => 'N/A',
                'personVisited' => 'N/A'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting visitor details for checkout: ' . $e->getMessage());
            return [
                'fullName' => 'N/A',
                'personVisited' => 'N/A'
            ];
        }
    }

    private function calculateCheckoutDuration($checkInLog, $checkoutLog)
    {
        if (!$checkInLog) {
            return 'N/A';
        }
        
        try {
            $checkInTime = Carbon::parse($checkInLog->created_at);
            $checkOutTime = Carbon::parse($checkoutLog->created_at);
            
            $diffInMinutes = $checkOutTime->diffInMinutes($checkInTime);
            
            if ($diffInMinutes < 60) {
                return $diffInMinutes . ' minutes';
            }
            
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;
            
            if ($minutes == 0) {
                return $hours . ' hours';
            }
            
            return $hours . ' hours ' . $minutes . ' minutes';
            
        } catch (\Exception $e) {
            Log::error('Error calculating duration: ' . $e->getMessage());
            return 'N/A';
        }
    }

public function getUpcomingAppointmentsAjax(Request $request)
{
    try {
        $perPage = 10;
        $page = $request->get('page', 1);
        
        // Get user access data
        $userAccessData = $this->menuService->getUserAccessData();
        $upcomingAppointments = $userAccessData['upcoming_appointments'] ?? [];
        
        // Log for debugging
        Log::info('Upcoming appointments data:', [
            'total_count' => count($upcomingAppointments),
            'current_page' => $page,
            'per_page' => $perPage
        ]);
        
        // Manual pagination
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($upcomingAppointments, $offset, $perPage);
        
        $html = '';
        if (!empty($paginatedData)) {
            foreach ($paginatedData as $index => $appointment) {
                $html .= '
                <tr>
                    <td>' . ($offset + $index + 1) . '</td>
                    <td>' . ($appointment['full_name'] ?? 'N/A') . '</td>
                    <td>' . ($appointment['contact_no'] ?? 'N/A') . '</td>
                    <td>' . ($appointment['ic_no'] ?? 'N/A') . '</td>
                    <td>' . ($appointment['name_of_person_visited'] ?? 'N/A') . '</td>
                    <td>' . (\Carbon\Carbon::parse($appointment['date_from'] ?? now())->format('M d, Y')) . '</td>
                    <td>' . (\Carbon\Carbon::parse($appointment['date_from'] ?? now())->format('h:i A')) . '</td>
                    <td>' . ($appointment['purpose'] ?? 'N/A') . '</td>
                </tr>';
            }
        } else {
            $html = '
            <tr>
                <td colspan="8" class="text-center">No upcoming appointments found.</td>
            </tr>';
        }
        
        // Generate pagination
        $totalPages = max(1, ceil(count($upcomingAppointments) / $perPage));
        $paginationHtml = $this->generateCustomPaginationHtml($page, $totalPages);
        
        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => $paginationHtml,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => count($upcomingAppointments)
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in getUpcomingAppointmentsAjax: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error loading data',
            'html' => '<tr><td colspan="8" class="text-center text-danger">Error loading data. Please try again.</td></tr>',
            'pagination' => ''
        ], 500);
    }
}


private function generateCustomPaginationHtml($currentPage, $totalPages)
{
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link pagination-link" href="#" data-page="' . ($currentPage - 1) . '">Previous</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link">Previous</span>';
        $html .= '</li>';
    }
    
    // Always show page 1
    if ($currentPage == 1) {
        $html .= '<li class="page-item active">';
        $html .= '<span class="page-link">1</span>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link pagination-link" href="#" data-page="1">1</a>';
        $html .= '</li>';
    }
    
    // Show ellipsis if current page > 5
    if ($currentPage > 5) {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link">...</span>';
        $html .= '</li>';
    }
    
    // Calculate start and end of middle pages
    $start = max(2, $currentPage - 2);
    $end = min($totalPages - 1, $currentPage + 2);
    
    // Show middle pages
    for ($i = $start; $i <= $end; $i++) {
        if ($i > 1 && $i < $totalPages) {
            if ($i == $currentPage) {
                $html .= '<li class="page-item active">';
                $html .= '<span class="page-link">' . $i . '</span>';
                $html .= '</li>';
            } else {
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link pagination-link" href="#" data-page="' . $i . '">' . $i . '</a>';
                $html .= '</li>';
            }
        }
    }
    
    // Show ellipsis if current page < totalPages - 4
    if ($currentPage < $totalPages - 4) {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link">...</span>';
        $html .= '</li>';
    }
    
    // Show last page if totalPages > 1
    if ($totalPages > 1) {
        if ($currentPage == $totalPages) {
            $html .= '<li class="page-item active">';
            $html .= '<span class="page-link">' . $totalPages . '</span>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link pagination-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a>';
            $html .= '</li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link pagination-link" href="#" data-page="' . ($currentPage + 1) . '">Next</a>';
        $html .= '</li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link">Next</span>';
        $html .= '</li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

// DashboardController.php में नया method add करें

public function getActiveSecurityAlertsAjax(Request $request)
{
    try {
        $perPage = 10;
        $page = $request->get('page', 1);
        
        // Get paginated data
        $activeSecurityAlerts = DeviceAccessLog::where('access_granted', 0)
            ->where('acknowledge', 0)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page');
        
        $enrichedLogs = $this->getEnrichedDeniedAccessLogs($activeSecurityAlerts);
        
        // Prepare HTML
        $html = '';
        if (count($enrichedLogs) > 0) {
            foreach ($enrichedLogs as $index => $enrichedLog) {
                $html .= '
                <tr>
                    <td>' . (($activeSecurityAlerts->currentPage() - 1) * $perPage + $index + 1) . '</td>
                    <td>' . ($enrichedLog['visitor_details']['fullName'] ?? 'N/A') . '</td>
                    <td>' . ($enrichedLog['visitor_details']['contactNo'] ?? 'N/A') . '</td>
                    <td>' . ($enrichedLog['visitor_details']['icNo'] ?? 'N/A') . '</td>
                    <td>' . ($enrichedLog['visitor_details']['personVisited'] ?? 'N/A') . '</td>
                    <td>' . ($enrichedLog['log']->location_name ?? 'Unknown Location') . '</td>
                    <td>' . ($enrichedLog['log']->reason ?: 'Other Reason') . '</td>
                    <td>' . (\Carbon\Carbon::parse($enrichedLog['log']->created_at)->format('d M Y h:i A')) . '</td>
                </tr>';
            }
        } else {
            $html = '<tr><td colspan="8" class="text-center">No active security alerts found.</td></tr>';
        }
        
        // ✅ CUSTOM PAGINATION GENERATE KAREIN
        $paginationHtml = $this->generateCustomPaginationHtml(
            $activeSecurityAlerts->currentPage(), 
            $activeSecurityAlerts->lastPage()
        );
        
        return response()->json([
            'success' => true,
            'html' => $html,
            'pagination' => $paginationHtml,
            'current_page' => $activeSecurityAlerts->currentPage(),
            'total' => $activeSecurityAlerts->total(),
            'per_page' => $perPage,
            'total_pages' => $activeSecurityAlerts->lastPage()
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error getting paginated security alerts: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error loading data'
        ], 500);
    }
}

}

