<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Exam;
use App\Models\ExamQualityAudit;
use App\Models\ExamQualityFinding;
use App\Models\ExamQualitySourceProfile;
use App\Models\Category;
use App\Models\Group;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionRepairDraft;
use App\Models\QuestionRepairRelease;
use App\Models\QuestionVersion;
use App\Models\SourceExamImport;
use App\Services\QuestionRepairService;
use App\Services\StructuredContentReviewService;
use App\Support\AiProvider;
use App\Services\QuestionAnswerEvaluator;
use App\Support\Tenant;
use App\Support\SaasAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExamQualityAuditController extends Controller
{
    public function index(Request $request)
    {
        $requestedExamIds = collect(
            $request->input('exam_ids', $request->filled('exam_id') ? [$request->input('exam_id')] : [])
        )->map(fn ($id) => (int) $id)->filter()->unique()->values();

        $tenantId = Tenant::id();

        $groups = Group::where('organization_id', $tenantId)
            ->displayOrdered()->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenantId)
            ->with('groups:id,group_name')->displayOrdered()
            ->get(['id', 'parent_id', 'title']);
        $packages = Package::where('organization_id', $tenantId)
            ->with('groups:id,group_name')->displayOrdered()
            ->get(['id', 'name', 'category_level_1', 'category_level_2']);
        $exams = Exam::where('organization_id', $tenantId)
            ->whereIn('id', $requestedExamIds)
            // Only live papers with attached questions can be audited. Source-import
            // targets remain hidden until an administrator explicitly publishes them.
            ->whereHas('questions')
            ->with([
                'groups:id,group_name',
                'packages:id,name,category_level_1,category_level_2',
                'qualitySources:id,exam_id,role,label,file_path',
            ])
            ->withCount(['questions', 'qualitySources'])
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'category_level_1', 'category_level_2', 'created_at']);
        // Render only explicitly requested exams. All other choices are loaded
        // through the conditional, searchable 30-result endpoint.
        $availableExams = $exams;

        $sourceImports = SourceExamImport::where('organization_id', $tenantId)
            ->whereNotNull('exam_id')
            ->whereIn('exam_id', $requestedExamIds)
            ->latest('id')->get(['id', 'exam_id', 'settings'])
            ->unique('exam_id')->keyBy('exam_id');

        $categoryById = $categories->keyBy('id');
        $packageById = $packages->keyBy('id');
        $categoryOptions = $categories->map(function ($category) use ($categoryById) {
            $groupIds = $category->groups->pluck('id');
            if ($groupIds->isEmpty() && $category->parent_id) {
                $groupIds = $categoryById->get($category->parent_id)?->groups?->pluck('id') ?? collect();
            }

            return [
                'id' => (int) $category->id,
                'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'title' => $category->title,
                'group_ids' => $groupIds->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();

        $packageOptions = $packages->map(function ($package) use ($categoryById) {
            $categoryIds = collect([$package->category_level_1, $package->category_level_2])->filter()->map(fn ($id) => (int) $id);
            $categoryIds->each(function ($id) use ($categoryIds, $categoryById) {
                $parentId = $categoryById->get($id)?->parent_id;
                if ($parentId) $categoryIds->push((int) $parentId);
            });
            $categoryGroupIds = $categoryIds->unique()->flatMap(
                fn ($id) => $categoryById->get($id)?->groups?->pluck('id') ?? collect()
            );
            return [
                'id' => (int) $package->id,
                'name' => $package->name,
                'category_id' => $package->category_level_1 ? (int) $package->category_level_1 : null,
                'subcategory_id' => $package->category_level_2 ? (int) $package->category_level_2 : null,
                'group_ids' => $package->groups->pluck('id')->merge($categoryGroupIds)
                    ->map(fn ($id) => (int) $id)->unique()->values(),
            ];
        })->values();

        $examOptions = $exams->map(function ($exam) use ($categoryById, $packageById, $sourceImports) {
            $importSettings = (array) ($sourceImports->get($exam->id)?->settings ?? []);
            $importPackages = collect($importSettings['package_ids'] ?? [])->map(fn ($id) => $packageById->get((int) $id))->filter();
            $packageGroupIds = $exam->packages->flatMap(fn ($package) => $package->groups->pluck('id'))
                ->merge($importPackages->flatMap(fn ($package) => $package->groups->pluck('id')));
            $categoryIds = collect([$exam->category_level_1, $exam->category_level_2])
                ->merge($exam->packages->flatMap(fn ($package) => [$package->category_level_1, $package->category_level_2]))
                ->merge($importPackages->flatMap(fn ($package) => [$package->category_level_1, $package->category_level_2]))
                ->merge([$importSettings['category_id'] ?? null, $importSettings['subcategory_id'] ?? null])
                ->filter()->map(fn ($id) => (int) $id);
            $categoryIds->each(function ($id) use ($categoryIds, $categoryById) {
                $parentId = $categoryById->get($id)?->parent_id;
                if ($parentId) $categoryIds->push((int) $parentId);
            });
            $categoryIds = $categoryIds->unique()->values();
            $categoryGroupIds = $categoryIds->flatMap(
                fn ($id) => $categoryById->get($id)?->groups?->pluck('id') ?? collect()
            );

            return [
                'id' => (int) $exam->id,
                'name' => $exam->name,
                'question_count' => (int) $exam->questions_count,
                'source_count' => (int) $exam->quality_sources_count,
                'source_imported' => $sourceImports->has($exam->id),
                'source_details' => $exam->qualitySources->map(fn ($source) => [
                    'role' => ucfirst((string) $source->role),
                    'name' => $source->label ?: basename((string) $source->file_path),
                ])->values(),
                'category_id' => $exam->category_level_1 ? (int) $exam->category_level_1 : null,
                'subcategory_id' => $exam->category_level_2 ? (int) $exam->category_level_2 : null,
                'category_ids' => $categoryIds,
                'package_ids' => $exam->packages->pluck('id')->merge($importSettings['package_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)->unique()->values(),
                'group_ids' => $exam->groups->pluck('id')->merge($packageGroupIds)->merge($categoryGroupIds)
                    ->merge($importSettings['group_ids'] ?? [])
                    ->unique()->map(fn ($id) => (int) $id)->values(),
            ];
        })->values();

        $requestedExamIds = collect(
            $request->input('exam_ids', $request->filled('exam_id') ? [$request->input('exam_id')] : [])
        )->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $examOptions->contains('id', $id))
            ->unique()
            ->values();

        $sourceProfiles = ExamQualitySourceProfile::where('organization_id', $tenantId)
            ->orderBy('name')->get(['id', 'name', 'settings']);
        $sourceProfileOptions = $sourceProfiles->mapWithKeys(
            fn ($profile) => [(string) $profile->id => $profile->settings]
        );

        $audits = ExamQualityAudit::where('organization_id', $tenantId)
            ->where(fn ($query) => $query->whereNull('options->manual_editor')->orWhere('options->manual_editor', false))
            ->with(['exam:id,name', 'requester:id,name'])
            ->latest()->simplePaginate(20)->withQueryString();

        $auditTotals = ExamQualityAudit::where('organization_id', $tenantId)
            ->where(fn ($query) => $query->whereNull('options->manual_editor')->orWhere('options->manual_editor', false))
            ->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status IN ('queued','starting','running','stop_requested') THEN 1 ELSE 0 END) AS running")
            ->first();

        $findingTotals = ExamQualityFinding::where('organization_id', $tenantId)
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count, SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count")
            ->first();

        $auditStats = [
            'total' => (int) ($auditTotals?->total ?? 0),
            'running' => (int) ($auditTotals?->running ?? 0),
            'open_findings' => (int) ($findingTotals?->open_count ?? 0),
            'resolved_findings' => (int) ($findingTotals?->resolved_count ?? 0),
        ];

        $auditEntitlements = [
            'source' => SaasAccess::featureEnabled('exam_quality_source'),
            'visual' => SaasAccess::featureEnabled('exam_quality_visual'),
            'ai' => SaasAccess::featureEnabled('exam_quality_ai'),
        ];

        $auditLimits = [
            'questions' => SaasAccess::limit('quality_questions_per_audit'),
            'source' => SaasAccess::limit('quality_source_per_audit'),
            'visual' => SaasAccess::limit('quality_visual_per_audit'),
            'ai' => SaasAccess::limit('quality_ai_per_audit'),
            'audits_monthly' => SaasAccess::limit('quality_audits_monthly'),
            'audits_used' => SaasAccess::usage('quality_audits_monthly'),
            'repairs_monthly' => SaasAccess::limit('quality_repairs_monthly'),
            'repairs_used' => SaasAccess::usage('quality_repairs_monthly'),
        ];

        return view('exam-quality.index', compact(
            'groups', 'categoryOptions', 'packageOptions', 'examOptions', 'availableExams', 'sourceProfiles', 'sourceProfileOptions', 'audits', 'auditStats', 'auditEntitlements', 'auditLimits', 'requestedExamIds'
        ));
    }

    public function examSearch(Request $request)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)],
        ]);
        $term = trim((string) ($data['q'] ?? ''));
        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = 30;
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $packageId = isset($data['package_id']) ? (int) $data['package_id'] : null;

        $categoryPackageIds = $categoryId ? Package::where('organization_id', $tenantId)
            ->where(fn ($query) => $query->where('category_level_1', $categoryId)->orWhere('category_level_2', $categoryId))
            ->pluck('id')->map(fn ($id) => (int) $id)->values() : collect();
        $groupCategoryIds = collect();
        if ($groupId) {
            $groupCategoryIds = Category::where('organization_id', $tenantId)
                ->whereHas('groups', fn ($query) => $query->whereKey($groupId))
                ->pluck('id')->map(fn ($id) => (int) $id);
            $childIds = Category::where('organization_id', $tenantId)->whereIn('parent_id', $groupCategoryIds)
                ->pluck('id')->map(fn ($id) => (int) $id);
            $groupCategoryIds = $groupCategoryIds->merge($childIds)->unique()->values();
        }
        $groupPackageIds = $groupId ? Package::where('organization_id', $tenantId)
            ->where(function ($query) use ($groupId, $groupCategoryIds) {
                $query->whereHas('groups', fn ($relation) => $relation->whereKey($groupId));
                if ($groupCategoryIds->isNotEmpty()) {
                    $query->orWhereIn('category_level_1', $groupCategoryIds)->orWhereIn('category_level_2', $groupCategoryIds);
                }
            })->pluck('id')->map(fn ($id) => (int) $id)->values() : collect();

        $importExamIds = collect();
        if ($groupId || $categoryId || $packageId) {
            $importQuery = SourceExamImport::where('organization_id', $tenantId)->whereNotNull('exam_id');
            if ($groupId) {
                $importQuery->where(function ($scope) use ($groupId, $groupPackageIds, $groupCategoryIds) {
                    $scope->whereJsonContains('settings->group_ids', $groupId);
                    foreach ($groupPackageIds as $id) $scope->orWhereJsonContains('settings->package_ids', $id);
                    foreach ($groupCategoryIds as $id) {
                        $scope->orWhere('settings->category_id', $id)->orWhere('settings->subcategory_id', $id);
                    }
                });
            }
            if ($categoryId) {
                $importQuery->where(function ($scope) use ($categoryId, $categoryPackageIds) {
                    $scope->where('settings->category_id', $categoryId)->orWhere('settings->subcategory_id', $categoryId);
                    foreach ($categoryPackageIds as $id) $scope->orWhereJsonContains('settings->package_ids', $id);
                });
            }
            if ($packageId) $importQuery->whereJsonContains('settings->package_ids', $packageId);
            $importExamIds = $importQuery->pluck('exam_id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        $query = Exam::query()->where('organization_id', $tenantId)->whereHas('questions');
        if ($groupId) {
            $query->where(function ($scope) use ($groupId, $groupPackageIds, $groupCategoryIds, $importExamIds) {
                $scope->whereHas('groups', fn ($relation) => $relation->whereKey($groupId))
                    ->orWhereHas('packages', fn ($relation) => $relation->whereIn('packages.id', $groupPackageIds));
                if ($groupCategoryIds->isNotEmpty()) {
                    $scope->orWhereIn('category_level_1', $groupCategoryIds)->orWhereIn('category_level_2', $groupCategoryIds)
                        ->orWhereHas('packages', fn ($relation) => $relation->whereIn('category_level_1', $groupCategoryIds)->orWhereIn('category_level_2', $groupCategoryIds));
                }
                if ($importExamIds->isNotEmpty()) $scope->orWhereIn('exams.id', $importExamIds);
            });
        }
        if ($categoryId) {
            $query->where(function ($scope) use ($categoryId, $categoryPackageIds, $importExamIds) {
                $scope->where('category_level_1', $categoryId)->orWhere('category_level_2', $categoryId)
                    ->orWhereHas('packages', fn ($relation) => $relation->whereIn('packages.id', $categoryPackageIds));
                if ($importExamIds->isNotEmpty()) $scope->orWhereIn('exams.id', $importExamIds);
            });
        }
        if ($packageId) {
            $query->where(function ($scope) use ($packageId, $importExamIds) {
                $scope->whereHas('packages', fn ($relation) => $relation->whereKey($packageId));
                if ($importExamIds->isNotEmpty()) $scope->orWhereIn('exams.id', $importExamIds);
            });
        }
        if ($term !== '') {
            $escaped = addcslashes($term, '%_\\');
            $query->where('name', 'like', '%'.$escaped.'%');
        }

        $total = (clone $query)->count();
        $exams = $query->withCount(['questions', 'qualitySources'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->skip(($page - 1) * $pageSize)->take($pageSize)->get(['id', 'name']);
        $sourceImportedIds = SourceExamImport::where('organization_id', $tenantId)
            ->whereIn('exam_id', $exams->pluck('id'))->pluck('exam_id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'results' => $exams->map(fn (Exam $exam) => [
                'id' => (int) $exam->id,
                'text' => $exam->name,
                'name' => $exam->name,
                'question_count' => (int) $exam->questions_count,
                'source_count' => (int) $exam->quality_sources_count,
                'source_imported' => in_array((int) $exam->id, $sourceImportedIds, true),
                'source_details' => [],
            ])->values(),
            'count' => $total,
            'page_size' => $pageSize,
            'pagination' => ['more' => $page * $pageSize < $total],
        ]);
    }
    public function store(Request $request, \App\Services\ExamQualityAuditProcessLauncher $processLauncher)
    {
        $tenantId = Tenant::id();
        SaasAccess::abortIfLimitReached('quality_audits_monthly');

        $questionMax = min(5000, SaasAccess::limit('quality_questions_per_audit') ?? 5000);
        $aiMax = min(100, SaasAccess::limit('quality_ai_per_audit') ?? 100);
        $visualMax = min(100, SaasAccess::limit('quality_visual_per_audit') ?? 100);
        $sourceMax = min(100, SaasAccess::limit('quality_source_per_audit') ?? 100);
        if ($questionMax < 1) abort(403, 'Your organization plan does not allow questions in an audit.');
        if ($request->boolean('include_source') && $sourceMax < 1) abort(403, 'Your organization plan source comparison limit is zero.');
        if ($request->boolean('include_visual') && $visualMax < 1) abort(403, 'Your organization plan visual audit limit is zero.');
        if ($request->boolean('include_image_audit') && $sourceMax < 1) abort(403, 'Your organization plan image source-audit limit is zero.');
        if ($request->boolean('include_ai') && $aiMax < 1) abort(403, 'Your organization plan AI audit limit is zero.');

        $data = $request->validate([
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)],
            'exam_id' => ['nullable', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'exam_ids' => ['nullable', 'array'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'include_ai' => ['nullable', 'boolean'],
            'include_visual' => ['nullable', 'boolean'],
            'include_source' => ['nullable', 'boolean'],
            'include_image_audit' => ['nullable', 'boolean'],
            'question_limit' => ['nullable', 'integer', 'min:1', 'max:'.$questionMax],
            'ai_limit' => ['nullable', 'integer', 'min:1', 'max:'.$aiMax],
            'visual_limit' => ['nullable', 'integer', 'min:1', 'max:'.$visualMax],
            'source_limit' => ['nullable', 'integer', 'min:1', 'max:'.$sourceMax],
            'image_limit' => ['nullable', 'integer', 'min:1', 'max:'.$sourceMax],
            'profile_enabled' => ['nullable', 'boolean'],
            'profile_id' => ['nullable', Rule::exists('exam_quality_source_profiles', 'id')->where('organization_id', $tenantId)],
            'profile_name' => ['nullable', 'string', 'max:120'],
            'save_profile' => ['nullable', 'boolean'],
            'source_type' => ['nullable', Rule::in(['auto','digital','scanned','mixed'])],
            'source_layout' => ['nullable', Rule::in(['auto','single_column','two_column','bilingual_side_by_side'])],
            'audit_language' => ['nullable', 'string', 'max:80'],
            'ignored_languages' => ['nullable', 'string', 'max:255'],
            'reading_order' => ['nullable', Rule::in(['auto','rows','columns_ltr','columns_rtl'])],
            'question_numbering' => ['nullable', Rule::in(['auto','shared','continuous','restarts'])],
            'answer_source' => ['nullable', Rule::in(['auto','combined','separate'])],
            'enable_ocr' => ['nullable', 'boolean'],
            'additional_instructions' => ['nullable', 'string', 'max:2000'],
            'sample_mode' => ['nullable', 'boolean'],
            'sample_questions' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->boolean('include_source')) SaasAccess::abortIfFeatureDisabled('exam_quality_source');
        if ($request->boolean('include_image_audit')) SaasAccess::abortIfFeatureDisabled('exam_quality_source');
        if ($request->boolean('include_visual')) SaasAccess::abortIfFeatureDisabled('exam_quality_visual');
        if ($request->boolean('include_ai')) SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $selectedExamIds = collect($data['exam_ids'] ?? [])
            ->when(! empty($data['exam_id']), fn ($ids) => $ids->push((int) $data['exam_id']))
            ->map(fn ($id) => (int) $id)->unique()->values();

        if ($selectedExamIds->isEmpty() && empty($data['package_id']) && empty($data['category_id']) && empty($data['group_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'exam_ids' => 'Choose a group, category, package or one or more exams.',
            ]);
        }

        $sourceImportScopeIds = SourceExamImport::where('organization_id', $tenantId)
            ->whereNotNull('exam_id')->get(['exam_id', 'settings'])
            ->filter(function ($import) use ($data) {
                $settings = (array) $import->settings;
                if (! empty($data['package_id'])) {
                    return in_array((int) $data['package_id'], array_map('intval', $settings['package_ids'] ?? []), true);
                }
                if (! empty($data['category_id'])) {
                    return in_array((int) $data['category_id'], [
                        (int) ($settings['category_id'] ?? 0),
                        (int) ($settings['subcategory_id'] ?? 0),
                    ], true);
                }
                if (! empty($data['group_id'])) {
                    return in_array((int) $data['group_id'], array_map('intval', $settings['group_ids'] ?? []), true);
                }
                return false;
            })->pluck('exam_id')->map(fn ($id) => (int) $id)->unique()->values();

        $examQuery = Exam::where('organization_id', $tenantId)
            ->whereHas('questions');
        if ($selectedExamIds->isNotEmpty()) {
            $examQuery->whereIn('id', $selectedExamIds);
        } elseif (! empty($data['package_id'])) {
            $examQuery->where(function ($query) use ($data, $sourceImportScopeIds) {
                $query->whereHas('packages', fn ($packageQuery) => $packageQuery->whereKey($data['package_id']))
                    ->orWhereIn('id', $sourceImportScopeIds);
            });
        } elseif (! empty($data['category_id'])) {
            $categoryId = (int) $data['category_id'];
            $examQuery->where(function ($query) use ($categoryId, $sourceImportScopeIds) {
                $query->where('category_level_1', $categoryId)
                    ->orWhere('category_level_2', $categoryId)
                    ->orWhereHas('packages', fn ($packageQuery) => $packageQuery
                        ->where('category_level_1', $categoryId)
                        ->orWhere('category_level_2', $categoryId))
                    ->orWhereIn('id', $sourceImportScopeIds);
            });
        } else {
            $groupId = (int) $data['group_id'];
            $examQuery->where(function ($query) use ($groupId, $sourceImportScopeIds) {
                $query->whereHas('groups', fn ($groupQuery) => $groupQuery->whereKey($groupId))
                    ->orWhereHas('packages.groups', fn ($groupQuery) => $groupQuery->whereKey($groupId))
                    ->orWhereIn('id', $sourceImportScopeIds);
            });
        }

        $examIds = $examQuery->orderByDesc('created_at')->pluck('id');
        if ($examIds->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'exam_ids' => 'No published exams with linked questions match this selection. Review and explicitly publish the source paper first.',
            ]);
        }

        $monthlyLimit = SaasAccess::limit('quality_audits_monthly');
        $monthlyUsed = SaasAccess::usage('quality_audits_monthly');
        if ($monthlyLimit !== null && $monthlyUsed + $examIds->count() > $monthlyLimit) {
            $remaining = max(0, $monthlyLimit - $monthlyUsed);
            throw \Illuminate\Validation\ValidationException::withMessages([
                'exam_ids' => "This selection contains {$examIds->count()} exams, but your plan has {$remaining} audit slots remaining this month.",
            ]);
        }

        $profile = [];
        if ($request->boolean('profile_enabled')) {
            if (! empty($data['profile_id'])) {
                $profile = (array) ExamQualitySourceProfile::where('organization_id', $tenantId)
                    ->findOrFail($data['profile_id'])->settings;
            }
            $profile = array_merge($profile, [
                'source_type' => $data['source_type'] ?? 'auto',
                'layout' => $data['source_layout'] ?? 'auto',
                'audit_language' => trim((string) ($data['audit_language'] ?? '')),
                'ignored_languages' => trim((string) ($data['ignored_languages'] ?? '')),
                'reading_order' => $data['reading_order'] ?? 'auto',
                'question_numbering' => $data['question_numbering'] ?? 'auto',
                'answer_source' => $data['answer_source'] ?? 'auto',
                'enable_ocr' => $request->boolean('enable_ocr'),
                'additional_instructions' => trim((string) ($data['additional_instructions'] ?? '')),
            ]);
            if ($request->boolean('save_profile')) {
                $profileName = trim((string) ($data['profile_name'] ?? ''));
                if ($profileName === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'profile_name' => 'Enter a name before saving this processing profile.',
                    ]);
                }
                ExamQualitySourceProfile::updateOrCreate(
                    ['organization_id' => $tenantId, 'name' => $profileName],
                    ['settings' => $profile, 'created_by' => auth()->id()]
                );
            }
        }

        $sampleQuestions = trim((string) ($data['sample_questions'] ?? ''));
        if ($request->boolean('sample_mode')) {
            if ($sampleQuestions === '' || ! preg_match('/^\s*\d+(?:\s*-\s*\d+)?(?:\s*,\s*\d+(?:\s*-\s*\d+)?)*\s*$/', $sampleQuestions)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sample_questions' => 'Enter sample paper question numbers such as 1-10, 19, 25-30.',
                ]);
            }
        }

        $settings = Configuration::where('organization_id', $tenantId)->first();
        $sourceProvider = $request->boolean('include_source')
            ? AiProvider::firstAvailable($settings, false, 'source_text_audit')
            : null;
        $imageProvider = $request->boolean('include_image_audit')
            ? AiProvider::firstAvailable($settings, true, 'image_audit')
            : null;
        $academicProvider = $request->boolean('include_ai')
            ? AiProvider::firstAvailable($settings, false, 'academic_review')
            : null;
        if ($request->boolean('include_source') && ! $sourceProvider) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'include_source' => 'No compatible Source Text Audit provider is configured. Review its priority, API key and model in Admin AI Settings.',
            ]);
        }
        if ($request->boolean('include_image_audit') && ! $imageProvider) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'include_image_audit' => 'No vision-capable Image Audit provider is configured in Admin AI Settings.',
            ]);
        }
        if ($request->boolean('include_ai') && ! $academicProvider) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'include_ai' => 'No Academic Review provider is configured. Review its priority, API key and model in Admin AI Settings.',
            ]);
        }

        $batchToken = (string) Str::uuid();
        $common = [
            'organization_id' => $tenantId,
            'requested_by' => auth()->id(),
            'status' => 'queued',
            'include_ai' => $request->boolean('include_ai'),
            'include_visual' => $request->boolean('include_visual'),
            'include_source' => $request->boolean('include_source'),
            'question_limit' => $data['question_limit'] ?? (SaasAccess::limit('quality_questions_per_audit') ?: null),
            'options' => [
                'ai_limit' => min((int) ($data['ai_limit'] ?? $aiMax), $aiMax),
                'visual_limit' => min((int) ($data['visual_limit'] ?? 30), $visualMax),
                'source_limit' => min((int) ($data['source_limit'] ?? $sourceMax), $sourceMax),
                'base_url' => $request->getSchemeAndHttpHost(),
                'source_ai_provider' => $sourceProvider['provider'] ?? null,
                'image_limit' => min((int) ($data['image_limit'] ?? $sourceMax), $sourceMax),
                'source_ai_model' => $sourceProvider['model'] ?? null,
                'academic_ai_provider' => $academicProvider['provider'] ?? null,
                'academic_ai_model' => $academicProvider['model'] ?? null,
                'include_image_audit' => $request->boolean('include_image_audit'),
                'image_ai_provider' => $imageProvider['provider'] ?? null,
                'image_ai_model' => $imageProvider['model'] ?? null,
                'combined_ai_request' => $request->boolean('include_source') && $request->boolean('include_ai')
                    && ($sourceProvider['provider'] ?? null) === ($academicProvider['provider'] ?? null)
                    && ($sourceProvider['model'] ?? null) === ($academicProvider['model'] ?? null),
                'provider_locked_at' => now()->toIso8601String(),
                'batch_token' => $batchToken,
                'source_profile' => $profile ?: null,
                'sample_mode' => $request->boolean('sample_mode'),
                'sample_questions' => $request->boolean('sample_mode') ? $sampleQuestions : null,
            ],
        ];

        $audits = DB::transaction(function () use ($examIds, $common) {
            return $examIds->map(fn ($examId) => ExamQualityAudit::create(array_merge($common, [
                'exam_id' => $examId,
                'public_token' => (string) Str::uuid(),
            ])));
        });

        if ($audits->count() === 1) {
            $started = $processLauncher->start((int) $audits->first()->id);
            $redirect = redirect()->route('exam-quality.show', $audits->first());
            return $started
                ? $redirect->with('success', 'Quality audit started immediately.')
                : $redirect->with('error', 'The immediate audit worker could not start. The recovery scheduler will retry automatically; check PHP_CLI_BINARY if it remains queued.');
        }

        $startedWorkers = $processLauncher->startBatch($batchToken, $audits->count());
        $redirect = redirect()->route('exam-quality.index');
        return $startedWorkers
            ? $redirect->with('success', $audits->count()." paper audits started immediately with {$startedWorkers} parallel workers.")
            : $redirect->with(
                'error',
                $audits->count().' audits were created, but the immediate workers could not start. The recovery scheduler will retry automatically; check PHP_CLI_BINARY if they remain queued.'
            );
    }

    public function startNow(ExamQualityAudit $audit, \App\Services\ExamQualityAuditProcessLauncher $processLauncher)
    {
        $this->authorizeTenant($audit);
        if ($audit->status !== 'queued') {
            return back()->with('success', 'This audit has already been claimed by a worker.');
        }

        if ($processLauncher->start((int) $audit->id)) {
            return back()->with('success', 'Audit worker started immediately. The status will refresh automatically.');
        }

        return back()->with(
            'error',
            'The immediate worker could not be launched. The scheduled worker will continue retrying automatically.'
        );
    }

    public function stop(ExamQualityAudit $audit)
    {
        $this->authorizeTenant($audit);
        if (! in_array($audit->status, ['queued','starting','running','stop_requested'], true)) {
            return back()->with('success', 'This audit is no longer running.');
        }
        $options = (array) $audit->options;
        $options['stop_requested_at'] = now()->toIso8601String();
        $options['stop_requested_by'] = auth()->id();
        $queued = $audit->status === 'queued';
        $audit->update([
            'status' => $queued ? 'cancelled' : 'stop_requested',
            'options' => $options,
            'completed_at' => $queued ? now() : null,
            'failure_message' => null,
        ]);
        return back()->with('success', $queued
            ? 'The queued audit was cancelled before an API call started.'
            : 'Stop requested. The current API request may finish, but no new paid batch will start.');
    }

    public function releaseIndex()
    {
        $releases = QuestionRepairRelease::where('organization_id', Tenant::id())
            ->with(['exam:id,name', 'creator:id,name'])
            ->withCount(['items', 'items as unrestored_items_count' => fn ($query) => $query->where('status', 'published')])
            ->latest('published_at')->paginate(25);

        return view('exam-quality.releases-index', compact('releases'));
    }

    public function publishSelectedPapers(Request $request, QuestionRepairService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'audit_ids' => ['required', 'array', 'min:1'],
            'audit_ids.*' => [Rule::exists('exam_quality_audits', 'id')->where('organization_id', $tenantId)],
        ]);

        $audits = ExamQualityAudit::where('organization_id', $tenantId)
            ->whereIn('id', array_unique(array_map('intval', $data['audit_ids'])))
            ->with('exam:id,name')->get();

        $publishedPapers = 0;
        $publishedQuestions = 0;
        $errors = [];
        foreach ($audits as $audit) {
            $draftIds = QuestionRepairDraft::where('organization_id', $tenantId)
                ->where('audit_id', $audit->id)
                ->whereIn('status', ['ready', 'needs_review'])
                ->whereNotNull('proposed_payload')
                ->whereNotNull('changed_fields')
                ->whereRaw('JSON_LENGTH(changed_fields) > 0')
                ->pluck('id')->all();

            if ($draftIds === []) {
                $errors[] = ($audit->exam?->name ?: "Audit {$audit->id}").': no repair drafts with changes are available to publish.';
                continue;
            }

            try {
                $release = $service->publishBatch(
                    $audit,
                    $draftIds,
                    (int) auth()->id(),
                    ($audit->exam?->name ?: 'Paper').' administrator release '.now()->format('Y-m-d H:i'),
                    true
                );
                $publishedPapers++;
                $publishedQuestions += $release->items->count();
            } catch (\Throwable $e) {
                $errors[] = ($audit->exam?->name ?: "Audit {$audit->id}").': '.$e->getMessage();
            }
        }

        if ($publishedPapers === 0) {
            return back()->with('error', implode(' ', $errors) ?: 'No selected paper was ready to publish.');
        }

        $message = "{$publishedQuestions} questions published across {$publishedPapers} selected papers.";
        if ($errors !== []) $message .= ' Some papers were skipped: '.implode(' ', $errors);

        return back()->with($errors === [] ? 'success' : 'warning', $message);
    }

    public function show(Request $request, ExamQualityAudit $audit)
    {
        $this->authorizeTenant($audit);
        $audit->load(['exam:id,name', 'requester:id,name']);
        $status = $request->query('status');
        $applyFilters = function ($query) use ($request, $status) {
            return $query
                ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
                ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
                ->when(!$status, fn ($q) => $q->where('status', 'open'))
                ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source));
        };

        $groupExpression = 'CASE WHEN question_id IS NULL THEN -id ELSE question_id END';
        // Keep findings in the same local-question sequence used by the source PDF.
        // Severity remains an issue attribute inside each question, not a reason to
        // randomly move that question away from its paper position.
        $paperOrderExpression = 'COALESCE((SELECT MIN(eq.id) FROM exam_questions eq WHERE eq.exam_id = '.(int) $audit->exam_id.' AND eq.question_id = exam_quality_findings.question_id), 2147483647)';
        $groupPages = $applyFilters($audit->findings())
            ->selectRaw("{$groupExpression} AS group_key")
            ->groupByRaw($groupExpression)
            ->orderByRaw("MIN({$paperOrderExpression}) ASC")
            ->orderByRaw('MIN(id) ASC')
            ->paginate(25)->withQueryString();

        $groupKeys = collect($groupPages->items())->pluck('group_key')->map(fn ($key) => (int) $key);
        $questionIds = $groupKeys->filter(fn ($key) => $key > 0)->values();
        $standaloneFindingIds = $groupKeys->filter(fn ($key) => $key < 0)->map(fn ($key) => abs($key))->values();

        $findings = $groupKeys->isEmpty()
            ? collect()
            : $applyFilters($audit->findings())
                ->with(['question:id,question,question_code,qtype_id', 'question.qtype:id,question_type,type'])
                ->where(function ($query) use ($questionIds, $standaloneFindingIds) {
                    if ($questionIds->isNotEmpty()) {
                        $query->whereIn('question_id', $questionIds);
                    }
                    if ($standaloneFindingIds->isNotEmpty()) {
                        $method = $questionIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('id', $standaloneFindingIds);
                    }
                })
                ->orderByRaw("FIELD(severity, 'critical', 'error', 'warning', 'info')")
                ->latest()->get();

        $findingGroups = $groupKeys->mapWithKeys(function ($key) use ($findings) {
            $items = $key > 0
                ? $findings->where('question_id', $key)->values()
                : $findings->where('id', abs($key))->values();
            return [(string) $key => $items];
        });

        // Match the local number used by paper/PDF rendering, which orders the
        // exam's questions by the exam_questions pivot ID.
        $paperQuestionNumbers = DB::table('exam_questions')
            ->where('exam_id', $audit->exam_id)
            ->orderBy('id')
            ->pluck('question_id')
            ->values()
            ->mapWithKeys(fn ($questionId, $index) => [(int) $questionId => $index + 1]);

        $activeTab = $request->query('tab') === 'passed' ? 'passed' : 'findings';
        $paperQuestionIds = $paperQuestionNumbers->keys()->map(fn ($id) => (int) $id)->values();
        $auditedQuestionIds = $paperQuestionIds;
        if (data_get($audit->options, 'sample_mode')) {
            $ordinals = collect(preg_split('/\s*,\s*/', (string) data_get($audit->options, 'sample_questions', ''), -1, PREG_SPLIT_NO_EMPTY))
                ->flatMap(function ($token) {
                    if (preg_match('/^(\d+)\s*(?:-|\\x{2013}|\\x{2014})\s*(\d+)$/u', trim($token), $matches)) {
                        $start = max(1, (int) $matches[1]);
                        $end = max(1, (int) $matches[2]);
                        return $start <= $end ? range($start, $end) : range($start, $end, -1);
                    }
                    return ctype_digit(trim($token)) ? [(int) trim($token)] : [];
                })->filter(fn ($ordinal) => $ordinal > 0)->unique()->sort()->take(500)->values();
            if ($ordinals->isNotEmpty()) {
                $auditedQuestionIds = $ordinals->map(fn ($ordinal) => $paperQuestionIds->get($ordinal - 1))->filter()->values();
            }
        }
        $auditedCount = (int) ($audit->total_questions ?: $audit->checked_questions);
        if ($auditedCount > 0) {
            $auditedQuestionIds = $auditedQuestionIds->take($auditedCount)->values();
        }
        $findingQuestionIds = $audit->findings()->whereNotNull('question_id')->distinct()->pluck('question_id')->map(fn ($id) => (int) $id);
        $passedQuestionIds = $auditedQuestionIds->diff($findingQuestionIds)->values();
        $passedQuery = Question::query()->with('qtype:id,question_type,type');
        if ($passedQuestionIds->isEmpty()) {
            $passedQuery->whereRaw('1 = 0');
        } else {
            $passedQuery->whereIn('id', $passedQuestionIds)
                ->orderByRaw('FIELD(id,'.implode(',', $passedQuestionIds->all()).')');
        }
        $passedQuestions = $passedQuery->paginate(25, ['*'], 'passed_page')->withQueryString();
        $repairDrafts = QuestionRepairDraft::where('audit_id', $audit->id)
            ->whereIn('question_id', $questionIds)->get()->keyBy('question_id');

        $openFindings = $audit->findings()->where('status', 'open');
        $auditStats = [
            'checked' => (int) $audit->checked_questions,
            'passed' => (int) $audit->passed_questions,
            'warning_questions' => (clone $openFindings)->whereNotNull('question_id')->whereIn('severity', ['warning', 'info'])->distinct()->count('question_id'),
            'warning_issues' => (clone $openFindings)->whereIn('severity', ['warning', 'info'])->count(),
            'error_questions' => (clone $openFindings)->whereNotNull('question_id')->whereIn('severity', ['critical', 'error'])->distinct()->count('question_id'),
            'error_issues' => (clone $openFindings)->whereIn('severity', ['critical', 'error'])->count(),
        ];

        $readyDraftCount = QuestionRepairDraft::where('audit_id', $audit->id)->where('status', 'ready')->count();
        $releases = QuestionRepairRelease::where('audit_id', $audit->id)->withCount(['items','items as restored_items_count' => fn ($q) => $q->where('status','restored')])->latest()->get();

        return view('exam-quality.show', compact('audit', 'findingGroups', 'groupPages', 'auditStats', 'paperQuestionNumbers', 'repairDrafts', 'readyDraftCount', 'releases', 'activeTab', 'passedQuestions'));
    }

    public function reviewRepairBatch(Request $request, ExamQualityAudit $audit)
    {
        $this->authorizeTenant($audit);
        $data = $request->validate(['draft_ids' => ['nullable','array'], 'draft_ids.*' => ['integer'], 'all_ready' => ['nullable','boolean']]);
        $query = QuestionRepairDraft::where('organization_id', Tenant::id())->where('audit_id', $audit->id)->where('status', 'ready');
        if (! ($data['all_ready'] ?? false)) {
            abort_if(empty($data['draft_ids']), 422, 'Select at least one ready draft.');
            $query->whereIn('id', $data['draft_ids']);
        }
        $drafts = $query->with(['question.qtype','exam:id,name'])->get();
        if (! ($data['all_ready'] ?? false)) abort_unless($drafts->count() === count(array_unique(array_map('intval', $data['draft_ids']))), 422, 'One or more drafts are not ready for batch review.');
        $audit->load('exam:id,name');
        return view('exam-quality.batch-review', compact('audit','drafts'));
    }

    public function publishRepairBatch(Request $request, ExamQualityAudit $audit, QuestionRepairService $service)
    {
        $this->authorizeTenant($audit);
        $data = $request->validate(['draft_ids' => ['required','array','min:1'], 'draft_ids.*' => ['integer'], 'release_name' => ['nullable','string','max:255'], 'force_publish' => ['nullable','boolean']]);
        try {
            $release = $service->publishBatch($audit->load('exam:id,name'), $data['draft_ids'], (int) auth()->id(), $data['release_name'] ?? null, $request->boolean('force_publish'));
            $message = $release->items->count().' questions published as one reversible paper release. You can view or restore it under Paper repair releases.';
            $redirectUrl = route('exam-quality.show', ['audit' => $audit->getKey()]);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => $redirectUrl]);
            }

            return redirect()->to($redirectUrl)->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error',$e->getMessage());
        }
    }

    public function showRelease(QuestionRepairRelease $release)
    {
        abort_unless((int) $release->organization_id === (int) Tenant::id(), 404);
        $release->load(['exam:id,name','creator:id,name','items.question:id,question,question_code','items.repairDraft:id,changed_fields','items.publishedVersion']);
        return view('exam-quality.release', compact('release'));
    }

    public function restoreRepairRelease(Request $request, QuestionRepairRelease $release, QuestionRepairService $service)
    {
        abort_unless((int) $release->organization_id === (int) Tenant::id(), 404);
        $data = $request->validate(['item_ids' => ['nullable','array'], 'item_ids.*' => ['integer'], 'restore_all' => ['nullable','boolean']]);
        try {
            $count = $service->restoreRelease($release, ($data['restore_all'] ?? false) ? [] : ($data['item_ids'] ?? []), (int) auth()->id());
            return back()->with('success', $count.' '.str('question')->plural($count).' restored. The replaced content was saved as a new version.');
        } catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }

    public function queueRepairs(Request $request, ExamQualityAudit $audit, QuestionRepairService $service)
    {
        $this->authorizeTenant($audit);
        $data = $request->validate(['question_ids' => ['nullable', 'array'], 'question_ids.*' => ['integer']]);
        $queued = $this->queueDraftsForAudit($audit, $service, $data['question_ids'] ?? []);
        $label = $queued === 1 ? 'draft' : 'drafts';
        return back()->with('success', "$queued repair $label queued. The scheduler will process them shortly.");
    }

    public function queueRepairBatch(Request $request, QuestionRepairService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'audit_ids' => ['required', 'array', 'min:1'],
            'audit_ids.*' => [Rule::exists('exam_quality_audits', 'id')->where('organization_id', $tenantId)],
        ]);
        $selectedIds = collect($data['audit_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $audits = ExamQualityAudit::where('organization_id', $tenantId)
            ->whereIn('id', $selectedIds)
            ->get()
            ->sortBy(fn (ExamQualityAudit $audit) => $selectedIds->search((int) $audit->id))
            ->values();
        $queued = 0;
        $audits->each(function ($audit) use ($service, &$queued) {
            $queued += $this->queueDraftsForAudit($audit, $service);
        });
        $message = $queued > 0
            ? "{$queued} missing or failed repair ".($queued === 1 ? 'draft was' : 'drafts were')." queued from saved audit evidence. No additional AI call will be made."
            : 'All available repair drafts already exist. No additional AI call was made.';

        return redirect()->route('exam-quality.show', $audits->first())
            ->with('success', $message.' The selected paper is open for review.');
    }

    private function queueDraftsForAudit(ExamQualityAudit $audit, QuestionRepairService $service, array $selectedQuestionIds = []): int
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $questionIds = $audit->findings()->where('status', 'open')->whereNotNull('question_id')
            ->when($selectedQuestionIds !== [], fn ($query) => $query->whereIn('question_id', $selectedQuestionIds))
            ->distinct()->pluck('question_id');
        $questions = $audit->exam->questions()->whereIn('questions.id', $questionIds)->get();
        $candidateCount = $questions->filter(function ($question) use ($audit, $selectedQuestionIds) {
            $status = QuestionRepairDraft::where('audit_id', $audit->id)->where('question_id', $question->id)->value('status');
            $blocked = $selectedQuestionIds !== [] ? ['queued','starting','processing','published'] : ['queued','starting','processing','ready','published'];
            return ! in_array($status, $blocked, true);
        })->count();
        $this->ensureRepairCapacity($candidateCount);
        $queued = 0;
        foreach ($questions as $question) {
            $draft = QuestionRepairDraft::firstOrNew(['audit_id' => $audit->id, 'question_id' => $question->id]);
            $blocked = $selectedQuestionIds !== [] ? ['queued','starting','processing','published'] : ['queued','starting','processing','ready','published'];
            if ($draft->exists && in_array($draft->status, $blocked, true)) continue;
            $draft->fill([
                'organization_id' => $audit->organization_id, 'exam_id' => $audit->exam_id,
                'status' => 'queued', 'original_payload' => $service->snapshot($question),
                'proposed_payload' => null, 'changed_fields' => null, 'evidence' => null,
                'confidence' => null, 'failure_message' => null, 'question_updated_at' => $question->updated_at,
                'created_by' => auth()->id(), 'reviewed_by' => null, 'reviewed_at' => null, 'published_at' => null,
            ])->save();
            $queued++;
        }
        return $queued;
    }
    public function showRepair(QuestionRepairDraft $draft, QuestionRepairService $service)
    {
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        $draft->load(['question.qtype', 'question.exams:id,name', 'exam:id,name', 'audit:id,exam_id']);
        $sharedExams = $draft->question->exams->count();
        $versions = QuestionVersion::where('organization_id', Tenant::id())->where('question_id', $draft->question_id)->with('creator:id,name')->latest()->get();
        $cropDefaultPage = $service->detectedSourcePage($draft);
        return view('exam-quality.repair', compact('draft', 'sharedExams', 'versions', 'cropDefaultPage'));
    }

    public function previewRepair(QuestionRepairDraft $draft)
    {
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        $draft->loadMissing(['question.qtype', 'exam:id,name']);
        $payload = array_replace((array) $draft->original_payload, (array) $draft->proposed_payload);

        return response()->view('question-drafts.preview', [
            'payload' => $payload,
            'title' => (string) ($draft->exam?->name ?: 'Exam paper'),
            'questionLabel' => 'Question ID '.$draft->question_id,
            'questionType' => (string) ($draft->question?->qtype?->question_type ?: $draft->question?->qtype?->type ?: 'Question'),
            'status' => (string) $draft->status,
            'contextLabel' => 'Audit repair draft',
            'backUrl' => route('exam-quality.repairs.show', $draft),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function previewPaper(ExamQualityAudit $audit, QuestionRepairService $service)
    {
        $this->authorizeTenant($audit);
        $audit->loadMissing('exam:id,name');
        $questionIds = DB::table('exam_questions')
            ->where('exam_id', $audit->exam_id)
            ->orderBy('id')
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $questionsById = Question::with('qtype:id,question_type,type')
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy('id');
        $drafts = QuestionRepairDraft::where('organization_id', Tenant::id())
            ->where('audit_id', $audit->id)
            ->whereIn('question_id', $questionIds)
            ->get()
            ->keyBy('question_id');
        $questions = $questionIds->map(function (int $questionId, int $index) use ($questionsById, $drafts, $service) {
            $question = $questionsById->get($questionId);
            if (! $question) return null;
            $draft = $drafts->get($questionId);
            $usesWorkingDraft = $draft
                && in_array($draft->status, ['ready', 'needs_review'], true)
                && ! empty($draft->proposed_payload);
            $payload = $usesWorkingDraft
                ? array_replace((array) $draft->original_payload, (array) $draft->proposed_payload)
                : $service->snapshot($question);

            return [
                'payload' => $payload,
                'label' => 'Paper Q. '.($index + 1).' - Question ID '.$questionId,
                'type' => (string) ($question->qtype?->question_type ?: $question->qtype?->type ?: 'Question'),
                'status' => (string) ($usesWorkingDraft ? $draft->status : 'Live content'),
            ];
        })->filter()->values();

        return response()->view('question-drafts.paper-preview', [
            'questions' => $questions,
            'title' => (string) ($audit->exam?->name ?: 'Exam paper'),
            'contextLabel' => 'Full audit repair draft',
            'backUrl' => route('exam-quality.show', $audit),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
    public function sourcePage(Request $request, QuestionRepairDraft $draft, QuestionRepairService $service)
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_source');
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        $data = $request->validate(['page' => ['required', 'integer', 'min:1'], 'source_role' => ['nullable', Rule::in(['questions','answers','combined'])]]);
        try { return response()->file($service->renderSourcePage($draft, (int) $data['page'], $data['source_role'] ?? 'questions'), ['Cache-Control' => 'private, max-age=300'])->deleteFileAfterSend(true); }
        catch (\Throwable $e) { abort(422, $e->getMessage()); }
    }

    public function cropRepairImage(Request $request, QuestionRepairDraft $draft, QuestionRepairService $service)
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_source');
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        $data = $request->validate([
            'page' => ['required', 'integer', 'min:1'], 'source_role' => ['required', Rule::in(['questions','answers','combined'])], 'target_field' => ['required', Rule::in(['question','option1','option2','option3','option4','option5','option6','explanation'])],
            'next_target' => ['nullable', Rule::in(['question','option1','option2','option3','option4','option5','option6','explanation'])],
            'background_mode' => ['nullable', Rule::in(['white', 'transparent'])],
            'crop_mode' => ['nullable', Rule::in(['image', 'mathpix'])], 'confirmed' => ['nullable', 'boolean'],
            'recognized_html' => ['nullable', 'string', 'max:100000'], 'mathpix_request_id' => ['nullable', 'string', 'max:255'], 'mathpix_confidence' => ['nullable', 'numeric', 'between:0,100'],
            'x0' => ['required', 'numeric', 'between:0,1'], 'y0' => ['required', 'numeric', 'between:0,1'],
            'x1' => ['required', 'numeric', 'between:0,1'], 'y1' => ['required', 'numeric', 'between:0,1'],
        ]);
        if ((float) $data['x0'] >= (float) $data['x1'] || (float) $data['y0'] >= (float) $data['y1']) throw \Illuminate\Validation\ValidationException::withMessages(['crop' => 'Draw a valid crop region on the source page.']);
        if (($data['crop_mode'] ?? 'image') === 'mathpix') {
            try {
                if (! empty($data['confirmed'])) {
                    $evidence = $service->applyManualOcrText($draft, $data['target_field'], (string) ($data['recognized_html'] ?? ''), [
                        'provider' => 'mathpix', 'request_id' => $data['mathpix_request_id'] ?? null,
                        'confidence' => isset($data['mathpix_confidence']) ? (float) $data['mathpix_confidence'] : null,
                        'source_page' => (int) $data['page'], 'source_role' => $data['source_role'],
                        'bbox_normalized' => [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']],
                    ]);
                    return response()->json(['message' => ucfirst($data['target_field']).' Mathpix text inserted and saved to the repair draft.', 'target' => $data['target_field'], 'html' => data_get($draft->fresh()->proposed_payload, $data['target_field']), 'recognition' => $evidence]);
                }
                $image = $service->extractMathpixCrop($draft, (int) $data['page'], [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']], $data['source_role']);
                try { $recognition = app(\App\Services\MathpixOcrService::class)->recognize($image, (int) $draft->organization_id); }
                finally { @unlink($image); }
                return response()->json(['message' => 'Mathpix recognition is ready for review.', 'target' => $data['target_field'], 'preview_required' => true, 'recognition' => $recognition]);
            } catch (\Throwable $exception) { return response()->json(['message' => $exception->getMessage()], 422); }
        }
        try {
            $crop = $service->applyManualImageCrop($draft, (int) $data['page'], [(float)$data['x0'],(float)$data['y0'],(float)$data['x1'],(float)$data['y1']], $data['target_field'], $data['source_role'], $data['background_mode'] ?? 'white');
            $fresh = $draft->fresh();
            $message = ucfirst($data['target_field']).' crop auto-saved to the repair draft.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message, 'target' => $data['target_field'],
                    'next_target' => $data['next_target'] ?? $data['target_field'],
                    'html' => data_get($fresh->proposed_payload, $data['target_field']), 'crop' => $crop,
                ]);
            }
            return redirect()->route('exam-quality.repairs.show', [
                'draft' => $draft, 'crop_page' => (int) $data['page'],
                'crop_target' => $data['next_target'] ?? $data['target_field'], 'source_role' => $data['source_role'],
            ])->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) return response()->json(['message' => $e->getMessage()], 422);
            return back()->with('error', $e->getMessage());
        }
    }

    public function restoreVersion(QuestionVersion $version, QuestionRepairService $service)
    {
        abort_unless((int) $version->organization_id === (int) Tenant::id(), 404);
        try {
            $service->restoreVersion($version, (int) auth()->id());
            return back()->with('success', 'The selected saved version was restored. The content it replaced was also saved, so this action remains reversible.');
        } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }

    public function updateRepair(Request $request, QuestionRepairDraft $draft)
    {
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        abort_unless(in_array($draft->status, ['ready','needs_review','rejected'], true), 422);
        $data = $request->validate(['proposed' => ['required', 'array']]);
        $proposed = collect($data['proposed'])->only(QuestionRepairService::FIELDS)->map(function ($value, $field) {
            if (in_array($field, ['fill_blank_config','nat_config'], true)) {
                if ($value === null || $value === '') return null;
                $decoded = json_decode((string) $value, true);
                if (! is_array($decoded)) throw \Illuminate\Validation\ValidationException::withMessages(["proposed.$field" => 'Enter valid JSON.']);
                return $decoded;
            }
            return $value;
        })->all();
        $original = (array) $draft->original_payload;
        $changed = collect($proposed)->filter(fn ($value, $field) => json_encode($value) !== json_encode($original[$field] ?? null))->keys()->values()->all();
        $evidence = app(StructuredContentReviewService::class)->reconcileEvidence((array) $draft->evidence, $proposed);
        $evidence['conflicts'] = collect((array) data_get($evidence, 'conflicts', []))->map(function (array $conflict) use ($proposed) {
            $field = (string) ($conflict['field'] ?? '');
            if ($field === '' || ! array_key_exists($field, $proposed)) return $conflict;
            $selected = $proposed[$field];
            $conflict['resolution'] = json_encode($selected) === json_encode($conflict['source_value'] ?? null)
                ? 'source_selected'
                : (json_encode($selected) === json_encode($conflict['academic_value'] ?? null) ? 'academic_selected' : 'custom_selected');
            return $conflict;
        })->all();
        $hasPendingStructuredReview = collect((array) data_get($evidence, 'structured_content', []))
            ->contains(fn ($item) => ($item['status'] ?? null) !== 'reviewed');
        $hasPendingConflict = collect((array) data_get($evidence, 'conflicts', []))
            ->contains(fn ($item) => ($item['resolution'] ?? 'manual_review') === 'manual_review');
        $draft->update([
            'proposed_payload' => $proposed,
            'changed_fields' => $changed,
            'evidence' => $evidence,
            'status' => ($changed === [] || $hasPendingStructuredReview || $hasPendingConflict) ? 'needs_review' : 'ready',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        return back()->with('success', 'Repair draft saved. The live question has not been changed.');
    }

    public function publishRepair(Request $request, QuestionRepairDraft $draft, QuestionRepairService $service)
    {
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        try {
            $service->publish($draft, (int) auth()->id(), $request->boolean('force_publish'));
            return redirect()->route('exam-quality.show', $draft->audit_id)->with('success', 'Repair published, previous question version saved, and related findings resolved.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectRepair(QuestionRepairDraft $draft)
    {
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        $draft->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        return back()->with('success', 'Repair draft rejected. The live question was not changed.');
    }

    public function retryRepair(QuestionRepairDraft $draft)
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        abort_unless((int) $draft->organization_id === (int) Tenant::id(), 404);
        abort_if($draft->status === 'published', 422, 'Published repairs cannot be regenerated.');
        $draft->update(['status' => 'queued', 'failure_message' => null, 'proposed_payload' => null, 'changed_fields' => null, 'evidence' => null]);
        return back()->with('success', 'Repair draft queued for regeneration.');
    }

    public function updateFinding(Request $request, ExamQualityFinding $finding)
    {
        abort_unless((int) $finding->organization_id === (int) Tenant::id(), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'resolved', 'false_positive', 'ignored'])]]);
        $finding->update([
            'status' => $data['status'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Finding status updated.');
    }

    public function preview(Request $request, string $audit, Question $question, QuestionAnswerEvaluator $evaluator)
    {
        $run = ExamQualityAudit::where('public_token', $audit)->firstOrFail();
        abort_unless($run->exam()->whereHas('questions', fn ($q) => $q->where('questions.id', $question->id))->exists(), 404);
        $question->loadMissing('qtype');
        $questionType = $evaluator->questionType($question);
        $fillBlankCount = $evaluator->fillBlankCount($question);

        return view('exam-quality.preview', compact('run', 'question', 'questionType', 'fillBlankCount'));
    }

    public function reviewStructuredContent(Request $request, QuestionRepairDraft $draft, StructuredContentReviewService $service)
    {
        $this->authorizeTenant($draft->audit);
        $data = $request->validate([
            'item_id' => 'nullable|string', 'item_ids' => 'nullable|array', 'item_ids.*' => 'string',
            'decision' => 'required|in:accept,keep_original', 'candidate_html' => 'nullable|string',
        ]);
        $ids = array_values(array_unique(array_filter(array_merge((array) ($data['item_ids'] ?? []), [$data['item_id'] ?? null]))));
        if ($ids === []) return back()->with('error', 'Select at least one structured-content item.');
        try {
            foreach ($ids as $id) $service->reviewRepairItem($draft->fresh(), $id, $data['decision'], count($ids) === 1 ? ($data['candidate_html'] ?? null) : null);
            return back()->with('success', count($ids).' structured-content item(s) reviewed.');
        } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
    }

    public function structuredContentSource(QuestionRepairDraft $draft, string $item, StructuredContentReviewService $service)
    {
        $this->authorizeTenant($draft->audit);
        $record = collect((array) data_get($draft->evidence, 'structured_content', []))->firstWhere('id', $item);
        abort_unless($record, 404);
        $path = $service->sourceVisualPath($record);
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        return response()->file(\Illuminate\Support\Facades\Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    private function ensureRepairCapacity(int $requested): void
    {
        if ($requested < 1 || SaasAccess::isPlatformAdmin()) return;
        $limit = SaasAccess::limit('quality_repairs_monthly');
        if ($limit !== null && SaasAccess::usage('quality_repairs_monthly') + $requested > $limit) {
            abort(403, 'This repair batch would exceed your organization plan monthly repair limit.');
        }
    }
    private function authorizeTenant(ExamQualityAudit $audit): void
    {
        abort_unless((int) $audit->organization_id === (int) Tenant::id(), 404);
    }
}
