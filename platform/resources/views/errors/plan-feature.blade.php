@extends('layouts.master')
@section('title', 'Feature Not Included')
@section('content')
<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-7">
        <div class="card">
            <div class="card-body text-center p-5">
                <div class="avatar-lg mx-auto mb-4">
                    <div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-1">
                        <i class="ri-lock-2-line"></i>
                    </div>
                </div>
                <h4 class="mb-2">Feature Not Included</h4>
                <p class="text-muted mb-4">{{ $message ?? 'This feature is not included in your organization plan.' }}</p>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection
