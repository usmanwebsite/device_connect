<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VisitorType;

class JavaVisitorTypeController extends Controller
{
    public function index(Request $request)
    {
        // Simple retrieval: only id and visitor_type
        $types = VisitorType::select('id', 'visitor_type')
        ->orderBy('visitor_type')
        ->get();


        return response()->json([
        'success' => true,
        'data' => $types
        ], 200);
    }
}
