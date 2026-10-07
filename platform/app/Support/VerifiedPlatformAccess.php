<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifiedPlatformAccess
{
    public static function allowed(Request $request, mixed $user): bool
    {
        return $user instanceof User && config('attendance.api_url')
            && $request->attributes->get('foundation_verified_platform_actor') === (string)$user->id
            && DB::table('users')->where('id',$user->id)->where('status','Active')
                ->where('deleted',false)->where('is_platform_admin',true)->exists();
    }
}
