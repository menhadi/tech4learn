@extends('students.layouts.app')

@section('title', 'Study Cards')

@section('content')
@component('components.breadcrumb')
    @slot('li_1') Student @endslot
    @slot('title') Study Cards @endslot
@endcomponent

<style>
    .flashcards-hero,
    .flashcards-card { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 22px rgba(15,23,42,.06); }
    .flashcards-hero { padding:22px; margin-bottom:18px; }
    .flashcards-hero h3 { color:var(--el-heading); font-weight:800; margin-bottom:6px; }
    .flashcards-card { height:100%; overflow:hidden; display:flex; flex-direction:column; transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .flashcards-card:hover { transform:translateY(-2px); border-color:color-mix(in srgb, var(--el-primary) 30%, var(--el-border)); box-shadow:0 14px 30px rgba(15,23,42,.08); }
    .flashcards-card-top { padding:20px; background:color-mix(in srgb, var(--el-primary) 9%, #fff); border-bottom:1px solid var(--el-border); }
    .flashcards-icon { width:48px; height:48px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:var(--el-primary); color:#fff; font-size:22px; margin-bottom:14px; }
    .flashcards-card-top h4 { color:var(--el-heading); font-weight:800; line-height:1.28; }
    .flashcards-meta { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .flashcards-meta span { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-weight:700; font-size:12px; }
    .flashcards-card-body { padding:18px 20px 20px; display:flex; flex:1; flex-direction:column; }
    .flashcards-progress { height:8px; background:var(--el-primary-soft); border-radius:999px; overflow:hidden; }
    .flashcards-progress span { display:block; height:100%; background:var(--el-primary); }
    .flashcards-card-action { margin-top:auto; padding-top:2px; }
    .flashcards-study-btn { min-height:46px; width:100%; display:inline-flex !important; align-items:center; justify-content:center; gap:8px; padding:11px 18px; border-radius:999px; line-height:1.2; font-size:15px; font-weight:800; text-decoration:none; white-space:nowrap; background:var(--el-secondary) !important; border-color:var(--el-secondary) !important; color:var(--el-secondary-contrast, #111827) !important; -webkit-text-fill-color:var(--el-secondary-contrast, #111827); box-shadow:0 8px 16px color-mix(in srgb, var(--el-secondary) 18%, transparent); }
    .flashcards-study-btn i { font-size:18px; line-height:1; }
    .flashcards-study-btn:hover,
    .flashcards-study-btn:focus,
    .flashcards-study-btn:active { background:color-mix(in srgb, var(--el-secondary) 88%, #fff) !important; border-color:color-mix(in srgb, var(--el-secondary) 84%, #000) !important; color:var(--el-secondary-contrast, #111827) !important; -webkit-text-fill-color:var(--el-secondary-contrast, #111827); }
    @media (max-width:575.98px) {
        .flashcards-card-top { padding:18px; }
        .flashcards-card-body { padding:16px 18px 18px; }
    }
</style>

<div class="flashcards-hero">
    <h3>{{ __('ui.study_cards') }}</h3>
    <p class="text-muted mb-0">{{ __('ui.flash_review_copy') }}</p>
</div>

<div class="row g-3">
    @forelse($packages as $package)
        @php
            $totalCards = $package->flashcardSets->sum('cards_count');
            $review = $reviewedCards->get($package->id);
            $pointSummary = $pointSummaries->get($package->id);
            $reviewed = (int) ($pointSummary->cards_studied ?? $review->reviewed_count ?? 0);
            $points = (int) ($pointSummary->total_points ?? $review->total_points ?? 0);
            $correctAnswers = (int) ($pointSummary->correct_answers ?? 0);
            $progress = $totalCards > 0 ? min(100, round(($reviewed / $totalCards) * 100)) : 0;
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="flashcards-card">
                <div class="flashcards-card-top">
                    <div class="flashcards-icon"><i class="mdi mdi-cards-outline"></i></div>
                    <h4 class="mb-1">{{ $package->name }}</h4>
                    <div class="text-muted">{{ $package->groups->pluck('group_name')->join(', ') ?: 'Study package' }}</div>
                    <div class="flashcards-meta">
                        <span><i class="mdi mdi-layers-outline"></i>{{ $package->flashcardSets->count() }} Sets</span>
                        <span><i class="mdi mdi-card-text-outline"></i>{{ $totalCards }} Cards</span>
                        <span><i class="mdi mdi-star-outline"></i>{{ $points }} Points</span>
                        <span><i class="mdi mdi-check-circle-outline"></i>{{ $correctAnswers }} Correct</span>
                    </div>
                </div>
                <div class="flashcards-card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">{{ __('ui.reviewed') }}</span>
                        <span class="text-muted">{{ $reviewed }} / {{ $totalCards }}</span>
                    </div>
                    <div class="flashcards-progress mb-3"><span style="width: {{ $progress }}%"></span></div>
                    <div class="flashcards-card-action">
                        <a href="{{ route('student.flashcards.show', $package) }}" class="el-btn el-btn-secondary flashcards-study-btn">
                            <i class="mdi mdi-cards-outline"></i><span>{{ __('ui.study_cards') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="flashcards-card p-4 text-center text-muted">
                No study cards are available for your active packages yet.
            </div>
        </div>
    @endforelse
</div>
@endsection
