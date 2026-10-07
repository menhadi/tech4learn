@extends('layouts.master')
@section('title', $editing ? 'Edit Official Source' : 'Add Official Source')
@section('content')
@php
    $discovery = (array) old('discovery_settings', $source->discovery_settings ?? []);
    $defaults = (array) ($source->exam_defaults ?? []);
    $sourceRules = old('rules', $source->exists ? $source->rules->map(fn($r) => $r->toArray() + ['external_key_template' => data_get($r->settings, 'external_key_template')])->all() : [[
        'name' => 'Default paper rule', 'priority' => 100, 'match_pattern' => '', 'language_mode' => 'single', 'language_id' => '',
        'extractor_script' => array_key_first($extractors), 'ready_policy' => 'question_only', 'exam_name_template' => '{detected_name}', 'enabled' => true, 'external_key_template' => ''
    ]]);
@endphp
<div class="container-fluid">
    <div class="mb-4"><a href="{{ $source->exists ? route('official-exam-sources.show', $source) : route('official-exam-sources.index') }}" class="text-muted"><i class="ri-arrow-left-line me-1"></i>Official Exam Monitor</a><h3 class="mt-2 mb-1">{{ $editing ? 'Edit source profile' : 'Add official source profile' }}</h3><p class="text-muted mb-0">Selectors and paper rules are versioned; editing this profile never changes an exam already created.</p></div>
    @if($errors->any())<div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $editing ? route('official-exam-sources.update', $source) : route('official-exam-sources.store') }}">@csrf @if($editing)@method('PUT')@endif
        <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Website and schedule</h5></div><div class="card-body"><div class="row g-3">
            <div class="col-md-4"><label class="form-label">Website name *</label><input class="form-control" name="website_name" value="{{ old('website_name', $source->website_name) }}" placeholder="UPSC" required></div>
            <div class="col-md-4"><label class="form-label">Listing/profile name *</label><input class="form-control" name="name" value="{{ old('name', $source->name) }}" placeholder="UPSC Civil Services papers" required></div>
            <div class="col-md-4"><label class="form-label">Discovery driver *</label><select class="form-select" name="driver">@foreach(['static'=>'Static HTML','form'=>'Server form/dropdowns','api'=>'JSON or HTML API','browser'=>'JavaScript/browser dropdowns'] as $value=>$label)<option value="{{ $value }}" @selected(old('driver', $source->driver ?: 'static') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-8"><label class="form-label">Official page, API, PDF or ZIP URL *</label><input type="url" class="form-control" name="source_url" value="{{ old('source_url', $source->source_url) }}" required></div>
            <div class="col-md-2"><label class="form-label">Check every (minutes)</label><input type="number" class="form-control" name="check_interval_minutes" value="{{ old('check_interval_minutes', $source->check_interval_minutes ?: 360) }}" min="15" required></div>
            <div class="col-md-2"><label class="form-label">After discovery</label><select class="form-select" name="automation_mode"><option value="queue" @selected(old('automation_mode', $source->automation_mode ?: 'draft') === 'queue')>Queue extraction</option><option value="draft" @selected(old('automation_mode', $source->automation_mode ?: 'draft') === 'draft')>Save draft only</option></select></div>
            <div class="col-12"><div class="form-check"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked(old('enabled', $source->exists ? $source->enabled : true))><label class="form-check-label" for="enabled">Enable scheduled checks</label></div></div>
        </div></div></div>

        <div class="card mb-4"><div class="card-header"><h5 class="mb-1">PDF roles and table/section selectors</h5><p class="text-muted mb-0">Use CSS/XPath for exact layouts; headings and filename patterns provide additional filtering.</p></div><div class="card-body">
            @foreach(['question'=>'Question papers','answer'=>'Answer keys','combined'=>'Combined question/solution papers'] as $role=>$label)
            @php $roleData = (array) data_get($source->discovery_settings, 'roles.'.$role, []); @endphp
            <div class="row g-2 align-items-end mb-3 border-bottom pb-3">
                <div class="col-lg-2"><input type="hidden" name="roles[{{ $role }}][enabled]" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="roles[{{ $role }}][enabled]" value="1" id="role-{{ $role }}" @checked(old("roles.$role.enabled", $source->exists ? ($roleData['enabled'] ?? false) : $role === 'question'))><label class="form-check-label fw-semibold" for="role-{{ $role }}">{{ $label }}</label></div></div>
                <div class="col-lg-4"><label class="form-label">CSS selector or XPath</label><input class="form-control" name="roles[{{ $role }}][selector]" value="{{ old("roles.$role.selector", $roleData['selector'] ?? '') }}" placeholder="#results-table or //table[1]"></div>
                <div class="col-lg-3"><label class="form-label">Heading contains</label><input class="form-control" name="roles[{{ $role }}][heading]" value="{{ old("roles.$role.heading", $roleData['heading'] ?? '') }}"></div>
                <div class="col-lg-3"><label class="form-label">Filename/link pattern</label><input class="form-control" name="roles[{{ $role }}][pattern]" value="{{ old("roles.$role.pattern", $roleData['pattern'] ?? '') }}" placeholder="text or /regular expression/i"></div>
            </div>
            @endforeach
        </div></div>

        @php $archive = (array) data_get($source->discovery_settings, 'archive', []); @endphp
        <div class="card mb-4"><div class="card-header"><h5 class="mb-0">ZIP archives</h5></div><div class="card-body"><div class="row g-3 align-items-end">
            <div class="col-md-2"><input type="hidden" name="archive_enabled" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="archive_enabled" value="1" id="archive-enabled" @checked(old('archive_enabled', $archive['enabled'] ?? false))><label class="form-check-label" for="archive-enabled">Discover ZIP files</label></div></div>
            <div class="col-md-4"><label class="form-label">ZIP selector</label><input class="form-control" name="archive_selector" value="{{ old('archive_selector', $archive['selector'] ?? '') }}"></div>
            <div class="col-md-3"><label class="form-label">ZIP link pattern</label><input class="form-control" name="archive_pattern" value="{{ old('archive_pattern', $archive['pattern'] ?? '') }}"></div>
            <div class="col-md-1"><label class="form-label">Download MB</label><input type="number" class="form-control" name="archive_max_download_mb" value="{{ old('archive_max_download_mb', $archive['max_download_mb'] ?? 500) }}" required></div>
            <div class="col-md-1"><label class="form-label">Extract MB</label><input type="number" class="form-control" name="archive_max_extracted_mb" value="{{ old('archive_max_extracted_mb', $archive['max_extracted_mb'] ?? 2048) }}" required></div>
            <div class="col-md-1"><label class="form-label">Max files</label><input type="number" class="form-control" name="archive_max_files" value="{{ old('archive_max_files', $archive['max_files'] ?? 500) }}" required></div>
        </div></div></div>

        <div class="card mb-4"><div class="card-header"><h5 class="mb-1">Dynamic driver configuration</h5><p class="text-muted mb-0">Only the JSON box for the selected driver is used.</p></div><div class="card-body"><div class="row g-3">
            <div class="col-lg-4"><label class="form-label">Form/dropdown JSON</label><textarea class="form-control font-monospace" rows="9" name="dynamic_json">{{ old('dynamic_json', json_encode(data_get($source->discovery_settings, 'dynamic', ['method'=>'GET','year_field'=>'year','exam_field'=>'exam','fields'=>new stdClass(),'max_combinations'=>30]), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) }}</textarea></div>
            <div class="col-lg-4"><label class="form-label">API JSON</label><textarea class="form-control font-monospace" rows="9" name="api_json">{{ old('api_json', json_encode(data_get($source->discovery_settings, 'api', ['method'=>'GET','response_type'=>'json','items_path'=>'data','url_path'=>'url','label_path'=>'label','variants'=>[new stdClass()]]), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) }}</textarea></div>
            <div class="col-lg-4"><label class="form-label">Browser/dropdown JSON</label><textarea class="form-control font-monospace" rows="9" name="browser_json">{{ old('browser_json', json_encode(data_get($source->discovery_settings, 'browser', ['year_selector'=>'#year','exam_selector'=>'#exam','submit_selector'=>'#search','results_selector'=>'#results','max_combinations'=>30]), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) }}</textarea></div>
        </div></div></div>

        <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Defaults for automatically created inactive exams</h5></div><div class="card-body"><div class="row g-3">
            <div class="col-md-3"><label class="form-label">Group *</label><select class="form-select" name="group_id" required><option value="">Select</option>@foreach($groups as $item)<option value="{{ $item->id }}" @selected(old('group_id', $defaults['group_id'] ?? '') == $item->id)>{{ $item->group_name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">None</option>@foreach($categories as $item)<option value="{{ $item->id }}" @selected(old('category_id', $defaults['category_id'] ?? '') == $item->id)>{{ $item->title }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Subcategory</label><select class="form-select" name="subcategory_id"><option value="">None</option>@foreach($subcategories as $item)<option value="{{ $item->id }}" @selected(old('subcategory_id', $defaults['subcategory_id'] ?? '') == $item->id)>{{ $item->title }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Package</label><select class="form-select" name="package_id"><option value="">None</option>@foreach($packages as $item)<option value="{{ $item->id }}" @selected(old('package_id', $defaults['package_id'] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Default language *</label><select class="form-select" name="language_id" required>@foreach($languages as $item)<option value="{{ $item->id }}" @selected(old('language_id', $defaults['language_id'] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            @foreach(['duration'=>'Duration (min)','attempt_count'=>'Attempts','passing_percentage'=>'Pass %','marks'=>'Marks','negative_marks'=>'Negative marks'] as $field=>$label)<div class="col-md-2"><label class="form-label">{{ $label }}</label><input type="number" step="0.01" min="0" class="form-control" name="{{ $field }}" value="{{ old($field, $defaults[$field] ?? ($field === 'duration' ? 180 : ($field === 'marks' ? 1 : 0))) }}" required></div>@endforeach
        </div></div></div>

        <div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center"><div><h5 class="mb-1">Paper classification and extraction rules</h5><p class="text-muted mb-0">Rules are evaluated by priority. Leave the last pattern empty as a fallback.</p></div><button class="btn btn-sm btn-outline-primary" type="button" id="add-rule">Add rule</button></div><div class="card-body" id="rules-container">
            @foreach($sourceRules as $index=>$rule)
            <div class="border rounded p-3 mb-3 rule-row"><input type="hidden" name="rules[{{ $index }}][id]" value="{{ $rule['id'] ?? '' }}"><div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Rule name *</label><input class="form-control" name="rules[{{ $index }}][name]" value="{{ $rule['name'] ?? '' }}" required></div>
                <div class="col-md-1"><label class="form-label">Priority</label><input type="number" class="form-control" name="rules[{{ $index }}][priority]" value="{{ $rule['priority'] ?? 100 }}" required></div>
                <div class="col-md-4"><label class="form-label">Match filename/link</label><input class="form-control" name="rules[{{ $index }}][match_pattern]" value="{{ $rule['match_pattern'] ?? '' }}" placeholder="GATE or /UPSC.*bilingual/i"></div>
                <div class="col-md-2"><label class="form-label">Language mode</label><select class="form-select" name="rules[{{ $index }}][language_mode]">@foreach(['single'=>'Single','bilingual'=>'Bilingual PDF','separate_languages'=>'Separate language PDFs','variants'=>'Separate variants'] as $value=>$label)<option value="{{ $value }}" @selected(($rule['language_mode'] ?? 'single') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Rule language</label><select class="form-select" name="rules[{{ $index }}][language_id]"><option value="">Use default</option>@foreach($languages as $item)<option value="{{ $item->id }}" @selected(($rule['language_id'] ?? '') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Extractor *</label><select class="form-select" name="rules[{{ $index }}][extractor_script]">@foreach($extractors as $value=>$label)<option value="{{ $value }}" @selected(($rule['extractor_script'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Ready when</label><select class="form-select" name="rules[{{ $index }}][ready_policy]"><option value="question_only" @selected(($rule['ready_policy'] ?? '') === 'question_only')>Question available</option><option value="question_and_answer" @selected(($rule['ready_policy'] ?? '') === 'question_and_answer')>Question + answer</option></select></div>
                <div class="col-md-3"><label class="form-label">Exam name template</label><input class="form-control" name="rules[{{ $index }}][exam_name_template]" value="{{ $rule['exam_name_template'] ?? '{detected_name}' }}" required></div>
                <div class="col-md-3"><label class="form-label">Identity template</label><input class="form-control" name="rules[{{ $index }}][external_key_template]" value="{{ $rule['external_key_template'] ?? '' }}" placeholder="Optional: {detected_name}|{year}"></div>
                <div class="col-md-2"><input type="hidden" name="rules[{{ $index }}][enabled]" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="rules[{{ $index }}][enabled]" value="1" @checked($rule['enabled'] ?? true)><label class="form-check-label">Enabled</label></div></div>
                <div class="col-md-1 text-end"><button class="btn btn-outline-danger remove-rule" type="button">Remove</button></div>
            </div></div>
            @endforeach
        </div></div>
        <div class="d-flex justify-content-end gap-2 mb-5"><a class="btn btn-light" href="{{ route('official-exam-sources.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $editing ? 'Save profile' : 'Create profile' }}</button></div>
    </form>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('rules-container');
  let next = container.querySelectorAll('.rule-row').length;
  document.getElementById('add-rule').addEventListener('click', () => {
    const first = container.querySelector('.rule-row');
    const clone = first.cloneNode(true);
    clone.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/rules\[\d+\]/, `rules[${next}]`); if (input.type !== 'checkbox' && input.tagName !== 'SELECT') input.value = ''; });
    clone.querySelector('input[name$="[priority]"]').value = String(100 + next);
    clone.querySelector('input[name$="[exam_name_template]"]').value = '{detected_name}';
    clone.querySelector('input[name$="[id]"]').value = '';
    container.appendChild(clone); next++;
  });
  container.addEventListener('click', event => { if (event.target.matches('.remove-rule') && container.querySelectorAll('.rule-row').length > 1) event.target.closest('.rule-row').remove(); });
});
</script>
@endpush
@endsection
