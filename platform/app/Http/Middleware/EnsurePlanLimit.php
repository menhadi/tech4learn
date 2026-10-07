<?php

namespace App\Http\Middleware;

use App\Support\SaasAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        SaasAccess::abortIfLimitReached($resource);

        return $next($request);
    }
}
