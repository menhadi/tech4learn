@extends('installer.imaster')

@section('title', 'Database Configuration')

@section('body')
    @parent
@endsection

@section('content')
    <div class="text-center mb-4">
        <img src="{{ URL::asset('build/images/logo-sm-1.png') }}" alt="Exam Frame Logo" style="width: 100px;">
        <h4 class="mt-3">Step 2: Database Configuration</h4>
    </div>

    <div class="col-12 col-md-8 col-lg-5">
        <div class="card shadow-lg installer-card">
            <div class="card-body p-4 p-md-5">
                <h5 class="card-title text-center mb-4">Database Details</h5>
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-info">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('installer.database.post') }}">
                    @csrf
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="app_name" name="app_name" placeholder="Ex: Exam Frame"
                            value="{{ old('app_name', 'Exam Frame') }}" required>
                        <label for="app_name">Application Name</label>
                    </div>

                    {{-- ✅ FIXED LINE: request()->getSchemeAndHttpHost() ki jagah url('/') --}}
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="app_url" name="app_url"
                            placeholder="Ex: http://localhost" value="{{ old('app_url', url('/')) }}" required>
                        <label for="app_url">Application URL</label>
                    </div>

                    <hr class="my-4">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="db_host" name="db_host" placeholder="Ex: 127.0.0.1"
                            value="{{ old('db_host', '127.0.0.1') }}" required>
                        <label for="db_host">Database Host</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="db_port" name="db_port" placeholder="Ex: 3306"
                            value="{{ old('db_port', '3306') }}" required>
                        <label for="db_port">Database Port</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="db_database" name="db_database"
                            placeholder="Ex: my_database" value="{{ old('db_database') }}" required>
                        <label for="db_database">Database Name</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="db_username" name="db_username"
                            placeholder="Ex: root" value="{{ old('db_username') }}" required>
                        <label for="db_username">Database Username</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="db_password" name="db_password"
                            placeholder="Ex: password">
                        <label for="db_password">Database Password</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Save & Continue</button>
                </form>
            </div>
        </div>
    </div>
@endsection