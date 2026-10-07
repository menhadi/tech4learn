<?php

namespace Tests\Unit;

use App\Services\ExamQualitySourceReviewer;
use App\Services\ExamQualitySourceStorage;
use App\Services\QuestionRepairService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExamQualityCombinedReviewTest extends TestCase
{
    public function test_source_and_academic_proposals_merge_without_overwriting_conflicts(): void
    {
        $method = new ReflectionMethod(ExamQualitySourceReviewer::class, 'mergeReviewProposals');
        $method->setAccessible(true);

        [$merged, $conflicts] = $method->invoke(new ExamQualitySourceReviewer(),
            ['question' => 'Source wording', 'option1' => 'Shared option'],
            ['question' => 'Academic rewrite', 'option1' => 'Shared option', 'explanation' => 'Academic explanation']
        );

        $this->assertSame('Source wording', $merged['question']);
        $this->assertSame('Academic explanation', $merged['explanation']);
        $this->assertSame('question', $conflicts[0]['field']);
        $this->assertSame('manual_review', $conflicts[0]['resolution']);
    }

    public function test_nested_combined_response_keeps_review_boundaries(): void
    {
        $method = new ReflectionMethod(ExamQualitySourceReviewer::class, 'sanitizeFidelityResponse');
        $method->setAccessible(true);
        $decoded = $method->invoke(new ExamQualitySourceReviewer(), [
            'source_review' => [
                'match_status' => 'minor_difference', 'source_reference' => 'page 1', 'source_excerpt' => 'literal',
                'answer_source_verified' => false, 'proposed_fields' => ['question' => 'Source text', 'answer' => 'unsafe'], 'issues' => [],
            ],
            'academic_review' => [
                'confidence' => 90, 'proposed_fields' => ['explanation' => 'Reasoned explanation'],
                'issues' => [['title' => 'Explanation mismatch']],
            ],
        ]);

        $this->assertSame(['question' => 'Source text'], $decoded['source_review']['proposed_fields']);
        $this->assertSame('Reasoned explanation', $decoded['academic_review']['proposed_fields']['explanation']);
    }

    public function test_source_text_lane_uses_only_attached_pdfs_and_keeps_academic_review_independent(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/ExamQualitySourceReviewer.php');

        $this->assertStringContainsString('attached active QUESTION, COMBINED, and ANSWERS PDFs are the sole source of authority', $source);
        $this->assertStringContainsString('Keep source_review and academic_review independent.', $source);
        $this->assertStringContainsString('private function attachedPdfSources(', $source);
        $this->assertStringContainsString('private function textAuditHtml(', $source);
        $this->assertStringNotContainsString('Question-specific source', $source);
        $this->assertStringNotContainsString('webSourceText(', $source);
        $this->assertStringNotContainsString('questionImages(', $source);
        $this->assertStringNotContainsString('STORED IMAGE FOR QUESTION_ID', $source);
    }

    public function test_fractional_provider_confidence_is_normalized_to_percent(): void
    {
        $method = new ReflectionMethod(ExamQualitySourceReviewer::class, 'normalizedConfidence');
        $method->setAccessible(true);
        $this->assertSame(90.0, $method->invoke(new ExamQualitySourceReviewer(), 0.9, 0));
        $this->assertSame(90.0, $method->invoke(new ExamQualitySourceReviewer(), 90, 0));
    }

    public function test_unresolved_critical_image_work_blocks_ready_draft(): void
    {
        $method = new ReflectionMethod(QuestionRepairService::class, 'imageNeedsReview');
        $method->setAccessible(true);
        $service = new QuestionRepairService();

        $this->assertTrue($method->invoke($service, ['status' => 'storage_unavailable'], []));
        $this->assertTrue($method->invoke($service, ['status' => 'visual_not_detected'], ['action' => 'extract']));
        $this->assertFalse($method->invoke($service, ['status' => 'canonical_source_visuals_applied'], ['action' => 'extract']));
        $this->assertFalse($method->invoke($service, ['status' => 'canonical_source_has_no_detected_visual'], []));
    }

    public function test_repair_and_remote_source_temporary_files_use_system_temp(): void
    {
        $cases = [
            [new ExamQualitySourceStorage(), 'temporaryFile', []],
            [new QuestionRepairService(), 'temporaryFile', ['.png']],
        ];

        foreach ($cases as [$service, $methodName, $arguments]) {
            $method = new ReflectionMethod($service, $methodName);
            $method->setAccessible(true);
            $path = $method->invoke($service, 'exam-quality-test-', ...$arguments);
            try {
                $this->assertFileExists($path);
                $this->assertStringStartsWith(strtolower(realpath(sys_get_temp_dir())), strtolower(realpath($path)));
            } finally {
                @unlink($path);
            }
        }
    }
}
