@extends('website.layouts.app') {{-- Or your main layout --}}

@section('title', 'Thank You')

@section('content')
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded-4 border-0">
                <div class="card-body p-5">
                    <h1 class="display-5 text-success mb-3">
                        <i class="bi bi-check-circle-fill"></i> Thank You!
                    </h1>
                    <p class="lead">{{ __('ui.order_placed') }}</p>
                    <p class="mb-4">{{ __('ui.confirmation_email_sent') }}</p>
                    <a href="{{ url('/courses') }}" class="btn btn-primary px-4">{{ __('ui.browse_more_courses') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
