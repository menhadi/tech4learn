<?php

namespace App\Services;

use App\Contracts\SourceQuestionAdapter;
use App\Models\SourceQuestionAdapterProfile;
use App\Services\SourceQuestionAdapters\ConfiguredHtmlAdapter;
use App\Services\SourceQuestionAdapters\ExamSideAdapter;

class SourceQuestionAdapterRegistry
{
    public function __construct(private ExamSideAdapter $examSide, private MathContentNormalizer $normalizer) {}

    public function for(string $url, ?string $key = 'auto', ?int $organizationId = null): SourceQuestionAdapter
    {
        $organizationId ??= (int) \App\Support\Tenant::id();
        $key = trim((string) $key) ?: 'auto';
        if ($key === $this->examSide->key()) {
            if (! $this->examSide->supports($url)) throw new \RuntimeException('The selected ExamSide adapter does not support this URL.');
            return $this->examSide;
        }
        if ($key !== 'auto') {
            $profile = SourceQuestionAdapterProfile::where('organization_id', $organizationId)->where('enabled', true)->where('key', $key)->first();
            if (! $profile) throw new \RuntimeException('The selected source adapter is unavailable.');
            $adapter = new ConfiguredHtmlAdapter($profile, $this->normalizer);
            if (! $adapter->supports($url)) throw new \RuntimeException('The selected adapter URL pattern does not match this URL.');
            return $adapter;
        }
        if ($this->examSide->supports($url)) return $this->examSide;
        foreach (SourceQuestionAdapterProfile::where('organization_id', $organizationId)->where('enabled', true)->orderBy('name')->get() as $profile) {
            $adapter = new ConfiguredHtmlAdapter($profile, $this->normalizer);
            if ($adapter->supports($url)) return $adapter;
        }
        throw new \RuntimeException('No source adapter is configured for this URL.');
    }

    public function options(?int $organizationId = null): array
    {
        $organizationId ??= (int) \App\Support\Tenant::id();
        $custom = SourceQuestionAdapterProfile::where('organization_id', $organizationId)->where('enabled', true)->orderBy('name')->get()
            ->map(fn ($profile) => ['key' => $profile->key, 'name' => $profile->name, 'pattern' => $profile->url_pattern, 'built_in' => false])->all();
        return array_merge([
            ['key' => 'auto', 'name' => 'Auto-detect from URL', 'pattern' => null, 'built_in' => true],
            ['key' => 'examside', 'name' => 'ExamSide', 'pattern' => '*://*.examside.com/*', 'built_in' => true],
        ], $custom);
    }
}
