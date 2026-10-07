@extends('layouts.master')
@section('title', 'AI Settings')
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Configuration')
@slot('title', 'AI Settings')
@endcomponent

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Errors:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $providers = [
        'deepseek' => [
            'label' => 'DeepSeek',
            'key_field' => 'deepseek_api_key',
            'model_field' => 'deepseek_model',
            'default_model' => 'deepseek-chat',
            'models' => ['deepseek-chat' => 'deepseek-chat', 'deepseek-reasoner' => 'deepseek-reasoner'],
            'help' => 'Supports text workflows plus vision workflows through the separately configured DeepSeek vision model.',
            'docs' => 'https://api-docs.deepseek.com/',
            'pricing' => 'https://api-docs.deepseek.com/quick_start/pricing',
        ],
        'openai' => [
            'label' => 'ChatGPT',
            'key_field' => 'openai_api_key',
            'model_field' => 'openai_model',
            'default_model' => 'gpt-4o',
            'models' => ['gpt-4o' => 'gpt-4o', 'gpt-4o-mini' => 'gpt-4o-mini', 'gpt-4.1' => 'gpt-4.1', 'gpt-4.1-mini' => 'gpt-4.1-mini'],
            'help' => 'Strong general model family for generation, review, and evaluation workflows.',
            'docs' => 'https://platform.openai.com/docs',
            'pricing' => 'https://openai.com/api/pricing/',
        ],
        'google' => [
            'label' => 'Gemini',
            'key_field' => 'google_gemini_api_key',
            'model_field' => 'google_gemini_model',
            'default_model' => 'gemini-1.5-flash',
            'models' => ['gemini-1.5-flash' => 'gemini-1.5-flash', 'gemini-1.5-pro' => 'gemini-1.5-pro', 'gemini-pro' => 'gemini-pro'],
            'help' => 'Fast generation option. Use the pricing link for current model cost.',
            'docs' => 'https://ai.google.dev/gemini-api/docs',
            'pricing' => 'https://ai.google.dev/pricing',
        ],
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'key_field' => 'anthropic_api_key',
            'model_field' => 'anthropic_model',
            'default_model' => 'claude-sonnet-4-5',
            'models' => ['claude-sonnet-4-5' => 'claude-sonnet-4-5', 'claude-haiku-4-5' => 'claude-haiku-4-5', 'claude-opus-4-1' => 'claude-opus-4-1'],
            'help' => 'Claude models for content, review, evaluation, translation and source workflows.',
            'docs' => 'https://docs.anthropic.com/en/docs/about-claude/models/overview',
            'pricing' => 'https://www.anthropic.com/pricing',
        ],
    ];
