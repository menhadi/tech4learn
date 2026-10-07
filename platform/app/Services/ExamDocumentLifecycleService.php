<?php

namespace App\Services;

use App\Jobs\GenerateExamPdfJob;
use App\Models\Exam;
use App\Models\ExamPdfBuild;
use App\Models\Language;
use App\Models\Package;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ExamDocumentLifecycleService
{
    public function queue(Exam $exam, ?Package $package, ?Language $language, string $type, ?int $userId = null): ExamPdfBuild
    {
        abort_unless(in_array($type, ['questions', 'solutions'], true), 422);

        $fingerprint = app(ExamPdfCacheService::class)
            ->fingerprint($exam, $language, $package, $type === 'solutions');
        $build = ExamPdfBuild::query()
            ->where('exam_id', $exam->id)
            ->where('package_id', $package?->id)
            ->where('language_id', $language?->id)
            ->where('document_type', $type)
            ->firstOrNew();

        if ($build->exists
            && hash_equals((string) $build->source_fingerprint, $fingerprint)
            && (in_array($build->status, ['queued', 'processing'], true)
                || ($build->status === 'ready' && is_file((string) $build->current_path)))) {
            return $build;
        }

        $build->fill([
            'organization_id' => $exam->organization_id,
            'exam_id' => $exam->id,
            'package_id' => $package?->id,
            'language_id' => $language?->id,
            'document_type' => $type,
            'status' => 'queued',
            'source_fingerprint' => $fingerprint,
            'last_error' => null,
            'queued_at' => now(),
            'started_at' => null,
            'requested_by' => $userId,
        ])->save();

        GenerateExamPdfJob::dispatch($build->id);

        return $build;
    }

    public function ready(Exam $exam, ?Package $package, ?Language $language, string $type): ?ExamPdfBuild
    {
        $build = ExamPdfBuild::query()
            ->where('exam_id', $exam->id)
            ->where('package_id', $package?->id)
            ->where('language_id', $language?->id)
            ->where('document_type', $type)
            ->where('status', 'ready')
            ->first();

        if (! $build || ! is_file((string) $build->current_path)) {
            return null;
        }

        $fingerprint = app(ExamPdfCacheService::class)
            ->fingerprint($exam, $language, $package, $type === 'solutions');

        return hash_equals((string) $build->source_fingerprint, $fingerprint) ? $build : null;
    }

    public function directory(Exam $exam, ?Package $package, ?Language $language, string $type): string
    {
        $organization = $exam->organization;
        $group = $package?->groups()->first() ?: $exam->groups()->first();
        $category = $package?->category ?: $exam->testTopic?->subject?->category;
        $subcategory = $package?->subcategory;

        $segments = [
            $this->segment($organization?->name ?? 'organization', $exam->organization_id),
            $this->segment($group?->group_name ?? 'unassigned-group', $group?->id ?? 0),
            $this->segment($category?->title ?? 'unassigned-category', $category?->id ?? 0),
            $this->segment($subcategory?->title ?? 'unassigned-subcategory', $subcategory?->id ?? 0),
            $this->segment($package?->name ?? 'unassigned-package', $package?->id ?? 0),
            $this->segment($exam->name, $exam->id),
            $this->segment($language?->name ?? 'English', $language?->id ?? 0),
            $type,
        ];

        return storage_path('app/exam-pdfs/'.implode('/', $segments));
    }

    public function printUrl(ExamPdfBuild $build): string
    {
        if (blank($build->exam->slug)) {
            $build->exam->forceFill([
                'slug' => (Str::slug((string) $build->exam->name) ?: 'exam').'-'.$build->exam_id,
            ])->save();
        }

        $parameters = [
            'id' => $build->exam->slug,
            'package' => $build->package?->slug ?: $build->package_id,
            'lang' => $build->language_id,
            'pdf_render' => 1,
        ];
        if ($build->document_type === 'solutions') {
            $parameters['solution'] = 1;
        }

        return URL::temporarySignedRoute('exam.print', now()->addMinutes(30), $parameters);
    }

    private function segment(mixed $name, int $id): string
    {
        if (is_array($name)) {
            $name = $name['en'] ?? reset($name) ?: 'item';
        }
        $decoded = json_decode((string) $name, true);
        if (is_array($decoded)) {
            $name = $decoded['en'] ?? reset($decoded) ?: 'item';
        }

        return (Str::slug((string) $name) ?: 'item').'--'.$id;
    }
}