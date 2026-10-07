@extends('students.layouts.simple')
@section('title') @lang('messages.guideline_title') @endsection
@section('content')

<div class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm" style="border: 1px solid var(--el-border); border-radius: 14px; overflow: hidden;">
                    <div class="card-header text-white" style="background: var(--el-primary);">
                        <h4 class="card-title mb-0 text-white">{{ $exam->name }} - @lang('messages.guideline_card_title')</h4>
                    </div>
                    <div class="card-body p-4">
                        <div class="progress mb-4" style="height: 10px; background: var(--el-primary-soft);">
                            <div class="progress-bar" id="guidelineProgressBar" role="progressbar" style="width: 0%; background: var(--el-primary);" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div id="guidelineSteps">
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <i class="ri-shut-down-line fs-1 text-danger mb-3"></i>
                                    <h5 class="mb-2">@lang('messages.guideline_step1_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step1_desc')</p>
                                </div>
                            </div>
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <i class="ri-keyboard-box-line fs-1 text-warning mb-3"></i>
                                    <h5 class="mb-2">@lang('messages.guideline_step2_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step2_desc')</p>
                                </div>
                            </div>
                            <div class="guideline-step" style="display: none;">
                                <div class="text-center">
                                    <i class="ri-wifi-off-line fs-1 mb-3" style="color: var(--el-secondary);"></i>
                                    <h5 class="mb-2">@lang('messages.guideline_step3_title')</h5>
                                    <p class="text-muted">@lang('messages.guideline_step3_desc')</p>
                                </div>
                            </div>
                            <div id="finalStep" class="text-center" style="display: none;">
                                <i class="ri-shield-check-line fs-1 mb-3" style="color: var(--el-primary);"></i>
                                <h5 class="mb-3">@lang('messages.guideline_final_title')</h5>
                                <button id="nextButton" class="btn btn-lg" style="background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff);">@lang('messages.guideline_proceed_button')</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer text-center bg-light">
                        <button id="skipButton" class="btn btn-sm" style="background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff);">@lang('messages.guideline_skip_button')</button>
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
            window.location.href = `{{ url('guest/exam/instructions/' . $exam->id) }}`;
        });

        skipButton.addEventListener('click', function() {
            finishAnimations();
        });

        startAnimations();
    });
</script>
@endsection
