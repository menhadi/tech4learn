<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;

class CheckInstallation
{
    public function handle($request, Closure $next)
    {
        if ($this->isInstalled()) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    private function isInstalled()
    {
        return env('APP_INSTALLED', false);
    }
}