<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Configuration;
use Carbon\Carbon;

use App\Models\ExamResult;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Group;

/**
 * Hamesha current user ke group IDs laane ka safe helper.
 * Kabhi null ya error nahi dega. Agar kuch bhi na mile to [] return karega.
 */
if (! function_exists('getUserGroupIds')) {
    function getUserGroupIds(): array
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return [];
            }

            // Relation ke through
            $ids = [];

            if (method_exists($user, 'groups')) {
                // ✅ FIX: 'id' ki jagah 'groups.id' kiya taaki ambiguous column error na aaye
                $ids = $user->groups()->pluck('groups.id')->toArray();
            }

            // Property ke through (agar loaded ho)
            if (property_exists($user, 'groups') && $user->groups) {
                $ids = array_merge($ids, collect($user->groups)->pluck('id')->all());
            }

            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        } catch (\Throwable $e) {
            // Error hone par empty array return karega
            return [];
        }
    }
}

/**
 * Return only the permission-role ID assigned to the current administrator.
 * Permission roles and exam-content groups are intentionally separate concepts.
 */
if (! function_exists('getUserPermissionRoleIds')) {
    function getUserPermissionRoleIds(): array
    {
        $roleId = (int) (Auth::user()?->ugroup_id ?? 0);

        return $roleId > 0 ? [$roleId] : [];
    }
}

/**
 * Safe count wrapper — agar query fail ho jaye to default return kare.
 */
