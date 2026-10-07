@include('components.package-card-styles')

@php
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

    $packageName = $displayText($package->name);
    $packageSlug = $package->slug ?: $package->id;
    $examCount = $package->exams_count ?? ($package->relationLoaded('exams') ? $package->exams->count() : 0);
    $studyCardCount = 0;

    if (($package->flashcards_enabled ?? false) && method_exists($package, 'flashcardSets')) {
        $studyCardSets = $package->relationLoaded('flashcardSets')
            ? $package->flashcardSets
            : $package->flashcardSets()
                ->where('status', true)
                ->whereHas('cards', fn ($query) => $query->where('status', true))
                ->withCount(['cards' => fn ($query) => $query->where('status', true)])
                ->get();

        $studyCardCount = (int) $studyCardSets->sum(function ($set) {
            if (isset($set->cards_count)) {
                return (int) $set->cards_count;
            }

            return $set->relationLoaded('cards') ? $set->cards->where('status', true)->count() : 0;
        });
    }

    $group = $package->relationLoaded('groups') ? $package->groups->first() : null;
    $groupId = $group?->id;
    $groupName = $group ? $displayText($group->group_name) : null;
    $allowGuestExamAttempts = getConfiguration()->allow_guest_exam_attempts ?? true;
    $isPaid = $package->package_type === 'paid';
    $hasDiscount = $isPaid && !empty($package->discounted_amount) && $package->discounted_amount > 0 && $package->discounted_amount < $package->amount;
    $price = $hasDiscount ? $package->discounted_amount : ($package->amount ?? 0);
    $currency = is_object($configuration_detail ?? null)
        ? ($configuration_detail->currency ?? 'Rs. ')
        : (($configuration_detail['currency'] ?? null) ?: 'Rs. ');
    $tags = $package->relationLoaded('tags') ? $package->tags : collect();
    $categoryName = $package->relationLoaded('category') ? $displayText($package->category?->title) : '';
    $subcategoryName = ($subcategoriesEnabled ?? true) && $package->relationLoaded('subcategory') ? $displayText($package->subcategory?->title) : '';
@endphp

<article class="course-card">
    <a href="{{ route('courses.detail', $packageSlug) }}" class="course-card-head">
        <div class="course-card-topline">
            <span class="course-card-badge {{ $isPaid ? 'course-card-badge-paid' : 'course-card-badge-free' }}">{{ $isPaid ? 'Paid' : 'Free' }}</span>
            @if($isPaid)
                <span class="course-card-price">{{ $currency }}{{ number_format($price, 2) }}</span>
            @endif
        </div>

        <div>
            @if($categoryName || $subcategoryName)<div class="course-card-hierarchy">{{ collect([$categoryName, $subcategoryName])->filter()->implode(' / ') }}</div>@endif
            <h3 class="course-card-title">{{ $packageName }}</h3>
            <div class="course-card-counts">
                <span class="course-card-exams">
                    <i class="ri-file-list-3-line"></i> {{ $examCount }} {{ \Illuminate\Support\Str::plural('Exam', $examCount) }}
                </span>
                @if($studyCardCount > 0)
                    <span class="course-card-exams course-card-study-count">
                        <i class="ri-stack-line"></i> {{ $studyCardCount }} {{ \Illuminate\Support\Str::plural('Study Card', $studyCardCount) }}
                    </span>
                @endif
            </div>
            @if($tags->isNotEmpty())
                <div class="course-card-tags">
                    @foreach($tags->take(3) as $tag)
                        <span class="course-card-tag">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </a>

    <div class="course-card-footer">
        <span class="course-card-validity">
            <i class="ri-calendar-check-line"></i>
            {{ $package->expiry_days ?? 365 }} days access
        </span>
        <a href="{{ route('courses.detail', $packageSlug) }}" class="course-card-explore">
            {{ __('ui.explore_exams') }} <i class="ri-arrow-right-line"></i>
        </a>
    </div>
</article>
