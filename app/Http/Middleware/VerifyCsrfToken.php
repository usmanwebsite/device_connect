<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/vms/java-auth/callback',
        'vms/visitor-info-door/get-visitors',
        '/vms/visitor-info-door',
        '/vms/dashboard/get-critical-alert-details',
        '/vms/encrypt',
        '/vms/decrypt',
    ];
}
