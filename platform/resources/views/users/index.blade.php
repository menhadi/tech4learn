@extends('layouts.master')
@section('title', 'Users')

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Users')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Administrator Users</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="userList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                <button type="button" class="btn btn-success add-btn" data-bs-toggle="modal"
                                    id="create-btn" data-bs-target="#showModal"><i
                                        class="ri-add-line align-bottom me-1"></i> Add</button>
                                <button type="button" class="btn btn-secondary"
                                    onclick="window.location.href='{{ route('ugroups.index') }}'">Assign form
                                    rights</button>
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="d-flex justify-content-sm-end">
                                <div class="search-box ms-2">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search name, email, username or mobile..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                                <div class="el-page-size ms-2">
                                    <select id="per-page-select" class="form-select">
                                        <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                        <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                        <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="el-result-count">Showing {{ $users->count() }} of {{ $users->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="userTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort" data-sort="name">Name</th>
                                    <th class="sort" data-sort="username">Username</th>
                                    <th class="sort" data-sort="email">Email</th>
                                    <th class="sort" data-sort="mobile">Mobile</th>
                                    <th class="sort" data-sort="ugroup_id">Access Type</th>
                                    <th class="sort" data-sort="status">Status</th>
                                    <th class="sort" data-sort="created_at">Created At</th>
                                    <th class="sort" data-sort="updated_at">Updated At</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($users as $user)
                                @php $isPrivileged = (bool) $user->is_platform_admin || in_array($user->organization_role, ['owner', 'admin'], true); @endphp
                                <tr>
                                    <td class="name">{{ $user->name }}</td>
                                    <td class="username">{{ $user->username }}</td>
                                    <td class="email">{{ $user->email }}</td>
                                    <td class="mobile">{{ $user->mobile }}</td>
                                    <td class="ugroup_id">
                                        @if($user->is_platform_admin)
                                            <span class="badge bg-primary-subtle text-primary">Platform Super Admin</span>
                                        @elseif($user->organization_role === 'owner')
                                            <span class="badge bg-warning-subtle text-warning">Organization Owner</span>
                                        @elseif($user->organization_role === 'admin')
                                            <span class="badge bg-info-subtle text-info">Organization Admin</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary">Staff</span>
                                            <span class="ms-1">{{ $user->ugroup?->ugroup_name ?? 'No role assigned' }}</span>
                                        @endif
                                    </td>
                                    <td class="status"><span class="badge {{ $user->status === 'Active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $user->status }}</span></td>
                                    <td class="created_at">@formatDate($user->created_at)</td>
                                    <td class="updated_at">@formatDate($user->updated_at)</td>
                                    <td>
                                        @if($isPrivileged)
                                            @if($canManagePrivilegedAccounts)
                                                <button type="button" class="btn btn-sm btn-outline-primary privileged-email-btn"
                                                    data-bs-toggle="modal" data-bs-target="#privilegedEmailModal"
                                                    data-id="{{ $user->id }}" data-name="{{ $user->name }}"
                                                    data-email="{{ $user->email }}">
                                                    <i class="ri-mail-settings-line me-1"></i>Edit Email
                                                </button>
                                            @else
                                                <span class="text-muted small">Managed by platform super admin</span>
                                            @endif
                                        @else
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-success edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $user->id }}" data-name="{{ $user->name }}"
                                                    data-username="{{ $user->username }}"
                                                    data-email="{{ $user->email }}" data-mobile="{{ $user->mobile }}"
                                                    data-ugroup_id="{{ $user->ugroup_id }}"
                                                    data-status="{{ $user->status }}">Edit</button>
                                                <button class="btn btn-sm btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $user->id }}">Remove</button>
                                            </div>
                                        @endif
                                    </td>
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

                    <div class="d-flex justify-content-end mt-3">
                        {{ $users->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="showModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="exampleModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="close-modal"></button>
            </div>
            <form class="tablelist-form" autocomplete="off" method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name-field" class="form-label">Name</label>
                        <input type="text" id="name-field" name="name" class="form-control" placeholder="Enter Name"
                            required />
                        <div class="invalid-feedback">Please enter a name.</div>
                    </div>
                    <div class="mb-3">
                        <label for="username-field" class="form-label">Username <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" id="username-field" name="username" class="form-control"
                            placeholder="Generated from email when blank" />
                        <div class="form-text">Can be used for login; generated automatically when blank.</div>
                    </div>
                    <div class="mb-3">
                        <label for="email-field" class="form-label">Email</label>
                        <input type="email" id="email-field" name="email" class="form-control" placeholder="Enter Email"
                            required />
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>
                    <div class="mb-3">
                        <label for="mobile-field" class="form-label">Mobile <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="tel" id="mobile-field" name="mobile" class="form-control"
                            placeholder="Enter mobile number" />
                        <div class="form-text">Optional contact number.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password-field" class="form-label">Password <span class="text-muted fw-normal edit-password-note">(required for new user)</span></label>
                        <div class="input-group">
                            <input type="password" id="password-field" name="password" class="form-control"
                                placeholder="Enter Password" minlength="8" />
                            <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                <i class="ri-eye-off-line"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter a password.</div>
                    </div>
                    <div class="mb-3">
                        <label for="ugroup_id-field" class="form-label">Permission Role</label>
                        <select id="ugroup_id-field" name="ugroup_id" class="form-control" required>
                            <option value="">Select Permission Role</option>
                            @foreach($ugroups as $ugroup)
                            <option value="{{ $ugroup->id }}">{{ $ugroup->ugroup_name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Controls which admin pages and actions this user can access.</div>
                    </div>
                    <div class="mb-3">
                        <label for="status-field" class="form-label">Status</label>
                        <select id="status-field" name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="add-btn">Add User</button>
                        <button type="submit" class="btn btn-success" id="edit-btn" style="display: none;">Update
                            User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@if($canManagePrivilegedAccounts)
<div class="modal fade" id="privilegedEmailModal" tabindex="-1" aria-labelledby="privilegedEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="privileged-email-form" method="POST" action="" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header bg-light p-3">
                <h5 class="modal-title" id="privilegedEmailModalLabel">Edit Administrator Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">This changes the login email for <strong id="privileged-email-user-name"></strong>.</p>
                <label for="privileged-email-field" class="form-label">Login Email</label>
                <input type="email" id="privileged-email-field" name="email" class="form-control" required>
                <div class="form-text">Every account must use a unique email address.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Email</button>
            </div>
        </form>
    </div>
</div>
@endif
<!-- Delete Modal -->
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
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/list.pagination.js/list.pagination.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

    let debounceTimeout;
    const privilegedEmailRoute = @json(route('saas.admin-users.email.update', ['user' => '__USER__']));
    const searchInput = document.getElementById('search-input');

    document.getElementById('per-page-select').addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });

    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            const searchQuery = searchInput.value;
            fetchUsers(searchQuery);
        }, 300);
    });

    function fetchUsers(query) {
        const url = new URL(window.location.href);
        url.searchParams.set('search', query);
        url.searchParams.set('per_page', document.getElementById('per-page-select').value);

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTableBody = doc.querySelector('#userTable tbody');
                document.querySelector('#userTable tbody').innerHTML = newTableBody.innerHTML;
                const newResultCount = doc.querySelector('.el-result-count');
                if (newResultCount) document.querySelector('.el-result-count').textContent = newResultCount.textContent;
            })
            .catch(error => console.error('Error fetching users:', error));
    }

    // Add/Edit Modal
    document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var username = button.getAttribute('data-username');
            var email = button.getAttribute('data-email');
            var mobile = button.getAttribute('data-mobile');
            var ugroup_id = button.getAttribute('data-ugroup_id');
            var status = button.getAttribute('data-status');
            var modal = this;

            if (id) {
                modal.querySelector('.modal-title').textContent = 'Edit User';
                modal.querySelector('#name-field').value = name;
                modal.querySelector('#username-field').value = username;
                modal.querySelector('#email-field').value = email;
                modal.querySelector('#mobile-field').value = mobile;
                modal.querySelector('#ugroup_id-field').value = ugroup_id;
                modal.querySelector('#status-field').value = status || 'Active';
                modal.querySelector('#password-field').value = '';
                modal.querySelector('#add-btn').style.display = 'none';
                modal.querySelector('#edit-btn').style.display = 'block';
                modal.querySelector('#password-field').removeAttribute('required');
                modal.querySelector('.edit-password-note').textContent = '(leave blank to keep current password)';

                // Set form action and method for update
                modal.querySelector('form').setAttribute('action', '{{ route("users.update", ":id") }}'.replace(':id', id));
                modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
            } else {
                modal.querySelector('.modal-title').textContent = 'Add User';
                modal.querySelector('#name-field').value = '';
                modal.querySelector('#username-field').value = '';
                modal.querySelector('#email-field').value = '';
                modal.querySelector('#mobile-field').value = '';
                modal.querySelector('#ugroup_id-field').value = '';
                modal.querySelector('#status-field').value = 'Active';
                modal.querySelector('#password-field').value = '';
                modal.querySelector('#add-btn').style.display = 'block';
                modal.querySelector('#edit-btn').style.display = 'none';
                modal.querySelector('#password-field').setAttribute('required', 'required');
                modal.querySelector('.edit-password-note').textContent = '(required for new user)';

                // Reset form action and method for create
                modal.querySelector('form').setAttribute('action', '{{ route("users.store") }}');
                var methodInput = modal.querySelector('input[name="_method"]');
                if (methodInput) {
                    methodInput.remove();
                }
            }
        });

        const privilegedEmailModal = document.getElementById('privilegedEmailModal');
        if (privilegedEmailModal) {
            privilegedEmailModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-id');
                this.querySelector('#privileged-email-user-name').textContent = button.getAttribute('data-name') || 'this administrator';
                this.querySelector('#privileged-email-field').value = button.getAttribute('data-email') || '';
                this.querySelector('#privileged-email-form').action = privilegedEmailRoute.replace('__USER__', userId);
            });
        }
        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('users.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
        });

        // Toggle password visibility
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordField = document.getElementById('password-field');
            const passwordIcon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('ri-eye-off-line');
                passwordIcon.classList.add('ri-eye-line');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('ri-eye-line');
                passwordIcon.classList.add('ri-eye-off-line');
            }
        });

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
