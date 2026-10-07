<?php

namespace App\Http\Middleware;

use App\Models\Exam;
use App\Models\Group;
use App\Models\Language;
use App\Models\Package;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResolveExamSlug
{
    public function handle(Request $request, Closure $next)
    {
        $this->resolveLanguageQuery($request);

        if ($request->routeIs('courses.index')) {
            if ($redirect = $this->redirectNumericGroupFilter($request)) {
                return $redirect;
            }

            $this->resolveGroupFilterForController($request);
        }

        if ($this->isExamRoute($request)) {
            if ($redirect = $this->redirectNumericExamUrl($request)) {
                return $redirect;
            }

            $this->resolveExamRouteParameter($request);
        }

        $response = $next($request);

        if ($request->routeIs('checkout.enroll_exam') && $request->filled('exam')) {
            $this->replaceEnrollmentRedirectWithClickedExam($request, $response);
        }

        return $response;
    }

    private function resolveLanguageQuery(Request $request): void
    {
        $lang = $request->query('lang');

        if (empty($lang) || is_numeric($lang)) {
            return;
        }

        $language = Language::enabledForOrganization($this->hostTenantId())->where('code', $lang)->first();

        if ($language) {
            $request->query->set('lang', $language->id);
            $request->merge(['lang' => $language->id]);
            $request->attributes->set('lang_code', $language->code);
        }
    }

    private function isExamRoute(Request $request): bool
    {
        return $request->routeIs(
            'guest.guideline',
            'guest.instructions',
            'guest.startExam',
            'exam.checkAttempts',
            'student.guideline',
            'student.instructions',
            'student.startExam'
        ) || Str::startsWith(trim($request->path(), '/'), 'exam-details/');
    }

    private function redirectNumericExamUrl(Request $request)
    {
        $examKey = $request->route('id');

        if (!$request->isMethod('GET') || !is_numeric($examKey)) {
            return null;
        }

        $exam = Exam::query()
            ->when($this->hostTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->find($examKey);

        if (!$exam || empty($exam->slug)) {
            return null;
        }

        if ($request->route() && $request->route()->getName()) {
            $params = $request->route()->parameters();
            $params['id'] = $exam->slug;
            return redirect()->route($request->route()->getName(), $params + $request->query(), 301);
        }

        return redirect(url('exam-details/' . $exam->slug), 301);
    }

    private function resolveExamRouteParameter(Request $request): void
    {
        $examKey = $request->route('id');

        if (empty($examKey) || is_numeric($examKey)) {
            return;
        }

        $exam = Exam::where('slug', $examKey)
            ->when($this->hostTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->first();

        if ($exam) {
            $request->route()->setParameter('id', $exam->id);
        }
    }

    private function redirectNumericGroupFilter(Request $request)
    {
        $groupKey = $request->query('group');

        if (!$request->isMethod('GET') || !is_numeric($groupKey)) {
            return null;
        }

        $group = Group::find($groupKey);

        if (!$group) {
            return null;
        }

        $query = $request->query();
        $query['group'] = $this->groupSlug($group);

        return redirect()->route('courses.index', $query, 301);
    }

    private function resolveGroupFilterForController(Request $request): void
    {
        $groupKey = $request->query('group');

        if (empty($groupKey) || is_numeric($groupKey)) {
            return;
        }

        $group = Group::whereHas('packages')
            ->when($this->hostTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->get()
            ->first(function ($item) use ($groupKey) {
            return $this->groupSlug($item) === $groupKey;
        });

        if ($group) {
            $request->merge(['group' => $group->id]);
        }
    }

    private function replaceEnrollmentRedirectWithClickedExam(Request $request, $response): void
    {
        if (!method_exists($response, 'getContent')) {
            return;
        }

        $data = json_decode($response->getContent(), true);

        if (!is_array($data) || empty($data['redirectUrl'])) {
            return;
        }

        $exam = $this->resolvePackageExam($request->input('id'), $request->input('exam'));

        if (!$exam) {
            return;
        }

        $routeName = auth('student')->check() ? 'student.guideline' : 'guest.guideline';
        $data['redirectUrl'] = route($routeName, ['id' => $exam->slug ?: $exam->id]);
        $response->setContent(json_encode($data));
    }

    private function resolvePackageExam($packageId, $examKey): ?Exam
    {
        if (empty($packageId) || empty($examKey)) {
            return null;
        }

        $package = Package::where('id', $packageId)
            ->where('status', 1)
            ->when($this->hostTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->first();

        if (!$package) {
            return null;
        }

        return $package->exams()
            ->where(function ($query) {
                $this->activeExamStatusQuery($query);
            })
            ->where(function ($query) use ($examKey) {
                if (is_numeric($examKey)) {
                    $query->where('exams.id', $examKey);
                }

                $query->orWhere('exams.slug', $examKey);
            })
            ->first();
    }

    private function activeExamStatusQuery($query): void
    {
        $query->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']);
    }

    private function groupSlug(Group $group): string
    {
        $decoded = json_decode($group->group_name, true);
        $name = is_array($decoded) ? ($decoded['en'] ?? reset($decoded)) : $group->group_name;

        return Str::slug($name ?: 'group-' . $group->id);
    }

    private function hostTenantId(): ?int
    {
        return class_exists(Tenant::class) ? Tenant::hostId(request()->getHost()) : null;
    }
}
