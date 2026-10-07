<?php

namespace App\Http\Controllers;

use App\Models\{Category, Group, Language, OfficialExamSource, OfficialExamSourceRule, Package};
use App\Services\OfficialExamMonitorService;
use App\Support\{SourceExtractorRegistry, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficialExamSourceController extends Controller
{
    public function index()
    {
        $sources = OfficialExamSource::query()->where('organization_id', Tenant::id())
            ->withCount(['rules', 'discoveries', 'discoveries as created_count' => fn ($q) => $q->where('status', 'created'), 'discoveries as attention_count' => fn ($q) => $q->whereIn('status', ['needs_configuration', 'awaiting_companion', 'revised', 'failed'])])
            ->latest()->paginate(20);
        return view('official-exam-sources.index', compact('sources'));
    }

    public function create(SourceExtractorRegistry $registry)
    {
        return view('official-exam-sources.form', $this->formData($registry) + ['source' => new OfficialExamSource(), 'editing' => false]);
    }

    public function store(Request $request, SourceExtractorRegistry $registry)
    {
        $data = $this->validated($request, $registry);
        $source = OfficialExamSource::create($data['source'] + ['organization_id' => Tenant::id(), 'created_by' => auth()->id(), 'next_check_at' => now()]);
        $this->syncRules($source, $data['rules']);
        return redirect()->route('official-exam-sources.show', $source)->with('success', 'Official source profile created. Its first automatic check is due now.');
    }

    public function show(OfficialExamSource $officialExamSource)
    {
        $this->owned($officialExamSource);
        $officialExamSource->load(['rules.language']);
        $runs = $officialExamSource->runs()->latest('started_at')->limit(20)->get();
        $discoveries = $officialExamSource->discoveries()->with(['rule:id,name', 'exam:id,name,status', 'sourceImport:id,status'])->latest('last_seen_at')->paginate(30);
        return view('official-exam-sources.show', ['source' => $officialExamSource, 'runs' => $runs, 'discoveries' => $discoveries]);
    }

    public function edit(OfficialExamSource $officialExamSource, SourceExtractorRegistry $registry)
    {
        $this->owned($officialExamSource);
        $officialExamSource->load('rules');
        return view('official-exam-sources.form', $this->formData($registry) + ['source' => $officialExamSource, 'editing' => true]);
    }

    public function update(Request $request, OfficialExamSource $officialExamSource, SourceExtractorRegistry $registry)
    {
        $this->owned($officialExamSource);
        $data = $this->validated($request, $registry, $officialExamSource);
        $officialExamSource->update($data['source'] + ['config_version' => $officialExamSource->config_version + 1, 'next_check_at' => now()]);
        $this->syncRules($officialExamSource, $data['rules']);
        return redirect()->route('official-exam-sources.show', $officialExamSource)->with('success', 'Source profile updated. Previous exams remain pinned to their original profile versions.');
    }

    public function destroy(OfficialExamSource $officialExamSource)
    {
        $this->owned($officialExamSource);
        if ($officialExamSource->discoveries()->exists()) {
            $officialExamSource->update(['enabled' => false]);
            return back()->with('success', 'This profile has history, so it was disabled instead of deleted.');
        }
        $officialExamSource->delete();
        return redirect()->route('official-exam-sources.index')->with('success', 'Official source profile deleted.');
    }

    public function run(OfficialExamSource $officialExamSource, OfficialExamMonitorService $monitor)
    {
        $this->owned($officialExamSource);
        $run = $monitor->check($officialExamSource);
        $message = "Check {$run->status}: {$run->found_count} found, {$run->created_count} created, {$run->skipped_count} skipped, {$run->review_count} need attention.";
        return back()->with($run->status === 'failed' ? 'error' : 'success', $message);
    }

    private function validated(Request $request, SourceExtractorRegistry $registry, ?OfficialExamSource $source = null): array
    {
        $tenant = (int) Tenant::id();
        $data = $request->validate([
            'website_name' => ['required', 'string', 'max:120'], 'name' => ['required', 'string', 'max:160', Rule::unique('official_exam_sources')->where('organization_id', $tenant)->ignore($source?->id)],
            'source_url' => ['required', 'url:http,https', 'max:2048'], 'driver' => ['required', Rule::in(['static', 'form', 'api', 'browser'])],
            'check_interval_minutes' => ['required', 'integer', 'min:15', 'max:43200'], 'enabled' => ['nullable', 'boolean'],
            'automation_mode' => ['required', Rule::in(['queue', 'draft'])],
            'group_id' => ['required', 'integer'], 'category_id' => ['nullable', 'integer'], 'subcategory_id' => ['nullable', 'integer'], 'package_id' => ['nullable', 'integer'], 'language_id' => ['required', 'integer'],
            'duration' => ['required', 'integer', 'min:0', 'max:1440'], 'attempt_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'passing_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'marks' => ['required', 'numeric', 'min:0'], 'negative_marks' => ['required', 'numeric', 'min:0'],
            'roles' => ['required', 'array'], 'roles.*.enabled' => ['nullable', 'boolean'], 'roles.*.selector' => ['nullable', 'string', 'max:1000'], 'roles.*.heading' => ['nullable', 'string', 'max:255'], 'roles.*.pattern' => ['nullable', 'string', 'max:1000'],
            'archive_enabled' => ['nullable', 'boolean'], 'archive_selector' => ['nullable', 'string', 'max:1000'], 'archive_pattern' => ['nullable', 'string', 'max:1000'],
            'archive_max_download_mb' => ['required', 'integer', 'min:1', 'max:2048'], 'archive_max_extracted_mb' => ['required', 'integer', 'min:1', 'max:8192'], 'archive_max_files' => ['required', 'integer', 'min:1', 'max:2000'],
            'dynamic_json' => ['nullable', 'json'], 'api_json' => ['nullable', 'json'], 'browser_json' => ['nullable', 'json'],
            'rules' => ['required', 'array', 'min:1'], 'rules.*.id' => ['nullable', 'integer'], 'rules.*.name' => ['required', 'string', 'max:160'],
            'rules.*.priority' => ['required', 'integer', 'min:1', 'max:100000'], 'rules.*.match_pattern' => ['nullable', 'string', 'max:2000'],
            'rules.*.language_mode' => ['required', Rule::in(['single', 'bilingual', 'separate_languages', 'variants'])], 'rules.*.language_id' => ['nullable', 'integer'],
            'rules.*.extractor_script' => ['required', 'string', 'max:255'], 'rules.*.ready_policy' => ['required', Rule::in(['question_only', 'question_and_answer'])],
            'rules.*.exam_name_template' => ['required', 'string', 'max:255'], 'rules.*.enabled' => ['nullable', 'boolean'], 'rules.*.external_key_template' => ['nullable', 'string', 'max:500'],
        ]);
        $owned = [
            'group_id' => Group::where('organization_id', $tenant)->whereKey($data['group_id'])->exists(),
            'category_id' => empty($data['category_id']) || Category::where('organization_id', $tenant)->whereKey($data['category_id'])->exists(),
            'subcategory_id' => empty($data['subcategory_id']) || Category::where('organization_id', $tenant)->whereKey($data['subcategory_id'])->exists(),
            'package_id' => empty($data['package_id']) || Package::where('organization_id', $tenant)->whereKey($data['package_id'])->exists(),
            'language_id' => Language::enabledForOrganization($tenant)->whereKey($data['language_id'])->exists(),
        ];
        foreach ($owned as $field => $valid) if (! $valid) throw ValidationException::withMessages([$field => 'The selected value does not belong to this organization.']);
        $extractors = $registry->available();
        foreach ($data['rules'] as $index => $rule) {
            if (! isset($extractors[$rule['extractor_script']])) throw ValidationException::withMessages(["rules.{$index}.extractor_script" => 'The selected extractor is unavailable.']);
            if (! empty($rule['language_id']) && ! Language::enabledForOrganization($tenant)->whereKey($rule['language_id'])->exists()) throw ValidationException::withMessages(["rules.{$index}.language_id" => 'The rule language is not enabled for this organization.']);
        }
        $roles = [];
        foreach (['question', 'answer', 'combined'] as $role) $roles[$role] = ['enabled' => (bool) data_get($data, "roles.{$role}.enabled", false), 'selector' => (string) data_get($data, "roles.{$role}.selector", ''), 'heading' => (string) data_get($data, "roles.{$role}.heading", ''), 'pattern' => (string) data_get($data, "roles.{$role}.pattern", '')];
        if (! $roles['question']['enabled'] && ! $roles['combined']['enabled']) throw ValidationException::withMessages(['roles' => 'Enable a question or combined PDF role.']);
        return [
            'source' => [
                'website_name' => $data['website_name'], 'name' => $data['name'], 'source_url' => $data['source_url'], 'driver' => $data['driver'],
                'check_interval_minutes' => $data['check_interval_minutes'], 'enabled' => (bool) ($data['enabled'] ?? false), 'automation_mode' => $data['automation_mode'],
                'discovery_settings' => [
                    'roles' => $roles,
                    'archive' => ['enabled' => (bool) ($data['archive_enabled'] ?? false), 'selector' => $data['archive_selector'] ?? '', 'pattern' => $data['archive_pattern'] ?? '', 'max_download_mb' => $data['archive_max_download_mb'], 'max_extracted_mb' => $data['archive_max_extracted_mb'], 'max_files' => $data['archive_max_files']],
                    'dynamic' => json_decode($data['dynamic_json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
                    'api' => json_decode($data['api_json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
                    'browser' => json_decode($data['browser_json'] ?? '{}', true, 512, JSON_THROW_ON_ERROR),
                ],
                'exam_defaults' => Arr::only($data, ['group_id', 'category_id', 'subcategory_id', 'package_id', 'language_id', 'duration', 'attempt_count', 'passing_percentage', 'marks', 'negative_marks']),
            ],
            'rules' => $data['rules'],
        ];
    }

    private function syncRules(OfficialExamSource $source, array $rules): void
    {
        $kept = [];
        foreach ($rules as $ruleData) {
            $id = (int) ($ruleData['id'] ?? 0);
            $rule = $id ? $source->rules()->whereKey($id)->first() : null;
            $payload = [
                'name' => $ruleData['name'], 'priority' => $ruleData['priority'], 'match_pattern' => $ruleData['match_pattern'] ?? null,
                'language_mode' => $ruleData['language_mode'], 'language_id' => $ruleData['language_id'] ?? null,
                'extractor_script' => $ruleData['extractor_script'], 'ready_policy' => $ruleData['ready_policy'],
                'exam_name_template' => $ruleData['exam_name_template'], 'enabled' => (bool) ($ruleData['enabled'] ?? false),
                'settings' => ['external_key_template' => $ruleData['external_key_template'] ?? null],
            ];
            if ($rule) { $rule->update($payload + ['version' => $rule->version + 1]); }
            else { $rule = $source->rules()->create($payload); }
            $kept[] = $rule->id;
        }
        $source->rules()->whereNotIn('id', $kept)->delete();
    }

    private function formData(SourceExtractorRegistry $registry): array
    {
        $tenant = (int) Tenant::id();
        return [
            'groups' => Group::where('organization_id', $tenant)->orderBy('group_name')->get(),
            'categories' => Category::where('organization_id', $tenant)->whereNull('parent_id')->where('status', 1)->orderBy('title')->get(),
            'subcategories' => Category::where('organization_id', $tenant)->whereNotNull('parent_id')->where('status', 1)->orderBy('title')->get(),
            'packages' => Package::where('organization_id', $tenant)->orderBy('name')->get(),
            'languages' => Language::enabledForOrganization($tenant)->orderBy('name')->get(),
            'extractors' => $registry->available(),
        ];
    }

    private function owned(OfficialExamSource $source): void
    {
        abort_unless((int) $source->organization_id === (int) Tenant::id(), 404);
    }
}
