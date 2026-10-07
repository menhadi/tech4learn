@extends('website.layouts.app')

@section('title', __('website.all_courses'))

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

    $selectedGroupName = request('group')
        ? optional($allGroups->firstWhere('id', (int) request('group')))->group_name
        : null;
    $selectedCategoryName = request('category')
        ? optional($allCategories->firstWhere('id', (int) request('category')))->title
        : null;
    $selectedSubcategoryName = request('subcategory')
        ? optional($childCategories->firstWhere('id', (int) request('subcategory')))->title
        : null;
    $selectedTagName = request('tag')
        ? optional(($availableTags ?? collect())->firstWhere('id', (int) request('tag')))->name
        : null;

    $activeFilters = collect([
        request('search') ? 'Search: ' . request('search') : null,
        $selectedGroupName ? 'Group: ' . $labelText($selectedGroupName) : null,
        $selectedCategoryName ? 'Category: ' . $labelText($selectedCategoryName) : null,
        $selectedSubcategoryName ? 'Subcategory: ' . $labelText($selectedSubcategoryName) : null,
        $selectedTagName ? 'Tag: ' . $selectedTagName : null,
        request('price') && request('price') !== 'all' ? ucfirst(request('price')) : null,
    ])->filter();

    $courseFilterUrl = function (array $changes = []) {
        $query = array_merge(request()->query(), $changes);
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return route('courses.index', $query);
    };

@endphp

