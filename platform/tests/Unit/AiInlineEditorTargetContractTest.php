<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AiInlineEditorTargetContractTest extends TestCase
{
    public function test_inline_ai_preserves_the_clicked_textarea_and_verifies_insertion(): void
    {
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/master.blade.php');

        $this->assertStringContainsString('let explicitAiEditor = null;', $layout);
        $this->assertStringContainsString('currentActiveEditor = explicitAiEditor || detectActiveEditor();', $layout);
        $this->assertStringContainsString('const targetEditor = currentActiveEditor;', $layout);
        $this->assertStringContainsString('insertAtCursor(response.content, targetEditor)', $layout);
        $this->assertMatchesRegularExpression("/explicitAiEditor\\s*=\\s*\\{\\s*type:\\s*'textarea'/", $layout);
        $this->assertStringContainsString("el.setRangeText(generated, start, end, 'end');", $layout);
        $this->assertStringContainsString("el.dispatchEvent(new Event('change', { bubbles: true }));", $layout);
        $this->assertStringContainsString('return el.value.includes(generated);', $layout);
    }
}