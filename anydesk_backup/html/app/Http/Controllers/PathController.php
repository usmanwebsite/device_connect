<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use App\Models\Path;
use App\Models\VendorLocation;
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
        try {
            Log::info('=== Paths Page Accessed ===');

            // Get token from session
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            
            $angularMenu = [];
            if ($token) {
                try {
                    // ✅ FIXED: Use getFilteredAngularMenuWithToken like Dashboard
                    $angularMenu = $this->menuService->getFilteredAngularMenuWithToken($token);

                    // Get user access data if needed
                    $userAccessData = $this->menuService->getUserAccessData();
                    if ($userAccessData && isset($userAccessData['user_id'])) {
                        session()->put('java_user_id', $userAccessData['user_id']);
                        Log::info('User ID saved in paths session:', ['user_id' => $userAccessData['user_id']]);
                    }
                    
                    if (empty($angularMenu) || (is_array($angularMenu) && count($angularMenu) === 0)) {
                        Log::warning('Empty angularMenu returned in paths. Redirecting user.');
                        return redirect()->away(config('app.angular_url'))
                            ->with('error', 'Session expired. Please login again.');
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Menu error in paths: ' . $e->getMessage());
                    $angularMenu = [];
                    return redirect()->away(config('app.angular_url'))
                        ->with('error', 'Session expired. Please login again.');
                }
            } else {
                Log::error('NO TOKEN FOUND IN PATHS PAGE!');
                return redirect()->away(config('app.angular_url'))
                    ->with('error', 'Session expired. Please login again.');
            }

            // Get paths and vendor locations
            $paths = Path::orderBy('id', 'desc')->get();
            
            $vendorLocations = VendorLocation::orderBy('name', 'asc')
                ->pluck('name')
                ->unique()
                ->values()
                ->all();
            
            Log::info('Paths page loaded successfully', [
                'paths_count' => $paths->count(),
                'vendor_locations_count' => count($vendorLocations)
            ]);
            
            return view('paths.index', compact('paths', 'angularMenu', 'vendorLocations'));
            
        } catch (\Exception $e) {
            Log::error('Error in PathController index: ' . $e->getMessage());
            
            return view('paths.index', [
                'paths' => collect(),
                'angularMenu' => [],
                'vendorLocations' => []
            ])->with('error', 'Error loading page: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
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

            Log::info('New path created: ' . $request->name);

            return redirect()->route('paths.index')->with('success', 'Path created successfully!');
            
        } catch (\Exception $e) {
            Log::error('Error creating path: ' . $e->getMessage());
            return back()->with('error', 'Error creating path: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        try {
            // Get token and menu like index method
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');
            
            if (!$token) {
                Log::error('No token found in edit path');
                return redirect()->route('paths.index')->with('error', 'Session expired. Please login again.');
            }
            
            $angularMenu = $this->menuService->getFilteredAngularMenuWithToken($token);
            
            $path = Path::findOrFail($id);
            $vendorLocations = VendorLocation::orderBy('name', 'asc')
                ->pluck('name')
                ->unique()
                ->values()
                ->all();
                
            return view('paths.edit', compact('path', 'vendorLocations', 'angularMenu'));
            
        } catch (\Exception $e) {
            Log::error('Error editing path: ' . $e->getMessage());
            return redirect()->route('paths.index')->with('error', 'Error loading edit page: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
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

            Log::info('Path updated: ' . $request->name);

            return redirect()->route('paths.index')->with('success', 'Path updated successfully!');
            
        } catch (\Exception $e) {
            Log::error('Error updating path: ' . $e->getMessage());
            return back()->with('error', 'Error updating path: ' . $e->getMessage());
        }
    }

    public function refreshLocationHierarchy()
    {
        try {
            Log::info('=== Starting refreshLocationHierarchy ===');
            
            $token = session()->get('java_backend_token') ?? session()->get('java_auth_token');

            if (!$token) {
                Log::error('Java token not found in session for location hierarchy refresh');
                return response()->json([
                    'success' => false,
                    'message' => 'Java token not found. Please login again.'
                ], 401);
            }

            Log::info('Token found, calling Java API...');
            $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
            $url = $javaBaseUrl . '/api/admin/locations/all-hierarchy';

            Log::info('Java API URL: ' . $url);

            // Call Java API for hierarchical data
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Accept' => 'application/json',
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error('Java API call failed. Status: ' . $response->status() . ' Body: ' . $response->body());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch location hierarchy from Java. Status: ' . $response->status()
                ], 500);
            }

            $javaData = $response->json();
            
            if (!isset($javaData['data']) || ($javaData['status'] ?? '') !== 'success') {
                Log::error('Invalid response from Java API: ' . json_encode($javaData));
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from Java API: ' . ($javaData['message'] ?? 'Unknown error')
                ], 500);
            }

            $locationData = $javaData['data'];
            $mainLocations = $locationData['main_locations'] ?? [];
            $subLocations = $locationData['sub_locations'] ?? [];

            Log::info('Java API response received', [
                'main_locations_count' => count($mainLocations),
                'sub_locations_count' => count($subLocations)
            ]);

            DB::beginTransaction();

            $mainInserted = 0;
            $mainSkipped = 0;
            $subInserted = 0;
            $subSkipped = 0;

            // 1. Process main locations into `locations` table
            foreach ($mainLocations as $mainLoc) {
                $name = trim($mainLoc['NAME'] ?? ($mainLoc['name'] ?? ''));
                
                if (empty($name)) {
                    continue;
                }
                
                // Check if location exists by name
                $existingLocation = Location::where('name', $name)->first();
                
                if (!$existingLocation) {
                    Location::create([
                        'name' => $name,
                        'statusId' => $mainLoc['STATUSID'] ?? ($mainLoc['statusId'] ?? '100001'),
                        'meetingRoom' => 'Null',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $mainInserted++;
                    Log::info('Inserted main location: ' . $name);
                } else {
                    $mainSkipped++;
                    Log::info('Skipped existing main location: ' . $name);
                }
            }

            // 2. Process sub-locations into `vendor_locations` table
            foreach ($subLocations as $subLoc) {
                $name = trim($subLoc['NAME'] ?? ($subLoc['name'] ?? ''));
                $parentName = trim($subLoc['PARENT_NAME'] ?? ($subLoc['parentName'] ?? ''));
                
                if (empty($name) || empty($parentName)) {
                    Log::warning('Skipping sub-location with empty name or parent', [
                        'name' => $name,
                        'parentName' => $parentName
                    ]);
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
                        'statusId' => $subLoc['STATUSID'] ?? ($subLoc['statusId'] ?? '100001'),
                        'meetingRoom' => 'Null',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $subInserted++;
                    Log::info('Inserted sub-location: ' . $name . ' under parent: ' . $parentName);
                } else {
                    $subSkipped++;
                    Log::info('Skipped existing sub-location: ' . $name);
                }
            }

            DB::commit();

            Log::info('Location hierarchy refresh completed', [
                'main_inserted' => $mainInserted,
                'main_skipped' => $mainSkipped,
                'sub_inserted' => $subInserted,
                'sub_skipped' => $subSkipped
            ]);

            return response()->json([
                'success' => true,
                'message' => "Location hierarchy refreshed successfully!",
                'summary' => [
                    'locations' => [
                        'inserted' => $mainInserted,
                        'skipped' => $mainSkipped,
                        'total' => count($mainLocations)
                    ],
                    'vendor_locations' => [
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

