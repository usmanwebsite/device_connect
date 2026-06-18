<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AngularRedirectController extends Controller
{
   public function redirect($route)
    {
        // dd('fg');
        $base = rtrim('http://' . request()->getHost() . ':8080', '/');
        $route = ltrim($route, '/');

        return redirect()->away($base . '/#/' . $route);
    }

}