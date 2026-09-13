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
            $hex=$match[1];
            $id=substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
            $request->attributes->set('tech4learn_workspace_id',$id);
            config(['session.cookie'=>'__Host-t4l_workspace','session.domain'=>null,'session.path'=>'/',
                'session.secure'=>true,'session.http_only'=>true,'session.same_site'=>'lax']);
        } elseif (str_starts_with($request->getHost(),'t4l-') && str_ends_with($request->getHost(),'.examelite.com')) {
            // An invalid wildcard host must not fall back to the central organisation.
            abort_unless(false,404);
        }
        return $next($request);
    }
}
