@extends('layouts.master')

@section('title', 'Razorpay Settings')

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Configuration')
@slot('title', 'Razorpay Payment Settings')
@endcomponent

<div class="row">
    <div class="col-lg-12 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Manage Razorpay Integration</h4>
                <p class="text-muted mb-0">Enter your Razorpay API keys here. You can find these in your Razorpay Dashboard > Settings > API Keys.</p> 
            </div>
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                @php
                    // Agar gateway data nahi hai to empty object banao
                    $gateway = $gateway ?? (object) ['key_id' => '', 'key_secret' => '', 'webhook_secret' => '', 'status' => 0];
                @endphp

                <form action="{{ route('payment-gateway.store') }}" method="POST">
                    @csrf
                    
                    {{-- Razorpay Key ID --}}
                    <div class="mb-3">
                        <label for="key_id" class="form-label">Razorpay Key ID</label>
                        <input type="text" name="key_id" id="key_id" class="form-control" required
                               value="{{ old('key_id', $gateway->key_id) }}"
                               placeholder="rzp_test_...">
                        <div class="form-text">Paste your Razorpay Key ID here.</div> 
                    </div>

                    {{-- Razorpay Key Secret --}}
                    <div class="mb-3">
                        <label for="key_secret" class="form-label">Razorpay Key Secret</label> 
                        <input type="text" name="key_secret" id="key_secret" class="form-control" required
                               value="{{ old('key_secret', $gateway->key_secret) }}"
                               placeholder="Enter your Key Secret">
                        <div class="form-text">Paste your Razorpay Key Secret here.</div>
                    </div>

                    {{-- Razorpay Webhook Secret (Optional) --}}
                    <div class="mb-3">
                        <label for="webhook_secret" class="form-label">Webhook Secret (Optional)</label>
                        <input type="password" name="webhook_secret" id="webhook_secret" class="form-control"
                               value="{{ old('webhook_secret', $gateway->webhook_secret) }}"
                               placeholder="Secret used for Webhook verification">
                        <div class="form-text">Only required if you are using Webhooks for auto-confirmation.</div>
                    </div>

                    {{-- Status Toggle --}}
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="1" {{ (old('status', $gateway->status) == 1) ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (old('status', $gateway->status) == 0) ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Save Razorpay Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection