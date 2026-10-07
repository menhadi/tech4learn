@extends('website.layouts.app')

@section('title', $title ?? __('website.all_courses'))

@section('content')
@php
    $labelText = function ($value) {
        if (is_array($value)) {
            return $value['en'] ?? reset($value) ?: '';
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? reset($decoded) ?: $value;
            }
        }

        return $value;
    };

    $currentLevel = isset($packages) ? 4 : 1;
    if (isset($categoryLevel1) && $categoryLevel1->count() > 0) {
        $currentLevel = 2;
    }
    if (isset($categoryLevel2) && $categoryLevel2->count() > 0) {
        $currentLevel = 3;
    }

    $browseSubtitle = match ($currentLevel) {
        2 => 'Choose a category, then continue to available packages.',
        3 => 'Choose a subcategory, then continue to available packages.',
        4 => 'Choose a package to view papers and start practicing.',
        default => 'Choose your exam group to begin.',
    };

    $browseLeaderboard = collect($browseLeaderboard ?? []);
    $hasBrowseLeaderboard = $browseLeaderboard->isNotEmpty();
    $showBrowseLeaderboard = $currentLevel > 1;
    $browseItemColumn = $showBrowseLeaderboard ? 'col-xl-6 col-lg-6 col-md-6' : 'col-xl-4 col-lg-4 col-md-6';
    $groupItemColumn = 'col-xl-3 col-lg-4 col-md-6';

    $browseFilterUrl = function (array $changes = []) {
        $query = request()->except('page');

        foreach ($changes as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        return request()->url() . ($query ? '?' . http_build_query($query) : '');
    };
@endphp

<style>
    .browse-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, var(--theme-body-bg, #ffffff));
        padding: 30px 0 18px;
    }

    .browse-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.8rem, 7vw, 3.2rem);
        font-weight: 900;
        line-height: 1.05;
        margin: 0 0 10px;
    }

    .browse-copy {
        color: var(--theme-text, #64748b);
        font-size: clamp(.95rem, 3vw, 1.08rem);
        max-width: 760px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .browse-breadcrumb {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
        font-size: 13px;
        font-weight: 700;
    }

    .browse-breadcrumb a {
        color: var(--theme-primary, #0f766e);
        text-decoration: none;
    }

    .browse-breadcrumb span {
        color: var(--theme-text, #64748b);
    }

    .browse-card-link {
        color: inherit;
        display: block;
        height: 100%;
        text-decoration: none;
    }



    .browse-home-step-icon {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border-radius: 14px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-size: 22px;
        height: 42px;
        justify-content: center;
        margin-bottom: 10px;
        width: 42px;
    }    .browse-group-card { align-items:center; background:var(--theme-card-bg,#fff); border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e3); border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.05); display:flex; gap:16px; min-height:0; padding:24px; transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .browse-group-card:hover { border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 38%,#fff); box-shadow:0 16px 34px rgba(15,23,42,.1); transform:translateY(-3px); }
    .browse-group-icon { align-items:center; background:var(--theme-primary,#0f766e); border-radius:14px; color:var(--theme-button-text,#fff); display:inline-flex; flex:0 0 64px; font-size:27px; height:64px; justify-content:center; width:64px; }
    .browse-group-content { flex:1; min-width:0; }
    .browse-group-title { color:var(--theme-heading,#0f172a); font-size:1.08rem; font-weight:900; line-height:1.3; margin:0 0 6px; overflow-wrap:anywhere; }
    .browse-group-title.is-long { font-size:.96rem; }
    .browse-group-title.is-very-long { font-size:.86rem; }
    .browse-group-meta { color:var(--theme-text,#64748b); font-size:13px; line-height:1.5; }
    .browse-group-arrow { color:var(--theme-primary,#0f766e); flex:0 0 auto; font-size:1.35rem; }


    .browse-category-card { align-items:center; background:linear-gradient(145deg,#fff,color-mix(in srgb,var(--theme-primary,#0f766e) 5%,#fff)); border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e3); border-left:4px solid color-mix(in srgb,var(--theme-primary,#0f766e) 55%,#fff); border-radius:16px; box-shadow:0 8px 24px rgba(15,23,42,.05); display:flex; gap:16px; height:100%; min-height:118px; padding:18px 20px; transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .browse-category-card:hover { border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 35%,#fff); box-shadow:0 16px 34px rgba(15,23,42,.1); transform:translateY(-3px); }
    .browse-category-icon { align-items:center; background:color-mix(in srgb,var(--theme-primary,#0f766e) 12%,#fff); border-radius:14px; color:var(--theme-primary,#0f766e); display:inline-flex; flex:0 0 48px; font-size:23px; height:48px; justify-content:center; width:48px; }

    .browse-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        display: flex;
        flex-direction: column;
        gap: 14px;
        height: 100%;
        min-height: 228px;
        padding: 17px;
        position: relative;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .browse-card::before {
        background: var(--theme-primary, #0f766e);
        border-radius: 12px 0 0 12px;
        content: "";
        inset: 0 auto 0 0;
        position: absolute;
        width: 5px;
    }

    .browse-card:hover {
        border-color: color-mix(in srgb, var(--theme-primary, #0f766e) 36%, #e2e8f0);
        box-shadow: 0 18px 34px rgba(15, 23, 42, .1);
        transform: translateY(-3px);
    }

    .browse-card-icon {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border-radius: 14px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        height: 48px;
        justify-content: center;
        width: 48px;
        font-size: 23px;
    }

    .browse-card-title {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 850;
        line-height: 1.35;
        margin: 0;
        overflow-wrap: anywhere;
    }

    .browse-card-kicker {
        color: var(--theme-text, #64748b);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .browse-card-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: auto;
    }

    .browse-stat {
        align-items: center;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #ffffff);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 5px;
        padding: 6px 10px;
    }

    .browse-action {
        align-items: center;
        background: var(--theme-secondary, #f59e0b);
        border: 1px solid var(--theme-secondary, #f59e0b);
        border-radius: 999px;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-size: 13px;
        font-weight: 800;
        gap: 8px;
        justify-content: center;
        padding: 10px 16px;
        width: 100%;
    }

    .browse-card:hover .browse-action {
        background: color-mix(in srgb, var(--theme-secondary, #f59e0b) 88%, #000000);
        color: var(--theme-button-text, #ffffff);
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
    }

    .browse-tag-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
    }

    .browse-tag-chip {
        align-items: center;
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #dbeafe);
        border-radius: 999px;
        color: var(--theme-heading, #0f172a);
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 6px;
        padding: 7px 11px;
        text-decoration: none;
    }

    .browse-tag-chip:hover,
    .browse-tag-chip:focus { background:color-mix(in srgb,var(--theme-primary,#0f766e) 7%,#fff); border-color:color-mix(in srgb,var(--theme-primary,#0f766e) 38%,#fff); color:var(--theme-primary,#0f766e); outline:none; text-decoration:none; transform:translateY(-1px); }
    .browse-tag-chip.is-active { background:var(--theme-primary,#0f766e); border-color:var(--theme-primary,#0f766e); box-shadow:0 6px 16px color-mix(in srgb,var(--theme-primary,#0f766e) 22%,transparent); color:var(--theme-button-text,#fff); }

    .browse-tag-chip { border-radius:12px; padding:9px 13px; transition:transform .18s ease,background .18s ease,border-color .18s ease; }
    .browse-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 22px;
        align-items: start;
    }

    .browse-leaderboard {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .06);
        padding: 16px;
        position: sticky;
        top: 90px;
    }

    .browse-leaderboard-title {
        color: var(--theme-heading, #0f172a);
        font-size: 1rem;
        font-weight: 900;
        line-height: 1.3;
        margin-bottom: 4px;
        overflow-wrap: anywhere;
    }

    .browse-leaderboard-row {
        align-items: center;
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        display: grid;
        gap: 10px;
        grid-template-columns: 38px minmax(0, 1fr) auto;
        padding: 11px 0;
    }

    .browse-leaderboard-rank {
        align-items: center;
        background: var(--theme-primary, #0f766e);
        border-radius: 50%;
        color: var(--theme-button-text, #ffffff);
        display: inline-flex;
        font-size: 13px;
        font-weight: 900;
        height: 34px;
        justify-content: center;
        width: 34px;
    }

    .browse-leaderboard-row:nth-of-type(2) .browse-leaderboard-rank {
        background: var(--theme-secondary, #f59e0b);
    }

    .browse-leaderboard-row:nth-of-type(3) .browse-leaderboard-rank {
        background: var(--theme-tertiary, #38bdf8);
    }

    .browse-leaderboard-name {
        color: var(--theme-heading, #0f172a);
        font-size: 14px;
        font-weight: 850;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .browse-leaderboard-score {
        color: var(--theme-primary, #0f766e);
        font-size: 14px;
        font-weight: 900;
    }

    .browse-leaderboard-empty {
        align-items: center;
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        color: var(--theme-text, #64748b);
        display: flex;
        gap: 8px;
        font-size: 13px;
        font-weight: 750;
        padding-top: 14px;
    }

    .browse-leaderboard-empty i {
        color: var(--theme-secondary, #f59e0b);
        font-size: 18px;
    }

    .browse-empty {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 14px;
        padding: 42px 18px;
    }


    /* Controls belong to the package grid, without a summary card. */
    .browse-package-controls {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        margin-bottom: 18px;
        width: 100%;
    }

    .browse-chip-row {
        align-items: center;
        display: flex;
        flex: 1 1 360px;
        flex-wrap: wrap;
        gap: 6px;
        margin-right: auto;
    }

    .browse-tag-chips {
        gap: 6px;
    }

    .browse-tag-chip {
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 55%, #cbd5e1);
        border-radius: 999px;
        color: var(--theme-heading, #0f172a);
        font-size: 14px;
        font-weight: 500;
        gap: 0;
        line-height: 1.4;
        padding: 8px 16px;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease;
    }

    .browse-tag-chip:hover,
    .browse-tag-chip:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, transparent);
        border-color: var(--theme-primary, #0f766e);
        color: var(--theme-primary, #0f766e);
        transform: none;
    }

    .browse-tag-chip.is-active {
        background: transparent;
        border-color: var(--theme-primary, #0f766e);
        box-shadow: none;
        color: var(--theme-primary, #0f766e);
        font-weight: 600;
    }
    @media (max-width: 767.98px) {
        .browse-card {
            min-height: 0;
        }

        .browse-package-controls {
            align-items: stretch;
            flex-direction: column;
            margin-bottom: 16px;
        }

        .browse-chip-row { flex-basis:auto; margin-right:0; width:100%; }
        .browse-tag-chips { flex-wrap:wrap; overflow:visible; padding-bottom:0; }
        .browse-tag-chip { flex:0 0 auto; max-width:100%; padding:6px 9px; white-space:normal; }
        .browse-category-card { box-shadow:0 10px 26px rgba(15,23,42,.07); min-height:108px; padding:16px 17px; }
        .browse-category-icon { flex-basis:44px; height:44px; width:44px; }

        .browse-layout {
            grid-template-columns: 1fr;
        }

        .browse-leaderboard {
            position: static;
        }
    }
</style>

<section class="browse-hero">
    <div class="container">
        <div class="text-center">
            @if(!empty($breadcrumbs))
                <nav class="browse-breadcrumb" aria-label="Breadcrumb">
                    @foreach($breadcrumbs as $breadcrumb)
                        @if(!$loop->last)
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['title'] }}</a>
                            <span>/</span>
                        @else
                            <span>{{ $breadcrumb['title'] }}</span>
                        @endif
                    @endforeach
                </nav>
            @endif
            <h1 class="browse-title">{{ $title ?? 'Browse Exams' }}</h1>
            <p class="browse-copy">{{ $subHeading ?? $browseSubtitle }}</p>
        </div>
    </div>
</section>

@include('website.partials.standalone-pdf-papers')

<section style="background: var(--theme-body-bg, #ffffff); padding: 26px 0 56px;">
    <div class="container">
        <div class="{{ $showBrowseLeaderboard ? 'browse-layout' : '' }}">
            <div>
        @if(isset($packages) && ($configuration_detail->show_package_tag_filters ?? true) && ($availableTags ?? collect())->isNotEmpty())
            <div class="browse-package-controls">
                <div class="browse-chip-row" aria-label="Package tag filters">
                    <a href="{{ $browseFilterUrl(['tag' => null]) }}" class="browse-tag-chip {{ request()->filled('tag') ? '' : 'is-active' }}">{{ __('website.course_all') }}</a>
                    @foreach(($availableTags ?? collect())->take(10) as $tag)
                        <a href="{{ $browseFilterUrl(['tag' => $tag->id]) }}" class="browse-tag-chip {{ (string) request('tag') === (string) $tag->id ? 'is-active' : '' }}">{{ $tag->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="row g-4">
            @if(isset($allGroups) && $currentLevel === 1)
                @forelse($allGroups as $groupIndex => $group)
                    @php
                        $examCount = $group->packages->sum(fn($pkg) => (int) ($pkg->exams_count ?? 0)) + (int) ($group->standalone_pdf_count ?? 0);
                        $packageCount = $group->packages->count();
                        $groupTitle = $labelText($group->group_name);
                        $groupIcons = ['ri-graduation-cap-line','ri-stethoscope-line','ri-tools-line','ri-government-line','ri-bank-line','ri-book-open-line','ri-building-4-line','ri-briefcase-4-line'];
                        $groupTitleClass = mb_strlen($groupTitle) > 42 ? 'is-very-long' : (mb_strlen($groupTitle) > 27 ? 'is-long' : '');
                    @endphp
                    <div class="col-12 col-sm-6 {{ $groupItemColumn }}">
                        <a href="{{ route('website.exams.index', ['group' => Str::slug($groupTitle)]) }}" class="browse-card-link">
                            <article class="d-flex h-100 align-items-center gap-3 p-3 p-lg-4" style="background:var(--theme-card-bg,#fff);border:1px solid color-mix(in srgb,var(--theme-primary,#0f766e) 18%,#dbe5e3);border-radius:16px;box-shadow:0 8px 24px rgba(15,23,42,.05);transition:transform .2s ease,box-shadow .2s ease;">
                                <span class="browse-home-step-icon flex-shrink-0"><i class="{{ $groupIcons[$groupIndex % count($groupIcons)] }}"></i></span>
                                <span class="min-w-0 flex-grow-1">
                                    <strong class="d-block text-truncate" style="font-size:1.05rem;color:var(--theme-heading,#0f172a);">{{ $groupTitle }}</strong>
                                    <small style="color:var(--theme-text,#64748b);">{{ $packageCount }} packages @if($examCount) &middot; {{ $examCount }} exams @endif</small>
                                </span>
                                <i class="ri-arrow-right-line flex-shrink-0" style="color:var(--theme-primary,#0f766e);font-size:1.25rem;"></i>
                            </article>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="browse-empty text-center">
                            <i class="ri-folder-open-line" style="font-size:54px;color:var(--theme-primary);"></i>
                            <h2 style="font-size:1.25rem;font-weight:900;margin-top:12px;">{{ __('ui.no_groups_available') }}</h2>
                            <p class="text-muted mb-0">{{ __('ui.check_back_soon') }}</p>
                        </div>
                    </div>
                @endforelse
            @endif

            @if(isset($categoryLevel1) && $currentLevel === 2)
                @foreach($categoryLevel1 as $categoryIndex => $catLevel1)
                    @php
                        $sourcePackages = isset($group) ? $group->packages : ($categoryPackages ?? collect());
                        $matchingPackages = $sourcePackages->filter(fn($pkg) => (int) $pkg->category_level_1 === (int) $catLevel1->id);
                        $examCount = $matchingPackages->sum(fn($pkg) => (int) ($pkg->exams_count ?? 0));
                        $categoryIcons = ['ri-calculator-line', 'ri-microscope-line', 'ri-government-line', 'ri-briefcase-4-line', 'ri-building-4-line', 'ri-graduation-cap-line'];
                    @endphp
                    <div class="col-12 col-sm-6 {{ $browseItemColumn }}">
                        <a href="{{ route('website.exams.index', isset($group) ? ['group' => Str::slug($labelText($group->group_name)), 'category' => $catLevel1->slug] : ['group' => 'all', 'category' => $catLevel1->slug]) }}" class="browse-card-link">
                            <article class="browse-category-card">
                                <span class="browse-category-icon"><i class="{{ $categoryIcons[$categoryIndex % count($categoryIcons)] }}"></i></span>
                                <div class="browse-group-content">
                                    <div class="browse-card-kicker">{{ isset($group) ? $labelText($group->group_name) : __('ui.exam_category') }}</div>
                                    <h2 class="browse-group-title">{{ $labelText($catLevel1->title) }}</h2>
                                    <div class="browse-group-meta">{{ $matchingPackages->count() }} packages &middot; {{ $examCount }} exams</div>
                                </div>
                                <i class="ri-arrow-right-line browse-group-arrow"></i>
                            </article>
                        </a>
                    </div>
                @endforeach
            @endif

            @if(isset($categoryLevel2) && $currentLevel === 3)
                @foreach($categoryLevel2 as $catLevel2)
                    @php
                        $sourcePackages = isset($group) ? $group->packages : ($categoryPackages ?? collect());
                        $matchingPackages = $sourcePackages->filter(function ($pkg) use ($catLevel2, $categoryModel) {
                            return (int) $pkg->category_level_1 === (int) $categoryModel->id
                                && (int) $pkg->category_level_2 === (int) $catLevel2->id;
                        });
                        $examCount = $matchingPackages->sum(fn($pkg) => (int) ($pkg->exams_count ?? 0));
                        $categoryRoute = isset($group)
                            ? ['group' => Str::slug($labelText($group->group_name)), 'category' => $categoryModel->slug, 'subcategory' => $catLevel2->slug]
                            : ['group' => 'all', 'category' => $categoryModel->slug, 'subcategory' => $catLevel2->slug];
                    @endphp
                    <div class="col-12 col-sm-6 {{ $browseItemColumn }}">
                        <a href="{{ route('website.exams.index', $categoryRoute) }}" class="browse-card-link">
                            <article class="browse-card">
                                <span class="browse-card-icon"><i class="ri-price-tag-3-line"></i></span>
                                <div>
                                    <div class="browse-card-kicker">{{ $labelText($categoryModel->title) }}</div>
                                    <h2 class="browse-card-title">{{ $labelText($catLevel2->title) }}</h2>
                                </div>
                                <div class="browse-card-stats">
                                    <span class="browse-stat"><i class="ri-file-copy-line"></i> {{ $examCount }} Exams</span>
                                    <span class="browse-stat"><i class="ri-box-line"></i> {{ __('ui.packages_count', ['count' => $matchingPackages->count()]) }}</span>
                                </div>
                                <span class="browse-action">{{ __('ui.explore') }} <i class="ri-arrow-right-line"></i></span>
                            </article>
                        </a>
                    </div>
                @endforeach
            @endif

            @if(isset($packages) && $currentLevel === 4)
                @forelse($packages as $package)
                    <div class="{{ $browseItemColumn }}">
                        @include('website.course_card', ['package' => $package, 'configuration_detail' => $configuration_detail])
                    </div>
                @empty
                    <div class="col-12">
                        <div class="browse-empty text-center">
                            <i class="ri-search-eye-line" style="font-size:54px;color:var(--theme-primary);"></i>
                            <h2 style="font-size:1.25rem;font-weight:900;margin-top:12px;">{{ __('ui.no_packages_found') }}</h2>
                            <p class="text-muted mb-3">{{ __('ui.try_group_category') }}</p>
                            <a href="{{ route('website.exams.index') }}" class="browse-action" style="max-width:220px;margin:0 auto;">{{ __('ui.browse_groups') }}</a>
                        </div>
                    </div>
                @endforelse
            @endif
        </div>

        @if(isset($packages) && $packages->hasPages())
            <div class="mt-5 d-flex justify-content-center courses-page-pagination">
                {{ $packages->links('pagination::bootstrap-5') }}
            </div>
        @endif
            </div>

            @if($showBrowseLeaderboard)
                <aside class="browse-leaderboard" data-leaderboard-panel="browse">
                    <h2 class="browse-leaderboard-title">{{ $title ?? 'Leaderboard' }} Leaderboard</h2>
                    @include('components.leaderboard-period-toggle', ['leaderboardPanelKey' => 'browse'])
                    <p class="mb-2" style="color:var(--theme-text, #64748b);font-size:12px;">{{ __('ui.best_scores_section') }}</p>
                    @forelse($browseLeaderboard as $index => $leader)
                        <div class="browse-leaderboard-row">
                            <span class="browse-leaderboard-rank">{{ $index + 1 }}</span>
                            <div>
                                <div class="browse-leaderboard-name">{{ $leader->name }}</div>
                                <div style="color:var(--theme-text, #64748b);font-size:11px;">{{ __('ui.best_score') }}</div>
                            </div>
                            <div class="browse-leaderboard-score">{{ number_format((float) $leader->best_percent, 1) }}%</div>
                        </div>
                    @empty
                        <div class="browse-leaderboard-empty">
                            <i class="ri-trophy-line"></i>
                            <span>{{ __('ui.no_scores_yet') }}</span>
                        </div>
                    @endforelse
                </aside>
            @endif
        </div>
    </div>
</section>
@endsection
