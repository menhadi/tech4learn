@extends('students.layouts.app')

@section('title', 'Practice Builder')

@section('content')
<style>
    .practice-builder {
        --pb-primary: var(--el-primary, #0f766e);
        --pb-secondary: var(--el-secondary, #f59e0b);
        --pb-soft: var(--el-primary-soft, #e6f4f1);
        --pb-border: var(--el-border, #cfe1df);
        --pb-text: var(--el-text, #0f172a);
        color: var(--pb-text);
        font-size: 13px;
    }
    .pb-hero,
    .pb-panel,
    .pb-recent {
        background: #fff;
        border: 1px solid var(--pb-border);
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
    }
    .pb-hero {
        padding: 16px 18px;
        display: grid;
        gap: 6px;
        margin-bottom: 18px;
    }
    .pb-eyebrow {
        color: var(--pb-primary);
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
        font-size: 11px;
    }
    .pb-hero h1 {
        margin: 0;
        font-size: clamp(20px, 1.9vw, 24px);
        font-weight: 800;
    }
    .pb-hero p {
        max-width: 780px;
        margin: 0;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }
    .pb-panel {
        padding: 18px;
        margin-bottom: 18px;
    }
    .pb-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }
    .pb-field label {
        display: block;
        font-weight: 800;
        margin-bottom: 7px;
        font-size: 13px;
    }
    .pb-field select,
    .pb-field input {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--pb-border);
        border-radius: 6px;
        padding: 0 12px;
        color: var(--pb-text);
        background: #fff;
        font-size: 13px;
    }
    .pb-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 18px;
        flex-wrap: wrap;
    }
    .pb-btn {
        border: 0;
        border-radius: 6px;
        min-height: 44px;
        padding: 0 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 800;
        text-decoration: none;
        transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease;
    }
    .pb-btn:hover {
        transform: translateY(-1px);
        text-decoration: none;
    }
    .pb-btn-primary {
        background: var(--pb-primary);
        color: #fff;
        box-shadow: 0 12px 22px color-mix(in srgb, var(--pb-primary) 24%, transparent);
    }
    .pb-btn-primary:hover,
    .pb-btn-primary:focus {
        background: color-mix(in srgb, var(--pb-primary) 86%, #000);
        color: #fff;
    }
    .pb-btn-secondary {
        background: var(--pb-secondary);
        color: #111827;
    }
    .pb-recent,
    .pb-suggestions {
        overflow: hidden;
    }
    .pb-recent-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--pb-border);
        background: var(--pb-soft);
    }
    .pb-recent-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
    }
    .pb-suggestions {
        background: #fff;
        border: 1px solid var(--pb-border);
        border-radius: 8px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
        margin-bottom: 18px;
    }
    .pb-suggestions-header {
        padding: 16px 20px;
        background: var(--pb-soft);
        border-bottom: 1px solid var(--pb-border);
    }
    .pb-suggestions-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
    }
    .pb-suggestion-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        padding: 18px 20px;
    }
    .pb-suggestion {
        border: 1px solid var(--pb-border);
        border-radius: 8px;
        padding: 14px;
        background: #fff;
    }
    .pb-suggestion strong {
        display: block;
        margin-bottom: 6px;
        color: var(--pb-primary);
        font-size: 14px;
    }
    .pb-suggestion p {
        margin: 0;
        color: #64748b;
        line-height: 1.45;
    }
    .pb-table {
        width: 100%;
        border-collapse: collapse;
    }
    .pb-table th,
    .pb-table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--pb-border);
        vertical-align: middle;
    }
    .pb-table th {
        background: var(--pb-soft);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 13px;
        color: var(--pb-text);
    }
    .pb-empty {
        padding: 24px 18px;
        color: #64748b;
        text-align: center;
    }
    .pb-score {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        background: var(--pb-soft);
        color: var(--pb-primary);
        font-weight: 800;
        padding: 6px 10px;
    }
    .pb-muted {
        color: #64748b;
    }
    .pb-table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .pb-btn-small {
        min-height: 36px;
        padding: 0 12px;
        font-size: 12px;
    }
    .pb-alert {
        border-radius: 6px;
        padding: 12px 14px;
        margin-bottom: 16px;
        font-weight: 700;
    }
    .pb-alert-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .pb-alert-success {
        background: var(--pb-soft);
        color: var(--pb-primary);
        border: 1px solid var(--pb-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }
    @media (max-width: 991px) {
        .pb-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .pb-hero,
        .pb-panel {
            padding: 18px;
        }
        .pb-grid {
            grid-template-columns: 1fr;
        }
        .pb-suggestion-grid {
            grid-template-columns: 1fr;
            padding: 14px;
        }
        .pb-actions,
        .pb-btn {
            width: 100%;
        }
        .pb-table,
        .pb-table tbody,
        .pb-table tr,
        .pb-table td {
            display: block;
            width: 100%;
        }
        .pb-table thead {
            display: none;
        }
        .pb-table td {
            padding: 12px 18px;
        }
        .pb-table td::before {
            content: attr(data-label);
            display: block;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .pb-table-actions {
            width: 100%;
        }
    }
</style>

<div class="practice-builder">
    <div class="pb-hero">
        <div class="pb-eyebrow">{{ __('ui.personal_practice') }}</div>
        <h1>{{ __('ui.build_practice_test') }}</h1>
        <p>{{ __('ui.practice_builder_copy') }}</p>
    </div>

    @if(session('error'))
        <div class="pb-alert pb-alert-error">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="pb-alert pb-alert-success">
            <span>{{ session('success') }}</span>
            @if(session('created_practice_exam_id'))
                <a class="pb-btn pb-btn-primary pb-btn-small" href="{{ route('student.instructions', ['id' => session('created_practice_exam_id')]) }}">
                    Start Test
                </a>
            @endif
        </div>
    @endif

    <form class="pb-panel" method="POST" action="{{ route('student.practice-builder.store') }}" id="practiceBuilderForm">
        @csrf
        <div class="pb-grid">
            <div class="pb-field">
                <label for="group_id">{{ __('ui.group') }}</label>
                <select name="group_id" id="group_id" required>
                    <option value="">{{ __('ui.select_group') }}</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>{{ $group->group_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="category_level_1">{{ __('ui.category') }} <span class="text-muted">(optional)</span></label>
                <select name="category_level_1" id="category_level_1">
                    <option value="">{{ __('ui.all_categories') }}</option>
                    @foreach($parentCategories as $category)
                        <option value="{{ $category->id }}"
                            data-group-ids="{{ $category->groups->pluck('id')->implode(',') }}"
                            @selected(old('category_level_1') == $category->id)>{{ $category->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field" data-subcategory-ui>
                <label for="category_level_2">{{ __('ui.subcategory') }} <span class="text-muted">(optional)</span></label>
                <select name="category_level_2" id="category_level_2">
                    <option value="">{{ __('ui.all_subcategories') }}</option>
                    @foreach($parentCategories as $category)
                        @foreach($category->children as $subcategory)
                            <option value="{{ $subcategory->id }}" data-parent-id="{{ $category->id }}"
                                @selected(old('category_level_2') == $subcategory->id)>{{ $subcategory->title }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="subject_id">{{ __('messages.my_exams_modal_subject') }}</label>
                <select name="subject_id" id="subject_id">
                    <option value="">{{ __('ui.all_subjects') }}</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}"
                            data-group-ids="{{ $subject->groups->pluck('id')->implode(',') }}"
                            @selected(old('subject_id') == $subject->id)>
                            {{ $subject->subject_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="topic_id">{{ __('ui.topic') }}</label>
                <select name="topic_id" id="topic_id">
                    <option value="">{{ __('ui.all_topics') }}</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->id }}" data-subject-id="{{ $topic->subject_id }}" @selected(old('topic_id') == $topic->id)>{{ $topic->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="stopic_id">{{ __('ui.sub_topic') }}</label>
                <select name="stopic_id" id="stopic_id">
                    <option value="">{{ __('ui.all_sub_topics') }}</option>
                    @foreach($subtopics as $subtopic)
                        <option value="{{ $subtopic->id }}" data-subject-id="{{ $subtopic->subject_id }}" data-topic-id="{{ $subtopic->topic_id }}" @selected(old('stopic_id') == $subtopic->id)>{{ $subtopic->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="qtype_id">{{ __('ui.question_type') }}</label>
                <select name="qtype_id" id="qtype_id">
                    <option value="">{{ __('ui.all_types') }}</option>
                    @foreach($questionTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('qtype_id') == $type->id)>{{ $type->question_type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="diff_id">{{ __('ui.difficulty') }}</label>
                <select name="diff_id" id="diff_id">
                    <option value="">{{ __('ui.all_difficulty') }}</option>
                    @foreach($difficulties as $difficulty)
                        <option value="{{ $difficulty->id }}" @selected(old('diff_id') == $difficulty->id)>{{ $difficulty->diff_level }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="question_count">{{ __('ui.questions') }}</label>
                <select name="question_count" id="question_count" required>
                    @foreach([5, 10, 20, 25, 50, 100] as $count)
                        <option value="{{ $count }}" @selected((int) old('question_count', 20) === $count)>{{ $count }} Questions</option>
                    @endforeach
                </select>
            </div>

            <div class="pb-field">
                <label for="duration">{{ __('ui.duration') }}</label>
                <select name="duration" id="duration" required>
                    @foreach([15, 30, 45, 60, 90, 120, 180] as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('duration', 30) === $minutes)>{{ $minutes }} minutes</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="pb-actions">
            <button type="submit" class="pb-btn pb-btn-primary">
                <i class="mdi mdi-play-circle-outline"></i>
                Create Practice Test
            </button>
        </div>
    </form>

    <div class="pb-suggestions">
        <div class="pb-suggestions-header">
            <h2>{{ __('ui.practice_suggestions') }}</h2>
        </div>
        <div class="pb-suggestion-grid">
            @foreach($practiceSuggestions as $suggestion)
                <div class="pb-suggestion">
                    <strong>{{ $suggestion['title'] }}</strong>
                    <p>{{ $suggestion['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="pb-recent">
        <div class="pb-recent-header">
            <h2>{{ __('ui.recent_practice_tests') }}</h2>
        </div>

        @if($recentPracticeExams->isEmpty())
            <div class="pb-empty">{{ __('ui.no_personal_tests') }}</div>
        @else
            <table class="pb-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.practice_test') }}</th>
                        <th>{{ __('ui.questions') }}</th>
                        <th>{{ __('ui.duration') }}</th>
                        <th>{{ __('messages.my_exams_result_button') }}</th>
                        <th>{{ __('ui.created') }}</th>
                        <th>{{ __('messages.dash_col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentPracticeExams as $exam)
                        @php
                            $summary = $practiceResultSummary->get($exam->id);
                            $latestResult = $summary['latest'] ?? null;
                            $bestPercent = $summary['best_percent'] ?? null;
                            $attempts = $summary['attempts'] ?? 0;
                        @endphp
                        <tr>
                            <td data-label="{{ __('ui.practice_test') }}">{{ $exam->name }}</td>
                            <td data-label="{{ __('ui.questions') }}">{{ $exam->questions_count }}</td>
                            <td data-label="{{ __('ui.duration') }}">{{ $exam->duration }} mins</td>
                            <td data-label="{{ __('messages.my_exams_result_button') }}">
                                @if($latestResult)
                                    <span class="pb-score">{{ number_format((float) $bestPercent, 2) }}%</span>
                                    <div class="pb-muted">{{ $attempts }} {{ \Illuminate\Support\Str::plural('attempt', $attempts) }}</div>
                                @else
                                    <span class="pb-muted">{{ __('ui.not_attempted') }}</span>
                                @endif
                            </td>
                            <td data-label="{{ __('ui.created') }}">{{ optional($exam->created_at)->format('d M Y') }}</td>
                            <td data-label="{{ __('messages.dash_col_action') }}">
                                <div class="pb-table-actions">
                                    <a class="pb-btn pb-btn-secondary pb-btn-small" href="{{ route('student.instructions', ['id' => $exam->id]) }}">
                                        Start
                                    </a>
                                    @if($latestResult)
                                        <a class="pb-btn pb-btn-primary pb-btn-small" href="{{ route('student.results.view', $latestResult->id) }}">
                                            {{ __('messages.my_exams_result_button') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const group = document.getElementById('group_id');
    const category = document.getElementById('category_level_1');
    const subcategory = document.getElementById('category_level_2');
    const subject = document.getElementById('subject_id');
    const topic = document.getElementById('topic_id');
    const subtopic = document.getElementById('stopic_id');

    function setOptionVisibility(select, callback) {
        Array.from(select.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            option.hidden = !callback(option);
        });
        if (select.selectedOptions[0] && select.selectedOptions[0].hidden) {
            select.value = '';
        }
    }

    function filterHierarchy() {
        const groupId = group.value;
        setOptionVisibility(category, option => {
            const groupIds = (option.dataset.groupIds || '').split(',').filter(Boolean);
            return !groupId || groupIds.length === 0 || groupIds.includes(groupId);
        });
        filterSubcategories();
        filterSubjects();
    }

    function filterSubcategories() {
        const categoryId = category.value;
        setOptionVisibility(subcategory, option => Boolean(categoryId) && option.dataset.parentId === categoryId);
    }

    function filterSubjects() {
        const groupId = group.value;
        setOptionVisibility(subject, option => !groupId || option.dataset.groupIds.split(',').includes(groupId));
        filterTopics();
    }

    function filterTopics() {
        const subjectId = subject.value;
        setOptionVisibility(topic, option => !subjectId || option.dataset.subjectId === subjectId);
        filterSubtopics();
    }

    function filterSubtopics() {
        const subjectId = subject.value;
        const topicId = topic.value;
        setOptionVisibility(subtopic, option => {
            const subjectMatch = !subjectId || option.dataset.subjectId === subjectId;
            const topicMatch = !topicId || option.dataset.topicId === topicId;
            return subjectMatch && topicMatch;
        });
    }

    group.addEventListener('change', filterHierarchy);
    category.addEventListener('change', filterSubcategories);
    subject.addEventListener('change', filterTopics);
    topic.addEventListener('change', filterSubtopics);
    filterHierarchy();
});
</script>
@endsection
