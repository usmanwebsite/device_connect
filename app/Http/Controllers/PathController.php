<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use App\Models\Path;
use App\Models\SubVendorLocation;
use App\Models\VendorLocation; // Add this
use App\Services\MenuService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

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

        // Validate that all door names exist in vendor_locations table
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
        
        // Validate that all door names exist in vendor_locations table
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

    public function refreshLocationHierarchy()
    {
        try {
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Java token not found. Please login again.'
                ], 401);
            }

            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $url = $javaBaseUrl . '/api/admin/locations/all-hierarchy';

            // Call Java API for hierarchical data
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch location hierarchy from Java. Status: ' . $response->status()
                ], 500);
            }

            $javaData = $response->json();
            
            if (!isset($javaData['data']) || $javaData['status'] !== 'success') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from Java API: ' . ($javaData['message'] ?? 'Unknown error')
                ], 500);
            }

            $locationData = $javaData['data'];
            $mainLocations = $locationData['main_locations'] ?? [];
            $subLocations = $locationData['sub_locations'] ?? [];

            DB::beginTransaction();

            $mainInserted = 0;
            $mainSkipped = 0;
            $subInserted = 0;
            $subSkipped = 0;

            // 1. Process main locations into `locations` table
            foreach ($mainLocations as $mainLoc) {
                $name = trim($mainLoc['NAME'] ?? '');
                
                if (empty($name)) {
                    continue;
                }
                
                // Check if location exists by name
                $existingLocation = Location::where('name', $name)->first();
                
                if (!$existingLocation) {
                    Location::create([
                        'name' => $name,
                        'statusId' => $mainLoc['STATUSID'] ?? '100001',
                        'meetingRoom' => 'Null',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $mainInserted++;
                } else {
                    $mainSkipped++;
                }
            }

            // 2. Process sub-locations into `vendor_locations` table
            foreach ($subLocations as $subLoc) {
                $name = trim($subLoc['NAME'] ?? '');
                $parentName = $subLoc['PARENT_NAME'] ?? '';
                
                if (empty($name) || empty($parentName)) {
                    continue;
                }
                
                // Find parent location by name
                $parentLocation = Location::where('name', $parentName)->first();
                
                if (!$parentLocation) {
                    Log::warning("Parent location not found for sub-location: {$name}. Parent Name: {$parentName}");
                    continue;
                }
                
                // Check if vendor location exists (by name and parent)
                $existingVendorLocation = VendorLocation::where('location_id', $parentLocation->id)
                    ->where('name', $name)
                    ->first();
                
                if (!$existingVendorLocation) {
                    VendorLocation::create([
                        'location_id' => $parentLocation->id,
                        'name' => $name,
                        'statusId' => $subLoc['STATUSID'] ?? '100001',
                        'meetingRoom' => 'Null',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $subInserted++;
                } else {
                    $subSkipped++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Location hierarchy refreshed successfully!",
                'summary' => [
                    'locations' => [ // Main locations
                        'inserted' => $mainInserted,
                        'skipped' => $mainSkipped,
                        'total' => count($mainLocations)
                    ],
                    'vendor_locations' => [ // Sub-locations
                        'inserted' => $subInserted,
                        'skipped' => $subSkipped,
                        'total' => count($subLocations)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error refreshing location hierarchy: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error refreshing location hierarchy: ' . $e->getMessage()
            ], 500);
        }
    }

}


