<?php

namespace App\Http\Middleware;

use App\Models\Configuration;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenant::clear();

        $logout=$request->is('logout') && $request->isMethod('POST');
        // A revoked native role must still be able to clear its sessions.
        $organization = $logout ? Tenant::resolveByHost($request->getHost()) : Tenant::resolve($request->getHost());

        app()->instance('currentOrganization', $organization);
        View::share('currentOrganization', $organization);

        if (Schema::hasTable('configurations')) {
            $timezone = Configuration::where('organization_id', $organization->id)->value('timezone');
            if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }
        }

        $response = $next($request);
        if (!$logout) { Tenant::assertAccess($organization, true); }
        return $response;
    }
}
