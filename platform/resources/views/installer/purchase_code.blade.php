@extends('installer.imaster')

@section('title', 'Enter Purchase Code')

@section('body')
@parent
@endsection

@section('content')
    <div class="text-center mb-4">
        <img src="{{ URL::asset('build/images/logo-sm-1.png') }}" alt="Exam Frame Logo" style="width: 100px;">
        <h4 class="mt-3">Step 1: License Verification</h4>
    </div>

    <div class="col-12 col-md-8 col-lg-5">
        <div class="card shadow-lg installer-card">
            <div class="card-body p-4 p-md-5">
                <h5 class="card-title text-center mb-4">Activate Your License</h5>

                {{-- Error Alerts --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- ✅ POST FORM ACTION ADDED --}}
                <form method="POST" action="{{ route('installer.purchase_code.post') }}">
                    @csrf
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="purchase_code" name="purchase_code" placeholder="Enter Code" required>
                        <label for="purchase_code">Purchase Code (License Key)</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Verify & Continue</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <small class="text-muted">Don't have a code? <a href="https://eduexpression.com" target="_blank">Buy Now</a></small>
                </div>
            </div>
        </div>
    </div>
@endsection