@extends('layouts.master')
@section('title', 'Paper Repair Version History')
@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
        <a href="{{ route('exam-quality.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Quality audits</a>
        <h3 class="mt-2 mb-1">Paper repair version history</h3>
        <p class="text-muted mb-0">Open any published paper release to inspect its questions and restore the complete release or selected questions.</p>
    </div>
</div>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Published paper releases</h5></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Paper</th><th>Release</th><th>Questions</th><th>Status</th><th>Published</th><th></th></tr></thead>
        <tbody>
        @forelse($releases as $release)
            <tr>
                <td><strong>{{ $release->exam?->name ?: 'Deleted paper' }}</strong></td>
                <td>{{ $release->name }}<div class="small text-muted">by {{ $release->creator?->name ?: 'System' }}</div></td>
                <td><strong>{{ $release->items_count }}</strong><div class="small text-muted">{{ $release->unrestored_items_count }} currently restorable</div></td>
                <td><span class="badge bg-{{ $release->status === 'published' ? 'success' : 'warning' }}">{{ ucfirst(str_replace('_',' ',$release->status)) }}</span></td>
                <td>{{ optional($release->published_at)->format('d M Y, h:i A') }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('exam-quality.releases.show',$release) }}"><i class="ri-history-line me-1"></i>View versions / restore</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-5">No paper repair release has been published yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($releases->hasPages())<div class="card-footer">{{ $releases->links() }}</div>@endif
</div>
@endsection
