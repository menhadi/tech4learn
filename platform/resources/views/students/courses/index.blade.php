@extends('students.layouts.app')

@section('title') Buy Courses @endsection

@section('content')
@component('components.breadcrumb')
    @slot('li_1') Student @endslot
    @slot('title') Buy Courses @endslot
@endcomponent

@php
    $configuration_detail = getConfiguration();
    $currency = $configuration_detail->currency ?? '';
    $displayText = static function ($value): string {
        if (is_array($value)) {
            $candidate = $value['en'] ?? reset($value);
            return is_scalar($candidate) ? (string) $candidate : '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded['en'] ?? reset($decoded);
                return is_scalar($candidate) ? (string) $candidate : '';
            }
        }

        return is_scalar($value) ? (string) $value : '';
    };
@endphp

<style>
    .student-filter-bar { background: #fff; border: 1px solid var(--el-border); border-radius: 8px; padding: 18px; box-shadow: 0 4px 16px rgba(2,6,23,.05); }
    .student-filter-bar .form-label { color: var(--el-heading); font-weight: 700; font-size: 13px; }
    .student-filter-bar .form-control,
    .student-filter-bar .form-select { min-height: 46px; border-color: var(--el-border); color: var(--el-heading); font-size: 15px; }
    .student-filter-bar .form-control:focus,
    .student-filter-bar .form-select:focus { border-color: var(--el-primary); box-shadow: 0 0 0 .16rem rgba(var(--el-primary-rgb), .14); }
    .student-course-card { border-radius: 8px; background: #fff; transition: box-shadow .2s ease, transform .2s ease; box-shadow: 0 6px 18px rgba(2,6,23,.06); border: 1px solid var(--el-border); }
    .student-course-card:hover { transform: translateY(-3px); box-shadow: 0 14px 26px rgba(2,6,23,.10) !important; }
    .student-course-hero { background: var(--el-primary); min-height: 180px; display: flex; align-items: center; justify-content: center; color: #fff; position: relative; overflow: hidden; padding: 20px; }
    .student-course-hero h4 { font-size: 16px; line-height: 1.4; word-wrap: break-word; overflow-wrap: break-word; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
    .student-course-badge { padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
    .student-course-action { border-radius: 999px; padding: 11px; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-student-primary { background: var(--el-primary); border: 1px solid var(--el-primary); color: var(--theme-button-text, #fff); }
    .btn-student-primary:hover { background: var(--el-primary-dark, var(--el-primary)); border-color: var(--el-primary-dark, var(--el-primary)); color: var(--theme-button-text, #fff); }
    .btn-student-secondary { background: var(--el-secondary); border: 1px solid var(--el-secondary); color: var(--theme-button-text, #fff); }
    .btn-student-clear { background: var(--el-secondary); border: 1px solid var(--el-secondary); color: var(--theme-button-text, #fff); }
    .btn-student-clear:hover { background: var(--el-secondary-dark, var(--el-secondary)); border-color: var(--el-secondary-dark, var(--el-secondary)); color: var(--theme-button-text, #fff); }
    .btn-student-outline { background: #fff; border: 1px solid var(--el-primary); color: var(--el-primary); }
    .student-course-meta { border-top: 1px solid var(--el-border); color: var(--el-muted); font-size: 13px; }
    .student-course-state { color: var(--el-primary); }
    .pagination .page-link { color: var(--el-primary); border-color: var(--el-border); }
    .pagination .active .page-link { background: var(--el-primary); border-color: var(--el-primary); color: var(--theme-button-text, #fff); }
</style>
@include('components.package-card-styles')

<style>
    .student-package-grid {
        --theme-primary: var(--el-primary, #0f766e);
        --ew-primary: var(--el-primary, #0f766e);
        --theme-secondary: var(--el-secondary, #f59e0b);
        --ew-accent: var(--el-secondary, #f59e0b);
    }
    .student-package-grid .course-card-title-link { color: inherit; text-decoration: none; }
    .student-package-grid .course-card-title-link:hover { color: var(--el-primary); }
    .student-package-grid .course-card-footer form { margin: 0; }
    .student-package-grid .student-course-action { margin: 0; min-width: 168px; white-space: nowrap; }
    @media (max-width: 420px) {
        .student-package-grid .student-course-action,
        .student-package-grid .course-card-footer form { width: 100%; }
    }
</style>

<div class="student-filter-bar mb-4">
    <form method="GET" action="{{ route('student.courses.index') }}" class="row g-3 align-items-end">
        <div class="col-xl-4 col-lg-4 col-md-6">
            <label class="form-label mb-1">{{ __('ui.search') }}</label>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search courses">
        </div>
        <div class="col-xl-2 col-lg-2 col-md-6">
            <label class="form-label mb-1">{{ __('ui.group') }}</label>
            <select name="group" class="form-select" onchange="this.form.submit();">
                <option value="">{{ __('messages.dash_next_all_button') }}</option>
                @foreach($allGroups as $group)
                    <option value="{{ $group->id }}" {{ (int) $groupId === (int) $group->id ? 'selected' : '' }}>{{ $group->group_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-6">
            <label class="form-label mb-1">{{ __('messages.purchased_show_table_price') }}</label>
            <select name="price" class="form-select" onchange="this.form.submit();">
                @foreach(['all' => 'All', 'paid' => 'Paid', 'free' => 'Free'] as $value => $label)
                    <option value="{{ $value }}" {{ $price === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-6">
            <label class="form-label mb-1">{{ __('ui.sort') }}</label>
            <select name="sort" class="form-select" onchange="this.form.submit();">
                <option value="default" {{ $sort === 'default' ? 'selected' : '' }}>Default</option>
                <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
                <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
                <option value="price_low_high" {{ $sort === 'price_low_high' ? 'selected' : '' }}>Price Low-High</option>
                <option value="price_high_low" {{ $sort === 'price_high_low' ? 'selected' : '' }}>Price High-Low</option>
            </select>
        </div>
        <div class="col-xl-2 col-lg-2 col-md-12 d-flex gap-2">
            <button class="btn btn-student-primary flex-fill" type="submit">{{ __('ui.search') }}</button>
            <a href="{{ route('student.courses.index') }}" class="btn btn-student-clear flex-fill">{{ __('messages.exam_clear_button') }}</a>
        </div>
    </form>
</div>

<div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
    <div class="text-muted small">
        Showing {{ $packages->firstItem() ?? 0 }}-{{ $packages->lastItem() ?? 0 }} of {{ $packages->total() }} courses
    </div>
</div>

<div class="row g-4 student-package-grid">
    @forelse($packages as $package)
        @php
            $isOwned = $purchasedPackageIds->contains($package->id);
            $isNew = $package->created_at->gt(\Carbon\Carbon::now()->subDays(7));
            $isFree = strtolower((string) $package->package_type) === 'free';
            $hasDiscount = ! $isFree && !empty($package->discounted_amount) && $package->discounted_amount > 0 && $package->discounted_amount < $package->amount;
            $priceAmount = ($hasDiscount ? $package->discounted_amount : $package->amount) ?? 0;
            $packageName = $displayText($package->name);
            $packageSlug = $package->slug ?: $package->id;
            $groupName = $package->relationLoaded('groups') ? $displayText($package->groups->first()?->group_name) : '';
            $categoryName = $package->relationLoaded('category') ? $displayText($package->category?->title) : '';
            $subcategoryName = ($subcategoriesEnabled ?? true) && $package->relationLoaded('subcategory') ? $displayText($package->subcategory?->title) : '';
            $tags = $package->relationLoaded('tags') ? $package->tags : collect();
            $cardUrl = $isOwned ? route('student.myexams') : route('courses.detail', $packageSlug);
        @endphp

        <div class="col-xl-4 col-lg-6 col-md-6">
            <article class="course-card">
                <div class="course-card-head">
                    <div class="course-card-topline">
                        <span class="course-card-badge {{ $isFree ? 'course-card-badge-free' : 'course-card-badge-paid' }}">
                            {{ $isFree ? 'Free' : 'Paid' }}
                        </span>
                        @unless($isFree)
                            <span class="course-card-price">{{ $currency }}{{ number_format($priceAmount, 2) }}</span>
                        @endunless
                    </div>

                    <div>
                        @if($groupName || $categoryName || $subcategoryName)
                            <div class="course-card-hierarchy">
                                {{ collect([$groupName, $categoryName, $subcategoryName])->filter()->implode(' / ') }}
                            </div>
                        @endif
                        <a href="{{ $cardUrl }}" class="course-card-title-link">
                            <h3 class="course-card-title">{{ $packageName }}</h3>
                        </a>
                        <div class="course-card-counts">
                            <span class="course-card-exams">
                                <i class="ri-file-list-3-line"></i>
                                {{ $package->exams_count }} {{ \Illuminate\Support\Str::plural('Exam', $package->exams_count) }}
                            </span>
                        </div>
                        @if($tags->isNotEmpty() || $isNew || $hasDiscount)
                            <div class="course-card-tags">
                                @foreach($tags->take(3) as $tag)
                                    <span class="course-card-tag">{{ $tag->name }}</span>
                                @endforeach
                                @if($isNew)
                                    <span class="course-card-tag">{{ __('ui.new') }}</span>
                                @endif
                                @if($hasDiscount)
                                    <span class="course-card-tag">
                                        {{ round((($package->amount - $package->discounted_amount) / $package->amount) * 100) }}% off
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="course-card-footer">
                    <span class="course-card-validity">
                        <i class="{{ $isOwned ? 'ri-checkbox-circle-line' : 'ri-add-circle-line' }}"></i>
                        {{ $isOwned ? 'Added to My Exams' : 'Ready to add' }}
                    </span>

                    @if($isOwned)
                        <a href="{{ route('student.myexams') }}" class="btn student-course-action btn-student-secondary">
                            <i class="fas fa-play"></i> Go to My Exams
                        </a>
                    @else
                        <form method="POST" action="{{ route('student.courses.enroll', $package->id) }}">
                            @csrf
                            <button type="submit" class="btn student-course-action btn-student-secondary w-100">
                                <i class="fas {{ $isFree ? 'fa-play' : 'fa-shopping-cart' }}"></i>
                                {{ $isFree ? 'Take All Exams Free' : 'Buy Course' }}
                                <i class="fas fa-arrow-right" style="font-size: 11px;"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="ri-book-open-line d-block text-muted mb-2" style="font-size: 48px;"></i>
                    <h5>{{ __('ui.no_courses_found') }}</h5>
                    <p class="text-muted">{{ __('ui.different_filters') }}</p>
                    <a href="{{ route('student.courses.index') }}" class="btn btn-student-primary">Clear Filters</a>
                </div>
            </div>
        </div>
    @endforelse
</div>
<div class="mt-5 d-flex justify-content-center">
    {{ $packages->links('pagination::bootstrap-5') }}
</div>
@endsection
