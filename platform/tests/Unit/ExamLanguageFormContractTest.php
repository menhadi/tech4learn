<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExamLanguageFormContractTest extends TestCase
{
    public function test_language_picker_submits_and_restores_checked_languages_without_select2(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/exams/action.blade.php');

        $this->assertStringContainsString("isset(\$exam) ? \$exam->languages->pluck('id')->all()", $view);
        $this->assertStringContainsString('type="hidden" name="language_ids[]"', $view);
        $this->assertStringContainsString('class="form-check-input student-language-checkbox"', $view);
        $this->assertStringContainsString('@if(!$isEnglish) name="language_ids[]" @endif', $view);
        $this->assertStringContainsString('@checked($isEnglish || in_array((int) $language->id, $selectedLanguageIds, true))', $view);
        $this->assertStringNotContainsString('class="form-control select2" id="language_ids"', $view);
    }
}
