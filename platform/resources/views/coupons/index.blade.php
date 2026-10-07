@extends('layouts.master')
@section('title', 'Coupons')
@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Coupons')
@endcomponent
@php
    $canAddCoupon = user_can_route_action('coupons.create', 'add');
    $canEditCoupon = user_can_route_action('coupons.edit', 'edit');
    $canDeleteCoupon = user_can_route_action('coupons.destroy', 'delete');
@endphp

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Manage Coupons</h4>
            </div>
            <div class="card-body">
                <div class="listjs-table" id="couponList">
                    <div class="row g-4 mb-3">
                        <div class="col-sm-auto">
                            <div>
                                @if($canAddCoupon)
                                <a href="{{ route('coupons.create') }}" class="btn btn-success add-btn"><i class="ri-add-line align-bottom me-1"></i> Add New Coupon</a>
                                @endif
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="d-flex justify-content-sm-end">
                                <form action="{{ route('coupons.index') }}" method="GET">
                                    <div class="search-box ms-2">
                                        <input type="text" name="search" class="form-control search" placeholder="Search by code..." value="{{ request('search') }}">
                                        <i class="ri-search-line search-icon"></i>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive table-card mt-3 mb-1">
                        <table class="table align-middle table-nowrap" id="customerTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Min Amount</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                    @if($canEditCoupon || $canDeleteCoupon)
                                    <th>Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="list form-check-all">
                                @forelse($coupons as $coupon)
                                <tr>
                                    <td class="fw-bold">{{ $coupon->code }}</td>
                                    <td>
                                        <span class="badge {{ $coupon->type == 'fixed' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning' }} text-uppercase">
                                            {{ $coupon->type }}
                                        </span>
                                    </td>
                                    <td>
                                        {{-- ✅ FIXED: Dynamic Currency Symbol --}}
                                        @if($coupon->type == 'fixed')
                                            {{ $configuration->currency ?? '$' }}{{ number_format($coupon->value, 2) }}
                                        @else
                                            {{ number_format($coupon->value, 2) }}%
                                        @endif
                                    </td>
                                    <td>
                                        @if($coupon->min_amount)
                                            {{ $configuration->currency ?? '$' }}{{ number_format($coupon->min_amount, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($coupon->expires_at)
                                            {{ \Carbon\Carbon::parse($coupon->expires_at)->format('d M, Y') }}
                                        @else
                                            <span class="text-success">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($coupon->status)
                                            <span class="badge bg-success-subtle text-success">Active</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger">Inactive</span>
                                        @endif
                                    </td>
                                    @if($canEditCoupon || $canDeleteCoupon)
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if($canEditCoupon)
                                            <div class="edit">
                                                <a href="{{ route('coupons.edit', $coupon->id) }}" class="btn btn-sm btn-success edit-item-btn">Edit</a>
                                            </div>
                                            @endif
                                            @if($canDeleteCoupon)
                                            <div class="remove">
                                                <button class="btn btn-sm btn-danger remove-item-btn" data-bs-toggle="modal" data-bs-target="#deleteRecordModal" data-id="{{ $coupon->id }}">Remove</button>
                                            </div>
                                            @endif
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ ($canEditCoupon || $canDeleteCoupon) ? 7 : 6 }}" class="text-center text-muted">No coupons found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        {{ $coupons->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canDeleteCoupon)
<div class="modal fade zoomIn" id="deleteRecordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <lord-icon src="https://cdn.lordicon.com/gsqxdxog.json" trigger="loop" colors="primary:#f7b84b,secondary:#f06548" style="width:100px;height:100px"></lord-icon>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you Sure ?</h4>
                        <p class="text-muted mx-4 mb-0">Are you Sure You want to Remove this Record ?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss=\"modal\">Close</button>
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
@endif
@endsection
@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Delete Modal Logic
        var deleteModal = document.getElementById('deleteRecordModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var id = button.getAttribute('data-id');
                var action = "{{ route('coupons.destroy', ':id') }}";
                action = action.replace(':id', id);
                document.getElementById('delete-form').setAttribute('action', action);
            });
        }

        @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 3000, showConfirmButton: false });
        @endif

        @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', timer: 3000, showConfirmButton: false });
        @endif
    });
</script>
@endsection
