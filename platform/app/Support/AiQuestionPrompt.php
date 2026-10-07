<?php

namespace App\Support;

class AiQuestionPrompt
{
    public static function defaults(): array
    {
        return [
            'quality' => "Create an original, exam-standard question. Match the specified exam context, difficulty and language. Test understanding rather than recall. Do not merely paraphrase the original or only change numbers. Avoid ambiguity. For MCQs, provide exactly one defensible answer and plausible distractors based on common mistakes. Preserve valid MathJax/LaTeX. Verify the answer before responding. Provide a concise step-by-step explanation. Return only valid JSON with no markdown.",
            'M' => "Create one multiple-choice variant using the full context below.\n{context}\nReturn JSON: {\"question\":\"text\",\"option1\":\"A\",\"option2\":\"B\",\"option3\":\"C\",\"option4\":\"D\",\"correct_option_number\":\"1\",\"explanation\":\"step-by-step explanation\"}",
            'T' => "Create one true/false variant using the full context below.\n{context}\nReturn JSON: {\"question\":\"statement\",\"true_false_answer\":\"true\",\"explanation\":\"explanation\"}",
            'F' => "Create one fill-in-the-blank variant using the full context below.\n{context}\nReturn JSON: {\"question\":\"sentence with ______\",\"fill_blank_answer\":\"answer\",\"explanation\":\"explanation\"}",
            'S' => "Create one subjective variant using the full context below.\n{context}\nReturn JSON: {\"question\":\"text\",\"subjective_answer\":\"model answer\",\"explanation\":\"marking guidance\"}",
            'NAT' => "Create one Numerical Answer Type (NAT) variant using the full context below. Preserve the original evaluation method (exact, range, or tolerance), use numerical values only, and verify the answer.\n{context}\nReturn JSON: {\"question\":\"text\",\"nat_mode\":\"exact|range|tolerance\",\"nat_value\":1.25,\"nat_min\":1.2,\"nat_max\":1.3,\"nat_tolerance\":0.05,\"explanation\":\"step-by-step explanation\"}. Include nat_value for exact/tolerance; include nat_min and nat_max for range.",
        ];
    }

    public static function render(?string $quality, ?string $template, string $context, string $type): string
    {
        $defaults = self::defaults();
        $selectedTemplate = $template ?: ($defaults[$type] ?? $defaults['M']);
        $body = str_replace('{context}', $context, $selectedTemplate);
        if (! str_contains($selectedTemplate, '{context}')) {
            $body .= "\n\nContext:\n".$context;
        }
        return trim(($quality ?: $defaults['quality'])."\n\n".$body);
    }
}
