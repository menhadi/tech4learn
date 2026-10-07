<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Configuration;
use App\Models\Exam;
use App\Models\FlashcardSet;
use App\Models\Group;
use App\Models\Package;
use App\Models\WebsitePage;
use App\Support\SaasAccess;
use App\Support\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeoGeneratorController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function applyTenant($query)
    {
        $tenantId = $this->tenantId();

        if ($tenantId && method_exists($query->getModel(), 'getTable')) {
            $table = $query->getModel()->getTable();

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'organization_id')) {
                $query->where($table . '.organization_id', $tenantId);
            }
        }

        return $query;
    }

    private array $seoFields = [
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_title',
        'og_description',
        'robots_meta',
        'seo_schema',
    ];

    public function dashboard()
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        $audits = collect($this->contentTypes())
            ->map(function (string $label, string $type) {
                $query = $this->queryForType($type);
                if (! $query) return null;

                $table = $query->getModel()->getTable();
                $total = (clone $query)->count();
                $fieldCounts = [];

                foreach (['meta_title', 'meta_description', 'canonical_url', 'seo_schema'] as $field) {
                    $fieldCounts[$field] = Schema::hasColumn($table, $field)
                        ? (clone $query)->whereNotNull($field)->where($field, '!=', '')->count()
                        : 0;
                }

                return ['type' => $type, 'label' => $label, 'total' => $total, 'fields' => $fieldCounts];
            })
            ->filter()
            ->values();

        $totalItems = max(1, (int) $audits->sum('total'));
        $coverage = function (string $field) use ($audits, $totalItems): int {
            return (int) round(($audits->sum(fn (array $audit) => $audit['fields'][$field] ?? 0) / $totalItems) * 100);
        };

        $metaTitleCoverage = $coverage('meta_title');
        $metaDescriptionCoverage = $coverage('meta_description');
        $canonicalCoverage = $coverage('canonical_url');
        $schemaCoverage = $coverage('seo_schema');
        $searchScore = (int) round(
            ($metaTitleCoverage * .30) +
            ($metaDescriptionCoverage * .30) +
            ($canonicalCoverage * .20) +
            ($schemaCoverage * .20)
        );
        $aiScore = (int) round(
            ($metaDescriptionCoverage * .35) +
            ($schemaCoverage * .45) +
            ($canonicalCoverage * .20)
        );

        $opportunities = collect([
            [
                'priority' => 'High',
                'area' => 'Search',
                'title' => 'Complete missing page descriptions',
                'detail' => (100 - $metaDescriptionCoverage) . '% of audited content still needs a clear search snippet and answer summary.',
                'action' => route('admin.seo.bulk'),
                'action_label' => 'Generate metadata',
                'impact' => 92,
            ],
            [
                'priority' => 'High',
                'area' => 'AI visibility',
                'title' => 'Add machine-readable page meaning',
                'detail' => (100 - $schemaCoverage) . '% of audited content has no JSON-LD schema for search and answer engines.',
                'action' => route('admin.seo.bulk'),
                'action_label' => 'Prepare schema',
                'impact' => 88,
            ],
            [
                'priority' => 'Medium',
                'area' => 'Technical',
                'title' => 'Connect Google Search Console',
                'detail' => 'Unlock query, page, CTR and ranking opportunities based on actual search demand.',
                'action' => '#connections',
                'action_label' => 'Review connection',
                'impact' => 81,
            ],
        ]);

        $searchIntegration = Schema::hasTable('seo_integrations')
            ? \App\Models\SeoIntegration::query()
                ->forOrganization($this->tenantId())
                ->where('provider', 'google_search_console')
                ->first()
            : null;
        $searchData = $searchIntegration?->data ?? [];
        $googleConfigured = filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
        $properties = collect($searchData['properties'] ?? []);
        $keywordOpportunities = collect($searchData['queries'] ?? [])
            ->filter(fn (array $row) => ($row['position'] ?? 0) >= 4
                && ($row['position'] ?? 0) <= 20
                && ($row['impressions'] ?? 0) >= 10)
            ->sortByDesc('impressions')
            ->take(8)
            ->values();

        $robotsPath = public_path('robots.txt');
        $robotsContent = is_file($robotsPath) ? (string) file_get_contents($robotsPath) : '';
        $globallyBlocked = preg_match('/user-agent:\s*\*[^#]*?disallow:\s*\/\s*(?:\r?\n|$)/i', $robotsContent) === 1;
        $namedAiBlocked = preg_match('/user-agent:\s*(?:gptbot|oai-searchbot|chatgpt-user|perplexitybot)[^#]*?disallow:\s*\/\s*(?:\r?\n|$)/i', $robotsContent) === 1;
        $aiCrawlerStatus = $robotsContent === ''
            ? 'unknown'
            : ($globallyBlocked ? 'blocked' : ($namedAiBlocked ? 'limited' : 'allowed'));

        return view('seo.dashboard', compact(
            'audits',
            'totalItems',
            'metaTitleCoverage',
            'metaDescriptionCoverage',
            'canonicalCoverage',
            'schemaCoverage',
            'searchScore',
            'aiScore',
            'opportunities',
            'searchIntegration',
            'searchData',
            'googleConfigured',
            'properties',
            'keywordOpportunities',
            'aiCrawlerStatus'
        ));
    }

    private function queryForType(string $type)
    {
        $query = match ($type) {
            'groups' => Group::query(),
            'categories' => Category::query()->whereNull('parent_id'),
            'subcategories' => Category::query()->whereNotNull('parent_id'),
            'packages' => Package::query(),
            'exams' => Exam::query(),
            'flashcard_sets' => FlashcardSet::query(),
            'website_pages' => WebsitePage::query(),
            'about_us' => $this->aboutQuery(),
            default => null,
        };

        if ($query) {
            $this->applyTenant($query);
        }

        return $query;
    }

    public function bulkForm()
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        return view('seo.bulk-generator', [
            'types' => $this->contentTypes(),
        ]);
    }

    public function bulkGenerate(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        $validated = $request->validate([
            'content_type' => 'required|string',
            'mode' => 'required|in:empty,all',
            'limit' => 'required|integer|min:1|max:200',
        ]);

        $contentType = $validated['content_type'];
        $types = $contentType === 'all'
            ? array_keys($this->contentTypes())
            : [$contentType];

        $updated = [];
        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($types as $type) {
            $items = $this->itemsForType($type, $validated['mode'], (int) $validated['limit']);

            $updated[$type] = [
                'updated' => 0,
                'skipped' => 0,
                'items' => [],
            ];

            foreach ($items as $item) {
                $context = $this->contextForItem($type, $item);

                if (($context['title'] ?? '') === '' && ($context['description'] ?? '') === '') {
                    $updated[$type]['skipped']++;
                    $totalSkipped++;
                    continue;
                }

                $seo = $this->generateSeoData($context['entity_type'], $context['title'], $context['description'], $context['url']);

                if (! $seo) {
                    $updated[$type]['skipped']++;
                    $totalSkipped++;
                    continue;
                }

                foreach ($this->seoFields as $field) {
                    if (Schema::hasColumn($item->getTable(), $field)) {
                        $item->{$field} = $seo[$field] ?? null;
                    }
                }

                $item->save();

                $updated[$type]['updated']++;
                $totalUpdated++;
                $updated[$type]['items'][] = $context['title'];
            }
        }

        return back()->with('seo_bulk_result', [
            'updated' => $totalUpdated,
            'skipped' => $totalSkipped,
            'details' => $updated,
        ]);
    }

    public function generate(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        $validated = $request->validate([
            'entity_type' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:5000',
            'url' => 'nullable|string|max:1000',
        ]);

        $seo = $this->generateSeoData(
            $validated['entity_type'] ?? 'ExamElite page',
            $validated['title'] ?? '',
            $validated['description'] ?? '',
            $validated['url'] ?? ''
        );

        if (! $seo) {
            return response()->json([
                'success' => false,
                'message' => 'AI SEO generation failed. Please check API settings.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $seo,
        ]);
    }


    public function bulkContentForm()
    {
        SaasAccess::abortIfFeatureDisabled('ai_content_generation');

        return view('seo.bulk-content-generator');
    }

    public function bulkContentGenerate(\Illuminate\Http\Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_content_generation');

        return back()->with('ai_content_result', [
            'updated' => 0,
            'skipped' => 0,
            'details' => [],
        ]);
    }

    private function contentTypes(): array
    {
        return [
            'groups' => 'Groups',
            'categories' => 'Categories',
            'subcategories' => 'Subcategories',
            'packages' => 'Packages',
            'exams' => 'Exams',
            'flashcard_sets' => 'Study Card Sets',
            'website_pages' => 'Website Pages',
            'about_us' => 'About Us',
        ];
    }

    private function itemsForType(string $type, string $mode, int $limit)
    {
        $query = match ($type) {
            'groups' => Group::query(),
            'categories' => Category::query()->whereNull('parent_id'),
            'subcategories' => Category::query()->whereNotNull('parent_id'),
            'packages' => Package::query(),
            'exams' => Exam::query(),
            'flashcard_sets' => FlashcardSet::query()
                ->with(['package:id,name,slug', 'group:id,group_name', 'subject:id,subject_name', 'topic:id,name', 'stopic:id,name'])
                ->withCount('cards'),
            'website_pages' => WebsitePage::query(),
            'about_us' => $this->aboutQuery(),
            default => null,
        };

        if ($query) {
            $this->applyTenant($query);
        }

        if (! $query) {
            return collect();
        }

        if ($mode === 'empty') {
            $query->where(function ($q) {
                $q->whereNull('meta_title')->orWhere('meta_title', '');
            });
        }

        return $query->orderBy('id')->limit($limit)->get();
    }

    private function aboutQuery()
    {
        if (class_exists(\App\Models\AboutUs::class)) {
            return \App\Models\AboutUs::query();
        }

        return null;
    }

    private function contextForItem(string $type, $item): array
    {
        return match ($type) {
            'groups' => $this->groupContext($item),
            'categories', 'subcategories' => $this->categoryContext($item, $type),
            'packages' => $this->packageContext($item),
            'exams' => $this->examContext($item),
            'flashcard_sets' => $this->flashcardSetContext($item),
            'website_pages' => [
                'entity_type' => 'website page',
                'title' => $this->text($item->title ?? $item->short_title ?? ''),
                'description' => $this->text($item->description ?? ''),
                'url' => url('/' . Str::slug($this->text($item->short_title ?? $item->title ?? 'page'))),
            ],
            'about_us' => [
                'entity_type' => 'about page',
                'title' => 'About ExamElite',
                'description' => $this->text(($item->title ?? '') . ' ' . ($item->description ?? '') . ' ' . ($item->content ?? '')),
                'url' => url('/about'),
            ],
            default => [
                'entity_type' => 'ExamElite page',
                'title' => '',
                'description' => '',
                'url' => url('/'),
            ],
        };
    }

    private function groupContext($group): array
    {
        $name = $this->text($group->group_name ?? '');

        $packageNames = collect();
        if (method_exists($group, 'packages')) {
            $packageNames = $group->packages()->limit(8)->get()->map(fn ($package) => $this->text($package->name ?? ''))->filter();
        }

        $examNames = collect();
        if (method_exists($group, 'exams')) {
            $examNames = $group->exams()->limit(8)->get()->map(fn ($exam) => $this->text($exam->name ?? ''))->filter();
        }

        $description = collect([
            'Group: ' . $name,
            $this->text($group->description ?? '') ? 'Description: ' . $this->text($group->description ?? '') : null,
            'Packages: ' . $packageNames->implode(', '),
            'Exams: ' . $examNames->implode(', '),
        ])->filter()->implode("\n");

        return [
            'entity_type' => 'exam group',
            'title' => $name,
            'description' => $description,
            'url' => url('/exam-groups/' . Str::slug($name)),
        ];
    }

    private function categoryContext($category, string $type): array
    {
        $name = $this->text($category->title ?? '');
        $parentName = $category->parent ? $this->text($category->parent->title ?? '') : '';

        $examCount = 0;
        if (Schema::hasColumn('exams', 'category_level_1')) {
            $examCount = DB::table('exams')
                ->where('category_level_1', $category->id)
                ->orWhere('category_level_2', $category->id)
                ->count();
        }

        $description = collect([
            $type === 'categories' ? 'Category: ' . $name : 'Subcategory: ' . $name,
            $this->text($category->description ?? '') ? 'Description: ' . $this->text($category->description ?? '') : null,
            $parentName ? 'Parent category: ' . $parentName : null,
            $examCount ? 'Related exams: ' . $examCount : null,
        ])->filter()->implode("\n");

        return [
            'entity_type' => $type === 'categories' ? 'exam category' : 'exam subcategory',
            'title' => $name,
            'description' => $description,
            'url' => url('/exam-groups/all/' . ($category->slug ?? Str::slug($name))),
        ];
    }

    private function packageContext($package): array
    {
        $name = $this->text($package->name ?? '');
        $description = $this->text($package->description ?? '');

        $examNames = collect();
        if (method_exists($package, 'exams')) {
            $examNames = $package->exams()->limit(12)->get()->map(fn ($exam) => $this->text($exam->name ?? ''))->filter();
        }

        $groupNames = collect();
        if (method_exists($package, 'groups')) {
            $groupNames = $package->groups()->limit(6)->get()->map(fn ($group) => $this->text($group->group_name ?? ''))->filter();
        }

        $category = $this->categoryName($package->category_level_1 ?? null);
        $subcategory = $this->categoryName($package->category_level_2 ?? null);

        $context = collect([
            'Package: ' . $name,
            $description ? 'Existing description: ' . $description : null,
            $category ? 'Category: ' . $category : null,
            $subcategory ? 'Subcategory: ' . $subcategory : null,
            $groupNames->isNotEmpty() ? 'Groups: ' . $groupNames->implode(', ') : null,
            $examNames->isNotEmpty() ? 'Included exams: ' . $examNames->implode(', ') : null,
            'Package type: ' . (($package->package_type ?? '') ?: 'exam package'),
            'Price: ' . (($package->discounted_amount ?? $package->amount ?? 0) > 0 ? 'paid' : 'free'),
        ])->filter()->implode("\n");

        return [
            'entity_type' => 'exam package',
            'title' => $name,
            'description' => $context,
            'url' => url('/course-detail/' . ($package->slug ?? $package->id)),
        ];
    }

    private function examContext($exam): array
    {
        $name = $this->text($exam->name ?? '');
        $package = method_exists($exam, 'packages') ? $exam->packages()->first() : null;
        $packageName = $package ? $this->text($package->name ?? '') : '';

        $groupNames = collect();
        if (method_exists($exam, 'groups')) {
            $groupNames = $exam->groups()->limit(6)->get()->map(fn ($group) => $this->text($group->group_name ?? ''))->filter();
        }

        $category = $this->categoryName($exam->category_level_1 ?? null);
        $subcategory = $this->categoryName($exam->category_level_2 ?? null);

        $questionCount = method_exists($exam, 'questions') ? $exam->questions()->count() : 0;

        $context = collect([
            'Exam: ' . $name,
            $packageName ? 'Package: ' . $packageName : null,
            $category ? 'Category: ' . $category : null,
            $subcategory ? 'Subcategory: ' . $subcategory : null,
            $groupNames->isNotEmpty() ? 'Groups: ' . $groupNames->implode(', ') : null,
            $questionCount ? 'Number of questions: ' . $questionCount : null,
            !empty($exam->duration) ? 'Duration: ' . $exam->duration . ' minutes' : null,
            !empty($exam->mode) ? 'Mode: ' . $exam->mode : null,
            $this->text($exam->instruction ?? '') ? 'Instruction: ' . $this->text($exam->instruction ?? '') : null,
            $this->text($exam->syllabus ?? '') ? 'Syllabus: ' . $this->text($exam->syllabus ?? '') : null,
        ])->filter()->implode("\n");

        return [
            'entity_type' => 'exam landing page',
            'title' => $name,
            'description' => $context,
            'url' => url('/exam-detail/' . ($exam->slug ?? $exam->id)),
        ];
    }

    private function flashcardSetContext(FlashcardSet $set): array
    {
        $title = $this->text($set->title ?? '');
        $packageName = $this->text($set->package?->name ?? '');
        $groupName = $this->text($set->group?->group_name ?? '');
        $subjectName = $this->text($set->subject?->subject_name ?? '');
        $topicName = $this->text($set->topic?->name ?? '');
        $subtopicName = $this->text($set->stopic?->name ?? '');

        $context = collect([
            'Study card set: ' . $title,
            $packageName ? 'Package: ' . $packageName : null,
            $groupName ? 'Group: ' . $groupName : null,
            $subjectName ? 'Subject: ' . $subjectName : null,
            $topicName ? 'Topic: ' . $topicName : null,
            $subtopicName ? 'Subtopic: ' . $subtopicName : null,
            'Cards: ' . (int) ($set->cards_count ?? 0),
            'Purpose: quick revision, formulas, objective question practice, and concept review.',
        ])->filter()->implode("\n");

        return [
            'entity_type' => 'study card set',
            'title' => $title,
            'description' => $context,
            'url' => $set->package
                ? url('/course-detail/' . ($set->package->slug ?? $set->package->id) . '/flashcards')
                : url('/flashcards/' . $set->id),
        ];
    }

    private function categoryName($categoryId): string
    {
        if (!$categoryId || !Schema::hasTable('category')) {
            return '';
        }

        $category = Category::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->find($categoryId);

        return $category ? $this->text($category->title ?? '') : '';
    }

    private function generateSeoData(string $entityType, string $title, string $description, string $url): ?array
    {
        $title = trim($title);
        $description = trim($description);

        if ($title === '' && $description === '') {
            return null;
        }

        $prompt = $this->buildPrompt($entityType, $title, $description, $url);
        $content = $this->askAi($prompt);
        $json = $this->extractJson($content);

        if (! is_array($json)) {
            return null;
        }

        return [
            'meta_title' => Str::limit($json['meta_title'] ?? '', 60, ''),
            'meta_description' => Str::limit($json['meta_description'] ?? '', 160, ''),
            'meta_keywords' => $json['meta_keywords'] ?? '',
            'canonical_url' => $json['canonical_url'] ?? $url,
            'og_title' => Str::limit($json['og_title'] ?? ($json['meta_title'] ?? ''), 70, ''),
            'og_description' => Str::limit($json['og_description'] ?? ($json['meta_description'] ?? ''), 200, ''),
            'robots_meta' => $json['robots_meta'] ?? 'index,follow',
            'seo_schema' => $json['seo_schema'] ?? '',
        ];
    }

    private function buildPrompt(string $entityType, string $title, string $description, string $url): string
    {
        return <<<PROMPT
You are an SEO assistant for ExamElite, an online exam and mock test platform.

Create SEO metadata for this {$entityType}.

Title:
{$title}

Description/content:
{$description}

URL:
{$url}

Rules:
- Use brand name ExamElite only if needed.
- Prefer words like exam, exams, mock test, previous year questions, PYQ, practice test.
- Do not use "course" unless unavoidable.
- Meta title must be under 60 characters.
- Meta description must be under 160 characters.
- Keywords must be comma separated.
- robots_meta should usually be "index,follow".
- seo_schema must be valid JSON-LD string or empty string.
- Return ONLY valid JSON with these keys:
meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description, robots_meta, seo_schema
PROMPT;
    }

    private function askAi(string $prompt): ?string
    {
        foreach (AiProvider::available(getConfiguration(), false, 'content_seo') as $provider) {
            try {
                $content = AiProvider::generateText($provider, $prompt, 'Return only valid JSON.', 0.3, 4096, 45);
                if (is_string($content) && trim($content) !== '') return $content;
                Log::warning('SEO generation provider failed', ['provider' => $provider['provider']]);
            } catch (\Throwable $exception) {
                Log::warning('SEO generation provider exception', ['provider' => $provider['provider'], 'message' => $exception->getMessage()]);
            }
        }
        return null;
    }

    private function extractJson(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/^```\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $decoded = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function text($value): string
    {
        if (is_array($value)) {
            $value = $value['en'] ?? reset($value) ?: '';
        }

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded['en'] ?? reset($decoded) ?: $value;
            }
        }

        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
    }
}
