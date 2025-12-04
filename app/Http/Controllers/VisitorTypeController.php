<?php

namespace App\Http\Controllers;

use App\Models\Path;
use App\Models\VisitorType;
use Illuminate\Http\Request;

class VisitorTypeController extends Controller
{
    // ✅ Simple index method
    public function index()
    {
        $visitorTypes = VisitorType::with('path')->orderBy('visitor_type')->get();
        return view('visitorTypes.index', compact('visitorTypes'));
    }

    public function create()
    {
        $paths = Path::orderBy('name')->get();
        return view('visitorTypes.create', compact('paths'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'visitor_type' => 'required|string|max:255|unique:visitor_types,visitor_type',
            'path_id' => 'required|exists:paths,id'
        ]);

        VisitorType::create($request->only(['visitor_type', 'path_id']));

        return redirect()->route('visitor-types.index')
            ->with('success', 'Visitor Type created successfully!');
    }

    public function edit($id)
    {
        $visitorType = VisitorType::findOrFail($id);
        $paths = Path::orderBy('name')->get();

        return view('visitorTypes.edit', compact('visitorType', 'paths'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'visitor_type' => 'required|string|max:255|unique:visitor_types,visitor_type,'.$id,
            'path_id' => 'required|exists:paths,id'
        ]);

        $visitorType = VisitorType::findOrFail($id);
        $visitorType->update($request->only(['visitor_type', 'path_id']));

        return redirect()->route('visitor-types.index')
            ->with('success', 'Visitor Type updated successfully!');
    }

    // ✅ SIMPLIFIED DELETE METHOD (Temporary for testing)
    public function destroy($id)
    {
        // Simple delete without logs for now
        $visitorType = VisitorType::find($id);
        
        if (!$visitorType) {
            return response()->json([
                'success' => false,
                'message' => 'Visitor Type not found!'
            ], 404);
        }
        
        $visitorType->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Visitor Type deleted successfully!'
        ]);
    }
}