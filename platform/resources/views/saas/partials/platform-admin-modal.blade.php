<div class="modal fade" id="createPlatformAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('saas.platform-admins.store') }}">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Add Platform Super Admin</h5>
                        <p class="text-muted small mb-0">This account can access every tenant and the SaaS Control Center.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile <span class="text-muted">(optional)</span></label>
                        <input type="text" name="mobile" class="form-control" maxlength="30">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        Grant this access only to a trusted platform owner. Passwords are stored as one-way hashes and cannot be viewed later.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn el-btn-soft" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn el-btn-primary"><i class="ri-shield-user-line me-1"></i> Create Super Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>
