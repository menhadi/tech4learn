<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateExamLanguageJob;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Services\ExamDocumentLifecycleService;
use App\Services\ExamTranslationService;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamDocumentController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $this->organizationId();
        $perPage = in_array((int) $request->per_page, [25, 50, 100], true) ? (int) $request->per_page : 25;

        $query = Exam::query()->where('organization_id', $organizationId)
            ->with(['languages', 'packages.groups', 'packages.category', 'packages.subcategory', 'pdfBuilds']);

        if ($request->filled('exam')) $query->whereKey($request->exam);
        if ($request->filled('language')) $query->whereHas('languages', fn ($q) => $q->whereKey($request->language));
        if ($request->filled('package')) $query->whereHas('packages', fn ($q) => $q->whereKey($request->package));
        if ($request->filled('category')) $query->whereHas('packages', fn ($q) => $q->where('category_level_1', $request->category));
        if ($request->filled('subcategory')) $query->whereHas('packages', fn ($q) => $q->where('category_level_2', $request->subcategory));
        if ($request->filled('group')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('groups', fn ($groups) => $groups->whereKey($request->group))
                    ->orWhereHas('packages.groups', fn ($groups) => $groups->whereKey($request->group));
            });
        }

        $exams = $query->displayOrdered()->paginate($perPage)->withQueryString();

        $translationStatusFilter = (string) $request->query('translation_status', '');
        $translationSearch = trim((string) $request->query('translation_search', ''));
        $translationSort = (string) $request->query('translation_sort', 'exam');
        $pdfStatusFilter = (string) $request->query('pdf_status', '');
        $pdfSearch = trim((string) $request->query('pdf_search', ''));
        $pdfDocumentTypeFilter = (string) $request->query('pdf_document_type', '');
        $pdfSort = (string) $request->query('pdf_sort', 'updated_desc');
        $activeTab = in_array((string) $request->query('tab'), ['translation', 'pdf'], true)
            ? (string) $request->query('tab')
            : 'translation';

        $allowedTranslationStatuses = ['pending', 'processing', 'ready', 'failed', 'approved', 'awaiting_approval'];
        if (! in_array($translationStatusFilter, $allowedTranslationStatuses, true)) {
            $translationStatusFilter = '';
        }

        $allowedPdfStatuses = ['not_built', 'queued', 'processing', 'ready', 'failed'];
        if (! in_array($pdfStatusFilter, $allowedPdfStatuses, true)) {
            $pdfStatusFilter = '';
        }

        $allowedPdfDocumentTypes = ['questions', 'solutions'];
        if (! in_array($pdfDocumentTypeFilter, $allowedPdfDocumentTypes, true)) {
            $pdfDocumentTypeFilter = '';
        }

        $explicitTranslationFilter = $request->filled('translation_status') || $request->filled('translation_search');
        $explicitPdfFilter = $request->filled('pdf_status') || $request->filled('pdf_search') || $request->filled('pdf_document_type');

        $translationRows = collect();
        $pdfRows = collect();

        foreach ($exams as $exam) {
            foreach ($exam->languages as $language) {
                $pivot = $language->pivot;
                $isEnglish = strtolower((string) $language->code) === 'en';

                if (! $isEnglish) {
                    $translationStatus = (string) ($pivot?->translation_status ?: 'pending');
                    $translationApproved = (bool) $pivot?->translation_approved_at;
                    $needsTranslation = ! $translationApproved;

                    if (! $explicitTranslationFilter && ! $needsTranslation) {
                        continue;
                    }

                    if ($translationStatusFilter !== '' && ! $this->translationFilterMatch($translationStatusFilter, $translationStatus, $translationApproved)) {
                        continue;
                    }

                    $translationText = strtolower($exam->name.' '.$language->name.' '.$language->code);
                    if ($translationSearch !== '' && stripos($translationText, $translationSearch) === false) {
                        continue;
                    }

                    $translationRows->push([
                        'exam' => $exam,
                        'exam_id' => $exam->id,
                        'exam_name' => $exam->name,
                        'language' => $language,
                        'language_id' => $language->id,
                        'language_name' => $language->name,
                        'language_code' => $language->code,
                        'pivot' => $pivot,
                        'translation_status' => $translationStatus,
                        'translation_approved' => $translationApproved,
                        'needs_translation' => $needsTranslation,
                        'status_sort' => $this->translationStatusOrder($translationStatus),
                        'updated_at' => $pivot?->updated_at,
                        'search_text' => $translationText,
                    ]);
                }

                foreach ($exam->packages as $package) {
                    $types = [];
                    if ($package->show_pdf_download ?? true) {
                        $types[] = ['questions', 'Question paper', 'exam.print.download'];
                    }
                    if ($package->show_solution_pdf_download ?? true) {
                        $types[] = ['solutions', 'Solution', 'exams.solutionPdf'];
                    }

                    foreach ($types as $item) {
                        [$documentType, $documentLabel, $routeName] = $item;
                        if ($pdfDocumentTypeFilter !== '' && $pdfDocumentTypeFilter !== $documentType) {
                            continue;
                        }

                        $build = $exam->pdfBuilds->first(function ($row) use ($package, $language, $documentType) {
                            return (int) $row->package_id === (int) $package->id
                                && (int) $row->language_id === (int) $language->id
                                && $row->document_type === $documentType;
                        });

                        $status = $build?->status ?: 'not_built';
                        if ($status === 'ready' && ! is_file((string) $build?->current_path)) {
                            $status = 'failed';
                        }

                        if (! $explicitPdfFilter && $status === 'ready' && is_file((string) $build?->current_path)) {
                            continue;
                        }

                        if ($pdfStatusFilter !== '' && $pdfStatusFilter !== $status) {
                            continue;
                        }

                        $pdfText = strtolower($exam->name.' '.$language->name.' '.$language->code.' '.$package->name.' '.$documentLabel.' '.$status);
                        if ($pdfSearch !== '' && stripos($pdfText, $pdfSearch) === false) {
                            continue;
                        }

                        $pdfRows->push([
                            'exam' => $exam,
                            'exam_id' => $exam->id,
                            'exam_name' => $exam->name,
                            'language' => $language,
                            'language_id' => $language->id,
                            'language_name' => $language->name,
                            'language_code' => $language->code,
                            'pivot' => $pivot,
                            'package' => $package,
                            'package_id' => $package->id,
                            'package_name' => $package->name,
                            'document_type' => $documentType,
                            'document_label' => $documentLabel,
                            'download_route' => $routeName,
                            'build' => $build,
                            'status' => $status,
                            'status_sort' => $this->pdfStatusOrder($status),
                            'updated_at' => $build?->updated_at,
                            'search_text' => $pdfText,
                        ]);
                    }
                }
            }
        }

        $translationRows = match ($translationSort) {
            'status' => $translationRows->sortBy('status_sort')->values(),
            'status_desc' => $translationRows->sortByDesc('status_sort')->values(),
            'language' => $translationRows->sortBy('language_name')->values(),
            'updated' => $translationRows->sortBy('updated_at')->values(),
            'updated_desc' => $translationRows->sortByDesc('updated_at')->values(),
            default => $translationRows->sortBy('exam_name')->values(),
        };

        $pdfRows = match ($pdfSort) {
            'status' => $pdfRows->sortBy('status_sort')->values(),
            'status_desc' => $pdfRows->sortByDesc('status_sort')->values(),
            'package' => $pdfRows->sortBy('package_name')->values(),
            'document' => $pdfRows->sortBy('document_type')->values(),
            'updated' => $pdfRows->sortBy('updated_at')->values(),
            'updated_desc' => $pdfRows->sortByDesc('updated_at')->values(),
            default => $pdfRows->sortByDesc('updated_at')->values(),
        };

        return view('exam-documents.index', [
            'exams' => $exams,
            'translationRows' => $translationRows,
            'pdfRows' => $pdfRows,
            'activeTab' => $activeTab,
            'translationStatusFilter' => $translationStatusFilter,
            'translationSearch' => $translationSearch,
            'translationSort' => $translationSort,
            'pdfStatusFilter' => $pdfStatusFilter,
            'pdfSearch' => $pdfSearch,
            'pdfDocumentTypeFilter' => $pdfDocumentTypeFilter,
            'pdfSort' => $pdfSort,
            'groups' => Group::where('organization_id', $organizationId)->orderBy('group_name')->get(),
            'categories' => Category::where('organization_id', $organizationId)->parents()->displayOrdered()->get(),
            'subcategories' => Category::where('organization_id', $organizationId)->children()->displayOrdered()->get(),
            'packages' => Package::where('organization_id', $organizationId)->displayOrdered()->get(),
            'languages' => Language::enabledForOrganization($organizationId)->orderBy('name')->get(),
            'examOptions' => Exam::where('organization_id', $organizationId)->displayOrdered()->get(['id', 'name']),
        ]);
    }

    private function translationFilterMatch(string $statusFilter, string $status, bool $approved): bool
    {
        if ($statusFilter === '') {
            return true;
        }

        if ($statusFilter === 'approved') {
            return $approved && $status === 'ready';
        }

        if ($statusFilter === 'awaiting_approval') {
            return $status === 'ready' && ! $approved;
        }

        return $status === $statusFilter;
    }

    private function translationStatusOrder(string $status): int
    {
        return match ($status) {
            'pending' => 1,
            'processing' => 2,
            'ready' => 4,
            'failed' => 5,
            default => 3,
        };
    }

    private function pdfStatusOrder(string $status): int
    {
        return match ($status) {
            'not_built' => 1,
            'queued' => 2,
            'processing' => 3,
            'ready' => 5,
            'failed' => 4,
            default => 6,
        };
    }

    public function translate(Exam $exam, Language $language)
    {
        $this->authorizeExam($exam);
        $exam->languages()->syncWithoutDetaching([$language->id => [
            'translation_status' => 'pending',
            'translation_approved_at' => null,
            'translation_approved_by' => null,
            'last_error' => null,
        ]]);
        TranslateExamLanguageJob::dispatch($exam->id, $language->id);

        return back()->with('success', 'Translation queued.');
    }

    public function approve(Exam $exam, Language $language, ExamTranslationService $translations, ExamDocumentLifecycleService $documents)
    {
        $this->authorizeExam($exam);
        $progress = $translations->progress($exam, $language);
        abort_unless($progress['remaining'] === 0 && $progress['exam_content_ready'], 422, 'Translation is incomplete or stale.');
        $exam->languages()->updateExistingPivot($language->id, [
            'translation_status' => 'ready',
            'translation_approved_at' => now(),
            'translation_approved_by' => Auth::id(),
        ]);
        $this->queueAutomaticPdfs($exam, $language, $documents);

        return back()->with('success', 'Translation approved.');
    }

    public function generate(Request $request, Exam $exam, Language $language, ExamDocumentLifecycleService $documents)
    {
        $this->authorizeExam($exam);
        $data = $request->validate(['package_id' => 'required|integer', 'document_type' => 'required|in:questions,solutions']);
        $package = $exam->packages()->whereKey($data['package_id'])->firstOrFail();
        if (strtolower((string) $language->code) !== 'en') {
            $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
            abort_unless($pivot?->translation_status === 'ready' && $pivot?->translation_approved_at, 422, 'Approve the current translation first.');
        }
        $documents->queue($exam, $package, $language, $data['document_type'], Auth::id());

        return back()->with('success', ucfirst($data['document_type']).' PDF queued.');
    }

    public function automation(Request $request, Exam $exam, Language $language, ExamDocumentLifecycleService $documents)
    {
        $this->authorizeExam($exam);
        $data = $request->validate(['auto_translate' => 'nullable|boolean', 'auto_pdf' => 'nullable|boolean']);
        $exam->languages()->syncWithoutDetaching([$language->id => [
            'auto_translate' => (bool) ($data['auto_translate'] ?? false),
            'auto_pdf' => (bool) ($data['auto_pdf'] ?? false),
        ]]);
        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
        if ($pivot?->auto_translate && $pivot?->translation_status !== 'ready') {
            TranslateExamLanguageJob::dispatch($exam->id, $language->id);
        } elseif ($pivot?->auto_pdf && $pivot?->translation_approved_at) {
            $this->queueAutomaticPdfs($exam, $language, $documents);
        }

        return back()->with('success', 'Automation updated.');
    }

    private function queueAutomaticPdfs(Exam $exam, Language $language, ExamDocumentLifecycleService $documents): void
    {
        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
        if (! $pivot?->auto_pdf) return;

        foreach ($exam->packages as $package) {
            if ($package->show_pdf_download ?? true) $documents->queue($exam, $package, $language, 'questions', Auth::id());
            if ($package->show_solution_pdf_download ?? true) $documents->queue($exam, $package, $language, 'solutions', Auth::id());
        }
    }

    private function organizationId(): int
    {
        return (int) (SaasAccess::organization()?->id ?: Auth::user()?->organization_id ?: 1);
    }

    private function authorizeExam(Exam $exam): void
    {
        abort_unless((int) $exam->organization_id === $this->organizationId(), 403);
    }
}

