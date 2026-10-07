<?php

namespace App\Services;

use App\Models\{Diff, Exam, ExamQualitySource, Language, OfficialExamDiscovery, OfficialExamSource, OfficialExamSourceRule, SourceExamImport};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class OfficialExamCreationService
{
    public function create(OfficialExamSource $source, OfficialExamSourceRule $rule, OfficialExamDiscovery $discovery, array $row): array
    {
        $defaults = (array) $source->exam_defaults;
        $storedSources = [];
        $importCopies = [];
        $exam = null;
        try {
            return DB::transaction(function () use ($source, $rule, $discovery, $row, $defaults, &$storedSources, &$importCopies, &$exam) {
                $locked = OfficialExamDiscovery::query()->lockForUpdate()->findOrFail($discovery->id);
                if ($locked->exam_id || $locked->source_exam_import_id) throw new \RuntimeException('This official exam discovery was already claimed.');
                $exam = Exam::create([
                    'organization_id' => $source->organization_id,
                    'name' => $discovery->exam_name,
                    'slug' => $this->uniqueSlug($discovery->exam_name, (int) $source->organization_id),
                    'test_type' => Exam::TEST_TYPE_PREVIOUS_YEAR,
                    'passing_percentage' => (float) ($defaults['passing_percentage'] ?? 0),
                    'duration' => (int) ($defaults['duration'] ?? 180),
                    'attempt_count' => (int) ($defaults['attempt_count'] ?? 0),
                    'start_date' => null, 'end_date' => null, 'browser_tolerance' => false,
                    'random_question' => false, 'result_after_finish' => true, 'option_shuffle' => false,
                    'allow_answer_change' => true, 'grouping_mode' => 'subject', 'timer_mode' => 'none', 'mode' => 'Exam',
                    'proctor' => false, 'calculator_allowed' => false,
                    'negative_marking' => (float) ($defaults['negative_marks'] ?? 0) > 0,
                    'multi_language' => $rule->language_mode !== 'single',
                    'tolerance_count' => 0, 'status' => 'Inactive',
                    'category_level_1' => $defaults['category_id'] ?? null,
                    'category_level_2' => $defaults['subcategory_id'] ?? null,
                ]);

                $scope = app(ExamScopeService::class)->resolve(
                    (int) $source->organization_id,
                    array_values(array_filter([(int) ($defaults['package_id'] ?? 0)])),
                    array_values(array_filter([(int) ($defaults['group_id'] ?? 0)])),
                    $defaults['category_id'] ?? null,
                    $defaults['subcategory_id'] ?? null,
                );
                app(ExamScopeService::class)->sync($exam, $scope);

                $uuid = (string) Str::uuid();
                foreach (['question' => 'questions', 'answer' => 'answers', 'combined' => 'combined'] as $inputRole => $sourceRole) {
                    $local = (string) ($row[$inputRole.'_local'] ?? '');
                    if ($local === '') continue;
                    $label = (string) ($row[$inputRole.'_label'] ?? basename($local));
                    $importRelative = "source-exam-imports/{$source->organization_id}/{$uuid}/{$inputRole}/".Str::uuid().'.pdf';
                    $read = Storage::disk('local')->readStream($local);
                    try {
                        if (! is_resource($read) || ! Storage::disk('local')->put($importRelative, $read)) throw new \RuntimeException('Unable to preserve the '.$inputRole.' PDF for extraction.');
                    } finally { if (is_resource($read)) fclose($read); }
                    $importCopies[$inputRole] = ['path' => $importRelative, 'name' => $label];

                    $sourceStaging = 'tmp/source-document-discovery/'.$source->organization_id.'/official-'.Str::uuid().'/'.$inputRole.'/'.basename($local);
                    $sourceRead = Storage::disk('local')->readStream($local);
                    try {
                        if (! is_resource($sourceRead) || ! Storage::disk('local')->put($sourceStaging, $sourceRead)) throw new \RuntimeException('Unable to stage the '.$inputRole.' PDF for permanent source storage.');
                    } finally { if (is_resource($sourceRead)) fclose($sourceRead); }
                    $stored = app(ExamQualitySourceStorage::class)->storeStaged(
                        $sourceStaging, $label, (int) $source->organization_id, (int) $exam->id, $sourceRole
                    );
                    $storedSources[$inputRole] = ExamQualitySource::create(array_merge($stored, [
                        'organization_id' => $source->organization_id, 'exam_id' => $exam->id,
                        'role' => $sourceRole, 'kind' => 'file',
                        'source_url' => ($row[$inputRole.'_url'] ?? '') ?: null, 'is_active' => true,
                    ]));
                }

                $question = $importCopies['question'] ?? $importCopies['combined'] ?? null;
                if (! $question) throw new \RuntimeException('No question or combined PDF was available for automatic import.');
                $answer = $importCopies['answer'] ?? $importCopies['combined'] ?? null;
                $languageId = (int) ($rule->language_id ?: ($defaults['language_id'] ?? 0));
                if (! $languageId) $languageId = (int) Language::enabledForOrganization((int) $source->organization_id)->orderBy('id')->value('id');
                $difficultyId = (int) Diff::query()->orderBy('id')->value('id');
                $import = SourceExamImport::create([
                    'organization_id' => $source->organization_id, 'created_by' => $source->created_by,
                    'exam_id' => $exam->id, 'name' => $exam->name,
                    'status' => $source->automation_mode === 'draft' ? 'draft' : 'queued',
                    'question_source_name' => $question['name'], 'question_source_path' => $question['path'], 'question_source_type' => 'pdf',
                    'answer_source_name' => $answer['name'] ?? null, 'answer_source_path' => $answer['path'] ?? null, 'answer_source_type' => $answer ? 'pdf' : null,
                    'solution_source_name' => $importCopies['combined']['name'] ?? null,
                    'solution_source_path' => $importCopies['combined']['path'] ?? null,
                    'solution_source_type' => isset($importCopies['combined']) ? 'pdf' : null,
                    'settings' => [
                        'official_source_id' => $source->id, 'official_discovery_id' => $discovery->id,
                        'source_profile_version' => $source->config_version, 'paper_rule_id' => $rule->id,
                        'paper_rule_version' => $rule->version, 'language_mode' => $rule->language_mode,
                        'group_ids' => array_values(array_filter([(int) ($defaults['group_id'] ?? 0)])),
                        'package_ids' => array_values(array_filter([(int) ($defaults['package_id'] ?? 0)])),
                        'category_id' => $defaults['category_id'] ?? null, 'subcategory_id' => $defaults['subcategory_id'] ?? null,
                        'subject_id' => null, 'qtype_id' => null, 'diff_id' => $difficultyId,
                        'language_id' => $languageId, 'duration' => (int) ($defaults['duration'] ?? 180),
                        'attempt_count' => (int) ($defaults['attempt_count'] ?? 0),
                        'passing_percentage' => (float) ($defaults['passing_percentage'] ?? 0),
                        'marks' => (float) ($defaults['marks'] ?? 1), 'negative_marks' => (float) ($defaults['negative_marks'] ?? 0),
                        'extractor_script' => $rule->extractor_script,
                        'profile' => array_merge(['document_type' => 'auto', 'layout' => 'auto', 'reading_order' => 'auto', 'question_numbering' => 'auto'], (array) data_get($rule->settings, 'extractor_profile', [])),
                    ],
                ]);
                $locked->update(['exam_id' => $exam->id, 'source_exam_import_id' => $import->id, 'status' => 'created', 'failure_message' => null]);
                return ['exam' => $exam, 'import' => $import];
            });
        } catch (\Throwable $exception) {
            foreach ($storedSources as $storedSource) {
                try { app(ExamQualitySourceStorage::class)->delete($storedSource); } catch (\Throwable) {}
            }
            foreach ($importCopies as $copy) Storage::disk('local')->delete($copy['path']);
            throw $exception;
        }
    }

    private function uniqueSlug(string $name, int $organizationId): string
    {
        $base = Str::slug($name) ?: 'exam';
        $slug = $base; $suffix = 2;
        while (Exam::where('organization_id', $organizationId)->where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}
