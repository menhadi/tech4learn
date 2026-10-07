@extends('layouts.master')
@section('title', 'Discover PDF Links')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <a href="{{ route('source-exams.index') }}" class="text-muted d-inline-flex align-items-center mb-2"><i class="ri-arrow-left-line me-1"></i>Source exams</a>
            <h3 class="mb-1">Discover Exam PDFs from a Webpage</h3>
            <p class="text-muted mb-0">Scan any public webpage that contains PDF links, review the matches, then create inactive exam drafts in bulk.</p>
        </div>

    </div>

    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-4"><div class="card-body">
        <form method="POST" action="{{ route('source-exams.discover.scan') }}">@csrf
            <h5 class="mb-3">Webpage or direct PDF sources</h5>
            <div class="row g-3">
                @foreach(['question' => ['Question papers', 'question or qp'], 'answer' => ['Answer keys / solutions', 'key'], 'combined' => ['Combined papers', 'combined']] as $role => [$label, $example])
                <div class="col-12"><div class="row g-2 align-items-end">
                    <div class="col-lg-6"><label class="form-label fw-semibold">{{ $label }} source URL @if($role !== 'question')<span class="text-muted fw-normal">(optional)</span>@endif</label><input type="url" class="form-control" name="{{ $role }}_source_url" value="{{ old($role.'_source_url', $sourceInputs[$role.'_source_url'] ?? '') }}" placeholder="Webpage URL or direct PDF URL"></div>
                    <div class="col-lg-3"><label class="form-label fw-semibold">Table heading contains</label><input type="text" class="form-control" name="{{ $role }}_heading" value="{{ old($role.'_heading', $sourceInputs[$role.'_heading'] ?? '') }}" placeholder="Example: {{ $role === 'answer' ? 'Answer Keys' : 'GATE Paper' }}"></div>
                    <div class="col-lg-3"><label class="form-label fw-semibold">Filename/URL contains</label><input type="text" class="form-control" name="{{ $role }}_pattern" value="{{ old($role.'_pattern', $sourceInputs[$role.'_pattern'] ?? '') }}" placeholder="Optional: {{ $example }}"></div>
                </div></div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="ri-radar-line me-1"></i>Scan structured sources</button></div>
            <div class="form-text mt-2">A pattern is a strict, case-insensitive filename/URL filter. For example, <code>key</code> in Answer Keys excludes every answer PDF without &ldquo;key&rdquo; in its filename or URL. Use the same webpage in multiple fields when it contains questions and keys.</div>
            <div class="alert alert-info py-2 mt-3 mb-0">For table pages, use the same webpage for papers and keys, then enter headings such as <strong>GATE Paper</strong> and <strong>Answer Keys</strong>. Hidden year tabs already present in the page HTML are scanned together.</div>
        </form>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <form method="POST" action="{{ route('source-exams.discover.local') }}" enctype="multipart/form-data">@csrf
            <h5 class="mb-3">Local PDF folders or files</h5>
            <div class="row g-3">
                @foreach(['question' => ['Question papers', 'question or qp'], 'answer' => ['Answer keys / solutions', 'key'], 'combined' => ['Combined papers', 'combined']] as $role => [$label, $example])
                <div class="col-12"><div class="row g-2 align-items-end">
                    <div class="col-lg-8"><label class="form-label fw-semibold">{{ $label }} @if($role !== 'question')<span class="text-muted fw-normal">(optional)</span>@endif</label><input type="file" class="form-control" name="{{ $role }}_pdfs[]" accept="application/pdf,.pdf" multiple webkitdirectory directory></div>
                    <div class="col-lg-4"><label class="form-label fw-semibold">Strict filename contains</label><input type="text" class="form-control" name="{{ $role }}_local_pattern" value="{{ old($role.'_local_pattern') }}" placeholder="Example: {{ $example }}"></div>
                </div></div>
                @endforeach
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-outline-primary" type="submit"><i class="ri-folder-upload-line me-1"></i>Read structured local PDFs</button></div>
            <div class="form-text mt-2">Choose folders or individual PDFs. Files stay in the source role where they were selected and are then filtered by that role&rsquo;s optional pattern.</div>
        </form>
    </div></div>
    @if($rows)
    <form method="POST" action="{{ route('source-exams.discover.store') }}" id="discovered-paper-form">
        @csrf
        <input type="hidden" name="page_url" value="{{ $pageUrl }}">

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-1">Defaults for new drafts</h5><p class="text-muted mb-0">A row-specific selection below overrides these values.</p></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6 col-xl-3"><label class="form-label">Group *</label><select class="form-select" name="default_group_id" id="default-group" required><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('default_group_id') == $group->id)>{{ $group->group_name }}</option>@endforeach</select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Category</label><select class="form-select" name="default_category_id" id="default-category"><option value="">None</option></select></div>
                    <div class="col-md-6 col-xl-3" data-subcategory-ui><label class="form-label">Subcategory</label><select class="form-select" name="default_subcategory_id" id="default-subcategory" disabled><option value="">None</option></select></div>
                    <div class="col-md-6 col-xl-3"><label class="form-label">Package</label><select class="form-select" name="default_package_id" id="default-package"><option value="">None</option></select></div>
                    <div class="col-md-4 col-xl-2"><label class="form-label">Language *</label><select class="form-select" name="language_id" required>@foreach($languages as $language)<option value="{{ $language->id }}" @selected(old('language_id', $languages->first()?->id) == $language->id)>{{ $language->name }}</option>@endforeach</select></div>
                    <div class="col-md-4 col-xl-2"><label class="form-label">Duration (min)</label><input class="form-control" type="number" name="duration" value="{{ old('duration', 180) }}" min="0" required></div>
                    <div class="col-md-4 col-xl-2"><label class="form-label">Attempts</label><input class="form-control" type="number" name="attempt_count" value="{{ old('attempt_count', 0) }}" min="0" required></div>
                    <div class="col-md-4 col-xl-1"><label class="form-label">Pass %</label><input class="form-control" type="number" name="passing_percentage" value="{{ old('passing_percentage', 0) }}" min="0" max="100" step="0.01" required></div>
                    <div class="col-md-4 col-xl-1"><label class="form-label">Marks</label><input class="form-control" type="number" name="marks" value="{{ old('marks', 1) }}" min="0" step="0.01" required></div>
                    <div class="col-md-4 col-xl-2"><label class="form-label">Negative marks</label><input class="form-control" type="number" name="negative_marks" value="{{ old('negative_marks', 0) }}" min="0" step="0.01" required></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><h5 class="mb-1">Review detected papers</h5><p class="text-muted mb-0"><span id="selected-paper-count">0</span> selected. Edit any match before creating drafts.</p></div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary" id="add-manual-row"><i class="ri-add-line me-1"></i>Add row</button>
                    @if(user_can_route_action('exams.edit', 'edit'))<button type="submit" name="publish_pdfs" value="1" class="btn btn-success" id="publish-discovered-pdfs">Publish selected PDFs</button>@endif
                    <button type="submit" class="btn btn-primary" id="create-paper-drafts"><i class="ri-draft-line me-1"></i>Create selected drafts</button>
                </div>
            </div>
            <div class="alert alert-info rounded-0 border-start-0 border-end-0 mb-0">
                Publishing makes the official PDF available for download and keeps online attempts disabled until active questions are added. The same group and optional category selections apply to both actions. Creating drafts downloads the selected PDFs into private source storage and creates inactive, zero-question exams. It does <strong>not</strong> run extraction, OCR or AI. The drafts appear in <a href="{{ route('source-exams.index') }}" target="_blank"><strong>Create Exam from Source</strong></a>; select them there and click <strong>Process selected drafts</strong> when ready.
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="discovered-paper-table" style="min-width:1900px">
                    <thead class="table-light"><tr>
                        <th style="width:52px"><input type="checkbox" class="form-check-input" id="select-all-discovered" checked aria-label="Select all detected papers"></th>
                        <th style="min-width:250px">Exam name</th><th style="width:95px">Year</th>
                        <th style="min-width:330px">Question PDF</th><th style="min-width:330px">Answer PDF</th><th style="min-width:330px">Combined PDF</th>
                        <th style="min-width:210px">Group override</th><th style="min-width:210px">Category override</th><th style="min-width:210px" data-subcategory-ui>Subcategory override</th><th style="min-width:250px">Package override</th>
                    </tr></thead>
                    <tbody>
                    @foreach($rows as $index => $row)
                        @include('source-exams.partials.discovery-row', ['index' => $index, 'row' => $row])
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    @endif
</div>

