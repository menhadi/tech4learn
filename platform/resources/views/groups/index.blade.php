@extends('layouts.master')
@section('title', 'Groups')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Groups')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add, Edit & Remove Groups</h4>
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
                            <a href="{{ route('admin.bulk-editor.index', 'groups') }}" class="btn el-btn-secondary"><i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit</a>
                            <x-google-sheets-button resource="groups" :filters="request()->query()" />
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
                    <div class="el-result-count">Showing {{ $groups->count() }} of {{ $groups->total() }}</div>
                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="groupTable">
                            <thead class="table-light">
                                <tr>
                                    {{-- <th scope="col" style="width: 50px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="checkAll"
                                                value="option">
                                        </div>
                                    </th> --}}
                                    <th class="sort" data-sort="group_name">Group Name</th>
                                    <th class="sort" data-sort="display_order">Display Order</th>
                                    <th class="sort" data-sort="action">Action</th>
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @foreach ($groups as $group)
                                <tr>
                                    {{-- <th scope="row">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="chk_child"
                                                value="{{ $group->id }}">
                                        </div>
                                    </th> --}}
                                    <td class="group_name">{{ $group->group_name }}</td>
                                    <td class="display_order">{{ $group->display_order ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <div class="edit">
                                                <button class="btn btn-sm el-btn-primary edit-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#showModal"
                                                    data-id="{{ $group->id }}"
                                                    data-group_name="{{ $group->group_name }}"
                                                    data-display_order="{{ $group->display_order }}"
                                                data-meta_title="{{ $group->meta_title }}"
                                                data-meta_description="{{ $group->meta_description }}"
                                                data-meta_keywords="{{ $group->meta_keywords }}"
                                                data-canonical_url="{{ $group->canonical_url }}"
                                                data-og_title="{{ $group->og_title }}"
                                                data-og_description="{{ $group->og_description }}"
                                                data-og_image="{{ $group->og_image }}"
                                                data-robots_meta="{{ $group->robots_meta }}"
                                                data-seo_schema="{{ $group->seo_schema }}">Edit</button>
                                            </div>
                                            <div class="remove">
                                                <button class="btn btn-sm el-btn-danger remove-item-btn"
                                                    data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                    data-id="{{ $group->id }}">Remove</button>
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
                        {{ $groups->links('vendor.pagination.custom') }}
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
            <form class="tablelist-form" autocomplete="off" method="POST" action="{{ route('groups.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="groupname-field" class="form-label">Group Name</label>
                        <input type="text" id="groupname-field" name="group_name" class="form-control"
                            placeholder="Enter Group Name" required />
                        <div class="invalid-feedback">Please enter a group name.</div>
                    </div>

                    <div class="mb-3">
                        <label for="display-order-field" class="form-label">Display Order</label>
                        <input type="number" id="display-order-field" name="display_order" class="form-control"
                            min="0" step="1" placeholder="Example: 1">
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <button class="btn btn-warning btn-sm d-inline-flex align-items-center gap-2 px-3 mb-3"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#groupSeoSettings"
                                aria-expanded="false"
                                aria-controls="groupSeoSettings">
                            <i class="ri-search-eye-line"></i>
                            <span>SEO Settings</span>
                            <span class="badge bg-light text-dark">Optional</span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>

                        <div class="collapse" id="groupSeoSettings">

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
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success" id="add-btn">Add Group</button>
                        <button type="submit" class="btn btn-success" id="edit-btn" style="display: none;">Update
                            Group</button>
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
    document.addEventListener('DOMContentLoaded', function() {
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
                fetchGroups(searchQuery);
            }, 300);
        });

        function fetchGroups(query) {
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
                    console.error('Error fetching groups:', error);
                });
        }

        // Add/Edit Modal
        document.getElementById('showModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var group_name = button.getAttribute('data-group_name');
            var display_order = button.getAttribute('data-display_order');
            var modal = this;

            const seoFields = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_title', 'og_description', 'og_image', 'robots_meta', 'seo_schema'];
            seoFields.forEach(function(field) {
                const input = modal.querySelector('#' + field + '-field');
                if (input) {
                    input.value = button.getAttribute('data-' + field) || (field === 'robots_meta' ? 'index,follow' : '');
                }
            });

            if (id) {
                modal.querySelector('.modal-title').textContent = 'Edit Group';
                modal.querySelector('#groupname-field').value = group_name;
                modal.querySelector('#display-order-field').value = display_order || '';
                modal.querySelector('#add-btn').style.display = 'none';
                modal.querySelector('#edit-btn').style.display = 'block';

                // Set form action and method for update
                modal.querySelector('form').setAttribute('action', '{{ route("groups.update", ":id") }}'.replace(':id', id));
                if (!modal.querySelector('input[name="_method"]')) {
                    modal.querySelector('form').insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                }
            } else {
                modal.querySelector('.modal-title').textContent = 'Add Group';
                modal.querySelector('#groupname-field').value = '';
                modal.querySelector('#display-order-field').value = '';
                modal.querySelector('#add-btn').style.display = 'block';
                modal.querySelector('#edit-btn').style.display = 'none';

                // Reset form action and method for create
                modal.querySelector('form').setAttribute('action', '{{ route("groups.store") }}');
                var methodInput = modal.querySelector('input[name="_method"]');
                if (methodInput) {
                    methodInput.remove();
                }
            }
        });

        // Delete Modal
        document.getElementById('deleteRecordModal').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var action = "{{ route('groups.destroy', ':id') }}";
            action = action.replace(':id', id);
            document.getElementById('delete-form').setAttribute('action', action);
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
