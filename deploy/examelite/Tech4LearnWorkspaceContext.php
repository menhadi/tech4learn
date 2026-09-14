<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Runs before cookie/session middleware; never changes the main ExamElite session. */
final class Tech4LearnWorkspaceContext
{
    public function handle(Request $request, Closure $next)
    {
        if (preg_match('/^t4l-([a-f0-9]{32})\.examelite\.com$/D', $request->getHost(), $match)) {
            // Private adapters use the central API and invoke native controllers
            // directly. Old browser sessions must never reach native pages again.
            abort_unless(false,410,'External workspaces have been retired. Open Exams & results in your Tech4Learn organisation.');
        } elseif (str_starts_with($request->getHost(),'t4l-') && str_ends_with($request->getHost(),'.examelite.com')) {
            // An invalid wildcard host must not fall back to the central organisation.
            abort_unless(false,404);
        }
        return $next($request);
    }
}