@if($rows)
<template id="discovery-row-template">
    @include('source-exams.partials.discovery-row', ['index' => '__INDEX__', 'row' => ['selected' => true, 'exam_name' => '', 'year' => '', 'question_url' => '', 'answer_url' => '', 'combined_url' => '', 'confidence' => 'manual']])
</template>
@endif
@push('scripts')
@if($rows)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const hierarchy = @json($defaultHierarchy);
    const initialDefaults = {
        category: @json((string) old('default_category_id', '')),
        subcategory: @json((string) old('default_subcategory_id', '')),
        package: @json((string) old('default_package_id', ''))
    };
    const groupSelect = document.getElementById('default-group');
    const categorySelect = document.getElementById('default-category');
    const subcategorySelect = document.getElementById('default-subcategory');
    const packageSelect = document.getElementById('default-package');
    const replaceOptions = (select, items, selectedValue, labelKey) => {
        const desired = String(selectedValue || '');
        select.replaceChildren(new Option('None', ''));
        items.forEach(item => select.add(new Option(item[labelKey], String(item.id))));
        select.value = items.some(item => String(item.id) === desired) ? desired : '';
    };
    const packagesForSelection = () => {
        const groupId = Number(groupSelect.value || 0);
        const categoryId = Number(categorySelect.value || 0);
        const subcategoryId = Number(subcategorySelect.value || 0);
        return hierarchy.packages.filter(item => {
            const belongsToGroup = item.group_ids.includes(groupId);
            const belongsToCategory = !categoryId || Number(item.category_id) === categoryId;
            const belongsToSubcategory = !subcategoryId || Number(item.subcategory_id) === subcategoryId;
            return groupId && belongsToGroup && belongsToCategory && belongsToSubcategory;
        });
    };
    const refreshPackages = selectedValue => {
        replaceOptions(packageSelect, packagesForSelection(), selectedValue, 'name');
        packageSelect.disabled = !groupSelect.value;
    };
    const refreshSubcategories = (selectedValue, selectedPackage = '') => {
        const categoryId = Number(categorySelect.value || 0);
        const items = hierarchy.subcategories.filter(item => categoryId && Number(item.category_id) === categoryId);
        replaceOptions(subcategorySelect, items, selectedValue, 'title');
        subcategorySelect.disabled = !categoryId || items.length === 0;
        refreshPackages(selectedPackage);
    };
    const refreshCategories = (selectedCategory = '', selectedSubcategory = '', selectedPackage = '') => {
        const groupId = Number(groupSelect.value || 0);
        const items = hierarchy.categories.filter(item => groupId && item.group_ids.includes(groupId));
        replaceOptions(categorySelect, items, selectedCategory, 'title');
        categorySelect.disabled = !groupId;
        refreshSubcategories(selectedSubcategory, selectedPackage);
    };
    const refreshRowHierarchies = () => document.querySelectorAll('#discovered-paper-table tbody tr').forEach(row => row.refreshHierarchy?.());
    groupSelect.addEventListener('change', () => { refreshCategories(); refreshRowHierarchies(); });
    categorySelect.addEventListener('change', () => { refreshSubcategories(); refreshRowHierarchies(); });
    subcategorySelect.addEventListener('change', () => { refreshPackages(); refreshRowHierarchies(); });
    refreshCategories(initialDefaults.category, initialDefaults.subcategory, initialDefaults.package);

    const initRowHierarchy = row => {
        const rowGroup = row.querySelector('.discovery-group');
        const rowCategory = row.querySelector('.discovery-category');
        const rowSubcategory = row.querySelector('.discovery-subcategory');
        const rowPackage = row.querySelector('.discovery-package');
        if (!rowGroup || row.dataset.hierarchyReady === '1') return;
        row.dataset.hierarchyReady = '1';
        const rowOptions = (select, items, selected, key) => {
            const desired = String(selected || '');
            select.replaceChildren(new Option('Use default', ''));
            items.forEach(item => select.add(new Option(item[key], String(item.id))));
            select.value = items.some(item => String(item.id) === desired) ? desired : '';
        };
        const effectiveGroup = () => Number(rowGroup.value || groupSelect.value || 0);
        const effectiveCategory = () => Number(rowCategory.value || categorySelect.value || 0);
        const effectiveSubcategory = () => Number(rowSubcategory.value || subcategorySelect.value || 0);
        const refreshRowPackages = (selected = rowPackage.value) => {
            const groupId = effectiveGroup(), categoryId = effectiveCategory(), subcategoryId = effectiveSubcategory();
            const items = hierarchy.packages.filter(item => groupId && item.group_ids.includes(groupId)
                && (!categoryId || Number(item.category_id) === categoryId)
                && (!subcategoryId || Number(item.subcategory_id) === subcategoryId));
            rowOptions(rowPackage, items, selected, 'name');
            rowPackage.disabled = !groupId;
        };
        const refreshRowSubcategories = (selected = rowSubcategory.value, selectedPackage = rowPackage.value) => {
            const categoryId = effectiveCategory();
            const items = hierarchy.subcategories.filter(item => categoryId && Number(item.category_id) === categoryId);
            rowOptions(rowSubcategory, items, selected, 'title');
            rowSubcategory.disabled = !categoryId || items.length === 0;
            refreshRowPackages(selectedPackage);
        };
        const refreshRowCategories = (selected = rowCategory.value, selectedSubcategory = rowSubcategory.value, selectedPackage = rowPackage.value) => {
            const groupId = effectiveGroup();
            const items = hierarchy.categories.filter(item => groupId && item.group_ids.includes(groupId));
            rowOptions(rowCategory, items, selected, 'title');
            rowCategory.disabled = !groupId;
            refreshRowSubcategories(selectedSubcategory, selectedPackage);
        };
        rowGroup.addEventListener('change', () => refreshRowCategories(''));
        rowCategory.addEventListener('change', () => refreshRowSubcategories(''));
        rowSubcategory.addEventListener('change', () => refreshRowPackages(''));
        row.refreshHierarchy = () => refreshRowCategories();
        refreshRowCategories();
    };

    const tbody = document.querySelector('#discovered-paper-table tbody');
    tbody.querySelectorAll('tr').forEach(initRowHierarchy);
    const master = document.getElementById('select-all-discovered');
    const count = document.getElementById('selected-paper-count');
    let nextIndex = {{ count($rows) }};
    const boxes = () => Array.from(tbody.querySelectorAll('.discovered-paper-checkbox'));
    const sync = () => {
        const all = boxes(), selected = all.filter(box => box.checked).length;
        count.textContent = selected;
        master.checked = all.length > 0 && selected === all.length;
        master.indeterminate = selected > 0 && selected < all.length;
        document.getElementById('create-paper-drafts').disabled = selected === 0;
        const publish = document.getElementById('publish-discovered-pdfs');
        if (publish) publish.disabled = selected === 0;
    };
    master.addEventListener('change', () => { boxes().forEach(box => box.checked = master.checked); sync(); });
    tbody.addEventListener('change', event => { if (event.target.matches('.discovered-paper-checkbox')) sync(); });
    document.getElementById('add-manual-row').addEventListener('click', () => {
        tbody.insertAdjacentHTML('afterbegin', document.getElementById('discovery-row-template').innerHTML.replaceAll('__INDEX__', nextIndex++));
        initRowHierarchy(tbody.querySelector('tr'));
        sync();
    });
    sync();
});
</script>
@endif
@endpush
@endsection
