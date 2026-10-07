<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoogleSheetsAdminAccess
{
    public static function allowed(): bool
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return false;
        }

        if (SaasAccess::isPlatformAdmin()) {
            return true;
        }

        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }
        } catch (\Throwable) {
            // Fall through to the organization membership check.
        }

        $organizationId = SaasAccess::organization()?->id;

        return (bool) ($organizationId && DB::table('organization_users')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereIn('role', ['owner', 'admin'])
            ->exists());
    }
}