<style>
    .courses-hero {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 4%, var(--theme-body-bg, #ffffff));
        padding: 26px 0 20px;
    }

    .courses-title {
        color: var(--theme-heading, #0f172a);
        font-size: clamp(1.7rem, 7vw, 3.1rem);
        font-weight: 900;
        line-height: 1.08;
        margin: 0 0 10px;
    }

    .courses-copy {
        color: var(--theme-text, #64748b);
        font-size: clamp(.95rem, 3vw, 1.08rem);
        max-width: 720px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .courses-filter-card {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 12%, #e2e8f0);
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, .06);
        padding: 20px;
        margin-top: 24px;
        position: relative;
    }

    .courses-filter-heading {
        align-items: center;
        display: flex;
        gap: 12px;
        margin-bottom: 18px;
    }

    .courses-filter-heading-icon {
        align-items: center;
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #ffffff);
        border-radius: 12px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        flex: 0 0 42px;
        font-size: 21px;
        height: 42px;
        justify-content: center;
    }

    .courses-filter-heading h2 {
        color: var(--theme-heading, #0f172a);
        font-size: 17px;
        font-weight: 800;
        line-height: 1.25;
        margin: 0 0 2px;
    }

    .courses-filter-heading p {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        line-height: 1.4;
        margin: 0;
    }

    .courses-filter-card.is-filtering::after {
        align-items: center;
        background: rgba(255,255,255,.78);
        border-radius: 14px;
        color: var(--theme-primary, #0f766e);
        content: "Updating packages...";
        display: flex;
        font-weight: 900;
        inset: 0;
        justify-content: center;
        position: absolute;
        z-index: 3;
    }

    .courses-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        align-items: end;
    }


    .courses-filter-primary {
        align-items: end;
        display: grid;
        gap: 10px;
        grid-template-columns: minmax(240px, 2fr) minmax(190px, 1fr) minmax(170px, 1fr) auto auto;
    }

    .courses-field label {
        color: var(--theme-text, #64748b);
        display: block;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 6px;
        text-transform: none;
        letter-spacing: 0;
    }

    .courses-control {
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 18%, #cbd5e1);
        border-radius: 12px;
        color: var(--theme-heading, #0f172a);
        min-height: 46px;
        padding: 0 13px;
        width: 100%;
        font-weight: 600;
        background-color: var(--theme-card-bg, #ffffff);
    }


    .courses-control:focus {
        border-color: var(--theme-primary, #0f766e);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-primary, #0f766e) 12%, transparent);
        outline: none;
    }

    .courses-filter-btn,
    .courses-reset-btn {
        align-items: center;
        border-radius: 11px;
        display: inline-flex;
        gap: 8px;
        font-weight: 800;
        justify-content: center;
        min-height: 46px;
        padding: 0 17px;
        text-decoration: none;
        white-space: nowrap;
    }

    .courses-filter-btn {
        background: var(--theme-primary, #0f766e);
        border: 1px solid var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        min-width: 124px;
    }

    .courses-reset-btn {
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 22%, #cbd5e1);
        color: var(--theme-text, #64748b);
    }

    .courses-reset-btn:hover,
    .courses-reset-btn:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, #ffffff);
        border-color: var(--theme-primary, #0f766e);
        color: var(--theme-primary, #0f766e);
        outline: none;
        text-decoration: none;
    }

    .courses-summary {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 3%, var(--theme-body-bg, #ffffff));
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 9%, #e2e8f0);
        border-radius: 10px;
        padding: 10px 12px;
    }

    .courses-result-count {
        color: var(--theme-text, #64748b);
        font-size: 13px;
        font-weight: 650;
    }

    .courses-count-pill {
        align-items: center;
        align-self: center;
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 28%, #dbe5e3);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 11px;
        font-weight: 800;
        gap: 6px;
        padding: 6px 10px;
        text-decoration: none;
        width: auto;
    }

    .courses-count-pill:hover,
    .courses-count-pill:focus,
    .courses-count-pill:active {
        background: var(--theme-primary, #0f766e);
        color: var(--theme-button-text, #ffffff);
        -webkit-text-fill-color: var(--theme-button-text, #ffffff);
        text-decoration: none;
    }

    .courses-chip {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 8%, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 20%, #ffffff);
        border-radius: 999px;
        color: var(--theme-primary, #0f766e);
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
    }

    .courses-empty {
        background: var(--theme-card-bg, #ffffff);
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 14%, #e2e8f0);
        border-radius: 14px;
        padding: 42px 18px;
    }

    @media (max-width: 1199.98px) {
        .courses-filter-primary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .courses-filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

    }

    @media (max-width: 767.98px) {
        .courses-filter-primary {
            grid-template-columns: 1fr;
        }

        .courses-filter-grid {
            grid-template-columns: 1fr;
        }


        .courses-filter-btn,
        .courses-reset-btn {
            width: 100%;
        }

        .courses-filter-card {
            border-radius: 14px;
            padding: 12px;
        }

        .courses-title,
        .courses-copy {
            text-align: left;
        }
    }

    /* Explore Packages filter toolbar */
    .courses-filter-layout {
        align-items: end;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .courses-filter-hierarchy,
    .courses-filter-primary {
        display: contents;
    }

    .courses-filter-hierarchy .courses-field {
        flex: 1 1 180px;
    }

    .courses-compact-field {
        flex: 1 1 155px;
        max-width: 190px;
    }

    .courses-filter-actions {
        display: flex;
        flex: 0 0 auto;
        gap: 8px;
    }

    .courses-tag-row {
        border-top: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 10%, #e2e8f0);
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 12px;
        padding-top: 12px;
    }

    .courses-tag-chip {
        background: transparent;
        border: 1px solid color-mix(in srgb, var(--theme-primary, #0f766e) 55%, #cbd5e1);
        border-radius: 999px;
        color: var(--theme-heading, #0f172a);
        display: inline-flex;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.4;
        padding: 8px 16px;
        text-decoration: none;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease;
    }

    .courses-tag-chip:hover,
    .courses-tag-chip:focus {
        background: color-mix(in srgb, var(--theme-primary, #0f766e) 6%, transparent);
        border-color: var(--theme-primary, #0f766e);
        color: var(--theme-primary, #0f766e);
        outline: none;
        text-decoration: none;
    }

    .courses-tag-chip.is-active {
        background: transparent;
        border-color: var(--theme-primary, #0f766e);
        color: var(--theme-primary, #0f766e);
        font-weight: 600;
    }

    @media (max-width: 767.98px) {
        .courses-filter-card {
            padding: 16px;
        }

        .courses-filter-heading {
            margin-bottom: 16px;
        }

        .courses-filter-layout {
            align-items: stretch;
            flex-direction: column;
        }

        .courses-filter-hierarchy .courses-field,
        .courses-compact-field {
            flex: 0 0 auto;
            max-width: none;
            min-width: 0;
            width: 100%;
        }

        .courses-filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        .courses-filter-btn,
        .courses-reset-btn {
            width: 100%;
        }

        .courses-summary {
            gap: 10px !important;
        }

        .courses-count-pill {
            align-self: flex-start;
        }
    }</style>

<section class="courses-hero">
    <div class="container">
        <div class="text-center">
            <h1 class="courses-title">{{ __('ui.explore_exam_packages') }}</h1>
            <p class="courses-copy">
                Search exam packages, previous year papers and mock tests. Pick a package, open papers, and start with fewer clicks.
            </p>
        </div>

        <form class="courses-filter-card js-courses-search" action="{{ route('courses.index') }}" method="GET">
            <div class="courses-filter-heading">
                <span class="courses-filter-heading-icon" aria-hidden="true"><i class="ri-equalizer-2-line"></i></span>
                <div>
                    <h2>{{ __('ui.find_right_package') }}</h2>
                    <p>{{ __('ui.package_filter_copy') }}</p>
                </div>
            </div>

            <div class="courses-filter-layout">
            @if($allGroups->isNotEmpty() || $allCategories->isNotEmpty() || $childCategories->isNotEmpty())
                <div class="courses-filter-hierarchy">
                    @if($allGroups->isNotEmpty())
                        <div class="courses-field">
                            <label for="groupFilter">{{ __('ui.group') }}</label>
                            <select id="groupFilter" class="courses-control" name="group">
                                <option value="">{{ __('ui.all_groups') }}</option>
                                @foreach($allGroups as $group)
                                    <option value="{{ $group->id }}" {{ (string) request('group') === (string) $group->id ? 'selected' : '' }}>
                                        {{ $labelText($group->group_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($allCategories->isNotEmpty())
                        <div class="courses-field">
                            <label for="categoryFilter">{{ __('ui.category') }}</label>
                            <select id="categoryFilter" class="courses-control" name="category">
                                <option value="">{{ __('ui.all_categories') }}</option>
                                @foreach($allCategories as $category)
                                    <option value="{{ $category->id }}" {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $labelText($category->title) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if($childCategories->isNotEmpty())
                        <div class="courses-field" data-subcategory-ui>
                            <label for="subcategoryFilter">{{ __('ui.subcategory') }}</label>
                            <select id="subcategoryFilter" class="courses-control" name="subcategory">
                                <option value="">{{ __('ui.all_subcategories') }}</option>
                                @foreach($childCategories as $category)
                                    <option value="{{ $category->id }}" {{ (string) request('subcategory') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $labelText($category->title) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            @endif

            <div class="courses-filter-primary">
                <div class="courses-field courses-compact-field">
                    <label for="sortSelect">{{ __('ui.sort_by') }}</label>
                    <select id="sortSelect" class="courses-control" name="sort">
                        <option value="default" {{ $sort === 'default' ? 'selected' : '' }}>{{ __('ui.latest') }}</option>
                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>{{ __('ui.name_az') }}</option>
                        <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>{{ __('ui.name_za') }}</option>
                        @if($hasPaidPackages ?? false)
                            <option value="price_low_high" {{ $sort === 'price_low_high' ? 'selected' : '' }}>{{ __('ui.price_low') }}</option>
                            <option value="price_high_low" {{ $sort === 'price_high_low' ? 'selected' : '' }}>{{ __('ui.price_high') }}</option>
                        @endif
                    </select>
                </div>

                @if($hasPaidPackages ?? false)
                    <div class="courses-field courses-compact-field">
                        <label for="priceFilter">{{ __('website.course_price') }}</label>
                        <select id="priceFilter" class="courses-control" name="price">
                            <option value="all" {{ request('price', 'all') === 'all' ? 'selected' : '' }}>{{ __('website.course_all') }}</option>
                            <option value="free" {{ request('price') === 'free' ? 'selected' : '' }}>{{ __('website.course_free') }}</option>
                            <option value="paid" {{ request('price') === 'paid' ? 'selected' : '' }}>{{ __('website.course_paid') }}</option>
                        </select>
                    </div>
                @endif

                <div class="courses-filter-actions">
                    <button class="courses-filter-btn" type="submit" id="coursesFilterBtn">
                        <i class="ri-search-line"></i> {{ __('website.search') }}
                    </button>
                    <a href="{{ route('courses.index') }}" class="courses-reset-btn"><i class="ri-refresh-line"></i> {{ __('ui.clear') }}</a>
                </div>
            </div>
            </div>

            @if(($configuration_detail->show_package_tag_filters ?? true) && ($availableTags ?? collect())->isNotEmpty())
                <div class="courses-tag-row" aria-label="Package tags">
                    <a href="{{ $courseFilterUrl(['tag' => null]) }}" class="courses-tag-chip {{ request()->filled('tag') ? '' : 'is-active' }}">{{ __('website.course_all') }}</a>
                    @foreach($availableTags as $tag)
                        <a href="{{ $courseFilterUrl(['tag' => $tag->id]) }}" class="courses-tag-chip {{ (string) request('tag') === (string) $tag->id ? 'is-active' : '' }}">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </form>
    </div>
</section>

<section class="section" style="background: var(--theme-body-bg, #ffffff); padding: 26px 0 56px;">
    <div class="container">
        <div class="courses-summary d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <p class="courses-result-count mb-1">
                    {{ __('website.course_showing') }}
                    <strong style="color: var(--theme-primary);">{{ $packages->firstItem() ?? 0 }}</strong>
                    to
                    <strong style="color: var(--theme-primary);">{{ $packages->lastItem() ?? 0 }}</strong>
                    {{ __('website.course_of') }}
                    <strong>{{ $packages->total() }}</strong>
                    packages
                </p>
                @if($activeFilters->isNotEmpty())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($activeFilters as $filter)
                            <span class="courses-chip">{{ $filter }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <a href="{{ route('website.exams.index') }}" class="courses-count-pill">
                <i class="ri-compass-3-line"></i> Browse by Group
            </a>
        </div>

        <div class="row g-4">
            @forelse ($packages as $package)
                <div class="col-xl-4 col-lg-4 col-md-6">
                    @include('website.course_card', ['package' => $package, 'configuration_detail' => $configuration_detail])
                </div>
            @empty
                <div class="col-12">
                    <div class="courses-empty text-center">
                        <i class="ri-search-eye-line" style="font-size:54px;color:var(--theme-primary);"></i>
                        <h2 style="font-size:1.25rem;font-weight:900;margin-top:12px;">{{ __('ui.no_packages_found') }}</h2>
                        <p class="text-muted mb-3">{{ __('ui.package_search_empty') }}</p>
                        <a href="{{ route('courses.index') }}" class="courses-filter-btn">{{ __('website.course_clear_filters_btn') }}</a>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="mt-5 d-flex justify-content-center courses-page-pagination">
            {{ $packages->links('pagination::bootstrap-5') }}
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.courses-filter-card');
        if (!form) return;


        let submitTimer = null;
        const submitWithDelay = function (delay = 0) {
            window.clearTimeout(submitTimer);
            submitTimer = window.setTimeout(function () {
                form.classList.add('is-filtering');
                form.submit();
            }, delay);
        };

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                submitWithDelay(0);
            });
        });

        form.addEventListener('submit', function () {
            form.classList.add('is-filtering');
        });
    });
</script>
@endpush
