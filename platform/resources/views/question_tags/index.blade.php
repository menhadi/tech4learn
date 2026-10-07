@extends('layouts.master')

@section('title', 'Question Tags')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Academics')
@slot('title', 'Question Tags')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card" id="questionTagsPanel">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="card-title mb-1">Question Tags</h4>
                        <p class="text-muted mb-0">Use tags for source, AI/manual, shared questions, review status, image questions, or organization-specific labels.</p>
                    </div>
                    <button class="btn el-btn-primary" data-bs-toggle="modal" data-bs-target="#tagModal" data-mode="create">
                        <i class="ri-add-line me-1"></i> Add Tag
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="el-table-toolbar">
                    <div class="el-table-toolbar-actions">
                        <span class="badge bg-primary-subtle text-primary border">Platform tags are read-only</span>
                    </div>
                    <div class="el-table-toolbar-controls">
                        <form method="GET" class="el-filter-search">
                            <div class="search-box">
                                <input type="text" name="search" class="form-control search"
                                    value="{{ request('search') }}" placeholder="Search question tags...">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive table-card mb-1">
                    <table class="table align-middle table-nowrap el-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Questions</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tags as $tag)
                                <tr>
                                    <td>
                                        <strong>{{ $tag->name }}</strong>
                                        <div class="text-muted small">{{ $tag->slug }}</div>
                                    </td>
                                    <td>
                                        @if($tag->organization_id)
                                            <span class="badge bg-primary-subtle text-primary border">Organization</span>
                                        @else
                                            <span class="badge bg-light text-body border">Platform default</span>
                                        @endif
                                    </td>
                                    <td>{{ $tag->questions_count }}</td>
                                    <td>
                                        <span class="badge {{ $tag->status ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                                            {{ $tag->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($tag->organization_id || ($canManageDefaultTags ?? false))
                                            <button class="btn btn-sm el-btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#tagModal"
                                                data-mode="edit"
                                                data-id="{{ $tag->id }}"
                                                data-name="{{ $tag->name }}"
                                                data-status="{{ $tag->status ? 1 : 0 }}">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('question-tags.destroy', $tag->id) }}" class="d-inline"
                                                data-swal-confirm="Delete this tag? It will be removed from questions too.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm el-btn-secondary">Delete</button>
                                            </form>
                                        @else
                                            <span class="text-muted small">Read-only</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No tags found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $tags->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="tagForm" action="{{ route('question-tags.store') }}">
                @csrf
                <input type="hidden" name="_method" id="tagMethod" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalTitle">Add Question Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="tag-name-field">Tag Name</label>
                        <input type="text" class="form-control" id="tag-name-field" name="name" required maxlength="80"
                            placeholder="Example: Needs Review">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="tag-status-field">Status</label>
                        <select class="form-select" id="tag-status-field" name="status" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn el-btn-primary">Save Tag</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('tagModal');
        const form = document.getElementById('tagForm');
        const method = document.getElementById('tagMethod');
        const title = document.getElementById('tagModalTitle');
        const nameField = document.getElementById('tag-name-field');
        const statusField = document.getElementById('tag-status-field');
        const storeUrl = @json(route('question-tags.store'));
        const updateUrl = @json(route('question-tags.update', ':id'));

        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const mode = button?.getAttribute('data-mode') || 'create';

            if (mode === 'edit') {
                title.textContent = 'Edit Question Tag';
                form.action = updateUrl.replace(':id', button.getAttribute('data-id'));
                method.value = 'PUT';
                nameField.value = button.getAttribute('data-name') || '';
                statusField.value = button.getAttribute('data-status') || '1';
                return;
            }

            title.textContent = 'Add Question Tag';
            form.action = storeUrl;
            method.value = 'POST';
            nameField.value = '';
            statusField.value = '1';
        });
    });
</script>
@endsection
