<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\CurriculumTaxonomyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCurriculumTaxonomy extends Command
{
    protected $signature = 'taxonomy:audit {--repair : Repair safe, unambiguous missing mappings}';

    protected $description = 'Audit tenant and group isolation of subjects, topics, subtopics, and questions';

    public function handle(CurriculumTaxonomyService $taxonomy): int
    {
        $checks = [
            'Subjects without a tenant' => DB::table('subjects')->whereNull('organization_id')->count(),
            'Subject/group tenant mismatches' => DB::table('subject_groups')
                ->join('subjects', 'subjects.id', '=', 'subject_groups.subject_id')
                ->join('groups', 'groups.id', '=', 'subject_groups.group_id')
                ->whereColumn('subjects.organization_id', '!=', 'groups.organization_id')->count(),
            'Topics without a group context' => DB::table('topics')->whereNull('group_id')->count(),
            'Topics outside their subject context' => DB::table('topics')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('subject_groups')
                    ->whereColumn('subject_groups.subject_id', 'topics.subject_id')
                    ->whereColumn('subject_groups.group_id', 'topics.group_id'))->count(),
            'Subtopics with a mismatched topic/group/subject' => DB::table('stopics')
                ->join('topics', 'topics.id', '=', 'stopics.topic_id')
                ->where(fn ($query) => $query
                    ->whereColumn('stopics.subject_id', '!=', 'topics.subject_id')
                    ->orWhereColumn('stopics.group_id', '!=', 'topics.group_id'))->count(),
            'Duplicate topic names inside one group/subject' => DB::query()->fromSub(
                DB::table('topics')->select('group_id', 'subject_id', 'name')->groupBy('group_id', 'subject_id', 'name')->havingRaw('COUNT(*) > 1'),
                'duplicate_topics'
            )->count(),
            'Duplicate subtopic names inside one group/topic' => DB::query()->fromSub(
                DB::table('stopics')->select('group_id', 'topic_id', 'name')->groupBy('group_id', 'topic_id', 'name')->havingRaw('COUNT(*) > 1'),
                'duplicate_stopics'
            )->count(),
            'Questions with mismatched legacy hierarchy' => DB::table('questions')
                ->leftJoin('topics', 'topics.id', '=', 'questions.topic_id')
                ->leftJoin('stopics', 'stopics.id', '=', 'questions.stopic_id')
                ->where(fn ($query) => $query
                    ->where(fn ($topic) => $topic->whereNotNull('questions.topic_id')->whereColumn('topics.subject_id', '!=', 'questions.subject_id'))
                    ->orWhere(fn ($stopic) => $stopic->whereNotNull('questions.stopic_id')->whereColumn('stopics.topic_id', '!=', 'questions.topic_id')))
                ->count(),
            'Question groups missing explicit taxonomy' => DB::table('question_groups')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('question_taxonomies')
                    ->whereColumn('question_taxonomies.question_id', 'question_groups.question_id')
                    ->whereColumn('question_taxonomies.group_id', 'question_groups.group_id'))->count(),
            'Question taxonomy tenant mismatches' => DB::table('question_taxonomies')
                ->join('questions', 'questions.id', '=', 'question_taxonomies.question_id')
                ->join('groups', 'groups.id', '=', 'question_taxonomies.group_id')
                ->where(fn ($query) => $query
                    ->whereColumn('question_taxonomies.organization_id', '!=', 'questions.organization_id')
                    ->orWhereColumn('question_taxonomies.organization_id', '!=', 'groups.organization_id'))->count(),
        ];

        $this->table(['Check', 'Count'], collect($checks)->map(fn ($count, $name) => [$name, $count]));

        if ($this->option('repair')) {
            Question::query()->whereHas('groups')->with(['groups:id', 'topic', 'stopic'])
                ->chunkById(200, function ($questions) use ($taxonomy) {
                    foreach ($questions as $question) {
                        $taxonomy->syncQuestion($question, $question->groups->pluck('id')->all());
                    }
                });
            $this->info('Safe question taxonomy mappings were rebuilt. Structural mismatches were only reported.');
        }

        return collect($checks)->sum() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
