<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\ExamQualitySource;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\QuestionTag;
use App\Models\QuestionSection;
use App\Models\ExamSection;
use App\Models\Qtype;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use App\Support\SaasAccess;
use App\Services\CurriculumTaxonomyService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\RemembersRowNumber;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionsImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithChunkReading
{
    use RemembersRowNumber;

    public int $importedCount = 0;
    public int $duplicateCount = 0;
    public int $createdRecordCount = 0;
    public int $updatedCount = 0;

    public function __construct(
        protected $subjectId,
        protected $topicId,
        protected $stopicId,
        protected array $groupIds,
        protected $organizationId = null,
        protected $categoryId = null,
        protected $subcategoryId = null,
        protected string $importMode = 'create',
    ) {
    }

    public function model(array $row)
    {
        if ($this->importMode === 'source_patch') return $this->patchSourcesOnly($row);
        if ($this->importMode === 'patch') return $this->patchExistingFields($row);
        if ($this->importMode === 'create') SaasAccess::abortIfLimitReached('questions');

        $groups = $this->resolveGroups($this->value($row, ['groups', 'group', 'group_name']));
        if ($groups->isEmpty()) {
            $groups = Group::query()->whereIn('id', $this->groupIds)->get();
        }
        if ($groups->isEmpty()) {
            $this->rowError('Provide at least one Group name in the sheet or select a default Group.');
        }
        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $category = $this->resolveCategory($this->value($row, ['category', 'category_name']), null, $groupIds)
            ?: ($this->categoryId ? Category::find($this->categoryId) : null);
        $subcategory = $this->resolveCategory($this->value($row, ['subcategory', 'sub_category', 'subcategory_name']), $category?->id, $groupIds)
            ?: ($this->subcategoryId ? Category::find($this->subcategoryId) : null);
        if ($subcategory && ! $category) {
            $this->rowError('Subcategory requires a Category.');
        }
        if ($subcategory && (int) $subcategory->parent_id !== (int) $category->id) {
            $this->rowError('Subcategory does not belong to the resolved Category.');
        }
        if ($category) $category->groups()->syncWithoutDetaching($groupIds);
        if ($subcategory) $subcategory->groups()->syncWithoutDetaching($groupIds);

        $subject = $this->resolveSubject($this->value($row, ['subject', 'subject_name']), $groupIds)
            ?: ($this->subjectId ? Subject::find($this->subjectId) : null);
        $section = $this->resolveSection($this->value($row, ['section', 'section_name']), $groupIds);
        $topic = $this->resolveTopic($this->value($row, ['topic', 'topic_name']), $subject?->id, $groupIds)
            ?: ($this->topicId ? Topic::find($this->topicId) : null);
        $subtopic = $this->resolveSubtopic($this->value($row, ['subtopic', 'sub_topic', 'stopic', 'subtopic_name']), $subject?->id, $topic?->id, $groupIds)
            ?: ($this->stopicId ? Stopic::find($this->stopicId) : null);

        if ($subject) $subject->groups()->syncWithoutDetaching($groupIds);

        if ($topic && ! $subject) {
            $this->rowError('Topic requires a Subject.');
        }
        if ($subtopic && ! $topic) {
            $this->rowError('Subtopic requires a Topic.');
        }
        app(CurriculumTaxonomyService::class)->validateSelection(
            (int) $this->organizationId, $groupIds, $subject?->id, $topic?->id, $subtopic?->id
        );

        $qtype = $this->resolveQuestionType($this->value($row, ['question_type', 'type']));
        $difficulty = $this->resolveDifficulty($this->value($row, ['difficulty_level', 'difficulty']));
        $language = $this->resolveLanguage($this->value($row, ['language', 'language_name', 'language_code']));
        $passage = $this->resolvePassage($this->value($row, ['passage', 'passage_name']));
        $questionText = trim((string) $this->value($row, ['question', 'question_text']));
        $questionCode = trim((string) $this->value($row, ['question_code', 'code']));

        if ($questionText === '') {
            $this->rowError('Question text is required.');
        }

        $matchedByCode = $questionCode !== '' ? Question::query()
            ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
            ->where('question_code', $questionCode)->first() : null;
        $existing = $this->importMode === 'create' ? null : $matchedByCode;

        if ($this->importMode === 'update' && ! $existing) {
            $this->rowError('Update mode requires an existing question_code.');
        }
        if ($this->importMode === 'upsert' && ! $existing) SaasAccess::abortIfLimitReached('questions');

        $isDuplicate = Question::query()
            ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
            ->where('subject_id', $subject?->id)
            ->where('question', $questionText)
            ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
            ->exists();

        $attributes = [
            'organization_id' => $this->organizationId,
            'qtype_id' => $qtype?->id,
            'subject_id' => $subject?->id,
            'question_section_id' => $section?->id,
            'topic_id' => $topic?->id,
            'stopic_id' => $subtopic?->id,
            'diff_id' => $difficulty?->id,
            'passage_id' => $passage?->id,
            'source_url' => $this->value($row, ['question_source_url', 'source_url']),
            'source_reference' => $this->value($row, ['question_source_reference', 'source_reference']),
            'question' => $questionText,
            'option1' => $this->value($row, ['option1', 'option_1']),
            'option2' => $this->value($row, ['option2', 'option_2']),
            'option3' => $this->value($row, ['option3', 'option_3']),
            'option4' => $this->value($row, ['option4', 'option_4']),
            'option5' => $this->value($row, ['option5', 'option_5']),
            'option6' => $this->value($row, ['option6', 'option_6']),
            'marks' => $this->value($row, ['marks']) ?: 1,
            'negative_marks' => $this->value($row, ['negative_marks']) ?: 0,
            'scoring_policy' => strtoupper((string) ($this->value($row, ['scoring_policy']) ?: 'NORMAL')),
            'hint' => $this->value($row, ['hint']),
            'explanation' => $this->value($row, ['explanation']),
            'answer' => $this->value($row, ['answer', 'correct_answer']),
            'true_false' => $this->value($row, ['true_false', 'truefalse']),
            'fill_blank' => $this->value($row, ['fill_blank', 'fill_in_the_blank']),
            'status' => $this->value($row, ['status']) ?: 'Yes',
            'si_answer1' => $this->value($row, ['si_answer1', 'si_answer_1', 'subjective_answer']),
        ];
        $rawCorrectIndices = $this->value($row, ['correct_option_indices', 'correct_options', 'correct_answers']);
        $correctIndices = $this->parseOptionIndices($rawCorrectIndices);
        if ($correctIndices === [] && blank($rawCorrectIndices)) {
            $correctIndices = collect(range(1, 6))
                ->filter(fn ($index) => filled($this->value($row, ['mi_answer'.$index, 'mi_answer_'.$index])))
                ->values()->all();
        }
        $attributes['correct_option_indices'] = $correctIndices;
        $qtypeCode = strtoupper(trim((string) $qtype?->type));
        if (in_array($qtypeCode, ['F', 'B'], true)) {
            $rawBlanks = trim((string) $this->value($row, ['fill_blank_answers', 'multiple_blank_answers']));
            if ($rawBlanks !== '') {
                $blanks = collect(preg_split('/\s*;;\s*/u', $rawBlanks, -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn ($blank) => ['answers' => array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/u', $blank, -1, PREG_SPLIT_NO_EMPTY))))])
                    ->filter(fn ($blank) => ! empty($blank['answers']))
                    ->values()
                    ->all();
                if ($blanks) {
                    $attributes['fill_blank_config'] = ['version' => 1, 'blanks' => $blanks];
                    $attributes['fill_blank'] = $blanks[0]['answers'][0];
                }
            }
        } elseif ($qtypeCode === 'NAT') {
            $mode = strtolower(trim((string) $this->value($row, ['nat_mode']))) ?: 'exact';
            $mode = in_array($mode, ['exact', 'range', 'tolerance'], true) ? $mode : 'exact';
            $config = ['version' => 1, 'mode' => $mode];
            if ($mode === 'range') {
                $rawMin = $this->value($row, ['nat_min']);
                $rawMax = $this->value($row, ['nat_max']);
                if ($rawMin === null || $rawMin === '' || $rawMax === null || $rawMax === '') $this->rowError('NAT range requires nat_min and nat_max.');
                $config['min'] = (float) $rawMin;
                $config['max'] = (float) $rawMax;
                if ($config['min'] > $config['max']) [$config['min'], $config['max']] = [$config['max'], $config['min']];
            } else {
                $rawValue = $this->value($row, ['nat_value', 'nat_exact_answer']);
                if ($rawValue === null || $rawValue === '') $this->rowError('NAT exact/tolerance mode requires nat_value.');
                $config['value'] = (float) $rawValue;
                if ($mode === 'tolerance') $config['tolerance'] = abs((float) $this->value($row, ['nat_tolerance']));
            }
            $attributes['nat_config'] = $config;
            $attributes['fill_blank'] = null;
        }
        if (! $existing && $this->importMode === 'upsert' && $questionCode !== '') $attributes['question_code'] = $questionCode;
        $question = $existing && in_array($this->importMode, ['update', 'upsert'], true)
            ? $existing->fill($attributes)
            : new Question($attributes);
        $question->save();
        $this->syncImportedLanguage($question,$language);
        if ($existing && in_array($this->importMode, ['update', 'upsert'], true)) $this->updatedCount++;
        $question->groups()->sync($groupIds);
        app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), $groupIds);

        $tagNames = $this->splitNames($this->value($row, ['tags', 'question_tags', 'tag']));
        if ($isDuplicate) {
            $tagNames[] = 'Duplicate';
            $this->duplicateCount++;
        }
        $tagIds = collect($tagNames)->filter()->unique(fn ($name) => $this->normalize($name))
            ->map(fn ($name) => $this->resolveTag($name)->id)->all();
        if ($tagIds || in_array($this->importMode, ['update', 'upsert'], true)) {
            $question->tags()->sync($tagIds);
        }

        $package = $this->resolvePackage($this->value($row, ['package', 'package_name']), $groupIds, $category?->id, $subcategory?->id);
        $examNames = $this->value($row, ['exams', 'exam', 'exam_name']);
        if (trim((string) $examNames) === '' && ($package || $category)) {
            $examNames = ($package?->name ?: $subcategory?->title ?: $category?->title) . ' - Imported Questions';
        }
        $exams = $this->resolveExams($examNames, $groupIds, $category?->id, $subcategory?->id, $package);
        $this->syncExamSourceUrls($exams, $row);
        $examPayload = $this->examQuestionPayload($question, $exams);
        if ($existing && in_array($this->importMode, ['update', 'upsert'], true)) {
            $question->exams()->sync($examPayload);
        } else {
            foreach ($exams as $exam) {
                $exam->questions()->syncWithoutDetaching([$question->id => $examPayload[$exam->id]]);
            }
        }

        $this->importedCount++;
        return $question;
    }

    private function resolveGroups($value)
    {
        $groups = collect();
        foreach ($this->splitNames($value) as $name) {
            $group = $this->findByName($this->groupQuery()->get(), 'group_name', $name);
            if (! $group) {
                $group = Group::create([
                    'organization_id' => $this->organizationId,
                    'group_name' => $name,
                    'slug' => $this->uniqueSlug(Group::class, $name),
                ]);
                $this->createdRecordCount++;
            }
            $groups->push($group);
        }
        return $groups->unique('id')->values();
    }

    private function resolveCategory($value, $parentId, array $groupIds): ?Category
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $query = Category::query()->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId));
        $category = $this->findByName($query->where('parent_id', $parentId)->get(), 'title', $name);
        if (! $category) {
            $category = Category::create([
                'organization_id' => $this->organizationId,
                'parent_id' => $parentId,
                'title' => $name,
                'slug' => $this->uniqueSlug(Category::class, $name, 'slug', $this->organizationId),
                'status' => 1,
            ]);
            $this->createdRecordCount++;
        }
        $category->groups()->syncWithoutDetaching($groupIds);
        return $category;
    }

    private function resolveSubject($value, array $groupIds): ?Subject
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $subject = $this->findByName(Subject::where('organization_id', $this->organizationId)->get(), 'subject_name', $name);
        if (! $subject) {
            $subject = Subject::create(['organization_id' => $this->organizationId, 'subject_name' => $name, 'ordering' => 0]);
            $this->createdRecordCount++;
        }
        $subject->groups()->syncWithoutDetaching($groupIds);
        return $subject;
    }

    private function resolveSection($value, array $groupIds): ?QuestionSection
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $section = QuestionSection::query()->where('organization_id', $this->organizationId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if (! $section) {
            $section = QuestionSection::create(['organization_id' => $this->organizationId, 'name' => $name, 'display_order' => 0, 'status' => true]);
            $this->createdRecordCount++;
        }
        $section->groups()->syncWithoutDetaching($groupIds);
        return $section;
    }

    private function examQuestionPayload(Question $question, $exams): array
    {
        $payload = [];
        $currentAssignments = \Illuminate\Support\Facades\DB::table('exam_questions')
            ->where('question_id', $question->id)->pluck('exam_section_id', 'exam_id');
        foreach ($exams as $exam) {
            if ($currentAssignments->has($exam->id)) {
                $payload[$exam->id] = ['exam_section_id' => $currentAssignments->get($exam->id)];
                continue;
            }
            $examSectionId = null;
            if ($question->question_section_id) {
                $definition = QuestionSection::find($question->question_section_id);
                $examSectionId = ExamSection::firstOrCreate(
                    ['exam_id' => $exam->id, 'question_section_id' => $definition?->id],
                    ['name' => $definition?->name ?: 'General', 'display_order' => $definition?->display_order ?: 0, 'duration' => null]
                )->id;
            }
            $payload[$exam->id] = ['exam_section_id' => $examSectionId];
        }
        return $payload;
    }
    private function resolveTopic($value, $subjectId, array $groupIds): ?Topic
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        if (! $subjectId || $groupIds === []) $this->rowError('A Subject and Group are required before Topic can be created.');
        $topics = collect($groupIds)->map(function ($groupId) use ($subjectId, $name) {
            $topic = $this->findByName(Topic::where('subject_id', $subjectId)->where('group_id', $groupId)->get(), 'name', $name);
            if (! $topic) {
                $topic = Topic::create(['subject_id' => $subjectId, 'group_id' => $groupId, 'name' => $name]);
                $this->createdRecordCount++;
            }
            return $topic;
        });
        return $topics->first();
    }

    private function resolveSubtopic($value, $subjectId, $topicId, array $groupIds): ?Stopic
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        if (! $subjectId || ! $topicId || $groupIds === []) $this->rowError('Subject, Group and Topic are required before Subtopic can be created.');
        $topicName = Topic::whereKey($topicId)->value('name');
        $subtopics = collect($groupIds)->map(function ($groupId) use ($subjectId, $topicName, $name) {
            $topic = $this->findByName(Topic::where('subject_id', $subjectId)->where('group_id', $groupId)->get(), 'name', $topicName);
            if (! $topic) $this->rowError('The selected topic is not available in every question group.');
            $subtopic = $this->findByName(Stopic::where('topic_id', $topic->id)->where('group_id', $groupId)->get(), 'name', $name);
            if (! $subtopic) {
                $subtopic = Stopic::create(['subject_id' => $subjectId, 'group_id' => $groupId, 'topic_id' => $topic->id, 'name' => $name]);
                $this->createdRecordCount++;
            }
            return $subtopic;
        });
        return $subtopics->first();
    }
    private function resolvePackage($value, array $groupIds, $categoryId, $subcategoryId): ?Package
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $packages = Package::query()->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId))->get();
        $package = $this->findByName($packages, 'name', $name);
        if (! $package) {
            $package = Package::create([
                'organization_id' => $this->organizationId,
                'name' => $name,
                'slug' => $this->uniqueSlug(Package::class, $name),
                'description' => 'Created from question import. Review before publishing.',
                'package_type' => 'free',
                'status' => false,
                'expiry_days' => 365,
                'category_level_1' => $categoryId,
                'category_level_2' => $subcategoryId,
            ]);
            $this->createdRecordCount++;
        }
        $package->groups()->syncWithoutDetaching($groupIds);
        return $package;
    }

    private function resolveExams($value, array $groupIds, $categoryId, $subcategoryId, ?Package $package)
    {
        $resolved = collect();
        foreach ($this->splitNames($value) as $name) {
            $query = Exam::query()->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId));
            $exam = $this->findByName($query->get(), 'name', $name);
            if (! $exam) {
                $exam = Exam::create([
                    'organization_id' => $this->organizationId,
                    'name' => $name,
                    'slug' => $this->uniqueSlug(Exam::class, $name),
                    'passing_percentage' => 0,
                    'duration' => 60,
                    'start_date' => now()->subMinute(),
                    'end_date' => now()->addYear(),
                    'mode' => 'Preparation',
                    'status' => 'Inactive',
                    'category_level_1' => $categoryId,
                    'category_level_2' => $subcategoryId,
                ]);
                $this->createdRecordCount++;
            }
            $exam->groups()->syncWithoutDetaching($groupIds);
            if ($package) $package->exams()->syncWithoutDetaching([$exam->id]);
            $resolved->push($exam);
        }
        return $resolved->unique('id')->values();
    }

    private function patchExistingFields(array $row): null
    {
        $questionCode = trim((string) $this->value($row, ['question_code', 'code']));
        if ($questionCode === '') $this->rowError('Patch update requires question_code.');

        $question = Question::query()
            ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
            ->where('question_code', $questionCode)
            ->with(['groups', 'exams', 'tags'])
            ->first();
        if (! $question) $this->rowError('No existing question matches question_code '.$questionCode.'.');

        $updates = [];
        $fields = [
            'question' => ['question', 'question_text'],
            'option1' => ['option1', 'option_1'], 'option2' => ['option2', 'option_2'],
            'option3' => ['option3', 'option_3'], 'option4' => ['option4', 'option_4'],
            'option5' => ['option5', 'option_5'], 'option6' => ['option6', 'option_6'],
            'marks' => ['marks'], 'negative_marks' => ['negative_marks'], 'scoring_policy' => ['scoring_policy'],
            'hint' => ['hint'], 'explanation' => ['explanation'],
            'answer' => ['answer', 'correct_answer'], 'true_false' => ['true_false', 'truefalse'],
            'fill_blank' => ['fill_blank', 'fill_in_the_blank'], 'status' => ['status'],

            'si_answer1' => ['si_answer1', 'si_answer_1', 'subjective_answer'],
            'source_url' => ['question_source_url', 'source_url'],
            'source_reference' => ['question_source_reference', 'source_reference'],
        ];
        foreach ($fields as $field => $aliases) {
            $value = $this->nonBlankValue($row, $aliases);
            if ($value !== null) $updates[$field] = $value;
        }
        $correctOptionValue = $this->nonBlankValue($row, ['correct_option_indices', 'correct_options', 'correct_answers']);
        if ($correctOptionValue !== null) {
            $updates['correct_option_indices'] = $this->parseOptionIndices($correctOptionValue);
        } else {
            $legacyCorrectIndices = collect(range(1, 6))
                ->filter(fn ($index) => $this->nonBlankValue($row, ['mi_answer'.$index, 'mi_answer_'.$index]) !== null)
                ->values()->all();
            if ($legacyCorrectIndices !== []) $updates['correct_option_indices'] = $legacyCorrectIndices;
        }
        if (isset($updates['source_url']) && ! filter_var($updates['source_url'], FILTER_VALIDATE_URL)) {
            $this->rowError('Question source must be a valid URL.');
        }

        $groupValue = $this->nonBlankValue($row, ['groups', 'group', 'group_name']);
        $groups = $question->groups;
        if ($groupValue !== null) {
            $groups = $this->resolveGroups($groupValue);
            if ($groups->isEmpty()) $this->rowError('At least one valid Group is required when patching groups.');
            $question->groups()->sync($groups->pluck('id')->all());
        }
        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $subjectValue = $this->nonBlankValue($row, ['subject', 'subject_name']);
        $subject = $subjectValue !== null ? $this->resolveSubject($subjectValue, $groupIds) : $question->subject;
        if ($subjectValue !== null) $updates['subject_id'] = $subject?->id;

        $sectionValue = $this->nonBlankValue($row, ['section', 'section_name']);
        if ($sectionValue !== null) $updates['question_section_id'] = $this->resolveSection($sectionValue, $groupIds)?->id;

        $topicValue = $this->nonBlankValue($row, ['topic', 'topic_name']);
        $topic = $topicValue !== null ? $this->resolveTopic($topicValue, $subject?->id, $groupIds) : $question->topic;
        if ($topicValue !== null) $updates['topic_id'] = $topic?->id;

        $subtopicValue = $this->nonBlankValue($row, ['subtopic', 'sub_topic', 'stopic', 'subtopic_name']);
        if ($subtopicValue !== null) $updates['stopic_id'] = $this->resolveSubtopic($subtopicValue, $subject?->id, $topic?->id, $groupIds)?->id;

        $typeValue = $this->nonBlankValue($row, ['question_type', 'type']);
        if ($typeValue !== null) $updates['qtype_id'] = $this->resolveQuestionType($typeValue)?->id;
        $difficultyValue = $this->nonBlankValue($row, ['difficulty_level', 'difficulty']);
        if ($difficultyValue !== null) $updates['diff_id'] = $this->resolveDifficulty($difficultyValue)?->id;
        $languageValue = $this->nonBlankValue($row, ['language', 'language_name', 'language_code']);
        $language = $languageValue !== null ? $this->resolveLanguage($languageValue) : null;
        $passageValue = $this->nonBlankValue($row, ['passage', 'passage_name']);
        if ($passageValue !== null) $updates['passage_id'] = $this->resolvePassage($passageValue)?->id;

        $blankAnswers = $this->nonBlankValue($row, ['fill_blank_answers', 'multiple_blank_answers']);
        if ($blankAnswers !== null) {
            $blanks = collect(preg_split('/\s*;;\s*/u', (string) $blankAnswers, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($blank) => ['answers' => array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/u', $blank, -1, PREG_SPLIT_NO_EMPTY))))])
                ->filter(fn ($blank) => ! empty($blank['answers']))->values()->all();
            if ($blanks) {
                $updates['fill_blank_config'] = ['version' => 1, 'blanks' => $blanks];
                $updates['fill_blank'] = $blanks[0]['answers'][0];
            }
        }

        $natMode = $this->nonBlankValue($row, ['nat_mode']);
        if ($natMode !== null) {
            $natMode = strtolower(trim((string) $natMode));
            if (! in_array($natMode, ['exact', 'range', 'tolerance'], true)) $this->rowError('NAT mode must be exact, range or tolerance.');
            $config = ['version' => 1, 'mode' => $natMode];
            if ($natMode === 'range') {
                $min = $this->nonBlankValue($row, ['nat_min']); $max = $this->nonBlankValue($row, ['nat_max']);
                if ($min === null || $max === null) $this->rowError('NAT range patch requires nat_min and nat_max.');
                $config['min'] = (float) $min; $config['max'] = (float) $max;
                if ($config['min'] > $config['max']) [$config['min'], $config['max']] = [$config['max'], $config['min']];
            } else {
                $value = $this->nonBlankValue($row, ['nat_value', 'nat_exact_answer']);
                if ($value === null) $this->rowError('NAT exact/tolerance patch requires nat_value.');
                $config['value'] = (float) $value;
                if ($natMode === 'tolerance') $config['tolerance'] = abs((float) ($this->nonBlankValue($row, ['nat_tolerance']) ?? 0));
            }
            $updates['nat_config'] = $config;
        }

        app(CurriculumTaxonomyService::class)->validateSelection(
            (int) $this->organizationId, $groupIds,
            (int) ($updates['subject_id'] ?? $question->subject_id) ?: null,
            (int) ($updates['topic_id'] ?? $question->topic_id) ?: null,
            (int) ($updates['stopic_id'] ?? $question->stopic_id) ?: null,
        );
        if ($updates !== []) $question->fill($updates)->save();
        $this->syncImportedLanguage($question,$language);
        app(CurriculumTaxonomyService::class)->syncQuestion($question->fresh(['topic', 'stopic']), $groupIds);

        $tagValue = $this->nonBlankValue($row, ['tags', 'question_tags', 'tag']);
        if ($tagValue !== null) {
            $tagIds = collect($this->splitNames($tagValue))->unique(fn ($name) => $this->normalize($name))->map(fn ($name) => $this->resolveTag($name)->id)->all();
            $question->tags()->sync($tagIds);
        }

        $examValue = $this->nonBlankValue($row, ['exams', 'exam', 'exam_name']);
        $exams = $question->exams;
        if ($examValue !== null) {
            $examNames = $this->splitNames($examValue);
            $exams = Exam::query()
                ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
                ->whereIn('name', $examNames)->get();
            if ($exams->count() !== count($examNames)) $this->rowError('Patch mode can link only existing exams. Check every exam name.');
            $question->exams()->sync($exams->pluck('id')->all());
        }
        if ($this->rowHasPaperSource($row) && $exams->isEmpty()) $this->rowError('A paper source requires an existing exam linked to the question.');
        $this->syncExamSourceUrls($exams, $row);

        if ($updates === [] && $groupValue === null && $tagValue === null && $examValue === null && ! $this->rowHasPaperSource($row)) {
            $this->rowError('No nonblank update value was supplied.');
        }
        $this->importedCount++;
        $this->updatedCount++;
        return null;
    }

    private function rowHasPaperSource(array $row): bool
    {
        return collect([
            $this->value($row, ['paper_question_source_url', 'exam_question_source_url']),
            $this->value($row, ['paper_answer_source_url', 'exam_answer_source_url']),
            $this->value($row, ['paper_combined_source_url', 'exam_combined_source_url']),
        ])->contains(fn ($value) => trim((string) $value) !== '');
    }

    private function nonBlankValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') return $row[$key];
        }
        return null;
    }
    private function patchSourcesOnly(array $row): null
    {
        $questionCode = trim((string) $this->value($row, ['question_code', 'code']));
        if ($questionCode === '') $this->rowError('Source-only update requires question_code.');

        $question = Question::query()
            ->when($this->organizationId, fn ($query) => $query->where('organization_id', $this->organizationId))
            ->where('question_code', $questionCode)
            ->with('exams')
            ->first();
        if (! $question) $this->rowError('No existing question matches question_code '.$questionCode.'.');

        $updates = [];
        $questionSourceUrl = trim((string) $this->value($row, ['question_source_url', 'source_url']));
        $questionSourceReference = trim((string) $this->value($row, ['question_source_reference', 'source_reference']));
        if ($questionSourceUrl !== '') {
            if (! filter_var($questionSourceUrl, FILTER_VALIDATE_URL)) $this->rowError('Question source must be a valid URL.');
            $updates['source_url'] = $questionSourceUrl;
        }
        if ($questionSourceReference !== '') $updates['source_reference'] = $questionSourceReference;
        if ($updates !== []) $question->forceFill($updates)->save();

        $examNames = $this->splitNames($this->value($row, ['exams', 'exam', 'exam_name']));
        $exams = $question->exams;
        if ($examNames !== []) {
            $normalizedNames = collect($examNames)->map(fn ($name) => $this->normalize($name));
            $exams = $exams->filter(fn ($exam) => $normalizedNames->contains($this->normalize($exam->name)))->values();
            if ($exams->isEmpty()) $this->rowError('None of the supplied exams are linked to this question.');
        }

        $hasPaperSource = collect([
            $this->value($row, ['paper_question_source_url', 'exam_question_source_url']),
            $this->value($row, ['paper_answer_source_url', 'exam_answer_source_url']),
            $this->value($row, ['paper_combined_source_url', 'exam_combined_source_url']),
        ])->contains(fn ($value) => trim((string) $value) !== '');
        if ($hasPaperSource && $exams->isEmpty()) $this->rowError('This question has no linked exam for the paper source.');
        $this->syncExamSourceUrls($exams, $row);

        if ($updates === [] && ! $hasPaperSource) $this->rowError('No source value was supplied. Blank cells are intentionally preserved.');
        $this->importedCount++;
        $this->updatedCount++;
        return null;
    }
    private function syncExamSourceUrls($exams, array $row): void
    {
        $sources = [
            'questions' => $this->value($row, ['paper_question_source_url', 'exam_question_source_url']),
            'answers' => $this->value($row, ['paper_answer_source_url', 'exam_answer_source_url']),
            'combined' => $this->value($row, ['paper_combined_source_url', 'exam_combined_source_url']),
        ];

        foreach ($exams as $exam) {
            foreach ($sources as $role => $url) {
                $url = trim((string) $url);
                if ($url === '') continue;
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $this->rowError(str_replace('_', ' ', $role).' source must be a valid URL.');
                }
                ExamQualitySource::firstOrCreate([
                    'organization_id' => $this->organizationId,
                    'exam_id' => $exam->id,
                    'role' => $role,
                    'kind' => 'url',
                    'source_url' => $url,
                ], [
                    'label' => ucfirst($role).' source URL',
                    'is_active' => true,
                ]);
            }
        }
    }
    private function resolveQuestionType($value): ?Qtype
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $normalized = $this->normalize($name);
        $qtype = Qtype::all()->first(fn ($item) => in_array($normalized, [$this->normalize($item->type), $this->normalize($item->question_type)], true));
        $aliasCode = match (true) {
            $normalized === 'f', str_contains($normalized, 'fill') => 'B',
            str_contains($normalized, 'multiple'), str_contains($normalized, 'mcq') => 'M',
            str_contains($normalized, 'subjective') => 'S',
            str_contains($normalized, 'true'), str_contains($normalized, 'false') => 'T',
            default => null,
        };
        if (! $qtype && $aliasCode) $qtype = Qtype::where('type', $aliasCode)->first();
        if (! $qtype) {
            $qtype = Qtype::create(['type' => strtoupper(substr($name, 0, 1)), 'question_type' => $name]);
            $this->createdRecordCount++;
        }
        return $qtype;
    }

    private function syncImportedLanguage(Question $question, ?Language $language): void
    {
        if (!$language) return;
        $fields=['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank','si_answer1'];
        $translation=QuestionLang::updateOrCreate(['question_id'=>$question->id,'language_id'=>$language->id],
            array_intersect_key($question->getAttributes(),array_flip($fields)));
        if ($translation->wasRecentlyCreated) $this->createdRecordCount++;
    }

    private function resolveDifficulty($value): ?Diff
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $difficulty = Diff::all()->first(fn ($item) => in_array($this->normalize($name), [$this->normalize($item->type), $this->normalize($item->diff_level)], true));
        if (! $difficulty) {
            $difficulty = Diff::create(['type' => Str::slug($name), 'diff_level' => $name]);
            $this->createdRecordCount++;
        }
        return $difficulty;
    }

    private function resolveLanguage($value): ?Language
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $query = Language::query()->when($this->organizationId, fn ($q) => $q->forOrganization($this->organizationId));
        $language = $query->get()->first(fn ($item) => in_array($this->normalize($name), [$this->normalize($item->name), $this->normalize($item->code)], true));
        if (! $language) {
            $language = Language::create(['organization_id' => $this->organizationId, 'name' => $name, 'code' => Str::lower(Str::slug($name)), 'is_enabled' => true]);
            $this->createdRecordCount++;
        }
        return $language;
    }

    private function resolvePassage($value): ?Passage
    {
        $name = trim((string) $value);
        if ($name === '') return null;
        $query = Passage::query()->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId));
        $passage = $this->findByName($query->get(), 'name', $name);
        if (! $passage) {
            $passage = Passage::create(['organization_id' => $this->organizationId, 'name' => $name]);
            $this->createdRecordCount++;
        }
        return $passage;
    }

    private function resolveTag(string $name): QuestionTag
    {
        $slug = Str::slug($name) ?: 'tag-' . Str::random(6);
        $tag = QuestionTag::where('organization_id', $this->organizationId)->where('slug', $slug)->first();
        if (! $tag) {
            $tag = QuestionTag::create(['organization_id' => $this->organizationId, 'name' => $name, 'slug' => $slug, 'status' => true]);
            $this->createdRecordCount++;
        }
        return $tag;
    }

    private function groupQuery()
    {
        return Group::query()->when($this->organizationId, fn ($q) => $q->where('organization_id', $this->organizationId));
    }

    private function findByName($items, string $field, string $name)
    {
        $normalized = $this->normalize($name);
        return $items->first(fn ($item) => $this->normalize($item->{$field}) === $normalized);
    }

    private function splitNames($value): array
    {
        if ($value === null || trim((string) $value) === '') return [];
        return collect(preg_split('/\s*\|\s*/', (string) $value))->map(fn ($name) => trim($name))->filter()->values()->all();
    }

    private function parseOptionIndices($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,;|\s]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }

        return collect((array) $value)
            ->map(function ($index) {
                $index = strtoupper(trim((string) $index));
                return preg_match('/^[A-F]$/', $index) ? ord($index) - 64 : (int) $index;
            })
            ->filter(fn ($index) => $index >= 1 && $index <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalize($value): string
    {
        return Str::lower(preg_replace('/\s+/', ' ', trim(strip_tags((string) $value))));
    }

    private function uniqueSlug(string $modelClass, string $name, string $column = 'slug', $organizationId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $counter = 2;
        while ($modelClass::query()->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))->where($column, $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function value(array $row, array $keys)
    {
        foreach ($keys as $key) if (array_key_exists($key, $row)) return $row[$key];
        return null;
    }

    public function chunkSize(): int
    {
        return 250;
    }

    private function rowError(string $message): never
    {
        throw ValidationException::withMessages(['file' => 'Row ' . $this->getRowNumber() . ': ' . $message]);
    }
}
