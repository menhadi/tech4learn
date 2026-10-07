<?php

namespace App\Support;

use App\Models\Configuration;
use App\Models\SourceExamImport;
use Illuminate\Support\Facades\File;

class SourceExtractorRegistry
{
    public const BUILTIN = 'default_source_exam_extractor.py';
    public const LEGACY_BUILTIN = 'extract-source-exam.py';

    public function available(): array
    {
        $scripts = [];
        $directory = base_path('scripts/extractors');
        if (File::isDirectory($directory)) {
            foreach (File::files($directory) as $file) {
                if (strtolower($file->getExtension()) !== 'py') {
                    continue;
                }
                $name = $file->getFilename();
                $scripts[$name] = $name === self::BUILTIN
                    ? 'Default source exam extractor (reviewable Python)'
                    : $file->getFilenameWithoutExtension();
            }
        }

        uasort($scripts, function (string $a, string $b): int {
            if (str_starts_with($a, 'Default ')) return -1;
            if (str_starts_with($b, 'Default ')) return 1;
            return strcasecmp($a, $b);
        });

        return $scripts;
    }

    public function resolve(SourceExamImport $import, ?Configuration $_configuration): string
    {
        $available = $this->available();
        $selected = trim((string) data_get($import->settings, 'extractor_script', self::BUILTIN));
        if ($selected === '' || $selected === self::LEGACY_BUILTIN) $selected = self::BUILTIN;

        if (! array_key_exists($selected, $available)) {
            throw new \RuntimeException("The selected extractor '{$selected}' is not available in scripts/extractors.");
        }

        return base_path('scripts/extractors/'.$selected);
    }

    public function processEnvironment(?Configuration $configuration): array
    {
        $environment = [
            'EXAMELITE_PUBLIC_ORIGIN' => rtrim((string) config('app.url'), '/'),
        ];
        $path = (string) (getenv('PATH') ?: ($_SERVER['PATH'] ?? ''));
        $home = rtrim((string) (getenv('HOME') ?: ($_SERVER['HOME'] ?? '')), DIRECTORY_SEPARATOR);
        if (PHP_OS_FAMILY !== 'Windows' && $home !== '') {
            $localBin = $home.DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'bin';
            $entries = array_filter(explode(PATH_SEPARATOR, $path));
            if (! in_array($localBin, $entries, true)) $environment['PATH'] = $localBin.PATH_SEPARATOR.$path;
        }

        if (! $configuration) return array_filter($environment);

        $priority = $configuration->ai_provider_priority ?? [];
        $tasks = $configuration->ai_task_priorities ?? [];

        return array_filter(array_merge($environment, [
            'EXAMELITE_AI_PROVIDER_PRIORITY' => json_encode($priority),
            'EXAMELITE_AI_TASK_PRIORITIES' => json_encode($tasks),
            'OPENAI_API_KEY' => $configuration->openai_api_key,
            'OPENAI_MODEL' => $configuration->openai_model,
            'DEEPSEEK_API_KEY' => $configuration->deepseek_api_key,
            'DEEPSEEK_MODEL' => $configuration->deepseek_model,
            'DEEPSEEK_VISION_MODEL' => $configuration->deepseek_vision_model ?: 'deepseek-v4-flash-vision-exp',
            'GOOGLE_GEMINI_API_KEY' => $configuration->google_gemini_api_key,
            'GOOGLE_GEMINI_MODEL' => $configuration->google_gemini_model,
            'ANTHROPIC_API_KEY' => $configuration->anthropic_api_key,
            'ANTHROPIC_MODEL' => $configuration->anthropic_model,
        ]), fn ($value) => $value !== null && $value !== '');
    }
}
