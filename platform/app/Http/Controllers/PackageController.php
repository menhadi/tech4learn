<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PackageTag;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\Group;
use App\Models\Category;
use Illuminate\Support\Facades\File;
use App\Models\Titles;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB; // Added DB facade
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use App\Support\SaasAccess;

class PackageController extends Controller
{
    private function paidPackagesAvailable(): bool
    {
        return SaasAccess::isPlatformOwner();
    }

    private function sectionTitle(string $sectionKey, int $legacyId)
    {
        $query = Titles::query();

        if (Schema::hasColumn('titles', 'section_key')) {
            $query->where('section_key', $sectionKey);
        } else {
            $query->where('id', $legacyId);
        }

        $tenantTitle = (clone $query)
            ->when(class_exists(\App\Support\Tenant::class)
                ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
                : null, function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->first();

        return $tenantTitle ?: $query->first();
    }

    private function validateTenantGroups(array $groupIds): void
    {
        $ownedCount = Group::where('organization_id', \App\Support\Tenant::id())
            ->whereIn('id', $groupIds)
            ->count();

        if ($ownedCount !== count(array_unique($groupIds))) {
            abort(422, 'Invalid group selected.');
        }
    }

    private function validateTenantExams(array $examIds): void
    {
        if (empty($examIds)) {
            return;
        }

        $ownedCount = Exam::where('organization_id', \App\Support\Tenant::id())
            ->whereIn('id', $examIds)
            ->count();

        if ($ownedCount !== count(array_unique($examIds))) {
            abort(422, 'Invalid exam selected.');
        }
    }

    private function ensureTenantOwns($model): void
    {
        if ((int) ($model->organization_id ?? 0) !== (int) \App\Support\Tenant::id()) {
            abort(404);
        }
    }

    private function packageUploadPath(): string
    {
        $path = public_path('uploads/package');

        if (! File::exists($path)) {
            try {
                File::makeDirectory($path, 0755, true);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'photo' => 'Package image folder could not be created. Please check public/uploads permissions.',
                ]);
            }
        }

        if (! File::isDirectory($path) || ! is_writable($path)) {
            throw ValidationException::withMessages([
                'photo' => 'Package image folder is not writable. Please check public/uploads/package permissions.',
            ]);
        }

