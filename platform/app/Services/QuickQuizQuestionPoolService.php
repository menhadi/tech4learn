<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuickQuizQuestionPoolService
{
    public function __construct(private QuestionAnswerEvaluator $evaluator)
    {
    }

    public function query(array $filters, ?int $tenantId): Builder
    {
        $groupId = (int) $filters['group_id'];
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;
        $subjectId = isset($filters['subject_id']) ? (int) $filters['subject_id'] : null;
        $topicId = isset($filters['topic_id']) ? (int) $filters['topic_id'] : null;
        $subtopicId = isset($filters['stopic_id']) ? (int) $filters['stopic_id'] : null;
        $packageId = isset($filters['package_id']) ? (int) $filters['package_id'] : null;
        $pypOnly = ! empty($filters['pyp_only']);

        return Question::query()
            ->with(['qtype:id,type,question_type'])
            ->when($tenantId, fn (Builder $query, int $id) => $query->where('questions.organization_id', $id))
            ->where(fn (Builder $query) => $query->whereNull('questions.status')->orWhereIn('questions.status', ['Yes', 'YES', 'yes', '1', 1]))
            ->whereIn('questions.id', $this->questionIdsForGroup($groupId))
            ->when($packageId, fn (Builder $query, int $id) => $query->whereIn(
                'questions.id',
                $this->questionIdsForPackage($id, $pypOnly)
            ))
            ->when($categoryId, fn (Builder $query, int $id) => $query->whereIn(
                'questions.id',
                $this->questionIdsForCategory($id)
            ))
            ->when($subjectId, fn (Builder $query, int $id) => $this->applyTaxonomyFilter($query, $groupId, 'subject_id', $id))
            ->when($topicId, fn (Builder $query, int $id) => $this->applyTaxonomyFilter($query, $groupId, 'topic_id', $id))
            ->when($subtopicId, fn (Builder $query, int $id) => $this->applyTaxonomyFilter($query, $groupId, 'stopic_id', $id))
            ->where(function (Builder $query) {
                $query->whereIn(
                    'questions.qtype_id',
                    DB::table('qtypes')->select('id')->whereIn('type', ['M', 'T', 'F', 'B', 'NAT'])
                )
                    ->orWhereNotNull('correct_option_indices')
                    ->orWhereNotNull('true_false')
                    ->orWhereNotNull('fill_blank')
                    ->orWhereNotNull('nat_config');
            })
            ->whereNotNull('question');
    }

    public function categoryIdsForGroup(int $groupId): Collection
    {
        $examCategories = DB::table('exam_groups as quick_quiz_exam_groups')
            ->join('exams as quick_quiz_group_exams', 'quick_quiz_group_exams.id', '=', 'quick_quiz_exam_groups.exam_id')
            ->where('quick_quiz_exam_groups.group_id', $groupId)
            ->whereNotNull('quick_quiz_group_exams.category_level_1')
            ->whereExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('exam_questions as quick_quiz_group_exam_questions')
                ->whereColumn('quick_quiz_group_exam_questions.exam_id', 'quick_quiz_group_exams.id'))
            ->select('quick_quiz_group_exams.category_level_1 as category_id');

        $packageCategories = DB::table('package_groups as quick_quiz_package_groups')
            ->join('packages as quick_quiz_group_packages', 'quick_quiz_group_packages.id', '=', 'quick_quiz_package_groups.package_id')
            ->join('exam_packages as quick_quiz_group_exam_packages', 'quick_quiz_group_exam_packages.package_id', '=', 'quick_quiz_group_packages.id')
            ->where('quick_quiz_package_groups.group_id', $groupId)
            ->whereNotNull('quick_quiz_group_packages.category_level_1')
            ->whereExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('exam_questions as quick_quiz_package_exam_questions')
                ->whereColumn('quick_quiz_package_exam_questions.exam_id', 'quick_quiz_group_exam_packages.exam_id'))
            ->select('quick_quiz_group_packages.category_level_1 as category_id');

        return $examCategories
            ->union($packageCategories)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    public function dimensionIds(string $column, array $filters, ?int $tenantId): Collection
    {
        if (! in_array($column, ['subject_id', 'topic_id', 'stopic_id'], true)) {
            throw new \InvalidArgumentException('Unsupported quick quiz dimension.');
        }

        $groupId = (int) $filters['group_id'];
        $questionAlias = 'quick_quiz_dimension_questions';
        $direct = DB::table('question_groups as quick_quiz_dimension_question_groups')
            ->join("questions as {$questionAlias}", "{$questionAlias}.id", '=', 'quick_quiz_dimension_question_groups.question_id')
            ->where('quick_quiz_dimension_question_groups.group_id', $groupId);
        $exam = DB::table('exam_groups as quick_quiz_dimension_exam_groups')
            ->join('exam_questions as quick_quiz_dimension_exam_questions', 'quick_quiz_dimension_exam_questions.exam_id', '=', 'quick_quiz_dimension_exam_groups.exam_id')
            ->join("questions as {$questionAlias}", "{$questionAlias}.id", '=', 'quick_quiz_dimension_exam_questions.question_id')
            ->where('quick_quiz_dimension_exam_groups.group_id', $groupId);
        $package = DB::table('package_groups as quick_quiz_dimension_package_groups')
            ->join('exam_packages as quick_quiz_dimension_exam_packages', 'quick_quiz_dimension_exam_packages.package_id', '=', 'quick_quiz_dimension_package_groups.package_id')
            ->join('exam_questions as quick_quiz_dimension_package_questions', 'quick_quiz_dimension_package_questions.exam_id', '=', 'quick_quiz_dimension_exam_packages.exam_id')
            ->join("questions as {$questionAlias}", "{$questionAlias}.id", '=', 'quick_quiz_dimension_package_questions.question_id')
            ->where('quick_quiz_dimension_package_groups.group_id', $groupId);
        foreach ([$direct, $exam, $package] as $query) {

            $this->applyDimensionFilters($query, $questionAlias, $column, $filters, $tenantId);
        }

        return $direct
            ->union($exam)
            ->union($package)
            ->pluck('dimension_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    private function applyDimensionFilters(
        QueryBuilder $query,
        string $alias,
        string $column,
        array $filters,
        ?int $tenantId
    ): void {
        $query
            ->when($tenantId, fn (QueryBuilder $scope, int $id) => $scope->where("{$alias}.organization_id", $id))
            ->where(fn (QueryBuilder $scope) => $scope
                ->whereNull("{$alias}.status")
                ->orWhereIn("{$alias}.status", ['Yes', 'YES', 'yes', '1', 1]))
            ->whereNotNull("{$alias}.question")
            ->whereNotNull("{$alias}.{$column}")
            ->when($filters['subject_id'] ?? null, fn (QueryBuilder $scope, int $id) => $scope->where("{$alias}.subject_id", $id))
            ->when($filters['topic_id'] ?? null, fn (QueryBuilder $scope, int $id) => $scope->where("{$alias}.topic_id", $id))
            ->when($filters['stopic_id'] ?? null, fn (QueryBuilder $scope, int $id) => $scope->where("{$alias}.stopic_id", $id))
            ->when($filters['package_id'] ?? null, fn (QueryBuilder $scope, int $id) => $scope->whereIn(
                "{$alias}.id",
                $this->questionIdsForPackage($id, ! empty($filters['pyp_only']))
            ))
            ->when($filters['category_id'] ?? null, fn (QueryBuilder $scope, int $id) => $scope->where(function (QueryBuilder $category) use ($alias, $id) {
                $category->whereExists(fn (QueryBuilder $exam) => $exam
                    ->selectRaw('1')
                    ->from('exam_questions as quick_quiz_dimension_category_exam_questions')
                    ->join('exams as quick_quiz_dimension_category_exams', 'quick_quiz_dimension_category_exams.id', '=', 'quick_quiz_dimension_category_exam_questions.exam_id')
                    ->where('quick_quiz_dimension_category_exams.category_level_1', $id)
                    ->whereColumn('quick_quiz_dimension_category_exam_questions.question_id', "{$alias}.id"))
                    ->orWhereExists(fn (QueryBuilder $package) => $package
                        ->selectRaw('1')
                        ->from('exam_questions as quick_quiz_dimension_category_package_questions')
                        ->join('exam_packages as quick_quiz_dimension_category_exam_packages', 'quick_quiz_dimension_category_exam_packages.exam_id', '=', 'quick_quiz_dimension_category_package_questions.exam_id')
                        ->join('packages as quick_quiz_dimension_category_packages', 'quick_quiz_dimension_category_packages.id', '=', 'quick_quiz_dimension_category_exam_packages.package_id')
                        ->where('quick_quiz_dimension_category_packages.category_level_1', $id)
                        ->whereColumn('quick_quiz_dimension_category_package_questions.question_id', "{$alias}.id"));
            }))
            ->where(function (QueryBuilder $scope) use ($alias) {
                $scope->whereIn(
                    "{$alias}.qtype_id",
                    DB::table('qtypes')->select('id')->whereIn('type', ['M', 'T', 'F', 'B', 'NAT'])
                )
                    ->orWhereNotNull("{$alias}.correct_option_indices")
                    ->orWhereNotNull("{$alias}.true_false")
                    ->orWhereNotNull("{$alias}.fill_blank")
                    ->orWhereNotNull("{$alias}.nat_config");
            })
            ->select("{$alias}.{$column} as dimension_id");
    }

    public function pick(array $filters, ?int $tenantId, int $count): Collection
    {
        $batchSize = max(80, $count * 8);
        $maxQuestionId = max(1, (int) Question::query()->max('id'));
        $pivot = random_int(1, $maxQuestionId);
        $baseQuery = $this->query($filters, $tenantId);

        $questions = (clone $baseQuery)
            ->where('questions.id', '>=', $pivot)
            ->orderBy('questions.id')
            ->limit($batchSize)
            ->get();

        if ($questions->count() < $batchSize) {
            $questions = $questions->concat(
                (clone $baseQuery)
                    ->where('questions.id', '<', $pivot)
                    ->orderBy('questions.id')
                    ->limit($batchSize - $questions->count())
                    ->get()
            );
        }

        $selected = $questions
            ->unique('id')
            ->filter(fn (Question $question) => $this->hasUsableAnswer($question))
            ->shuffle()
            ->take($count)
            ->values();

        $selected->loadMissing(['subject:id,subject_name', 'topic:id,name']);

        return $selected;
    }

    private function hasUsableAnswer(Question $question): bool
    {
        return match ($this->evaluator->questionType($question)) {
            'multiple_choice_radio', 'multiple_choice_checkbox' => $this->evaluator->correctOptionIndices($question) !== [],
            'true_false' => filled($question->true_false),
            'fill_blank' => $this->evaluator->fillBlankRules($question) !== [],
            'nat' => $this->hasUsableNatRule($question),
            default => false,
        };
    }

    private function questionIdsForGroup(int $groupId): QueryBuilder
    {
        $direct = DB::table('question_groups as quick_quiz_question_groups')
            ->where('quick_quiz_question_groups.group_id', $groupId)
            ->select('quick_quiz_question_groups.question_id');

        $exam = DB::table('exam_groups as quick_quiz_exam_groups')
            ->join('exam_questions as quick_quiz_exam_questions', 'quick_quiz_exam_questions.exam_id', '=', 'quick_quiz_exam_groups.exam_id')
            ->where('quick_quiz_exam_groups.group_id', $groupId)
            ->select('quick_quiz_exam_questions.question_id');

        $package = DB::table('package_groups as quick_quiz_package_groups')
            ->join('exam_packages as quick_quiz_exam_packages', 'quick_quiz_exam_packages.package_id', '=', 'quick_quiz_package_groups.package_id')
            ->join('exam_questions as quick_quiz_package_questions', 'quick_quiz_package_questions.exam_id', '=', 'quick_quiz_exam_packages.exam_id')
            ->where('quick_quiz_package_groups.group_id', $groupId)
            ->select('quick_quiz_package_questions.question_id');

        return $direct->union($exam)->union($package);

    }

    private function questionIdsForCategory(int $categoryId): QueryBuilder
    {
        $exam = DB::table('exam_questions as quick_quiz_category_exam_questions')
            ->join('exams as quick_quiz_category_exams', 'quick_quiz_category_exams.id', '=', 'quick_quiz_category_exam_questions.exam_id')
            ->where('quick_quiz_category_exams.category_level_1', $categoryId)
            ->select('quick_quiz_category_exam_questions.question_id');

        $package = DB::table('exam_questions as quick_quiz_category_package_questions')
            ->join('exam_packages as quick_quiz_category_exam_packages', 'quick_quiz_category_exam_packages.exam_id', '=', 'quick_quiz_category_package_questions.exam_id')
            ->join('packages as quick_quiz_category_packages', 'quick_quiz_category_packages.id', '=', 'quick_quiz_category_exam_packages.package_id')
            ->where('quick_quiz_category_packages.category_level_1', $categoryId)
            ->select('quick_quiz_category_package_questions.question_id');

        return $exam->union($package);
    }

    private function questionIdsForPackage(int $packageId, bool $pypOnly = false): QueryBuilder
    {
        return DB::table('exam_packages as quick_quiz_selected_package')
            ->join('exams as quick_quiz_selected_exams', 'quick_quiz_selected_exams.id', '=', 'quick_quiz_selected_package.exam_id')
            ->join('exam_questions as quick_quiz_selected_questions', 'quick_quiz_selected_questions.exam_id', '=', 'quick_quiz_selected_exams.id')
            ->where('quick_quiz_selected_package.package_id', $packageId)
            ->where('quick_quiz_selected_exams.status', 'Active')
            ->when($pypOnly, fn (QueryBuilder $query) => $query->where(function (QueryBuilder $scope) {
                $scope->where('quick_quiz_selected_exams.test_type', 'previous_year')
                    ->orWhere('quick_quiz_selected_exams.category_level_1', 'previous_year_papers')
                    ->orWhere('quick_quiz_selected_exams.category_level_2', 'year_wise');
            }))
            ->select('quick_quiz_selected_questions.question_id');
    }

    private function hasUsableNatRule(Question $question): bool
    {
        $rule = $this->evaluator->natRule($question);

        return $rule['mode'] === 'range'
            ? filled($rule['min']) && filled($rule['max'])
            : filled($rule['value']);
    }

    private function applyTaxonomyFilter(Builder $query, int $groupId, string $column, int $value): void
    {
        if (! Schema::hasTable('question_taxonomies')) {
            $query->where("questions.{$column}", $value);

            return;
        }

        $query->where(function (Builder $scope) use ($groupId, $column, $value) {
            $scope->whereExists(fn (QueryBuilder $taxonomy) => $taxonomy
                ->selectRaw('1')
                ->from('question_taxonomies as quick_quiz_filter_taxonomy')
                ->whereColumn('quick_quiz_filter_taxonomy.question_id', 'questions.id')
                ->where('quick_quiz_filter_taxonomy.group_id', $groupId)
                ->where("quick_quiz_filter_taxonomy.{$column}", $value))
                ->orWhere(function (Builder $fallback) use ($groupId, $column, $value) {
                    $fallback->where("questions.{$column}", $value)
                        ->whereNotExists(fn (QueryBuilder $taxonomy) => $taxonomy
                            ->selectRaw('1')
                            ->from('question_taxonomies as quick_quiz_filter_taxonomy_fallback')
                            ->whereColumn('quick_quiz_filter_taxonomy_fallback.question_id', 'questions.id')
                            ->where('quick_quiz_filter_taxonomy_fallback.group_id', $groupId));
                });
        });
    }
}
