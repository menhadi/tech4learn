@php
    $current = $original[$field] ?? null;
    $value = array_key_exists($field, $payload) ? $payload[$field] : $current;
    $isJson = $field === 'correct_option_indices';
    if ($isJson) {
        $current = $current ? json_encode($current, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '[]';
        $value = $value ? json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '[]';
    }
    $changed = in_array($field, (array) $draft->changed_fields, true);
    $editorId = 'paper-field-'.$draft->id.'-'.$field;
    $previewId = 'paper-preview-'.$draft->id.'-'.$field;
    $canCrop = $hasPdfSource && in_array($field, ['question','option1','option2','option3','option4','option5','option6','explanation'], true);
    $hasImage = $canCrop && is_string($value) && stripos($value, '<img') !== false;
@endphp
<div class="border rounded p-3 mb-3 {{ $changed ? 'border-warning' : '' }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <strong>{{ $label }}</strong>
        <div class="d-flex align-items-center gap-2">
            @if($canCrop)
                <button type="button" class="btn btn-sm btn-outline-primary crop-field-trigger" aria-pressed="false" data-crop-into="{{ $field }}" data-crop-action="{{ route('exams.paper.questions.crop', [$exam, $draft]) }}" data-crop-page-url="{{ route('exams.paper.questions.source-page', [$exam, $draft]) }}" data-crop-page="{{ $item['crop_page'] }}" data-editor-selector="#{{ $editorId }}"><i class="ri-crop-line me-1"></i>Crop into this field</button>
                <button type="button" class="btn btn-sm btn-outline-danger remove-field-image {{ $hasImage ? '' : 'd-none' }}" data-remove-image data-remove-url="{{ route('exams.paper.questions.remove-image', [$exam, $draft]) }}" data-remove-field="{{ $field }}" data-editor-selector="#{{ $editorId }}"><i class="ri-delete-bin-line me-1"></i>Remove image</button>
            @endif
            @if($changed)<span class="badge bg-warning-subtle text-warning">Draft change</span>@endif
        </div>
    </div>
    <details class="mb-2"><summary class="small text-muted" style="cursor:pointer">Show current live value</summary><div class="form-control bg-light paper-live-value mt-2" style="white-space:pre-wrap">{!! $isJson ? e($current) : ($current ?: '<span class="text-muted">Empty</span>') !!}</div></details>
    <textarea id="{{ $editorId }}" class="form-control" rows="{{ in_array($field, ['question','explanation'], true) ? 5 : 2 }}" name="proposed[{{ $field }}]" @if(!$isJson) data-paper-preview="{{ $previewId }}" data-active-crop-field="{{ $field }}" @endif>{{ $value }}</textarea>
    @if(!$isJson)<div class="small text-muted mt-2">Rendered draft</div><div class="border rounded p-2 mt-1 paper-field-preview" id="{{ $previewId }}">{!! $value ?: '<span class="text-muted">Empty</span>' !!}</div>@endif
</div>
