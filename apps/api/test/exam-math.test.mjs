import { test } from "node:test";
import assert from "node:assert/strict";
import { texToMathML } from "../../admin/src/exam-math.ts";
test("exam formulas use a bounded isolated MathJax parser without external loaders", () => {
  for (const tex of [
    String.raw`\frac{1}{2}`,
    String.raw`\sqrt{x^2+1}`,
    String.raw`\begin{pmatrix}a&b\\c&d\end{pmatrix}`,
    String.raw`\ce{H2O}`,
    String.raw`\sum_{n=1}^{10}n`,
  ]) {
    const mml = texToMathML(tex);
    assert.match(mml, /<math/);
    assert.ok(!mml.includes("<merror"));
  }
  assert.throws(() => texToMathML("x".repeat(10001)));
  assert.throws(() => texToMathML(String.raw`\require{html}`));
  assert.throws(() => texToMathML(String.raw`\href{https://evil.test}{x}`));
  assert.throws(() => texToMathML(String.raw`\def\repeat{\repeat}\repeat`));
  assert.match(
    texToMathML(String.raw`\newcommand{\localmacro}{x}\localmacro`),
    /<mi[^>]*>x<\/mi>/,
  );
  assert.throws(() => texToMathML(String.raw`\localmacro`));
});
