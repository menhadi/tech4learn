@if(session()->has('exam_import_summary'))
    @php
        $importSummary = session('exam_import_summary');
        $processed = $importSummary['created'] + $importSummary['updated'] + $importSummary['failed'];
        $importMetrics = [
            'Rows processed' => $processed,
            'Created' => $importSummary['created'],
            'Updated' => $importSummary['updated'],
            'Failed' => $importSummary['failed'],
            'Question PDFs' => $importSummary['question_pdfs'],
            'Answer PDFs' => $importSummary['answer_pdfs'],
            'Combined PDFs' => $importSummary['combined_pdfs'],
            'PDFs removed' => $importSummary['pdf_removals'],
        ];
    @endphp
    <div class="alert {{ $importSummary['failed'] ? 'alert-warning' : 'alert-success' }} border-0 mb-4" role="alert">
        <h5 class="alert-heading mb-1">
            <i class="{{ $importSummary['failed'] ? 'ri-error-warning-line' : 'ri-checkbox-circle-line' }} me-1"></i>
            Import completed
        </h5>
        <p class="mb-3">{{ session('warning') ?: session('success') }}</p>
        <div class="row g-2">
            @foreach($importMetrics as $label => $value)
                <div class="col-6 col-md-3">
                    <div class="bg-white bg-opacity-75 rounded p-2 h-100">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-5 fw-semibold">{{ number_format($value) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@isset($preview)
    @if(!$preview['errors'])
        <div class="alert alert-secondary border-0 mb-4">
            <strong>PDF changes planned:</strong>
            {{ number_format($preview['question_pdfs']) }} question,
            {{ number_format($preview['answer_pdfs']) }} answer,
            {{ number_format($preview['combined_pdfs']) }} combined PDF(s) attached/replaced;
            {{ number_format($preview['pdf_removals']) }} removed.
        </div>
    @endif
@endisset
