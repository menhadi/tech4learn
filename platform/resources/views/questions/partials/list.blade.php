@php
    $canAddQuestion = $canAddQuestion ?? user_can_route_action('questions.create', 'add');
    $canEditQuestion = $canEditQuestion ?? user_can_route_action('questions.edit', 'edit');
    $canDeleteQuestion = $canDeleteQuestion ?? user_can_route_action('questions.destroy', 'delete');
    $showSubtopicColumn = request()->filled('subtopic');
    $showDifficultyColumn = request()->filled('diff');
    $showLanguageColumn = request()->filled('language');
    $showMarksColumn = request()->filled('marks_min') || request()->filled('marks_max');
    $showNegativeMarksColumn = request()->filled('negative_marks_min') || request()->filled('negative_marks_max');
    $showStatusColumn = request()->filled('status');
    $showTagsColumn = request()->filled('tag');
    $showPassageColumn = request()->filled('has_passage');
    $temporaryColumnCount = collect([
        $showSubtopicColumn,
        $showDifficultyColumn,
        $showLanguageColumn,
        $showMarksColumn,
        $showNegativeMarksColumn,
        $showStatusColumn,
        $showTagsColumn,
        $showPassageColumn,
    ])->filter()->count();
@endphp

<div id="questionList" data-result-count="Showing {{ $questions->count() }} questions{{ $questions->hasMorePages() ? ' (more available)' : '' }}">
    <div class="el-table-toolbar el-table-toolbar-stacked">
        <div class="el-table-toolbar-actions">
            @if($canAddQuestion)
                <a href="{{ route('questions.importExport') }}" class="btn el-btn-primary el-btn-icon add-btn">
                    <i class="ri-file-upload-line align-bottom me-1"></i> Import/Export
                </a>
                <a href="{{ route('ai.generator.form') }}" class="btn el-btn-primary el-btn-icon add-btn {{ \App\Support\SaasAccess::featureEnabled('ai_generator') ? '' : 'js-plan-feature-locked' }}" data-plan-feature="{{ \App\Support\SaasAccess::featureEnabled('ai_generator') ? '' : 'ai_generator' }}">
                    <i class="ri-magic-line align-bottom me-1"></i> AI Generator
                </a>
                <button type="button" class="btn el-btn-primary el-btn-icon {{ \App\Support\SaasAccess::featureEnabled('ai_regeneration') ? '' : 'js-plan-feature-locked' }}" id="aiRegenerateBtn" data-plan-feature="{{ \App\Support\SaasAccess::featureEnabled('ai_regeneration') ? '' : 'ai_regeneration' }}">
                    <i class="ri-ai-generate-line"></i> AI Regenerate Selected Questions
                </button>
                <a href="{{ route('questions.create') }}" class="btn el-btn-primary el-btn-icon add-btn">
                    <i class="ri-add-line align-bottom me-1"></i> Add
                </a>
            @endif
            @if($canEditQuestion)
                <a href="{{ route('admin.bulk-editor.index', 'questions') }}" class="btn el-btn-secondary el-btn-icon">
                    <i class="ri-edit-box-line align-bottom me-1"></i> Bulk Edit
                </a>
                <x-google-sheets-button resource="questions" :filters="request()->query()" />
            @endif
            @if($canDeleteQuestion)
                <button id="bulk-delete-btn" class="btn el-btn-danger el-btn-icon">
                    <i class="ri-delete-bin-line align-bottom me-1"></i> Bulk Delete
                </button>
            @endif
        </div>
        <div class="el-table-toolbar-controls">
            <div class="el-filter-search">
                <div class="search-box">
                    <input type="text" id="search-input" class="form-control search" placeholder="Search questions..." value="{{ request('search') }}">
                    <i class="ri-search-line search-icon"></i>
                </div>
            </div>
            <div class="el-page-size">
                <select id="per-page-select" class="form-select">
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 per page</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 per page</option>
                    <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500 per page</option>
                </select>
            </div>
        </div>
    </div>
    <div class="table-responsive table-card mt-3 mb-1">
        <table class="table table-hover align-middle el-table" id="questionTable">
            <thead>
                <tr>
                    @if($canDeleteQuestion)
                        <th><input type="checkbox" id="select-all"></th>
                    @endif
                    <th>Question</th>
                    <th>Exam usage</th>
                    <th>Group</th>
                    <th>Subject</th>
                    <th>Topic</th>
                    @if($showSubtopicColumn)
                        <th>Subtopic</th>
                    @endif
                    <th>Type</th>
                    @if($showTagsColumn)
                        <th>Tags</th>
                    @endif
                    @if($showDifficultyColumn)
                        <th>Difficulty</th>
                    @endif
                    @if($showLanguageColumn)
                        <th>Language</th>
                    @endif
                    @if($showMarksColumn)
                        <th>Marks</th>
                    @endif
                    @if($showNegativeMarksColumn)
                        <th>Negative</th>
                    @endif
                    @if($showStatusColumn)
                        <th>Status</th>
                    @endif
                    @if($showPassageColumn)
                        <th>Passage</th>
                    @endif
                    @if($canEditQuestion || $canDeleteQuestion)
                        <th>Action</th>
                    @endif
                </tr>
            </thead>
            <tbody class="list form-check-all">
                @forelse ($questions as $question)
                    <tr>
                        @if($canDeleteQuestion)
                            <td>
                                <input type="checkbox" class="question-checkbox" value="{{ $question->id }}">
                            </td>
                        @endif
                        <td>
                            @php
                                $rawQuestion = (string) $question->question;
                                $questionText = trim(strip_tags($rawQuestion));

                                if ($questionText === '') {
                                    preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawQuestion, $imageMatch);
                                    $questionText = $imageMatch[1] ?? trim($rawQuestion);
                                }

                                $questionText = preg_replace('/\s+/', ' ', $questionText ?? '');
                            @endphp
                            <button type="button"
                                class="btn btn-link p-0 text-start text-body js-exam-assignment-open"
                                data-question-id="{{ $question->id }}"
                                title="View this question and manage its exam assignments">
                                {{ \Illuminate\Support\Str::limit($questionText ?: 'No question text', 80) }}
                            </button>
                        </td>
                        <td>
                            <button type="button"
                                class="btn btn-sm {{ $question->exams_count ? 'el-btn-primary' : 'btn-outline-secondary' }} js-exam-assignment-open js-exam-usage-badge"
                                data-question-id="{{ $question->id }}"
                                data-exam-count="{{ $question->exams_count }}">
                                <i class="ri-file-list-3-line me-1"></i>
                                <span>{{ $question->exams_count ? $question->exams_count.' exam'.($question->exams_count === 1 ? '' : 's') : 'Not used' }}</span>
                            </button>
                        </td>
                        <td>
                            @foreach ($question->groups as $group)
                                {{ $group->group_name }}{{ !$loop->last ? ' | ' : '' }}
                            @endforeach
                        </td>
                        <td>{{ $question->subject?->subject_name ?? 'No Subject' }}</td>
                        <td>{{ $question->topic?->name ?? 'N/A' }}</td>
                        @if($showSubtopicColumn)
                            <td>{{ $question->stopic?->name ?? 'N/A' }}</td>
                        @endif
                        <td>{{ $question->qtype?->question_type ?? 'N/A' }}</td>
                        @if($showTagsColumn)
                            <td>
                                @forelse($question->tags as $tag)
                                    <span class="badge bg-primary-subtle text-primary border me-1 mb-1">{{ $tag->name }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                        @endif
                        @if($showDifficultyColumn)
                            <td>{{ $question->diff?->diff_level ?? 'N/A' }}</td>
                        @endif
                        @if($showLanguageColumn)
                            <td>{{ $question->language?->name ?? 'N/A' }}</td>
                        @endif
                        @if($showMarksColumn)
                            <td>{{ $question->marks ?? '0' }}</td>
                        @endif
                        @if($showNegativeMarksColumn)
                            <td>{{ $question->negative_marks ?? '0' }}</td>
                        @endif
                        @if($showStatusColumn)
                            <td>{{ $question->status === 'Yes' ? 'Active' : 'Inactive' }}</td>
                        @endif
                        @if($showPassageColumn)
                            <td>{{ $question->passage_id ? 'Yes' : 'No' }}</td>
                        @endif
                        @if($canEditQuestion || $canDeleteQuestion)
                            <td>
                                <div class="dropdown">
                                    <button class="btn el-btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        @if($canEditQuestion)
                                            <li><a class="dropdown-item" href="{{ route('questions.edit', $question->id) }}">Edit</a></li>
                                            <li><a class="dropdown-item" href="{{ route('questions_langs.create', $question->id) }}">Add Question Language</a></li>
                                        @endif
                                        @if($canDeleteQuestion)
                                            <li><button class="dropdown-item remove-item-btn" data-bs-toggle="modal" data-bs-target="#deleteRecordModal" data-id="{{ $question->id }}">Remove</button></li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 7 + $temporaryColumnCount + ($canDeleteQuestion ? 1 : 0) + (($canEditQuestion || $canDeleteQuestion) ? 1 : 0) }}" class="text-center">No questions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-3">
        {{ $questions->appends(request()->query())->links('pagination::simple-bootstrap-5') }}
    </div>
</div>
