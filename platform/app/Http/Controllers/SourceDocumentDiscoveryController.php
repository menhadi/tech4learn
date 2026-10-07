<?php

namespace App\Http\Controllers;

use App\Models\{Category, Diff, Exam, ExamQualitySource, Group, Language, Package, SourceExamImport};
use App\Services\ExamQualitySourceStorage;
use App\Services\SourceDocumentDiscoveryService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SourceDocumentDiscoveryController extends Controller
{
    public function index()
    {
        return view('source-exams.discover', array_merge($this->formData(), [
            'pageUrl' => '',
            'sourceInputs' => [],
            'rows' => [],
        ]));
    }

    public function scan(Request $request, SourceDocumentDiscoveryService $discovery)
    {
        $data = $request->validate([
            "question_source_url" => ["nullable", "url:http,https", "max:2048", "required_without:combined_source_url"],
            "answer_source_url" => ["nullable", "url:http,https", "max:2048"],
            "combined_source_url" => ["nullable", "url:http,https", "max:2048"],
            "question_pattern" => ["nullable", "string", "max:200"],
            "answer_pattern" => ["nullable", "string", "max:200"],
            "combined_pattern" => ["nullable", "string", "max:200"],
            "question_heading" => ["nullable", "string", "max:200"],
            "answer_heading" => ["nullable", "string", "max:200"],
            "combined_heading" => ["nullable", "string", "max:200"],
        ]);
        try {
            $rows = $discovery->discoverStructured([
                "question" => ["url" => $data["question_source_url"] ?? "", "pattern" => $data["question_pattern"] ?? "", "heading" => $data["question_heading"] ?? ""],
                "answer" => ["url" => $data["answer_source_url"] ?? "", "pattern" => $data["answer_pattern"] ?? "", "heading" => $data["answer_heading"] ?? ""],
                "combined" => ["url" => $data["combined_source_url"] ?? "", "pattern" => $data["combined_pattern"] ?? "", "heading" => $data["combined_heading"] ?? ""],
            ]);
        } catch (\Throwable $exception) {
            return back()->withInput()->with("error", "Link discovery failed: ".$exception->getMessage());
        }
        return view("source-exams.discover", array_merge($this->formData(), [
            "pageUrl" => $data["question_source_url"] ?? $data["combined_source_url"] ?? "", "sourceInputs" => $data, "rows" => $rows,
        ]));
    }
    public function scanLocal(Request $request, SourceDocumentDiscoveryService $discovery)
    {
        $request->validate([
            "question_pdfs" => ["nullable","array","max:200","required_without:combined_pdfs"],
            "question_pdfs.*" => ["file","mimes:pdf","max:102400"], "answer_pdfs" => ["nullable","array","max:200"],
            "answer_pdfs.*" => ["file","mimes:pdf","max:102400"], "combined_pdfs" => ["nullable","array","max:200"],
            "combined_pdfs.*" => ["file","mimes:pdf","max:102400"],
            "question_local_pattern" => ["nullable","string","max:200"], "answer_local_pattern" => ["nullable","string","max:200"],
            "combined_local_pattern" => ["nullable","string","max:200"],
        ]);
        $tenant=(int) Tenant::id(); $batch=(string) Str::uuid(); $items=[];
        foreach (["question","answer","combined"] as $role) {
            $items[$role] = ["pattern" => $request->input($role."_local_pattern", ""), "files" => []];
            foreach ($request->file($role."_pdfs",[]) as $position=>$file) {
                $name=$file->getClientOriginalName();
                $path=$file->storeAs("tmp/source-document-discovery/{$tenant}/{$batch}/{$role}", ($position+1)."-".Str::slug(pathinfo($name,PATHINFO_FILENAME)).".pdf", "local");
                $items[$role]["files"][]=["name"=>$name,"token"=>$path];
            }
        }
        try { $rows = $discovery->discoverLocalStructured($items); }
        catch (\Throwable $exception) { return back()->withInput()->with("error", "Local PDF discovery failed: ".$exception->getMessage()); }
        return view("source-exams.discover", array_merge($this->formData(), ["pageUrl"=>"", "sourceInputs"=>[], "rows"=>$rows]));
    }
    public function store(Request $request, ExamQualitySourceStorage $sourceStorage)
    {
        $data = $request->validate([
            'page_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*.selected' => ['nullable', 'boolean'],
            'rows.*.exam_name' => ['nullable', 'string', 'max:255'],
            'rows.*.year' => ['nullable', 'string', 'max:10'],
            'rows.*.question_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rows.*.answer_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rows.*.combined_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rows.*.question_local' => ['nullable', 'string', 'max:1024'],
            'rows.*.answer_local' => ['nullable', 'string', 'max:1024'],
            'rows.*.combined_local' => ['nullable', 'string', 'max:1024'],
            'rows.*.group_id' => ['nullable', 'integer'],
            'rows.*.category_id' => ['nullable', 'integer'],
            'rows.*.new_category' => ['nullable', 'string', 'max:200'],
            'publish_pdfs' => ['nullable', 'boolean'],
            'rows.*.subcategory_id' => ['nullable', 'integer'],
            'rows.*.package_id' => ['nullable', 'integer'],
            'default_group_id' => ['required', 'integer'],
            'rows.*.question_label' => ['nullable', 'string', 'max:255'],
            'rows.*.answer_label' => ['nullable', 'string', 'max:255'],
            'rows.*.combined_label' => ['nullable', 'string', 'max:255'],
            'default_category_id' => ['nullable', 'integer'],
            'default_subcategory_id' => ['nullable', 'integer'],
            'default_package_id' => ['nullable', 'integer'],
            'language_id' => ['required', 'integer'],
            'duration' => ['required', 'integer', 'min:0', 'max:1440'],
            'attempt_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'passing_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'marks' => ['required', 'numeric', 'min:0'],
            'negative_marks' => ['required', 'numeric', 'min:0'],
        ]);

        $selected = collect($data['rows'])->filter(fn (array $row) => ! empty($row['selected']))->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'Select at least one discovered paper.']);
        }

        $tenant = (int) Tenant::id();
        $ownedGroups = Group::where('organization_id', $tenant)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedCategories = Category::where('organization_id', $tenant)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ownedPackages = Package::where('organization_id', $tenant)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $languageId = (int) Language::enabledForOrganization($tenant)->whereKey($data['language_id'])->value('id');
        // Difficulty is a question-level extraction fallback, not an exam field.
        $difficultyId = (int) Diff::orderBy('id')->value('id');
        if (! $languageId || ! $difficultyId) {
            throw ValidationException::withMessages(['language_id' => 'Select a valid language.']);
        }

        if (! empty($data['publish_pdfs'])) abort_unless(user_can_route_action('exams.edit', 'edit'), 403);
        if ($selected->contains(fn ($row) => ! empty($row['new_category']))) abort_unless(user_can_route_action('category.create', 'add'), 403);

        $created = 0;
        $failures = [];
        foreach ($selected as $position => $row) {
            $name = trim((string) ($row['exam_name'] ?? ''));
            $questionUrl = trim((string) ($row['question_url'] ?? ''));
            $answerUrl = trim((string) ($row['answer_url'] ?? ''));
            $combinedUrl = trim((string) ($row['combined_url'] ?? ''));
            $questionLocal = trim((string) ($row['question_local'] ?? ''));
            $answerLocal = trim((string) ($row['answer_local'] ?? ''));
            $combinedLocal = trim((string) ($row['combined_local'] ?? ''));
            $questionLabel = trim((string) ($row['question_label'] ?? ''));
            $answerLabel = trim((string) ($row['answer_label'] ?? ''));
            $combinedLabel = trim((string) ($row['combined_label'] ?? ''));
            if ($name === '' || ($questionUrl === '' && $combinedUrl === '' && $questionLocal === '' && $combinedLocal === '')) {
                $failures[] = 'Row '.($position + 1).' needs an exam name and a question or combined PDF URL.';
                continue;
            }

            $groupId = (int) (($row['group_id'] ?? null) ?: $data['default_group_id']);
            $categoryId = (int) (($row['category_id'] ?? null) ?: ($data['default_category_id'] ?? 0)) ?: null;
            $subcategoryId = (int) (($row['subcategory_id'] ?? null) ?: ($data['default_subcategory_id'] ?? 0)) ?: null;
            $packageId = (int) (($row['package_id'] ?? null) ?: ($data['default_package_id'] ?? 0)) ?: null;
            if (! in_array($groupId, $ownedGroups, true)
                || ($categoryId && ! in_array($categoryId, $ownedCategories, true))
                || ($subcategoryId && ! in_array($subcategoryId, $ownedCategories, true))
                || ($packageId && ! in_array($packageId, $ownedPackages, true))) {
                $failures[] = $name.': a classification does not belong to this organization.';
                continue;
            }

            $exam = null;
            $storedSources = [];
            try {
                \Illuminate\Support\Facades\DB::beginTransaction();
                SaasAccess::abortIfLimitReached('exams');
                if (! empty($row['new_category'])) {
                    if ($packageId) throw new \RuntimeException('Category is inherited from the selected package. Edit its category or use a standalone paper.');
                    $categoryId = app(\App\Services\ExamPdfPublicationService::class)->category($tenant, $row['new_category'], [$groupId])->id;
                    $subcategoryId = null;
                }
                $exam = $this->createExam($name, $tenant, $categoryId, $subcategoryId, $data);
                $scope = app(\App\Services\ExamScopeService::class)->resolve(
                    $tenant,
                    $packageId ? [$packageId] : [],
                    [$groupId],
                    $categoryId,
                    $subcategoryId,
                );
                app(\App\Services\ExamScopeService::class)->sync($exam, $scope);

                $sourceInputs = [
                    'questions' => ['url' => $questionUrl, 'local' => $questionLocal, 'label' => $questionLabel],
                    'answers' => ['url' => $answerUrl, 'local' => $answerLocal, 'label' => $answerLabel],
                    'combined' => ['url' => $combinedUrl, 'local' => $combinedLocal, 'label' => $combinedLabel],
                ];
                foreach ($sourceInputs as $role => $input) {
                    if ($input['url'] === '' && $input['local'] === '') {
                        continue;
                    }
                    $stored = $input['local'] !== ''
                        ? $sourceStorage->storeStaged($input['local'], $input['label'] ?: basename($input['local']), $tenant, $exam->id, $role)
                        : $sourceStorage->storeUrl($input['url'], $tenant, $exam->id, $role, $input['label']);
                    $storedSources[$role] = ExamQualitySource::create(array_merge($stored, [
                        'organization_id' => $tenant,
                        'exam_id' => $exam->id,
                        'role' => $role,
                        'kind' => 'file',
                        'source_url' => $input['url'] !== '' ? $input['url'] : null,
                        'is_active' => true,
                    ]));
                }

                $questionSource = $storedSources['questions'] ?? $storedSources['combined'] ?? null;
                if (! $questionSource) {
                    throw new \RuntimeException('No usable question PDF was saved.');
                }
                $answerSource = $storedSources['answers'] ?? $storedSources['combined'] ?? null;
                SourceExamImport::create([
                    'organization_id' => $tenant,
                    'created_by' => auth()->id(),
                    'exam_id' => $exam->id,
                    'name' => $name,
                    'status' => 'draft',
                    'question_source_name' => $questionSource->label,
                    'question_source_path' => $questionSource->file_path,
                    'question_source_type' => 'pdf',
                    'answer_source_name' => $answerSource?->label,
                    'answer_source_path' => $answerSource?->file_path,
                    'answer_source_type' => $answerSource ? 'pdf' : null,
                    'solution_source_name' => $storedSources['combined']?->label ?? null,
                    'solution_source_path' => $storedSources['combined']?->file_path ?? null,
                    'solution_source_type' => isset($storedSources['combined']) ? 'pdf' : null,
                    'settings' => $this->settings($data, $groupId, $packageId, $categoryId, $subcategoryId, $languageId, $difficultyId),
                ]);
                if (! empty($data['publish_pdfs'])) app(\App\Services\ExamPdfPublicationService::class)->publish($tenant, [$exam->id]);
                \Illuminate\Support\Facades\DB::commit();
                $created++;
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\DB::rollBack();
                foreach ($storedSources as $source) {
                    try {
                        $sourceStorage->delete($source);
                        $source->delete();
                    } catch (\Throwable) {
                    }
                }
                if ($exam) {
                    $exam->delete();
                }
                $failures[] = $name.': '.$exception->getMessage();
            }
        }

        $message = "{$created} exam draft(s) created in Create Exam from Source. Select them there and click Process selected drafts when ready.";
        if (! empty($data['publish_pdfs'])) $message = "{$created} paper(s) published for PDF download. Extraction drafts remain available for later processing.";
        if ($failures) {
            $message .= ' '.count($failures).' row(s) failed: '.implode(' | ', array_slice($failures, 0, 5));
        }
        return redirect()->route('source-exams.index')->with($created ? 'success' : 'error', $message);
    }

    private function formData(): array
    {
        $tenant = (int) Tenant::id();
        $categories = Category::where('organization_id', $tenant)
            ->whereNull('parent_id')->where('status', 1)->with('groups:id')->orderBy('title')->get();
        $subcategories = Category::where('organization_id', $tenant)
            ->whereNotNull('parent_id')->where('status', 1)->orderBy('title')->get();
        $packages = Package::where('organization_id', $tenant)
            ->with('groups:id')->orderBy('name')->get();

        return [
            'groups' => Group::where('organization_id', $tenant)->orderBy('group_name')->get(),
            'categories' => $categories,
            'subcategories' => $subcategories,
            'packages' => $packages,
            'languages' => Language::enabledForOrganization($tenant)->orderBy('name')->get(),
            'defaultHierarchy' => [
                'categories' => $categories->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'title' => $category->title,
                    'group_ids' => $category->groups->pluck('id')->map(fn ($id) => (int) $id)->values(),
                ])->values(),
                'subcategories' => $subcategories->map(fn (Category $subcategory) => [
                    'id' => (int) $subcategory->id,
                    'title' => $subcategory->title,
                    'category_id' => (int) $subcategory->parent_id,
                ])->values(),
                'packages' => $packages->map(fn (Package $package) => [
                    'id' => (int) $package->id,
                    'name' => $package->name,
                    'category_id' => $package->category_level_1 ? (int) $package->category_level_1 : null,
                    'subcategory_id' => $package->category_level_2 ? (int) $package->category_level_2 : null,
                    'group_ids' => $package->groups->pluck('id')->map(fn ($id) => (int) $id)->values(),
                ])->values(),
            ],
        ];
    }

    private function createExam(string $name, int $tenant, ?int $categoryId, ?int $subcategoryId, array $defaults): Exam
    {
        return Exam::create([
            'organization_id' => $tenant,
            'name' => $name,
            'test_type' => Exam::TEST_TYPE_PREVIOUS_YEAR,
            'slug' => $this->uniqueSlug($name, $tenant),
            'passing_percentage' => $defaults['passing_percentage'],
            'duration' => $defaults['duration'],
            'attempt_count' => $defaults['attempt_count'],
            'start_date' => now(),
            'end_date' => now()->addYears(5),
            'browser_tolerance' => false,
            'random_question' => false,
            'result_after_finish' => true,
            'option_shuffle' => false,
            'allow_answer_change' => true,
            'grouping_mode' => 'subject',
            'timer_mode' => 'none',
            'is_subject_timer' => false,
            'proctor' => false,
            'calculator_allowed' => false,
            'negative_marking' => (float) $defaults['negative_marks'] > 0,
            'tolerance_count' => 0,
            'status' => 'Inactive',
            'category_level_1' => $categoryId,
            'category_level_2' => $subcategoryId,
        ]);
    }

    private function settings(array $data, int $groupId, ?int $packageId, ?int $categoryId, ?int $subcategoryId, int $languageId, int $difficultyId): array
    {
        return [
            'source_page_url' => $data['page_url'] ?? null,
            'group_ids' => [$groupId],
            'package_ids' => $packageId ? [$packageId] : [],
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'subject_id' => null,
            'qtype_id' => null,
            'diff_id' => $difficultyId,
            'language_id' => $languageId,
            'duration' => (int) $data['duration'],
            'attempt_count' => (int) $data['attempt_count'],
            'passing_percentage' => (float) $data['passing_percentage'],
            'marks' => (float) $data['marks'],
            'negative_marks' => (float) $data['negative_marks'],
            'extractor_script' => \App\Support\SourceExtractorRegistry::BUILTIN,
            'profile' => ['document_type' => 'auto', 'layout' => 'auto', 'reading_order' => 'auto', 'question_numbering' => 'auto'],
        ];
    }

    private function uniqueSlug(string $name, int $tenant): string
    {
        $base = Str::slug($name) ?: 'exam';
        $slug = $base;
        $suffix = 2;
        while (Exam::where('organization_id', $tenant)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
