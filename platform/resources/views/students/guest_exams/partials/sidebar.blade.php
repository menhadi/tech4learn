{{-- ✅ STYLE: Custom Scrollbar for Palette --}}
<style>
    .palette-scroll::-webkit-scrollbar { width: 6px; }
    .palette-scroll::-webkit-scrollbar-track { background: var(--el-primary-soft); border-radius: 4px; }
    .palette-scroll::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--el-primary) 42%, #fff); border-radius: 4px; }
    .palette-scroll::-webkit-scrollbar-thumb:hover { background: var(--el-primary); }
    .exam-side-card {
        background: var(--el-surface, #fff);
        border: 1px solid var(--el-border);
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }
    .exam-timer-card {
        background: var(--el-primary-soft);
        color: var(--el-heading);
    }
    .exam-timer-card .fs-4 {
        color: var(--el-primary);
    }
    .exam-proctor-card {
        background: var(--el-heading, #0f172a);
        border-color: color-mix(in srgb, var(--el-primary) 26%, #000);
    }
    .exam-palette-card {
        background: color-mix(in srgb, var(--el-primary) 5%, var(--el-surface, #fff));
    }
    .exam-palette-card.exam-palette-static {
        flex: 0 0 auto !important;
        min-height: auto !important;
    }
    .exam-palette-static .palette-scroll {
        flex: 0 0 auto !important;
        overflow: visible !important;
        padding-right: 0 !important;
    }
    .question-status {
        border-color: color-mix(in srgb, var(--el-primary) 35%, var(--el-border)) !important;
        box-shadow: 0 4px 10px rgba(15, 23, 42, .08);
    }
    .exam-legend-divider {
        border-top: 1px solid color-mix(in srgb, var(--el-primary) 16%, var(--el-border));
    }
    .exam-sidebar-action {
        background: var(--el-primary-soft);
        border-color: color-mix(in srgb, var(--el-primary) 22%, var(--el-border));
        color: var(--el-primary);
        font-weight: 700;
    }
    .exam-sidebar-action:hover,
    .exam-sidebar-action:focus {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
    }
    .exam-submit-action {
        background: var(--el-primary);
        border-color: var(--el-primary);
        color: var(--theme-button-text, #fff);
        font-weight: 800;
    }
    .exam-submit-action:hover,
    .exam-submit-action:focus {
        background: var(--el-primary-dark, var(--el-primary));
        border-color: var(--el-primary-dark, var(--el-primary));
        color: var(--theme-button-text, #fff);
    }
</style>

{{-- ✅ MODIFIED: Added h-100 to ensure full height utilization --}}
<div class="col-md-4 d-flex flex-column ps-md-2 h-100"> 

    {{-- 1. TOP SECTION: Timer & Proctor (Fixed Height) --}}
    <div style="flex-shrink: 0;"> 
        {{-- Timer --}}
         @if($remainingTime > 0)
        <div class="exam-side-card exam-timer-card p-3 mb-2 text-center">
            <div class="h5 mb-1">@lang('messages.exam_sidebar_time_remaining')</div>
            <div class="d-flex justify-content-around">
                <div><span class="fs-4 fw-bold" id="hours">00</span><br><small>@lang('messages.exam_sidebar_hours')</small></div>
                <div><span class="fs-4 fw-bold" id="minutes">00</span><br><small>@lang('messages.exam_sidebar_minutes')</small></div>
                <div><span class="fs-4 fw-bold" id="seconds">00</span><br><small>@lang('messages.exam_sidebar_seconds')</small></div>
            </div>
        </div>
         @else
             <div class="exam-side-card exam-timer-card p-3 mb-2 text-center">
                 <div class="h5 mb-1 text-muted">@lang('messages.exam_sidebar_no_time_limit')</div>
             </div>
         @endif

        {{-- Proctoring Video --}}
        @if($exam->proctor)
        <div class="exam-side-card exam-proctor-card mb-2 text-center p-1">
            <video id="webcam" autoplay playsinline muted class="rounded" style="width: 100%; max-height: 120px; object-fit: cover; display: block;"></video>
             <small class="text-white-50 d-block pt-1">@lang('messages.exam_sidebar_proctoring_active')</small>
        </div>
        @endif
    </div>

    {{-- 2. MIDDLE SECTION: Palette & Legend (Takes Remaining Space) --}}
    {{-- ✅ MODIFIED: Removed overflow-y-auto from parent, added min-height: 0 to allow flex shrinking --}}
    <div class="exam-side-card exam-palette-card exam-palette-static p-3 mb-2">
        
        {{-- Title --}}
        <div style="flex-shrink: 0;" class="mb-2">
            <strong>@lang('messages.exam_sidebar_palette_title')</strong>
        </div>

        {{-- ✅ SCROLLABLE AREA: Sirf ye area scroll karega agar questions zyada hain --}}
        <div class="palette-scroll">
            <div class="d-flex flex-wrap" id="questionPalette">
                
                {{-- Logic for Subject-wise Numbering --}}
                @php
                    $paletteNumbers = [];
                    $subjectCounters = [];
                    foreach ($exam->questions as $idx => $ques) {
                        $sId = $ques->exam_group_key ?? 'all';
                        if (!isset($subjectCounters[$sId])) {
                            $subjectCounters[$sId] = 0;
                        }
                        $subjectCounters[$sId]++;
                        $paletteNumbers[$idx] = $subjectCounters[$sId];
                    }
                @endphp

                @forelse($exam->questions as $index => $question)
                    @php
                        $status = 'not_visited';
                        $examStat = $examStats[$question->id] ?? null;

                        if ($examStat) {
                             $isReviewed = $examStat->review ?? false;
                             $isAnswered = $examStat->answered ?? false;

                             if ($isReviewed && $isAnswered) {
                                 $status = 'review_answer';
                             } elseif ($isReviewed) {
                                 $status = 'review';
                             } elseif ($isAnswered) {
                                 $status = 'answered';
                             } elseif ($examStat->opened) {
                                $status = 'not_answered';
                             }
                        }
                        
                        $altTextKey = 'messages.exam_sidebar_legend_not_visited';
                        switch ($status) {
                            case 'answered': $altTextKey = 'messages.exam_sidebar_legend_answered'; break;
                            case 'not_answered': $altTextKey = 'messages.exam_sidebar_legend_not_answered'; break;
                            case 'review': $altTextKey = 'messages.exam_sidebar_legend_review'; break;
                            case 'review_answer': $altTextKey = 'messages.exam_sidebar_legend_review_ans'; break;
                        }
                        
                        $displayNumber = $paletteNumbers[$index];
                    @endphp
                    
                    <div class="position-relative m-1 question-status" data-index="{{ $index }}" data-subject-id="{{ $question->exam_group_key ?? 'all' }}"
                        style="width: 35px; height: 35px; cursor: pointer; border-radius: 50%; overflow: hidden; flex-shrink: 0;"
                        title="@lang('messages.exam_sidebar_palette_go_to_q') {{ $displayNumber }}"> 
                        
                        <img src="{{ asset('assets/images/exam-status/' . $status . '.svg') }}" alt="@lang($altTextKey)" style="width: 100%; height: 100%; object-fit: cover;">
                        <span class="position-absolute top-50 start-50 translate-middle fw-semibold text-dark small">{{ $displayNumber }}</span>
                    </div>

                 @empty
                    <p class="text-muted small">@lang('messages.exam_sidebar_palette_empty')</p>
                 @endforelse
            </div>
        </div>

        {{-- Fixed Legend Area at Bottom of Palette Box --}}
        <div class="exam-legend-divider" style="flex-shrink: 0; margin-top: 10px; padding-top: 10px;">
            <strong>@lang('messages.exam_sidebar_legend_title')</strong>
            <div class="row row-cols-2 g-1 mt-1 small">
                <div class="col d-flex align-items-center">
                    <img src="{{ asset('assets/images/exam-status/answered.svg') }}" style="width: 16px; height: 16px; margin-right: 4px;">
                    <span class="text-nowrap">@lang('messages.exam_sidebar_legend_answered') (<span class="legend-answered">0</span>)</span>
                </div>
                <div class="col d-flex align-items-center">
                    <img src="{{ asset('assets/images/exam-status/not_answered.svg') }}" style="width: 16px; height: 16px; margin-right: 4px;">
                    <span class="text-nowrap">@lang('messages.exam_sidebar_legend_not_answered') (<span class="legend-not_answered">0</span>)</span>
                </div>
                <div class="col d-flex align-items-center">
                    <img src="{{ asset('assets/images/exam-status/not_visited.svg') }}" style="width: 16px; height: 16px; margin-right: 4px;">
                    <span class="text-nowrap">@lang('messages.exam_sidebar_legend_not_visited') (<span class="legend-not_visited">0</span>)</span>
                </div>
                 <div class="col d-flex align-items-center">
                    <img src="{{ asset('assets/images/exam-status/review.svg') }}" style="width: 16px; height: 16px; margin-right: 4px;">
                    <span class="text-nowrap">@lang('messages.exam_sidebar_legend_review') (<span class="legend-review">0</span>)</span>
                </div>
                 <div class="col d-flex align-items-center">
                    <img src="{{ asset('assets/images/exam-status/review_answer.svg') }}" style="width: 16px; height: 16px; margin-right: 4px;">
                    <span class="text-nowrap">@lang('messages.exam_sidebar_legend_review_ans') (<span class="legend-review_answer">0</span>)</span>
                </div>
            </div>
            
            {{-- Filter Dropdown --}}
            <div class="mt-2 d-flex align-items-center">
                <label for="filterSelect" class="form-label me-2 mb-0 small fw-semibold">@lang('messages.exam_sidebar_filter_label')</label>
                <select class="form-select form-select-sm w-auto flex-grow-1" id="filterSelect">
                    <option value="all">@lang('messages.exam_sidebar_filter_all')</option>
                    <option value="not_visited">@lang('messages.exam_sidebar_filter_not_visited')</option>
                    <option value="not_answered">@lang('messages.exam_sidebar_filter_not_answered')</option>
                    <option value="answered">@lang('messages.exam_sidebar_filter_answered')</option>
                    <option value="review">@lang('messages.exam_sidebar_filter_review')</option>
                    <option value="review_answer">@lang('messages.exam_sidebar_filter_review_ans')</option>
                </select>
            </div>
        </div>
    </div>

    {{-- 3. BOTTOM SECTION: Buttons (Fixed at bottom) --}}
    <div class="row g-2 mt-auto" style="flex-shrink: 0;">
        <div class="col-6">
            <button class="btn btn-sm btn-outline-secondary exam-sidebar-action w-100" data-bs-toggle="modal" data-bs-target="#questionPaperModal">
                <i class="mdi mdi-file-document-outline"></i> @lang('messages.exam_sidebar_btn_paper')
            </button>
        </div>
        <div class="col-6">
            <button class="btn btn-sm btn-outline-secondary exam-sidebar-action w-100" data-bs-toggle="modal" data-bs-target="#instructionsModal">
                <i class="mdi mdi-information-outline"></i> @lang('messages.exam_sidebar_btn_instructions')
            </button>
        </div>
        <div class="col-6">
            <button class="btn btn-sm btn-outline-secondary exam-sidebar-action w-100" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="mdi mdi-account-circle-outline"></i> @lang('messages.exam_sidebar_btn_profile')
            </button>
        </div>
        <!-- <div class="col-6">
            <button class="btn btn-danger btn-sm w-100 report-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="mdi mdi-alert-circle-outline"></i> @lang('messages.exam_sidebar_btn_report')
            </button>
        </div> -->
        <div class="col-6">
            <button class="btn w-100 btn-sm exam-submit-action" data-bs-toggle="modal" data-bs-target="#finalizeExamModal">
                <i class="mdi mdi-check-circle-outline"></i> @lang('messages.exam_sidebar_btn_submit')
            </button>
        </div>
    </div>
</div>
