<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AttendanceBridge;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerifyPlatformIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        // Logout must remain usable when the remote account/session was revoked.
        if (!config('attendance.api_url') || ($request->is('logout') && $request->isMethod('POST'))) {
            return $next($request);
        }
        $actor=Auth::guard('web')->user();
        if (!$actor instanceof User) { return $next($request); }
        $marker=$request->session()->get('foundation_platform_identity');
        $platform=(bool)DB::table('users')->where('id',$actor->id)->value('is_platform_admin');
        if (!$platform && $marker===null) { return $next($request); }
        abort_unless($platform && is_array($marker),403);
        $bridge=app(AttendanceBridge::class);
        $identity=$bridge->platformIdentity($request,$actor);
        abort_unless($identity===$marker,403);
        $response=$next($request);
        // Long requests must not release privileged data after role/link revocation.
        abort_unless($bridge->platformIdentity($request,$actor)===$identity,403);
        $response->headers->set('Cache-Control','no-store');
        return $response;
    }
}
