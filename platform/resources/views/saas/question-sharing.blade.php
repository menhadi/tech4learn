@extends('layouts.master')

@section('title', 'Question Sharing')

@section('content')
@php
    $perPageOptions = [50, 100, 500];
    $optionText = function ($value) {
        if (is_array($value)) {
            return ($value[app()->getLocale()] ?? $value['en'] ?? reset($value)) ?: '-';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return ($decoded[app()->getLocale()] ?? $decoded['en'] ?? reset($decoded)) ?: '-';
            }
        }

        return $value ?: '-';
    };
    $masterAdvancedFiltersActive = request()->filled('master_category_id')
        || request()->filled('master_subcategory_id')
        || request()->filled('master_package_id')
        || request()->filled('master_exam_id')
        || request()->filled('master_subject_id')
        || request()->filled('master_topic_id')
        || request()->filled('master_subtopic_id')
        || request()->filled('master_qtype_id')
        || request()->filled('master_diff_id')
        || request()->filled('master_tag_id')
        || request()->filled('master_language_id')
        || request()->filled('master_status')
        || request()->filled('master_marks_min')
        || request()->filled('master_marks_max')
        || request()->filled('master_negative_marks_min')
        || request()->filled('master_negative_marks_max')
        || request()->filled('master_has_image')
        || request()->filled('master_has_passage')
        || request()->filled('master_ai_generated');
    $organizationAdvancedFiltersActive = request()->filled('org_category_id')
        || request()->filled('org_subcategory_id')
        || request()->filled('org_package_id')
        || request()->filled('org_exam_id')
        || request()->filled('org_subject_id')
        || request()->filled('org_topic_id')
        || request()->filled('org_subtopic_id')
        || request()->filled('org_qtype_id')
        || request()->filled('org_diff_id')
        || request()->filled('org_tag_id')
        || request()->filled('org_language_id')
        || request()->filled('org_status')
        || request()->filled('org_marks_min')
        || request()->filled('org_marks_max')
        || request()->filled('org_negative_marks_min')
        || request()->filled('org_negative_marks_max')
        || request()->filled('org_has_image')
        || request()->filled('org_has_passage')
        || request()->filled('org_ai_generated');
    $statusOptions = ['Yes' => 'Active', 'No' => 'Inactive'];
    $questionPreview = function ($question) {
        $rawQuestion = (string) $question->question;
        $questionText = trim(strip_tags($rawQuestion));

        if ($questionText === '') {
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawQuestion, $imageMatch);
            $questionText = $imageMatch[1] ?? trim($rawQuestion);
        }

        return preg_replace('/\s+/', ' ', $questionText ?? '') ?: 'Untitled question';
    };
@endphp

