@extends('layouts.master')
@section('title', 'All Orders')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'All Orders')
@endcomponent

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Order List</h4>
            </div>
            <div class="card-body">
                @php
                    $sortLink = fn ($column) => request()->fullUrlWithQuery([
                        'sort' => $column,
                        'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
                        'page' => null,
                    ]);
                    $sortIcon = fn ($column) => $sort === $column
                        ? ($direction === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line')
                        : 'ri-arrow-up-down-line';
                @endphp
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <span class="text-muted">Showing {{ $orders->count() }} of {{ $orders->total() }}</span>
                    <form method="GET" action="{{ route('orders.index') }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <select name="per_page" class="form-select" onchange="this.form.submit()" aria-label="Rows per page">
                            @foreach([50, 100, 500] as $size)
                                <option value="{{ $size }}" {{ $perPage == $size ? 'selected' : '' }}>{{ $size }} per page</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="table-responsive table-card">
                    <table class="table align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><a href="{{ $sortLink('id') }}" class="text-body">Order No <i class="{{ $sortIcon('id') }}"></i></a></th>
                                <th><a href="{{ $sortLink('student') }}" class="text-body">Student <i class="{{ $sortIcon('student') }}"></i></a></th>
                                <th><a href="{{ $sortLink('items') }}" class="text-body">Items <i class="{{ $sortIcon('items') }}"></i></a></th>
                                <th><a href="{{ $sortLink('amount') }}" class="text-body">Amount <i class="{{ $sortIcon('amount') }}"></i></a></th>
                                <th><a href="{{ $sortLink('method') }}" class="text-body">Method <i class="{{ $sortIcon('method') }}"></i></a></th>
                                <th><a href="{{ $sortLink('status') }}" class="text-body">Status <i class="{{ $sortIcon('status') }}"></i></a></th>
                                <th><a href="{{ $sortLink('date') }}" class="text-body">Date <i class="{{ $sortIcon('date') }}"></i></a></th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td class="fw-bold">{{ $order->order_number ?? $order->id }}</td>
                                <td>
                                    @if($order->student)
                                        <h6 class="mb-0">{{ $order->student->name }}</h6>
                                        <small class="text-muted">{{ $order->student->email }}</small>
                                    @else
                                        <span class="text-danger">Guest/Deleted</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Show first package name --}}
                                    @if($order->items->isNotEmpty())
                                        {{ $order->items->first()->name ?? ($order->items->first()->package->name ?? 'Course') }}
                                        @if($order->items->count() > 1)
                                            <span class="badge bg-soft-info text-info">+{{ $order->items->count() - 1 }} more</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $configuration_detail->currency }}{{ number_format($order->total_amount ?? $order->total, 2) }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst($order->payment_method) }}
                                    </span>
                                </td>
                                <td>
                                    @if($order->status == 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($order->status == 'pending')
                                        <span class="badge bg-warning">Pending</span>
                                    @else
                                        <span class="badge bg-danger">{{ ucfirst($order->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $order->created_at->format('d M, Y') }}</td>
                                <td>
                                    <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-primary">
                                        <i class="ri-eye-line align-bottom"></i> Manage
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">No orders found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $orders->links('vendor.pagination.custom') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
