@extends('layouts.master')
@section('title', 'Order Details')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Orders')
@slot('title', 'Order #' . ($order->order_number ?? $order->id))
@endcomponent

<div class="row">
    {{-- Left Column: Order Info --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Order Items</h5>
                <span class="badge fs-12 {{ $order->status == 'completed' ? 'bg-success' : 'bg-warning' }}">
                    Status: {{ ucfirst($order->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Package Name</th>
                                <th class="text-end">Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <h6 class="fs-14 mb-1">{{ $item->name ?? ($item->package->name ?? 'Unknown Package') }}</h6>
                                </td>
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($item->price, 2) }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                            </tr>
                            @endforeach
                            <tr class="border-top">
                                <td colspan="3" class="text-end fw-medium">Sub Total</td>
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($order->total_amount ?? $order->total, 2) }}</td>
                            </tr>
                            @if($order->discount > 0)
                            <tr>
                                <td colspan="3" class="text-end text-success">Discount ({{ $order->coupon_code }})</td>
                                <td class="text-end text-success">-{{ $configuration_detail->currency }}{{ number_format($order->discount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Grand Total</td>
                                <td class="text-end fw-bold">{{ $configuration_detail->currency }}{{ number_format($order->total_amount ?? $order->total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('orders.index') }}" class="btn btn-light">Back to List</a>
        </div>
    </div>

    {{-- Right Column: Student Info & Actions --}}
    <div class="col-lg-4">
        {{-- Student Details --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Student Details</h5>
            </div>
            <div class="card-body">
                @if($order->student)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded-circle bg-light d-flex align-items-center justify-content-center">
                                <i class="ri-user-3-line fs-20 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="fs-14 mb-1">{{ $order->student->name }}</h6>
                            <p class="text-muted mb-0">Student</p>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="ri-mail-line me-2 text-muted"></i> {{ $order->student->email }}</li>
                        <li class="mb-2"><i class="ri-phone-line me-2 text-muted"></i> {{ $order->student->phone }}</li>
                    </ul>
                @else
                    <div class="alert alert-warning">Student data not found.</div>
                @endif
            </div>
        </div>

        {{-- Payment Info --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Payment Info</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Method:</span>
                    <span class="fw-medium">{{ ucfirst($order->payment_method) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge {{ $order->payment_status == 'Completed' ? 'bg-success' : 'bg-warning' }}">
                        {{ $order->payment_status }}
                    </span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Date:</span>
                    <span>{{ $order->created_at->format('d M Y') }}</span>
                </div>
            </div>
        </div>

        {{-- ✅ ADMIN ACTION: Approve Offline Payment --}}
        @if($order->status == 'pending')
        <div class="card border-warning border-2">
            <div class="card-header bg-warning-subtle">
                <h5 class="card-title mb-0 text-warning-emphasis">Take Action</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">If you have received the payment (Offline/Cash), mark this order as Completed to activate the course for the student.</p>
                
                <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" data-swal-confirm="Are you sure you want to approve this order?">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="ri-check-double-line align-middle me-1"></i> Mark as Completed
                    </button>
                </form>

                <hr>
                
                <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" data-swal-confirm="Cancel this order?">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                        Cancel Order
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection