@extends('installer.imaster')

@section('title', 'Admin Information')

@section('body')
    @parent
@endsection

@section('content')
    <div class="text-center mb-4">
        <img src="{{ URL::asset('build/images/logo-sm-1.png') }}" alt="Exam Frame Logo" style="width: 100px;">
        <h4 class="mt-3">Step 3: Create Admin User</h4>
    </div>

    <div class="col-12 col-md-8 col-lg-5">
        <div class="card shadow-lg installer-card">
            <div class="card-body p-4 p-md-5">
                <h5 class="card-title text-center mb-4">Admin Information</h5>
                
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
                
                {{-- Duplicate content hata diya --}}
                <form method="POST" onsubmit="return validatePasswords()">
                    @csrf
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="organization_name" name="organization_name"
                            placeholder="Organization Name" value="{{ old('organization_name') }}" required>
                        <label for="organization_name">Organization Name</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="domain_name" name="domain_name"
                            placeholder="Domain Name" value="{{ old('domain_name', request()->getHttpHost()) }}" required>
                        <label for="domain_name">Domain Name</label>
                    </div>
                    
                    <hr class="my-4">

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="name" name="name"
                            placeholder="User Name" value="{{ old('name') }}" required>
                        <label for="name">User Name</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email"
                            placeholder="Email" value="{{ old('email') }}" required>
                        <label for="email">Email</label>
                    </div>
                    
                    {{-- Modern password field floating label ke saath --}}
                    <div class_=("form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                        <button class="btn password-toggle-btn" type="button" id="toggle-password">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password_confirmation"
                            name="password_confirmation" placeholder="Confirm Password" required>
                        <label for="password_confirmation">Confirm Password</label>
                        <button class="btn password-toggle-btn" type="button" id="toggle-password-confirmation">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Complete Installation</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const passwordIcon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('ri-eye-off-line');
                passwordIcon.classList.add('ri-eye-line');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('ri-eye-line');
                passwordIcon.classList.add('ri-eye-off-line');
            }
        });

        document.getElementById('toggle-password-confirmation').addEventListener('click', function() {
            const passwordField = document.getElementById('password_confirmation');
            const passwordIcon = this.querySelector('i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.classList.remove('ri-eye-off-line');
                passwordIcon.classList.add('ri-eye-line');
            } else {
                passwordField.type = 'password';
                passwordIcon.classList.remove('ri-eye-line');
                passwordIcon.classList.add('ri-eye-off-line');
            }
        });

        function validatePasswords() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            if (password !== confirmPassword) {
                // Aap SweetAlert2 use kar rahe hain, toh normal alert ki jagah yeh use karein
                Swal.fire({
                    title: 'Error!',
                    text: 'Passwords do not match. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            if (password.length < 8) {
                 Swal.fire({
                    title: 'Warning!',
                    text: 'Password should be at least 8 characters long.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            return true;
        }
    </script>
@endsection