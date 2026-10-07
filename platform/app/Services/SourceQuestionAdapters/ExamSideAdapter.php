<?php

namespace App\Services\SourceQuestionAdapters;

use App\Contracts\SourceQuestionAdapter;
use App\Services\MathContentNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use DOMDocument;
use DOMElement;
use DOMXPath;

class ExamSideAdapter implements SourceQuestionAdapter
{
    public function __construct(private MathContentNormalizer $normalizer) {}
    public function key(): string { return 'examside'; }
    public function supports(string $url): bool { return (bool) preg_match('~https?://([^/]*\\.)?examside\\.com/~i', $url); }

    public function extract(string $url, array $metadata = []): array
    {
        $response = Http::withHeaders(['User-Agent' => 'ExamElite Source Import/1.0'])->timeout(45)->retry(2, 500)->get($url);
        $response->throw();
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8">'.$response->body(), LIBXML_NOERROR|LIBXML_NOWARNING|LIBXML_NONET);
            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' question-component ')]");
            if (! $nodes || $nodes->length === 0) throw new \RuntimeException('No question component found on source page.');
            $component = $this->selectComponent($nodes, $url, $xpath);
            if (! $component) throw new \RuntimeException('The URL did not identify a unique question on the source page.');

            $questionNode = $this->first($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' question ')]", $component);
            $options = [];
            foreach ($xpath->query(".//*[@role='button']//*[contains(concat(' ', normalize-space(@class), ' '), ' question ')]", $component) ?: [] as $node) {
                $options[] = $this->cleanNode($node);
            }
            if ($options === []) {
                foreach ($xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' options ')]//*[self::button or @role='button']", $component) ?: [] as $node) $options[] = $this->cleanNode($node);
            }
            $header = trim((string) ($this->first($xpath, ".//*[contains(@class,'font-semibold')]", $component)?->textContent ?? ''));
            [$marks, $negative] = $this->marks($component->textContent);
            $question = $questionNode ? $this->cleanNode($questionNode) : '';
            $imageUrls = [];
            foreach ($xpath->query(".//img[@src]", $component) ?: [] as $image) {
                $src = trim((string) $image->getAttribute('src'));
                if ($src !== '') $imageUrls[] = $this->absoluteUrl($src, $url);
            }
            if (preg_match_all("~<img\b[^>]*\bsrc=\"([^\"]+)\"~i", $question, $matches)) {
                foreach ($matches[1] as $src) $imageUrls[] = $this->absoluteUrl(trim((string) $src), $url);
            }
            $imageUrls = array_values(array_unique(array_filter($imageUrls)));
            $embedded = $this->embeddedEnglishData($response->body(), $url);
            if (($embedded['question'] ?? '') !== '') $question = $embedded['question'];
            if (!empty($embedded['options'])) $options = $embedded['options'];
            $marks = $embedded['marks'] ?? $marks;
            $negative = $embedded['negative_marks'] ?? $negative;
            $typeLabel = (string) ($embedded['question_type'] ?? $this->questionTypeLabel($component, $metadata));
            $normalized = $this->normalizer->normalize($question);
            if ($normalized['status'] === 'needs_review') throw new \RuntimeException(implode(' ', $normalized['issues']));
            if (preg_match_all('~<img[^>]*src="([^"]+)"~i', $normalized['content'], $embeddedImages)) {
                foreach ($embeddedImages[1] as $src) $imageUrls[] = $this->absoluteUrl(trim((string) $src), $url);
            }
            $normalizedOptions = [];
            foreach (array_slice($options, 0, 6) as $option) {
                $n = $this->normalizer->normalize($option);
                if ($n['status'] === 'needs_review') throw new \RuntimeException(implode(' ', $n['issues']));
                $normalizedOptions[] = $n['content'];
                if (preg_match_all('~<img[^>]*src="([^"]+)"~i', $n['content'], $optionImages)) {
                    foreach ($optionImages[1] as $src) $imageUrls[] = $this->absoluteUrl(trim((string) $src), $url);
                }
            }
            $normalizedExplanation = null;
            if (trim((string) ($embedded['explanation'] ?? '')) !== '') {
                $explanation = $this->normalizer->normalize((string) $embedded['explanation']);
                if ($explanation['status'] === 'needs_review') throw new \RuntimeException(implode(' ', $explanation['issues']));
                $normalizedExplanation = $explanation['content'];
                if (preg_match_all('~<img[^>]*src="([^"]+)"~i', $normalizedExplanation, $explanationImages)) {
                    foreach ($explanationImages[1] as $src) $imageUrls[] = $this->absoluteUrl(trim((string) $src), $url);
                }
            }
            if (preg_match_all('~<img[^>]*src="([^"]+)"~i', $normalized['content'], $normalizedImages)) {
                foreach ($normalizedImages[1] as $src) $imageUrls[] = $this->absoluteUrl(trim((string) $src), $url);
            }
            $imageUrls = array_values(array_unique(array_filter($imageUrls)));
            return [
                'question' => $normalized['content'], 'options' => $normalizedOptions,
                'question_type' => $this->questionType($typeLabel), 'marks' => $marks, 'negative_marks' => $negative,
                'paper_name' => $header, 'answer' => $embedded['answer'] ?? null,
                'correct_option_indices' => $embedded['correct_option_indices'] ?? [],
                'explanation' => $normalizedExplanation,
                'needs_review' => false, 'review_reason' => null,
                'source_url' => $url, 'source_adapter' => $this->key(), 'adapter_version' => 2, 'image_urls' => $imageUrls,
                'exam' => $embedded['exam'] ?? null,
                'subject' => $embedded['subject'] ?? null,
                'topic' => $embedded['topic'] ?? null,
                'subtopic' => $embedded['subtopic'] ?? null,
                'difficulty_level' => $embedded['difficulty_level'] ?? null,
                'language' => $embedded['language'] ?? null,
            ];
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function embeddedEnglishData(string $html, string $url): array
    {
        $questionStart = false;
        if (preg_match('/-([a-z0-9]{16})\.?[a-z]*$/i', (string) parse_url($url, PHP_URL_PATH), $idMatch)) {
            $idPattern = '/\bquestion_id:"'.preg_quote($idMatch[1], '/').'"/i';
            if (preg_match($idPattern, $html, $questionIdMatch, PREG_OFFSET_CAPTURE)) {
                $questionStart = strpos($html, 'question:{en:{', $questionIdMatch[0][1]);
            }
        }
        if ($questionStart === false) $questionStart = strpos($html, 'question:{en:{');
        if ($questionStart === false) return [];
        $objectStart = strrpos(substr($html, 0, $questionStart), '{country:');
        $segment = substr($html, $objectStart === false ? $questionStart : $objectStart, 250000);
        $segment = preg_split('/},hi:\{/', $segment, 2)[0] ?? $segment;
        $decode = static function (string $value): string {
            $decoded = json_decode('"'.$value.'"', true);
            return is_string($decoded) ? $decoded : stripslashes($value);
        };
        $result = [
            'question' => '', 'options' => [], 'correct_option_indices' => [],
            'answer' => null, 'explanation' => null, 'marks' => null, 'negative_marks' => null,
            'exam' => null, 'subject' => null, 'topic' => null, 'subtopic' => null,
            'difficulty_level' => null, 'question_type' => null, 'language' => null,
        ];
        $prefix = preg_split('/question:\{en:\{/', $segment, 2)[0] ?? '';
        foreach ([
            'paperTitle' => 'exam', 'subject' => 'subject', 'chapterGroup' => 'topic',
            'chapter' => 'subtopic', 'difficulty' => 'difficulty_level', 'type' => 'question_type',
        ] as $sourceKey => $targetKey) {
            if (preg_match('/\b'.preg_quote($sourceKey, '/').':"((?:\\\\.|[^"\\\\])*)"/s', $prefix, $match)) {
                $result[$targetKey] = $this->humanizeSourceValue($decode($match[1]));
            }
        }
        if (preg_match('/\bmarks:(-?\d+(?:\.\d+)?)/', $prefix, $match)) $result['marks'] = (float) $match[1];
        if (preg_match('/\bnegMarks:(-?\d+(?:\.\d+)?)/', $prefix, $match)) $result['negative_marks'] = abs((float) $match[1]);
        if (preg_match('/\blanguages:\["((?:\\\\.|[^"\\\\])*)"/', $prefix, $match)) $result['language'] = $this->languageName($decode($match[1]));
        if (preg_match('/content:"((?:\\\\.|[^"\\\\])*)"/s', $segment, $match)) $result['question'] = $decode($match[1]);
        if (preg_match('/options:\[(.*?)\],correct_options:/s', $segment, $match)) {
            if (preg_match_all('/identifier:"([A-F])",content:"((?:\\\\.|[^"\\\\])*)"/s', $match[1], $options, PREG_SET_ORDER)) {
                foreach ($options as $option) $result['options'][] = $decode($option[2]);
            }
        }
        if (preg_match('/correct_options:\[((?:"[A-F]"(?:,"[A-F]")*)?)\]/s', $segment, $match) && $match[1] !== '') {
            preg_match_all('/"([A-F])"/', $match[1], $letters);
            $result['correct_option_indices'] = array_values(array_map(static fn ($letter) => ord($letter) - 64, $letters[1] ?? []));
            $result['answer'] = implode(',', $letters[1] ?? []);
        }
        if (preg_match('/answer:(null|"((?:\\\\.|[^"\\\\])*)")/s', $segment, $match) && $match[1] !== 'null') $result['answer'] = $decode($match[2]);
        if (preg_match('/explanation:(null|"((?:\\\\.|[^"\\\\])*)")/s', $segment, $match) && $match[1] !== 'null') $result['explanation'] = $decode($match[2]);
        return $result;
    }

    private function humanizeSourceValue(string $value): string
    {
        return Str::of($value)->replace(['-', '_'], ' ')->squish()->title()->toString();
    }

    private function languageName(string $value): string
    {
        return match (Str::lower(trim($value))) {
            'en', 'eng' => 'English',
            'hi', 'hin' => 'Hindi',
            default => $this->humanizeSourceValue($value),
        };
    }

    private function absoluteUrl(string $src, string $pageUrl): string
    {
        if (preg_match('~^https?://~i', $src)) return $src;
        $base = parse_url($pageUrl);
        if (str_starts_with($src, '//')) return (($base['scheme'] ?? 'https').':'.$src);
        if (str_starts_with($src, '/')) return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').$src;
        $directory = rtrim(str_replace(chr(92), '/', dirname($base['path'] ?? '/')), '/');
        return ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '').$directory.'/'.$src;
    }
    private function selectComponent($nodes, string $url, DOMXPath $xpath): ?DOMElement
    {
        // ExamSide renders the question belonging to the requested URL first.
        // Later components are recommendations and belong to separate URLs.
        $first = $nodes->item(0);

        return $first instanceof DOMElement ? $first : null;
    }
    private function first(DOMXPath $xpath, string $query, DOMElement $context): ?DOMElement { $n = $xpath->query($query, $context); return $n && $n->length ? $n->item(0) : null; }
    private function cleanNode(DOMElement $node): string { $clone = $node->cloneNode(true); foreach ($clone->childNodes as $child) if ($child instanceof DOMElement && preg_match('/^(button|svg)$/i', $child->tagName)) $clone->removeChild($child); return trim($this->html($clone)); }
    private function html($node): string { $doc = new DOMDocument('1.0','UTF-8'); $doc->appendChild($doc->importNode($node,true)); return $doc->saveHTML(); }
    private function marks(string $text): array { preg_match('/\\+\\s*(\\d+(?:\\.\\d+)?)/', $text, $p); preg_match('/-\\s*(\\d+(?:\\.\\d+)?)/', $text, $n); return [isset($p[1]) ? (float) $p[1] : null, isset($n[1]) ? (float) $n[1] : null]; }
    private function questionTypeLabel(DOMElement $component, array $metadata): string
    {
        // The source page is authoritative. CSV metadata may contain a generic
        // fallback (for example "single") and must not override NAT/subjective.
        $prefix = Str::lower(Str::substr(trim((string) $component->textContent), 0, 700));
        foreach ([
            'subjective' => '/\b(subjective|descriptive|essay)\b/i',
            'numeric' => '/\b(nat|numerical(?: answer)?|numeric(?: answer)?|integer answer)\b/i',
            'true_false' => '/\b(true\s*\/?\s*false|true-false)\b/i',
            'fill_blank' => '/\b(fill\s*in\s*the\s*blanks?|fill\s*blank)\b/i',
            'multiple' => '/\b(mcq|multiple\s+choice|multiple\s+correct)\b/i',
        ] as $label => $pattern) {
            if (preg_match($pattern, $prefix)) return $label;
        }
        foreach (['question_type', 'type'] as $key) {
            if (!empty($metadata[$key])) return (string) $metadata[$key];
        }
        return $prefix;
    }
    private function questionType(string $label): string
    {
        $code = strtoupper(trim($label));
        if (in_array($code, ['M', 'NAT', 'B', 'T', 'S'], true)) return $code;
        if ($code === 'F') return 'B';
        $label = strtolower($label);
        if (str_contains($label, 'numerical') || str_contains($label, 'numeric') || str_contains($label, 'integer') || preg_match('/\bnat\b/', $label)) return 'NAT';
        if (str_contains($label, 'fill in') || str_contains($label, 'fill blank')) return 'B';
        if (str_contains($label, 'true') && str_contains($label, 'false')) return 'T';
        if (str_contains($label, 'subjective') || str_contains($label, 'descriptive') || str_contains($label, 'essay')) return 'S';
        return 'M';
    }
}