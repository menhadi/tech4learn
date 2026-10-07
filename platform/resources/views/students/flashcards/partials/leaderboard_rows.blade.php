@if($leaders->isNotEmpty())
    @foreach($leaders as $index => $leader)
        <div class="study-leader-row">
            <span class="study-leader-rank rank-{{ $index + 1 }}">{{ $index + 1 }}</span>
            <div>
                <div class="study-leader-name">{{ $leader->student?->name ?: 'Student' }}</div>
                <div class="study-leader-meta">{{ (int) $leader->cards_studied }} cards studied | {{ (int) $leader->correct_answers }} correct</div>
            </div>
            <span class="study-points-chip"><i class="mdi mdi-star-outline"></i>{{ (int) $leader->total_points }}</span>
        </div>
    @endforeach
@else
    <p class="text-muted mb-0">{{ __('ui.no_learners_period') }}</p>
@endif
