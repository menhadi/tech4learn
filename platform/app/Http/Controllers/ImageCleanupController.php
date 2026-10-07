<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Configuration;
use App\Models\Exam;
use App\Models\Group;
use App\Models\ImageCleanupItem;
use App\Models\ImageCleanupRun;
use App\Models\Package;
use App\Models\Question;
use App\Services\ImageCleanupProcessLauncher;
use App\Services\ImageCleanupService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ImageCleanupController extends Controller
{
    public function index()
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $tenantId = Tenant::id();
        $groups = Group::where('organization_id', $tenantId)->displayOrdered()->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'parent_id', 'title']);
        $packages = Package::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'name', 'category_level_1', 'category_level_2']);
        $categoryById = $categories->keyBy('id');
        $categoryOptions = $categories->map(function ($category) use ($categoryById) {
            $groups = $category->groups->pluck('id');
            if ($groups->isEmpty() && $category->parent_id) $groups = $categoryById->get($category->parent_id)?->groups?->pluck('id') ?? collect();
            return ['id' => (int) $category->id, 'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'title' => $category->title, 'group_ids' => $groups->map(fn ($id) => (int) $id)->values()];
        })->values();
        $packageOptions = $packages->map(function ($package) use ($categoryById) {
            $groupIds = $package->groups->pluck('id')
                ->merge($categoryById->get($package->category_level_1)?->groups?->pluck('id') ?? collect())
                ->merge($categoryById->get($package->category_level_2)?->groups?->pluck('id') ?? collect());
            return ['id' => (int) $package->id, 'name' => $package->name,
                'category_id' => $package->category_level_1 ? (int) $package->category_level_1 : null,
                'subcategory_id' => $package->category_level_2 ? (int) $package->category_level_2 : null,
                'group_ids' => $groupIds->unique()->map(fn ($id) => (int) $id)->values()];
        })->values();
        $configuration = Configuration::where('organization_id', $tenantId)->first();
        $runs = ImageCleanupRun::where('organization_id', $tenantId)->with(['exam:id,name', 'requester:id,name'])
            ->withCount(['items as publishable_items_count' => fn ($items) => $items->where('status', 'ready')])
            ->latest()->simplePaginate(20)->withQueryString();
        return view('image-cleanup.index', compact('groups', 'categoryOptions', 'packageOptions', 'configuration', 'runs'));
    }

    public function examSearch(Request $request)
    {
        $tenantId = Tenant::id();
        $data = $this->validateFilters($request, $tenantId);
        $query = $this->filteredExamQuery($tenantId, $data);
        $total = (clone $query)->count();
        $results = $query->withCount('questions')->latest('id')->limit(30)->get(['id', 'name'])->map(fn ($exam) => [
            'id' => (int) $exam->id, 'text' => $exam->name, 'question_count' => (int) $exam->questions_count,
        ]);
        return response()->json(['results' => $results, 'total' => $total]);
    }

    public function selectionSummary(Request $request, ImageCleanupService $service)
    {
        $tenantId = Tenant::id();
        $data = $this->validateFilters($request, $tenantId);
        $exams = $this->filteredExamQuery($tenantId, $data)->get(['id', 'name']);
        $imageCount = $exams->sum(fn (Exam $exam) => $service->discover($exam)->count());

        return response()->json(['paper_count' => $exams->count(), 'image_count' => $imageCount]);
    }

    public function images(Exam $exam, ImageCleanupService $service)
    {
        $this->authorizeTenant($exam);
        return response()->json(['images' => $service->discover($exam)]);
    }

    public function store(Request $request, ImageCleanupService $service, ImageCleanupProcessLauncher $launcher)
    {
        SaasAccess::abortIfFeatureDisabled('exam_quality_ai');
        $tenantId = Tenant::id();
        $data = $request->validate([
            'selection_mode' => ['required', Rule::in(['selected_images', 'all_images', 'all_filtered'])],
            'exam_id' => ['nullable', 'required_if:selection_mode,selected_images', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'exam_ids' => ['nullable', 'required_if:selection_mode,all_images', 'array', 'min:1', 'max:30'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'selected_images' => ['nullable', 'required_if:selection_mode,selected_images', 'array', 'min:1', 'max:500'],
            'selected_images.*' => ['required', 'string', 'distinct', 'max:255'],
            'action' => ['required', Rule::in(['remove_image', 'local_cleanup', 'remove_watermark', 'clean_enhance', 'redraw'])],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'filter_q' => ['nullable', 'string', 'max:120'],
            'filter_group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'filter_category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'filter_package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)],
        ]);
        $configuration = Configuration::where('organization_id', $tenantId)->first();
        if (! $configuration?->image_cleanup_enabled) return back()->withInput()->with('error', 'Enable Image Cleanup Bot in Admin AI Settings first.');
        $local = in_array($data['action'], ['remove_image', 'local_cleanup'], true);
        $removeOnly = $data['action'] === 'remove_image';
        $provider = $local ? 'local' : (string) ($configuration->image_cleanup_provider ?: 'openai');
        $model = $removeOnly ? 'local-remove-image' : ($local ? 'local-gd-cleanup' : ($provider === 'google'
            ? (string) ($configuration->image_cleanup_google_model ?: 'gemini-3.1-flash-image')
            : (string) ($configuration->image_cleanup_openai_model ?: 'gpt-image-2')));
        if (! $local && $provider === 'openai' && blank($configuration->openai_api_key)) return back()->withInput()->with('error', 'Configure the OpenAI API key in Admin AI Settings.');
        if (! $local && $provider === 'google' && blank($configuration->google_gemini_api_key)) return back()->withInput()->with('error', 'Configure the Gemini API key in Admin AI Settings.');
        $needsGd = ! $removeOnly && ($local || (bool) $configuration->image_cleanup_branding_enabled);
        if ($needsGd && ! function_exists('imagecreatefromstring')) return back()->withInput()->with('error', 'This cleanup/branding selection requires the PHP GD extension on the worker.');

        $selections = collect();
        if ($data['selection_mode'] === 'all_filtered') {
            $filters = ['q' => $data['filter_q'] ?? null, 'group_id' => $data['filter_group_id'] ?? null,
                'category_id' => $data['filter_category_id'] ?? null, 'package_id' => $data['filter_package_id'] ?? null];
            $exams = $this->filteredExamQuery($tenantId, $filters)->get();
            foreach ($exams as $exam) {
                $images = $service->discover($exam);
                if ($images->isNotEmpty()) $selections->push(compact('exam', 'images'));
            }
        } elseif ($data['selection_mode'] === 'all_images') {
            $exams = Exam::where('organization_id', $tenantId)->whereIn('id', $data['exam_ids'])->get()->keyBy('id');
            foreach ($data['exam_ids'] as $examId) {
                $exam = $exams->get((int) $examId);
                $images = $service->discover($exam);
                if ($images->isNotEmpty()) $selections->push(compact('exam', 'images'));
            }
        } else {
            $exam = Exam::where('organization_id', $tenantId)->findOrFail($data['exam_id']);
            $images = $service->resolveSelection($exam, $data['selected_images']);
            if ($images->count() !== count($data['selected_images'])) return back()->withInput()->with('error', 'One or more selected images changed. Reload the images and select them again.');
            $selections->push(compact('exam', 'images'));
        }

        $totalImages = $selections->sum(fn (array $selection) => $selection['images']->count());
        if ($totalImages < 1) return back()->withInput()->with('error', 'No existing images were found in the selected paper(s).');
        if ($totalImages > 5000) return back()->withInput()->with('error', 'This selection contains '.$totalImages.' images. Narrow the filters so one submission has at most 5,000 image-processing items.');

        $batchToken = (string) Str::uuid();
        $runs = DB::transaction(function () use ($selections, $tenantId, $data, $provider, $model, $configuration, $batchToken, $local) {
            return $selections->map(fn (array $selection) => $this->createRun(
                $selection['exam'], $selection['images'], $tenantId, $data, $provider, $model,
                $local ? 'local' : (string) ($configuration->image_cleanup_quality ?: 'medium'), $batchToken
            ));
        });
        $started = $runs->take(2)->filter(fn (ImageCleanupRun $run) => $launcher->start($run->id))->count();
        $requestSummary = $removeOnly
            ? ' Each selected standalone image will be removed in a draft with 0 paid API requests.'
            : ($local
                ? ' Local pixel cleanup uses PHP GD and creates 0 paid API requests.'
                : ' Each image creates one paid API request ('.$totalImages.' total).');
        $message = $totalImages.' image(s) from '.$runs->count().' paper(s) queued.'.$requestSummary.' '.
            ($started ? $started.' paper worker(s) started immediately.' : 'The scheduled workers will start shortly.');
        if ($runs->count() === 1) return redirect()->route('image-cleanup.show', $runs->first())->with('success', $message);
        return redirect()->route('image-cleanup.index')->with('success', $message);
    }

    private function createRun(Exam $exam, $images, int $tenantId, array $data, string $provider, string $model, string $quality, string $batchToken): ImageCleanupRun
    {
        $run = ImageCleanupRun::create([
            'organization_id' => $tenantId, 'exam_id' => $exam->id, 'requested_by' => auth()->id(),
            'batch_token' => $batchToken, 'status' => 'queued', 'provider' => $provider,
            'model' => $model, 'quality' => $quality, 'action' => $data['action'],
            'instructions' => trim((string) ($data['instructions'] ?? '')) ?: null,
            'total_images' => $images->count(),
        ]);
        $questions = $exam->questions()->whereIn('questions.id', $images->pluck('question_id'))->get()->keyBy('id');
        foreach ($images as $image) {
            $question = $questions->get($image['question_id']);
            ImageCleanupItem::create([
                'organization_id' => $tenantId, 'run_id' => $run->id, 'exam_id' => $exam->id,
                'question_id' => $image['question_id'], 'field' => $image['field'], 'image_index' => $image['image_index'],
                'status' => 'queued', 'original_src' => $image['src'], 'original_field_html' => (string) $question->{$image['field']},
            ]);
        }
        return $run;
    }

    public function show(ImageCleanupRun $run)
    {
        $this->authorizeTenant($run);
        $run->load(['exam:id,name', 'requester:id,name']);
        $items = $run->items()->with('question:id,question_code,question')->orderBy('id')->paginate(30);
        $reprocessableCount = $run->items()->count();
        return view('image-cleanup.show', compact('run', 'items', 'reprocessableCount'));
    }

    public function stop(ImageCleanupRun $run)
    {
        $this->authorizeTenant($run);
        if ($run->status === 'queued') {
            $run->items()->where('status', 'queued')->update(['status' => 'cancelled']);
            $run->update(['status' => 'cancelled', 'stop_requested_at' => now(), 'completed_at' => now(), 'processed_images' => $run->total_images]);
        } elseif (in_array($run->status, ['starting', 'running'], true)) {
            $run->update(['status' => 'stop_requested', 'stop_requested_at' => now()]);
        }
        return back()->with('success', 'Stop requested. The current paid image may finish, but no new image request will start.');
    }

    public function retry(ImageCleanupRun $run, ImageCleanupProcessLauncher $launcher)
    {
        $this->authorizeTenant($run);
        $failed = $run->items()->whereIn('status', ['failed', 'cancelled'])->update(['status' => 'queued', 'failure_message' => null]);
        if (! $failed) return back()->with('error', 'There are no failed or cancelled images to retry.');
        $run->update([
            'status' => 'queued', 'stop_requested_at' => null, 'completed_at' => null, 'failure_message' => null,
            'processed_images' => $run->items()->whereNotIn('status', ['queued', 'processing'])->count(),
            'ready_count' => $run->items()->where('status', 'ready')->count(),
            'failed_count' => 0,
        ]);
        $launcher->start($run->id);
        return back()->with('success', $failed.' image(s) queued for retry.');
    }

    public function reprocessItem(Request $request, ImageCleanupRun $run, ImageCleanupItem $item, ImageCleanupProcessLauncher $launcher, ImageCleanupService $service)
    {
        $this->authorizeTenant($run);
        abort_unless((int) $item->organization_id === (int) Tenant::id() && (int) $item->run_id === (int) $run->id, 404);
        if (! in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            return back()->with('error', 'Wait for this cleanup run to stop or finish before requesting another paid image edit.');
        }
        $data = $request->validate(['instructions' => ['required', 'string', 'min:3', 'max:2000']]);
        try { $rebase = $item->status === 'published' ? $this->currentImageSource($item, $service) : []; }
        catch (\Throwable $exception) { return back()->with('error', $exception->getMessage()); }
        $item->update($rebase + [
            'status' => 'queued', 'instructions' => trim($data['instructions']), 'proposed_field_html' => null,
            'output_path' => null, 'request_id' => null, 'failure_message' => null,
            'published_by' => null, 'published_at' => null,
        ]);
        $this->resumeRun($run);
        $launcher->start($run->id);
        $message = $run->provider === 'local'
            ? 'Only this image was queued for local reprocessing. No paid API request will be made.'
            : 'Only this image was queued with its corrective prompt. Exactly one additional paid image request will be made.';
        return back()->with('success', $message);
    }

    public function reprocessPaper(Request $request, ImageCleanupRun $run, ImageCleanupProcessLauncher $launcher, ImageCleanupService $service)
    {
        $this->authorizeTenant($run);
        if (! in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            return back()->with('error', 'Wait for this cleanup run to stop or finish before reprocessing the paper.');
        }
        $data = $request->validate(['instructions' => ['required', 'string', 'min:3', 'max:2000']]);
        $items = $run->items()->get();
        if ($items->isEmpty()) return back()->with('error', 'This run has no images to reprocess.');
        try {
            $rebases = $items->filter(fn (ImageCleanupItem $item) => $item->status === 'published')
                ->mapWithKeys(fn (ImageCleanupItem $item) => [$item->id => $this->currentImageSource($item, $service)]);
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }
        $run->update(['instructions' => trim($data['instructions'])]);
        foreach ($items as $item) {
            $item->update(($rebases->get($item->id) ?? []) + [
                'status' => 'queued', 'proposed_field_html' => null, 'output_path' => null,
                'request_id' => null, 'failure_message' => null, 'published_by' => null, 'published_at' => null,
            ]);
        }
        $count = $items->count();
        $this->resumeRun($run);
        $launcher->start($run->id);
        $message = $run->provider === 'local'
            ? $count.' image(s) queued for local reprocessing with 0 paid API requests.'
            : $count.' image(s) queued with the shared paper prompt. This creates '.$count.' additional paid image requests.';
        return back()->with('success', $message);
    }
    public function publish(Request $request, ImageCleanupRun $run, ImageCleanupService $service)
    {
        $this->authorizeTenant($run);
        $data = $request->validate(['item_ids' => ['required', 'array', 'min:1'], 'item_ids.*' => ['integer', 'distinct']]);
        $items = $run->items()->whereIn('id', $data['item_ids'])->where('status', 'ready')->get();
        if ($items->count() !== count($data['item_ids'])) return back()->with('error', 'Some selected drafts are no longer publishable. Refresh and try again.');
        try {
            $count = $service->publish($items, (int) auth()->id());
            $run->update(['ready_count' => $run->items()->where('status', 'ready')->count()]);
        } catch (\Throwable $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', $count.' cleaned image(s) published. Previous question versions were saved.');
    }

    public function reject(Request $request, ImageCleanupRun $run)
    {
        $this->authorizeTenant($run);
        $data = $request->validate(['item_ids' => ['required', 'array', 'min:1'], 'item_ids.*' => ['integer', 'distinct']]);
        $count = $run->items()->whereIn('id', $data['item_ids'])->where('status', 'ready')->update(['status' => 'rejected']);
        return back()->with('success', $count.' generated image draft(s) rejected. Originals remain unchanged.');
    }

    public function publishRuns(Request $request, ImageCleanupService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'publish_scope' => ['required', Rule::in(['selected', 'all_ready'])],
            'run_ids' => ['nullable', 'required_if:publish_scope,selected', 'array', 'min:1'],
            'run_ids.*' => ['integer', 'distinct'],
        ]);
        $query = ImageCleanupRun::where('organization_id', $tenantId)
            ->whereHas('items', fn ($items) => $items->where('status', 'ready'));
        if ($data['publish_scope'] === 'selected') $query->whereIn('id', $data['run_ids']);
        $runs = $query->with('exam:id,name')->oldest()->get();
        if ($runs->isEmpty()) return back()->with('error', 'No ready image drafts were found for the selected cleanup runs.');

        $published = 0;
        $failed = [];
        foreach ($runs as $run) {
            $items = $run->items()->where('status', 'ready')->get();
            if ($items->isEmpty()) continue;
            try {
                $published += $service->publish($items, (int) auth()->id());
                $run->update(['ready_count' => $run->items()->where('status', 'ready')->count()]);
            } catch (\Throwable $exception) { $failed[] = ($run->exam?->name ?: 'Run '.$run->id).': '.$exception->getMessage(); }
        }
        $message = $published.' cleaned image draft(s) published by administrator without requiring separate review approval.';
        if ($failed) $message .= ' '.count($failed).' paper(s) could not be published: '.implode(' ', $failed);
        return back()->with($published ? 'success' : 'error', $message);
    }

    private function currentImageSource(ImageCleanupItem $item, ImageCleanupService $service): array
    {
        $question = Question::where('organization_id', Tenant::id())->whereKey($item->question_id)->first();
        if (! $question || ! in_array($item->field, ImageCleanupService::FIELDS, true)) {
            throw new \RuntimeException('The current live image could not be located for reprocessing. Start a new cleanup run from the paper.');
        }
        $html = (string) $question->{$item->field};
        $image = collect($service->images($html))->firstWhere('index', (int) $item->image_index);
        if (! $image) throw new \RuntimeException('The current live image position changed. Start a new cleanup run from the paper.');
        return ['original_src' => $image['src'], 'original_field_html' => $html];
    }
    private function resumeRun(ImageCleanupRun $run): void
    {
        $run->update([
            'status' => 'queued', 'stop_requested_at' => null, 'completed_at' => null, 'failure_message' => null,
            'processed_images' => $run->items()->whereNotIn('status', ['queued', 'processing'])->count(),
            'ready_count' => $run->items()->where('status', 'ready')->count(),
            'failed_count' => $run->items()->where('status', 'failed')->count(),
        ]);
    }
    private function validateFilters(Request $request, int $tenantId): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:120'],
            'group_id' => ['nullable', Rule::exists('groups', 'id')->where('organization_id', $tenantId)],
            'category_id' => ['nullable', Rule::exists('category', 'id')->where('organization_id', $tenantId)],
            'package_id' => ['nullable', Rule::exists('packages', 'id')->where('organization_id', $tenantId)]]);
    }

    private function filteredExamQuery(int $tenantId, array $data)
    {
        $query = Exam::where('organization_id', $tenantId)->whereHas('questions', fn ($q) => $q->where(function ($images) {
            foreach (ImageCleanupService::FIELDS as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $images->{$method}($field, 'like', '%<img%');
            }
        }));
        $this->applyHierarchy($query, $data);
        if ($term = trim((string) ($data['q'] ?? ''))) $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        return $query;
    }

    private function applyHierarchy($query, array $data): void
    {
        if (! empty($data['group_id'])) {
            $id = (int) $data['group_id'];
            $query->where(fn ($q) => $q->whereHas('groups', fn ($g) => $g->whereKey($id))->orWhereHas('packages.groups', fn ($g) => $g->whereKey($id)));
        }
        if (! empty($data['category_id'])) {
            $id = (int) $data['category_id'];
            $query->where(fn ($q) => $q->where('category_level_1', $id)->orWhere('category_level_2', $id)
                ->orWhereHas('packages', fn ($p) => $p->where('category_level_1', $id)->orWhere('category_level_2', $id)));
        }
        if (! empty($data['package_id'])) $query->whereHas('packages', fn ($q) => $q->whereKey((int) $data['package_id']));
    }

    private function authorizeTenant(object $model): void
    {
        abort_unless((int) $model->organization_id === (int) Tenant::id(), 404);
    }
}
