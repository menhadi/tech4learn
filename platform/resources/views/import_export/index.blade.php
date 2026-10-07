@extends('layouts.master')
@section('title', 'Question Management')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<style>
    /* Modern Dropzone Style */
    .upload-box {
        border: 2px dashed var(--el-border, #d7e2df);
        border-radius: var(--el-radius, 4px);
        background: var(--el-surface-muted, #f8fafc);
        text-align: center;
        padding: 40px 20px;
        transition: all 0.3s ease;
        position: relative;
        cursor: pointer;
    }
    .upload-box:hover, .upload-box.dragover {
        border-color: var(--el-primary, var(--vz-primary));
        background: var(--el-primary-soft, rgba(var(--vz-primary-rgb), 0.1));
    }
    .upload-icon {
        font-size: 48px;
        color: var(--el-primary, var(--vz-primary));
        margin-bottom: 15px;
    }
    .upload-text h5 { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
    .upload-text p { font-size: 13px; color: var(--el-muted, #7b8497); margin-bottom: 0; }
    
    /* Step indicators */
    .step-badge {
        display: inline-block;
        background: var(--el-primary-soft, rgba(var(--vz-primary-rgb), 0.1));
        color: var(--el-primary, var(--vz-primary));
        padding: 4px 10px;
        border-radius: var(--el-radius, 4px);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .nav-tabs-custom .nav-item .nav-link.active {
        color: var(--el-primary, var(--vz-primary));
        background-color: transparent;
    }
    .nav-tabs-custom .nav-item .nav-link::after {
        background-color: var(--el-primary, var(--vz-primary));
    }

    .question-hub-icon {
        color: var(--el-primary, var(--vz-primary));
    }
</style>
@endsection

@section('content')
@php
    $showExportTab = request('tab') === 'export';
@endphp
@component('components.breadcrumb')
@slot('li_1', 'Academic')
@slot('title', 'Questions Hub')
@endcomponent
@if($importReport)
<div class="alert alert-{{ count($importReport['errors'] ?? []) ? 'warning' : 'success' }} shadow-sm mb-3" role="status">
    <h5 class="alert-heading mb-2">Import report</h5>
    <div class="row g-2 small">
        <div class="col-md-4"><strong>File:</strong> {{ $importReport['file'] }}</div>
        <div class="col-md-4"><strong>Mode:</strong> {{ $importReport['mode'] }}</div>
        <div class="col-md-4"><strong>Rows processed:</strong> {{ $importReport['processed'] }}</div>
        <div class="col-md-4"><strong>Updated:</strong> {{ $importReport['updated'] }}</div>
        <div class="col-md-4"><strong>Duplicates:</strong> {{ $importReport['duplicates'] }}</div>
        <div class="col-md-4"><strong>Hierarchy records created:</strong> {{ $importReport['created_records'] }}</div>
    </div>
    @if(count($importReport['errors'] ?? []))
        <hr class="my-2"><strong>Errors</strong>
        <ul class="mb-0">@foreach($importReport['errors'] ?? [] as $error)<li>{{ $error }}</li>@endforeach</ul>
    @else
        <div class="mt-2">No warnings or errors.</div>
    @endif
</div>
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header border-bottom-0">
                <div class="d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Manage Questions</h5>
                    <div class="flex-shrink-0">
                        <ul class="nav nav-tabs-custom card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link {{ $showExportTab ? '' : 'active' }}" data-bs-toggle="tab" href="#importTab" role="tab">
                                    <i class="ri-file-upload-line align-bottom me-1"></i> Import Questions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $showExportTab ? 'active' : '' }}" data-bs-toggle="tab" href="#exportTab" role="tab">
                                    <i class="ri-file-download-line align-bottom me-1"></i> Export
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4">
                <div class="tab-content">
                    
                    {{-- ================= IMPORT TAB ================= --}}
                    <div class="tab-pane {{ $showExportTab ? '' : 'active show' }}" id="importTab" role="tabpanel">
                        <form method="POST" action="{{ route('questions.import') }}" enctype="multipart/form-data" id="importForm">
                            @csrf
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <span class="step-badge">Import Source</span>
                                            <h6 class="mb-0">Excel/CSV or Google Sheet</h6>
                                        </div>
                                        <a href="{{ route('questions.downloadTemplate') }}" class="btn btn-sm el-btn-secondary">
                                            <i class="ri-download-2-line align-bottom me-1"></i> Download Template
                                        </a>
                                    </div>
                                    <div class="alert alert-info py-2 mb-3">
                                        <strong>MCQ/MSQ correct options:</strong> use the <code>correct_option_indices</code> column. Enter <code>2</code> for option 2, or <code>1,3</code> when options 1 and 3 are both correct. Store positions only, not duplicated option text.
                                    </div>

                                    <label class="upload-box d-block" id="dropzone" for="excel_file" role="button" tabindex="0">
                                        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.csv" class="visually-hidden">
                                        <div class="upload-icon">
                                            <i class="ri-file-excel-2-line"></i>
                                        </div>
                                        <div class="upload-text">
                                            <h5 id="file-label">Click to upload or drag and drop</h5>
                                            <p class="text-muted">XLSX up to 5MB, or CSV up to 1GB. Large CSV files upload safely in chunks.</p>
                                        </div>
                                    </label>
                                    
                                    <div class="text-center text-muted fw-semibold my-3">OR</div>
                                    <div class="mb-3">
                                        <label class="form-label" for="google_sheet_url">Google Sheet URL</label>
                                        <input type="url" class="form-control" id="google_sheet_url" name="google_sheet_url" value="{{ old('google_sheet_url') }}" placeholder="https://docs.google.com/spreadsheets/d/...">
                                        <div class="form-text">Share the sheet as &quot;Anyone with the link - Viewer&quot;. The first row must use the template headings. Subject, Topic and Subtopic are optional; leave all three blank when they are not relevant. If Topic is provided, Subject is required, and if Subtopic is provided, both Subject and Topic are required.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="import_mode">Import behaviour</label>
                                        <select class="form-select" id="import_mode" name="import_mode" required>
                                            <option value="create" @selected(old('import_mode', 'create') === 'create')>Create new questions (safe default)</option>
                                            <option value="patch" @selected(old('import_mode') === 'patch')>Patch existing questions (update nonblank cells only)</option>
                                            <option value="source_patch" @selected(old('import_mode') === 'source_patch')>Update sources only (preserve every other field)</option>
                                            <option value="update" @selected(old('import_mode') === 'update')>Replace existing questions by question_code</option>
                                            <option value="upsert" @selected(old('import_mode') === 'upsert')>Update matching codes and create missing questions</option>
                                        </select>
                                        <div class="form-text">Patch update changes only nonblank cells and preserves every blank/missing field. Source-only update is stricter and accepts only source columns. Both require existing question_code and never create questions.</div>
                                    </div>
                                    <div id="file-preview" class="mt-3 d-none">
                                        <div class="alert alert-success d-flex align-items-center">
                                            <i class="ri-file-list-3-line fs-20 me-2"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="alert-heading mb-0" id="filename-display">filename.xlsx</h6>
                                            </div>
                                            <button type="button" class="btn-close" id="remove-file"></button>
                                        </div>
                                    </div>

                                    <div id="large-import-progress" class="card border mt-3 d-none">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong id="large-import-title">Preparing import</strong>
                                                <span id="large-import-percent">0%</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div id="large-import-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                                            </div>
                                            <div id="large-import-message" class="small text-muted mt-2">Do not close this page while the file is uploading.</div>
                                            <div id="large-import-counts" class="small mt-2 d-none"></div>
                                            <a id="large-import-errors" class="btn btn-sm btn-outline-danger mt-2 d-none" href="#">Download row error report</a>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-end">
                                        <button type="submit" id="import-submit-button" class="btn el-btn-primary px-4 w-sm">
                                            <i class="ri-upload-cloud-2-line align-bottom me-1"></i> Import Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- ================= EXPORT TAB ================= --}}
                    <div class="tab-pane {{ $showExportTab ? 'active show' : '' }}" id="exportTab" role="tabpanel">
                        <div class="row justify-content-center">
                            <div class="col-xl-10">
                                <div class="text-center mb-4">
                                    <i class="ri-file-download-line fs-48 question-hub-icon"></i>
                                    <h4>Export Question Bank</h4>
                                    <p class="text-muted">Filter first, then export. CSV streams safely for very large question banks and opens directly in Excel.</p>
                                </div>
                                <form id="question-export-form" class="card shadow-sm border" method="GET" action="{{ route('questions.export') }}">
                                    <div class="card-body">
                                        <div class="alert alert-info py-2"><strong>Large export:</strong> use Excel-compatible CSV for 25,000 to 200,000+ questions. XLSX is available for filtered results up to 25,000 questions.</div>
                                        <h6 class="text-uppercase text-muted mb-3">Academic hierarchy</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4"><label class="form-label">Group</label><select id="exp_group" name="group_id" class="form-control select2"><option value="">All Groups</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->group_name }}</option>@endforeach</select></div>
                                            <div class="col-md-4"><label class="form-label">Category</label><select id="exp_category" name="category_id" class="form-control select2"><option value="">All Categories</option>@foreach($parentCategories as $category)<option value="{{ $category->id }}" data-group-ids="{{ $category->groups->pluck('id')->implode(',') }}">{{ $category->title }}</option>@endforeach</select></div>
                                            <div class="col-md-4" data-subcategory-ui><label class="form-label">Subcategory</label><select id="exp_category_2" name="subcategory_id" class="form-control select2" disabled><option value="">All Subcategories</option>@foreach($parentCategories as $category)@foreach($category->children as $subcategory)<option value="{{ $subcategory->id }}" data-parent-id="{{ $category->id }}">{{ $subcategory->title }}</option>@endforeach @endforeach</select></div>
                                            <div class="col-md-4"><label class="form-label">Package</label><select id="exp_package" name="package_id" class="form-control select2"><option value="">All Packages</option>@foreach($packages as $package)<option value="{{ $package->id }}" data-group-ids="{{ $package->groups->pluck('id')->implode(',') }}" data-category-id="{{ $package->category_level_1 }}" data-subcategory-id="{{ $package->category_level_2 }}">{{ $package->name }}</option>@endforeach</select></div>
                                            <div class="col-md-4">
                                                <label class="form-label">Exam</label>
                                                <select id="exp_exam" name="exam_id" class="form-control select2">
                                                    <option value="">All Exams</option>
                                                    @foreach($exams as $exam)
                                                        @php
                                                            $examGroupIds = $exam->groups->pluck('id')->merge($exam->packages->flatMap(fn ($package) => $package->groups->pluck('id')))->filter()->unique();
                                                            $examCategoryIds = collect([$exam->category_level_1])->merge($exam->packages->pluck('category_level_1'))->filter()->unique();
                                                            $examSubcategoryIds = collect([$exam->category_level_2])->merge($exam->packages->pluck('category_level_2'))->filter()->unique();
                                                        @endphp
                                                        <option value="{{ $exam->id }}"
                                                            data-group-ids="{{ $examGroupIds->implode(',') }}"
                                                            data-package-ids="{{ $exam->packages->pluck('id')->implode(',') }}"
                                                            data-category-ids="{{ $examCategoryIds->implode(',') }}"
                                                            data-subcategory-ids="{{ $examSubcategoryIds->implode(',') }}">{{ $exam->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4"><label class="form-label">Subject</label><select id="exp_subject" name="subject_id" class="form-control select2"><option value="">All Subjects</option>@foreach($subjects as $subject)<option value="{{ $subject->id }}" data-group-ids="{{ $subject->groups->pluck('id')->implode(',') }}">{{ $subject->subject_name }}</option>@endforeach</select></div>
                                            <div class="col-md-4"><label class="form-label">Topic</label><select id="exp_topic" name="topic_id" class="form-control select2" disabled><option value="">All Topics</option></select></div>
                                            <div class="col-md-4"><label class="form-label">Subtopic</label><select id="exp_subtopic" name="stopic_id" class="form-control select2" disabled><option value="">All Subtopics</option></select></div>
                                            <div class="col-md-4"><label class="form-label">Question section</label><select name="question_section_id" class="form-control select2"><option value="">All Sections</option>@foreach($questionSections as $section)<option value="{{ $section->id }}">{{ $section->name }}</option>@endforeach</select></div>
                                        </div>
                                        <details class="mt-4" open>
                                            <summary class="fw-semibold mb-3">Advanced question filters</summary>
                                            <div class="row g-3">
                                                <div class="col-md-4"><label class="form-label">Question type</label><select name="qtype_id" class="form-control select2"><option value="">All Types</option>@foreach($questionTypes as $type)<option value="{{ $type->id }}">{{ $type->question_type ?: $type->type }}</option>@endforeach</select></div>
                                                <div class="col-md-4"><label class="form-label">Difficulty</label><select name="diff_id" class="form-control select2"><option value="">All Difficulties</option>@foreach($difficulties as $difficulty)<option value="{{ $difficulty->id }}">{{ $difficulty->diff_level ?: $difficulty->type }}</option>@endforeach</select></div>
                                                <div class="col-md-4"><label class="form-label">Language</label><select name="language_id" class="form-control select2"><option value="">All Languages</option>@foreach($languages as $language)<option value="{{ $language->id }}">{{ $language->name }}</option>@endforeach</select></div>
                                                <div class="col-md-4"><label class="form-label">Tag</label><select name="question_tag_id" class="form-control select2"><option value="">All Tags</option>@foreach($questionTags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></div>
                                                <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-control"><option value="">Any Status</option><option value="Yes">Active</option><option value="No">Inactive</option></select></div>
                                                <div class="col-md-4"><label class="form-label">Creation method</label><select name="ai_generated" class="form-control"><option value="">AI and Manual</option><option value="yes">AI Generated</option><option value="no">Manual</option></select></div>
                                                <div class="col-md-4"><label class="form-label">Passage</label><select name="has_passage" class="form-control"><option value="">With or Without</option><option value="yes">Has Passage</option><option value="no">No Passage</option></select></div>
                                                <div class="col-md-4"><label class="form-label">Explanation</label><select name="has_explanation" class="form-control"><option value="">With or Without</option><option value="yes">Has Explanation</option><option value="no">No Explanation</option></select></div>
                                                <div class="col-md-4"><label class="form-label">Source reference</label><select name="has_source" class="form-control"><option value="">With or Without</option><option value="yes">Has Source</option><option value="no">No Source</option></select></div>
                                                <div class="col-md-4"><label class="form-label">Question code</label><input name="question_code" class="form-control" maxlength="100" placeholder="EWQ-..."></div>
                                                <div class="col-md-8"><label class="form-label">Question, hint or explanation contains</label><input name="search" class="form-control" maxlength="200" placeholder="Keyword or phrase"></div>
                                                <div class="col-md-3"><label class="form-label">Minimum marks</label><input type="number" step="0.01" name="marks_min" class="form-control"></div>
                                                <div class="col-md-3"><label class="form-label">Maximum marks</label><input type="number" step="0.01" name="marks_max" class="form-control"></div>
                                                <div class="col-md-3"><label class="form-label">Created from</label><input type="date" name="created_from" class="form-control"></div>
                                                <div class="col-md-3"><label class="form-label">Created through</label><input type="date" name="created_to" class="form-control"></div>
                                            </div>
                                        </details>
                                        <div class="row g-3 align-items-end mt-3">
                                            <div class="col-md-7"><label class="form-label">Output format</label><select name="format" class="form-control"><option value="csv" selected>Excel-compatible CSV — recommended for large exports</option><option value="xlsx">Excel XLSX — maximum 25,000 questions</option></select></div>
                                            <div class="col-md-5 d-flex gap-2"><button type="reset" id="export-reset-btn" class="btn btn-light flex-grow-1">Clear Filters</button><button type="submit" id="export-btn" class="btn el-btn-primary flex-grow-1"><i class="ri-download-line align-bottom me-1"></i> Export Questions</button></div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<script>
    $(document).ready(function() {        @if($importReport)
        const importReport = @json($importReport);
        const rows = [
            ['File', importReport.file], ['Mode', importReport.mode], ['Rows processed', importReport.processed],
            ['Questions updated', importReport.updated], ['Duplicates tagged', importReport.duplicates],
            ['Hierarchy records created', importReport.created_records], ['Warnings', (importReport.warnings || []).length],
            ['Errors', (importReport.errors || []).length]
        ].map(row => `<tr><th class="text-start pe-4">${row[0]}</th><td class="text-start">${row[1] ?? ''}</td></tr>`).join('');
        const detailItems = [...(importReport.warnings || []), ...(importReport.errors || [])];
        const detailHtml = detailItems.length ? `<hr><div class="text-start"><strong>Details</strong><ul>${detailItems.map(item => `<li>${item}</li>`).join('')}</ul></div>` : '<div class="text-success mt-2">No warnings or errors.</div>';
        Swal.fire({icon: importReport.errors?.length ? 'warning' : 'success', title: 'Import report', html: `<table class="table table-sm"><tbody>${rows}</tbody></table>${detailHtml}`, confirmButtonText: 'Close', width: 620});
        @endif
        $('.select2').select2({ width: '100%' });

        // ==========================================
        // DRAG & DROP LOGIC
        // ==========================================
        const dropzone = $('#dropzone');
        const fileInput = $('#excel_file');
        const fileLabel = $('#file-label');
        const filePreview = $('#file-preview');
        const filenameDisplay = $('#filename-display');
        const removeFileBtn = $('#remove-file');

        dropzone.on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput[0]?.click();
            }
        });

        fileInput.on('change', function() {
            handleFiles(this.files);
        });

        dropzone.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        });

        dropzone.on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
        });

        dropzone.on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            if (window.DataTransfer) {
                const transfer = new DataTransfer();
                Array.from(files).forEach(file => transfer.items.add(file));
                fileInput[0].files = transfer.files;
            }
            handleFiles(files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const fileName = files[0].name;
                filenameDisplay.text(fileName);
                dropzone.hide();
                filePreview.removeClass('d-none');
            }
        }

        removeFileBtn.on('click', function() {
            fileInput.val('');
            filePreview.addClass('d-none');
            dropzone.show();
        });

        $('#importForm').on('submit', function(e) {
            const hasFile = fileInput[0].files && fileInput[0].files.length > 0;
            const hasGoogleSheet = $.trim($('#google_sheet_url').val()).length > 0;
            if (!hasFile && !hasGoogleSheet) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Select an import source',
                    text: 'Upload an Excel/CSV file or enter a Google Sheet URL.'
                });
                return;
            }

            const file = hasFile ? fileInput[0].files[0] : null;
            if (!file || hasGoogleSheet || file.size <= 5 * 1024 * 1024) return;
            e.preventDefault();
            if (!/\.csv$/i.test(file.name)) {
                Swal.fire('Use CSV for a large import', 'XLSX remains available up to 5MB. Save a larger workbook as CSV first.', 'warning');
                return;
            }
            startLargeImport(file).catch(showLargeImportError);
        });

        const largeImport = {
            initialize: @json(Route::has('questions.large-import.initialize') ? route('questions.large-import.initialize') : null),
            chunk: @json(Route::has('questions.large-import.chunk') ? route('questions.large-import.chunk', '__UPLOAD_ID__') : null),
            finalize: @json(Route::has('questions.large-import.finalize') ? route('questions.large-import.finalize', '__UPLOAD_ID__') : null),
            status: @json(Route::has('questions.large-import.status') ? route('questions.large-import.status', '__UPLOAD_ID__') : null)
        };
        const csrfToken = $('#importForm input[name="_token"]').val();

        async function requestJson(url, options = {}) {
            options.headers = Object.assign({'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken}, options.headers || {});
            const response = await fetch(url, options);
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = body.errors ? Object.values(body.errors).flat()[0] : null;
                throw new Error(validation || body.message || `Request failed (${response.status})`);
            }
            return body;
        }

        async function startLargeImport(file) {
            if (!largeImport.initialize || !largeImport.chunk || !largeImport.finalize || !largeImport.status) {
                throw new Error('Large imports are not enabled on this staging deployment. Please use a CSV smaller than 5 MB.');
            }
            const chunkSize = 512 * 1024;
            const totalChunks = Math.ceil(file.size / chunkSize);
            setLargeImportUi('Uploading CSV', 0, 'Keep this page open until all file chunks reach the server.');
            $('#import-submit-button, #remove-file').prop('disabled', true);
            const initialized = await requestJson(largeImport.initialize, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({original_name: file.name, total_bytes: file.size, total_chunks: totalChunks, import_mode: $('#import_mode').val()})
            });
            const uploadId = initialized.upload_id;
            for (let index = 0; index < totalChunks; index++) {
                const form = new FormData();
                form.append('chunk_index', index);
                form.append('chunk', file.slice(index * chunkSize, Math.min(file.size, (index + 1) * chunkSize)), `${file.name}.part`);
                await retryRequest(() => requestJson(largeImport.chunk.replace('__UPLOAD_ID__', uploadId), {method: 'POST', body: form}));
                const percent = Math.round(((index + 1) / totalChunks) * 100);
                setLargeImportUi('Uploading CSV', percent, `Uploaded ${index + 1} of ${totalChunks} chunks.`);
            }
            setLargeImportUi('Preparing import', 100, 'Upload complete. The server is verifying the file.');
            await requestJson(largeImport.finalize.replace('__UPLOAD_ID__', uploadId), {method: 'POST'});
            localStorage.setItem('questionLargeImportId', uploadId);
            await pollLargeImport(uploadId);
        }

        async function retryRequest(callback) {
            let lastError;
            for (let attempt = 1; attempt <= 3; attempt++) {
                try { return await callback(); } catch (error) {
                    lastError = error;
                    if (attempt < 3) await new Promise(resolve => setTimeout(resolve, attempt * 1000));
                }
            }
            throw lastError;
        }

        async function pollLargeImport(uploadId) {
            const data = await requestJson(largeImport.status.replace('__UPLOAD_ID__', uploadId));
            const total = Number(data.total_rows || 0);
            const processed = Number(data.processed_rows || 0);
            const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
            const labels = {queued: 'Queued for import', starting: 'Starting import', processing: 'Importing questions', completed: 'Import completed', failed: 'Import failed'};
            setLargeImportUi(labels[data.status] || 'Preparing import', data.status === 'completed' ? 100 : percent,
                data.message || (data.status === 'queued' ? 'The background importer will start within one minute.' : `${processed.toLocaleString()} of ${total.toLocaleString()} rows processed.`));
            $('#large-import-counts').removeClass('d-none').text(`Processed: ${processed.toLocaleString()} | Imported: ${Number(data.imported_rows).toLocaleString()} | Updated: ${Number(data.updated_rows).toLocaleString()} | Duplicates: ${Number(data.duplicate_rows).toLocaleString()} | Failed: ${Number(data.failed_rows).toLocaleString()}`);
            if (data.error_report_url) $('#large-import-errors').attr('href', data.error_report_url).removeClass('d-none');
            if (data.status === 'completed' || data.status === 'failed') {
                localStorage.removeItem('questionLargeImportId');
                $('#import-submit-button, #remove-file').prop('disabled', false);
                $('#large-import-bar').removeClass('progress-bar-animated');
                if (data.status === 'completed') Swal.fire('Import completed', `${processed.toLocaleString()} rows were processed.`, data.failed_rows ? 'warning' : 'success');
                return;
            }
            setTimeout(() => pollLargeImport(uploadId).catch(showLargeImportError), 5000);
        }

        function setLargeImportUi(title, percent, message) {
            $('#large-import-progress').removeClass('d-none');
            $('#large-import-title').text(title);
            $('#large-import-percent').text(`${percent}%`);
            $('#large-import-bar').css('width', `${percent}%`);
            $('#large-import-message').text(message);
        }

        function showLargeImportError(error) {
            $('#import-submit-button, #remove-file').prop('disabled', false);
            $('#large-import-title').text('Import stopped');
            $('#large-import-message').text(error.message || 'The import could not continue.');
            Swal.fire('Import stopped', error.message || 'The import could not continue.', 'error');
        }

        const activeLargeImport = localStorage.getItem('questionLargeImportId');
        if (activeLargeImport) pollLargeImport(activeLargeImport).catch(() => localStorage.removeItem('questionLargeImportId'));


        // ==========================================
        // EXPORT DROPDOWN LOGIC
        // ==========================================
        function filterExportPackages() {
            const groupId = String($('#exp_group').val() || '');
            const categoryId = String($('#exp_category').val() || '');
            const subcategoryId = String($('#exp_category_2').val() || '');
            $('#exp_package option[value!=""]').each(function() {
                const groupIds = String($(this).data('group-ids') || '').split(',').filter(Boolean);
                const groupMatch = !groupId || groupIds.includes(groupId);
                const categoryMatch = !categoryId || String($(this).data('category-id') || '') === categoryId;
                const subcategoryMatch = !subcategoryId || String($(this).data('subcategory-id') || '') === subcategoryId;
                $(this).prop('disabled', !(groupMatch && categoryMatch && subcategoryMatch));
            });
            if ($('#exp_package option:selected').prop('disabled')) $('#exp_package').val('').trigger('change.select2');
            filterExportExams();
        }

        function filterExportExams() {
            const selected = {
                group: String($('#exp_group').val() || ''),
                category: String($('#exp_category').val() || ''),
                subcategory: String($('#exp_category_2').val() || ''),
                package: String($('#exp_package').val() || '')
            };
            const matches = function(option, key, attribute) {
                if (!selected[key]) return true;
                return String(option.data(attribute) || '').split(',').filter(Boolean).includes(selected[key]);
            };

            $('#exp_exam option[value!=""]').each(function() {
                const option = $(this);
                option.prop('disabled', !(
                    matches(option, 'group', 'group-ids') &&
                    matches(option, 'category', 'category-ids') &&
                    matches(option, 'subcategory', 'subcategory-ids') &&
                    matches(option, 'package', 'package-ids')
                ));
            });
            if ($('#exp_exam option:selected').prop('disabled')) $('#exp_exam').val('').trigger('change.select2');
        }

        function filterExportHierarchy() {
            const groupId = String($('#exp_group').val() || '');
            $('#exp_category option[value!=""]').each(function() {
                const groupIds = String($(this).data('group-ids') || '').split(',').filter(Boolean);
                $(this).prop('disabled', Boolean(groupId) && groupIds.length > 0 && !groupIds.includes(groupId));
            });
            if ($('#exp_category option:selected').prop('disabled')) $('#exp_category').val('').trigger('change.select2');

            $('#exp_subject option[value!=""]').each(function() {
                const groupIds = String($(this).data('group-ids') || '').split(',').filter(Boolean);
                $(this).prop('disabled', Boolean(groupId) && !groupIds.includes(groupId));
            });
            if ($('#exp_subject option:selected').prop('disabled')) {
                $('#exp_subject').val('').trigger('change.select2');
                resetSelect('#exp_topic');
                resetSelect('#exp_subtopic');
            }

            const categoryId = String($('#exp_category').val() || '');
            $('#exp_category_2 option[value!=""]').each(function() {
                $(this).prop('disabled', !categoryId || String($(this).data('parent-id')) !== categoryId);
            });
            $('#exp_category_2').prop('disabled', !categoryId);
            if ($('#exp_category_2 option:selected').prop('disabled') || !categoryId) $('#exp_category_2').val('').trigger('change.select2');
            filterExportPackages();
        }

        $('#exp_group').on('change', function() {
            filterExportHierarchy();
            fetchTopics($('#exp_subject').val(), '#exp_topic', '#exp_subtopic');
        });
        $('#exp_category').on('change', filterExportHierarchy);
        $('#exp_category_2').on('change', filterExportPackages);
        $('#exp_package').on('change', filterExportExams);

        $('#exp_subject').change(function() {
            fetchTopics($(this).val(), '#exp_topic', '#exp_subtopic');
        });

        $('#exp_topic').change(function() {
            fetchSubtopics($(this).val(), '#exp_subtopic');
        });

        $('#question-export-form').on('submit', function(e) {
            const min = parseFloat($(this).find('[name="marks_min"]').val());
            const max = parseFloat($(this).find('[name="marks_max"]').val());
            if (!Number.isNaN(min) && !Number.isNaN(max) && max < min) {
                e.preventDefault();
                Swal.fire('Check marks range', 'Maximum marks cannot be lower than minimum marks.', 'warning');
                return;
            }

            const button = $('#export-btn');
            button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Preparing export...');
            window.setTimeout(function() {
                button.prop('disabled', false).html('<i class="ri-download-line align-bottom me-1"></i> Export Questions');
            }, 8000);
        });

        $('#export-reset-btn').on('click', function() {
            window.setTimeout(function() {
                $('#question-export-form .select2').val('').trigger('change.select2');
                filterExportHierarchy();
                resetSelect('#exp_topic');
                resetSelect('#exp_subtopic');
            }, 0);
        });
        filterExportHierarchy();

        // Helper Functions
        function fetchTopics(subjectId, topicSelector, subtopicSelector) {
            if (subjectId) {
                $.ajax({
                    url: '/get-topics-by-subject/' + subjectId,
                    type: 'GET',
                    data: { group_id: $('#exp_group').val() || '' },
                    success: function(data) {
                        $(topicSelector).empty().append('<option value="">Select Topic</option>');
                        $.each(data, function(key, value) {
                            $(topicSelector).append('<option value="' + value.id + '">' + value.name + '</option>');
                        });
                        $(topicSelector).prop('disabled', false);
                    }
                });
            } else {
                resetSelect(topicSelector);
                resetSelect(subtopicSelector);
            }
        }

        function fetchSubtopics(topicId, subtopicSelector) {
            if (topicId) {
                $.ajax({
                    url: '/get-subtopics-by-topic/' + topicId,
                    type: 'GET',
                    success: function(data) {
                        $(subtopicSelector).empty().append('<option value="">Select Subtopic</option>');
                        $.each(data, function(key, value) {
                            $(subtopicSelector).append('<option value="' + value.id + '">' + value.name + '</option>');
                        });
                        $(subtopicSelector).prop('disabled', false);
                    }
                });
            } else {
                resetSelect(subtopicSelector);
            }
        }

        function resetSelect(selector) {
            $(selector).empty().append('<option value="">Select...</option>').prop('disabled', true);
        }
    });
</script>
@endsection
