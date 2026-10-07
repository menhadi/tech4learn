@extends('layouts.master')

@section('title', 'Course Packages')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    .form-switch .form-check-label { cursor: pointer; }
    
    /* Stat Cards Styling */
    .stat-card { transition: all 0.2s; border-left: 4px solid; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .border-blue { border-color: var(--el-primary, var(--vz-primary)); }
    .border-green { border-color: var(--el-primary, var(--vz-primary)); }
    .border-info { border-color: var(--el-secondary, var(--vz-warning)); }
    .border-orange { border-color: var(--el-secondary, var(--vz-warning)); }
    #packageTable {
        width: 100%;
        min-width: 980px;
        table-layout: fixed;
    }
    #packageTable .package-name-column {
        width: 28%;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    #packageTable .package-pricing-column { width: 11%; }
    #packageTable .package-groups-column { width: 22%; white-space: normal; }
    #packageTable .package-validity-column { width: 11%; }
    #packageTable .package-visibility-column { width: 14%; }
    #packageTable .package-action-column { width: 8%; }
    #packageTable .package-name-column a { white-space: normal; }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Academic')
@slot('title', 'Course Packages')
@endcomponent

@php
    $canAddPackage = user_can_route_action('packages.create', 'add');
    $canEditPackage = user_can_route_action('packages.edit', 'edit');
    $canDeletePackage = user_can_route_action('packages.destroy', 'delete');
@endphp

{{-- 1. INSIGHT CARDS FOR COACHING OWNERS --}}
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-blue h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Total Revenue</p>
                        <h2 class="mt-2 ff-secondary fw-semibold text-primary">{{ $configuration_detail->currency }}{{ number_format($stats['revenue']) }}</h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-primary-subtle rounded-circle fs-3 text-primary"><i class="ri-money-dollar-circle-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-green h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Active Packages</p>
                        <h2 class="mt-2 ff-secondary fw-semibold">{{ $stats['active'] }} <span class="fs-12 text-muted fw-normal">/ {{ $stats['total'] }}</span></h2>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-success-subtle rounded-circle fs-3 text-success"><i class="ri-store-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Best Seller</p>
                        <h5 class="mt-2 fw-bold text-dark text-truncate" style="max-width: 150px;" title="{{ $stats['top_seller'] }}">{{ $stats['top_seller'] }}</h5>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-info-subtle rounded-circle fs-3 text-info"><i class="ri-star-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-orange h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="fw-medium text-muted mb-0">Catalog Mix</p>
                        <div class="mt-2">
                            <span class="badge bg-success">{{ $stats['paid_count'] }} Paid</span>
                            <span class="badge bg-info">{{ $stats['free_count'] }} Free</span>
                        </div>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title bg-warning-subtle rounded-circle fs-3 text-warning"><i class="ri-pie-chart-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Title Setting Section --}}
<div class="row">
    <div class="col-12">
        <div class="card collapsed-card">
            <div class="card-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#titleSettings" role="button" aria-expanded="false">
                <h5 class="card-title mb-0 text-muted"><i class="ri-settings-4-line me-1"></i> Page Title Settings</h5>
                <i class="ri-arrow-down-s-line"></i>
            </div>
            <div class="collapse" id="titleSettings">
                <div class="card-body border-top">
                    <form action="{{ route('website.title.update') }}" method="post">
                        @csrf
                        <input type="hidden" name="id" value="3">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-5">
                                <label class="form-label">Main Title</label>
                                <input type="text" name="title" value="{{ $titles->title }}" class="form-control">
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label">Sub Title</label>
                                <input type="text" name="sub_title" value="{{ $titles->sub_title }}" class="form-control">
                            </div>
                            <div class="col-lg-2">
                                <button class="btn btn-success w-100">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3. Packages Table --}}
