<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student-api') ?: $request->user();
        $hostOrganizationId = Tenant::hostId($request->getHost());

        abort_unless(
            $student
            && $student->organization_id
            && (int) $student->organization_id === (int) $hostOrganizationId,
            404
        );

        return $next($request);
    }
}