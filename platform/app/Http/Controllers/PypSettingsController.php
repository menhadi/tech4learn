<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PypPackageSetting;
use App\Services\PypContentService;
use App\Support\Tenant;
use Illuminate\Http\Request;

class PypSettingsController extends Controller
{
    public function index(PypContentService $pyp)
    {
        $tenantId = Tenant::id();
        $packages = Package::query()
            ->where('status', 1)
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->whereHas('exams', fn ($query) => $query->where('exams.status', 'Active')->where(function ($scope) {
                $scope->where('test_type', 'previous_year')
                    ->orWhere('category_level_1', 'previous_year_papers')
                    ->orWhere('category_level_2', 'year_wise');
            }))
            ->withCount(['exams as pyp_exams_count' => fn ($query) => $query->where('exams.status', 'Active')->where(function ($scope) {
                $scope->where('test_type', 'previous_year')
                    ->orWhere('category_level_1', 'previous_year_papers')
                    ->orWhere('category_level_2', 'year_wise');
            })])
            ->with('groups:id,group_name')
            ->orderBy('name')
            ->get();
        $packageSettings = PypPackageSetting::query()
            ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
            ->whereIn('package_id', $packages->pluck('id'))
            ->get()
            ->keyBy('package_id');
        $settings = $pyp->settings();

        return view('admin.pyp-settings', compact('packages', 'packageSettings', 'settings'));
    }

    public function update(Request $request, PypContentService $pyp)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'subject_pages' => ['nullable', 'boolean'],
            'topic_pages' => ['nullable', 'boolean'],
            'subtopic_pages' => ['nullable', 'boolean'],
            'analysis_enabled' => ['nullable', 'boolean'],
            'min_questions' => ['required', 'integer', 'min:1', 'max:500'],
            'min_years_for_historical' => ['required', 'integer', 'min:1', 'max:20'],
            'cache_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'sample_questions' => ['required', 'integer', 'min:1', 'max:20'],
            'packages' => ['nullable', 'array'],
            'packages.*.enabled' => ['nullable', 'boolean'],
            'packages.*.indexable' => ['nullable', 'boolean'],
            'packages.*.analysis_mode' => ['required', 'in:historical,content_only,disabled'],
            'packages.*.meta_title' => ['nullable', 'string', 'max:255'],
            'packages.*.meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $configuration = getConfiguration();
        $old = $pyp->settings();
        $configuration->update(['pyp_settings' => [
            'enabled' => $request->boolean('enabled'),
            'subject_pages' => $request->boolean('subject_pages'),
            'topic_pages' => $request->boolean('topic_pages'),
            'subtopic_pages' => $request->boolean('subtopic_pages'),
            'analysis_enabled' => $request->boolean('analysis_enabled'),
            'min_questions' => (int) $validated['min_questions'],
            'min_years_for_historical' => (int) $validated['min_years_for_historical'],
            'cache_minutes' => (int) $validated['cache_minutes'],
            'sample_questions' => (int) $validated['sample_questions'],
            'cache_version' => ((int) ($old['cache_version'] ?? 1)) + 1,
        ]]);

        $tenantId = Tenant::id();
        foreach ($validated['packages'] ?? [] as $packageId => $values) {
            $package = Package::query()
                ->when($tenantId, fn ($query, $id) => $query->where('organization_id', $id))
                ->findOrFail($packageId);
            PypPackageSetting::updateOrCreate(
                ['organization_id' => $package->organization_id, 'package_id' => $package->id],
                [
                    'enabled' => filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'indexable' => filter_var($values['indexable'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'analysis_mode' => $values['analysis_mode'],
                    'meta_title' => $values['meta_title'] ?? null,
                    'meta_description' => $values['meta_description'] ?? null,
                ]
            );
        }

        return back()->with('success', 'PYP pages and analysis settings saved.');
    }
}
