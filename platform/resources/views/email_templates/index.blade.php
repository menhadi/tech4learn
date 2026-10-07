@extends('layouts.master')
@section('title', 'Email Templates')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Email Templates')
@endcomponent
@php
    $canAddEmailTemplate = user_can_route_action('email-templates.create', 'add');
    $canEditEmailTemplate = user_can_route_action('email-templates.edit', 'edit');
    $canDeleteEmailTemplate = user_can_route_action('email-templates.destroy', 'delete');
@endphp

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Email Templates</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="emailTemplateList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                @if($canAddEmailTemplate)
                                <a href="{{ route('email-templates.create') }}" class="btn btn-success add-btn"><i
                                        class="ri-add-line align-bottom me-1"></i> Add</a>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="d-flex justify-content-sm-end">
                                <div class="search-box ms-2">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="emailTemplateTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="name">Name</th>
                                    <th class="sort" data-sort="type">Use</th>
                                    <th class="sort" data-sort="subject">Subject</th>
                                    <th class="sort" data-sort="status">Status</th>
                                    <th class="sort" data-sort="created_at">Created At</th>
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    @if($canEditEmailTemplate || $canDeleteEmailTemplate)
                                    <th class="sort" data-sort="action">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($emailTemplates as $emailTemplate)
                                <tr>
                                    <td class="name">{{ $emailTemplate->name }}</td>
                                    <td class="type">
                                        @if(($emailTemplate->type ?? '') === 'student_welcome')
                                            <span class="badge bg-info-subtle text-info">Student Welcome</span>
                                        @else
                                            <span class="text-muted">Manual</span>
                                        @endif
                                    </td>
                                    <td class="subject">{{ \Illuminate\Support\Str::limit($emailTemplate->subject ?? '-', 45) }}</td>
                                    <td class="status">{{ $emailTemplate->status }}</td>
                                    <td class="created_at">@formatDate($emailTemplate->created_at)</td>
                                    <td class="updated_at">@formatDate($emailTemplate->updated_at)</td>
                                    @if($canEditEmailTemplate || $canDeleteEmailTemplate)
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if($canEditEmailTemplate)
                                            <div class="edit">
                                                <a href="{{ route('email-templates.edit', $emailTemplate->id) }}"
                                                    class="btn btn-sm btn-success edit-item-btn">Edit</a>
                                            </div>
                                            @endif
                                            @if($canDeleteEmailTemplate)
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $emailTemplate->id }}">Remove</button>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="noresult" style="display: none">
                            <div class="text-center">
                                <lord-icon src="https://cdn.lordicon.com/msoeawqm.json" trigger="loop"
                                    colors="primary:#121331,secondary:#08a88a" style="width:75px;height:75px">
                                </lord-icon>
                                <h5 class="mt-2">Sorry! No Result Found</h5>
                                <p class="text-muted mb-0">We've searched more than 150+ Orders We did not find any
                                    orders for you search.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        {{ $emailTemplates->links('vendor.pagination.custom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
@if($canDeleteEmailTemplate)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="btn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop"
                        colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you Sure ?</h4>
                        <p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record ?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/list.pagination.js/list.pagination.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        let debounceTimeout;
        const searchInput = document.getElementById('search-input');

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const searchQuery = searchInput.value;
                fetchEmailTemplates(searchQuery);
            }, 300);
        });

        function fetchEmailTemplates(query) {
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#emailTemplateTable tbody');
                    document.querySelector('#emailTemplateTable tbody').innerHTML = newTableBody.innerHTML;
                })
                .catch(error => console.error('Error fetching email templates:', error));
        }

        // Delete Modal
        const deleteModal = document.getElementById('deleteRecordModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var action = "{{ route('email-templates.destroy', ':id') }}";
                action = action.replace(':id', id);
                document.getElementById('delete-form').setAttribute('action', action);
            });
        }

        // Display success or error messages
        @if(session('success'))
        Swal.fire({
            icon: 'success'
            , title: 'Success'
            , text: '{{ session('success') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error'
            , title: 'Error'
            , text: '{{ session('error') }}'
            , timer: 3000
            , showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error'
            , title: 'Validation Error'
            , text: '{{ implode(", ", $errors->all()) }}'
            , timer: 5000
            , showConfirmButton: true
        });
        @endif
    });
</script>
@endsection
