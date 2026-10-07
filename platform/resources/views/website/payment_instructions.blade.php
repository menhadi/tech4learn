{{-- resources/views/website/payment_instructions.blade.php --}}

@extends('website.layouts.app') {{-- Aapka main website layout --}}

@section('title', 'Payment Instructions')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">{{ __('ui.payment_order_title', ['order' => $order->id]) }}</h4>
                </div>
                <div class="card-body">
                    <p class="lead">{{ __('ui.bank_transfer_copy') }}</p>

                    <div class="alert alert-info">
                        <h5 class="alert-heading">{{ __('ui.amount_to_pay') }}</h5>
                        <p class="fs-4 fw-bold mb-0">
                            {{ $configuration_detail->currency ?? 'VND' }} {{ number_format($order->total, 2) }}
                        </p>
                    </div>

                    <h5 class="mt-4">{{ __('ui.bank_details') }}</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th scope="row" style="width: 30%;">{{ __('ui.account_number') }}</th>
                                <td><strong>{{ $gateway->key_id }}</strong></td> {{-- key_id mein number save hai --}}
                            </tr>
                            <tr>
                                <th scope="row">{{ __('ui.account_name') }}</th>
                                <td><strong>{{ $gateway->key_secret }}</strong></td> {{-- key_secret mein name save hai --}}
                            </tr>
                            <tr>
                                <th scope="row">{{ __('ui.bank_name') }}</th>
                                <td>{{-- Yahaan aap bank ka naam manually likh sakte hain ya admin mein field add kar sakte hain --}}
                                    (Please specify Bank Name) 
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning mt-4">
                        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> {{ __('ui.important_payment_code') }}</h5>
                        <p>{{ __('ui.payment_code_copy') }}</p>
                        <p class="fs-4 fw-bold text-center bg-light p-2 rounded border">
                            <code>{{ $paymentCode }}</code>
                        </p>
                        <p class="mb-0">{{ __('ui.payment_code_warning') }}</p>
                    </div>

                    <hr>

                    <h5>{{ __('ui.what_happens_next') }}</h5>
                    <ul>
                        <li>{{ __('ui.transfer_auto_detect') }}</li>
                        <li>{{ __('ui.payment_email_confirmation') }}</li>
                        <li>{{ __('ui.access_purchased_dashboard') }}</li>
                    </ul>

                    <div class="text-center mt-4">
                        <a href="{{ route('student.dashboard') }}" class="btn btn-secondary me-2">{{ __('ui.go_my_dashboard') }}</a>
                        {{-- Aap yahaan "Check Payment Status" button bhi add kar sakte hain jo order status check kare --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
{{-- Agar Font Awesome use kar rahe hain --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
<style>
    .card-header {
        border-bottom: 0;
    }
    code {
        color: #d63384; /* Bootstrap's pink color for code */
        word-break: break-all;
    }
</style>
@endpush