<?php

namespace App\Services;

class SourceUrlNormalizer
{
    public function normalize(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) && ! (($scheme === 'https' && $parts['port'] === 443) || ($scheme === 'http' && $parts['port'] === 80))
            ? ':'.$parts['port'] : '';
        $path = preg_replace('~/+~', '/', (string) ($parts['path'] ?? '/')) ?: '/';
        if ($path !== '/') $path = rtrim($path, '/');

        $query = '';
        if (! empty($parts['query'])) {
            parse_str((string) $parts['query'], $parameters);
            foreach (array_keys($parameters) as $key) {
                if (preg_match('/^(utm_|fbclid$|gclid$)/i', (string) $key)) unset($parameters[$key]);
            }
            ksort($parameters);
            $queryString = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
            if ($queryString !== '') $query = '?'.$queryString;
        }

        return $scheme.'://'.$host.$port.$path.$query;
    }

    public function hash(string $url): string
    {
        return hash('sha256', $this->normalize($url));
    }
}
