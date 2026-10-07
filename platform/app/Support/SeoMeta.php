<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Package;
use App\Models\WebsitePage;
use Illuminate\Support\Str;

class SeoMeta
{
    private static function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private static function tenantQuery($query)
    {
        $tenantId = self::tenantId();
        $model = $query->getModel();

        if ($tenantId && \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'organization_id')) {
            $query->where($model->getTable() . '.organization_id', $tenantId);
        }

        return $query;
    }


    public static function forCurrentRequest(): array
    {
        $route = request()->route();
        $routeName = $route?->getName();

        return match ($routeName) {
            'home' => self::home(),
            'courses.index' => self::courses(),
            'courses.detail' => self::packageDetail($route?->parameter('id')),
            'exam.detail' => self::examDetail($route?->parameter('slug')),
            'website.exams.index' => self::examGroups(
                $route?->parameter('group'),
                $route?->parameter('category'),
                $route?->parameter('subcategory')
            ),
            'page.show' => self::websitePage($route?->parameter('slug')),
            'about' => self::simple('About Us', 'Learn more about ExamElite and our online exam preparation platform.'),
            'contact' => self::simple('Contact Us', 'Contact ExamElite for support, exam preparation help, and platform information.'),
            default => self::simple(
                trim($__title = view()->shared('__seo_title', 'Online Mock Tests and Practice Exams')) ?: 'Online Mock Tests and Practice Exams',
                'Practice online mock tests, previous year papers, scholarship tests and competitive exam questions.'
            ),
        };
    }

    public static function home(): array
    {
        return self::build([
            'title' => 'Online Mock Tests, PYQs and Practice Exams',
            'description' => 'Practice online mock tests, previous year questions, scholarship tests and competitive exam papers for school, college and competitive exams.',
            'canonical' => url('/'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'ExamElite',
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/courses') . '?search={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ]);
    }

    public static function courses(): array
    {
        return self::build([
            'title' => 'Exam Packages',
            'description' => 'Explore free and paid exam packages, mock tests, previous year papers and practice exams.',
            'canonical' => url('/courses'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Exam Packages',
                'url' => url('/courses'),
            ],
        ]);
    }

    public static function packageDetail($id): array
    {
        if (empty($id)) {
            return self::courses();
        }

        $package = self::tenantQuery(Package::with(['exams', 'groups']))
            ->where('status', 1)
            ->when(is_numeric($id), fn ($q) => $q->where('id', $id), fn ($q) => $q->where('slug', $id))
            ->first();

        if (! $package) {
            return self::courses();
        }

        $name = self::text($package->name);
        $description = self::text($package->description)
            ?: "Practice {$name} with online exams, mock tests and detailed exam preparation.";

        $image = $package->photo ?: null;
        $price = (float) (($package->discounted_amount > 0) ? $package->discounted_amount : $package->amount);

        $examItems = $package->exams->values()->map(function ($exam, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => self::text($exam->name),
            ];
        })->all();

        return self::build([
            'title' => $package->meta_title ?: $name,
            'description' => $package->meta_description ?: $description,
            'keywords' => $package->meta_keywords,
            'canonical' => $package->canonical_url ?: route('courses.detail', $package->slug ?: $package->id),
            'image' => $package->og_image ?: $image,
            'og_title' => $package->og_title ?: ($package->meta_title ?: $name),
            'og_description' => $package->og_description ?: ($package->meta_description ?: $description),
            'robots' => $package->robots_meta ?: 'index,follow',
            'schema' => $package->seo_schema ?: [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Course',
                        'name' => $name,
                        'description' => $description,
                        'provider' => [
                            '@type' => 'Organization',
                            'name' => 'ExamElite',
                            'url' => url('/'),
                        ],
                    ],
                    [
                        '@type' => 'Product',
                        'name' => $name,
                        'description' => $description,
                        'image' => $image ? asset($image) : null,
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => $price,
                            'priceCurrency' => 'INR',
                            'availability' => 'https://schema.org/InStock',
                            'url' => route('courses.detail', $package->slug ?: $package->id),
                        ],
                    ],
                    [
                        '@type' => 'ItemList',
                        'name' => "{$name} Exams",
                        'itemListElement' => $examItems,
                    ],
                ],
            ],
        ]);
    }


    public static function examDetail($slug): array
    {
        if (empty($slug)) {
            return self::simple('Exam Details', 'Practice online exams and mock tests on ExamElite.');
        }

        $exam = self::tenantQuery(Exam::withCount('questions'))
            ->with(['packages', 'groups'])
            ->where('slug', $slug)
            ->first();

        if (! $exam) {
            return self::simple('Exam Details', 'Practice online exams and mock tests on ExamElite.');
        }

        $name = self::text($exam->name);
        $package = $exam->packages->first();
        $packageName = $package ? self::text($package->name) : null;

        $description = $exam->meta_description
            ?: "Practice {$name} online on ExamElite" . ($packageName ? " from {$packageName}" : '') . ". View exam details, duration, questions and start your preparation.";

        return self::build([
            'title' => $exam->meta_title ?: $name,
            'description' => $description,
            'keywords' => $exam->meta_keywords,
            'canonical' => $exam->canonical_url ?: route('exam.detail', $exam->slug),
            'image' => $exam->og_image ?: ($package->photo ?? null),
            'og_title' => $exam->og_title ?: ($exam->meta_title ?: $name),
            'og_description' => $exam->og_description ?: $description,
            'robots' => $exam->robots_meta ?: 'index,follow',
            'schema' => $exam->seo_schema ?: [
                '@context' => 'https://schema.org',
                '@type' => 'Quiz',
                'name' => $name,
                'description' => $description,
                'url' => route('exam.detail', $exam->slug),
                'educationalUse' => 'Practice exam',
                'provider' => [
                    '@type' => 'Organization',
                    'name' => 'ExamElite',
                    'url' => url('/'),
                ],
                'isPartOf' => $packageName ? [
                    '@type' => 'Course',
                    'name' => $packageName,
                    'url' => route('courses.detail', $package->slug ?: $package->id),
                ] : null,
                'timeRequired' => !empty($exam->duration) ? 'PT' . (int) $exam->duration . 'M' : null,
                'numberOfQuestions' => $exam->questions_count ?? null,
            ],
        ]);
    }


    public static function examGroups($groupSlug = null, $categorySlug = null, $subcategorySlug = null): array
    {
        $group = $groupSlug
            ? self::tenantQuery(Group::query())->get()->first(function ($item) use ($groupSlug) {
                return \Illuminate\Support\Str::slug(self::text($item->group_name ?? '')) === $groupSlug;
            })
            : null;
        $category = $categorySlug ? self::tenantQuery(Category::where('slug', $categorySlug))->first() : null;
        $subcategory = $subcategorySlug ? self::tenantQuery(Category::where('slug', $subcategorySlug))->first() : null;

        if ($subcategory) {
            $title = $subcategory->meta_title ?: self::text($subcategory->title ?? $subcategory->name) . ($group ? ' - ' . self::text($group->group_name) : '');
            $description = $subcategory->meta_description ?: "Explore " . self::text($subcategory->title ?? $subcategory->name) . " exam packages, practice tests and mock exams.";
            $model = $subcategory;
        } elseif ($category) {
            $title = $category->meta_title ?: self::text($category->title ?? $category->name) . ($group ? ' - ' . self::text($group->group_name) : '');
            $description = $category->meta_description ?: "Explore " . self::text($category->title ?? $category->name) . " exam categories, packages and practice tests.";
            $model = $category;
        } elseif ($group) {
            $title = $group->meta_title ?: self::text($group->group_name);
            $description = $group->meta_description ?: "Explore " . self::text($group->group_name) . " exam groups, packages, mock tests and previous year papers.";
            $model = $group;
        } else {
            return self::simple('Exam Groups', 'Browse exam groups, categories, subcategories and online test packages.');
        }

        return self::build([
            'title' => $title,
            'description' => $description,
            'keywords' => $model->meta_keywords ?? null,
            'canonical' => $model->canonical_url ?: url()->current(),
            'image' => $model->og_image ?? null,
            'og_title' => $model->og_title ?? $title,
            'og_description' => $model->og_description ?? $description,
            'robots' => $model->robots_meta ?? 'index,follow',
            'schema' => $model->seo_schema ?? [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'description' => $description,
                'url' => url()->current(),
            ],
        ]);
    }

    public static function websitePage($slug): array
    {
        $page = self::tenantQuery(WebsitePage::where('short_title->en', $slug))->first();

        if (! $page) {
            return self::simple('Page', 'ExamElite information page.');
        }

        $title = $page->meta_title ?: self::text($page->title ?? $page->short_title ?? $slug);
        $description = $page->meta_description ?: (self::text($page->content ?? $page->description ?? '') ?: "{$title} - ExamElite");

        return self::build([
            'title' => $title,
            'description' => $description,
            'keywords' => $page->meta_keywords,
            'canonical' => $page->canonical_url ?: url()->current(),
            'image' => $page->og_image,
            'og_title' => $page->og_title ?: $title,
            'og_description' => $page->og_description ?: $description,
            'robots' => $page->robots_meta ?: 'index,follow',
            'schema' => $page->seo_schema ?: [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => url()->current(),
            ],
        ]);
    }

    public static function simple(string $title, string $description): array
    {
        return self::build([
            'title' => $title,
            'description' => $description,
            'canonical' => url()->current(),
        ]);
    }

    private static function build(array $seo): array
    {
        $seo['description'] = Str::limit(self::text($seo['description'] ?? ''), 160, '');
        $seo['robots'] = $seo['robots'] ?? 'index,follow';

        return $seo;
    }

    private static function text($value): string
    {
        if (is_array($value)) {
            $value = $value['en'] ?? reset($value) ?: '';
        }

        if (is_string($value) && self::looksJson($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded['en'] ?? reset($decoded) ?: $value;
            }
        }

        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);
        $value = str_ireplace(['Exampace', 'ExamPace', 'Examways', 'ExamWays', 'examways.org'], 'ExamElite', $value);

        return trim($value);
    }

    private static function looksJson(string $value): bool
    {
        $value = trim($value);
        return str_starts_with($value, '{') || str_starts_with($value, '[');
    }
}
