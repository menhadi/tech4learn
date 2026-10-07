@extends('students.layouts.app')

@section('title') @lang('messages.results_title') @endsection

@section('content')
<style>
    .student-result-stat,
    .student-result-shell,
    .result-card { border: 1px solid var(--el-border); border-radius: 8px; box-shadow: 0 4px 16px rgba(2,6,23,.05); }
    .student-result-stat .stat-icon { width: 46px; height: 46px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--el-primary-soft); color: var(--el-primary); }
    .student-result-stat .stat-icon.secondary { background: var(--el-secondary-soft); color: var(--el-secondary); }
    .student-result-stat .stat-title { color: var(--el-muted); font-weight: 700; font-size: 12px; letter-spacing: .02em; text-transform: uppercase; }
    .result-card { transition: box-shadow .2s ease-in-out, transform .2s ease-in-out; overflow: hidden; }
    .result-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(2,6,23,.09)!important; }
    .student-result-filter { border-radius: 6px!important; padding: 9px 16px; font-weight: 700; border: 1px solid var(--el-border); color: var(--el-heading); background: #fff; }
    .student-result-filter.active-primary { background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff); }
    .student-result-filter.active-secondary { background: var(--el-secondary); border-color: var(--el-secondary); color: var(--theme-button-text, #fff); }
    .student-result-filter.active-soft { background: var(--el-secondary-soft); border-color: var(--el-secondary); color: var(--el-secondary); }
    .student-result-action { background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff); border-radius: 6px; font-weight: 700; }
    .student-result-action:hover { background: var(--el-primary-dark, var(--el-primary)); border-color: var(--el-primary-dark, var(--el-primary)); color: var(--theme-button-text, #fff); }
    .student-result-muted-action { background: var(--el-secondary); border-color: var(--el-secondary); color: var(--theme-button-text, #fff); border-radius: 6px; font-weight: 700; }
    .student-result-rank { background: var(--el-primary-soft); color: var(--el-primary); border: 1px solid var(--el-border); border-radius: 999px; padding: 6px 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; }
    @media (max-width: 767.98px) {
        .filter-btn-group { width: 100%; flex-wrap: wrap; }
        .filter-btn-group .student-result-filter { flex: 1 1 auto; text-align: center; }
    }
</style>

@component('components.breadcrumb')
    @slot('li_1') @lang('messages.results_breadcrumb_dashboards') @endslot
    @slot('title') @lang('messages.results_breadcrumb_my_results') @endslot
@endcomponent

{{-- ===== Stats Row ===== --}}
<div class="row">
    <div class="col-md-3">
        <div class="card student-result-stat">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">@lang('messages.results_stat_total_attempts')</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value">{{ $stats['total_attempts'] }}</span></h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="stat-icon"><i class="ri-repeat-line fs-3"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card student-result-stat">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">{{ __('ui.rank') }}</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4">{{ $stats['rank'] ? '#' . $stats['rank'] : '-' }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="stat-icon"><i class="ri-checkbox-circle-line fs-3"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card student-result-stat">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">{{ __('ui.percentile') }}</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4">{{ $stats['percentile'] !== null ? number_format($stats['percentile'], 1) . '%' : '-' }}</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="stat-icon secondary"><i class="ri-percent-line fs-3"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card student-result-stat">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1 overflow-hidden">
                        <p class="text-uppercase fw-medium text-muted text-truncate mb-0">@lang('messages.results_stat_highest_score')</p>
                    </div>
                </div>
                <div class="d-flex align-items-end justify-content-between mt-4">
                    <div>
                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span class="counter-value">{{ number_format($stats['highest_score'], 2) }}</span>%</h4>
                    </div>
                    <div class="avatar-sm flex-shrink-0">
                        <span class="stat-icon secondary"><i class="ri-trophy-line fs-3"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== Results List ===== --}}
<div class="card student-result-shell">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0 flex-grow-1">@lang('messages.results_history_title')</h5>
        <div class="flex-shrink-0">
            <div class="d-flex gap-1 filter-btn-group">
                <a href="{{ route('student.results') }}" class="btn student-result-filter active-primary">{{ __('messages.dash_next_all_button') }}</a>
                <a href="{{ route('student.leaderboard') }}" class="btn student-result-filter">{{ __('ui.rank') }}</a>
                <a href="{{ route('student.leaderboard') }}" class="btn student-result-filter">{{ __('ui.percentile') }}</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="vstack gap-3">
            @forelse ($results as $result)
                @php
                    $exam = $result->exam;
                    $resultVisible = (int) ($exam->result_after_finish ?? 0) === 1;
                @endphp
                <div class="card result-card mb-0">
                    <div class="card-body">
                        <div class="row align-items-center gy-3">
                            <div class="col-md-4">
                                <h5 class="mb-1 fw-semibold">{{ $exam->name ?? __('ui.exam_removed') }}</h5>
                                <p class="text-muted mb-0 small">@lang('messages.results_card_attempted_on') {{ \Carbon\Carbon::parse($result->start_time)->format('d M, Y') }}</p>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    {{-- ✅✅✅ FIX APPLIED HERE ✅✅✅ --}}
                                    @if($resultVisible)
                                        {{-- Show Score if Allowed --}}
                                        <div class="col-4 text-center">
                                            <p class="text-muted mb-1">@lang('messages.results_card_your_score')</p>
                                            <h6 class="mb-0">{{ number_format($result->obtained_marks, 2) }} / {{ number_format($result->total_marks, 2) }}</h6>
                                        </div>
                                        <div class="col-4 text-center">
                                            <p class="text-muted mb-1">@lang('messages.results_card_percentage')</p>
                                            <h6 class="mb-0">{{ number_format($result->percent, 2) }}%</h6>
                                        </div>
                                        <div class="col-4 text-center">
                                            <p class="text-muted mb-1">{{ __('ui.rank') }}</p>
                                            <span class="student-result-rank"><i class="ri-trophy-line"></i>{{ $result->display_rank ? '#' . $result->display_rank : '-' }}</span>
                                        </div>
                                    @else
                                        {{-- Hide Score if Not Allowed --}}
                                        <div class="col-12 text-center">
                                            <div class="alert alert-warning mb-0 py-2 d-inline-block">
                                                <i class="ri-time-line align-middle me-1"></i> Result Awaited / Hidden by Admin
                                            </div>
                                        </div>
                                    @endif
                                    {{-- ✅✅✅ END FIX ✅✅✅ --}}
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                @if($resultVisible)
                                    <a href="{{ route('student.results.view', $result->id) }}" class="btn student-result-action">@lang('messages.results_card_view_report_button')</a>
                                @else
                                    <button class="btn student-result-muted-action" disabled>@lang('messages.results_card_view_report_button')</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">
                    <div class="fs-1 mb-3">📂</div>
                    <h4>@lang('messages.results_empty_title')</h4>
                    <p>@lang('messages.results_empty_desc')</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
