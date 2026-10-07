@extends('layouts.master')
@section('rich-editor', true)

@section('title', isset($exam) ? 'Edit Exam' : 'Add Exam')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($exam) ? 'Edit Exam' : 'Add Exam')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="card-title mb-0">{{ isset($exam) ? 'Edit Exam' : 'Add Exam' }}</h4>
                @isset($exam)
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary" href="{{ route('exams.paper.preview', $exam) }}" target="_blank"><i class="ri-file-paper-2-line me-1"></i>View full paper</a>
                        <a class="btn btn-primary" href="{{ route('exams.paper.edit', $exam) }}"><i class="ri-edit-box-line me-1"></i>Edit full paper</a>
                    </div>
                @endisset
            </div>
            <div class="card-body">
                <form action="{{ isset($exam) ? route('exams.update', $exam->id) : route('exams.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @if(isset($exam))
                    @method('PUT')
                    @endif
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ old('name', isset($exam) ? $exam->name : '') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label for="display_order" class="form-label">Display Order <span class="text-muted">(optional)</span></label>
                            <input type="number" class="form-control" id="display_order" name="display_order" min="0"
                                value="{{ old('display_order', $exam->display_order ?? '') }}" placeholder="Fallback">
                        </div>
                        <div class="col-md-3">
                            <label for="passing_percentage" class="form-label">Passing Percentage <span class="text-muted">(optional)</span></label>
                            <input type="number" class="form-control" id="passing_percentage" name="passing_percentage"
                                value="{{ old('passing_percentage', isset($exam) && (int) $exam->passing_percentage > 0 ? $exam->passing_percentage : '') }}"
                                min="0" max="100">
                        </div>
                    </div>
                    <div class="card border mb-4">
                        <div class="card-header">
                            <h5 class="mb-1">Package display classification</h5>
                            <p class="text-muted mb-0">This controls where the exam appears inside every package. It does not create another package.</p>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-3">
                                    <label for="test_type" class="form-label">Test Type</label>
                                    <select id="test_type" name="test_type" class="form-select" required>
                                        @foreach($testTypeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('test_type', $exam->test_type ?? \App\Models\Exam::TEST_TYPE_OTHER) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3" id="testSubjectField">
                                    <label for="test_subject_id" class="form-label">Subject</label>
                                    <select id="test_subject_id" name="test_subject_id" class="form-select">
                                        <option value="">Select subject</option>
                                        @foreach($testSubjects as $subject)
                                            <option value="{{ $subject->id }}" data-group-ids="{{ $subject->groups->pluck('id')->implode(',') }}" @selected((string) old('test_subject_id', $exam->test_subject_id ?? '') === (string) $subject->id)>{{ $subject->subject_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3" id="testTopicField">
                                    <label for="test_topic_id" class="form-label">Topic</label>
                                    <select id="test_topic_id" name="test_topic_id" class="form-select">
                                        <option value="">Select topic</option>
                                        @foreach($testSubjects as $subject)
                                            @foreach($subject->topics as $topic)
                                                <option value="{{ $topic->id }}" data-subject-id="{{ $subject->id }}" data-group-id="{{ $topic->group_id }}" @selected((string) old('test_topic_id', $exam->test_topic_id ?? '') === (string) $topic->id)>{{ $topic->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3" id="testSubtopicField">
                                    <label for="test_stopic_id" class="form-label">Subtopic</label>
                                    <select id="test_stopic_id" name="test_stopic_id" class="form-select">
                                        <option value="">Select subtopic</option>
                                        @foreach($testSubjects as $subject)
                                            @foreach($subject->topics as $topic)
                                                @foreach($topic->stopics as $stopic)
                                                    <option value="{{ $stopic->id }}" data-subject-id="{{ $subject->id }}" data-topic-id="{{ $topic->id }}" data-group-id="{{ $stopic->group_id }}" @selected((string) old('test_stopic_id', $exam->test_stopic_id ?? '') === (string) $stopic->id)>{{ $stopic->name }}</option>
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-text mt-2" id="testClassificationHelp"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <x-textarea-editor id="instruction" name="instruction" label="Instruction"
                            placeholder="Enter instructions here"
                            :value="old('instruction', isset($exam) ? $exam->instruction : '')" />
                    </div>
                    <div class="mb-3">
                        <x-textarea-editor id="syllabus" name="syllabus" label="Syllabus"
                            placeholder="Enter syllabus here"
                            :value="old('syllabus', isset($exam) ? $exam->syllabus : '')" />
                    </div>
                    @php
                        $englishLanguage = $languages->first(fn ($language) => (strtolower((string) $language->code) === 'en' || strtolower((string) $language->name) === 'english')) ?? $languages->first();
                        $selectedLanguageIds = collect(old('language_ids', isset($exam) ? $exam->languages->pluck('id')->all() : [$englishLanguage?->id]))
                            ->filter()->map(fn ($id) => (int) $id)->all();
                    @endphp
                    <div class="mb-3">
                        <label class="form-label">Student languages</label>
                        @if($englishLanguage)
                            <input type="hidden" name="language_ids[]" value="{{ $englishLanguage->id }}">
                        @endif
                        <div id="language_ids">
                            @foreach($languages as $language)
                                @php $isEnglish = (int) $language->id === (int) $englishLanguage?->id; @endphp
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input student-language-checkbox"
                                        id="student-language-{{ $language->id }}" value="{{ $language->id }}"
                                        @if(!$isEnglish) name="language_ids[]" @endif
                                        @checked($isEnglish || in_array((int) $language->id, $selectedLanguageIds, true))
                                        @disabled($isEnglish)>
                                    <label class="form-check-label" for="student-language-{{ $language->id }}">{{ $language->name }}{{ $isEnglish ? ' (default)' : '' }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">English is always available. Students see this selector only when another language is enabled; missing translations are generated and saved in batches of five when selected.</div>
                    </div>
                    @if(isset($exam))
                        @php $preparableLanguages = $exam->languages->reject(fn ($language) => app(App\Services\ExamLanguageService::class)->isEnglish($language)); @endphp
                        @if($preparableLanguages->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label d-block">Prepare missing translations</label>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($preparableLanguages as $preparableLanguage)
                                        <button type="button" class="btn btn-outline-primary prepare-exam-language"
                                            data-language-name="{{ $preparableLanguage->name }}"
                                            data-endpoint="{{ route('exams.languages.prepare', ['exam' => $exam->id, 'language' => $preparableLanguage->id]) }}">
                                            Prepare {{ $preparableLanguage->name }}
                                        </button>
                                    @endforeach
                                </div>
                                <div id="prepareLanguageStatus" class="form-text" aria-live="polite">Only missing or incomplete translations are generated; existing translations are preserved.</div>
                            </div>
                        @endif
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="duration" class="form-label">Duration (Min)</label>
                            <input type="number" class="form-control" id="duration" name="duration"
                                value="{{ old('duration', isset($exam) ? $exam->duration : '') }}" required>
                            <small class="form-text text-muted">0 for unlimited duration</small>
                        </div>
                        <div class="col-md-6">
                            <label for="attempt_count" class="form-label">Attempt Count</label>
                            <input type="number" class="form-control" id="attempt_count" name="attempt_count"
                                    value="{{ old('attempt_count', isset($exam) ? (int) $exam->attempt_count : 0) }}" min="0" step="1" required>
                            <small class="form-text text-muted">Maximum attempts allowed per student. Use 0 for unlimited attempts.</small>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                                value="{{ old('start_date', isset($exam) ? $exam->start_date->format('Y-m-d\TH:i') : '') }}"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                value="{{ old('end_date', isset($exam) ? $exam->end_date->format('Y-m-d\TH:i') : '') }}"
                                required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="browser_tolerance" class="form-label">Browser Tolerance</label>
                            <div>
                                <input type="radio" id="browser_tolerance_yes" name="browser_tolerance" value="1" {{
                                    old('browser_tolerance', isset($exam) ? $exam->browser_tolerance : '1') == '1' ?
                                'checked' : '' }}>
                                <label for="browser_tolerance_yes">Yes</label>
                                <input type="radio" id="browser_tolerance_no" name="browser_tolerance" value="0" {{
                                    old('browser_tolerance', isset($exam) ? $exam->browser_tolerance : '1') == '0' ?
                                'checked' : '' }} style="margin-left: 20px;">
                                <label for="browser_tolerance_no">No</label>
                            </div>
                        </div>
                        <div class="col-md-6" id="tolerance_count_div" style="display: none;">
                            <label for="tolerance_count" class="form-label">Tolerance Count</label>
                            <input type="number" class="form-control" id="tolerance_count" name="tolerance_count" min="0"
                                value="{{ old('tolerance_count', isset($exam) ? ($exam->tolerance_count ?? 0) : 0) }}"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label for="random_question" class="form-label">Random Question</label>
                            <div>
                                <input type="radio" id="random_question_yes" name="random_question" value="1" {{
                                    old('random_question', isset($exam) ? $exam->random_question : '1') == '1' ?
                                'checked' : '' }}>
                                <label for="random_question_yes">Yes</label>
                                <input type="radio" id="random_question_no" name="random_question" value="0" {{
                                    old('random_question', isset($exam) ? $exam->random_question : '1') == '0' ?
                                'checked' : '' }} style="margin-left: 20px;">
                                <label for="random_question_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="result_after_finish" class="form-label">Result After Finish</label>
                            <div>
                                <input type="radio" id="result_after_finish_yes" name="result_after_finish" value="1" {{
                                    old('result_after_finish', isset($exam) ? $exam->result_after_finish : '1') == '1' ?
                                'checked' : '' }}>
                                <label for="result_after_finish_yes">Yes</label>
                                <input type="radio" id="result_after_finish_no" name="result_after_finish" value="0" {{
                                    old('result_after_finish', isset($exam) ? $exam->result_after_finish : '1') == '0' ?
                                'checked' : '' }} style="margin-left: 20px;">
                                <label for="result_after_finish_no">No</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="option_shuffle" class="form-label">Option Shuffle</label>
                            <div>
                                <input type="radio" id="option_shuffle_yes" name="option_shuffle" value="1" {{
                                    old('option_shuffle', isset($exam) ? $exam->option_shuffle : '1') == '1' ? 'checked'
                                : '' }}>
                                <label for="option_shuffle_yes">Yes</label>
                                <input type="radio" id="option_shuffle_no" name="option_shuffle" value="0" {{
                                    old('option_shuffle', isset($exam) ? $exam->option_shuffle : '1') == '0' ? 'checked'
                                : '' }} style="margin-left: 20px;">
                                <label for="option_shuffle_no">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Allow students to change a saved answer later?</label>
                            <div>
                                <input type="radio" id="allow_answer_change_yes" name="allow_answer_change" value="1"
                                    @checked((string) old('allow_answer_change', isset($exam) ? (int) $exam->allow_answer_change : 1) === '1')>
                                <label for="allow_answer_change_yes">Yes (default)</label>
                                <input type="radio" id="allow_answer_change_no" name="allow_answer_change" value="0"
                                    @checked((string) old('allow_answer_change', isset($exam) ? (int) $exam->allow_answer_change : 1) === '0') style="margin-left: 20px;">
                                <label for="allow_answer_change_no">No, lock after Save &amp; Next</label>
                            </div>
                            <div class="form-text">When disabled, a response is locked only after it is saved and the student moves to another question.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            @php
                                $savedGroupingMode = old('grouping_mode', isset($exam) ? ($exam->grouping_mode ?: ($exam->timer_mode === 'section' ? 'section' : 'subject')) : 'subject');
                                $savedUseGroupTimer = (string) old('use_group_timer', isset($exam) ? ($exam->timer_mode !== 'none' || $exam->is_subject_timer ? '1' : '0') : '0');
                            @endphp
                            <label for="grouping_mode" class="form-label">Display Questions By</label>
                            <select id="grouping_mode" name="grouping_mode" class="form-select">
                                <option value="subject" @selected($savedGroupingMode === 'subject')>Subject</option>
                                <option value="section" @selected($savedGroupingMode === 'section')>Section</option>
                                <option value="none" @selected($savedGroupingMode === 'none')>No grouping</option>
                            </select>
                            <div class="form-text">Subject and Section are independent optional question classifications.</div>
                            <label class="form-label mt-3">Separate timer for each displayed group?</label>
                            <div>
                                <label class="me-3"><input type="radio" name="use_group_timer" value="1" @checked($savedUseGroupTimer === '1')> Yes</label>
                                <label><input type="radio" name="use_group_timer" value="0" @checked($savedUseGroupTimer !== '1')> No</label>
                            </div>
                            <div class="form-text">When No is selected, tabs remain visible but the exam uses one overall timer.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="proctor" class="form-label">Proctor</label>
                            <div>
                                <input type="radio" id="proctor_yes" name="proctor" value="1" {{ old('proctor',
                                    isset($exam) ? $exam->proctor : '1') == '1' ? 'checked' : '' }}>
                                <label for="proctor_yes">Yes</label>
                                <input type="radio" id="proctor_no" name="proctor" value="0" {{ old('proctor',
                                    isset($exam) ? $exam->proctor : '1') == '0' ? 'checked' : '' }} style="margin-left:
                                20px;">
                                <label for="proctor_no">No</label>
                            </div>
                        </div>
                    </div>

                    {{-- ✅ UPDATED ROW: Calculator & Negative Marking --}}
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Allow Calculator</label>
                            <div>
                                <input type="radio" id="calculator_allowed_yes" name="calculator_allowed" value="1" {{
                                    old('calculator_allowed', isset($exam) ? $exam->calculator_allowed : '0') == '1' ? 'checked' : '' }}>
                                <label for="calculator_allowed_yes">Yes</label>
                                
                                <input type="radio" id="calculator_allowed_no" name="calculator_allowed" value="0" {{
                                    old('calculator_allowed', isset($exam) ? $exam->calculator_allowed : '0') == '0' ? 'checked' : '' }} style="margin-left: 20px;">
                                <label for="calculator_allowed_no">No</label>
                            </div>
                        </div>

                        {{-- ✅ NEW FIELD ADDED HERE --}}
                        <div class="col-md-6">
                            <label for="negative_marking" class="form-label">Negative Marking</label>
                            <div>
                                <input type="radio" id="negative_marking_yes" name="negative_marking" value="1" {{
                                    old('negative_marking', isset($exam) ? $exam->negative_marking : '0') == '1' ?
                                'checked' : '' }}>
                                <label for="negative_marking_yes">Yes</label>
                                
                                <input type="radio" id="negative_marking_no" name="negative_marking" value="0" {{
                                    old('negative_marking', isset($exam) ? $exam->negative_marking : '0') == '0' ?
                                'checked' : '' }} style="margin-left: 20px;">
                                <label for="negative_marking_no">No</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="groups" class="form-label">Select Group</label>
                            <select class="form-control select2" id="groups" name="groups[]" multiple>
                                @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ isset($exam) && in_array($group->id, old('groups',
                                    $exam->groups->pluck('id')->toArray())) ? 'selected' : '' }}>
                                    {{ $group->group_name }}
                                </option>
                                @endforeach
                            </select>
                            <div id="derivedGroupHelp" class="form-text">Choose groups directly only for an exam without packages.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="packages" class="form-label">Select Package (Optional)</label>
                            <select class="form-control select2" id="packages" name="packages[]" multiple>
                                @foreach($packages as $package)
                                <option value="{{ $package->id }}" data-group-ids="{{ $package->groups->pluck('id')->implode(',') }}" data-category-id="{{ $package->category_level_1 }}" data-subcategory-id="{{ $package->category_level_2 }}" {{ isset($exam) && in_array($package->id,
                                    old('packages', $exam->packages->pluck('id')->toArray())) ? 'selected' : '' }}>
                                    {{ $package->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        
                        <!-- <div class="col-md-6">
                            <label for="category_level_1" class="form-label">Category Level 1</label>
                            <select id="category_level_1" name="category_level_1" class="form-control">
                                <option value="">Select Category Level 1</option>
                                <option @if(isset($exam)) {{ $exam->category_level_1 == 'previous_year_papers' ? 'selected' : '' }} @endif value="previous_year_papers">Previous Year Papers</option>
                                <option @if(isset($exam)) {{ $exam->category_level_1 == 'mock_tests' ? 'selected' : '' }} @endif value="mock_tests">Mock Tests</option>
                            </select>
                        </div> -->

                        <!-- <div class="col-md-6">
                            <label for="category_level_2" class="form-label">Category Level 2</label>
                            <select id="category_level_2" name="category_level_2" class="form-control">
                                <option value="">Select Category Level 2</option>
                                @if(isset($exam))
                                    @if($exam->category_level_1 == 'previous_year_papers')
                                        <option {{ $exam->category_level_2 == 'year_wise' ? 'selected' : '' }} value="year_wise">Year Wise</option>
                                        <option {{ $exam->category_level_2 == 'subject_wise' ? 'selected' : '' }} value="subject_wise">Subject Wise</option>
                                    @else
                                        <option {{ $exam->category_level_2 == 'full_length_test' ? 'selected' : '' }} value="full_length_test">Full Length Test</option>
                                        <option {{ $exam->category_level_2 == 'subject_wise_test' ? 'selected' : '' }} value="subject_wise_test">Subject Wise Test</option>
                                    @endif;
                                @endif
                            </select>
                        </div> -->

                        <div class="col-md-6 exam-manual-scope">

                            <label for="category_level_1" class="form-label">
                                Category Level 1
                            </label>

                            <select id="category_level_1"
                                name="category_level_1"
                                class="form-control">

                                <option value="">
                                    Select Category Level 1
                                </option>

                                @foreach($parentCategories as $parentCategory)

                                    <option
                                        value="{{ $parentCategory->id }}"

                                        @if(isset($exam))
                                            {{ $exam->category_level_1 == $parentCategory->id ? 'selected' : '' }}
                                        @endif>

                                        {{ $parentCategory->title }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                        <div class="col-md-6 exam-manual-scope">

                            <label for="category_level_2" class="form-label">
                                Category Level 2
                            </label>

                            <select id="category_level_2"
                                name="category_level_2"
                                class="form-control">

                                <option value="">
                                    Select Category Level 2
                                </option>

                                @foreach($childCategories as $childCategory)

                                    <option
                                        value="{{ $childCategory->id }}"
                                        data-parent="{{ $childCategory->parent_id }}"

                                        @if(isset($exam))
                                            {{ $exam->category_level_2 == $childCategory->id ? 'selected' : '' }}
                                        @endif>

                                        {{ $childCategory->title }}

                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>

                    <div class="card border mb-4">
                        <div class="card-header"><h5 class="mb-1">Private audit sources</h5><p class="text-muted mb-0">Optional. These PDFs/URLs are never public and are used only to verify questions, options, images and answers.</p></div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach(['questions' => 'Question paper', 'answers' => 'Answer / solution', 'combined' => 'Combined questions + answers'] as $role => $label)
                                    @php
                                        $field = $role === 'questions' ? 'question' : ($role === 'answers' ? 'answer' : 'combined');
                                        $currentSource = isset($exam)
                                            ? $exam->qualitySources->where('role', $role)->sortByDesc('id')->first()
                                            : null;
                                        $savedUrl = $currentSource?->source_url;
                                    @endphp
                                    <div class="col-lg-4">
                                        <label class="form-label">{{ $label }} PDF</label>
                                        <input type="file" class="form-control" accept="application/pdf,.pdf" name="source_{{ $field }}_pdf">
                                        @if($currentSource)
                                            <div class="d-flex align-items-center gap-2 mt-2 small">
                                                <i class="ri-file-pdf-2-line text-danger"></i>
                                                <span class="text-truncate" title="{{ $currentSource->label }}">{{ $currentSource->label ?: 'Saved source PDF' }}</span>
                                                <a href="{{ route('exams.sources.download', [$exam, $currentSource]) }}" target="_blank" rel="noopener" class="ms-auto text-nowrap">
                                                    <i class="ri-external-link-line"></i> Open
                                                </a>
                                            </div>
                                        @endif
                                        <label class="form-label mt-2">{{ $label }} URL</label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" name="source_{{ $field }}_url"
                                                value="{{ old('source_'.$field.'_url', $savedUrl) }}" placeholder="https://...">
                                            @if($savedUrl)
                                                <a class="btn btn-outline-secondary" href="{{ $savedUrl }}" target="_blank" rel="noopener" title="Open original URL in a new tab"><i class="ri-external-link-line"></i></a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if(isset($exam) && $exam->qualitySources->isNotEmpty())
                                <div class="mt-3"><div class="fw-semibold mb-2">Attached sources</div>
                                    @foreach($exam->qualitySources as $source)
                                        <label class="d-flex align-items-center gap-2 border rounded p-2 mb-2">
                                            <input type="checkbox" class="form-check-input mt-0" name="remove_source_ids[]" value="{{ $source->id }}">
                                            <span class="badge bg-light text-dark">{{ ucfirst($source->role) }}</span>
                                            <span class="text-truncate">{{ $source->label ?: $source->source_url }}</span>
                                            <a href="{{ route('exams.sources.download', [$exam, $source]) }}" class="small ms-auto" target="_blank" rel="noopener" onclick="event.stopPropagation();"><i class="ri-external-link-line"></i> Open</a>
                                            <span class="text-danger small">Remove on save</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                      @include('partials.seo-fields', ['seoModel' => $exam ?? null])

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('exams.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success ms-2">{{ isset($exam) ? 'Update' : 'Submit'
                            }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const grouping = document.getElementById('grouping_mode');
    const timerYes = document.querySelector('input[name="use_group_timer"][value="1"]');
    const timerNo = document.querySelector('input[name="use_group_timer"][value="0"]');
    function syncGroupingTimerControls() {
        const disabled = grouping?.value === 'none';
        if (timerYes) timerYes.disabled = disabled;
        if (disabled && timerNo) timerNo.checked = true;
    }
    grouping?.addEventListener('change', syncGroupingTimerControls);
    syncGroupingTimerControls();
    const groupSelect = document.getElementById('groups');
    const testType = document.getElementById('test_type');
    const testSubject = document.getElementById('test_subject_id');
    const testTopic = document.getElementById('test_topic_id');
    const testSubtopic = document.getElementById('test_stopic_id');
    const subjectField = document.getElementById('testSubjectField');
    const topicField = document.getElementById('testTopicField');
    const subtopicField = document.getElementById('testSubtopicField');
    const classificationHelp = document.getElementById('testClassificationHelp');
    const subjectOptions = testSubject ? Array.from(testSubject.options) : [];
    const topicOptions = testTopic ? Array.from(testTopic.options) : [];
    const subtopicOptions = testSubtopic ? Array.from(testSubtopic.options) : [];

    function selectedGroupIds() {
        return groupSelect ? Array.from(groupSelect.selectedOptions).map(option => option.value) : [];
    }

    function syncSubjectOptions() {
        const selectedGroups = selectedGroupIds();
        subjectOptions.forEach(function (option) {
            if (!option.value) return;
            const available = (option.dataset.groupIds || '').split(',').filter(Boolean);
            option.hidden = selectedGroups.length > 0 && !selectedGroups.every(id => available.includes(id));
            option.disabled = option.hidden;
        });
        const selected = testSubject?.options[testSubject.selectedIndex];
        if (selected?.hidden) testSubject.value = '';
        syncTopicOptions();
    }
    function syncTopicOptions() {
        if (!testTopic) return;
        const subjectId = testSubject?.value || '';
        const primaryGroupId = selectedGroupIds()[0] || '';
        topicOptions.forEach(function (option) {
            if (!option.value) return;
            option.hidden = option.dataset.subjectId !== subjectId || (!!primaryGroupId && option.dataset.groupId !== primaryGroupId);
            option.disabled = option.hidden;
        });
        const selected = testTopic.options[testTopic.selectedIndex];
        if (selected && selected.hidden) testTopic.value = '';
        syncSubtopicOptions();
    }

    function syncSubtopicOptions() {
        if (!testSubtopic) return;
        const subjectId = testSubject?.value || '';
        const topicId = testTopic?.value || '';
        subtopicOptions.forEach(function (option) {
            if (!option.value) return;
            const primaryGroupId = selectedGroupIds()[0] || '';
            option.hidden = option.dataset.subjectId !== subjectId || option.dataset.topicId !== topicId || (!!primaryGroupId && option.dataset.groupId !== primaryGroupId);
            option.disabled = option.hidden;
        });
        const selected = testSubtopic.options[testSubtopic.selectedIndex];
        if (selected && selected.hidden) testSubtopic.value = '';
    }

    function syncTestClassification() {
        const type = testType?.value || 'other';
        const needsSubject = type === 'subject_test' || type === 'topic_test' || type === 'subtopic_test';
        const needsTopic = type === 'topic_test' || type === 'subtopic_test';
        const needsSubtopic = type === 'subtopic_test';
        subjectField?.classList.toggle('d-none', !needsSubject);
        topicField?.classList.toggle('d-none', !needsTopic);
        subtopicField?.classList.toggle('d-none', !needsSubtopic);
        if (testSubject) testSubject.required = needsSubject;
        if (testTopic) testTopic.required = needsTopic;
        if (testSubtopic) testSubtopic.required = needsSubtopic;
        if (!needsSubject && testSubject) testSubject.value = '';
        if (!needsTopic && testTopic) testTopic.value = '';
        if (!needsSubtopic && testSubtopic) testSubtopic.value = '';
        syncTopicOptions();
        syncSubtopicOptions();
        if (classificationHelp) {
            classificationHelp.textContent = needsSubtopic
                ? 'Shown under Subtopic Tests, then the selected subject, topic, and subtopic.'
                : (needsTopic
                    ? 'Shown under Topic Tests, then the selected subject and topic.'
                    : (needsSubject ? 'Shown under Subject Tests and the selected subject.' : 'Shown under the selected test-type section.'));
        }
    }

    groupSelect?.addEventListener('change', syncSubjectOptions);
    testType?.addEventListener('change', syncTestClassification);
    testSubject?.addEventListener('change', syncTopicOptions);
    testTopic?.addEventListener('change', syncSubtopicOptions);
    syncSubjectOptions();
    syncTestClassification();
});
</script>
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>

    $(function () {

        // -----------------------------
        // Level 2 options configuration
        // -----------------------------
        const level2Options = {
            previous_year_papers: [
                { value: 'year_wise', text: 'Year Wise' },
                { value: 'subject_wise', text: 'Subject Wise' }
            ],
            mock_tests: [
                { value: 'full_length_test', text: 'Full Length Test' },
                { value: 'subject_wise_test', text: 'Subject Wise Test' }
            ]
        };

        // -----------------------------
        // Helper: Render options
        // -----------------------------
        const renderOptions = ($select, items, placeholder, valueKey = 'id', textKey = 'name') => {
            let html = `<option value="">${placeholder}</option>`;

            items.forEach(item => {
                html += `<option value="${item[valueKey]}">${item[textKey]}</option>`;
            });

            $select.html(html);
        };

        // -----------------------------
        // Helper: Toggle Exam/Subject UI
        // -----------------------------
        const toggleLevel3Containers = (category_level_2) => {
            const isExamType = ['year_wise', 'full_length_test'].includes(category_level_2);

            $('#parentExamList').toggleClass('d-none', !isExamType);
            $('#parentSubjectList').toggleClass('d-none', isExamType);

            return isExamType;
        };

        // -----------------------------
        // Fetch Level 3 data
        // -----------------------------
        const fetchCategoryLevel3 = () => {
            const category_level_2 = $('#category_level_2').val();
            const group_ids = $('#group_ids-field').val() || [];

            if (!category_level_2) {
                $('#exam_id').html('<option value="">Select Exam</option>');
                $('#subject_id').html('<option value="">Select Subject</option>');
                return;
            }

            const isExamType = toggleLevel3Containers(category_level_2);

            $.ajax({
                url: '{{ route("packages.categoryLevel3") }}',
                type: 'GET',
                dataType: 'json',
                data: {
                    category_level_2,
                    group_ids
                },
                success: function (res) {
                    if (!res.data) return;

                    if (isExamType) {
                        renderOptions(
                            $('#exam_id'),
                            res.data,
                            'Select Exam',
                            'id',
                            'name'
                        );
                    } else {
                        renderOptions(
                            $('#subject_id'),
                            res.data,
                            'Select Subject',
                            'id',
                            'subject_name'
                        );
                    }
                },
                error: function () {
                    alert('Unable to fetch data.');
                }
            });
        };

        // -----------------------------
        // Category Level 1 change
        // -----------------------------
        // $('#category_level_1').on('change', function () {
        //     const category_level_1 = $(this).val();
        //     const items = level2Options[category_level_1] || [];

        //     renderOptions(
        //         $('#category_level_2'),
        //         items,
        //         'Select Category Level 2',
        //         'value',
        //         'text'
        //     );

        //     // Reset Level 3 selects
        //     $('#exam_id').html('<option value="">Select Exam</option>');
        //     $('#subject_id').html('<option value="">Select Subject</option>');

        //     $('#parentExamList, #parentSubjectList').addClass('d-none');
        // });

        // -----------------------------
        // Trigger fetch when Level 2 or Group changes
        // -----------------------------
        // $('#category_level_2, #group_ids-field').on('change', fetchCategoryLevel3);
        $('#group_ids-field').on('change', fetchCategoryLevel3);

        @if(isset($exam))
            toggleLevel3Containers($('#category_level_2').val());
        @endif;

    });

    document.addEventListener('DOMContentLoaded', function() {
        const browserToleranceYes = document.getElementById('browser_tolerance_yes');
        const browserToleranceNo = document.getElementById('browser_tolerance_no');
        const toleranceCountDiv = document.getElementById('tolerance_count_div');

        function toggleToleranceCount(show) {
            if (show) {
                toleranceCountDiv.style.display = 'block';
                document.getElementById('tolerance_count').required = true;
            } else {
                toleranceCountDiv.style.display = 'none';
                document.getElementById('tolerance_count').required = false;
            }
        }

        // Initial check
        if (browserToleranceYes.checked) {
            toggleToleranceCount(true);
        } else {
            toggleToleranceCount(false);
        }

        // Event listeners for radio buttons
        browserToleranceYes.addEventListener('change', function() {
            toggleToleranceCount(true);
        });

        browserToleranceNo.addEventListener('change', function() {
            toggleToleranceCount(false);
        });

        if ($.fn.select2) $('.select2').select2({
            placeholder: "Select an option",
            allowClear: true,
            closeOnSelect: false
        });

        @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '{{ session('success') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session('error') }}',
            timer: 3000,
            showConfirmButton: false
        });
        @endif

        @if($errors->any())
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: '{{ implode(", ", $errors->all()) }}',
            timer: 5000,
            showConfirmButton: true
        });
        @endif


    });

    document.addEventListener('DOMContentLoaded', function () {
        const prepareStatus = document.getElementById('prepareLanguageStatus');
        document.querySelectorAll('.prepare-exam-language').forEach(button => {
            button.addEventListener('click', async function () {
                button.disabled = true;
                const original = button.textContent;
                try {
                    while (true) {
                        button.textContent = 'Preparing…';
                        const response = await fetch(button.dataset.endpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @json(csrf_token())
                            }
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) throw new Error(payload.message || 'Translation preparation failed.');
                        if (prepareStatus) prepareStatus.textContent = `${button.dataset.languageName}: ${payload.translated || 0} of ${payload.total || 0} questions prepared.`;
                        if (payload.status === 'ready' || ((payload.remaining || 0) === 0 && payload.exam_content_ready)) {
                            button.textContent = 'Prepared ' + button.dataset.languageName;
                            break;
                        }
                        if (payload.status === 'processing') await new Promise(resolve => setTimeout(resolve, 1000));
                    }
                } catch (error) {
                    button.disabled = false;
                    button.textContent = original;
                    if (prepareStatus) prepareStatus.textContent = error.message;
                }
            });
        });

        const level1 = document.getElementById('category_level_1');

        const level2 = document.getElementById('category_level_2');

        const allOptions = Array.from(level2.options);
        const packageSelect = document.getElementById('packages');
        const groupSelectForPackages = document.getElementById('groups');
        const manualScopeFields = document.querySelectorAll('.exam-manual-scope');
        const derivedGroupHelp = document.getElementById('derivedGroupHelp');

        function syncPackageScope() {
            const selectedPackages = Array.from(packageSelect?.selectedOptions || []);
            const packaged = selectedPackages.length > 0;
            const derivedGroupIds = new Set(selectedPackages.flatMap(option => (option.dataset.groupIds || '').split(',').filter(Boolean)));

            if (packaged && groupSelectForPackages) {
                Array.from(groupSelectForPackages.options).forEach(option => {
                    option.selected = derivedGroupIds.has(String(option.value));
                });
            }

            if (groupSelectForPackages) {
                groupSelectForPackages.disabled = packaged;
                if (window.jQuery) window.jQuery(groupSelectForPackages).trigger('change.select2');
            }
            manualScopeFields.forEach(field => {
                field.classList.toggle('d-none', packaged);
                field.querySelectorAll('select').forEach(select => select.disabled = packaged);
            });
            if (derivedGroupHelp) {
                derivedGroupHelp.textContent = packaged
                    ? 'Read-only: combined automatically from all selected packages.'
                    : 'Choose groups directly only for an exam without packages.';
            }
        }

        function filterLevel2() {

            const parentId = level1.value;

            level2.innerHTML = '';

            // Default option
            let defaultOption = document.createElement('option');

            defaultOption.value = '';

            defaultOption.text = 'Select Category Level 2';

            level2.appendChild(defaultOption);

            allOptions.forEach(option => {

                if (!option.value) {
                    return;
                }

                if (option.dataset.parent === parentId) {

                    level2.appendChild(option.cloneNode(true));
                }
            });
        }

        // Initial load
        filterLevel2();

        level1.addEventListener('change', function () {

            level2.value = '';

            filterLevel2();
        });
        packageSelect?.addEventListener('change', syncPackageScope);
        syncPackageScope();
    });
</script>
@endsection
