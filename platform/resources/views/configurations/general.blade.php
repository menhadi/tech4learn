@extends('layouts.master')

@section('title', 'General Configuration')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
{{-- Select2 CSS for searchable dropdown (Optional but good for long lists) --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .package-tag-toggle {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .package-tag-toggle .package-tag-toggle-label {
        align-items: center;
        background: transparent !important;
        border: 2px solid #0f766e !important;
        border-radius: 999px !important;
        color: #0f5132 !important;
        cursor: pointer;
        display: inline-flex;
        font-size: 15px;
        font-weight: 600;
        justify-content: center;
        min-height: 46px;
        min-width: 86px;
        padding: 8px 22px !important;
        transition: background-color .18s ease, color .18s ease, box-shadow .18s ease;
    }

    .package-tag-toggle .btn-check:checked + .package-tag-toggle-label {
        background: #0f766e !important;
        border-color: #0f766e !important;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, .14) !important;
        color: #ffffff !important;
    }

    .package-tag-toggle .btn-check:focus-visible + .package-tag-toggle-label {
        box-shadow: 0 0 0 4px rgba(15, 118, 110, .2) !important;
    }
</style>
@endsection

@section('content')
@if(\App\Support\SaasAccess::isPlatformAdmin())
<p><a class="btn btn-outline-primary" href="{{ route('configurations.analytics') }}">Google Analytics 4 settings</a></p>
@endif
@component('components.breadcrumb')
@slot('li_1', 'Configurations')
@slot('title', 'General Configuration')
@endcomponent
@if(\App\Support\SaasAccess::featureEnabled('sms_messaging'))
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('configurations.messaging') }}" class="btn btn-outline-primary"><i class="ri-message-3-line me-1"></i> Messaging Settings</a>
</div>
@endif

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">General Configuration</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('configurations.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    {{-- Row 1: Site Name & Org Name --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name"
                                value="{{ old('site_name', $configuration->name ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="organization_name" class="form-label">Organization Name</label>
                            <input type="text" class="form-control" id="organization_name" name="organization_name"
                                value="{{ old('organization_name', $configuration->organization_name ?? '') }}"
                                required>
                        </div>
                    </div>

                    {{-- Row 2: Tagline & Phone --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="organization_tagline" class="form-label">Organization Tagline</label>
                            <textarea class="form-control" id="organization_tagline" name="organization_tagline"
                                required>{{ old('organization_tagline', $configuration->organization_tagline ?? '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="organization_phone" class="form-label">Organization Phone No.</label>
                            <input type="text" class="form-control" id="organization_phone" name="organization_phone"
                                value="{{ old('organization_phone', $configuration->organization_phone ?? '') }}"
                                required>
                        </div>
                    </div>

                    {{-- Row 3: Alt Phone & Address --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="organization_alternate_phone" class="form-label">Organization Alternate Phone No.</label>
                            <input type="text" class="form-control" id="organization_alternate_phone" name="organization_alternate_phone"
                                value="{{ old('organization_alternate_phone', $configuration->organization_alternate_phone ?? '') }}"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label for="organization_address" class="form-label">Organization Address</label>
                            <textarea class="form-control" id="organization_address" name="organization_address"
                                required>{{ old('organization_address', $configuration->organization_address ?? '') }}</textarea>
                        </div>
                    </div>

                    {{-- Row 4: Domain & Email --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="domain" class="form-label">Domain</label>
                            <input type="text" class="form-control" id="domain" name="domain"
                                value="{{ old('domain', $configuration->domain_name ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="organization_email" class="form-label">Organization Email</label>
                            <input type="email" class="form-control" id="organization_email" name="organization_email"
                                value="{{ old('organization_email', $configuration->email ?? '') }}" required>
                        </div>
                    </div>

                    {{-- ✅ Row 5: Currency & TIMEZONE (NEW) --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="currency" class="form-label">Organization Currency</label>
                            <input type="text" class="form-control" id="currency" name="currency"
                                value="{{ old('currency', $configuration->currency ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">System Timezone</label>
                            <select name="timezone" id="timezone" class="form-control select2">
                                @foreach(timezone_identifiers_list() as $timezone)
                                    <option value="{{ $timezone }}" 
                                        {{ (old('timezone', $configuration->timezone ?? config('app.timezone')) == $timezone) ? 'selected' : '' }}>
                                        {{ $timezone }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Select your local timezone (e.g., Asia/Kolkata)</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="allow_guest_exam_attempts" class="form-label">Guest Exam Attempts</label>
                            <select name="allow_guest_exam_attempts" id="allow_guest_exam_attempts" class="form-control">
                                <option value="1" {{ old('allow_guest_exam_attempts', $configuration->allow_guest_exam_attempts ?? true) ? 'selected' : '' }}>
                                    Enabled
                                </option>
                                <option value="0" {{ !old('allow_guest_exam_attempts', $configuration->allow_guest_exam_attempts ?? true) ? 'selected' : '' }}>
                                    Disabled
                                </option>
                            </select>
                            <small class="text-muted">When disabled, guest exam actions redirect to student login.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="student_welcome_email_enabled" class="form-label">Automatic Student Welcome Email</label>
                            <select name="student_welcome_email_enabled" id="student_welcome_email_enabled" class="form-control">
                                <option value="1" {{ old('student_welcome_email_enabled', $configuration->student_welcome_email_enabled ?? true) ? 'selected' : '' }}>
                                    Enabled
                                </option>
                                <option value="0" {{ !old('student_welcome_email_enabled', $configuration->student_welcome_email_enabled ?? true) ? 'selected' : '' }}>
                                    Disabled
                                </option>
                            </select>
                            <small class="text-muted">Sends the active “Student Welcome Email” template once after registration verification.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_pending_admin_email_enabled" class="form-label">Pending OTP Admin Alert</label>
                            <select name="student_pending_admin_email_enabled" id="student_pending_admin_email_enabled" class="form-control">
                                <option value="1" {{ old('student_pending_admin_email_enabled', $configuration->student_pending_admin_email_enabled ?? true) ? 'selected' : '' }}>
                                    Enabled
                                </option>
                                <option value="0" {{ !old('student_pending_admin_email_enabled', $configuration->student_pending_admin_email_enabled ?? true) ? 'selected' : '' }}>
                                    Disabled
                                </option>
                            </select>
                            <small class="text-muted">Sends one alert to the Organization Email above when a student is stuck at OTP verification. If it is unavailable, the system mail-from address is used.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="student_manual_activation_email_enabled" class="form-label">Manual Activation Student Email</label>
                            <select name="student_manual_activation_email_enabled" id="student_manual_activation_email_enabled" class="form-control">
                                <option value="1" {{ old('student_manual_activation_email_enabled', $configuration->student_manual_activation_email_enabled ?? true) ? 'selected' : '' }}>
                                    Enabled
                                </option>
                                <option value="0" {{ !old('student_manual_activation_email_enabled', $configuration->student_manual_activation_email_enabled ?? true) ? 'selected' : '' }}>
                                    Disabled
                                </option>
                            </select>
                            <small class="text-muted">Sends the student an activation email after admin changes Pending to Active.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4"><label class="form-label">Require Student Registration Number</label><select name="require_student_registration_number" class="form-control"><option value="1" {{ old('require_student_registration_number',$configuration->require_student_registration_number ?? false) ? 'selected' : '' }}>Required (school/institute)</option><option value="0" {{ !old('require_student_registration_number',$configuration->require_student_registration_number ?? false) ? 'selected' : '' }}>Optional (public website)</option></select></div>
                        <div class="col-md-4"><label class="form-label">Founder Follow-up After 1 Hour</label><select name="student_founder_followup_enabled" class="form-control"><option value="1" {{ old('student_founder_followup_enabled',$configuration->student_founder_followup_enabled ?? true) ? 'selected' : '' }}>Enabled</option><option value="0" {{ !old('student_founder_followup_enabled',$configuration->student_founder_followup_enabled ?? true) ? 'selected' : '' }}>Disabled</option></select></div>
                        <div class="col-md-4"><label class="form-label">Guest Contact Welcome Email</label><select name="guest_contact_email_enabled" class="form-control"><option value="1" {{ old('guest_contact_email_enabled',$configuration->guest_contact_email_enabled ?? true) ? 'selected' : '' }}>Enabled</option><option value="0" {{ !old('guest_contact_email_enabled',$configuration->guest_contact_email_enabled ?? true) ? 'selected' : '' }}>Disabled</option></select></div>
                    </div>
                    {{-- Row 6: Meta Data --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="meta_title" class="form-label">Meta Title</label>
                            <input type="text" class="form-control" id="meta_title" name="meta_title"
                                value="{{ old('meta_title', $configuration->meta_title ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="meta_keyword" class="form-label">Meta Keyword</label>
                            <input type="text" class="form-control" id="meta_keyword" name="meta_keyword"
                                value="{{ old('meta_keyword', $configuration->meta_keyword ?? '') }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="meta_content" class="form-label">Meta Content</label>
                        <textarea class="form-control" id="meta_content" name="meta_content" rows="4"
                            required>{{ old('meta_content', $configuration->meta_content ?? '') }}</textarea>
                    </div>

                    {{-- Row 7: Branding --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="powered_by" class="form-label">Powered By Name</label>
                            <input type="text" class="form-control" id="powered_by" name="powered_by"
                                value="{{ old('powered_by', $configuration->powered_by ?? '') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="powered_link" class="form-label">Powered By Link</label>
                            <input type="url" class="form-control" id="powered_link" name="powered_link"
                                value="{{ old('powered_link', $configuration->powered_link ?? '') }}" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        
<div class="card mt-4 w-100">
    <div class="card-header">
        <h5 class="card-title mb-0">Theme Colors</h5>
        <p class="text-muted mb-0 mt-1">These colors apply to the full public website: header, footer, buttons, links and headings.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @php
                $themeFields = [
                    'theme_primary_color' => ['Primary Color', '#0f766e'],
                    'theme_secondary_color' => ['Secondary Color', '#f59e0b'],
                    'theme_header_bg' => ['Header Background', '#ffffff'],
                    'theme_header_text' => ['Header Text', '#0f172a'],
                    'theme_footer_bg' => ['Footer Background', '#0f172a'],
                    'theme_footer_text' => ['Footer Text', '#cbd5e1'],
                    'theme_body_bg' => ['Body Background', '#ffffff'],
                    'theme_heading_color' => ['Heading Color', '#0f172a'],
                    'theme_button_text' => ['Button Text', '#ffffff'],
                ];
                $secondaryMenuBackgroundForPicker = old('secondary_menu_bg_color', $configuration->secondary_menu_bg_color ?? '')
                    ?: ($configuration->theme_heading_color ?? '#101828');
                $secondaryMenuFields = [
                    'secondary_menu_bg_color' => ['Secondary Menu Background', '#101828'],
                    'secondary_menu_text_color' => ['Secondary Menu Text', '#ffffff'],
                    'secondary_menu_hover_color' => ['Secondary Menu Hover', '#f59e0b'],
                    'secondary_menu_top_border_color' => ['Secondary Menu Top Line', $secondaryMenuBackgroundForPicker],
                    'secondary_menu_bottom_border_color' => ['Secondary Menu Bottom Line', $secondaryMenuBackgroundForPicker],
                ];
            @endphp

            @foreach($themeFields as $field => [$label, $default])
                <div class="col-md-4">
                    <label class="form-label">{{ $label }}</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color"
                            value="{{ old($field, $configuration->$field ?? $default) }}"
                            onchange="this.nextElementSibling.value = this.value">
                        <input type="text" name="{{ $field }}" class="form-control"
                            value="{{ old($field, $configuration->$field ?? $default) }}"
                            placeholder="{{ $default }}">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="border-top mt-4 pt-4">
            <h6 class="mb-1">Secondary Header Menu</h6>
            <p class="text-muted mb-3">Line colors are optional. A top or bottom line appears only when its saved color is different from the secondary menu background.</p>
            <div class="row g-3">
                @foreach($secondaryMenuFields as $field => [$label, $default])
                    @php
                        $savedColor = old($field, $configuration->$field ?? '');
                        $isLineColor = in_array($field, ['secondary_menu_top_border_color', 'secondary_menu_bottom_border_color'], true);
                    @endphp
                    <div class="col-md-4">
                        <label class="form-label">{{ $label }}</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color"
                                value="{{ $savedColor ?: $default }}"
                                onchange="this.nextElementSibling.value = this.value">
                            <input type="text" name="{{ $field }}" class="form-control"
                                value="{{ $savedColor }}" placeholder="{{ $isLineColor ? 'No line (leave empty)' : 'Theme default (' . $default . ')' }}">
                        </div>
                        @if($isLineColor)
                            <div class="form-text">Empty or the same color as the menu background means no line.</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @php
                $showPackageTagFilters = (string) old(
                    'show_package_tag_filters',
                    (int) ($configuration->show_package_tag_filters ?? true)
                );
            @endphp
            <div class="border-top mt-4 pt-4">
                <h6 class="mb-1">Package Tag Filters</h6>
                <p class="text-muted mb-3">Choose whether tag filters such as PYP, Mock Test and Full Length appear on public package and category pages.</p>
                <div class="package-tag-toggle" role="radiogroup" aria-label="Display package tag filters">
                    <input type="radio" class="btn-check" name="show_package_tag_filters" id="show_package_tag_filters_yes" value="1" {{ $showPackageTagFilters === '1' ? 'checked' : '' }}>
                    <label class="package-tag-toggle-label" for="show_package_tag_filters_yes">Yes</label>

                    <input type="radio" class="btn-check" name="show_package_tag_filters" id="show_package_tag_filters_no" value="0" {{ $showPackageTagFilters === '0' ? 'checked' : '' }}>
                    <label class="package-tag-toggle-label" for="show_package_tag_filters_no">No</label>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="mt-4 pt-3 border-top text-start">
    <button type="submit" class="btn el-btn-primary el-btn-icon px-4">
        <i class="ri-save-line"></i> Save Changes
    </button>
</div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
{{-- Select2 JS --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Initialize Select2 for Timezone dropdown (Searchable)
        $('#timezone').select2({
            placeholder: "Select Timezone",
            allowClear: false
        });

        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: '{{ implode(", ", $errors->all()) }}',
            timer: 5000,
            showConfirmButton: true
        });
        @endif
    });
</script>
@endsection
