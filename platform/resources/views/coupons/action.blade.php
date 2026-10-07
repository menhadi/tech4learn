@extends('layouts.master')
@section('title', isset($coupon) ? 'Edit Coupon' : 'Add Coupon')

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($coupon) ? 'Edit Coupon' : 'Add Coupon')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ isset($coupon) ? 'Edit Coupon' : 'Add Coupon' }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ isset($coupon) ? route('coupons.update', $coupon->id) : route('coupons.store') }}" method="POST">
                    @csrf
                    @if(isset($coupon))
                    @method('PUT')
                    @endif

                    <div class="row">

                        {{-- Coupon Code --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="code" class="form-label">Coupon Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $coupon->code ?? '') }}" style="text-transform: uppercase;" required>
                                <small class="text-muted">Unique code (e.g. SUMMER50)</small>
                            </div>
                        </div>

                        {{-- Coupon Type (Dropdown) --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="fixed" {{ old('type', $coupon->type ?? '') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                    <option value="percent" {{ old('type', $coupon->type ?? '') == 'percent' ? 'selected' : '' }}>Percentage (%)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Coupon Value --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="value" class="form-label">Discount Value <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    {{-- ✅ FIXED: Dynamic Symbol ID --}}
                                    <span class="input-group-text" id="value-symbol">{{ $configuration->currency ?? '$' }}</span>
                                    <input type="number" step="0.01" class="form-control" id="value" name="value" value="{{ old('value', $coupon->value ?? '') }}" required>
                                </div>
                            </div>
                        </div>

                        {{-- Min Cart Amount --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="min_amount" class="form-label">Min Cart Amount (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $configuration->currency ?? '$' }}</span>
                                    <input type="number" step="0.01" class="form-control" id="min_amount" name="min_amount" value="{{ old('min_amount', $coupon->min_amount ?? '') }}">
                                </div>
                            </div>
                        </div>

                        {{-- Expiry Date --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="expires_at" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="expires_at" name="expires_at" value="{{ old('expires_at', isset($coupon->expires_at) ? \Carbon\Carbon::parse($coupon->expires_at)->format('Y-m-d') : '') }}">
                            </div>
                        </div>

                        {{-- Max Uses --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label for="max_uses" class="form-label">Max Uses (Total)</label>
                                <input type="number" class="form-control" id="max_uses" name="max_uses" value="{{ old('max_uses', $coupon->max_uses ?? '') }}">
                                <small class="text-muted">Leave blank for unlimited</small>
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="1" {{ old('status', $coupon->status ?? '1') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $coupon->status ?? '1') == '0' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Buttons --}}
                        <div class="col-lg-6">
                             <a href="{{ route('coupons.index') }}" class="btn btn-light w-100">Cancel</a>
                        </div>
                        <div class="col-lg-6">
                             <button type="submit" class="btn btn-success w-100">{{ isset($coupon) ? 'Update Coupon' : 'Create Coupon' }}</button>
                        </div>

                    </div> 
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const typeSelect = document.getElementById('type');
        const symbolSpan = document.getElementById('value-symbol');
        
        // ✅ Configuration Currency passed from Controller
        const currency = "{{ $configuration->currency ?? '$' }}";

        function updateSymbol() {
            if(typeSelect.value === 'percent') {
                symbolSpan.textContent = '%';
            } else {
                symbolSpan.textContent = currency;
            }
        }

        if(typeSelect) {
            typeSelect.addEventListener('change', updateSymbol);
            updateSymbol(); // Initialize on page load
        }
    });
</script>
@endsection