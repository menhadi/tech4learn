<?php

namespace App\Services\SourceQuestionAdapters;

use App\Contracts\SourceQuestionAdapter;
use App\Models\SourceQuestionAdapterProfile;
use App\Services\MathContentNormalizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\CssSelector\CssSelectorConverter;

class ConfiguredHtmlAdapter implements SourceQuestionAdapter
{
    public function __construct(private SourceQuestionAdapterProfile $profile, private MathContentNormalizer $normalizer) {}
    public function key(): string { return $this->profile->key; }

    public function supports(string $url): bool
    {
        $patterns = preg_split('/\r?\n/', (string) $this->profile->url_pattern) ?: [];
        return collect($patterns)->map('trim')->filter()->contains(fn ($pattern) => Str::is($pattern, $url));
    }

    public function extract(string $url, array $metadata = []): array
    {
        $response = Http::withHeaders(['User-Agent' => 'ExamElite Source Import/1.0'])->timeout(45)->retry(2, 500)->get($url);
        $response->throw();
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8">'.$response->body(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
            $xpath = new DOMXPath($doc);
            $selectors = (array) $this->profile->selectors;
            $container = $this->first($xpath, $selectors['container'] ?? null, $doc) ?: $doc;
            $questionNode = $this->first($xpath, $selectors['question'] ?? null, $container);
            if (! $questionNode) throw new \RuntimeException('Configured question selector did not match this page.');
            $question = $this->normalize($this->content($questionNode, $url));
            $options = [];
            foreach ($this->nodes($xpath, $selectors['options'] ?? null, $container) as $node) $options[] = $this->normalize($this->content($node, $url));
            $answer = $this->value($xpath, $selectors['answer'] ?? null, $container, $url);
            $explanation = $this->value($xpath, $selectors['explanation'] ?? null, $container, $url, true);
            $marksText = $this->plainValue($xpath, $selectors['marks'] ?? null, $container);
            $negativeText = $this->plainValue($xpath, $selectors['negative_marks'] ?? null, $container);
            $typeText = $this->plainValue($xpath, $selectors['question_type'] ?? null, $container);
            $academic = [];
            foreach (['group', 'category', 'subcategory', 'package', 'exam', 'subject', 'section', 'topic', 'subtopic', 'difficulty_level', 'language'] as $field) {
                $academic[$field] = $this->plainValue($xpath, $selectors[$field] ?? null, $container);
            }
            $imageUrls = [];
            foreach (array_merge([$question], $options, [$explanation]) as $html) if (preg_match_all('~<img[^>]*src="([^"]+)"~i', (string) $html, $matches)) foreach ($matches[1] as $src) $imageUrls[] = $this->absoluteUrl($src, $url);
            foreach ($this->nodes($xpath, $selectors['images'] ?? null, $container) as $image) if ($image instanceof DOMElement && $image->hasAttribute('src')) $imageUrls[] = $this->absoluteUrl($image->getAttribute('src'), $url);
            return [
                'question' => $question, 'options' => array_slice(array_values(array_filter($options, fn ($value) => trim(strip_tags($value)) !== '')), 0, 6),
                'question_type' => $this->questionType($typeText ?: (string) ($metadata['question_type'] ?? $metadata['type'] ?? 'single')),
                'marks' => $this->number($marksText), 'negative_marks' => $this->number($negativeText), 'answer' => $answer, 'explanation' => $explanation,
                'needs_review' => false, 'review_reason' => null, 'source_url' => $url, 'source_adapter' => $this->key(),
                'adapter_version' => $this->profile->version, 'image_urls' => array_values(array_unique(array_filter($imageUrls))),
                ...array_filter($academic, fn ($value) => trim((string) $value) !== ''),
            ];
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function nodes(DOMXPath $xpath, ?string $selector, DOMNode $context): array
    {
        $selector = trim((string) $selector); if ($selector === '') return [];
        try { $query = str_starts_with($selector, '/') || str_starts_with($selector, '.') ? $selector : (new CssSelectorConverter())->toXPath($selector); $nodes = $xpath->query($query, $context); }
        catch (\Throwable $e) { throw new \RuntimeException('Invalid selector "'.$selector.'": '.$e->getMessage(), 0, $e); }
        return $nodes ? iterator_to_array($nodes) : [];
    }
    private function first(DOMXPath $xpath, ?string $selector, DOMNode $context): ?DOMNode { return $this->nodes($xpath, $selector, $context)[0] ?? null; }
    private function value(DOMXPath $xpath, ?string $selector, DOMNode $context, string $url, bool $normalize = false): ?string { $node = $this->first($xpath, $selector, $context); if (! $node) return null; $value = $this->content($node, $url); return $normalize ? $this->normalize($value) : trim(strip_tags($value)); }
    private function plainValue(DOMXPath $xpath, ?string $selector, DOMNode $context): ?string { $node = $this->first($xpath, $selector, $context); return $node ? trim((string) $node->textContent) : null; }
    private function content(DOMNode $node, string $url): string
    {
        $clone = $node->cloneNode(true);
        if ($clone instanceof DOMElement) foreach ($clone->getElementsByTagName('img') as $image) if ($image->hasAttribute('src')) $image->setAttribute('src', $this->absoluteUrl($image->getAttribute('src'), $url));
        $doc = new DOMDocument('1.0', 'UTF-8'); foreach ($clone->childNodes as $child) $doc->appendChild($doc->importNode($child, true)); return trim((string) $doc->saveHTML());
    }
    private function normalize(string $value): string { $result = $this->normalizer->normalize($value); if (($result['status'] ?? null) === 'needs_review') throw new \RuntimeException(implode(' ', (array) ($result['issues'] ?? []))); return (string) ($result['content'] ?? $value); }
    private function absoluteUrl(string $src, string $pageUrl): string
    {
        $src = trim($src); if (preg_match('~^https?://~i', $src)) return $src; $base = parse_url($pageUrl);
        if (str_starts_with($src, '//')) return ($base['scheme'] ?? 'https').':'.$src;
        if (str_starts_with($src, '/')) return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').$src;
        $directory = rtrim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/'); return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').$directory.'/'.$src;
    }
    private function number(?string $value): ?float { if ($value === null || ! preg_match('/-?\d+(?:\.\d+)?/', $value, $match)) return null; return abs((float) $match[0]); }
    private function questionType(string $label): string
    {
        $code = strtoupper(trim($label));
        if (in_array($code, ['M', 'NAT', 'B', 'T', 'S'], true)) return $code;
        if ($code === 'F') return 'B';
        $label = strtolower($label);
        if (str_contains($label, 'numerical') || str_contains($label, 'numeric') || str_contains($label, 'integer') || preg_match('/\bnat\b/', $label)) return 'NAT';
        if (str_contains($label, 'fill')) return 'B';
        if (str_contains($label, 'true') && str_contains($label, 'false')) return 'T';
        if (str_contains($label, 'subjective') || str_contains($label, 'descriptive')) return 'S';
        return 'M';
    }
}
