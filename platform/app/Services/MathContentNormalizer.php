<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

class MathContentNormalizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'sup', 'sub', 'span', 'a', 'img', 'hr',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'link', 'meta', 'base',
    ];

    private const OPERATOR_MAP = [
        "−" => '-', "–" => '-', "×" => '\\times ', "÷" => '\\div ',
        "±" => '\\pm ', "∓" => '\\mp ', "·" => '\\cdot ', "⋅" => '\\cdot ',
        "≤" => '\\le ', "≥" => '\\ge ', "≠" => '\\ne ', "≈" => '\\approx ',
        "≡" => '\\equiv ', "→" => '\\to ', "←" => '\\leftarrow ',
        "↔" => '\\leftrightarrow ', "⇒" => '\\Rightarrow ', "⇌" => '\\rightleftharpoons ',
        "∑" => '\\sum ', "∏" => '\\prod ', "∫" => '\\int ', "√" => '\\sqrt{}',
        "∞" => '\\infty ', "∂" => '\\partial ', "∇" => '\\nabla ',
        "∈" => '\\in ', "∉" => '\\notin ', "⊂" => '\\subset ', "⊆" => '\\subseteq ',
        "∪" => '\\cup ', "∩" => '\\cap ', "∴" => '\\therefore ', "∵" => '\\because ',
        "′" => '\\prime ', "″" => '\\prime\\prime ', "°" => '^{\\circ}',
        "⁢" => '', "⁣" => ',', "⁡" => '', " " => ' ',
    ];

    private const IDENTIFIER_MAP = [
        'α' => '\\alpha ', 'β' => '\\beta ', 'γ' => '\\gamma ', 'δ' => '\\delta ',
        'ε' => '\\epsilon ', 'θ' => '\\theta ', 'λ' => '\\lambda ', 'μ' => '\\mu ',
        'π' => '\\pi ', 'ρ' => '\\rho ', 'σ' => '\\sigma ', 'τ' => '\\tau ',
        'φ' => '\\phi ', 'ω' => '\\omega ', 'Δ' => '\\Delta ', 'Ω' => '\\Omega ',
    ];

    public function normalize(?string $content): array
    {
        $original = (string) $content;
        if (trim($original) === '') {
            return $this->result($original, 'clean', false, 0);
        }

        if (! class_exists(DOMDocument::class)) {
            return $this->result($original, 'needs_review', false, 0, ['PHP DOM extension is required.']);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $repairable = $this->repairMixedInlineDelimiters($original);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="math-content-root">'.$repairable.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
            if (! $loaded) {
                return $this->result($original, 'needs_review', false, 0, ['Content could not be parsed as HTML.']);
            }

            $xpath = new DOMXPath($document);
            $mathNodes = [];
            foreach ($xpath->query('//*[local-name()="math"]') ?: [] as $node) {
                $mathNodes[] = $node;
            }

            $converted = 0;
            $removedEmpty = 0;
            foreach (array_reverse($mathNodes) as $math) {
                if (! $math->parentNode) {
                    continue;
                }

                $target = $this->generatedMathWrapper($math);
                $tex = trim($this->mathNodeToTex($math));
                if ($tex === '') {
                    if ($this->isIgnorableEmptyMath($math)) {
                        $target->parentNode?->removeChild($target);
                        $removedEmpty++;
                        continue;
                    }

                    throw new \RuntimeException('A MathML expression produced empty TeX source.');
                }

                $display = strtolower((string) $this->attribute($math, 'display')) === 'block'
                    || $math->getElementsByTagName('mtable')->length > 0;
                $replacement = $document->createTextNode($display ? '\\['.$tex.'\\]' : '\\('.$tex.'\\)');
                $target->parentNode?->replaceChild($replacement, $target);
                $converted++;
            }

            $this->sanitizeDocument($document);
            $root = $document->getElementById('math-content-root');
            if (! $root) {
                return $this->result($original, 'needs_review', false, $converted, ['Normalized content root was lost.']);
            }

            $normalized = '';
            foreach ($root->childNodes as $child) {
                $normalized .= $document->saveHTML($child);
            }
            $normalized = trim($normalized);

            $issues = $this->validate($original, $normalized, $converted, count($mathNodes) - $removedEmpty);
            if ($issues !== []) {
                return $this->result($original, 'needs_review', false, $converted, $issues, $normalized);
            }

            $changed = $normalized !== trim($original);
            $status = $converted > 0 ? 'converted' : ($changed ? 'sanitized' : 'clean');

            return $this->result($normalized, $status, $changed, $converted);
        } catch (Throwable $exception) {
            return $this->result($original, 'needs_review', false, 0, [$exception->getMessage()]);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Repair legacy delimiter forms at display time without rewriting valid HTML.
     * This is intentionally narrower than normalize(): it only handles the
     * malformed forms produced by older source imports.
     */
    public function repairForDisplay(?string $content): string
    {
        $content = (string) $content;

        $content = preg_replace(
            '/(?<=[A-Za-z0-9,.;:()\]\}])\s*\$\$\s*(.+?)\s*\$\$(?=\s*[A-Za-z0-9,.;:()\[\{])/s',
            '\\($1\\)',
            $content
        ) ?? $content;

        // Some imported rows use $$...$$ inline with no surrounding text
        // character on one side. Convert those single-line forms as well;
        // standalone display equations (which contain a line break) remain
        // available to MathJax as $$...$$.
        $content = preg_replace(
            '/\$\$\s*([^$\r\n]+?)\s*\$\$/s',
            '\\($1\\)',
            $content
        ) ?? $content;
        // Older imports wrapped ordinary connective words in TeX delimiters.
        $content = preg_replace_callback(
            '/\\\\\((and|or|then|where|because)\\\\\)/i',
            static fn (array $match): string => $match[1],
            $content
        ) ?? $content;

        return $content;
    }
    private function repairMixedInlineDelimiters(string $content): string
    {
        $content = preg_replace_callback(
            '/\\\\\((?:(?!<|\\\\\)).)*?\K(?<!\\\\)\$\$/s',
            static fn () => '\\)',
            $content
        ) ?? $content;

        return preg_replace_callback(
            '/(?<!\\\\)\$\$(?=(?:(?!<|\\\\\().)*?\\\\\))/s',
            static fn () => '\\(',
            $content
        ) ?? $content;
    }

    private function mathNodeToTex(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return $node->nodeValue ?? '';
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $name = strtolower($node->localName ?: $node->nodeName);
        $children = fn () => $this->childrenToTex($node);

        return match ($name) {
            'math', 'm', 'mrow', 'mstyle', 'mpadded', 'semantics' => $this->semanticChildrenToTex($node),
            'mi' => $this->identifierToTex($node),
            'mn' => trim((string) $node->textContent),
            'mo' => $this->operatorToTex((string) $node->textContent),
            'mtext' => $this->textToTex((string) $node->textContent),
            'ms' => '\\text{'.$this->escapeText((string) $node->textContent).'}',
            'mspace' => $this->spaceToTex($node),
            'msub' => $this->scriptToTex($node, 'sub'),
            'msup' => $this->scriptToTex($node, 'sup'),
            'msubsup' => $this->scriptToTex($node, 'subsup'),
            'mfrac' => $this->fractionToTex($node),
            'msqrt' => '\\sqrt{'.$children().'}',
            'mroot' => $this->rootToTex($node),
            'mfenced' => $this->fencedToTex($node),
            'mtable' => $this->tableToTex($node),
            'mtr', 'mlabeledtr' => $this->rowToTex($node),
            'mtd' => $children(),
            'mover' => $this->overUnderToTex($node, 'over'),
            'munder' => $this->overUnderToTex($node, 'under'),
            'munderover' => $this->overUnderToTex($node, 'underover'),
            'menclose' => $this->encloseToTex($node),
            'mphantom' => '\\phantom{'.$children().'}',
            'maligngroup', 'malignmark' => '&',
            'none', 'annotation', 'annotation-xml' => '',
            default => throw new \RuntimeException('Unsupported MathML element: '.$name),
        };
    }

    private function semanticChildrenToTex(DOMNode $node): string
    {
        foreach ($this->elementChildren($node) as $child) {
            if (! in_array(strtolower($child->localName ?: $child->nodeName), ['annotation', 'annotation-xml'], true)) {
                if (strtolower($node->localName ?: $node->nodeName) === 'semantics') {
                    return $this->mathNodeToTex($child);
                }
            }
        }

        return $this->childrenToTex($node);
    }

    private function isIgnorableEmptyMath(DOMNode $node): bool
    {
        if (trim((string) $node->textContent) !== '') {
            return false;
        }

        $ignorable = [
            'math', 'm', 'mrow', 'mstyle', 'mpadded', 'mo', 'mspace',
            'msub', 'msup', 'msubsup', 'mfrac', 'mfenced', 'none',
        ];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE
                && (! in_array(strtolower($child->localName ?: $child->nodeName), $ignorable, true)
                    || ! $this->isIgnorableEmptyMath($child))) {
                return false;
            }
        }

        return true;
    }

    private function childrenToTex(DOMNode $node): string
    {
        $tex = '';
        foreach ($node->childNodes as $child) {
            $tex .= $this->mathNodeToTex($child);
        }
        return trim($tex);
    }

    private function identifierToTex(DOMNode $node): string
    {
        $value = trim((string) $node->textContent);
        if (isset(self::IDENTIFIER_MAP[$value])) {
            return self::IDENTIFIER_MAP[$value];
        }
        if (isset(self::OPERATOR_MAP[$value])) {
            return self::OPERATOR_MAP[$value];
        }

        $roman = strtolower((string) $this->attribute($node, 'mathvariant')) === 'normal'
            || strtolower((string) $this->attribute($node, 'data-mjx-auto-op')) === 'false'
            || mb_strlen($value) > 1;

        return $roman ? '\\mathrm{'.$this->escapeIdentifier($value).'}' : $this->escapeIdentifier($value);
    }

    private function operatorToTex(string $operator): string
    {
        $operator = trim($operator);
        if (isset(self::OPERATOR_MAP[$operator])) {
            return self::OPERATOR_MAP[$operator];
        }

        return match ($operator) {
            '{' => '\\{', '}' => '\\}', '_' => '\\_', '<' => '<', '>' => '>',
            default => $operator,
        };
    }

    private function textToTex(string $text): string
    {
        if (trim($text) === '') {
            return '\\,';
        }

        return '\\text{'.$this->escapeText(trim($text)).'}';
    }

    private function spaceToTex(DOMNode $node): string
    {
        return match (strtolower((string) $this->attribute($node, 'width'))) {
            '2em' => '\\qquad ', '1em' => '\\quad ', 'thinmathspace' => '\\,',
            'mediummathspace' => '\\:', 'thickmathspace' => '\\;',
            default => '\\,',
        };
    }

    private function scriptToTex(DOMNode $node, string $mode): string
    {
        $children = $this->elementChildren($node);
        $base = isset($children[0]) ? $this->mathNodeToTex($children[0]) : '';
        $sub = isset($children[1]) ? $this->mathNodeToTex($children[1]) : '';
        $sup = isset($children[2]) ? $this->mathNodeToTex($children[2]) : '';

        return match ($mode) {
            'sub' => $base.'_{'.$sub.'}',
            'sup' => $base.'^{'.$sub.'}',
            default => $base.'_{'.$sub.'}^{'.$sup.'}',
        };
    }

    private function fractionToTex(DOMNode $node): string
    {
        $children = $this->elementChildren($node);
        return '\\frac{'.(isset($children[0]) ? $this->mathNodeToTex($children[0]) : '').'}{'
            .(isset($children[1]) ? $this->mathNodeToTex($children[1]) : '').'}';
    }

    private function rootToTex(DOMNode $node): string
    {
        $children = $this->elementChildren($node);
        return '\\sqrt['.(isset($children[1]) ? $this->mathNodeToTex($children[1]) : '').']{'
            .(isset($children[0]) ? $this->mathNodeToTex($children[0]) : '').'}';
    }

    private function fencedToTex(DOMNode $node): string
    {
        $open = $this->attribute($node, 'open') ?? '(';
        $close = $this->attribute($node, 'close') ?? ')';
        $separator = $this->attribute($node, 'separators') ?? ',';
        $parts = array_map(fn (DOMElement $child) => $this->mathNodeToTex($child), $this->elementChildren($node));

        return '\\left'.$this->delimiterToTex($open).implode($separator.' ', $parts).'\\right'.$this->delimiterToTex($close);
    }

    private function tableToTex(DOMNode $node, bool $horizontalRules = false): string
    {
        $rows = [];
        $columnCount = 0;
        foreach ($this->elementChildren($node) as $row) {
            if (in_array(strtolower($row->localName ?: $row->nodeName), ['mtr', 'mlabeledtr'], true)) {
                $rows[] = $this->rowToTex($row);
                $columnCount = max($columnCount, count(array_filter(
                    $this->elementChildren($row),
                    fn (DOMElement $child) => strtolower($child->localName ?: $child->nodeName) === 'mtd'
                )));
            }
        }
        if ($rows === []) {
            if (trim((string) $node->textContent) === '') {
                return '';
            }
            throw new \RuntimeException('MathML table contains no rows.');
        }

        if ($horizontalRules) {
            $columns = str_repeat('c', max(1, $columnCount));
            return '\\begin{array}{'.$columns.'}\\hline '
                .implode(' \\\\ \\hline ', $rows).' \\\\ \\hline\\end{array}';
        }

        return '\\begin{aligned}'.implode(' \\\\ ', $rows).'\\end{aligned}';
    }

    private function rowToTex(DOMNode $node): string
    {
        return implode(' & ', array_map(
            fn (DOMElement $cell) => $this->mathNodeToTex($cell),
            array_values(array_filter($this->elementChildren($node), fn (DOMElement $child) => strtolower($child->localName ?: $child->nodeName) === 'mtd'))
        ));
    }

    private function overUnderToTex(DOMNode $node, string $mode): string
    {
        $children = $this->elementChildren($node);
        $base = isset($children[0]) ? $this->mathNodeToTex($children[0]) : '';
        $second = isset($children[1]) ? $this->mathNodeToTex($children[1]) : '';
        $third = isset($children[2]) ? $this->mathNodeToTex($children[2]) : '';
        $accent = trim((string) ($children[1]->textContent ?? ''));

        if ($mode === 'over' && $accent === "\u{2015}") {
            return '\\overline{'.$base.'}';
        }

        if ($mode === 'over' && in_array(trim((string) ($children[1]->textContent ?? '')), ['¯', '‾'], true)) {
            return '\\overline{'.$base.'}';
        }
        if ($mode === 'over' && trim((string) ($children[1]->textContent ?? '')) === '^') {
            return '\\hat{'.$base.'}';
        }

        return match ($mode) {
            'over' => '\\overset{'.$second.'}{'.$base.'}',
            'under' => '\\underset{'.$second.'}{'.$base.'}',
            default => '\\underset{'.$second.'}{\\overset{'.$third.'}{'.$base.'}}',
        };
    }

    private function encloseToTex(DOMNode $node): string
    {
        $notation = strtolower((string) $this->attribute($node, 'notation'));
        if (in_array($notation, ['updiagonalstrike', 'downdiagonalstrike'], true)) {
            return '\\not{'.$this->childrenToTex($node).'}';
        }

        if ($notation === '' || str_contains($notation, 'box')) {
            return '\\boxed{'.$this->childrenToTex($node).'}';
        }

        $notations = preg_split('/\s+/', trim($notation)) ?: [];
        $unsupported = array_diff($notations, ['top', 'bottom', 'left', 'right']);
        if ($unsupported === [] && $notations !== []) {
            $children = $this->elementChildren($node);
            $isTable = count($children) === 1
                && strtolower($children[0]->localName ?: $children[0]->nodeName) === 'mtable';
            $hasTop = in_array('top', $notations, true);
            $hasBottom = in_array('bottom', $notations, true);
            $hasLeft = in_array('left', $notations, true);
            $hasRight = in_array('right', $notations, true);

            if ($isTable && $hasTop && $hasBottom) {
                $content = $this->tableToTex($children[0], true);
            } else {
                $content = $this->childrenToTex($node);
                if ($hasBottom) {
                    $content = '\\underline{'.$content.'}';
                }
                if ($hasTop) {
                    $content = '\\overline{'.$content.'}';
                }
            }

            if ($hasLeft || $hasRight) {
                $content = '\\left'.($hasLeft ? '|' : '.').$content.'\\right'.($hasRight ? '|' : '.');
            }

            return $content;
        }

        throw new \RuntimeException('Unsupported MathML menclose notation: '.$notation);
    }

    private function sanitizeDocument(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $elements = [];
        foreach ($xpath->query('//*') ?: [] as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') !== 'math-content-root') {
                $elements[] = $element;
            }
        }

        foreach (array_reverse($elements) as $element) {
            if (! $element->parentNode) {
                continue;
            }
            $tag = strtolower($element->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $element->parentNode->removeChild($element);
                continue;
            }
            if ($tag === 'svg' || str_starts_with($tag, 'mjx-')) {
                $element->parentNode->removeChild($element);
                continue;
            }
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($element);
                continue;
            }
            $this->sanitizeAttributes($element);
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $allowed = match ($tag) {
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'width', 'height'],
            'td', 'th' => ['colspan', 'rowspan', 'scope'],
            'ol' => ['start', 'type'],
            default => [],
        };

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a') {
            $href = $element->getAttribute('href');
            if (! $this->safeUrl($href, true)) {
                $element->removeAttribute('href');
            }
            if ($element->getAttribute('target') !== '_blank') {
                $element->removeAttribute('target');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            if (! $this->safeUrl($element->getAttribute('src'), false)) {
                $element->parentNode?->removeChild($element);
                return;
            }
            foreach (['width', 'height'] as $dimension) {
                if ($element->hasAttribute($dimension) && ! preg_match('/^\d{1,4}$/', $element->getAttribute($dimension))) {
                    $element->removeAttribute($dimension);
                }
            }
        }
    }

    private function validate(string $original, string $normalized, int $converted, int $originalMathCount): array
    {
        $issues = [];
        if (preg_match('/<math\b|mathjax-mathml|<svg\b|<mjx-container\b/i', $normalized)) {
            $issues[] = 'Generated or source MathML markup remains after normalization.';
        }
        if ($originalMathCount > 0 && $converted !== $originalMathCount) {
            $issues[] = 'Not every MathML expression was converted.';
        }
        $inlineExpressions = substr_count($normalized, '\\(');
        $displayExpressions = substr_count($normalized, '\\[');
        if ($converted > 0 && ($inlineExpressions + $displayExpressions) < $converted) {
            $issues[] = 'One or more converted MathML expressions were lost from the normalized content.';
        }
        if ($this->hasMeaningfulContent($original, $originalMathCount > 0)
            && ! $this->hasMeaningfulContent($normalized, false)) {
            $issues[] = 'Normalization would remove all meaningful content.';
        }
        if ($inlineExpressions !== substr_count($normalized, '\\)')) {
            $issues[] = 'Inline MathJax delimiters are unbalanced.';
        }
        if ($displayExpressions !== substr_count($normalized, '\\]')) {
            $issues[] = 'Display MathJax delimiters are unbalanced.';
        }
        $unsafeTag = preg_match('/<\/?(?:script|iframe|object|embed|form)\b/i', $normalized);
        $unsafeEventAttribute = preg_match('/<[^>]+\son[a-z][a-z0-9_-]*\s*=/i', $normalized);
        $unsafeJavascriptUrl = preg_match('/<(?:a|img)\b[^>]+(?:href|src)\s*=\s*(["\'])?\s*javascript\s*:/i', $normalized);
        if ($unsafeTag || $unsafeEventAttribute || $unsafeJavascriptUrl) {
            $issues[] = 'Unsafe HTML remains after sanitization.';
        }

        return $issues;
    }

    private function hasMeaningfulContent(string $content, bool $containsMath): bool
    {
        if ($containsMath || preg_match('/\\\\\(|\\\\\[|<img\\b/i', $content)) {
            return true;
        }

        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\\s\\x{00A0}]+/u', ' ', $text) ?? '') !== '';
    }

    private function generatedMathWrapper(DOMNode $math): DOMNode
    {
        $target = $math;
        $parent = $math->parentNode;
        while ($parent instanceof DOMElement) {
            $tag = strtolower($parent->tagName);
            $class = strtolower($parent->getAttribute('class'));
            if (str_starts_with($tag, 'mjx-') || str_contains($class, 'mathjax-mathml') || str_contains($class, 'mjx-assistive-mml')) {
                $target = $parent;
                $parent = $parent->parentNode;
                continue;
            }
            break;
        }

        return $target;
    }

    private function containsElement(DOMNode $node, string $name): bool
    {
        foreach ($node->getElementsByTagName('*') as $child) {
            if (strtolower($child->localName ?: $child->nodeName) === $name) {
                return true;
            }
        }
        return false;
    }

    private function elementChildren(DOMNode $node): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }
        return $children;
    }

    private function attribute(DOMNode $node, string $name): ?string
    {
        return $node instanceof DOMElement && $node->hasAttribute($name) ? $node->getAttribute($name) : null;
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function safeUrl(string $url, bool $allowMail): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        if (preg_match('~^https://~i', $url)) {
            return true;
        }
        return $allowMail && preg_match('~^mailto:[^\s@]+@[^\s@]+$~i', $url) === 1;
    }

    private function delimiterToTex(string $delimiter): string
    {
        return match ($delimiter) {
            '{' => '\\{', '}' => '\\}', '[' => '[', ']' => ']',
            '|' => '|', '‖' => '\\|', '' => '.', default => $delimiter,
        };
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', '{', '}', '%', '#', '&', '_'], ['\\textbackslash{}', '\\{', '\\}', '\\%', '\\#', '\\&', '\\_'], $text);
    }

    private function escapeIdentifier(string $text): string
    {
        return str_replace(['{', '}', '#', '&', '_'], ['\\{', '\\}', '\\#', '\\&', '\\_'], $text);
    }

    private function result(string $content, string $status, bool $changed, int $mathCount, array $issues = [], ?string $candidate = null): array
    {
        return [
            'content' => $content,
            'candidate' => $candidate,
            'status' => $status,
            'changed' => $changed,
            'math_count' => $mathCount,
            'issues' => $issues,
        ];
    }
}
