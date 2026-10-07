@extends('layouts.master')
@section('title', 'Question Types')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Question Types')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Question Types</h4>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="qtypeList">
                    <div class="row g-4 mb-3">
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
                        <table class="table align-middle table-nowrap" id="qtypeTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="question_type">Question Type</th>
                                    @if($canManageFixedOptions ?? false)
                                    <th class="sort" data-sort="action">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($qtypes as $qtype)
                                <tr>
                                    <td class="question_type">{{ $qtype->question_type }}</td>
                                    @if($canManageFixedOptions ?? false)
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $qtype->id }}"
                                                    data-question_type="{{ $qtype->question_type }}">Edit</button>
                                            </div>
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
                        {{ $qtypes->links('vendor.pagination.custom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManageFixedOptions ?? false)
<!-- Edit Modal -->
<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="exampleModalLabel">Edit Qtype</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="close-modal"></button>
            </div>
            <form class="tablelist-form" autocomplete="off" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="question_type-field" class="form-label">Question Type</label>
                        <input type="text" id="question_type-field" name="question_type" class="form-control"
                            placeholder="Enter Question Type" required />
                        <div class="invalid-feedback">Please enter a question type.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="edit-btn">Update Qtype</button>
                    </div>
                </div>
            </form>
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
                fetchQtypes(searchQuery);
            }, 300);
        });

        function fetchQtypes(query) {
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#qtypeTable tbody');
                    document.querySelector('#qtypeTable tbody').innerHTML = newTableBody.innerHTML;
                })
                .catch(error => console.error('Error fetching qtypes:', error));
        }

        @if($canManageFixedOptions ?? false)
        // Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var question_type = button.getAttribute('data-question_type');
            var modal = this;

            modal.querySelector('#question_type-field').value = question_type;

            // Set form action and method for update
            modal.querySelector('form').setAttribute('action', '{{ route("qtypes.update", ":id") }}'.replace(':id', id));
        });
        @endif

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
