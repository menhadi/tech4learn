<?php

namespace Tests\Unit;

use App\Services\StructuredContentReviewService;
use Tests\TestCase;

class StructuredContentReviewServiceTest extends TestCase
{
    public function test_it_detects_and_validates_a_semantic_table(): void
    {
        $items = app(StructuredContentReviewService::class)->inspect([], [
            'question' => '<table><thead><tr><th>x</th><th>y</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>',
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('table', $items[0]['type']);
        $this->assertTrue($items[0]['validation']['valid']);
    }

    public function test_it_flags_a_flattened_or_malformed_table(): void
    {
        $items = app(StructuredContentReviewService::class)->inspect(
            ['question' => '<table><tr><th>x</th><th>y</th></tr><tr><td>1</td><td>2</td></tr></table>'],
            ['question' => '<table><tr><td>1</td></tr></table>']
        );

        $this->assertCount(1, $items);
        $this->assertFalse($items[0]['validation']['valid']);
        $this->assertSame('needs_review', $items[0]['status']);
    }

    public function test_it_validates_mathjax_matrix_source(): void
    {
        $items = app(StructuredContentReviewService::class)->inspect([], [
            'question' => '<p>\\[A=\\begin{bmatrix}1 & 2 \\\\ 3 & 4\\end{bmatrix}\\]</p>',
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('matrix', $items[0]['type']);
        $this->assertTrue($items[0]['validation']['valid']);
    }

    public function test_it_rejects_unbalanced_mathjax_source(): void
    {
        $items = app(StructuredContentReviewService::class)->inspect([], [
            'question' => '<p>\\(\\frac{x}{y\\)</p>',
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('equation', $items[0]['type']);
        $this->assertFalse($items[0]['validation']['valid']);
    }

    public function test_it_ignores_plain_prose(): void
    {
        $items = app(StructuredContentReviewService::class)->inspect([], [
            'question' => '<p>Which statement is correct?</p>',
        ]);

        $this->assertSame([], $items);
    }
}
