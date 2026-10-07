<?php

namespace App\Services;

use App\Models\{Category, Configuration, Exam, ExamQualitySource, Group, Package, Qtype, Question, QuestionLang, SourceExamImport, SourceExamQuestionDraft};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use App\Support\{SourceExamImageContract, SourceExtractorRegistry};
use Symfony\Component\Process\Process;


class SourceExamImportService
{
    public function renderDraftSourcePage(SourceExamImport $import, int $page): string
    {
        $source = (string) $import->question_source_path;
        if ($source === '' || ! Storage::disk('local')->exists($source)) {
            throw new \RuntimeException('The original question PDF is not available for this draft.');
        }
        $output = $this->draftTemporaryFile('source-draft-page-', '.png');
        $this->runDraftPdfCrop(Storage::disk('local')->path($source), $output, $page, [0, 0, 1, 1]);
        return $output;
    }

    public function extractMathpixCrop(SourceExamQuestionDraft $draft, int $page, array $bbox): string
    {
        $draft->loadMissing('sourceImport');
        $source = (string) ($draft->sourceImport?->question_source_path ?? '');
        if ($source === '' || ! Storage::disk('local')->exists($source)) throw new \RuntimeException('The original question PDF is not available for Mathpix cropping.');
        $output = $this->draftTemporaryFile('source-mathpix-', '.png');
        try { $this->runDraftPdfCrop(Storage::disk('local')->path($source), $output, $page, $bbox, 'white'); }
        catch (\Throwable $exception) { @unlink($output); throw $exception; }
        return $output;
    }

    public function applyManualOcrText(SourceExamQuestionDraft $draft, string $target, string $html, array $recognition = []): array
    {
        $fields = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        if (! in_array($target, $fields, true)) throw new \InvalidArgumentException('Invalid text target field.');
        if (trim(strip_tags($html)) === '' && ! str_contains($html, '\\(') && ! str_contains($html, '\\[')) throw new \InvalidArgumentException('Recognized text cannot be empty.');
        if (mb_strlen($html) > 100000) throw new \InvalidArgumentException('Recognized text is too long for one question field.');
        $payload = (array) $draft->payload;
        $payload[$target] = $html;
        $evidence = (array) $draft->source_evidence;
        $evidence['manual_mathpix_ocr'][$target] = array_merge($recognition, ['target_field' => $target, 'saved_at' => now()->toIso8601String()]);
        $draft->update(['payload' => $payload, 'source_evidence' => $evidence]);
        return (array) $evidence['manual_mathpix_ocr'][$target];
    }
    public function applyManualImageCrop(SourceExamQuestionDraft $draft, int $page, array $bbox, string $target, string $backgroundMode = 'white'): array
    {
        $allowed = ['question','option1','option2','option3','option4','option5','option6','explanation'];
        if (! in_array($target, $allowed, true)) throw new \InvalidArgumentException('Invalid image target field.');
        if (! in_array($backgroundMode, ['white', 'transparent'], true)) throw new \InvalidArgumentException('Invalid crop background mode.');
        $draft->loadMissing('sourceImport');
        $import = $draft->sourceImport;
        if (! $import || (int) $import->organization_id !== (int) $draft->organization_id) {
            throw new \RuntimeException('The source exam for this draft is unavailable.');
        }
        $source = (string) $import->question_source_path;
        if ($source === '' || ! Storage::disk('local')->exists($source)) {
            throw new \RuntimeException('The original question PDF is not available for cropping.');
        }

        $relative = collect([
            'question-images', 'manual-crops', 'org-'.$draft->organization_id,
            'source-'.$import->id, 'question-'.$draft->id, $target.'.png',
        ])->implode('/');
        $disk = Storage::disk('public');
        $directory = str_replace('\\', '/', dirname($relative));
        if (! $disk->makeDirectory($directory) && ! $disk->directoryExists($directory)) {
            throw new \RuntimeException('The manual-crop image directory is not writable.');
        }
        $output = $this->draftTemporaryFile('source-draft-crop-', '.png');
        $stream = null;
        try {
            $this->runDraftPdfCrop(Storage::disk('local')->path($source), $output, $page, $bbox, $backgroundMode);
            $stream = fopen($output, 'rb');
            if (! is_resource($stream) || ! $disk->put($relative, $stream)) {
                throw new \RuntimeException('The cropped image could not be saved.');
            }
        } finally {
            if (is_resource($stream)) fclose($stream);
            @unlink($output);
        }

        $payload = (array) $draft->payload;
        $current = (string) ($payload[$target] ?? '');
        $withoutImages = trim((string) preg_replace('#(?:<p[^>]*>\s*)?<img\b[^>]*>(?:\s*</p>)?#is', '', $current));
        if (preg_match('/^(?:image|diagram|figure)\s+(?:will\s+appear\s+soon|not\s+available|missing)$/i', trim(strip_tags($withoutImages)))) $withoutImages = '';
        $publicUrl = $disk->url($relative).'?v='.time();
        $optionIndex = str_starts_with($target, 'option') ? (int) substr($target, 6) : 0;
        $alt = $optionIndex > 0 ? 'Option '.chr(64 + $optionIndex).' diagram' : 'Question diagram';
        $payload[$target] = trim($withoutImages."\n".'<p><img src="'.$publicUrl.'" alt="'.$alt.'"></p>');
        $evidence = (array) $draft->source_evidence;
        $manualCrops = (array) ($evidence['manual_crops'] ?? []);
        $manualCrops[$target] = [
            'status' => 'manually_cropped', 'path' => $relative, 'target_field' => $target,
            'source_page' => $page, 'bbox_normalized' => array_map('floatval', $bbox),
            'background_mode' => $backgroundMode,
        ];
        $evidence['manual_crops'] = $manualCrops;
        $draft->update(['payload' => $payload, 'source_evidence' => $evidence]);

        return $manualCrops[$target];
    }

