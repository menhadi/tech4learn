<?php

namespace App\Services;

use App\Models\QuestionRepairDraft;
use App\Models\SourceExamQuestionDraft;
use Illuminate\Support\Str;

class StructuredContentReviewService
{
    public const FIELDS = ['question', 'option1', 'option2', 'option3', 'option4', 'option5', 'option6', 'explanation'];

    public function inspect(array $original, array $candidate, array $sourceEvidence = [], ?string $provider = null): array
    {
        $items = [];

        foreach (self::FIELDS as $field) {
            $before = (string) ($original[$field] ?? '');
            $after = (string) ($candidate[$field] ?? $before);
            $type = $this->classify($before.' '.$after);

            if (! $type) {
                continue;
            }

            $validation = $this->validate($type, $after);
            $items[] = [
                'id' => (string) Str::uuid(),
                'field' => $field,
                'type' => $type,
                'status' => $validation['valid'] ? 'ready' : 'needs_review',
                'original_html' => $before,
                'candidate_html' => $after,
                'provider' => $provider,
                'confidence' => $validation['confidence'],
                'validation' => $validation,
                'source' => $this->sourceForField($sourceEvidence, $field),
                'reviewed_at' => null,
                'decision' => null,
            ];
        }

        return $items;
    }

    public function attachToRepair(QuestionRepairDraft $draft, array $original, array $candidate, array $evidence): array
    {
        $sourceEvidence = (array) data_get($evidence, 'canonical_source', []);

        if ($sourceEvidence === []) {
            $finding = $draft->audit?->findings()
                ->where('question_id', $draft->question_id)
                ->latest('id')
                ->first();
            $sourceEvidence = (array) data_get($finding?->evidence, 'canonical_source', []);
        }

        $evidence['structured_content'] = $this->inspect(
            $original,
            $candidate,
            $sourceEvidence,
            $evidence['provider'] ?? null
        );

        return $evidence;
    }

    public function attachToSourceDraft(SourceExamQuestionDraft $draft, array $payload, array $sourceEvidence): array
    {
        $sourceEvidence['structured_content'] = $this->inspect(
            $payload,
            $payload,
            $sourceEvidence,
            data_get($sourceEvidence, 'provider')
        );

        return $sourceEvidence;
    }

    public function reviewRepairItem(QuestionRepairDraft $draft, string $itemId, string $decision, ?string $candidateHtml = null): void
    {
        $evidence = (array) $draft->evidence;
        $items = collect((array) ($evidence['structured_content'] ?? []))
            ->map(function ($item) use ($itemId, $decision, $candidateHtml, $draft) {
                if (($item['id'] ?? null) !== $itemId) {
                    return $item;
                }

                $field = (string) ($item['field'] ?? '');
                $this->assertReviewTarget($field, $decision);
                $html = $decision === 'keep_original'
                    ? (string) ($item['original_html'] ?? '')
                    : (string) ($candidateHtml ?? $item['candidate_html'] ?? '');
                $validation = $this->validate((string) ($item['type'] ?? ''), $html);

                if ($decision === 'accept' && ! $validation['valid']) {
                    throw new \RuntimeException('This reconstruction still fails validation: '.implode(' ', $validation['messages']));
                }

                $payload = (array) $draft->proposed_payload;
                $payload[$field] = $html;
                $draft->proposed_payload = $payload;

                return $this->reviewedItem($item, $html, $validation, $decision);
            })
            ->values()
            ->all();

        $evidence['structured_content'] = $items;
        $draft->evidence = $evidence;
        $this->syncRepairStatus($draft, $items);
        $draft->save();
    }

    public function reviewSourceItem(SourceExamQuestionDraft $draft, string $itemId, string $decision, ?string $candidateHtml = null): void
    {
        $evidence = (array) $draft->source_evidence;
        $items = collect((array) ($evidence['structured_content'] ?? []))
            ->map(function ($item) use ($itemId, $decision, $candidateHtml, $draft) {
                if (($item['id'] ?? null) !== $itemId) {
                    return $item;
                }

                $field = (string) ($item['field'] ?? '');
                $this->assertReviewTarget($field, $decision);
                $html = $decision === 'keep_original'
                    ? (string) ($item['original_html'] ?? '')
                    : (string) ($candidateHtml ?? $item['candidate_html'] ?? '');
                $validation = $this->validate((string) ($item['type'] ?? ''), $html);

                if ($decision === 'accept' && ! $validation['valid']) {
                    throw new \RuntimeException('This reconstruction still fails validation: '.implode(' ', $validation['messages']));
                }

                $payload = (array) $draft->payload;
                $payload[$field] = $html;
                $draft->payload = $payload;

                return $this->reviewedItem($item, $html, $validation, $decision);
            })
            ->values()
            ->all();

        $evidence['structured_content'] = $items;
        $draft->source_evidence = $evidence;
        $draft->status = collect($items)->every(fn ($item) => ($item['status'] ?? null) === 'reviewed')
            ? 'ready'
            : 'needs_review';
        $draft->save();
    }

