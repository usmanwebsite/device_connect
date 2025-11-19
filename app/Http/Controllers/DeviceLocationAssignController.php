<?php

namespace App\Http\Controllers;

use App\Models\DeviceConnection;
use Illuminate\Http\Request;
use App\Models\VendorLocation;
use App\Models\DeviceLocationAssign;

class DeviceLocationAssignController extends Controller
{
    // Form view
    public function create()
    {
        $devices = DeviceConnection::all();
        $locations = VendorLocation::all();
        return view('assign_device', compact('devices', 'locations'));
    }

    // Store assignment
    public function store(Request $request)
    {
        $request->validate([
            'device_id' => 'required|exists:device_connections,id',
            'location_id' => 'required|exists:vendor_locations,id',
        ]);

        DeviceLocationAssign::create([
            'device_id' => $request->device_id,
            'location_id' => $request->location_id,
        ]);

        return redirect()->back()->with('success', 'Device assigned to location successfully!');
    }
}