<style>
    #masterSharingCard.el-filter-working,
    #organizationSharingCard.el-filter-working,
    #masterSharingCard .el-filter-working,
    #organizationSharingCard .el-filter-working {
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    #masterSharingCard.el-filter-working::before,
    #organizationSharingCard.el-filter-working::before,
    #masterSharingCard .el-filter-working::before,
    #organizationSharingCard .el-filter-working::before,
    #masterSharingCard.el-filter-working::after,
    #organizationSharingCard.el-filter-working::after,
    #masterSharingCard .el-filter-working::after,
    #organizationSharingCard .el-filter-working::after {
        content: none !important;
        display: none !important;
    }

    .saas-sharing-loading {
        align-items: center;
        color: var(--el-primary, var(--vz-primary, #0f766e));
        display: none;
        font-weight: 600;
        gap: 8px;
    }

    .saas-sharing-loading::before {
        animation: saas-sharing-pulse 0.75s ease-in-out infinite alternate;
        background: var(--el-primary, var(--vz-primary, #0f766e));
        border-radius: 999px;
        content: "";
        height: 8px;
        width: 8px;
    }

    .saas-sharing-updating .saas-sharing-loading {
        display: inline-flex;
    }

    .saas-sharing-updating .js-sharing-count {
        display: none;
    }

    .saas-sharing-updating .el-filter-bar :is(input, select, button, a),
    .saas-sharing-updating .el-table-toolbar :is(input, select, button, a) {
        pointer-events: none;
    }

    @keyframes saas-sharing-pulse {
        from {
            opacity: 0.45;
            transform: scale(0.85);
        }
        to {
            opacity: 1;
            transform: scale(1.15);
        }
    }
</style>

<div class="container-fluid px-2 px-lg-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h4 class="mb-1">Question Sharing</h4>
            <p class="text-muted mb-0">Copy questions between the platform bank and organizations without linking the edited copies.</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('saas.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> SaaS Control Center
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{!! implode('<br>', $errors->all()) !!}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4" id="masterSharingCard">
        <div class="card-header bg-white">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h5 class="card-title mb-1">Share Platform Questions to Organizations</h5>
                    <p class="text-muted mb-0">Selected questions are copied into target organizations with their subject, topic, subtopic, passage, language and groups created when missing.</p>
                </div>
                <div class="small">
                    <span class="text-muted js-sharing-count">Showing {{ $masterQuestions->count() }} of {{ $masterQuestions->total() }}</span>
                    <span class="saas-sharing-loading">Updating questions...</span>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form id="masterFilterForm" method="GET" action="{{ route('saas.question-sharing.index') }}" class="row g-2 align-items-end mb-3 el-filter-bar js-auto-filter-form" data-ajax-targets="#masterSharingCard,#organizationSharingCard">
                <input type="hidden" name="organization_id" value="{{ $selectedOrganization?->id }}">
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Group</label>
                    <select id="masterGroupFilter" class="form-select js-searchable-select js-cascade-filter" name="master_group_id" data-clear="#masterCategoryFilter,#masterSubcategoryFilter,#masterPackageFilter,#masterExamFilter,#masterSubjectFilter,#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All groups</option>
                        @foreach($masterFilters['groups'] as $group)
                            <option value="{{ $group->id }}" @selected((int) request('master_group_id') === $group->id)>{{ $optionText($group->group_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-auto col-md-auto">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button class="btn el-btn-primary el-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#masterAdvancedFilters" aria-expanded="{{ $masterAdvancedFiltersActive ? 'true' : 'false' }}" aria-controls="masterAdvancedFilters">
                        <i class="ri-equalizer-line"></i> Advanced Filters
                    </button>
                </div>
                <div class="col-lg-auto col-md-auto">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn el-btn-primary"><i class="ri-filter-3-line"></i> Apply Filters</button>
                    <a href="{{ route('saas.question-sharing.index', ['organization_id' => $selectedOrganization?->id, 'per_page' => $perPage]) }}" class="btn el-btn-secondary js-ajax-reset" data-targets="#masterSharingCard,#organizationSharingCard">Reset</a>
                </div>
                <div id="masterAdvancedFilters" class="collapse {{ $masterAdvancedFiltersActive ? 'show' : '' }} w-100">
                <div class="row g-2 align-items-end mt-2 border-top pt-3">
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Category</label>
                    <select id="masterCategoryFilter" class="form-select js-searchable-select js-cascade-filter" name="master_category_id" data-clear="#masterSubcategoryFilter,#masterPackageFilter,#masterExamFilter,#masterSubjectFilter,#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All categories</option>
                        @foreach($masterFilters['categories'] as $category)
                            <option value="{{ $category->id }}" @selected((int) request('master_category_id') === $category->id)>{{ $optionText($category->title) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6" data-subcategory-ui>
                    <label class="form-label">Subcategory</label>
                    <select id="masterSubcategoryFilter" class="form-select js-searchable-select js-cascade-filter" name="master_subcategory_id" data-clear="#masterPackageFilter,#masterExamFilter,#masterSubjectFilter,#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All subcategories</option>
                        @foreach($masterFilters['subcategories'] as $category)
                            <option value="{{ $category->id }}" @selected((int) request('master_subcategory_id') === $category->id)>{{ $optionText($category->title) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Package</label>
                    <select id="masterPackageFilter" class="form-select js-searchable-select js-cascade-filter" name="master_package_id" data-clear="#masterExamFilter,#masterSubjectFilter,#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All packages</option>
                        @foreach($masterFilters['packages'] as $package)
                            <option value="{{ $package->id }}" @selected((int) request('master_package_id') === $package->id)>{{ $optionText($package->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Exam</label>
                    <select id="masterExamFilter" class="form-select js-searchable-select js-cascade-filter" name="master_exam_id" data-clear="#masterSubjectFilter,#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All exams</option>
                        @foreach($masterFilters['exams'] as $exam)
                            <option value="{{ $exam->id }}" @selected((int) request('master_exam_id') === $exam->id)>{{ $exam->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Subject</label>
                    <select id="masterSubjectFilter" class="form-select js-searchable-select js-cascade-filter" name="master_subject_id" data-clear="#masterTopicFilter,#masterSubtopicFilter">
                        <option value="">All subjects</option>
                        @foreach($masterFilters['subjects'] as $subject)
                            <option value="{{ $subject->id }}" @selected((int) request('master_subject_id') === $subject->id)>{{ $subject->subject_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Topic</label>
                    <select id="masterTopicFilter" class="form-select js-searchable-select js-cascade-filter" name="master_topic_id" data-clear="#masterSubtopicFilter">
                        <option value="">All topics</option>
                        @foreach($masterFilters['topics'] as $topic)
                            <option value="{{ $topic->id }}" @selected((int) request('master_topic_id') === $topic->id)>{{ $topic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Subtopic</label>
                    <select id="masterSubtopicFilter" class="form-select js-searchable-select" name="master_subtopic_id">
                        <option value="">All subtopics</option>
                        @foreach($masterFilters['stopics'] as $stopic)
                            <option value="{{ $stopic->id }}" @selected((int) request('master_subtopic_id') === $stopic->id)>{{ $stopic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Question Type</label>
                    <select class="form-select js-searchable-select" name="master_qtype_id">
                        <option value="">All types</option>
                        @foreach($masterFilters['qtypes'] as $qtype)
                            <option value="{{ $qtype->id }}" @selected((int) request('master_qtype_id') === $qtype->id)>{{ $qtype->question_type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Difficulty</label>
                    <select class="form-select js-searchable-select" name="master_diff_id">
                        <option value="">All difficulty</option>
                        @foreach($masterFilters['diffs'] as $diff)
                            <option value="{{ $diff->id }}" @selected((int) request('master_diff_id') === $diff->id)>{{ $diff->diff_level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Question Tags</label>
                    <select class="form-select js-searchable-select" name="master_tag_id">
                        <option value="">All tags</option>
                        @foreach($masterFilters['questionTags'] as $tag)
                            <option value="{{ $tag->id }}" @selected((int) request('master_tag_id') === $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Language</label>
                    <select class="form-select js-searchable-select" name="master_language_id">
                        <option value="">All languages</option>
                        @foreach($masterFilters['languages'] as $language)
                            <option value="{{ $language->id }}" @selected((int) request('master_language_id') === $language->id)>{{ $language->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select js-searchable-select" name="master_status">
                        <option value="">All status</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('master_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Positive Marks</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="master_marks_min" value="{{ request('master_marks_min') }}" placeholder="Min">
                        <input type="number" step="0.01" class="form-control" name="master_marks_max" value="{{ request('master_marks_max') }}" placeholder="Max">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Negative Marks</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="master_negative_marks_min" value="{{ request('master_negative_marks_min') }}" placeholder="Min">
                        <input type="number" step="0.01" class="form-control" name="master_negative_marks_max" value="{{ request('master_negative_marks_max') }}" placeholder="Max">
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Image</label>
                    <select class="form-select js-searchable-select" name="master_has_image">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('master_has_image') === 'yes')>Has Image</option>
                        <option value="no" @selected(request('master_has_image') === 'no')>No Image</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Passage</label>
                    <select class="form-select js-searchable-select" name="master_has_passage">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('master_has_passage') === 'yes')>Has Passage</option>
                        <option value="no" @selected(request('master_has_passage') === 'no')>No Passage</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">AI</label>
                    <select class="form-select js-searchable-select" name="master_ai_generated">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('master_ai_generated') === 'yes')>AI Generated</option>
                        <option value="no" @selected(request('master_ai_generated') === 'no')>Manual</option>
                    </select>
                </div>
                </div>
                </div>
            </form>

            <form method="POST" action="{{ route('saas.question-sharing.share') }}">
                @csrf

                <div class="el-table-toolbar">
                    <div class="el-table-toolbar-actions">
                        <div class="dropdown el-target-org-selector">
                            <button class="btn el-btn-soft w-100 text-start dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                Select organizations
                            </button>
                            <div class="dropdown-menu p-2 w-100">
                                <div class="search-box mb-2">
                                    <input type="text" class="form-control form-control-sm js-filter-list" data-target="#targetOrganizationList" placeholder="Search organization">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input js-select-visible" type="checkbox" id="selectVisibleOrganizations" data-target="#targetOrganizationList">
                                    <label class="form-check-label" for="selectVisibleOrganizations">Select visible organizations</label>
                                </div>
                                <div id="targetOrganizationList" class="border p-2 overflow-auto" style="max-height: 220px;">
                                    <div class="row g-2">
                                        @foreach($organizations as $organization)
                                            <div class="col-12 js-filter-item" data-search="{{ strtolower($organization->name.' '.$organization->domain.' '.$organization->subdomain) }}">
                                                <label class="form-check d-block py-1">
                                                    <input class="form-check-input" type="checkbox" name="organization_ids[]" value="{{ $organization->id }}">
                                                    <span class="form-check-label">
                                                        {{ $organization->name }}
                                                        <span class="text-muted small d-block">{{ $organization->domain ?: $organization->subdomain }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="el-table-toolbar-controls">
                        <div class="el-filter-search">
                            <div class="search-box">
                                <input type="text" class="form-control search js-external-auto-filter" form="masterFilterForm" data-form="#masterFilterForm" name="master_search" value="{{ request('master_search') }}" placeholder="Question, ID or subject">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                        <div class="el-page-size">
                            <select class="form-select js-external-auto-filter" form="masterFilterForm" data-form="#masterFilterForm" name="per_page">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per page</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 el-table">
                        <thead>
                            <tr>
                                <th style="width: 42px;">
                                    <input class="form-check-input js-select-table" type="checkbox" data-target="#masterQuestionTable">
                                </th>
                                <th>Question</th>
                                <th>Group</th>
                                <th>Package / Exam</th>
                                <th>Type</th>
                                <th>Subject</th>
                            </tr>
                        </thead>
                        <tbody id="masterQuestionTable">
                            @if($masterQuestions->count())
                            @foreach($masterQuestions as $question)
                                @php
                                    $groups = $question->groups->pluck('group_name')->filter()->take(2)->map($optionText)->implode(', ');
                                    $exams = $question->exams->pluck('name')->filter()->take(2)->implode(', ');
                                    $packages = $question->exams->flatMap->packages->pluck('name')->filter()->unique()->take(2)->map($optionText)->implode(', ');
                                    $previewText = $questionPreview($question);
                                @endphp
                                <tr>
                                    <td><input class="form-check-input" type="checkbox" name="question_ids[]" value="{{ $question->id }}"></td>
                                    <td>
                                        <div class="fw-medium" title="{{ $previewText }}">{{ \Illuminate\Support\Str::limit($previewText, 130) }}</div>
                                        <div class="text-muted small">ID {{ $question->id }}</div>
                                    </td>
                                    <td>{{ $groups ?: '-' }}</td>
                                    <td>
                                        <div>{{ $packages ?: '-' }}</div>
                                        <div class="text-muted small">{{ $exams ?: '-' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $question->qtype?->question_type ?: '-' }}</div>
                                        <div class="text-muted small">{{ $question->diff?->diff_level ?: '-' }}</div>
                                        <div class="text-muted small">+{{ $question->marks ?? 0 }} / -{{ $question->negative_marks ?? 0 }}</div>
                                    </td>
                                    <td>{{ $question->subject?->subject_name ?: '-' }}</td>
                                </tr>
                            @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No platform questions found.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    {{ $masterQuestions->links('vendor.pagination.custom') }}
                    <button type="submit" class="btn el-btn-primary">
                        <i class="ri-share-forward-line"></i> Share Selected
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" id="organizationSharingCard">
        <div class="card-header bg-white">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h5 class="card-title mb-1">Copy Organization Questions to Platform</h5>
                    <p class="text-muted mb-0">Choose an organization, filter its questions, then copy selected questions into the platform bank.</p>
                </div>
                @if(method_exists($organizationQuestions, 'total'))
                    <div class="small">
                        <span class="text-muted js-sharing-count">Showing {{ $organizationQuestions->count() }} of {{ $organizationQuestions->total() }}</span>
                        <span class="saas-sharing-loading">Updating questions...</span>
                    </div>
                @endif
            </div>
        </div>
        <div class="card-body">
            <form id="organizationFilterForm" method="GET" action="{{ route('saas.question-sharing.index') }}" class="row g-2 align-items-end mb-3 el-filter-bar js-auto-filter-form" data-ajax-targets="#masterSharingCard,#organizationSharingCard">
                <div class="col-xl-3 col-md-6">
                    <label class="form-label">Organization</label>
                    <select class="form-select js-searchable-select js-cascade-filter" name="organization_id" data-clear="#orgGroupFilter,#orgCategoryFilter,#orgSubcategoryFilter,#orgPackageFilter,#orgExamFilter,#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        @foreach($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected($selectedOrganization?->id === $organization->id)>{{ $organization->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Group</label>
                    <select id="orgGroupFilter" class="form-select js-searchable-select js-cascade-filter" name="org_group_id" data-clear="#orgCategoryFilter,#orgSubcategoryFilter,#orgPackageFilter,#orgExamFilter,#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All groups</option>
                        @foreach($organizationFilters['groups'] as $group)
                            <option value="{{ $group->id }}" @selected((int) request('org_group_id') === $group->id)>{{ $optionText($group->group_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-auto col-md-auto">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button class="btn el-btn-primary el-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#organizationAdvancedFilters" aria-expanded="{{ $organizationAdvancedFiltersActive ? 'true' : 'false' }}" aria-controls="organizationAdvancedFilters">
                        <i class="ri-equalizer-line"></i> Advanced Filters
                    </button>
                </div>
                <div class="col-xl-auto col-md-auto">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn el-btn-primary"><i class="ri-filter-3-line"></i> Apply Filters</button>
                    <a href="{{ route('saas.question-sharing.index', ['organization_id' => $selectedOrganization?->id, 'per_page' => $perPage]) }}" class="btn el-btn-secondary js-ajax-reset" data-targets="#masterSharingCard,#organizationSharingCard">Reset</a>
                </div>
                <div id="organizationAdvancedFilters" class="collapse {{ $organizationAdvancedFiltersActive ? 'show' : '' }} w-100">
                <div class="row g-2 align-items-end mt-2 border-top pt-3">
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Category</label>
                    <select id="orgCategoryFilter" class="form-select js-searchable-select js-cascade-filter" name="org_category_id" data-clear="#orgSubcategoryFilter,#orgPackageFilter,#orgExamFilter,#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All categories</option>
                        @foreach($organizationFilters['categories'] as $category)
                            <option value="{{ $category->id }}" @selected((int) request('org_category_id') === $category->id)>{{ $optionText($category->title) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6" data-subcategory-ui>
                    <label class="form-label">Subcategory</label>
                    <select id="orgSubcategoryFilter" class="form-select js-searchable-select js-cascade-filter" name="org_subcategory_id" data-clear="#orgPackageFilter,#orgExamFilter,#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All subcategories</option>
                        @foreach($organizationFilters['subcategories'] as $category)
                            <option value="{{ $category->id }}" @selected((int) request('org_subcategory_id') === $category->id)>{{ $optionText($category->title) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Package</label>
                    <select id="orgPackageFilter" class="form-select js-searchable-select js-cascade-filter" name="org_package_id" data-clear="#orgExamFilter,#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All packages</option>
                        @foreach($organizationFilters['packages'] as $package)
                            <option value="{{ $package->id }}" @selected((int) request('org_package_id') === $package->id)>{{ $optionText($package->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Exam</label>
                    <select id="orgExamFilter" class="form-select js-searchable-select js-cascade-filter" name="org_exam_id" data-clear="#orgSubjectFilter,#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All exams</option>
                        @foreach($organizationFilters['exams'] as $exam)
                            <option value="{{ $exam->id }}" @selected((int) request('org_exam_id') === $exam->id)>{{ $exam->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Subject</label>
                    <select id="orgSubjectFilter" class="form-select js-searchable-select js-cascade-filter" name="org_subject_id" data-clear="#orgTopicFilter,#orgSubtopicFilter">
                        <option value="">All subjects</option>
                        @foreach($organizationFilters['subjects'] as $subject)
                            <option value="{{ $subject->id }}" @selected((int) request('org_subject_id') === $subject->id)>{{ $subject->subject_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Topic</label>
                    <select id="orgTopicFilter" class="form-select js-searchable-select js-cascade-filter" name="org_topic_id" data-clear="#orgSubtopicFilter">
                        <option value="">All topics</option>
                        @foreach($organizationFilters['topics'] as $topic)
                            <option value="{{ $topic->id }}" @selected((int) request('org_topic_id') === $topic->id)>{{ $topic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Subtopic</label>
                    <select id="orgSubtopicFilter" class="form-select js-searchable-select" name="org_subtopic_id">
                        <option value="">All subtopics</option>
                        @foreach($organizationFilters['stopics'] as $stopic)
                            <option value="{{ $stopic->id }}" @selected((int) request('org_subtopic_id') === $stopic->id)>{{ $stopic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Question Type</label>
                    <select class="form-select js-searchable-select" name="org_qtype_id">
                        <option value="">All types</option>
                        @foreach($organizationFilters['qtypes'] as $qtype)
                            <option value="{{ $qtype->id }}" @selected((int) request('org_qtype_id') === $qtype->id)>{{ $qtype->question_type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Difficulty</label>
                    <select class="form-select js-searchable-select" name="org_diff_id">
                        <option value="">All difficulty</option>
                        @foreach($organizationFilters['diffs'] as $diff)
                            <option value="{{ $diff->id }}" @selected((int) request('org_diff_id') === $diff->id)>{{ $diff->diff_level }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Question Tags</label>
                    <select class="form-select js-searchable-select" name="org_tag_id">
                        <option value="">All tags</option>
                        @foreach($organizationFilters['questionTags'] as $tag)
                            <option value="{{ $tag->id }}" @selected((int) request('org_tag_id') === $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Language</label>
                    <select class="form-select js-searchable-select" name="org_language_id">
                        <option value="">All languages</option>
                        @foreach($organizationFilters['languages'] as $language)
                            <option value="{{ $language->id }}" @selected((int) request('org_language_id') === $language->id)>{{ $language->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select js-searchable-select" name="org_status">
                        <option value="">All status</option>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('org_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Positive Marks</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="org_marks_min" value="{{ request('org_marks_min') }}" placeholder="Min">
                        <input type="number" step="0.01" class="form-control" name="org_marks_max" value="{{ request('org_marks_max') }}" placeholder="Max">
                    </div>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Negative Marks</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" name="org_negative_marks_min" value="{{ request('org_negative_marks_min') }}" placeholder="Min">
                        <input type="number" step="0.01" class="form-control" name="org_negative_marks_max" value="{{ request('org_negative_marks_max') }}" placeholder="Max">
                    </div>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Image</label>
                    <select class="form-select js-searchable-select" name="org_has_image">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('org_has_image') === 'yes')>Has Image</option>
                        <option value="no" @selected(request('org_has_image') === 'no')>No Image</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">Passage</label>
                    <select class="form-select js-searchable-select" name="org_has_passage">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('org_has_passage') === 'yes')>Has Passage</option>
                        <option value="no" @selected(request('org_has_passage') === 'no')>No Passage</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label">AI</label>
                    <select class="form-select js-searchable-select" name="org_ai_generated">
                        <option value="">Any</option>
                        <option value="yes" @selected(request('org_ai_generated') === 'yes')>AI Generated</option>
                        <option value="no" @selected(request('org_ai_generated') === 'no')>Manual</option>
                    </select>
                </div>
                </div>
                </div>
            </form>

            <form method="POST" action="{{ route('saas.question-sharing.copy-to-master') }}">
                    @csrf
                    <input type="hidden" name="source_organization_id" value="{{ $selectedOrganization?->id }}">

                    <div class="el-table-toolbar">
                        <div class="el-table-toolbar-actions"></div>
                        <div class="el-table-toolbar-controls">
                            <div class="el-filter-search">
                                <div class="search-box">
                                    <input type="text" class="form-control search js-external-auto-filter" form="organizationFilterForm" data-form="#organizationFilterForm" name="org_search" value="{{ request('org_search') }}" placeholder="Question, ID or subject">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                            </div>
                            <div class="el-page-size">
                                <select class="form-select js-external-auto-filter" form="organizationFilterForm" data-form="#organizationFilterForm" name="per_page">
                                    @foreach($perPageOptions as $option)
                                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per page</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 el-table">
                            <thead>
                                <tr>
                                    <th style="width: 42px;">
                                        <input class="form-check-input js-select-table" type="checkbox" data-target="#organizationQuestionTable">
                                    </th>
                                    <th>Question</th>
                                    <th>Group</th>
                                    <th>Package / Exam</th>
                                    <th>Type</th>
                                    <th>Subject</th>
                                </tr>
                            </thead>
                            <tbody id="organizationQuestionTable">
                                @if(method_exists($organizationQuestions, 'count') && $organizationQuestions->count())
                                @foreach($organizationQuestions as $question)
                                    @php
                                        $groups = $question->groups->pluck('group_name')->filter()->take(2)->map($optionText)->implode(', ');
                                        $exams = $question->exams->pluck('name')->filter()->take(2)->implode(', ');
                                        $packages = $question->exams->flatMap->packages->pluck('name')->filter()->unique()->take(2)->map($optionText)->implode(', ');
                                        $previewText = $questionPreview($question);
                                    @endphp
                                    <tr>
                                        <td><input class="form-check-input" type="checkbox" name="question_ids[]" value="{{ $question->id }}"></td>
                                        <td>
                                            <div class="fw-medium" title="{{ $previewText }}">{{ \Illuminate\Support\Str::limit($previewText, 130) }}</div>
                                            <div class="text-muted small">ID {{ $question->id }}</div>
                                        </td>
                                        <td>{{ $groups ?: '-' }}</td>
                                        <td>
                                            <div>{{ $packages ?: '-' }}</div>
                                            <div class="text-muted small">{{ $exams ?: '-' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $question->qtype?->question_type ?: '-' }}</div>
                                            <div class="text-muted small">{{ $question->diff?->diff_level ?: '-' }}</div>
                                            <div class="text-muted small">+{{ $question->marks ?? 0 }} / -{{ $question->negative_marks ?? 0 }}</div>
                                        </td>
                                        <td>{{ $question->subject?->subject_name ?: '-' }}</td>
                                    </tr>
                                @endforeach
                                @else
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No questions found for this organization.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        @if(method_exists($organizationQuestions, 'links'))
                            {{ $organizationQuestions->links('vendor.pagination.custom') }}
                        @endif
                        <button type="submit" class="btn el-btn-soft">
                            <i class="ri-inbox-archive-line"></i> Copy Selected to Platform
                        </button>
                    </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Recent Sharing</h5>
        </div>
        <div class="card-body">
            @forelse($recentBatches as $batch)
                <div class="border-bottom pb-2 mb-2">
                    <div class="fw-medium">{{ str_replace('_', ' ', ucfirst($batch->direction)) }}</div>
                    <div class="text-muted small">
                        {{ $batch->question_count }} questions | {{ $batch->created_at->diffForHumans() }}
                    </div>
                </div>
            @empty
                <div class="text-muted">No sharing history yet.</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function loadQuestionSharingUrl(url, targets) {
    targets = targets && targets.length ? targets : ['#masterSharingCard', '#organizationSharingCard'];
    const targetElements = targets
        .map(function (selector) {
            return document.querySelector(selector);
        })
        .filter(Boolean);

    targetElements.forEach(function (element) {
        element.classList.add('saas-sharing-updating');
    });

    fetch(url.toString(), {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(function (response) {
        if (!response.ok) {
            throw new Error('Unable to refresh question sharing.');
        }

        return response.text();
    }).then(function (html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');

        targets.forEach(function (selector) {
            const current = document.querySelector(selector);
            const incoming = doc.querySelector(selector);

            if (current && incoming) {
                current.innerHTML = incoming.innerHTML;
            }
        });

        targetElements.forEach(function (element) {
            element.classList.remove('saas-sharing-updating');
        });

        window.history.pushState({}, '', url.toString());
        document.dispatchEvent(new CustomEvent('examelite:ajax-filtered', {
            detail: { url: url.toString() }
        }));
    }).catch(function () {
        targetElements.forEach(function (element) {
            element.classList.remove('saas-sharing-updating');
        });
        window.location.href = url.toString();
    });
}

function initQuestionSharing(root) {
    root = root || document;

    function formUrl(form) {
        const url = new URL(form.action || window.location.href, window.location.origin);
        const formData = new FormData(form);
        url.search = '';

        formData.forEach(function (value, key) {
            if (value !== null && String(value) !== '') {
                url.searchParams.append(key, value);
            }
        });

        return url;
    }

    function ajaxTargets(form) {
        return (form.dataset.ajaxTargets || '#masterSharingCard,#organizationSharingCard')
            .split(',')
            .map(item => item.trim())
            .filter(Boolean);
    }

    function refreshSharing(form) {
        if (!form || typeof fetch === 'undefined') {
            if (form && form.requestSubmit) {
                form.requestSubmit();
            } else if (form) {
                form.submit();
            }
            return;
        }

        loadQuestionSharingUrl(formUrl(form), ajaxTargets(form));
    }

    root.querySelectorAll('.js-filter-list:not([data-sharing-ready])').forEach(function (input) {
        input.dataset.sharingReady = '1';
        input.addEventListener('input', function () {
            const target = document.querySelector(input.dataset.target);
            if (!target) return;

            const search = input.value.toLowerCase();

            target.querySelectorAll('.js-filter-item').forEach(function (item) {
                item.classList.toggle('d-none', !item.dataset.search.includes(search));
            });
        });
    });

    root.querySelectorAll('.js-select-visible:not([data-sharing-ready])').forEach(function (checkbox) {
        checkbox.dataset.sharingReady = '1';
        checkbox.addEventListener('change', function () {
            const target = document.querySelector(checkbox.dataset.target);
            if (!target) return;

            target.querySelectorAll('.js-filter-item:not(.d-none) input[type="checkbox"]').forEach(function (itemCheckbox) {
                itemCheckbox.checked = checkbox.checked;
            });
        });
    });

    root.querySelectorAll('.js-select-table:not([data-sharing-ready])').forEach(function (checkbox) {
        checkbox.dataset.sharingReady = '1';
        checkbox.addEventListener('change', function () {
            const target = document.querySelector(checkbox.dataset.target);
            if (!target) return;

            target.querySelectorAll('input[type="checkbox"]').forEach(function (itemCheckbox) {
                itemCheckbox.checked = checkbox.checked;
            });
        });
    });

    if (window.jQuery && jQuery.fn.select2) {
        jQuery(root).find('.js-searchable-select').each(function () {
            const $select = jQuery(this);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            $select.select2({
                width: '100%',
                allowClear: false,
                dropdownAutoWidth: true
            });
        });
    }

    root.querySelectorAll('.js-cascade-filter:not([data-sharing-ready])').forEach(function (select) {
        select.dataset.sharingReady = '1';
        select.addEventListener('change', function () {
            if (select.dataset.clear) {
                document.querySelectorAll(select.dataset.clear).forEach(function (dependentSelect) {
                    dependentSelect.value = '';
                    if (window.jQuery && jQuery.fn.select2) {
                        jQuery(dependentSelect).trigger('change.select2');
                    }
                });
            }
        });
    });

    root.querySelectorAll('.js-ajax-reset:not([data-sharing-ready])').forEach(function (link) {
        link.dataset.sharingReady = '1';
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const targets = (link.dataset.targets || '').split(',').map(item => item.trim()).filter(Boolean);
            loadQuestionSharingUrl(new URL(link.href), targets);
        });
    });

    root.querySelectorAll('.js-auto-filter-form:not([data-sharing-filter-ready])').forEach(function (form) {
        form.dataset.sharingFilterReady = '1';
        let filterTimer;

        function refreshForm(delay) {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(function () {
                refreshSharing(form);
            }, delay);
        }

        form.addEventListener('submit', function (event) {
            if (typeof fetch === 'undefined') {
                return;
            }

            event.preventDefault();
            refreshSharing(form);
        });

        if (window.jQuery) {
            jQuery(form).find('select').on('change.saasSharingFilter', function () {
                refreshForm(150);
            });
        } else {
            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    refreshForm(150);
                });
            });
        }

        form.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (input) {
            input.addEventListener('input', function () {
                refreshForm(650);
            });
        });
    });

    root.querySelectorAll('.js-external-auto-filter:not([data-sharing-filter-ready])').forEach(function (control) {
        control.dataset.sharingFilterReady = '1';
        let externalTimer;

        function refreshExternal(delay) {
            clearTimeout(externalTimer);
            externalTimer = setTimeout(function () {
                const form = document.querySelector(control.dataset.form);
                if (!form) return;

                refreshSharing(form);
            }, delay);
        }

        control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', function () {
            refreshExternal(control.tagName === 'SELECT' ? 150 : 650);
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initQuestionSharing(document);
});

document.addEventListener('examelite:ajax-filtered', function (event) {
    document.querySelectorAll('#masterSharingCard.el-filter-working, #organizationSharingCard.el-filter-working, #masterSharingCard .el-filter-working, #organizationSharingCard .el-filter-working').forEach(function (element) {
        element.classList.remove('el-filter-working');
    });
    initQuestionSharing(document);
});

document.addEventListener('click', function (event) {
    const link = event.target.closest('#masterSharingCard .pagination a, #organizationSharingCard .pagination a');
    if (!link || typeof fetch === 'undefined') {
        return;
    }

    event.preventDefault();
    loadQuestionSharingUrl(new URL(link.href), ['#masterSharingCard', '#organizationSharingCard']);
});
</script>
@endsection
