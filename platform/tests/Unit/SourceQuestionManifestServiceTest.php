<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\SourceQuestionManifestService;
use PHPUnit\Framework\TestCase;

class SourceQuestionManifestServiceTest extends TestCase
{
    public function test_it_reports_a_source_visual_missing_from_the_stored_question(): void
    {
        $question = new Question();
        $question->forceFill(['question' => '<p>Read the diagram.</p>', 'option1' => 'A']);
        $manifest = [
            'status' => 'ready', 'schema_version' => 1,
            'visuals' => [['target_field' => 'question', 'private_path' => 'private/q1.png']],
        ];

        $findings = (new SourceQuestionManifestService())->compareStored($question, $manifest);

        $this->assertCount(1, $findings);
        $this->assertSame('source_visual_mismatch', $findings[0]['type']);
        $this->assertSame('question', $findings[0]['evidence']['target_field']);
    }

    public function test_it_does_not_report_matching_visual_counts(): void
    {
        $question = new Question();
        $question->forceFill(['question' => '<p>Read.</p><img src="/q1.png">']);
        $manifest = [
            'status' => 'ready', 'schema_version' => 1,
            'visuals' => [['target_field' => 'question', 'private_path' => 'private/q1.png']],
        ];

        $this->assertSame([], (new SourceQuestionManifestService())->compareStored($question, $manifest));
    }
}