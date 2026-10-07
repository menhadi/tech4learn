<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subjects', 'organization_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
        if ($this->hasIndex('subjects', 'subjects_subject_name_unique')) {
            Schema::table('subjects', fn (Blueprint $table) => $table->dropUnique('subjects_subject_name_unique'));
        }

        $this->splitSubjectsByTenant();

        if (! $this->hasIndex('subjects', 'subjects_tenant_name_unique')) {
            Schema::table('subjects', fn (Blueprint $table) => $table->unique(['organization_id', 'subject_name'], 'subjects_tenant_name_unique'));
        }
        if (! $this->hasIndex('subjects', 'subjects_tenant_order_idx')) {
            Schema::table('subjects', fn (Blueprint $table) => $table->index(['organization_id', 'ordering'], 'subjects_tenant_order_idx'));
        }

        $this->deduplicateSubjectGroups();
        if (! $this->hasIndex('subject_groups', 'subject_groups_subject_group_unique')) {
            Schema::table('subject_groups', fn (Blueprint $table) => $table->unique(['subject_id', 'group_id'], 'subject_groups_subject_group_unique'));
        }

        if (! Schema::hasColumn('topics', 'group_id')) {
            Schema::table('topics', function (Blueprint $table) {
                $table->foreignId('group_id')->nullable()->after('subject_id')->constrained()->cascadeOnDelete();
            });
        }
        if (! $this->hasIndex('topics', 'topics_group_subject_name_idx')) {
            Schema::table('topics', fn (Blueprint $table) => $table->index(['group_id', 'subject_id', 'name'], 'topics_group_subject_name_idx'));
        }
        if (! Schema::hasColumn('stopics', 'group_id')) {
            Schema::table('stopics', function (Blueprint $table) {
                $table->foreignId('group_id')->nullable()->after('subject_id')->constrained()->cascadeOnDelete();
            });
        }
        if (! $this->hasIndex('stopics', 'stopics_group_subject_topic_name_idx')) {
            Schema::table('stopics', fn (Blueprint $table) => $table->index(['group_id', 'subject_id', 'topic_id', 'name'], 'stopics_group_subject_topic_name_idx'));
        }

        if (! Schema::hasTable('question_taxonomies')) {
            Schema::create('question_taxonomies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->foreignId('group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('stopic_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->unique(['question_id', 'group_id'], 'question_taxonomies_question_group_unique');
                $table->index(['organization_id', 'group_id', 'subject_id'], 'question_taxonomies_tenant_group_subject_idx');
            });
        }

        $this->splitTopicTreesByGroup();
        $this->backfillQuestionTaxonomies();
    }

    public function down(): void
    {
        Schema::dropIfExists('question_taxonomies');

        Schema::table('stopics', function (Blueprint $table) {
            $table->dropIndex('stopics_group_subject_topic_name_idx');
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex('topics_group_subject_name_idx');
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::table('subject_groups', function (Blueprint $table) {
            $table->dropUnique('subject_groups_subject_group_unique');
        });
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique('subjects_tenant_name_unique');
            $table->dropIndex('subjects_tenant_order_idx');
            $table->dropConstrainedForeignId('organization_id');
        });
    }

    private function splitSubjectsByTenant(): void
    {
        $subjects = DB::table('subjects')->orderBy('id')->get();

        foreach ($subjects as $subject) {
            $tenantIds = DB::table('subject_groups')
                ->join('groups', 'groups.id', '=', 'subject_groups.group_id')
                ->where('subject_groups.subject_id', $subject->id)
                ->whereNotNull('groups.organization_id')
                ->orderBy('groups.organization_id')
                ->distinct()
                ->pluck('groups.organization_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($tenantIds->isEmpty()) continue;

            $primaryTenantId = (int) ($subject->organization_id ?: $tenantIds->first());
            if (! $subject->organization_id) {
                DB::table('subjects')->where('id', $subject->id)->update(['organization_id' => $primaryTenantId]);
            }

            foreach ($tenantIds as $tenantId) {
                $tenantId = (int) $tenantId;
                if ($tenantId === $primaryTenantId) continue;

                $targetSubjectId = DB::table('subjects')
                    ->where('organization_id', $tenantId)
                    ->where('subject_name', $subject->subject_name)
                    ->value('id');
                if (! $targetSubjectId) {
                    $targetSubjectId = DB::table('subjects')->insertGetId([
                        'organization_id' => $tenantId,
                        'subject_name' => $subject->subject_name,
                        'ordering' => $subject->ordering,
                        'created_at' => $subject->created_at,
                        'updated_at' => $subject->updated_at,
                    ]);
                }
                $topicMap = $this->cloneTopicTree((int) $subject->id, (int) $targetSubjectId);

                DB::table('subject_groups')
                    ->where('subject_id', $subject->id)
                    ->whereIn('group_id', DB::table('groups')->where('organization_id', $tenantId)->select('id'))
                    ->update(['subject_id' => $targetSubjectId]);

                $this->moveTenantSubjectReferences((int) $subject->id, (int) $targetSubjectId, $tenantId, $topicMap);
            }
        }
    }

    private function cloneTopicTree(int $sourceSubjectId, int $targetSubjectId): array
    {
        $topicMap = [];
        $stopicMap = [];

        foreach (DB::table('topics')->where('subject_id', $sourceSubjectId)->orderBy('id')->get() as $topic) {
            $newTopicId = DB::table('topics')->where('subject_id', $targetSubjectId)
                ->where('name', $topic->name)->value('id');
            if (! $newTopicId) {
                $newTopicId = DB::table('topics')->insertGetId([
                    'subject_id' => $targetSubjectId,
                    'name' => $topic->name,
                    'display_order' => $topic->display_order ?? 0,
                    'created_at' => $topic->created_at,
                    'updated_at' => $topic->updated_at,
                ]);
            }
            $topicMap[(int) $topic->id] = (int) $newTopicId;

            foreach (DB::table('stopics')->where('topic_id', $topic->id)->orderBy('id')->get() as $stopic) {
                $newStopicId = DB::table('stopics')->where('subject_id', $targetSubjectId)
                    ->where('topic_id', $newTopicId)->where('name', $stopic->name)->value('id');
                if (! $newStopicId) {
                    $newStopicId = DB::table('stopics')->insertGetId([
                        'subject_id' => $targetSubjectId,
                        'topic_id' => $newTopicId,
                        'name' => $stopic->name,
                        'display_order' => $stopic->display_order ?? 0,
                        'created_at' => $stopic->created_at,
                        'updated_at' => $stopic->updated_at,
                    ]);
                }
                $stopicMap[(int) $stopic->id] = (int) $newStopicId;
            }
        }

        return ['topics' => $topicMap, 'stopics' => $stopicMap];
    }

    private function moveTenantSubjectReferences(
        int $oldSubjectId,
        int $newSubjectId,
        int $tenantId,
        array $treeMap
    ): void {
        foreach ([
            ['questions', 'subject_id'],
            ['flashcard_sets', 'subject_id'],
            ['exam_stats', 'subject_id'],
            ['questions_report', 'subject_id'],
            ['quick_quiz_sessions', 'subject_id'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id') || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table)
                ->where('organization_id', $tenantId)
                ->where($column, $oldSubjectId);

            if ($table === 'questions') {
                foreach ($treeMap['stopics'] as $oldStopicId => $newStopicId) {
                    DB::table('questions')
                        ->where('organization_id', $tenantId)
                        ->where('subject_id', $oldSubjectId)
                        ->where('stopic_id', $oldStopicId)
                        ->update(['stopic_id' => $newStopicId]);
                }

                foreach ($treeMap['topics'] as $oldTopicId => $newTopicId) {
                    DB::table('questions')
                        ->where('organization_id', $tenantId)
                        ->where('subject_id', $oldSubjectId)
                        ->where('topic_id', $oldTopicId)
                        ->update(['topic_id' => $newTopicId]);
                }

                $query->update(['subject_id' => $newSubjectId]);
            } else {
                $query->update([$column => $newSubjectId]);
            }
        }

        if (Schema::hasTable('exam_subject_durations') && Schema::hasTable('exams')) {
            $examIds = DB::table('exams')->where('organization_id', $tenantId)->pluck('id');
            DB::table('exam_subject_durations')->whereIn('exam_id', $examIds)
                ->where('subject_id', $oldSubjectId)->update(['subject_id' => $newSubjectId]);
        }
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'test_subject_id')) {
            foreach ($treeMap['stopics'] as $oldStopicId => $newStopicId) {
                DB::table('exams')
                    ->where('organization_id', $tenantId)
                    ->where('test_subject_id', $oldSubjectId)
                    ->where('test_stopic_id', $oldStopicId)
                    ->update(['test_stopic_id' => $newStopicId]);
            }

            foreach ($treeMap['topics'] as $oldTopicId => $newTopicId) {
                DB::table('exams')
                    ->where('organization_id', $tenantId)
                    ->where('test_subject_id', $oldSubjectId)
                    ->where('test_topic_id', $oldTopicId)
                    ->update(['test_topic_id' => $newTopicId]);
            }

            DB::table('exams')
                ->where('organization_id', $tenantId)
                ->where('test_subject_id', $oldSubjectId)
                ->update(['test_subject_id' => $newSubjectId]);
        }
    }

    private function deduplicateSubjectGroups(): void
    {
        DB::table('subject_groups')->orderBy('id')->get()->groupBy(
            fn ($row) => $row->subject_id.':'.$row->group_id
        )->each(function ($rows) {
            $duplicateIds = $rows->pluck('id')->slice(1);
            if ($duplicateIds->isNotEmpty()) {
                DB::table('subject_groups')->whereIn('id', $duplicateIds)->delete();
            }
        });
    }

    private function splitTopicTreesByGroup(): void
    {
        foreach (DB::table('subjects')->orderBy('id')->get() as $subject) {
            $groupIds = DB::table('subject_groups')->where('subject_id', $subject->id)
                ->orderBy('group_id')->pluck('group_id')->map(fn ($id) => (int) $id)->unique()->values();
            if ($groupIds->isEmpty()) continue;

            $templateGroupId = (int) $groupIds->first();
            DB::table('topics')->where('subject_id', $subject->id)->whereNull('group_id')->update(['group_id' => $templateGroupId]);
            DB::table('stopics')->where('subject_id', $subject->id)->whereNull('group_id')->update(['group_id' => $templateGroupId]);
            $templateTopics = DB::table('topics')->where('subject_id', $subject->id)
                ->where('group_id', $templateGroupId)->orderBy('id')->get();

            foreach ($groupIds as $groupId) {
                $groupId = (int) $groupId;
                if ($groupId === $templateGroupId) continue;

                foreach ($templateTopics as $topic) {
                    $targetTopicId = DB::table('topics')
                        ->where('subject_id', $subject->id)->where('group_id', $groupId)
                        ->where('name', $topic->name)->value('id');
                    if (! $targetTopicId) {
                        $targetTopicId = DB::table('topics')->insertGetId([
                            'subject_id' => $subject->id, 'group_id' => $groupId,
                            'name' => $topic->name, 'display_order' => $topic->display_order ?? 0,
                            'created_at' => $topic->created_at, 'updated_at' => $topic->updated_at,
                        ]);
                    }

                    foreach (DB::table('stopics')->where('topic_id', $topic->id)->orderBy('id')->get() as $stopic) {
                        $exists = DB::table('stopics')->where('subject_id', $subject->id)
                            ->where('group_id', $groupId)->where('topic_id', $targetTopicId)
                            ->where('name', $stopic->name)->exists();
                        if ($exists) continue;
                        DB::table('stopics')->insert([
                            'subject_id' => $subject->id, 'group_id' => $groupId,
                            'topic_id' => $targetTopicId, 'name' => $stopic->name,
                            'display_order' => $stopic->display_order ?? 0,
                            'created_at' => $stopic->created_at, 'updated_at' => $stopic->updated_at,
                        ]);
                    }
                }
            }
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) return false;
        return collect(Schema::getIndexes($table))->contains(
            fn (array $definition) => ($definition['name'] ?? null) === $index
        );
    }

    private function backfillQuestionTaxonomies(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->backfillQuestionTaxonomiesSqlite();

            return;
        }

        $timestamp = now()->format('Y-m-d H:i:s');

        DB::statement(
            <<<'SQL'
                INSERT INTO question_taxonomies
                    (organization_id, question_id, group_id, subject_id, topic_id, stopic_id, created_at, updated_at)
                SELECT
                    COALESCE(q.organization_id, g.organization_id),
                    qg.question_id,
                    qg.group_id,
                    q.subject_id,
                    target_topic.id,
                    target_stopic.id,
                    ?,
                    ?
                FROM question_groups qg
                INNER JOIN questions q ON q.id = qg.question_id
                INNER JOIN `groups` g ON g.id = qg.group_id
                LEFT JOIN topics source_topic ON source_topic.id = q.topic_id
                LEFT JOIN stopics source_stopic ON source_stopic.id = q.stopic_id
                LEFT JOIN topics target_topic
                    ON target_topic.group_id = qg.group_id
                    AND target_topic.subject_id = q.subject_id
                    AND target_topic.name = source_topic.name
                LEFT JOIN stopics target_stopic
                    ON target_stopic.group_id = qg.group_id
                    AND target_stopic.topic_id = target_topic.id
                    AND target_stopic.name = source_stopic.name
                ON DUPLICATE KEY UPDATE
                    organization_id = VALUES(organization_id),
                    subject_id = VALUES(subject_id),
                    topic_id = VALUES(topic_id),
                    stopic_id = VALUES(stopic_id),
                    updated_at = VALUES(updated_at)
                SQL,
            [$timestamp, $timestamp]
        );
    }
    private function backfillQuestionTaxonomiesSqlite(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');

        DB::statement(
            <<<'SQL'
                INSERT INTO question_taxonomies
                    (organization_id, question_id, group_id, subject_id, topic_id, stopic_id, created_at, updated_at)
                SELECT
                    COALESCE(q.organization_id, g.organization_id),
                    qg.question_id,
                    qg.group_id,
                    q.subject_id,
                    target_topic.id,
                    target_stopic.id,
                    ?,
                    ?
                FROM question_groups qg
                INNER JOIN questions q ON q.id = qg.question_id
                INNER JOIN "groups" g ON g.id = qg.group_id
                LEFT JOIN topics source_topic ON source_topic.id = q.topic_id
                LEFT JOIN stopics source_stopic ON source_stopic.id = q.stopic_id
                LEFT JOIN topics target_topic
                    ON target_topic.group_id = qg.group_id
                    AND target_topic.subject_id = q.subject_id
                    AND target_topic.name = source_topic.name
                LEFT JOIN stopics target_stopic
                    ON target_stopic.group_id = qg.group_id
                    AND target_stopic.topic_id = target_topic.id
                    AND target_stopic.name = source_stopic.name
                WHERE 1 = 1
                ON CONFLICT(question_id, group_id) DO UPDATE SET
                    organization_id = excluded.organization_id,
                    subject_id = excluded.subject_id,
                    topic_id = excluded.topic_id,
                    stopic_id = excluded.stopic_id,
                    updated_at = excluded.updated_at
                SQL,
            [$timestamp, $timestamp]
        );
    }
};
