@extends('layouts.master')
@section('title','New Source Exam')
@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('source-exams.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Source exams</a>
        <h3 class="mt-2 mb-1">Create exam from source</h3>
        <p class="text-muted">Select existing zero-question exams through the linked hierarchy, then process their private PDF or DOCX sources.</p>
    </div>
    <form method="POST" action="{{ route('source-exams.store') }}" enctype="multipart/form-data" data-disable-ai-inline>
        @csrf
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">1. Exam and private sources</h5></div>
            <div class="card-body">
                <div class="row g-3 mb-3" id="source-exam-hierarchy">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="source-filter-group">Group</label>
                        <select id="source-filter-group" class="form-select">
                            <option value="">All groups</option>
                            @foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="source-filter-category">Category</label>
                        <select id="source-filter-category" class="form-select">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" data-groups="{{ $category->groups->pluck('id')->join(',') }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3" data-subcategory-ui>
                        <label class="form-label" for="source-filter-subcategory">Subcategory</label>
                        <select id="source-filter-subcategory" class="form-select">
                            <option value="">All subcategories</option>
                            @foreach($subcategories as $subcategory)<option value="{{ $subcategory->id }}" data-parent="{{ $subcategory->parent_id }}">{{ $subcategory->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="source-filter-package">Package</label>
                        <select id="source-filter-package" class="form-select">
                            <option value="">All packages</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}" data-groups="{{ $package->groups->pluck('id')->join(',') }}" data-category="{{ $package->category_level_1 }}" data-subcategory="{{ $package->category_level_2 }}">{{ $package->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label">Existing empty exams *</label>
                    <select name="target_exam_ids[]" id="source-target-exam" class="form-select select2" multiple required>
                        @foreach($emptyExams as $item)
                            @php
                                $itemGroupIds = $item->groups->pluck('id')->merge($item->packages->flatMap(fn ($package) => $package->groups->pluck('id')))->filter()->unique()->values();
                                $itemCategoryIds = collect([$item->category_level_1])->merge($item->packages->pluck('category_level_1'))->filter()->unique()->values();
                                $itemSubcategoryIds = collect([$item->category_level_2])->merge($item->packages->pluck('category_level_2'))->filter()->unique()->values();
                                $itemPackageIds = $item->packages->pluck('id')->filter()->unique()->values();
                            @endphp
                            <option value="{{ $item->id }}"
                                data-groups="{{ $itemGroupIds->join(',') }}"
                                data-categories="{{ $itemCategoryIds->join(',') }}"
                                data-subcategories="{{ $itemSubcategoryIds->join(',') }}"
                                data-packages="{{ $itemPackageIds->join(',') }}"
                                @selected(in_array($item->id, array_map('intval', old('target_exam_ids', [])), true))>
                                {{ $item->name }}
                                @if($item->groups->isNotEmpty()) &middot; {{ $item->groups->pluck('group_name')->join(', ') }} @endif
                                @if($item->packages->isNotEmpty()) &middot; {{ $item->packages->pluck('name')->join(', ') }} @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text"><span id="source-exam-match-count">{{ $emptyExams->count() }}</span> matching zero-question exams. Select one or more; each uses its own attached private PDF file or PDF URL.</div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-4"><label class="form-label">Question paper override (optional)</label><input type="file" name="question_source" class="form-control" accept=".pdf,.docx"><div class="form-text">Uses the selected exam's private question/combined PDF by default.</div></div>
                    <div class="col-md-4"><label class="form-label">Answer key override (optional)</label><input type="file" name="answer_source" class="form-control" accept=".pdf,.docx,.txt"><div class="form-text">Uses the selected exam's private answer/combined PDF when available.</div></div>
                    <div class="col-md-4"><label class="form-label">Solutions override (optional)</label><input type="file" name="solution_source" class="form-control" accept=".pdf,.docx,.txt"></div>
                </div>
            </div>
        </div>
        <div class="card mb-3"><div class="card-header"><h5 class="mb-0">2. Select the exact extractor</h5></div><div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label">Document type</label><select name="profile[document_type]" class="form-select"><option value="auto">Auto detect</option><option value="digital">Digital/searchable</option><option value="scanned">Scanned/OCR</option><option value="mixed">Mixed</option></select></div>
            <div class="col-12">
                <label class="form-label" for="source-extractor-script">Python extractor *</label>
                <select id="source-extractor-script" name="extractor_script" class="form-select" required>
                    @foreach($extractors as $script => $label)
                        <option value="{{ $script }}" @selected(old('extractor_script', \App\Support\SourceExtractorRegistry::BUILTIN) === $script)>{{ $label }} - {{ $script }}</option>
                    @endforeach
                </select>
                <div class="form-text">This exact script controls extraction. Hard-coded API behavior stays inside the script; environment-based API credentials come from Admin AI Settings.</div>
            </div>
            <div class="col-md-3"><label class="form-label">Layout</label><select name="profile[layout]" class="form-select"><option value="auto">Auto detect</option><option value="single">Single column</option><option value="two-column">Two columns</option><option value="bilingual">Bilingual side by side</option></select></div>
            <div class="col-md-3"><label class="form-label">Reading order</label><select name="profile[reading_order]" class="form-select"><option value="auto">Auto</option><option value="columns">Columns first</option><option value="rows">Rows first</option></select></div>
            <div class="col-md-3"><label class="form-label">Question numbering</label><select name="profile[question_numbering]" class="form-select"><option value="auto">Auto</option><option value="continuous">Continuous</option><option value="resets">Resets by section</option></select></div>
            <div class="col-md-6">
                <label class="form-label">Fallback question type</label>
                <select name="qtype_id" class="form-select">
                    <option value="">Auto detect (recommended)</option>
                    @foreach($qtypes as $qtype)<option value="{{ $qtype->id }}" @selected((string) old('qtype_id') === (string) $qtype->id)>{{ $qtype->question_type }} ({{ $qtype->type }})</option>@endforeach
                </select>
                <div class="form-text">Used only when the selected extractor does not return a question type and options/answer patterns cannot identify MCQ, True/False, Fill Blank or NAT.</div>
            </div>
        </div></div>
        <div class="d-flex flex-wrap justify-content-end gap-2 mb-4"><a class="btn btn-outline-secondary" href="{{ route('source-exams.index') }}">Cancel</a><button class="btn btn-outline-primary" type="submit" name="processing_action" value="draft"><i class="ri-save-line me-1"></i>Save as draft</button><button class="btn btn-primary" type="submit" name="processing_action" value="queue"><i class="ri-play-circle-line me-1"></i>Create & start extraction</button></div>
    </form>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const group = document.getElementById('source-filter-group');
    const category = document.getElementById('source-filter-category');
    const subcategory = document.getElementById('source-filter-subcategory');
    const packageSelect = document.getElementById('source-filter-package');
    const examSelect = document.getElementById('source-target-exam');
    const count = document.getElementById('source-exam-match-count');
    if (!group || !category || !subcategory || !packageSelect || !examSelect) return;

    const examOptions = Array.from(examSelect.options).map(option => option.cloneNode(true));
    const categoryOptions = Array.from(category.options);
    const subcategoryOptions = Array.from(subcategory.options);
    const packageOptions = Array.from(packageSelect.options);
    const ids = value => String(value || '').split(',').filter(Boolean);
    const includes = (csv, value) => !value || ids(csv).includes(String(value));

    function filterNative(select, options, predicate) {
        options.forEach(option => {
            if (!option.value) return;
            const visible = predicate(option);
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (select.value && select.selectedOptions[0]?.disabled) select.value = '';
    }

    function rebuildExamOptions() {
        const selected = new Set(Array.from(examSelect.selectedOptions).map(option => option.value));
        const filters = { group: group.value, category: category.value, subcategory: subcategory.value, package: packageSelect.value };
        const matches = examOptions.filter(option =>
            includes(option.dataset.groups, filters.group) &&
            includes(option.dataset.categories, filters.category) &&
            includes(option.dataset.subcategories, filters.subcategory) &&
            includes(option.dataset.packages, filters.package)
        );
        if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(examSelect).hasClass('select2-hidden-accessible')) window.jQuery(examSelect).select2('destroy');
        examSelect.replaceChildren(...matches.map(option => {
            const clone = option.cloneNode(true);
            clone.selected = selected.has(clone.value);
            return clone;
        }));
        count.textContent = String(matches.length);
        if (window.jQuery && window.jQuery.fn.select2) window.jQuery(examSelect).select2({ width: '100%', placeholder: 'Search and select one or more exams' });
    }

    function applyHierarchy() {
        filterNative(category, categoryOptions, option => !group.value || !option.dataset.groups || includes(option.dataset.groups, group.value));
        filterNative(subcategory, subcategoryOptions, option => !category.value || String(option.dataset.parent) === String(category.value));
        filterNative(packageSelect, packageOptions, option =>
            includes(option.dataset.groups, group.value) &&
            (!category.value || String(option.dataset.category) === String(category.value)) &&
            (!subcategory.value || String(option.dataset.subcategory) === String(subcategory.value))
        );
        rebuildExamOptions();
    }

    [group, category, subcategory, packageSelect].forEach(select => select.addEventListener('change', applyHierarchy));
    applyHierarchy();
});
</script>
@endpush
