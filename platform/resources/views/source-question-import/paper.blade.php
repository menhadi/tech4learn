@extends('layouts.master')
@section('title', $mode === 'edit' ? 'Edit extracted paper' : 'View extracted paper')
@section('content')
@php
    $meta = fn ($item, $key) => trim((string) (($item->payload['metadata'][$key] ?? $item->metadata[$key] ?? '') ?: ''));
    $label = collect(['group','category','exam','package','subject'])->map(fn($key) => $meta($items->first(), $key))->filter()->implode(' / ');
$renderMath = function ($value) {
        $content = app(\App\Services\MathContentNormalizer::class)->normalize((string) $value)['content'];
        // Legacy inline $$...$$ from older runs is converted to inline delimiters when surrounded by text.
        return preg_replace('/(?<=\S)\$\$(.+?)\$\$(?=\S)/s', '\\($1\\)', $content) ?? $content;
    };
@endphp
@include('partials.student-mathjax')
@component('components.breadcrumb') @slot('li_1','Academic') @slot('title', ($mode === 'edit' ? 'Edit' : 'View').' extracted paper') @endcomponent
<div class="card mb-3"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><h3 class="mb-1">{{ $label ?: 'Filtered paper' }}</h3><div class="text-muted">Run #{{ $run->id }} · {{ $items->count() }} question(s) selected. Each source URL stays beside its question.</div></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route('source-question-import.show', array_merge([$run], request()->query())) }}"><i class="ri-arrow-left-line me-1"></i>Back to run</a>@if($mode === 'edit')<a class="btn btn-outline-primary" target="_blank" href="{{ route('source-question-import.paper.preview', array_merge([$run], request()->query())) }}"><i class="ri-eye-line me-1"></i>View full paper</a>@else<a class="btn btn-primary" href="{{ route('source-question-import.paper.edit', array_merge([$run], request()->query())) }}"><i class="ri-edit-line me-1"></i>Edit full paper</a>@endif</div>
</div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($items->isEmpty())<div class="alert alert-info">No questions match the selected filters.</div>@endif
<div class="source-paper-list">
@foreach($items as $index => $item)
    @php $payload = is_array($item->payload) ? $item->payload : []; $options = array_values($payload['options'] ?? []); @endphp
    <article class="card mb-3 source-paper-row" id="source-item-{{ $item->id }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><strong>Question {{ $index + 1 }} · CSV row {{ $item->row_number }}</strong><span class="badge bg-light text-dark border">{{ $item->status }}</span></div>
        <div class="card-body"><div class="row g-3 align-items-start">
            <div class="col-xl-4"><div class="source-url-pane"><div class="small text-uppercase text-muted fw-semibold mb-2">Source URL</div><a href="{{ $item->source_url }}" target="_blank" rel="noopener" class="source-url">{{ $item->source_url }}</a><div class="small text-muted mt-3">{{ $meta($item,'group') }} @if($meta($item,'exam')) · {{ $meta($item,'exam') }} @endif @if($meta($item,'category')) · {{ $meta($item,'category') }} @endif</div>@if($item->question_id)<a class="btn btn-sm btn-outline-success mt-3" href="{{ route('questions.edit', $item->question_id) }}"><i class="ri-edit-line me-1"></i>Open normal question editor</a>@endif</div></div>
            <div class="col-xl-8">@if($mode === 'edit' && !$item->question_id)
                <form method="POST" action="{{ route('source-question-import.item.update', $item) }}" data-disable-ai-inline>@csrf @method('PATCH')
                    <label class="form-label fw-semibold">Question</label><textarea class="form-control mb-3" name="question" rows="4" required>{{ $payload['question'] ?? '' }}</textarea>
                    <div class="row g-2">@foreach(range(1,6) as $optionIndex)<div class="col-md-6"><label class="form-label">Option {{ chr(64+$optionIndex) }}</label><textarea class="form-control" name="option{{ $optionIndex }}" rows="2">{{ $options[$optionIndex-1] ?? '' }}</textarea></div>@endforeach</div>
                    <div class="row g-2 mt-1"><div class="col-md-6"><label class="form-label">Answer</label><textarea class="form-control" name="answer" rows="2">{{ $payload['answer'] ?? '' }}</textarea></div><div class="col-md-6"><label class="form-label">Explanation</label><textarea class="form-control" name="explanation" rows="2">{{ $payload['explanation'] ?? '' }}</textarea></div></div>
                    <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save question draft</button><a class="btn btn-outline-secondary" href="{{ route('source-question-import.item', $item) }}">Open individual view</a></div>
                </form>
            @else
                <div class="question-content math-content">{!! $renderMath($payload['question'] ?? $item->question?->question ?? '') !!}</div><ol class="mt-3 math-content">@foreach($options as $option)<li class="mb-2">{!! $renderMath($option) !!}</li>@endforeach</ol><div class="small text-muted">Marks: {{ $payload['marks'] ?? $item->question?->marks ?? '' }} · Negative: {{ $payload['negative_marks'] ?? $item->question?->negative_marks ?? '' }}</div><div class="d-flex flex-wrap gap-2 mt-3 question-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('source-question-import.item', $item) }}"><i class="ri-eye-line me-1"></i>View question</a>@if($item->question_id)<a class="btn btn-sm btn-primary" href="{{ route('questions.edit', $item->question_id) }}"><i class="ri-edit-line me-1"></i>Edit question</a>@else<a class="btn btn-sm btn-primary" href="{{ route('source-question-import.paper.edit', array_merge([$run], request()->query(), ['focus' => $item->id])) }}#source-item-{{ $item->id }}"><i class="ri-edit-line me-1"></i>Edit question</a>@endif</div>
            @endif</div>
        </div></div>
    </article>
@endforeach
</div>
<style>.source-paper-row{scroll-margin-top:20px}.source-url-pane{position:sticky;top:1rem;border:1px solid #dbe5ee;border-radius:.75rem;padding:1rem;background:#f8fbfd}.source-url{display:block;overflow-wrap:anywhere;line-height:1.45}.question-content{white-space:normal;border:1px solid #e5e7eb;border-radius:.5rem;padding:1rem;min-height:80px}@media(max-width:1199px){.source-url-pane{position:static}}.math-content{overflow-wrap:anywhere}.math-content img{max-width:100%;height:auto}.question-actions .btn{min-width:140px}.ai-inline-generate-btn,.ai-inline-content-btn{display:none!important}</style>
@endsection
