<?php

namespace Tests\Unit;

use App\Services\GoogleSheets\GoogleSheetResourceRegistry;
use PHPUnit\Framework\TestCase;

class GoogleSheetsSyncContractTest extends TestCase
{
    public function test_registry_exposes_only_approved_admin_resources(): void
    {
        $registry = new GoogleSheetResourceRegistry;

        $this->assertSame(
            ['exams', 'subjects', 'topics', 'subtopics', 'groups', 'categories', 'subcategories', 'packages', 'questions'],
            array_keys($registry->resources())
        );
        $this->assertArrayNotHasKey('users', $registry->resources());
        $this->assertArrayNotHasKey('orders', $registry->resources());
    }

    public function test_field_selection_cannot_include_raw_database_columns(): void
    {
        $registry = new GoogleSheetResourceRegistry;

        $this->assertSame(
            ['name', 'duration'],
            $registry->selectedFields('exams', ['name', 'password', 'organization_id', 'duration'])
        );
    }

    public function test_routes_and_shared_table_actions_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/web.php');
        $examIndex = file_get_contents($root.'/resources/views/exams/index.blade.php');
        $questionList = file_get_contents($root.'/resources/views/questions/partials/list.blade.php');

        $this->assertStringContainsString("prefix('admin/google-sheets')", $routes);
        $this->assertStringContainsString("name('admin.google-sheets.')", $routes);
        $this->assertStringContainsString('x-google-sheets-button resource="exams"', $examIndex);
        $this->assertStringContainsString('x-google-sheets-button resource="questions"', $questionList);
    }

    public function test_system_columns_are_not_user_selected_fields(): void
    {
        $registry = new GoogleSheetResourceRegistry;
        $fields = array_keys($registry->definition('exams')['fields']);

        $this->assertNotContains('record_id', $fields);
        $this->assertNotContains('record_version', $fields);
        $this->assertNotContains('sync_status', $fields);
    }
}
