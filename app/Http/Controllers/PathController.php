<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Path;
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

public function refreshVendorLocations()
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
        $url = $javaBaseUrl . '/api/admin/locations/active';

        // 🔗 Call Java API
        $response = Http::withHeaders([
            'x-auth-token' => $token,
            'Accept' => 'application/json',
        ])->timeout(30)->get($url);

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations from Java. Status: ' . $response->status()
            ], 500);
        }

        $javaData = $response->json();
        
        // Check if response has correct format
        if (!isset($javaData['data']) || $javaData['status'] !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid response from Java API: ' . ($javaData['message'] ?? 'Unknown error')
            ], 500);
        }

        $javaLocations = collect($javaData['data'])
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique();

        // Existing Laravel locations
        $existing = VendorLocation::pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->toArray();

        $inserted = 0;
        $skipped = 0;

        DB::beginTransaction();

        foreach ($javaLocations as $location) {
            $location = trim($location);
            
            if (empty($location)) {
                continue;
            }
            
            if (!in_array(strtolower($location), $existing)) {
                VendorLocation::create([
                    'meetingRoom' => 'Null',
                    'name' => $location,
                    'statusId' => '100001',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $inserted++;
            } else {
                $skipped++;
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Locations refreshed successfully. $inserted new location(s) added, $skipped already existed.",
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total_received' => $javaLocations->count()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Error refreshing vendor locations: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Error refreshing locations: ' . $e->getMessage()
        ], 500);
    }
}

}


