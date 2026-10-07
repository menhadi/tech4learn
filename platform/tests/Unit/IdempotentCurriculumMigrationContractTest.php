<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class IdempotentCurriculumMigrationContractTest extends TestCase
{
    public function test_curriculum_migration_tolerates_partially_applied_schema(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_07_30_000004_isolate_curriculum_taxonomy_by_tenant_and_group.php');

        $this->assertStringContainsString("if (! Schema::hasColumn('subjects', 'organization_id'))", $migration);
        $this->assertStringContainsString("if (! Schema::hasColumn('topics', 'group_id'))", $migration);
        $this->assertStringContainsString("if (! Schema::hasColumn('stopics', 'group_id'))", $migration);
        $this->assertStringContainsString("if (! Schema::hasTable('question_taxonomies'))", $migration);
        $this->assertStringContainsString("private function hasIndex(", $migration);
        $this->assertStringContainsString("subjects_tenant_name_unique", $migration);
        $this->assertStringContainsString("subject_groups_subject_group_unique", $migration);
    }

    public function test_curriculum_data_transforms_reuse_existing_clones(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_07_30_000004_isolate_curriculum_taxonomy_by_tenant_and_group.php');

        $this->assertStringContainsString("->where('organization_id', \$tenantId)", $migration);
        $this->assertStringContainsString("->where('subject_id', \$targetSubjectId)", $migration);
        $this->assertStringContainsString("->whereNull('group_id')", $migration);
        $this->assertStringContainsString("if (\$exists) continue;", $migration);
        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE", $migration);
    }

    public function test_large_taxonomy_backfill_uses_bulk_queries(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_07_30_000004_isolate_curriculum_taxonomy_by_tenant_and_group.php');

        $this->assertStringContainsString('INSERT INTO question_taxonomies', $migration);
        $this->assertStringContainsString('FROM question_groups qg', $migration);
        $this->assertStringNotContainsString('foreach ($rows as $row)', $migration);
        $this->assertStringNotContainsString("\$query->get(['id', 'topic_id', 'stopic_id'])", $migration);
    }
}
