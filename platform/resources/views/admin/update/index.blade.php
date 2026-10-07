@extends('layouts.master')

@section('title', 'System Update')

@section('content')
<div class="container-fluid py-4">
    
    {{-- Page Title Header --}}
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h3 class="fw-bold text-dark m-0">System Update</h3>
            <p class="text-muted m-0 small">Manage your application version and patches.</p>
        </div>
    </div>

    <div class="row">
        
        {{-- LEFT COLUMN: Main Action Area --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-5">
                    
                    {{-- 1. Current Status Section --}}
                    <div class="text-center mb-5">
                        <h6 class="text-uppercase text-muted fw-bold letter-spacing-1">Current Version</h6>
                        
                        @php
                            $current_version = optional(\App\Models\SystemUpdate::first())->current_version ?? config('app.version', 'v1.0.0');
                        @endphp

                        <div class="d-inline-block py-3 px-5 mt-2 rounded-pill bg-light border border-2">
                            <h1 class="display-4 fw-bold text-primary m-0" id="current-version-text" style="letter-spacing: -1px;">
                                {{ $current_version }}
                            </h1>
                        </div>
                        
                        <div id="updateStatus" class="mt-4">
                            <p class="text-muted"><i class="mdi mdi-clock-outline"></i> Last checked: Just now</p>
                        </div>
                    </div>

                    {{-- 2. Action Buttons --}}
                    <div class="d-grid gap-2 col-md-6 mx-auto">
                        <button id="checkUpdateBtn" class="btn btn-primary btn-lg py-3 shadow-sm transition-hover">
                            <i class="mdi mdi-refresh me-2"></i> Check for Updates
                        </button>
                    </div>

                    <hr class="my-5 border-light">

                    {{-- 3. Update Available Section (Hidden Initially) --}}
                    <div id="updateAvailable" class="card border-success border-start border-4 bg-soft-success mb-3" style="display: none; background-color: #f8fff9;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="text-success fw-bold mb-0">
                                        <i class="mdi mdi-arrow-up-circle me-2"></i> New Version Available
                                    </h5>
                                    <span class="badge bg-success mt-1">v<span id="latest-version-text"></span></span>
                                </div>
                                <button id="installUpdateBtn" class="btn btn-success fw-bold px-4">
                                    Install Update
                                </button>
                            </div>
                            
                            <div class="bg-white p-3 rounded border">
                                <small class="text-muted fw-bold text-uppercase">Changelog</small>
                                <pre id="changelog-text" class="text-dark small mt-2 mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Installing Section (Hidden Initially) --}}
                    <div id="installingUpdate" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
                        <h4 class="mt-4 fw-bold text-dark">Updating System...</h4>
                        <p class="text-danger mb-0 fw-bold">Do not close this window or reload the page.</p>
                        <p class="text-muted small">This may take a few minutes.</p>
                        
                        <div class="progress mt-3 mx-auto" style="height: 6px; width: 60%;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN: Sidebar (Safety & Info) --}}
        <div class="col-lg-4">
            {{-- Safety Checklist --}}
            <div class="card shadow-sm border-0 rounded-3 mb-4 bg-white">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold m-0 text-dark"><i class="mdi mdi-shield-check-outline me-2 text-warning"></i> Pre-Update Checklist</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 d-flex text-muted">
                            <i class="mdi mdi-check-circle-outline me-2 text-primary"></i>
                            <span>Ensure your server has stable internet connection.</span>
                        </li>
                        <li class="mb-3 d-flex text-muted">
                            <i class="mdi mdi-check-circle-outline me-2 text-primary"></i>
                            <span>Users should not be taking exams during update.</span>
                        </li>
                        <li class="d-flex text-danger fw-bold bg-soft-danger p-2 rounded">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            <span>Take a FULL BACKUP (Database + Files) before proceeding.</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- System Info --}}
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body">
                    <small class="text-uppercase text-muted fw-bold">System Health</small>
                    <div class="d-flex justify-content-between align-items-center mt-3 border-bottom pb-2">
                        <span class="text-dark">PHP Version</span>
                        <span class="fw-bold">{{ phpversion() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 border-bottom pb-2">
                        <span class="text-dark">Laravel Version</span>
                        <span class="fw-bold">{{ app()->version() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="text-dark">Environment</span>
                        <span class="badge bg-info">{{ app()->environment() }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkBtn = document.getElementById('checkUpdateBtn');
    const installBtn = document.getElementById('installUpdateBtn');
    
    const statusDiv = document.getElementById('updateStatus');
    const updateAvailableDiv = document.getElementById('updateAvailable');
    const installingUpdateDiv = document.getElementById('installingUpdate');
    
    const latestVersionText = document.getElementById('latest-version-text');
    const changelogText = document.getElementById('changelog-text');
    const currentVersionText = document.getElementById('current-version-text');

    // Helper: Set Status Message
    const setStatus = (html, type = 'info') => {
        let colorClass = 'text-muted';
        if (type === 'success') colorClass = 'text-success fw-bold';
        if (type === 'danger') colorClass = 'text-danger fw-bold';
        statusDiv.innerHTML = `<span class="${colorClass}">${html}</span>`;
    };

    // 1. Check for Update
    checkBtn.addEventListener('click', async () => {
        checkBtn.disabled = true;
        const originalBtnText = checkBtn.innerHTML;
        checkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Checking...';
        setStatus('Connecting to update server...');
        updateAvailableDiv.style.display = 'none';

        try {
            const res = await fetch("{{ route('admin.update.check') }}", {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });

            const json = await res.json();

            if (json.ok) {
                if (json.needs_update) {
                    latestVersionText.textContent = json.latest;
                    changelogText.textContent = json.notes || 'No release notes provided.';
                    updateAvailableDiv.style.display = 'block';
                    // Scroll to update section smoothly
                    updateAvailableDiv.scrollIntoView({ behavior: 'smooth' });
                    setStatus('');
                } else {
                    setStatus(`<i class="mdi mdi-check-circle me-1"></i> You are on the latest version (v${json.current}).`, 'success');
                }
            } else {
                throw new Error(json.message || 'Server error');
            }
        } catch (e) {
            console.error(e);
            setStatus(`<i class="mdi mdi-alert-circle me-1"></i> ${e.message}`, 'danger');
        } finally {
            checkBtn.disabled = false;
            checkBtn.innerHTML = originalBtnText;
        }
    });

    // 2. Install Update
    installBtn.addEventListener('click', async () => {
        const confirmation = await Swal.fire({
            icon: 'warning',
            title: 'Critical safety check',
            text: 'Have you backed up the database and files before installing this update?',
            showCancelButton: true,
            confirmButtonText: 'Backup complete - install',
            confirmButtonColor: '#dc3545'
        });
        if (!confirmation.isConfirmed) return;

        // UI Updates
        installBtn.disabled = true;
        checkBtn.disabled = true;
        updateAvailableDiv.style.display = 'none';
        installingUpdateDiv.style.display = 'block';
        setStatus('Installation in progress...');

        try {
            const res = await fetch("{{ route('admin.update.apply') }}", {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });

            const json = await res.json();

            if (json.ok) {
                installingUpdateDiv.innerHTML = `
                    <div class="py-4">
                        <i class="mdi mdi-check-circle text-success" style="font-size: 5rem;"></i>
                        <h3 class="fw-bold mt-3 text-dark">System Updated!</h3>
                        <p class="text-muted">Reloading dashboard...</p>
                    </div>
                `;
                setTimeout(() => window.location.reload(), 3000);
            } else {
                throw new Error(json.message || 'Installation failed');
            }
        } catch (e) {
            installingUpdateDiv.style.display = 'none';
            updateAvailableDiv.style.display = 'block';
            installBtn.disabled = false;
            checkBtn.disabled = false;
            alert(`Error: ${e.message}`);
        }
    });
});
</script>
@endpush