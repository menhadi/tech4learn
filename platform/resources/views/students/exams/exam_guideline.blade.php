@extends('students.layouts.simple')
@section('title') @lang('messages.guideline_title') @endsection
@section('content')

<style>
    .exam-guideline-page {
        background: color-mix(in srgb, var(--el-primary, #0f766e) 5%, var(--el-body-bg, #f8fafc));
    }

    .exam-guideline-card {
        border: 1px solid var(--el-border);
        border-radius: 14px;
        overflow: hidden;
    }

    .exam-guideline-card .card-header {
        background: var(--el-primary);
        border-bottom: 0;
        padding: 18px 22px;
    }

    .exam-guideline-icon {
        align-items: center;
        background: var(--el-primary-soft);
        border-radius: 16px;
        color: var(--el-primary);
        display: inline-flex;
        height: 64px;
        justify-content: center;
        margin-bottom: 14px;
        width: 64px;
    }

    .exam-guideline-icon.is-secondary {
        background: var(--el-secondary-soft);
        color: var(--el-secondary);
    }

    .exam-guideline-footer {
        background: var(--el-surface-muted);
        border-top: 1px solid var(--el-border);
    }

    .exam-guideline-btn {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
        font-weight: 700;
    }

    .exam-guideline-btn:hover,
    .exam-guideline-btn:focus {
        background: var(--el-primary-dark, var(--el-primary));
        border-color: var(--el-primary-dark, var(--el-primary));
        color: var(--theme-button-text, #fff);
    }
</style>

<div class="d-flex align-items-center justify-content-center min-vh-100 exam-guideline-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm exam-guideline-card">
                    <div class="card-header text-white">
                        <h4 class="card-title mb-0 text-white">{{ $exam->name }} - @lang('messages.guideline_card_title')</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="progress mb-4" style="height: 10px; background: var(--el-primary-soft);">
                            <div class="progress-bar" id="guidelineProgressBar" role="progressbar" style="width: 0%; background: var(--el-primary);" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div id="guidelineSteps">
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <span class="exam-guideline-icon is-secondary"><i class="ri-shut-down-line fs-1"></i></span>
                                    <h5 class="mb-2">@lang('messages.guideline_step1_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step1_desc')</p>
                                </div>
                            </div>
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <span class="exam-guideline-icon is-secondary"><i class="ri-keyboard-box-line fs-1"></i></span>
                                    <h5 class="mb-2">@lang('messages.guideline_step2_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step2_desc')</p>
                                </div>
                            </div>
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <span class="exam-guideline-icon is-secondary"><i class="ri-wifi-off-line fs-1"></i></span>
                                    <h5 class="mb-2">@lang('messages.guideline_step3_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step3_desc')</p>
                                </div>
                            </div>
                            <div id="finalStep" class="text-center" style="display: none;">
                                <span class="exam-guideline-icon"><i class="ri-shield-check-line fs-1"></i></span>
                                <h5 class="mb-3">@lang('messages.guideline_final_title')</h5>
                                <button id="nextButton" class="btn btn-lg exam-guideline-btn">@lang('messages.guideline_proceed_button')</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer text-center exam-guideline-footer">
                        <button id="skipButton" class="btn btn-sm exam-guideline-btn">@lang('messages.guideline_skip_button')</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const progressBar = document.getElementById('guidelineProgressBar');
        const steps = document.querySelectorAll('.guideline-step');
        const finalStep = document.getElementById('finalStep');
        const nextButton = document.getElementById('nextButton');
        const skipButton = document.getElementById('skipButton');

        let progress = 0;
        const stepDuration = 4000; // Har step 4 second ka
        const totalDuration = steps.length * stepDuration;
        let progressInterval, stepInterval;

        function startAnimations() {
            let currentStep = 0;
            steps[currentStep].style.display = 'block';

            stepInterval = setInterval(() => {
                if (steps[currentStep]) steps[currentStep].style.display = 'none';
                currentStep++;
                if (currentStep < steps.length) {
                    steps[currentStep].style.display = 'block';
                } else {
                    finishAnimations();
                }
            }, stepDuration);

            progressInterval = setInterval(() => {
                progress += 1;
                progressBar.style.width = progress + '%';
                if (progress >= 100) {
                    finishAnimations();
                }
            }, totalDuration / 100);
        }

        function finishAnimations() {
            clearInterval(stepInterval);
            clearInterval(progressInterval);
            steps.forEach(s => s.style.display = 'none');
            progressBar.style.width = '100%';
            finalStep.style.display = 'block';
            skipButton.style.display = 'none';
        }

        nextButton.addEventListener('click', function() {
            window.location.href = `{{ url('/exam/instructions/' . $exam->id) }}`;
        });

        skipButton.addEventListener('click', function() {
            finishAnimations();
        });

        startAnimations();
    });
</script>
@endsection
