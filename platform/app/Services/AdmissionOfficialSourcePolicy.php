<?php

namespace App\Services;

use App\Models\AdmissionExamDefinition;
use InvalidArgumentException;

class AdmissionOfficialSourcePolicy
{
    public function assertAllowed(
        AdmissionExamDefinition $exam,
        string $url,
        ?string $listingUrl = null
    ): string {
        $host = $this->httpsHost($url);

        if ($this->matchesAny($host, $exam->official_domains ?? [])) {
            return $host;
        }

        if ($this->matchesAny($host, $exam->document_delivery_domains ?? [])) {
            if (! $listingUrl) {
                throw new InvalidArgumentException(
                    'A government delivery URL must be linked from an approved official listing page.'
                );
            }

            $this->assertAuthorityUrl($exam, $listingUrl);

            return $host;
        }

        throw new InvalidArgumentException("The domain [{$host}] is not approved for {$exam->name}.");
    }

    public function assertAuthorityUrl(AdmissionExamDefinition $exam, string $url): string
    {
        $host = $this->httpsHost($url);
        if (! $this->matchesAny($host, $exam->official_domains ?? [])) {
            throw new InvalidArgumentException(
                "The listing domain [{$host}] is not an approved authority for {$exam->name}."
            );
        }

        return $host;
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = $this->normalizeHost((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    private function httpsHost(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host'])) {
            throw new InvalidArgumentException('The official resource URL is invalid.');
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Official resources must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Official resource URLs cannot contain credentials.');
        }

        $host = $this->normalizeHost((string) $parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Official resources must use an approved authority domain.');
        }

        return $host;
    }

    private function matchesAny(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            $domain = $this->normalizeHost((string) $domain);
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = strtolower($ascii);
            }
        }

        return $host;
    }
}
