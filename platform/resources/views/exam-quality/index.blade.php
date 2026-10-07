@extends('layouts.master')
@section('title', 'Exam Quality Audit')
@section('content')
    <style>
        .audit-stat-card { border: 0; border-left: 4px solid var(--vz-primary); }
        .audit-stat-card.is-warning { border-left-color: var(--vz-warning); }
        .audit-stat-card .audit-stat-icon { color: var(--vz-primary); background: rgba(var(--vz-primary-rgb), 0.1); }
        .audit-stat-card.is-warning .audit-stat-icon { color: var(--vz-warning); background: rgba(var(--vz-warning-rgb), 0.12); }
        .audit-exam-field .select2-container { width: 100% !important; }
        .audit-exam-field .select2-container--default .select2-selection--multiple { min-height: 58px; padding: 7px 10px; }
        .audit-exam-result { padding: 4px 2px; line-height: 1.35; }
        .audit-exam-result__title { color: var(--vz-body-color); font-weight: 600; white-space: normal; }
        .audit-exam-result__meta { color: var(--vz-secondary-color); font-size: .78rem; margin-top: 3px; white-space: normal; }
        .audit-exam-field .select2-selection__choice { max-width: calc(100% - 8px); overflow: hidden; text-overflow: ellipsis; }
        .audit-exam-dropdown .select2-results__option--highlighted[aria-selected],
        .audit-exam-dropdown .select2-results__option--highlighted[data-selected] { background-color: #405189 !important; color: #fff !important; }
        .audit-exam-dropdown .select2-results__option--highlighted .audit-exam-result__title,
        .audit-exam-dropdown .select2-results__option--highlighted .audit-exam-result__meta { color: #fff !important; }
    </style>
    <div class="row g-3 mb-4">
        @foreach([
            ['Total Audits', $auditStats['total'], 'ri-shield-check-line', false],
            ['Running', $auditStats['running'], 'ri-loader-4-line', false],
            ['Open Findings', $auditStats['open_findings'], 'ri-error-warning-line', true],
            ['Resolved Findings', $auditStats['resolved_findings'], 'ri-checkbox-circle-line', false],
        ] as [$label, $value, $icon, $warning])
            <div class="col-xl-3 col-md-6">
                <div class="card audit-stat-card {{ $warning ? 'is-warning' : '' }} h-100 mb-0">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div><p class="text-muted mb-1">{{ $label }}</p><h2 class="mb-0">{{ $value }}</h2></div>
                        <span class="avatar-md audit-stat-icon rounded-circle d-inline-flex align-items-center justify-content-center fs-2"><i class="{{ $icon }}"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="row"><div class="col-12"><div class="card">
        <div class="card-header">
            <h4 class="card-title mb-1">Exam Quality Audit</h4>
            <p class="text-muted mb-0">Compare stored questions, options, images, formulas and answers with their private original sources, then run structural, browser and academic checks.</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('exam-quality.store') }}" id="audit-create-form" class="row g-3 align-items-end">@csrf
                <div class="col-md-4">
                    <label class="form-label">Group</label>
                    <select id="audit-group" name="group_id" class="form-select"><option value="">All groups</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select id="audit-category" name="category_id" class="form-select"><option value="">All categories</option></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Package</label>
                    <select id="audit-package" name="package_id" class="form-select"><option value="">All packages</option></select>
                </div>
                <div class="col-12 audit-exam-field">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                        <label class="form-label mb-0" for="audit-exam-search">Search exams</label>
                        <span class="badge bg-light text-dark border">Maximum 30 shown at a time</span>
                    </div>
                    <input id="audit-exam-search" type="search" class="form-control mb-2" autocomplete="off" placeholder="Type an exam name to search within the selected Group, Category and Package">
                    <label class="form-label" for="audit-exam">Matching exams <span class="text-muted fw-normal">(repeat audits allowed)</span></label>
                    <select id="audit-exam" name="exam_ids[]" class="form-select" multiple data-placeholder="Select one or more matching exams">
                        @foreach($availableExams as $availableExam)
                            <option value="{{ $availableExam->id }}" @selected($requestedExamIds->contains((int) $availableExam->id))>{{ $availableExam->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" id="audit-match-count">Select multiple exams, or leave this empty to audit all papers matching the selected hierarchy.</div>
                </div>

                <div class="col-12">
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="profile_enabled" value="1" id="source-profile-enabled" @checked(old('profile_enabled'))>
                                    <label class="form-check-label fw-semibold" for="source-profile-enabled">Use optional source processing profile</label>
                                </div>
                                <div class="small text-muted">Leave this off for normal papers. Existing automatic audit behavior remains unchanged.</div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" type="button" id="source-profile-toggle"><i class="ri-settings-3-line me-1"></i>Configure profile / sample</button>
                        </div>
                        <div id="source-profile-panel" class="mt-3 {{ old('profile_enabled') || old('sample_mode') ? '' : 'd-none' }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Reusable preset</label>
                                    <select name="profile_id" id="source-profile-preset" class="form-select">
                                        <option value="">Custom profile</option>
                                        @foreach($sourceProfiles as $profile)<option value="{{ $profile->id }}" @selected((int) old('profile_id') === (int) $profile->id)>{{ $profile->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-md-3"><label class="form-label">PDF type</label><select name="source_type" id="profile-source-type" class="form-select"><option value="auto">Auto detect</option><option value="digital">Digital PDF</option><option value="scanned">Scanned PDF</option><option value="mixed">Mixed digital/scanned</option></select></div>
                                <div class="col-md-3"><label class="form-label">Layout</label><select name="source_layout" id="profile-layout" class="form-select"><option value="auto">Auto detect</option><option value="single_column">Single column</option><option value="two_column">Two columns</option><option value="bilingual_side_by_side">Bilingual side-by-side</option></select></div>
                                <div class="col-md-3"><label class="form-label">Language to audit</label><input name="audit_language" id="profile-language" value="{{ old('audit_language') }}" class="form-control" placeholder="e.g. English"></div>
                                <div class="col-md-3"><label class="form-label">Ignore language(s)</label><input name="ignored_languages" id="profile-ignore-languages" value="{{ old('ignored_languages') }}" class="form-control" placeholder="e.g. Hindi, Bengali"></div>
                                <div class="col-md-3"><label class="form-label">Reading order</label><select name="reading_order" id="profile-reading-order" class="form-select"><option value="auto">Auto detect</option><option value="rows">Across rows</option><option value="columns_ltr">Columns, left to right</option><option value="columns_rtl">Columns, right to left</option></select></div>
                                <div class="col-md-3"><label class="form-label">Question numbering</label><select name="question_numbering" id="profile-numbering" class="form-select"><option value="auto">Auto detect</option><option value="shared">Shared across languages</option><option value="continuous">Continuous</option><option value="restarts">May restart by section</option></select></div>
                                <div class="col-md-3"><label class="form-label">Answer source</label><select name="answer_source" id="profile-answer-source" class="form-select"><option value="auto">Auto detect</option><option value="combined">Combined question/answer PDF</option><option value="separate">Separate answer PDF</option></select></div>
                                <div class="col-md-3 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="hidden" name="enable_ocr" value="0"><input class="form-check-input" type="checkbox" name="enable_ocr" id="profile-enable-ocr" value="1" @checked(old('enable_ocr'))><span class="form-check-label">Use OCR/vision for scans</span></label></div>
                                <div class="col-12"><label class="form-label">Additional source instructions</label><textarea name="additional_instructions" id="profile-instructions" class="form-control" rows="2" maxlength="2000" placeholder="Example: Hindi is on the left and English is on the right. Audit English only. Ignore publisher logos and watermarks.">{{ old('additional_instructions') }}</textarea></div>
                                <div class="col-md-4"><label class="form-check mb-2"><input type="hidden" name="save_profile" value="0"><input class="form-check-input" type="checkbox" name="save_profile" value="1" id="save-source-profile" @checked(old('save_profile'))><span class="form-check-label">Save as reusable preset</span></label><input name="profile_name" id="source-profile-name" value="{{ old('profile_name') }}" class="form-control" placeholder="Preset name" {{ old('save_profile') ? '' : 'disabled' }}></div>
                                <div class="col-md-8">
                                    <label class="form-check mb-2"><input type="hidden" name="sample_mode" value="0"><input class="form-check-input" type="checkbox" name="sample_mode" value="1" id="sample-audit-enabled" @checked(old('sample_mode'))><span class="form-check-label fw-semibold">Run a sample audit first</span></label>
                                    <input name="sample_questions" id="sample-question-numbers" value="{{ old('sample_questions') }}" class="form-control" placeholder="Paper question numbers: 1-10, 19, 25-30" {{ old('sample_mode') ? '' : 'disabled' }}>
                                    <div class="form-text">Uses the already attached source PDF; no separate sample upload is required.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12"><div class="alert alert-info py-2 mb-0">One audit run prepares every draft. Source Text, Image Extraction and Academic Review each use their own Admin AI Settings priority. Text and academic work can use DeepSeek; the image lane automatically skips text-only providers.</div></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Question limit</label><input type="number" min="1" max="{{ $auditLimits['questions'] ?? 5000 }}" name="question_limit" value="{{ old('question_limit') }}" class="form-control" placeholder="All"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Source comparison limit</label><input type="number" min="1" max="{{ $auditLimits['source'] ?? 100 }}" name="source_limit" value="{{ old('source_limit', $auditLimits['source'] ?? 100) }}" class="form-control" {{ $auditEntitlements['source'] ? '' : 'disabled' }}></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Image audit limit</label><input type="number" min="1" max="{{ $auditLimits['source'] ?? 100 }}" name="image_limit" value="{{ old('image_limit', $auditLimits['source'] ?? 100) }}" class="form-control" {{ $auditEntitlements['source'] ? '' : 'disabled' }}></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">AI review limit</label><input type="number" min="1" max="{{ $auditLimits['ai'] ?? 100 }}" name="ai_limit" value="{{ old('ai_limit', $auditLimits['ai'] ?? 100) }}" class="form-control" {{ $auditEntitlements['ai'] ? '' : 'disabled' }}></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label">Visual check limit</label><input type="number" min="1" max="{{ $auditLimits['visual'] ?? 100 }}" name="visual_limit" value="{{ old('visual_limit', min(30, $auditLimits['visual'] ?? 30)) }}" class="form-control" {{ $auditEntitlements['visual'] ? '' : 'disabled' }}></div>

                <div class="col-12 d-flex flex-wrap gap-4">
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="audit-include-source" name="include_source" value="1" @checked(old('include_source')) {{ $auditEntitlements['source'] ? '' : 'disabled' }}><span class="form-check-label">Source text fidelity (local OCR + selected text API)</span></label>
                    <label class="form-check form-switch"><input type="hidden" name="include_image_audit" value="0"><input class="form-check-input" type="checkbox" name="include_image_audit" value="1" @checked(old('include_image_audit', $auditEntitlements['source'])) {{ $auditEntitlements['source'] ? '' : 'disabled' }}><span class="form-check-label">Image audit & automatic PDF extraction (recommended)</span></label>
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="audit-include-ai" name="include_ai" value="1" @checked(old('include_ai')) {{ $auditEntitlements['ai'] ? '' : 'disabled' }}><span class="form-check-label">Separate academic correctness reviewer</span></label>
                    <label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="include_visual" value="1" @checked(old('include_visual')) {{ $auditEntitlements['visual'] ? '' : 'disabled' }}><span class="form-check-label">Browser visual bot</span></label>
                    <span class="badge bg-light text-dark border px-3 py-2" title="Automatically checks MathJax, mhchem, formulas and chemistry rendering in every audit."><i class="ri-checkbox-circle-line text-success me-1"></i> MathJax & Chemistry Rule Bot: always on</span>
                </div>
                <div class="col-12">
                    <div class="border-top pt-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                        <span class="small text-muted">One run audits the paper and prepares safe drafts for administrator review. Nothing is published automatically.</span>
                        <button class="btn btn-primary px-4 flex-shrink-0"><i class="ri-play-circle-line me-1"></i> Start Audit &amp; Prepare Repairs</button>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge bg-light text-dark">Audits this month: {{ $auditLimits['audits_used'] }}{{ $auditLimits['audits_monthly'] !== null ? ' / '.$auditLimits['audits_monthly'] : '' }}</span>
                <span class="badge bg-light text-dark">AI repairs this month: {{ $auditLimits['repairs_used'] }}{{ $auditLimits['repairs_monthly'] !== null ? ' / '.$auditLimits['repairs_monthly'] : '' }}</span>
                @foreach(['source' => 'Source comparison', 'visual' => 'Browser bot', 'ai' => 'AI review & repair'] as $key => $label)
                    <span class="badge bg-{{ $auditEntitlements[$key] ? 'success' : 'secondary' }}">{{ $label }}: {{ $auditEntitlements[$key] ? 'Included' : 'Not in plan' }}</span>
                @endforeach
            </div>
            <div class="alert alert-light border mt-4 mb-0">
                <strong>Private one-pass workflow:</strong> Local PDF extraction/OCR supplies source text to the selected text provider. The optional image bot inspects the paper once, returns all tight crop coordinates, and ExamElite extracts the images locally. Findings and repair drafts are created together; nothing changes live until an administrator publishes.
                <span id="audit-source-note" class="d-block mt-2 text-muted"></span>
            </div>
        </div>
    </div></div></div>

    <form method="POST" action="{{ route('exam-quality.papers.publish') }}" id="audit-history-form">@csrf<div class="card"><div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><div><h5 class="mb-0">Audit History</h5><div class="small text-muted">Use View to review a paper's questions and repair drafts. Select papers only when publishing prepared changes.</div></div><div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-success audit-selection-action" formaction="{{ route('exam-quality.papers.publish') }}" data-swal-confirm="Administrator override: publish all prepared changes in the selected papers even if warnings or errors remain? Each paper will create its own reversible release." data-swal-title="Publish selected papers?" disabled><i class="ri-check-double-line me-1"></i>Publish selected papers</button><a class="btn btn-sm btn-outline-primary" href="{{ route('exam-quality.releases.index') }}"><i class="ri-history-line me-1"></i>Version history & restore</a></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th style="width:48px"><input class="form-check-input" type="checkbox" id="audit-select-all" aria-label="Select all audits"></th><th>Exam</th><th>Status</th><th>Progress</th><th>Findings</th><th>Requested</th><th></th></tr></thead><tbody>
    @forelse($audits as $audit)<tr><td><input class="form-check-input audit-select" type="checkbox" name="audit_ids[]" value="{{ $audit->id }}" aria-label="Select {{ $audit->exam?->name }}"></td><td><strong>{{ $audit->exam?->name }}</strong><div class="text-muted small">Rules @if($audit->include_source) &middot; Source @endif @if($audit->include_ai) &middot; AI @endif @if($audit->include_visual) &middot; Browser @endif @if(data_get($audit->options,'source_profile')) &middot; Profile @endif @if(data_get($audit->options,'sample_mode')) &middot; Sample {{ data_get($audit->options,'sample_questions') }} @endif</div></td><td><span class="badge bg-{{ $audit->status === 'completed' ? 'success' : ($audit->status === 'failed' ? 'danger' : ($audit->status === 'cancelled' ? 'secondary' : 'warning')) }}">{{ ucfirst(str_replace('_',' ',$audit->status)) }}</span></td><td>{{ $audit->checked_questions }} / {{ $audit->total_questions ?: '—' }}</td><td><span class="text-danger">{{ $audit->error_count }} errors</span> &middot; <span class="text-warning">{{ $audit->warning_count }} warnings</span></td><td>{{ $audit->created_at->format('d M Y H:i') }}<div class="small text-muted">{{ $audit->requester?->name }}</div></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('exam-quality.show', $audit) }}">View</a>@if(in_array($audit->status,['queued','starting','running']))<button class="btn btn-sm btn-outline-danger" formaction="{{ route('exam-quality.stop',$audit) }}" data-swal-confirm="Stop this audit? The current API request may finish, but no new paid batch will start."><i class="ri-stop-circle-line"></i> Stop</button>@endif</div></td></tr>
    @empty<tr><td colspan="7" class="text-center text-muted py-5">No audits have been run yet.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer">{{ $audits->links() }}</div></div></form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const auditCheckboxes = Array.from(document.querySelectorAll('.audit-select'));
    const auditSelectAll = document.getElementById('audit-select-all');
    const syncAuditActions = () => {
        const selected = auditCheckboxes.filter(input => input.checked).length;
        document.querySelectorAll('.audit-selection-action').forEach(button => button.disabled = selected === 0);
        if (auditSelectAll) {
            auditSelectAll.checked = selected > 0 && selected === auditCheckboxes.length;
            auditSelectAll.indeterminate = selected > 0 && selected < auditCheckboxes.length;
        }
    };
    auditSelectAll?.addEventListener('change', event => {
        auditCheckboxes.forEach(input => input.checked = event.target.checked);
        syncAuditActions();
    });
    auditCheckboxes.forEach(input => input.addEventListener('change', syncAuditActions));
    syncAuditActions();
    const categories = @json($categoryOptions);
    const packages = @json($packageOptions);
    const exams = @json($examOptions);
    const groupSelect = document.getElementById('audit-group');
    const categorySelect = document.getElementById('audit-category');
    const packageSelect = document.getElementById('audit-package');
    const examSelect = document.getElementById('audit-exam');
    const sourceNote = document.getElementById('audit-source-note');
    const matchCount = document.getElementById('audit-match-count');
    const examSearchInput = document.getElementById('audit-exam-search');
    const examSearchUrl = @json(route('exam-quality.exams.search'));
    let examSearchSequence = 0;
    let matchingExamCount = 0;
    const sourceProfiles = @json($sourceProfileOptions);
    const profileEnabled = document.getElementById('source-profile-enabled');
    const profilePanel = document.getElementById('source-profile-panel');
    const profilePreset = document.getElementById('source-profile-preset');
    const sampleEnabled = document.getElementById('sample-audit-enabled');
    const sampleNumbers = document.getElementById('sample-question-numbers');
    const saveProfile = document.getElementById('save-source-profile');
    const profileName = document.getElementById('source-profile-name');

    const numberValue = element => element.value ? Number(element.value) : null;
    const intersects = (values, selected) => !selected || (values || []).map(Number).includes(selected);
    const selectedExamIds = () => Array.from(examSelect.selectedOptions).map(entry => Number(entry.value)).filter(Boolean);
    const examById = id => exams.find(row => Number(row.id) === Number(id));
    const examMeta = exam => {
        const sources = (exam.source_details || []).map(source => `${source.role}: ${source.name}`).join(' · ');
        const origin = exam.source_imported ? 'Created from source' : null;
        return [origin, `${exam.question_count} questions`, `${exam.source_count} PDF/source${exam.source_count === 1 ? '' : 's'}`, sources]
            .filter(Boolean).join(' · ');
    };
    const renderExamResult = item => {
        if (!item.id) return item.text;
        const exam = examById(item.id);
        if (!exam || !window.jQuery) return item.text;
        const wrapper = document.createElement('div');
        wrapper.className = 'audit-exam-result';
        const title = document.createElement('div');
        title.className = 'audit-exam-result__title';
        title.textContent = exam.name;
        const meta = document.createElement('div');
        meta.className = 'audit-exam-result__meta';
        meta.textContent = examMeta(exam);
        wrapper.append(title, meta);
        return window.jQuery(wrapper);
    };
    const option = (value, label) => new Option(label, value);
    const refill = (select, firstLabel, rows, labelKey, selectedValue = null) => {
        select.replaceChildren(option('', firstLabel));
        rows.forEach(row => select.add(option(row.id, row[labelKey])));
        if (selectedValue && rows.some(row => Number(row.id) === Number(selectedValue))) select.value = selectedValue;
    };

    function syncCategories() {
        const groupId = numberValue(groupSelect);
        const previous = numberValue(categorySelect);
        const rows = categories.filter(category => !category.parent_id && intersects(category.group_ids, groupId));
        refill(categorySelect, 'All categories', rows, 'title', previous);
        syncPackages();
    }

    function syncPackages() {
        const groupId = numberValue(groupSelect);
        const categoryId = numberValue(categorySelect);
        const previous = numberValue(packageSelect);
        const rows = packages.filter(pkg => intersects(pkg.group_ids, groupId) && (!categoryId || Number(pkg.category_id) === categoryId));
        refill(packageSelect, 'All packages', rows, 'name', previous);
        syncExams();
    }

    function updateExamSummary() {
        const selectedCount = selectedExamIds().length;
        const shownCount = Math.min(30, matchingExamCount);
        if (selectedCount) {
            matchCount.textContent = `${selectedCount} selected paper${selectedCount === 1 ? '' : 's'}; ${shownCount} of ${matchingExamCount} matching papers shown.`;
        } else if (matchingExamCount) {
            matchCount.textContent = `Showing ${shownCount} of ${matchingExamCount} matching papers. Type above to narrow the results.`;
        } else {
            matchCount.textContent = 'No exams match this hierarchy and search text.';
        }
        updateSourceNote();
    }

    function clearExamSelection() {
        examSelect.replaceChildren();
        matchingExamCount = 0;
        if (window.jQuery && window.jQuery.fn.select2) window.jQuery(examSelect).val(null).trigger('change.select2');
    }

    async function syncExams(term = examSearchInput.value.trim()) {
        const requestId = ++examSearchSequence;
        const params = new URLSearchParams({q: term, page: '1'});
        if (groupSelect.value) params.set('group_id', groupSelect.value);
        if (categorySelect.value) params.set('category_id', categorySelect.value);
        if (packageSelect.value) params.set('package_id', packageSelect.value);
        const selected = new Map(Array.from(examSelect.selectedOptions).map(entry => [Number(entry.value), entry.textContent]));
        matchCount.textContent = 'Loading matching exams...';
        try {
            const response = await fetch(`${examSearchUrl}?${params.toString()}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('The matching exams could not be loaded.');
            const payload = await response.json();
            if (requestId !== examSearchSequence) return;
            const rows = payload.results || [];
            matchingExamCount = Number(payload.count || 0);
            rows.forEach(item => {
                const existing = examById(item.id);
                if (existing) Object.assign(existing, item);
                else exams.push(item);
            });
            examSelect.replaceChildren();
            selected.forEach((label, id) => {
                const entry = option(id, label);
                entry.selected = true;
                examSelect.add(entry);
            });
            rows.forEach(exam => {
                if (selected.has(Number(exam.id))) return;
                const entry = option(exam.id, `${exam.name} - ${examMeta(exam)}`);
                entry.title = `${exam.name} - ${examMeta(exam)}`;
                examSelect.add(entry);
            });
            if (window.jQuery && window.jQuery.fn.select2) window.jQuery(examSelect).trigger('change.select2');
            updateExamSummary();
        } catch (error) {
            if (requestId === examSearchSequence) matchCount.textContent = error.message || 'The matching exams could not be loaded.';
        }
    }
    function updateSourceNote() {
        const ids = selectedExamIds();
        const exam = ids.length === 1 ? exams.find(row => Number(row.id) === ids[0]) : null;
        if (ids.length > 1) {
            sourceNote.textContent = `${ids.length} selected papers will each use their own attached private PDF sources.`;
            return;
        }
        if (!exam) {
            const groupId = numberValue(groupSelect), categoryId = numberValue(categorySelect), packageId = numberValue(packageSelect);
            sourceNote.textContent = (groupId || categoryId || packageId)
                ? 'All matching papers will use their own attached private PDF sources.'
                : '';
            return;
        }
        sourceNote.textContent = exam.source_count
            ? `${exam.source_count} private PDF source(s) attached. The attached PDF is the only authority for source text and images.`
            : 'No private PDF source is attached yet. Add it from Exam Edit before enabling source comparison or image extraction.';
    }

    const profileFields = {
        source_type: document.getElementById('profile-source-type'),
        layout: document.getElementById('profile-layout'),
        audit_language: document.getElementById('profile-language'),
        ignored_languages: document.getElementById('profile-ignore-languages'),
        reading_order: document.getElementById('profile-reading-order'),
        question_numbering: document.getElementById('profile-numbering'),
        answer_source: document.getElementById('profile-answer-source'),
        additional_instructions: document.getElementById('profile-instructions'),
    };
    profileFields.source_type.value = @json(old('source_type', 'auto'));
    profileFields.layout.value = @json(old('source_layout', 'auto'));
    profileFields.reading_order.value = @json(old('reading_order', 'auto'));
    profileFields.question_numbering.value = @json(old('question_numbering', 'auto'));
    profileFields.answer_source.value = @json(old('answer_source', 'auto'));
    const syncProfilePanel = () => profilePanel.classList.toggle('d-none', !profileEnabled.checked && !sampleEnabled.checked);
    document.getElementById('source-profile-toggle')?.addEventListener('click', () => {
        profilePanel.classList.toggle('d-none');
        if (!profilePanel.classList.contains('d-none')) profileEnabled.focus();
    });
    profileEnabled?.addEventListener('change', () => {
        if (profileEnabled.checked) {
            const sourceToggle = document.getElementById('audit-include-source');
            if (sourceToggle && !sourceToggle.disabled) sourceToggle.checked = true;
        }
        syncProfilePanel();
    });
    sampleEnabled?.addEventListener('change', () => {
        sampleNumbers.disabled = !sampleEnabled.checked;
        syncProfilePanel();
    });
    saveProfile?.addEventListener('change', () => profileName.disabled = !saveProfile.checked);
    profilePreset?.addEventListener('change', () => {
        const settings = sourceProfiles[String(profilePreset.value)] || null;
        if (!settings) return;
        Object.entries(profileFields).forEach(([key, field]) => {
            if (settings[key] !== undefined && settings[key] !== null) field.value = settings[key];
        });
        document.getElementById('profile-enable-ocr').checked = Boolean(settings.enable_ocr);
        profileEnabled.checked = true;
        syncProfilePanel();
    });

    groupSelect.addEventListener('change', () => { clearExamSelection(); syncCategories(); });
    categorySelect.addEventListener('change', () => { clearExamSelection(); syncPackages(); });
    packageSelect.addEventListener('change', () => { clearExamSelection(); syncExams(); });
    examSelect.addEventListener('change', updateExamSummary);
    let examSearchTimer = null;
    examSearchInput.addEventListener('input', () => {
        clearTimeout(examSearchTimer);
        examSearchTimer = setTimeout(() => syncExams(), 250);
    });
    const auditForm = document.getElementById('audit-create-form');
    auditForm?.addEventListener('submit', async event => {
        const selectedCount = selectedExamIds().length;
        const hasHierarchyScope = groupSelect.value || categorySelect.value || packageSelect.value;
        if (!hasHierarchyScope && selectedCount === 0) {
            event.preventDefault();
            await Swal.fire({icon:'warning', title:'Choose audit scope', text:'Select a group, category, package or one or more exams.', confirmButtonColor:'#087f73'});
            return;
        }
        if (hasHierarchyScope && selectedCount === 0 && auditForm.dataset.bulkConfirmed !== '1') {
            event.preventDefault();
            const result = await Swal.fire({
                icon: 'warning', title: 'Queue every matching paper?',
                text: 'No individual exams are selected. This will create one audit for every published paper in the selected hierarchy.',
                showCancelButton: true, confirmButtonText: 'Queue all matching papers', confirmButtonColor: '#087f73'
            });
            if (result.isConfirmed) {
                auditForm.dataset.bulkConfirmed = '1';
                auditForm.requestSubmit();
            }
        }
    });
    if (window.jQuery && window.jQuery.fn.select2) {
        const $examSelect = window.jQuery(examSelect);
        if ($examSelect.hasClass('select2-hidden-accessible')) $examSelect.select2('destroy');
        $examSelect.select2({
            width: '100%',
            closeOnSelect: false,
            placeholder: 'Search and select one or more exams',
            minimumInputLength: 0,
            ajax: {
                url: @json(route('exam-quality.exams.search')),
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term || examSearchInput.value.trim(),
                    page: params.page || 1,
                    group_id: groupSelect.value || undefined,
                    category_id: categorySelect.value || undefined,
                    package_id: packageSelect.value || undefined,
                }),
                processResults: (response, params) => {
                    params.page = params.page || 1;
                    (response.results || []).forEach(item => {
                        const existing = examById(item.id);
                        if (existing) Object.assign(existing, item);
                        else exams.push(item);
                    });
                    matchingExamCount = Number(response.count || 0);
                    updateExamSummary();
                    return {
                        results: response.results || [],
                        pagination: {more: Boolean(response.pagination?.more)},
                    };
                },
            },
            dropdownCssClass: 'audit-exam-dropdown',
            templateResult: renderExamResult,
            templateSelection: item => examById(item.id)?.name || item.text,
        });
    }
    syncCategories();
});
</script>
@endsection