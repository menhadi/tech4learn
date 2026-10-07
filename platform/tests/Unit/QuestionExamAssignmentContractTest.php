<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuestionExamAssignmentContractTest extends TestCase
{
    public function test_question_library_exposes_usage_and_assignment_filters(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/QuestionController.php');
        $list = file_get_contents(__DIR__.'/../../resources/views/questions/partials/list.blade.php');
        $index = file_get_contents(__DIR__.'/../../resources/views/questions/index.blade.php');

        $this->assertStringContainsString("->withCount('exams')", $controller);
        $this->assertStringContainsString("whereDoesntHave('exams')", $controller);
        $this->assertStringContainsString('js-exam-assignment-open', $list);
        $this->assertStringContainsString('js-exam-usage-badge', $list);
        $this->assertStringContainsString('exam-assignment-filter', $index);
        $this->assertStringContainsString("questions.partials.exam-assignment-modal", $index);
    }

    public function test_assignment_endpoints_are_tenant_scoped_and_apply_only_differences(): void
    {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/QuestionExamAssignmentController.php');

        $this->assertStringContainsString("Route::get('questions/{question}/exam-assignments'", $routes);
        $this->assertStringContainsString("Route::put('questions/{question}/exam-assignments'", $routes);
        $this->assertStringContainsString("where('organization_id', \$tenantId)", $controller);
        $this->assertStringContainsString('ensureTenantOwns($question)', $controller);
        $this->assertStringContainsString('$requestedIds->diff($currentIds)', $controller);
        $this->assertStringContainsString('$currentIds->diff($requestedIds)', $controller);
        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertStringContainsString('syncWithoutDetaching', $controller);
        $this->assertStringContainsString("['question_section_id' => \$question->questionSection->id]", $controller);
        $this->assertStringContainsString("audit_log('question.exam_assignments.updated'", $controller);
    }

    public function test_modal_preserves_selection_across_filters_and_reviews_removals(): void
    {
        $modal = file_get_contents(__DIR__.'/../../resources/views/questions/partials/exam-assignment-modal.blade.php');
        $script = file_get_contents(__DIR__.'/../../resources/views/questions/partials/exam-assignment-script.blade.php');

        $this->assertStringContainsString('assignmentGroupFilter', $modal);
        $this->assertStringContainsString('assignmentCategoryFilter', $modal);
        $this->assertStringContainsString('assignmentSubcategoryFilter', $modal);
        $this->assertStringContainsString('assignmentPackageFilter', $modal);
        $this->assertStringContainsString('original: new Set()', $script);
        $this->assertStringContainsString('selected: new Set()', $script);
        $this->assertStringContainsString('difference(state.selected, state.original)', $script);
        $this->assertStringContainsString('difference(state.original, state.selected)', $script);
        $this->assertStringContainsString('exam_ids: [...state.selected]', $script);
        $this->assertStringContainsString('active exams or exams with attempts', $script);
    }
}
