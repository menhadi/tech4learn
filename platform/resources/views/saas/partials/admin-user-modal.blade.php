<div class="modal fade" id="addAdminModal{{ $organization->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('saas.organizations.admin-users.store', $organization) }}" class="modal-content">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">Add Organization User - {{ $organization->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-info">
                    Create an Owner, Admin, or Staff account for {{ $organization->name }}. An organization can have multiple Owners and Admins. The user can log in from the admin login page.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Organization Role</label>
                        <select name="organization_role" class="form-select" required>
                            <option value="owner">Owner</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn el-btn-primary">Create Organization User</button>
            </div>
        </form>
    </div>
</div>
