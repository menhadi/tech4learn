@extends('website.layouts.app')

@section('title', __('website.checkout_title'))

@section('content')

@php
    $checkoutCurrency = $configuration->currency ?? 'Rs.';
@endphp

<style>
    :root {
        --checkout-primary: var(--theme-primary, #0f766e);
        --checkout-secondary: var(--theme-secondary, #f59e0b);
        --checkout-heading: var(--theme-heading, #0f172a);
        --checkout-muted: var(--theme-text, #64748b);
        --checkout-border: color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        --checkout-soft: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        --checkout-bg: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, var(--theme-body-bg, #ffffff));
    }

    .checkout-hero {
        background: color-mix(in srgb, var(--checkout-primary) 5%, var(--theme-body-bg, #ffffff));
        border-bottom: 1px solid color-mix(in srgb, var(--checkout-primary) 14%, transparent);
        padding: 30px 0 28px;
    }
    .checkout-kicker {
        align-items: center;
        background: var(--checkout-soft);
        border: 1px solid var(--checkout-border);
        border-radius: 999px;
        color: var(--checkout-primary);
        display: inline-flex;
        font-size: 13px;
        font-weight: 800;
        gap: 8px;
        margin-bottom: 10px;
        padding: 7px 14px;
        text-transform: uppercase;
    }
    .checkout-hero-title {
        color: var(--checkout-heading);
        font-size: clamp(1.8rem, 4vw, 2.45rem);
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1.12;
        margin: 0;
    }
    .checkout-breadcrumb {
        align-items: center;
        background: transparent;
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 12px 0 0;
        padding: 0;
    }
    .checkout-breadcrumb .breadcrumb-item,
    .checkout-breadcrumb .breadcrumb-item a {
        color: var(--checkout-muted) !important;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
    }
    .checkout-breadcrumb .breadcrumb-item a:hover {
        color: var(--checkout-primary) !important;
    }
    .checkout-breadcrumb .breadcrumb-item.active {
        color: var(--checkout-primary) !important;
    }
    .checkout-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
        color: color-mix(in srgb, var(--checkout-primary) 50%, var(--checkout-muted));
    }
    .checkout-progress {
        position: relative;
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    .checkout-progress::before {
        content: '';
        position: absolute;
        top: 15px;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--checkout-border);
        z-index: 0;
        border-radius: 10px;
    }
    .step {
        position: relative;
        z-index: 1;
        text-align: center;
        background: #fff;
        padding: 0 10px;
    }
    .step-icon {
        width: 35px;
        height: 35px;
        background: #e2e8f0;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 5px;
        font-weight: bold;
        font-size: 14px;
        transition: all 0.3s;
    }
    .step-active .step-icon {
        background: var(--checkout-primary);
        transform: scale(1.2);
        box-shadow: 0 0 0 5px color-mix(in srgb, var(--checkout-primary) 16%, transparent);
    }
    .step-done .step-icon {
        background: var(--checkout-primary);
    }
    .step-pay .step-icon,
    .step-exam .step-icon {
        background: var(--checkout-secondary);
        color: white;
        box-shadow: 0 0 0 5px color-mix(in srgb, var(--checkout-secondary) 16%, transparent);
    }
    .step-label {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 5px;
        display: block;
    }
    .step-active .step-label {
        color: var(--checkout-primary);
    }
    .step-pay .step-label,
    .step-exam .step-label {
        color: var(--checkout-secondary);
    }

    /* Checkout Card */
    .checkout-card {
        background: var(--theme-card-bg, #ffffff);
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border: 1px solid var(--checkout-border);
        transition: all 0.3s ease;
    }
    .checkout-card:hover {
        box-shadow: 0 8px 24px color-mix(in srgb, var(--checkout-primary) 10%, transparent);
        border-color: var(--checkout-primary);
    }

    /* Order Summary */
    .order-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid var(--checkout-border);
    }
    .order-item:last-child {
        border-bottom: none;
    }
    .order-item-icon {
        width: 40px;
        height: 40px;
        background: var(--checkout-soft);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--checkout-primary);
        font-size: 18px;
    }
    .order-item-details {
        flex: 1;
    }
    .order-item-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
    }
    .order-item-price {
        font-weight: 700;
        color: var(--checkout-primary);
        font-size: 15px;
    }

    /* Buttons */
    .btn-teal {
        background: var(--checkout-primary);
        color: var(--theme-button-text, #ffffff);
        border: none;
        padding: 12px 28px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 16px;
        transition: all 0.3s ease;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 20px -14px var(--checkout-primary);
    }
    .btn-teal:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 24px -16px var(--checkout-primary);
        color: var(--theme-button-text, #ffffff);
    }

    .btn-coral {
        background: var(--checkout-secondary);
        color: var(--theme-button-text, #ffffff);
        border: none;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 16px;
        transition: all 0.3s ease;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 20px -14px var(--checkout-secondary);
    }
    .btn-coral:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 24px -16px var(--checkout-secondary);
        color: white;
    }

    .btn-outline-teal {
        background: transparent;
        border: 2px solid var(--checkout-primary);
        color: var(--checkout-primary);
        padding: 10px 24px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .btn-outline-teal:hover {
        background: var(--checkout-primary);
        color: white;
        transform: translateY(-2px);
    }

    /* Form */
    .form-control {
        border: 2px solid var(--checkout-border);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    .form-control:focus {
            border-color: var(--checkout-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--checkout-primary) 16%, transparent);
        background: white;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--checkout-primary);
        display: inline-block;
    }

    /* Payment Options */
    .payment-option {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border: 2px solid var(--checkout-border);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    .payment-option:hover {
        border-color: var(--checkout-primary);
        background: var(--checkout-soft);
    }
    .payment-option.selected {
        border-color: var(--checkout-primary);
        background: var(--checkout-soft);
    }
</style>

{{-- Hero Section with Breadcrumb --}}
<section class="checkout-hero">
    <div class="container">
        <div class="text-center">
            <span class="checkout-kicker"><i class="ri-shopping-bag-line"></i> {{ __('ui.secure_checkout') }}</span>
            <h1 class="checkout-hero-title">{{ __('website.cart_checkout') }}</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb checkout-breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ url('/') }}">{{ __('website.home') }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('website.cart_checkout') }}</li>
                </ol>
            </nav>
        </div>
    </div>
</section>

{{-- Main Content --}}
<section class="checkout-section py-5" style="background: var(--checkout-bg);">
    <div class="container">

        {{-- Progress Bar with Yellow Icons for Step 3 & 4 --}}
        <div class="checkout-progress">
            <div class="step step-done">
                <div class="step-icon"><i class="ri-check-line"></i></div>
                <span class="step-label">{{ __('ui.selected') }}</span>
            </div>
            <div class="step step-active">
                <div class="step-icon">2</div>
                <span class="step-label">{{ __('ui.review') }}</span>
            </div>
            <div class="step step-pay">
                <div class="step-icon">3</div>
                <span class="step-label">{{ __('ui.pay') }}</span>
            </div>
            <div class="step step-exam">
                <div class="step-icon"><i class="ri-flag-line"></i></div>
                <span class="step-label">{{ __('ui.exam_bang') }}</span>
            </div>
        </div>

        {{-- Login Prompt --}}
        @if (!Auth::guard('student')->check())
        <div class="alert border-0 shadow-sm mb-4" style="border-radius: 16px; background: var(--checkout-soft); color: var(--checkout-heading);">
            <i class="ri-user-smile-line fs-5 align-middle me-2"></i>
            <strong>{{ __('ui.already_account') }}</strong>
            <a href="{{ route('student.signin', ['redirect' => 'checkout']) }}" class="fw-bold text-decoration-underline ms-1" style="color: var(--checkout-primary);">{{ __('ui.login_here') }}</a> {{ __('ui.save_time') }}
        </div>
        @endif

        <form action="{{ route('checkout.store') }}" method="POST" id="checkout-form">
            @csrf
            <div class="row g-4">

                {{-- LEFT COLUMN: User Details --}}
                <div class="col-lg-7">
                    @if(Auth::guard('student')->check())
                        <div class="checkout-card mb-4" style="background: var(--checkout-soft); border-color: var(--checkout-primary);">
                            <div class="d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: var(--checkout-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="ri-user-line" style="color: white; font-size: 22px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 text-uppercase fs-12 fw-bold" style="color: var(--checkout-primary);">{{ __('ui.buying_for') }}</h6>
                                    <h5 class="mb-0 fs-16 text-dark">{{ $student->name }}</h5>
                                    <small class="text-muted">{{ $student->email }}</small>
                                </div>
                                <div class="text-end">
                                    <i class="ri-checkbox-circle-fill" style="color: var(--checkout-primary); font-size: 28px;"></i>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Order Items --}}
                    <div class="checkout-card mb-4">
                        <h5 class="section-title"><i class="ri-shopping-bag-line me-2" style="color: var(--checkout-primary);"></i>{{ __('ui.order_summary') }}</h5>

                        @if(isset($cartItems) && count($cartItems) > 0)
                            @foreach($cartItems as $item)
                            <div class="order-item">
                                <div class="order-item-icon">
                                    <i class="ri-file-copy-line"></i>
                                </div>
                                <div class="order-item-details">
                                    <div class="order-item-name">{{ $item['name'] }}</div>
                                    <small style="color: #94a3b8;">{{ $item['quantity'] }} x {{ $checkoutCurrency }}{{ number_format($item['price'], 2) }}</small>
                                </div>
                                <div class="order-item-price">{{ $checkoutCurrency }}{{ number_format($item['total'], 2) }}</div>
                            </div>
                            @endforeach
                        @else
                            <div class="text-center py-4">
                                <i class="ri-shopping-bag-line" style="font-size: 48px; color: #cbd5e1;"></i>
                                <p class="mt-2 text-muted">{{ __('website.cart_empty') }}</p>
                                <a href="{{ route('courses.index') }}" class="btn-teal">{{ __('website.cart_browse_courses') }}</a>
                            </div>
                        @endif
                    </div>

                    {{-- Coupon Section --}}
                    @if(isset($cartItems) && count($cartItems) > 0)
                    <div class="checkout-card mb-4">
                        <h5 class="section-title"><i class="ri-coupon-line me-2" style="color: var(--checkout-primary);"></i>{{ __('ui.apply_coupon') }}</h5>
                        <div class="d-flex gap-2">
                            <input type="text" id="coupon_code" class="form-control" placeholder="Enter coupon code" style="flex: 1;">
                            <button type="button" id="apply-coupon-btn" class="btn-teal" style="padding: 12px 24px; font-size: 14px;">{{ __('website.checkout_apply') }}</button>
                        </div>
                        <div id="coupon-message" class="mt-2"></div>
                    </div>
                    @endif
                </div>

                {{-- RIGHT COLUMN: Order Total & Payment --}}
                <div class="col-lg-5">
                    <div class="checkout-card">
                        <h5 class="section-title"><i class="ri-money-rupee-circle-line me-2" style="color: var(--checkout-secondary);"></i>{{ __('website.checkout_payment_method') }}</h5>

                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                            <div class="d-flex justify-content-between" style="padding: 6px 0;">
                                <span style="color: #64748b;">{{ __('website.checkout_subtotal') }}</span>
                                <span style="font-weight: 600; color: #1e293b;">{{ $checkoutCurrency }}{{ number_format($subtotal ?? 0, 2) }}</span>
                            </div>
                            @if(isset($discount) && $discount > 0)
                            <div class="d-flex justify-content-between" style="padding: 6px 0;">
                                <span style="color: var(--checkout-primary);">{{ __('website.checkout_discount') }}</span>
                                <span style="color: var(--checkout-primary); font-weight: 600;">-{{ $checkoutCurrency }}{{ number_format($discount, 2) }}</span>
                            </div>
                            @endif
                            <div class="d-flex justify-content-between" style="padding: 12px 0 0 0; border-top: 2px solid #e2e8f0; margin-top: 8px;">
                                <span style="font-weight: 700; font-size: 18px; color: #1e293b;">{{ __('website.checkout_total') }}</span>
                                <span style="font-weight: 800; font-size: 22px; color: var(--checkout-primary);">{{ $checkoutCurrency }}{{ number_format($finalAmount ?? 0, 2) }}</span>
                            </div>
                        </div>

                        {{-- Payment Method --}}
                        <div class="mb-4">
                            <h6 style="font-weight: 600; color: #1e293b; margin-bottom: 12px;">{{ __('website.checkout_payment_method') }}</h6>
                            <div class="payment-option selected" style="margin-bottom: 10px;">
                                <input type="radio" name="payment_method" value="razorpay" checked style="accent-color: var(--checkout-primary); width: 18px; height: 18px;">
                                <div>
                                    <strong style="color: #1e293b;">Razorpay</strong>
                                    <small style="display: block; color: #64748b;">{{ __('ui.razorpay_copy') }}</small>
                                </div>
                                <img src="https://razorpay.com/favicon.png" alt="Razorpay" style="height: 30px; margin-left: auto;">
                            </div>
                            <div class="payment-option">
                                <input type="radio" name="payment_method" value="offline" style="accent-color: var(--checkout-primary); width: 18px; height: 18px;">
                                <div>
                                    <strong style="color: #1e293b;">{{ __('ui.offline_payment') }}</strong>
                                    <small style="display: block; color: #64748b;">{{ __('ui.offline_payment_copy') }}</small>
                                </div>
                            </div>
                        </div>

                        {{-- Place Order Button --}}
                        <button type="submit" id="place-order-btn" class="btn-coral w-100" style="font-size: 18px; padding: 16px;">
                            <i class="ri-lock-2-line me-2"></i> {{ ($finalAmount ?? 0) > 0 ? 'Pay Now & Start Exam' : 'Activate Free Course' }}
                        </button>

                        <p style="text-align: center; margin-top: 12px; font-size: 12px; color: #94a3b8;">
                            <i class="ri-shield-check-line me-1" style="color: var(--checkout-primary);"></i> After payment, this package is added to My Exams automatically.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
$(document).ready(function() {
    // Payment option selection
    $('.payment-option').click(function() {
        $('.payment-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    // Coupon apply
    const applyBtn = document.getElementById('apply-coupon-btn');
    if(applyBtn) {
        applyBtn.addEventListener('click', function() {
            const code = document.getElementById('coupon_code').value;
            if(!code) return;
            applyBtn.disabled = true;
            applyBtn.innerText = '...';
            fetch('{{ route("checkout.apply_coupon") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ code: code })
            }).then(res => res.json()).then(data => {
                if(data.success) { location.reload(); }
                else { document.getElementById('coupon-message').innerHTML = `<span class="text-danger">${data.message}</span>`; applyBtn.disabled = false; applyBtn.innerText = 'Apply'; }
            });
        });
    }

    // Form submit
    const form = document.getElementById('checkout-form');
    const rzpBtn = document.getElementById('place-order-btn');
    const csrfToken = '{{ csrf_token() }}';
    const payableAmount = Number('{{ (float) ($finalAmount ?? 0) }}');

    form.addEventListener('submit', function(e) {
        const paymentInput = document.querySelector('input[name="payment_method"]:checked');
        const paymentMethod = paymentInput ? paymentInput.value : 'offline';

        if (paymentMethod === 'razorpay' && payableAmount > 0) {
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }

            rzpBtn.disabled = true;
            rzpBtn.innerText = 'Processing...';

            const formData = new FormData(form);
            fetch('/create-razorpay-order', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.id) {
                    var options = {
                        "key": "{{ $gateway->key_id ?? '' }}",
                        "amount": data.amount,
                        "currency": "INR",
                        "name": "{{ $configuration->name ?? 'Course Purchase' }}",
                        "description": "Unlock Exam",
                        "image": "{{ asset('storage/' . ($configuration->favicon ?? '')) }}",
                        "order_id": data.id,
                        "handler": function(response){ verifyPayment(response, data.id); },
                        "prefill": {
                            "name": document.querySelector('input[name="name"]')?.value || "{{ $student->name ?? '' }}",
                            "email": document.querySelector('input[name="email"]')?.value || "{{ $student->email ?? '' }}",
                            "contact": document.querySelector('input[name="phone"]')?.value || "{{ $student->phone ?? '' }}"
                        },
                        "theme": { "color": "{{ $configuration->primary_color ?? '#0f766e' }}" },
                        "modal": {
                            "ondismiss": function(){
                                rzpBtn.disabled = false;
                                rzpBtn.innerText = 'Pay Now & Start Exam';
                            }
                        }
                    };
                    var rzp1 = new Razorpay(options);
                    rzp1.open();
                } else {
                    rzpBtn.disabled = false;
                    rzpBtn.innerText = 'Pay Now & Start Exam';
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Payment Init Failed' });
                }
            })
            .catch(error => {
                rzpBtn.disabled = false;
                rzpBtn.innerText = 'Pay Now & Start Exam';
                Swal.fire({ icon: 'error', title: 'Error', text: 'Server connection failed.' });
            });
        }
    });

    function verifyPayment(response, localOrderId) {
        let rzpPaymentId = document.createElement('input');
        rzpPaymentId.type = 'hidden';
        rzpPaymentId.name = 'razorpay_payment_id';
        rzpPaymentId.value = response.razorpay_payment_id;
        form.appendChild(rzpPaymentId);

        let rzpOrderId = document.createElement('input');
        rzpOrderId.type = 'hidden';
        rzpOrderId.name = 'razorpay_order_id';
        rzpOrderId.value = response.razorpay_order_id;
        form.appendChild(rzpOrderId);

        let rzpSignature = document.createElement('input');
        rzpSignature.type = 'hidden';
        rzpSignature.name = 'razorpay_signature';
        rzpSignature.value = response.razorpay_signature;
        form.appendChild(rzpSignature);

        form.submit();
    }
});
</script>
@endsection
