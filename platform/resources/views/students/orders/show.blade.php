@extends('students.layouts.app')
@section('title', 'Order Details')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'My Orders')
@slot('title', 'Order #' . $order->order_number)
@endcomponent
<style>
    .student-order-status { border-radius: 999px; padding: 6px 12px; font-weight: 800; display: inline-flex; }
    .student-order-status.completed { background: var(--el-primary-soft); color: var(--el-primary); }
    .student-order-status.pending { background: var(--el-secondary-soft); color: var(--el-secondary); }
    .student-order-status.failed { background: #fee2e2; color: #b91c1c; }
    .student-order-action { background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff); border-radius: 6px; font-weight: 700; }
    .student-order-action:hover { background: var(--el-primary-dark, var(--el-primary)); border-color: var(--el-primary-dark, var(--el-primary)); color: var(--theme-button-text, #fff); }
    .student-order-table thead th { background: var(--el-primary-soft); color: var(--el-heading); }
</style>

<div class="row">
    <div class="col-lg-12">
        {{-- 1. Order Status & Info Card --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ __('ui.order_information') }}</h5>
                <span class="student-order-status {{ $order->status == 'completed' ? 'completed' : ($order->status == 'pending' ? 'pending' : 'failed') }}">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <p class="text-muted mb-1">{{ __('ui.order_number') }}</p>
                        <h5 class="fs-14">{{ $order->order_number }}</h5>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted mb-1">{{ __('ui.date') }}</p>
                        <h5 class="fs-14">{{ $order->created_at->format('d M Y, h:i A') }}</h5>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted mb-1">{{ __('ui.payment_method') }}</p>
                        <h5 class="fs-14">{{ ucfirst($order->payment_method) }}</h5>
                    </div>
                    <div class="col-md-3">
                        <p class="text-muted mb-1">{{ __('ui.payment_status') }}</p>
                        <h5 class="fs-14" style="color: {{ $order->payment_status == 'Completed' ? 'var(--el-primary)' : 'var(--el-secondary)' }};">
                            {{ ucfirst($order->payment_status) }}
                        </h5>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Items List --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ __('ui.order_items') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle mb-0 student-order-table">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('ui.package_course_name') }}</th>
                                <th class="text-end">{{ __('messages.purchased_show_table_price') }}</th>
                                <th class="text-center">{{ __('messages.purchased_show_table_qty') }}</th>
                                <th class="text-end">{{ __('messages.purchased_table_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <h5 class="fs-14 mb-1">{{ $item->name ?? ($item->package->name ?? __('ui.course')) }}</h5>
                                </td>
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($item->price, 2) }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($item->price * $item->quantity, 2) }}</td>
                            </tr>
                            @endforeach
                            
                            {{-- Calculations --}}
                            <tr class="border-top">
                                <td colspan="3" class="text-end">{{ __('ui.sub_total') }}</td>
                                {{-- ✅ FIXED: Use $order->total instead of $order->total_amount --}}
                                <td class="text-end">{{ $configuration_detail->currency }}{{ number_format($order->total + ($order->discount ?? 0), 2) }}</td>
                            </tr>
                            @if($order->discount > 0)
                            <tr>
                                <td colspan="3" class="text-end text-success">Discount ({{ $order->coupon_code ?? __('ui.applied') }}):</td>
                                <td class="text-end text-success">-{{ $configuration_detail->currency }}{{ number_format($order->discount, 2) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="3" class="text-end fw-bold">{{ __('messages.purchased_show_grand_total') }}</td>
                                {{-- ✅ FIXED: Use $order->total --}}
                                <td class="text-end fw-bold fs-16">{{ $configuration_detail->currency }}{{ number_format($order->total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('student.orders.index') }}" class="btn student-order-action"><i class="ri-arrow-left-line align-bottom me-1"></i> {{ __('ui.back_to_history') }}</a>
        </div>
    </div>
</div>
@endsection
