@extends('website.layouts.app')

@section('title', ($seo['title'] ?? 'Study Cards'))

@section('content')
@php
    $studentLoggedIn = auth('student')->check();
    $loginUrl = route('student.signin', ['redirect' => url()->current()]);
    $totalSets = $package->flashcardSets->count();
    $totalCards = $package->flashcardSets->sum(fn ($set) => $set->cards->count());
    $firstSet = $package->flashcardSets->first();
    $heroTitle = $totalSets === 1 && $firstSet ? $firstSet->title : 'Study Cards';
    $visibleCardLimit = 20;
    $globalCardNumber = 0;
    $studyHierarchy = $studyHierarchy ?? collect();
    $studyIndexHierarchy = $studyIndexHierarchy ?? $studyHierarchy;
    $studyFilter = $studyFilter ?? ['section' => null, 'topic' => null, 'subtopic' => null];
    $hasStudyFilter = $hasStudyFilter ?? false;
    $activeStudyTitle = $activeStudyTitle ?? null;
    $studyBaseUrl = route('website.flashcards.show', $package->slug ?: $package->id);
@endphp

<style>
    .guest-flashcards-wrap { background:var(--theme-body-bg, #f8fafc); padding:0 0 46px; }
    .guest-flashcards-panel,
    .guest-flashcards-side-card,
    .guest-flashcard { background:#fff; border:1px solid var(--theme-border, #d6e4e2); border-radius:16px; box-shadow:0 14px 35px rgba(15,23,42,.06); }
    .guest-flashcards-hero { margin:0 0 28px; padding:34px 0; background:color-mix(in srgb, var(--theme-primary, #0f766e) 6%, var(--theme-body-bg, #ffffff)); border-bottom:1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0); box-shadow:0 16px 36px rgba(15,23,42,.04); }
    .guest-flashcards-hero-grid { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:22px; align-items:center; }
    .guest-flashcards-eyebrow { display:inline-flex; align-items:center; gap:8px; margin-bottom:10px; padding:7px 12px; border-radius:999px; background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); font-size:13px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
    .guest-flashcards-hero h1 { color:var(--theme-heading, #0f172a); font-size:clamp(24px, 4vw, 38px); line-height:1.15; font-weight:900; margin:0 0 8px; }
    .guest-flashcards-hero p { color:var(--theme-text, #64748b); margin:0; font-size:16px; }
    .guest-flashcards-hero-meta { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
    .guest-flashcards-hero-meta span { display:inline-flex; align-items:center; gap:7px; padding:8px 12px; border-radius:999px; background:#fff; border:1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 16%, #ffffff); color:var(--theme-primary, #0f766e); font-size:13px; font-weight:850; }
    .guest-flashcards-grid { display:grid; grid-template-columns:minmax(0, 1fr) 340px; gap:18px; align-items:start; }
    .guest-flashcards-panel { overflow:hidden; }
    .guest-flashcards-set-header { padding:16px 20px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #fff); border-bottom:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcards-set-header h2 { font-size:21px; color:var(--theme-heading, #0f172a); font-weight:850; margin:0; }
    .guest-flashcards-set-header p { margin:4px 0 0; color:var(--theme-text, #64748b); }
    .guest-flashcards-card-list { display:grid; }
    .guest-flashcards-list-head,
    .guest-flashcards-card-row { display:grid; grid-template-columns:64px minmax(0, 1fr) 160px 138px; gap:16px; align-items:center; }
    .guest-flashcards-list-head { padding:14px 20px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #fff); color:var(--theme-heading, #0f172a); font-weight:900; font-size:13px; text-transform:uppercase; border-bottom:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcards-card-row { padding:16px 20px; background:#fff; border-bottom:1px solid color-mix(in srgb, var(--theme-border, #d6e4e2) 78%, #fff); }
    .guest-flashcards-card-row:nth-child(even) { background:color-mix(in srgb, var(--theme-primary, #0f766e) 3%, #fff); }
    .guest-flashcards-row-no { display:inline-block; color:var(--theme-primary, #0f766e); font-size:18px; font-weight:900; line-height:1; }
    .guest-flashcards-card-title { color:var(--theme-heading, #0f172a); font-size:17px; font-weight:900; line-height:1.35; margin:0; }
    .guest-flashcards-card-summary { margin:5px 0 0; color:var(--theme-text, #64748b); font-size:13px; line-height:1.45; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .guest-flashcards-card-chip { display:inline-flex; align-items:center; justify-content:center; width:max-content; max-width:100%; padding:7px 12px; border-radius:999px; background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); font-size:13px; font-weight:850; }
    .guest-flashcards-card-action { justify-self:end; min-width:110px; }
    .guest-flashcard-detail { display:none; }
    .guest-flashcard-detail.is-open { display:block; }
    .guest-flashcard { box-shadow:0 12px 28px rgba(15,23,42,.06); margin:18px; padding:0; overflow:hidden; border-left:5px solid var(--theme-primary, #0f766e); }
    .guest-flashcard-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0; padding:14px 16px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 7%, #fff); border-bottom:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcard-body { padding:16px; display:grid; gap:14px; }
    .guest-flashcard-number { width:42px; height:42px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:var(--theme-primary, #0f766e); color:#fff; font-weight:900; flex:0 0 auto; }
    .guest-flashcard-meta { display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); font-weight:800; font-size:13px; }
    .guest-flashcard-front,
    .guest-flashcard-question { color:var(--theme-heading, #0f172a); font-size:16px; line-height:1.65; }
    .guest-flashcard-front { padding:14px; border-radius:12px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 5%, #fff); border:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcard-question { font-weight:700; }
    .guest-flashcard-options { display:grid; gap:10px; }
    .guest-flashcard-option { width:100%; text-align:left; padding:12px 14px; border:1px solid var(--theme-border, #d6e4e2); border-radius:10px; background:#fff; color:var(--theme-heading, #0f172a); font-weight:700; transition:.18s ease; }
    .guest-flashcard-option:hover { border-color:var(--theme-primary, #0f766e); background:var(--theme-primary-soft, #e6f3f1); }
    .guest-flashcard-option.is-correct { border-color:#16a34a; background:#dcfce7; color:#166534; }
    .guest-flashcard-option.is-wrong { border-color:#ef4444; background:#fee2e2; color:#991b1b; }
    .guest-flashcard-back { display:none; margin-top:16px; padding:16px; border-radius:14px; border:1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #fff); background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-heading, #0f172a); }
    .guest-flashcard-back.is-visible { display:block; }
    .guest-flashcard-back strong { color:var(--theme-primary, #0f766e); }
    .guest-flashcard-login-prompt { display:none; margin-top:14px; padding:14px; border:1px solid color-mix(in srgb, var(--theme-secondary, #f59e0b) 35%, #fff); border-radius:12px; background:color-mix(in srgb, var(--theme-secondary, #f59e0b) 10%, #fff); color:var(--theme-heading, #0f172a); }
    .guest-flashcard-login-prompt.is-visible { display:block; }
    .guest-flashcards-cta,
    .guest-flashcards-outline { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:11px 20px; font-weight:800; text-decoration:none; border:1px solid var(--theme-primary, #0f766e); }
    .guest-flashcards-cta { background:var(--theme-primary, #0f766e); color:#fff; }
    .guest-flashcards-cta:hover { color:#fff; background:color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000); }
    .guest-flashcards-cta:active,
    .guest-flashcards-cta:focus,
    .guest-flashcards-cta.active { color:#fff !important; background:color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000) !important; border-color:color-mix(in srgb, var(--theme-primary, #0f766e) 88%, #000) !important; -webkit-text-fill-color:#fff; outline:none; }
    .guest-flashcards-outline { background:#fff; color:var(--theme-primary, #0f766e); }
    .guest-flashcards-outline:hover { background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); }
    .guest-flashcards-outline:active,
    .guest-flashcards-outline:focus,
    .guest-flashcards-outline.active { background:var(--theme-primary-soft, #e6f3f1) !important; color:var(--theme-primary, #0f766e) !important; border-color:var(--theme-primary, #0f766e) !important; -webkit-text-fill-color:var(--theme-primary, #0f766e); outline:none; }
    .guest-flashcards-secondary { background:var(--theme-secondary, #f59e0b); border-color:var(--theme-secondary, #f59e0b); color:var(--theme-button-text, #fff); }
    .guest-flashcards-secondary:hover,
    .guest-flashcards-secondary:active,
    .guest-flashcards-secondary:focus { background:color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000) !important; border-color:color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000) !important; color:var(--theme-button-text, #fff) !important; -webkit-text-fill-color:var(--theme-button-text, #fff); outline:none; }
    .guest-flashcard-report { min-width:auto; padding:8px 12px; font-size:13px; }
    .guest-flashcard-report-modal .modal-content { border-radius:16px; border:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcard-report-modal .modal-header { background:var(--theme-primary-soft, #e6f3f1); border-bottom:1px solid var(--theme-border, #d6e4e2); }
    .guest-flashcard-report-modal .modal-title { color:var(--theme-heading, #0f172a); font-weight:900; }
    .guest-flashcards-toast { position:fixed; right:20px; bottom:20px; z-index:1090; display:none; padding:10px 14px; border-radius:999px; background:var(--theme-primary, #0f766e); color:#fff; font-weight:850; box-shadow:0 14px 34px rgba(15,23,42,.18); }
    .guest-flashcards-toast.is-visible { display:block; }
    .guest-flashcards-side { position:static; min-width:0; max-width:100%; display:grid; gap:16px; }
    .guest-flashcards-side-card { padding:20px; }
    .guest-flashcards-side-icon { width:42px; height:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; background:var(--theme-primary, #0f766e); color:#fff; font-size:20px; line-height:1; margin-bottom:12px; }
    .guest-flashcards-side h2 { font-size:22px; line-height:1.25; font-weight:900; color:var(--theme-heading, #0f172a); margin:0 0 16px; }
    .guest-flashcards-side-list { display:grid; gap:12px; margin-bottom:18px; }
    .guest-flashcards-side-item { display:flex; gap:12px; align-items:center; color:var(--theme-text, #64748b); font-weight:700; }
    .guest-flashcards-side-item i { width:42px; height:42px; min-width:42px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; color:var(--theme-primary, #0f766e); background:var(--theme-primary-soft, #e6f3f1); font-size:20px; line-height:1; flex:0 0 42px; }
    .guest-flashcards-leaders { margin:0; }
    .guest-flashcards-side-card:not(.guest-flashcards-leader-card) .guest-flashcards-leaders { display:none; }
    .guest-flashcards-leaders h3 { font-size:17px; font-weight:900; color:var(--theme-heading, #0f172a); margin:0 0 10px; }
    .guest-flashcards-leader { display:grid; grid-template-columns:34px minmax(0, 1fr) auto; gap:10px; align-items:center; padding:9px 0; border-top:1px solid color-mix(in srgb, var(--theme-border, #d6e4e2) 70%, #fff); }
    .guest-flashcards-leader:first-of-type { border-top:0; }
    .guest-flashcards-leader-rank { width:30px; height:30px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); font-weight:900; }
    .guest-flashcards-leader-rank.rank-1 { background:var(--theme-primary, #0f766e); color:#fff; }
    .guest-flashcards-leader-rank.rank-2 { background:var(--theme-secondary, #f59e0b); color:var(--theme-button-text, #fff); }
    .guest-flashcards-leader-rank.rank-3 { background:var(--theme-tertiary, #38bdf8); color:#fff; }
    .guest-flashcards-leader-name { font-weight:850; color:var(--theme-heading, #0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .guest-flashcards-leader-meta { color:var(--theme-text, #64748b); font-size:12px; }
    .guest-flashcards-leader-points { color:var(--theme-primary, #0f766e); font-weight:900; }
    .guest-flashcards-empty { padding:28px; color:var(--theme-text, #64748b); }
    .guest-study-section { overflow:hidden; }
    .guest-study-section-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; padding:20px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #fff); border-bottom:1px solid var(--theme-border, #d6e4e2); }
    .guest-study-section-head h2 { margin:0; font-size:24px; line-height:1.25; font-weight:900; color:var(--theme-heading, #0f172a); }
    .guest-study-section-head p { margin:5px 0 0; color:var(--theme-text, #64748b); }
    .guest-study-section-count { display:inline-flex; align-items:center; gap:8px; flex:0 0 auto; padding:8px 12px; border-radius:999px; background:var(--theme-primary, #0f766e); color:#fff; font-weight:900; }
    .guest-study-topic { padding:16px 20px 20px; }
    .guest-study-topic + .guest-study-topic { border-top:1px solid var(--theme-border, #d6e4e2); }
    .guest-study-topic-title { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; color:var(--theme-heading, #0f172a); font-weight:900; }
    .guest-study-topic-title span { color:var(--theme-primary, #0f766e); font-size:13px; font-weight:850; }
    .guest-study-subtopic { border:1px solid var(--theme-border, #d6e4e2); border-radius:16px; overflow:hidden; background:#fff; margin-top:12px; }
    .guest-study-subtopic-head { display:flex; justify-content:space-between; gap:12px; padding:12px 16px; background:var(--theme-body-bg, #f8fafc); color:var(--theme-heading, #0f172a); font-weight:850; }
    .guest-study-subtopic-head span { color:var(--theme-text, #64748b); font-size:13px; }
    .guest-study-set { border-top:1px solid var(--theme-border, #d6e4e2); }
    .guest-study-set:first-of-type { border-top:0; }
    .guest-study-set .guest-flashcards-set-header { background:#fff; }
    .guest-flashcard.is-card-hidden,
    .guest-flashcards-card-row.is-card-hidden { display:none; }
    .guest-flashcards-load { display:flex; justify-content:center; padding:0 16px 22px; }
    .guest-flashcards-load .guest-flashcards-cta { min-width:220px; }
    .guest-study-index-panel { background:#fff; border:1px solid var(--theme-border, #d6e4e2); border-radius:16px; box-shadow:0 14px 35px rgba(15,23,42,.06); margin-bottom:18px; overflow:hidden; }
    .guest-study-index-head { padding:16px 20px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #fff); border-bottom:1px solid var(--theme-border, #d6e4e2); display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .guest-study-index-head h2 { margin:0; color:var(--theme-heading, #0f172a); font-size:22px; font-weight:900; }
    .guest-study-index-head p { margin:4px 0 0; color:var(--theme-text, #64748b); }
    .guest-study-index-active { display:inline-flex; align-items:center; gap:7px; padding:8px 12px; border-radius:999px; background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); font-weight:850; font-size:13px; }
    .guest-study-index { display:grid; gap:12px; padding:16px 20px; }
    .guest-study-index-section { border:1px solid var(--theme-border, #d6e4e2); border-radius:14px; padding:14px; background:#fff; }
    .guest-study-index-link { display:flex; align-items:center; justify-content:space-between; gap:12px; color:var(--theme-heading, #0f172a); text-decoration:none; font-weight:900; }
    .guest-study-index-link:hover { color:var(--theme-primary, #0f766e); }
    .guest-study-index-count { color:var(--theme-primary, #0f766e); background:var(--theme-primary-soft, #e6f3f1); border-radius:999px; padding:5px 10px; font-size:12px; font-weight:900; white-space:nowrap; }
    .guest-study-index-topic-list { display:grid; gap:8px; margin-top:12px; }
    .guest-study-index-topic { padding:10px 12px; border-radius:12px; background:color-mix(in srgb, var(--theme-primary, #0f766e) 4%, #fff); }
    .guest-study-index-subtopics { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .guest-study-index-chip { display:inline-flex; align-items:center; gap:6px; padding:7px 10px; border-radius:999px; border:1px solid var(--theme-border, #d6e4e2); color:var(--theme-primary, #0f766e); text-decoration:none; font-size:13px; font-weight:850; background:#fff; }
    .guest-study-index-chip:hover { background:var(--theme-primary-soft, #e6f3f1); color:var(--theme-primary, #0f766e); }
    @media (max-width: 991px) {
        .guest-flashcards-grid { grid-template-columns:1fr; }
        .guest-flashcards-side { position:static; order:-1; }
    }
    @media (max-width: 767px) {
        .guest-flashcards-hero { padding:24px 0; }
        .guest-flashcards-hero-grid { grid-template-columns:1fr; }
        .guest-flashcard { margin:12px; }
        .guest-flashcard-head { align-items:flex-start; }
        .guest-flashcards-side-card { padding:16px; }
        .guest-flashcards-list-head { display:none; }
        .guest-flashcards-card-row { grid-template-columns:44px minmax(0, 1fr); gap:10px; padding:15px; }
        .guest-flashcards-card-row > :nth-child(3) { grid-column:2; }
        .guest-flashcards-card-action { grid-column:1 / -1; justify-self:stretch; width:100%; }
    }
</style>

<section class="guest-flashcards-wrap">
    <div class="guest-flashcards-hero">
        <div class="container guest-flashcards-hero-grid">
            <div>
                <span class="guest-flashcards-eyebrow"><i class="ri-stack-line"></i> {{ __('ui.study_cards') }}</span>
                <h1>{{ $heroTitle }}</h1>
                <p>{{ $package->name }} study cards with key points, linked questions, and explanations.</p>
                <div class="guest-flashcards-hero-meta">
                    <span><i class="ri-file-list-3-line"></i> {{ $totalCards }} study cards</span>
                    <span><i class="ri-folder-2-line"></i> {{ $totalSets }} study set{{ $totalSets === 1 ? '' : 's' }}</span>
                    <span><i class="ri-book-open-line"></i> {{ __('ui.linked_course') }}</span>
                </div>
            </div>
            <a href="{{ route('courses.detail', $package->slug ?: $package->id) }}" class="guest-flashcards-cta guest-flashcards-secondary">
                <i class="ri-arrow-left-line"></i> Back to Exam
            </a>
        </div>
    </div>

    <div class="container">
        <div class="guest-flashcards-grid">
            <main>
                <div class="guest-study-index-panel">
                    <div class="guest-study-index-head">
                        <div>
                            <h2>{{ __('ui.choose_study_cards') }}</h2>
                            <p>{{ __('ui.choose_study_copy') }}</p>
                        </div>
                        @if($activeStudyTitle)
                            <span class="guest-study-index-active"><i class="ri-filter-3-line"></i>{{ $activeStudyTitle }}</span>
                        @endif
                    </div>
                    @if($studyIndexHierarchy->isEmpty())
                        <div class="p-4 text-muted">{{ __('ui.no_cards_course') }}</div>
                    @else
                        <div class="guest-study-index">
                            @foreach($studyIndexHierarchy as $indexSection)
                                @php $sectionUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key']]); @endphp
                                <div class="guest-study-index-section">
                                    <a class="guest-study-index-link" href="{{ $sectionUrl }}">
                                        <span>{{ $indexSection['label'] }}</span>
                                        <span class="guest-study-index-count">{{ $indexSection['cards_count'] }} cards</span>
                                    </a>
                                    <div class="guest-study-index-topic-list">
                                        @foreach($indexSection['topics'] as $indexTopic)
                                            @php $topicUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key'], 'study_topic' => $indexTopic['key']]); @endphp
                                            <div class="guest-study-index-topic">
                                                <a class="guest-study-index-link" href="{{ $topicUrl }}">
                                                    <span>{{ $indexTopic['label'] }}</span>
                                                    <span class="guest-study-index-count">{{ $indexTopic['cards_count'] }} cards</span>
                                                </a>
                                                <div class="guest-study-index-subtopics">
                                                    @foreach($indexTopic['subtopics'] as $indexSubtopic)
                                                        @php $subtopicUrl = $studyBaseUrl . '?' . http_build_query(['study_section' => $indexSection['key'], 'study_topic' => $indexTopic['key'], 'study_subtopic' => $indexSubtopic['key']]); @endphp
                                                        <a class="guest-study-index-chip" href="{{ $subtopicUrl }}">{{ $indexSubtopic['label'] }} <strong>{{ $indexSubtopic['cards_count'] }}</strong></a>
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
                    <div class="guest-flashcards-panel guest-flashcards-empty">{{ __('ui.choose_study_path') }}</div>
                @endif

                <?php foreach ($studyHierarchy as $section): ?>
                    <section class="guest-flashcards-panel guest-study-section mb-3">
                        <div class="guest-study-section-head">
                            <div>
                                <h2><?= e($section['label']) ?></h2>
                                <p>{{ __('ui.related_study_cards') }}</p>
                            </div>
                            <span class="guest-study-section-count">
                                <i class="ri-stack-line"></i>
                                <?= e(trans_choice('ui.card_count_choice', $section['cards_count'], ['count' => $section['cards_count']])) ?>
                            </span>
                        </div>

                        <?php foreach ($section['topics'] as $topic): ?>
                            <div class="guest-study-topic">
                                <div class="guest-study-topic-title">
                                    <strong><?= e($topic['label']) ?></strong>
                                    <span><?= e(trans_choice('ui.card_count_choice', $topic['cards_count'], ['count' => $topic['cards_count']])) ?></span>
                                </div>

                                <?php foreach ($topic['subtopics'] as $subtopic): ?>
                                    <div class="guest-study-subtopic">
                                        <div class="guest-study-subtopic-head">
                                            <strong><?= e($subtopic['label']) ?></strong>
                                            <span><?= e(trans_choice('ui.set_count_choice', $subtopic['sets_count'], ['count' => $subtopic['sets_count']])) ?></span>
                                        </div>

                                        <?php foreach ($subtopic['sets'] as $set): ?>
                                            <div class="guest-study-set">
                                                <div class="guest-flashcards-set-header">
                                                    <h2><?= e($set->title) ?></h2>
                                                    <p><?= e(trans_choice('ui.study_card_count_choice', $set->cards->count(), ['count' => $set->cards->count()])) ?></p>
                                                </div>

                                                <?php if ($set->cards->isNotEmpty()): ?>
                                                    <div class="guest-flashcards-list-head">
                                                        <span>No</span>
                                                        <span>{{ __('ui.study_card') }}</span>
                                                        <span>{{ __('ui.questions') }}</span>
                                                        <span>{{ __('ui.action') }}</span>
                                                    </div>
                                                    <div class="guest-flashcards-card-list">
                                                <?php endif; ?>

                                                <?php foreach ($set->cards as $card): ?>
                            <?php
                                $globalCardNumber++;
                                $totalQuestionCount = (int) ($card->display_question_pool_count ?? 0);
                                if ($totalQuestionCount < 1) {
                                    $totalQuestionCount = $card->sourceQuestions->isNotEmpty()
                                        ? $card->sourceQuestions->count()
                                        : ($card->sourceQuestion ? 1 : 0);
                                }
                                $firstQuestion = $card->getRelationValue('displayQuestion') ?: $card->sourceQuestions->first() ?: $card->sourceQuestion;
                                $linkedQuestions = collect([$firstQuestion])->filter();
                                $cardTypeLabel = $firstQuestion
                                    ? ($firstQuestion->qtype?->question_type ?: 'Question')
                                    : 'Study Card';
                                $hasCardFront = trim(strip_tags((string) $card->front)) !== '';
                                $hasCardBack = trim(strip_tags((string) $card->back)) !== '';
                                $hasCardExplanation = trim(strip_tags((string) $card->explanation)) !== '';
                                $cardHiddenClass = $globalCardNumber > $visibleCardLimit ? ' is-card-hidden' : '';
                                $cardDetailId = 'study-card-detail-' . $card->id;
                                $plainFront = trim(preg_replace('/\s+/', ' ', strip_tags((string) $card->front)));
                                $plainQuestion = $firstQuestion ? trim(preg_replace('/\s+/', ' ', strip_tags((string) $firstQuestion->question))) : '';
                                $setTitle = trim((string) $set->title);
                                $cardTitle = trim((string) ($card->title ?? ''));
                                $cardDisplayTitle = $cardTitle !== ''
                                    ? $cardTitle
                                    : ($setTitle !== '' ? $setTitle . ' ' . $globalCardNumber : 'Study Card ' . $globalCardNumber);
                                $cardSummary = $plainFront ?: ($plainQuestion ?: 'Open this card to read the concept and answer linked questions.');
                                $questionCount = $totalQuestionCount;
                            ?>
                            <div class="guest-flashcards-card-row{{ $cardHiddenClass }}" data-study-card-row data-card-index="<?= e($globalCardNumber) ?>">
                                <span class="guest-flashcards-row-no"><?= e($globalCardNumber) ?></span>
                                <div>
                                    <h3 class="guest-flashcards-card-title"><?= e($cardDisplayTitle) ?></h3>
                                    <p class="guest-flashcards-card-summary"><?= e($cardSummary) ?></p>
                                </div>
                                <span class="guest-flashcards-card-chip">
                                    <?= e(trans_choice('ui.question_count_choice', $questionCount, ['count' => $questionCount])) ?>
                                </span>
                                <button type="button" class="guest-flashcards-cta guest-flashcards-card-action js-study-card-toggle" data-target="<?= e($cardDetailId) ?>" aria-expanded="false">
                                    <i class="ri-book-open-line"></i> Read
                                </button>
                            </div>

                            <article id="<?= e($cardDetailId) ?>" class="guest-flashcard guest-flashcard-detail{{ $cardHiddenClass }}" data-card-index="<?= e($globalCardNumber) ?>" data-study-card data-question-id="{{ $firstQuestion?->id }}" data-report-url="{{ route('website.flashcards.report', $card) }}" data-track-url="{{ route('website.flashcards.track', $card) }}">
                                <div class="guest-flashcard-head">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="guest-flashcard-number"><?= e($globalCardNumber) ?></span>
                                        <span class="guest-flashcard-meta"><?= e($cardTypeLabel) ?> &middot; <?= e($card->difficulty ?: __('ui.medium')) ?></span>
                                    </div>
                                    <button type="button" class="guest-flashcards-secondary guest-flashcard-report js-guest-report-card" data-card-title="{{ $set->title }} card {{ $globalCardNumber }}">
                                        <i class="ri-flag-line"></i> Report
                                    </button>
                                </div>

                                <div class="guest-flashcard-body">
                                <?php if ($hasCardFront): ?>
                                    <div class="guest-flashcard-front"><?= $card->front ?></div>
                                <?php endif; ?>

                                <?php if ($linkedQuestions->isEmpty()): ?>
                                    <button type="button" class="guest-flashcards-outline mt-3 js-guest-show-answer">{{ __('ui.show_explanation') }}</button>
                                <?php endif; ?>

                                <?php foreach ($linkedQuestions as $question): ?>
                                    <?php
                                        $questionType = strtoupper(trim((string) ($question?->qtype?->type ?? '')));
                                        $questionTypeLabel = strtolower((string) ($question?->qtype?->question_type ?? ''));
                                        $isTrueFalse = $question && ($questionType === 'T' || $questionType === 'TF' || str_contains($questionTypeLabel, 'true') || str_contains($questionTypeLabel, 'false'));
                                        $isFillBlank = $question && ($questionType === 'F' || $questionType === 'FB' || str_contains($questionTypeLabel, 'fill'));
                                        $questionOptions = collect();
                                        $correctOptions = collect();

                                        if ($isTrueFalse) {
                                            $questionOptions = collect(['True', 'False']);
                                            $correctOptions = collect([mb_strtolower(trim(strip_tags((string) $question->true_false)))])->filter()->values();
                                        } elseif (! $isFillBlank) {
                                            $questionOptions = collect(range(1, 6))
                                                ->map(fn ($index) => trim((string) $question->{'option' . $index}))
                                                ->filter()
                                                ->values();
                                            $correctOptions = collect($question->correctOptionValues())
                            ->map(fn ($answer) => mb_strtolower(trim(strip_tags((string) $answer))))->values();
                                        }
                                    ?>

                                    <div class="guest-flashcard-question"><?= $question->question ?></div>

                                    <?php if ($questionOptions->isNotEmpty()): ?>
                                        <div class="guest-flashcard-options">
                                            <?php foreach ($questionOptions as $option): ?>
                                                <?php $normalized = mb_strtolower(trim(strip_tags((string) $option))); ?>
                                                <button type="button" class="guest-flashcard-option" data-correct="<?= $correctOptions->contains($normalized) ? '1' : '0' ?>">
                                                    <?= $option ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="guest-flashcards-outline mt-3 js-guest-show-answer">{{ __('ui.show_explanation') }}</button>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <div class="guest-flashcard-login-prompt">
                                    <strong>{{ __('ui.login_required_explanation') }}</strong>
                                    <div class="mt-1">{{ __('ui.login_unlock_cards') }}</div>
                                    <a href="<?= e($loginUrl) ?>" class="guest-flashcards-cta mt-3">{{ __('ui.login_study_all') }}</a>
                                </div>

                                <div class="guest-flashcard-back">
                                    <?php if ($hasCardBack): ?>
                                        <strong>{{ __('ui.card_explanation') }}</strong>
                                        <div class="mt-2 mb-3"><?= $card->back ?></div>
                                    <?php endif; ?>

                                    @if(($card->checks ?? collect())->isNotEmpty())
                                        <div class="mb-3"><strong>Knowledge Checks ({{ $card->checks->count() }})</strong></div>
                                        @foreach($card->checks as $check)
                                            <details class="border rounded p-2 mb-2">
                                                <summary class="fw-bold">{{ $check->difficulty ?: 'Practice' }}: {{ $check->question }}</summary>
                                                @if($check->options)<div class="mt-2">@foreach($check->options as $option)<div>&bull; {{ $option }}</div>@endforeach</div>@endif
                                                @if($check->correct_answer)<div class="mt-2"><strong>Answer:</strong> {{ $check->correct_answer }}</div>@endif
                                                @if($check->explanation)<div class="mt-2"><strong>Explanation:</strong> {{ $check->explanation }}</div>@endif
                                            </details>
                                        @endforeach
                                    @endif
                                    <?php foreach ($linkedQuestions as $question): ?>
                                        <?php
                                            $questionType = strtoupper(trim((string) ($question->qtype->type ?? '')));
                                            $questionTypeLabel = strtolower((string) ($question->qtype->question_type ?? ''));
                                            $isTrueFalseAnswer = $questionType === 'T' || $questionType === 'TF' || str_contains($questionTypeLabel, 'true') || str_contains($questionTypeLabel, 'false');
                                            $isFillBlankAnswer = $questionType === 'F' || $questionType === 'FB' || str_contains($questionTypeLabel, 'fill');
                                            $answerHtml = collect($question->correctOptionValues())
                                                ->map(fn ($answer) => trim((string) $answer))
                                                ->filter()
                                                ->implode('<br>');

                                            if ($isTrueFalseAnswer) {
                                                $answerHtml = ucfirst(strtolower((string) $question->true_false));
                                            }

                                            if ($isFillBlankAnswer) {
                                                $answerHtml = (string) $question->fill_blank;
                                            }
                                        ?>

                                        <div class="mb-3">
                                            <strong>{{ __('ui.correct_answer') }}</strong>
                                            <div class="mt-2"><?= $answerHtml ?: '-' ?></div>

                                            <?php if (!empty($question->explanation)): ?>
                                                <strong class="d-block mt-3">{{ __('ui.explanation') }}</strong>
                                                <div class="mt-2"><?= $question->explanation ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($hasCardExplanation): ?>
                                        <strong class="d-block mt-3">{{ __('ui.extra_notes') }}</strong>
                                        <div class="mt-2"><?= $card->explanation ?></div>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </article>
                                                <?php endforeach; ?>

                                                <?php if ($set->cards->isNotEmpty()): ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                @if($hasStudyFilter && $studyHierarchy->isEmpty())
                    <div class="guest-flashcards-panel guest-flashcards-empty">{{ __('ui.no_active_cards_selection') }}</div>
                @endif

                <?php if ($hasStudyFilter && $totalCards > $visibleCardLimit): ?>
                    <div class="guest-flashcards-load">
                        <button type="button" class="guest-flashcards-cta guest-flashcards-secondary js-load-study-cards" data-step="20">
                            Load 20 More
                        </button>
                    </div>
                <?php endif; ?>
            </main>

            <aside class="guest-flashcards-side">
                @if(isset($learningLeaders))
                    <div class="guest-flashcards-side-card guest-flashcards-leader-card" data-leaderboard-panel="public-flashcard">
                        <div class="guest-flashcards-leaders">
                            <h3>{{ __('ui.top_study_learners') }}</h3>
                            @include('components.leaderboard-period-toggle', [
                                'leaderboardPanelKey' => 'public-flashcard',
                                'leaderboardApiUrl' => route('website.flashcards.leaderboard', $package->slug ?: $package->id),
                            ])
                            <div data-leaderboard-rows>
                                @include('website.partials.flashcard_leaderboard_rows', ['leaders' => $learningLeaders])
                            </div>
                        </div>
                    </div>
                @endif

                <div class="guest-flashcards-side-card">
                <span class="guest-flashcards-side-icon"><i class="ri-stack-line"></i></span>
                <h2>{{ $package->name }}</h2>
                <div class="guest-flashcards-side-list">
                    <div class="guest-flashcards-side-item"><i class="ri-file-list-3-line"></i><span>{{ $totalCards }} study cards included</span></div>
                    <div class="guest-flashcards-side-item"><i class="ri-folder-2-line"></i><span>{{ $totalSets }} study set{{ $totalSets === 1 ? '' : 's' }}</span></div>
                    <div class="guest-flashcards-side-item"><i class="ri-book-open-line"></i><span>{{ __('ui.free_preview') }}</span></div>
                </div>
                @if($studentLoggedIn)
                    <a href="{{ route('student.dashboard') }}" class="guest-flashcards-cta w-100">{{ __('ui.go_dashboard') }}</a>
                @else
                    <a href="{{ $loginUrl }}" class="guest-flashcards-cta w-100">{{ __('ui.login_study_all') }}</a>
                @endif
                </div>
            </aside>
        </div>
    </div>
</section>

<div class="modal fade guest-flashcard-report-modal" id="guestFlashcardReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="guestFlashcardReportForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('ui.report_study_card') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="guestFlashcardReportTitle">{{ __('ui.correction_copy') }}</p>
                @guest('student')
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ __('ui.name') }}</label>
                            <input type="text" name="guest_name" class="form-control" placeholder="{{ __('ui.optional') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ __('ui.email') }}</label>
                            <input type="email" name="guest_email" class="form-control" placeholder="{{ __('ui.optional') }}">
                        </div>
                    </div>
                @endguest
                <label class="form-label fw-bold mt-3">{{ __('ui.issue_type') }}</label>
                <select name="report_type" class="form-control mb-3">
                    <option value="Study Card Issue">{{ __('ui.study_card_issue') }}</option>
                    <option value="Wrong Answer">{{ __('ui.wrong_answer') }}</option>
                    <option value="Explanation Issue">{{ __('ui.explanation_issue') }}</option>
                    <option value="Typo Error">{{ __('ui.typo_error') }}</option>
                    <option value="Formatting Issue">{{ __('ui.formatting_issue') }}</option>
                    <option value="Other">{{ __('ui.other') }}</option>
                </select>
                <label class="form-label fw-bold">{{ __('ui.details') }} <span class="text-muted fw-normal">(optional)</span></label>
                <textarea name="message" class="form-control" rows="4" placeholder="Optional: describe what should be corrected."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="guest-flashcards-outline" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                <button type="submit" class="guest-flashcards-cta">{{ __('ui.submit_report') }}</button>
            </div>
        </form>
    </div>
</div>
<div class="guest-flashcards-toast" id="guestFlashcardReportToast"></div>

<script>
window.MathJax = {
    tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], packages: {'[+]': ['mhchem']} },
    loader: { load: ['[tex]/mhchem'] },
    svg: { fontCache: 'global' }
};

window.flashcardLoginRequired = @json(! $studentLoggedIn);
const guestFlashcardCsrf = @json(csrf_token());
let activeGuestReportCard = null;

function showGuestReportToast(message) {
    const toast = document.getElementById('guestFlashcardReportToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(window.guestFlashcardReportToastTimer);
    window.guestFlashcardReportToastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2200);
}

function trackGuestStudyCard(card, payload) {
    if (!card?.dataset?.trackUrl) {
        return;
    }

    const requestPayload = {...payload};
    if (card?.dataset?.questionId) {
        requestPayload.question_id = card.dataset.questionId;
    }

    fetch(card.dataset.trackUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': guestFlashcardCsrf,
        },
        body: JSON.stringify(requestPayload),
    }).catch(() => {});
}

const guestFlashcardReportForm = document.getElementById('guestFlashcardReportForm');
if (guestFlashcardReportForm) {
    guestFlashcardReportForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!activeGuestReportCard?.dataset?.reportUrl) return;

        const submitButton = guestFlashcardReportForm.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;

        fetch(activeGuestReportCard.dataset.reportUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': guestFlashcardCsrf,
            },
            body: new FormData(guestFlashcardReportForm),
        })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => {
                guestFlashcardReportForm.reset();
                const modal = document.getElementById('guestFlashcardReportModal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).hide();
                }
                showGuestReportToast(data.message || 'Study card report submitted.');
            })
            .catch(() => showGuestReportToast('Unable to submit report. Please try again.'))
            .finally(() => {
                if (submitButton) submitButton.disabled = false;
            });
    });
}

document.addEventListener('click', function (event) {
    const reportButton = event.target.closest('.js-guest-report-card');
    const option = event.target.closest('.guest-flashcard-option');
    const showButton = event.target.closest('.js-guest-show-answer');
    const loadCardsButton = event.target.closest('.js-load-study-cards');
    const studyCardToggle = event.target.closest('.js-study-card-toggle');

    if (reportButton) {
        activeGuestReportCard = reportButton.closest('.guest-flashcard');
        const title = document.getElementById('guestFlashcardReportTitle');
        if (title) {
            title.textContent = 'Tell us what needs correction in ' + (reportButton.dataset.cardTitle || 'this study card') + '.';
        }
        const modal = document.getElementById('guestFlashcardReportModal');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
        return;
    }

    if (studyCardToggle) {
        const target = document.getElementById(studyCardToggle.dataset.target);
        if (!target) return;

        const isOpen = target.classList.toggle('is-open');
        studyCardToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        studyCardToggle.innerHTML = isOpen
            ? '<i class="ri-eye-off-line"></i> Hide'
            : '<i class="ri-book-open-line"></i> Read';

        if (isOpen) {
            trackGuestStudyCard(target, {event: 'view'});
            if (window.MathJax?.typesetPromise) {
                window.MathJax.typesetPromise([target]).catch(() => {});
            }
            target.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        return;
    }

    if (loadCardsButton) {
        const step = parseInt(loadCardsButton.dataset.step || '20', 10);
        const hiddenRows = Array.from(document.querySelectorAll('.guest-flashcards-card-row.is-card-hidden')).slice(0, step);
        const revealedCards = [];

        hiddenRows.forEach((row) => {
            row.classList.remove('is-card-hidden');
            document.querySelectorAll('.guest-flashcard.is-card-hidden[data-card-index="' + row.dataset.cardIndex + '"]').forEach((card) => {
                card.classList.remove('is-card-hidden');
                revealedCards.push(card);
            });
        });

        if (!document.querySelector('.guest-flashcards-card-row.is-card-hidden')) {
            loadCardsButton.closest('.guest-flashcards-load')?.remove();
        }

        if (window.MathJax?.typesetPromise && revealedCards.length) {
            window.MathJax.typesetPromise(revealedCards).catch(() => {});
        }

        return;
    }

    if (option) {
        const card = option.closest('.guest-flashcard');
        const optionGroup = option.closest('.guest-flashcard-options');
        const isCorrect = option.dataset.correct === '1';
        optionGroup.querySelectorAll('.guest-flashcard-option').forEach((item) => {
            if (item.dataset.correct === '1') item.classList.add('is-correct');
            item.disabled = true;
        });
        if (!isCorrect) option.classList.add('is-wrong');
        card.dataset.answered = '1';

        trackGuestStudyCard(card, {event: 'answer', correct: isCorrect});
        const visibleCards = Array.from(document.querySelectorAll('[data-study-card]:not(.is-card-hidden)'));
        if (visibleCards.length && visibleCards.every((item) => item.dataset.answered === '1')) {
            trackGuestStudyCard(card, {event: 'complete', correct: isCorrect});
        }

        if (window.flashcardLoginRequired) {
            card.querySelector('.guest-flashcard-login-prompt')?.classList.add('is-visible');
        } else {
            card.querySelector('.guest-flashcard-back')?.classList.add('is-visible');
        }
    }

    if (showButton) {
        const card = showButton.closest('.guest-flashcard');
        trackGuestStudyCard(card, {event: 'view'});

        if (window.flashcardLoginRequired) {
            card.querySelector('.guest-flashcard-login-prompt')?.classList.add('is-visible');
            return;
        }

        const back = card.querySelector('.guest-flashcard-back');
        back?.classList.toggle('is-visible');
        showButton.textContent = back?.classList.contains('is-visible') ? 'Hide Explanation' : 'Show Explanation';
    }
});
</script>
<script id="guest-flashcard-mathjax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-svg.js"></script>
@endsection