        return $path;
    }

    private function availablePackageTags()
    {
        $tenantId = \App\Support\Tenant::id();

        return PackageTag::query()
            ->where('status', 1)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $tenantId);
            })
            ->orderBy('name')
            ->get();
    }

    private function syncPackageTags(Package $package, array $tagValues): void
    {
        $tenantId = \App\Support\Tenant::id();
        $tagIds = [];

        foreach ($tagValues as $tagValue) {
            $tagValue = trim((string) $tagValue);

            if ($tagValue === '') {
                continue;
            }

            if (is_numeric($tagValue)) {
                $tag = PackageTag::query()
                    ->where('id', (int) $tagValue)
                    ->where('status', 1)
                    ->where(function ($query) use ($tenantId) {
                        $query->whereNull('organization_id')
                            ->orWhere('organization_id', $tenantId);
                    })
                    ->first();

                if ($tag) {
                    $tagIds[] = $tag->id;
                }

                continue;
            }

            $name = Str::limit(strip_tags($tagValue), 60, '');
            $slug = Str::slug($name);

            if ($slug === '') {
                continue;
            }

            $tag = PackageTag::firstOrCreate(
                ['organization_id' => $tenantId, 'slug' => $slug],
                ['name' => $name, 'status' => true]
            );

            $tagIds[] = $tag->id;
        }

        $package->tags()->sync(array_values(array_unique($tagIds)));
    }

    public function index(Request $request)
    {
        // 1. Base Query
        $query = Package::with(['groups', 'tags'])->where('organization_id', \App\Support\Tenant::id()); // Eager load groups

        // 2. Search Logic
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->input('search') . '%')
                  ->orWhere('description', 'like', '%' . $request->input('search') . '%');
            });
        }

        if ($request->filled('group')) {

            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('groups.id', $request->group);
            });

        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('package_tags.id', $request->tag);
            });
        }

        // 3. Fetch Packages
        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;
        $packages = $query->displayOrdered()->paginate($perPage)->withQueryString();

        // 4. Fetch Page Elements (Titles/Config)
        $titles = $this->sectionTitle('packages', 3);
        $configuration_detail = getConfiguration();

        // 5. Calculate Insights (New Logic)
        // Note: Adjust 'order_items' table name if your DB uses something else.
        // Assuming 'order_items' links packages to orders.
        
        $totalPackages = Package::where('organization_id', \App\Support\Tenant::id())->count();
        $activePackages = Package::where('organization_id', \App\Support\Tenant::id())->where('status', 1)->count();
        
        // Calculate Revenue (Approximate based on order_items if exists, else 0)
        // We use a try-catch or check to avoid crashing if table doesn't exist yet
        $totalRevenue = 0;
        $topPackageName = 'N/A';
        
        try {
            $totalRevenue = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.organization_id', \App\Support\Tenant::id())
                ->sum(DB::raw('order_items.price * order_items.quantity'));
            
            $bestSeller = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->select('order_items.package_id', DB::raw('count(*) as total'))
                ->where('orders.organization_id', \App\Support\Tenant::id())
                ->groupBy('order_items.package_id')
                ->orderByDesc('total')
                ->first();
                
            if($bestSeller) {
                $topPkg = Package::where('organization_id', \App\Support\Tenant::id())->find($bestSeller->package_id);
                $topPackageName = $topPkg ? $topPkg->name : 'N/A';
            }
        } catch (\Exception $e) {
            // Table might not exist or empty, ignore
        }

        $stats = [
            'total' => $totalPackages,
            'active' => $activePackages,
            'revenue' => $totalRevenue,
            'top_seller' => $topPackageName,
            'paid_count' => Package::where('organization_id', \App\Support\Tenant::id())->where('package_type', 'paid')->count(),
            'free_count' => Package::where('organization_id', \App\Support\Tenant::id())->where('package_type', 'free')->count(),
        ];

        $groups = Group::where('organization_id', \App\Support\Tenant::id())->whereHas('packages')->orderBy('group_name')->get();
        $packageTags = $this->availablePackageTags();

        return view('packages.index', compact('packages', 'titles', 'configuration_detail', 'stats', 'groups', 'packageTags', 'perPage'));
    }

    public function create()
    {
        $selectedExamOrder = null;

        $parentCategories = Category::with('groups')->where('organization_id', \App\Support\Tenant::id())->whereNull('parent_id')
            ->where('status', 1)
            ->displayOrdered()
            ->get();

        $childCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNotNull('parent_id')
            ->where('status', 1)
            ->displayOrdered()
            ->get();

        $groups = Group::where('organization_id', \App\Support\Tenant::id())->displayOrdered()->get();
        $paidPackagesAvailable = $this->paidPackagesAvailable();
        $packageTags = $this->availablePackageTags();
        $flashcardsAvailable = SaasAccess::featureEnabled('flashcards');
        $aiFlashcardGenerationAvailable = SaasAccess::featureEnabled('ai_flashcard_generation');

        return view('packages.action', compact('groups', 'parentCategories', 'childCategories', 'paidPackagesAvailable', 'packageTags', 'flashcardsAvailable', 'aiFlashcardGenerationAvailable', 'selectedExamOrder'));
    }

    public function edit(Package $package)
    {
        $this->ensureTenantOwns($package);
        $package->load(['tags', 'exams']);
        $selectedExamOrder = $package->exams->first()?->pivot?->display_order;
        $groups = Group::where('organization_id', \App\Support\Tenant::id())->displayOrdered()->get();

        $categoryLevel2 = $package->category_level_2;
        $groupIds = $package->groups()->pluck('groups.id')->toArray();

        $level3Data = collect();
        $level3Type = null;

        // Exam List
        if (in_array($categoryLevel2, ['year_wise', 'full_length_test'])) {

            $query = Exam::where('organization_id', \App\Support\Tenant::id())->select('id', 'name');

            if (!empty($groupIds)) {
                $query->whereHas('groups', function ($q) use ($groupIds) {
                    $q->whereIn('groups.id', $groupIds);
                });
            }

            $level3Data = $query->orderBy('name')->get();
            $level3Type = 'exam';
        }
        // Subject List
        else if (in_array($categoryLevel2, ['subject_wise', 'subject_wise_test'])) {

            $query = Subject::select('id', 'subject_name')
                ->whereHas('groups', function ($q) {
                    $q->where('groups.organization_id', \App\Support\Tenant::id());
                });

            if (!empty($groupIds)) {
                $query->whereHas('groups', function ($q) use ($groupIds) {
                    $q->whereIn('groups.id', $groupIds);
                });
            }

            $level3Data = $query->orderBy('subject_name')->get();
            $level3Type = 'subject';
        }

        $parentCategories = Category::with('groups')->where('organization_id', \App\Support\Tenant::id())->whereNull('parent_id')
            ->where('status', 1)
            ->displayOrdered()
            ->get();

        $childCategories = Category::where('organization_id', \App\Support\Tenant::id())->whereNotNull('parent_id')
            ->where('status', 1)
            ->displayOrdered()
            ->get();

        $paidPackagesAvailable = $this->paidPackagesAvailable();
        $packageTags = $this->availablePackageTags();
        $flashcardsAvailable = SaasAccess::featureEnabled('flashcards');
        $aiFlashcardGenerationAvailable = SaasAccess::featureEnabled('ai_flashcard_generation');

        return view('packages.action', compact('package', 'groups', 'level3Data', 'level3Type', 'parentCategories', 'childCategories', 'paidPackagesAvailable', 'packageTags', 'flashcardsAvailable', 'aiFlashcardGenerationAvailable', 'selectedExamOrder'));
    }

    public function store(Request $request)
    {
        SaasAccess::abortIfLimitReached('packages');

        $request->validate([
            'name' => 'required|string|max:255',
            // 'slug2' => 'required|unique:packages,slug2',
            'slug' => 'nullable|unique:packages,slug',
            // 'description' => 'required|string',
            'description' => 'sometimes|nullable',
            'amount' => 'nullable|numeric',
            'discounted_amount' => 'nullable|numeric',
            'package_type' => 'required|in:free,paid',
            'auto_enroll_on_registration' => 'nullable|boolean',
            'status' => 'required|boolean',
            'show_pdf_download' => 'nullable|boolean',
            'pdf_title_text' => 'nullable|string|max:255',
            'pdf_header_text' => 'nullable|string|max:255',
            'pdf_footer_text' => 'nullable|string|max:500',
            'pdf_watermark_text' => 'nullable|string|max:255',
            'show_solution_pdf_download' => 'nullable|boolean',
            'solution_pdf_title_text' => 'nullable|string|max:255',
            'solution_pdf_header_text' => 'nullable|string|max:255',
            'solution_pdf_footer_text' => 'nullable|string|max:500',
            'solution_pdf_watermark_text' => 'nullable|string|max:255',
            'flashcards_enabled' => 'nullable|boolean',
            'guest_flashcards_enabled' => 'nullable|boolean',
            'ai_flashcard_generation_enabled' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'expiry_days' => 'required|integer',
            'display_order' => 'nullable|integer|min:0',
            'exam_display_order' => 'nullable|integer|min:0',
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:groups,id',
            'exam_id' => 'sometimes|nullable',
            'subject_id' => 'sometimes|nullable|numeric',

            'category_level_1' => 'sometimes|nullable|numeric',
            'category_level_2' => 'sometimes|nullable|numeric',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'nullable|string|max:80',
        ]);

        if ($request->input('package_type') === 'paid' && ! $this->paidPackagesAvailable()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Paid packages are currently available only on the default platform site.');
        }

        $this->validateTenantGroups($request->group_ids);
        \App\Support\CategoryHierarchy::validate($request->integer('category_level_1') ?: null, $request->integer('category_level_2') ?: null, (array) $request->input('group_ids', []), (int) \App\Support\Tenant::id());
        $examIds = array_filter((array) $request->input('exam_id', []));
        $this->validateTenantExams($examIds);

        $package = new Package($request->except([
            'photo',
            '_token',
            'group_ids',
            'exam_id',
            'subject_id',
            'tag_ids',
            'exam_display_order',
        ]));
        $package->organization_id = \App\Support\Tenant::id();
        $package->slug = $this->uniquePackageSlug($request->input('slug') ?: $request->input('name'));
        $package->auto_enroll_on_registration = $request->input('package_type') === 'free'
            && $request->boolean('auto_enroll_on_registration');
        $package->show_pdf_download = $request->boolean('show_pdf_download');
        $package->show_solution_pdf_download = $request->boolean('show_solution_pdf_download');
        $package->flashcards_enabled = $request->boolean('flashcards_enabled') && SaasAccess::featureEnabled('flashcards');
        $package->guest_flashcards_enabled = $package->flashcards_enabled && $request->boolean('guest_flashcards_enabled');
        $package->ai_flashcard_generation_enabled = $package->flashcards_enabled
            && $request->boolean('ai_flashcard_generation_enabled')
            && SaasAccess::featureEnabled('ai_flashcard_generation');

        if ($request->hasFile('photo')) 
        {
            $image = $request->file('photo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move($this->packageUploadPath(), $imageName);
            $package->photo = 'uploads/package/' . $imageName;
        }

        $package->save();
        $package->groups()->sync($request->group_ids);
        $this->syncPackageTags($package, (array) $request->input('tag_ids', []));

        if (! empty($examIds)) {
            $examOrder = $request->filled('exam_display_order') ? (int) $request->exam_display_order : null;
            $examPivot = collect($examIds)->mapWithKeys(fn ($examId) => [
                (int) $examId => ['display_order' => $examOrder],
            ])->all();
            $package->exams()->sync($examPivot);
        }

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    public function update(Request $request, Package $package)
    {
        $this->ensureTenantOwns($package);

        $id = $package->id;

        $request->validate([
            'name' => 'required|string|max:255',
            // 'description' => 'required|string',
            'description' => 'sometimes|nullable',
            // 'slug2' => 'required|unique:packages,slug2,'.$id,
            'slug' => 'nullable|unique:packages,slug,'.$id,
            'amount' => 'nullable|numeric',
            'discounted_amount' => 'nullable|numeric',
            'package_type' => 'required|in:free,paid',
            'auto_enroll_on_registration' => 'nullable|boolean',
            'status' => 'required|boolean',
            'show_pdf_download' => 'nullable|boolean',
            'pdf_title_text' => 'nullable|string|max:255',
            'pdf_header_text' => 'nullable|string|max:255',
            'pdf_footer_text' => 'nullable|string|max:500',
            'pdf_watermark_text' => 'nullable|string|max:255',
            'show_solution_pdf_download' => 'nullable|boolean',
            'solution_pdf_title_text' => 'nullable|string|max:255',
            'solution_pdf_header_text' => 'nullable|string|max:255',
            'solution_pdf_footer_text' => 'nullable|string|max:500',
            'solution_pdf_watermark_text' => 'nullable|string|max:255',
            'flashcards_enabled' => 'nullable|boolean',
            'guest_flashcards_enabled' => 'nullable|boolean',
            'ai_flashcard_generation_enabled' => 'nullable|boolean',
            'photo' => 'nullable|image',
            'remove_photo' => 'nullable|boolean',
            'expiry_days' => 'required|integer',
            'display_order' => 'nullable|integer|min:0',
            'exam_display_order' => 'nullable|integer|min:0',
            'group_ids' => 'required|array',
            'group_ids.*' => 'exists:groups,id',

            'exam_id' => 'sometimes|nullable',
            'subject_id' => 'sometimes|nullable|numeric',

            'category_level_1' => 'sometimes|nullable|numeric',
            'category_level_2' => 'sometimes|nullable|numeric',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'nullable|string|max:80',
        ]);

        if ($request->input('package_type') === 'paid' && ! $this->paidPackagesAvailable()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Paid packages are currently available only on the default platform site.');
        }

        $this->validateTenantGroups($request->group_ids);
        \App\Support\CategoryHierarchy::validate($request->integer('category_level_1') ?: null, $request->integer('category_level_2') ?: null, (array) $request->input('group_ids', []), (int) \App\Support\Tenant::id());
        $examIds = array_filter((array) $request->input('exam_id', []));
        $this->validateTenantExams($examIds);

        if ($request->boolean('remove_photo') && $package->photo && File::exists(public_path($package->photo))) {
            File::delete(public_path($package->photo));
            $package->photo = null;
        }

        if ($request->hasFile('photo')) 
        {
            if ($package->photo && File::exists(public_path($package->photo))) 
            {
                File::delete(public_path($package->photo));
            }

            $image = $request->file('photo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move($this->packageUploadPath(), $imageName);
            $package->photo = 'uploads/package/' . $imageName;
        }

        $package->fill($request->except([
            'photo',
            '_token',
            '_method',
            'group_ids',
            'exam_id',
            'subject_id',
            'tag_ids',
            'remove_photo',
            'exam_display_order',
        ]));
        $package->auto_enroll_on_registration = $request->input('package_type') === 'free'
            && $request->boolean('auto_enroll_on_registration');
        $package->show_pdf_download = $request->boolean('show_pdf_download');
        $package->show_solution_pdf_download = $request->boolean('show_solution_pdf_download');
        $package->flashcards_enabled = $request->boolean('flashcards_enabled') && SaasAccess::featureEnabled('flashcards');
        $package->guest_flashcards_enabled = $package->flashcards_enabled && $request->boolean('guest_flashcards_enabled');
        $package->ai_flashcard_generation_enabled = $package->flashcards_enabled
            && $request->boolean('ai_flashcard_generation_enabled')
            && SaasAccess::featureEnabled('ai_flashcard_generation');
        $package->slug = $this->uniquePackageSlug($request->input('slug') ?: $request->input('name'), $package->id);
        $package->save();

        $package->groups()->sync($request->group_ids);
        $this->syncPackageTags($package, (array) $request->input('tag_ids', []));

        if (! empty($examIds)) {
            $examOrder = $request->filled('exam_display_order') ? (int) $request->exam_display_order : null;
            $examPivot = collect($examIds)->mapWithKeys(fn ($examId) => [
                (int) $examId => ['display_order' => $examOrder],
            ])->all();
            $package->exams()->sync($examPivot);
        }

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package)
    {
        $this->ensureTenantOwns($package);
        if ($package->photo && File::exists(public_path($package->photo)))
        {
            File::delete(public_path($package->photo));
        }

        $package->delete();
        return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
    }

    /**
     * Package ka status (publish/unpublish) toggle karein.
     */
    public function toggleStatus(Request $request, Package $package)
    {
        $this->ensureTenantOwns($package);

        $package->status = !$package->status; 
        $package->save();

        return response()->json(['success' => true, 'newStatus' => $package->status]);
    }

    public function categoryLevel3(Request $request)
    {
        $categoryLevel2 = $request->category_level_2;
        $groupIds = $request->group_ids ?? [];

        /*
        |--------------------------------------------------------------------------
        | 1. Exam List
        |--------------------------------------------------------------------------
        | year_wise
        | full_length_test
        */
        if (in_array($categoryLevel2, ['year_wise', 'full_length_test'])) {

            $query = Exam::where('organization_id', \App\Support\Tenant::id())->select('id', 'name');

            // Apply group filter only if group_ids is not empty
            if (!empty($groupIds)) {
                $query->whereHas('groups', function ($q) use ($groupIds) {
                    $q->whereIn('groups.id', $groupIds);
                });
            }

            $data = $query->orderBy('name')->get();

            return response()->json([
                'type' => 'exam',
                'data' => $data,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Subject List
        |--------------------------------------------------------------------------
        | subject_wise
        | subject_wise_test
        */
        $query = Subject::select('id', 'subject_name')
            ->whereHas('groups', function ($q) {
                $q->where('groups.organization_id', \App\Support\Tenant::id());
            });

        // Apply group filter only if group_ids is not empty
        if (!empty($groupIds)) {
            $query->whereHas('groups', function ($q) use ($groupIds) {
                $q->whereIn('groups.id', $groupIds);
            });
        }

        $data = $query->orderBy('subject_name')->get();

        return response()->json([
            'type' => 'subject',
            'data' => $data,
        ]);
    }

    private function uniquePackageSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'package';
        $slug = $baseSlug;
        $counter = 2;

        while (Package::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}
