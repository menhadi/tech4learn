(function () {
    'use strict';
    const resources = /(?:\/(subjects|topics|stopics|exams|questions|category|subcategories|groups|packages|passages|students|results|student-funnel|users)$|\/exams\/[^/]+\/add-questions$)/;
    const base = () => location.pathname.replace(/\/$/, '');
    const bulkResources = /\/(subjects|topics|stopics|exams|questions|category|subcategories|groups|packages|passages)$/;
    const pageKey = () => `admin-table-return:${base()}`;

    function rememberPage() {
        if (location.search) sessionStorage.setItem(pageKey(), location.href);
    }

    function restorePage() {
        if (location.search) return false;
        const saved = sessionStorage.getItem(pageKey());
        if (!saved || saved === location.href) return false;
        sessionStorage.removeItem(pageKey());
        location.replace(saved);
        return true;
    }

    async function confirmDelete(count) {
        const text = base() === '/subjects'
            ? `Do you really want to delete ${count} selected subject${count === 1 ? '' : 's'}? Related topics/subtopics will be removed, while questions, papers, attempts and results are preserved with their classification cleared.`
            : `Do you really want to delete ${count} selected record${count === 1 ? '' : 's'}? This action cannot be undone.`;
        if (!window.Swal) return false;
        const result = await Swal.fire({
            title: 'Are you sure?', text, icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Yes, delete', confirmButtonColor: '#d33'
        });
        return result.isConfirmed;
    }

    async function deleteSelected(boxes) {
        const ids = boxes.filter(box => box.checked).map(box => box.value);
        if (!ids.length || !await confirmDelete(ids.length)) return;

        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const failed = [];
        let completed = 0;
        let cursor = 0;

        if (window.Swal) {
            Swal.fire({
                title: 'Deleting selected records...',
                text: 'Preparing the batch. Please keep this page open.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        }

        if (base() === '/subjects') {
            try {
                const response = await fetch(base() + '/bulk-delete', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ ids })
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Bulk deletion failed.');

                if (window.Swal) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Subjects deleted',
                        text: payload.message,
                        timer: 1200,
                        showConfirmButton: false
                    });
                }
                location.reload();
                return;
            } catch (error) {
                if (window.Swal) {
                    await Swal.fire({ icon: 'error', title: 'Deletion failed', text: error?.message || 'The subjects could not be deleted.' });
                } else {
                    window.alert(error?.message || 'The subjects could not be deleted.');
                }
                return;
            }
        }

        const updateProgress = () => {
            if (!window.Swal) return;
            Swal.update({
                title: 'Deleting ' + completed + ' of ' + ids.length + ' records...',
                text: failed.length
                    ? failed.length + ' record(s) could not be deleted and will be reported when the batch finishes.'
                    : 'The batch is processing. Please keep this page open.'
            });
        };

        const deleteOne = async id => {
            try {
                const response = await fetch(base() + '/' + encodeURIComponent(id), {
                    method: 'POST',
                    credentials: 'same-origin',
                    redirect: 'manual',
                    body: '_method=DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });

                if (response.status >= 400) {
                    throw new Error('Server returned status ' + response.status);
                }
            } catch (error) {
                failed.push({ id, message: error?.message || 'Request failed' });
            } finally {
                completed++;
                updateProgress();
            }
        };

        // A small worker pool is much faster than one-by-one deletion without
        // overwhelming PHP/MySQL with hundreds of simultaneous requests.
        const worker = async () => {
            while (cursor < ids.length) {
                const index = cursor++;
                await deleteOne(ids[index]);
            }
        };

        const concurrency = Math.min(6, ids.length);
        await Promise.all(Array.from({ length: concurrency }, () => worker()));

        if (window.Swal) {
            if (failed.length) {
                await Swal.fire({
                    icon: 'warning',
                    title: 'Bulk deletion completed with warnings',
                    text: (ids.length - failed.length) + ' record(s) processed; ' + failed.length + ' request(s) failed. The table will now refresh.',
                    confirmButtonText: 'Refresh table'
                });
            } else {
                await Swal.fire({
                    icon: 'success',
                    title: 'Bulk deletion completed',
                    text: ids.length + ' selected record(s) were processed.',
                    timer: 900,
                    showConfirmButton: false
                });
            }
        }

        location.reload();
    }
    function addSelection(table) {
        if (!bulkResources.test(base())) return;
        const head = table.tHead?.rows[0];
        const actions = table.closest('.card-body')?.querySelector('.el-table-toolbar-actions');
        if (!head || !actions) return;
        // Questions already have their own permission-aware bulk selection workflow.
        if (table.querySelector('.question-checkbox, .topic-select, .stopic-select, #select-all')) return;
        const deleteSelector = '.remove-item-btn[data-id], [data-bs-target="#deleteRecordModal"][data-id]';
        const rows = [...table.tBodies[0].rows];
        if (!rows.some(row => row.querySelector(deleteSelector) || row.dataset.recordId)) return;
        const th = document.createElement('th');
        table.classList.add('has-admin-selection');
        th.className = 'admin-select-column';
        th.style.width = '42px';
        th.innerHTML = '<input class="form-check-input" type="checkbox" aria-label="Select all rows">';
        head.prepend(th);
        rows.forEach(row => {
            const td = document.createElement('td');
            td.className = 'admin-select-column';
            const id = row.querySelector(deleteSelector)?.dataset.id || row.dataset.recordId;
            const isProtected = row.dataset.deleteProtected === '1';
            const protectedReason = row.dataset.deleteProtectedReason || 'Cannot delete this exam because results already exist.';
            if (id) {
                td.innerHTML = isProtected
                    ? `<span class="admin-protected-select d-inline-flex" tabindex="0" role="button" title="${protectedReason}"><input class="form-check-input admin-row-select" type="checkbox" value="${id}" aria-label="Deletion protected" disabled style="pointer-events:none"></span>`
                    : `<input class="form-check-input admin-row-select" type="checkbox" value="${id}" aria-label="Select row">`;
            }
            row.prepend(td);
        });
        table.querySelectorAll('.admin-protected-select').forEach(wrapper => {
            const showReason = event => {
                event.preventDefault();
                event.stopPropagation();
                if (window.bootstrap?.Tooltip) {
                    const tooltip = bootstrap.Tooltip.getOrCreateInstance(wrapper, { trigger: 'manual', placement: 'top' });
                    tooltip.show();
                    window.setTimeout(() => tooltip.hide(), 2600);
                } else if (window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Deletion unavailable',
                        text: protectedReason,
                        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--el-primary').trim() || '#0f7f75'
                    });
                } else {
                    window.alert(protectedReason);
                }
            };
            wrapper.addEventListener('click', showReason);
            wrapper.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') showReason(event);
            });
        });
        const button = document.createElement('button');
        button.type = 'button'; button.disabled = true;
        button.className = 'btn el-btn-danger ms-2';
        button.innerHTML = '<i class="ri-delete-bin-line align-bottom me-1"></i> Bulk Delete';
        const add = actions.querySelector('.add-btn, #create-btn');
        add ? add.after(button) : actions.prepend(button);
        const all = th.querySelector('input');
        const boxes = [...table.querySelectorAll('.admin-row-select:not(:disabled)')];
        const sync = () => {
            const selected = boxes.filter(box => box.checked).length;
            button.disabled = !selected;
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        };
        all.onchange = () => { boxes.forEach(box => box.checked = all.checked); sync(); };
        boxes.forEach(box => box.onchange = sync);
        button.onclick = () => deleteSelected(boxes);
    }

    function enhanceCurrentTable() {
        document.querySelectorAll('.page-content table').forEach(table => {
            if (table.dataset.adminEnhanced) return;
            table.dataset.adminEnhanced = 'true';
            addSelection(table);
            addSorting(table);
        });
    }

    function addSorting(table) {
        // Remove the unused List.js marker so only the shared sort indicator is shown.
        [...table.querySelectorAll('thead th.sort')].forEach(th => th.classList.remove('sort'));
        const headers = [...table.querySelectorAll('thead th')]
            .filter(th => !th.querySelector('input') && !/action/i.test(th.textContent));
        headers.forEach(th => {
            th.classList.add('admin-sortable');
            th.style.cursor = 'pointer';
            th.title = 'Click to sort';
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');
            const indicator = document.createElement('span');
            indicator.className = 'admin-sort-indicator ri-arrow-up-down-line ms-1';
            indicator.setAttribute('aria-hidden', 'true');
            th.appendChild(indicator);

            const sortRows = () => {
                const index = [...th.parentNode.children].indexOf(th);
                const direction = th.dataset.direction === 'asc' ? 'desc' : 'asc';
                headers.forEach(item => {
                    delete item.dataset.direction;
                    item.removeAttribute('aria-sort');
                    const itemIndicator = item.querySelector('.admin-sort-indicator');
                    if (itemIndicator) itemIndicator.className = 'admin-sort-indicator ri-arrow-up-down-line ms-1';
                });
                th.dataset.direction = direction;
                th.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
                indicator.className = 'admin-sort-indicator ms-1 ' + (direction === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line');
                [...table.tBodies[0].rows]
                    .sort((a, b) => a.cells[index].textContent.trim().localeCompare(
                        b.cells[index].textContent.trim(), undefined, { numeric: true, sensitivity: 'base' }
                    ) * (direction === 'asc' ? 1 : -1))
                    .forEach(row => table.tBodies[0].append(row));
            };

            th.addEventListener('click', sortRows);
            th.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sortRows();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!resources.test(base())) return;
        const table = document.querySelector('.page-content table');
        if (!table || restorePage()) return;
        enhanceCurrentTable();
        new MutationObserver(enhanceCurrentTable).observe(document.querySelector('.page-content') || document.body, {
            childList: true, subtree: true
        });
        document.addEventListener('click', event => {
            if (event.target.closest('.edit-item-btn, a[href*="/edit"]')) rememberPage();
        });
        document.addEventListener('submit', event => {
            const method = event.target.querySelector('input[name="_method"]')?.value;
            if (/PUT|PATCH/i.test(method || '')) rememberPage();
        });
    });
}());
