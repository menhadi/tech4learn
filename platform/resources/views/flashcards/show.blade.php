@extends('layouts.master')

@section('title', 'Manage Study Cards')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Study Cards')
    @slot('title', $flashcardSet->title)
@endcomponent

@php
    $aiGenerationAllowed = \App\Support\SaasAccess::featureEnabled('ai_flashcard_generation')
        && (bool) ($flashcardSet->package?->ai_flashcard_generation_enabled);
@endphp

<style>
    .flashcard-detail-panel { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 20px rgba(15,23,42,.05); }
    .flashcard-detail-panel .table thead th { background:var(--el-table-header-bg, color-mix(in srgb, var(--el-primary) 10%, #fff)); color:var(--el-heading); font-weight:700; }
    .flashcard-body-preview { max-width:720px; white-space:normal; color:var(--el-heading); }
    .flashcard-body-preview__front { font-weight:700; }
    .flashcard-body-preview__back,
    .flashcard-body-preview__question { margin-top:5px; color:var(--el-muted); font-size:13px; }
    .flashcard-body-preview__label { color:var(--el-primary); font-weight:800; }
    .flashcard-ai-panel { background:color-mix(in srgb, var(--el-primary) 8%, #fff); border:1px solid var(--el-border); border-radius:8px; padding:14px; color:var(--el-heading); }
    .flashcard-source-badge { display:inline-flex; align-items:center; gap:5px; max-width:220px; padding:5px 9px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .flashcard-detail-panel .btn { box-shadow:none !important; border-radius:6px; font-weight:700; min-height:42px; min-width:118px; padding:10px 18px; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .flashcard-detail-panel .dropdown .btn { min-width:auto; }
    .flashcard-action-row .btn,
    .flashcard-bulk-row .btn { min-height:40px; }
    .flashcard-type-pill { display:inline-flex; align-items:center; padding:5px 9px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-size:12px; font-weight:800; white-space:nowrap; }
    .flashcard-bulk-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--el-border); }
    .flashcard-per-page { min-width:150px; }
    .flashcard-question-bank { background:#fff; border:1px solid var(--el-border); border-radius:8px; margin:16px; overflow:hidden; }
    .flashcard-question-bank__head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--el-border); background:var(--el-primary-soft); }
    .flashcard-question-bank__filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; padding:16px; border-bottom:1px solid var(--el-border); }
    .flashcard-question-bank__actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--el-border); }
    .flashcard-question-bank__actions .btn { min-width:120px; }
    .flashcard-table-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-end; padding:14px 16px; border-bottom:1px solid var(--el-border); background:#fff; }
    .flashcard-table-toolbar .flashcard-search { width:min(360px, 100%); }
    .flashcard-table-toolbar .flashcard-per-page { width:150px; }
    .flashcard-question-preview { max-width:520px; white-space:normal; color:var(--el-heading); }
    .flashcard-question-meta { color:var(--el-muted); font-size:13px; margin-top:4px; }
    .flashcard-question-table thead th { background:var(--el-table-header-bg, color-mix(in srgb, var(--el-primary) 10%, #fff)); color:var(--el-heading); font-weight:700; }
    .flashcard-create-options { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:16px; border-bottom:1px solid var(--el-border); background:#fff; }
    .flashcard-create-option { border:0; width:auto; }
    .flashcard-table-btn { min-width:84px; min-height:36px; padding:8px 12px; justify-content:center; }
    .flashcard-question-bank-toggle { border:0; color:inherit; }
    .flashcard-import-form { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:flex-end; }
    .flashcard-import-form .form-control { min-height:42px; max-width:260px; }
</style>

<div class="flashcard-detail-panel">
    <div class="d-flex justify-content-between align-items-start p-3 border-bottom">
        <div>
            <h4 class="mb-1">{{ $flashcardSet->title }}</h4>
            <div class="text-muted">
                {{ $flashcardSet->package?->name ?? 'Package' }}
                @if($flashcardSet->subject) | {{ $flashcardSet->subject->subject_name }} @endif
                @if($flashcardSet->topic) | {{ $flashcardSet->topic->name }} @endif
                @if($flashcardSet->stopic) | {{ $flashcardSet->stopic->name }} @endif
            </div>
        </div>
        <div class="d-flex gap-2 flashcard-action-row flex-wrap justify-content-end">
            <a href="{{ route('flashcards.index') }}" class="btn el-btn-secondary">
                <i class="ri-arrow-left-line me-1"></i> Back
            </a>
            <a href="{{ route('flashcards.cards.create', $flashcardSet) }}" class="btn el-btn-primary flashcard-create-option">
                <i class="ri-file-edit-line"></i>
                <span>Manual Study Card</span>
            </a>
            @if($aiGenerationAllowed)
                <button class="btn el-btn-primary" data-bs-toggle="modal" data-bs-target="#aiFlashcardModal">
                    <i class="ri-magic-line me-1"></i> Generate with AI
                </button>
            @endif
            <form action="{{ route('flashcards.cards.link-by-scope', $flashcardSet) }}" method="POST" class="d-inline" data-swal-confirm="Link matching scoped questions to study cards that do not have questions yet?">
                @csrf
                <input type="hidden" name="questions_per_card" value="1">
                <input type="hidden" name="only_empty_cards" value="1">
                <button type="submit" class="btn el-btn-secondary">
                    <i class="ri-link-m"></i> Link Questions by Scope
                </button>
            </form>
            <div class="dropdown">
                <button class="btn el-btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri-download-2-line"></i> Template
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('flashcards.cards.template', [$flashcardSet, 'format' => 'csv']) }}">Download CSV Template</a></li>
                    <li><a class="dropdown-item" href="{{ route('flashcards.cards.template', [$flashcardSet, 'format' => 'xlsx']) }}">Download Excel Template</a></li>
                </ul>
            </div>
            <div class="dropdown">
                <button class="btn el-btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri-file-download-line"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('flashcards.cards.export', [$flashcardSet, 'format' => 'csv']) }}">Export CSV</a></li>
                    <li><a class="dropdown-item" href="{{ route('flashcards.cards.export', [$flashcardSet, 'format' => 'xlsx']) }}">Export Excel</a></li>
                </ul>
            </div>
            <form action="{{ route('flashcards.cards.import', $flashcardSet) }}" method="POST" enctype="multipart/form-data" class="flashcard-import-form">
                @csrf
                <input type="file" name="card_file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                <button type="submit" class="btn el-btn-primary">
                    <i class="ri-upload-cloud-2-line"></i> Import
                </button>
            </form>
        </div>
    </div>

    @if($errors->has('ai_flashcards') || $errors->has('source_urls'))
        <div class="alert alert-danger m-3 mb-0">
            {{ $errors->first('ai_flashcards') ?: $errors->first('source_urls') }}
        </div>
    @endif

    @if(! $aiGenerationAllowed)
        <div class="flashcard-ai-panel m-3 mb-0">
            <strong>AI study card generation is not active for this package.</strong>
            <div class="small text-muted mt-1">Enable the SaaS feature and package option when you want to generate cards from PDFs, URLs, or pasted content.</div>
        </div>
    @endif

    <form action="{{ route('flashcards.cards.bulk-status', $flashcardSet) }}" method="POST" id="flashcardBulkForm">
        @csrf
        <input type="hidden" name="per_page" value="{{ $perPage ?? 50 }}">
    </form>
    <div class="flashcard-bulk-row">
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" form="flashcardBulkForm" name="bulk_action" value="activate" class="btn el-btn-primary">
                    <i class="ri-check-line me-1"></i> Activate Selected
                </button>
                <button type="submit" form="flashcardBulkForm" name="bulk_action" value="deactivate" class="btn el-btn-secondary">
                    <i class="ri-close-line me-1"></i> Deactivate Selected
                </button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted fw-bold mb-0" for="flashcardPerPage">Show</label>
                <select id="flashcardPerPage" class="form-select flashcard-per-page" onchange="window.location.href=this.value">
                    @foreach([50, 100, 500] as $size)
                        <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" {{ (int) ($perPage ?? 50) === $size ? 'selected' : '' }}>{{ $size }} cards</option>
                    @endforeach
                </select>
            </div>
        </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:44px;"><input type="checkbox" class="form-check-input" id="selectAllFlashcards"></th>
                    <th style="width:70px;">Order</th>
                    <th>Cards</th>
                    <th style="width:130px;">View Card</th>
                    <th>Type</th>
                    <th>Difficulty</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cards as $card)
                    @php
                        $linkedQuestions = $card->sourceQuestions->isNotEmpty()
                            ? $card->sourceQuestions
                            : collect([$card->sourceQuestion])->filter();
                    @endphp
                    <tr>
                        <td><input type="checkbox" form="flashcardBulkForm" name="card_ids[]" value="{{ $card->id }}" class="form-check-input flashcard-row-check"></td>
                        <td>{{ $card->sort_order }}</td>
                        <td>
                            <div class="flashcard-body-preview">
                                <div class="flashcard-body-preview__front">
                                    {{ $card->title ?: (\Illuminate\Support\Str::limit(strip_tags($card->front), 180) ?: 'Study card content') }}
                                </div>
                                @if($card->title && trim(strip_tags((string) $card->front)) !== '')
                                    <div class="flashcard-body-preview__question">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($card->front), 140) }}
                                    </div>
                                @endif
                                @if(trim(strip_tags((string) $card->back)) !== '')
                                    <div class="flashcard-body-preview__back">
                                        <span class="flashcard-body-preview__label">Back:</span>
                                        {{ \Illuminate\Support\Str::limit(strip_tags($card->back), 120) }}
                                    </div>
                                @endif
                                @if($linkedQuestions->isNotEmpty())
                                    <div class="flashcard-body-preview__question">
                                        <span class="flashcard-body-preview__label">Questions:</span>
                                        {{ $linkedQuestions->count() }} linked
                                        @foreach($linkedQuestions->take(2) as $linkedQuestion)
                                            <div>
                                                ID {{ $linkedQuestion->id }}:
                                                {{ \Illuminate\Support\Str::limit(strip_tags($linkedQuestion->question), 110) }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <a href="{{ route('flashcards.cards.edit', [$flashcardSet, $card]) }}" class="btn el-btn-secondary btn-sm flashcard-table-btn">
                                <i class="ri-eye-line"></i> View Card
                            </a>
                        </td>
                        <td>
                            @php $cardTypeLabel = ['basic' => 'Q/A', 'mcq' => 'MCQ', 'true_false' => 'True/False', 'fill_blank' => 'Fill Blank', 'multi_select' => 'Multiselect'][$card->card_type ?? 'basic'] ?? 'Q/A'; @endphp
                            <span class="flashcard-type-pill">{{ $cardTypeLabel }}</span>
                        </td>
                        <td>{{ $card->difficulty ?: '-' }}</td>
                        <td><span class="badge {{ $card->status ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $card->status ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn el-btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="ri-more-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('flashcards.cards.edit', [$flashcardSet, $card]) }}">Edit</a></li>
                                    <li>
                                        <form action="{{ route('flashcards.cards.destroy', [$flashcardSet, $card]) }}" method="POST" data-swal-confirm="Remove this study card?">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="per_page" value="{{ $perPage ?? 50 }}">
                                            <button class="dropdown-item text-danger" type="submit">Remove</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No cards added yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    <div class="p-3">{{ $cards->links() }}</div>
    </div>

