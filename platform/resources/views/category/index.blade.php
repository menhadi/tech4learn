@extends('layouts.master')
@section('title', $subcategories ? 'Subcategories' : 'Categories')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', $subcategories ? 'Subcategories' : 'Categories')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove {{ $subcategories ? 'Subcategories' : 'Categories' }}</h4>
            </div>
            <div class="two-column-menu">
                <p></p>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="groupList">
                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions">
                            <button type="button" class="btn el-btn-primary add-btn" data-bs-toggle="modal"
                                id="create-btn" data-bs-target="#showModal"><i
                                    class="ri-add-line align-bottom me-1"></i> Add</button>
                            <a href="{{ route('admin.bulk-editor.index', $subcategories ? 'subcategories' : 'categories') }}" class="btn el-btn-secondary"><i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit</a>
                            <x-google-sheets-button :resource="$subcategories ? 'subcategories' : 'categories'" :filters="request()->query()" />
                        </div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" id="search-input" class="form-control search"
                                        placeholder="Search..." value="{{ request('search') }}">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="el-page-size">
                                <select id="per-page-select" class="form-select" aria-label="Rows per page">
                                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                    <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="el-result-count">Showing {{ $categories->count() }} of {{ $categories->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="groupTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="sort">Title</th>
                                    <th class="sort">{{ $subcategories ? 'Parent Category' : 'Linked Groups' }}</th>
                                    <th class="sort">Display Order</th>
                                    <th class="sort">Status</th>
                                    <th class="sort">Action</th>
                                </tr>
                            </thead>

                            <tbody class="list form-check-all">
                                @foreach ($categories as $category)
                                <tr>

                                    <td>{{ $category->title }}</td>

                                    <td>
                                        @if($subcategories) {{ $category->parent?->title ?? '-' }} @else {{ $category->groups->pluck('group_name')->join(', ') ?: 'All Groups' }} @endif
                                    </td>

                                    <td>{{ $category->display_order ?: '-' }}</td>


                                    <td>
                                        @if($category->status)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2">

                                            <div class="edit">
                                                <button class="btn btn-sm el-btn-primary edit-item-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#showModal"

                                                    data-id="{{ $category->id }}"
                                                    data-record="{{ json_encode([
                                                        'title' => $category->title,
                                                        'description' => $category->description,
                                                        'meta_title' => $category->meta_title,
                                                        'meta_description' => $category->meta_description,
                                                        'meta_keywords' => $category->meta_keywords,
                                                        'canonical_url' => $category->canonical_url,
                                                        'og_title' => $category->og_title,
                                                        'og_description' => $category->og_description,
                                                        'og_image' => $category->og_image,
                                                        'robots_meta' => $category->robots_meta,
                                                        'seo_schema' => $category->seo_schema,
                                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                                                    data-title="{{ $category->title }}"
                                                    data-parent_id="{{ $category->parent_id }}"
                                                    data-status="{{ $category->status }}"
                                                    data-show_in_header="{{ $category->show_in_header ? 1 : 0 }}"
                                                    data-header_display_order="{{ $category->header_display_order }}"
                                                    data-display_order="{{ $category->display_order }}"
                                                    data-group_orders="{{ $category->groups->mapWithKeys(fn($group) => [$group->id => $group->pivot->display_order])->toJson() }}"
                                                    data-group_ids="{{ $category->groups->pluck('id')->implode(',') }}">

                                                    Edit
                                                </button>
                                            </div>

                                            <div class="remove">
                                                <button class="btn btn-sm el-btn-danger remove-item-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $category->id }}">

                                                    Remove
                                                </button>
                                            </div>

                                        </div>
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

                    <div class="d-flex justify-content-end">
                        {{ $categories->links('vendor.pagination.custom') }}
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
            
            <form class="tablelist-form" autocomplete="off" method="POST"
                action="{{ route($subcategories ? 'subcategories.store' : 'category.store') }}">

                @csrf

                <div class="modal-body">

                    <div class="mb-3 {{ $subcategories ? '' : 'd-none' }}">
                        <label class="form-label">Parent Category</label>

                        <select name="parent_id" id="parent_id-field" class="form-select" {{ $subcategories ? 'required' : '' }}>

                            <option value="">Parent Category</option>

                            @foreach ($parentCategories as $parent)
                                <option value="{{ $parent->id }}">
                                    {{ $parent->title }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    @if(!$subcategories)
                    <div class="mb-3">
                        <label class="form-label">Linked Groups <span class="text-muted">(optional; empty means all groups)</span></label>
                        <select name="group_ids[]" id="category_group_ids-field" class="form-select" multiple>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                        <div class="row g-2 mt-2" id="category-group-orders">
                            @foreach($groups as $group)
                                <div class="col-md-6 category-group-order-wrap d-none" data-group-id="{{ $group->id }}">
                                    <label class="form-label small mb-1">Order within {{ $group->group_name }}</label>
                                    <input type="number" min="0" name="group_orders[{{ $group->id }}]" data-group-id="{{ $group->id }}" class="form-control category-group-order" placeholder="Use general order">
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Only selected groups appear here. Enter a smaller number to show this category earlier within that group; leave blank to use the general Display Order below.</small>
                    </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">{{ $subcategories ? 'Subcategory Name' : 'Category Name' }}</label>

                        <input type="text"
                            name="title"
                            id="title-field"
                            class="form-control"
                            placeholder="Enter {{ $subcategories ? 'Subcategory Name' : 'Category Name' }}"
                            required>
                    </div>

                            <div class="mb-3">
                                <label for="description-field" class="form-label">Description</label>
                                <textarea id="description-field" name="description" class="form-control" rows="4" placeholder="Short SEO-friendly {{ $subcategories ? 'subcategory' : 'category' }} description"></textarea>
                            </div>



                    <!-- <div class="mb-3">
                        <label class="form-label">Slug</label>

                        <input type="text"
                            name="slug"
                            id="slug-field"
                            class="form-control"
                            placeholder="Enter Slug"
                            required>
                    </div> -->

                    <div class="mb-3">
                        <label class="form-label">General Display Order <span class="text-muted">(optional)</span></label>
                        <input type="number" name="display_order" id="display_order-field" class="form-control" min="0" placeholder="Fallback to title">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>

                        <select name="status" id="status-field" class="form-select" required>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="border-top pt-3 mt-3 {{ $subcategories ? 'd-none' : '' }}">
                        <h6 class="mb-3">Header Settings</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-check form-switch">
                                    <input type="checkbox" name="show_in_header" value="1" id="show_in_header-field" class="form-check-input">
                                    <label class="form-check-label" for="show_in_header-field">Show in Header</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Header Order</label>
                                    <input type="number" name="header_display_order" id="header_display_order-field" class="form-control" min="0" placeholder="e.g. 1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <button class="btn btn-warning btn-sm d-inline-flex align-items-center gap-2 px-3 mb-3"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#categorySeoSettings"
                                aria-expanded="false"
                                aria-controls="categorySeoSettings">
                            <i class="ri-search-eye-line"></i>
                            <span>SEO Settings</span>
                            <span class="badge bg-light text-dark">Optional</span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>

                        <div class="collapse" id="categorySeoSettings">

                        <div class="mb-3">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="meta_title" id="meta_title-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Meta Description</label>
                            <textarea name="meta_description" id="meta_description-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Meta Keywords</label>
                            <textarea name="meta_keywords" id="meta_keywords-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Canonical URL</label>
                            <input type="url" name="canonical_url" id="canonical_url-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Title</label>
                            <input type="text" name="og_title" id="og_title-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Description</label>
                            <textarea name="og_description" id="og_description-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OG Image URL / Path</label>
                            <input type="text" name="og_image" id="og_image-field" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Robots Meta</label>
                            <select name="robots_meta" id="robots_meta-field" class="form-select">
                                <option value="index,follow">index,follow</option>
                                <option value="noindex,follow">noindex,follow</option>
                                <option value="index,nofollow">index,nofollow</option>
                                <option value="noindex,nofollow">noindex,nofollow</option>
                            </select>
                        </div>

                            <div class="mb-3">
                                <label class="form-label">SEO Schema JSON</label>
                                <textarea name="seo_schema" id="seo_schema-field" class="form-control font-monospace" rows="4"></textarea>
                            </div>
                        </div>
                    </div>


                </div>

                <div class="modal-footer">
                    <div class="hstack gap-2 justify-content-end">

                        <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                            Close
                        </button>

                        <button type="submit"
                            class="btn btn-success"
                            id="add-btn">
                            Add {{ $subcategories ? 'Subcategory' : 'Category' }}
                        </button>

                        <button type="submit"
                            class="btn btn-success"
                            id="edit-btn"
                            style="display:none;">
                            Update {{ $subcategories ? 'Subcategory' : 'Category' }}
                        </button>

                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

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

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        let debounceTimeout;
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
                fetchCategory(searchQuery);
            }, 300);
        });

        function fetchCategory(query) {
            document.querySelector('.el-table-toolbar')?.classList.add('el-filter-working');
            const url = new URL(window.location.href);
            url.searchParams.set('search', query);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#groupTable tbody');
                    document.querySelector('#groupTable tbody').innerHTML = newTableBody.innerHTML;
                    const newResultCount = doc.querySelector('.el-result-count');
                    if (newResultCount) {
                        document.querySelector('.el-result-count').textContent = newResultCount.textContent;
                    }
                    document.querySelector('.el-table-toolbar')?.classList.remove('el-filter-working');
                })
                .catch(error => {
                    document.querySelector('.el-table-toolbar')?.classList.remove('el-filter-working');
                    console.error('Error fetching categories:', error);
                });
        }

        const modal = document.getElementById('showModal');

        modal.addEventListener('show.bs.modal', function (event) {

            const button = event.relatedTarget;

            const id = button.getAttribute('data-id');

            const form = modal.querySelector('form');

            const titleField = modal.querySelector('#title-field');
            const recordFieldIds = ['description', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots_meta', 'seo_schema'];
            const displayValue = (value) => {
                if (value && typeof value === 'object') return value.en ?? Object.values(value)[0] ?? '';
                if (typeof value === 'string' && value.trim().startsWith('{')) {
                    try { return displayValue(JSON.parse(value)); } catch (error) { return value; }
                }
                return value ?? '';
            };
            const fillRecordFields = (record = {}) => {
                recordFieldIds.forEach((field) => {
                    const input = modal.querySelector(`#${field}-field`);
                    if (input) input.value = displayValue(record[field]);
                });
            };
            const parentField = modal.querySelector('#parent_id-field');
            const groupField = modal.querySelector('#category_group_ids-field');
            const statusField = modal.querySelector('#status-field');
            const showInHeaderField = modal.querySelector('#show_in_header-field');
            const headerDisplayOrderField = modal.querySelector('#header_display_order-field');
            const displayOrderField = modal.querySelector('#display_order-field');
            const groupOrderFields = modal.querySelectorAll('.category-group-order');
            const groupOrderWrappers = modal.querySelectorAll('.category-group-order-wrap');
            const syncGroupOrderVisibility = () => {
                const selected = groupField ? Array.from(groupField.selectedOptions).map(option => option.value) : [];
                groupOrderWrappers.forEach(wrapper => wrapper.classList.toggle('d-none', !selected.includes(wrapper.dataset.groupId)));
            };
            if (groupField) {
                $(groupField).off('change.categoryOrder').on('change.categoryOrder', syncGroupOrderVisibility);
            }

            const addBtn = modal.querySelector('#add-btn');
            const editBtn = modal.querySelector('#edit-btn');

            // Remove old method input first
            const oldMethodInput = form.querySelector('input[name="_method"]');

            if (oldMethodInput) {
                oldMethodInput.remove();
            }

            // RESET DROPDOWN OPTIONS
            showInHeaderField.checked = false;
            headerDisplayOrderField.value = '';
            displayOrderField.value = '';
            groupOrderFields.forEach(field => field.value = '');
            syncGroupOrderVisibility();

            parentField.querySelectorAll('option').forEach(option => {
                option.hidden = false;
            });

            // =========================
            // EDIT MODE
            // =========================
            if (id) {

                let record = {};
                try { record = JSON.parse(button.getAttribute('data-record') || '{}'); } catch (error) { console.error('Invalid category edit data', error); }
                const title = displayValue(record.title || button.getAttribute('data-title'));
                const parentId = button.getAttribute('data-parent_id');
                const status = button.getAttribute('data-status');
                const showInHeader = button.getAttribute('data-show_in_header');
                const headerDisplayOrder = button.getAttribute('data-header_display_order');
                const displayOrder = button.getAttribute('data-display_order');
                const groupOrders = JSON.parse(button.getAttribute('data-group_orders') || '{}');

                modal.querySelector('.modal-title').textContent = '{{ $subcategories ? 'Edit Subcategory' : 'Edit Category' }}';

                titleField.value = title;
                fillRecordFields(record);
                parentField.value = parentId ?? '';
                if (groupField) $(groupField).val([]).trigger('change');
                statusField.value = status;
                showInHeaderField.checked = showInHeader == '1';
                headerDisplayOrderField.value = headerDisplayOrder || '';
                displayOrderField.value = displayOrder || '';
                groupOrderFields.forEach(field => field.value = groupOrders[field.dataset.groupId] || '');
                const groupIds = (button.getAttribute('data-group_ids') || '').split(',').filter(Boolean);
                if (groupField) $(groupField).val(groupIds).trigger('change');
                syncGroupOrderVisibility();

                // Hide current category from parent dropdown
                const currentOption = parentField.querySelector(`option[value="${id}"]`);

                if (currentOption) {
                    currentOption.hidden = true;
                }

                // Buttons
                addBtn.style.display = 'none';
                editBtn.style.display = 'inline-block';

                // Update route
                form.action = '{{ url($subcategories ? 'subcategories' : 'category') }}/' + id;

                // Add PUT method
                form.insertAdjacentHTML(
                    'beforeend',
                    '<input type="hidden" name="_method" value="PUT">'
                );

            }

            // =========================
            // ADD MODE
            // =========================
            else {

                modal.querySelector('.modal-title').textContent = '{{ $subcategories ? 'Add Subcategory' : 'Add Category' }}';

                titleField.value = '';
                fillRecordFields({ robots_meta: 'index,follow' });
                parentField.value = '';
                statusField.value = 1;

                addBtn.style.display = 'inline-block';
                editBtn.style.display = 'none';

                form.action = '{{ route($subcategories ? 'subcategories.store' : 'category.store') }}';
            }
        });
    });

    document.getElementById('deleteRecordModal')
        .addEventListener('show.bs.modal', function(event) {

        let button = event.relatedTarget;

        let id = button.getAttribute('data-id');

        let action = '{{ route($subcategories ? 'subcategories.destroy' : 'category.destroy', ':id') }}';

        action = action.replace(':id', id);

        document.getElementById('delete-form')
            .setAttribute('action', action);
    });
</script>
@endsection
