<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Language;
use App\Models\Passage;
use App\Models\PassageLang;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QuestionSharingService
{
    public function copyQuestionToOrganization(Question $sourceQuestion, int $targetOrganizationId): Question
    {
        return DB::transaction(function () use ($sourceQuestion, $targetOrganizationId) {
            $sourceQuestion->loadMissing(['groups', 'langs.language', 'subject', 'topic', 'stopic', 'passage.langs.language']);

            $targetGroups = $this->mapGroups($sourceQuestion, $targetOrganizationId);
            $targetSubject = $this->mapSubject($sourceQuestion, $targetOrganizationId, $targetGroups);
            $targetTopic = $this->mapTopic($sourceQuestion, $targetSubject, $targetGroups);
            $targetStopic = $this->mapStopic($sourceQuestion, $targetSubject, $targetTopic, $targetGroups);
            $targetPassage = $this->mapPassage($sourceQuestion, $targetOrganizationId);
            $targetLanguage = $this->mapLanguage($sourceQuestion->language, $targetOrganizationId);

            $data = $sourceQuestion->only([
                'qtype_id',
                'question',
                'option1',
                'option2',
                'option3',
                'option4',
                'option5',
                'option6',
                'marks',
                'negative_marks',
                'scoring_policy',
                'hint',
                'explanation',
                'answer',
                'true_false',
                'fill_blank',
                'status',
                'correct_option_indices',
                'si_answer1',
                'diff_id',
            ]);

            $data['organization_id'] = $targetOrganizationId;
            $data['subject_id'] = $targetSubject?->id;
            $data['topic_id'] = $targetTopic?->id;
            $data['stopic_id'] = $targetStopic?->id;
            $data['passage_id'] = $targetPassage?->id;
            $data['language_id'] = $targetLanguage?->id;

            $targetQuestion = Question::create($data);
            $targetQuestion->groups()->sync($targetGroups->pluck('id')->all());
            app(CurriculumTaxonomyService::class)->syncQuestion($targetQuestion->fresh(['topic', 'stopic']), $targetGroups->pluck('id')->all());

            foreach ($sourceQuestion->langs as $sourceLang) {
                $language = $this->mapLanguage($sourceLang->language, $targetOrganizationId);

                if (! $language) {
                    continue;
                }

                QuestionLang::create([
                    'question_id' => $targetQuestion->id,
                    'language_id' => $language->id,
                    'question' => $sourceLang->question,
                    'option1' => $sourceLang->option1,
                    'option2' => $sourceLang->option2,
                    'option3' => $sourceLang->option3,
                    'option4' => $sourceLang->option4,
                    'option5' => $sourceLang->option5,
                    'option6' => $sourceLang->option6,
                    'hint' => $sourceLang->hint,
                    'explanation' => $sourceLang->explanation,
                    'fill_blank' => $sourceLang->fill_blank,
                ]);
            }

            return $targetQuestion;
        });
    }

    private function mapGroups(Question $sourceQuestion, int $targetOrganizationId)
    {
        $groups = collect();

        foreach ($sourceQuestion->groups as $sourceGroup) {
            $groups->push($this->firstOrCreateGroup($sourceGroup, $targetOrganizationId));
        }

        if ($groups->isEmpty()) {
            $groups->push(Group::firstOrCreate(
                ['organization_id' => $targetOrganizationId, 'group_name' => 'Shared Questions'],
                ['description' => 'Questions copied from another organization.']
            ));
        }

        return $groups->unique('id')->values();
    }

    private function firstOrCreateGroup(Group $sourceGroup, int $targetOrganizationId): Group
    {
        $name = $this->displayValue($sourceGroup->group_name) ?: 'Shared Questions';

        return Group::query()
            ->where('organization_id', $targetOrganizationId)
            ->get()
            ->first(fn ($group) => $this->sameText($group->group_name, $name))
            ?: Group::create([
                'organization_id' => $targetOrganizationId,
                'group_name' => $sourceGroup->group_name,
                'description' => $sourceGroup->description,
                'display_order' => $sourceGroup->display_order,
            ]);
    }

    private function mapSubject(Question $sourceQuestion, int $targetOrganizationId, $targetGroups): ?Subject
    {
        if (! $sourceQuestion->subject) {
            return null;
        }

        $name = $sourceQuestion->subject->subject_name;
        $targetSubject = Subject::firstOrCreate([
            'organization_id' => $targetOrganizationId,
            'subject_name' => $name,
        ], [
            'ordering' => $sourceQuestion->subject->ordering,
        ]);

        foreach ($targetGroups as $group) {
            DB::table('subject_groups')->updateOrInsert([
                'subject_id' => $targetSubject->id,
                'group_id' => $group->id,
            ]);
        }

        return $targetSubject;
    }

    private function mapTopic(Question $sourceQuestion, ?Subject $targetSubject, $targetGroups): ?Topic
    {
        if (! $sourceQuestion->topic || ! $targetSubject) {
            return null;
        }

        $topics = $targetGroups->map(fn (Group $group) => Topic::firstOrCreate([
            'subject_id' => $targetSubject->id,
            'group_id' => $group->id,
            'name' => $sourceQuestion->topic->name,
        ], ['display_order' => $sourceQuestion->topic->display_order]));

        return $topics->first();
    }

    private function mapStopic(Question $sourceQuestion, ?Subject $targetSubject, ?Topic $targetTopic, $targetGroups): ?Stopic
    {
        if (! $sourceQuestion->stopic || ! $targetSubject || ! $targetTopic) {
            return null;
        }

        $stopics = $targetGroups->map(function (Group $group) use ($sourceQuestion, $targetSubject) {
            $topic = Topic::where('subject_id', $targetSubject->id)
                ->where('group_id', $group->id)
                ->where('name', $sourceQuestion->topic->name)
                ->firstOrFail();

            return Stopic::firstOrCreate([
                'subject_id' => $targetSubject->id,
                'group_id' => $group->id,
                'topic_id' => $topic->id,
                'name' => $sourceQuestion->stopic->name,
            ], ['display_order' => $sourceQuestion->stopic->display_order]);
        });

        return $stopics->first();
    }
    private function mapPassage(Question $sourceQuestion, int $targetOrganizationId): ?Passage
    {
        if (! $sourceQuestion->passage) {
            return null;
        }

        $targetPassage = Passage::firstOrCreate([
            'organization_id' => $targetOrganizationId,
            'name' => $sourceQuestion->passage->name,
        ]);

        foreach ($sourceQuestion->passage->langs as $sourcePassageLang) {
            $language = $this->mapLanguage($sourcePassageLang->language, $targetOrganizationId);

            if (! $language) {
                continue;
            }

            PassageLang::updateOrCreate(
                ['passage_id' => $targetPassage->id, 'language_id' => $language->id],
                ['passage' => $sourcePassageLang->passage]
            );
        }

        return $targetPassage;
    }

    private function mapLanguage($sourceLanguage, int $targetOrganizationId): ?Language
    {
        if (! $sourceLanguage) {
            return null;
        }

        $platformOrganizationId = Schema::hasTable('organizations')
            ? DB::table('organizations')->where('slug', 'examelite')->value('id')
            : null;
        $sourceLanguageId = $platformOrganizationId && (int) $targetOrganizationId !== (int) $platformOrganizationId
            ? ($sourceLanguage->source_language_id ?: $sourceLanguage->id)
            : null;

        $query = Language::query();

        if (Schema::hasColumn('languages', 'organization_id')) {
            $query->where('organization_id', $targetOrganizationId);
        }

        $targetLanguage = (clone $query)->where('code', $sourceLanguage->code)->first()
            ?: (clone $query)->where('name', $sourceLanguage->name)->first();

        if ($targetLanguage) {
            $updates = [];

            if (Schema::hasColumn('languages', 'source_language_id') && $sourceLanguageId && ! $targetLanguage->source_language_id) {
                $updates['source_language_id'] = $sourceLanguageId;
            }

            if (Schema::hasColumn('languages', 'is_enabled') && ! $targetLanguage->is_enabled) {
                $updates['is_enabled'] = true;
            }

            if ($updates) {
                $targetLanguage->update($updates);
            }

            return $targetLanguage;
        }

        $data = [
            'organization_id' => $targetOrganizationId,
            'name' => $sourceLanguage->name,
            'code' => $sourceLanguage->code,
            'value1' => $sourceLanguage->value1,
            'value2' => $sourceLanguage->value2,
            'is_enabled' => true,
        ];

        if (Schema::hasColumn('languages', 'source_language_id')) {
            $data['source_language_id'] = $sourceLanguageId;
        }

        if (! Schema::hasColumn('languages', 'is_enabled')) {
            unset($data['is_enabled']);
        }

        return Language::create($data);
    }

    private function sameText($left, string $right): bool
    {
        return Str::lower($this->displayValue($left)) === Str::lower($right);
    }

    private function displayValue($value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded));
            }
        }

        return (string) $value;
    }
}