    private function runDraftPdfCrop(string $pdfPath, string $output, int $page, array $bbox, string $backgroundMode = 'white'): void
    {
        $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
        $args = [$python, base_path('scripts/extract-pdf-region.py'), $pdfPath, $output, (string) $page];
        foreach ($bbox as $coordinate) $args[] = (string) ((float) $coordinate);
        $args[] = $backgroundMode;
        $process = new Process($args, base_path(), null, null, 180);
        $process->run();
        $result = json_decode(trim($process->getOutput()), true);
        if (! $process->isSuccessful() || ! is_file($output)) {
            throw new \RuntimeException((string) ($result['error'] ?? trim($process->getErrorOutput()) ?: 'PDF crop extraction failed.'));
        }
    }

    private function draftTemporaryFile(string $prefix, string $suffix): string
    {
        $path = @tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) throw new \RuntimeException('The system temporary directory is not writable.');
        $output = $path.$suffix;
        if (! @rename($path, $output)) {
            @unlink($path);
            throw new \RuntimeException('A temporary crop file could not be prepared.');
        }
        return $output;
    }

    public function process(SourceExamImport $import): void
    {
        $import->update(['status'=>'processing','processing_started_at'=>now(),'failure_message'=>null]);
        try {
            $settings = $import->settings ?? [];
            $group = Group::query()->find(collect($settings['group_ids'] ?? [])->first());
            $category = Category::query()->find($settings['category_id'] ?? null);
            $subcategory = Category::query()->find($settings['subcategory_id'] ?? null);
            $package = Package::query()->find(collect($settings['package_ids'] ?? [])->first());
            $outputRelative = collect([
                'question-images',
                Str::slug((string) ($group?->group_name ?: 'ungrouped')) ?: 'ungrouped',
                Str::slug((string) ($category?->title ?: 'uncategorized')) ?: 'uncategorized',
                Str::slug((string) ($subcategory?->title ?: 'no-subcategory')) ?: 'no-subcategory',
                Str::slug((string) ($package?->name ?: 'no-package')) ?: 'no-package',
                Str::slug($import->name) ?: 'paper',
                'images',
            ])->implode('/');
            Storage::disk('public')->makeDirectory($outputRelative);
            $output = Storage::disk('public')->path($outputRelative);
            $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
            $resultRelative = "source-exam-results/{$import->organization_id}/{$import->id}/result.json";
            Storage::disk('local')->makeDirectory(dirname($resultRelative));
            Storage::disk('local')->delete($resultRelative);
            $resultPath = Storage::disk('local')->path($resultRelative);
            $configuration = Configuration::query()->where('organization_id', $import->organization_id)->first();
            $extractors = app(SourceExtractorRegistry::class);
            $script = $extractors->resolve($import, $configuration);
            $questionPath = Storage::disk('local')->path($import->question_source_path);
            $legacyCustomExtractor = ! $this->supportsExtractorContract($script);
            $command = $legacyCustomExtractor
                ? [$python, $script]
                : [$python, $script, '--questions', $questionPath, '--out', $output, '--public-prefix', '/storage/'.$outputRelative, '--paper-code', Str::slug($import->name), '--profile', json_encode($import->settings['profile'] ?? []), '--result-file', $resultPath];
            if (! $legacyCustomExtractor) {
                foreach (['answers'=>'answer_source_path','solutions'=>'solution_source_path'] as $flag=>$field) if ($import->{$field}) array_push($command, '--'.$flag, Storage::disk('local')->path($import->{$field}));
            }
            $legacyWorkRelative = "source-exam-work/{$import->organization_id}/{$import->id}";
            $legacyInputRelative = $legacyWorkRelative.'/input';
            $processDirectory = base_path();
            $legacyInput = dirname($questionPath);
            if ($legacyCustomExtractor) {
                Storage::disk('local')->deleteDirectory($legacyWorkRelative);
                Storage::disk('local')->makeDirectory($legacyInputRelative);
                foreach (['question_source_path' => 'questions', 'answer_source_path' => 'answers', 'solution_source_path' => 'solutions'] as $field => $role) {
                    $sourceRelative = $import->{$field};
                    if (! $sourceRelative || ! Storage::disk('local')->exists($sourceRelative)) continue;
                    $extension = pathinfo($sourceRelative, PATHINFO_EXTENSION) ?: 'pdf';
                    Storage::disk('local')->copy($sourceRelative, $legacyInputRelative.'/'.$role.'.'.$extension);
                }
                $legacyInput = Storage::disk('local')->path($legacyInputRelative);
                $processDirectory = Storage::disk('local')->path($legacyWorkRelative);
            }
            $process = new Process($command, $processDirectory, $extractors->processEnvironment($configuration), null, 1800);
            $extractorStartedAt = time();
            if ($legacyCustomExtractor) {
                $process->setInput(
                    $legacyInput.PHP_EOL
                    .$output.PHP_EOL
                    .str_repeat(PHP_EOL, 10)
                );
            }
            $process->run();
            $diagnostics = $this->processDiagnostics($process);
            if (! $process->isSuccessful()) throw new \RuntimeException($diagnostics ?: 'Source extractor exited with code '.($process->getExitCode() ?? 'unknown').'.');
            if ($legacyCustomExtractor && ! Storage::disk('local')->exists($resultRelative)) {
                $this->adoptLegacyExtractorResult(
                    [$legacyInput, $processDirectory, $output],
                    $resultRelative,
                    $extractorStartedAt
                );
            }
            if (! Storage::disk('local')->exists($resultRelative)) throw new \RuntimeException('Source extractor completed without producing its result file.'.($diagnostics !== '' ? "\n\n".$diagnostics : ''));
            $result = json_decode(Storage::disk('local')->get($resultRelative), true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('Source extractor result is invalid JSON: '.json_last_error_msg());
            if (! is_array($result) || ! ($result['ok'] ?? false)) throw new \RuntimeException($result['error'] ?? 'Source extractor returned invalid output.');
            if (empty($result['questions'])) throw new \RuntimeException('No questions were detected. For scanned PDFs, install/configure OCR or upload a searchable PDF/DOCX.');
            $normalizedRows = collect($result['questions'])
                ->map(fn (array $row): array => app(SourceExamImageContract::class)->normalize($row, $outputRelative))
                ->all();
            if ($legacyCustomExtractor) Storage::disk('local')->deleteDirectory($legacyWorkRelative);
            SourceExamQuestionDraft::where('source_exam_import_id',$import->id)->delete();
            foreach ($normalizedRows as $row) {
                $payload = $this->payload($row, $import);
                $aiFields = [];
                $sourceEvidence = [
                    'pages'=>$row['source_pages'] ?? [],
                    'solution_source_available'=>$row['solution_source_available'] ?? false,
                    'diagram_required'=>(bool) ($row['diagram_required'] ?? false),
                    'diagram_description'=>$row['diagram_description'] ?? null,
                    'extracted_images'=>(array) ($row['extracted_images'] ?? []),
                    'extractor_metadata'=>(array) ($row['extractor_metadata'] ?? []),
                ];
                $draft = new SourceExamQuestionDraft(['organization_id'=>$import->organization_id,'source_exam_import_id'=>$import->id,'paper_question_number'=>$row['paper_question_number'],'printed_question_number'=>$row['printed_question_number'],'status'=>$aiFields ? 'needs_review' : 'ready','payload'=>$payload,'source_evidence'=>$sourceEvidence,'ai_fields'=>$aiFields]);
                $sourceEvidence = app(StructuredContentReviewService::class)->attachToSourceDraft($draft, $payload, $sourceEvidence);
                $draft->source_evidence = $sourceEvidence;
                if (collect((array) ($sourceEvidence['structured_content'] ?? []))->isNotEmpty()) $draft->status = 'needs_review';
                $draft->save();
            }
            $import->refresh(); $count=$import->drafts()->count(); $review=$import->drafts()->where('status','needs_review')->count();
            $import->update(['status'=>'review','detected_questions'=>$count,'ready_questions'=>$count-$review,'review_questions'=>$review,'processing_completed_at'=>now()]);
        } catch (\Throwable $e) {
            $import->update(['status'=>'failed','failure_message'=>mb_substr($e->getMessage(),0,65000),'processing_completed_at'=>now()]);
            throw $e;
        }
    }

    private function payload(array $row, SourceExamImport $import): array
    {
        $settings = $import->settings;
        $options = array_pad($this->questionOptions($row), 6, null);
        $answer = strtoupper(trim((string) ($row['correct_answer'] ?? '')));
        $fallbackQtype = ! empty($settings['qtype_id']) ? (int) $settings['qtype_id'] : null;

        $payload = [
            'qtype_id' => $this->detectQtypeId($row, $fallbackQtype),
            'subject_id' => ! empty($settings['subject_id']) ? (int) $settings['subject_id'] : null,
            'diff_id' => (int) $settings['diff_id'],
            'language_id' => (int) $settings['language_id'],
            'question' => $row['question'] ?: null,
            'option1' => $options[0] ?: null,
            'option2' => $options[1] ?: null,
            'option3' => $options[2] ?: null,
            'option4' => $options[3] ?: null,
            'option5' => $options[4] ?: null,
            'option6' => $options[5] ?: null,
            'marks' => isset($row['marks']) && is_numeric($row['marks']) ? (float) $row['marks'] : (float) $settings['marks'],
            'negative_marks' => isset($row['negative_marks']) && is_numeric($row['negative_marks']) ? (float) $row['negative_marks'] : (float) $settings['negative_marks'],
            'correct_answer' => $answer ?: null,
            'explanation' => $row['explanation'] ?? null,
            'status' => 'Yes',
        ];
        $rawCorrectAnswers = $row['correct_answers'] ?? $row['correct_options'] ?? [];
        if (is_string($rawCorrectAnswers)) $rawCorrectAnswers = preg_split('/[,;|\\s]+/', trim($rawCorrectAnswers)) ?: [];
        $correctAnswers = collect((array) $rawCorrectAnswers)
            ->map(function ($value) {
                if (is_numeric($value)) return (int) $value;
                $value = strtoupper(trim((string) $value));
                return preg_match('/^[A-F]$/', $value) ? ord($value) - 64 : null;
            })->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->values()->all();
        if ($correctAnswers !== []) $payload['correct_answers'] = $correctAnswers;
        if (! empty($row['nat_config']) && is_array($row['nat_config'])) $payload['nat_config'] = $row['nat_config'];
        return $payload;
    }

    private function detectQtypeId(array $row, ?int $fallback): int
    {
        static $types;
        $types ??= Qtype::query()->get(['id', 'type'])->groupBy(fn ($type) => strtoupper(trim((string) $type->type)));
        $idFor = static function (array $codes) use ($types): ?int {
            foreach ($codes as $code) {
                $match = $types->get($code)?->first();
                if ($match) return (int) $match->id;
            }
            return null;
        };

        $declaredType = $this->questionTypeCode($row);
        if ($declaredType !== '') {
            $declaredCodes = $declaredType === 'B' ? ['B', 'F'] : [$declaredType];
            if ($id = $idFor($declaredCodes)) return $id;
        }

        $question = trim(strip_tags((string) ($row['question'] ?? '')));
        $answer = strtolower(trim((string) ($row['correct_answer'] ?? '')));
        $optionValues = collect($this->questionOptions($row));
        $optionTexts = $optionValues
            ->map(fn ($value) => strtolower(trim(strip_tags((string) $value))))
            ->filter(fn ($value) => $value !== '')
            ->values();

        $trueFalseOptions = $optionTexts->count() === 2
            && $optionTexts->contains('true')
            && $optionTexts->contains('false');
        if (in_array($answer, ['true', 'false'], true) || $trueFalseOptions) {
            if ($id = $idFor(['T'])) return $id;
        }
        if ($optionValues->count() >= 2) {
            if ($id = $idFor(['M'])) return $id;
        }
        if (preg_match('/_{2,}|\[\s*blank\s*\]|\{\{\s*blank/iu', $question)) {
            if ($id = $idFor(['B', 'F'])) return $id;
        }
        if ($answer !== '' && is_numeric($answer)) {
            if ($id = $idFor(['NAT'])) return $id;
        }

        if ($fallback && Qtype::whereKey($fallback)->exists()) return $fallback;
        if ($id = $idFor(['S'])) return $id;

        return (int) Qtype::query()->value('id');
    }

    private function questionTypeCode(array $row): string
    {
        $declared = strtolower(trim((string) (
            $row['question_type'] ?? $row['questionType'] ?? $row['qtype'] ?? $row['type'] ?? ''
        )));
        $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', $declared) ?? $declared);

        return match (true) {
            in_array(strtoupper($declared), ['M', 'T', 'F', 'B', 'S', 'NAT'], true) => strtoupper($declared),
            str_contains($normalized, 'true false'), str_contains($normalized, 'boolean') => 'T',
            $normalized === 'nat', str_contains($normalized, 'numerical'),
                str_contains($normalized, 'numeric') => 'NAT',
            str_contains($normalized, 'fill'), str_contains($normalized, 'blank') => 'F',
            str_contains($normalized, 'mcq'), str_contains($normalized, 'multiple choice'),
                str_contains($normalized, 'single choice'), str_contains($normalized, 'single correct'),
                str_contains($normalized, 'multiple correct'), str_contains($normalized, 'objective') => 'M',
            str_contains($normalized, 'subjective'), str_contains($normalized, 'descriptive'),
                str_contains($normalized, 'essay'), str_contains($normalized, 'short answer'),
                str_contains($normalized, 'long answer') => 'S',
            default => strtoupper(trim($declared)),
        };
    }

    private function questionOptions(array $row): array
    {
        $options = $row['options'] ?? $row['choices'] ?? null;
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) $options = $decoded;
        }
        if (! is_array($options) || $options === []) {
            $normalizedKeys = collect($row)->mapWithKeys(
                fn ($value, $key) => [strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $key) ?? '') => $value]
            );
            $options = [];
            foreach (range(1, 6) as $index) {
                $letter = chr(96 + $index);
                foreach (["option{$index}", "option{$letter}", "option{$index}text", "option{$letter}text", "choice{$index}", "choice{$letter}", "choice{$index}text", "choice{$letter}text", $letter] as $key) {
                    if ($normalizedKeys->has($key)) {
                        $options[] = $normalizedKeys->get($key);
                        break;
                    }
                }
            }
        }

        return collect($options)->map(function ($option) {
            if (! is_array($option)) return $option;

            return $option['text'] ?? $option['option_text'] ?? $option['choice_text']
                ?? $option['content'] ?? $option['html'] ?? $option['value']
                ?? $option['option'] ?? $option['label'] ?? null;
        })->filter(fn ($option) => $this->hasQuestionContent($option))
            ->values()->all();
    }

    private function hasQuestionContent(mixed $value): bool
    {
        if ($value === null) return false;

        $content = trim((string) $value);

        return trim(strip_tags($content)) !== ''
            || preg_match('/<(?:img|svg|math|table)\b/i', $content) === 1;
    }
    public function publish(SourceExamImport $import, array $draftIds=[]): Exam
    {
        return DB::transaction(function() use($import,$draftIds) {
            $import=SourceExamImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if($import->status==='published') return $import->exam;
            $settings=$import->settings; $drafts=$import->drafts()->when($draftIds,fn($q)=>$q->whereIn('id',$draftIds))->whereIn('status',['ready','needs_review'])->orderBy('paper_question_number')->get();
            if($drafts->isEmpty()) throw new \RuntimeException('No ready or needs-review question drafts were selected.');
            if ($import->exam_id) {
                $exam = Exam::query()
                    ->where('organization_id', $import->organization_id)
                    ->whereKey($import->exam_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($exam->questions()->exists() || $exam->results()->exists()) {
                    throw new \RuntimeException('The selected exam is no longer empty. Choose another zero-question exam.');
                }
            } else {
                // Backward compatibility for source imports created before existing-exam selection.
                $exam=Exam::create(['organization_id'=>$import->organization_id,'name'=>$import->name,'slug'=>$this->uniqueSlug($import->name,$import->organization_id),'passing_percentage'=>$settings['passing_percentage']??0,'duration'=>$settings['duration'],'attempt_count'=>$settings['attempt_count']??1,'start_date'=>now(),'end_date'=>now()->addYears(5),'browser_tolerance'=>false,'random_question'=>false,'result_after_finish'=>true,'option_shuffle'=>false,'allow_answer_change'=>true,'grouping_mode'=>'subject','timer_mode'=>'none','is_subject_timer'=>false,'proctor'=>false,'calculator_allowed'=>false,'negative_marking'=>(float)$settings['negative_marks']>0,'tolerance_count'=>0,'status'=>'Active','category_level_1'=>$settings['category_id']??null,'category_level_2'=>$settings['subcategory_id']??null]);
            }
            $scope = app(ExamScopeService::class)->resolve(
                (int) $import->organization_id,
                (array) ($settings['package_ids'] ?? []),
                (array) ($settings['group_ids'] ?? []),
                ! empty($settings['category_id']) ? (int) $settings['category_id'] : null,
                ! empty($settings['subcategory_id']) ? (int) $settings['subcategory_id'] : null,
            );
            app(ExamScopeService::class)->sync($exam, $scope);            $publishedQuestionIds = [];
            foreach($drafts as $draft) {
                $data=$draft->payload; $answer=$data['correct_answer']??null; unset($data['correct_answer']); $data['organization_id']=$import->organization_id;
                $type = strtoupper((string) Qtype::whereKey($data['qtype_id'])->value('type'));
                if($type==='M') {
                    $answers = collect($data['correct_answers'] ?? [])->map(fn($value)=>(int)$value)->filter(fn($value)=>$value>=1&&$value<=6)->unique()->values();
                    if($answers->isEmpty() && is_numeric($answer) && (int)$answer>=1 && (int)$answer<=6) $answers=collect([(int)$answer]);
                    if($answers->isEmpty() && is_string($answer) && preg_match('/^[A-F]$/i',$answer)) $answers=collect([ord(strtoupper($answer))-64]);
                    $data['correct_option_indices'] = $answers->sort()->values()->all();
                } elseif(in_array($type,['F','B'],true)) {
                    $config=$data['fill_blank_config']??null;
                    if(!$config && $answer) $config=['version'=>1,'blanks'=>[['answers'=>[$answer]]]];
                    $data['fill_blank_config']=$config;
                    $data['fill_blank']=data_get($config,'blanks.0.answers.0');
                } elseif($type==='NAT') {
                    if(empty($data['nat_config']) && $answer!==null && is_numeric($answer)) $data['nat_config']=['version'=>1,'mode'=>'exact','value'=>(float)$answer];
                } elseif($type==='T') {
                    $truth=$data['true_false']??$answer;
                    if (is_string($truth) && preg_match('/^[A-F]$/i', $truth)) {
                        $truth = $data['option'.(ord(strtoupper($truth)) - 64)] ?? $truth;
                    } elseif (is_numeric($truth) && (int) $truth >= 1 && (int) $truth <= 6) {
                        $truth = $data['option'.(int) $truth] ?? $truth;
                    }
                    $data['true_false']=in_array(strtolower((string)$truth),['true','yes','1'],true)?'true':'false';
                } elseif($type==='S') $data['si_answer1']=$data['si_answer1']??$answer;
                unset($data['correct_answers']);
                // Image-only or failed-text-extraction questions may have no textual stem.
                // The language table requires a non-null question value.
                $data['question'] = (string) ($data['question'] ?? '');
                $question=Question::create($data); $question->groups()->sync($scope['group_ids']); $exam->questions()->attach($question->id);
                $publishedQuestionIds[] = (int) $question->id;
                QuestionLang::create(['question_id'=>$question->id,'language_id'=>$data['language_id'],'question'=>$data['question'],'option1'=>$data['option1'],'option2'=>$data['option2'],'option3'=>$data['option3'],'option4'=>$data['option4'],'option5'=>$data['option5'],'option6'=>$data['option6'],'explanation'=>$data['explanation'],'fill_blank'=>$data['fill_blank']??null]);
                $draft->update(['question_id'=>$question->id,'status'=>'published']);
            }
            $linkedCount = DB::table('exam_questions')
                ->where('exam_id', $exam->id)
                ->whereIn('question_id', $publishedQuestionIds)
                ->count();
            if ($linkedCount !== count($publishedQuestionIds)) {
                throw new \RuntimeException('Publication was rolled back because not all selected questions were linked to the exam.');
            }
            foreach(['questions'=>'question','answers'=>'answer','combined'=>'solution'] as $role=>$prefix) {
                $path=$import->{$prefix.'_source_path'}??null;
                $alreadyLinked=$exam->qualitySources()->where('role',$role)->where('is_active',true)->exists();
                if(!$alreadyLinked && $path && strtolower(pathinfo($path,PATHINFO_EXTENSION))==='pdf') ExamQualitySource::create(['organization_id'=>$import->organization_id,'exam_id'=>$exam->id,'role'=>$role,'kind'=>'file','label'=>$import->{$prefix.'_source_name'},'storage_disk'=>'local','file_path'=>$path,'is_active'=>true]);
            }
            $import->update(['exam_id'=>$exam->id,'status'=>'published','published_at'=>now()]); return $exam;
        });
    }
    private function processDiagnostics(Process $process): string
    {
        $sections = [];
        $output = trim($process->getOutput());
        $error = trim($process->getErrorOutput());
        if ($output !== '') $sections[] = "Extractor output:\n".$output;
        if ($error !== '') $sections[] = "Extractor warnings/errors:\n".$error;
        $diagnostics = implode("\n\n", $sections);
        $diagnostics = preg_replace('/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $diagnostics) ?? $diagnostics;

        return mb_substr($diagnostics, 0, 20000);
    }


    private function supportsExtractorContract(string $script): bool
    {
        $source = is_file($script) ? (string) file_get_contents($script) : '';

        return str_contains($source, '--questions') && str_contains($source, '--result-file');
    }

    private function adoptLegacyExtractorResult(array $roots, string $resultRelative, int $startedAt): void
    {
        $candidates = [];
        foreach (array_unique($roots) as $root) {
            if (! is_dir($root)) continue;
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if (! $file->isFile() || strtolower($file->getExtension()) !== 'json') continue;
                    if ($file->getMTime() + 2 < $startedAt) continue;
                    $candidates[$file->getPathname()] = $file->getMTime();
                }
            } catch (\Throwable) {
                continue;
            }
        }
        arsort($candidates);

        foreach (array_keys($candidates) as $candidate) {
            $decoded = json_decode((string) file_get_contents($candidate), true);
            if (! is_array($decoded)) continue;
            $rows = array_is_list($decoded)
                ? $decoded
                : ($decoded['questions'] ?? data_get($decoded, 'data.questions')
                    ?? $decoded['records'] ?? $decoded['items'] ?? null);
            if (! is_array($rows)) continue;
            $questions = collect($rows)->values()->map(function ($row, $index) {
                if (! is_array($row)) return null;
                $question = trim((string) ($row['question'] ?? $row['question_text'] ?? $row['question_html'] ?? $row['question_text_html'] ?? $row['stem'] ?? $row['text'] ?? ''));
                if ($question === '') return null;

                return array_merge($row, [
                    'paper_question_number' => $row['paper_question_number'] ?? $row['question_number'] ?? $row['number'] ?? ($index + 1),
                    'printed_question_number' => $row['printed_question_number'] ?? $row['question_number'] ?? $row['number'] ?? ($index + 1),
                    'question' => $question,
                    'options' => $this->questionOptions($row),
                    'question_type' => $row['question_type'] ?? $row['questionType'] ?? $row['qtype'] ?? $row['type'] ?? null,
                    'correct_answer' => $row['correct_answer'] ?? $row['answer'] ?? $row['correct_option'] ?? $row['answer_key'] ?? data_get($row, 'correct_options.0') ?? null,
                    'explanation' => $row['explanation'] ?? $row['solution'] ?? null,
                ]);
            })->filter()->values()->all();
            if ($questions === []) continue;

            Storage::disk('local')->put($resultRelative, json_encode([
                'ok' => true,
                'questions' => $questions,
                'legacy_extractor_output' => $candidate,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }

        throw new \RuntimeException('The legacy extractor finished but no new JSON question output was found. Update the script to accept --questions and --result-file for full compatibility.');
    }
    private function uniqueSlug(string $name,int $organizationId): string { $base=Str::slug($name)?:'exam'; $slug=$base;$i=2;while(Exam::where('organization_id',$organizationId)->where('slug',$slug)->exists())$slug=$base.'-'.$i++;return $slug; }
}
