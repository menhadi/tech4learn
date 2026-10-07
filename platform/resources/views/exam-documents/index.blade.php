@extends('layouts.master')

@section('title', 'Translations & PDFs')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Exams & Questions')
    @slot('title', 'Translations & PDFs')
@endcomponent

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h4 class="mb-1">Exam translations and PDF library</h4>
                <p class="text-muted mb-0">Translate and approve content, then build the cached question paper and solution. Students only receive the latest approved file.</p>
            </div>
            <span class="badge bg-light text-dark border">Admin controlled</span>
        </div>

        <form method="get" class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Group</label>
                <select name="group" class="form-select">
                    <option value="">All groups</option>
                    @foreach($groups as $value)
                        <option value="{{ $value->id }}" @selected((string) request('group') === (string) $value->id)>{{ $value->group_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach($categories as $value)
                        <option value="{{ $value->id }}" @selected((string) request('category') === (string) $value->id)>{{ $value->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Subcategory</label>
                <select name="subcategory" class="form-select">
                    <option value="">All subcategories</option>
                    @foreach($subcategories as $value)
                        <option value="{{ $value->id }}" @selected((string) request('subcategory') === (string) $value->id)>{{ $value->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Package</label>
                <select name="package" class="form-select">
                    <option value="">All packages</option>
                    @foreach($packages as $value)
                        <option value="{{ $value->id }}" @selected((string) request('package') === (string) $value->id)>{{ $value->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Exam</label>
                <select name="exam" class="form-select">
                    <option value="">All exams</option>
                    @foreach($examOptions as $value)
                        <option value="{{ $value->id }}" @selected((string) request('exam') === (string) $value->id)>{{ $value->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Language</label>
                <select name="language" class="form-select">
                    <option value="">All languages</option>
                    @foreach($languages as $value)
                        <option value="{{ $value->id }}" @selected((string) request('language') === (string) $value->id)>{{ $value->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="form-label">Rows</label>
                <select name="per_page" class="form-select">
                    @foreach([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('exam-documents.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>


@php
    $preservedFilters = request()->except([
        'translation_status',
        'translation_search',
        'translation_sort',
        'pdf_status',
        'pdf_search',
        'pdf_sort',
        'pdf_document_type',
        'tab',
        'page',
    ]);

    $translationTabActive = $activeTab === 'translation';
    $translationStatusClass = [
        'pending' => 'secondary',
        'processing' => 'info',
        'ready' => 'success',
        'failed' => 'danger',
        'approved' => 'success',
        'awaiting_approval' => 'warning',
    ];
    $pdfStatusClass = [
        'not_built' => 'secondary',
        'queued' => 'warning',
        'processing' => 'info',
        'ready' => 'success',
        'failed' => 'danger',
    ];
@endphp

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $translationTabActive ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#translation-table" type="button" role="tab" aria-selected="{{ $translationTabActive ? 'true' : 'false' }}">Translation ({{ $translationRows->count() }})</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ ! $translationTabActive ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pdf-table" type="button" role="tab" aria-selected="{{ ! $translationTabActive ? 'true' : 'false' }}">PDF ({{ $pdfRows->count() }})</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ $translationTabActive ? 'show active' : '' }}" id="translation-table" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="translation_search" class="form-control" value="{{ $translationSearch }}" placeholder="Search exam or language">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Status</label>
                        <select name="translation_status" class="form-select">
                            <option value="">Needs action (default)</option>
                            <option value="pending" @selected($translationStatusFilter === 'pending')>Pending</option>
                            <option value="processing" @selected($translationStatusFilter === 'processing')>Processing</option>
                            <option value="failed" @selected($translationStatusFilter === 'failed')>Failed</option>
                            <option value="ready" @selected($translationStatusFilter === 'ready')>Ready</option>
                            <option value="approved" @selected($translationStatusFilter === 'approved')>Approved</option>
                            <option value="awaiting_approval" @selected($translationStatusFilter === 'awaiting_approval')>Awaiting approval</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Sort</label>
                        <select name="translation_sort" class="form-select">
                            <option value="exam" @selected($translationSort === 'exam')>Exam</option>
                            <option value="language" @selected($translationSort === 'language')>Language</option>
                            <option value="status" @selected($translationSort === 'status')>Status</option>
                            <option value="status_desc" @selected($translationSort === 'status_desc')>Status (desc)</option>
                            <option value="updated_desc" @selected($translationSort === 'updated_desc')>Last updated</option>
                        </select>
                    </div>
                    @foreach($preservedFilters as $key => $value)
                        @if($value !== null && $value !== '' && ! is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="tab" value="translation">
                    <div class="col-xl-2 col-md-4 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="{{ route('exam-documents.index', array_merge($preservedFilters, ['tab' => 'translation', 'per_page' => request('per_page', 25)])) }}">Reset table</a>
                        <a class="btn btn-outline-dark" href="{{ route('exam-documents.index', ['tab' => 'translation', 'per_page' => request('per_page', 25)]) }}">Reset all</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Language</th>
                                <th>Translation status</th>
                                <th>Approval</th>
                                <th>Last updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($translationRows as $row)
                                @php
                                    $pivot = $row['pivot'];
                                    $statusClass = $translationStatusClass[$row['translation_status']] ?? 'secondary';
                                    $approvalClass = $row['translation_approved'] ? 'success' : 'warning';
                                    $approvalText = $row['translation_approved'] ? 'Approved' : 'Awaiting approval';
                                    $needsAction = $row['needs_translation'];
                                @endphp
                                <tr>
                                    <td>{{ $row['exam_name'] }}</td>
                                    <td>{{ $row['language_name'] }} <small class="text-muted">({{ strtoupper((string) $row['language_code']) }})</small></td>
                                    <td>
                                        <span class="badge bg-{{ $statusClass }}">{{ str_replace('_', ' ', $row['translation_status']) }}</span>
                                        @if($needsAction)
                                            <span class="badge bg-danger">Need action</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $approvalClass }}">{{ $approvalText }}</span></td>
                                    <td>{{ optional($row['updated_at'])->format('M d, Y H:i') ?: '—' }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <form method="post" action="{{ route('exam-documents.translate', [$row['exam'], $row['language']]) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary">Translate / refresh</button>
                                            </form>

                                            <form method="post" action="{{ route('exam-documents.approve', [$row['exam'], $row['language']]) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" @disabled($row['translation_status'] !== 'ready')>Approve</button>
                                            </form>

                                            <form method="post" action="{{ route('exam-documents.automation', [$row['exam'], $row['language']]) }}" class="d-flex gap-2 align-items-center">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="auto_translate" value="0">
                                                <label class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="auto_translate" value="1" @checked($pivot?->auto_translate)>
                                                    <span class="form-check-label">Auto translate</span>
                                                </label>
                                                <input type="hidden" name="auto_pdf" value="0">
                                                <label class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="auto_pdf" value="1" @checked($pivot?->auto_pdf)>
                                                    <span class="form-check-label">Auto PDF</span>
                                                </label>
                                                <button class="btn btn-sm btn-outline-dark">Save automation</button>
                                            </form>
                                        </div>
                                        @if($pivot?->last_error)
                                            <div class="text-danger small mt-2">{{ \Illuminate\Support\Str::limit($pivot->last_error, 140) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted py-3">No translation rows match your current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ ! $translationTabActive ? 'show active' : '' }}" id="pdf-table" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="pdf_search" class="form-control" value="{{ $pdfSearch }}" placeholder="Search paper/language/package">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Status</label>
                        <select name="pdf_status" class="form-select">
                            <option value="">Needs action (default)</option>
                            <option value="not_built" @selected($pdfStatusFilter === 'not_built')>Not built</option>
                            <option value="queued" @selected($pdfStatusFilter === 'queued')>Queued</option>
                            <option value="processing" @selected($pdfStatusFilter === 'processing')>Processing</option>
                            <option value="failed" @selected($pdfStatusFilter === 'failed')>Failed</option>
                            <option value="ready" @selected($pdfStatusFilter === 'ready')>Ready</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Document</label>
                        <select name="pdf_document_type" class="form-select">
                            <option value="">All documents</option>
                            <option value="questions" @selected($pdfDocumentTypeFilter === 'questions')>Question paper</option>
                            <option value="solutions" @selected($pdfDocumentTypeFilter === 'solutions')>Solution</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Sort</label>
                        <select name="pdf_sort" class="form-select">
                            <option value="updated_desc" @selected($pdfSort === 'updated_desc')>Last updated</option>
                            <option value="updated" @selected($pdfSort === 'updated')>Last updated (asc)</option>
                            <option value="package" @selected($pdfSort === 'package')>Package</option>
                            <option value="document" @selected($pdfSort === 'document')>Document</option>
                            <option value="status" @selected($pdfSort === 'status')>Status</option>
                            <option value="status_desc" @selected($pdfSort === 'status_desc')>Status (desc)</option>
                        </select>
                    </div>
                    @foreach($preservedFilters as $key => $value)
                        @if($value !== null && $value !== '' && ! is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="tab" value="pdf">
                    <div class="col-xl-2 col-md-4 d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1">Apply</button>
                        <a class="btn btn-outline-secondary" href="{{ route('exam-documents.index', array_merge($preservedFilters, ['tab' => 'pdf', 'per_page' => request('per_page', 25)])) }}">Reset table</a>
                        <a class="btn btn-outline-dark" href="{{ route('exam-documents.index', ['tab' => 'pdf', 'per_page' => request('per_page', 25)]) }}">Reset all</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Package</th>
                                <th>Language</th>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Last updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pdfRows as $row)
                                @php
                                    $status = $row['status'];
                                    $statusClass = $pdfStatusClass[$status] ?? 'secondary';
                                    $isReady = $status === 'ready' && (!empty($row['build']) && is_file((string) $row['build']->current_path));
                                    $canGenerate = true;
                                    if (strtolower((string) $row['language_code']) !== 'en') {
                                        $canGenerate = (bool) ($row['pivot']?->translation_approved_at);
                                    }

                                    if ($row['download_route'] === 'exams.solutionPdf') {
                                        $downloadUrl = route('exams.solutionPdf', [
                                            'exam' => $row['exam']->slug ?: $row['exam']->id,
                                            'package' => $row['package']->slug ?: $row['package']->id,
                                            'lang' => $row['language_id'],
                                        ]);
                                    } else {
                                        $downloadUrl = route('exam.print.download', [
                                            'id' => $row['exam']->slug ?: $row['exam']->id,
                                            'package' => $row['package']->slug ?: $row['package']->id,
                                            'lang' => $row['language_id'],
                                        ]);
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $row['exam_name'] }}</td>
                                    <td>{{ $row['package_name'] }}</td>
                                    <td>{{ $row['language_name'] }} <small class="text-muted">({{ strtoupper((string) $row['language_code']) }})</small></td>
                                    <td>{{ $row['document_label'] }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusClass }}">{{ str_replace('_', ' ', $status) }}</span>
                                    </td>
                                    <td>{{ optional($row['updated_at'])->format('M d, Y H:i') ?: '—' }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <form method="post" action="{{ route('exam-documents.generate', [$row['exam'], $row['language']]) }}">
                                                @csrf
                                                <input type="hidden" name="package_id" value="{{ $row['package_id'] }}">
                                                <input type="hidden" name="document_type" value="{{ $row['document_type'] }}">
                                                <button class="btn btn-sm btn-outline-dark" @disabled(!$canGenerate)>Generate / refresh</button>
                                            </form>

                                            @if($isReady)
                                                <a href="{{ $downloadUrl }}" class="btn btn-sm btn-outline-success" target="_blank">Download</a>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary" disabled>Not ready</button>
                                            @endif
                                        </div>
                                        @if(! empty($row['build']?->last_error))
                                            <div class="text-danger small mt-2">{{ \Illuminate\Support\Str::limit($row['build']->last_error, 140) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted py-3">No PDF rows match your current filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{ $exams->links() }}
@endsection




