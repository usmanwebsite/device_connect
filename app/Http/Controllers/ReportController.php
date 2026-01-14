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

            // Column mapping for ordering - صرف ان columns کے لیے جو database میں موجود ہیں
            $columns = [
                0 => 'staff_no', // No - staff_no سے order کریں
                1 => 'staff_no', // Visitor Name - بھی staff_no سے order کریں (frontend میں update ہوگا)
                2 => 'staff_no', // Contact No - بھی staff_no سے order کریں
                3 => 'staff_no', // IC No - یہ staff_no ہی ہے
                4 => 'staff_no', // Person Visited - بھی staff_no سے order کریں
                5 => 'total_access', // Total Access - یہ موجود ہے
                6 => 'first_access', // First Access - یہ موجود ہے
                7 => 'last_access'  // Last Access - یہ موجود ہے
            ];

            // Get unique staff numbers with pagination
            $query = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
                ->where(function($query) use ($locations) {
                    foreach ($locations as $location) {
                        $query->orWhere('location_name', 'like', '%' . $location . '%');
                    }
                })
                ->select('staff_no')
                ->selectRaw('COUNT(*) as total_access')
                ->selectRaw('MIN(created_at) as first_access')
                ->selectRaw('MAX(created_at) as last_access')
                ->groupBy('staff_no');

            // Apply search
            if (!empty($searchValue)) {
                $query->where('staff_no', 'like', '%' . $searchValue . '%');
            }

            // Get total count before pagination
            $totalRecords = $query->count();

            // Apply ordering
            if (isset($columns[$orderColumn])) {
                if ($columns[$orderColumn] === 'total_access' || 
                    $columns[$orderColumn] === 'first_access' || 
                    $columns[$orderColumn] === 'last_access') {
                    $query->orderBy($columns[$orderColumn], $orderDirection);
                } else {
                    // باقی سب کے لیے staff_no سے order کریں
                    $query->orderBy('staff_no', $orderDirection);
                }
            } else {
                $query->orderBy('staff_no', 'asc');
            }

            // Apply pagination
            $staffList = $query->skip($start)->take($length)->get();

            Log::info('Staff list found:', ['count' => $staffList->count()]);

            // Get all logs for these staff numbers in the date range
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
                    'staff_no' => $staff->staff_no, // یہ icNo ہے اور frontend میں استعمال ہوگا
                    'full_name' => 'Loading...', // Java API سے آئے گا
                    'contact_no' => 'Loading...', // Java API سے آئے گا
                    'ic_no' => $staff->staff_no, // یہ staff_no ہی ہے (icNo)
                    'person_visited' => 'Loading...', // Java API سے آئے گا
                    'total_access' => $staff->total_access,
                    'first_access' => $staff->first_access ? Carbon::parse($staff->first_access)->format('d/m/Y H:i') : 'N/A',
                    'last_access' => $staff->last_access ? Carbon::parse($staff->last_access)->format('d/m/Y H:i') : 'N/A',
                    'logs' => $staffLogs
                ];
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $formattedData,
                'success' => true,
                'from_datetime' => $fromDateTime,
                'to_datetime' => $toDateTime
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
                    // Determine reason based on access_granted
                    $reason = $log->access_granted ? '--' : ($log->reason ?? 'N/A');
                    
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
                        'date_time' => Carbon::parse($log->created_at)->format('d M Y h:i A'),
                        'location' => $log->location_name ?? 'Unknown',
                        'access_granted' => $log->access_granted ? 'Yes' : 'No',
                        'reason' => $reason,
                        'action' => $log->access_granted ? 'Entered' : 'Denied Entry',
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


