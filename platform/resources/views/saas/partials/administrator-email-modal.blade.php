<div class="modal fade" id="editAdministratorEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="editAdministratorEmailForm" method="POST" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Change Administrator Login Email</h5>
                    <p class="text-muted small mb-0">The administrator will use the new email for login and password recovery.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="administratorEmailInput">Login Email</label>
                <input id="administratorEmailInput" type="email" name="email" class="form-control" required maxlength="255">
                <div class="form-text">An email address can belong to only one administrator account.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn el-btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn el-btn-primary"><i class="ri-save-line me-1"></i> Save Email</button>
            </div>
        </form>
    </div>
</div>
