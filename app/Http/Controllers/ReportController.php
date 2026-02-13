<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceAccessLog;
use App\Models\DeviceConnection;
use App\Models\VendorLocation;
use App\Models\DeviceLocationAssign;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MenuService;
use Illuminate\Support\Facades\Http;

class ReportController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function accessLogs()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $locations = VendorLocation::orderBy('name')->get();
            
        return view('reports.main_report', compact('locations', 'angularMenu'));
    }


    public function getVisitorDetails(Request $request)
    {
    // dd('hello');
    $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://127.0.0.1:8080');
    $icNo = $request->query('icNo');

    if (!$icNo) {
        return response()->json(['status' => 'error', 'message' => 'IC No required'], 400);
    }

    $token = session()->get('java_backend_token')
           ?? session()->get('java_auth_token');

    if (!$token) {
        return response()->json(['status' => 'error', 'message' => 'Authentication expired'], 401);
    }

    $response = Http::withHeaders([
            'x-auth-token' => $token,
            'Accept'       => 'application/json',
        ])
        ->timeout(15)
        ->get(
            $javaBaseUrl  . '/api/vendorpass/get-visitor-details',
            ['icNo' => $icNo]
        );

        // dd($response->json());

    return response()->json($response->json(), $response->status());
}


public function getAccessLogsData(Request $request)
{
    try {
        $request->validate([
            'from_date' => 'required|date_format:Y-m-d H:i',
            'to_date'   => 'required|date_format:Y-m-d H:i',
            'locations' => 'required|array'
        ]);

        // Convert datetime-local format to database format
        $fromDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->from_date)
            ->format('Y-m-d H:i:s');

        $toDateTime = Carbon::createFromFormat('Y-m-d H:i', $request->to_date)
            ->format('Y-m-d H:i:s');
        
        Log::info('Access Logs Report Filters:', [
            'from_datetime' => $fromDateTime,
            'to_datetime' => $toDateTime,
            'locations' => $request->input('locations')
        ]);

        $locations = $request->input('locations');

        // Get pagination parameters
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $draw = $request->input('draw', 1);
        $searchValue = $request->input('search.value', '');
        $orderColumn = $request->input('order.0.column', 0);
        $orderDirection = $request->input('order.0.dir', 'asc');

        $columns = [
            0 => 'staff_no', 
            1 => 'staff_no', 
            2 => 'staff_no', 
            3 => 'staff_no', 
            4 => 'staff_no', 
            5 => 'total_access', 
            6 => 'first_access_original', 
            7 => 'last_access_original'  
        ];

        // Step 1: First get total UNIQUE visitors count
        $uniqueVisitorsQuery = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->whereNotNull('staff_no')
            ->where(function($query) use ($locations) {
                foreach ($locations as $location) {
                    $query->orWhere('location_name', 'like', '%' . $location . '%');
                }
            });
        
        // Apply search for count query too
        if (!empty($searchValue)) {
            $uniqueVisitorsQuery->where('staff_no', 'like', '%' . $searchValue . '%');
        }
        
        // Get total UNIQUE visitors (distinct staff numbers)
        $totalUniqueVisitors = $uniqueVisitorsQuery->distinct('staff_no')->count('staff_no');
        
        Log::info('Total unique visitors found:', ['count' => $totalUniqueVisitors]);

        // Step 2: Main query for paginated data
        $query = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->whereNotNull('staff_no')
            ->where(function($query) use ($locations) {
                foreach ($locations as $location) {
                    $query->orWhere('location_name', 'like', '%' . $location . '%');
                }
            })
            ->select('staff_no')
            ->selectRaw('COUNT(*) as total_access')
            ->selectRaw('MIN(created_at) as first_access_original')
            ->selectRaw('MAX(created_at) as last_access_original')
            ->groupBy('staff_no');

        // Apply search
        if (!empty($searchValue)) {
            $query->where('staff_no', 'like', '%' . $searchValue . '%');
            
            // For filtered count (when searching)
            $filteredUniqueVisitorsQuery = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
                ->where(function($query) use ($locations) {
                    foreach ($locations as $location) {
                        $query->orWhere('location_name', 'like', '%' . $location . '%');
                    }
                })
                ->where('staff_no', 'like', '%' . $searchValue . '%');
            
            $filteredUniqueVisitors = $filteredUniqueVisitorsQuery->distinct('staff_no')->count('staff_no');
        } else {
            $filteredUniqueVisitors = $totalUniqueVisitors;
        }

        // Apply ordering
        if (isset($columns[$orderColumn])) {
            if ($columns[$orderColumn] === 'total_access' || 
                $columns[$orderColumn] === 'first_access_original' || 
                $columns[$orderColumn] === 'last_access_original') {
                $query->orderBy($columns[$orderColumn], $orderDirection);
            } else {
                $query->orderBy('staff_no', $orderDirection);
            }
        } else {
            $query->orderBy('first_access_original', 'desc'); // Default: latest first access first
        }

        // Apply pagination
        $staffList = $query->skip($start)->take($length)->get();

        Log::info('Paginated staff list found:', [
            'count' => $staffList->count(),
            'page' => ($start/$length) + 1
        ]);

        // Get all logs for these staff numbers in the date range (for detailed info if needed)
        $staffNos = $staffList->pluck('staff_no');
        
        $accessLogs = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->whereIn('staff_no', $staffNos)
            ->where(function($query) use ($locations) {
                foreach ($locations as $location) {
                    $query->orWhere('location_name', 'like', '%' . $location . '%');
                }
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('staff_no');

        // Format the response for DataTables
        $formattedData = [];
        foreach ($staffList as $index => $staff) {
            $staffLogs = $accessLogs[$staff->staff_no] ?? [];
            
            $formattedData[] = [
                'DT_RowIndex' => $start + $index + 1,
                'staff_no' => $staff->staff_no, 
                'full_name' => 'N/A', 
                'contact_no' => 'N/A',
                'ic_no' => $staff->staff_no,
                'person_visited' => 'N/A', 
                'total_access' => $staff->total_access,
                'first_access' => $staff->first_access_original ? Carbon::parse($staff->first_access_original)->format('d/m/Y H:i') : 'N/A',
                'last_access' => $staff->last_access_original ? Carbon::parse($staff->last_access_original)->format('d/m/Y H:i') : 'N/A',
                'logs' => $staffLogs
            ];
        }

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalUniqueVisitors, // Total unique visitors (without search)
            'recordsFiltered' => $filteredUniqueVisitors, // Filtered unique visitors (with search)
            'data' => $formattedData,
            'success' => true,
            'from_datetime' => $fromDateTime,
            'to_datetime' => $toDateTime,
            'summary' => [
                'total_unique_visitors' => $totalUniqueVisitors,
                'total_access_records' => DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
                    ->where(function($query) use ($locations) {
                        foreach ($locations as $location) {
                            $query->orWhere('location_name', 'like', '%' . $location . '%');
                        }
                    })->count()
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Access logs report error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Error fetching report data: ' . $e->getMessage()
        ], 500);
    }
}

