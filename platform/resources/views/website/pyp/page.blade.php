@extends('website.layouts.app')

@section('content')
@php
    $packageName = is_array($package->name) ? ($package->name['en'] ?? reset($package->name)) : $package->name;
    $packageSlug = $package->slug;
    $subjectToken = $subject['token'] ?? null;
    $topicToken = $topic['token'] ?? null;
    $groupId = $package->groups->first()?->id;
    $children = $pageType === 'index'
        ? ($settings['subject_pages'] ? $subjects : collect())
        : ($pageType === 'subject'
            ? ($settings['topic_pages'] ? $topics : collect())
            : ($pageType === 'topic' && $settings['subtopic_pages'] ? $subtopics : collect()));
    $childLabel = $pageType === 'index' ? 'Subjects' : ($pageType === 'subject' ? 'Topics' : 'Subtopics');
@endphp

<style>
    .pyp-page{background:var(--theme-body-bg,#f8fafc);color:var(--theme-text,#334155);padding:clamp(24px,4vw,56px) 0 72px}
    .pyp-shell{width:min(1180px,calc(100% - 28px));margin:auto}
    .pyp-breadcrumb{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:18px;font-size:.9rem}
    .pyp-breadcrumb a{color:var(--theme-primary,#0f766e);text-decoration:none;font-weight:700}
    .pyp-hero,.pyp-panel,.pyp-card{background:var(--theme-card-bg,#fff);border:1px solid var(--theme-border,#dbe3ea);border-radius:18px}
    .pyp-hero{padding:clamp(24px,4vw,44px)}
    .pyp-kicker{color:var(--theme-primary,#0f766e);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase;font-weight:800}
    .pyp-hero h1{color:var(--theme-heading,#0f172a);font-size:clamp(2rem,4.4vw,3.6rem);line-height:1.07;margin:10px 0 14px;max-width:900px}
    .pyp-hero p{font-size:clamp(1rem,1.8vw,1.18rem);line-height:1.65;max-width:850px;margin:0}
    .pyp-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}
    .pyp-tab{padding:10px 15px;border-radius:10px;border:1px solid var(--theme-border,#dbe3ea);text-decoration:none;color:var(--theme-heading,#0f172a);font-weight:750;background:var(--theme-card-bg,#fff)}
    .pyp-tab:hover,.pyp-tab.is-active{background:var(--theme-primary,#0f766e);border-color:var(--theme-primary,#0f766e);color:var(--theme-button-text,#fff)}
    .pyp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:18px 0}
    .pyp-stat{padding:20px}.pyp-stat strong{display:block;color:var(--theme-heading,#0f172a);font-size:1.75rem}.pyp-stat span{font-size:.86rem;font-weight:700}
    .pyp-panel{padding:clamp(20px,3vw,32px);margin-top:18px}
    .pyp-section-head{display:flex;justify-content:space-between;gap:16px;align-items:end;margin-bottom:20px}
    .pyp-section-head h2{color:var(--theme-heading,#0f172a);margin:0;font-size:clamp(1.35rem,2.4vw,1.9rem)}
    .pyp-section-head p{margin:5px 0 0}.pyp-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .pyp-card{padding:20px;text-decoration:none;color:inherit;transition:transform .2s,border-color .2s}
    .pyp-card:hover{transform:translateY(-2px);border-color:var(--theme-primary,#0f766e)}
    .pyp-card h3{color:var(--theme-heading,#0f172a);font-size:1.05rem;margin:0 0 10px}
    .pyp-card-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.82rem}
    .pyp-card-meta span{background:color-mix(in srgb,var(--theme-primary,#0f766e) 8%,var(--theme-card-bg,#fff));padding:5px 8px;border-radius:7px}
    .pyp-practice{display:flex;justify-content:space-between;gap:20px;align-items:center;background:color-mix(in srgb,var(--theme-primary,#0f766e) 9%,var(--theme-card-bg,#fff));border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 25%,var(--theme-border,#dbe3ea))}
    .pyp-practice h2{margin:0 0 5px;color:var(--theme-heading,#0f172a)}
    .pyp-action{border:0;border-radius:10px;padding:12px 18px;background:var(--theme-primary,#0f766e);color:var(--theme-button-text,#fff);font-weight:800;text-decoration:none;white-space:nowrap}
    .pyp-table-wrap{overflow:auto}.pyp-table{width:100%;border-collapse:collapse;min-width:650px}
    .pyp-table th,.pyp-table td{padding:14px 12px;border-bottom:1px solid var(--theme-border,#dbe3ea);text-align:left}
    .pyp-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--theme-heading,#0f172a)}
    .pyp-paper-link{color:var(--theme-primary,#0f766e);font-weight:750;text-decoration:none}
    .pyp-question{padding:18px 0;border-bottom:1px solid var(--theme-border,#dbe3ea)}.pyp-question:last-child{border:0}
    .pyp-question-label{font-size:.78rem;color:var(--theme-primary,#0f766e);font-weight:800;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
    .pyp-question-body{color:var(--theme-heading,#0f172a);font-size:1rem;line-height:1.7}
    .pyp-analysis-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:18px}
    .pyp-bars{display:grid;gap:12px}.pyp-bar-row{display:grid;grid-template-columns:70px 1fr 70px;gap:10px;align-items:center}
    .pyp-bar-track{height:12px;border-radius:99px;background:color-mix(in srgb,var(--theme-border,#dbe3ea) 70%,transparent);overflow:hidden}
    .pyp-bar-fill{height:100%;border-radius:99px;background:var(--theme-primary,#0f766e)}
    .pyp-filter{border:1px solid var(--theme-border,#dbe3ea);background:var(--theme-card-bg,#fff);color:var(--theme-heading,#0f172a);border-radius:9px;padding:9px 12px}
    .pyp-note{padding:14px;border:1px solid color-mix(in srgb,var(--theme-secondary,#e87918) 40%,var(--theme-border,#dbe3ea));border-radius:10px;background:color-mix(in srgb,var(--theme-secondary,#e87918) 8%,var(--theme-card-bg,#fff))}
    @media(max-width:850px){.pyp-stats{grid-template-columns:repeat(2,1fr)}.pyp-grid,.pyp-analysis-grid{grid-template-columns:1fr}.pyp-practice{align-items:flex-start;flex-direction:column}}
    @media(max-width:480px){.pyp-shell{width:min(100% - 20px,1180px)}.pyp-hero{padding:22px 18px}.pyp-stats{gap:9px}.pyp-stat{padding:15px}.pyp-stat strong{font-size:1.4rem}.pyp-tab{font-size:.85rem;padding:9px 11px}}
</style>

<main class="pyp-page">
    <div class="pyp-shell">
        <nav class="pyp-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span>
            <a href="{{ route('courses.detail',$packageSlug) }}">{{ $packageName }}</a><span>/</span>
            @if($subject)<a href="{{ route('pyp.index',$packageSlug) }}">Previous year papers</a><span>/</span>@endif
            @if($topic)<a href="{{ route('pyp.subject',[$packageSlug,$subjectToken]) }}">{{ $subject['name'] }}</a><span>/</span>@endif
            @if($subtopic)<a href="{{ route('pyp.topic',[$packageSlug,$subjectToken,$topicToken]) }}">{{ $topic['name'] }}</a><span>/</span>@endif
            <span>{{ $pageType === 'analysis' ? 'Analysis' : ($subtopic['name'] ?? $topic['name'] ?? $subject['name'] ?? 'PYP') }}</span>
        </nav>

        <section class="pyp-hero">
            <span class="pyp-kicker">{{ $pageType === 'analysis' ? 'Data-backed preparation' : 'Previous year question hub' }}</span>
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $description }}</p>
            <nav class="pyp-tabs" aria-label="Previous year paper sections">
                <a class="pyp-tab {{ $pageType === 'index' ? 'is-active' : '' }}" href="{{ route('pyp.index',$packageSlug) }}">Year & subject wise</a>
                @if($settings['analysis_enabled'] && $analysisMode !== 'disabled')
                    <a class="pyp-tab {{ $pageType === 'analysis' ? 'is-active' : '' }}" href="{{ route('pyp.analysis',$packageSlug) }}">Trends & analysis</a>
                @endif
                <a class="pyp-tab" href="{{ route('courses.detail',$packageSlug) }}">All tests</a>
            </nav>
        </section>

        <section class="pyp-stats" aria-label="Coverage summary">
            @foreach([['Papers',$pageStats['papers']],['Years',$pageStats['years']],['Questions',$pageStats['questions']],['Topics',$pageStats['topics']]] as [$label,$value])
                <div class="pyp-stat pyp-card"><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></div>
            @endforeach
        </section>

        @if($pageType === 'analysis')
            <section class="pyp-panel">
                <div class="pyp-section-head">
                    <div><h2>Historical question trend</h2><p>Compare question volume by year and subject.</p></div>
                    <select id="pypSubjectFilter" class="pyp-filter" aria-label="Filter trend by subject">
                        <option value="">All subjects</option>
                        @foreach($subjects as $item)<option value="{{ $item['id'] }}">{{ $item['name'] }}</option>@endforeach
                    </select>
                </div>
                @if(!$historicalAvailable)
                    <div class="pyp-note mb-3">Historical comparison needs at least {{ $settings['min_years_for_historical'] }} years. Current content coverage is shown without claiming a historical trend.</div>
                @endif
                <div class="pyp-analysis-grid">
                    <div>
                        <div id="pypTrendBars" class="pyp-bars" aria-live="polite"></div>
                    </div>
                    <div>
                        <h3 style="color:var(--theme-heading,#0f172a)">Difficulty mix</h3>
                        <div class="pyp-bars">
                            @foreach($dataset['difficulty'] as $row)
                                <div class="pyp-bar-row"><span>{{ $row['label'] }}</span><div class="pyp-bar-track"><div class="pyp-bar-fill" style="width:{{ $row['percentage'] }}%"></div></div><strong>{{ $row['percentage'] }}%</strong></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @elseif($children->isNotEmpty())
            <section class="pyp-panel">
                <div class="pyp-section-head"><div><h2>Browse by {{ strtolower($childLabel) }}</h2><p>Open a focused page built from the same verified paper data.</p></div></div>
                <div class="pyp-grid">
                    @foreach($children as $child)
                        @php
                            $childUrl = $pageType === 'index'
                                ? route('pyp.subject',[$packageSlug,$child['token']])
                                : ($pageType === 'subject'
                                    ? route('pyp.topic',[$packageSlug,$subjectToken,$child['token']])
                                    : route('pyp.subtopic',[$packageSlug,$subjectToken,$topicToken,$child['token']]));
                        @endphp
                        <a class="pyp-card" href="{{ $childUrl }}">
                            <h3>{{ $child['name'] }}</h3>
                            <div class="pyp-card-meta"><span>{{ $child['unique_questions'] }} questions</span><span>{{ $child['papers'] }} papers</span><span>{{ count($child['years']) }} years</span></div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="pyp-panel pyp-practice">
            <div><h2>Practice this PYP selection</h2><p class="mb-0">Start a short quiz from {{ $subtopic['name'] ?? $topic['name'] ?? $subject['name'] ?? $packageName }}.</p></div>
            <button type="button" class="pyp-action" data-quick-quiz-open data-qq-source="pyp_page"
                data-qq-group-id="{{ $groupId }}" data-qq-package-id="{{ $package->id }}" data-qq-pyp-only="1"
                data-qq-subject-id="{{ $subject['id'] ?? '' }}" data-qq-subject-label="{{ $subject['name'] ?? '' }}"
                data-qq-topic-id="{{ $topic['id'] ?? '' }}" data-qq-topic-label="{{ $topic['name'] ?? '' }}"
                data-qq-subtopic-id="{{ $subtopic['id'] ?? '' }}" data-qq-subtopic-label="{{ $subtopic['name'] ?? '' }}">
                Start PYP Practice <i class="ri-arrow-right-line"></i>
            </button>
        </section>

        <section class="pyp-panel">
            <div class="pyp-section-head"><div><h2>Included previous year papers</h2><p>Open the complete paper page when you want a full-length attempt.</p></div></div>
            <div class="pyp-table-wrap"><table class="pyp-table">
                <thead><tr><th>Paper</th><th>Year</th><th>Questions in this view</th><th>Open</th></tr></thead>
                <tbody>
                    @foreach($exams as $exam)
                        @php $count = $entries->where('exam_id',$exam['id'])->pluck('question_id')->unique()->count(); @endphp
                        <tr><td>{{ $exam['name'] }}</td><td>{{ $exam['year'] ?: '—' }}</td><td>{{ $count ?: 'Full paper' }}</td><td>@if($exam['slug'])<a class="pyp-paper-link" href="{{ route('exam.detail',$exam['slug']) }}">View paper <i class="ri-arrow-right-line"></i></a>@endif</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>

        @if($sampleQuestions->isNotEmpty() && $pageType !== 'analysis')
            <section class="pyp-panel">
                <div class="pyp-section-head"><div><h2>Sample previous year questions</h2><p>A crawlable preview of the questions covered in this collection.</p></div></div>
                @foreach($sampleQuestions as $index => $item)
                    <article class="pyp-question">
                        <div class="pyp-question-label">Question {{ $index + 1 }} · {{ $item['year'] ?: 'PYP' }} · {{ $item['topic_name'] }}</div>
                        <div class="pyp-question-body">{!! $item['question'] !!}</div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</main>

@include('website.partials.quick-quiz', ['quizGroups' => $package->groups, 'quickQuizDefaultGroupId' => $groupId])
@endsection

@push('scripts')
@if($pageType === 'analysis')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const entries = @json($entries->map(fn($row) => ['year'=>$row['year'],'subject_id'=>$row['subject_id'],'question_id'=>$row['question_id']])->values());
    const select = document.getElementById('pypSubjectFilter');
    const target = document.getElementById('pypTrendBars');
    const render = () => {
        const subject = select.value;
        const rows = entries.filter(row => row.year && (!subject || String(row.subject_id) === subject));
        const years = {};
        rows.forEach(row => { years[row.year] ??= new Set(); years[row.year].add(row.question_id); });
        const ordered = Object.entries(years).sort((a,b) => Number(a[0]) - Number(b[0]));
        const max = Math.max(1,...ordered.map(row => row[1].size));
        target.innerHTML = ordered.length ? ordered.map(([year,ids]) => `<div class="pyp-bar-row"><strong>${year}</strong><div class="pyp-bar-track"><div class="pyp-bar-fill" style="width:${Math.max(4,ids.size/max*100)}%"></div></div><span>${ids.size} Qs</span></div>`).join('') : '<p>No year-tagged questions are available for this selection.</p>';
    };
    select.addEventListener('change', render); render();
});
</script>
@endif
@endpush
