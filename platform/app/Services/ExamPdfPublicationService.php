<?php

namespace App\Services;

use App\Models\{Category, Exam, ExamQualitySource, SourceExamImport};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamPdfPublicationService
{
    public function source(Exam $exam): ?ExamQualitySource
    {
        $storage = app(ExamQualitySourceStorage::class);
        return $exam->qualitySources()->where('organization_id', $exam->organization_id)
            ->where('is_active', true)->whereIn('role', ['questions', 'combined'])
            ->orderByRaw("CASE WHEN role = 'questions' THEN 0 ELSE 1 END")->latest('id')->get()
            ->first(fn ($source) => $source->file_path
                && strtolower(pathinfo($source->file_path, PATHINFO_EXTENSION)) === 'pdf'
                && Storage::disk($storage->diskFor($source))->exists($source->file_path));
    }

    public function category(int $tenant, string $title, array $groupIds): Category
    {
        $title = trim($title);
        if ($title === '') throw ValidationException::withMessages(['new_category' => 'Enter a category name.']);
        $existing = Category::where('organization_id', $tenant)->whereNull('parent_id')->where('title', $title)->first();
        if ($existing) {
            \App\Support\CategoryHierarchy::validate($existing->id, null, $groupIds, $tenant);
            return $existing;
        }
        $base = Str::slug($title) ?: 'category';
        $slug = $base;
        for ($suffix = 2; Category::where('slug', $slug)->exists(); $suffix++) $slug = $base.'-'.$suffix;
        $category = Category::create(['organization_id' => $tenant, 'title' => $title, 'slug' => $slug, 'status' => 1]);
        $category->groups()->sync($groupIds);
        return $category;
    }

    public function publish(int $tenant, array $ids, array $options = []): int
    {
        return DB::transaction(function () use ($tenant, $ids, $options) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $exams = Exam::where('organization_id', $tenant)->whereIn('id', $ids)->lockForUpdate()->get();
            if (! $ids || $exams->count() !== count($ids)) throw ValidationException::withMessages(['exam_ids' => 'Select valid exams from this organization.']);
            $mode = $options['category_mode'] ?? 'keep';
            foreach ($exams as $exam) {
                if (! $this->source($exam)) throw ValidationException::withMessages(['exam_ids' => $exam->name.': a saved question or combined PDF is required.']);
            }
            $allGroups = $exams->flatMap(fn ($exam) => $exam->groups()->pluck('groups.id'))->unique()->values()->all();
            if (! empty($options['group_id'])) $allGroups = [(int) $options['group_id']];
            $newCategory = $mode === 'create' ? $this->category($tenant, $options['new_category'] ?? '', $allGroups) : null;
            foreach ($exams as $exam) {
                $groupIds = ! empty($options['group_id']) ? [(int) $options['group_id']] : $exam->groups()->pluck('groups.id')->all();
                $packageIds = $exam->packages()->pluck('packages.id')->all();
                if ($packageIds && ($mode !== 'keep' || ! empty($options['group_id']))) {
                    throw ValidationException::withMessages(['category_mode' => $exam->name.': group and category come from its package. Keep classification or edit the package first.']);
                }
                $categoryId = match ($mode) {
                    'assign' => (int) ($options['category_id'] ?? 0) ?: null,
                    'create' => $newCategory->id,
                    default => $exam->category_level_1,
                };
                if ($mode === 'assign' && ! $categoryId) throw ValidationException::withMessages(['category_id' => 'Select a category.']);
                $scope = app(ExamScopeService::class)->resolve($tenant, $packageIds, $groupIds, $categoryId, $mode === 'keep' ? $exam->category_level_2 : null);
                app(ExamScopeService::class)->sync($exam, $scope);
                if (! $exam->slug) {
                    $base = Str::slug($exam->name) ?: 'exam';
                    $slug = $base;
                    for ($suffix = 2; Exam::where('organization_id', $tenant)->where('slug', $slug)->whereKeyNot($exam->id)->exists(); $suffix++) $slug = $base.'-'.$suffix;
                    $exam->slug = $slug;
                }
                $exam->fill(['status' => 'Active'])->save();
                // Extraction must retain the classification selected when publishing the PDF.
                foreach (SourceExamImport::where('organization_id', $tenant)->where('exam_id', $exam->id)->get() as $import) {
                    $import->update(['settings' => array_merge((array) $import->settings, [
                        'group_ids' => $scope['group_ids'], 'package_ids' => $scope['package_ids'],
                        'category_id' => $scope['category_level_1'], 'subcategory_id' => $scope['category_level_2'],
                    ])]);
                }
            }
            return $exams->count();
        });
    }
}