@if($aiGenerationAllowed)
<div class="modal fade" id="aiFlashcardModal" tabindex="-1" aria-labelledby="aiFlashcardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" action="{{ route('flashcards.ai-generate', $flashcardSet) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-header" style="background:var(--el-primary-soft);">
                <div>
                    <h5 class="modal-title" id="aiFlashcardModalLabel">Generate Study Cards with AI</h5>
                    <div class="text-muted small">Each URL or file is processed as a separate source. Cards are drafts by default for review.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="flashcard-ai-panel mb-3">
                    <strong>Quality guidance:</strong>
                    use clean source text whenever possible. MathJax/LaTeX and chemistry notation are supported, including <code>\( x^2 \)</code> and <code>\ce{H2SO4}</code>.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cards per source</label>
                        <select name="cards_per_source" class="form-control">
                            <option value="5">5 cards</option>
                            <option value="10" selected>10 cards</option>
                            <option value="15">15 cards</option>
                            <option value="20">20 cards</option>
                            <option value="30">30 cards</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Save as</label>
                        <select name="publish_status" class="form-control">
                            <option value="draft" selected>Draft - review before publishing</option>
                            <option value="active">Active - publish immediately</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="flashcard-ai-panel mb-2">
                            AI creates the study front/back content only. Objective questions are attached automatically from this set scope.
                        </div>
                        <label class="form-label">Paste content</label>
                        <textarea name="source_text" rows="6" class="form-control" placeholder="Paste notes, chapter text, formulas, definitions, or study material here."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Source URLs</label>
                        <textarea name="source_urls" rows="4" class="form-control" placeholder="https://example.com/chapter-1&#10;https://example.com/chapter-2"></textarea>
                        <div class="form-text">Add one URL per line. Each URL creates separately sourced cards.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Upload PDF/TXT files</label>
                        <input type="file" name="source_files[]" class="form-control" multiple accept=".pdf,.txt">
                        <div class="form-text">Upload up to 5 files, 10 MB each. Text-based PDFs work best; scanned PDFs may need OCR first.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn el-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn el-btn-primary">
                    <i class="ri-magic-line me-1"></i> Generate Draft Cards
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
    document.getElementById('selectAllFlashcards')?.addEventListener('change', function () {
        document.querySelectorAll('.flashcard-row-check').forEach((checkbox) => {
            checkbox.checked = this.checked;
        });
    });

    document.getElementById('flashcardBulkForm')?.addEventListener('submit', function (event) {
        if (!document.querySelector('.flashcard-row-check:checked')) {
            event.preventDefault();
            alert('Select at least one study card first.');
        }
    });
</script>
@endpush
@endsection
