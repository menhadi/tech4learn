<?php

namespace App\Services;

use App\Models\ExamQualitySource;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Builds an immutable, coordinate-backed representation of one source question.
 * Audit consumes this manifest; repair is deliberately a later operation.
 */
class SourceQuestionManifestService
{
    public function forQuestion(Question $question, int $examId, int $organizationId, array $processingProfile = [], bool $retainTemporaryVisuals = false): array
    {
        $questionPdf = filter_var($question->source_url, FILTER_VALIDATE_URL)
            && str_ends_with(strtolower((string) parse_url($question->source_url, PHP_URL_PATH)), '.pdf')
            ? new ExamQualitySource([
                'organization_id' => $organizationId, 'exam_id' => $examId,
                'role' => 'questions', 'kind' => 'url', 'label' => 'Question-specific PDF',
                'source_url' => $question->source_url, 'is_active' => true,
            ]) : null;
        $source = $questionPdf ?: ExamQualitySource::where('organization_id', $organizationId)
            ->where('exam_id', $examId)->where('is_active', true)
            ->whereIn('role', ['questions', 'combined'])
            ->orderByRaw("FIELD(role, 'questions', 'combined')")
            ->first();
        if (! $source) return ['status' => 'unavailable', 'message' => 'No question or combined PDF source is attached.'];

        $ordinal = $this->paperOrdinal($question->id, $examId);
        if ($ordinal < 1) return ['status' => 'unavailable', 'message' => 'Question order in this paper could not be determined.'];

        [$pdfPath, $cleanup] = $this->localPdf($source);
        try {
            $fingerprint = sha1(implode('|', [$source->id ?: $source->source_url, $source->updated_at?->timestamp, filesize($pdfPath), 'schema-7-tight-composite-visuals', json_encode($processingProfile)]));
            $relative = "exam-quality/manifests/{$organizationId}/{$examId}/{$fingerprint}";
            $manifestPath = Storage::disk('local')->path($relative.'/questions/'.$ordinal.'.json');
            if (is_file($manifestPath)) {
                $cached = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($cached) && ($cached['ok'] ?? false)) return $cached;
            }

            $outputDirectory = Storage::disk('local')->path($relative.'/visuals');
            $ephemeralOutput = false;
            if ((! is_dir($outputDirectory) && ! @mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory))
                || (is_dir($outputDirectory) && ! is_writable($outputDirectory))) {
                $ephemeralOutput = true;
                $outputDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'exam-manifest-'.Str::uuid();
                if (! @mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
                    return [
                        'status' => 'storage_unavailable', 'adapter' => 'pdf_coordinate_v1',
                        'question_id' => $question->id, 'paper_question_number' => $ordinal,
                        'source_id' => $source->id, 'message' => 'Neither the application nor system temporary directory is writable.',
                    ];
                }
            }
            $process = new Process([
                'python3', base_path('scripts/extract-pdf-question-visuals.py'), $pdfPath,
                $outputDirectory, (string) $ordinal, Str::slug((string) ($source->label ?: 'paper')),
                json_encode($processingProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], base_path(), null, null, 240);
            $process->run();
            $manifest = json_decode(trim($process->getOutput()), true);
            if (! $process->isSuccessful() || ! is_array($manifest) || ! ($manifest['ok'] ?? false)) {
                if ($ephemeralOutput) {
                    foreach (glob($outputDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) @unlink($file);
                    @rmdir($outputDirectory);
                }
                return [
                    'status' => 'needs_vision_adapter',
                    'adapter' => 'pdf_coordinate_v1',
                    'question_id' => $question->id,
                    'paper_question_number' => $ordinal,
                    'source_id' => $source->id,
                    'message' => (string) ($manifest['error'] ?? trim($process->getErrorOutput()) ?: 'The deterministic PDF adapter could not locate this question.'),
                ];
            }
            $manifest['status'] = 'ready';
            $manifest['adapter'] = 'pdf_coordinate_v1';
            $manifest['match_key'] = ['paper_ordinal' => $ordinal, 'question_code' => $question->question_code, 'source_reference' => $question->source_reference];
            $manifest['source_id'] = $source->id;
            $manifest['source_role'] = $source->role;
            $manifest['source_label'] = $source->label;
            $manifest['question_id'] = $question->id;
            $manifest['processing_profile'] = $processingProfile ?: null;
            $manifest['storage_mode'] = $ephemeralOutput ? 'system_temporary' : 'private_cache';
            $manifest['visuals'] = collect((array) ($manifest['visuals'] ?? []))->map(function (array $visual) use ($relative, $ephemeralOutput) {
                if ($ephemeralOutput) $visual['temporary_path'] = (string) ($visual['path'] ?? '');
                else $visual['private_path'] = $relative.'/visuals/'.basename((string) $visual['path']);
                unset($visual['path']);
                return $visual;
            })->all();
            if (! $ephemeralOutput) {
                if (! is_dir(dirname($manifestPath))) @mkdir(dirname($manifestPath), 0775, true);
                @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } elseif (! $retainTemporaryVisuals || empty($manifest['visuals'])) {
                foreach ((array) $manifest['visuals'] as &$visual) {
                    if (is_file((string) ($visual['temporary_path'] ?? ''))) @unlink($visual['temporary_path']);
                    unset($visual['temporary_path']);
                }
                unset($visual);
                @rmdir($outputDirectory);
            }
            return $manifest;
        } finally {
            if (is_callable($cleanup)) $cleanup();
        }
    }

