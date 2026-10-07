<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\PypContentService;
use App\Support\Tenant;
use Illuminate\Http\Request;

class PypPageController extends WebsiteController
{
    public function __construct(private PypContentService $pyp) {}

    public function index(Request $request, string $package)
    {
        return $this->renderPage($request, $package, 'index');
    }

    public function analysis(Request $request, string $package)
    {
        return $this->renderPage($request, $package, 'analysis');
    }

    public function subject(Request $request, string $package, string $subject)
    {
        return $this->renderPage($request, $package, 'subject', $subject);
    }

    public function topic(Request $request, string $package, string $subject, string $topic)
    {
        return $this->renderPage($request, $package, 'topic', $subject, $topic);
    }

    public function subtopic(Request $request, string $package, string $subject, string $topic, string $subtopic)
    {
        return $this->renderPage($request, $package, 'subtopic', $subject, $topic, $subtopic);
    }

    private function renderPage(
        Request $request,
        string $packageSlug,
        string $pageType,
        ?string $subjectToken = null,
        ?string $topicToken = null,
        ?string $subtopicToken = null
    ) {
        $package = Package::query()
            ->with('groups:id,group_name')
            ->where('slug', $packageSlug)
            ->where('status', 1)
            ->when(Tenant::id(), fn ($query, $id) => $query->where('organization_id', $id))
            ->firstOrFail();

        abort_unless($this->pyp->enabledFor($package), 404);
        $settings = $this->pyp->settings();
        $packageSetting = $this->pyp->packageSetting($package);
        abort_if($pageType === 'analysis' && ! $settings['analysis_enabled'], 404);
        abort_if($pageType === 'analysis' && ($packageSetting?->analysis_mode === 'disabled'), 404);
        abort_if($pageType === 'subject' && ! $settings['subject_pages'], 404);
        abort_if($pageType === 'topic' && (! $settings['subject_pages'] || ! $settings['topic_pages']), 404);
        abort_if($pageType === 'subtopic' && (! $settings['subject_pages'] || ! $settings['topic_pages'] || ! $settings['subtopic_pages']), 404);

        $dataset = $this->pyp->dataset($package);
        $subjectId = $subjectToken ? $this->pyp->idFromToken($subjectToken) : null;
        $topicId = $topicToken ? $this->pyp->idFromToken($topicToken) : null;
        $subtopicId = $subtopicToken ? $this->pyp->idFromToken($subtopicToken) : null;
        $subject = $subjectId ? collect($dataset['subjects'])->firstWhere('id', $subjectId) : null;
        $topic = $topicId ? collect($dataset['topics'])->firstWhere('id', $topicId) : null;
        $subtopic = $subtopicId ? collect($dataset['subtopics'])->firstWhere('id', $subtopicId) : null;

        if ($subjectToken) {
            abort_unless($subject, 404);
        }
        if ($topicToken) {
            abort_unless($topic && (int) $topic['subject_id'] === $subjectId, 404);
        }
        if ($subtopicToken) {
            abort_unless($subtopic && (int) $subtopic['topic_id'] === $topicId, 404);
        }

        $canonicalTokensMatch = (! $subjectToken || $subjectToken === $subject['token'])
            && (! $topicToken || $topicToken === $topic['token'])
            && (! $subtopicToken || $subtopicToken === $subtopic['token']);
        if (! $canonicalTokensMatch) {
            $parameters = [$package->slug];
            if ($subject) {
                $parameters[] = $subject['token'];
            }
            if ($topic) {
                $parameters[] = $topic['token'];
            }
            if ($subtopic) {
                $parameters[] = $subtopic['token'];
            }

            return redirect()->route('pyp.'.$pageType, $parameters, 301);
        }

        $entries = $this->pyp->filteredEntries($dataset, $subjectId, $topicId, $subtopicId);
        $subjects = collect($dataset['subjects']);
        $topics = collect($dataset['topics'])->when($subjectId, fn ($items) => $items->where('subject_id', $subjectId))->values();
        $subtopics = collect($dataset['subtopics'])->when($topicId, fn ($items) => $items->where('topic_id', $topicId))->values();
        $exams = collect($dataset['exams'])->whereIn('id', $entries->pluck('exam_id')->unique())->values();
        if ($pageType === 'index' || $pageType === 'analysis') {
            $exams = collect($dataset['exams']);
        }

        $sampleQuestions = $entries->unique('question_id')
            ->take(max(1, (int) $settings['sample_questions']))
            ->values();
        $titleParts = array_filter([$subtopic['name'] ?? null, $topic['name'] ?? null, $subject['name'] ?? null]);
        $scopeName = $titleParts ? implode(' - ', $titleParts) : $this->text($package->name);
        $pageTitle = $pageType === 'analysis'
            ? $this->text($package->name).' Previous Year Paper Trends & Analysis'
            : $scopeName.' Previous Year Questions';
        $description = $pageType === 'analysis'
            ? 'Explore year-wise question trends, subject weightage, difficulty and paper coverage for '.$this->text($package->name).'.'
            : 'Practice '.$scopeName.' previous year questions organised from real papers, with year-wise coverage and clear topic navigation.';

        if ($pageType === 'index') {
            $pageTitle = $packageSetting?->meta_title ?: $pageTitle;
            $description = $packageSetting?->meta_description ?: $description;
        }
        $indexable = (bool) ($packageSetting?->indexable ?? true)
            && $entries->pluck('question_id')->unique()->count() >= (int) $settings['min_questions'];

        $canonical = $request->url();
        $seo = [
            'title' => $pageTitle,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => $pageType === 'analysis' ? 'Dataset' : 'CollectionPage',
                'name' => $pageTitle,
                'description' => $description,
                'url' => $canonical,
                'isPartOf' => ['@type' => 'Course', 'name' => $this->text($package->name), 'url' => route('courses.detail', $package->slug)],
                'dateModified' => $dataset['updated_at'],
            ],
        ];

        $pageStats = [
            'papers' => $exams->count(),
            'years' => $entries->pluck('year')->filter()->unique()->count(),
            'questions' => $entries->pluck('question_id')->unique()->count(),
            'topics' => $entries->pluck('topic_id')->filter()->unique()->count(),
        ];
        $analysisMode = $packageSetting?->analysis_mode ?? 'historical';
        $historicalAvailable = $analysisMode === 'historical'
            && $pageStats['years'] >= (int) $settings['min_years_for_historical'];
        $commonData = $this->getCommonViewData();

        return view('website.pyp.page', array_merge($commonData, compact(
            'package', 'dataset', 'settings', 'pageType', 'subject', 'topic', 'subtopic',
            'entries', 'subjects', 'topics', 'subtopics', 'exams', 'sampleQuestions',
            'pageTitle', 'description', 'pageStats', 'analysisMode', 'historicalAvailable', 'seo'
        )));
    }

    private function text($value): string
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

        return trim((string) ($value ?? ''));
    }
}
