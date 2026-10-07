@extends('layouts.master')
@section('title', 'Official Exam Monitor')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h3 class="mb-1">Official Exam Monitor</h3><p class="text-muted mb-0">Create inactive exam drafts only when configured official sources publish a new paper.</p></div>
        <a href="{{ route('official-exam-sources.create') }}" class="btn btn-primary"><i class="ri-add-line me-1"></i>Add source profile</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Website / profile</th><th>Driver</th><th>Schedule</th><th>Rules</th><th>Discoveries</th><th>Attention</th><th>Last check</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($sources as $source)
            <tr>
                <td><a class="fw-semibold" href="{{ route('official-exam-sources.show', $source) }}">{{ $source->website_name }}</a><div class="small text-muted">{{ $source->name }}</div>@unless($source->enabled)<span class="badge bg-secondary-subtle text-secondary">Disabled</span>@endunless</td>
                <td><span class="badge bg-info-subtle text-info text-uppercase">{{ $source->driver }}</span></td>
                <td>Every {{ $source->check_interval_minutes }} min<div class="small text-muted">Next: {{ $source->next_check_at?->diffForHumans() ?? 'due now' }}</div></td>
                <td>{{ $source->rules_count }}</td><td>{{ $source->discoveries_count }} <span class="small text-muted">({{ $source->created_count }} created)</span></td>
                <td>@if($source->attention_count)<span class="badge bg-warning-subtle text-warning">{{ $source->attention_count }}</span>@else<span class="text-muted">0</span>@endif</td>
                <td>{{ $source->last_checked_at?->diffForHumans() ?? 'Never' }}@if($source->last_error)<div class="small text-danger text-truncate" style="max-width:260px">{{ $source->last_error }}</div>@endif</td>
                <td class="text-end"><div class="d-inline-flex gap-2"><form method="POST" action="{{ route('official-exam-sources.run', $source) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Check now</button></form><a class="btn btn-sm btn-outline-secondary" href="{{ route('official-exam-sources.edit', $source) }}">Edit</a></div></td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-5">No official source profiles have been configured.</td></tr>@endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $sources->links() }}</div>
</div>
@endsection
