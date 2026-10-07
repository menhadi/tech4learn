<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\ExamQualitySource;
use App\Models\Question;
use App\Support\AiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class ExamQualitySourceReviewer
{
    private array $sourceManifests = [];
    private bool $cacheSourceDocuments = false;
    private array $processingProfile = [];
    private array $academicQuestionIds = [];
    private array $localSourceTextCache = [];

    public function review(Question $question, int $examId, int $organizationId, ?array $manifest = null, array $processingProfile = [], ?string $selectedProvider = null): array
    {
        $this->processingProfile = $processingProfile;
        $this->sourceManifests[$question->id] = $manifest ?? app(SourceQuestionManifestService::class)->forQuestion($question, $examId, $organizationId, $this->processingProfile);
        $settings = Configuration::where('organization_id', $organizationId)->first();
        if (! $settings) throw new \RuntimeException('AI Settings are not configured for this organization.');

        $sources = $this->attachedPdfSources($examId, $organizationId);
        if ($sources->isEmpty()) throw new \RuntimeException('No active attached PDF is available for source-text review.');

        $providers = $this->providers($settings, false, $selectedProvider, 'source_text_audit');
        if ($providers === []) {
            throw new \RuntimeException('The selected source text-review API is not configured or available.');
        }

        $errors = [];
        foreach ($providers as $provider) {
            try {
                $decoded = match ($provider['name']) {
                    'deepseek' => $this->reviewWithDeepSeek($question, $sources, $provider),
                    'openai' => $this->reviewWithOpenAi($question, $sources, $provider),
                    'gemini' => $this->reviewWithGemini($question, $sources, $provider),
                    'claude' => $this->reviewWithClaude($question, $sources, $provider),
                };
                $decoded = $this->sanitizeFidelityResponse($decoded);
                return collect($this->findings($decoded, $provider['name']))
                    ->map(function (array $finding) use ($question) {
                        $finding['evidence'] = array_merge((array) ($finding['evidence'] ?? []), [
                            'canonical_source' => $this->sourceManifests[$question->id] ?? null,
                        ]);
                        return $finding;
                    })->all();
            } catch (\Throwable $e) {
                $errors[] = ucfirst($provider['name']).': '.$e->getMessage();
                // A successful but malformed/omitted AI response may already be
                // billable. Stop instead of silently charging another provider.
                if (! $e instanceof \Illuminate\Http\Client\RequestException) {
                    break;
                }
            }
        }
        throw new \RuntimeException('All configured source-review providers failed. '.implode(' | ', $errors));
    }

    /**
     * Compare several stored questions with one copy of the source document.
     * This is the cost-efficient audit path; the PDF is not resent once per question.
     */
    public function reviewBatch(Collection $questions, int $examId, int $organizationId, array $processingProfile = [], ?string $selectedProvider = null, bool $cacheSourceDocuments = false): array
    {
        $this->cacheSourceDocuments = $cacheSourceDocuments;
        try {
            $reviews = $this->proposeRepairBatch($questions, $examId, $organizationId, $processingProfile, $selectedProvider);
        } finally {
            $this->cacheSourceDocuments = false;
        }

        return collect($reviews)->mapWithKeys(function (array $review, int|string $questionId) {
            $decoded = [
                'match_status' => $review['match_status'] ?? null,
                'source_reference' => $review['source_reference'] ?? null,
                'source_excerpt' => $review['source_excerpt'] ?? null,
                'summary' => $review['summary'] ?? null,
                'confidence' => $review['confidence'] ?? 0,
                'proposed_fields' => $review['proposed_fields'] ?? [],
                'image_instruction' => $review['image_instruction'] ?? null,
                'issues' => $review['issues'] ?? [],
            ];

            return [(int) $questionId => $this->findings($decoded, (string) ($review['provider'] ?? 'source'))];
        })->all();
    }

    /** Review several questions from the same exam in one provider request. */
    public function proposeRepairBatch(Collection $questions, int $examId, int $organizationId, array $processingProfile = [], ?string $selectedProvider = null, array $academicQuestionIds = []): array
    {
        $this->processingProfile = $processingProfile;
        $this->academicQuestionIds = array_values(array_unique(array_map('intval', $academicQuestionIds)));
        $questions = $questions->filter()->values();
        if ($questions->isEmpty()) return [];
        foreach ($questions as $question) {
            $this->sourceManifests[$question->id] = app(SourceQuestionManifestService::class)->forQuestion($question, $examId, $organizationId, $this->processingProfile);
        }
        $settings = Configuration::where('organization_id', $organizationId)->first();
        if (! $settings) throw new \RuntimeException('AI Settings are not configured for this organization.');
        $sources = $this->attachedPdfSources($examId, $organizationId);
        if ($sources->isEmpty()) throw new \RuntimeException('No active attached PDF is available for batch source-text review.');
        $providers = $this->providers($settings, false, $selectedProvider, 'source_text_audit');
        if ($providers === []) throw new \RuntimeException('No compatible AI provider is configured for batch repair.');

        $errors = [];
        foreach ($providers as $provider) {
            try {
                $decoded = match ($provider['name']) {
                    'deepseek' => $this->batchWithDeepSeek($questions, $sources, $provider),
                    'openai' => $this->batchWithOpenAi($questions, $sources, $provider),
                    'gemini' => $this->batchWithGemini($questions, $sources, $provider),
                    'claude' => $this->batchWithClaude($questions, $sources, $provider),
                };
                $decoded = $this->sanitizeFidelityResponse($decoded);
                $items = collect((array) ($decoded['repairs'] ?? []))->keyBy(fn ($item) => (int) ($item['question_id'] ?? 0));
                $allowed = ['question','option1','option2','option3','option4','option5','option6','answer','true_false','fill_blank','fill_blank_config','nat_config','correct_option_indices','si_answer1','hint','explanation'];
                return $questions->mapWithKeys(function ($question) use ($items, $allowed, $provider) {
                    $item = (array) $items->get($question->id, []);
                    if ($item === []) throw new \RuntimeException('The batch response omitted question '.$question->id.'.');
                    $source = isset($item['source_review']) ? (array) $item['source_review'] : $item;
                    $academic = (array) ($item['academic_review'] ?? []);
                    $sourceFields = collect((array) ($source['proposed_fields'] ?? []))->only($allowed)->all();
                    $academicFields = collect((array) ($academic['proposed_fields'] ?? []))->only($allowed)->all();
                    [$mergedFields, $conflicts] = $this->mergeReviewProposals($sourceFields, $academicFields);
                    return [$question->id => ['provider' => $provider['name'], 'model' => $provider['model'],
                        'proposed_fields' => $mergedFields, 'source_proposed_fields' => $sourceFields,
                        'academic_proposed_fields' => $academicFields,
                        'conflicts' => $conflicts,
                        'academic_review' => $academic,
                        'match_status' => $source['match_status'] ?? null, 'source_reference' => $source['source_reference'] ?? null,
                        'source_excerpt' => $source['source_excerpt'] ?? null,
                        'summary' => $source['summary'] ?? null, 'confidence' => $this->normalizedConfidence($source['confidence'] ?? null, 0),
                        'image_instruction' => $source['image_instruction'] ?? null, 'issues' => (array) ($source['issues'] ?? [])]];
                })->all();
            } catch (\Throwable $e) {
                $errors[] = ucfirst($provider['name']).': '.$e->getMessage();
                if (! $e instanceof \Illuminate\Http\Client\RequestException) {
                    break;
                }
            }
        }
        throw new \RuntimeException('All configured batch repair providers failed. '.implode(' | ', $errors));
    }

    /** One paid request with isolated source and academic results plus a repair proposal. */
    public function reviewAndRepairBatch(Collection $questions, int $examId, int $organizationId, array $processingProfile, string $selectedProvider, array $academicQuestionIds = [], bool $cacheSourceDocuments = false): array
    {
        $this->cacheSourceDocuments = $cacheSourceDocuments;
        try {
            $reviews = $this->proposeRepairBatch($questions, $examId, $organizationId, $processingProfile, $selectedProvider, $academicQuestionIds);
        } finally {
            $this->cacheSourceDocuments = false;
            $this->academicQuestionIds = [];
        }
        return collect($reviews)->mapWithKeys(function (array $review, int|string $questionId) {
            $sourceDecoded = [
                'match_status' => $review['match_status'] ?? null, 'source_reference' => $review['source_reference'] ?? null,
                'source_excerpt' => $review['source_excerpt'] ?? null, 'summary' => $review['summary'] ?? null,
                'confidence' => $review['confidence'] ?? 0, 'proposed_fields' => $review['source_proposed_fields'] ?? [],
                'image_instruction' => $review['image_instruction'] ?? null, 'issues' => $review['issues'] ?? [],
            ];
            return [(int) $questionId => [
                'source_findings' => $this->findings($sourceDecoded, (string) ($review['provider'] ?? 'source')),
                'academic_findings' => $this->academicFindings((array) ($review['academic_review'] ?? []), (string) ($review['provider'] ?? 'ai')),
                'proposal' => $review,
            ]];
        })->all();
    }

    private function batchPrompt(Collection $questions): string
    {
        $firstPrompt = $this->comparisonPrompt($questions->first());
        $profileMarker = ' SOURCE PROCESSING PROFILE:';
        $manifestMarker = ' CANONICAL SOURCE MANIFEST:';
        $policyEnd = strpos($firstPrompt, $profileMarker);
        $policy = $policyEnd === false ? $firstPrompt : substr($firstPrompt, 0, $policyEnd);
        $policy = preg_replace(
            '/Return strict JSON only:.*?Use match_status exact only when all stored (?:content|text) matches and cite evidence\./s',
            '',
            $policy
        ) ?? $policy;
        $profile = $this->processingProfile === [] ? null : $this->processingProfile;
        $items = $questions->map(function ($question) use ($manifestMarker) {
            $prompt = $this->comparisonPrompt($question);
            $dataStart = strpos($prompt, $manifestMarker);

            return [
                'question_id' => $question->id,
                'comparison_data' => $dataStart === false ? $prompt : ltrim(substr($prompt, $dataStart)),
            ];
        })->all();

        $academicIds = $this->academicQuestionIds;
        $academicPolicy = $academicIds === [] ? ' Set academic_review to null for every item and conflicts to an empty array.'
            : ' For question_ids listed in ACADEMIC REVIEW IDS, also perform a separate conservative academic review of correctness, ambiguity, options, answers, explanations, MathJax and mhchem. Never use academic reasoning as source evidence. Put it only inside academic_review. For all other IDs set academic_review to null.';
        return $policy.$academicPolicy
            .' Apply the source policy independently to every batch item. Return exactly: '
            .'{"repairs":[{"question_id":123,"source_review":{"match_status":"exact|minor_difference|major_difference|not_found","source_reference":"page/question/section","source_excerpt":"literal short excerpt","summary":"fidelity comparison only","confidence":0,"answer_source_verified":false,"explanation_source_verified":false,"proposed_fields":{},"image_instruction":null,"issues":[]},"academic_review":{"summary":"academic review only","confidence":0,"proposed_fields":{},"issues":[{"type":"academic_error","severity":"warning|error|critical","title":"short title","details":"specific reason","confidence":0,"suggestion":"optional correction"}]},"conflicts":[]}]}.'
            .' Return one repairs entry for every supplied question_id. Do not merge questions or reuse evidence between questions.'
            .' Keep source_review and academic_review independent. Report different proposals for the same field in conflicts and never silently blend them.'
            .' ACADEMIC REVIEW IDS: '.json_encode($academicIds)
            .' SOURCE PROCESSING PROFILE: '.json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .' BATCH ITEMS: '.json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function batchWithDeepSeek(Collection $questions, Collection $sources, array $provider): array
    {
        $sourceText = $sources->map(fn ($source) => strtoupper($source->role).' SOURCE '.($source->label ?: '')."\n".$this->sourceText($source))->implode("\n\n");
        $response = Http::withToken($provider['key'])->timeout(300)->retry(2, 1000)->post('https://api.deepseek.com/chat/completions', [
            'model' => $provider['model'], 'messages' => [['role' => 'system', 'content' => 'Return strict JSON only.'], ['role' => 'user', 'content' => $sourceText."\n\n".$this->batchPrompt($questions)]],
            'response_format' => ['type' => 'json_object'], 'max_tokens' => min(16000, 3500 * $questions->count()),
        ])->throw()->json();
        return $this->decodeJson((string) data_get($response, 'choices.0.message.content', ''));
    }

    private function batchWithOpenAi(Collection $questions, Collection $sources, array $provider): array
    {
        $content = [];
        foreach ($sources as $source) {
            $content[] = ['type' => 'input_text', 'text' => strtoupper($source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') $content[] = ['type' => 'input_file', 'file_id' => $this->openAiFileId($source, $provider['key'])];
            elseif ($this->looksLikePdf($source->source_url)) $content[] = ['type' => 'input_file', 'file_url' => $source->source_url];
            else throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
        }

        $content[] = ['type' => 'input_text', 'text' => $this->batchPrompt($questions)];
        $response = Http::withToken($provider['key'])->timeout(360)->retry(2, 1000)->post('https://api.openai.com/v1/responses', [
            'model' => $provider['model'], 'input' => [['role' => 'user', 'content' => $content]],
        ])->throw()->json();
        return $this->decodeJson($this->openAiResponseText($response));
    }

    private function batchWithGemini(Collection $questions, Collection $sources, array $provider): array
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = ['text' => strtoupper($source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') $parts[] = ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $this->geminiFileUri($source, $provider['key'])]];
            elseif ($this->looksLikePdf($source->source_url)) $parts[] = $this->geminiPdfUrlPart($source->source_url, $provider['key']);
            else throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
        }

        $parts[] = ['text' => $this->batchPrompt($questions)];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($provider['model']).':generateContent?key='.urlencode($provider['key']);
        $response = Http::timeout(360)->retry(2, 1000)->post($url, ['contents' => [['role' => 'user', 'parts' => $parts]], 'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json']])->throw()->json();
        return $this->decodeJson((string) data_get($response, 'candidates.0.content.parts.0.text', ''));
    }

    public function proposeRepair(Question $question, int $examId, int $organizationId, array $processingProfile = [], ?string $selectedProvider = null): array
    {
        $this->processingProfile = $processingProfile;
        $this->sourceManifests[$question->id] = app(SourceQuestionManifestService::class)->forQuestion($question, $examId, $organizationId, $this->processingProfile);
        $settings = Configuration::where('organization_id', $organizationId)->first();
        if (! $settings) throw new \RuntimeException('AI Settings are not configured for this organization.');

        $sources = $this->attachedPdfSources($examId, $organizationId);
        if ($sources->isEmpty()) throw new \RuntimeException('No active attached PDF is available for source-text repair.');

        $providers = $this->providers($settings, false, $selectedProvider, 'source_text_audit');
        if ($providers === []) throw new \RuntimeException('No supported source text-review provider is configured.');

        $errors = [];
        foreach ($providers as $provider) {
            try {
                $decoded = match ($provider['name']) {
                    'deepseek' => $this->reviewWithDeepSeek($question, $sources, $provider),
                    'openai' => $this->reviewWithOpenAi($question, $sources, $provider),
                    'gemini' => $this->reviewWithGemini($question, $sources, $provider),
                    'claude' => $this->reviewWithClaude($question, $sources, $provider),
                };
                $decoded = $this->sanitizeFidelityResponse($decoded);
                $allowed = ['question','option1','option2','option3','option4','option5','option6','answer','true_false','fill_blank','fill_blank_config','nat_config','correct_option_indices','si_answer1','hint','explanation'];
                $proposed = collect((array) ($decoded['proposed_fields'] ?? []))->only($allowed)->all();
                return [
                    'provider' => $provider['name'],
                    'proposed_fields' => $proposed,
                    'source_reference' => $decoded['source_reference'] ?? null,
                    'source_excerpt' => $decoded['source_excerpt'] ?? null,
                    'summary' => $decoded['summary'] ?? null,
                    'confidence' => $this->normalizedConfidence($decoded['confidence'] ?? null, 0),
                    'image_instruction' => $decoded['image_instruction'] ?? null,
                    'issues' => (array) ($decoded['issues'] ?? []),
                ];
            } catch (\Throwable $e) {
                $errors[] = ucfirst($provider['name']).': '.$e->getMessage();
                // A successful but malformed/omitted AI response may already be
                // billable. Stop instead of silently charging another provider.
                if (! $e instanceof \Illuminate\Http\Client\RequestException) {
                    break;
                }
            }
        }
        throw new \RuntimeException('All configured repair providers failed. '.implode(' | ', $errors));
    }

    /** Only active exam-level PDF attachments may act as source-fidelity evidence. */
    private function attachedPdfSources(int $examId, int $organizationId): Collection
    {
        return ExamQualitySource::where('organization_id', $organizationId)
            ->where('exam_id', $examId)
            ->where('is_active', true)
            ->whereIn('role', ['questions','combined','answers'])
            ->orderByRaw("FIELD(role, 'questions', 'combined', 'answers')")
            ->get()
            ->filter(fn (ExamQualitySource $source) => $source->kind === 'file' || $this->looksLikePdf($source->source_url))
            ->values();
    }

    private function providers(Configuration $settings, bool $requiresVision, ?string $selectedProvider = null, string $task = 'source_text_audit'): array
    {
        $providers = collect(AiProvider::available($settings, $requiresVision, $task))
            ->filter(fn (array $provider) => in_array($provider['provider'], ['deepseek', 'chatgpt', 'gemini', 'claude'], true))
            ->map(fn (array $provider) => [
                'name' => match ($provider['provider']) {
                    'chatgpt' => 'openai',
                    'gemini' => 'gemini',
                    'claude' => 'claude',
                    default => 'deepseek',
                },
                'key' => $provider['key'],
                'model' => $provider['model'],
            ])->values();

        if ($selectedProvider && $selectedProvider !== 'auto') {
            $preferred = $selectedProvider === 'chatgpt' ? 'openai' : $selectedProvider;
            $provider = $providers->firstWhere('name', $preferred);
            $providers = $provider ? collect([$provider]) : collect();
        }

        return $providers->all();
    }

    private function reviewWithDeepSeek(Question $question, Collection $sources, array $provider): array
    {
        $sourceText = $sources->map(fn ($source) => strtoupper($source->role).' SOURCE '.($source->label ?: '')."\n".$this->sourceText($source))->implode("\n\n");
        $response = Http::withToken($provider['key'])->timeout(180)->retry(2, 1000)
            ->post('https://api.deepseek.com/chat/completions', [
                'model' => $provider['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return strict JSON only.'],
                    ['role' => 'user', 'content' => $sourceText."\n\n".$this->comparisonPrompt($question)],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 4000,
            ])->throw()->json();
        return $this->decodeJson((string) data_get($response, 'choices.0.message.content', ''));
    }

    private function reviewWithOpenAi(Question $question, Collection $sources, array $provider): array
    {
        $content = [];
        foreach ($sources as $source) {
            $content[] = ['type' => 'input_text', 'text' => strtoupper($source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                $content[] = ['type' => 'input_file', 'file_id' => $this->openAiFileId($source, $provider['key'])];
            } elseif ($this->looksLikePdf($source->source_url)) {
                $content[] = ['type' => 'input_file', 'file_url' => $source->source_url];
            } else {
                throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
            }
        }

        $content[] = ['type' => 'input_text', 'text' => $this->comparisonPrompt($question)];

        $response = Http::withToken($provider['key'])->timeout(240)->retry(2, 1000)
            ->post('https://api.openai.com/v1/responses', [
                'model' => $provider['model'], 'input' => [['role' => 'user', 'content' => $content]],
            ])->throw()->json();
        return $this->decodeJson($this->openAiResponseText($response));
    }

    private function reviewWithGemini(Question $question, Collection $sources, array $provider): array
    {
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = ['text' => strtoupper($source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                $parts[] = ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $this->geminiFileUri($source, $provider['key'])]];
            } elseif ($this->looksLikePdf($source->source_url)) {
                $parts[] = $this->geminiPdfUrlPart($source->source_url, $provider['key']);
            } else {
                throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
            }
        }

        $parts[] = ['text' => $this->comparisonPrompt($question)];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($provider['model']).':generateContent?key='.urlencode($provider['key']);
        $response = Http::timeout(240)->retry(2, 1000)->post($url, [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
        ])->throw()->json();
        return $this->decodeJson((string) data_get($response, 'candidates.0.content.parts.0.text', ''));
    }

    private function reviewWithClaude(Question $question, Collection $sources, array $provider): array
    {
        return $this->claudeRequest(
            $this->claudeContent($sources, collect([$question]), $this->comparisonPrompt($question)),
            $provider,
            4000
        );
    }

    private function batchWithClaude(Collection $questions, Collection $sources, array $provider): array
    {
        return $this->claudeRequest(
            $this->claudeContent($sources, $questions, $this->batchPrompt($questions)),
            $provider,
            min(16000, 3500 * $questions->count())
        );
    }

    private function claudeContent(Collection $sources, Collection $questions, string $prompt): array
    {
        $content = [];
        foreach ($sources as $source) {
            $content[] = ['type' => 'text', 'text' => strtoupper((string) $source->role).' SOURCE: '.($source->label ?: 'Source')];
            if ($source->kind === 'file') {
                [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
                try {
                    if (! is_file($path)) throw new \RuntimeException('Uploaded source PDF is missing from private storage.');
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
            } else {
                throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
            }
        }

        // The source blocks are identical across audit chunks. Mark the end of
        // that stable prefix so Claude bills later chunks as cache reads rather
        // than repeatedly charging the complete PDF at normal input rates.
        if ($this->cacheSourceDocuments && $content !== []) $content[array_key_last($content)]['cache_control'] = ['type' => 'ephemeral'];


        $content[] = ['type' => 'text', 'text' => $prompt];

        return $content;
    }

    private function claudeRequest(array $content, array $provider, int $maxTokens): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $provider['key'],
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(360)->retry(2, 1000)->post('https://api.anthropic.com/v1/messages', [
            'model' => $provider['model'],
            'max_tokens' => $maxTokens,
            'system' => 'Return strict JSON only.',
            'messages' => [['role' => 'user', 'content' => $content]],
        ])->throw()->json();

        $raw = collect((array) ($response['content'] ?? []))
            ->where('type', 'text')->pluck('text')->implode("\n");
        if (trim($raw) === '') throw new \RuntimeException('Claude returned no source-comparison text.');

        return $this->decodeJson($raw);
    }
    private function openAiFileId(ExamQualitySource $source, string $apiKey): string
    {
        if ($source->provider === 'openai' && $source->provider_file_id && $source->provider_uploaded_at?->greaterThan(now()->subDays(25))) return $source->provider_file_id;
        [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        if (! is_file($path)) throw new \RuntimeException('Uploaded source PDF is missing from private storage.');
        $stream = fopen($path, 'r');
        try {
            $response = Http::withToken($apiKey)->timeout(180)->attach('file', $stream, basename($path))
                ->post('https://api.openai.com/v1/files', ['purpose' => 'user_data'])->throw()->json();
        } finally { if (is_resource($stream)) fclose($stream); if ($cleanup) $cleanup(); }
        $id = (string) ($response['id'] ?? '');
        if ($id === '') throw new \RuntimeException('OpenAI did not return a source file ID.');
        $source->update(['provider' => 'openai', 'provider_file_id' => $id, 'provider_uploaded_at' => now()]);
        return $id;
    }

    private function geminiFileUri(ExamQualitySource $source, string $apiKey): string
    {
        if ($source->provider === 'gemini' && $source->provider_file_id && $source->provider_uploaded_at?->greaterThan(now()->subHours(40))) return $source->provider_file_id;
        [$path, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        try {
            if (! is_file($path)) throw new \RuntimeException('Uploaded source PDF is missing from private storage.');
            $size = filesize($path);
            $start = Http::withHeaders([
                'X-Goog-Upload-Protocol' => 'resumable', 'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $size, 'X-Goog-Upload-Header-Content-Type' => 'application/pdf',
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
        $response = Http::timeout(120)->retry(2, 1000)
            ->withHeaders(['User-Agent' => 'ExamQualityAudit/1.0'])->get($url)->throw();
        $body = $response->body();
        if ($body === '') throw new \RuntimeException('The source PDF URL returned an empty file.');
        if (strlen($body) <= 18 * 1024 * 1024) {
            return ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($body)]];
        }

        $size = strlen($body);
        $start = Http::withHeaders([
            'X-Goog-Upload-Protocol' => 'resumable', 'X-Goog-Upload-Command' => 'start',
            'X-Goog-Upload-Header-Content-Length' => (string) $size,
            'X-Goog-Upload-Header-Content-Type' => 'application/pdf',
        ])->withBody(json_encode(['file' => ['display_name' => basename((string) parse_url($url, PHP_URL_PATH)) ?: 'source.pdf']]), 'application/json')
            ->post('https://generativelanguage.googleapis.com/upload/v1beta/files?key='.urlencode($apiKey))->throw();
        $uploadUrl = $start->header('X-Goog-Upload-URL');
        if (! $uploadUrl) throw new \RuntimeException('Gemini did not return an upload URL for the remote PDF.');
        $uploaded = Http::withHeaders([
            'Content-Length' => (string) $size, 'X-Goog-Upload-Offset' => '0',
            'X-Goog-Upload-Command' => 'upload, finalize', 'Content-Type' => 'application/pdf',
        ])->withBody($body, 'application/pdf')->post($uploadUrl)->throw()->json();
        $uri = (string) data_get($uploaded, 'file.uri', '');
        if ($uri === '') throw new \RuntimeException('Gemini did not return a file URI for the remote PDF.');

        return ['file_data' => ['mime_type' => 'application/pdf', 'file_uri' => $uri]];
    }
    private function sanitizeFidelityResponse(array $decoded): array
    {
        if (isset($decoded['source_review']) && is_array($decoded['source_review'])) {
            $decoded['source_review'] = $this->sanitizeFidelityResponse($decoded['source_review']);
            $decoded['academic_review'] = isset($decoded['academic_review']) && is_array($decoded['academic_review'])
                ? $this->sanitizeAcademicResponse($decoded['academic_review']) : null;
            return $decoded;
        }
        if (isset($decoded['repairs']) && is_array($decoded['repairs'])) {
            $decoded['repairs'] = array_map(
                fn ($item) => is_array($item) ? $this->sanitizeFidelityResponse($item) : $item,
                $decoded['repairs']
            );
            return $decoded;
        }

        $reference = trim((string) ($decoded['source_reference'] ?? ''));
        $excerpt = trim((string) ($decoded['source_excerpt'] ?? ''));
        $hasVisualEvidence = ! empty($decoded['image_instruction']);
        $answerVerified = ($decoded['answer_source_verified'] ?? false) === true && $reference !== '' && $excerpt !== '';
        $explanationVerified = ($decoded['explanation_source_verified'] ?? false) === true && $reference !== '' && $excerpt !== '';
        $answerFields = ['answer','true_false','fill_blank','fill_blank_config','nat_config','correct_option_indices','si_answer1'];
        $proposed = (array) ($decoded['proposed_fields'] ?? []);
        if (! $answerVerified) $proposed = array_diff_key($proposed, array_flip($answerFields));
        if (! $explanationVerified) unset($proposed['explanation'], $proposed['hint']);
        $decoded['proposed_fields'] = $proposed;

        $forbidden = ['academic_error','factual_error','incorrect_answer','wrong_answer','answer_correctness','option_plausibility','question_ambiguity','difficulty','marks'];
        $decoded['issues'] = array_values(array_filter((array) ($decoded['issues'] ?? []), function ($issue) use ($answerVerified, $explanationVerified, $reference, $excerpt, $hasVisualEvidence, $forbidden) {
            if (! is_array($issue)) return false;
            $type = preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($issue['type'] ?? 'source_mismatch')));
            foreach ($forbidden as $blocked) {
                if (str_contains($type, $blocked)) return false;
            }
            if (! $answerVerified && (str_contains($type, 'answer') || str_contains($type, 'correct_option') || str_contains($type, 'solution'))) return false;
            if (! $explanationVerified && (str_contains($type, 'explanation') || str_contains($type, 'hint'))) return false;
            if ($type !== 'not_found' && $type !== 'source_question_not_found' && $reference === '') return false;
            if ($type !== 'not_found' && $type !== 'source_question_not_found' && $excerpt === '' && ! $hasVisualEvidence) return false;
            return true;
        }));

        $matchStatus = strtolower(trim((string) ($decoded['match_status'] ?? '')));
        $confidence = $this->normalizedConfidence($decoded['confidence'] ?? null, 0);
        $hasSourceEvidence = $reference !== '' && ($excerpt !== '' || $hasVisualEvidence);
        $verifiedExact = $matchStatus === 'exact'
            && $decoded['issues'] === []
            && $hasSourceEvidence
            && $confidence >= 85;

        if ($matchStatus === 'exact' && ! $verifiedExact) {
            $reasons = [];
            if ($reference === '') $reasons[] = 'no source page/question reference';
            if ($excerpt === '' && ! $hasVisualEvidence) $reasons[] = 'no quoted or visual source evidence';
            if ($confidence < 85) $reasons[] = 'confidence below 85%';
            if ($decoded['issues'] !== []) $reasons[] = 'reported discrepancies remain';
            $decoded['match_status'] = 'unverified';
            $decoded['summary'] = 'The provider claimed an exact match, but it was not sufficiently source-backed: '.implode(', ', $reasons).'.';
            $decoded['issues'][] = [
                'type' => 'source_comparison_unverified',
                'severity' => 'warning',
                'title' => 'Exact source match was not verified',
                'details' => $decoded['summary'],
            ];
        } elseif ($verifiedExact) {
            $decoded['summary'] = 'Exact source match verified with source evidence.';
        }

        return $decoded;
    }

    private function sanitizeAcademicResponse(array $decoded): array
    {
        $allowed = ['question','option1','option2','option3','option4','option5','option6','answer','true_false','fill_blank','fill_blank_config','nat_config','correct_option_indices','si_answer1','hint','explanation'];
        $decoded['proposed_fields'] = collect((array) ($decoded['proposed_fields'] ?? []))->only($allowed)
            ->reject(fn ($value) => $value === null || $value === '')->all();
        $decoded['confidence'] = $this->normalizedConfidence($decoded['confidence'] ?? null, 0);
        $decoded['issues'] = array_values(array_filter((array) ($decoded['issues'] ?? []), 'is_array'));
        return $decoded;
    }

    private function mergeReviewProposals(array $source, array $academic): array
    {
        $merged = $source;
        $conflicts = [];
        foreach ($academic as $field => $value) {
            if (! array_key_exists($field, $source)) $merged[$field] = $value;
            elseif (json_encode($source[$field]) !== json_encode($value)) $conflicts[] = [
                'field' => $field, 'source_value' => $source[$field], 'academic_value' => $value, 'resolution' => 'manual_review',
            ];
        }
        return [$merged, $conflicts];
    }

    private function academicFindings(array $decoded, string $provider): array
    {
        return array_values(array_filter(array_map(function ($issue) use ($decoded, $provider) {
            if (! is_array($issue) || empty($issue['title'])) return null;
            return [
                'type' => preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($issue['type'] ?? 'academic_review'))),
                'severity' => in_array($issue['severity'] ?? null, ['info','warning','error','critical'], true) ? $issue['severity'] : 'warning',
                'title' => mb_substr((string) $issue['title'], 0, 255), 'details' => (string) ($issue['details'] ?? ''),
                'confidence' => $this->normalizedConfidence($issue['confidence'] ?? $decoded['confidence'] ?? null, 50),
                'evidence' => ['provider' => $provider, 'suggestion' => $issue['suggestion'] ?? null,
                    'proposed_fields' => $decoded['proposed_fields'] ?? [], 'review_kind' => 'academic'],
            ];
        }, (array) ($decoded['issues'] ?? []))));
    }

    private function findings(array $decoded, string $provider): array
    {
        if (($decoded['match_status'] ?? null) === 'exact' && empty($decoded['issues'])) return [];
        $issues = (array) ($decoded['issues'] ?? []);
        if ($issues === []) $issues[] = [
            'type' => ($decoded['match_status'] ?? '') === 'not_found' ? 'source_question_not_found' : 'source_mismatch',
            'severity' => ($decoded['match_status'] ?? '') === 'not_found' ? 'error' : 'warning',
            'title' => ($decoded['match_status'] ?? '') === 'not_found' ? 'Question not found in source' : 'Question differs from source',
            'details' => $decoded['summary'] ?? 'Stored content does not exactly match the supplied source.',
        ];
        return array_values(array_filter(array_map(function ($issue) use ($decoded, $provider) {
            if (! is_array($issue)) return null;
            return [
                'type' => preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) ($issue['type'] ?? 'source_mismatch'))),
                'severity' => in_array($issue['severity'] ?? null, ['info','warning','error','critical'], true) ? $issue['severity'] : 'error',
                'title' => mb_substr((string) ($issue['title'] ?? 'Source mismatch'), 0, 255),
                'details' => (string) ($issue['details'] ?? $decoded['summary'] ?? ''),
                'confidence' => $this->normalizedConfidence($decoded['confidence'] ?? null, 70),
                'evidence' => [
                    'provider' => $provider, 'source_reference' => $decoded['source_reference'] ?? null,
                    'source_excerpt' => $decoded['source_excerpt'] ?? null,
                    'suggested_correction' => $issue['suggested_correction'] ?? null,
                    'proposed_fields' => $decoded['proposed_fields'] ?? null,
                    'image_instruction' => $decoded['image_instruction'] ?? null,
                    'match_status' => $decoded['match_status'] ?? null,
                ],
            ];
        }, $issues)));
    }

    private function comparisonPrompt(Question $question): string
    {
        $evaluator = app(QuestionAnswerEvaluator::class);
        $stored = [
            'question_id' => $question->id, 'question_code' => $question->question_code,
            'source_reference' => $question->source_reference,
            'type' => $evaluator->questionType($question),
            'type_label' => $question->qtype?->question_type,
            'canonical_answer' => $evaluator->correctAnswerSnapshot($question),
            'question' => $this->plain($question->question),
            'question_html' => mb_substr($this->textAuditHtml($question->question), 0, 30000),
            'options' => collect(range(1, 6))->map(fn ($i) => $this->plain($question->{'option'.$i}))->filter()->values()->all(),
            'editable_html' => collect(['question','option1','option2','option3','option4','option5','option6','hint','explanation'])
                ->mapWithKeys(fn ($field) => [$field => mb_substr($this->textAuditHtml($question->{$field}), 0, 30000)])->all(),
            'correct_option_indices' => $question->correctOptionIndices(),
            'subjective_reference_answer' => $question->si_answer1, 'true_false' => $question->true_false,
            'fill_blank' => $question->fill_blank_config ?: $question->fill_blank, 'nat' => $question->nat_config,
            'explanation' => $this->plain($question->explanation),
        ];
        $manifest = $this->sourceManifests[$question->id] ?? [];
        $profile = $this->processingProfile === [] ? null : $this->processingProfile;

        $policy = <<<'PROMPT'
This is the text-only source-fidelity lane, never academic or image review. The attached active QUESTION, COMBINED, and ANSWERS PDFs are the sole source of authority; nothing else may be used as evidence. The stored question is only the comparison target. Locate this exact question in the attached PDF/OCR text and compare wording, characters, formulas, symbols, question type, every option/value, and text/table content. Ignore images, diagrams and visual placement; the independent image bot owns them.

Never solve the question. Never judge factual correctness, ambiguity, difficulty, marks, option plausibility, or whether an answer should be correct. Answers and explanations are transcription data only. Compare an answer only when an attached ANSWERS or COMBINED source explicitly states it. Compare an explanation only when the attached source explicitly contains it. Otherwise set the corresponding verification boolean false and omit all findings and proposals for that content.

Every discrepancy must cite source_reference and source_excerpt. Do not infer missing content. Do not flag typography that preserves identical content. Set image_instruction to null and never add, remove, compare or rewrite img tags. proposed_fields may contain only complete source-faithful text replacements proven by the extracted source text.

Return strict JSON only: {"match_status":"exact|minor_difference|major_difference|not_found","source_reference":"page/question/section","source_excerpt":"literal short excerpt","summary":"text fidelity comparison only","confidence":0-100,"answer_source_verified":true,"explanation_source_verified":true,"proposed_fields":{},"image_instruction":null,"issues":[{"type":"question_text|formula|option|missing_option|answer_key|explanation|not_found","severity":"warning|error|critical","title":"short title","details":"exact literal discrepancy","suggested_correction":"source-faithful correction"}]}. Use match_status exact only when all stored text matches and cite evidence.
PROMPT;

        return $policy
            .' SOURCE PROCESSING PROFILE: '.json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .' CANONICAL SOURCE MANIFEST: '.json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .' STORED QUESTION: '.json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sourceText(ExamQualitySource $source): string
    {
        if ($source->kind !== 'file' && ! $this->looksLikePdf($source->source_url)) {
            throw new \RuntimeException('Only attached PDF sources are permitted for source-text review.');
        }

        $cacheKey = implode('|', [$source->id ?: $source->source_url, $source->updated_at?->timestamp]);
        if (isset($this->localSourceTextCache[$cacheKey])) return $this->localSourceTextCache[$cacheKey];

        $cleanup = null;
        if ($source->kind === 'file') {
            [$pdfPath, $cleanup] = app(ExamQualitySourceStorage::class)->localPath($source);
        } else {
            $response = Http::timeout(120)->retry(2, 750)
                ->withHeaders(['User-Agent' => 'ExamQualityTextAudit/1.0'])
                ->get((string) $source->source_url)->throw();
            $pdfPath = @tempnam(sys_get_temp_dir(), 'exam-text-source-');
            if ($pdfPath === false || file_put_contents($pdfPath, $response->body()) === false) {
                throw new \RuntimeException('The source PDF could not be prepared for local text extraction.');
            }
            $cleanup = fn () => @unlink($pdfPath);
        }

        try {
            $process = new Process([
                'python3', base_path('scripts/extract-pdf-text.py'), $pdfPath, '1',
            ], base_path(), null, null, 360);
            $process->run();
            $decoded = json_decode(trim($process->getOutput()), true);
            if (! $process->isSuccessful() || ! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
                throw new \RuntimeException((string) ($decoded['error'] ?? trim($process->getErrorOutput()) ?: 'Local PDF text extraction failed.'));
            }
            $text = collect((array) ($decoded['pages'] ?? []))->map(function (array $page) {
                $body = trim((string) ($page['text'] ?? ''));
                return $body === '' ? null : 'PDF PAGE '.(int) ($page['page'] ?? 0)."\n".$body;
            })->filter()->implode("\n\n");
            if ($text === '') throw new \RuntimeException('No readable text was extracted from the source PDF, including local OCR.');

            return $this->localSourceTextCache[$cacheKey] = mb_substr($text, 0, 500000);
        } finally {
            if (is_callable($cleanup)) $cleanup();
        }
    }

    private function openAiResponseText(array $response): string
    {
        foreach ((array) ($response['output'] ?? []) as $item) foreach ((array) ($item['content'] ?? []) as $content) {
            if (($content['type'] ?? null) === 'output_text') return (string) ($content['text'] ?? '');
        }
        throw new \RuntimeException('OpenAI returned no source-comparison text.');
    }

    private function decodeJson(string $raw): array
    {
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)) ?? $raw);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) throw new \RuntimeException('Source comparison returned invalid JSON.');
        return $decoded;
    }

    private function looksLikePdf(?string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.pdf');
    }

    private function normalizedConfidence(mixed $value, float $default): float
    {
        if ($value === null || $value === '') return $default;
        $confidence = (float) $value;
        if ($confidence > 0 && $confidence <= 1) $confidence *= 100;
        return max(0, min(100, $confidence));
    }

    private function textAuditHtml(mixed $value): string
    {
        return preg_replace('/<img\b[^>]*>/i', '', (string) $value) ?? (string) $value;
    }

    private function plain(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value))) ?? '');
    }
}
