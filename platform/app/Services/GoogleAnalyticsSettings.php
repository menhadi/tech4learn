<?php

namespace App\Services;

class GoogleAnalyticsSettings
{
    public static function hostKey(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host));
    }

    public function current(string $host): array
    {
        $defaults = ['enabled' => false, 'measurement_id' => ''];
        if (! \Illuminate\Support\Facades\Schema::hasTable('google_analytics_settings')) {
            return $defaults;
        }
        $row = \Illuminate\Support\Facades\DB::table('google_analytics_settings')->where('host', self::hostKey($host))->first();

        return $row ? ['enabled' => (bool) $row->enabled, 'measurement_id' => $row->measurement_id ?? ''] : $defaults;
    }

    public function save(string $host, bool $enabled, ?string $measurementId): void
    {
        \Illuminate\Support\Facades\DB::table('google_analytics_settings')->updateOrInsert(
            ['host' => self::hostKey($host)],
            ['enabled' => $enabled, 'measurement_id' => $measurementId, 'updated_at' => now()]
        );
    }
}
