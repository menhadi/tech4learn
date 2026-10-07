<?php

namespace App\Services\GoogleSheets;

use App\Models\Category;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Models\Question;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class GoogleSheetResourceRegistry
{
    public function resources(): array
    {
        return $this->definitions();
    }

    public function definition(string $resource): array
    {
        $definitions = $this->definitions();
        abort_unless(isset($definitions[$resource]), 404, 'This admin resource is not available for Google Sheets sync.');

        return $definitions[$resource];
    }

    public function selectedFields(string $resource, array $fields): array
    {
        return array_values(array_intersect(array_keys($this->definition($resource)['fields']), array_values(array_unique($fields))));
    }

    public function filters(string $resource, array $input): array
    {
        return Arr::only($input, array_merge(['search'], $this->definition($resource)['filters']));
    }

    public function query(string $resource, int $organizationId, array $filters = []): Builder
    {
        $definition = $this->definition($resource);
        $model = $definition['model'];
        $query = $model::query();

        $query = match ($resource) {
            'topics', 'subtopics' => $query->whereHas('group', fn (Builder $group) => $group->where('organization_id', $organizationId)),
            'categories' => $query->where('organization_id', $organizationId)->whereNull('parent_id'),
            'subcategories' => $query->where('organization_id', $organizationId)->whereNotNull('parent_id'),
            default => $query->where('organization_id', $organizationId),
        };

        $this->applyFilters($query, $resource, $filters);
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $scope) use ($definition, $search) {
                foreach ($definition['search'] as $index => $column) {
                    $index === 0
                        ? $scope->where($column, 'like', '%'.$search.'%')
                        : $scope->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return $query->orderBy($definition['order'][0], $definition['order'][1]);
    }

    public function rules(string $resource, array $selectedFields): array
    {
        $fields = $this->definition($resource)['fields'];
        return collect($selectedFields)->mapWithKeys(fn ($field) => [$field => $fields[$field]['rules']])->all();
    }

    public function relatedValueBelongsToOrganization(string $field, mixed $value, int $organizationId): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $direct = [
            'group_id' => Group::class, 'subject_id' => Subject::class, 'test_subject_id' => Subject::class,
            'category_level_1' => Category::class, 'category_level_2' => Category::class, 'parent_id' => Category::class,
            'qtype_id' => Qtype::class, 'diff_id' => Diff::class, 'language_id' => Language::class,
        ];
        if (isset($direct[$field])) {
            $model = $direct[$field];
            $query = $model::query()->whereKey((int) $value);
            if (in_array($field, ['qtype_id', 'diff_id', 'language_id'], true)) {
                return $query->exists();
            }
            return $query->where('organization_id', $organizationId)->exists();
        }
        if (in_array($field, ['topic_id', 'test_topic_id'], true)) {
            return Topic::whereKey((int) $value)->whereHas('group', fn (Builder $q) => $q->where('organization_id', $organizationId))->exists();
        }
        if (in_array($field, ['stopic_id', 'test_stopic_id'], true)) {
            return Stopic::whereKey((int) $value)->whereHas('group', fn (Builder $q) => $q->where('organization_id', $organizationId))->exists();
        }

        return true;
    }

    private function applyFilters(Builder $query, string $resource, array $filters): void
    {
        $group = (int) ($filters['group_id'] ?? 0) ?: null;
        $category = (int) ($filters['category_id'] ?? 0) ?: null;
        $subcategory = (int) ($filters['subcategory_id'] ?? 0) ?: null;
        $package = (int) ($filters['package_id'] ?? 0) ?: null;
        $subject = (int) ($filters['subject_id'] ?? 0) ?: null;
        $topic = (int) ($filters['topic_id'] ?? 0) ?: null;
        $subtopic = (int) ($filters['subtopic_id'] ?? 0) ?: null;

        match ($resource) {
            'exams' => $query
                ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
                ->when($category, fn ($q) => $q->where(function ($s) use ($category) { $s->where('category_level_1', $category)->orWhereHas('packages', fn ($p) => $p->where('category_level_1', $category)); }))
                ->when($subcategory, fn ($q) => $q->where(function ($s) use ($subcategory) { $s->where('category_level_2', $subcategory)->orWhereHas('packages', fn ($p) => $p->where('category_level_2', $subcategory)); }))
                ->when($package, fn ($q) => $q->whereHas('packages', fn ($p) => $p->whereKey($package))),
            'subjects' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group))),
            'topics' => $query->when($group, fn ($q) => $q->where('group_id', $group))->when($subject, fn ($q) => $q->where('subject_id', $subject)),
            'subtopics' => $query->when($group, fn ($q) => $q->where('group_id', $group))->when($subject, fn ($q) => $q->where('subject_id', $subject))->when($topic, fn ($q) => $q->where('topic_id', $topic)),
            'questions' => $query
                ->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))
                ->when($category, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->where('category_level_1', $category)))
                ->when($subcategory, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->where('category_level_2', $subcategory)))
                ->when($package, fn ($q) => $q->whereHas('exams.packages', fn ($p) => $p->whereKey($package)))
                ->when($subject, fn ($q) => $q->where('subject_id', $subject))->when($topic, fn ($q) => $q->where('topic_id', $topic))->when($subtopic, fn ($q) => $q->where('stopic_id', $subtopic)),
            'categories' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group))),
            'subcategories' => $query->when($group, fn ($q) => $q->whereHas('parent.groups', fn ($g) => $g->whereKey($group)))->when($category, fn ($q) => $q->where('parent_id', $category)),
            'packages' => $query->when($group, fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($group)))->when($category, fn ($q) => $q->where('category_level_1', $category))->when($subcategory, fn ($q) => $q->where('category_level_2', $subcategory)),
            default => null,
        };
    }

    private function definitions(): array
    {
        $nullableId = ['nullable', 'integer', 'min:1'];
        $boolean = ['nullable', Rule::in([0, 1, '0', '1', true, false])];

        return [
            'exams' => [
                'title' => 'Exams', 'model' => Exam::class, 'search' => ['name'], 'order' => ['id', 'desc'],
                'filters' => ['group_id', 'category_id', 'subcategory_id', 'package_id'],
                'fields' => [
                    'name' => $this->field('Name', 'text', ['required', 'string', 'max:255']),
                    'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
                    'test_type' => $this->field('Test type', 'select', ['nullable', Rule::in(array_keys(Exam::testTypeLabels()))], Exam::testTypeLabels()),
                    'test_subject_id' => $this->field('Subject ID', 'number', $nullableId),
                    'test_topic_id' => $this->field('Topic ID', 'number', $nullableId),
                    'test_stopic_id' => $this->field('Subtopic ID', 'number', $nullableId),
                    'passing_percentage' => $this->field('Passing percentage', 'number', ['nullable', 'numeric', 'min:0', 'max:100']),
                    'duration' => $this->field('Duration (minutes)', 'number', ['nullable', 'integer', 'min:0', 'max:1440']),
                    'attempt_count' => $this->field('Attempt limit', 'number', ['nullable', 'integer', 'min:0']),
                    'start_date' => $this->field('Start date', 'datetime', ['nullable', 'date']),
                    'end_date' => $this->field('End date', 'datetime', ['nullable', 'date']),
                    'status' => $this->field('Status', 'select', ['required', Rule::in(['Active', 'Inactive'])], ['Active' => 'Active', 'Inactive' => 'Inactive']),
                    'offline_enabled' => $this->field('Offline mode', 'boolean', $boolean),
                    'show_answer_sheet' => $this->field('Show answer sheet', 'boolean', $boolean),
                    'negative_marking' => $this->field('Negative marking', 'boolean', $boolean),
                    'random_question' => $this->field('Random questions', 'boolean', $boolean),
                    'result_after_finish' => $this->field('Result after finish', 'boolean', $boolean),
                    'instant_result' => $this->field('Instant result', 'boolean', $boolean),
                    'option_shuffle' => $this->field('Shuffle options', 'boolean', $boolean),
                    'allow_answer_change' => $this->field('Allow answer change', 'boolean', $boolean),
                    'multi_language' => $this->field('Multiple languages', 'boolean', $boolean),
                    'calculator_allowed' => $this->field('Calculator allowed', 'boolean', $boolean),
                    'proctor' => $this->field('Proctoring', 'boolean', $boolean),
                    'tolerance_count' => $this->field('Tolerance count', 'number', ['nullable', 'integer', 'min:0']),
                ],
            ],
            'subjects' => $this->simple(Subject::class, 'Subjects', 'subject_name', [
                'subject_name' => $this->field('Subject name', 'text', ['required', 'string', 'max:255']),
                'ordering' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
            ], ['group_id']),
            'topics' => $this->simple(Topic::class, 'Topics', 'name', [
                'name' => $this->field('Topic name', 'text', ['required', 'string', 'max:255']),
                'group_id' => $this->field('Group ID', 'number', ['required', 'integer', 'min:1']),
                'subject_id' => $this->field('Subject ID', 'number', ['required', 'integer', 'min:1']),
                'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
            ], ['group_id', 'subject_id']),
            'subtopics' => $this->simple(Stopic::class, 'Subtopics', 'name', [
                'name' => $this->field('Subtopic name', 'text', ['required', 'string', 'max:255']),
                'group_id' => $this->field('Group ID', 'number', ['required', 'integer', 'min:1']),
                'subject_id' => $this->field('Subject ID', 'number', ['required', 'integer', 'min:1']),
                'topic_id' => $this->field('Topic ID', 'number', ['required', 'integer', 'min:1']),
                'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
            ], ['group_id', 'subject_id', 'topic_id']),
            'groups' => $this->simple(Group::class, 'Groups', 'group_name', [
                'group_name' => $this->field('Group name', 'text', ['required', 'string', 'max:255']),
                'slug' => $this->field('Slug', 'text', ['nullable', 'string', 'max:255']),
                'description' => $this->field('Description', 'textarea', ['nullable', 'string']),
                'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
            ]),
            'categories' => $this->categoryDefinition('Categories', false),
            'subcategories' => $this->categoryDefinition('Subcategories', true),
            'packages' => $this->simple(Package::class, 'Packages', 'name', [
                'name' => $this->field('Package name', 'text', ['required', 'string', 'max:255']),
                'slug' => $this->field('Slug', 'text', ['nullable', 'string', 'max:255']),
                'description' => $this->field('Description', 'textarea', ['nullable', 'string']),
                'amount' => $this->field('Price', 'number', ['nullable', 'numeric', 'min:0']),
                'discounted_amount' => $this->field('Discounted price', 'number', ['nullable', 'numeric', 'min:0']),
                'expiry_days' => $this->field('Validity days', 'number', ['nullable', 'integer', 'min:0']),
                'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
                'category_level_1' => $this->field('Category ID', 'number', $nullableId),
                'category_level_2' => $this->field('Subcategory ID', 'number', $nullableId),
                'status' => $this->field('Status', 'boolean', $boolean),
                'auto_enroll_on_registration' => $this->field('Auto enroll', 'boolean', $boolean),
                'flashcards_enabled' => $this->field('Flashcards', 'boolean', $boolean),
            ], ['group_id', 'category_id', 'subcategory_id']),
            'questions' => [
                'title' => 'Questions', 'model' => Question::class, 'search' => ['question_code', 'question'], 'order' => ['id', 'desc'],
                'filters' => ['group_id', 'category_id', 'subcategory_id', 'package_id', 'subject_id', 'topic_id', 'subtopic_id'],
                'fields' => [
                    'question_code' => $this->field('Question code', 'text', ['required', 'string', 'max:255']),
                    'question' => $this->field('Question', 'textarea', ['required', 'string']),
                    'qtype_id' => $this->field('Question type ID', 'number', ['required', 'integer', 'min:1']),
                    'subject_id' => $this->field('Subject ID', 'number', $nullableId),
                    'topic_id' => $this->field('Topic ID', 'number', $nullableId),
                    'stopic_id' => $this->field('Subtopic ID', 'number', $nullableId),
                    'diff_id' => $this->field('Difficulty ID', 'number', $nullableId),
                    'language_id' => $this->field('Language ID', 'number', $nullableId),
                    'option1' => $this->field('Option 1', 'textarea', ['nullable', 'string']),
                    'option2' => $this->field('Option 2', 'textarea', ['nullable', 'string']),
                    'option3' => $this->field('Option 3', 'textarea', ['nullable', 'string']),
                    'option4' => $this->field('Option 4', 'textarea', ['nullable', 'string']),
                    'option5' => $this->field('Option 5', 'textarea', ['nullable', 'string']),
                    'option6' => $this->field('Option 6', 'textarea', ['nullable', 'string']),
                    'correct_option_indices' => $this->field('Correct option positions', 'text', ['nullable', 'array']),
                    'si_answer1' => $this->field('Single/NAT answer', 'text', ['nullable', 'string']),
                    'marks' => $this->field('Marks', 'number', ['nullable', 'numeric']),
                    'negative_marks' => $this->field('Negative marks', 'number', ['nullable', 'numeric']),
                    'hint' => $this->field('Hint', 'textarea', ['nullable', 'string']),
                    'explanation' => $this->field('Explanation', 'textarea', ['nullable', 'string']),
                    'status' => $this->field('Status', 'text', ['nullable', 'string', 'max:50']),
                    'source_url' => $this->field('Source URL', 'url', ['nullable', 'url', 'max:2048']),
                    'source_reference' => $this->field('Source reference', 'text', ['nullable', 'string', 'max:255']),
                ],
            ],
        ];
    }

    private function field(string $label, string $type, array $rules, array $options = []): array
    {
        return compact('label', 'type', 'rules', 'options');
    }

    private function simple(string $model, string $title, string $search, array $fields, array $filters = []): array
    {
        return ['title' => $title, 'model' => $model, 'search' => [$search], 'order' => ['id', 'desc'], 'filters' => $filters, 'fields' => $fields];
    }

    private function categoryDefinition(string $title, bool $subcategory): array
    {
        $boolean = ['nullable', Rule::in([0, 1, '0', '1', true, false])];
        $fields = [
            'title' => $this->field($subcategory ? 'Subcategory title' : 'Category title', 'text', ['required', 'string', 'max:255']),
            'description' => $this->field('Description', 'textarea', ['nullable', 'string']),
            'slug' => $this->field('Slug', 'text', ['nullable', 'string', 'max:255']),
            'display_order' => $this->field('Display order', 'number', ['nullable', 'integer', 'min:0']),
            'status' => $this->field('Status', 'boolean', $boolean),
            'show_in_header' => $this->field('Show in header', 'boolean', $boolean),
            'header_display_order' => $this->field('Header order', 'number', ['nullable', 'integer', 'min:0']),
        ];
        if ($subcategory) {
            $fields = ['parent_id' => $this->field('Parent category ID', 'number', ['required', 'integer', 'min:1'])] + $fields;
        }
        return [
            'title' => $title, 'model' => Category::class, 'search' => ['title'], 'order' => ['id', 'desc'],
            'filters' => $subcategory ? ['group_id', 'category_id'] : ['group_id'], 'fields' => $fields,
        ];
    }
}
