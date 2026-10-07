<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Tenant
{
    private static ?Organization $organization = null;
    private static ?string $resolvedKey = null;
    private static ?string $accessKey = null;

    public static function resolve(?string $host = null): Organization
    {
        $host = self::normalizeHost($host ?: request()->getHost());
        if (!self::$organization || self::$resolvedKey !== $host) {
            self::$organization = self::resolveByHost($host);
            self::$resolvedKey = $host;
        }
        return self::assertAccess(self::$organization);
    }

    public static function resolveByHost(?string $host = null): Organization
    {
        $host = self::normalizeHost($host ?: request()->getHost());

        return Cache::remember('tenant.organization.host.'.hash('sha256', $host), 300, function () use ($host) {
            $platformHost = self::normalizeHost((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $subdomain = null;

            if ($platformHost !== '' && str_ends_with($host, '.'.$platformHost)) {
                $candidate = substr($host, 0, -1 * (strlen($platformHost) + 1));
                if ($candidate !== '' && ! str_contains($candidate, '.')) {
                    $subdomain = $candidate;
                }
            }

            return Organization::query()
                ->where(function ($query) use ($host, $subdomain) {
                    $query->where('domain', $host);
                    if ($subdomain !== null) {
                        $query->orWhere('subdomain', $subdomain);
                    }
                })
                ->where('status', 'active')
                ->firstOrFail();
        });
    }

    public static function hostId(?string $host = null): ?int
    {
        return self::resolveByHost($host)?->id;
    }

    public static function id(?string $host = null): ?int
    {
        return self::resolve($host)?->id;
    }

    public static function current(?string $host = null): Organization
    {
        return self::resolve($host);
    }

    public static function clear(): void
    {
        self::$organization = null;
        self::$resolvedKey = null;
        self::$accessKey = null;
    }

    private static function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    public static function assertAccess(Organization $organization, bool $refresh = false): Organization
    {
        $actor = Auth::user() ?? Auth::guard('student')->user();
        $key = $organization->id.':'.($actor ? get_class($actor).':'.$actor->getAuthIdentifier() : 'public');
        if (!$refresh && self::$accessKey === $key) {
            return $organization;
        }
        $organization = Organization::query()->whereKey($organization->id)
            ->where('status', 'active')->firstOrFail();
        if ($actor instanceof User) {
            $stored = DB::table('users')->where('id', $actor->getAuthIdentifier())->first();
            abort_unless($stored && $stored->status === 'Active' && !(bool)($stored->deleted ?? false), 403);
            // Legacy "admin" roles are tenant roles, never platform authority.
            if (!(bool)$stored->is_platform_admin) {
                abort_unless(DB::table('organization_users')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $stored->id)->where('status', 1)->exists(), 403);
            }
        } elseif ($actor instanceof Student) {
            abort_unless(DB::table('students')->where('id', $actor->getAuthIdentifier())
                ->where('organization_id', $organization->id)->where('status', 'Active')->exists(), 403);
        } elseif ($actor !== null) {
            abort(403);
        }
        self::$accessKey = $key;
        self::$organization = $organization;
        return $organization;
    }
}
