<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\ExamQualitySource;
use App\Models\Question;
use App\Support\AiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ExamQualityImageReviewer
{
    /**
     * Inspect one paper once and return all source-image crop instructions.
     * The provider locates visuals; QuestionRepairService performs every crop locally.
     */
    public function reviewBatch(
        Collection $questions,
        int $examId,
        int $organizationId,
        ?string $selectedProvider = null
    ): array {
        $questions = $questions->filter()->values();
        if ($questions->isEmpty()) return [];

        $settings = Configuration::where('organization_id', $organizationId)->first();
        if (! $settings) throw new \RuntimeException('AI Settings are not configured for this organization.');
        $sources = ExamQualitySource::where('organization_id', $organizationId)
            ->where('exam_id', $examId)->where('is_active', true)
            ->whereIn('role', ['questions', 'combined'])
            ->orderByRaw("FIELD(role, 'questions', 'combined')")->get()
            ->filter(fn (ExamQualitySource $source) => $source->kind === 'file' || $this->looksLikePdf($source->source_url))
            ->values();
        if ($sources->isEmpty()) throw new \RuntimeException('No question or combined source is attached for image audit.');

        $providers = collect(AiProvider::available($settings, true, 'image_audit'))
            ->filter(fn (array $provider) => in_array($provider['provider'], ['chatgpt', 'gemini', 'claude', 'deepseek'], true));
        if ($selectedProvider && $selectedProvider !== 'auto') {
            $providers = $providers->where('provider', $selectedProvider);
        }
        if ($providers->isEmpty()) {
            throw new \RuntimeException('Image audit requires a configured DeepSeek, Gemini, OpenAI or Claude vision provider.');
        }

        $errors = [];
        foreach ($providers as $provider) {
            try {
                $decoded = match ($provider['provider']) {
                    'chatgpt' => $this->withOpenAi($questions, $sources, $provider),
                    'gemini' => $this->withGemini($questions, $sources, $provider),
                    'claude' => $this->withClaude($questions, $sources, $provider),
                    'deepseek' => $this->withDeepSeek($questions, $sources, $provider),
                };

                return $this->sanitize($decoded, $questions, $provider);
            } catch (\Throwable $exception) {
                $errors[] = ucfirst($provider['provider']).': '.$exception->getMessage();
                // A malformed successful response may already be billable.
                if (! $exception instanceof \Illuminate\Http\Client\RequestException) break;
            }
        }

        throw new \RuntimeException('Image audit failed. '.implode(' | ', $errors));
    }

    private function prompt(Collection $questions): string
    {
        $items = $questions->values()->map(function (Question $question, int $index) {
            $fields = collect(['question','option1','option2','option3','option4','option5','option6','explanation'])
                ->mapWithKeys(fn (string $field) => [$field => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $question->{$field})) ?? ''), 0, 1500)])->all();

            return [
                'question_id' => (int) $question->id,
                'paper_ordinal' => $index + 1,
                'question_code' => $question->question_code,
                'source_reference' => $question->source_reference,
                'fields' => $fields,
            ];
        });

        return <<<'PROMPT'
You are the image extraction bot. The attached QUESTION/COMBINED PDF is the sole source of authority. Existing stored images, placeholders, URLs, and current image placement are never evidence and must not influence what you extract. Stored question text is supplied only to map each PDF question and choose the destination field. Do not review wording, answers, correctness, explanations, MathJax, or academic quality.

Find and return every meaningful diagram, graph, figure, photograph, map, or image-rendered table belonging to every listed question. Return a separate entry for every visual, including multiple visuals in one question or field. Ignore logos, headers, footers, watermarks, page backgrounds, ordinary text, and option labels. A scanned PDF page is one large bitmap: locate a tight rectangle around each actual visual inside that scan.

For every PDF visual, first locate the printed question number, then trace the complete visual from its highest to lowest and leftmost to rightmost visible mark. source_page and bbox_normalized are mandatory and must tightly enclose the whole visual—not a partial edge—and exclude the question wording, answer choices, solutions, and adjacent questions. Leave only a 1-2% margin around the visual. Coordinates are [left, top, right, bottom] fractions of the full PDF page. target_field must identify where the PDF visual belongs. Always use action "sync". Return an empty images array only when the authoritative PDF contains no meaningful visuals for any listed question.

