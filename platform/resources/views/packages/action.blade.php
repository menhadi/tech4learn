@extends('layouts.master')
@section('rich-editor', true)

@section('title', isset($package) ? 'Edit Package' : 'Add Package')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
{{-- Select2 CSS --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .package-feature-box {
        border: 1px solid var(--el-border);
        border-radius: 8px;
        background: var(--el-primary-soft);
    }
</style>
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', isset($package) ? 'Edit Package' : 'Add Package')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ isset($package) ? 'Edit Package' : 'Add Package' }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ isset($package) ? route('packages.update', $package->id) : route('packages.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    @if(isset($package))
                    @method('PUT')
                    @endif

                    <div class="mb-3">
                        <label for="display_order" class="form-label">Package Display Order (optional)</label>
                        <input type="number" min="0" id="display_order" name="display_order" class="form-control"
                            value="{{ old('display_order', $package->display_order ?? '') }}" placeholder="Leave blank to use the normal name order">
                    </div>

                    {{-- 1. Groups Field --}}
                    <div class="mb-3">
                        <label for="group_ids-field" class="form-label">Groups</label>
                        <select id="group_ids-field" name="group_ids[]" class="form-control" multiple required>
                            @foreach($groups as $group)
                            <option value="{{ $group->id }}"
                                @if(old('group_ids'))
                                    {{ in_array($group->id, old('group_ids')) ? 'selected' : '' }}
                                @elseif(isset($package) && $package->groups->contains($group->id))
                                    selected
                                @endif
                            >
                                {{ $group->group_name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label for="category_level_1" class="form-label">Category</label>
                            <select id="category_level_1" name="category_level_1" class="form-control">
                                <option value="">Select Category</option>
                                @foreach($parentCategories as $parentCategory)
                                    <option value="{{ $parentCategory->id }}" data-groups="{{ $parentCategory->groups->pluck('id')->implode(',') }}"
                                        @if(isset($package))
                                            {{ $package->category_level_1 == $parentCategory->id ? 'selected' : '' }}
                                        @endif>
                                        {{ $parentCategory->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" data-subcategory-ui>
                            <label for="category_level_2" class="form-label">Subcategory</label>
                            <select id="category_level_2" name="category_level_2" class="form-control">
                                <option value="">Select Subcategory</option>
                                @foreach($childCategories as $childCategory)
                                    <option value="{{ $childCategory->id }}" data-parent="{{ $childCategory->parent_id }}"
                                        @if(isset($package))
                                            {{ $package->category_level_2 == $childCategory->id ? 'selected' : '' }}
                                        @endif>
                                        {{ $childCategory->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @php
                        $selectedTagIds = collect(old('tag_ids', isset($package) ? $package->tags->pluck('id')->all() : []))
                            ->map(fn($tagId) => (string) $tagId)
                            ->all();
                    @endphp

                    <div class="mb-3">
                        <label for="tag_ids-field" class="form-label">Tags</label>
                        <select id="tag_ids-field" name="tag_ids[]" class="form-control" multiple>
                            @foreach($packageTags ?? collect() as $tag)
                                <option value="{{ $tag->id }}" {{ in_array((string) $tag->id, $selectedTagIds, true) ? 'selected' : '' }}>
                                    {{ $tag->name }}
                                </option>
                            @endforeach
                            @foreach($selectedTagIds as $selectedTagId)
                                @if(!is_numeric($selectedTagId))
                                    <option value="{{ $selectedTagId }}" selected>{{ $selectedTagId }}</option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">Use tags like PYP, Mock Test, Year Wise, Subject Wise, Topic Wise, Full Length.</small>
                    </div>

                    <!-- <div class="mb-3 d-none">
                        <label for="category_level_1" class="form-label">Category</label>
                        <select id="category_level_1" name="category_level_1" class="form-control">
                            <option value="">Select Category</option>
                            <option @if(isset($package)) {{ $package->category_level_1 == 'previous_year_papers' ? 'selected' : '' }} @endif value="previous_year_papers">Previous Year Papers</option>
                            <option @if(isset($package)) {{ $package->category_level_1 == 'mock_tests' ? 'selected' : '' }} @endif value="mock_tests">Mock Tests</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none">
                        <label for="category_level_2" class="form-label">Subcategory</label>
                        <select id="category_level_2" name="category_level_2" class="form-control">
                            <option value="">Select Subcategory</option>
                            @if(isset($package))
                                @if($package->category_level_1 == 'previous_year_papers')
                                    <option {{ $package->category_level_2 == 'year_wise' ? 'selected' : '' }} value="year_wise">Year Wise</option>
                                    <option {{ $package->category_level_2 == 'subject_wise' ? 'selected' : '' }} value="subject_wise">Subject Wise</option>
                                @else
                                    <option {{ $package->category_level_2 == 'year_wise' ? 'selected' : '' }} value="full_length_test">Full Length Test</option>
                                    <option {{ $package->category_level_2 == 'subject_wise' ? 'selected' : '' }} value="subject_wise_test">Subject Wise Test</option>
                                @endif;
                            @endif
                        </select>
                    </div> -->

                    {{-- Exam List --}}
                    <div
                        id="parentExamList"
                        class="mb-3 {{ (isset($level3Type) && $level3Type === 'exam') ? '' : 'd-none' }}"
                    >
                        <label for="exam_id" class="form-label">Exam List</label>
                        <select id="exam_id" name="exam_id" class="form-control">
                            <option value="">Select Exam List</option>

                            @if(isset($level3Type) && $level3Type === 'exam')
                                @foreach($level3Data as $exam)
                                    <option
                                        value="{{ $exam->id }}"
                                        {{ (isset($package) && $package->exams->contains($exam->id)) ? 'selected' : '' }}
                                    >
                                        {{ $exam->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <label for="exam_display_order" class="form-label mt-2">Exam Order in this Package (optional)</label>
                        <input type="number" min="0" id="exam_display_order" name="exam_display_order" class="form-control"
                            value="{{ old('exam_display_order', $selectedExamOrder ?? '') }}" placeholder="Leave blank to use the normal exam name order">
                    </div>

                    {{-- Subject List --}}
                    <div
                        id="parentSubjectList"
                        class="mb-3 {{ (isset($level3Type) && $level3Type === 'subject') ? '' : 'd-none' }}"
                    >
                        <label for="subject_id" class="form-label">Subject List</label>
                        <select id="subject_id" name="subject_id" class="form-control">
                            <option value="">Select Subject List</option>

                            @if(isset($level3Type) && $level3Type === 'subject')
                                @foreach($level3Data as $subject)
                                    <option
                                        value="{{ $subject->id }}"
                                        {{ (isset($package) && $package->subject_id == $subject->id) ? 'selected' : '' }}
                                    >
                                        {{ $subject->subject_name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    {{-- 2. Name Field --}}
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name"
                            value="{{ old('name', isset($package) ? $package->name : '') }}" required>
                    </div>

                    {{-- 3. Description Field --}}
                    <div class="mb-3 position-relative">
                        @component('components.textarea-editor', [
                        'id' => 'description',
                        'name' => 'description',
                        'label' => 'Description',
                        'placeholder' => 'Enter description...',
                        'value' => old('description', isset($package) ? $package->description : '')
                        ])
                        @endcomponent
                    </div>

                    {{-- Layout Breaker to stop overlapping --}}
                    <div class="w-100 my-3"></div> 

                    {{-- 4. Package Type & Expiry Days (Side by Side) --}}
                    <div class="row align-items-center">
                        {{-- Package Type --}}
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Package Type</label>
                            <div class="d-flex gap-3 mt-2">
                                @if($paidPackagesAvailable ?? false)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="paid" name="package_type" value="paid" 
                                        {{ old('package_type', isset($package) ? $package->package_type : 'paid') == 'paid' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="paid">Paid</label>
                                </div>
                                @endif
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="free" name="package_type" value="free" 
                                        {{ !($paidPackagesAvailable ?? false) || old('package_type', isset($package) ? $package->package_type : 'paid') == 'free' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="free">Free</label>
                                </div>
                            </div>
                        </div>

                        {{-- Expiry Days --}}
                        <div class="col-md-6 mb-3">
                            <label for="expiry_days" class="form-label">Expiry Days</label>
                            <input type="number" class="form-control" id="expiry_days" name="expiry_days"
                                value="{{ old('expiry_days', isset($package) ? $package->expiry_days : '') }}" required>
                        </div>
                    </div>

                    <div class="mb-3 p-3 package-feature-box" id="registration-auto-enroll-box">
                        <input type="hidden" name="auto_enroll_on_registration" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto_enroll_on_registration"
                                name="auto_enroll_on_registration" value="1"
                                {{ old('auto_enroll_on_registration', $package->auto_enroll_on_registration ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="auto_enroll_on_registration">
                                Add automatically after general group registration
                            </label>
                        </div>
                        <small class="text-muted">Free packages only. Contextual enrolment still adds the exact package the student selected.</small>
                    </div>

                    {{-- 5. Amount Fields --}}
                    <div class="mb-3" id="amount-fields">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount"
                                    value="{{ old('amount', isset($package) ? $package->amount : '') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="discounted_amount" class="form-label">Discounted Amount</label>
                                <input type="number" class="form-control" id="discounted_amount" name="discounted_amount"
                                    value="{{ old('discounted_amount', isset($package) ? $package->discounted_amount : '') }}">
                            </div>
                        </div>
                    </div>

                    {{-- 6. Photo --}}
                    <div class="mb-3">
                        <label for="photo" class="form-label">Photo</label>
                        <input type="file" class="form-control" id="photo" name="photo" onchange="previewImage(event)">
                        @if(isset($package) && $package->photo)
                        <div class="mt-3 d-flex align-items-start gap-3" id="existing-photo-wrap">
                            <img id="photo-preview" src="{{ asset($package->photo) }}" alt="Photo"
                                style="max-width: 200px;">
                            <div>
                                <input type="hidden" name="remove_photo" id="remove_photo-field" value="0">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="remove-photo-btn">
                                    <i class="ri-delete-bin-line me-1"></i> Remove image
                                </button>
                                <div class="text-muted small mt-1">The package will be saved without an image.</div>
                            </div>
                        </div>
                        @else
                        <img id="photo-preview" src="#" alt="Photo"
                            style="display: none; max-width: 200px; margin-top: 10px;">
                        @endif
                    </div>

                    {{-- 7. STATUS (Fixed at Bottom) --}}
                    <div class="mb-3 p-3 package-feature-box">
                        <label for="package_status" class="form-label fw-bold">Status</label>
                        <select class="form-select" id="package_status" name="status" required>
                            <option value="1" {{ old('status', $package->status ?? 1) == 1 ? 'selected' : '' }}>Published (Visible to students)</option>
                            <option value="0" {{ old('status', $package->status ?? 1) == 0 ? 'selected' : '' }}>Unpublished (Hidden from students)</option>
                        </select>
                        <small class="text-muted">Set whether this package is visible to students or not.</small>
                    </div>

                    <div class="mb-3 p-3 package-feature-box">
                        <input type="hidden" name="show_pdf_download" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="show_pdf_download" name="show_pdf_download" value="1"
                                {{ old('show_pdf_download', $package->show_pdf_download ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="show_pdf_download">
                                Show question-paper PDF on course details
                            </label>
                        </div>
                        <small class="text-muted">Controls the public Question Paper button. Admins can still use exam management tools.</small>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="pdf_title_text">First-page title</label>
                                <input type="text" class="form-control" id="pdf_title_text" name="pdf_title_text"
                                    value="{{ old('pdf_title_text', $package->pdf_title_text ?? '') }}"
                                    maxlength="255" placeholder="Optional: no title by default">
                                <small class="text-muted">Controls the large website title previously shown as ExamElite on the first page. Leave blank to hide it.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="pdf_header_text">PDF page header</label>
                                <input type="text" class="form-control" id="pdf_header_text" name="pdf_header_text"
                                    value="{{ old('pdf_header_text', $package->pdf_header_text ?? '') }}"
                                    maxlength="255" placeholder="Default: paper name">
                                <small class="text-muted">Repeated from page 2 onward; the first PDF page has no repeating header.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="pdf_footer_text">PDF page footer</label>
                                <input type="text" class="form-control" id="pdf_footer_text" name="pdf_footer_text"
                                    value="{{ old('pdf_footer_text', $package->pdf_footer_text ?? '') }}"
                                    maxlength="500" placeholder="Default: website name and link">
                                <small class="text-muted">The website link remains clickable beside this text.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold" for="pdf_watermark_text">PDF watermark</label>
                                <input type="text" class="form-control" id="pdf_watermark_text" name="pdf_watermark_text"
                                    value="{{ old('pdf_watermark_text', $package->pdf_watermark_text ?? '') }}"
                                    maxlength="255" placeholder="Optional: no watermark by default">
                                <small class="text-muted">If entered, it is shown lightly behind the questions on every page. Leave blank for no watermark.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 p-3 package-feature-box">
                        <input type="hidden" name="show_solution_pdf_download" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="show_solution_pdf_download" name="show_solution_pdf_download" value="1"
                                {{ old('show_solution_pdf_download', $package->show_solution_pdf_download ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="show_solution_pdf_download">
                                Show protected solution PDF
                            </label>
                        </div>
                        <small class="text-muted">Students must log in or register. Free packages are automatically activated in My Exams before downloading.</small>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="solution_pdf_title_text">Solution first-page title</label>
                                <input type="text" class="form-control" id="solution_pdf_title_text" name="solution_pdf_title_text"
                                    value="{{ old('solution_pdf_title_text', $package->solution_pdf_title_text ?? '') }}"
                                    maxlength="255" placeholder="Optional: no title by default">
                                <small class="text-muted">Controls the large website title on the first solution page. Leave blank to hide it.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="solution_pdf_header_text">Solution PDF page header</label>
                                <input type="text" class="form-control" id="solution_pdf_header_text" name="solution_pdf_header_text"
                                    value="{{ old('solution_pdf_header_text', $package->solution_pdf_header_text ?? '') }}"
                                    maxlength="255" placeholder="Default: paper name - Solutions">
                                <small class="text-muted">Repeated from page 2 onward; the first solution page has no repeating header.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="solution_pdf_footer_text">Solution PDF page footer</label>
                                <input type="text" class="form-control" id="solution_pdf_footer_text" name="solution_pdf_footer_text"
                                    value="{{ old('solution_pdf_footer_text', $package->solution_pdf_footer_text ?? '') }}"
                                    maxlength="500" placeholder="Default: website name and link">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold" for="solution_pdf_watermark_text">Solution PDF watermark</label>
                                <input type="text" class="form-control" id="solution_pdf_watermark_text" name="solution_pdf_watermark_text"
                                    value="{{ old('solution_pdf_watermark_text', $package->solution_pdf_watermark_text ?? '') }}"
                                    maxlength="255" placeholder="Optional: no watermark by default">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 p-3 package-feature-box">
                        <input type="hidden" name="flashcards_enabled" value="0">
                        @if($flashcardsAvailable ?? false)
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="flashcards_enabled" name="flashcards_enabled" value="1"
                                    {{ old('flashcards_enabled', $package->flashcards_enabled ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="flashcards_enabled">
                                    Enable study cards for this package
                                </label>
                            </div>
                            <small class="text-muted">Students with access to this package can study the cards linked to it.</small>
                            <input type="hidden" name="guest_flashcards_enabled" value="0">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="guest_flashcards_enabled" name="guest_flashcards_enabled" value="1"
                                    {{ old('guest_flashcards_enabled', $package->guest_flashcards_enabled ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="guest_flashcards_enabled">
                                    Allow guests to study cards
                                </label>
                            </div>
                            <small class="text-muted">Non-logged-in visitors can open study cards from the course details page when this is enabled.</small>
                        @else
                            <input type="hidden" name="guest_flashcards_enabled" value="0">
                            <div class="fw-bold text-muted">Study Cards are not included in this SaaS plan.</div>
                        @endif
                    </div>

                    <div class="mb-3 p-3 package-feature-box">
                        <input type="hidden" name="ai_flashcard_generation_enabled" value="0">
                        @if($aiFlashcardGenerationAvailable ?? false)
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="ai_flashcard_generation_enabled" name="ai_flashcard_generation_enabled" value="1"
                                    {{ old('ai_flashcard_generation_enabled', $package->ai_flashcard_generation_enabled ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="ai_flashcard_generation_enabled">
                                    Allow AI study card generation for this package
                                </label>
                            </div>
                            <small class="text-muted">Manual study cards work without this. AI generation can be added to the package workflow separately.</small>
                        @else
                            <div class="fw-bold text-muted">AI study card generation is not included in this SaaS plan.</div>
                        @endif
                    </div>

                      @include('partials.seo-fields', ['seoModel' => $package ?? null])

                    {{-- Buttons --}}
                    <div class="d-flex justify-content-end mt-4">
                        <a href="{{ route('packages.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-success ms-2">{{ isset($package) ? 'Update' : 'Submit' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
{{-- Select2 JS --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
        // Category change
        // -----------------------------
        // $('#category_level_1').on('change', function () {
        //     const category_level_1 = $(this).val();
        //     const items = level2Options[category_level_1] || [];

        //     renderOptions(
        //         $('#category_level_2'),
        //         items,
        //         'Select Subcategory',
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
        const filterPackageCategories = () => {
            const selectedGroups = ($('#group_ids-field').val() || []).map(String);
            const $category = $('#category_level_1');
            $category.find('option[value!=""]').each(function () {
                const allowed = String($(this).data('groups') || '').split(',').filter(Boolean);
                const visible = allowed.length === 0 || selectedGroups.every(id => allowed.includes(id));
                $(this).prop('disabled', !visible).prop('hidden', !visible);
            });
            if ($category.find('option:selected').prop('disabled')) $category.val('');

            const parentId = String($category.val() || '');
            const $subcategory = $('#category_level_2');
            $subcategory.find('option[value!=""]').each(function () {
                const visible = parentId !== '' && String($(this).data('parent')) === parentId;
                $(this).prop('disabled', !visible).prop('hidden', !visible);
            });
            if ($subcategory.find('option:selected').prop('disabled')) $subcategory.val('');
        };

        $('#category_level_1').on('change.categoryFilter', filterPackageCategories);
        $('#group_ids-field').on('change.categoryFilter', function () {
            filterPackageCategories();
            fetchCategoryLevel3();
        });
        filterPackageCategories();

        @if(isset($exam))
            toggleLevel3Containers($('#category_level_2').val());
        @endif;

    });


    document.addEventListener('DOMContentLoaded', function() {
        // Radio button logic (Paid/Free)
        const packageTypeRadios = document.querySelectorAll('input[name="package_type"]');
        const amountFields = document.getElementById('amount-fields');
        const autoEnrollBox = document.getElementById('registration-auto-enroll-box');

        // Function to toggle amount fields
        function toggleAmountFields() {
            const selected = document.querySelector('input[name="package_type"]:checked');
            if (selected && selected.value === 'free') {
                amountFields.style.display = 'none';
                if (autoEnrollBox) autoEnrollBox.style.display = '';
            } else {
                amountFields.style.display = 'block';
                if (autoEnrollBox) autoEnrollBox.style.display = 'none';
            }
        }

        // Add listeners
        packageTypeRadios.forEach(radio => {
            radio.addEventListener('change', toggleAmountFields);
        });
        
        // Run on load
        toggleAmountFields();

        // Select2 logic
        $(document).ready(function() {
            $('#group_ids-field').select2({
                placeholder: "Select Groups",
                allowClear: true,
                closeOnSelect: false,
                width: '100%' // Force width fix
            });

            $('#tag_ids-field').select2({
                placeholder: "Select or type tags",
                tags: true,
                tokenSeparators: [','],
                closeOnSelect: false,
                width: '100%'
            });
        });

        // SweetAlert logic
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

    // Image preview logic
    function previewImage(event) {
        const removeField = document.getElementById('remove_photo-field');
        const existingWrap = document.getElementById('existing-photo-wrap');
        if (removeField) {
            removeField.value = '0';
        }
        if (existingWrap) {
            existingWrap.classList.remove('d-none');
        }

        const reader = new FileReader();
        reader.onload = function() {
            const output = document.getElementById('photo-preview');
            output.src = reader.result;
            output.style.display = 'block';
        };
        if(event.target.files[0]){
            reader.readAsDataURL(event.target.files[0]);
        }
    }

    document.addEventListener('click', function(event) {
        const button = event.target.closest('#remove-photo-btn');
        if (!button) return;

        const removeField = document.getElementById('remove_photo-field');
        const preview = document.getElementById('photo-preview');
        const fileInput = document.getElementById('photo');
        const existingWrap = document.getElementById('existing-photo-wrap');

        if (removeField) {
            removeField.value = '1';
        }
        if (fileInput) {
            fileInput.value = '';
        }
        if (preview) {
            preview.removeAttribute('src');
            preview.style.display = 'none';
        }
        if (existingWrap) {
            existingWrap.classList.add('d-none');
        }
    });

</script>
@endsection
