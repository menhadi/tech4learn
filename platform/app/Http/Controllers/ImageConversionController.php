<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertQuestionImageToPng;
use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\ImageConversionItem;
use App\Models\ImageConversionRun;
use App\Services\QuestionImageConversionService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ImageConversionController extends Controller
{
    public function index(Request $request, QuestionImageConversionService $service)
    {
        $tenantId = Tenant::id();
        $groups = Group::where('organization_id', $tenantId)->displayOrdered()->get(['id', 'group_name']);
        $categories = Category::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'parent_id', 'title']);
        $categoryById = $categories->keyBy('id');
        $categoryOptions = $categories->map(function ($category) use ($categoryById) {
            $groupIds = $category->groups->pluck('id');
            if ($groupIds->isEmpty() && $category->parent_id) $groupIds = $categoryById->get($category->parent_id)?->groups?->pluck('id') ?? collect();
            return ['id' => (int) $category->id, 'title' => $category->title,
                'group_ids' => $groupIds->map(fn ($id) => (int) $id)->values()];
        })->values();
        $examOptions = Exam::where('organization_id', $tenantId)->with('groups:id,group_name')->displayOrdered()->get(['id', 'name', 'category_level_1', 'category_level_2'])
            ->map(fn (Exam $exam) => [
                'id' => (int) $exam->id, 'name' => $exam->name,
                'category_id' => $exam->category_level_1 ? (int) $exam->category_level_1 : null,
                'subcategory_id' => $exam->category_level_2 ? (int) $exam->category_level_2 : null,
                'group_ids' => $exam->groups->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ])->values();

        $status = in_array($request->query('status'), ['queued', 'processing', 'success', 'failed'], true) ? $request->query('status') : null;
        $items = ImageConversionItem::where('organization_id', $tenantId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->integer('run'), fn ($query, $run) => $query->where('run_id', $run))
            ->with(['exam:id,name', 'question:id,question_code'])->latest()->paginate(50)->withQueryString();
        $active = ImageConversionItem::where('organization_id', $tenantId)->whereIn('status', ['queued', 'processing'])->exists();

        return view('image-converter.index', compact('groups', 'categoryOptions', 'examOptions', 'items', 'status', 'active') + [
            'capabilities' => $service->capabilities(),
        ]);
    }

    public function preview(Request $request, QuestionImageConversionService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'exam_ids' => ['required', 'array', 'min:1', 'max:50'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
        ]);
        $exams = Exam::where('organization_id', $tenantId)->whereIn('id', $data['exam_ids'])->get();
        $images = $exams->flatMap(fn (Exam $exam) => $service->discover($exam))->unique('identity')->values();
        return response()->json(['images' => $images, 'count' => $images->count()]);
    }

    public function store(Request $request, QuestionImageConversionService $service)
    {
        $tenantId = Tenant::id();
        $data = $request->validate([
            'exam_ids' => ['required', 'array', 'min:1', 'max:50'],
            'exam_ids.*' => ['integer', 'distinct', Rule::exists('exams', 'id')->where('organization_id', $tenantId)],
            'selected_images' => ['required', 'array', 'min:1', 'max:5000'],
            'selected_images.*' => ['string', 'distinct', 'max:255'],
        ]);
        $exams = Exam::where('organization_id', $tenantId)->whereIn('id', $data['exam_ids'])->get();
        $images = $service->resolveTokens($exams, $data['selected_images']);
        if ($images->count() !== count($data['selected_images'])) return back()->with('error', 'One or more selected images changed or are no longer convertible. Reload the table and try again.');

        $unsupported = $images->where('local', false);
        if ($unsupported->isNotEmpty()) return back()->with('error', $unsupported->count().' selected image(s) are remote or outside local public storage and cannot be converted in place.');

        $run = DB::transaction(function () use ($images, $tenantId) {
            $run = ImageConversionRun::create([
                'organization_id' => $tenantId, 'requested_by' => auth()->id(),
                'status' => 'queued', 'total_images' => $images->count(),
            ]);
            foreach ($images as $image) {
                ImageConversionItem::create([
                    'organization_id' => $tenantId, 'run_id' => $run->id,
                    'exam_id' => $image['exam_id'], 'question_id' => $image['question_id'],
                    'field' => $image['field'], 'image_index' => $image['image_index'],
                    'status' => 'queued', 'original_src' => $image['src'],
                ]);
            }
            return $run;
        });
        foreach ($run->items as $item) ConvertQuestionImageToPng::dispatch($item->id);

        return redirect()->route('image-converter.index', ['run' => $run->id])
            ->with('success', $run->total_images.' image(s) queued for server-side PNG conversion. This page will refresh while processing.');
    }

    public function retry(ImageConversionItem $item)
    {
        abort_unless((int) $item->organization_id === (int) Tenant::id(), 404);
        if ($item->status !== 'failed') return back()->with('error', 'Only failed conversions can be retried.');
        $item->update(['status' => 'queued', 'failure_message' => null]);
        $item->run()->update(['status' => 'processing', 'completed_at' => null]);
        ConvertQuestionImageToPng::dispatch($item->id);
        return back()->with('success', 'The failed image was queued for retry.');
    }
}
