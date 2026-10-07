<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExamImportExportHierarchyContractTest extends TestCase
{
    public function test_export_filters_follow_group_category_subcategory_package_hierarchy(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ExamImportExportController.php');
        $view = file_get_contents(__DIR__.'/../../resources/views/exams/import.blade.php');

        $this->assertStringContainsString('$this->filterData($tenantId, $request)', $controller);
        $this->assertStringContainsString("whereHas('groups'", $controller);
        $this->assertStringContainsString("whereHas('parent.groups'", $controller);
        $this->assertStringContainsString("where('category_level_1', \$categoryId)", $controller);
        $this->assertStringContainsString("where('category_level_2', \$subcategoryId)", $controller);

        $this->assertStringContainsString('id="exam-export-filter"', $view);
        $this->assertStringContainsString('data-filter="group"', $view);
        $this->assertStringContainsString("group: ['category', 'subcategory', 'package']", $view);
        $this->assertStringContainsString('window.location.assign', $view);
    }
}