    public function compareStored(Question $question, array $manifest): array
    {
        if (($manifest['status'] ?? null) !== 'ready') return [];
        $findings = [];
        $byTarget = collect((array) ($manifest['visuals'] ?? []))->groupBy('target_field');
        $sourceTableTargets = array_fill_keys((array) ($manifest['source_html_table_targets'] ?? []), true);
        $canonicalFields = (array) ($manifest['canonical_fields'] ?? []);
        foreach (['question','option1','option2','option3','option4','option5','option6'] as $field) {
            $sourceCount = $byTarget->get($field, collect())->count();
            $html = (string) ($question->{$field} ?? '');
            preg_match_all('/<img\b[^>]*src=["\'][^"\']+["\']/i', $html, $matches);
            $storedCount = count($matches[0] ?? []);
            $placeholder = (bool) preg_match('/image\s+will\s+appear\s+soon|placeholder/i', strip_tags($html));
            if ($sourceCount > 0 && ($storedCount < $sourceCount || $placeholder)) {
                $findings[] = [
                    'type' => 'source_visual_mismatch', 'severity' => 'error',
                    'title' => ucfirst($field).' image differs from source',
                    'details' => "Source has {$sourceCount} visual(s); stored {$field} has {$storedCount}".($placeholder ? ' and contains a placeholder.' : '.'),
                    'confidence' => 98,
                    'evidence' => ['canonical_source' => $manifest, 'target_field' => $field,
                        'source_visual_count' => $sourceCount, 'stored_visual_count' => $storedCount],
                ];
            }

            if (isset($sourceTableTargets[$field])) {
                $sourceHtml = (string) data_get($canonicalFields, $field.'.text', '');
                $sourceShape = $this->tableShape($sourceHtml);
                $storedShape = $this->tableShape($html);
                if ($storedShape === []) {
                    $findings[] = [
                        'type' => 'source_table_structure_mismatch', 'severity' => 'error',
                        'title' => ucfirst($field).' table structure is missing',
                        'details' => 'The source contains a structured data table, but the stored content is flattened text rather than an HTML table.',
                        'confidence' => 99,
                        'evidence' => ['canonical_source' => $manifest, 'target_field' => $field,
                            'source_table_shape' => $sourceShape, 'stored_table_shape' => []],
                    ];
                } elseif ($sourceShape !== [] && $sourceShape !== $storedShape) {
                    $findings[] = [
                        'type' => 'source_table_structure_mismatch', 'severity' => 'error',
                        'title' => ucfirst($field).' table layout differs from source',
                        'details' => 'The stored table row/column geometry does not match the authoritative source table.',
                        'confidence' => 97,
                        'evidence' => ['canonical_source' => $manifest, 'target_field' => $field,
                            'source_table_shape' => $sourceShape, 'stored_table_shape' => $storedShape],
                    ];
                }
            }
        }
        return $findings;
    }

    private function tableShape(string $html): array
    {
        if (! preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $tables)) {
            return [];
        }

        return collect($tables[1])->map(function (string $table) {
            preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $table, $rows);
            return collect($rows[1] ?? [])->map(function (string $row) {
                preg_match_all('/<(?:td|th)\b[^>]*>/i', $row, $cells);
                return count($cells[0] ?? []);
            })->filter()->values()->all();
        })->filter()->values()->all();
    }

    private function paperOrdinal(int $questionId, int $examId): int
    {
        $ids = DB::table('exam_questions')->where('exam_id', $examId)->orderBy('id')->pluck('question_id');
        $index = $ids->search(fn ($id) => (int) $id === $questionId);
        return $index === false ? 0 : $index + 1;
    }

    private function localPdf(ExamQualitySource $source): array
    {
        if ($source->kind === 'file') return app(ExamQualitySourceStorage::class)->localPath($source);
        $url = (string) $source->source_url;
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.pdf')) {
            throw new \RuntimeException('Canonical extraction requires a direct PDF source URL.');
        }
        $body = Http::timeout(120)->retry(2, 750)->withHeaders(['User-Agent' => 'ExamQualityAudit/1.0'])->get($url)->throw()->body();
        if ($body === '') throw new \RuntimeException('The source PDF URL returned an empty file.');
        $path = @tempnam(sys_get_temp_dir(), 'exam-manifest-pdf-');
        if ($path === false || file_put_contents($path, $body) === false) {
            throw new \RuntimeException('The system temporary directory is not writable.');
        }
        return [$path, fn () => @unlink($path)];
    }
}
