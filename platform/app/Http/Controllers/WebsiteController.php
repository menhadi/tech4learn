<?php

namespace App\Http\Controllers;
use Illuminate\Support\Str;
use App\Models\HeroSlider;
use App\Models\Features;
use App\Models\Package;
use App\Models\Counter;
use App\Models\Testimonial;
use App\Models\AboutUs;
use App\Models\WebsitePage; 
use App\Models\Group;
use App\Models\Configuration;
use App\Models\Titles;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\FlashcardStudyHierarchy;
use App\Support\SaasAccess;
use App\Models\ExamResult; 
use App\Models\Exam; 
use App\Models\Category;
use App\Models\NavigationItem;
use App\Models\NavigationSetting;
use App\Models\Flashcard;
use App\Models\PackageTag;
use App\Models\QuestionsReport;
use App\Models\StudentFlashcardPoint;
use App\Models\StudentFlashcardPointEvent;
use App\Models\SaasLead;
use App\Services\FlashcardQuestionRotationService;
use App\Services\ExamDisplayOrder;
use App\Services\StudentActivityTracker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class WebsiteController extends Controller
{
    // Helper method to get common data for views

    private function tenantContentQuery($query)
    {
        return $query->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function defaultTenantId(): ?int
    {
        static $defaultTenantId = null;

        if ($defaultTenantId !== null) {
            return $defaultTenantId;
        }

        $defaultTenantId = DB::table('organizations')
            ->where('slug', 'examelite')
            ->value('id');

        return $defaultTenantId;
    }

    private function homepageCollection(string $modelClass, bool $enabled)
    {
        if (! $enabled) {
            return collect();
        }

        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasColumn($table, 'organization_id')) {
            return $modelClass::query()->orderBy('id')->get();
        }

        $tenantId = $this->tenantId();
        $items = $modelClass::query()
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->orderBy('id')
            ->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        $defaultTenantId = $this->defaultTenantId();

        if ($defaultTenantId && (int) $defaultTenantId !== (int) $tenantId) {
            return $modelClass::query()
                ->where('organization_id', $defaultTenantId)
                ->orderBy('id')
                ->get();
        }

        return $items;
    }

    private function homepageTitle(int $id, bool $enabled)
    {
        if (! $enabled) {
            return null;
        }

        $tenantId = $this->tenantId();
        $sectionKey = $this->titleSectionKey($id);

        if (Schema::hasColumn('titles', 'section_key')) {
            $title = Titles::query()
                ->where('section_key', $sectionKey)
                ->when($tenantId, function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                })
                ->first();

            if ($title) {
                return $title;
            }

            $defaultTenantId = $this->defaultTenantId();

            if ($defaultTenantId && (int) $defaultTenantId !== (int) $tenantId) {
                $defaultTitle = Titles::query()
                    ->where('section_key', $sectionKey)
                    ->where('organization_id', $defaultTenantId)
                    ->first();

                if ($defaultTitle) {
                    return $defaultTitle;
                }
            }
        }

        $title = Titles::query()
            ->where('id', $id)
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->first();

        if ($title) {
            return $title;
        }

        $defaultTenantId = $this->defaultTenantId();

        if ($defaultTenantId && (int) $defaultTenantId !== (int) $tenantId) {
            return Titles::query()
                ->where('id', $id)
                ->where('organization_id', $defaultTenantId)
                ->first();
        }

        return null;
    }

    private function titleSectionKey(int $id): string
    {
        return [
            1 => 'features',
            2 => 'testimonials',
            3 => 'packages',
            4 => 'banner',
            5 => 'top_performers',
            6 => 'counters',
        ][$id] ?? 'section_' . $id;
    }

    private function displayText($value): string
    {
        if (is_array($value)) {
            return (string) ($value['en'] ?? reset($value) ?: '');
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? reset($decoded) ?: $value);
            }
        }

        return (string) ($value ?? '');
    }

    private function tenantQuery($query)
    {
        $tenantId = $this->tenantId();
        $model = $query->getModel();

        if ($tenantId && Schema::hasColumn($model->getTable(), 'organization_id')) {
            $query->where($model->getTable() . '.organization_id', $tenantId);
        }

        return $query;
    }

    private function publicPackageQuery($query)
    {
        if (! $this->paidPackagesAvailable()) {
            $query->where('package_type', 'free');
        }

        return $query->whereHas('exams', fn ($examQuery) => $examQuery->active());
    }

    private function paidPackagesAvailable(): bool
    {
        return SaasAccess::isPlatformOrganization() && SaasAccess::featureEnabled('paid_packages');
    }

    private function availablePackageTags()
    {
        $tenantId = $this->tenantId();

        return PackageTag::query()
            ->where('status', 1)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('organization_id')
                    ->when($tenantId, fn ($q) => $q->orWhere('organization_id', $tenantId));
            })
            ->whereHas('packages', function ($query) use ($tenantId) {
                $query->where('packages.status', 1)
                    ->when($tenantId, fn ($q) => $q->where('packages.organization_id', $tenantId));
            })
            ->orderBy('name')
            ->get();
    }

    private function applyPackageTagFilter($query, Request $request)
    {
        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($tagQuery) use ($request) {
                $tagQuery->where('package_tags.id', $request->input('tag'));
            });
        }

        return $query;
    }

    private function homepageSectionEnabled($configuration, string $key, bool $default = true): bool
    {
        $homepageSectionKeys = [
            'homepage_show_hero',
            'homepage_show_featured_packages',
            'homepage_show_top_performers',
            'homepage_show_features',
            'homepage_show_testimonials',
            'homepage_show_counters',
        ];

        $hasEnabledSection = collect($homepageSectionKeys)->contains(function ($sectionKey) use ($configuration) {
            return (bool) data_get($configuration, $sectionKey, false);
        });

        if (! $hasEnabledSection && in_array($key, [
            'homepage_show_hero',
            'homepage_show_featured_packages',
            'homepage_show_top_performers',
            'homepage_show_features',
            'homepage_show_counters',
        ], true)) {
            return true;
        }

        return (bool) data_get($configuration, $key, $default);
    }

    private function activePackageQuery($query)
    {
        return $query->where(function ($statusQuery) {
            $statusQuery->where('packages.status', 1)
                ->orWhere('packages.status', '1')
                ->orWhere('packages.status', true);
        })->whereHas('exams', fn ($examQuery) => $examQuery->active());
    }

    protected function getCommonViewData() {
        $menuTenantId = $this->tenantId();
        $tenantKey = $menuTenantId ?: 'platform';
        $menuHierarchy = Cache::remember("website.menu.hierarchy.{$tenantKey}", now()->addMinutes(10), function () use ($menuTenantId) {
            return $this->tenantQuery(Group::query())
                ->whereHas('packages', fn ($query) => $query->where('status', 1)->whereHas('exams', fn ($examQuery) => $examQuery->active()))
                ->with(['categories' => fn ($query) => $query->when($menuTenantId, fn ($tenantQuery, $id) => $tenantQuery->where('category.organization_id', $id))->whereNull('parent_id')->where('status', 1)->whereNotNull('slug')->with(['children' => fn ($childQuery) => $childQuery->when($menuTenantId, fn ($tenantQuery, $id) => $tenantQuery->where('category.organization_id', $id))->where('status', 1)->whereNotNull('slug')])])
                ->displayOrdered()
                ->get()
                ->map(function ($group) use ($menuTenantId) {
                    $packages = $group->packages()->when($menuTenantId, fn ($query, $id) => $query->where('packages.organization_id', $id))->where('packages.status', 1)->whereHas('exams', fn ($examQuery) => $examQuery->active())->whereNotNull('packages.slug')
                        ->with(['category:id,title,slug', 'subcategory:id,title,slug'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])->take(4)->get();

                    return [
                        'id' => $group->id,
                        'name' => $this->displayText($group->group_name),
                        'slug' => $group->slug ?: Str::slug($this->displayText($group->group_name)),
                        'categories' => $group->categories->map(fn ($category) => [
                            'id' => $category->id,
                            'title' => $this->displayText($category->title),
                            'slug' => $category->slug,
                            'description' => Str::limit(strip_tags($this->displayText($category->description)), 76),
                            'children' => $category->children->map(fn ($child) => [
                                'title' => $this->displayText($child->title),
                                'slug' => $child->slug,
                            ])->values()->all(),
                        ])->values()->all(),
                        'packages' => $packages->map(fn ($package) => [
                            'name' => $this->displayText($package->name),
                            'slug' => $package->slug,
                            'exams_count' => $package->exams_count,
                            'category' => $this->displayText($package->category?->title),
                        ])->values()->all(),
                    ];
                })->values();
        });
        $navigation = Cache::remember('website.navigation.'.$tenantKey, now()->addMinutes(10), function () use ($menuTenantId) {
            $settings = NavigationSetting::where('organization_id', $menuTenantId)->first();
            $query = NavigationItem::where('organization_id', $menuTenantId)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
            return [
                'header' => $settings?->header_enabled ? (clone $query)->where('location', 'header')->whereNull('parent_id')->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->get() : collect(),
                'secondary' => $settings?->secondary_enabled ? (clone $query)->where('location', 'secondary')->get() : collect(),
                'footer' => $settings?->footer_enabled ? (clone $query)->where('location', 'footer')->get() : collect(),
                'footer_brand_label' => $settings?->footer_enabled ? $settings->footer_brand_label : null,
            ];
        });
        $customHeaderNavigation = $navigation['header'];
        $customSecondaryNavigation = $navigation['secondary'] ?? collect();
        $customFooterNavigation = $navigation['footer'];
        $customFooterBrandLabel = $navigation['footer_brand_label'] ?? null;
        // Fetch pages for the 'More' dropdown where show_in_menu is true
        $navbarPages = $this->tenantQuery(WebsitePage::where('show_in_menu', 1))->orderBy('id', 'asc')->get();
        $footerPages = $this->tenantQuery(WebsitePage::where('show_in_footer', 1))->orderBy('id', 'asc')->get();

        $footerPopularGroups = $this->tenantQuery(Group::whereHas('packages', function ($q) {
                $q->where('status', 1);
            }))
            ->orderBy('display_order', 'asc')
            ->limit(10)
            ->get();

        $footerPopularPackages = $this->tenantQuery(Package::where('status', 1))
            ->whereNotNull('slug')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $footerPopularCategories = $this->tenantQuery(Category::with('parent'))
            ->whereNull('parent_id')
            ->whereNotNull('slug')
            ->orderBy('title')
            ->limit(12)
            ->get();

        $configuration_detail = getConfiguration();

        $footerCategoryLinks = $footerPopularCategories->map(function ($category) {
            $title = $category->title ?? '';

            if (is_array($title)) {
                $title = $title['en'] ?? reset($title) ?: '';
            }

            if (is_string($title) && str_starts_with(trim($title), '{')) {
                $decoded = json_decode($title, true);
                if (is_array($decoded)) {
                    $title = $decoded['en'] ?? reset($decoded) ?: $title;
                }
            }

            if (empty($title) || empty($category->slug)) {
                return null;
            }

            $url = url('/exam-groups/all/' . $category->slug);

            if (!empty($category->parent_id) && $category->parent && !empty($category->parent->slug)) {
                $url = url('/exam-groups/all/' . $category->parent->slug . '/' . $category->slug);
            }

            return [
                'title' => $title,
                'url' => $url,
            ];
        })->filter()->values();

        return compact('menuHierarchy', 'customHeaderNavigation', 'customSecondaryNavigation', 'customFooterNavigation', 'customFooterBrandLabel', 'navbarPages', 'footerPages', 'footerPopularGroups', 'footerCategoryLinks', 'footerPopularPackages', 'configuration_detail');
    }

    public function dashboard()
    {
        $commonData = $this->getCommonViewData();
        $configuration = $commonData['configuration_detail'] ?? getConfiguration();
        $tenantId = $this->tenantId();
        $showHero = $this->homepageSectionEnabled($configuration, 'homepage_show_hero', true);
        $showPackages = $this->homepageSectionEnabled($configuration, 'homepage_show_featured_packages', true);
        $showTopPerformers = $this->homepageSectionEnabled($configuration, 'homepage_show_top_performers', true);
        $showTestimonials = $this->homepageSectionEnabled($configuration, 'homepage_show_testimonials', true);
        $showCounters = $this->homepageSectionEnabled($configuration, 'homepage_show_counters', true);
        $showFeatures = $this->homepageSectionEnabled($configuration, 'homepage_show_features', true);
        
        // General Dashboard Data
        $HeroSlider = $this->homepageCollection(HeroSlider::class, $showHero);
        $Features = $this->homepageCollection(Features::class, $showFeatures);

        $topSellingPackageIds = topSellingPackage();

        if ($showPackages && $topSellingPackageIds->count() > 0) {

            $allPackages = $this->activePackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])))
                ->whereIn('id', $topSellingPackageIds)
                ->get()
                ->sortBy(function ($package) use ($topSellingPackageIds) {
                    return array_search($package->id, $topSellingPackageIds->toArray());
                })
                ->values();

        } elseif ($showPackages) {

            $allPackages = $this->activePackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])))
                ->latest()
                ->take(9)
                ->get();
        } else {
            $allPackages = collect();
        }

        if ($showPackages && $allPackages->isEmpty()) {
            $allPackages = $this->activePackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])))
                ->latest()
                ->take(9)
                ->get();
        }

        if ($showPackages && $allPackages->count() < 9) {
            $fillPackages = $this->activePackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])))
                ->whereNotIn('id', $allPackages->pluck('id')->filter()->all())
                ->latest()
                ->take(9 - $allPackages->count())
                ->get();

            $allPackages = $allPackages->merge($fillPackages)->take(9)->values();
        }
        
        $groups = $this->tenantQuery(Group::whereHas('packages', function ($query) {
                $query->where('status', 1);
            }))
            ->with([
                'packages' => function ($query) use ($tenantId) {
                    $query->where('status', 1)
                        ->when($tenantId, function ($query, $tenantId) {
                            $query->where('packages.organization_id', $tenantId);
                        })
                        ->withCount(['exams' => fn ($examQuery) => $examQuery->active()])
                        ->orderBy('created_at', 'desc');
                }
            ])
            ->orderBy('display_order', 'asc')
            ->take(12)
            ->get();

        $homepageCategories = $this->tenantQuery(Category::withCount(['packages' => function ($query) {
                $query->where('status', 1);
            }])
            ->whereNull('parent_id')
            ->where('status', 1)
            ->whereHas('packages', function ($query) {
                $query->where('status', 1);
            }))
            ->displayOrdered()
            ->take(12)
            ->get();

        $homepageSubcategories = subcategories_enabled() ? $this->tenantQuery(Category::with('parent')
            ->withCount(['subcategoryPackages' => function ($query) {
                $query->where('status', 1);
            }])
            ->whereNotNull('parent_id')
            ->where('status', 1)
            ->whereHas('subcategoryPackages', function ($query) {
                $query->where('status', 1);
            }))
            ->displayOrdered()
            ->take(12)
            ->get() : collect();
        $homepagePackageCount = $this->activePackageQuery($this->tenantQuery(Package::query()))->count();
        $homepageExamCount = $this->tenantQuery(Exam::query())
            ->where(function ($query) {
                $query->where('status', 'Active')
                    ->orWhere('status', 1)
                    ->orWhere('status', '1');
            })
            ->count();
        $homeSearchSuggestions = collect()
            ->merge($groups->pluck('group_name')->map(fn ($value) => $this->displayText($value)))
            ->merge($this->activePackageQuery($this->tenantQuery(Package::query()))->latest()->limit(12)->pluck('name')->map(fn ($value) => $this->displayText($value)))
            ->merge($this->tenantQuery(Exam::query())->active()->latest()->limit(12)->pluck('name')->map(fn ($value) => $this->displayText($value)))
            ->filter()
            ->unique()
            ->take(24)
            ->values();

        $Counter = $this->homepageCollection(Counter::class, $showCounters);
        $Testimonial = $this->homepageCollection(Testimonial::class, $showTestimonials);
        $Features_Titles = $this->homepageTitle(1, $showFeatures);
        $Testimonial_Titles = $this->homepageTitle(2, $showTestimonials);
        $Counter_Titles = $this->homepageTitle(6, $showCounters);
        $Packages_Titles = $this->homepageTitle(3, $showPackages);
        $Banner_Titles = $this->homepageTitle(4, true);
        $TopPerformers_Titles = $this->homepageTitle(5, $showTopPerformers);
        
        // Top Performers (using 'percent' column)
        // $topPerformers = ExamResult::whereNotNull('percent') 
        //                    ->whereHas('student') 
        //                    ->whereHas('exam') 
        //                    ->with(['student:id,name,photo', 'exam:id,name']) 
        //                    ->orderBy('percent', 'desc') 
        //                    ->orderBy('end_time', 'desc') 
        //                    ->take(5) 
        //                    ->get();

        // The homepage renders the group-wise leaderboard below; avoid loading a second, unused all-results collection.
        $topPerformers = collect();

        $groupWisePerformers = $showTopPerformers ? collect(groupWisePerformers(request('leaderboard_period', 'year'))) : collect();
        $fallbackPerformerGroups = $showTopPerformers && $groupWisePerformers->isEmpty()
            ? $groups->take(5)->map(function ($group) {
                return [
                    'group' => [
                        'id' => $group->id,
                        'name' => $this->displayText($group->group_name ?? $group->name ?? 'Exam Group'),
                    ],
                    'top_performers' => collect(),
                ];
            })->values()
            : collect();

        $seo = [
            'title' => $configuration->name ?? 'ExamElite',
            'description' => 'Search mock tests, previous year papers and exam preparation packages. Start practicing from clear package and exam pages.',
            'canonical' => route('home'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $configuration->name ?? 'ExamElite',
                'url' => route('home'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('courses.index') . '?search={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        // Merge common data with specific data for this view
        $viewData = array_merge($commonData, compact(
            'HeroSlider', 'Features', 'allPackages', 'groups', 'Counter', 'Testimonial',
            'Features_Titles', 'Testimonial_Titles', 'Counter_Titles', 'Packages_Titles', 'Banner_Titles',
            'TopPerformers_Titles', 'topPerformers', 'groupWisePerformers', 'fallbackPerformerGroups',
            'homepageCategories', 'homepageSubcategories', 'homepagePackageCount', 'homepageExamCount', 'homeSearchSuggestions', 'seo'
        ));

        return view('website.dashboard', $viewData);
    }

    public function about()
    {
        $aboutPage = $this->tenantQuery(WebsitePage::query())->get()->first(function ($page) {
            $shortTitle = $page->short_title;
            if (is_array($shortTitle)) {
                $shortTitle = $shortTitle['en'] ?? reset($shortTitle) ?: '';
            }
            return \Illuminate\Support\Str::slug((string) $shortTitle) === 'about-us';
        });

        if ($aboutPage) {
            return redirect()->route('page.show', ['slug' => 'about-us']);
        }

        $commonData = $this->getCommonViewData();
        $AboutUs = $this->tenantQuery(AboutUs::query())->first();
        $Features = $this->tenantContentQuery(Features::query())->get();
        $Features_Titles = $this->tenantContentQuery(Titles::query())->where('id','1')->first();

        $viewData = array_merge($commonData, compact('AboutUs','Features','Features_Titles'));
        return view('website.about', $viewData);
    }

    public function exams_old(
        Request $request,
        $groupSlug = null,
        $category = null,
        $subcategory = null
    ) {
        if (! subcategories_enabled() && ! empty($subcategory)) {
            return redirect()->route('website.exams.index', array_filter([
                'group' => $groupSlug,
                'category' => $category,
            ]));
        }

        $commonData = $this->getCommonViewData();
        $availableTags = $this->availablePackageTags();

        /*
        |--------------------------------------------------------------------------
        | Default Data
        |--------------------------------------------------------------------------
        */

        $title = 'Explore Exams';
        $subHeading = 'Choose a group, category or package to reach the right papers quickly.';

        $breadcrumbs = [
            [
                'title' => 'Home',
                'url'   => url('/')
            ],
            [
                'title' => 'Exams',
                'url'   => route('website.exams.index')
            ]
        ];

        /*
        |--------------------------------------------------------------------------
        | Groups
        |--------------------------------------------------------------------------
        */

        $allGroups = Group::whereHas('packages', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('exams', function ($q) {

                $q->where('status', 'Active')
                    ->whereNotNull('category_level_1')
                    ->where('category_level_1', '!=', '')
                    ->whereNotNull('category_level_2')
                    ->where('category_level_2', '!=', '');
            })
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Find Group
        |--------------------------------------------------------------------------
        */

        $group = null;

        if (!empty($groupSlug)) {

            $group = $allGroups->first(function ($item) use ($groupSlug) {
                $groupName = $this->displayText($item->group_name);

                return !empty($groupName)
                    && Str::slug($groupName) === $groupSlug;
            });

            if (!$group) {
                return redirect()->route('website.exams.index');
            }

            $title = 'Explore ' . $group->group_name . ' Exams';
            $subHeading = 'Choose a category or package from this exam group.';

            $breadcrumbs[] = [
                'title' => $group->group_name,
                'url'   => route('website.exams.index', [
                    'group' => $groupSlug
                ])
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Group Only
        |--------------------------------------------------------------------------
        */

        if (!empty($groupSlug) && empty($category)) {

            $categoryLevel1 = Exam::whereHas('groups', function ($q) use ($group) {
                    $q->where('groups.id', $group->id);
                })
                ->where('status', 'Active')
                ->whereNotNull('category_level_1')
                ->where('category_level_1', '!=', '')
                ->distinct()
                ->pluck('category_level_1');

            if ($categoryLevel1->isEmpty()) {
                return redirect()->route('website.exams.index');
            }

            return view('website.groups', array_merge(
                $commonData,
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'categoryLevel1',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        if (!empty($groupSlug) && !empty($category) && empty($subcategory)) {

            $formattedCategory = ucwords(str_replace('_', ' ', $category));

            $title = $formattedCategory . ' - ' . $group->group_name;

            $breadcrumbs[] = [
                'title' => $formattedCategory,
                'url'   => route('website.exams.index', [
                    'group'    => $groupSlug,
                    'category' => $category
                ])
            ];

            $categoryLevel2 = Exam::whereHas('groups', function ($q) use ($group) {
                    $q->where('groups.id', $group->id);
                })
                ->where('status', 'Active')
                ->where('category_level_1', $category)
                ->whereNotNull('category_level_2')
                ->where('category_level_2', '!=', '')
                ->distinct()
                ->pluck('category_level_2');

            if (! subcategories_enabled() || $categoryLevel2->isEmpty()) {
                return redirect()->route('website.exams.index');
            }

            return view('website.groups', array_merge(
                $commonData,
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'category',
                    'categoryLevel2',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Subcategory / Packages
        |--------------------------------------------------------------------------
        */

        if (!empty($groupSlug) && !empty($category) && !empty($subcategory)) {

            $formattedCategory = ucwords(str_replace('_', ' ', $category));
            $formattedSubCategory = ucwords(str_replace('_', ' ', $subcategory));

            $title = $formattedSubCategory . ' - ' . $group->group_name;

            $breadcrumbs[] = [
                'title' => $formattedCategory,
                'url'   => route('website.exams.index', [
                    'group'    => $groupSlug,
                    'category' => $category
                ])
            ];

            $breadcrumbs[] = [
                'title' => $formattedSubCategory,
                'url'   => route('website.exams.index', [
                    'group'       => $groupSlug,
                    'category'    => $category,
                    'subcategory' => $subcategory
                ])
            ];

            $sort = $request->input('sort', 'default');

            $query = Package::query()
                ->where('status', 1)
                ->whereHas('groups', function ($q) use ($group) {
                    $q->where('groups.id', $group->id);
                })
                ->whereHas('exams', function ($q) use ($category, $subcategory) {

                    $q->where('category_level_1', $category)
                        ->where('category_level_2', $subcategory)
                        ->where('status', 'Active');
                });

            switch ($sort) {

                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;

                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;

                case 'price_low_high':
                    $query->where('package_type', 'paid')
                        ->orderByRaw('COALESCE(discounted_amount, amount) asc');
                    break;

                case 'price_high_low':
                    $query->where('package_type', 'paid')
                        ->orderByRaw('COALESCE(discounted_amount, amount) desc');
                    break;

                default:
                    $query->displayOrdered();
                    break;
            }

            $packages = $query
                ->paginate(9)
                ->withQueryString();

            return view('website.groups', array_merge(
                $commonData,
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'category',
                    'subcategory',
                    'packages',
                    'sort',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Default Page
        |--------------------------------------------------------------------------
        */

        return view('website.groups', array_merge(
            $commonData,
            compact(
                'allGroups',
                'title',
                'subHeading',
                'breadcrumbs'
            )
        ));
    }

    private function browseSeo(string $title, ?string $subHeading, array $breadcrumbs = []): array
    {
        $cleanTitle = trim(strip_tags($title ?: 'Explore Exams'));
        $description = trim(strip_tags($subHeading ?: 'Browse exam groups, categories, packages and mock tests.'));

        return [
            'title' => $cleanTitle,
            'description' => $description,
            'canonical' => url()->current(),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $cleanTitle,
                'description' => Str::limit($description, 240, ''),
                'breadcrumb' => [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => collect($breadcrumbs)->values()->map(function ($breadcrumb, $index) {
                        return [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => strip_tags($breadcrumb['title'] ?? ''),
                            'item' => $breadcrumb['url'] ?? url()->current(),
                        ];
                    })->all(),
                ],
            ],
        ];
    }

    private function browseLeaderboard(?Group $group = null, ?Category $category = null, ?Category $subcategory = null)
    {
        $tenantId = $this->tenantId();

        $query = ExamResult::query()
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->whereNotNull('exam_results.student_id')
            ->whereNotNull('exam_results.percent')
            ->whereNotNull('exam_results.end_time')
            ->when(request('leaderboard_period', 'year') !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('students.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId);
            })
            ->when($group, function ($query, $group) {
                $query->whereExists(function ($exists) use ($group) {
                    $exists->select(DB::raw(1))
                        ->from('exam_groups')
                        ->whereColumn('exam_groups.exam_id', 'exams.id')
                        ->where('exam_groups.group_id', $group->id);
                });
            })
            ->when($category, function ($query, $category) {
                $query->where(function ($categoryScope) use ($category) {
                    $categoryScope->where('exams.category_level_1', $category->id)
                        ->orWhereExists(function ($packageScope) use ($category) {
                            $packageScope->select(DB::raw(1))
                                ->from('exam_packages')
                                ->join('packages', 'exam_packages.package_id', '=', 'packages.id')
                                ->whereColumn('exam_packages.exam_id', 'exams.id')
                                ->where('packages.category_level_1', $category->id);
                        });
                });
            })
            ->when($subcategory, function ($query, $subcategory) {
                $query->where(function ($subcategoryScope) use ($subcategory) {
                    $subcategoryScope->where('exams.category_level_2', $subcategory->id)
                        ->orWhereExists(function ($packageScope) use ($subcategory) {
                            $packageScope->select(DB::raw(1))
                                ->from('exam_packages')
                                ->join('packages', 'exam_packages.package_id', '=', 'packages.id')
                                ->whereColumn('exam_packages.exam_id', 'exams.id')
                                ->where('packages.category_level_2', $subcategory->id);
                        });
                });
            });

        return $query
            ->select(
                'exam_results.student_id',
                'students.name',
                'students.photo',
                DB::raw('MAX(exam_results.percent) as best_percent'),
                DB::raw('MAX(exam_results.end_time) as last_attempt')
            )
            ->groupBy('exam_results.student_id', 'students.name', 'students.photo')
            ->orderByDesc('best_percent')
            ->orderByDesc('last_attempt')
            ->take(5)
            ->get();
    }

    public function headerSearch(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []])->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $tenantId = $this->tenantId();
        $results = collect();
        $examLimit = min(max((int) $request->query('exam_limit', 30), 30), 5000);
        $paidPackagesAvailable = $this->paidPackagesAvailable();

        $publicPackageConstraint = function ($query) use ($tenantId, $paidPackagesAvailable) {
            $query->where('packages.status', 1)
                ->when($tenantId, fn ($tenantQuery, $id) => $tenantQuery->where('packages.organization_id', $id))
                ->when(! $paidPackagesAvailable, fn ($tenantQuery) => $tenantQuery->where('packages.package_type', 'free'));
        };

        $eligibleExamQuery = $this->tenantQuery(Exam::query())
            ->where('status', 'Active')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->where('name', 'like', $like)
            ->whereHas('questions')
            ->whereHas('packages', $publicPackageConstraint);

        $matchingExamCount = (clone $eligibleExamQuery)->count();
        $exams = $eligibleExamQuery
            ->latest('created_at')
            ->latest('id')
            ->limit($examLimit)
            ->get(['id', 'name', 'slug']);

        foreach ($exams as $exam) {
            $results->push([
                'label' => $this->displayText($exam->name),
                'type' => 'Exam',
                'url' => route('exam.detail', $exam->slug),
                'icon' => 'ri-file-list-3-line',
            ]);
        }

        $packages = $this->publicPackageQuery($this->tenantQuery(Package::query()))
            ->where('status', 1)
            ->where('name', 'like', $like)
            ->displayOrdered()
            ->limit(12)
            ->get(['id', 'name', 'slug']);

        foreach ($packages as $package) {
            $results->push([
                'label' => $this->displayText($package->name),
                'type' => 'Package',
                'url' => route('courses.detail', $package->slug ?: $package->id),
                'icon' => 'ri-stack-line',
            ]);
        }

        $categories = $this->tenantQuery(Category::with(['groups', 'parent.groups']))
            ->where('status', 1)
            ->whereNotNull('slug')
            ->where('title', 'like', $like)
            ->displayOrdered()
            ->limit(12)
            ->get();

        foreach ($categories as $category) {
            $parent = $category->parent;
            $group = $category->groups->first() ?: $parent?->groups?->first();
            $groupSlug = $group ? \Illuminate\Support\Str::slug($this->displayText($group->group_name)) : 'all';
            $parameters = $parent
                ? ['group' => $groupSlug, 'category' => $parent->slug, 'subcategory' => $category->slug]
                : ['group' => $groupSlug, 'category' => $category->slug];

            $results->push([
                'label' => $this->displayText($category->title),
                'type' => $parent ? 'Subcategory' : 'Category',
                'url' => route('website.exams.index', $parameters),
                'icon' => $parent ? 'ri-node-tree' : 'ri-folder-3-line',
            ]);
        }

        $groups = $this->tenantQuery(Group::query())
            ->where('group_name', 'like', $like)
            ->whereHas('packages', function ($query) use ($tenantId) {
                $query->where('packages.status', 1)
                    ->when($tenantId, fn ($tenantQuery, $id) => $tenantQuery->where('packages.organization_id', $id));
            })
            ->displayOrdered()
            ->limit(12)
            ->get(['id', 'group_name']);

        foreach ($groups as $group) {
            $groupSlug = \Illuminate\Support\Str::slug($this->displayText($group->group_name));
            $results->push([
                'label' => $this->displayText($group->group_name),
                'type' => 'Group',
                'url' => route('website.exams.index', ['group' => $groupSlug]),
                'icon' => 'ri-layout-grid-line',
            ]);
        }

        return response()->json([
            'results' => $results
                ->filter(fn ($item) => filled($item['label']) && filled($item['url']))
                ->unique(fn ($item) => $item['type'] . '|' . mb_strtolower(trim($item['label'])))
                ->values(),
            'meta' => [
                'exam_limit' => $examLimit,
                'exam_total' => $matchingExamCount,
                'has_more_exams' => $matchingExamCount > $examLimit,
            ],
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }
    public function exams(
        Request $request,
        $groupSlug = null,
        $category = null,
        $subcategory = null
    ) {
        if (! subcategories_enabled() && ! empty($subcategory)) {
            return redirect()->route('website.exams.index', array_filter([
                'group' => $groupSlug,
                'category' => $category,
            ]));
        }

        $commonData = $this->getCommonViewData();
        $availableTags = $this->availablePackageTags();

        /*
        |--------------------------------------------------------------------------
        | Default Data
        |--------------------------------------------------------------------------
        */

        $title = 'Explore Exams';

        $subHeading = 'Choose a group, category or package to reach the right papers quickly.';

        $breadcrumbs = [
            [
                'title' => 'Home',
                'url'   => url('/')
            ],
            [
                'title' => 'Exams',
                'url'   => route('website.exams.index')
            ]
        ];

        /*
        |--------------------------------------------------------------------------
        | Groups
        |--------------------------------------------------------------------------
        */

        // $allGroups = Group::whereHas('packages', function ($q) {

        //         $q->where('status', 1);

        //     })
        //     ->whereHas('exams', function ($q) {

        //         $q->where('status', 'Active')
        //             ->whereNotNull('category_level_1')
        //             ->whereNotNull('category_level_2');

        //     })
        //     ->get();

        $isAllGroupsBrowse = $groupSlug === 'all';
        if ($isAllGroupsBrowse) {
            $groupSlug = null;
        }

        $allGroups = $this->tenantQuery(Group::where(function ($groups) {
            $groups->whereHas('packages', fn ($q) => $q->where('status', 1))
                ->orWhereHas('exams', fn ($q) => $q->standalonePdfPapers());
        }))
            ->with(['packages' => function ($query) {
                $query->where('status', 1)
                    ->when($this->tenantId(), function ($query, $tenantId) {
                        $query->where('packages.organization_id', $tenantId);
                    })
                    ->withCount(['exams' => fn ($examQuery) => $examQuery->active()]);
            }])
            ->withCount(['exams as standalone_pdf_count' => fn ($q) => $q->standalonePdfPapers()])
            ->orderBy('display_order', 'asc')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Find Group
        |--------------------------------------------------------------------------
        */

        $paperGroup = $groupSlug ? $allGroups->first(fn ($item) => $item->slug === $groupSlug || (! $item->slug && Str::slug($item->group_name) === $groupSlug)) : null;
        $standalonePaperQuery = $this->tenantQuery(Exam::query())->standalonePdfPapers()
            ->when($groupSlug, fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('groups.id', $paperGroup?->id ?? 0)))
            ->when($category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $category)))
            ->when($subcategory, fn ($q) => $q->whereHas('subcategory', fn ($c) => $c->where('slug', $subcategory)))
            ->with('category')->displayOrdered();
        $commonData['standalonePapers'] = (clone $standalonePaperQuery)->paginate(12, ['*'], 'pdf_page')->withQueryString();

        $group = null;
        if ($isAllGroupsBrowse && empty($category)) {
            $title = 'Explore All Categories';
            $subHeading = 'Choose a category to discover its subcategories, packages and exams.';
            $categoryPackages = $this->publicPackageQuery($this->tenantQuery(Package::query()))
                ->where('status', 1)->whereNotNull('category_level_1')->withCount(['exams' => fn ($examQuery) => $examQuery->active()])->get();
            $categoryIds = $categoryPackages->pluck('category_level_1')->filter()->unique()->values();
            $categoryIds = $categoryIds->merge((clone $standalonePaperQuery)->pluck('category_level_1'))->filter()->unique();
            $categoryLevel1 = $this->tenantQuery(Category::whereIn('id', $categoryIds)->whereNull('parent_id'))
                ->displayOrdered()->get();
            $breadcrumbs[] = ['title' => 'Categories', 'url' => route('website.exams.index', ['group' => 'all'])];
            return view('website.groups', array_merge($commonData, [
                'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                'browseLeaderboard' => collect(),
            ], compact('title', 'subHeading', 'categoryLevel1', 'categoryPackages', 'breadcrumbs')));
        }

        if (!empty($groupSlug)) {

            $group = $allGroups->first(function ($item) use ($groupSlug) {

                return !empty($item->group_name)
                    && Str::slug($item->group_name) === $groupSlug;
            });

            if (!$group) {

                return redirect()->route('website.exams.index');
            }

            $groupName = $this->displayText($group->group_name);
            $title = 'Explore ' . $groupName . ' Exams';
            $subHeading = 'Choose a category or package from this exam group.';

            $breadcrumbs[] = [
                'title' => $groupName,
                'url'   => route('website.exams.index', [
                    'group' => $groupSlug
                ])
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Find Category By Slug
        |--------------------------------------------------------------------------
        */

        $categoryModel = null;

        if (!empty($category)) {

            $categoryModel = $this->tenantQuery(Category::where('slug', $category)
                ->whereNull('parent_id')
            )
                ->first();

            if (!$categoryModel) {

                return redirect()->route('website.exams.index');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Find Subcategory By Slug
        |--------------------------------------------------------------------------
        */

        $subcategoryModel = null;

        if (!empty($subcategory)) {

            $subcategoryModel = $this->tenantQuery(Category::where('slug', $subcategory)
                ->whereNotNull('parent_id')
            )
                ->first();

            if (!$subcategoryModel) {

                return redirect()->route('website.exams.index');
            }
        }

        if (empty($groupSlug) && !empty($category) && empty($subcategory)) {
            $title = $categoryModel->title;
            $subHeading = subcategories_enabled() ? 'Choose a subcategory or package from this category.' : 'Choose a package from this category.';
            $breadcrumbs[] = [
                'title' => $categoryModel->title,
                'url' => route('website.exams.index', [
                    'group' => 'all',
                    'category' => $categoryModel->slug,
                ]),
            ];

            $categoryPackages = $this->publicPackageQuery($this->tenantQuery(Package::query()))
                ->where('status', 1)
                ->where('category_level_1', $categoryModel->id)
                ->withCount(['exams' => fn ($examQuery) => $examQuery->active()])
                ->get();

            $subcategoryIds = $categoryPackages
                ->pluck('category_level_2')
                ->filter()
                ->unique()
                ->values();

            $categoryLevel2 = $this->tenantQuery(Category::whereIn('id', $subcategoryIds)
                ->whereNotNull('parent_id')
            )
                ->displayOrdered()
                ->get();

            if (! subcategories_enabled() || $categoryLevel2->isEmpty()) {
                $sort = $request->input('sort', 'default');
                $query = $this->applyPackageTagFilter($this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))), $request)
                    ->where('status', 1)
                    ->where('category_level_1', $categoryModel->id);

                $hasPaidPackages = (clone $query)->where('package_type', 'paid')->exists();

                switch ($sort) {
                    case 'name_asc':
                        $query->orderBy('name', 'asc');
                        break;
                    case 'name_desc':
                        $query->orderBy('name', 'desc');
                        break;
                    case 'price_low_high':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) asc');
                        break;
                    case 'price_high_low':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) desc');
                        break;
                    default:
                        $query->displayOrdered();
                        break;
                }

                $packages = $query->paginate(9)->withQueryString();

                return view('website.groups', array_merge(
                    $commonData,
                    [
                        'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                        'browseLeaderboard' => $this->browseLeaderboard(null, $categoryModel),
                    ],
                    compact('title', 'subHeading', 'categoryModel', 'packages', 'sort', 'availableTags', 'hasPaidPackages', 'breadcrumbs')
                ));
            }

            return view('website.groups', array_merge(
                $commonData,
                [
                    'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                    'browseLeaderboard' => $this->browseLeaderboard(null, $categoryModel),
                ],
                compact('title', 'subHeading', 'categoryModel', 'categoryLevel2', 'categoryPackages', 'breadcrumbs')
            ));
        }

        if (empty($groupSlug) && !empty($category) && !empty($subcategory)) {
            $title = $subcategoryModel->title;
            $subHeading = 'Choose a package to view papers and start practicing.';
            $breadcrumbs[] = [
                'title' => $categoryModel->title,
                'url' => route('website.exams.index', [
                    'group' => 'all',
                    'category' => $categoryModel->slug,
                ]),
            ];
            $breadcrumbs[] = [
                'title' => $subcategoryModel->title,
                'url' => route('website.exams.index', [
                    'group' => 'all',
                    'category' => $categoryModel->slug,
                    'subcategory' => $subcategoryModel->slug,
                ]),
            ];

            $sort = $request->input('sort', 'default');
            $query = $this->applyPackageTagFilter($this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))), $request)
                ->where('status', 1)
                ->where('category_level_1', $categoryModel->id)
                ->where('category_level_2', $subcategoryModel->id);

            $hasPaidPackages = (clone $query)->where('package_type', 'paid')->exists();

            switch ($sort) {
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'price_low_high':
                    $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) asc');
                    break;
                case 'price_high_low':
                    $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) desc');
                    break;
                default:
                    $query->displayOrdered();
                    break;
            }

            $packages = $query->paginate(9)->withQueryString();

            return view('website.groups', array_merge(
                $commonData,
                [
                    'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                    'browseLeaderboard' => $this->browseLeaderboard(null, $categoryModel, $subcategoryModel),
                ],
                compact('title', 'subHeading', 'categoryModel', 'subcategoryModel', 'packages', 'sort', 'availableTags', 'hasPaidPackages', 'breadcrumbs')
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Group Only
        |--------------------------------------------------------------------------
        */

        // if (!empty($groupSlug) && empty($category)) {

        //     $categoryIds = Exam::whereHas('groups', function ($q) use ($group) {

        //             $q->where('groups.id', $group->id);

        //         })
        //         ->where('status', 'Active')
        //         ->whereNotNull('category_level_1')
        //         ->distinct()
        //         ->pluck('category_level_1');

        //     $categoryLevel1 = Category::whereIn('id', $categoryIds)
        //         ->whereNull('parent_id')
        //         ->get();

        //     if ($categoryLevel1->isEmpty()) {

        //         return redirect()->route('website.exams.index');
        //     }

        //     return view('website.groups', array_merge(
        //         $commonData,
        //         compact(
        //             'group',
        //             'title',
        //             'subHeading',
        //             'categoryLevel1',
        //             'breadcrumbs'
        //         )
        //     ));
        // }

        if (!empty($groupSlug) && empty($category)) {

            $categoryIds = $this->tenantQuery(Package::whereHas('groups', function ($q) use ($group) {
                    $q->where('groups.id', $group->id);
                }))
                ->where('status', 1)
                ->whereNotNull('category_level_1')
                ->distinct()
                ->pluck('category_level_1');

            $categoryIds = $categoryIds->merge((clone $standalonePaperQuery)->pluck('category_level_1'))->filter()->unique();
            $categoryLevel1 = $this->tenantQuery(Category::query())
                ->whereIn('category.id', $categoryIds)
                ->whereNull('category.parent_id')
                ->get();

            if ($categoryLevel1->isEmpty()) {
                $sort = $request->input('sort', 'default');
                $query = $this->applyPackageTagFilter($this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))), $request)
                    ->where('status', 1)
                    ->whereHas('groups', function ($q) use ($group) {
                        $q->where('groups.id', $group->id);
                    });

                $hasPaidPackages = (clone $query)->where('package_type', 'paid')->exists();

                switch ($sort) {
                    case 'name_asc':
                        $query->orderBy('name', 'asc');
                        break;
                    case 'name_desc':
                        $query->orderBy('name', 'desc');
                        break;
                    case 'price_low_high':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) asc');
                        break;
                    case 'price_high_low':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) desc');
                        break;
                    default:
                        $query->displayOrdered();
                        break;
                }

                $packages = $query->paginate(9)->withQueryString();

                return view('website.groups', array_merge(
                    $commonData,
                    [
                        'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                        'browseLeaderboard' => $this->browseLeaderboard($group),
                    ],
                    compact('group', 'title', 'subHeading', 'packages', 'sort', 'availableTags', 'hasPaidPackages', 'breadcrumbs')
                ));
            }

            return view('website.groups', array_merge(
                $commonData,
                [
                    'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                    'browseLeaderboard' => $this->browseLeaderboard($group),
                ],
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'categoryLevel1',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        // if (!empty($groupSlug) && !empty($category) && empty($subcategory)) {

        //     $formattedCategory = $categoryModel->title;

        //     $title = $formattedCategory . ' - ' . $group->group_name;

        //     $breadcrumbs[] = [
        //         'title' => $formattedCategory,
        //         'url'   => route('website.exams.index', [
        //             'group'    => $groupSlug,
        //             'category' => $categoryModel->slug
        //         ])
        //     ];

        //     $subcategoryIds = Exam::whereHas('groups', function ($q) use ($group) {

        //             $q->where('groups.id', $group->id);

        //         })
        //         ->where('status', 'Active')
        //         ->where('category_level_1', $categoryModel->id)
        //         ->whereNotNull('category_level_2')
        //         ->distinct()
        //         ->pluck('category_level_2');

        //     $categoryLevel2 = Category::whereIn('id', $subcategoryIds)
        //         ->whereNotNull('parent_id')
        //         ->get();

        //     if (! subcategories_enabled() || $categoryLevel2->isEmpty()) {

        //         return redirect()->route('website.exams.index');
        //     }

        //     return view('website.groups', array_merge(
        //         $commonData,
        //         compact(
        //             'group',
        //             'title',
        //             'subHeading',
        //             'categoryModel',
        //             'categoryLevel2',
        //             'breadcrumbs'
        //         )
        //     ));
        // }

        if (!empty($groupSlug) && !empty($category) && empty($subcategory)) {

            $formattedCategory = $categoryModel->title;

            $title = $formattedCategory . ' - ' . $this->displayText($group->group_name);
            $subHeading = subcategories_enabled() ? 'Choose a subcategory or package from this category.' : 'Choose a package from this category.';

            $breadcrumbs[] = [
                'title' => $formattedCategory,
                'url'   => route('website.exams.index', [
                    'group'    => $groupSlug,
                    'category' => $categoryModel->slug
                ])
            ];

            // $subcategoryIds = $group->packages()
            //     ->where('status', 1)
            //     ->where('category_level_1', $categoryModel->id)
            //     ->whereNotNull('category_level_2')
            //     ->distinct()
            //     ->pluck('category_level_2');

            $subcategoryIds = $group->packages()
                ->reorder()
                ->where('status', 1)
                ->when($this->tenantId(), function ($query, $tenantId) {
                    $query->where('packages.organization_id', $tenantId);
                })
                ->where('category_level_1', $categoryModel->id)
                ->whereNotNull('category_level_2')
                ->distinct()
                ->pluck('category_level_2');

            $categoryLevel2 = $group->categories()
                ->whereIn('category.id', $subcategoryIds)
                ->whereNotNull('category.parent_id')
                ->get();

            if (! subcategories_enabled() || $categoryLevel2->isEmpty()) {
                $sort = $request->input('sort', 'default');
                $query = $this->applyPackageTagFilter($this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))), $request)
                    ->where('status', 1)
                    ->where('category_level_1', $categoryModel->id)
                    ->whereHas('groups', function ($q) use ($group) {
                        $q->where('groups.id', $group->id);
                    });

                $hasPaidPackages = (clone $query)->where('package_type', 'paid')->exists();

                switch ($sort) {
                    case 'name_asc':
                        $query->orderBy('name', 'asc');
                        break;
                    case 'name_desc':
                        $query->orderBy('name', 'desc');
                        break;
                    case 'price_low_high':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) asc');
                        break;
                    case 'price_high_low':
                        $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) desc');
                        break;
                    default:
                        $query->displayOrdered();
                        break;
                }

                $packages = $query->paginate(9)->withQueryString();

                return view('website.groups', array_merge(
                    $commonData,
                    [
                        'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                        'browseLeaderboard' => $this->browseLeaderboard($group, $categoryModel),
                    ],
                    compact('group', 'title', 'subHeading', 'categoryModel', 'packages', 'sort', 'availableTags', 'hasPaidPackages', 'breadcrumbs')
                ));
            }

            return view('website.groups', array_merge(
                $commonData,
                [
                    'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                    'browseLeaderboard' => $this->browseLeaderboard($group, $categoryModel),
                ],
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'categoryModel',
                    'categoryLevel2',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Subcategory / Packages
        |--------------------------------------------------------------------------
        */

        // if (!empty($groupSlug) && !empty($category) && !empty($subcategory)) {

        //     $formattedCategory = $categoryModel->title;

        //     $formattedSubCategory = $subcategoryModel->title;

        //     $title = $formattedSubCategory . ' - ' . $group->group_name;

        //     $breadcrumbs[] = [
        //         'title' => $formattedCategory,
        //         'url'   => route('website.exams.index', [
        //             'group'    => $groupSlug,
        //             'category' => $categoryModel->slug
        //         ])
        //     ];

        //     $breadcrumbs[] = [
        //         'title' => $formattedSubCategory,
        //         'url'   => route('website.exams.index', [
        //             'group'       => $groupSlug,
        //             'category'    => $categoryModel->slug,
        //             'subcategory' => $subcategoryModel->slug
        //         ])
        //     ];

        //     $sort = $request->input('sort', 'default');

        //     $query = Package::query()

        //         ->where('status', 1)

        //         ->whereHas('groups', function ($q) use ($group) {

        //             $q->where('groups.id', $group->id);

        //         })

        //         ->whereHas('exams', function ($q) use ($categoryModel, $subcategoryModel) {

        //             $q->where('category_level_1', $categoryModel->id)

        //                 ->where('category_level_2', $subcategoryModel->id)

        //                 ->where('status', 'Active');

        //         });

        //     switch ($sort) {

        //         case 'name_asc':

        //             $query->orderBy('name', 'asc');

        //             break;

        //         case 'name_desc':

        //             $query->orderBy('name', 'desc');

        //             break;

        //         case 'price_low_high':

        //             $query->where('package_type', 'paid')

        //                 ->orderByRaw('COALESCE(discounted_amount, amount) asc');

        //             break;

        //         case 'price_high_low':

        //             $query->where('package_type', 'paid')

        //                 ->orderByRaw('COALESCE(discounted_amount, amount) desc');

        //             break;

        //         default:

        //             $query->latest();

        //             break;
        //     }

        //     $packages = $query
        //         ->paginate(9)
        //         ->withQueryString();

        //     return view('website.groups', array_merge(
        //         $commonData,
        //         compact(
        //             'group',
        //             'title',
        //             'subHeading',
        //             'categoryModel',
        //             'subcategoryModel',
        //             'packages',
        //             'sort',
        //             'breadcrumbs'
        //         )
        //     ));
        // }

        if (!empty($groupSlug) && !empty($category) && !empty($subcategory)) {

            $formattedCategory = $categoryModel->title;

            $formattedSubCategory = $subcategoryModel->title;

            $title = $formattedSubCategory . ' - ' . $this->displayText($group->group_name);
            $subHeading = 'Choose a package to view papers and start practicing.';

            $breadcrumbs[] = [
                'title' => $formattedCategory,
                'url'   => route('website.exams.index', [
                    'group'    => $groupSlug,
                    'category' => $categoryModel->slug
                ])
            ];

            $breadcrumbs[] = [
                'title' => $formattedSubCategory,
                'url'   => route('website.exams.index', [
                    'group'       => $groupSlug,
                    'category'    => $categoryModel->slug,
                    'subcategory' => $subcategoryModel->slug
                ])
            ];

            $sort = $request->input('sort', 'default');

            $query = $this->applyPackageTagFilter($this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))), $request)

                ->where('status', 1)

                ->where('category_level_1', $categoryModel->id)

                ->where('category_level_2', $subcategoryModel->id)

                ->whereHas('groups', function ($q) use ($group) {

                    $q->where('groups.id', $group->id);

                });

            $hasPaidPackages = (clone $query)->where('package_type', 'paid')->exists();

            switch ($sort) {

                case 'name_asc':

                    $query->orderBy('name', 'asc');

                    break;

                case 'name_desc':

                    $query->orderBy('name', 'desc');

                    break;

                case 'price_low_high':

                    $query->where('package_type', 'paid')
                        ->orderByRaw('COALESCE(discounted_amount, amount) asc');

                    break;

                case 'price_high_low':

                    $query->where('package_type', 'paid')
                        ->orderByRaw('COALESCE(discounted_amount, amount) desc');

                    break;

                default:

                    $query->displayOrdered();

                    break;
            }

            $packages = $query
                ->paginate(9)
                ->withQueryString();

            return view('website.groups', array_merge(
                $commonData,
                [
                    'seo' => $this->browseSeo($title, $subHeading, $breadcrumbs),
                    'browseLeaderboard' => $this->browseLeaderboard($group, $categoryModel, $subcategoryModel),
                ],
                compact(
                    'group',
                    'title',
                    'subHeading',
                    'categoryModel',
                    'subcategoryModel',
                    'packages',
                    'sort',
                    'availableTags',
                    'hasPaidPackages',
                    'breadcrumbs'
                )
            ));
        }

        /*
        |--------------------------------------------------------------------------
        | Default Page
        |--------------------------------------------------------------------------
        */

        return view('website.groups', array_merge(
            $commonData,
            ['seo' => $this->browseSeo($title, $subHeading, $breadcrumbs)],
            compact(
                'allGroups',
                'title',
                'subHeading',
                'breadcrumbs'
            )
        ));
    }

    public function courses(Request $request)
    {
        if (! subcategories_enabled()) {
            $request->query->remove('subcategory');
        }

        $commonData = $this->getCommonViewData();
        $sort = $request->input('sort', 'default');
        $allGroups = $this->tenantQuery(Group::whereHas('packages', function ($packageQuery) {
                $this->publicPackageQuery($packageQuery)->where('packages.status', 1);
            }))
            ->orderBy('group_name')
            ->get();

        $categoryPackageQuery = $this->publicPackageQuery(
            $this->tenantQuery(Package::query())
        )->where('packages.status', 1);

        if ($request->filled('group')) {
            $categoryPackageQuery->whereHas('groups', function ($groupQuery) use ($request) {
                $groupQuery->where('groups.id', $request->input('group'));
            });
        }

        $categoryIds = $categoryPackageQuery
            ->whereNotNull('category_level_1')
            ->distinct()
            ->pluck('category_level_1');

        $allCategories = $this->tenantQuery(Category::whereNull('parent_id'))
            ->where('status', 1)
            ->whereIn('id', $categoryIds)
            ->orderBy('title')
            ->get();

        $childCategories = collect();

        if ($request->filled('category')) {
            $subcategoryPackageQuery = $this->publicPackageQuery(
                $this->tenantQuery(Package::query())
            )
                ->where('packages.status', 1)
                ->where('category_level_1', $request->input('category'));

            if ($request->filled('group')) {
                $subcategoryPackageQuery->whereHas('groups', function ($groupQuery) use ($request) {
                    $groupQuery->where('groups.id', $request->input('group'));
                });
            }

            $subcategoryIds = $subcategoryPackageQuery
                ->whereNotNull('category_level_2')
                ->distinct()
                ->pluck('category_level_2');

            $childCategories = $this->tenantQuery(Category::whereNotNull('parent_id'))
                ->where('status', 1)
                ->whereIn('id', $subcategoryIds)
                ->orderBy('title')
                ->get();
        }

        $availableTags = $this->availablePackageTags();

        $query = $this->publicPackageQuery(
            $this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()]))
        )->where('status', 1); // Only show active packages

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('groups', function ($groupQuery) use ($search) {
                        $groupQuery->where('group_name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('tags', function ($tagQuery) use ($search) {
                        $tagQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('exams', function ($examQuery) use ($search) {
                        $examQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('group')) {
            $query->whereHas('groups', function ($q) use ($request) {
                $q->where('group_id', $request->group);
            });
        }
        if ($request->filled('category')) {
            $query->where('category_level_1', $request->category);
        }
        if ($request->filled('subcategory')) {
            $query->where('category_level_2', $request->subcategory);
        }
        $this->applyPackageTagFilter($query, $request);

        $hasPaidPackages = (clone $query)
            ->where('package_type', 'paid')
            ->exists();

        if (! $hasPaidPackages && in_array($sort, ['price_low_high', 'price_high_low'], true)) {
            $sort = 'default';
        }

        if ($request->filled('price') && $request->price != 'all') {
            $query->where('package_type', $request->price);
        }

        switch ($sort) {
            case 'name_asc': $query->orderBy('name', 'asc'); break;
            case 'name_desc': $query->orderBy('name', 'desc'); break;
            case 'price_low_high': $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) asc'); break;
            case 'price_high_low': $query->where('package_type', 'paid')->orderByRaw('COALESCE(discounted_amount, amount) desc'); break;
            default: $query->displayOrdered(); break; // Default hierarchy order
        }

        $packages = $query->paginate(9)->withQueryString();
        $seo = [
            'title' => 'Explore Exam Packages',
            'description' => 'Browse mock tests, previous year papers and exam preparation packages. Search by exam, group, category, free package or paid package.',
            'canonical' => route('courses.index'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Explore Exam Packages',
                'description' => 'Browse online exam preparation packages, mock tests and previous year papers.',
            ],
        ];
        $viewData = array_merge($commonData, compact('packages', 'sort', 'allGroups', 'allCategories', 'childCategories', 'availableTags', 'hasPaidPackages', 'seo'));
        return view('website.courses', $viewData); 
    }

    public function coursesdetail($id)
    {

        $query = $this->publicPackageQuery($this->tenantQuery(Package::with([
            'groups',
            'tags',
        ])))->where('status', 1);

        $package = is_numeric($id)
            ? $query->where('id', $id)->firstOrFail()
            : $query->where('slug', $id)->firstOrFail();

        $orderedExamIds = $this->orderedPackageExamIds($package);
        $packageExamCount = $orderedExamIds->count();
        $initialExams = $this->loadPackageExamBatch($orderedExamIds, 0, max(1, $packageExamCount));
        $loadedExamCount = $initialExams->count();
        $this->ensureExamSlugs($initialExams);
        $package->setRelation('exams', $initialExams);
        $examTypeLabels = Exam::testTypeLabels();
        $packageExamsByType = $initialExams
            ->groupBy(fn ($exam) => $this->resolvedPackageExamTestType($exam, $package));
        $pypHubAvailable = app(\App\Services\PypContentService::class)->enabledFor($package);

        $id = $package->id;

        $commonData = $this->getCommonViewData();
        //$package = Package::with(['exams', 'groups'])->where('status', 1)->findOrFail($id); // Ensure package is active
        $enrollmentQuery = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.package_id', $package->id)
            ->whereNotNull('orders.student_id')
            ->where('orders.status', 'completed');

        if (Schema::hasColumn('orders', 'organization_id') && $package->organization_id) {
            $enrollmentQuery->where('orders.organization_id', $package->organization_id);
        }

        $enrollmentCount = $enrollmentQuery
            ->distinct('orders.student_id')
            ->count('orders.student_id');
        $similarPackages = collect();
        $packageLeaderboard = collect();
        $flashcardSets = collect();
        $flashcardCardCount = 0;
        $groupId = null;
        if ($package->groups->isNotEmpty()) {
            $groupId = $package->groups->first()->id;
            $similarPackages = $this->publicPackageQuery($this->tenantQuery(Package::with(['groups', 'tags', 'category', 'subcategory'])->withCount(['exams' => fn ($examQuery) => $examQuery->active()])->whereHas('groups', function ($query) use ($groupId) {
                $query->where('group_id', $groupId);
            })))
            ->where('id', '!=', $id)
            ->where('status', 1) // Only active similar packages
            ->latest()
            ->take(12)
            ->get();
        }

        $examIds = $orderedExamIds;
        if ($examIds->isNotEmpty()) {
            $packageLeaderboard = ExamResult::query()
                ->join('students', 'exam_results.student_id', '=', 'students.id')
                ->whereIn('exam_results.exam_id', $examIds)
                ->whereNotNull('exam_results.student_id')
                ->whereNotNull('exam_results.percent')
                ->whereNotNull('exam_results.end_time')
                ->when(request('leaderboard_period', 'year') !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
                ->when($this->tenantId(), function ($query, $tenantId) {
                    $query->where('exam_results.organization_id', $tenantId)
                        ->where('students.organization_id', $tenantId);
                })
                ->select(
                    'exam_results.student_id',
                    'students.name',
                    'students.photo',
                    DB::raw('MAX(exam_results.percent) as best_percent'),
                    DB::raw('MAX(exam_results.end_time) as last_attempt')
                )
                ->groupBy('exam_results.student_id', 'students.name', 'students.photo')
                ->orderByDesc('best_percent')
                ->orderByDesc('last_attempt')
                ->take(5)
                ->get();
        }

        if (SaasAccess::featureEnabled('flashcards') && (bool) ($package->flashcards_enabled ?? false)) {
            $flashcardSets = $package->flashcardSets()
                ->where('status', true)
                ->whereHas('cards', fn ($query) => $query->where('status', true))
                ->withCount(['cards' => fn ($query) => $query->where('status', true)])
                ->orderBy('title')
                ->get();
            $flashcardCardCount = (int) $flashcardSets->sum('cards_count');
        }

        $packageName = $this->displayText($package->name);
        $packageDescription = $this->displayText($package->meta_description ?: $package->description);
        $packageSchema = $package->seo_schema ?: [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $packageName,
            'description' => Str::limit(strip_tags($packageDescription ?: $packageName), 240, ''),
            'provider' => [
                '@type' => 'Organization',
                'name' => $commonData['configuration_detail']->name ?? config('app.name', 'ExamElite'),
                'url' => route('home'),
            ],
            'hasCourseInstance' => [
                '@type' => 'CourseInstance',
                'courseMode' => 'online',
                'name' => $packageName,
            ],
        ];
        $seo = [
            'title' => $this->displayText($package->meta_title) ?: $packageName,
            'description' => $packageDescription ?: 'Practice ' . $packageName . ' mock tests and previous year papers online.',
            'keywords' => $this->displayText($package->meta_keywords),
            'canonical' => $package->canonical_url ?: route('courses.detail', $package->slug ?: $package->id),
            'robots' => $package->robots_meta ?: 'index,follow',
            'og_title' => $this->displayText($package->og_title) ?: $packageName,
            'og_description' => $this->displayText($package->og_description) ?: $packageDescription,
            'image' => $package->og_image ? 'storage/' . $package->og_image : null,
            'schema' => $packageSchema,
        ];

        $viewData = array_merge($commonData, compact('package', 'packageExamCount', 'loadedExamCount', 'examTypeLabels', 'packageExamsByType', 'pypHubAvailable', 'enrollmentCount', 'similarPackages', 'groupId', 'packageLeaderboard', 'flashcardSets', 'flashcardCardCount', 'seo'));
        return view('website.course-detail', $viewData); 
    }

    public function courseExams(Request $request, $id)
    {
        $packageQuery = $this->publicPackageQuery($this->tenantQuery(Package::with('groups')))
            ->where('status', 1);

        $package = is_numeric($id)
            ? $packageQuery->where('id', $id)->firstOrFail()
            : $packageQuery->where('slug', $id)->firstOrFail();

        $offset = max(0, $request->integer('offset'));
        $limit = 20;
        $orderedExamIds = $this->orderedPackageExamIds($package);
        $exams = $this->loadPackageExamBatch($orderedExamIds, $offset, $limit);
        $this->ensureExamSlugs($exams);

        $groupId = $package->groups->first()?->id;
        $allowGuestExamAttempts = getConfiguration()->allow_guest_exam_attempts ?? true;
        $html = $exams->values()->map(function ($exam, $index) use ($package, $groupId, $allowGuestExamAttempts, $offset) {
            return view('website.partials.course-exam-row', [
                'exam' => $exam,
                'examIndex' => $offset + $index + 1,
                'examDisplayName' => $this->displayText($exam->name),
                'package' => $package,
                'groupId' => $groupId,
                'allowGuestExamAttempts' => $allowGuestExamAttempts,
            ])->render();
        })->implode('');

        $loaded = min($offset + $exams->count(), $orderedExamIds->count());

        return response()->json([
            'html' => $html,
            'loaded' => $loaded,
            'total' => $orderedExamIds->count(),
            'has_more' => $loaded < $orderedExamIds->count(),
        ]);
    }

    public function guestFlashcards(Request $request, $id)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $query = $this->publicPackageQuery($this->tenantQuery(Package::with([
            'flashcardSets' => function ($query) {
                $query->where('status', true)
                      ->whereHas('cards', fn ($cardQuery) => $cardQuery->where('status', true))
                      ->with([
                          'group:id,group_name',
                          'category:id,title',
                          'subcategory:id,title',
                          'subject:id,subject_name,ordering',
                          'topic:id,name,display_order',
                          'stopic:id,name,display_order',
                          'cards' => fn ($cardQuery) => $cardQuery->where('status', true)
                              ->with([
                                  'sourceQuestion.qtype:id,type,question_type',
                                  'sourceQuestion.diff:id,diff_level',
                                  'sourceQuestions.qtype:id,type,question_type',
                                  'sourceQuestions.diff:id,diff_level',
                                  'checks' => fn ($checkQuery) => $checkQuery->where('status', true)->orderBy('sort_order'),
                              ])
                              ->orderBy('sort_order')
                              ->orderBy('id'),
                      ])
                      ->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')
                      ->orderBy('display_order')
                      ->orderBy('title');
            },
        ])))->where('status', 1)
            ->where('flashcards_enabled', true)
            ->where('guest_flashcards_enabled', true);

        $package = is_numeric($id)
            ? $query->where('id', $id)->firstOrFail()
            : $query->where('slug', $id)->firstOrFail();

          $commonData = $this->getCommonViewData();
          $primarySet = $package->flashcardSets->first();
          $packageTitle = $this->displayText($package->name);
          $student = Auth::guard('student')->user();
          $guestId = $this->ensureGuestTrackingId($request);

          app(FlashcardQuestionRotationService::class)->attachDisplayQuestions(
              $package->flashcardSets->flatMap(fn ($set) => $set->cards),
              $student?->id,
              $student ? null : $guestId,
              $package->organization_id
          );

          $studyIndexHierarchy = FlashcardStudyHierarchy::build($package->flashcardSets);
          $studyFilter = [
              'section' => $request->query('study_section') ?: null,
              'topic' => $request->query('study_topic') ?: null,
              'subtopic' => $request->query('study_subtopic') ?: null,
          ];
          $hasStudyFilter = collect($studyFilter)->filter(fn ($value) => filled($value))->isNotEmpty();
          $activeStudySets = $hasStudyFilter
              ? FlashcardStudyHierarchy::filterSets($package->flashcardSets, $studyFilter['section'], $studyFilter['topic'], $studyFilter['subtopic'])
              : collect();
          $studyHierarchy = $hasStudyFilter ? FlashcardStudyHierarchy::build($activeStudySets) : collect();
          $activeStudyTitle = $hasStudyFilter
              ? FlashcardStudyHierarchy::selectionTitle($studyIndexHierarchy, $studyFilter['section'], $studyFilter['topic'], $studyFilter['subtopic'])
              : null;

          StudentActivityTracker::track($student ? StudentActivityTracker::STUDY_CARDS_OPENED : StudentActivityTracker::GUEST_STUDY_CARDS_OPENED, [
              'student_id' => $student?->id,
              'guest_id' => $student ? null : $guestId,
              'source' => $student ? 'student' : 'guest',
              'organization_id' => $package->organization_id,
              'package_id' => $package->id,
              'metadata' => [
                  'package_name' => $packageTitle,
                  'sets' => $package->flashcardSets->count(),
                  'cards' => $package->flashcardSets->sum(fn ($set) => $set->cards->count()),
              ],
          ], $request);

          $seo = [
              'title' => $primarySet?->meta_title ?: ($primarySet?->og_title ?: $packageTitle . ' Study Cards'),
              'description' => $primarySet?->meta_description ?: ($primarySet?->og_description ?: 'Study cards for ' . $packageTitle . '.'),
              'keywords' => $primarySet?->meta_keywords,
              'robots' => $primarySet?->robots_meta ?: 'index,follow',
              'canonical' => $primarySet?->canonical_url ?: route('website.flashcards.show', $package->slug ?: $package->id),
              'og_title' => $primarySet?->og_title ?: ($primarySet?->meta_title ?: $packageTitle . ' Study Cards'),
              'og_description' => $primarySet?->og_description ?: ($primarySet?->meta_description ?: 'Study cards for ' . $packageTitle . '.'),
              'og_image' => $primarySet?->og_image,
              'schema' => $primarySet?->seo_schema,
          ];

          if ($request->query('leaderboard_period', 'year') === 'all') {
              $learningLeaders = StudentFlashcardPoint::query()
                  ->with('student:id,name,email,photo')
                  ->where('package_id', $package->id)
                  ->selectRaw('student_id, sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                  ->groupBy('student_id');
          } else {
              $learningLeaders = StudentFlashcardPointEvent::query()
                  ->with('student:id,name,email,photo')
                  ->where('package_id', $package->id)
                  ->whereYear('occurred_at', now()->year)
                  ->selectRaw('student_id, sum(points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                  ->groupBy('student_id');
          }

          $learningLeaders = $learningLeaders
              ->orderByDesc('total_points')
              ->orderByDesc('correct_answers')
              ->limit(10)
              ->get();

        return view('website.flashcards', array_merge($commonData, compact('package', 'seo', 'learningLeaders', 'studyHierarchy', 'studyIndexHierarchy', 'studyFilter', 'hasStudyFilter', 'activeStudyTitle')));
    }

    public function flashcardLeaderboard(Request $request, $id)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');
        $query = $this->publicPackageQuery($this->tenantQuery(Package::query()))
            ->where('status', 1)
            ->where('flashcards_enabled', true)
            ->where('guest_flashcards_enabled', true);
        $package = is_numeric($id)
            ? $query->where('id', $id)->firstOrFail()
            : $query->where('slug', $id)->firstOrFail();

        if ($request->query('leaderboard_period', 'year') === 'all') {
            $leaders = StudentFlashcardPoint::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->selectRaw('student_id, sum(total_points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                ->groupBy('student_id');
        } else {
            $leaders = StudentFlashcardPointEvent::query()
                ->with('student:id,name,email,photo')
                ->where('package_id', $package->id)
                ->whereYear('occurred_at', now()->year)
                ->selectRaw('student_id, sum(points) as total_points, sum(cards_studied) as cards_studied, sum(correct_answers) as correct_answers')
                ->groupBy('student_id');
        }

        $leaders = $leaders->orderByDesc('total_points')
            ->orderByDesc('correct_answers')
            ->limit(10)
            ->get();

        return view('website.partials.flashcard_leaderboard_rows', compact('leaders'));
    }
    public function reportFlashcard(Request $request, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:800'],
            'report_type' => ['nullable', 'string', 'max:80'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:190'],
        ]);

        $flashcard->load(['set.package', 'set.subject', 'sourceQuestion', 'sourceQuestions']);

        $set = $flashcard->set;
        $package = $set?->package;
        $tenantId = $this->tenantId();

        abort_if(
            ! $set || ! $package || ! $set->status || ! $flashcard->status ||
            ! $package->flashcards_enabled || ! $package->guest_flashcards_enabled,
            404
        );

        abort_if($tenantId && (int) $package->organization_id !== (int) $tenantId, 404);

        $question = $flashcard->sourceQuestion ?: $flashcard->sourceQuestions->first();
        $student = Auth::guard('student')->user();
        $guestId = $this->ensureGuestTrackingId($request);

        QuestionsReport::create([
            'organization_id' => $tenantId ?: $package->organization_id,
            'guest_id' => $student ? null : $guestId,
            'guest_name' => $data['guest_name'] ?? null,
            'guest_email' => $data['guest_email'] ?? null,
            'student_id' => $student?->id,
            'question_id' => $question?->id,
            'flashcard_id' => $flashcard->id,
            'flashcard_set_id' => $set->id,
            'report_source' => 'flashcard',
            'subject_id' => $question?->subject_id ?: $set?->subject_id,
            'question_type' => $data['report_type'] ?: 'Study Card Issue',
            'message' => $data['message'] ?? '',
            'status' => 'Pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Study card report submitted.',
        ]);
    }

    public function trackGuestFlashcard(Request $request, Flashcard $flashcard)
    {
        SaasAccess::abortIfFeatureDisabled('flashcards');

        $data = $request->validate([
            'event' => ['required', 'in:view,answer,complete'],
            'correct' => ['nullable', 'boolean'],
            'question_id' => ['nullable', 'integer'],
        ]);

        $flashcard->load(['set.package', 'sourceQuestion', 'sourceQuestions']);

        $set = $flashcard->set;
        $package = $set?->package;
        $tenantId = $this->tenantId();

        abort_if(
            ! $set || ! $package || ! $set->status || ! $flashcard->status ||
            ! $package->flashcards_enabled || ! $package->guest_flashcards_enabled,
            404
        );

        abort_if($tenantId && (int) $package->organization_id !== (int) $tenantId, 404);

        $student = Auth::guard('student')->user();
        $guestId = $this->ensureGuestTrackingId($request);

        $eventName = match ($data['event']) {
            'answer' => $student ? StudentActivityTracker::STUDY_CARD_ANSWERED : StudentActivityTracker::GUEST_STUDY_CARD_ANSWERED,
            'complete' => $student ? StudentActivityTracker::STUDY_CARD_COMPLETED : StudentActivityTracker::GUEST_STUDY_CARD_COMPLETED,
            default => $student ? StudentActivityTracker::STUDY_CARDS_OPENED : StudentActivityTracker::GUEST_STUDY_CARDS_OPENED,
        };

        $questionId = ! empty($data['question_id']) ? (int) $data['question_id'] : null;

        if ($data['event'] === 'answer' && $questionId) {
            app(FlashcardQuestionRotationService::class)->recordAttempt(
                $flashcard,
                $questionId,
                (bool) ($data['correct'] ?? false),
                $student?->id,
                $student ? null : $guestId,
                $package->organization_id
            );
        }

        StudentActivityTracker::track($eventName, [
            'student_id' => $student?->id,
            'guest_id' => $student ? null : $guestId,
            'source' => $student ? 'student' : 'guest',
            'organization_id' => $package->organization_id,
            'package_id' => $package->id,
            'metadata' => [
                'flashcard_set_id' => $set->id,
                'flashcard_id' => $flashcard->id,
                'question_id' => $questionId,
                'correct' => (bool) ($data['correct'] ?? false),
            ],
        ], $request);

        return response()->json(['success' => true]);
    }

    private function ensureGuestTrackingId(Request $request): string
    {
        $guestId = $request->session()->get('guest_id') ?: $request->cookie('guest_id') ?: (string) Str::uuid();

        $request->session()->put('guest_id', $guestId);

        return $guestId;
    }

    private function orderedPackageExamIds(Package $package)
    {
        $examSummaries = $package->exams()
            ->where('exams.status', 'Active')
            ->select(['exams.id', 'exams.name', 'exams.created_at'])
            ->get();

        return ExamDisplayOrder::newestYearFirst($examSummaries)
            ->pluck('id')
            ->map(fn ($examId) => (int) $examId)
            ->values();
    }

    private function resolvedPackageExamTestType(Exam $exam, Package $package): string
    {
        $type = (string) ($exam->test_type ?: Exam::TEST_TYPE_OTHER);
        if ($type !== Exam::TEST_TYPE_OTHER && array_key_exists($type, Exam::testTypeLabels())) {
            return $type;
        }

        $searchableName = Str::lower(
            $this->displayText($exam->name ?? '') . ' ' . $this->displayText($package->name ?? '')
        );

        return match (true) {
            Str::contains($searchableName, ['subtopic', 'sub-topic']) => Exam::TEST_TYPE_SUBTOPIC,
            Str::contains($searchableName, ['topic test', 'topic-wise', 'topic wise']) => Exam::TEST_TYPE_TOPIC,
            Str::contains($searchableName, ['subject test', 'subject-wise', 'subject wise']) => Exam::TEST_TYPE_SUBJECT,
            Str::contains($searchableName, ['full-length', 'full length']) => Exam::TEST_TYPE_FULL_LENGTH,
            Str::contains($searchableName, ['previous year', 'previous-year', 'pyp']) => Exam::TEST_TYPE_PREVIOUS_YEAR,
            default => Exam::TEST_TYPE_OTHER,
        };
    }

    private function loadPackageExamBatch($orderedExamIds, int $offset, int $limit)
    {
        $batchIds = $orderedExamIds->slice($offset, $limit)->values();
        if ($batchIds->isEmpty()) {
            return collect();
        }

        $examsById = Exam::query()
            ->active()
            ->whereIn('id', $batchIds)
            ->with([
                'testSubject:id,subject_name',
                'testTopic:id,subject_id,name',
                'testSubtopic:id,subject_id,topic_id,name',
            ])
            ->withCount('questions')
            ->get()
            ->keyBy('id');

        return $batchIds
            ->map(fn ($examId) => $examsById->get($examId))
            ->filter()
            ->values();
    }

    private function ensureExamSlugs($exams): void
    {
        foreach ($exams as $exam) {
            if (!empty($exam->slug)) {
                continue;
            }

            $baseSlug = Str::slug($exam->name ?: 'exam-' . $exam->id) ?: 'exam-' . $exam->id;
            $slug = $baseSlug;
            $counter = 2;

            while (Exam::where('slug', $slug)->where('id', '!=', $exam->id)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            $exam->forceFill(['slug' => $slug])->save();
        }
    }

    public function forInstitutes()
    {
        $commonData = $this->getCommonViewData();
        $tenantId = $this->tenantId() ?: $this->defaultTenantId();

        $saasPage = WebsitePage::query()
            ->when($tenantId && Schema::hasColumn('website_pages', 'organization_id'), function ($query) use ($tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->get()
            ->first(function ($page) {
                return Str::slug($this->displayText($page->short_title ?? '')) === 'for-institutes';
            });

        $pageTitle = $saasPage ? $this->displayText($saasPage->title) : 'ExamElite for Institutes';
        $pageBody = $saasPage ? $this->displayText($saasPage->description) : '';
        $hasEditablePageBody = trim(strip_tags($pageBody)) !== '';
        $pageIntro = $hasEditablePageBody
            ? '<p>Online exam platform for schools, coaching institutes, NGOs, and training teams.</p>'
            : '';

        if (trim(strip_tags($pageIntro)) === '') {
            $pageIntro = '<p>Run online exams, study cards, reports, and institute branding from one simple platform.</p>';
        }

        $landingPlans = $this->publicSaasPlans();

        $instituteRequestForm = view('website.partials.institute-request-form', [
            'landingPlans' => $landingPlans,
        ])->render();

        if ($hasEditablePageBody) {
            $pageBody = str_replace('[institute_request_form]', $instituteRequestForm, $pageBody);
        }
        return view('website.for-institutes', array_merge($commonData, [
            'saasPage' => $saasPage,
            'saasPageTitle' => $pageTitle,
            'saasPageIntro' => $pageIntro,
            'saasPageBody' => $pageBody,
            'hasEditablePageBody' => $hasEditablePageBody,
            'landingPlans' => $landingPlans,
            'instituteRequestForm' => $instituteRequestForm,
            'seo' => [
                'title' => $this->displayText($saasPage->meta_title ?? null) ?: $pageTitle,
                'description' => $this->displayText($saasPage->meta_description ?? null) ?: 'Launch a branded online exam platform for schools, NGOs, coaching institutes, and training organizations with ExamElite.',
                'keywords' => $this->displayText($saasPage->meta_keywords ?? null),
                'canonical' => $saasPage?->canonical_url ?: route('website.forInstitutes'),
                'robots' => $saasPage?->robots_meta ?: 'index,follow',
                'og_title' => $this->displayText($saasPage->og_title ?? null) ?: $pageTitle,
                'og_description' => $this->displayText($saasPage->og_description ?? null) ?: 'ExamElite institute platform for branded online exams and learning.',
                'image' => $saasPage?->og_image ? 'storage/' . $saasPage->og_image : null,
                'schema' => $saasPage?->seo_schema,
            ],
        ]));
    }

    private function publicSaasPlans(): array
    {
        return [
            [
                'name' => 'Free',
                'subtitle' => 'For NGOs and very small institutes with fewer than 50 students.',
                'badge' => 'Under 50 students',
                'price' => 'Rs. 0',
                'period' => 'forever',
                'cta' => 'Apply for Free',
                'features' => [
                    'Branded student login',
                    'Free exams and study cards',
                    'Student registration controls',
                    'Basic reports and progress tracking',
                    'Admin-managed student access',
                    'Website page controls',
                ],
            ],
            [
                'name' => 'Growth',
                'subtitle' => 'For schools and coaching teams running regular online exams.',
                'badge' => 'Most institutes',
                'price' => 'Contact us',
                'period' => 'monthly',
                'cta' => 'Request Growth Plan',
                'features' => [
                    'Everything in Free',
                    'Free and paid course options',
                    'Question bank and exam management',
                    'Student funnel tracking',
                    'Email and website settings',
                    'Question sharing tools',
                    'Study cards and learning reports',
                ],
            ],
            [
                'name' => 'Professional',
                'subtitle' => 'For larger institutes that need advanced controls and automation.',
                'badge' => 'Advanced setup',
                'price' => 'Custom',
                'period' => 'quote',
                'cta' => 'Talk to Us',
                'features' => [
                    'Everything in Growth',
                    'Custom roles and access control',
                    'AI tools when enabled',
                    'Own AI/API option',
                    'Advanced performance reports',
                    'Priority setup support',
                    'Multi-team administration',
                ],
            ],
        ];
    }
    public function storeInstituteLead(Request $request)
    {
        $validated = $request->validate([
            'preferred_plan' => ['nullable', 'string', 'max:120'],
            'institute_name' => ['required', 'string', 'max:180'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'institute_type' => ['nullable', 'string', 'max:80'],
            'expected_students' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        SaasLead::create(array_merge($validated, [
            'organization_id' => $this->tenantId() ?: $this->defaultTenantId(),
            'source_url' => $request->headers->get('referer') ?: $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'status' => 'new',
        ]));

        return back()->with('saas_lead_success', 'Thank you. Our team will contact you and help choose the right plan.');
    }
    public function contact()
    {
        $commonData = $this->getCommonViewData();
        $viewData = array_merge($commonData, []); // Add specific data if needed later
        return view('website.contact', $viewData); 
    }

    // Handles generic website pages: /pages/{slug}
    public function show($slug)
    {
        $commonData = $this->getCommonViewData();

        $page = $this->tenantQuery(WebsitePage::query())
            ->get()
            ->first(function ($page) use ($slug) {
                $shortTitle = $page->short_title;

                if (is_array($shortTitle)) {
                    $shortTitle = $shortTitle['en'] ?? reset($shortTitle) ?: '';
                }

                if (is_string($shortTitle) && str_starts_with(trim($shortTitle), '{')) {
                    $decoded = json_decode($shortTitle, true);
                    if (is_array($decoded)) {
                        $shortTitle = $decoded['en'] ?? reset($decoded) ?: $shortTitle;
                    }
                }

                return (string) $page->id === (string) $slug
                    || \Illuminate\Support\Str::slug((string) $shortTitle) === $slug;
            });

        if (!$page) {
            return redirect()->route('home');
        }

        $pageDescription = $this->displayText($page->description ?? '');
        $landingPlans = [];

        if (str_contains($pageDescription, '[institute_request_form]')) {
            $landingPlans = $this->publicSaasPlans();
            $page->description = str_replace(
                '[institute_request_form]',
                view('website.partials.institute-request-form', [
                    'landingPlans' => $landingPlans,
                ])->render(),
                $pageDescription
            );
        }

        $viewData = array_merge($commonData, compact('page', 'landingPlans'));
        return view('website.pages', $viewData);
    }


    public function examLanding($slug)
    {
        $commonData = $this->getCommonViewData();

        $exam = $this->tenantQuery(Exam::withCount('questions'))
            ->active()
            ->with(['groups'])
            ->where('slug', $slug)
            ->firstOrFail();

        $package = $this->publicPackageQuery(
            $this->tenantQuery($exam->packages()->with('groups')->where('status', 1))
        )->first();

        $examName = $this->displayText($exam->name);
        $examDescription = $this->displayText($exam->meta_description ?: $exam->instruction ?: $exam->syllabus);
        $examSchema = $exam->seo_schema ?: [
            '@context' => 'https://schema.org',
            '@type' => 'Quiz',
            'name' => $examName,
            'description' => Str::limit(strip_tags($examDescription ?: $examName), 240, ''),
            'educationalUse' => 'Practice test',
        ];

        if (is_array($examSchema) && $package) {
            $examSchema['isPartOf'] = [
                '@type' => 'Course',
                'name' => $this->displayText($package->name),
                'url' => route('courses.detail', $package->slug ?: $package->id),
            ];
        }
        $seo = [
            'title' => $this->displayText($exam->meta_title) ?: $examName,
            'description' => $examDescription ?: 'Practice ' . $examName . ' online with exam-style questions.',
            'keywords' => $this->displayText($exam->meta_keywords),
            'canonical' => $exam->canonical_url ?: route('exam.detail', $exam->slug),
            'robots' => $exam->robots_meta ?: 'index,follow',
            'og_title' => $this->displayText($exam->og_title) ?: $examName,
            'og_description' => $this->displayText($exam->og_description) ?: $examDescription,
            'image' => $exam->og_image ? 'storage/' . $exam->og_image : null,
            'schema' => $examSchema,
        ];

        $viewData = array_merge($commonData, compact('exam', 'package', 'seo'));

        return view('website.exam-detail', $viewData);
    }


}
