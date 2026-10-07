@extends('layouts.master')
@section('title', 'AI Answers & Explanations')
@section('content')
@component('components.breadcrumb')
@slot('li_1', 'AI Tools')
@slot('title', 'AI Answers & Explanations')
@endcomponent

<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Create answer and explanation drafts</h4></div>
    <div class="card-body">
        <div class="alert alert-info">
            AI fills missing answers according to the question type and writes a complete explanation for every question.
            Official answers verified in the exam editor are preserved. Unverified saved answers are independently solved and checked. Subjects are preserved; topics and subtopics are classified within the assigned subject and group/category, and difficulty is assessed from the question.
            Equations and chemical formulas use LaTeX/MathJax. Required answer, explanation, topic, subtopic, and difficulty fields must be complete before publication.
            Nothing changes on a live question until an administrator explicitly publishes an approved draft.
        </div>
        <form method="POST" action="{{ route('ai-answers.store') }}" id="ai-answer-create-form" data-disable-ai-inline>
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Group</label>
                    <select id="ai-answer-group" name="group_id" class="form-select"><option value="">All groups</option>
                        @foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ is_array($group->group_name) ? collect($group->group_name)->filter()->first() : $group->group_name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select id="ai-answer-category" name="category_id" class="form-select"><option value="">All categories</option></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Package</label>
                    <select id="ai-answer-package" name="package_id" class="form-select"><option value="">All packages</option></select>
                </div>
                <div class="col-12 ai-answer-exam-field">
                    <label class="form-label d-block">Exams <span class="text-muted">(optional)</span></label>
                    <div class="form-text mb-2">Leave all papers unselected to process the complete group, category, or package. Select papers below to restrict the job.</div>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-8"><input id="ai-answer-exam-search" type="search" class="form-control" placeholder="Filter exams by name"></div>
                        <div class="col-md-4 text-md-end"><span id="ai-answer-exam-count" class="text-muted">Choose a hierarchy to load exams.</span></div>
                    </div>
                    <div class="border rounded bg-light p-2">
                        <label class="form-check mb-2 px-2"><input id="ai-answer-select-visible" type="checkbox" class="form-check-input"> <span class="form-check-label fw-semibold">Select all displayed exams</span></label>
                        <div id="ai-answer-exam-list" class="bg-white border rounded p-2" style="max-height:320px;overflow:auto"><div class="text-muted p-3">Choose a hierarchy or type an exam name.</div></div>
                        <button id="ai-answer-load-more" type="button" class="btn btn-sm btn-light mt-2 d-none">Load more exams</button>
                    </div>
                    <div id="ai-answer-selected-exams"></div>
                    <div class="form-text">Checked papers are retained while searching or loading more results.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Specific question IDs or codes <span class="text-muted">(optional)</span></label>
                    <textarea name="question_identifiers" class="form-control" rows="3" placeholder="337631, EWQ-0000337632">{{ old('question_identifiers') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Additional instructions <span class="text-muted">(optional)</span></label>
                    <textarea name="additional_instructions" class="form-control" rows="3" maxlength="3000" placeholder="For example: use SI units; explain using the shortest valid method.">{{ old('additional_instructions') }}</textarea>
                    <div class="form-text">Use this for paper-specific requirements such as the expected method, notation, units, syllabus level, or explanation length. Clear, focused instructions improve consistency; the standard student-facing solution rules still apply.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">AI models</label>
                    <div class="form-control-plaintext">Uses AI Settings for model selection and provider priority.</div>
                    <div class="form-text">Questions with images use the configured vision model. No model override is applied here.</div>
                </div>

                <div class="col-12 text-end">
                    <button class="btn btn-primary" data-swal-confirm="Create versioned AI drafts for every matching question? Live questions will remain unchanged." data-swal-title="Start AI drafting" data-swal-button="Start processing">
                        <i class="ri-magic-line me-1"></i> Generate drafts
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('ai-answers.papers.publish') }}" id="publish-ai-answer-papers">
    @csrf
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
        <h4 class="card-title mb-0">Recent paper jobs</h4>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <label class="form-check mb-0">
                <input type="checkbox" id="select-ready-papers" class="form-check-input">
                <span class="form-check-label">Select all publishable papers on this page</span>
            </label>
            <button id="publish-selected-papers" class="btn btn-primary" disabled data-swal-confirm="Publish all ready drafts and explicitly approve discrepancy proposals from the selected papers? A restorable version is recorded before each change." data-swal-title="Publish selected papers" data-swal-button="Publish">
                <i class="ri-check-double-line me-1"></i> Publish selected papers
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th style="width:48px"></th><th>Paper</th><th>Provider</th><th>Status</th><th>Progress</th><th>Ready</th><th>Discrepancies</th><th>New curriculum</th><th></th></tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td><input class="form-check-input ready-paper-checkbox" type="checkbox" name="run_ids[]" value="{{ $run->id }}" @disabled(($run->ready_count + $run->discrepancy_count) < 1) aria-label="Select {{ $run->exam?->name }}"></td>
                    <td><strong>{{ $run->exam?->name }}</strong><div class="text-muted small">{{ $run->created_at?->format('d M Y H:i') }}</div></td>
                    <td>{{ $run->provider === 'auto' ? 'Admin priority' : ucfirst($run->provider) }}</td>
                    <td><span class="badge bg-{{ in_array($run->status, ['completed']) ? 'success' : (in_array($run->status, ['failed']) ? 'danger' : 'warning') }}">{{ ucfirst($run->status) }}</span></td>
                    <td>{{ $run->processed_questions }}/{{ $run->total_questions }}</td>
                    <td>{{ $run->ready_count }}</td><td>{{ $run->discrepancy_count }}</td>
                    <td class="small">
                        <span title="Subjects">S {{ $run->new_subjects_count }}</span>
                        <span class="text-muted mx-1">&middot;</span>
                        <span title="Topics">T {{ $run->new_topics_count }}</span>
                        <span class="text-muted mx-1">&middot;</span>
                        <span title="Subtopics">ST {{ $run->new_subtopics_count }}</span>
                    </td>
                    <td><a href="{{ route('ai-answers.show', $run) }}" class="btn btn-sm btn-primary">Review</a></td>
                </tr>
            @empty<tr><td colspan="9" class="text-center text-muted py-4">No answer-generation jobs yet.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($runs->hasPages())<div class="card-footer">{{ $runs->links() }}</div>@endif
