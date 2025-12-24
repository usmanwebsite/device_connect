<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Path;
use App\Models\VendorLocation; // Add this
use App\Services\MenuService;

class PathController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function index()
    {
        $angularMenu = $this->menuService->getFilteredAngularMenu();
        $paths = Path::orderBy('id','desc')->get();
        
        // Get all unique door names from vendor_locations table
        $vendorLocations = VendorLocation::orderBy('name', 'asc')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
        
        return view('paths.index', compact('paths', 'angularMenu', 'vendorLocations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'doors' => 'required|array|min:1',
        ]);

        // Validate that all door names exist in vendor_locations
        $validDoors = [];
        foreach ($request->doors as $door) {
            if (VendorLocation::where('name', $door)->exists()) {
                $validDoors[] = $door;
            } else {
                return back()->withErrors(['doors' => "Invalid door name: {$door}"]);
            }
        }

        Path::create([
            'name' => $request->name,
            'doors' => implode(',', $validDoors),
        ]);

        return redirect()->route('paths.index')->with('success', 'Path created successfully!');
    }

    public function edit($id)
    {
        $path = Path::findOrFail($id);
        $vendorLocations = VendorLocation::orderBy('name', 'asc')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
            
        return view('paths.edit', compact('path', 'vendorLocations'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'doors' => 'required|array|min:1',
        ]);

        $path = Path::findOrFail($id);
        
        // Validate that all door names exist in vendor_locations
        $validDoors = [];
        foreach ($request->doors as $door) {
            if (VendorLocation::where('name', $door)->exists()) {
                $validDoors[] = $door;
            } else {
                return back()->withErrors(['doors' => "Invalid door name: {$door}"]);
            }
        }
        
        $path->update([
            'name' => $request->name,
            'doors' => implode(',', $validDoors),
        ]);

        return redirect()->route('paths.index')->with('success', 'Path updated successfully!');
    }
}


