<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\FlashcardQuestionAttempt;
use App\Models\Package;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FlashcardQuestionRotationService
{
    public function attachDisplayQuestions($cards, ?int $studentId = null, ?string $guestId = null, ?int $organizationId = null): void
    {
        collect($cards)->each(function (Flashcard $card) use ($studentId, $guestId, $organizationId) {
            $card->setRelation('displayQuestion', $this->chooseForCard($card, $studentId, $guestId, $organizationId));
        });
    }

    public function chooseForCard(Flashcard $card, ?int $studentId = null, ?string $guestId = null, ?int $organizationId = null): ?Question
    {
        $scopedQuery = $this->scopedQuestionQuery($card, $organizationId);
        $scopedCount = $scopedQuery ? (clone $scopedQuery)->count() : 0;

        if ($scopedCount > 0) {
            $card->setAttribute('display_question_pool_count', $scopedCount);

            return $this->chooseFromScopedQuery($card, $scopedQuery, $studentId, $guestId, $organizationId, $scopedCount);
        }

        $questions = $this->linkedQuestions($card);
        $card->setAttribute('display_question_pool_count', $questions->count());

        if (! $this->smartRotationEnabled($organizationId) || $questions->count() === 1 || (! $studentId && ! $guestId)) {
            return $this->sortByDifficulty($questions)->first();
        }

        $ids = $questions->pluck('id')->filter()->values();

        $attempts = FlashcardQuestionAttempt::query()
            ->where('flashcard_id', $card->id)
            ->whereIn('question_id', $ids)
            ->when($studentId, fn ($query) => $query->where('student_id', $studentId))
            ->when(! $studentId && $guestId, fn ($query) => $query->where('guest_id', $guestId))
            ->get()
            ->keyBy('question_id');

        $unseen = $questions->reject(fn ($question) => $attempts->has($question->id));
        if ($unseen->isNotEmpty()) {
            return $this->sortByDifficulty($unseen)->first();
        }

        $needsReview = $questions->filter(function ($question) use ($attempts) {
            $attempt = $attempts->get($question->id);
            return $attempt && ! $attempt->last_is_correct;
        });

        if ($needsReview->isNotEmpty()) {
            return $this->sortByDifficulty($needsReview)->sortBy(function ($question) use ($attempts) {
                $attempt = $attempts->get($question->id);
                return [
                    $attempt?->attempts ?? 0,
                    optional($attempt?->last_attempted_at)->timestamp ?? 0,
                ];
            })->first();
        }

        return $this->sortByDifficulty($questions)->sortBy(function ($question) use ($attempts) {
            $attempt = $attempts->get($question->id);
            return [
                $attempt?->attempts ?? 0,
                optional($attempt?->last_attempted_at)->timestamp ?? 0,
            ];
        })->first();
    }

    public function recordAttempt(Flashcard $card, int $questionId, bool $correct, ?int $studentId = null, ?string $guestId = null, ?int $organizationId = null): void
    {
        if (! $this->questionBelongsToCard($card, $questionId, $organizationId)) {
            return;
        }

        $lookup = [
            'flashcard_id' => $card->id,
            'question_id' => $questionId,
            'student_id' => $studentId,
            'guest_id' => $studentId ? null : $guestId,
        ];

        $attempt = FlashcardQuestionAttempt::firstOrNew($lookup);
        $attempt->organization_id = $organizationId;
        $attempt->attempts = (int) $attempt->attempts + 1;
        $attempt->correct_attempts = (int) $attempt->correct_attempts + ($correct ? 1 : 0);
        $attempt->wrong_attempts = (int) $attempt->wrong_attempts + ($correct ? 0 : 1);
        $attempt->last_is_correct = $correct;
        $attempt->last_attempted_at = now();
        $attempt->save();
    }

    private function smartRotationEnabled(?int $organizationId): bool
    {
        if (! Schema::hasTable('configurations') || ! Schema::hasColumn('configurations', 'study_card_question_rotation_enabled')) {
            return true;
        }

        $query = Configuration::query();

        if (Schema::hasColumn('configurations', 'organization_id')) {
            $query->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                ->when(! $organizationId, fn ($query) => $query->whereNull('organization_id'));
        }

        $configuration = $query->first();

        return (bool) ($configuration?->study_card_question_rotation_enabled ?? true);
    }

    private function chooseFromScopedQuery(Flashcard $card, Builder $query, ?int $studentId, ?string $guestId, ?int $organizationId, int $poolCount): ?Question
    {
        if (! $this->smartRotationEnabled($organizationId) || (! $studentId && ! $guestId)) {
            return $this->orderedQuestionAtOffset($query, $this->deterministicOffset($card, $guestId, $poolCount));
        }

        $attempts = FlashcardQuestionAttempt::query()
            ->where('flashcard_id', $card->id)
            ->when($studentId, fn ($attemptQuery) => $attemptQuery->where('student_id', $studentId))
            ->when(! $studentId && $guestId, fn ($attemptQuery) => $attemptQuery->where('guest_id', $guestId))
            ->get()
            ->keyBy('question_id');

        $attemptedIds = $attempts->keys()->filter()->values()->all();
        $unseenQuery = clone $query;

        if ($attemptedIds) {
            $unseenQuery->whereNotIn('questions.id', $attemptedIds);
        }

        $unseenCount = (clone $unseenQuery)->count();
        $unseen = $this->orderedQuestionAtOffset(
            $unseenQuery,
            $this->deterministicOffset($card, (string) ($studentId ?: $guestId), $unseenCount)
        );

        if ($unseen) {
            return $unseen;
        }

        $reviewIds = $attempts
            ->filter(fn ($attempt) => ! $attempt->last_is_correct)
            ->sortBy(fn ($attempt) => [
                (int) $attempt->attempts,
                optional($attempt->last_attempted_at)->timestamp ?? 0,
            ])
            ->keys()
            ->values();

        foreach ($reviewIds as $questionId) {
            $question = (clone $query)->whereKey($questionId)->first();
            if ($question) {
                return $question;
            }
        }

        $oldestIds = $attempts
            ->sortBy(fn ($attempt) => [
                (int) $attempt->attempts,
                optional($attempt->last_attempted_at)->timestamp ?? 0,
            ])
            ->keys()
            ->values();

        foreach ($oldestIds as $questionId) {
            $question = (clone $query)->whereKey($questionId)->first();
            if ($question) {
                return $question;
            }
        }

        return $this->firstOrderedQuestion($query);
    }

    private function firstOrderedQuestion(Builder $query): ?Question
    {
        return $this->orderedQuestionQuery($query)->first();
    }

    private function orderedQuestionAtOffset(Builder $query, int $offset): ?Question
    {
        $orderedQuery = $this->orderedQuestionQuery($query);

        return (clone $orderedQuery)->offset(max(0, $offset))->limit(1)->first()
            ?: $orderedQuery->first();
    }

    private function orderedQuestionQuery(Builder $query): Builder
    {
        $query = clone $query;

        if (Schema::hasTable('diffs') && Schema::hasColumn('questions', 'diff_id')) {
            $query
                ->leftJoin('diffs as flashcard_rotation_diffs', 'questions.diff_id', '=', 'flashcard_rotation_diffs.id')
                ->select('questions.*')
                ->orderByRaw("
                    CASE
                        WHEN LOWER(COALESCE(flashcard_rotation_diffs.diff_level, '')) LIKE '%easy%' THEN 1
                        WHEN LOWER(COALESCE(flashcard_rotation_diffs.diff_level, '')) LIKE '%hard%' THEN 3
                        ELSE 2
                    END
                ");
        }

        return $query->orderBy('questions.id');
    }

    private function deterministicOffset(Flashcard $card, ?string $viewerKey, int $poolCount): int
    {
        if ($poolCount < 2) {
            return 0;
        }

        $setId = $card->flashcard_set_id ?: ($card->set?->id ?? 0);
        $scopeKey = $card->id . '|' . $setId . '|' . (string) $viewerKey;

        return abs(crc32($scopeKey)) % $poolCount;
    }

    private function questionBelongsToCard(Flashcard $card, int $questionId, ?int $organizationId): bool
    {
        $scopedQuery = $this->scopedQuestionQuery($card, $organizationId);

        if ($scopedQuery && (clone $scopedQuery)->exists()) {
            return (clone $scopedQuery)->whereKey($questionId)->exists();
        }

        return $this->linkedQuestions($card)->pluck('id')->contains($questionId);
    }

    private function scopedQuestionQuery(Flashcard $card, ?int $organizationId): ?Builder
    {
        $set = $card->relationLoaded('set') ? $card->set : $card->set()->first();

        if (! $set) {
            return null;
        }

        $scopeOrganizationId = $organizationId ?: $set->organization_id;

        $scopes = [];

        if ($set->stopic_id) {
            $scopes[] = fn (Builder $query) => $query->where('questions.stopic_id', $set->stopic_id);
        }

        if ($set->topic_id) {
            $scopes[] = fn (Builder $query) => $query->where('questions.topic_id', $set->topic_id);
        }

        if ($set->subject_id) {
            $scopes[] = fn (Builder $query) => $query->where('questions.subject_id', $set->subject_id);
        }

        if ($set->package_id) {
            $scopes[] = fn (Builder $query) => $this->wherePackage($query, (int) $set->package_id);
        }

        if ($set->category_level_2) {
            $scopes[] = fn (Builder $query) => $this->whereCategory($query, 'category_level_2', $set->category_level_2, $scopeOrganizationId);
        }

        if ($set->category_level_1) {
            $scopes[] = fn (Builder $query) => $this->whereCategory($query, 'category_level_1', $set->category_level_1, $scopeOrganizationId);
        }

        if ($set->group_id) {
            $scopes[] = fn (Builder $query) => $this->whereAnyGroup($query, collect([(int) $set->group_id]));
        }

        foreach ($scopes as $applyScope) {
            $query = $this->baseQuestionQuery($scopeOrganizationId);
            $applyScope($query);

            if ((clone $query)->count() > 0) {
                return $query;
            }
        }

        return null;
    }

    private function baseQuestionQuery(?int $organizationId): Builder
    {
        $query = Question::query()
            ->with([
                'qtype:id,type,question_type',
                'diff:id,diff_level',
                'subject:id,subject_name',
                'topic:id,name',
                'stopic:id,name',
            ])
            ->select('questions.*')
            ->distinct();

        $this->whereOrganization($query, $organizationId);
        $this->whereApproved($query);
        $this->whereObjective($query);

        return $query;
    }

    private function wherePackage(Builder $query, int $packageId): void
    {
        $query->whereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId));
    }

    private function whereOrganization(Builder $query, ?int $organizationId): void
    {
        if ($organizationId && Schema::hasColumn('questions', 'organization_id')) {
            $query->where('questions.organization_id', $organizationId);
        }
    }

    private function whereAnyGroup(Builder $query, Collection $groupIds): void
    {
        $groupIds = $groupIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($groupIds->isEmpty()) {
            return;
        }

        $query->where(function ($scope) use ($groupIds) {
            $scope
                ->whereHas('groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $groupIds))
                ->orWhereHas('exams.groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $groupIds))
                ->orWhereHas('exams.packages.groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $groupIds));
        });
    }

    private function whereApproved(Builder $query): void
    {
        if (! Schema::hasColumn('questions', 'status')) {
            return;
        }

        $query->where(function ($scope) {
            $scope
                ->whereNull('questions.status')
                ->orWhere('questions.status', 1)
                ->orWhere('questions.status', '1')
                ->orWhere('questions.status', 'Yes')
                ->orWhere('questions.status', 'yes')
                ->orWhere('questions.status', 'Active')
                ->orWhere('questions.status', 'active')
                ->orWhere('questions.status', true);
        });
    }

    private function whereObjective(Builder $query): void
    {
        $query->whereHas('qtype', function ($qtypeQuery) {
            $qtypeQuery
                ->where(function ($scope) {
                    $scope
                        ->whereNull('type')
                        ->orWhereRaw("LOWER(type) <> 's'");
                })
                ->where(function ($scope) {
                    $scope
                        ->whereNull('question_type')
                        ->orWhereRaw("LOWER(question_type) NOT LIKE '%subjective%'");
                });
        });
    }

    private function whereCategory(Builder $query, string $column, $categoryId, ?int $organizationId): void
    {
        if (! $categoryId) {
            return;
        }

        $categoryGroupIds = $this->groupIdsForCategory($column, $categoryId, $organizationId);

        $query->where(function ($scope) use ($column, $categoryId, $categoryGroupIds) {
            $hasCondition = false;

            if (Schema::hasColumn('questions', $column)) {
                $scope->where("questions.$column", $categoryId);
                $hasCondition = true;
            }

            if (Schema::hasColumn('exams', $column)) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('exams', fn ($examQuery) => $examQuery->where("exams.$column", $categoryId));
                $hasCondition = true;
            }

            if (Schema::hasColumn('packages', $column)) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('exams.packages', fn ($packageQuery) => $packageQuery->where("packages.$column", $categoryId));
                $hasCondition = true;
            }

            if ($categoryGroupIds->isNotEmpty()) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                $scope->{$method}('groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $categoryGroupIds));
                $scope->orWhereHas('exams.groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $categoryGroupIds));
                $scope->orWhereHas('exams.packages.groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $categoryGroupIds));
            }
        });
    }

    private function groupIdsForCategory(string $column, $categoryId, ?int $organizationId): Collection
    {
        $groupIds = collect();

        if (! $categoryId) {
            return $groupIds;
        }

        if (Schema::hasTable('packages') && Schema::hasColumn('packages', $column)) {
            $groupIds = $groupIds->merge(
                Package::query()
                    ->with('groups:id')
                    ->when($organizationId && Schema::hasColumn('packages', 'organization_id'), fn ($packageQuery) => $packageQuery->where('organization_id', $organizationId))
                    ->where($column, $categoryId)
                    ->get()
                    ->flatMap(fn ($package) => $package->groups->pluck('id'))
            );

            $groupIds = $groupIds->merge(
                Exam::query()
                    ->with('groups:id')
                    ->when($organizationId && Schema::hasColumn('exams', 'organization_id'), fn ($examQuery) => $examQuery->where('organization_id', $organizationId))
                    ->whereHas('packages', fn ($packageQuery) => $packageQuery->where($column, $categoryId))
                    ->get()
                    ->flatMap(fn ($exam) => $exam->groups->pluck('id'))
            );
        }

        if (Schema::hasTable('exams') && Schema::hasColumn('exams', $column)) {
            $groupIds = $groupIds->merge(
                Exam::query()
                    ->with('groups:id')
                    ->when($organizationId && Schema::hasColumn('exams', 'organization_id'), fn ($examQuery) => $examQuery->where('organization_id', $organizationId))
                    ->where($column, $categoryId)
                    ->get()
                    ->flatMap(fn ($exam) => $exam->groups->pluck('id'))
            );

            $groupIds = $groupIds->merge(
                Package::query()
                    ->with('groups:id')
                    ->when($organizationId && Schema::hasColumn('packages', 'organization_id'), fn ($packageQuery) => $packageQuery->where('organization_id', $organizationId))
                    ->whereHas('exams', fn ($examQuery) => $examQuery->where($column, $categoryId))
                    ->get()
                    ->flatMap(fn ($package) => $package->groups->pluck('id'))
            );
        }

        return $groupIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function linkedQuestions(Flashcard $card): Collection
    {
        $questions = collect();

        if ($card->relationLoaded('sourceQuestions')) {
            $questions = $questions->merge($card->sourceQuestions);
        }

        if ($card->relationLoaded('sourceQuestion') && $card->sourceQuestion) {
            $questions->push($card->sourceQuestion);
        }

        return $questions
            ->filter(fn ($question) => $question instanceof Question && $question->id)
            ->unique('id')
            ->values();
    }

    private function sortByDifficulty(Collection $questions): Collection
    {
        return $questions->sortBy(fn ($question) => [$this->difficultyRank($question), $question->id])->values();
    }

    private function difficultyRank(Question $question): int
    {
        $label = strtolower(trim((string) (
            $question->diff?->diff_level
            ?? $question->diff?->name
            ?? $question->difficulty
            ?? $question->diff_level
            ?? ''
        )));

        return match (true) {
            str_contains($label, 'easy') => 1,
            str_contains($label, 'hard') => 3,
            default => 2,
        };
    }
}
