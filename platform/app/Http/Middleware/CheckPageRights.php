<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\PageRights;
use App\Support\SaasAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckPageRights
{
    public function handle(Request $request, Closure $next)
    {
        // Always require login
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        if (\App\Support\VerifiedPlatformAccess::allowed($request,$user)) {
            return $next($request);
        }

        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // Continue to organization/page-right checks.
        }

        $organization = SaasAccess::organization();

        if ($organization) {
            $organizationRole = DB::table('organization_users')
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->where('status', 1)
                ->value('role');

            if (in_array($organizationRole, ['owner', 'admin'], true)) {
                return $next($request);
            }
        }

        $groupIds = function_exists('getUserPermissionRoleIds') ? getUserPermissionRoleIds() : [];

        if (empty($groupIds)) {
            abort(403, 'You do not have access to this area.');
        }

        $page = Page::whereIn('action_name', $this->routeCandidates($request))->first();

        if (! $page) {
            abort(403, 'This page is not assigned to your role.');
        }

        $rightColumn = $this->rightColumn($request);
        $hasRight = PageRights::where('page_id', $page->id)
            ->whereIn('ugroup_id', $groupIds)
            ->where(function ($query) use ($rightColumn) {
                $query->where('view_right', 1);

                if ($rightColumn !== 'view_right' && Schema::hasColumn('page_rights', $rightColumn)) {
                    $query->where($rightColumn, 1);
                }
            })
            ->exists();

        if (! $hasRight) {
            abort(403, 'You do not have permission for this action.');
        }

        return $next($request);
    }

    private function routeCandidates(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return [];
        }

        $candidates = [$routeName];

        if ($routeName === 'website.title.update') {
            $candidates[] = 'homepage-content.index';
        }

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
                'sections' => 'subjects.index',
                'topics' => 'topics.index',
                'stopics' => 'stopics.index',
                'question-tags' => 'questions.index',
                'packages' => 'packages.index',
                'exams' => 'exams.index',
                'students' => 'students.index',
                'demo-students' => 'students.index',
                'configurations' => 'configurations.general',
                'website' => 'configurations.website',
                'features' => 'homepage-content.index',
                'counters' => 'homepage-content.index',
                'testimonial' => 'homepage-content.index',
                'ai' => 'ai.generator.form',
                'ai-regenerator' => 'ai.generator.form',
                'ai-content' => 'ai.generator.form',
                'subjective-upload' => 'ai.generator.form',
            ];

            $candidates[] = $moduleFallbacks[$prefix] ?? $prefix . '.index';
        }

        return array_values(array_unique($candidates));
    }

    private function rightColumn(Request $request): string
    {
        $method = strtoupper($request->method());
        $routeName = (string) optional($request->route())->getName();

        if (str_ends_with($routeName, '.destroy') || str_contains($routeName, '.remove') || str_contains($routeName, 'remove')) {
            return 'delete_right';
        }

        if (str_contains($routeName, 'import') || str_contains($routeName, 'export') || str_contains($routeName, 'downloadTemplate')) {
            return 'add_right';
        }

        if (
            str_contains($routeName, 'toggle')
            || str_ends_with($routeName, '.update')
            || str_contains($routeName, 'updateStatus')
            || str_contains($routeName, 'bulkAssignGroup')
            || str_contains($routeName, '.assign')
        ) {
            return 'edit_right';
        }

        if ($method === 'POST') {
            return 'add_right';
        }

        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return 'edit_right';
        }

        if ($method === 'DELETE') {
            return 'delete_right';
        }

        return 'view_right';
    }
}
