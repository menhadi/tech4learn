<?php

namespace App\Services;

use App\Models\UrlRedirect;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UrlRedirectService
{
    public function findForRequest(Request $request): ?UrlRedirect
    {
        if (! in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)
            || ! Schema::hasTable('url_redirects')) {
            return null;
        }

        $organizationId = Tenant::id($request->getHost());
        $sourcePath = self::normalizeSourcePath($request->getPathInfo());

        if (! $organizationId || ! $sourcePath) {
            return null;
        }

        $redirect = UrlRedirect::query()
            ->forOrganization($organizationId)
            ->where('source_path', $sourcePath)
            ->where('is_active', true)
            ->first();

        if (! $redirect || $this->wouldLoop($redirect)) {
            return null;
        }

        return $redirect;
    }

    public function recordHit(UrlRedirect $redirect): void
    {
        UrlRedirect::query()->whereKey($redirect->getKey())->update([
            'hit_count' => DB::raw('hit_count + 1'),
            'last_hit_at' => now(),
        ]);
    }

    public static function normalizeSourcePath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === ''
            || str_contains($value, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        $path = parse_url(str_starts_with($value, '/') ? $value : '/'.$value, PHP_URL_PATH);
        $path = preg_replace('#/+#', '/', (string) $path);

        if ($path === '') {
            return '/';
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function normalizeTargetPath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === ''
            || ! str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return null;
        }

        $fragmentPosition = strpos($value, '#');
        if ($fragmentPosition !== false) {
            $value = substr($value, 0, $fragmentPosition);
        }

        [$path, $query] = array_pad(explode('?', $value, 2), 2, null);
        $path = self::normalizeSourcePath($path);

        if (! $path) {
            return null;
        }

        return $query !== null && $query !== '' ? $path.'?'.$query : $path;
    }

    public static function targetPathOnly(string $target): string
    {
        return self::normalizeSourcePath(parse_url($target, PHP_URL_PATH)) ?: '/';
    }

    private function wouldLoop(UrlRedirect $redirect): bool
    {
        $targetPath = self::targetPathOnly($redirect->target_path);

        if ($targetPath === $redirect->source_path) {
            return true;
        }

        return UrlRedirect::query()
            ->forOrganization((int) $redirect->organization_id)
            ->where('is_active', true)
            ->where('source_path', $targetPath)
            ->where('target_path', $redirect->source_path)
            ->exists();
    }
}
