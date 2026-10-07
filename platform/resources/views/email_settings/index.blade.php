@extends('layouts.master')
@section('title', 'Email Configuration')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', 'Settings')
@slot('title', 'Email Configuration')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header align-items-center d-flex">
                <h4 class="card-title mb-0 flex-grow-1">Email System</h4>
                <div class="flex-shrink-0">
                    {{-- Logic: Agar URL me ?page=2 hai to Logs tab active hoga, nahi to Settings tab --}}
                    @php
                        $isPagination = request()->has('page'); 
                    @endphp

                    <ul class="nav nav-tabs-custom rounded card-header-tabs border-bottom-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $isPagination ? '' : 'active' }}" data-bs-toggle="tab" href="#settings" role="tab">
                                <i class="ri-settings-3-line"></i> Settings & Test
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $isPagination ? 'active' : '' }}" data-bs-toggle="tab" href="#logs" role="tab">
                                <i class="ri-history-line"></i> Email Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </div><div class="card-body">
                <div class="tab-content text-muted">
                    
                    {{-- Settings Tab Content --}}
                    <div class="tab-pane {{ $isPagination ? '' : 'active' }}" id="settings" role="tabpanel">
                        
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <strong> Using Gmail?</strong> Do not use your login password. You must use an <b>App Password</b>.
                            <br><small>Go to Google Account > Security > 2-Step Verification > App Passwords.</small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>

                        <div class="row">
                            <div class="col-md-7 border-end">
                                <h5 class="mb-3 text-primary">SMTP Configuration</h5>
                                <form method="POST" action="{{ isset($emailSetting) ? route('email-settings.update', $emailSetting->id) : route('email-settings.store') }}">
                                    @csrf
                                    @if(isset($emailSetting)) @method('PUT') @endif

                                    <div class="mb-3">
                                        <label class="form-label">Mail Driver Type</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="type-smtp" name="type" value="smtp" {{ old('type', $emailSetting->type ?? 'smtp') == 'smtp' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="type-smtp">SMTP (Recommended)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="type-local" name="type" value="local" {{ old('type', $emailSetting->type ?? '') == 'local' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="type-local">Local / Sendmail</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="smtp-fields">
                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label">Host</label>
                                                <input type="text" name="host" class="form-control" value="{{ old('host', $emailSetting->host ?? '') }}" placeholder="e.g. smtp.gmail.com">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Port</label>
                                                <input type="number" name="port" class="form-control" value="{{ old('port', $emailSetting->port ?? '587') }}" placeholder="587">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Username (Email)</label>
                                            <input type="text" name="username" class="form-control" value="{{ old('username', $emailSetting->username ?? '') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Password / App Password</label>
                                            <input type="password" name="password" class="form-control" value="{{ old('password', $emailSetting->password ?? '') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Encryption</label>
                                            <select name="tls" class="form-select">
                                                <option value="1" {{ old('tls', $emailSetting->tls ?? 1) == 1 ? 'selected' : '' }}>TLS (Standard)</option>
                                                <option value="0" {{ old('tls', $emailSetting->tls ?? 1) == 0 ? 'selected' : '' }}>SSL</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="card bg-light border mb-3"><div class="card-body"><h6>Founder Follow-up Sender</h6><div class="row"><div class="col-md-6"><label class="form-label">Sender Name</label><input name="founder_from_name" class="form-control" value="{{ old('founder_from_name',$emailSetting->founder_from_name ?? '') }}" placeholder="Founder / CEO name"></div><div class="col-md-6"><label class="form-label">Sender Email</label><input type="email" name="founder_from_address" class="form-control" value="{{ old('founder_from_address',$emailSetting->founder_from_address ?? '') }}" placeholder="founder@example.com"></div></div><small class="text-muted">SMTP above still delivers the email. Some providers require this address to be an approved alias; otherwise leave it blank and only customize the sender name.</small></div></div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">Save Settings</button>
                                    </div>
                                </form>
                            </div>

                            <div class="col-md-5">
                                <h5 class="mb-3 text-success">Test Connection</h5>
                                <div class="card bg-light border">
                                    <div class="card-body">
                                        <p class="text-muted small">Enter an email address to receive a test mail. Make sure you <b>Save Settings</b> before testing.</p>
                                        
                                        <form action="{{ route('email-settings.test') }}" method="POST">
                                            @csrf
                                            <div class="mb-3">
                                                <label class="form-label">Receiver Email</label>
                                                <input type="email" name="test_email" class="form-control" required placeholder="your@email.com">
                                            </div>
                                            <button type="submit" class="btn btn-success w-100"><i class="ri-send-plane-fill align-bottom me-1"></i> Send Test Email</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Logs Tab Content --}}
                    <div class="tab-pane {{ $isPagination ? 'active' : '' }}" id="logs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>To</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Error Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($emailLogs as $log)
                                    <tr>
                                        <td style="white-space:nowrap;">{{ $log->created_at->format('d M, h:i A') }}</td>
                                        <td>{{ $log->to_email }}</td>
                                        <td>{{ Str::limit($log->subject, 20) }}</td>
                                        <td>
                                            @if($log->status == 'Success')
                                                <span class="badge bg-success">Success</span>
                                            @else
                                                <span class="badge bg-danger">Failed</span>
                                            @endif
                                        </td>
                                        <td class="text-danger small">{{ Str::limit($log->error_message, 50) }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No logs found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                            
                            {{-- Pagination Style Fix --}}
                            <div class="d-flex justify-content-end mt-3">
                                {{ $emailLogs->links('pagination::bootstrap-5') }}
                            </div>
                            
                        </div>
                    </div>

                </div>
            </div></div></div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const smtpFields = document.getElementById('smtp-fields');
        const typeSmtp = document.getElementById('type-smtp');
        const typeLocal = document.getElementById('type-local');

        function toggleSmtp() {
            smtpFields.style.display = typeSmtp.checked ? 'block' : 'none';
        }

        typeSmtp.addEventListener('change', toggleSmtp);
        typeLocal.addEventListener('change', toggleSmtp);
        toggleSmtp(); // Run on load

        // Alerts using SweetAlert
        @if(session('success'))
        Swal.fire({ icon: 'success', title: 'Success', text: '{{ session('success') }}', timer: 3000, showConfirmButton: false });
        @endif

        @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: '{{ session('error') }}', }); 
        @endif
    });
</script>
@endsection