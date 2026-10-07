<?php

namespace App\Http\Middleware;

use App\Support\SaasAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! SaasAccess::isPlatformAdmin()) {
            abort(403, 'Only platform admins can access SaaS Control Center.');
        }

        return $next($request);
    }
}
