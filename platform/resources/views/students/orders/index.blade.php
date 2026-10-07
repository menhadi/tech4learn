@extends('students.layouts.app')
@section('title', 'Purchase History')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Dashboard')
@slot('title', 'My Purchase History')
@endcomponent

<style>
    .student-orders-card { border: 1px solid var(--el-border); border-radius: 8px; box-shadow: 0 4px 16px rgba(2,6,23,.05); overflow: hidden; }
    .student-orders-card .card-header { background: #fff; border-bottom: 1px solid var(--el-border); padding: 18px 22px; }
    .student-orders-table { border: 1px solid var(--el-border); border-radius: 8px; overflow: hidden; }
    .student-orders-table table { color: var(--el-heading); }
    .student-orders-table thead th { background: var(--el-primary-soft); color: var(--el-heading); border-bottom: 1px solid var(--el-border); font-size: 13px; text-transform: uppercase; letter-spacing: .01em; white-space: nowrap; }
    .student-orders-table thead th[data-sort] { cursor: pointer; user-select: none; }
    .student-orders-table thead th[data-sort]::after { content: "\ea4e"; font-family: remixicon; margin-left: 8px; color: var(--el-muted); font-size: 13px; }
    .student-orders-table thead th.sorted-asc::after { content: "\ea78"; color: var(--el-primary); }
    .student-orders-table thead th.sorted-desc::after { content: "\ea4e"; color: var(--el-primary); }
    .student-orders-table tbody tr:nth-child(even) { background: var(--el-surface-muted); }
    .student-orders-table tbody td { border-color: var(--el-border); vertical-align: middle; }
    .student-order-amount { color: var(--el-primary); font-weight: 800; }
    .student-order-status { border-radius: 999px; padding: 6px 12px; font-weight: 800; display: inline-flex; }
    .student-order-status.completed { background: var(--el-primary-soft); color: var(--el-primary); }
    .student-order-status.pending { background: var(--el-secondary-soft); color: var(--el-secondary); }
    .student-order-status.failed { background: #fee2e2; color: #b91c1c; }
    .student-order-action { background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff); border-radius: 6px; font-weight: 700; }
    .student-order-action:hover { background: var(--el-primary-dark, var(--el-primary)); border-color: var(--el-primary-dark, var(--el-primary)); color: var(--theme-button-text, #fff); }
    .student-order-empty-icon { background: var(--el-primary-soft); color: var(--el-primary); }
</style>

<div class="row">
    <div class="col-12">
        <div class="card student-orders-card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('ui.my_orders') }}</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive student-orders-table">
                    <table class="table align-middle mb-0" id="studentOrdersTable">
                        <thead>
                            <tr>
                                <th data-sort="text">{{ __('ui.order_no') }}</th>
                                <th data-sort="number">{{ __('ui.date') }}</th>
                                <th data-sort="text">{{ __('ui.packages_items') }}</th>
                                <th data-sort="number">{{ __('ui.amount') }}</th>
                                <th data-sort="text">{{ __('messages.purchased_table_payment') }}</th>
                                <th data-sort="text">{{ __('ui.status') }}</th>
                                <th>{{ __('messages.dash_col_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                            <tr>
                                <td class="fw-medium">{{ $order->order_number ?? $order->id }}</td>
                                <td data-sort-value="{{ $order->created_at->timestamp }}">
                                    {{ $order->created_at->format('d M Y') }}<br>
                                    <small class="text-muted">{{ $order->created_at->format('h:i A') }}</small>
                                </td>
                                <td>
                                    @if($order->items && $order->items->count() > 0)
                                        @foreach($order->items->take(2) as $item)
                                            <div class="d-flex align-items-center">
                                                <i class="ri-book-open-line text-muted me-2"></i>
                                                {{ $item->name ?? ($item->package->name ?? 'Course Package') }}
                                            </div>
                                        @endforeach
                                        @if($order->items->count() > 2)
                                            <small class="text-muted ps-4">{{ __('ui.more_count', ['count' => $order->items->count() - 2]) }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="student-order-amount" data-sort-value="{{ (float) $order->total }}">
                                    {{-- ✅ FIXED: Use $order->total --}}
                                    {{ $configuration_detail->currency }}{{ number_format($order->total, 2) }}
                                </td>
                                <td>
                                    {{ ucfirst($order->payment_method) }}
                                </td>
                                <td>
                                    <span class="student-order-status {{ $order->status == 'completed' ? 'completed' : ($order->status == 'pending' ? 'pending' : 'failed') }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('student.orders.show', $order->id) }}" class="btn btn-sm student-order-action">
                                        <i class="ri-eye-fill align-bottom me-1"></i> View Details
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="avatar-lg mx-auto mb-3">
                                        <div class="avatar-title student-order-empty-icon rounded-circle display-5">
                                            <i class="ri-shopping-cart-2-off-line"></i>
                                        </div>
                                    </div>
                                    <h5 class="text-muted">{{ __('ui.no_orders_found') }}</h5>
                                    <p class="text-muted">{{ __('ui.no_courses_purchased') }}</p>
                                    <a href="{{ route('student.courses.index') }}" class="btn student-order-action mt-2">{{ __('ui.explore_courses') }}</a>
                                </td>
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

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('studentOrdersTable');
    if (!table) return;

    table.querySelectorAll('thead th[data-sort]').forEach((header, index) => {
        header.addEventListener('click', () => {
            const type = header.dataset.sort || 'text';
            const direction = header.classList.contains('sorted-asc') ? 'desc' : 'asc';

            table.querySelectorAll('thead th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
            header.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(row => row.children.length > 1);
            rows.sort((a, b) => {
                const cellA = a.children[index];
                const cellB = b.children[index];
                const rawA = cellA?.dataset.sortValue || cellA?.innerText.trim() || '';
                const rawB = cellB?.dataset.sortValue || cellB?.innerText.trim() || '';
                const valueA = type === 'number' ? parseFloat(rawA) || 0 : rawA.toLowerCase();
                const valueB = type === 'number' ? parseFloat(rawB) || 0 : rawB.toLowerCase();

                if (valueA < valueB) return direction === 'asc' ? -1 : 1;
                if (valueA > valueB) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            const tbody = table.querySelector('tbody');
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>
@endsection