@endphp

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">AI Provider Settings</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('configurations.ai.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    @php
                        $savedPriority = old('ai_provider_priority', $configuration->ai_provider_priority ?? []);
                        if (count($savedPriority) !== 4) {
                            $savedPriority = ['google', 'openai', 'anthropic', 'deepseek'];
                        }
                        $taskPriorityDefinitions = [
                            'translation' => ['Translation', 'Used for translating questions and educational content.'],
                            'academic_review' => ['Academic Review', 'Checks correctness, ambiguity, answer consistency and explanation quality independently of source fidelity.'],
                            'source_text_audit' => ['Source Text Audit', 'Uses locally extracted/OCR text. DeepSeek can be selected here without paying a vision provider for text review.'],
                            'image_audit' => ['Image Audit & Extraction', 'Vision-only paper pass that returns all diagram/image crop coordinates. DeepSeek uses the configured DeepSeek vision model.'],
                            'answer_explanation' => ['Answers & Explanations', 'Generates missing answers and explanations, then independently checks stored answer/explanation pairs.'],
                            'question_generation' => ['Question Generation', 'Used for imported/source questions and new AI-generated questions.'],
                            'question_regeneration' => ['Question Regeneration', 'Used when improving or regenerating existing questions.'],
                            'content_seo' => ['AI Content & SEO', 'Used for website content, descriptions, metadata and SEO generation.'],
                            'subjective_assessment' => ['Subjective Assessment', 'Every configured provider assesses independently; successful scores are averaged.'],
                        ];
                        $savedTaskPriorities = old('ai_task_priorities', $configuration->ai_task_priorities ?? []);
                    @endphp
                    <div class="row g-3 mb-4">

                        <div class="col-12">
                            <label class="form-label">Source Fidelity Audit / PDF Provider Priority</label>
                            <div class="row g-2">
                                @foreach (['First choice', 'Second choice', 'Third choice', 'Fourth choice'] as $priorityIndex => $priorityLabel)
                                    <div class="col-md-3">
                                        <div class="input-group">
                                            <span class="input-group-text">{{ $priorityIndex + 1 }}</span>
                                            <select class="form-select js-provider-priority" name="ai_provider_priority[]" aria-label="{{ $priorityLabel }}">
                                                @foreach ($providers as $key => $provider)
                                                    <option value="{{ $key }}" @selected(($savedPriority[$priorityIndex] ?? null) === $key)>{{ $provider['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-text">{{ $priorityLabel }}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="alert alert-info py-2 px-3 mt-2 mb-0 small">
                                Source Fidelity Audit tries providers in this exact order. Extraction scripts receive these configured credentials as environment variables. Academic Review uses its separate workflow priority below.
                            </div>
                        </div>
                    </div>

                    <div class="card border mt-2 mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-1">Workflow Provider & Model Priority</h5>
                            <p class="text-muted small mb-0">Each workflow tries providers in its own order. The model used is the model version configured for that provider in the table below.</p>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ($taskPriorityDefinitions as $taskKey => [$taskLabel, $taskHelp])
                                    @php
                                        $taskPriority = $savedTaskPriorities[$taskKey] ?? $savedPriority;
                                        if (count((array) $taskPriority) !== 4) $taskPriority = $savedPriority;
                                    @endphp
                                    <div class="col-xl-6">
                                        <div class="border rounded p-3 h-100 js-priority-group">
                                            <div class="fw-semibold">{{ $taskLabel }}</div>
                                            <div class="small text-muted mb-2">{{ $taskHelp }}</div>
                                            <div class="row g-2">
                                                @foreach (['First', 'Second', 'Third', 'Fourth'] as $priorityIndex => $priorityLabel)
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">{{ $priorityLabel }}</label>
                                                        <select class="form-select js-provider-priority" name="ai_task_priorities[{{ $taskKey }}][]">
                                                            @foreach ($providers as $key => $provider)
                                                                <option value="{{ $key }}" @selected(($taskPriority[$priorityIndex] ?? null) === $key)>{{ $provider['label'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 14%;">AI Model</th>
                                    <th style="width: 32%;">API Key</th>
                                    <th style="width: 20%;">Model Version</th>
                                    <th>Information</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($providers as $key => $provider)
                                    @php
                                        $keyField = $provider['key_field'];
                                        $modelField = $provider['model_field'];
                                        $selectedModel = old($modelField, $configuration->{$modelField} ?? $provider['default_model']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $provider['label'] }}</div>
                                            <span class="badge bg-light text-dark">Available when configured</span>
                                        </td>
                                        <td>
                                            <input type="password" class="form-control" id="{{ $keyField }}" name="{{ $keyField }}" value="{{ old($keyField, $configuration->{$keyField} ?? '') }}" placeholder="Paste API key">
                                        </td>
                                        <td>
                                            @php $isCustomModel = ! array_key_exists($selectedModel, $provider['models']); @endphp
                                            <select class="form-select js-model-choice" name="{{ $modelField }}" id="{{ $modelField }}" data-custom-target="{{ $modelField }}_custom">
                                                @foreach ($provider['models'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(! $isCustomModel && $selectedModel === $value)>{{ $label }}</option>
                                                @endforeach
                                                <option value="__custom__" @selected($isCustomModel)>Custom / newly released model</option>
                                            </select>
                                            <input type="text" class="form-control mt-2 js-custom-model {{ $isCustomModel ? '' : 'd-none' }}" id="{{ $modelField }}_custom" name="{{ $modelField }}_custom" value="{{ $isCustomModel ? $selectedModel : '' }}" placeholder="Exact provider model name">
                                        </td>
                                        <td>
                                            <div class="small text-muted mb-2">{{ $provider['help'] }}</div>
                                            <a href="{{ $provider['docs'] }}" target="_blank" rel="noopener" class="me-3">Docs</a>
                                            <a href="{{ $provider['pricing'] }}" target="_blank" rel="noopener">Pricing</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card border-primary mt-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-1">DeepSeek Vision</h5>
                            <p class="text-muted small mb-0">Used automatically whenever a workflow sends PDF pages, crops, screenshots, diagrams, or other images to DeepSeek.</p>
                        </div>
                        <div class="card-body">
                            @php
                                $deepseekVisionModel = old('deepseek_vision_model', $configuration->deepseek_vision_model ?? 'deepseek-v4-flash-vision-exp');
                                $deepseekVisionModels = ['deepseek-v4-flash-vision-exp' => 'deepseek-v4-flash-vision-exp'];
                                $isCustomDeepseekVisionModel = ! array_key_exists($deepseekVisionModel, $deepseekVisionModels);
                            @endphp
                            <label class="form-label" for="deepseek_vision_model">Vision model</label>
                            <select class="form-select js-model-choice" name="deepseek_vision_model" id="deepseek_vision_model" data-custom-target="deepseek_vision_model_custom">
                                @foreach ($deepseekVisionModels as $value => $label)
                                    <option value="{{ $value }}" @selected(! $isCustomDeepseekVisionModel && $deepseekVisionModel === $value)>{{ $label }}</option>
                                @endforeach
                                <option value="__custom__" @selected($isCustomDeepseekVisionModel)>Custom / newly released vision model</option>
                            </select>
                            <input type="text" class="form-control mt-2 js-custom-model {{ $isCustomDeepseekVisionModel ? '' : 'd-none' }}" id="deepseek_vision_model_custom" name="deepseek_vision_model_custom" value="{{ $isCustomDeepseekVisionModel ? $deepseekVisionModel : '' }}" placeholder="Exact DeepSeek vision model name">
                            <div class="form-text">Do not enter a text-only model such as deepseek-chat here. DeepSeek currently documents deepseek-v4-flash-vision-exp for image input.</div>
                        </div>
                    </div>

                    <div class="card border-primary mt-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-1">Mathpix Equation &amp; STEM Crop OCR</h5>
                            <p class="text-muted small mb-0">Used only when an administrator manually crops text or an equation from an authoritative PDF. Recognition is previewed before it is inserted and saved to the draft.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="mathpix_enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="mathpix_enabled" name="mathpix_enabled" value="1" @checked((bool) old('mathpix_enabled', $configuration->mathpix_enabled ?? false))>
                                <label class="form-check-label fw-semibold" for="mathpix_enabled">Enable crop-to-MathJax with Mathpix</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-4"><label class="form-label" for="mathpix_app_id">Mathpix App ID</label><input class="form-control" id="mathpix_app_id" name="mathpix_app_id" value="{{ old('mathpix_app_id', $configuration->mathpix_app_id ?? '') }}" autocomplete="off"></div>
                                <div class="col-lg-4"><label class="form-label" for="mathpix_app_key">Mathpix App Key</label><input type="password" class="form-control" id="mathpix_app_key" name="mathpix_app_key" value="{{ old('mathpix_app_key', $configuration->mathpix_app_key ?? '') }}" autocomplete="new-password"></div>
                                <div class="col-lg-4"><label class="form-label" for="mathpix_min_confidence">Low-confidence warning below</label><div class="input-group"><input type="number" min="0" max="100" step="0.01" class="form-control" id="mathpix_min_confidence" name="mathpix_min_confidence" value="{{ old('mathpix_min_confidence', $configuration->mathpix_min_confidence ?? 70) }}"><span class="input-group-text">%</span></div></div>
                            </div>
                            <div class="form-text mt-2">Credentials stay on the server. Complex chemical structures should remain images; use Mathpix text insertion for equations, formulas, reactions and mixed STEM text.</div>
                        </div>
                    </div>
                                        <div class="card border-primary mt-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-1">Image Cleanup &amp; Faithful Redraw Bot</h5>
                            <p class="text-muted small mb-0">Used only for administrator-selected existing images. Each image is a separate paid request and the result remains a draft until reviewed and published.</p>
                        </div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="image_cleanup_enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="image_cleanup_enabled" name="image_cleanup_enabled" value="1" @checked((bool) old('image_cleanup_enabled', $configuration->image_cleanup_enabled ?? false))>
                                <label class="form-check-label fw-semibold" for="image_cleanup_enabled">Enable the Image Cleanup Bot</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-3"><label class="form-label" for="image_cleanup_provider">Image-edit API</label><select class="form-select" id="image_cleanup_provider" name="image_cleanup_provider"><option value="openai" @selected(old('image_cleanup_provider', $configuration->image_cleanup_provider ?? 'openai') === 'openai')>OpenAI Image API</option><option value="google" @selected(old('image_cleanup_provider', $configuration->image_cleanup_provider ?? 'openai') === 'google')>Google Gemini Image</option></select></div>
                                <div class="col-lg-3"><label class="form-label" for="image_cleanup_openai_model">OpenAI image model</label><input class="form-control" id="image_cleanup_openai_model" name="image_cleanup_openai_model" value="{{ old('image_cleanup_openai_model', $configuration->image_cleanup_openai_model ?? 'gpt-image-2') }}"></div>
                                <div class="col-lg-3"><label class="form-label" for="image_cleanup_google_model">Gemini image model</label><input class="form-control" id="image_cleanup_google_model" name="image_cleanup_google_model" value="{{ old('image_cleanup_google_model', $configuration->image_cleanup_google_model ?? 'gemini-3.1-flash-image') }}"></div>
                                <div class="col-lg-3"><label class="form-label" for="image_cleanup_quality">Output quality</label><select class="form-select" id="image_cleanup_quality" name="image_cleanup_quality">@foreach(['low','medium','high'] as $quality)<option value="{{ $quality }}" @selected(old('image_cleanup_quality', $configuration->image_cleanup_quality ?? 'medium') === $quality)>{{ ucfirst($quality) }}</option>@endforeach</select></div>
                            </div>
                            <div class="border rounded p-3 mt-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                                    <div><h6 class="mb-1">Brand cleaned images</h6><div class="small text-muted">Applied locally after cleanup, with no additional API request. Logo mode reuses the organization logo.</div></div>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="image_cleanup_branding_enabled" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="image_cleanup_branding_enabled" name="image_cleanup_branding_enabled" value="1" @checked((bool) old('image_cleanup_branding_enabled', $configuration->image_cleanup_branding_enabled ?? false))>
                                        <label class="form-check-label fw-semibold" for="image_cleanup_branding_enabled">Add watermark</label>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-lg-4"><label class="form-label" for="image_cleanup_branding_mode">Watermark type</label><select class="form-select" id="image_cleanup_branding_mode" name="image_cleanup_branding_mode"><option value="logo" @selected(old('image_cleanup_branding_mode', $configuration->image_cleanup_branding_mode ?? 'logo') === 'logo')>Organization logo</option><option value="text" @selected(old('image_cleanup_branding_mode', $configuration->image_cleanup_branding_mode ?? 'logo') === 'text')>Text watermark</option></select></div>
                                    <div class="col-lg-5"><label class="form-label" for="image_cleanup_watermark_text">Watermark text / logo fallback</label><input class="form-control" id="image_cleanup_watermark_text" name="image_cleanup_watermark_text" maxlength="120" value="{{ old('image_cleanup_watermark_text', $configuration->image_cleanup_watermark_text ?? '') }}" placeholder="ExamElite"></div>
                                    <div class="col-lg-3"><label class="form-label" for="image_cleanup_watermark_opacity">Opacity</label><div class="input-group"><input type="number" min="3" max="25" class="form-control" id="image_cleanup_watermark_opacity" name="image_cleanup_watermark_opacity" value="{{ old('image_cleanup_watermark_opacity', $configuration->image_cleanup_watermark_opacity ?? 8) }}"><span class="input-group-text">%</span></div></div>
                                </div>
                                <div class="form-text mt-2">A subtle centered mark is added to every generated or locally cleaned draft. PHP GD must be enabled on the worker.</div>
                            </div>                            <div class="alert alert-warning py-2 mt-3 mb-0 small"><strong>No provider fallback:</strong> the selected API and model are locked into each run so cost and behavior remain predictable. Generated technical diagrams require symbol-by-symbol administrator verification.</div>
                        </div>
                    </div>                    @php $promptDefaults = \App\Support\AiQuestionPrompt::defaults(); @endphp
                    <div class="card mt-4">
                        <div class="card-header"><h5 class="card-title mb-0">AI Question Regeneration Quality & Prompts</h5></div>
                        <div class="card-body">
                            <p class="text-muted">These instructions are applied when regenerating selected questions. Use <code>{context}</code> in every type template; it is replaced with group, category, subcategory, package, exam, subject, topic, subtopic, difficulty, language, marks, tags, passage, and the original question, options, answer and explanation.</p>
                            <div class="mb-3">
                                <label class="form-label">Global quality instructions</label>
                                <textarea class="form-control" rows="6" name="ai_regeneration_quality_prompt">{{ old('ai_regeneration_quality_prompt', $configuration->ai_regeneration_quality_prompt ?: $promptDefaults['quality']) }}</textarea>
                            </div>
                            @foreach (['mcq' => ['MCQ', 'M'], 'true_false' => ['True / False', 'T'], 'fill_blank' => ['Fill in the Blank', 'F'], 'subjective' => ['Subjective', 'S'], 'nat' => ['Numerical Answer Type (NAT)', 'NAT']] as $promptKey => [$promptLabel, $typeCode])
                                @php $promptField = 'ai_regeneration_'.$promptKey.'_prompt'; @endphp
                                <div class="mb-3">
                                    <label class="form-label">{{ $promptLabel }} template</label>
                                    <textarea class="form-control font-monospace" rows="6" name="{{ $promptField }}">{{ old($promptField, $configuration->{$promptField} ?: $promptDefaults[$typeCode]) }}</textarea>
                                </div>
                            @endforeach
                            <div class="alert alert-info mb-0">To restore defaults, clear a prompt field and save. The default template will be used automatically.</div>
                        </div>
                    </div>
                    <div class="card mt-4 mb-0">
                        <div class="card-body">
                            <label class="d-flex align-items-start gap-3 mb-0">
                                <input type="checkbox"
                                       class="form-check-input mt-1"
                                       name="study_card_question_rotation_enabled"
                                       value="1"
                                       @checked((bool) old('study_card_question_rotation_enabled', $configuration->study_card_question_rotation_enabled ?? true))>
                                <span>
                                    <span class="fw-semibold d-block">Smart Study Card Question Rotation</span>
                                    <span class="text-muted small d-block">
                                        Show unseen linked questions first, then rotate by Easy/Medium/Hard and previous answers. Disable this if you want each study card to always show the same first linked question.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                                        <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Save AI Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.js-priority-group, .col-12').forEach(function(group) {
        const prioritySelects = Array.from(group.querySelectorAll('.js-provider-priority'));
        if (prioritySelects.length !== 4) return;
        prioritySelects.forEach(function(select) {
            select.addEventListener('change', function() {
                const used = new Set();
                prioritySelects.forEach(function(item) {
                    if (used.has(item.value)) {
                        const replacement = Object.keys(@json($providers)).find(key => !used.has(key));
                        if (replacement) item.value = replacement;
                    }
                    used.add(item.value);
                });
            });
        });
    });
    document.querySelectorAll('.js-model-choice').forEach(function(select) {
        const custom = document.getElementById(select.dataset.customTarget);
        const sync = function() {
            const enabled = select.value === '__custom__';
            custom.classList.toggle('d-none', !enabled);
            custom.required = enabled;
        };
        select.addEventListener('change', sync);
        sync();
    });
});
</script>
@endsection
