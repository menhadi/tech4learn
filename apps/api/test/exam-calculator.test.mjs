import { test } from "node:test";
import assert from "node:assert/strict";
import { examCalculator as calculate } from "../../admin/src/exam-calculator.ts";
test("native calculator semantics preserve precedence, radians and bounded expression parsing", () => {
  for (const [expression, expected] of [
    ["2+3*4", 14],
    ["(2+3)*4", 20],
    ["sin(0)", 0],
    ["cos(0)", 1],
    ["log(100)", 2],
    ["ln(1)", 0],
    ["sqrt(81)", 9],
    ["7%4", 3],
    ["-2*-3", 6],
    ["1/3", 0.3333333333],
  ])
    assert.equal(calculate(expression), expected);
  for (const expression of [
    "1/0",
    "sqrt(-1)",
    "sin(0",
    "2+",
    "globalThis.process.exit()",
    "2**3",
    "(".repeat(257),
  ])
    assert.throws(() => calculate(expression));
});
