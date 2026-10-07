<?php

namespace App\Http\Controllers;

use App\Exports\ExamWorkbookExport;
use App\Models\Category;
use App\Models\Exam;
use App\Models\ExamQualitySource;
use App\Models\Group;
use App\Models\Package;
use App\Services\ExamQualitySourceStorage;
use App\Services\ExamWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ExamImportExportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = (int) \App\Support\Tenant::id();

        return view('exams.import', $this->filterData($tenantId, $request));
    }

    public function export(Request $request, ExamWorkbookService $service)
    {
        $filters = $request->validate([
            'group_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'subcategory_id' => 'nullable|integer',
            'package_id' => 'nullable|integer',
            'search' => 'nullable|string|max:150',
        ]);
        $tenantId = (int) \App\Support\Tenant::id();
        $name = 'exams-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new ExamWorkbookExport($service, $tenantId, $filters), $name, ExcelWriter::XLSX);
    }

    public function preview(Request $request, ExamWorkbookService $service)
    {
        $validated = $request->validate(['workbook' => 'required|file|mimes:xlsx,xls,csv|max:20480']);
        $tenantId = (int) \App\Support\Tenant::id();
        $token = (string) Str::uuid();
        $extension = strtolower($validated['workbook']->getClientOriginalExtension() ?: 'xlsx');
        $path = $validated['workbook']->storeAs("exam-imports/{$tenantId}", "{$token}.{$extension}", 'local');
        try {
            $preview = $service->preview(Storage::disk('local')->path($path), $tenantId);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['workbook' => 'The workbook could not be read: '.$e->getMessage()]);
        }
        session()->put("exam_imports.{$token}", ['path' => $path, 'tenant_id' => $tenantId, 'expires_at' => now()->addHour()->timestamp]);

        return view('exams.import', array_merge($this->filterData($tenantId), compact('preview', 'token')));
    }

    public function apply(Request $request, ExamWorkbookService $service)
    {
        $request->validate(['token' => 'required|uuid']);
        $token = (string) $request->input('token');
        $entry = session()->pull("exam_imports.{$token}");
        $tenantId = (int) \App\Support\Tenant::id();
        if (! $entry || (int) ($entry['tenant_id'] ?? 0) !== $tenantId || (int) ($entry['expires_at'] ?? 0) < now()->timestamp) {
            return redirect()->route('exams.import.index')->with('error', 'The preview expired. Please upload the workbook again.');
        }
        $path = (string) $entry['path'];
        if (! Storage::disk('local')->exists($path)) {
            return redirect()->route('exams.import.index')->with('error', 'The uploaded workbook is no longer available.');
        }

        try {
            $summary = $service->apply(Storage::disk('local')->path($path), $tenantId);
        } finally {
            Storage::disk('local')->delete($path);
        }
        $processed = $summary['created'] + $summary['updated'] + $summary['failed'];
        $message = "Import complete: {$processed} rows processed; {$summary['created']} created, "
            ."{$summary['updated']} updated, {$summary['failed']} failed. PDFs attached/replaced: "
            ."{$summary['question_pdfs']} question, {$summary['answer_pdfs']} answer, "
            ."{$summary['combined_pdfs']} combined; {$summary['pdf_removals']} removed.";

        return redirect()->route('exams.import.index')
            ->with($summary['failed'] ? 'warning' : 'success', $message)
            ->with('import_errors', array_slice($summary['errors'], 0, 100))
            ->with('exam_import_summary', $summary);
    }

    public function downloadSource(Exam $exam, ExamQualitySource $source, ExamQualitySourceStorage $storage)
    {
        $tenantId = (int) \App\Support\Tenant::id();
        abort_unless((int) $exam->organization_id === $tenantId && (int) $source->organization_id === $tenantId && (int) $source->exam_id === (int) $exam->id, 404);
        if ($source->kind === 'url') return redirect()->away($source->source_url);
        $disk = $storage->diskFor($source);
        abort_unless($source->file_path && Storage::disk($disk)->exists($source->file_path), 404);

        return Storage::disk($disk)->response(
            $source->file_path,
            $source->label ?: basename($source->file_path),
            ['Content-Type' => 'application/pdf'],
            'inline'
        );
    }

    private function filterData(int $tenantId, ?Request $request = null): array
    {
        $groupId = $request?->integer('group_id') ?: null;
        $categoryId = $request?->integer('category_id') ?: null;
        $subcategoryId = $request?->integer('subcategory_id') ?: null;

        return [
            'exportGroups' => Group::where('organization_id', $tenantId)->orderBy('group_name')->get(['id', 'group_name']),
            'exportCategories' => Category::where('organization_id', $tenantId)
                ->parents()
                ->when($groupId, fn ($query) => $query->whereHas('groups', fn ($groups) => $groups->whereKey($groupId)))
                ->orderBy('title')
                ->get(['id', 'title']),
            'exportSubcategories' => Category::where('organization_id', $tenantId)
                ->children()
                ->when($groupId, fn ($query) => $query->whereHas('parent.groups', fn ($groups) => $groups->whereKey($groupId)))
                ->when($categoryId, fn ($query) => $query->where('parent_id', $categoryId))
                ->orderBy('title')
                ->get(['id', 'title', 'parent_id']),
            'exportPackages' => Package::where('organization_id', $tenantId)
                ->when($groupId, fn ($query) => $query->whereHas('groups', fn ($groups) => $groups->whereKey($groupId)))
                ->when($categoryId, fn ($query) => $query->where('category_level_1', $categoryId))
                ->when($subcategoryId, fn ($query) => $query->where('category_level_2', $subcategoryId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
