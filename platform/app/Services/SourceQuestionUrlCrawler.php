<?php

namespace App\Services;

use Illuminate\Support\Str;

class SourceQuestionUrlCrawler
{
    public function parse(string $html, string $pageUrl, string $baseUrl, string $adapter, ?string $questionPattern = null): array
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $questionUrls = [];
            $crawlUrls = [];

            foreach ($xpath->query('//a[@href]') ?: [] as $link) {
                $url = $this->absoluteUrl(trim((string) $link->getAttribute('href')), $pageUrl);
                if ($url === null || ! $this->sameHost($url, $baseUrl)) continue;

                if ($this->isQuestionUrl($url, $adapter, $questionPattern)) {
                    $questionUrls[$url] = true;
                } elseif ($this->withinScope($url, $baseUrl) && $this->isCrawlablePage($url)) {
                    $crawlUrls[$url] = true;
                }
            }

            return [
                'question_urls' => array_keys($questionUrls),
                'crawl_urls' => array_keys($crawlUrls),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function isQuestionUrl(string $url, string $adapter, ?string $pattern = null): bool
    {
        $pattern = trim((string) $pattern);
        if ($pattern !== '') return Str::is($pattern, $url);

        if ($adapter === 'examside' || str_ends_with(strtolower((string) parse_url($url, PHP_URL_HOST)), 'examside.com')) {
            return preg_match('/-[a-z0-9]{16}\.?[a-z]*$/i', (string) parse_url($url, PHP_URL_PATH)) === 1;
        }

        return false;
    }

    private function absoluteUrl(string $href, string $pageUrl): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(mailto:|tel:|javascript:|data:)~i', $href)) return null;
        if (str_starts_with($href, '//')) $href = (parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https').':'.$href;

        if (! preg_match('~^https?://~i', $href)) {
            $page = parse_url($pageUrl);
            if (! is_array($page) || empty($page['host'])) return null;
            $origin = ($page['scheme'] ?? 'https').'://'.$page['host'].(isset($page['port']) ? ':'.$page['port'] : '');
            if (str_starts_with($href, '?')) {
                $href = $origin.($page['path'] ?? '/').$href;
            } elseif (str_starts_with($href, '/')) {
                $href = $origin.$href;
            } else {
                $directory = rtrim(str_replace('\\', '/', dirname((string) ($page['path'] ?? '/'))), '/');
                $href = $origin.($directory === '' ? '' : $directory).'/'.$href;
            }
        }

        $parts = parse_url($href);
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) return null;
        $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']).$port.$path.$query;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', preg_replace('~/+~', '/', $path) ?: '/') as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') array_pop($segments); else $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    private function sameHost(string $url, string $baseUrl): bool
    {
        return strcasecmp((string) parse_url($url, PHP_URL_HOST), (string) parse_url($baseUrl, PHP_URL_HOST)) === 0;
    }

    private function isCrawlablePage(string $url): bool
    {
        $extension = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return $extension === '' || in_array($extension, ['htm', 'html', 'php', 'asp', 'aspx'], true);
    }

    private function withinScope(string $url, string $baseUrl): bool
    {
        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        $scope = str_ends_with($basePath, '/') ? rtrim($basePath, '/') : str_replace('\\', '/', dirname($basePath));
        if ($scope === '/' || $scope === '.' || $scope === '') return true;

        return str_starts_with((string) parse_url($url, PHP_URL_PATH), rtrim($scope, '/').'/');
    }
}