public function getStaffMovement($staffNo)
{
    try {
        // Get all movement history for specific staff
        $movementHistory = DeviceAccessLog::where('staff_no', $staffNo)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($log) {
                $accessGranted = (bool) $log->access_granted;
                
                $reason = $accessGranted ? '--' : ($log->reason ?? 'N/A');
                
                // Get type from device_location_assigns via device_connections
                $isType = 'N/A';
                
                // Only proceed if device_id exists
                if ($log->device_id) {
                    // First get device_connections record by matching device_id
                    $deviceConnection = DeviceConnection::where('device_id', $log->device_id)->first();
                    
                    if ($deviceConnection) {
                        // Now use the id from device_connections to find device_location_assigns
                        $deviceLocationAssign = DeviceLocationAssign::where('device_id', $deviceConnection->id)->first();
                        
                        if ($deviceLocationAssign) {
                            $isType = $deviceLocationAssign->is_type;
                        }
                    }
                }
                
                // If still not found via device_id, fallback to location-based logic
                if ($isType === 'N/A') {
                    // First try to get by exact location_id
                    if ($log->location_id) {
                        $deviceLocation = DeviceLocationAssign::where('location_id', $log->location_id)->first();
                        if ($deviceLocation) {
                            $isType = $deviceLocation->is_type;
                        }
                    } 
                    // If location_id not available, try by location name
                    else if ($log->location_name) {
                        // Try to find location by name
                        $location = VendorLocation::where('name', $log->location_name)->first();
                        if ($location) {
                            $deviceLocation = DeviceLocationAssign::where('location_id', $location->id)->first();
                            if ($deviceLocation) {
                                $isType = $deviceLocation->is_type;
                            }
                        } else {
                            // Try partial match if exact not found
                            $location = VendorLocation::where('name', 'like', '%' . $log->location_name . '%')->first();
                            if ($location) {
                                $deviceLocation = DeviceLocationAssign::where('location_id', $location->id)->first();
                                if ($deviceLocation) {
                                    $isType = $deviceLocation->is_type;
                                }
                            }
                        }
                    }
                    
                    // If still not found, check if location name contains "Turnstile" or similar
                    if ($isType === 'N/A' && $log->location_name) {
                        if (stripos($log->location_name, 'Turnstile') !== false || 
                            stripos($log->location_name, 'Main Gate') !== false ||
                            stripos($log->location_name, 'Entrance') !== false) {
                            $isType = 'check_in'; // Default to check_in
                        }
                    }
                }
                
                return [
                    'date_time' => Carbon::parse($log->created_at)->format('d M Y h:i:s A'),
                    'location' => $log->location_name ?? 'Unknown',
                    'access_granted' => $log->access_granted, // اصل value (1 یا 0)
                    'reason' => $reason,
                    'type' => $isType
                ];
            });

        return response()->json([
            'success' => true,
            'staff_no' => $staffNo,
            'movement_history' => $movementHistory,
            'total_movements' => $movementHistory->count()
        ]);

    } catch (\Exception $e) {
        Log::error('Staff movement error: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error fetching staff movement data'
        ], 500);
    }
}
}


