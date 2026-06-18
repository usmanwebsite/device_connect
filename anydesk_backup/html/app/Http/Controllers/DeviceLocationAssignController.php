<?php

namespace App\Http\Controllers;

use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use App\Models\VendorLocation;
use App\Models\IpRange; // نیا model بنائیں
use Illuminate\Http\Request;
use App\Services\MenuService;
use Illuminate\Support\Carbon;

class DeviceLocationAssignController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    // ✅ INDEX - IP Range ke sath
    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        
        // Get IP Range settings
        $ipRange = IpRange::first(); // First record from ip_ranges table
        
        // Get all device connections
        $devices = DeviceConnection::with(['assignments'])->get();
        
        $assignmentsData = [];
        
        foreach ($devices as $device) {
            // Check registration status
            $isRegistered = $device->assignments()->exists();
            
            // Check online/offline status (if last heartbeat within 1 minute)
            $isOnline = $device->last_heartbeat && 
                       Carbon::parse($device->last_heartbeat)->diffInSeconds(now()) <= 60;
            
            // Get assignment if exists
            $assignment = $device->assignments()->first();
            
            $assignmentsData[] = [
                'device_connection_id' => $device->id,
                'device_id' => $device->device_id,
                'ip' => $device->ip,
                'last_heartbeat' => $device->last_heartbeat,
                'status' => $isOnline ? 'online' : 'offline',
                'is_registered' => $isRegistered,
                'location_name' => $assignment ? $assignment->location->name : null,
                'is_type' => $assignment ? $assignment->is_type : null,
                'created_at' => $assignment ? $assignment->created_at : null,
                'assignment_id' => $assignment ? $assignment->id : null,
            ];
        }
        
        return view('device_assignments.index', compact('assignmentsData', 'angularMenu', 'ipRange'));
    }

    // ✅ UPDATE IP RANGE
    public function updateIpRange(Request $request)
    {
        $request->validate([
            'ip_range_from' => 'required|ipv4',
            'ip_range_to'   => 'required|ipv4',
        ]);

        // ✅ Proper IP comparison
        if (ip2long($request->ip_range_from) > ip2long($request->ip_range_to)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'IP Range To must be greater than or equal to IP Range From.');
        }

        $ipRange = IpRange::first();

        if (!$ipRange) {
            IpRange::create([
                'ip_range_from' => $request->ip_range_from,
                'ip_range_to'   => $request->ip_range_to
            ]);
        } else {
            $ipRange->update([
                'ip_range_from' => $request->ip_range_from,
                'ip_range_to'   => $request->ip_range_to
            ]);
        }

        return redirect()->route('device-assignments.index')
            ->with('success', 'IP Range updated successfully!');
    }


    // ✅ GET DEVICE STATUS (AJAX) - For real-time updates
    public function getDeviceStatus()
    {
        $devices = DeviceConnection::with(['assignments'])->get();
        
        $devicesData = [];
        foreach ($devices as $device) {
            // Check online/offline status
            $isOnline = $device->last_heartbeat && 
                       Carbon::parse($device->last_heartbeat)->diffInSeconds(now()) <= 60;
            
            $devicesData[] = [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'status' => $isOnline ? 'online' : 'offline',
                'last_heartbeat_formatted' => $device->last_heartbeat ? 
                    Carbon::parse($device->last_heartbeat)->format('d-m-Y H:i:s') : 'Never',
                'ip' => $device->ip,
                'is_registered' => $device->assignments()->exists()
            ];
        }
        
        return response()->json([
            'success' => true,
            'devices' => $devicesData
        ]);
    }

    // ✅ CREATE - Status condition removed
    public function create()
    {
        $devices = DeviceConnection::all();
        $locations = VendorLocation::orderBy('name')->get();
        $types = DeviceLocationAssign::getTypes();
        
        return view('device_assignments.create', compact('devices', 'locations', 'types'));
    }

    // ✅ STORE
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:device_connections,id',
            'location_id' => 'required|exists:vendor_locations,id',
            'is_type' => 'required|in:check_in,check_out'
        ]);

        // Check if device already has assignment of same type
        $existing = DeviceLocationAssign::where('device_id', $request->device_id)
            ->where('is_type', $request->is_type)
            ->first();
            
        if ($existing) {
            $typeText = $request->is_type == 'check_in' ? 'Check-In' : 'Check-Out';
            return redirect()->back()
                ->withInput()
                ->with('error', "This device already has a {$typeText} assignment!");
        }

        DeviceLocationAssign::create([
            'device_id' => $request->device_id,
            'location_id' => $request->location_id,
            'is_type' => $request->is_type
        ]);

        return redirect()->route('device-assignments.index')
            ->with('success', 'Device assigned successfully!');
    }

    // ✅ EDIT - Status condition removed
    public function edit($id)
    {
        $assignment = DeviceLocationAssign::findOrFail($id);
        $devices = DeviceConnection::all();
        $locations = VendorLocation::orderBy('name')->get();
        $types = DeviceLocationAssign::getTypes();

        return view('device_assignments.edit', compact('assignment', 'devices', 'locations', 'types'));
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        $request->validate([
            'device_id' => 'required|exists:device_connections,id',
            'location_id' => 'required|exists:vendor_locations,id',
            'is_type' => 'required|in:check_in,check_out'
        ]);

        $assignment = DeviceLocationAssign::findOrFail($id);
        
        // Check for duplicate assignment (excluding current one)
        $existing = DeviceLocationAssign::where('device_id', $request->device_id)
            ->where('is_type', $request->is_type)
            ->where('id', '!=', $id)
            ->first();
            
        if ($existing) {
            $typeText = $request->is_type == 'check_in' ? 'Check-In' : 'Check-Out';
            return redirect()->back()
                ->withInput()
                ->with('error', "This device already has a {$typeText} assignment!");
        }

        $assignment->update([
            'device_id' => $request->device_id,
            'location_id' => $request->location_id,
            'is_type' => $request->is_type
        ]);

        return redirect()->route('device-assignments.index')
            ->with('success', 'Assignment updated successfully!');
    }

    // ✅ DESTROY
    public function destroy($id)
    {
        $assignment = DeviceLocationAssign::find($id);
        
        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found!'
            ], 404);
        }
        
        $assignment->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully!'
        ]);
    }
}

