@extends('layouts.master')

@section('title')
    Search & AI Visibility
@endsection

@section('content')
    <style>
        .visibility-dashboard { --v-ink:#17213b; --v-muted:#697386; --v-line:#e5e9f0; }
        .visibility-hero { background:linear-gradient(135deg,#062f2e 0%,#0f766e 58%,#159486 100%); border:0; color:#fff; overflow:hidden; position:relative; }
        .visibility-hero::after { background:rgba(255,255,255,.08); border-radius:999px; content:''; height:290px; position:absolute; right:-90px; top:-170px; width:290px; }
        .visibility-eyebrow,.visibility-kicker { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
        .visibility-eyebrow { color:#99f6e4; }
        .visibility-kicker { color:var(--v-muted); }
        .visibility-hero h1 { color:#fff; font-size:clamp(1.65rem,3vw,2.45rem); letter-spacing:-.035em; max-width:720px; }
        .visibility-hero p { color:rgba(255,255,255,.76); max-width:690px; }
        .visibility-live { align-items:center; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); border-radius:999px; display:inline-flex; font-size:.77rem; gap:.45rem; padding:.42rem .75rem; }
        .visibility-live::before { background:#5eead4; border-radius:50%; box-shadow:0 0 0 4px rgba(94,234,212,.16); content:''; height:7px; width:7px; }
        .visibility-card { border:1px solid var(--v-line); box-shadow:0 10px 30px rgba(27,39,66,.045); }
        .visibility-score { align-items:center; background:conic-gradient(var(--score-color) calc(var(--score) * 1%),#e9edf3 0); border-radius:50%; display:flex; height:86px; justify-content:center; position:relative; width:86px; }
        .visibility-score::before { background:var(--vz-card-bg,#fff); border-radius:50%; content:''; inset:8px; position:absolute; }
        .visibility-score strong { color:var(--v-ink); font-size:1.45rem; position:relative; }
        .visibility-title { color:var(--v-ink); font-size:1rem; font-weight:700; }
        .visibility-progress { background:#edf1f5; border-radius:999px; height:7px; overflow:hidden; }
        .visibility-progress > span { background:var(--bar-color,#0f766e); border-radius:inherit; display:block; height:100%; width:var(--bar-width); }
        .visibility-opportunity { align-items:center; border-bottom:1px solid var(--v-line); display:grid; gap:1rem; grid-template-columns:minmax(0,1fr) 108px 142px; padding:1.15rem 0; }
        .visibility-opportunity:last-child { border-bottom:0; }
        .visibility-tag { border-radius:999px; display:inline-flex; font-size:.7rem; font-weight:700; padding:.3rem .58rem; }
        .visibility-tag-search { background:#e7f7f4; color:#0f766e; }
        .visibility-tag-ai { background:#f1ebff; color:#6d3cd2; }
        .visibility-tag-technical { background:#eef3fb; color:#41628c; }
        .visibility-impact { color:var(--v-muted); font-size:.73rem; }
        .visibility-empty-chart { align-items:center; background:linear-gradient(180deg,rgba(15,118,110,.05),transparent); border-bottom:1px solid var(--v-line); display:flex; height:155px; justify-content:center; position:relative; }
        .visibility-empty-chart::after { background-image:linear-gradient(#edf0f4 1px,transparent 1px),linear-gradient(90deg,#edf0f4 1px,transparent 1px); background-size:100% 38px,64px 100%; content:''; inset:0; opacity:.8; position:absolute; }
        .visibility-empty-chart span { background:var(--vz-card-bg,#fff); border:1px solid var(--v-line); border-radius:999px; color:var(--v-muted); font-size:.76rem; padding:.45rem .8rem; position:relative; z-index:1; }
        .visibility-connection { align-items:flex-start; border:1px solid var(--v-line); border-radius:.55rem; display:flex; gap:.85rem; padding:1rem; }
        .visibility-connection-icon { align-items:center; background:#f1f5f9; border-radius:.55rem; color:#475569; display:flex; flex:0 0 42px; height:42px; justify-content:center; }
        .visibility-metrics { display:grid; grid-template-columns:1fr 1fr; }
        .visibility-metric { border-bottom:1px solid var(--v-line); padding:1rem 1.15rem; }
        .visibility-metric:nth-child(odd) { border-right:1px solid var(--v-line); }
        .visibility-ready-item { align-items:flex-start; border-bottom:1px solid var(--v-line); display:flex; gap:.8rem; padding:.9rem 0; }
        .visibility-ready-item:last-child { border-bottom:0; }
        .visibility-ready-icon { align-items:center; background:#ecfdf5; border-radius:50%; color:#0f766e; display:flex; flex:0 0 32px; height:32px; justify-content:center; }
        [data-bs-theme="dark"] .visibility-dashboard { --v-ink:#edf3f2; --v-muted:#9ba8b4; --v-line:#2d3946; }
        [data-bs-theme="dark"] .visibility-score::before { background:#202832; }
        @media (max-width:767.98px) { .visibility-opportunity { grid-template-columns:1fr; } .visibility-opportunity .btn { justify-self:start; } }
    </style>

    <div class="visibility-dashboard">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif

        <div class="card visibility-hero mb-4">
            <div class="card-body p-4 p-lg-5 position-relative" style="z-index:1">
                <div class="row align-items-end g-4">
                    <div class="col-lg-8">
                        <div class="visibility-eyebrow mb-3">Organic growth control center</div>
                        <h1 class="mb-3">Make every page easy to find, understand and recommend.</h1>
                        <p class="mb-4">Monitor traditional search performance and prepare ExamElite content for Google, AI answers and emerging discovery tools from one workspace.</p>
                        <span class="visibility-live">Live content audit · {{ number_format($totalItems) }} records checked</span>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="{{ route('admin.seo.bulk') }}" class="btn btn-light px-4">Optimize content</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card visibility-card h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="visibility-score" style="--score:{{ $searchScore }};--score-color:#0f766e"><strong>{{ $searchScore }}</strong></div>
                    <div><div class="visibility-kicker mb-1">Search health</div><div class="visibility-title">Google readiness</div><small class="text-muted">Metadata + technical signals</small></div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card visibility-card h-100"><div class="card-body d-flex align-items-center gap-3">
                    <div class="visibility-score" style="--score:{{ $aiScore }};--score-color:#7c3aed"><strong>{{ $aiScore }}</strong></div>
                    <div><div class="visibility-kicker mb-1">AI visibility</div><div class="visibility-title">Answer readiness</div><small class="text-muted">Meaning + structured data</small></div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card visibility-card h-100"><div class="card-body">
                    <div class="visibility-kicker mb-2">Content coverage</div>
                    <div class="d-flex align-items-end justify-content-between"><strong class="fs-3 text-body">{{ $metaDescriptionCoverage }}%</strong><span class="text-muted small">described</span></div>
                    <div class="visibility-progress mt-3" style="--bar-width:{{ $metaDescriptionCoverage }}%"><span></span></div>
                </div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card visibility-card h-100"><div class="card-body">
                    <div class="visibility-kicker mb-2">Schema coverage</div>
                    <div class="d-flex align-items-end justify-content-between"><strong class="fs-3 text-body">{{ $schemaCoverage }}%</strong><span class="text-muted small">machine-ready</span></div>
                    <div class="visibility-progress mt-3" style="--bar-width:{{ $schemaCoverage }}%;--bar-color:#7c3aed"><span></span></div>
                </div></div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card visibility-card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div><div class="visibility-kicker">Prioritized automatically</div><h4 class="card-title mb-0 mt-1">Next best opportunities</h4></div>
                        <span class="badge bg-light text-dark">{{ $opportunities->count() }} actions</span>
                    </div>
                    <div class="card-body py-0">
                        @foreach($opportunities as $opportunity)
                            @php $tagClass = $opportunity['area'] === 'Search' ? 'search' : ($opportunity['area'] === 'AI visibility' ? 'ai' : 'technical'); @endphp
                            <div class="visibility-opportunity">
                                <div>
                                    <div class="d-flex gap-2 align-items-center mb-2"><span class="visibility-tag visibility-tag-{{ $tagClass }}">{{ $opportunity['area'] }}</span><small class="text-muted">{{ $opportunity['priority'] }} priority</small></div>
                                    <h5 class="fs-14 mb-1">{{ $opportunity['title'] }}</h5>
                                    <p class="text-muted mb-0 small">{{ $opportunity['detail'] }}</p>
                                </div>
                                <div><div class="d-flex justify-content-between visibility-impact mb-1"><span>Impact</span><strong>{{ $opportunity['impact'] }}</strong></div><div class="visibility-progress" style="--bar-width:{{ $opportunity['impact'] }}%;--bar-color:#f59e0b"><span></span></div></div>
                                <a href="{{ $opportunity['action'] }}" class="btn btn-sm btn-outline-primary">{{ $opportunity['action_label'] }}</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card visibility-card h-100">
                    <div class="card-header"><div class="visibility-kicker">Actual search demand</div><h4 class="card-title mb-0 mt-1">Search performance</h4></div>
                    @if(!empty($searchData['summary']))
                        @php $summary = $searchData['summary']; @endphp
                        <div class="visibility-metrics">
                            <div class="visibility-metric"><div class="visibility-kicker">Clicks</div><strong class="fs-4 text-body">{{ number_format($summary['clicks'] ?? 0) }}</strong><div class="small {{ ($summary['changes']['clicks'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ ($summary['changes']['clicks'] ?? 0) >= 0 ? '+' : '' }}{{ $summary['changes']['clicks'] ?? 0 }}%</div></div>
                            <div class="visibility-metric"><div class="visibility-kicker">Impressions</div><strong class="fs-4 text-body">{{ number_format($summary['impressions'] ?? 0) }}</strong><div class="small {{ ($summary['changes']['impressions'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ ($summary['changes']['impressions'] ?? 0) >= 0 ? '+' : '' }}{{ $summary['changes']['impressions'] ?? 0 }}%</div></div>
                            <div class="visibility-metric"><div class="visibility-kicker">CTR</div><strong class="fs-4 text-body">{{ number_format(($summary['ctr'] ?? 0) * 100, 1) }}%</strong><div class="small text-muted">Last 28 days</div></div>
                            <div class="visibility-metric"><div class="visibility-kicker">Avg. position</div><strong class="fs-4 text-body">{{ number_format($summary['position'] ?? 0, 1) }}</strong><div class="small text-muted">Google web</div></div>
                        </div>
                    @else
                        <div class="visibility-empty-chart"><span>{{ $searchIntegration ? 'Choose a property or run the first sync' : 'Connect Search Console to begin' }}</span></div>
                    @endif
                    <div class="card-body" id="connections">
                        <div class="visibility-connection">
                            <div class="visibility-connection-icon"><i class="ri-google-line fs-5"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2"><h5 class="fs-14 mb-1">Google Search Console</h5>@if($searchIntegration)<span class="badge bg-success-subtle text-success">Connected</span>@endif</div>
                                <p class="text-muted small mb-2">
                                    @if($searchIntegration?->property_url)
                                        {{ $searchIntegration->property_url }}<br>Last sync: {{ $searchIntegration->last_synced_at?->diffForHumans() ?? 'not yet' }}
                                    @else
                                        Add queries, clicks, CTR, positions and page trends.
                                    @endif
                                </p>
                                @if(!$searchIntegration)
                                    @if($googleConfigured)
                                        <a href="{{ route('admin.seo.google.connect') }}" class="btn btn-sm btn-primary">Connect Google</a>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">Google credentials required</span>
                                        <div class="text-muted small mt-2">Register callback: <code>{{ route('admin.seo.google.callback') }}</code></div>
                                    @endif
                                @else
                                    @if($properties->count() > 1)
                                        <form method="POST" action="{{ route('admin.seo.google.property') }}" class="d-flex gap-2 mb-2">
                                            @csrf
                                            <select name="property_url" class="form-select form-select-sm" aria-label="Search Console property">
                                                @foreach($properties as $property)
                                                    <option value="{{ $property['url'] }}" @selected($searchIntegration->property_url === $property['url'])>{{ $property['url'] }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Use</button>
                                        </form>
                                    @endif
                                    <div class="d-flex flex-wrap gap-2">
                                        @if($searchIntegration->property_url)
                                            <form method="POST" action="{{ route('admin.seo.google.sync') }}">@csrf<button class="btn btn-sm btn-primary">Refresh data</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.seo.google.disconnect') }}" onsubmit="return confirm('Disconnect Search Console and remove stored tokens?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ghost-danger">Disconnect</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if($searchIntegration?->last_error)<div class="alert alert-warning py-2 px-3 small mt-3 mb-0">{{ $searchIntegration->last_error }}</div>@endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="card visibility-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><div class="visibility-kicker">Positions 4–20</div><h4 class="card-title mb-0 mt-1">Keyword quick wins</h4></div>
                        @if($searchIntegration)<span class="badge bg-primary-subtle text-primary">Search Console</span>@endif
                    </div>
                    @if($keywordOpportunities->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light"><tr><th>Query</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Position</th></tr></thead>
                                <tbody>
                                    @foreach($keywordOpportunities as $keyword)
                                        <tr>
                                            <td><strong>{{ $keyword['query'] }}</strong></td>
                                            <td>{{ number_format($keyword['impressions']) }}</td>
                                            <td>{{ number_format($keyword['clicks']) }}</td>
                                            <td>{{ number_format($keyword['ctr'] * 100, 1) }}%</td>
                                            <td><span class="badge bg-warning-subtle text-warning">{{ number_format($keyword['position'], 1) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="card-body text-center py-5">
                            <i class="ri-search-eye-line fs-1 text-muted"></i>
                            <h5 class="mt-2">Keyword opportunities will appear here</h5>
                            <p class="text-muted small mb-0">The daily sync identifies high-impression queries already close to page one.</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card visibility-card h-100">
                    <div class="card-header"><div class="visibility-kicker">Generative discovery</div><h4 class="card-title mb-0 mt-1">AI answer-engine readiness</h4></div>
                    <div class="card-body py-2">
                        <div class="visibility-ready-item"><div class="visibility-ready-icon"><i class="ri-braces-line"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>Structured meaning</strong><span>{{ $schemaCoverage }}%</span></div><div class="visibility-progress mt-2" style="--bar-width:{{ $schemaCoverage }}%;--bar-color:#7c3aed"><span></span></div><small class="text-muted">JSON-LD helps systems identify exams, pages and entities.</small></div></div>
                        <div class="visibility-ready-item"><div class="visibility-ready-icon"><i class="ri-question-answer-line"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>Answer-ready summaries</strong><span>{{ $metaDescriptionCoverage }}%</span></div><div class="visibility-progress mt-2" style="--bar-width:{{ $metaDescriptionCoverage }}%"><span></span></div><small class="text-muted">Clear descriptions make page purpose easier to retrieve.</small></div></div>
                        <div class="visibility-ready-item"><div class="visibility-ready-icon"><i class="ri-links-line"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>Canonical citation paths</strong><span>{{ $canonicalCoverage }}%</span></div><div class="visibility-progress mt-2" style="--bar-width:{{ $canonicalCoverage }}%;--bar-color:#2563eb"><span></span></div><small class="text-muted">Stable canonical URLs reduce ambiguity and duplication.</small></div></div>
                        <div class="visibility-ready-item"><div class="visibility-ready-icon"><i class="ri-robot-2-line"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>AI crawler access</strong>
                            <span class="badge {{ $aiCrawlerStatus === 'allowed' ? 'bg-success-subtle text-success' : ($aiCrawlerStatus === 'blocked' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') }}">{{ ucfirst($aiCrawlerStatus) }}</span>
                        </div><small class="text-muted">Checks robots.txt for Google and common answer-engine crawlers.</small></div></div>
                        <div class="alert alert-light border small mt-2 mb-2"><i class="ri-information-line me-1"></i>Readiness improves eligibility; no tool can guarantee inclusion in an AI-generated answer.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card visibility-card">
            <div class="card-header"><div class="visibility-kicker">Coverage by content type</div><h4 class="card-title mb-0 mt-1">Search and answer-engine foundations</h4></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Content</th><th>Records</th><th>Titles</th><th>Descriptions</th><th>Canonical</th><th>Schema</th></tr></thead>
                    <tbody>
                        @foreach($audits as $audit)
                            @php $denominator = max(1, $audit['total']); @endphp
                            <tr><td><strong>{{ $audit['label'] }}</strong></td><td>{{ number_format($audit['total']) }}</td><td>{{ round(($audit['fields']['meta_title'] / $denominator) * 100) }}%</td><td>{{ round(($audit['fields']['meta_description'] / $denominator) * 100) }}%</td><td>{{ round(($audit['fields']['canonical_url'] / $denominator) * 100) }}%</td><td>{{ round(($audit['fields']['seo_schema'] / $denominator) * 100) }}%</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
