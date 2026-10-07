<?php

namespace App\Services;

use App\Models\{Diff, Exam, Group, Language, Qtype, QuestionSection, Stopic, Subject, Topic};
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SourceQuestionAssignmentResolver
{
    public function resolve(array $payload, int $organizationId): array
    {
        $groupNames = $this->splitNames($payload['group'] ?? null);
        $groups = $this->matchingMany(Group::where('organization_id', $organizationId)->get(), 'group_name', $groupNames);
        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->all();

        $subject = $this->matchingOne(
            Subject::where('organization_id', $organizationId)->get(),
            'subject_name',
            $payload['subject'] ?? null,
        );
        $section = $this->matchingOne(
            QuestionSection::where('organization_id', $organizationId)
                ->when($groupIds !== [], fn ($query) => $query->whereHas('groups', fn ($groupQuery) => $groupQuery->whereIn('groups.id', $groupIds)))
                ->get(),
            'name',
            $payload['section'] ?? null,
        );
        $topic = $this->matchingOne(
            Topic::query()
                ->when($subject, fn ($query) => $query->where('subject_id', $subject->id))
                ->when($groupIds !== [], fn ($query) => $query->whereIn('group_id', $groupIds))
                ->get(),
            'name',
            $payload['topic'] ?? null,
        );
        $subtopic = $this->matchingOne(
            Stopic::query()
                ->when($subject, fn ($query) => $query->where('subject_id', $subject->id))
                ->when($topic, fn ($query) => $query->where('topic_id', $topic->id))
                ->when($groupIds !== [], fn ($query) => $query->whereIn('group_id', $groupIds))
                ->get(),
            'name',
            $payload['subtopic'] ?? null,
        );
        $qtype = $this->matchingByAliases(
            Qtype::all(),
            ['type', 'question_type'],
            $payload['question_type'] ?? null,
        );
        $difficulty = $this->matchingByAliases(
            Diff::all(),
            ['type', 'diff_level'],
            $payload['difficulty_level'] ?? null,
        );
        $language = $this->matchingByAliases(
            Language::query()->forOrganization($organizationId)->get(),
            ['code', 'name'],
            $payload['language'] ?? 'English',
        );
        $examNames = $this->splitNames($payload['exam'] ?? null);
        $exams = $this->matchingMany(Exam::where('organization_id', $organizationId)->get(), 'name', $examNames);

        return [
            'group' => $this->manyResult($groupNames, $groups, 'group_name'),
            'exam' => $this->manyResult($examNames, $exams, 'name'),
            'subject' => $this->singleResult($payload['subject'] ?? null, $subject, 'subject_name'),
            'section' => $this->singleResult($payload['section'] ?? null, $section, 'name'),
            'topic' => $this->singleResult($payload['topic'] ?? null, $topic, 'name'),
            'subtopic' => $this->singleResult($payload['subtopic'] ?? null, $subtopic, 'name'),
            'question_type' => $this->singleResult($payload['question_type'] ?? null, $qtype, 'question_type'),
            'difficulty_level' => $this->singleResult($payload['difficulty_level'] ?? null, $difficulty, 'diff_level'),
            'language' => $this->singleResult($payload['language'] ?? 'English', $language, 'name'),
            'category' => $payload['category'] ?? null,
            'subcategory' => $payload['subcategory'] ?? null,
            'package' => $payload['package'] ?? null,
        ];
    }

    private function matchingOne(Collection $records, string $field, mixed $value): mixed
    {
        $needle = $this->key((string) $value);
        if ($needle === '') return null;

        return $records->first(fn ($record) => $this->key((string) data_get($record, $field)) === $needle);
    }

    private function matchingByAliases(Collection $records, array $fields, mixed $value): mixed
    {
        $needle = $this->key((string) $value);
        if ($needle === '') return null;

        return $records->first(fn ($record) => collect($fields)->contains(
            fn ($field) => $this->key((string) data_get($record, $field)) === $needle
        ));
    }

    private function matchingMany(Collection $records, string $field, array $names): Collection
    {
        $needles = collect($names)->map(fn ($name) => $this->key($name))->filter()->all();
        return $records->filter(fn ($record) => in_array($this->key((string) data_get($record, $field)), $needles, true))->values();
    }

    private function singleResult(mixed $requested, mixed $record, string $field): ?array
    {
        $requested = trim((string) $requested);
        if ($requested === '') return null;

        return $record
            ? ['id' => (int) $record->id, 'name' => (string) data_get($record, $field), 'assigned' => true]
            : ['id' => null, 'name' => $requested, 'assigned' => false];
    }

    private function manyResult(array $requested, Collection $records, string $field): ?array
    {
        if ($requested === []) return null;
        $matchedNames = $records->pluck($field)->map(fn ($name) => (string) $name)->values()->all();
        $matchedKeys = collect($matchedNames)->map(fn ($name) => $this->key($name))->all();
        $unmatched = array_values(array_filter($requested, fn ($name) => ! in_array($this->key($name), $matchedKeys, true)));

        return [
            'ids' => $records->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'names' => $matchedNames !== [] ? $matchedNames : $requested,
            'assigned' => $unmatched === [] && $records->isNotEmpty(),
            'unmatched' => $unmatched,
        ];
    }

    private function splitNames(mixed $value): array
    {
        if (is_array($value)) return collect($value)->map(fn ($name) => trim((string) $name))->filter()->values()->all();
        return collect(preg_split('/\s*[|;]\s*/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($name) => trim($name))->filter()->values()->all();
    }

    private function key(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $json = json_decode($value, true);
        if (is_array($json)) $value = (string) ($json['en'] ?? $json['default'] ?? reset($json));

        return preg_replace('/[^\pL\pN]+/u', '', Str::lower(trim($value))) ?? '';
    }
}
