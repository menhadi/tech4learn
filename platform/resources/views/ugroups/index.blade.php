@extends('layouts.master')
@section('title', 'Roles')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Roles')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Roles</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="ugroupList">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            <button type="button" class="btn el-btn-primary add-btn" data-bs-toggle="modal"
                                id="create-btn" data-bs-target="#showModal"><i
                                    class="ri-add-line align-bottom me-1"></i> Add</button>
                            <button type="button" class="btn el-btn-secondary"
                                onclick="window.location.href='{{ route('users.index') }}'">Back to users</button>
                        </div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap el-table" id="ugroupTable">
                            <thead>
                                <tr>
                                    <th class="sort" data-sort="ugroup_name">Role Name</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($ugroups as $ugroup)
                                <tr>
                                    <td class="ugroup_name">{{ $ugroup->ugroup_name }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm btn-primary edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $ugroup->id }}"
                                                    data-ugroup_name="{{ $ugroup->ugroup_name }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $ugroup->id }}">Remove</button>
                                            </div>
                                            <div class="assign-right">
                                                <button class="btn btn-sm btn-warning assign-right-btn"
                                                    data-bs-toggle="modal" data-bs-target="#assignRightModal"
                                                    data-id="{{ $ugroup->id }}">Permissions</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="noresult" style="display: none">
                            <div class="text-center">
                                <lord-icon src="https://cdn.lordicon.com/msoeawqm.json" trigger="loop" style="width:75px;height:75px">
                                </lord-icon>
                                <h5 class="mt-2">No results found</h5>
                            </div>
                        </div>
                    </div>
                    {{ $ugroups->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('ugroups.store') }}" id="ugroupForm">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="formMethod">
                    <div class="mb-3">
                        <label for="ugroupname-field" class="form-label">Role Name</label>
                        <input type="text" id="ugroupname-field" name="ugroup_name" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="add-btn">Add</button>
                        <button type="submit" class="btn btn-primary" id="edit-btn" style="display: none;">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteRecordModal" tabindex="-1" aria-labelledby="deleteRecordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRecordLabel">Delete Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="delete-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <p>Are you sure you want to delete this role?</p>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Yes, Delete It!</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assign Right Modal -->
