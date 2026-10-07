@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit
        ? route('flashcards.cards.update', [$flashcardSet, $card])
        : route('flashcards.cards.store', $flashcardSet);
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ $action }}" method="POST">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif
                <input type="hidden" name="per_page" value="{{ request('per_page', 50) }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $isEdit ? 'Edit Study Card' : 'Add Study Card' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Card Title <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $card->title ?? '') }}" maxlength="255" placeholder="Example: Newton's laws quick revision">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Card Type</label>
                            @php $cardType = old('card_type', $card->card_type ?? 'basic'); @endphp
                            <select name="card_type" class="form-select js-flashcard-type">
                                <option value="basic" {{ $cardType === 'basic' ? 'selected' : '' }}>Basic Q/A</option>
                                <option value="mcq" {{ $cardType === 'mcq' ? 'selected' : '' }}>MCQ</option>
                                <option value="true_false" {{ $cardType === 'true_false' ? 'selected' : '' }}>True / False</option>
                                <option value="multi_select" {{ $cardType === 'multi_select' ? 'selected' : '' }}>Multiselect MCQ</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Front</label>
                            <textarea name="front" class="form-control" rows="4" required>{{ old('front', $card->front ?? '') }}</textarea>
                        </div>
                        <div class="col-12 flashcard-options-field">
                            <label class="form-label">Options</label>
                            <textarea name="options" class="form-control" rows="4" placeholder="One option per line">{{ old('options', isset($card) && is_array($card->options) ? implode("\n", $card->options) : '') }}</textarea>
                            <div class="form-text">For MCQ and multiselect, add one option per line. True/False cards use True and False automatically.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Back</label>
                            <textarea name="back" class="form-control" rows="4" required>{{ old('back', $card->back ?? '') }}</textarea>
                            <div class="form-text">For MCQ, enter the correct option. For multiselect, enter all correct options separated by commas.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Explanation</label>
                            <textarea name="explanation" class="form-control" rows="3">{{ old('explanation', $card->explanation ?? '') }}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hint</label>
                            <input type="text" name="hint" class="form-control" value="{{ old('hint', $card->hint ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Difficulty</label>
                            <select name="difficulty" class="form-select">
                                <option value="">Not Set</option>
                                @foreach(['Easy', 'Medium', 'Hard'] as $difficulty)
                                    <option value="{{ $difficulty }}" {{ old('difficulty', $card->difficulty ?? '') === $difficulty ? 'selected' : '' }}>{{ $difficulty }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $card->sort_order ?? 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="1" {{ (string) old('status', isset($card) ? (int) $card->status : 1) === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ (string) old('status', isset($card) ? (int) $card->status : 1) === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn el-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn el-btn-primary">{{ $isEdit ? 'Update Card' : 'Add Card' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('shown.bs.modal', function (event) {
                const modal = event.target;
                modal.querySelectorAll('.js-flashcard-type').forEach(function (select) {
                    const syncOptionsField = function () {
                        const field = select.closest('.modal-body')?.querySelector('.flashcard-options-field');
                        if (!field) return;

                        field.style.display = select.value === 'basic' || select.value === 'true_false' ? 'none' : '';
                    };

                    select.removeEventListener('change', syncOptionsField);
                    select.addEventListener('change', syncOptionsField);
                    syncOptionsField();
                });
            });
        </script>
    @endpush
@endonce
