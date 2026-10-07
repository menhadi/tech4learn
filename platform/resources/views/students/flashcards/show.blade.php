@extends('students.layouts.app')

@section('title', 'Study Cards')

@section('content')
@component('components.breadcrumb')
    @slot('li_1') Study Cards @endslot
    @slot('title') {{ $package->name }} @endslot
@endcomponent

@php
    $totalStudySets = $package->flashcardSets->count();
    $totalStudyCards = $package->flashcardSets->sum(fn ($set) => $set->cards->count());
    $globalStudyCardNumber = 0;
    $studyHierarchy = $studyHierarchy ?? collect();
    $studyIndexHierarchy = $studyIndexHierarchy ?? $studyHierarchy;
    $studyFilter = $studyFilter ?? ['section' => null, 'topic' => null, 'subtopic' => null];
    $hasStudyFilter = $hasStudyFilter ?? false;
    $activeStudyTitle = $activeStudyTitle ?? null;
    $studyBaseUrl = route('student.flashcards.show', $package);
    $continueSet = $courseProgress?->current_flashcard_set_id ? $package->flashcardSets->firstWhere('id', $courseProgress->current_flashcard_set_id) : null;
    $continueParams = $continueSet ? array_filter([
        'study_section' => $continueSet->subject_id ? 'subject-'.$continueSet->subject_id : ($continueSet->category_level_1 ? 'category-'.$continueSet->category_level_1 : null),
        'study_topic' => $continueSet->topic_id ? 'topic-'.$continueSet->topic_id : ($continueSet->category_level_2 ? 'subcategory-'.$continueSet->category_level_2 : null),
        'study_subtopic' => $continueSet->stopic_id ? 'subtopic-'.$continueSet->stopic_id : null,
    ]) : [];
    $continueUrl = $continueSet ? $studyBaseUrl.'?'.http_build_query($continueParams).'#student-study-card-'.$courseProgress->current_flashcard_id : null;
    $reviewCards = $progressByCard->filter(fn ($progress) => $progress->wrong_attempts > 0 || in_array($progress->confidence, ['again', 'hard'], true));
    $studiedCount = function ($sets) use ($progressByCard) { $ids = collect($sets)->flatMap(fn ($set) => $set->cards->pluck('id')); return $ids->filter(fn ($id) => $progressByCard->has($id) && $progressByCard->get($id)->viewed_at)->count(); };
@endphp

