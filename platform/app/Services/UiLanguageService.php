<?php

namespace App\Services;

use App\Models\Language;
use App\Support\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class UiLanguageService
{
    private const NATIVE_NAMES = [
        'en' => 'English', 'hi' => 'हिन्दी',
        'ar' => 'العربية', 'ur' => 'اردو', 'fr' => 'Français',
        'it' => 'Italiano', 'ru' => 'Русский', 'es' => 'Español',
    ];

    private const RTL = ['ar', 'fa', 'he', 'ur'];

    public function available(?int $organizationId = null): Collection
    {
        $installed = collect(File::directories(lang_path()))
            ->map(fn (string $path) => strtolower(basename($path)))
            ->filter(fn (string $code) => preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/i', $code))
            ->reject(fn (string $code) => $code === 'vi')
            ->values();
        $organizationId ??= class_exists(Tenant::class) ? Tenant::id() : null;
        $configured = Language::enabledForOrganization($organizationId)
            ->whereIn('code', $installed)->get()
            ->keyBy(fn (Language $language) => strtolower((string) $language->code));
        if ($configured->isNotEmpty()) {
            $installed = $installed
                ->filter(fn (string $code) => $code === 'en' || $configured->has($code))
                ->values();
        }

        return $installed->map(function (string $code) use ($configured) {
            $language = $configured->get($code);
            return (object) [
                'code' => $code,
                'name' => $language?->name ?: (self::NATIVE_NAMES[$code] ?? strtoupper($code)),
                'native_name' => self::NATIVE_NAMES[$code] ?? $language?->name ?? strtoupper($code),
                'direction' => $this->direction($code),
            ];
        })->sortBy(fn ($language) => $language->code === 'en' ? '0' : '1'.$language->name)->values();
    }

    public function supports(string $locale, ?int $organizationId = null): bool
    {
        return $this->available($organizationId)->contains('code', strtolower($locale));
    }

    public function direction(string $locale): string
    {
        return in_array(strtolower(explode('-', $locale)[0]), self::RTL, true) ? 'rtl' : 'ltr';
    }
}
