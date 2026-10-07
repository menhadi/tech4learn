<?php

namespace App\Services;

use App\Support\AiProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlashcardAiGeneratorService
{
    public function generate(array $source, array $context, int $cardCount, string $requestedType = 'auto'): array
    {
        $ai = AiProvider::firstAvailable(getConfiguration());

        if (! $ai) {
            throw new \RuntimeException('No AI API key is configured for flashcard generation.');
        }

        $prompt = $this->buildPrompt($source, $context, $cardCount);
        $content = $this->callAi($ai, $prompt);
        $cards = $this->extractCards($content);

        if (empty($cards)) {
            throw new \RuntimeException('AI did not return valid flashcards. Please try again with clearer source content.');
        }

        return array_map(function (array $card) {
            return [
                'front' => $this->cleanContent($card['front'] ?? ''),
                'back' => $this->cleanContent($card['back'] ?? ''),
                'card_type' => 'basic',
                'options' => null,
                'explanation' => null,
                'hint' => Str::limit(trim(strip_tags((string) ($card['hint'] ?? ''))), 500, ''),
                'difficulty' => in_array($card['difficulty'] ?? '', ['Easy', 'Medium', 'Hard'], true)
                    ? $card['difficulty']
                    : 'Medium',
            ];
        }, $cards);
    }

    public function sourceFromUrl(string $url): array
    {
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'ExamElite Flashcard Generator'])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Could not read URL: ' . $url);
        }

        $title = $url;
        $html = $response->body();

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8')) ?: $url;
        }

        $html = preg_replace('/<(script|style|noscript|svg|nav|footer|header)[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = $this->normalizeText(strip_tags($html));

        if ($text === '') {
            throw new \RuntimeException('URL has no readable text: ' . $url);
        }

        return [
            'type' => 'url',
            'label' => $title,
            'url' => $url,
            'text' => $text,
        ];
    }

    public function sourceFromUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $label = $file->getClientOriginalName();

        if ($extension === 'txt') {
            $text = file_get_contents($file->getRealPath());
        } elseif ($extension === 'pdf') {
            $text = $this->extractPdfText($file->getRealPath());
        } else {
            throw new \RuntimeException('Only PDF and TXT files are supported for AI flashcard generation right now.');
        }

        $text = $this->normalizeText((string) $text);

        if ($text === '') {
            throw new \RuntimeException('No readable text found in file: ' . $label);
        }

        return [
            'type' => $extension,
            'label' => $label,
            'url' => null,
            'text' => $text,
        ];
    }

    public function sourceFromText(string $text): array
    {
        $text = $this->normalizeText($text);

        if ($text === '') {
            throw new \RuntimeException('Paste content is empty.');
        }

        return [
            'type' => 'text',
            'label' => 'Pasted content',
            'url' => null,
            'text' => $text,
        ];
    }

    private function buildPrompt(array $source, array $context, int $cardCount): string
    {
        $sourceText = Str::limit($source['text'], 18000, "\n[Source truncated]");
        $package = $context['package'] ?? 'Package';
        $set = $context['set'] ?? 'Flashcard Set';
        $subject = $context['subject'] ?? 'General';
        $topic = $context['topic'] ?? '';
        $subtopic = $context['subtopic'] ?? '';
        $sourceLabel = $source['label'] ?? 'Source';

        return <<<PROMPT
Create {$cardCount} high-quality study-content flashcards from the source below.

Context:
- Package: {$package}
- Flashcard set: {$set}
- Subject: {$subject}
- Topic: {$topic}
- Subtopic: {$subtopic}
- Source: {$sourceLabel}

Quality rules:
- Treat the source as the main concept/theory/formula reference.
- Use only facts supported by the source. Do not invent.
- IMPORTANT: Do not create quiz questions, MCQs, true/false items, fill blanks, answer options, or answer keys.
- Questions will be attached separately from the verified platform question bank.
- The front side must contain the concept, formula, definition, rule, or prompt students should study before answering the attached question.
- The back side must contain the explanation, derivation, key points, common mistakes, memory hook, or exam-facing application for the concept.
- Avoid duplicate cards and avoid trivial questions.
- Prefer important concepts, definitions, formulas, exceptions, common mistakes, and exam-facing applications.
- Support MathJax/LaTeX. Use inline math like \\( a^2+b^2=c^2 \\). Use chemical notation like \\( H_2SO_4 \\) or \\( \\ce{H2SO4} \\) when useful.
- Keep HTML simple: p, br, strong, em, ul, ol, li, sup, sub are allowed.
- Keep the front concise enough to fit on a card.
- Make the back useful enough to support review after the student answers a linked question.

Return ONLY valid JSON. No markdown. Format:
[
  {
    "front": "Study concept or formula",
    "back": "Explanation, derivation, or key points",
    "hint": "Short hint",
    "difficulty": "Easy|Medium|Hard"
  }
]

Source content:
{$sourceText}
PROMPT;
    }

    private function callAi(array $ai, string $prompt): string
    {
        try {
            $content = AiProvider::generateText($ai, $prompt, 'You create accurate educational flashcards. Return only valid JSON.', 0.2, 8192, 90);
            if (is_string($content) && trim($content) !== '') return $content;
        } catch (\Throwable $e) {
            Log::warning('AI flashcard generation request failed', ['provider' => $ai['provider'] ?? null, 'model' => $ai['model'] ?? null, 'message' => $e->getMessage()]);
        }
        throw new \RuntimeException('AI flashcard generation failed. Please check AI settings and try again.');
    }

    private function extractCards(?string $content): array
    {
        $content = trim((string) $content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $this->normalizeCards($decoded);
        }

        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $this->normalizeCards($decoded);
            }
        }

        return [];
    }

    private function normalizeCards(array $cards): array
    {
        return array_values(array_filter($cards, function ($card) {
            return is_array($card)
                && trim((string) ($card['front'] ?? '')) !== ''
                && trim((string) ($card['back'] ?? '')) !== '';
        }));
    }

    private function normalizeCardType(?string $cardType): string
    {
        $cardType = strtolower(trim((string) $cardType));

        return in_array($cardType, ['auto', 'basic', 'mcq', 'true_false', 'fill_blank', 'multi_select'], true)
            ? $cardType
            : 'basic';
    }

    private function normalizeOptions($options, string $cardType): ?array
    {
        $cardType = $this->normalizeCardType($cardType);

        if (in_array($cardType, ['auto', 'basic', 'fill_blank'], true)) {
            return null;
        }

        if ($cardType === 'true_false') {
            return ['True', 'False'];
        }

        if (is_string($options)) {
            $options = preg_split('/\R+/', $options) ?: [];
        }

        if (! is_array($options)) {
            return null;
        }

        $items = collect($options)
            ->map(fn ($option) => trim(strip_tags((string) $option)))
            ->filter()
            ->unique()
            ->take($cardType === 'multi_select' ? 6 : 5)
            ->values()
            ->all();

        return empty($items) ? null : $items;
    }

    private function cleanContent(string $content): string
    {
        $content = trim($content);
        $content = strip_tags($content, '<p><br><strong><b><em><i><ul><ol><li><sup><sub><code>');

        return $content;
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text);

        return trim((string) $text);
    }

    private function extractPdfText(string $path): string
    {
        if (! function_exists('shell_exec')) {
            return '';
        }

        $command = 'pdftotext ' . escapeshellarg($path) . ' - 2>NUL';
        if (DIRECTORY_SEPARATOR === '/') {
            $command = 'pdftotext ' . escapeshellarg($path) . ' - 2>/dev/null';
        }

        return (string) shell_exec($command);
    }
}
