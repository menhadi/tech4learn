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
        // 💳 Payment Webhooks (Existing)
        '/payment/webhook/*',

        // 🛠️ Installer Routes (New - 419 Error Fix)
        'installer/*',             // Sabhi installer sub-pages ke liye
        'installer/purchase-code', // Khaas taur par purchase verification ke liye
        'installer/database',      // Database setup ke liye
        'installer/information',   // Admin setup ke liye
    ];
}