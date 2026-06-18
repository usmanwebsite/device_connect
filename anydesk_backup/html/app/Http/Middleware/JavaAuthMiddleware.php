<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class JavaAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if Java token exists in session
        $javaToken = Session::get('java_token');
        
        if (!$javaToken) {
            Log::info('No Java token found, redirecting to Java login');
            
            // Build Java login URL with redirect back
            $javaLoginUrl = 'http://localhost:8080/#/pages/login';
            $redirectUrl = urlencode(route('java-auth.callback'));
            
            // You can either:
            // 1. Redirect to Java login page
            // return redirect($javaLoginUrl . '?redirect_url=' . $redirectUrl);
            
            // 2. Or show a message with login button
            return redirect('/')->with('info', 'Please login via Java system first.');
        }

        // Optional: Verify token periodically (every 30 minutes)
        $authTime = Session::get('java_auth_time');
        if ($authTime) {
            $authTime = strtotime($authTime);
            $currentTime = time();
            $timeDiff = $currentTime - $authTime;
            
            // If session is older than 30 minutes, verify token
            if ($timeDiff > 1800) { // 1800 seconds = 30 minutes
                try {
                    $javaBaseUrl = env('JAVA_BACKEND_URL', 'http://localhost:8080');
                    
                    $response = Http::withHeaders([
                        'x-auth-token' => $javaToken,
                        'Accept' => 'application/json',
                    ])->timeout(5)->get($javaBaseUrl . '/api/auth/check-token');

                    if (!$response->successful()) {
                        // Token invalid, clear session
                        Session::flush();
                        return redirect('/')->with('error', 'Session expired. Please login again.');
                    }
                    
                    // Update auth time
                    Session::put('java_auth_time', now()->toDateTimeString());
                    
                } catch (\Exception $e) {
                    Log::error('Java token verification failed: ' . $e->getMessage());
                    // Continue anyway if verification fails
                }
            }
        }

        // Add Java user info to request for controllers to use
        $request->attributes->add([
            'java_user' => [
                'token' => $javaToken,
                'username' => Session::get('java_username'),
                'display_name' => Session::get('java_display_name'),
                'auth_time' => Session::get('java_auth_time')
            ]
        ]);

        return $next($request);
    }
}