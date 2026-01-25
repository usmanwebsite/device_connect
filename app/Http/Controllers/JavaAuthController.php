<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Services\MenuService;

class JavaAuthController extends Controller
{
    protected $menuService;

    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }

    public function handleCallback(Request $request)
    {
        // dd('heelo');
        Log::info('=== JavaAuthController Callback Start ===');
        Log::info('Callback payload', $request->all());
        Log::info('Session ID before:', ['session_id' => session()->getId()]);

        if (!$request->token) {
            Log::error('Token missing in callback');
            return redirect()->route('login')->with('error', 'Invalid callback');
        }

        // ✅ Session start करें
        session()->start();
        
        // ✅ Session regenerate न करें (session data lose हो जाता है)
        // session()->regenerate(); // ❌ ये comment out करें
        
        // ✅ Pehle purana session data clear करें
        $oldSessionId = session()->getId();
        Log::info('Old Session ID:', ['old_id' => $oldSessionId]);
        
        // ✅ Token save करें (multiple keys में)
        session()->put('java_backend_token', $request->token);
        session()->put('java_auth_token', $request->token);
        session()->put('java_user', $request->username ?? 'superadmin');
        
        // ✅ Session ID को persist करें
        $newSessionId = session()->getId();
        Log::info('New Session ID:', ['new_id' => $newSessionId]);
        
        // ✅ Session को forcefully save करें
        session()->save();
        
        Log::info('Token saved in session', [
            'session_id' => session()->getId(),
            'has_java_backend_token' => session()->has('java_backend_token'),
            'has_java_auth_token' => session()->has('java_auth_token'),
            'token_sample' => substr(session()->get('java_backend_token'), 0, 25)
        ]);
        
        // ✅ IMMEDIATE TEST: DashboardController में token check करें
        // Redirect से पहले test करें
        $testToken = session()->get('java_backend_token');
        Log::info('Token verification before redirect:', [
            'token_exists' => !empty($testToken),
            'length' => strlen($testToken)
        ]);

        // ✅ Direct URL use करें, route के through नहीं
        return redirect('vms/dashboard');
    }


    public function logout(Request $request)
    {
        // 1. Laravel auth + session destroy
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();

        $response = redirect()->away('http://localhost:8080/#/pages/login');

        foreach ($request->cookies->all() as $name => $value) {
            $response->withCookie(
                Cookie::forget($name, '/', config('session.domain'))
            );
        }

        return $response;
    }


}
