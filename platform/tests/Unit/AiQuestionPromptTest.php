<?php

namespace Tests\Unit;

use App\Support\AiQuestionPrompt;
use PHPUnit\Framework\TestCase;

class AiQuestionPromptTest extends TestCase
{
    public function test_nat_default_prompt_contains_context_and_all_answer_modes(): void
    {
        $prompt = AiQuestionPrompt::render(null, null, 'Original answer: {"mode":"range","min":1,"max":2}', 'NAT');

        $this->assertStringContainsString('Numerical Answer Type (NAT)', $prompt);
        $this->assertStringContainsString('Original answer:', $prompt);
        $this->assertStringContainsString('nat_mode', $prompt);
        $this->assertStringContainsString('nat_value', $prompt);
        $this->assertStringContainsString('nat_min', $prompt);
        $this->assertStringContainsString('nat_max', $prompt);
        $this->assertStringContainsString('nat_tolerance', $prompt);
    }
}