<style>
    .study-hero,
    .study-set,
    .study-card { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 22px rgba(15,23,42,.06); }
    .study-hero { padding:22px; margin-bottom:18px; display:flex; justify-content:space-between; gap:14px; align-items:center; }
    .study-hero h3 { color:var(--el-heading); font-weight:800; margin-bottom:5px; }
    .study-set { margin-bottom:18px; overflow:hidden; }
    .study-set-header { padding:16px 18px; background:color-mix(in srgb, var(--el-primary) 9%, #fff); border-bottom:1px solid var(--el-border); }
    .study-section-header { padding:18px; background:color-mix(in srgb, var(--el-primary) 10%, #fff); border-bottom:1px solid var(--el-border); display:flex; justify-content:space-between; gap:14px; align-items:flex-start; }
    .study-section-header h4 { margin:0; color:var(--el-heading); font-weight:900; }
    .study-section-header p { margin:4px 0 0; color:var(--el-muted); }
    .study-section-count { display:inline-flex; align-items:center; gap:7px; flex:0 0 auto; padding:7px 11px; border-radius:999px; background:var(--el-primary); color:#fff; font-size:13px; font-weight:900; }
    .study-topic { padding:15px 18px; }
    .study-topic + .study-topic { border-top:1px solid var(--el-border); }
    .study-topic-title { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:12px; color:var(--el-heading); font-weight:900; }
    .study-topic-title span { color:var(--el-primary); font-size:13px; font-weight:850; }
    .study-subtopic { border:1px solid var(--el-border); border-radius:12px; overflow:hidden; background:#fff; margin-top:12px; }
    .study-subtopic-head { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; background:var(--el-bg); color:var(--el-heading); font-weight:850; }
    .study-subtopic-head span { color:var(--el-muted); font-size:13px; }
    .study-set-inner { border-top:1px solid var(--el-border); }
    .study-set-inner:first-of-type { border-top:0; }
    .study-set-inner .study-set-header { background:#fff; }
    .study-index-panel { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 22px rgba(15,23,42,.06); margin-bottom:18px; overflow:hidden; }
    .study-index-head { padding:16px 18px; background:color-mix(in srgb, var(--el-primary) 8%, #fff); border-bottom:1px solid var(--el-border); display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .study-index-head h4 { margin:0; color:var(--el-heading); font-weight:900; }
    .study-index-head p { margin:4px 0 0; color:var(--el-muted); }
    .study-index-active { display:inline-flex; align-items:center; gap:7px; padding:7px 11px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-weight:850; font-size:13px; }
    .study-index { display:grid; gap:12px; padding:16px 18px; }
    .study-index-section { border:1px solid var(--el-border); border-radius:12px; padding:14px; background:#fff; }
    .study-index-link { display:flex; align-items:center; justify-content:space-between; gap:12px; color:var(--el-heading); text-decoration:none; font-weight:900; }
    .study-index-link:hover { color:var(--el-primary); }
    .study-index-count { color:var(--el-primary); background:var(--el-primary-soft); border-radius:999px; padding:5px 10px; font-size:12px; font-weight:900; white-space:nowrap; }
    .study-index-topic-list { display:grid; gap:8px; margin-top:12px; }
    .study-index-topic { padding:10px 12px; border-radius:12px; background:color-mix(in srgb, var(--el-primary) 4%, #fff); }
    .study-index-subtopics { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .study-index-chip { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:999px; border:1px solid var(--el-border); color:var(--el-primary); text-decoration:none; font-size:13px; font-weight:850; background:#fff; }
    .study-index-chip:hover { background:var(--el-primary-soft); color:var(--el-primary); }
    .study-hero-actions { display:flex; justify-content:flex-end; align-items:center; }
    .study-hero .study-back-btn { display:inline-flex; align-items:center; gap:8px; min-width:auto; padding:10px 16px; }
    .study-layout { display:grid; grid-template-columns:minmax(0, 1fr) 340px; gap:18px; align-items:start; }
    .study-main { min-width:0; max-width:100%; overflow:visible; }
    .study-set-header h4, .study-card-row-title, .study-side-title { overflow-wrap:anywhere; word-break:break-word; }
    .study-side { position:static; min-width:0; max-width:100%; display:grid; gap:16px; }
    .study-side-card { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 22px rgba(15,23,42,.06); padding:16px; }
    .study-side-icon { width:52px; height:52px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:var(--el-primary); color:#fff; font-size:25px; margin-bottom:12px; }
    .study-side-title { color:var(--el-heading); font-size:20px; font-weight:900; line-height:1.25; margin-bottom:10px; }
    .study-side-meta { display:grid; gap:10px; }
    .study-side-meta span { display:flex; align-items:center; gap:10px; color:var(--el-muted); font-weight:700; }
    .study-side-meta i { width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:var(--el-primary-soft); color:var(--el-primary); font-size:19px; }
    .study-card { box-shadow:none; margin:14px; padding:18px; cursor:default; border-radius:12px; background:linear-gradient(180deg, #fff 0%, color-mix(in srgb, var(--el-primary) 3%, #fff) 100%); transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .study-card-detail { display:none; }
    .study-card-detail.is-open { display:block; }
    .study-card-list-head,
    .study-card-row { display:grid; grid-template-columns:56px minmax(0, 1fr) 150px 126px; gap:14px; align-items:center; }
    .study-card-list-head { padding:13px 18px; background:color-mix(in srgb, var(--el-primary) 8%, #fff); border-bottom:1px solid var(--el-border); color:var(--el-heading); font-size:12px; font-weight:900; text-transform:uppercase; }
    .study-card-row { padding:15px 18px; border-bottom:1px solid color-mix(in srgb, var(--el-border) 78%, #fff); background:#fff; }
    .study-card-row:nth-child(even) { background:color-mix(in srgb, var(--el-primary) 3%, #fff); }
    .study-card-row-no { color:var(--el-primary); font-size:18px; font-weight:900; }
    .study-card-row-title { color:var(--el-heading); font-weight:900; line-height:1.35; margin:0; }
    .study-card-row-summary { margin:5px 0 0; color:var(--el-muted); font-size:13px; line-height:1.45; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .study-card-row-chip { width:max-content; max-width:100%; display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-size:13px; font-weight:850; }
    .study-card-row-action { justify-self:end; min-width:104px; }
    .study-card:hover { transform:translateY(-1px); box-shadow:0 10px 26px rgba(15,23,42,.08); }
    .study-card-front { font-size:17px; line-height:1.65; color:var(--el-heading); padding:14px; border:1px solid var(--el-border); border-radius:10px; background:#fff; }
    .study-card-back { display:none; margin-top:14px; padding:16px; border-radius:10px; background:var(--el-primary-soft); color:var(--el-heading); border:1px solid color-mix(in srgb, var(--el-primary) 20%, #fff); transform-origin:top center; }
    .study-card-back.is-visible { display:block; animation:studyFlipIn .32s ease; }
    .study-card.is-flipped { border-color:color-mix(in srgb, var(--el-primary) 45%, var(--el-border)); box-shadow:0 14px 32px rgba(15,23,42,.08); }
    @keyframes studyFlipIn {
        from { opacity:0; transform:perspective(900px) rotateX(-18deg) translateY(-6px); }
        to { opacity:1; transform:perspective(900px) rotateX(0) translateY(0); }
    }
    .study-card-type { display:inline-flex; align-items:center; padding:5px 10px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-size:12px; font-weight:800; }
    .study-card-options { display:grid; gap:8px; margin-top:14px; }
    .study-card-option { padding:10px 12px; border:1px solid var(--el-border); border-radius:8px; background:#fff; color:var(--el-heading); text-align:left; font-weight:700; transition:.18s ease; }
    .study-card-option:hover { border-color:var(--el-primary); background:var(--el-primary-soft); }
    .study-card-option.is-correct { border-color:#16a34a; background:#dcfce7; color:#166534; }
    .study-card-option.is-wrong { border-color:#ef4444; background:#fee2e2; color:#991b1b; }
    .study-correct-answer { display:block; margin-top:8px; padding:8px 12px; border-radius:8px; background:#fff; color:var(--el-primary); font-weight:800; }
    .study-card-answer-action { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:14px; }
    .study-answer-help { color:var(--el-muted); font-size:13px; }
    .study-card .js-show-answer.is-disabled,
    .study-card .js-show-answer[aria-disabled="true"] { opacity:.72; cursor:not-allowed; background:var(--el-primary-soft) !important; border-color:color-mix(in srgb, var(--el-primary) 24%, #fff) !important; color:var(--el-primary) !important; -webkit-text-fill-color:var(--el-primary); }
    .study-card .js-show-answer.is-disabled:hover,
    .study-card .js-show-answer[aria-disabled="true"]:hover { transform:none; box-shadow:none !important; }
    .study-card.is-answer-needed { border-color:color-mix(in srgb, var(--el-secondary) 50%, var(--el-border)); }
    .study-review-buttons { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .study-review-buttons button,
    .study-card .js-show-answer,
    .study-hero .el-btn { min-width:96px; box-shadow:none !important; border-radius:8px; font-weight:700; }
    .study-review-buttons .el-btn-primary:hover,
    .study-review-buttons .el-btn-primary:focus,
    .study-review-buttons .el-btn-primary:active,
    .study-card .js-show-answer:hover,
    .study-card .js-show-answer:focus,
    .study-card .js-show-answer:active { color:#fff !important; -webkit-text-fill-color:#fff; background:color-mix(in srgb, var(--el-primary) 88%, #000) !important; border-color:color-mix(in srgb, var(--el-primary) 88%, #000) !important; }
    .study-review-buttons .el-btn-secondary:hover,
    .study-review-buttons .el-btn-secondary:focus,
    .study-review-buttons .el-btn-secondary:active,
    .study-hero .el-btn-secondary:hover,
    .study-hero .el-btn-secondary:focus,
    .study-hero .el-btn-secondary:active { color:var(--el-secondary-contrast, #111827) !important; -webkit-text-fill-color:var(--el-secondary-contrast, #111827); background:color-mix(in srgb, var(--el-secondary) 88%, #fff) !important; border-color:color-mix(in srgb, var(--el-secondary) 88%, #000) !important; }
    .study-rating-help { color:var(--el-muted); font-size:13px; margin-top:12px; }
    .study-stats-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; margin-top:14px; }
    .study-stat { padding:12px; border:1px solid var(--el-border); border-radius:8px; background:#fff; }
    .study-stat strong { display:block; color:var(--el-primary); font-size:22px; line-height:1; }
    .study-stat span { color:var(--el-muted); font-size:12px; font-weight:700; }
    .study-leaderboard { background:#fff; border:1px solid var(--el-border); border-radius:8px; box-shadow:0 8px 22px rgba(15,23,42,.06); padding:16px; margin-bottom:18px; }
    .study-leaderboard h4 { color:var(--el-heading); font-weight:800; margin-bottom:12px; }
    .study-leader-row { display:grid; grid-template-columns:42px minmax(0, 1fr) auto; gap:10px; align-items:center; padding:10px 0; border-top:1px solid var(--el-border); }
    .study-leader-row:first-of-type { border-top:0; }
    .study-leader-rank { width:36px; height:36px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:var(--el-primary-soft); color:var(--el-primary); font-weight:900; }
    .study-leader-rank.rank-1 { background:var(--el-primary); color:#fff; }
    .study-leader-rank.rank-2 { background:var(--el-secondary); color:var(--el-secondary-contrast, #111827); }
    .study-leader-rank.rank-3 { background:var(--el-tertiary, #38bdf8); color:#fff; }
    .study-leader-name { color:var(--el-heading); font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .study-leader-meta { color:var(--el-muted); font-size:12px; }
    .study-points-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:var(--el-primary-soft); color:var(--el-primary); font-weight:800; }
    .study-report-btn { min-width:auto !important; padding:8px 12px !important; font-size:13px; box-shadow:none !important; }
    .study-report-modal .modal-content { border-radius:12px; border:1px solid var(--el-border); }
    .study-report-modal .modal-header { background:var(--el-primary-soft); border-bottom:1px solid var(--el-border); }
    .study-report-modal .modal-title { color:var(--el-heading); font-weight:800; }
    .study-points-toast { position:fixed; right:18px; bottom:18px; z-index:1050; display:none; padding:10px 14px; border-radius:8px; background:var(--el-primary); color:#fff; font-weight:800; box-shadow:0 14px 34px rgba(15,23,42,.18); }
    .study-points-toast.is-visible { display:block; }
    @media (max-width: 1199px) {
        .study-layout { grid-template-columns:1fr; }
        .study-side { position:static; order:-1; }
    }
    @media (max-width: 767px) {
        .study-card-list-head { display:none; }
        .study-card-row { grid-template-columns:40px minmax(0, 1fr); gap:10px; padding:14px; }
        .study-card-row > :nth-child(3) { grid-column:2; }
        .study-card-row-action { grid-column:1 / -1; justify-self:stretch; width:100%; }
    }
    @media (max-width: 575px) {
        .study-hero { flex-direction:column; align-items:flex-start; }
        .study-hero-actions { justify-content:flex-start; width:100%; }
        .study-review-buttons button { flex:1 1 45%; }
        .study-stats-grid { grid-template-columns:1fr; }
    }
</style>

<div class="study-hero">
    <div>
        <h3>{{ $package->name }}</h3>
        <p class="text-muted mb-0">{{ __('ui.read_reveal_rate') }}</p>
        <div class="study-stats-grid">
            <div class="study-stat">
                <strong id="studyTotalPoints">{{ (int) ($pointSummary->total_points ?? 0) }}</strong>
                <span>{{ __('ui.study_points') }}</span>
            </div>
            <div class="study-stat">
                <strong id="studyCardsStudied">{{ (int) ($pointSummary->cards_studied ?? 0) }}</strong>
                <span>{{ __('ui.cards_studied') }}</span>
            </div>
            <div class="study-stat">
                <strong id="studyCorrectAnswers">{{ (int) ($pointSummary->correct_answers ?? 0) }}</strong>
                <span>{{ __('ui.correct_answers') }}</span>
            </div>
        </div>
    </div>
    <div class="study-hero-actions d-flex gap-2 flex-wrap">
        @if($continueUrl)<a href="{{ $continueUrl }}" class="el-btn el-btn-primary"><i class="mdi mdi-play-circle-outline"></i> {{ __('ui.continue_learning') }}</a>@endif
        <a href="{{ route('student.flashcards.index') }}" class="el-btn el-btn-secondary study-back-btn">
            <i class="mdi mdi-arrow-left"></i>
            Back to Study Cards
        </a>
    </div>
</div>

<div class="study-layout">
<main class="study-main">
<div class="study-index-panel">
    <div class="study-index-head">
        <div>
            <h4>{{ __('ui.choose_study_cards') }}</h4>
            <p>{{ __('ui.choose_study_copy') }}</p>
        </div>
        @if($activeStudyTitle)
            <span class="study-index-active"><i class="mdi mdi-filter-variant"></i>{{ $activeStudyTitle }}</span>
        @endif
    </div>
    @if($studyIndexHierarchy->isEmpty())
        <div class="p-4 text-muted">{{ __('ui.no_active_cards_package') }}</div>
    @else
        <div class="study-index">
            @foreach($studyIndexHierarchy as $indexSection)
                @php $sectionUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key']]); $sectionSets = collect($indexSection['topics'])->flatMap(fn ($topic) => collect($topic['subtopics'])->flatMap(fn ($subtopic) => $subtopic['sets'])); $sectionDone = $studiedCount($sectionSets); @endphp
                <div class="study-index-section">
                    <a class="study-index-link" href="{{ $sectionUrl }}">
                        <span>{{ $indexSection['label'] }}</span>
                        <span class="study-index-count">{{ $sectionDone }}/{{ $indexSection['cards_count'] }} studied</span>
                    </a>
                    <div class="study-index-topic-list">
                        @foreach($indexSection['topics'] as $indexTopic)
                            @php $topicUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key'], 'study_topic' => $indexTopic['key']]); $topicSets = collect($indexTopic['subtopics'])->flatMap(fn ($subtopic) => $subtopic['sets']); $topicDone = $studiedCount($topicSets); @endphp
                            <div class="study-index-topic">
                                <a class="study-index-link" href="{{ $topicUrl }}">
                                    <span>{{ $indexTopic['label'] }}</span>
                                    <span class="study-index-count">{{ $topicDone }}/{{ $indexTopic['cards_count'] }}</span>
                                </a>
                                <div class="study-index-subtopics">
                                    @foreach($indexTopic['subtopics'] as $indexSubtopic)
                                        @php $subtopicUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key'], 'study_topic' => $indexTopic['key'], 'study_subtopic' => $indexSubtopic['key']]); $subtopicDone = $studiedCount($indexSubtopic['sets']); @endphp
                                        <a class="study-index-chip" href="{{ $subtopicUrl }}">{{ $indexSubtopic['label'] }} <strong>{{ $subtopicDone }}/{{ $indexSubtopic['cards_count'] }}</strong></a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if(! $hasStudyFilter && $studyIndexHierarchy->isNotEmpty())
    <div class="study-card text-center text-muted">{{ __('ui.choose_study_path') }}</div>
@endif
@foreach($studyHierarchy as $section)
    <div class="study-set">
        <div class="study-section-header">
            <div>
                <h4>{{ $section['label'] }}</h4>
                <p>{{ __('ui.related_study_cards') }}</p>
            </div>
            <span class="study-section-count">
                <i class="mdi mdi-layers-triple-outline"></i>
                {{ $section['cards_count'] }} card{{ $section['cards_count'] === 1 ? '' : 's' }}
            </span>
        </div>

        @foreach($section['topics'] as $topic)
            <div class="study-topic">
                <div class="study-topic-title">
                    <strong>{{ $topic['label'] }}</strong>
                    <span>{{ $topic['cards_count'] }} card{{ $topic['cards_count'] === 1 ? '' : 's' }}</span>
                </div>

                @foreach($topic['subtopics'] as $subtopic)
                    <div class="study-subtopic">
                        <div class="study-subtopic-head">
                            <strong>{{ $subtopic['label'] }}</strong>
                            <span>{{ $subtopic['sets_count'] }} set{{ $subtopic['sets_count'] === 1 ? '' : 's' }}</span>
                        </div>

                        @foreach($subtopic['sets'] as $set)
                            <div class="study-set-inner">
                                <div class="study-set-header">
                                    <h4 class="mb-1">{{ $set->title }}</h4>
                                    <div class="text-muted">{{ $set->cards->count() }} cards</div>
                                </div>

        @if($set->cards->isNotEmpty())
            <div class="study-card-list-head">
                <span>{{ __('messages.my_exams_js_no') }}</span>
                <span>{{ __('ui.study_card') }}</span>
                <span>{{ __('ui.questions') }}</span>
                <span>{{ __('messages.dash_col_action') }}</span>
            </div>
        @endif

        @forelse($set->cards as $card)
            @php
                $globalStudyCardNumber++;
                $review = $reviews->get($card->id);
                $question = $card->getRelationValue('displayQuestion') ?: $card->sourceQuestions->first() ?: $card->sourceQuestion;
                $questionType = strtoupper(trim((string) ($question?->qtype?->type ?? '')));
                $questionTypeLabel = strtolower((string) ($question?->qtype?->question_type ?? ''));
                $isTrueFalse = $question && ($questionType === 'T' || $questionType === 'TF' || str_contains($questionTypeLabel, 'true') || str_contains($questionTypeLabel, 'false'));
                $isFillBlank = $question && ($questionType === 'F' || $questionType === 'FB' || str_contains($questionTypeLabel, 'fill'));
                $isMcq = $question && ! $isTrueFalse && ! $isFillBlank;
                $questionOptions = collect();
                $correctOptions = collect();

                if ($question) {
                    if ($isTrueFalse) {
                        $questionOptions = collect(['True', 'False']);
                        $correctOptions = collect([mb_strtolower(trim(strip_tags((string) $question->true_false)))])->filter()->values();
                    } elseif ($isFillBlank) {
                        $correctOptions = collect([mb_strtolower(trim(strip_tags((string) $question->fill_blank)))])->filter()->values();
                    } else {
                        $questionOptions = collect(range(1, 6))
                            ->map(fn ($index) => trim((string) $question->{'option' . $index}))
                            ->filter()
                            ->values();
                        $correctOptions = collect($question->correctOptionValues())
                            ->map(fn ($answer) => mb_strtolower(trim(strip_tags((string) $answer))))->values();
                    }
                } else {
                    $questionOptions = collect($card->options ?? []);
                    $correctOptions = collect(preg_split('/\s*\|\|\s*/', trim(strip_tags((string) $card->back))) ?: [])
                        ->map(fn ($answer) => mb_strtolower(trim($answer)))
                        ->filter()
                        ->values();
                }

                $cardTypeLabel = $question
                    ? ($question->qtype?->question_type ?: 'Question')
                    : (['basic' => 'Q/A', 'mcq' => 'MCQ', 'true_false' => 'True/False', 'fill_blank' => 'Fill Blank', 'multi_select' => 'Multiselect'][$card->card_type ?? 'basic'] ?? 'Q/A');
                $questionCount = (int) ($card->display_question_pool_count ?? 0);
                if ($questionCount < 1) {
                    $questionCount = $card->sourceQuestions->isNotEmpty() ? $card->sourceQuestions->count() : ($question ? 1 : 0);
                }
                $questionCount += ($card->checks?->count() ?? 0);
                $plainFront = trim(preg_replace('/\s+/', ' ', strip_tags((string) $card->front)));
                $plainQuestion = $question ? trim(preg_replace('/\s+/', ' ', strip_tags((string) $question->question))) : '';
                $cardTitle = trim((string) ($card->title ?? ''));
                $cardDisplayTitle = $cardTitle !== ''
                    ? $cardTitle
                    : (($set->title ? $set->title . ' ' : 'Study Card ') . $globalStudyCardNumber);
                $cardSummary = $plainFront ?: ($plainQuestion ?: 'Open this card to read the concept and answer linked questions.');
                $cardDetailId = 'student-study-card-' . $card->id;
            @endphp
            <div class="study-card-row" id="card-row-{{ $card->id }}">
                <span class="study-card-row-no">{{ $globalStudyCardNumber }}</span>
                <div>
                    <h5 class="study-card-row-title">{{ $cardDisplayTitle }}</h5>
                    <p class="study-card-row-summary">{{ $cardSummary }}</p>
                </div>
                <span class="study-card-row-chip">{{ $questionCount }} question{{ $questionCount === 1 ? '' : 's' }}</span>
                <button type="button" class="el-btn el-btn-primary study-card-row-action js-student-study-toggle" data-target="{{ $cardDetailId }}" aria-expanded="false">
                    <i class="mdi mdi-book-open-page-variant-outline me-1"></i> Read
                </button>
            </div>
            <div id="{{ $cardDetailId }}"
                 class="study-card study-card-detail"
                 data-track-url="{{ route('student.flashcards.track', $card) }}"
                 data-report-url="{{ route('student.flashcards.report', $card) }}"
                 data-question-id="{{ $question?->id }}"
                 data-requires-answer="{{ $questionOptions->isNotEmpty() ? '1' : '0' }}"
                 data-answered="{{ $questionOptions->isNotEmpty() ? '0' : '1' }}">
                <div class="d-flex justify-content-between gap-3 flex-wrap mb-2">
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <span class="study-card-type">{{ $cardTypeLabel }}</span>
                        <strong>{{ $card->difficulty ?: 'Study Card' }}</strong>
                    </div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        @if($review)
                            <span class="badge bg-success-subtle text-success">Last marked: {{ ucfirst($review->response) }}</span>
                        @endif
                        <button type="button" class="el-btn el-btn-secondary study-report-btn js-study-report" data-card-title="{{ $set->title }} card {{ $loop->iteration }}">
                            <i class="mdi mdi-flag-outline me-1"></i> {{ __('messages.results_view_breadcrumb_report') }}
                        </button>
                    </div>
                </div>
                @if(trim(strip_tags((string) $card->front)) !== '')
                    <div class="study-card-front">{!! $card->front !!}</div>
                @endif
                @if($question)
                    <div class="study-card-front mt-3">{!! $question->question !!}</div>
                @endif
                @if($questionOptions->isNotEmpty())
                    <div class="study-card-options">
                        @foreach($questionOptions as $option)
                            @php $normalized = mb_strtolower(trim(strip_tags((string) $option))); @endphp
                            <button type="button" class="study-card-option" data-correct="{{ $correctOptions->contains($normalized) ? '1' : '0' }}">{!! $option !!}</button>
                        @endforeach
                    </div>
                @endif
                @if($card->hint)
                    <div class="text-muted mt-2"><i class="mdi mdi-lightbulb-outline me-1"></i>{{ $card->hint }}</div>
                @endif
                <div class="study-card-answer-action">
                    <button type="button"
                            class="el-btn el-btn-primary js-show-answer {{ $questionOptions->isNotEmpty() ? 'is-disabled' : '' }}"
                            @if($questionOptions->isNotEmpty()) aria-disabled="true" @endif>
                        <i class="mdi mdi-rotate-3d-variant me-1"></i>
                        {{ $questionOptions->isNotEmpty() ? 'Choose an option first' : 'Show Answer' }}
                    </button>
                    @if($questionOptions->isNotEmpty())
                        <span class="study-answer-help">{{ __('ui.select_option_unlock') }}</span>
                    @endif
                </div>
                <div class="study-card-back">
                    <div class="fw-bold mb-2">{{ __('ui.answer') }}</div>
                    @if(trim(strip_tags((string) $card->back)) !== '')
                        <div class="mb-3">{!! $card->back !!}</div>
                    @endif
                    <div class="study-correct-answer">
                        @if($question)
                            @if($isTrueFalse)
                                {!! ucfirst(strtolower((string) $question->true_false)) !!}
                            @elseif($isFillBlank)
                                {!! $question->fill_blank !!}
                            @else
                                {!! collect($question->correctOptionValues())->filter()->implode('<br>') !!}
                            @endif
                        @else
                            {!! $card->back !!}
                        @endif
                    </div>
                    @if($question?->explanation || $card->explanation)
                        <div class="fw-bold mt-3 mb-2">{{ __('ui.explanation') }}</div>
                        <div>{!! $question?->explanation ?: $card->explanation !!}</div>
                    @endif
                    @if(($card->checks ?? collect())->isNotEmpty())
                        <div class="fw-bold mt-3 mb-2">Knowledge Checks ({{ $card->checks->count() }})</div>
                        @foreach($card->checks as $check)
                            <details class="border rounded p-2 mb-2">
                                <summary class="fw-bold">{{ $check->difficulty ?: 'Practice' }}: {{ $check->question }}</summary>
                                @if($check->options)<div class="mt-2">@foreach($check->options as $option)<div>&bull; {{ $option }}</div>@endforeach</div>@endif
                                @if($check->correct_answer)<div class="mt-2"><strong>Answer:</strong> {{ $check->correct_answer }}</div>@endif
                                @if($check->explanation)<div class="mt-2"><strong>{{ __('messages.results_view_explanation') }}</strong> {{ $check->explanation }}</div>@endif
                            </details>
                        @endforeach
                    @endif
                    <div class="study-rating-help">{{ __('ui.rate_recall') }}</div>
                    <form action="{{ route('student.flashcards.review', $card) }}" method="POST" class="study-review-buttons">
                        @csrf
                        <button type="submit" name="response" value="again" class="el-btn el-btn-secondary" title="I did not remember. Review sooner.">{{ __('ui.again') }}</button>
                        <button type="submit" name="response" value="hard" class="el-btn el-btn-secondary" title="I remembered with difficulty.">{{ __('ui.hard') }}</button>
                        <button type="submit" name="response" value="good" class="el-btn el-btn-primary" title="I remembered correctly.">{{ __('messages.feedback_option_exp_good') }}</button>
                        <button type="submit" name="response" value="easy" class="el-btn el-btn-primary" title="I remembered confidently.">{{ __('ui.easy') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-4 text-muted">{{ __('ui.no_active_cards_set') }}</div>
        @endforelse
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endforeach
@if($hasStudyFilter && $studyHierarchy->isEmpty())
    <div class="study-card text-center text-muted">{{ __('ui.no_active_cards_selection') }}</div>
@endif
</main>

<aside class="study-side">
    @if(isset($leaderboard))
        <div class="study-leaderboard" data-leaderboard-panel="flashcard">
            <h4><i class="mdi mdi-trophy-outline me-1"></i>{{ __('ui.top_learners_course') }}</h4>
            @include('components.leaderboard-period-toggle', [
                'leaderboardPanelKey' => 'flashcard',
                'leaderboardApiUrl' => route('student.flashcards.leaderboard', $package),
            ])
            <div data-leaderboard-rows>
                @include('students.flashcards.partials.leaderboard_rows', ['leaders' => $leaderboard])
            </div>
        </div>
    @endif

    @if($reviewCards->isNotEmpty())
        <div class="study-side-card">
            <div class="study-side-title">{{ __('ui.review_queue') }}</div>
            <p class="text-muted">{{ $reviewCards->count() }} card{{ $reviewCards->count() === 1 ? '' : 's' }} marked incorrect or low confidence. All curriculum content remains open.</p>
        </div>
    @endif
    <div class="study-side-card">
        <span class="study-side-icon"><i class="mdi mdi-cards-outline"></i></span>
        <div class="study-side-title">{{ $package->name }}</div>
        <div class="study-side-meta">
            <span><i class="mdi mdi-folder-outline"></i>{{ $totalStudySets }} study set{{ $totalStudySets === 1 ? '' : 's' }}</span>
            <span><i class="mdi mdi-card-text-outline"></i>{{ $totalStudyCards }} study card{{ $totalStudyCards === 1 ? '' : 's' }}</span>
            <span><i class="mdi mdi-star-outline"></i><strong id="studySideTotalPoints">{{ (int) ($pointSummary->total_points ?? 0) }}</strong> {{ __('ui.study_points_lower') }}</span>
        </div>
    </div>
</aside>
</div>

<div class="modal fade study-report-modal" id="studyCardReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="studyCardReportForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('ui.report_study_card') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.my_exams_modal_close_button') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="studyCardReportTitle">{{ __('ui.correction_copy') }}</p>
                <label class="form-label fw-bold">{{ __('ui.issue_type') }}</label>
                <select name="report_type" class="form-control mb-3">
                    <option value="Study Card Issue">{{ __('ui.study_card_issue') }}</option>
                    <option value="Wrong Answer">{{ __('ui.wrong_answer') }}</option>
                    <option value="Explanation Issue">{{ __('ui.explanation_issue') }}</option>
                    <option value="Typo Error">{{ __('ui.typo_error') }}</option>
                    <option value="Formatting Issue">{{ __('ui.formatting_issue') }}</option>
                    <option value="Other">{{ __('ui.other') }}</option>
                </select>
                <label class="form-label fw-bold">{{ __('messages.my_exams_details_button') }} <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="message" class="form-control" rows="4" placeholder="Optional: describe what should be corrected."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="el-btn el-btn-secondary" data-bs-dismiss="modal">{{ __('messages.edit_profile_cancel_button') }}</button>
                <button type="submit" class="el-btn el-btn-primary">{{ __('ui.submit_report') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="study-points-toast" id="studyPointsToast"></div>

<script>
window.MathJax = {
    tex: {
        inlineMath: [['$', '$'], ['\\(', '\\)']],
        packages: {'[+]': ['mhchem']}
    },
    loader: {
        load: ['[tex]/mhchem']
    },
    svg: {
        fontCache: 'global'
    }
};

const studyCardCsrf = @json(csrf_token());
const trackedViews = new WeakSet();
let activeStudyReportCard = null;

function showStudyToast(message) {
    const toast = document.getElementById('studyPointsToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(window.studyPointsToastTimer);
    window.studyPointsToastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2200);
}

function updateStudyStats(data) {
    if (!data) return;
    const totalPoints = document.getElementById('studyTotalPoints');
    const sideTotalPoints = document.getElementById('studySideTotalPoints');
    const cardsStudied = document.getElementById('studyCardsStudied');
    const correctAnswers = document.getElementById('studyCorrectAnswers');
    if (totalPoints && typeof data.total_points !== 'undefined') totalPoints.textContent = data.total_points;
    if (sideTotalPoints && typeof data.total_points !== 'undefined') sideTotalPoints.textContent = data.total_points;
    if (cardsStudied && typeof data.cards_studied !== 'undefined') cardsStudied.textContent = data.cards_studied;
    if (correctAnswers && typeof data.correct_answers !== 'undefined') correctAnswers.textContent = data.correct_answers;

    if (Number(data.points_awarded || 0) > 0) {
        showStudyToast('+' + data.points_awarded + ' study point' + (Number(data.points_awarded) === 1 ? '' : 's'));
    }
}

function trackStudyCard(card, payload) {
    const url = card?.dataset?.trackUrl;
    if (!url) return Promise.resolve();

    const requestPayload = {...payload};
    if (card?.dataset?.questionId) {
        requestPayload.question_id = card.dataset.questionId;
    }

    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': studyCardCsrf,
        },
        body: JSON.stringify(requestPayload),
    })
        .then((response) => response.ok ? response.json() : null)
        .then(updateStudyStats)
        .catch(() => null);
}

const studyCardObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting || trackedViews.has(entry.target)) return;
            trackedViews.add(entry.target);
            trackStudyCard(entry.target, {event: 'view'});
        });
    }, {threshold: 0.45})
    : null;

document.querySelectorAll('.study-card[data-track-url]').forEach((card) => {
    if (studyCardObserver) {
        studyCardObserver.observe(card);
    } else {
        trackStudyCard(card, {event: 'view'});
    }
});

const studyCardReportForm = document.getElementById('studyCardReportForm');
if (studyCardReportForm) {
    studyCardReportForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!activeStudyReportCard?.dataset?.reportUrl) return;

        const submitButton = studyCardReportForm.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        fetch(activeStudyReportCard.dataset.reportUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': studyCardCsrf,
            },
            body: new FormData(studyCardReportForm),
        })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => {
                studyCardReportForm.reset();
                const modal = document.getElementById('studyCardReportModal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
                showStudyToast(data.message || 'Study card report submitted.');
            })
            .catch(() => showStudyToast('Unable to submit report. Please try again.'))
            .finally(() => {
                if (submitButton) submitButton.disabled = false;
            });
    });
}

document.addEventListener('click', function (event) {
    const studyToggle = event.target.closest('.js-student-study-toggle');
    if (studyToggle) {
        const detail = document.getElementById(studyToggle.dataset.target || '');
        if (!detail) return;
        const isOpen = detail.classList.toggle('is-open');
        studyToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        studyToggle.innerHTML = isOpen
            ? '<i class="mdi mdi-eye-off-outline me-1"></i> Hide'
            : '<i class="mdi mdi-book-open-page-variant-outline me-1"></i> Read';
        if (isOpen && window.MathJax && MathJax.typesetPromise) {
            MathJax.typesetPromise([detail]);
        }
        return;
    }

    const reportButton = event.target.closest('.js-study-report');
    if (reportButton) {
        activeStudyReportCard = reportButton.closest('.study-card');
        const title = document.getElementById('studyCardReportTitle');
        if (title) {
            title.textContent = 'Tell us what needs correction in ' + (reportButton.dataset.cardTitle || 'this study card') + '.';
        }
        const modal = document.getElementById('studyCardReportModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
        return;
    }

    const option = event.target.closest('.study-card-option');
    if (option) {
        const card = option.closest('.study-card');
        card.querySelectorAll('.study-card-option').forEach((item) => {
            if (item.dataset.correct === '1') item.classList.add('is-correct');
            item.disabled = true;
        });
        if (option.dataset.correct !== '1') option.classList.add('is-wrong');
        const button = card.querySelector('.js-show-answer');
        card.dataset.answered = '1';
        card.classList.remove('is-answer-needed');
        if (button) {
            button.classList.remove('is-disabled');
            button.removeAttribute('aria-disabled');
            button.innerHTML = '<i class="mdi mdi-rotate-3d-variant me-1"></i> Show Answer';
        }
        trackStudyCard(card, {event: 'answer', correct: option.dataset.correct === '1'});
        return;
    }

    const card = event.target.closest('.study-card');
    if (!card) return;

    const interactive = event.target.closest('button, a, input, select, textarea, form');
    const button = event.target.closest('.js-show-answer') || (!interactive ? card.querySelector('.js-show-answer') : null);
    if (!button) return;

    const back = card.querySelector('.study-card-back');
    if (!back) return;

    if (card.dataset.requiresAnswer === '1' && card.dataset.answered !== '1') {
        card.classList.add('is-answer-needed');
        button.classList.add('is-disabled');
        button.setAttribute('aria-disabled', 'true');
        button.innerHTML = '<i class="mdi mdi-check-circle-outline me-1"></i> Choose an option first';
        return;
    }

    back.classList.toggle('is-visible');
    card.classList.toggle('is-flipped', back.classList.contains('is-visible'));
    button.innerHTML = back.classList.contains('is-visible')
        ? '<i class="mdi mdi-eye-off-outline me-1"></i> Hide Answer'
        : '<i class="mdi mdi-rotate-3d-variant me-1"></i> Show Answer';

    if (back.classList.contains('is-visible') && window.MathJax && MathJax.typesetPromise) {
        MathJax.typesetPromise([back]);
    }
});
</script>
<script id="flashcard-mathjax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-svg.js"></script>
@endsection
