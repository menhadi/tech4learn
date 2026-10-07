@extends('layouts.master')
@section('title', 'SMS Templates')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'SMS Templates')
@endcomponent

@php
    $canAddSmsTemplate = user_can_route_action('sms-templates.create', 'add');
    $canEditSmsTemplate = user_can_route_action('sms-templates.edit', 'edit');
    $canDeleteSmsTemplate = user_can_route_action('sms-templates.destroy', 'delete');
@endphp

<div class="alert alert-info d-flex align-items-start gap-2">
    <i class="ri-information-line fs-4"></i>
    <div>
        <strong>Ready-made OTP templates are installed automatically.</strong>
        Edit the active SMS or WhatsApp template below and keep the <code>{#otp#}</code> and <code>{#siteName#}</code> placeholders. Twilio SMS uses the active SMS text directly. MSG91, 2Factor, and WhatsApp still require the matching approved provider template ID or name in Messaging Settings.
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove SMS Templates</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="smsTemplateList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                @if($canAddSmsTemplate)
                                <a href="{{ route('sms-templates.create') }}" class="btn btn-success add-btn"><i
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
                        <table class="table align-middle table-nowrap" id="smsTemplateTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="name">Name</th>
                                    <th>Purpose</th>
                                    <th class="sort" data-sort="description">Description</th>
                                    <th class="sort" data-sort="status">Status</th>
                                    <th class="sort" data-sort="created_at">Created At</th>
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    @if($canEditSmsTemplate || $canDeleteSmsTemplate)
                                        <th class="sort" data-sort="action">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($smsTemplates as $smsTemplate)
                                <tr>
                                    <td class="name">{{ $smsTemplate->name }}</td>
                                    <td>
                                        @if($smsTemplate->type === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_SMS)
                                            <span class="badge bg-primary-subtle text-primary">OTP SMS text</span>
                                        @elseif($smsTemplate->type === \App\Models\SmsTemplate::TYPE_STUDENT_OTP_WHATSAPP)
                                            <span class="badge bg-success-subtle text-success">OTP WhatsApp text</span>
                                        @else
                                            <span class="text-muted">Custom</span>
                                        @endif
                                    </td>
                                    <td class="description text-wrap" style="min-width:320px;">{{ $smsTemplate->description }}</td>
                                    <td class="status">{{ $smsTemplate->status }}</td>
                                    <td class="created_at">@formatDate($smsTemplate->created_at)</td>
                                    <td class="updated_at">@formatDate($smsTemplate->updated_at)</td>
                                    @if($canEditSmsTemplate || $canDeleteSmsTemplate)
                                        <td>
                                            <div class="d-flex gap-2">
                                                @if($canEditSmsTemplate)
                                                    <div class="edit">
                                                        <a href="{{ route('sms-templates.edit', $smsTemplate->id) }}"
                                                            class="btn btn-sm btn-success edit-item-btn">Edit</a>
                                                    </div>
                                                @endif
                                                @if($canDeleteSmsTemplate && ! $smsTemplate->isReadyMade())
                                                    <div class="remove">
                                                        <button class="btn btn-sm btn-danger remove-item-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                            data-id="{{ $smsTemplate->id }}">Remove</button>
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
                        {{ $smsTemplates->links('vendor.pagination.custom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canDeleteSmsTemplate)
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
                fetchSmsTemplates(searchQuery);
            }, 300);
        });

        function fetchSmsTemplates(query) {
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#smsTemplateTable tbody');
                    document.querySelector('#smsTemplateTable tbody').innerHTML = newTableBody.innerHTML;
                })
                .catch(error => console.error('Error fetching SMS templates:', error));
        }

        // Delete Modal
        var deleteRecordModal = document.getElementById('deleteRecordModal');
        if (deleteRecordModal) {
            deleteRecordModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var action = "{{ route('sms-templates.destroy', ':id') }}";
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
