@if($leaders->isNotEmpty())
    @foreach($leaders as $index => $leader)
        <div class="guest-flashcards-leader">
            <span class="guest-flashcards-leader-rank rank-{{ $index + 1 }}">{{ $index + 1 }}</span>
            <div>
                <div class="guest-flashcards-leader-name">{{ $leader->student?->name ?: 'Student' }}</div>
                <div class="guest-flashcards-leader-meta">{{ __('ui.studied_correct_count', ['studied' => (int) $leader->cards_studied, 'correct' => (int) $leader->correct_answers]) }}</div>
            </div>
            <span class="guest-flashcards-leader-points">{{ (int) $leader->total_points }}</span>
        </div>
    @endforeach
@else
    <p class="text-muted mb-0">{{ __('ui.no_learners_period') }}</p>
@endif
