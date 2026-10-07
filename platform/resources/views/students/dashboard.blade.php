@extends('students.layouts.app')
@section('title') @lang('messages.dash_breadcrumb_dashboard') @endsection

@section('content')
@component('components.breadcrumb')
    @slot('li_1') @lang('messages.dash_breadcrumb_dashboards') @endslot
    @slot('title') @lang('messages.dash_breadcrumb_dashboard') @endslot
@endcomponent

<style>
.student-card{ border:1px solid var(--el-border); border-radius:8px; box-shadow:0 4px 16px rgba(2,6,23,.05); }
.student-card:hover{ box-shadow:0 8px 22px rgba(2,6,23,.08); }
.student-kpi-icon{ width:44px; height:44px; border-radius:8px; display:flex; align-items:center; justify-content:center; margin-right:.75rem; background:var(--el-primary-soft); color:var(--el-primary); }
.student-kpi-icon.secondary{ background:var(--el-secondary-soft); color:var(--el-secondary); }
.small-muted{ font-size:0.85rem; color:var(--el-muted); }
.student-badge{ padding:4px 10px; border-radius:4px; font-size:0.75rem; font-weight:600; }
.student-badge-soft{ background:var(--el-primary-soft); color:var(--el-primary); }
.student-section-title{ font-size:1rem; font-weight:700; color:var(--el-heading, #1f2937); margin-bottom:1rem; display:block; }
.student-strip{ background:var(--el-surface-muted); border-bottom:1px solid var(--el-border); padding:12px 20px; border-radius:8px 8px 0 0; }
.student-profile-avatar{ width:90px; height:90px; object-fit:cover; border:4px solid #fff; box-shadow:0 8px 20px rgba(var(--el-primary-rgb), .22); }
.student-table{ border:1px solid var(--el-border); border-radius:8px; overflow:hidden; }
.student-table table{ margin-bottom:0; color:var(--el-heading); }
.student-table thead th{ background:var(--el-primary-soft); color:var(--el-heading); border-bottom:1px solid var(--el-border); font-size:13px; text-transform:uppercase; letter-spacing:.01em; }
.student-table tbody tr:nth-child(even){ background:var(--el-surface-muted); }
.student-table tbody td{ border-color:var(--el-border); vertical-align:middle; }
.student-action-primary{ background:var(--el-primary); border-color:var(--el-primary); color:var(--theme-button-text, #fff); border-radius:6px; font-weight:700; }
.student-action-primary:hover{ background:var(--el-primary-dark, var(--el-primary)); border-color:var(--el-primary-dark, var(--el-primary)); color:var(--theme-button-text, #fff); }
.student-action-secondary{ background:var(--el-secondary); border-color:var(--el-secondary); color:var(--theme-button-text, #fff); border-radius:6px; font-weight:700; }
.student-action-secondary:hover{ background:var(--el-secondary-dark, var(--el-secondary)); border-color:var(--el-secondary-dark, var(--el-secondary)); color:var(--theme-button-text, #fff); }
.student-status-pass{ background:var(--el-primary-soft); color:var(--el-primary); }
.student-status-fail{ background:var(--el-secondary-soft); color:var(--el-secondary); }
.student-rank-badge{ background:var(--el-primary-soft); color:var(--el-primary); border:1px solid var(--el-border); border-radius:999px; padding:5px 11px; font-weight:800; display:inline-flex; align-items:center; gap:5px; }
.student-insight-card{ border-left:5px solid var(--el-primary); }
.student-insight-summary{ color:var(--el-heading); line-height:1.55; }
.student-insight-points{ display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
.student-insight-point{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--el-border); background:var(--el-primary-soft); color:var(--el-primary); font-size:12px; font-weight:800; }
.student-overall-list{ padding-left:18px; color:var(--el-muted); margin-bottom:0; }
.student-overall-list li + li{ margin-top:8px; }
</style>

<div class="row">
    {{-- Left Column --}}
    <div class="col-xxl-9 col-lg-8">
        <div class="row">
            {{-- KPI Cards --}}
            <div class="col-md-4">
                <div class="card student-card mb-4">
                    <div class="card-body d-flex align-items-center">
                        <div class="student-kpi-icon">
                            <i class="ri-book-open-line fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $totalExams ?? 0 }}</h4>
                            <span class="small-muted">@lang('messages.dash_kpi_total_exams')</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card student-card mb-4">
                    <div class="card-body d-flex align-items-center">
                        <div class="student-kpi-icon secondary">
                            <i class="ri-trophy-line fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $dashboardGroupRank ? '#' . $dashboardGroupRank : '-' }}</h4>
                            <span class="small-muted">{{ __('ui.group_rank') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card student-card mb-4">
                    <div class="card-body d-flex align-items-center">
                        <div class="student-kpi-icon">
                            <i class="ri-percent-line fs-3"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold text-dark">{{ number_format($avgPercentile ?? 0, 1) }}%</h4>
                            <span class="small-muted">@lang('messages.dash_kpi_avg_score')</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card student-card student-insight-card mb-4">
            <div class="student-strip d-flex align-items-center justify-content-between">
                <span class="student-section-title mb-0"><i class="ri-lightbulb-flash-line me-1" style="color:var(--el-primary);"></i> {{ __('ui.overall_analysis') }}</span>
                <span class="student-badge student-badge-soft">{{ __('ui.all_exams') }}</span>
            </div>
            <div class="card-body">
                <p class="student-insight-summary mb-2">{{ $overallPerformance['summary'] ?? '' }}</p>
                @if(!empty($overallPerformance['points']))
                    <div class="student-insight-points mb-3">
                        @foreach($overallPerformance['points'] as $point)
                            <span class="student-insight-point"><i class="ri-bar-chart-2-line"></i>{{ $point }}</span>
                        @endforeach
                    </div>
                @endif
                <ul class="student-overall-list">
                    @foreach(($overallPerformance['tips'] ?? []) as $tip)
                        <li>{{ $tip }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Failed Exams Section (Focus Area) --}}
        @if(isset($failedExams) && count($failedExams) > 0)
        <div class="card student-card mb-4">
            <div class="student-strip d-flex align-items-center justify-content-between">
                <span class="student-section-title mb-0"><i class="ri-focus-3-line me-1" style="color:var(--el-secondary);"></i> @lang('messages.dash_focus_area')</span>
                <span class="student-badge student-status-fail">{{ __('ui.needs_improvement') }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive student-table">
                    <table class="table table-borderless align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">@lang('messages.dash_col_exam')</th>
                                <th scope="col">@lang('messages.dash_col_score')</th>
                                <th scope="col" class="text-end">@lang('messages.dash_col_action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedExams as $fail)
                            <tr>
                                <td class="fw-medium">{{ $fail->exam->display_name ?? $fail->exam->name ?? __('ui.exam') }}</td>
                                <td class="fw-bold" style="color:var(--el-secondary);">{{ $fail->obtained_marks }}/{{ $fail->total_marks }}</td>
                                <td class="text-end">
                                    <a href="{{ route('student.results', ['exam_id' => $fail->exam_id]) }}" class="btn btn-sm student-action-secondary">
                                        @lang('messages.dash_btn_analyze')
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
        
        {{-- Recent Results --}}
        <div class="card student-card">
            <div class="student-strip">
                <span class="student-section-title mb-0"><i class="ri-history-line me-1" style="color:var(--el-primary);"></i> @lang('messages.dash_recent_history')</span>
            </div>
            <div class="card-body">
                @if(isset($recentExams) && count($recentExams) > 0)
                <div class="table-responsive student-table">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>@lang('messages.dash_col_exam')</th>
                                <th>@lang('messages.dash_col_date')</th>
                                <th>{{ __('ui.rank') }}</th>
                                <th class="text-end">@lang('messages.dash_col_view')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentExams as $exam)
                            <tr>
                                <td>
                                    <h6 class="mb-0 text-dark">{{ $exam->exam->display_name ?? $exam->exam->name ?? __('ui.exam') }}</h6>
                                </td>
                                <td class="text-muted small">
                                    {{ \Carbon\Carbon::parse($exam->created_at)->format('d M Y') }}
                                </td>
                                <td>
                                    <span class="student-rank-badge"><i class="ri-trophy-line"></i>{{ $exam->display_rank ? '#' . $exam->display_rank : '-' }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('student.results', ['exam_id' => $exam->exam_id]) }}" class="btn btn-sm student-action-primary">
                                        <i class="ri-arrow-right-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    {{-- ✅ FIXED: Removed broken image, added clean Icon --}}
                    <div class="text-center py-5">
                        <div class="mb-3">
                            {{-- Using a standard Remix Icon (Clipboard) that matches the theme --}}
                            <i class="ri-clipboard-line text-muted" style="font-size: 50px; opacity: 0.3;"></i>
                        </div>
                        <p class="text-muted mb-3">@lang('messages.dash_no_history')</p>
                        <a href="{{ route('student.myexams') }}" class="btn student-action-primary btn-sm px-4">@lang('messages.dash_btn_start_exam')</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-xxl-3 col-lg-4">
        
        {{-- Profile/Welcome Card --}}
        <div class="card student-card mb-4 border-0">
            <div class="card-body text-center p-4">
                {{-- Modern Photo Container --}}
                <div class="mx-auto mb-4 position-relative" style="width: 90px; height: 90px;">
                     {{-- Photo with object-fit, modern shadow and white border --}}
                    <img src="{{ Auth::guard('student')->user()->photo ? asset('storage/' . Auth::guard('student')->user()->photo) : 'https://ui-avatars.com/api/?name='.urlencode(Auth::guard('student')->user()->name).'&background=ffffff&color=0f766e' }}"
                         class="img-fluid rounded-circle student-profile-avatar" width="90" height="90" decoding="async"
                         alt="user">
                </div>
                
                {{-- Dark text for light background --}}
                <h5 class="mb-2 fw-bold text-dark">@lang('messages.dash_welcome'), {{ Auth::guard('student')->user()->name }}!</h5>
                <p class="mb-4 text-muted small">@lang('messages.dash_keep_learning')</p>
                
                {{-- Primary colored button --}}
                <a href="{{ route('student.profile') }}" class="btn student-action-primary w-100 py-2 fw-semibold shadow-sm">
                    @lang('messages.dash_btn_view_profile')
                </a>
            </div>
        </div>

        {{-- Latest Exam Guidance --}}
        @if(!empty($latestPerformanceInsight) && !empty($latestInsightResult))
        <div class="card student-card student-insight-card">
            <div class="student-strip d-flex align-items-center justify-content-between">
                <span class="student-section-title mb-0"><i class="ri-sparkling-2-line me-1" style="color:var(--el-primary);"></i> {{ __('ui.performance_guidance') }}</span>
                <span class="student-badge student-badge-soft">{{ __('ui.latest_exam') }}</span>
            </div>
            <div class="card-body">
                <div class="small-muted fw-semibold mb-2">{{ $latestInsightResult->exam->display_name ?? $latestInsightResult->exam->name ?? __('ui.latest_exam_lower') }}</div>
                <p class="student-insight-summary mb-2">{{ $latestPerformanceInsight['summary'] ?? '' }}</p>
                @if(!empty($latestPerformanceInsight['data_points']))
                    <div class="student-insight-points">
                        @foreach(array_slice($latestPerformanceInsight['data_points'], 0, 4) as $point)
                            <span class="student-insight-point"><i class="ri-bar-chart-2-line"></i>{{ $point }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="mt-3">
                    <a href="{{ route('student.results.view', $latestInsightResult->id) }}" class="btn btn-sm student-action-primary">
                        View full analysis
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
{{-- ✅✅✅ COLORFUL POPUP LOGIC (FULL CODE) ✅✅✅ --}}

<script>
    document.addEventListener("DOMContentLoaded", function() {
        @if(false && session('new_purchase'))
            
            // 1. Colorful Confetti Blast
            var duration = 3 * 1000;
            var animationEnd = Date.now() + duration;
            var colors = ['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF']; 
            
            var interval = setInterval(function() {
                var timeLeft = animationEnd - Date.now();
                if (timeLeft <= 0) return clearInterval(interval);
                var particleCount = 50 * (timeLeft / duration);
                
                confetti({
                    particleCount: particleCount,
                    startVelocity: 30,
                    spread: 360,
                    origin: { x: Math.random(), y: Math.random() - 0.2 },
                    colors: colors
                });
            }, 250);

            // 2. Vibrant Success Popup
            Swal.fire({
                title: '',
                html: `
                    <div style="text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                        
                        <div style="margin-bottom: 20px;">
                           <i class="ri-checkbox-circle-line" style="font-size: 96px; color: var(--el-primary); filter: drop-shadow(0px 5px 10px rgba(0,0,0,0.12));"></i>
                        </div>

                        <h2 style="
                            color: var(--el-primary);
                            font-weight: 800;
                            font-size: 32px;
                            margin-bottom: 5px;
                            text-transform: uppercase;
                        ">
                            Exam Unlocked! 🔓
                        </h2>
                        
                        <p style="font-size: 16px; color: #555; margin-bottom: 25px; font-weight: 500;">
                            Yay! Your course is ready. Let's start learning! 🚀
                        </p>

                        <div style="
                            background: #fff0f5; 
                            border: 2px dashed #ff6b6b; 
                            padding: 20px; 
                            border-radius: 15px; 
                            text-align: left;
                            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                        ">
                            <p style="margin-bottom: 12px; font-size: 15px; color: #d63384; font-weight: 700; text-transform:uppercase; letter-spacing:1px;">
                                <i class="ri-flashlight-fill"></i> What's Next?
                            </p>
                            <ul style="margin: 0; padding-left: 20px; font-size: 15px; color: #444; line-height: 1.8;">
                                <li>{{ __('ui.dashboard_start_step') }}</li>
                                <li>{{ __('ui.dashboard_attempt_step') }}</li>
                            </ul>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#4f46e5',
                cancelButtonColor: '#ff6b6b',
                confirmButtonText: '🚀 Go to My Exams',
                cancelButtonText: 'I will do it later',
                width: '500px',
                padding: '2em',
                allowOutsideClick: false,
                backdrop: `rgba(255, 255, 255, 0.8)`,
                customClass: {
                    popup: 'swal2-bouncy-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('student.myexams') }}"; 
                }
            });

        @endif
    });
</script>

<style>
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
        40% {transform: translateY(-20px);}
        60% {transform: translateY(-10px);}
    }
    .swal2-bouncy-popup {
        border-radius: 20px !important;
        border: 5px solid #e0e7ff;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('new_purchase'))
            const styles = getComputedStyle(document.documentElement);
            const primaryColor = (styles.getPropertyValue('--el-primary') || '#0f766e').trim();
            const secondaryColor = (styles.getPropertyValue('--el-secondary') || '#f59e0b').trim();

            Swal.fire({
                icon: 'success',
                title: 'Course activated',
                text: 'Your exams are ready in My Exams.',
                showCancelButton: true,
                confirmButtonText: 'Go to My Exams',
                cancelButtonText: 'Stay here',
                confirmButtonColor: primaryColor,
                cancelButtonColor: secondaryColor,
                width: '420px',
                allowOutsideClick: false,
                customClass: { popup: 'student-unlock-popup' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ route('student.myexams') }}";
                }
            });
        @endif
    });
</script>
<style>
    .student-unlock-popup {
        border-radius: 8px !important;
        border: 1px solid var(--el-border);
    }
</style>
@endpush