if (! function_exists('safeCount')) {
    function safeCount(callable $cb, int $default = 0): int
    {
        try {
            return (int) $cb();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

/**
 * Safe collect wrapper — kabhi null return nahi karega, hamesha Collection dega.
 */
if (! function_exists('safeCollect')) {
    function safeCollect(callable $cb)
    {
        try {
            $res = $cb();
            return $res instanceof \Illuminate\Support\Collection ? $res : collect($res ?? []);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}

/**
 * App configuration loader (configurations table se) + cache.
 */
if (! function_exists('getConfiguration')) {
    function getConfiguration(): Configuration
    {
        $request = app()->bound('request') ? request() : null;
        $tenantId = null;
        $hasOrganizationColumn = false;

        try {
            if (class_exists(\App\Support\Tenant::class)) {
                $schemaMemoKey = '_examelite.schema.configurations.organization_id';
                $hasOrganizationColumn = $request?->attributes->has($schemaMemoKey)
                    ? (bool) $request->attributes->get($schemaMemoKey)
                    : \Schema::hasColumn('configurations', 'organization_id');
                $request?->attributes->set($schemaMemoKey, $hasOrganizationColumn);

                if ($hasOrganizationColumn) {
                    $tenantId = \App\Support\Tenant::hostId($request?->getHost() ?? '') ?: \App\Support\Tenant::id();
                }
            }
        } catch (\Throwable $e) {
            // The legacy configuration fallback below remains available during setup.
        }

        $memoKey = '_examelite.configuration.'.($tenantId ?: 'platform');
        $memoized = $request?->attributes->get($memoKey);
        if ($memoized instanceof Configuration) {
            return $memoized;
        }

        $remember = static function (Configuration $configuration) use ($request, $memoKey): Configuration {
            $request?->attributes->set($memoKey, $configuration);

            return $configuration;
        };

        $query = Configuration::query();

        try {
            if ($hasOrganizationColumn && $tenantId !== null) {
                $tenantConfig = (clone $query)
                    ->where('organization_id', $tenantId)
                    ->first();

                if ($tenantConfig) {
                    return $remember($tenantConfig);
                }
            }
        } catch (\Throwable $e) {
            // Fallback to legacy configuration below.
        }

        $configuration = ($hasOrganizationColumn ? null : Configuration::query()->first()) ?? new Configuration([
            'name'        => config('app.name', 'Tech4Learn'),
            'domain_name' => config('app.url'),
            'email'       => config('mail.from.address'),
        ]);

        return $remember($configuration);
    }
}

/**
 * Chhota accessor: configuration('key', $default)
 */
if (! function_exists('configuration')) {
    function configuration(string $key, $default = null)
    {
        $conf = getConfiguration();
        return isset($conf->$key) && !is_null($conf->$key) ? $conf->$key : $default;
    }
}

if (! function_exists('subcategories_enabled')) {
    function subcategories_enabled(): bool
    {
        try {
            return (bool) configuration('subcategories_enabled', true);
        } catch (\Throwable $e) {
            return true;
        }
    }
}

if (! function_exists('audit_log')) {
    function audit_log(string $action, $auditable = null, array $metadata = []): void
    {
        try {
            \App\Models\AuditLog::create([
                'organization_id' => class_exists(\App\Support\SaasAccess::class) ? \App\Support\SaasAccess::organization()?->id : null,
                'user_id' => \Illuminate\Support\Facades\Auth::guard('web')->id(),
                'action' => $action,
                'auditable_type' => is_object($auditable) ? get_class($auditable) : null,
                'auditable_id' => is_object($auditable) ? ($auditable->id ?? null) : null,
                'metadata' => $metadata ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }
}

if (! function_exists('user_can_route_action')) {
    function user_can_route_action(string $routeName, string $action = 'view'): bool
    {
        try {
            $user = Auth::user();

            if (\App\Support\VerifiedPlatformAccess::allowed(request(),$user)) {
                return true;
            }

            if (! $user) {
                return false;
            }

            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            if (class_exists(\App\Support\SaasAccess::class)) {
                $organization = \App\Support\SaasAccess::organization();

                if ($organization && \Illuminate\Support\Facades\Schema::hasTable('organization_users')) {
                    $organizationRole = \Illuminate\Support\Facades\DB::table('organization_users')
                        ->where('organization_id', $organization->id)
                        ->where('user_id', $user->id)
                        ->where('status', 1)
                        ->value('role');

                    if (in_array($organizationRole, ['owner', 'admin'], true)) {
                        return true;
                    }
                }
            }

            $groupIds = function_exists('getUserPermissionRoleIds') ? getUserPermissionRoleIds() : [];

            if (empty($groupIds) || ! \Illuminate\Support\Facades\Schema::hasTable('pages')) {
                return false;
            }

            $page = \App\Models\Page::whereIn('action_name', route_permission_candidates($routeName))->first();

            if (! $page) {
                return false;
            }

            $rightColumn = [
                'view' => 'view_right',
                'add' => 'add_right',
                'create' => 'add_right',
                'store' => 'add_right',
                'import' => 'add_right',
                'export' => 'add_right',
                'download' => 'add_right',
                'edit' => 'edit_right',
                'update' => 'edit_right',
                'delete' => 'delete_right',
                'destroy' => 'delete_right',
            ][$action] ?? 'view_right';

            return \App\Models\PageRights::where('page_id', $page->id)
                ->whereIn('ugroup_id', $groupIds)
                ->where('view_right', 1)
                ->when($rightColumn !== 'view_right', function ($query) use ($rightColumn) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('page_rights', $rightColumn)) {
                        $query->where($rightColumn, 1);
                    }
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (! function_exists('route_permission_candidates')) {
    function route_permission_candidates(string $routeName): array
    {
        $candidates = [$routeName];

        foreach (['.create', '.store', '.edit', '.update', '.destroy', '.show'] as $suffix) {
            if (str_ends_with($routeName, $suffix)) {
                $candidates[] = substr($routeName, 0, -strlen($suffix)) . '.index';
            }
        }

        $prefix = explode('.', $routeName)[0] ?? null;

        if ($prefix) {
            $moduleFallbacks = [
                'questions_langs' => 'questions.index',
                'pagerights' => 'ugroups.index',
                'subjects' => 'subjects.index',
                'topics' => 'topics.index',
                'stopics' => 'stopics.index',
                'packages' => 'packages.index',
                'exams' => 'exams.index',
                'students' => 'students.index',
                'configurations' => 'configurations.general',
                'website' => 'configurations.website',
                'ai' => 'ai.generator.form',
                'ai-regenerator' => 'ai.generator.form',
                'ai-content' => 'ai.generator.form',
                'subjective-upload' => 'ai.generator.form',
            ];

            $candidates[] = $moduleFallbacks[$prefix] ?? $prefix . '.index';
        }

        return array_values(array_unique($candidates));
    }
}

/**
 * Blade helper: @formatDate($dt)
 */
if (! function_exists('formatDate')) {
    function formatDate($value, string $format = 'd-m-Y H:i')
    {
        if (empty($value)) return '';
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}

if (! function_exists('updateImageSrcWithAsset')) {
    /**
     * Finds all <img> tags in an HTML string and replaces their src attribute
     * with a proper asset() URL. This is useful for content from a WYSIWYG editor.
     *
     * @param string|null $htmlContent The HTML content from the database.
     * @return string The modified HTML with updated image paths.
     */
    function updateImageSrcWithAsset($htmlContent)
    {
        if (empty($htmlContent)) {
            return '';
        }

        $dom = new DOMDocument();
        // Use @ to suppress warnings from malformed HTML
        @$dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = $dom->getElementsByTagName('img');

        foreach ($images as $img) {
            $src = $img->getAttribute('src');

            // Check if the src is not an absolute URL (doesn't start with http/https)
            if ($src && !preg_match('/^(http|https):\/\//', $src)) {
                // Prepend the asset URL
                $newSrc = asset($src);
                $img->setAttribute('src', $newSrc);
            }
        }

        return $dom->saveHTML();
    }
}

if (! function_exists('studentAvgPercentile')) {
    /**
     * Finds all <img> tags in an HTML string and replaces their src attribute
     * with a proper asset() URL. This is useful for content from a WYSIWYG editor.
     *
     * @param string|null $htmlContent The HTML content from the database.
     * @return string The modified HTML with updated image paths.
     */
    function studentAvgPercentile($studentId)
    {
        $tenantId = class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;

        return ExamResult::where('student_id', $studentId)
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereNotNull('end_time')
            ->selectRaw('COUNT(*) as total_exams, SUM(CASE WHEN result = "Pass" THEN 1 ELSE 0 END) as best_result_count, AVG(obtained_marks) as avg_obtained_marks, AVG(percent) as avg_percentile')
            ->first();
    }
}

if (! function_exists('topSellingPackage')) {
    /**
     * Finds all <img> tags in an HTML string and replaces their src attribute
     * with a proper asset() URL. This is useful for content from a WYSIWYG editor.
     *
     * @param string|null $htmlContent The HTML content from the database.
     * @return string The modified HTML with updated image paths.
     */
    function topSellingPackage()
    {
        $tenantId = class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;

        $cacheKey = 'homepage.top-packages.'.($tenantId ?: 'platform');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenantId) {
            return OrderItem::select('order_items.package_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->when($tenantId, function ($query, $tenantId) {
                    $query->where('orders.organization_id', $tenantId);
                })
                ->selectRaw('SUM(quantity) as total_sold')
                ->groupBy('order_items.package_id')
                ->orderByDesc('total_sold')
                ->whereNotNull('order_items.package_id')
                ->take(8)
                ->pluck('order_items.package_id');
        });
    }
}

if (! function_exists('groupWisePerformers')) {
    /**
     * Build homepage leaderboard by exam group.
     *
     * Rules:
     * - logged-in students only
     * - completed attempts only
     * - valid percent only
     * - exam-to-group relation comes from exam_groups
     * - one best score per student per group
     * - percentile is calculated within each group from best scores
     */
    function groupWisePerformers(string $period = 'year')
    {
        $tenantId = class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;

        $cacheKey = 'homepage.group-performers.'.($tenantId ?: 'platform').'.'.($period === 'all' ? 'all' : 'year');

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenantId, $period) {
            $attempts = DB::table('exam_results')
            ->join('exam_groups', 'exam_results.exam_id', '=', 'exam_groups.exam_id')
            ->join('groups', 'exam_groups.group_id', '=', 'groups.id')
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId)
                    ->where('groups.organization_id', $tenantId)
                    ->where('students.organization_id', $tenantId);
            })
            ->whereNotNull('exam_results.student_id')
            ->whereNotNull('exam_results.end_time')
            ->whereNotNull('exam_results.percent')
            ->when($period !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->select(
                'exam_groups.group_id',
                'groups.group_name',
                'students.id as student_id',
                'students.name as student_name',
                'students.photo',
                'exams.name as exam_name',
                'exam_results.percent',
                'exam_results.end_time'
            )
            ->orderByDesc('exam_results.percent')
            ->orderByDesc('exam_results.end_time')
            ->get();

        if ($attempts->isEmpty()) {
            return collect();
        }

        $bestByGroupStudent = $attempts
            ->groupBy('group_id')
            ->map(function ($groupAttempts) {
                return $groupAttempts
                    ->groupBy('student_id')
                    ->map(function ($studentAttempts) {
                        return $studentAttempts
                            ->sortByDesc('end_time')
                            ->sortByDesc('percent')
                            ->first();
                    })
                    ->values();
            });

        return $bestByGroupStudent
            ->map(function ($students, $groupId) {
                $students = $students->sortByDesc('percent')->values();
                $totalStudents = max($students->count(), 1);
                $groupName = $students->first()->group_name ?? null;
                $decodedGroupName = json_decode($groupName, true);
                if (is_array($decodedGroupName)) {
                    $groupName = $decodedGroupName[app()->getLocale()] ?? $decodedGroupName['en'] ?? reset($decodedGroupName);
                }

                return [
                    'group' => [
                        'id' => $groupId,
                        'name' => $groupName,
                    ],
                    'top_performers' => $students->take(5)->map(function ($item) use ($students, $totalStudents) {
                        $belowOrEqual = $students->filter(function ($student) use ($item) {
                            return (float) $student->percent <= (float) $item->percent;
                        })->count();

                        $percentile = round(($belowOrEqual / $totalStudents) * 100, 2);

                        return [
                            'student_name' => $item->student_name,
                            'student_photo' => $item->photo,
                            'score_percent' => round((float) $item->percent, 2),
                            'percentile' => $percentile,
                            'avg_percentile' => round((float) $item->percent, 2),
                            'package_name' => null,
                            'exam_name' => $item->exam_name ?? null,
                        ];
                    })->values()
                ];
            })
            ->sortByDesc(function ($groupData) {
                return $groupData['top_performers']->first()['score_percent'] ?? 0;
            })
            ->take(5)
            ->values();
        });
    }
}

if (! function_exists('packageWisePerformers')) {

    function packageWisePerformers(string $period = 'year')
    {
        $tenantId = class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;

        // Step 1: Top Packages
        $topPackageIds = DB::table('exam_results')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->join('exam_packages', 'exams.id', '=', 'exam_packages.exam_id')
            ->join('packages', 'exam_packages.package_id', '=', 'packages.id')
            ->select('exam_packages.package_id', DB::raw('MAX(exam_results.percent) as max_percent'))
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId)
                    ->where('packages.organization_id', $tenantId);
            })
            ->whereNotNull('exam_results.percent')
            ->groupBy('exam_packages.package_id')
            ->orderByDesc('max_percent')
            ->take(5)
            ->pluck('package_id');

        // Step 2: Aggregated Results
        $results = DB::table('exam_results')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->join('exam_packages', 'exams.id', '=', 'exam_packages.exam_id')
            ->join('students', 'exam_results.student_id', '=', 'students.id')
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId)
                    ->where('students.organization_id', $tenantId);
            })
            ->whereIn('exam_packages.package_id', $topPackageIds)
            ->whereNotNull('exam_results.percent')
            ->when($period !== 'all', fn ($query) => $query->whereYear('exam_results.end_time', now()->year))
            ->select(
                'exam_packages.package_id',
                'students.id as student_id',
                'students.name as student_name',
                'students.photo',
                DB::raw('AVG(exam_results.percent) as avg_percentile'),
                DB::raw('MAX(exam_results.end_time) as last_exam_time')
            )
            ->groupBy('exam_packages.package_id', 'students.id', 'students.name', 'students.photo')
            ->orderByDesc('avg_percentile')
            ->orderByDesc('last_exam_time')
            ->get();

        // Step 3: Best Attempt Mapping
        $bestAttempts = DB::table('exam_results')
            ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
            ->join('exam_packages', 'exams.id', '=', 'exam_packages.exam_id')
            ->join('packages', 'exam_packages.package_id', '=', 'packages.id')
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('exam_results.organization_id', $tenantId)
                    ->where('exams.organization_id', $tenantId)
                    ->where('packages.organization_id', $tenantId);
            })
            ->whereIn('exam_packages.package_id', $topPackageIds)
            ->select(
                'exam_packages.package_id',
                'exam_results.student_id',
                'packages.name as package_name',
                'exams.name as exam_name',
                'exam_results.percent'
            )
            ->orderByDesc('exam_results.percent')
            ->get()
            ->groupBy(['package_id', 'student_id'])
            ->map(fn($group) => $group->map(fn($student) => $student->first()));

        // Step 4: Package Data
        $packages = Package::whereIn('id', $topPackageIds)
            ->when($tenantId, function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->where('status', 1)
            ->select('id', 'name', 'slug')
            ->get()
            ->keyBy('id');

        // Step 5: Final Format
        return collect($results)
            ->groupBy('package_id')
            ->map(function ($students, $packageId) use ($packages, $bestAttempts) {

                return [
                    'package' => $packages[$packageId] ?? null,
                    'top_performers' => $students->take(5)->map(function ($item) use ($packageId, $bestAttempts) {

                        $best = $bestAttempts[$packageId][$item->student_id] ?? null;

                        return [
                            'student_name' => $item->student_name,
                            'student_photo' => $item->photo,
                            'avg_percentile' => round($item->avg_percentile, 2),
                            'package_name' => $best->package_name ?? null,
                            'exam_name' => $best->exam_name ?? null,
                        ];
                    })->values()
                ];
            })
            ->values();
    }

}

if (! function_exists('countExamQuestions')) {

    function countExamQuestions($examId)
    {
        
        return DB::table('exam_questions')
            ->where('exam_id', $examId)->count();
    }

}

if (! function_exists('examQuestionsList')) {

    function examQuestionsList()
    {
        
        return DB::table('exam_questions')
        ->join('exams', 'exam_questions.exam_id', '=', 'exams.id')
        ->select('exam_id', 'exams.name')        
        ->distinct()
        ->orderBy('exams.name')
        ->get();
    }

}
