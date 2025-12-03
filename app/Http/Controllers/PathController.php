<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Path;
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
        // dd($paths);
        return view('paths.index', compact('paths','angularMenu'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'doors' => 'required|array|min:1', // at least one door
        ]);

        Path::create([
            'name' => $request->name,
            'doors' => implode(',', $request->doors), // store comma separated
        ]);

        return redirect()->route('paths.index')->with('success', 'Path created successfully!');

    }

    public function edit($id)
    {
        $path = Path::findOrFail($id);
        return view('paths.edit', compact('path'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'doors' => 'required|array|min:1',
            'door_order' => 'sometimes|string',
        ]);

        $path = Path::findOrFail($id);
        
        // Use custom order if provided, otherwise use doors array
        $doors = $request->filled('door_order') 
            ? explode(',', $request->door_order)
            : $request->doors;
        
        $path->update([
            'name' => $request->name,
            'doors' => implode(',', $doors),
        ]);

        return redirect()->route('paths.index')->with('success', 'Path updated successfully!');
    }


}
