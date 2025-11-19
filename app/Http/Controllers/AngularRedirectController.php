<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AngularRedirectController extends Controller
{
   public function redirect($route)
    {
        // dd('fg');
        $base = rtrim(config('app.angular_url'), '/');
        $route = ltrim($route, '/');

        return redirect()->away($base . '/#/' . $route);
    }

}