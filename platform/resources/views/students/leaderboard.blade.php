@extends('students.layouts.app')

@section('title', 'Leaderboard')

@section('content')
@component('components.breadcrumb')
    @slot('li_1') Student @endslot
    @slot('title') Leaderboard @endslot
@endcomponent

@php
    $avatarUrl = function ($photo, $name) {
        if ($photo && filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        if ($photo) {
            return asset('storage/' . $photo);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'Student') . '&background=0f766e&color=ffffff';
    };

    $formatTime = function ($seconds) {
        if (! $seconds || $seconds >= 999999) {
            return 'N/A';
        }

        $minutes = floor($seconds / 60);
        $remaining = $seconds % 60;

        return $minutes . 'm ' . $remaining . 's';
    };
@endphp

<style>
.leaderboard-page{ --leader-tertiary:var(--el-tertiary, color-mix(in srgb, var(--el-primary) 46%, var(--el-secondary))); color:var(--el-heading); }
.leaderboard-hero{ border:1px solid var(--el-border); background:var(--el-surface); border-radius:8px; padding:22px; box-shadow:0 5px 18px rgba(2,6,23,.05); }
.leaderboard-hero h4{ font-weight:800; margin-bottom:6px; }
.leaderboard-hero p{ color:var(--el-muted); margin-bottom:0; }
.leaderboard-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:18px; }
.leaderboard-card{ border:1px solid var(--el-border); background:var(--el-surface); border-radius:8px; overflow:hidden; box-shadow:0 5px 18px rgba(2,6,23,.05); }
.leaderboard-card-head{ padding:18px 20px; background:var(--el-primary-soft); border-bottom:1px solid var(--el-border); display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
.leaderboard-card-title{ font-weight:800; font-size:18px; margin-bottom:4px; color:var(--el-heading); }
.leaderboard-card-subtitle{ color:var(--el-muted); font-size:13px; }
.leaderboard-rank-pill{ background:var(--el-primary); color:var(--theme-button-text,#fff); padding:8px 12px; border-radius:6px; font-weight:800; white-space:nowrap; }
.leaderboard-toggle{ border:0; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
.leaderboard-toggle[aria-expanded="true"] .leaderboard-toggle-icon{ transform:rotate(180deg); }
.leaderboard-toggle-icon{ transition:transform .18s ease; }
.leaderboard-metrics{ display:grid; grid-template-columns:repeat(3,1fr); gap:10px; padding:14px 20px; border-bottom:1px solid var(--el-border); }
.leaderboard-metric{ background:var(--el-surface-muted); border:1px solid var(--el-border); border-radius:6px; padding:10px; }
.leaderboard-metric span{ display:block; font-size:11px; text-transform:uppercase; color:var(--el-muted); font-weight:800; }
.leaderboard-metric strong{ display:block; font-size:18px; color:var(--el-primary); margin-top:2px; }
.podium{ display:grid; grid-template-columns:1fr 1.12fr 1fr; align-items:end; gap:10px; padding:22px 20px 14px; }
.podium-place{ --podium-bg:var(--el-primary); --podium-metal:#d4af37; text-align:center; border:1px solid var(--el-border); border-radius:8px; padding:14px 10px; background:var(--el-surface-muted); min-height:150px; display:flex; flex-direction:column; justify-content:center; }
.podium-place.rank-1{ --podium-bg:var(--el-primary); --podium-metal:#d4af37; min-height:178px; box-shadow:0 10px 24px rgba(var(--el-primary-rgb),.12); }
.podium-place.rank-2{ --podium-bg:var(--el-secondary); --podium-metal:#c0c0c0; }
.podium-place.rank-3{ --podium-bg:var(--leader-tertiary); --podium-metal:#cd7f32; }
.podium-trophy{ font-size:22px; margin-bottom:6px; color:var(--podium-metal); }
.podium-avatar{ width:58px; height:58px; border-radius:50%; object-fit:cover; margin:0 auto 9px; border:4px solid var(--podium-metal); background:var(--podium-bg); padding:3px; box-shadow:0 5px 14px rgba(2,6,23,.16); }
.podium-rank{ color:var(--podium-bg); font-weight:900; font-size:18px; }
.podium-name{ font-weight:800; color:var(--el-heading); line-height:1.25; font-size:13px; min-height:34px; }
.podium-score{ color:var(--podium-bg); font-weight:900; margin-top:5px; }
.leaderboard-list{ padding:0 20px 18px; }
.leaderboard-row{ display:grid; grid-template-columns:44px 42px 1fr auto; align-items:center; gap:10px; border:1px solid var(--el-border); background:var(--el-surface); border-radius:7px; padding:10px; margin-top:8px; }
.leaderboard-row.current{ border-color:var(--el-primary); background:var(--el-primary-soft); }
.leaderboard-row-rank{ width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--el-secondary); color:var(--theme-button-text,#fff); font-weight:900; }
.leaderboard-row img{ width:36px; height:36px; border-radius:50%; object-fit:cover; }
.leaderboard-row-name{ font-weight:800; color:var(--el-heading); line-height:1.2; }
.leaderboard-row-meta{ color:var(--el-muted); font-size:12px; margin-top:2px; }
.leaderboard-score{ color:var(--el-primary); font-weight:900; white-space:nowrap; }
.leaderboard-empty{ border:1px dashed var(--el-border); border-radius:8px; padding:34px; text-align:center; color:var(--el-muted); background:var(--el-surface); }
@media (max-width:575.98px){
    .leaderboard-hero{ padding:18px; }
    .leaderboard-grid{ grid-template-columns:1fr; }
    .leaderboard-card-head{ flex-direction:column; }
    .leaderboard-metrics{ grid-template-columns:1fr; }
    .podium{ grid-template-columns:1fr; }
    .podium-place,.podium-place.rank-1{ min-height:auto; }
    .podium-place.rank-1{ order:1; }
    .podium-place.rank-2{ order:2; }
    .podium-place.rank-3{ order:3; }
    .podium-placeholder{ display:none; }
    .leaderboard-row{ grid-template-columns:38px 38px 1fr; }
    .leaderboard-score{ grid-column:3; }
}
</style>

<div class="leaderboard-page" data-leaderboard-panel="student">
    <div class="leaderboard-hero mb-4">
        <h4>{{ __('ui.leaderboard') }}</h4>
        <p>{{ __('ui.rank_update_copy') }}</p>
                    @include('components.leaderboard-period-toggle', ['leaderboardPanelKey' => 'student'])
    </div>

    @if($boards->isEmpty())
        <div class="leaderboard-empty">
            <i class="ri-trophy-line d-block mb-2" style="font-size:44px;color:var(--el-primary);"></i>
            Complete an exam to unlock your leaderboard ranks.
        </div>
    @else
        <div class="leaderboard-grid">
            @foreach($boards as $board)
                @php
                    $top = collect($board['top']);
                    $first = $top->firstWhere('rank', 1);
                    $second = $top->firstWhere('rank', 2);
                    $third = $top->firstWhere('rank', 3);
                    $others = $top->where('rank', '>', 3);
                    $isExamBoard = ($board['title'] ?? '') === 'Exam leaderboard';
                    $collapseId = 'exam-rank-details-' . $loop->iteration;
                @endphp
                <div class="leaderboard-card">
                    <div class="leaderboard-card-head">
                        <div>
                            <div class="leaderboard-card-title">{{ $board['label'] }}</div>
                            <div class="leaderboard-card-subtitle">{{ $board['title'] }}</div>
                        </div>
                        @if($isExamBoard)
                            <button class="leaderboard-rank-pill leaderboard-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                View Exam Rank <i class="ri-arrow-down-s-line leaderboard-toggle-icon"></i>
                            </button>
                        @else
                            <div class="leaderboard-rank-pill">Your Rank #{{ $board['rank'] }}</div>
                        @endif
                    </div>
                    <div id="{{ $collapseId }}" class="{{ $isExamBoard ? 'collapse' : '' }}">
                    <div class="leaderboard-metrics">
                        <div class="leaderboard-metric">
                            <span>{{ __('ui.percentile') }}</span>
                            <strong>{{ $board['percentile'] }}%</strong>
                        </div>
                        <div class="leaderboard-metric">
                            <span>{{ __('ui.students') }}</span>
                            <strong>{{ $board['total_students'] }}</strong>
                        </div>
                        <div class="leaderboard-metric">
                            <span>{{ __('ui.scope') }}</span>
                            <strong style="font-size:14px;">{{ $board['subtitle'] }}</strong>
                        </div>
                    </div>

                    <div class="podium">
                        @foreach([$second, $first, $third] as $student)
                            @if($student)
                                <div class="podium-place rank-{{ $student['rank'] }}">
                                    <img class="podium-avatar" src="{{ $avatarUrl($student['photo'], $student['name']) }}" alt="{{ $student['name'] }}">
                                    <i class="ri-trophy-fill podium-trophy"></i>
                                    <div class="podium-rank">#{{ $student['rank'] }}</div>
                                    <div class="podium-name">{{ $student['name'] }}</div>
                                    <div class="podium-score">{{ $student['score'] }}%</div>
                                </div>
                            @else
                                <div class="podium-placeholder"></div>
                            @endif
                        @endforeach
                    </div>

                    @if($others->isNotEmpty())
                        <div class="leaderboard-list">
                            @foreach($others as $student)
                                <div class="leaderboard-row {{ $student['is_current'] ? 'current' : '' }}">
                                    <div class="leaderboard-row-rank">{{ $student['rank'] }}</div>
                                    <img src="{{ $avatarUrl($student['photo'], $student['name']) }}" alt="{{ $student['name'] }}">
                                    <div>
                                        <div class="leaderboard-row-name">{{ $student['name'] }}</div>
                                        <div class="leaderboard-row-meta">{{ $student['attempts'] }} attempts - {{ $formatTime($student['time']) }}</div>
                                    </div>
                                    <div class="leaderboard-score">{{ $student['score'] }}%</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
