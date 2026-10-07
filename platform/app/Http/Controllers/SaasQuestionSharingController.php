<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Diff;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionShareBatch;
use App\Models\QuestionShareItem;
use App\Models\QuestionTag;
use App\Models\Qtype;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Stopic;
use App\Models\Language;
use App\Services\QuestionSharingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SaasQuestionSharingController extends Controller
{
    public function index(Request $request)
    {
        $platformOrganization = $this->platformOrganization();
        $organizations = Organization::where('id', '!=', $platformOrganization?->id)->orderBy('name')->get();
        $perPage = in_array((int) $request->input('per_page', 50), [50, 100, 500], true)
            ? (int) $request->input('per_page', 50)
            : 50;
        $selectedOrganization = $request->filled('organization_id')
            ? Organization::find($request->integer('organization_id'))
            : $organizations->first();

        $masterQuestions = $this->filteredQuestionQuery($platformOrganization->id, $request, 'master_')
            ->latest()
            ->paginate($perPage, ['*'], 'master_page')
            ->appends($request->query());

        $organizationQuestions = collect();

        if ($selectedOrganization) {
            $organizationQuestions = $this->filteredQuestionQuery($selectedOrganization->id, $request, 'org_')
                ->latest()
                ->paginate($perPage, ['*'], 'org_page')
                ->appends($request->query());
        }

        $masterFilters = $this->filterOptions($platformOrganization->id, $request, 'master_');
        $organizationFilters = $selectedOrganization ? $this->filterOptions($selectedOrganization->id, $request, 'org_') : [
            'groups' => collect(),
            'categories' => collect(),
            'subcategories' => collect(),
            'packages' => collect(),
            'exams' => collect(),
            'qtypes' => Qtype::displayOrdered(),
            'diffs' => Diff::orderBy('diff_level')->get(),
            'questionTags' => collect(),
            'subjects' => collect(),
            'topics' => collect(),
            'stopics' => collect(),
            'languages' => collect(),
        ];
        $recentBatches = QuestionShareBatch::latest()->limit(8)->get();

        return view('saas.question-sharing', compact(
            'platformOrganization',
            'organizations',
            'selectedOrganization',
            'masterQuestions',
            'organizationQuestions',
            'masterFilters',
            'organizationFilters',
            'perPage',
            'recentBatches'
        ));
    }

    public function shareToOrganizations(Request $request, QuestionSharingService $sharingService)
    {
        $platformOrganization = $this->platformOrganization();

        $validated = $request->validate([
            'question_ids' => ['required', 'array'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
            'organization_ids' => ['required', 'array'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
        ]);

        $questions = Question::where('organization_id', $platformOrganization->id)
            ->whereIn('id', $validated['question_ids'])
            ->get();

        if ($questions->isEmpty()) {
            return back()->withErrors(['question_ids' => 'Please select at least one master question.']);
        }

        foreach ($validated['organization_ids'] as $organizationId) {
            if ((int) $organizationId === (int) $platformOrganization->id) {
                continue;
            }

            $batch = QuestionShareBatch::create([
                'source_organization_id' => $platformOrganization->id,
                'target_organization_id' => $organizationId,
                'direction' => 'master_to_org',
                'created_by' => Auth::id(),
                'question_count' => $questions->count(),
            ]);

            foreach ($questions as $question) {
                $copiedQuestion = $sharingService->copyQuestionToOrganization($question, (int) $organizationId);

                QuestionShareItem::create([
                    'batch_id' => $batch->id,
                    'source_question_id' => $question->id,
                    'target_question_id' => $copiedQuestion->id,
                    'status' => 'copied',
                ]);
            }
        }

        audit_log('questions.shared_to_organizations', null, [
            'question_ids' => $validated['question_ids'],
            'organization_ids' => $validated['organization_ids'],
        ]);

        return redirect()->route('saas.question-sharing.index')->with('success', 'Questions shared successfully.');
    }

    public function copyToMaster(Request $request, QuestionSharingService $sharingService)
    {
        $platformOrganization = $this->platformOrganization();

        $validated = $request->validate([
            'source_organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'question_ids' => ['required', 'array'],
            'question_ids.*' => ['integer', 'exists:questions,id'],
        ]);

        $questions = Question::where('organization_id', $validated['source_organization_id'])
            ->whereIn('id', $validated['question_ids'])
            ->get();

        if ((int) $validated['source_organization_id'] === (int) $platformOrganization->id) {
            return back()->withErrors(['source_organization_id' => 'Select an organization, not the master organization.']);
        }

        if ($questions->isEmpty()) {
            return back()->withErrors(['question_ids' => 'Please select at least one organization question.']);
        }

        $batch = QuestionShareBatch::create([
            'source_organization_id' => $validated['source_organization_id'],
            'target_organization_id' => $platformOrganization->id,
            'direction' => 'org_to_master',
            'created_by' => Auth::id(),
            'question_count' => $questions->count(),
        ]);

        foreach ($questions as $question) {
            $copiedQuestion = $sharingService->copyQuestionToOrganization($question, $platformOrganization->id);

            QuestionShareItem::create([
                'batch_id' => $batch->id,
                'source_question_id' => $question->id,
                'target_question_id' => $copiedQuestion->id,
                'status' => 'copied',
            ]);
        }

        audit_log('questions.copied_to_master', null, [
            'source_organization_id' => $validated['source_organization_id'],
            'question_ids' => $validated['question_ids'],
        ]);

        return redirect()
            ->route('saas.question-sharing.index', ['organization_id' => $validated['source_organization_id']])
            ->with('success', 'Questions copied to master successfully.');
    }

    private function platformOrganization(): Organization
    {
        return Organization::where('slug', 'examelite')->firstOrFail();
    }

    private function filteredQuestionQuery(int $organizationId, Request $request, string $prefix)
    {
        return Question::with(['qtype', 'diff', 'subject', 'groups', 'exams.packages'])
            ->where('organization_id', $organizationId)
            ->when($request->filled($prefix.'search'), function ($query) use ($request, $prefix) {
                $search = trim($request->input($prefix.'search'));

                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('id', $search)
                        ->orWhere('question', 'like', "%{$search}%")
                        ->orWhereHas('subject', fn ($subjectQuery) => $subjectQuery->where('subject_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled($prefix.'group_id'), function ($query) use ($request, $prefix) {
                $groupId = $request->integer($prefix.'group_id');

                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($questionGroupQuery) => $questionGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
                });
            })
            ->when($request->filled($prefix.'category_id'), function ($query) use ($request, $prefix) {
                $categoryId = $request->integer($prefix.'category_id');

                $query->whereHas('exams', function ($examQuery) use ($categoryId) {
                    $examQuery->where(function ($categoryQuery) use ($categoryId) {
                        $categoryQuery->where('category_level_1', $categoryId)
                            ->orWhereHas('packages', function ($packageQuery) use ($categoryId) {
                                $packageQuery->where('category_level_1', $categoryId);
                            });
                    });
                });
            })
            ->when($request->filled($prefix.'subcategory_id'), function ($query) use ($request, $prefix) {
                $subcategoryId = $request->integer($prefix.'subcategory_id');

                $query->whereHas('exams', function ($examQuery) use ($subcategoryId) {
                    $examQuery->where(function ($subcategoryQuery) use ($subcategoryId) {
                        $subcategoryQuery->where('category_level_2', $subcategoryId)
                            ->orWhereHas('packages', function ($packageQuery) use ($subcategoryId) {
                                $packageQuery->where('category_level_2', $subcategoryId);
                            });
                    });
                });
            })
            ->when($request->filled($prefix.'package_id'), function ($query) use ($request, $prefix) {
                $packageId = $request->integer($prefix.'package_id');

                $query->whereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId));
            })
            ->when($request->filled($prefix.'exam_id'), function ($query) use ($request, $prefix) {
                $query->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.id', $request->integer($prefix.'exam_id')));
            })
            ->when($request->filled($prefix.'qtype_id'), function ($query) use ($request, $prefix) {
                $query->where('qtype_id', $request->integer($prefix.'qtype_id'));
            })
            ->when($request->filled($prefix.'diff_id'), function ($query) use ($request, $prefix) {
                $query->where('diff_id', $request->integer($prefix.'diff_id'));
            })
            ->when($request->filled($prefix.'tag_id'), function ($query) use ($request, $prefix) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('question_tags.id', $request->integer($prefix.'tag_id')));
            })
            ->when($request->filled($prefix.'subject_id'), function ($query) use ($request, $prefix) {
                $query->where('subject_id', $request->integer($prefix.'subject_id'));
            })
            ->when($request->filled($prefix.'topic_id'), function ($query) use ($request, $prefix) {
                $query->where('topic_id', $request->integer($prefix.'topic_id'));
            })
            ->when($request->filled($prefix.'subtopic_id'), function ($query) use ($request, $prefix) {
                $query->where('stopic_id', $request->integer($prefix.'subtopic_id'));
            })
            ->when($request->filled($prefix.'language_id'), function ($query) use ($request, $prefix) {
                $query->where('language_id', $request->integer($prefix.'language_id'));
            })
            ->when($request->filled($prefix.'status'), function ($query) use ($request, $prefix) {
                $query->where('status', $request->input($prefix.'status'));
            })
            ->when($request->filled($prefix.'marks_min'), function ($query) use ($request, $prefix) {
                $query->where('marks', '>=', $request->input($prefix.'marks_min'));
            })
            ->when($request->filled($prefix.'marks_max'), function ($query) use ($request, $prefix) {
                $query->where('marks', '<=', $request->input($prefix.'marks_max'));
            })
            ->when($request->filled($prefix.'negative_marks_min'), function ($query) use ($request, $prefix) {
                $query->where('negative_marks', '>=', $request->input($prefix.'negative_marks_min'));
            })
            ->when($request->filled($prefix.'negative_marks_max'), function ($query) use ($request, $prefix) {
                $query->where('negative_marks', '<=', $request->input($prefix.'negative_marks_max'));
            })
            ->when($request->filled($prefix.'has_image'), function ($query) use ($request, $prefix) {
                $imageMatch = function ($imageQuery) {
                    $imageQuery->where('question', 'like', '%<img%')
                        ->orWhere('question', 'like', '%.png%')
                        ->orWhere('question', 'like', '%.jpg%')
                        ->orWhere('question', 'like', '%.jpeg%')
                        ->orWhere('question', 'like', '%.webp%')
                        ->orWhere('question', 'like', '%.gif%');
                };

                if ($request->input($prefix.'has_image') === 'yes') {
                    $query->where($imageMatch);
                } elseif ($request->input($prefix.'has_image') === 'no') {
                    $query->where(function ($noImageQuery) {
                        $noImageQuery->where('question', 'not like', '%<img%')
                            ->where('question', 'not like', '%.png%')
                            ->where('question', 'not like', '%.jpg%')
                            ->where('question', 'not like', '%.jpeg%')
                            ->where('question', 'not like', '%.webp%')
                            ->where('question', 'not like', '%.gif%');
                    });
                }
            })
            ->when($request->filled($prefix.'has_passage'), function ($query) use ($request, $prefix) {
                if ($request->input($prefix.'has_passage') === 'yes') {
                    $query->whereNotNull('passage_id');
                } elseif ($request->input($prefix.'has_passage') === 'no') {
                    $query->whereNull('passage_id');
                }
            })
            ->when($request->filled($prefix.'ai_generated') && Schema::hasColumn('questions', 'ai_generated'), function ($query) use ($request, $prefix) {
                if ($request->input($prefix.'ai_generated') === 'yes') {
                    $query->whereNotNull('ai_generated')->where('ai_generated', '!=', '');
                } elseif ($request->input($prefix.'ai_generated') === 'no') {
                    $query->where(function ($aiQuery) {
                        $aiQuery->whereNull('ai_generated')->orWhere('ai_generated', '');
                    });
                }
            });
    }

    private function filterOptions(int $organizationId, Request $request, string $prefix): array
    {
        $groupId = $request->integer($prefix.'group_id') ?: null;
        $categoryId = $request->integer($prefix.'category_id') ?: null;
        $subcategoryId = $request->integer($prefix.'subcategory_id') ?: null;
        $packageId = $request->integer($prefix.'package_id') ?: null;
        $examId = $request->integer($prefix.'exam_id') ?: null;
        $subjectId = $request->integer($prefix.'subject_id') ?: null;
        $topicId = $request->integer($prefix.'topic_id') ?: null;

        $categoryIds = collect();

        if ($groupId) {
            $examCategoryIds = Exam::where('organization_id', $organizationId)
                ->where(function ($query) use ($groupId) {
                    $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                        ->orWhereHas('packages.groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId));
                })
                ->get(['category_level_1', 'category_level_2'])
                ->flatMap(fn ($exam) => [$exam->category_level_1, $exam->category_level_2]);

            $packageCategoryIds = Package::where('organization_id', $organizationId)
                ->where(function ($query) use ($groupId) {
                    $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId));
                })
                ->get(['category_level_1', 'category_level_2'])
                ->flatMap(fn ($package) => [$package->category_level_1, $package->category_level_2]);

            $categoryIds = $examCategoryIds->merge($packageCategoryIds)->filter()->unique()->values();
        }

        $questionScope = Question::where('organization_id', $organizationId)
            ->when($groupId, function ($query) use ($groupId) {
                $query->where(function ($groupQuery) use ($groupId) {
                    $groupQuery->whereHas('groups', fn ($questionGroupQuery) => $questionGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                        ->orWhereHas('exams.packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->whereHas('exams', function ($examQuery) use ($categoryId) {
                    $examQuery->where('category_level_1', $categoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_1', $categoryId));
                });
            })
            ->when($subcategoryId, function ($query) use ($subcategoryId) {
                $query->whereHas('exams', function ($examQuery) use ($subcategoryId) {
                    $examQuery->where('category_level_2', $subcategoryId)
                        ->orWhereHas('packages', fn ($packageQuery) => $packageQuery->where('category_level_2', $subcategoryId));
                });
            })
            ->when($packageId, fn ($query) => $query->whereHas('exams.packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId)))
            ->when($examId, fn ($query) => $query->whereHas('exams', fn ($examQuery) => $examQuery->where('exams.id', $examId)));

        $hasQuestionContext = $groupId || $categoryId || $subcategoryId || $packageId || $examId;
        $subjectIds = $hasQuestionContext ? (clone $questionScope)->pluck('subject_id')->filter()->unique()->values() : collect();
        $topicIds = $hasQuestionContext || $subjectId ? (clone $questionScope)
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->pluck('topic_id')
            ->filter()
            ->unique()
            ->values() : collect();
        $subtopicIds = $hasQuestionContext || $subjectId || $topicId ? (clone $questionScope)
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($topicId, fn ($query) => $query->where('topic_id', $topicId))
            ->pluck('stopic_id')
            ->filter()
            ->unique()
            ->values() : collect();

        return [
            'groups' => Group::where('organization_id', $organizationId)->orderBy('group_name')->get(),
            'categories' => Category::where('organization_id', $organizationId)
                ->whereNull('parent_id')
                ->when($groupId, fn ($query) => $query->whereIn('id', $categoryIds))
                ->orderBy('title')
                ->get(),
            'subcategories' => Category::where('organization_id', $organizationId)
                ->whereNotNull('parent_id')
                ->when($categoryId, fn ($query) => $query->where('parent_id', $categoryId))
                ->when($groupId, fn ($query) => $query->whereIn('id', $categoryIds))
                ->orderBy('title')
                ->get(),
            'packages' => Package::where('organization_id', $organizationId)
                ->when($groupId, function ($query) use ($groupId) {
                    $query->where(function ($groupQuery) use ($groupId) {
                        $groupQuery->whereHas('groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId))
                            ->orWhereHas('exams.groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId));
                    });
                })
                ->when($categoryId, function ($query) use ($categoryId) {
                    $query->where(function ($categoryQuery) use ($categoryId) {
                        $categoryQuery->where('category_level_1', $categoryId)
                            ->orWhereHas('exams', fn ($examQuery) => $examQuery->where('category_level_1', $categoryId));
                    });
                })
                ->when($subcategoryId, function ($query) use ($subcategoryId) {
                    $query->where(function ($subcategoryQuery) use ($subcategoryId) {
                        $subcategoryQuery->where('category_level_2', $subcategoryId)
                            ->orWhereHas('exams', fn ($examQuery) => $examQuery->where('category_level_2', $subcategoryId));
                    });
                })
                ->orderBy('name')
                ->get(),
            'exams' => Exam::where('organization_id', $organizationId)
                ->when($groupId, function ($query) use ($groupId) {
                    $query->where(function ($groupQuery) use ($groupId) {
                        $groupQuery->whereHas('groups', fn ($examGroupQuery) => $examGroupQuery->where('groups.id', $groupId))
                            ->orWhereHas('packages.groups', fn ($packageGroupQuery) => $packageGroupQuery->where('groups.id', $groupId));
                    });
                })
                ->when($categoryId, function ($query) use ($categoryId) {
                    $query->where(function ($categoryQuery) use ($categoryId) {
                        $categoryQuery->where('category_level_1', $categoryId)
                            ->orWhereHas('packages', function ($packageQuery) use ($categoryId) {
                                $packageQuery->where('category_level_1', $categoryId);
                            });
                    });
                })
                ->when($subcategoryId, function ($query) use ($subcategoryId) {
                    $query->where(function ($subcategoryQuery) use ($subcategoryId) {
                        $subcategoryQuery->where('category_level_2', $subcategoryId)
                            ->orWhereHas('packages', function ($packageQuery) use ($subcategoryId) {
                                $packageQuery->where('category_level_2', $subcategoryId);
                            });
                    });
                })
                ->when($packageId, fn ($query) => $query->whereHas('packages', fn ($packageQuery) => $packageQuery->where('packages.id', $packageId)))
                ->orderBy('name')
                ->get(),
            'qtypes' => Qtype::displayOrdered(),
            'diffs' => Diff::orderBy('diff_level')->get(),
            'questionTags' => QuestionTag::where(function ($query) use ($organizationId) {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $organizationId);
            })
                ->where('status', true)
                ->orderBy('name')
                ->get(),
            'subjects' => Subject::whereHas('groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
                ->when($subjectIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $subjectIds))
                ->when($hasQuestionContext && $subjectIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('subject_name')
                ->get(),
            'topics' => Topic::whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
                ->when($topicIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $topicIds))
                ->when(($hasQuestionContext || $subjectId) && $topicIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->orderBy('name')
                ->get(),
            'stopics' => Stopic::whereHas('subject.groups', fn ($query) => $query->where('groups.organization_id', $organizationId))
                ->when($subtopicIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $subtopicIds))
                ->when(($hasQuestionContext || $subjectId || $topicId) && $subtopicIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
                ->when($topicId, fn ($query) => $query->where('topic_id', $topicId))
                ->orderBy('name')
                ->get(),
            'languages' => Language::enabledForOrganization($organizationId)->orderBy('name')->get(),
        ];
    }
}
