<?php

namespace Tests\Unit;

use App\Services\MathContentNormalizer;
use PHPUnit\Framework\TestCase;

class MathContentNormalizerTest extends TestCase
{
    public function test_it_converts_generated_mathml_to_compact_mathjax_source(): void
    {
        $input = <<<'HTML'
<p>Given radius <span class="mathjax-mathml" style="position:relative"><math xmlns="http://www.w3.org/1998/Math/MathML"><msub><mi>R</mi><mi>e</mi></msub><mo>=</mo><mn>6400</mn><mrow><mtext> </mtext><mi data-mjx-auto-op="false">km</mi></mrow></math></span>.</p>
<p><span class="mathjax-mathml"><math xmlns="http://www.w3.org/1998/Math/MathML"><mtable><mtr><mtd><mi>d</mi></mtd><mtd><mo>=</mo><msub><mi>R</mi><mi>e</mi></msub><mo>−</mo><mi>r</mi></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mn>4400</mn><mi data-mjx-auto-op="false">km</mi></mtd></mtr></mtable></math></span></p>
HTML;

        $result = (new MathContentNormalizer())->normalize($input);

        $this->assertSame('converted', $result['status']);
        $this->assertTrue($result['changed']);
        $this->assertSame(2, $result['math_count']);
        $this->assertStringContainsString('\\(R_{e}=6400\\,\\mathrm{km}\\)', $result['content']);
        $this->assertStringContainsString('\\[\\begin{aligned}d &amp; =R_{e}-r \\\\  &amp; =4400\\mathrm{km}\\end{aligned}\\]', $result['content']);
        $this->assertDoesNotMatchRegularExpression('/<math\b|mathjax-mathml|style=/i', $result['content']);
    }

    public function test_it_removes_unsafe_html_and_attributes_without_damaging_latex(): void
    {
        $input = '<p onclick="steal()">Solve \\(x^2=4\\).</p><script>alert(1)</script><img src="javascript:alert(1)" onerror="steal()"><a href="https://example.test" target="_blank">Source</a>';

        $result = (new MathContentNormalizer())->normalize($input);

        $this->assertSame('sanitized', $result['status']);
        $this->assertStringContainsString('\\(x^2=4\\)', $result['content']);
        $this->assertStringNotContainsString('steal', $result['content']);
        $this->assertStringNotContainsString('alert', $result['content']);
        $this->assertStringNotContainsString('<img', $result['content']);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result['content']);
    }

    public function test_it_refuses_unknown_mathml_instead_of_silently_losing_an_equation(): void
    {
        $input = '<p><math><mmultiscripts><mi>T</mi></mmultiscripts></math></p>';

        $result = (new MathContentNormalizer())->normalize($input);

        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($result['changed']);
        $this->assertSame($input, $result['content']);
        $this->assertStringContainsString('Unsupported MathML element', implode(' ', $result['issues']));
    }
}