<div class="modal fade" id="assignRightModal" tabindex="-1" aria-labelledby="assignRightLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignRightLabel">Role Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assign-right-form" method="POST" action="{{ route('pagerights.store') }}">
                    @csrf
                    <input type="hidden" name="ugroup_id" id="assign-ugroup-id">
                    <div class="alert alert-info small mb-3">
                        Give View first, then choose whether this role can Add, Edit, or Delete records for that module.
                    </div>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm align-middle mb-0 el-table el-permission-table">
                            <thead class="position-sticky top-0">
                                <tr>
                                    <th>Module</th>
                                    <th class="text-center">View</th>
                                    <th class="text-center">Add</th>
                                    <th class="text-center">Edit</th>
                                    <th class="text-center">Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pages as $page)
                                <tr>
                                    <td>
                                        <input type="hidden" name="pages[]" value="{{ $page->id }}" class="page-include-input" disabled>
                                        <span class="fw-medium">{{ $page->page_name }}</span>
                                    </td>
                                    @foreach (['view_right' => 'View', 'add_right' => 'Add', 'edit_right' => 'Edit', 'delete_right' => 'Delete'] as $right => $label)
                                    <td class="text-center">
                                        <input class="form-check-input permission-checkbox permission-{{ $right }}"
                                            type="checkbox"
                                            name="rights[{{ $page->id }}][{{ $right }}]"
                                            value="1"
                                            data-page-id="{{ $page->id }}"
                                            data-right="{{ $right }}"
                                            aria-label="{{ $page->page_name }} {{ $label }}">
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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
                fetchUgroups(searchQuery);
            }, 300);
        });

        function fetchUgroups(query) {
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#ugroupTable tbody');
                    document.querySelector('#ugroupTable tbody').innerHTML = newTableBody.innerHTML;
                })
                .catch(error => console.error('Error fetching ugroups:', error));
        }

        // Reset modal when hidden
        document.getElementById('showModal').addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('ugroupForm');
            const modal = this;
            
            // Reset form
            form.reset();
            form.setAttribute('action', '{{ route("ugroups.store") }}');
            document.getElementById('formMethod').value = 'POST';
            
            // Reset modal title and buttons
            modal.querySelector('.modal-title').textContent = 'Add Role';
            document.getElementById('add-btn').style.display = 'block';
            document.getElementById('edit-btn').style.display = 'none';
            
            // Remove any existing method override inputs
            const existingMethodInputs = form.querySelectorAll('input[name="_method"]:not(#formMethod)');
            existingMethodInputs.forEach(input => input.remove());
        });

        // Add/Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var modal = this;
            var form = document.getElementById('ugroupForm');

            // Check if button exists (for programmatic opening)
            if (!button) return;

            var id = button.getAttribute('data-id');
            var ugroup_name = button.getAttribute('data-ugroup_name');

            if (id && ugroup_name) {
                // Edit mode
                modal.querySelector('.modal-title').textContent = 'Edit Role';
                modal.querySelector('#ugroupname-field').value = ugroup_name;
                document.getElementById('add-btn').style.display = 'none';
                document.getElementById('edit-btn').style.display = 'block';

                // Set form action and method for update
                form.setAttribute('action', '{{ route("ugroups.update", ":id") }}'.replace(':id', id));
                document.getElementById('formMethod').value = 'PUT';
            } else {
                // Add mode
                modal.querySelector('.modal-title').textContent = 'Add Role';
                modal.querySelector('#ugroupname-field').value = '';
                document.getElementById('add-btn').style.display = 'block';
                document.getElementById('edit-btn').style.display = 'none';

                // Reset form action and method for create
                form.setAttribute('action', '{{ route("ugroups.store") }}');
                document.getElementById('formMethod').value = 'POST';
            }
        });

        function syncPermissionRow(pageId) {
            const rowChecks = document.querySelectorAll(`.permission-checkbox[data-page-id="${pageId}"]`);
            const includeInput = document.querySelector(`.page-include-input[value="${pageId}"]`);
            const viewCheck = document.querySelector(`.permission-checkbox[data-page-id="${pageId}"][data-right="view_right"]`);
            const hasAnyRight = Array.from(rowChecks).some(checkbox => checkbox.checked);

            if (includeInput) {
                includeInput.disabled = !hasAnyRight;
            }

            if (hasAnyRight && viewCheck) {
                viewCheck.checked = true;
            }
        }

        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                syncPermissionRow(this.dataset.pageId);
            });
        });

        // Assign Right Modal
        document.getElementById('assignRightModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var modal = this;

            modal.querySelector('#assign-ugroup-id').value = id;
            modal.querySelectorAll('.permission-checkbox').forEach(checkbox => checkbox.checked = false);
            modal.querySelectorAll('.page-include-input').forEach(input => input.disabled = true);

            // Fetch existing rights and check the checkboxes
            fetch(`{{ url('ugroups') }}/${id}/rights`)
                .then(response => response.json())
                .then(data => {
                    Object.keys(data || {}).forEach(pageId => {
                        const rightSet = data[pageId] || {};

                        ['view_right', 'add_right', 'edit_right', 'delete_right'].forEach(right => {
                            const checkbox = modal.querySelector(`.permission-checkbox[data-page-id="${pageId}"][data-right="${right}"]`);
                            if (checkbox) {
                                checkbox.checked = Boolean(Number(rightSet[right]));
                            }
                        });

                        syncPermissionRow(pageId);
                    });
                })
                .catch(error => console.error('Error fetching rights:', error));
        });

        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('ugroups.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // Display success or error messages
        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: '{{ implode(", ", $errors->all()) }}',
            timer: 5000,
            showConfirmButton: true
        });
        @endif
    });
</script>
@endsection
