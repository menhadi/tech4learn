<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\SalesReport;
use App\Models\Configuration;
use Illuminate\Support\Facades\Auth;

class PurchasedCourse extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function studentOrderQuery()
    {
        return Order::where('student_id', Auth::guard('student')->id())
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            });
    }

    private function configuration()
    {
        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    public function purchasedcourse()
    {
        $orders = $this->studentOrderQuery()->latest()->paginate(10);
        $configuration_detail = $this->configuration();

        return view('students.orders.index', compact('orders','configuration_detail'));
    }

    public function show($id)
    {
        $order = $this->studentOrderQuery()->with('items')->findOrFail($id);
        $configuration_detail = $this->configuration();

        return view('students.orders.show', compact('order','configuration_detail'));
    }
}
