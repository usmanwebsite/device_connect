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
        'java-auth/callback',
        'visitor-info-door/get-visitors',
        'visitor-info-door',
        '/dashboard/get-critical-alert-details',
        '/encrypt',
        '/decrypt',
    ];
}
