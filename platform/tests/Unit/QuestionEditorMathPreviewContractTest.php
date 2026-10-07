<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuestionEditorMathPreviewContractTest extends TestCase
{
    public function test_question_editor_has_one_equation_workflow_and_student_preview(): void
    {
        $editor = $this->source('resources/views/questions/action.blade.php');

        $this->assertStringNotContainsString('Show Math Equation Builder', $editor);
        $this->assertStringNotContainsString("section('mathlive'", $editor);
        $this->assertStringContainsString('Preview as Student', $editor);
        $this->assertStringContainsString("CKEDITOR.instances[id].getData()", $editor);
        $this->assertStringContainsString("mathJax.typesetPromise([previewShell])", $editor);
        $this->assertStringContainsString("@include('partials.student-mathjax')", $editor);
    }

    public function test_admin_and_exam_delivery_share_one_mathjax_configuration(): void
    {
        $renderer = $this->source('resources/views/partials/student-mathjax.blade.php');

        foreach ([
            'resources/views/students/exams/partials/questions.blade.php',
            'resources/views/students/guest_exams/partials/questions.blade.php',
            'resources/views/questions/action.blade.php',
        ] as $path) {
            $this->assertStringContainsString(
                "@include('partials.student-mathjax')",
                $this->source($path),
            );
        }

        $this->assertStringContainsString("load: ['[tex]/mhchem']", $renderer);
        $this->assertStringContainsString("inlineMath: [['$', '$'], ['\\\\(', '\\\\)']]", $renderer);
        $this->assertStringContainsString("displayMath: [['\\\\[', '\\\\]'], ['$$', '$$']]", $renderer);
        $this->assertStringContainsString('tex-mml-svg.js', $renderer);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path),
        );
    }
}
