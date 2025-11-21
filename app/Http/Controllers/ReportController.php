<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceAccessLog;
use App\Models\VendorLocation; // Add this
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function accessLogs()
    {
        $locations = VendorLocation::orderBy('name')->get();
            
        return view('reports.main_report', compact('locations'));
    }

    public function getAccessLogsData(Request $request)
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'location' => 'required|string'
            ]);

            $date = $request->input('date');
            $location = $request->input('location');

            // ✅ Get unique staff numbers for the selected date and location
            $staffList = DeviceAccessLog::whereDate('created_at', $date)
                ->where('location_name', 'like', '%' . $location . '%')
                ->select('staff_no')
                ->distinct()
                ->get()
                ->pluck('staff_no');

            // ✅ Get all logs for these staff numbers on that date
            $accessLogs = DeviceAccessLog::whereDate('created_at', $date)
                ->whereIn('staff_no', $staffList)
                ->where('location_name', 'like', '%' . $location . '%')
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('staff_no');

            return response()->json([
                'success' => true,
                'staff_list' => $staffList,
                'access_logs' => $accessLogs,
                'total_staff' => $staffList->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Access logs report error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching report data'
            ], 500);
        }
    }

    public function getStaffMovement($staffNo)
    {
        try {
            // ✅ Get all movement history for specific staff
            $movementHistory = DeviceAccessLog::where('staff_no', $staffNo)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($log) {
                    return [
                        'date_time' => Carbon::parse($log->created_at)->format('d M Y h:i A'),
                        'location' => $log->location_name ?? 'Unknown',
                        'access_granted' => $log->access_granted ? 'Yes' : 'No',
                        'reason' => $log->reason ?? 'N/A',
                        'action' => $log->access_granted ? 'Entered' : 'Denied Entry'
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
