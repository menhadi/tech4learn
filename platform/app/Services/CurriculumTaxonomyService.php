<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Question;
use App\Models\QuestionTaxonomy;
use App\Models\Stopic;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CurriculumTaxonomyService
{
    public function validateSelection(
        int $tenantId,
        array $groupIds,
        ?int $subjectId,
        ?int $topicId,
        ?int $stopicId
    ): void {
        $groupIds = collect($groupIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if (Group::where('organization_id', $tenantId)->whereIn('id', $groupIds)->count() !== $groupIds->count()) {
            throw ValidationException::withMessages(['group_ids' => 'One or more selected groups do not belong to this tenant.']);
        }
        if (! $subjectId) {
            if ($topicId || $stopicId) {
                throw ValidationException::withMessages(['subject_id' => 'Select a subject before a topic or subtopic.']);
            }
            return;
        }

        $subject = Subject::where('organization_id', $tenantId)->find($subjectId);
        if (! $subject) {
            throw ValidationException::withMessages(['subject_id' => 'The selected subject does not belong to this tenant.']);
        }
        $linkedGroupIds = $subject->groups()->whereIn('groups.id', $groupIds)->pluck('groups.id');
        if ($linkedGroupIds->count() !== $groupIds->count()) {
            throw ValidationException::withMessages(['subject_id' => 'The selected subject is not available in every selected group.']);
        }

        $topic = $topicId ? Topic::where('subject_id', $subjectId)->find($topicId) : null;
        if ($topicId && (! $topic || ! $groupIds->contains((int) $topic->group_id))) {
            throw ValidationException::withMessages(['topic_id' => 'The selected topic does not belong to this subject/group context.']);
        }
        if ($topic && Topic::where('subject_id', $subjectId)->whereIn('group_id', $groupIds)->where('name', $topic->name)
            ->distinct()->count('group_id') !== $groupIds->count()) {
            throw ValidationException::withMessages(['topic_id' => 'This topic is not configured in every selected group.']);
        }
        if ($stopicId) {
            $stopic = Stopic::where('subject_id', $subjectId)->where('topic_id', $topicId)->find($stopicId);
            if (! $stopic || (int) $stopic->group_id !== (int) $topic?->group_id) {
                throw ValidationException::withMessages(['stopic_id' => 'The selected subtopic does not belong to the selected topic.']);
            }
            $matchingSubtopics = Stopic::query()->join('topics', 'topics.id', '=', 'stopics.topic_id')
                ->where('stopics.subject_id', $subjectId)->whereIn('stopics.group_id', $groupIds)
                ->where('topics.name', $topic->name)->where('stopics.name', $stopic->name)
                ->distinct()->count('stopics.group_id');
            if ($matchingSubtopics !== $groupIds->count()) {
                throw ValidationException::withMessages(['stopic_id' => 'This subtopic is not configured in every selected group.']);
            }
        }
    }

    public function syncQuestion(Question $question, array $groupIds): void
    {
        $groupIds = collect($groupIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $topicName = $question->topic?->name;
        $stopicName = $question->stopic?->name;

        foreach ($groupIds as $groupId) {
            $topicId = $topicName && $question->subject_id
                ? Topic::where('group_id', $groupId)->where('subject_id', $question->subject_id)->where('name', $topicName)->value('id')
                : null;
            $stopicId = $stopicName && $topicId
                ? Stopic::where('group_id', $groupId)->where('topic_id', $topicId)->where('name', $stopicName)->value('id')
                : null;

            QuestionTaxonomy::updateOrCreate(
                ['question_id' => $question->id, 'group_id' => $groupId],
                [
                    'organization_id' => $question->organization_id,
                    'subject_id' => $question->subject_id,
                    'topic_id' => $topicId,
                    'stopic_id' => $stopicId,
                ]
            );
        }
        $question->taxonomies()->whereNotIn('group_id', $groupIds)->delete();
    }

    public function cloneTreeForNewGroups(Subject $subject, Collection $newGroupIds): void
    {
        $sourceGroupId = $subject->topics()->whereNotNull('group_id')->value('group_id');
        if (! $sourceGroupId) {
            return;
        }
        $sourceTopics = $subject->topics()->where('group_id', $sourceGroupId)->with('stopics')->get();
        foreach ($newGroupIds as $groupId) {
            foreach ($sourceTopics as $topic) {
                $clone = Topic::firstOrCreate(
                    ['subject_id' => $subject->id, 'group_id' => $groupId, 'name' => $topic->name],
                    ['display_order' => $topic->display_order]
                );
                foreach ($topic->stopics as $stopic) {
                    Stopic::firstOrCreate(
                        ['subject_id' => $subject->id, 'group_id' => $groupId, 'topic_id' => $clone->id, 'name' => $stopic->name],
                        ['display_order' => $stopic->display_order]
                    );
                }
            }
        }
    }
}