<div class="row">
    <div class="col-lg-12">
        <div class="card" id="packagesPanel">
            <!-- <div class="card-header border-bottom-0">
                <div class="d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">Packages List</h5>
                    <div class="flex-shrink-0">
                        <div class="d-flex gap-1">
                            <a href="{{ route('packages.create') }}" class="btn btn-success add-btn"><i class="ri-add-line align-bottom me-1"></i> Create Package</a>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="card-header">
                <h4 class="card-title mb-0">Packages List</h4>
                <div class="row mt-3 align-items-stretch">
                    <div class="col-md-2">
                        <select id="groupFilter" class="form-control select2">
                            <option value="">All Groups</option>
                            @foreach($groups as $examGroup)
                                <option
                                    value="{{ $examGroup['id'] }}"
                                    {{ request()->get('group') == $examGroup['id'] ? 'selected' : '' }}
                                >
                                    {{ $examGroup['group_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="tagFilter" class="form-control select2">
                            <option value="">All Tags</option>
                            @foreach($packageTags ?? collect() as $packageTag)
                                <option
                                    value="{{ $packageTag->id }}"
                                    {{ request()->get('tag') == $packageTag->id ? 'selected' : '' }}
                                >
                                    {{ $packageTag->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button id="search-btn" class="btn btn-primary">Search</button>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button id="reset-btn" class="btn btn-secondary">Reset</button>
                    </div>
                </div>
            </div>
            
            <!-- <div class="card-body border-bottom-dashed border-bottom">
                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="search-box">
                            <input type="text" id="search-input" class="form-control search" placeholder="Search package name, description..." value="{{ request('search') }}">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="card-body">
                <div class="el-table-toolbar">
                    <div class="el-table-toolbar-actions">
                        @if($canAddPackage)
                            <a href="{{ route('packages.create') }}" class="btn el-btn-primary add-btn"><i class="ri-add-line align-bottom me-1"></i> Create Package</a>
                        @endif
                        @if($canEditPackage)
                            <a href="{{ route('admin.bulk-editor.index', 'packages') }}" class="btn el-btn-secondary"><i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit</a>
                            <x-google-sheets-button resource="packages" :filters="request()->query()" />
                        @endif
                    </div>
                    <div class="el-table-toolbar-controls">
                        <div class="el-filter-search">
                            <div class="search-box">
                                <input type="text" id="search-input" class="form-control search"
                                    placeholder="Search package name, description..." value="{{ request('search') }}">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                        <div class="el-page-size">
                            <select id="per-page-select" class="form-select">
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                                <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="el-result-count">Showing {{ $packages->count() }} of {{ $packages->total() }}</div>
                <div class="table-responsive table-card mb-1">
                    <table class="table align-middle" id="packageTable">
                        <thead class="table-light text-muted">
                            <tr>
                                <th class="package-name-column">Package</th>
                                <th class="package-pricing-column">Pricing</th>
                                <th class="package-groups-column">Access Groups</th>
                                <th class="package-validity-column">Validity</th>
                                <th class="package-visibility-column">Visibility</th>
                                @if($canEditPackage || $canDeletePackage)
                                    <th class="package-action-column">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="list form-check-all">
                            @foreach ($packages as $package)
                            <tr>
                                <td class="package-name-column">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h5 class="fs-14 mb-1"><a href="#" class="text-dark">{{ $package->name }}</a></h5>
                                            <p class="text-muted mb-0 fs-12">{{ ucfirst($package->package_type) }}</p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="package-pricing-column">
                                    @if($package->package_type == 'paid')
                                        @if($package->discounted_amount)
                                            <h5 class="fs-14 mb-0">{{ $configuration_detail->currency }}{{ number_format($package->discounted_amount, 2) }}</h5>
                                            <small class="text-decoration-line-through text-muted">{{ $configuration_detail->currency }}{{ number_format($package->amount, 2) }}</small>
                                        @else
                                            <h5 class="fs-14 mb-0">{{ $configuration_detail->currency }}{{ number_format($package->amount, 2) }}</h5>
                                        @endif
                                    @else
                                        <span class="badge bg-success-subtle text-success">Free</span>
                                    @endif
                                </td>

                                <td class="package-groups-column">
                                    <div class="d-flex flex-wrap gap-1">
                                            @foreach ($package->groups as $group)
                                                <span class="badge bg-light text-body border">{{ $group->group_name }}</span>
                                            @endforeach
                                            @foreach ($package->tags as $tag)
                                                <span class="badge bg-primary-subtle text-primary border">{{ $tag->name }}</span>
                                            @endforeach
                                    </div>
                                </td>

                                <td class="package-validity-column">
                                    <span class="badge bg-info-subtle text-info"><i class="ri-time-line align-bottom me-1"></i> {{ $package->expiry_days }} Days</span>
                                </td>
                                
                                <td class="package-visibility-column">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input package-status-toggle" type="checkbox" role="switch" 
                                               id="status-switch-{{ $package->id }}" 
                                               data-id="{{ $package->id }}" 
                                               {{ $canEditPackage ? '' : 'disabled' }}
                                               {{ $package->status ? 'checked' : '' }}>
                                        <label class="form-check-label {{ $package->status ? 'text-success' : 'text-danger' }}" 
                                               for="status-switch-{{ $package->id }}">
                                            {{ $package->status ? 'Published' : 'Hidden' }}
                                        </label>
                                    </div>
                                </td>

                                @if($canEditPackage || $canDeletePackage)
                                    <td class="package-action-column">
                                        <div class="dropdown">
                                            <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ri-more-fill"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @if($canEditPackage)
                                                    <li><a class="dropdown-item" href="{{ route('packages.edit', $package->id) }}"><i class="ri-pencil-fill align-bottom me-2 text-muted"></i> Edit</a></li>
                                                @endif
                                                @if($canEditPackage && $canDeletePackage)
                                                    <li class="dropdown-divider"></li>
                                                @endif
                                                @if($canDeletePackage)
                                                    <li>
                                                        <button class="dropdown-item text-danger remove-item-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteRecordModal"
                                                            data-id="{{ $package->id }}">
                                                            <i class="ri-delete-bin-fill align-bottom me-2"></i> Delete
                                                        </button>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
                    @if($packages->isEmpty())
                        <div class="noresult py-5">
                            <div class="text-center">
                                <lord-icon src="https://cdn.lordicon.com/msoeawqm.json" trigger="loop" colors="primary:#121331,secondary:#08a88a" style="width:75px;height:75px"></lord-icon>
                                <h5 class="mt-2">No Packages Found</h5>
                                <p class="text-muted mb-0">Try creating a new package or adjust filters.</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $packages->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>

@if($canDeletePackage)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button></div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5"><h4>Are you Sure?</h4><p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record?</p></div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <form id="delete-form" method="POST" action="">@csrf @method('DELETE')<button type="submit" class="btn w-sm btn-danger">Yes, Delete It!</button></form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/prismjs/prism.js') }}"></script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        function applyFilters() {
            const group = $('#groupFilter').val();
            const tag = $('#tagFilter').val();
            const search = $('#search-input').val();
            const perPage = $('#per-page-select').val();

            const url = new URL(window.location.href.split('?')[0]); // Base URL without old params
            if (group) url.searchParams.set('group', group);
            if (tag) url.searchParams.set('tag', tag);
            if (search) url.searchParams.set('search', search);
            if (perPage) url.searchParams.set('per_page', perPage);

            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#packagesPanel'], document.querySelector('#packagesPanel'));
                return;
            }

            window.location.href = url.toString();
        }

        const urlParams = new URLSearchParams(window.location.search);
        const selectedGroup = urlParams.get('group');
        const selectedSearch = urlParams.get('search');

        // Search button click
        $(document).on('click', '#search-btn', function() {
            applyFilters();
        });

        $(document).on('click', '#filterBtn', function(event) {
            applyFilters();
        });

        // Search on Enter key press
        $(document).on('keyup', '#search-input', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Per-page select change
        $(document).on('change', '#per-page-select', function() {
            applyFilters();
        });

        // Reset button click
        $(document).on('click', '#reset-btn', function() {
            const url = new URL(window.location.href.split('?')[0]);
            if (window.ExamLiteAjaxFilter) {
                window.ExamLiteAjaxFilter.loadUrl(url, ['#packagesPanel'], document.querySelector('#packagesPanel'));
                return;
            }
            window.location.href = url.toString();
        });

        // Search Logic
        let debounceTimeout;
        $(document).on('input', '#search-input', function() {
            const searchInput = this;
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('search', searchInput.value);
                if (window.ExamLiteAjaxFilter) {
                    window.ExamLiteAjaxFilter.loadUrl(url, ['#packagesPanel'], document.querySelector('#packagesPanel'));
                    return;
                }
                window.location.href = url.toString();
            }, 500);
        });
        
        // Delete Modal Logic
        var deleteModal = document.getElementById('deleteRecordModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var action = "{{ route('packages.destroy', ':id') }}".replace(':id', id);
                document.getElementById('delete-form').setAttribute('action', action);
            });
        }

        // SweetAlerts
        @if(session('success')) Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 2000, showConfirmButton: false }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 2000, showConfirmButton: false }); @endif

        // Status Toggle Logic
        $(document).on('change', '.package-status-toggle', function() {
            let packageId = $(this).data('id');
            let status = $(this).is(':checked'); 
            let label = $(`label[for="status-switch-${packageId}"]`);
            let toggle = $(this);

            // UI Update (Optimistic)
            label.text(status ? 'Published' : 'Hidden').toggleClass('text-success text-danger');

            $.ajax({
                url: `/packages/${packageId}/toggle-status`,
                type: 'POST',
                data: { '_token': '{{ csrf_token() }}', 'status': status },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Updated!', timer: 1000, showConfirmButton: false, toast: true, position: 'top-end' });
                    } else {
                        // Revert if failed
                        toggle.prop('checked', !status);
                        label.text(!status ? 'Published' : 'Hidden').toggleClass('text-success text-danger');
                    }
                },
                error: function() {
                    toggle.prop('checked', !status); // Revert
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong' });
                }
            });
        });
    });
</script>
@endsection
