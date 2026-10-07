<script>
(function () {
    const modalElement = document.getElementById('questionExamAssignmentModal');
    if (!modalElement) return;

    const state = {
        questionId: null,
        initialized: false,
        canManage: false,
        original: new Set(),
        selected: new Set(),
        examMeta: new Map(),
        request: null,
        searchTimer: null
    };
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const summary = document.getElementById('questionAssignmentSummary');
    const rows = document.getElementById('questionAssignmentRows');
    const loading = document.getElementById('questionAssignmentLoading');
    const tableWrap = document.getElementById('questionAssignmentTableWrap');
    const empty = document.getElementById('questionAssignmentEmpty');
    const alertBox = document.getElementById('questionAssignmentAlert');
    const pagination = document.getElementById('questionAssignmentPagination');
    const resultCount = document.getElementById('questionAssignmentResultCount');
    const changes = document.getElementById('questionAssignmentChanges');
    const saveButton = document.getElementById('saveQuestionAssignments');
    const filters = {
        group: document.getElementById('assignmentGroupFilter'),
        category: document.getElementById('assignmentCategoryFilter'),
        subcategory: document.getElementById('assignmentSubcategoryFilter'),
        package: document.getElementById('assignmentPackageFilter'),
        assignment: document.getElementById('assignmentStatusFilter'),
        search: document.getElementById('assignmentExamSearch')
    };

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value == null ? '' : String(value);
        return node.innerHTML;
    }

    function endpoint(kind) {
        return modalElement.dataset[kind + 'Url'].replace('__QUESTION__', state.questionId);
    }

    function difference(left, right) {
        return [...left].filter(function (id) { return !right.has(id); });
    }

    function plural(count, word) {
        return count + ' ' + word + (count === 1 ? '' : 's');
    }

    function updateChanges() {
        const additions = difference(state.selected, state.original);
        const removals = difference(state.original, state.selected);
        const total = state.selected.size;
        if (!additions.length && !removals.length) {
            changes.textContent = total ? plural(total, 'exam') + ' assigned ? No changes' : 'Not used in any exam ? No changes';
            changes.className = 'question-assignment-state text-muted';
            saveButton.disabled = true;
            return;
        }
        changes.textContent = total + ' assigned after save ? ' + plural(additions.length, 'addition') + ' ? ' + plural(removals.length, 'removal');
        changes.className = 'question-assignment-state text-primary fw-semibold';
        saveButton.disabled = !state.canManage;
    }

    function fillSelect(select, items, placeholder, labelKey) {
        const selectedValue = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item[labelKey] || '-';
            option.selected = String(item.id) === String(selectedValue);
            select.appendChild(option);
        });
        if (![...select.options].some(function (option) { return option.selected; })) select.value = '';
    }

    function renderSummary(question) {
        const metadata = [question.subject, question.topic, question.type, question.difficulty].filter(Boolean);
        const edit = question.edit_url
            ? '<a href="' + escapeHtml(question.edit_url) + '" class="btn btn-sm el-btn-secondary"><i class="ri-edit-line me-1"></i>Edit Question</a>'
            : '';
        summary.innerHTML =
            '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2"><div>' +
            '<div class="fw-semibold">' + escapeHtml(question.code) + '</div>' +
            '<div class="question-assignment-preview mt-1">' + escapeHtml(question.preview || 'No question text') + '</div>' +
            '<div class="small text-muted mt-2">' + escapeHtml(metadata.join(' ? ') || 'No classification') + '</div>' +
            '</div>' + edit + '</div>';
    }

    function currentVisibleExams() {
        return [...rows.querySelectorAll('.js-assignment-exam-check')]
            .map(function (box) { return state.examMeta.get(Number(box.value)); })
            .filter(Boolean);
    }

    function renderRows(exams) {
        rows.innerHTML = '';
        exams.forEach(function (exam) {
            state.examMeta.set(Number(exam.id), exam);
            const id = Number(exam.id);
            const checked = state.selected.has(id);
            const changed = checked !== state.original.has(id);
            const tr = document.createElement('tr');
            if (changed) tr.classList.add('is-changed');
            tr.innerHTML =
                '<td><input class="form-check-input js-assignment-exam-check" type="checkbox" value="' + id + '"' +
                (checked ? ' checked' : '') + (state.canManage ? '' : ' disabled') + ' aria-label="Use ' + escapeHtml(exam.name) + '"></td>' +
                '<td><div class="fw-semibold">' + escapeHtml(exam.name) + '</div><div class="small ' +
                (changed ? 'text-primary fw-semibold' : 'text-muted') + '">' +
                (changed ? (checked ? 'Will be added' : 'Will be removed') : (exam.assigned ? 'Currently assigned' : 'Not assigned')) +
                '</div></td>' +
                '<td><span class="badge ' + (exam.status === 'Active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary') + '">' +
                escapeHtml(exam.status || 'Unknown') + '</span></td>' +
                '<td>' + exam.question_count + '</td>' +
                '<td>' + (exam.results_count ? '<span class="text-warning fw-semibold">' + exam.results_count + '</span>' : '0') + '</td>' +
                '<td><a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(exam.view_url) + '" target="_blank" rel="noopener">Open</a></td>';
            rows.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        pagination.innerHTML = '';
        if (meta.last_page <= 1) return;
        const previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'btn btn-sm el-btn-secondary';
        previous.textContent = 'Previous';
        previous.disabled = meta.current_page <= 1;
        previous.addEventListener('click', function () { load(meta.current_page - 1); });
        const label = document.createElement('span');
        label.className = 'small text-muted';
        label.textContent = 'Page ' + meta.current_page + ' of ' + meta.last_page;
        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn btn-sm el-btn-secondary';
        next.textContent = 'Next';
        next.disabled = meta.current_page >= meta.last_page;
        next.addEventListener('click', function () { load(meta.current_page + 1); });
        pagination.append(previous, label, next);
    }

    async function load(page) {
        page = page || 1;
        if (!state.questionId) return;
        if (state.request) state.request.abort();
        state.request = new AbortController();
        loading.classList.remove('d-none');
        tableWrap.classList.add('d-none');
        empty.classList.add('d-none');
        alertBox.classList.add('d-none');
        const url = new URL(endpoint('index'), window.location.origin);
        const params = {
            group_id: filters.group.value,
            category_id: filters.category.value,
            subcategory_id: filters.subcategory.value,
            package_id: filters.package.value,
            assignment: filters.assignment.value,
            search: filters.search.value.trim(),
            page: page
        };
        Object.entries(params).forEach(function (entry) {
            if (entry[1]) url.searchParams.set(entry[0], entry[1]);
        });

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: state.request.signal
            });
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok) throw new Error(data.message || 'Could not load exam assignments.');
            if (!state.initialized) {
                state.original = new Set((data.assigned_exam_ids || []).map(Number));
                state.selected = new Set(state.original);
                state.initialized = true;
            }
            state.canManage = Boolean(data.can_manage);
            renderSummary(data.question);
            fillSelect(filters.group, data.filters.groups, 'All Groups', 'group_name');
            fillSelect(filters.category, data.filters.categories, 'All Categories', 'title');
            fillSelect(filters.subcategory, data.filters.subcategories, 'All Subcategories', 'title');
            fillSelect(filters.package, data.filters.packages, 'All Packages', 'name');
            renderRows(data.exams.data || []);
            renderPagination(data.exams);
            resultCount.textContent = plural(data.exams.total, 'matching exam');
            loading.classList.add('d-none');
            (data.exams.data || []).length ? tableWrap.classList.remove('d-none') : empty.classList.remove('d-none');
            updateChanges();
            if (!state.canManage) changes.textContent += ' ? View only';
        } catch (error) {
            if (error.name === 'AbortError') return;
            loading.classList.add('d-none');
            alertBox.textContent = error.message;
            alertBox.classList.remove('d-none');
        }
    }

    function resetState(questionId) {
        if (state.request) state.request.abort();
        state.questionId = Number(questionId);
        state.initialized = false;
        state.canManage = false;
        state.original = new Set();
        state.selected = new Set();
        state.examMeta = new Map();
        filters.group.value = '';
        filters.category.value = '';
        filters.subcategory.value = '';
        filters.package.value = '';
        filters.assignment.value = 'all';
        filters.search.value = '';
        summary.innerHTML = '<div class="placeholder-glow"><span class="placeholder col-3"></span><span class="placeholder col-8 d-block mt-2"></span></div>';
        rows.innerHTML = '';
        pagination.innerHTML = '';
        changes.textContent = 'Loading assignments...';
        saveButton.disabled = true;
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-exam-assignment-open');
        if (!trigger) return;
        event.preventDefault();
        resetState(trigger.dataset.questionId);
        modal.show();
        load(1);
    });

    rows.addEventListener('change', function (event) {
        const checkbox = event.target.closest('.js-assignment-exam-check');
        if (!checkbox) return;
        const id = Number(checkbox.value);
        checkbox.checked ? state.selected.add(id) : state.selected.delete(id);
        renderRows(currentVisibleExams());
        updateChanges();
    });

    filters.group.addEventListener('change', function () {
        filters.category.value = '';
        filters.subcategory.value = '';
        filters.package.value = '';
        load(1);
    });
    filters.category.addEventListener('change', function () {
        filters.subcategory.value = '';
        filters.package.value = '';
        load(1);
    });
    filters.subcategory.addEventListener('change', function () {
        filters.package.value = '';
        load(1);
    });
    filters.package.addEventListener('change', function () { load(1); });
    filters.assignment.addEventListener('change', function () { load(1); });
    filters.search.addEventListener('input', function () {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(function () { load(1); }, 400);
    });
    document.getElementById('assignmentResetFilters').addEventListener('click', function () {
        filters.group.value = '';
        filters.category.value = '';
        filters.subcategory.value = '';
        filters.package.value = '';
        filters.assignment.value = 'all';
        filters.search.value = '';
        load(1);
    });

    saveButton.addEventListener('click', async function () {
        const additions = difference(state.selected, state.original);
        const removals = difference(state.original, state.selected);
        if (!additions.length && !removals.length) return;
        const riskyRemovals = removals.filter(function (id) {
            const exam = state.examMeta.get(id);
            return exam && (exam.status === 'Active' || exam.results_count > 0);
        });
        const warning = riskyRemovals.length
            ? '<br><span class="text-warning">' + plural(riskyRemovals.length, 'removal') + ' affect active exams or exams with attempts.</span>'
            : '';
        const confirmed = window.Swal
            ? (await Swal.fire({
                icon: removals.length ? 'warning' : 'question',
                title: 'Save exam assignments?',
                html: '<strong>' + additions.length + '</strong> additions and <strong>' + removals.length + '</strong> removals will be applied.' + warning,
                showCancelButton: true,
                confirmButtonText: 'Save Assignments'
            })).isConfirmed
            : window.confirm('Apply ' + additions.length + ' additions and ' + removals.length + ' removals?');
        if (!confirmed) return;

        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
        try {
            const response = await fetch(endpoint('update'), {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ exam_ids: [...state.selected] })
            });
            const data = await response.json().catch(function () { return {}; });
            if (!response.ok) throw new Error(data.message || 'Could not save exam assignments.');
            modal.hide();
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Assignments updated', text: data.message, timer: 2200, showConfirmButton: false });
            if (window.refreshQuestionList) window.refreshQuestionList();
        } catch (error) {
            alertBox.textContent = error.message;
            alertBox.classList.remove('d-none');
            updateChanges();
        } finally {
            saveButton.innerHTML = 'Save Assignments';
        }
    });
})();
</script>
