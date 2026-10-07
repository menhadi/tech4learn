@extends('layouts.master')
@section('title', 'URL Redirects')

@section('content')
@component('components.breadcrumb')
    @slot('li_1', 'Website & Content')
    @slot('title', 'URL Redirects')
@endcomponent

<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-1">Add a redirect</h4>
                <p class="text-muted mb-0">Use this only when an old URL has a closely matching replacement.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('redirects.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="source_path">Old 404 path</label>
                        <input class="form-control @error('source_path') is-invalid @enderror" id="source_path" name="source_path" value="{{ old('source_path') }}" placeholder="/old-exam-page" required>
                        @error('source_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="target_path">Working destination</label>
                        <input class="form-control @error('target_path') is-invalid @enderror" id="target_path" name="target_path" value="{{ old('target_path') }}" placeholder="/exam-detail/current-exam" required>
                        <div class="form-text">Internal paths only. Begin with <code>/</code>.</div>
                        @error('target_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="status_code">Redirect type</label>
                        <select class="form-select" id="status_code" name="status_code">
                            <option value="301" @selected(old('status_code', 301) == 301)>301 — Permanent (recommended)</option>
                            <option value="302" @selected(old('status_code') == 302)>302 — Temporary</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="note">Note</label>
                        <input class="form-control" id="note" name="note" value="{{ old('note') }}" maxlength="500" placeholder="Why this page moved">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', '1') == '1')>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <button class="btn btn-primary w-100" type="submit"><i class="ri-add-line me-1"></i>Add redirect</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="card-title mb-1">Managed redirects</h4>
                    <p class="text-muted mb-0">Unmapped missing URLs continue to return a proper 404.</p>
                </div>
                <form method="GET" action="{{ route('redirects.index') }}" class="d-flex gap-2">
                    <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search paths">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Old URL → destination</th>
                                <th>Status</th>
                                <th>Usage</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($redirects as $redirect)
                                <tr>
                                    <td style="min-width: 310px">
                                        <div class="fw-semibold text-danger">{{ $redirect->source_path }}</div>
                                        <div class="text-muted"><i class="ri-arrow-right-line me-1"></i><a href="{{ $redirect->target_path }}" target="_blank" rel="noopener">{{ $redirect->target_path }}</a></div>
                                        @if($redirect->note)<small class="text-muted">{{ $redirect->note }}</small>@endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $redirect->status_code === 301 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">{{ $redirect->status_code }}</span>
                                        <span class="badge {{ $redirect->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $redirect->is_active ? 'Active' : 'Inactive' }}</span>
                                    </td>
                                    <td>
                                        <div>{{ number_format($redirect->hit_count) }} hits</div>
                                        <small class="text-muted">{{ $redirect->last_hit_at?->diffForHumans() ?: 'Never used' }}</small>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-redirect"
                                            data-bs-toggle="modal" data-bs-target="#editRedirectModal"
                                            data-action="{{ route('redirects.update', $redirect) }}"
                                            data-source="{{ $redirect->source_path }}"
                                            data-target="{{ $redirect->target_path }}"
                                            data-status="{{ $redirect->status_code }}"
                                            data-active="{{ $redirect->is_active ? 1 : 0 }}"
                                            data-note="{{ $redirect->note }}">Edit</button>
                                        <form method="POST" action="{{ route('redirects.destroy', $redirect) }}" class="d-inline" onsubmit="return confirm('Delete this redirect?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">No redirects have been added.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($redirects->hasPages())
                <div class="card-footer">{{ $redirects->links() }}</div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="editRedirectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editRedirectForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit redirect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="edit_source_path">Old 404 path</label><input class="form-control" id="edit_source_path" name="source_path" required></div>
                    <div class="mb-3"><label class="form-label" for="edit_target_path">Working destination</label><input class="form-control" id="edit_target_path" name="target_path" required></div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_status_code">Redirect type</label>
                        <select class="form-select" id="edit_status_code" name="status_code"><option value="301">301 — Permanent</option><option value="302">302 — Temporary</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label" for="edit_note">Note</label><input class="form-control" id="edit_note" name="note" maxlength="500"></div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.querySelectorAll('.edit-redirect').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('editRedirectForm').action = button.dataset.action;
        document.getElementById('edit_source_path').value = button.dataset.source;
        document.getElementById('edit_target_path').value = button.dataset.target;
        document.getElementById('edit_status_code').value = button.dataset.status;
        document.getElementById('edit_note').value = button.dataset.note || '';
        document.getElementById('edit_is_active').checked = button.dataset.active === '1';
    });
});
</script>
@endsection
