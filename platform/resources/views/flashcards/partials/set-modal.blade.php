@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('flashcards.update', $set) : route('flashcards.store');
    $categoryNames = collect($categoryNames ?? []);
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ $action }}" method="POST" data-study-card-set-form data-mode="{{ $isEdit ? 'edit' : 'create' }}">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEdit ? 'Edit Study Card Set' : 'Add Study Card Set' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Package</label>
                            <select name="package_id" class="form-select" required>
                                <option value="">Select Package</option>
                                @foreach($packages as $packageOption)
                                    <option
                                        value="{{ $packageOption->id }}"
                                        data-package-name="{{ $packageOption->name }}"
                                        data-group-ids="{{ $packageOption->groups->pluck('id')->implode(',') }}"
                                        data-category-id="{{ $packageOption->category_level_1 }}"
                                        data-subcategory-id="{{ $packageOption->category_level_2 }}"
                                        data-category-name="{{ $categoryNames->get($packageOption->category_level_1) }}"
                                        data-subcategory-name="{{ $categoryNames->get($packageOption->category_level_2) }}"
                                        {{ (string) old('package_id', $set->package_id ?? '') === (string) $packageOption->id ? 'selected' : '' }}
                                    >
                                        {{ $packageOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Create study card sets anytime. Students see them only when the package and set are active.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $set->title ?? '') }}" required data-study-card-title>
                            <small class="text-muted">Auto-filled from category, subject, topic and sub topic. You can edit it.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Group</label>
                            <select name="group_id" class="form-select">
                                <option value="">Package Level</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}" {{ (string) old('group_id', $set->group_id ?? '') === (string) $group->id ? 'selected' : '' }}>
                                        {{ $group->group_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_level_1" class="form-select" data-study-card-category>
                                <option value="">Any Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) old('category_level_1', $set->category_level_1 ?? '') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6" data-subcategory-ui>
                            <label class="form-label">Subcategory</label>
                            <select name="category_level_2" class="form-select" data-study-card-subcategory>
                                <option value="">Any Subcategory</option>
                                @foreach($subcategories as $subcategory)
                                    <option
                                        value="{{ $subcategory->id }}"
                                        data-parent-id="{{ $subcategory->parent_id }}"
                                        {{ (string) old('category_level_2', $set->category_level_2 ?? '') === (string) $subcategory->id ? 'selected' : '' }}
                                    >
                                        {{ $subcategory->title }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Visible for verification; not used in the auto title.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select">
                                <option value="">Any Subject</option>
                                @foreach($subjects as $subject)
                                    <option
                                        value="{{ $subject->id }}"
                                        data-group-ids="{{ $subject->groups->pluck('id')->implode(',') }}"
                                        {{ (string) old('subject_id', $set->subject_id ?? '') === (string) $subject->id ? 'selected' : '' }}
                                    >
                                        {{ $subject->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Topic</label>
                            <select name="topic_id" class="form-select">
                                <option value="">Any Topic</option>
                                @foreach($topics as $topic)
                                    <option
                                        value="{{ $topic->id }}"
                                        data-subject-id="{{ $topic->subject_id }}"
                                        data-group-id="{{ $topic->group_id }}"
                                        {{ (string) old('topic_id', $set->topic_id ?? '') === (string) $topic->id ? 'selected' : '' }}
                                    >
                                        {{ $topic->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sub Topic</label>
                            <select name="stopic_id" class="form-select">
                                <option value="">Any Sub Topic</option>
                                @foreach($stopics as $stopic)
                                    <option
                                        value="{{ $stopic->id }}"
                                        data-subject-id="{{ $stopic->subject_id }}"
                                        data-topic-id="{{ $stopic->topic_id }}"
                                        data-group-id="{{ $stopic->group_id }}"
                                        {{ (string) old('stopic_id', $set->stopic_id ?? '') === (string) $stopic->id ? 'selected' : '' }}
                                    >
                                        {{ $stopic->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Curriculum Order <span class="text-muted">(optional)</span></label><input type="number" name="display_order" class="form-control" min="0" value="{{ old('display_order', $set->display_order ?? '') }}" placeholder="Use title order"><small class="text-muted">Lower positive numbers appear first. This does not lock navigation.</small></div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="1" {{ (string) old('status', isset($set) ? (int) $set->status : 1) === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ (string) old('status', isset($set) ? (int) $set->status : 1) === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    @include('partials.seo-fields', ['seoModel' => $set ?? null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn el-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn el-btn-primary">{{ $isEdit ? 'Update Set' : 'Create Set' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
if (!window.bindStudyCardSetTitles) {
    window.bindStudyCardSetTitles = function(root = document) {
        root.querySelectorAll('[data-study-card-set-form]').forEach(function(form) {
            if (form.dataset.titleBound === '1') return;
            form.dataset.titleBound = '1';

            const titleInput = form.querySelector('[data-study-card-title]');
            if (!titleInput) return;

            let manualTitle = form.dataset.mode === 'edit' && titleInput.value.trim().length > 0;
            const selects = ['package_id', 'group_id', 'category_level_1', 'category_level_2', 'subject_id', 'topic_id', 'stopic_id']
                .map((name) => form.querySelector(`[name="${name}"]`))
                .filter(Boolean);

            const optionList = function(name) {
                const select = form.querySelector(`[name="${name}"]`);
                return select ? Array.from(select.options) : [];
            };

            const selectedValue = function(name) {
                return form.querySelector(`[name="${name}"]`)?.value || '';
            };

            const selectedText = function(name) {
                const select = form.querySelector(`[name="${name}"]`);
                if (!select || !select.value) return '';
                return select.options[select.selectedIndex]?.textContent?.trim() || '';
            };

            const csvHas = function(csv, value) {
                if (!value) return true;
                if (!csv) return false;
                return csv.split(',').map((item) => item.trim()).includes(String(value));
            };

            const hideOption = function(option, hidden) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                option.hidden = hidden;
                option.disabled = hidden;
            };

            const clearHiddenSelection = function(name) {
                const select = form.querySelector(`[name="${name}"]`);
                if (!select) return false;
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption?.hidden || selectedOption?.disabled) {
                    select.value = '';
                    return true;
                }
                return false;
            };

            const selectByOptionText = function(select, text) {
                if (!select || !text) return;
                const option = Array.from(select.options).find((item) => item.textContent.trim() === text && !item.hidden && !item.disabled);
                if (option) select.value = option.value;
            };

            const packageMatchesGroup = function(option, groupId) {
                return !option.value || csvHas(option.dataset.groupIds || '', groupId);
            };

            const packageMatchesCategory = function(option, categoryId, subcategoryId = '') {
                if (!option.value) return true;
                if (categoryId && option.dataset.categoryId !== categoryId) return false;
                if (subcategoryId && option.dataset.subcategoryId !== subcategoryId) return false;
                return true;
            };

            const filterScopeOptions = function() {
                const groupId = selectedValue('group_id');
                const categoryId = selectedValue('category_level_1');
                const subcategoryId = selectedValue('category_level_2');
                const subjectId = selectedValue('subject_id');
                const topicId = selectedValue('topic_id');

                const packageOptions = optionList('package_id');
                const packageOptionsForGroup = packageOptions.filter((option) => option.value && packageMatchesGroup(option, groupId));

                const allowedCategoryIds = new Set(packageOptionsForGroup.map((option) => option.dataset.categoryId).filter(Boolean));
                optionList('category_level_1').forEach((option) => {
                    hideOption(option, !!groupId && allowedCategoryIds.size > 0 && !allowedCategoryIds.has(option.value));
                });
                if (clearHiddenSelection('category_level_1')) {
                    form.querySelector('[name="category_level_2"]') && (form.querySelector('[name="category_level_2"]').value = '');
                }

                const currentCategoryId = selectedValue('category_level_1');
                const packageOptionsForCategory = packageOptionsForGroup.filter((option) => packageMatchesCategory(option, currentCategoryId));
                const allowedSubcategoryIds = new Set(packageOptionsForCategory.map((option) => option.dataset.subcategoryId).filter(Boolean));
                optionList('category_level_2').forEach((option) => {
                    const wrongParent = currentCategoryId && option.dataset.parentId !== currentCategoryId;
                    const unavailableInGroup = !!groupId && allowedSubcategoryIds.size > 0 && !allowedSubcategoryIds.has(option.value);
                    hideOption(option, wrongParent || unavailableInGroup);
                });
                clearHiddenSelection('category_level_2');

                const currentSubcategoryId = selectedValue('category_level_2');
                packageOptions.forEach((option) => {
                    hideOption(
                        option,
                        !packageMatchesGroup(option, groupId) || !packageMatchesCategory(option, currentCategoryId, currentSubcategoryId)
                    );
                });
                clearHiddenSelection('package_id');

                const allowedSubjectIds = new Set();
                optionList('subject_id').forEach((option) => {
                    const hidden = !!groupId && !csvHas(option.dataset.groupIds || '', groupId);
                    hideOption(option, hidden);
                    if (option.value && !hidden) allowedSubjectIds.add(option.value);
                });
                if (clearHiddenSelection('subject_id')) {
                    form.querySelector('[name="topic_id"]') && (form.querySelector('[name="topic_id"]').value = '');
                    form.querySelector('[name="stopic_id"]') && (form.querySelector('[name="stopic_id"]').value = '');
                }

                const currentSubjectId = selectedValue('subject_id');
                optionList('topic_id').forEach((option) => {
                    const wrongSubject = currentSubjectId
                        ? option.dataset.subjectId !== currentSubjectId
                        : (!!groupId && allowedSubjectIds.size > 0 && !allowedSubjectIds.has(option.dataset.subjectId));
                    const wrongGroup = !!groupId && option.dataset.groupId !== groupId;
                    hideOption(option, wrongSubject || wrongGroup);
                });
                if (clearHiddenSelection('topic_id')) {
                    form.querySelector('[name="stopic_id"]') && (form.querySelector('[name="stopic_id"]').value = '');
                }

                const currentTopicId = selectedValue('topic_id');
                optionList('stopic_id').forEach((option) => {
                    const wrongTopic = currentTopicId && option.dataset.topicId !== currentTopicId;
                    const wrongSubject = !currentTopicId && currentSubjectId
                        ? option.dataset.subjectId !== currentSubjectId
                        : (!currentTopicId && !currentSubjectId && !!groupId && allowedSubjectIds.size > 0 && !allowedSubjectIds.has(option.dataset.subjectId));
                    const wrongGroup = !!groupId && option.dataset.groupId !== groupId;
                    hideOption(option, wrongTopic || wrongSubject || wrongGroup);
                });
                clearHiddenSelection('stopic_id');
            };

            const applyPackageScopeDefaults = function(force = false) {
                const packageSelect = form.querySelector('[name="package_id"]');
                const categorySelect = form.querySelector('[name="category_level_1"]');
                const subcategorySelect = form.querySelector('[name="category_level_2"]');

                filterScopeOptions();
                const packageOption = packageSelect?.value ? packageSelect.options[packageSelect.selectedIndex] : null;

                if (packageOption && (force || !categorySelect?.value)) {
                    selectByOptionText(categorySelect, packageOption.dataset.categoryName || '');
                }

                filterScopeOptions();

                if (packageOption && (force || !subcategorySelect?.value)) {
                    selectByOptionText(subcategorySelect, packageOption.dataset.subcategoryName || '');
                }

                filterScopeOptions();
            };

            const rebuildTitle = function() {
                const packageSelect = form.querySelector('[name="package_id"]');
                const packageOption = packageSelect?.options[packageSelect.selectedIndex];
                filterScopeOptions();

                if (manualTitle) return;

                const parts = [
                    selectedText('category_level_1') || packageOption?.dataset.categoryName || '',
                    selectedText('subject_id'),
                    selectedText('topic_id'),
                    selectedText('stopic_id'),
                ].filter(Boolean);

                if (!parts.length) {
                    const groupText = selectedText('group_id');
                    const packageName = packageOption?.dataset.packageName || selectedText('package_id');
                    if (groupText) parts.push(groupText);
                    if (packageName) parts.push(packageName);
                }

                titleInput.value = parts.join(' | ');
            };

            titleInput.addEventListener('input', function() {
                manualTitle = titleInput.value.trim().length > 0;
            });

            form.querySelector('[name="package_id"]')?.addEventListener('change', function() {
                applyPackageScopeDefaults(true);
            });

            selects.forEach((select) => select.addEventListener('change', rebuildTitle));

            applyPackageScopeDefaults(false);
            rebuildTitle();
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.bindStudyCardSetTitles();
    });

    document.addEventListener('shown.bs.modal', function(event) {
        window.bindStudyCardSetTitles(event.target);
    });
}
</script>
