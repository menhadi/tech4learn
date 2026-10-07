@extends('students.layouts.app')

@section('title') @lang('messages.help_title') @endsection

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">@lang('messages.help_center_title')</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">@lang('messages.sidebar_dashboard')</a></li>
                        <li class="breadcrumb-item active">@lang('messages.help_breadcrumb_help')</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ CODE CHANGE: Poore page ko ek 'Help Center' jaisa redesign kiya gaya hai --}}

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h3 class="fw-semibold">@lang('messages.help_hero_title')</h3>
                <p class="text-muted">@lang('messages.help_hero_subtitle')</p>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-5">
        <div class="col-lg-4 col-md-6">
            <div class="card text-center h-100 feature-card">
                <div class="card-body py-5">
                    <i class="ri-play-circle-line fs-1" style="color: var(--el-primary);"></i>
                    <h5 class="fs-16 mt-3">@lang('messages.help_card_1_title')</h5>
                    <p class="text-muted fs-14">@lang('messages.help_card_1_desc')</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card text-center h-100 feature-card">
                <div class="card-body py-5">
                    <i class="ri-file-chart-line fs-1" style="color: var(--el-secondary);"></i>
                    <h5 class="fs-16 mt-3">@lang('messages.help_card_2_title')</h5>
                    <p class="text-muted fs-14">@lang('messages.help_card_2_desc')</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card text-center h-100 feature-card">
                <div class="card-body py-5">
                    <i class="ri-user-settings-line fs-1" style="color: var(--el-primary);"></i>
                    <h5 class="fs-16 mt-3">@lang('messages.help_card_3_title')</h5>
                    <p class="text-muted fs-14">@lang('messages.help_card_3_desc')</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        <div class="col-lg-8">
            <h4 class="fw-semibold mb-4">@lang('messages.help_faq_title')</h4>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">@lang('messages.help_faq_q1')</button></h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">@lang('messages.help_faq_a1')</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">@lang('messages.help_faq_q2')</button></h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">@lang('messages.help_faq_a2')</div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">@lang('messages.help_faq_q3')</button></h2>
                    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">@lang('messages.help_faq_a3')</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="ri-mail-send-line fs-1" style="color: var(--el-primary);"></i>
                    <h5 class="mt-3">@lang('messages.help_support_title')</h5>
                    <p class="text-muted">@lang('messages.help_support_desc')</p>
                    
                    {{-- ✅ Dynamically fetching contact info --}}
                    @php
                        $config = getConfiguration();
                    @endphp

                    <div class="mt-4">
                        <p class="mb-1"><strong>@lang('messages.help_support_email')</strong></p>
                        <p><a href="mailto:{{ $config->email ?? '' }}">{{ $config->email ?? 'Not available' }}</a></p>
                        <p class="mb-1"><strong>@lang('messages.help_support_phone')</strong></p>
                        <p>{{ $config->organization_phone ?? 'Not available' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .feature-card { transition: transform .3s ease, box-shadow .3s ease; border: 1px solid var(--el-border, var(--vz-border-color-translucent)); }
    .feature-card:hover { transform: translateY(-8px); box-shadow: 0 1rem 3rem rgba(0,0,0,.08)!important; }
</style>

@endsection