</div>
</form>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-1">Curriculum records created by AI publication</h4>
        <div class="text-muted small">Permanent audit of subjects, topics, and subtopics created when an administrator publishes a draft.</div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Type</th><th>Name</th><th>Paper</th><th>Published by</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($recentCurriculumEvents as $event)
                <tr>
                    <td><span class="badge bg-info text-dark">{{ ucfirst($event->entity_type) }}</span></td>
                    <td><strong>{{ $event->name }}</strong></td>
                    <td><a href="{{ route('ai-answers.show', $event->run_id) }}">{{ $event->run?->exam?->name ?: 'Paper '.$event->run_id }}</a></td>
                    <td>{{ $event->creator?->name ?: 'Deleted administrator' }}</td>
                    <td>{{ $event->created_at?->format('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No curriculum records have been created by AI publication yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($recentCurriculumEvents->hasPages())<div class="card-footer">{{ $recentCurriculumEvents->links() }}</div>@endif
</div>
@endsection
@push('scripts')
<script>
const publishPaperButton = document.getElementById('publish-selected-papers');
const updatePublishPaperButton = () => {
    publishPaperButton.disabled = !document.querySelector('.ready-paper-checkbox:checked');
};
document.getElementById('select-ready-papers')?.addEventListener('change', event => {
    document.querySelectorAll('.ready-paper-checkbox:not(:disabled)').forEach(box => box.checked = event.target.checked);
    updatePublishPaperButton();
});
document.querySelectorAll('.ready-paper-checkbox').forEach(box => box.addEventListener('change', updatePublishPaperButton));
updatePublishPaperButton();
const aiAnswerCategories = @json($categoryOptions);
const aiAnswerPackages = @json($packageOptions);
const initialExamIds = new Set(@json($selectedExams->pluck('id')->map(fn($id) => (int) $id)->values()).map(Number));
const aiAnswerGroup = document.getElementById('ai-answer-group');
const aiAnswerCategory = document.getElementById('ai-answer-category');
const aiAnswerPackage = document.getElementById('ai-answer-package');
const examSearch = document.getElementById('ai-answer-exam-search');
const examList = document.getElementById('ai-answer-exam-list');
const examCount = document.getElementById('ai-answer-exam-count');
const selectVisible = document.getElementById('ai-answer-select-visible');
const loadMore = document.getElementById('ai-answer-load-more');
const selectedExams = document.getElementById('ai-answer-selected-exams');
const oldCategoryId = Number(@json(old('category_id'))) || 0;
const oldPackageId = Number(@json(old('package_id'))) || 0;
let examPage = 1;
let searchTimer = null;
const selectedNumber = select => Number(select?.value || 0);
const belongsToGroup = (row, groupId) => !groupId || (row.group_ids || []).map(Number).includes(groupId);
const addOptions = (select, placeholder, rows, labelKey, selectedId) => {
    select.replaceChildren(new Option(placeholder, ''));
    rows.forEach(row => select.add(new Option(row[labelKey], row.id, false, Number(row.id) === Number(selectedId))));
};
const syncPackages = (preserve = false) => {
    const groupId = selectedNumber(aiAnswerGroup);
    const categoryId = selectedNumber(aiAnswerCategory);
    const previous = preserve ? oldPackageId : selectedNumber(aiAnswerPackage);
    const rows = aiAnswerPackages.filter(pkg => belongsToGroup(pkg, groupId)
        && (!categoryId || Number(pkg.category_id) === categoryId || Number(pkg.subcategory_id) === categoryId));
    addOptions(aiAnswerPackage, 'All packages', rows, 'name', previous);
};
const syncCategories = (preserve = false) => {
    const groupId = selectedNumber(aiAnswerGroup);
    const previous = preserve ? oldCategoryId : selectedNumber(aiAnswerCategory);
    addOptions(aiAnswerCategory, 'All categories', aiAnswerCategories.filter(row => belongsToGroup(row, groupId)), 'title', previous);
    syncPackages(preserve);
};

const syncSelectedExams = () => {
    selectedExams.replaceChildren();
    [...initialExamIds].forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'exam_ids[]'; input.value = id;
        selectedExams.append(input);
    });
};
const renderExam = exam => {
    const label = document.createElement('label'); label.className = 'd-flex align-items-start gap-2 border-bottom py-2 px-2 mb-0';
    const box = document.createElement('input'); box.type = 'checkbox'; box.value = exam.id;
    box.className = 'form-check-input mt-1 ai-answer-exam-checkbox'; box.checked = initialExamIds.has(Number(exam.id));
    box.addEventListener('change', () => {
        box.checked ? initialExamIds.add(Number(exam.id)) : initialExamIds.delete(Number(exam.id));
        syncSelectedExams();
    });
    const text = document.createElement('span'); text.className = 'flex-grow-1';
    const name = document.createElement('strong'); name.textContent = exam.name || exam.text;
    const meta = document.createElement('small'); meta.className = 'd-block text-muted'; meta.textContent = `${exam.question_count || 0} questions`;
    text.append(name, meta); label.append(box, text); examList.append(label);
};
const loadExams = async (reset = true) => {
    const hasHierarchy = aiAnswerGroup.value || aiAnswerCategory.value || aiAnswerPackage.value;
    const term = examSearch.value.trim();
    if (!hasHierarchy && !term) {
        examList.innerHTML = '<div class="text-muted p-3">Select a group, category or package, or type an exam name.</div>';
        examCount.textContent = 'No hierarchy selected'; loadMore.classList.add('d-none'); return;
    }
    if (reset) { examPage = 1; examList.innerHTML = '<div class="text-muted p-3">Loading matching exams...</div>'; }
    const query = new URLSearchParams({q: term, page: String(examPage), group_id: aiAnswerGroup.value, category_id: aiAnswerCategory.value, package_id: aiAnswerPackage.value});
    try {
        const response = await fetch(@json(route('ai-answers.exams.search')) + '?' + query.toString(), {headers:{'Accept':'application/json'}});
        if (!response.ok) throw new Error('Could not load exams.');
        const data = await response.json();
        if (reset) examList.replaceChildren();
        (data.results || []).forEach(renderExam);
        if (!examList.children.length) examList.innerHTML = '<div class="text-muted p-3">No exams match this hierarchy.</div>';
        examCount.textContent = `${data.total || 0} matching exam${Number(data.total) === 1 ? '' : 's'}`;
        loadMore.classList.toggle('d-none', !data.pagination?.more);
    } catch (error) {
        if (reset) examList.innerHTML = '<div class="text-danger p-3">Exams could not be loaded. Please refresh and try again.</div>';
        loadMore.classList.add('d-none');
    }
};
const hierarchyChanged = () => { initialExamIds.clear(); syncSelectedExams(); selectVisible.checked = false; loadExams(true); };
aiAnswerGroup.addEventListener('change', () => { syncCategories(); hierarchyChanged(); });
aiAnswerCategory.addEventListener('change', () => { syncPackages(); hierarchyChanged(); });
aiAnswerPackage.addEventListener('change', hierarchyChanged);
selectVisible.addEventListener('change', () => examList.querySelectorAll('.ai-answer-exam-checkbox').forEach(box => { box.checked = selectVisible.checked; box.dispatchEvent(new Event('change')); }));
loadMore.addEventListener('click', () => { examPage++; loadExams(false); });
examSearch.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadExams(true), 300); });
syncCategories(true); syncSelectedExams(); loadExams(true);
@if(session('error')) Swal.fire({icon:'error', title:'Unable to continue', text:@json(session('error')), width:520}); @endif
@if(session('success')) Swal.fire({icon:'success', title:'Done', text:@json(session('success')), width:520}); @endif
@if($errors->any()) Swal.fire({icon:'error', title:'Please check the selection', html:@json('<ul class="text-start mb-0"><li>'.implode('</li><li>', $errors->all()).'</li></ul>'), width:560}); @endif
@if($runs->contains(fn($run) => in_array($run->status, ['queued','starting','running']))) setTimeout(() => location.reload(), 10000); @endif
</script>
@endpush
