// Adapted from ExamElite student calculator: radians, native operator precedence and rounding.
export function examCalculator(input: string): number {
  const functions: Record<string, (value: number) => number> = {
    sin: Math.sin,
    cos: Math.cos,
    tan: Math.tan,
    log: Math.log10,
    ln: Math.log,
    sqrt: Math.sqrt,
  };
  if (input.length > 256) throw new Error("Expression is too long");
  const source = String(input || "").replace(/\s+/g, "");
  const matched = source.match(
    /sin|cos|tan|log|ln|sqrt|\d+(?:\.\d+)?|[()+\-*/%]/g,
  );

  if (!matched || matched.join("") !== source) {
    throw new Error("Invalid expression");
  }

  const tokens = matched;
  let index = 0;
  const peek = () => tokens[index];
  const next = () => tokens[index++];

  function parseExpression(): number {
    let value = parseTerm();
    while (peek() === "+" || peek() === "-") {
      const operator = next();
      const right = parseTerm();
      value = operator === "+" ? value + right : value - right;
    }
    return value;
  }

  function parseTerm(): number {
    let value = parseFactor();
    while (peek() === "*" || peek() === "/" || peek() === "%") {
      const operator = next();
      const right = parseFactor();
      if ((operator === "/" || operator === "%") && right === 0) {
        throw new Error("Division by zero");
      }
      if (operator === "*") value *= right;
      if (operator === "/") value /= right;
      if (operator === "%") value %= right;
    }
    return value;
  }

  function parseFactor(): number {
    const token = next();

    if (token === "+") return parseFactor();
    if (token === "-") return -parseFactor();

    if (functions[token]) {
      if (next() !== "(") throw new Error("Invalid function");
      const value = parseExpression();
      if (next() !== ")") throw new Error("Invalid function");
      return functions[token](value);
    }

    if (token === "(") {
      const value = parseExpression();
      if (next() !== ")") throw new Error("Invalid expression");
      return value;
    }

    if (/^\d+(?:\.\d+)?$/.test(token)) {
      return Number(token);
    }

    throw new Error("Invalid expression");
  }

  const result = parseExpression();
  if (index !== tokens.length || !Number.isFinite(result)) {
    throw new Error("Invalid expression");
  }

  const rounded =
    Math.round((result + Number.EPSILON) * 10000000000) / 10000000000;
  if (!Number.isFinite(rounded)) throw new Error("Result is too large");
  return rounded;
}
