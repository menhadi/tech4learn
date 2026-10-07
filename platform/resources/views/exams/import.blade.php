@extends('layouts.master')

@section('title', 'Exam Import / Export')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Academic')
@slot('title', 'Exam Import / Export')
@endcomponent

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h4 class="mb-1">Exam data management</h4>
            <p class="text-muted mb-0">Export only the exams you need, safely edit the workbook, preview it, and then import.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="#export-exams" class="btn btn-lg el-btn-secondary"><i class="ri-file-excel-2-line me-1"></i> Export Exams</a>
            <a href="#import-exams" class="btn btn-lg el-btn-primary"><i class="ri-upload-2-line me-1"></i> Import Exams</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-9">
        <div class="card" id="export-exams">
            <div class="card-header">
                <h4 class="card-title mb-1">Export exams</h4>
                <p class="text-muted mb-0">Leave every filter as All to export all exams, or combine filters to export a smaller workbook.</p>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('exams.export') }}" id="exam-export-filter" data-hierarchy-url="{{ route('exams.import.index') }}">
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label fw-semibold">Group</label>
                            <select name="group_id" class="form-select hierarchy-filter" data-filter="group">
                                <option value="">All Groups</option>
                                @foreach($exportGroups as $group)
                                    <option value="{{ $group->id }}" @selected((string) request('group_id') === (string) $group->id)>{{ $group->group_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category_id" class="form-select hierarchy-filter" data-filter="category">
                                <option value="">All Categories</option>
                                @foreach($exportCategories as $category)
                                    <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-4" data-subcategory-ui>
                            <label class="form-label fw-semibold">Subcategory</label>
                            <select name="subcategory_id" class="form-select hierarchy-filter" data-filter="subcategory">
                                <option value="">All Subcategories</option>
                                @foreach($exportSubcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}" @selected((string) request('subcategory_id') === (string) $subcategory->id)>{{ $subcategory->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label fw-semibold">Package</label>
                            <select name="package_id" class="form-select">
                                <option value="">All Packages</option>
                                @foreach($exportPackages as $package)
                                    <option value="{{ $package->id }}" @selected((string) request('package_id') === (string) $package->id)>{{ $package->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-5">
                            <label class="form-label fw-semibold">Exam name contains</label>
                            <div class="position-relative">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control ps-5" maxlength="150" placeholder="e.g. NEET, CAT 2025, Scholarship">
                                <i class="ri-search-line position-absolute top-50 translate-middle-y text-muted" style="left: 1rem"></i>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3 d-flex align-items-end gap-2">
                            <button class="btn el-btn-secondary flex-grow-1" type="submit"><i class="ri-download-2-line me-1"></i> Export XLSX</button>
                            <a href="{{ route('exams.import.index') }}#export-exams" class="btn el-btn-outline" title="Clear filters"><i class="ri-refresh-line"></i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" id="import-exams">
            <div class="card-header">
                <h4 class="card-title mb-1">Import edited workbook</h4>
                <p class="text-muted mb-0">The import is previewed first. Nothing is changed until you click Apply.</p>
            </div>
            <div class="card-body">
                @include('exams.partials.import-summary')

                <div class="alert alert-info border-0">
                    <strong>Safe editing rules:</strong> a blank cell keeps the existing database value. Use <code>__CLEAR__</code> only when you intentionally want to remove an optional value.
                    Keep <code>operation</code> as <code>UPDATE</code> for existing exams, or use <code>CREATE</code> for a new exam. Relation columns use comma-separated IDs.
                </div>

                <form method="POST" action="{{ route('exams.import.preview') }}" enctype="multipart/form-data" class="border rounded p-3">
                    @csrf
                    <label class="form-label fw-semibold">Edited XLSX / XLS / CSV file</label>
                    <input type="file" name="workbook" class="form-control @error('workbook') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                    @error('workbook')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn el-btn-primary mt-3" type="submit"><i class="ri-search-eye-line me-1"></i> Preview Import</button>
                </form>

                @isset($preview)
                    <div class="mt-4">
                        <h5>Preview summary</h5>
                        <div class="row g-3 mt-1">
                            @foreach(['total' => 'Rows', 'valid' => 'Valid', 'updates' => 'Updates', 'creates' => 'Creates'] as $key => $label)
                                <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-semibold">{{ $preview[$key] }}</div></div></div>
                            @endforeach
                        </div>
                        @if($preview['errors'])
                            <div class="alert alert-danger mt-3 mb-0">
                                <strong>{{ count($preview['errors']) }} row(s) need correction.</strong>
                                <ul class="mb-0 mt-2">@foreach(array_slice($preview['errors'], 0, 50) as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
                            </div>
                        @else
                            <form method="POST" action="{{ route('exams.import.apply') }}" class="mt-3" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerText='Applying changes...';">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">
                                <button class="btn el-btn-secondary" type="submit"><i class="ri-check-double-line me-1"></i> Apply {{ $preview['valid'] }} Changes</button>
                            </form>
                        @endif
                    </div>
                @endisset

                @if(session('import_errors'))
                    <div class="alert alert-warning mt-3"><ul class="mb-0">@foreach(session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">How updates work</h5></div>
            <div class="card-body small text-muted">
                <p><strong>Blank cell:</strong> preserve the current value.</p>
                <p><strong><code>__CLEAR__</code>:</strong> remove an optional value or relation.</p>
                <p><strong>UPDATE:</strong> match the existing record using <code>exam_id</code>.</p>
                <p class="mb-0"><strong>CREATE:</strong> make a new exam after validation.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Package display columns</h5></div>
            <div class="card-body small text-muted">
                <p><strong>Test type:</strong> use <code>full_length</code>, <code>subject_test</code>, <code>topic_test</code>, <code>subtopic_test</code>, <code>sectional_test</code>, <code>previous_year</code>, or <code>other</code>.</p>
                <p><strong>Hierarchy:</strong> enter IDs in <code>test_subject_id</code>, <code>test_topic_id</code>, and <code>test_subtopic_id</code> as required by the type.</p>
                <p class="mb-0"><strong>Name columns:</strong> exported for reference only; imports use the ID columns.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">PDF source columns</h5></div>
            <div class="card-body small text-muted">
                <p><strong>Action:</strong> KEEP, REPLACE, or REMOVE.</p>
                <p><strong>Kind:</strong> <code>url</code> for an HTTPS PDF or <code>file</code> for private storage.</p>
                <p><strong>Disk:</strong> <code>local</code> or <code>r2</code>.</p>
                <p class="mb-0"><strong>Value:</strong> the URL or an R2 object key such as <code>exam-quality-sources/1/245/questions/paper.pdf</code>.</p>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('exam-export-filter');
    if (!form) return;

    form.querySelectorAll('.hierarchy-filter').forEach(select => {
        select.addEventListener('change', () => {
            const resets = {
                group: ['category', 'subcategory', 'package'],
                category: ['subcategory', 'package'],
                subcategory: ['package'],
            };

            (resets[select.dataset.filter] || []).forEach(name => {
                const dependent = form.querySelector(`[name="${name}_id"]`);
                if (dependent) dependent.value = '';
            });

            const params = new URLSearchParams();
            new FormData(form).forEach((value, key) => {
                if (String(value).trim() !== '') params.set(key, value);
            });
            const query = params.toString();
            window.location.assign(form.dataset.hierarchyUrl + (query ? `?${query}` : '') + '#export-exams');
        });
    });
});
</script>
@endpush
@endsection
