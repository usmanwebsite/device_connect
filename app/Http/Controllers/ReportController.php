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
                'from_date' => 'required|date_format:Y-m-d\TH:i',
                'to_date' => 'required|date_format:Y-m-d\TH:i',
                'locations' => 'required|array'
            ]);

            // ✅ Convert datetime-local format to database format
            $fromDateTime = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('from_date'));
            $toDateTime = Carbon::createFromFormat('Y-m-d\TH:i', $request->input('to_date'));
            
            // ✅ Add seconds to make complete datetime
            $fromDateTime = $fromDateTime->format('Y-m-d H:i:s');
            $toDateTime = $toDateTime->format('Y-m-d H:i:s');
            
            Log::info('Access Logs Report Filters:', [
                'from_datetime' => $fromDateTime,
                'to_datetime' => $toDateTime,
                'locations' => $request->input('locations')
            ]);

            $locations = $request->input('locations');

            // Get unique staff numbers for the selected date range and locations
            $staffList = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
                ->where(function($query) use ($locations) {
                    foreach ($locations as $location) {
                        $query->orWhere('location_name', 'like', '%' . $location . '%');
                    }
                })
                ->select('staff_no')
                ->distinct()
                ->get()
                ->pluck('staff_no');

            Log::info('Staff list found:', ['count' => $staffList->count()]);

            // Get all logs for these staff numbers in the date range
            $accessLogs = DeviceAccessLog::whereBetween('created_at', [$fromDateTime, $toDateTime])
                ->whereIn('staff_no', $staffList)
                ->where(function($query) use ($locations) {
                    foreach ($locations as $location) {
                        $query->orWhere('location_name', 'like', '%' . $location . '%');
                    }
                })
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('staff_no');

            return response()->json([
                'success' => true,
                'staff_list' => $staffList,
                'access_logs' => $accessLogs,
                'total_staff' => $staffList->count(),
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