    public function reconcileEvidence(array $evidence, array $payload): array
    {
        $items = collect((array) ($evidence['structured_content'] ?? []))
            ->map(function (array $item) use ($payload) {
                $field = (string) ($item['field'] ?? '');
                if (! in_array($field, self::FIELDS, true) || ! array_key_exists($field, $payload)) {
                    return $item;
                }

                $html = (string) $payload[$field];
                if ($html === (string) ($item['candidate_html'] ?? '')) {
                    return $item;
                }

                $validation = $this->validate((string) ($item['type'] ?? ''), $html);
                $item['candidate_html'] = $html;
                $item['validation'] = $validation;
                $item['confidence'] = $validation['confidence'];
                $item['status'] = $validation['valid'] ? 'ready' : 'needs_review';
                $item['decision'] = null;
                $item['reviewed_at'] = null;

                return $item;
            })
            ->values()
            ->all();

        $evidence['structured_content'] = $items;

        return $evidence;
    }

    public function sourceVisualPath(array $item): ?string
    {
        $path = data_get($item, 'source.private_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function assertReviewTarget(string $field, string $decision): void
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw new \InvalidArgumentException('Invalid structured-content target.');
        }

        if (! in_array($decision, ['accept', 'keep_original'], true)) {
            throw new \InvalidArgumentException('Invalid structured-content decision.');
        }
    }

    private function reviewedItem(array $item, string $html, array $validation, string $decision): array
    {
        $item['candidate_html'] = $html;
        $item['validation'] = $validation;
        $item['status'] = 'reviewed';
        $item['decision'] = $decision;
        $item['reviewed_at'] = now()->toIso8601String();

        return $item;
    }

    private function syncRepairStatus(QuestionRepairDraft $draft, array $items): void
    {
        $pending = collect($items)->contains(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
        $original = (array) $draft->original_payload;
        $candidate = (array) $draft->proposed_payload;
        $draft->changed_fields = collect($candidate)
            ->filter(fn ($value, $field) => json_encode($value) !== json_encode($original[$field] ?? null))
            ->keys()
            ->values()
            ->all();
        $draft->status = $pending || empty($draft->changed_fields) ? 'needs_review' : 'ready';
    }

    private function classify(string $html): ?string
    {
        if (preg_match('/<table\b/i', $html)) {
            return 'table';
        }

        if (preg_match('/\\\\begin\{(?:p|b|B|v|V)?matrix\}|<m(?:table|tr|td)\b/i', $html)) {
            return 'matrix';
        }

        if (preg_match('/\\\\begin\{(?:aligned|align\*?|cases|array|split|gathered)\}|\\\\(?:frac|dfrac|tfrac|partial|int|iint|iiint|sum|prod|lim|sqrt|ce)\b|<math\b/i', $html)) {
            return 'equation';
        }

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/(?:\x{2202}|\x{222B}|\x{2211}|\x{220F}|\x{221A}|\x{2264}|\x{2265}|\x{2260}|\x{2192}|\x{21CC}|\b(?:sin|cos|tan|log|lim)\b).{0,120}(?:=|\x{2264}|\x{2265}|\x{2192}|\x{21CC})/u', $plain)) {
            return 'equation';
        }

        return null;
    }

    private function validate(string $type, string $html): array
    {
        $messages = [];
        $confidence = 100;

        if ($type === 'table') {
            $rows = preg_match_all('/<tr\b/i', $html);
            $cells = preg_match_all('/<(?:td|th)\b/i', $html);

            if ($rows < 2 || $cells < 4) {
                $messages[] = 'The table has too few structured rows or cells.';
                $confidence -= 60;
            }

            if (! preg_match('/<(?:thead|th)\b/i', $html)) {
                $messages[] = 'No explicit table header was detected.';
                $confidence -= 15;
            }
        } else {
            if (substr_count($html, '{') !== substr_count($html, '}')) {
                $messages[] = 'MathJax braces are unbalanced.';
                $confidence -= 70;
            }

            preg_match_all('/\\\\begin\{([^}]+)\}/', $html, $begins);
            preg_match_all('/\\\\end\{([^}]+)\}/', $html, $ends);
            if (($begins[1] ?? []) !== ($ends[1] ?? [])) {
                $messages[] = 'MathJax environments are not balanced.';
                $confidence -= 70;
            }

            if (preg_match('/<math\b|mathjax-mathml|<svg\b/i', $html)) {
                $messages[] = 'Pre-rendered formula markup must be replaced by MathJax source.';
                $confidence -= 45;
            }

            if ($type === 'matrix' && ! preg_match('/\\\\begin\{(?:p|b|B|v|V)?matrix\}/', $html)) {
                $messages[] = 'Matrix structure is not represented by a MathJax matrix environment.';
                $confidence -= 50;
            }
        }

        $valid = $confidence >= 70 && $messages === [];

        return [
            'valid' => $valid,
            'confidence' => max(0, $confidence),
            'messages' => $messages ?: ['Structure passed deterministic validation.'],
        ];
    }

    private function sourceForField(array $sourceEvidence, string $field): ?array
    {
        $visual = collect((array) ($sourceEvidence['visuals'] ?? []))
            ->first(fn ($visual) => ($visual['target_field'] ?? null) === $field);

        return $visual
            ? collect($visual)->only(['private_path', 'page', 'bbox', 'target_field', 'visual_type'])->all()
            : null;
    }
}