Return strict JSON only:
{"images":[{"question_id":123,"action":"sync","source_page":1,"bbox_normalized":[0.1,0.2,0.9,0.7],"target_field":"question|option1|option2|option3|option4|option5|option6|explanation","description":"visual found in authoritative PDF","confidence":0-100}]}
PROMPT
            .' QUESTIONS: '.json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function withOpenAi(Collection $questions, Collection $sources, array $provider): array
    {
        $content = [];
        foreach ($sources as $source) {
            $content[] = ['type' => 'input_text', 'text' => strtoupper((string) $source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                $content[] = ['type' => 'input_file', 'file_id' => $this->openAiFileId($source, $provider['key'])];
            } elseif ($this->looksLikePdf($source->source_url)) {
                $content[] = ['type' => 'input_file', 'file_url' => $source->source_url];
            }
        }

        $content[] = ['type' => 'input_text', 'text' => $this->prompt($questions)];

        $response = Http::withToken($provider['key'])->timeout(360)->retry(2, 1000)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $provider['model'],
                'input' => [['role' => 'user', 'content' => $content]],
                'max_output_tokens' => 6000,
            ])->throw()->json();
        $raw = '';
        foreach ((array) ($response['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text') $raw .= (string) ($part['text'] ?? '');
            }
        }
        return $this->decode($raw);
    }

    private function withGemini(Collection $questions, Collection $sources, array $provider): array
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = ['text' => strtoupper((string) $source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                $parts[] = ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $this->geminiFileUri($source, $provider['key'])]];
            } elseif ($this->looksLikePdf($source->source_url)) {
                $parts[] = $this->geminiPdfUrlPart($source->source_url, $provider['key']);
            }
        }

        $parts[] = ['text' => $this->prompt($questions)];
        $response = Http::timeout(360)->retry(2, 1000)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($provider['model']).':generateContent?key='.urlencode($provider['key']),
            [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 6000, 'responseMimeType' => 'application/json'],
            ]
        )->throw()->json();

        return $this->decode((string) data_get($response, 'candidates.0.content.parts.0.text', ''));
    }

    private function withClaude(Collection $questions, Collection $sources, array $provider): array
    {
        $content = [];
        foreach ($sources as $source) {
            $content[] = ['type' => 'text', 'text' => strtoupper((string) $source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
                try {
                    if (filesize($path) > 30 * 1024 * 1024) throw new \RuntimeException('Claude source PDFs are limited to 30 MB.');
                    $content[] = ['type' => 'document', 'source' => [
                        'type' => 'base64', 'media_type' => 'application/pdf',
                        'data' => base64_encode((string) file_get_contents($path)),
                    ]];
                } finally {
                    if ($cleanup) $cleanup();
                }
            } elseif ($this->looksLikePdf($source->source_url)) {
                $content[] = ['type' => 'document', 'source' => ['type' => 'url', 'url' => $source->source_url]];
            }
        }

        $content[] = ['type' => 'text', 'text' => $this->prompt($questions)];
        $response = Http::withHeaders([
            'x-api-key' => $provider['key'], 'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(360)->retry(2, 1000)->post('https://api.anthropic.com/v1/messages', [
            'model' => $provider['model'], 'max_tokens' => 6000,
            'system' => 'Return strict JSON only.',
            'messages' => [['role' => 'user', 'content' => $content]],
        ])->throw()->json();

        return $this->decode(collect((array) ($response['content'] ?? []))->where('type', 'text')->pluck('text')->implode("\n"));
    }

    private function withDeepSeek(Collection $questions, Collection $sources, array $provider): array
    {
        if (! class_exists(\Imagick::class)) {
            throw new \RuntimeException('DeepSeek PDF image audit requires the Imagick PHP extension to render PDF pages.');
        }

        $content = [['type' => 'input_text', 'text' => $this->prompt($questions)]];
        foreach ($sources as $source) {
            foreach ($this->deepSeekPdfPages($source) as $page) {
                $content[] = ['type' => 'input_text', 'text' => $page['label']];
                $content[] = ['type' => 'input_image', 'image_url' => $page['image_url'], 'detail' => 'original'];
            }
        }

        $response = Http::withToken($provider['key'])->timeout(360)->retry(2, 1000)
            ->post('https://api.deepseek.com/responses', [
                'model' => $provider['model'],
                'input' => [['role' => 'user', 'content' => $content]],
                'max_output_tokens' => 6000,
            ])->throw()->json();

        $raw = '';
        foreach ((array) ($response['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'output_text') $raw .= (string) ($part['text'] ?? '');
            }
        }

        return $this->decode($raw);
    }

    private function deepSeekPdfPages(ExamQualitySource $source): array
    {
        [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        $pages = [];
        try {
            $pdf = new \Imagick();
            $pdf->setResolution(120, 120);
            $pdf->readImage($path);
            foreach ($pdf as $index => $page) {
                $page->setImageBackgroundColor('white');
                $page = $page->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $page->setImageFormat('jpeg');
                $page->setImageCompressionQuality(82);
                $pages[] = [
                    'label' => strtoupper((string) $source->role).' SOURCE '.($source->label ?: 'PDF').' — page '.($index + 1),
                    'image_url' => 'data:image/jpeg;base64,'.base64_encode($page->getImageBlob()),
                ];
                $page->clear();
                $page->destroy();
            }
            $pdf->clear();
            $pdf->destroy();
        } finally {
            if ($cleanup) $cleanup();
        }

        if ($pages === []) throw new \RuntimeException('DeepSeek could not render any pages from the source PDF.');
        return $pages;
    }

    private function sanitize(array $decoded, Collection $questions, array $provider): array
    {
        if (! array_key_exists('images', $decoded) || ! is_array($decoded['images'])) {
            throw new \RuntimeException('The image provider response did not contain a valid images array.');
        }
        $questionIds = $questions->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $allowedTargets = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        $outcomes = [];
        $diagnostics = [];
        foreach ($decoded['images'] as $index => $item) {
            if (! is_array($item)) {
                $diagnostics[] = ['item_number' => $index + 1, 'question_id' => null, 'reason' => 'The image entry is not an object.'];
                continue;
            }
            $questionId = (int) ($item['question_id'] ?? 0);
            $bbox = array_values((array) ($item['bbox_normalized'] ?? []));
            $target = (string) ($item['target_field'] ?? 'question');
            $action = (string) ($item['action'] ?? '');
            $reason = match (true) {
                ! $questionIds->has($questionId) => 'The provider returned a question_id outside this audit batch.',
                $action !== 'sync' => 'The action must be sync.',
                (int) ($item['source_page'] ?? 0) < 1 => 'The source page is missing or invalid.',
                count($bbox) !== 4 => 'The crop must contain four normalized coordinates.',
                ! collect($bbox)->every(fn ($value) => is_numeric($value) && (float) $value >= 0 && (float) $value <= 1) => 'Crop coordinates must be numbers between 0 and 1.',
                (float) $bbox[0] >= (float) $bbox[2] || (float) $bbox[1] >= (float) $bbox[3] => 'The crop rectangle is inverted or empty.',
                default => null,
            };
            if ($reason !== null) {
                $diagnostics[] = ['item_number' => $index + 1, 'question_id' => $questionId ?: null, 'reason' => $reason];
                continue;
            }
            if (! in_array($target, $allowedTargets, true)) $target = 'question';
            $existingInstructions = (array) data_get($outcomes, $questionId.'.proposal.image_instructions', []);
            $instruction = [
                'action' => 'sync',
                'source_page' => (int) $item['source_page'],
                'bbox_normalized' => array_map('floatval', $bbox),
                'target_field' => $target,
                'image_index' => collect($existingInstructions)->where('target_field', $target)->count() + 1,
                'description' => trim((string) ($item['description'] ?? 'Visual found in the authoritative PDF.')),
            ];
            $instructions = [...$existingInstructions, $instruction];
            $confidence = max(0, min(100, (float) ($item['confidence'] ?? 75)));
            $firstInstruction = $instructions[0];
            $details = count($instructions).' authoritative PDF visual(s) will rebuild the stored image placement.';
            $outcomes[$questionId] = [
                'finding' => [
                    'type' => 'source_pdf_visual_sync', 'severity' => 'warning',
                    'title' => 'Authoritative PDF visuals will be synchronized',
                    'details' => $details,
                    'confidence' => max($confidence, (float) data_get($outcomes, $questionId.'.finding.confidence', 0)),
                    'evidence' => [
                        'provider' => $provider['provider'], 'model' => $provider['model'],
                        'review_kind' => 'image', 'image_instruction' => $firstInstruction,
                        'image_instructions' => $instructions,
                    ],
                ],
                'proposal' => [
                    'provider' => $provider['provider'], 'model' => $provider['model'],
                    'proposed_fields' => [], 'image_instruction' => $firstInstruction,
                    'image_instructions' => $instructions,
                    'summary' => $details, 'confidence' => $confidence,
                ],
            ];
        }

        // The provider's complete PDF inventory is authoritative. If it found no
        // source visual for a question, any stored image is stale and must be
        // removed. Questions already containing no image need no repair draft.
        foreach ($questions as $question) {
            $questionId = (int) $question->id;
            if (isset($outcomes[$questionId])) continue;

            $storedImageFields = collect($allowedTargets)
                ->filter(fn (string $field) => preg_match('/<img\b/i', (string) ($question->{$field} ?? '')))
                ->values()
                ->all();
            if ($storedImageFields === []) continue;

            $details = 'The authoritative PDF contains no visual for this question. Stored images in '
                .implode(', ', $storedImageFields).' will be removed from the draft.';
            $outcomes[$questionId] = [
                'finding' => [
                    'type' => 'source_pdf_visual_sync', 'severity' => 'warning',
                    'title' => 'Stored images are absent from the authoritative PDF',
                    'details' => $details, 'confidence' => 100,
                    'evidence' => [
                        'provider' => $provider['provider'], 'model' => $provider['model'],
                        'review_kind' => 'image', 'image_instruction' => null,
                        'image_instructions' => [], 'remove_stored_images' => true,
                        'stored_image_fields' => $storedImageFields,
                    ],
                ],
                'proposal' => [
                    'provider' => $provider['provider'], 'model' => $provider['model'],
                    'proposed_fields' => [], 'image_instruction' => null,
                    'image_instructions' => [], 'remove_stored_images' => true,
                    'summary' => $details, 'confidence' => 100,
                ],
            ];
        }

        if ($diagnostics !== []) $outcomes['_diagnostics'] = $diagnostics;
        return $outcomes;
    }

    private function decode(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) throw new \RuntimeException('The image provider returned invalid JSON.');
        return $decoded;
    }

    private function openAiFileId(ExamQualitySource $source, string $apiKey): string
    {
        if ($source->provider === 'openai' && $source->provider_file_id
            && $source->provider_uploaded_at?->greaterThan(now()->subDays(25))) {
            return $source->provider_file_id;
        }
        [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        $stream = fopen($path, 'r');
        try {
            $response = Http::withToken($apiKey)->timeout(180)->attach('file', $stream, basename($path))
                ->post('https://api.openai.com/v1/files', ['purpose' => 'user_data'])->throw()->json();
        } finally {
            if (is_resource($stream)) fclose($stream);
            if ($cleanup) $cleanup();
        }
        $id = (string) ($response['id'] ?? '');
        if ($id === '') throw new \RuntimeException('OpenAI did not return a source file ID.');
        $source->update(['provider' => 'openai', 'provider_file_id' => $id, 'provider_uploaded_at' => now()]);
        return $id;
    }

    private function geminiFileUri(ExamQualitySource $source, string $apiKey): string
    {
        if ($source->provider === 'gemini' && $source->provider_file_id
            && $source->provider_uploaded_at?->greaterThan(now()->subHours(40))) {
            return $source->provider_file_id;
        }
        [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        try {
            $size = filesize($path);
            $start = Http::withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable', 'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $size,
                'X-Goog-Upload-Header-Content-Type' => 'application/pdf',
                'Content-Type' => 'application/json',
            ])->withBody(json_encode(['file' => ['display_name' => basename($path)]]), 'application/json')
                ->post('https://generativelanguage.googleapis.com/upload/v1beta/files?key='.urlencode($apiKey))->throw();
            $uploadUrl = $start->header('X-Goog-Upload-URL');
            if (! $uploadUrl) throw new \RuntimeException('Gemini did not return an upload URL.');
            $uploaded = Http::withHeaders([
                'Content-Length' => (string) $size, 'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize', 'Content-Type' => 'application/pdf',
            ])->withBody((string) file_get_contents($path), 'application/pdf')->post($uploadUrl)->throw()->json();
        } finally {
            if ($cleanup) $cleanup();
        }
        $uri = (string) data_get($uploaded, 'file.uri', '');
        if ($uri === '') throw new \RuntimeException('Gemini did not return a source file URI.');
        $source->update(['provider' => 'gemini', 'provider_file_id' => $uri, 'provider_uploaded_at' => now()]);
        return $uri;
    }

    private function geminiPdfUrlPart(string $url, string $apiKey): array
    {
        $body = Http::timeout(120)->retry(2, 1000)
            ->withHeaders(['User-Agent' => 'ExamQualityImageAudit/1.0'])->get($url)->throw()->body();
        if ($body === '') throw new \RuntimeException('The source PDF URL returned an empty file.');
        if (strlen($body) <= 18 * 1024 * 1024) {
            return ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($body)]];
        }
        throw new \RuntimeException('Remote Gemini image-audit PDFs must be 18 MB or smaller; upload the PDF as a private exam source instead.');
    }

    private function looksLikePdf(?string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL)
            && str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.pdf');
    }
}
