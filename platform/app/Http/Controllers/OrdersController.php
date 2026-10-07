<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\SalesReport;
use App\Models\Configuration;

class OrdersController extends Controller
{
    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantOrderQuery()
    {
        $query = Order::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function tenantSalesReportQuery()
    {
        $query = SalesReport::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function configuration()
    {
        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $sortable = ['id' => 'id', 'student' => 'first_name', 'items' => 'items', 'amount' => 'total', 'method' => 'payment_method', 'status' => 'status', 'date' => 'created_at'];
        $requestedSort = (string) $request->input('sort', 'date');
        $sort = array_key_exists($requestedSort, $sortable) ? $requestedSort : 'date';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $ordersQuery = $this->tenantOrderQuery()->with(['student', 'items.package']);
        if ($sort === 'items') {
            $ordersQuery->orderBy(
                OrderItem::select('name')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->orderBy('order_items.id')
                    ->limit(1),
                $direction
            );
        } else {
            $ordersQuery->orderBy($sortable[$sort], $direction);
        }
        $orders = $ordersQuery->paginate($perPage)->withQueryString();
        $configuration_detail = $this->configuration();

        return view('orders.index', compact('orders', 'configuration_detail', 'perPage', 'sort', 'direction'));
    }

    public function show($id)
    {
        $order = $this->tenantOrderQuery()->with(['items', 'student'])->findOrFail($id);
        $configuration_detail = $this->configuration();

        return view('orders.show', compact('order','configuration_detail'));
    }

    // ✅ NEW: Status Update Logic (Offline Approval)
    public function updateStatus(Request $request, $id)
    {
        $order = $this->tenantOrderQuery()->findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled'
        ]);

        $order->status = $request->status;

        // Agar completed hai, to Payment bhi Completed maano
        if ($request->status == 'completed') {
            $order->payment_status = 'Completed';
        } elseif ($request->status == 'cancelled') {
            $order->payment_status = 'Failed';
        }

        $order->save();

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }

    public function transactions(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $sortable = ['id' => 'id', 'order' => 'order_id', 'transaction' => 'transaction_id', 'method' => 'payment_gateway', 'amount' => 'amount', 'status' => 'status', 'date' => 'created_at'];
        $requestedSort = (string) $request->input('sort', 'date');
        $sort = array_key_exists($requestedSort, $sortable) ? $requestedSort : 'date';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $transactions = Transaction::query()
            ->when($this->currentTenantId(), function ($query, $tenantId) {
                $query->whereHas('order', function ($orderQuery) use ($tenantId) {
                    $orderQuery->where('organization_id', $tenantId);
                });
            })
            ->orderBy($sortable[$sort], $direction)
            ->paginate($perPage)->withQueryString();
        $configuration_detail = $this->configuration();

        return view('transactions.index', compact('transactions', 'configuration_detail', 'perPage', 'sort', 'direction'));
    }

    public function salesReports()
    {
        $salesReports = $this->tenantSalesReportQuery()->with('package')->orderBy('created_at', 'desc')->paginate(10);
        $configuration_detail = $this->configuration();

        return view('sales_reports.index', compact('salesReports','configuration_detail'));
    }
}
