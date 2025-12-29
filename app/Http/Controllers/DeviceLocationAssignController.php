<?php

namespace App\Http\Controllers;

use App\Models\DeviceConnection;
use App\Models\DeviceLocationAssign;
use Illuminate\Http\Request;
use App\Models\VendorLocation;
use App\Services\MenuService;

class DeviceLocationAssignController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    // ✅ INDEX
    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $assignments = DeviceLocationAssign::with(['device', 'location'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('device_assignments.index', compact('assignments', 'angularMenu'));
    }

    // ✅ CREATE - Status condition removed
    public function create()
    {
        $devices = DeviceConnection::all(); // ✅ Simple - all devices
        $locations = VendorLocation::orderBy('name')->get();
        $types = DeviceLocationAssign::getTypes(); // ✅ Get types
        
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
        $devices = DeviceConnection::all(); // ✅ Simple - all devices
        $locations = VendorLocation::orderBy('name')->get();
        $types = DeviceLocationAssign::getTypes(); // ✅ Get types

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

