<?php

namespace App\Http\Middleware;

use App\Support\SaasAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! SaasAccess::featureEnabled($feature)) {
            if ($feature === 'public_website' && ! auth()->check()) {
                $target = SaasAccess::defaultWebsiteUrl();
                $targetHost = parse_url($target, PHP_URL_HOST);

                if ($targetHost && ! Str::is($request->getHost(), $targetHost)) {
                    return redirect()->away($target);
                }
            }

            if ($feature === 'student_self_registration' && ! auth()->check()) {
                return redirect()->route('student.signin')
                    ->withErrors(['registration' => 'Student self registration is not enabled for this organization. Please use the login details provided by your institute.']);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This feature is not included in your organization plan.',
                ], 403);
            }

            if (auth()->check()) {
                return response()->view('errors.plan-feature', [
                    'feature' => $feature,
                    'message' => 'This feature is not included in your organization plan.',
                ], 403);
            }

            abort(403, 'This feature is not enabled for your organization plan.');
        }

        return $next($request);
    }
}
