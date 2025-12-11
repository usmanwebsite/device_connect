<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class JavaAuthController extends Controller
{
public function handleCallback(Request $request)
{
    if ($request->success == 'true' && $request->token) {

        session([
            'java_auth_token' => $request->token,
            'java_user' => $request->user
        ]);

        session()->save(); // VERY IMPORTANT

        return redirect()->route('dashboard');
    }

    return redirect()->route('login')->with('error', 'Invalid callback data');
}

    
    // ✅ NEW: Direct token validation endpoint
    public function validateToken(Request $request)
    {
        $token = $request->token;
        
        if (!$token) {
            return response()->json(['valid' => false, 'error' => 'No token provided']);
        }
        
        // Simple validation - you can add more logic
        return response()->json([
            'valid' => true,
            'username' => $request->username ?? 'superadmin',
            'redirect' => url('/dashboard?token=' . $token . '&username=' . $request->username)
        ]);
    }
}

