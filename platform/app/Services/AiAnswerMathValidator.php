<?php

namespace App\Services;

class AiAnswerMathValidator
{
    public function validate(string $html): void
    {
        if (preg_match('/<\/?(?:sup|sub|math|svg|img)\b|```/i', $html)) {
            throw new \RuntimeException('Formulas must use LaTeX/MathJax source, not HTML, images, or Markdown.');
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/(?<!\\\\)\$/', $text)) {
            throw new \RuntimeException('Use MathJax inline or display delimiters instead of dollar delimiters.');
        }
        $parts = preg_split('/(\\\\[()\[\]])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $closing = null;
        $expression = '';
        $prose = '';
        foreach ($parts as $part) {
            if (in_array($part, ['\\(', '\\['], true)) {
                if ($closing !== null) throw new \RuntimeException('Nested MathJax delimiters are not allowed.');
                $closing = $part === '\\(' ? '\\)' : '\\]';
                $expression = '';
            } elseif (in_array($part, ['\\)', '\\]'], true)) {
                if ($closing !== $part) throw new \RuntimeException('MathJax delimiters are unbalanced.');
                $this->validateExpression($expression);
                $closing = null;
                $prose .= ' ';
            } elseif ($closing !== null) {
                $expression .= $part;
            } else {
                $prose .= $part;
            }
        }
        if ($closing !== null) throw new \RuntimeException('MathJax delimiters are unbalanced.');
        if (preg_match('/\\\\[A-Za-z]+|[=^_<>\x{2070}-\x{209F}\x{2190}-\x{22FF}]|\b[A-Za-z0-9]+\s*[+*\/]\s*[A-Za-z0-9]+|\b(?:[A-Z][a-z]?\d+)+(?:[A-Z][a-z]?\d*)*\b/u', $prose)) {
            throw new \RuntimeException('Equations and chemical formulas must be enclosed in MathJax delimiters.');
        }
    }

    private function validateExpression(string $expression): void
    {
        if (trim($expression) === '') throw new \RuntimeException('MathJax expressions cannot be empty.');
        $depth = 0;
        preg_match_all('/(?<!\\\\)[{}]/', $expression, $braces);
        foreach ($braces[0] as $brace) {
            $depth += $brace === '{' ? 1 : -1;
            if ($depth < 0) throw new \RuntimeException('MathJax braces are unbalanced.');
        }
        if ($depth !== 0) throw new \RuntimeException('MathJax braces are unbalanced.');
        preg_match_all('/\\\\(begin|end)\{([^}]+)\}/', $expression, $environments, PREG_SET_ORDER);
        $stack = [];
        foreach ($environments as $environment) {
            if ($environment[1] === 'begin') $stack[] = $environment[2];
            elseif (array_pop($stack) !== $environment[2]) throw new \RuntimeException('MathJax environments are unbalanced.');
        }
        if ($stack !== []) throw new \RuntimeException('MathJax environments are unbalanced.');
    }
}
