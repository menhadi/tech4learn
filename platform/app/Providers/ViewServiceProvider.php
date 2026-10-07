<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Page;
use App\Models\WebsitePage;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema; // <-- YEH ADD KAREIN
use Illuminate\Database\QueryException; // <-- YEH BHI ADD KAREIN
use Illuminate\Support\Facades\DB;
use App\Support\SaasAccess;

class ViewServiceProvider extends ServiceProvider
{
    public function boot()
    {
        View::composer('*', function ($view) {
            $view->with('subcategoriesEnabled', subcategories_enabled());
        });

        // ==== YEH AAPKA PURANA SIDEBAR WALA CODE HAI (YEH SAFE HAI) ====
        View::composer('layouts.sidebar', function ($view) {
            $user = Auth::user();
            $groupIds = function_exists('getUserPermissionRoleIds') ? getUserPermissionRoleIds() : [];

            if (\App\Support\VerifiedPlatformAccess::allowed(request(),$user)) {
                $pages = Page::with('children')->orderBy('ordering')->get();
            } elseif ($user && ! empty($groupIds)) {
                $pages = Page::with('children')->whereHas('pageRights', function ($query) use ($groupIds) {
                    $query->whereIn('ugroup_id', $groupIds)
                        ->where('view_right', 1);
                })->orderBy('ordering')->get();
            } else {
                $canSeeAllPages = $user && (
                    (method_exists($user, 'hasRole') && $user->hasRole('admin'))
                    || $this->isOrganizationAdmin($user)
                );

                $pages = $canSeeAllPages
                    ? Page::with('children')->orderBy('ordering')->get()
                    : collect();
            }

            if (! subcategories_enabled()) {
                $pages = $pages->reject(fn ($page) => str_starts_with((string) $page->action_name, 'subcategories'))
                    ->each(function ($page) {
                        if ($page->relationLoaded('children')) {
                            $page->setRelation('children', $page->children
                                ->reject(fn ($child) => str_starts_with((string) $child->action_name, 'subcategories'))
                                ->values());
                        }
                    })->values();
            }

            $isPlatformOwner = $user ? SaasAccess::isPlatformAdmin() : false;

            $sidebarGroups = $this->buildSidebarGroups($pages);

            $view->with('pages', $pages);
            $view->with('sidebarGroups', $sidebarGroups);
            $view->with('isPlatformOwner', $isPlatformOwner);
        });


        // ==== YEH RAHA FIX ====
        // Hum 'env' check ke bajaye try...catch block ka istemal karenge
        
        try {
            // Hum check karenge ki table 'website_pages' maujood hai ya nahi
            if (Schema::hasTable('website_pages')) {
                View::composer('*', function ($view) {
                    $organizationId = SaasAccess::organization()?->id;
                    $attributeKey = 'examelite.navbar_pages.' . ($organizationId ?: 'platform');
                    $request = request();

                    if (! $request->attributes->has($attributeKey)) {
                        $request->attributes->set($attributeKey, WebsitePage::where('show_in_menu', 1)
                            ->when($organizationId && Schema::hasColumn('website_pages', 'organization_id'), function ($query) use ($organizationId) {
                                $query->where('organization_id', $organizationId);
                            })
                            ->orderBy('title')
                            ->get());
                    }

                    $view->with('navbarPages', $request->attributes->get($attributeKey));
                });
            }
        } catch (QueryException $e) {
            // Agar database connected nahi hai (installer ke dauran),
            // toh yeh error ko pakad lega aur kuch nahi karega.
            // Isse aapka installer crash nahi hoga.
            \Illuminate\Support\Facades\Log::warning('ViewServiceProvider skipped: ' . $e->getMessage());
        }

    }

    public function register()
    {
        //
    }

    private function isOrganizationAdmin($user): bool
    {
        $organizationId = SaasAccess::organization()?->id;

        if (! $organizationId || ! Schema::hasTable('organization_users')) {
            return false;
        }

        return DB::table('organization_users')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->where('status', 1)
            ->exists();
    }

    private function buildSidebarGroups($pages)
    {
        $groups = collect([
            'overview' => ['label' => 'Overview', 'icon' => 'ri-dashboard-line', 'pages' => collect()],
            'academics' => ['label' => 'Academic Structure', 'icon' => 'ri-book-open-line', 'pages' => collect()],
            'assessments' => ['label' => 'Exams & Questions', 'icon' => 'ri-file-list-3-line', 'pages' => collect()],
            'students' => ['label' => 'Students & Results', 'icon' => 'ri-user-star-line', 'pages' => collect()],
            'commerce' => ['label' => 'Packages & Sales', 'icon' => 'ri-shopping-bag-3-line', 'pages' => collect()],
            'flashcards' => ['label' => 'Study Tools', 'icon' => 'ri-stack-line', 'pages' => collect()],
            'ai' => ['label' => 'AI Tools', 'icon' => 'ri-magic-line', 'pages' => collect()],
            'website' => ['label' => 'Website & Content', 'icon' => 'ri-global-line', 'pages' => collect()],
            'email' => ['label' => 'Email & Messaging', 'icon' => 'ri-mail-send-line', 'pages' => collect()],
            'users' => ['label' => 'Users & Permissions', 'icon' => 'ri-shield-user-line', 'pages' => collect()],
            'settings' => ['label' => 'Settings', 'icon' => 'ri-settings-4-line', 'pages' => collect()],
        ]);

        foreach ($pages as $page) {
            $key = $this->sidebarGroupKey($page->action_name, $page->page_name);
            $group = $groups->get($key, $groups->get('settings'));
            $group['pages']->push($page);
            $groups->put($key, $group);
        }

        return $groups
            ->filter(fn ($group) => $group['pages']->isNotEmpty())
            ->values();
    }

    private function sidebarGroupKey(?string $routeName, ?string $pageName): string
    {
        $routeName = (string) $routeName;
        $pageName = strtolower((string) $pageName);

        foreach ([
            'overview' => ['dashboard'],
            'academics' => ['groups', 'category', 'subcategories', 'subjects', 'topics', 'stopics', 'passages', 'sections'],
            'assessments' => ['questions', 'question-tags', 'exams', 'source-exams', 'exam-quality', 'image-converter', 'qtypes', 'diffs'],
            'flashcards' => ['flashcards'],
            'students' => ['students', 'results'],
            'users' => ['users', 'ugroups', 'pagerights'],
            'commerce' => ['packages', 'package-tags', 'coupons', 'orders', 'transactions', 'sales-reports', 'payment-gateway'],
            'website' => ['homepage-content', 'features', 'counters', 'testimonial', 'websitepages', 'configurations.website', 'website.title', 'pyp-pages'],
            'ai' => ['ai.generator', 'ai-answers', 'image-cleanup', 'configurations.ai'],
            'email' => ['email-templates', 'email-settings', 'configurations.messaging', 'send-email', 'sms-templates'],
            'settings' => ['configurations', 'languages', 'diffs', 'qtypes', 'payment-gateway'],
        ] as $group => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return $group;
                }
            }
        }

        if (str_contains($pageName, 'email') || str_contains($pageName, 'sms')) {
            return 'email';
        }

        if (str_contains($pageName, 'website') || str_contains($pageName, 'slider')) {
            return 'website';
        }

        return 'settings';
    }
}
