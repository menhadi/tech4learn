<style>
    .question-assignment-summary {
        background: var(--vz-light, #f3f6f9);
        border: 1px solid var(--vz-border-color, #e9ebec);
        border-radius: .5rem;
        padding: 1rem;
    }
    .question-assignment-preview {
        color: var(--vz-body-color, #212529);
        max-width: 900px;
    }
    .question-assignment-filter {
        min-width: 150px;
    }
    .question-assignment-table tbody tr.is-changed {
        background: rgba(13, 110, 253, .06);
    }
    .question-assignment-state {
        min-height: 24px;
    }
</style>

<div class="modal fade" id="questionExamAssignmentModal" tabindex="-1" aria-hidden="true"
    data-index-url="{{ url('questions/__QUESTION__/exam-assignments') }}"
    data-update-url="{{ url('questions/__QUESTION__/exam-assignments') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Question Exam Assignments</h5>
                    <div class="small text-muted">Review where this question is used, then add or remove exam links.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="questionAssignmentSummary" class="question-assignment-summary mb-3">
                    <div class="placeholder-glow">
                        <span class="placeholder col-3"></span>
                        <span class="placeholder col-8 d-block mt-2"></span>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Group</label>
                        <select id="assignmentGroupFilter" class="form-select question-assignment-filter">
                            <option value="">All Groups</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Category</label>
                        <select id="assignmentCategoryFilter" class="form-select question-assignment-filter">
                            <option value="">All Categories</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4" data-subcategory-ui>
                        <label class="form-label">Subcategory</label>
                        <select id="assignmentSubcategoryFilter" class="form-select question-assignment-filter">
                            <option value="">All Subcategories</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Package</label>
                        <select id="assignmentPackageFilter" class="form-select question-assignment-filter">
                            <option value="">All Packages</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Saved Assignment</label>
                        <select id="assignmentStatusFilter" class="form-select question-assignment-filter">
                            <option value="all">All Exams</option>
                            <option value="assigned">Assigned</option>
                            <option value="unassigned">Not Assigned</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label class="form-label">Search Exams</label>
                        <input id="assignmentExamSearch" type="search" class="form-control" placeholder="Exam name">
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div id="questionAssignmentResultCount" class="small text-muted"></div>
                    <button type="button" id="assignmentResetFilters" class="btn btn-sm el-btn-secondary">Reset Exam Filters</button>
                </div>

                <div id="questionAssignmentAlert" class="alert alert-danger d-none"></div>
                <div id="questionAssignmentLoading" class="text-center py-5">
                    <span class="spinner-border text-primary" role="status"></span>
                    <div class="text-muted mt-2">Loading exams...</div>
                </div>
                <div id="questionAssignmentTableWrap" class="table-responsive border rounded d-none">
                    <table class="table table-hover align-middle mb-0 question-assignment-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:55px">Use</th>
                                <th>Exam</th>
                                <th>Status</th>
                                <th>Questions</th>
                                <th>Attempts</th>
                                <th style="width:90px">Open</th>
                            </tr>
                        </thead>
                        <tbody id="questionAssignmentRows"></tbody>
                    </table>
                </div>
                <div id="questionAssignmentEmpty" class="text-center text-muted border rounded py-5 d-none">No exams match these filters.</div>
                <div id="questionAssignmentPagination" class="d-flex justify-content-end align-items-center gap-2 mt-3"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <div id="questionAssignmentChanges" class="question-assignment-state text-muted">No changes</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn el-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="saveQuestionAssignments" class="btn el-btn-primary" disabled>Save Assignments</button>
                </div>
            </div>
        </div>
    </div>
</div>
