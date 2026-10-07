<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use App\Models\Configuration; // ✅ Add This Line

class CouponController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantCouponQuery()
    {
        return Coupon::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function ensureTenantOwns(Coupon $coupon): void
    {
        if ($this->tenantId() && (int) ($coupon->organization_id ?? 0) !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    private function configuration()
    {
        return function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
    }

    public function index(Request $request)
    {
        $query = $this->tenantCouponQuery();

        if ($request->has('search')) {
            $query->where('code', 'like', '%' . $request->input('search') . '%');
        }

        $coupons = $query->latest()->paginate(15);
        
        // ✅ Add This: Configuration fetch karo
        $configuration = $this->configuration();

        return view('coupons.index', compact('coupons', 'configuration'));
    }

    public function create()
    {
        // ✅ Add This
        $configuration = $this->configuration();
        return view('coupons.action', compact('configuration'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'status' => 'required|boolean',
        ]);

        Coupon::create(array_merge(
            $request->only(['code', 'type', 'value', 'min_amount', 'max_uses', 'expires_at', 'status']),
            ['organization_id' => $this->tenantId()]
        ));

        return redirect()->route('coupons.index')->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        $this->ensureTenantOwns($coupon);
        // ✅ Add This
        $configuration = $this->configuration();
        return view('coupons.action', compact('coupon', 'configuration'));
    }

    // ... baaki update/destroy functions same rahenge
    public function update(Request $request, Coupon $coupon)
    {
        $this->ensureTenantOwns($coupon);
        $request->validate([
            'code' => 'required|string|unique:coupons,code,' . $coupon->id,
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
            'status' => 'required|boolean',
        ]);

        $coupon->update($request->only(['code', 'type', 'value', 'min_amount', 'max_uses', 'expires_at', 'status']));

        return redirect()->route('coupons.index')->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->ensureTenantOwns($coupon);
        $coupon->delete();
        return redirect()->route('coupons.index')->with('success', 'Coupon deleted successfully.');
    }
}
