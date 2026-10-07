@extends('layouts.master')
@section('title', 'Add Question Language')

@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
@component('components.breadcrumb')
@slot('li_1', '')
@slot('title', 'Add Question Language')
@endcomponent

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">Add Question Language</h4>
            </div>
            <div class="card-body">
                <form id="question-lang-form" method="POST"
                    action="{{ route('questions_langs.store', $question->id) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="language_id" class="form-label">Language</label>
                        <select id="language_id" name="language_id" class="form-control select2" required>
                            <option value="">Select Language</option>
                            @foreach($languages as $language)
                            <option value="{{ $language->id }}">{{ $language->name }}</option>
                            @endforeach
                        </select>
                        @if ($errors->has('language_id'))
                        <div class="invalid-feedback">{{ $errors->first('language_id') }}</div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label for="qtype" class="form-label">Question Type</label>
                        <input type="text" id="qtype" class="form-control" value="{{ $question->qtype->question_type }}"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label for="question" class="form-label">Question</label>
                        <textarea id="question" name="question" class="form-control" rows="3" required></textarea>
                        @if ($errors->has('question'))
                        <div class="invalid-feedback">{{ $errors->first('question') }}</div>
                        @endif
                    </div>

                    <div id="multiple-choice-options" style="display: none;">
                        @for ($i = 1; $i <= 6; $i++) <div class="mb-3">
                            <label for="option{{ $i }}" class="form-label">Option {{ $i }}</label>
                            <textarea id="option{{ $i }}" name="option{{ $i }}" class="form-control"
                                rows="2"></textarea>
                            @if ($errors->has('option' . $i))
                            <div class="invalid-feedback">{{ $errors->first('option' . $i) }}</div>
                            @endif
                    </div>
                    @endfor
            </div>

            <div id="true-false-options" style="display: none;">
                <div class="mb-3">
                    <label for="true_false" class="form-label">True/False</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="true_false" id="true" value="true">
                            <label class="form-check-label" for="true">True</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="true_false" id="false" value="false">
                            <label class="form-check-label" for="false">False</label>
                        </div>
                    </div>
                    @if ($errors->has('true_false'))
                    <div class="invalid-feedback">{{ $errors->first('true_false') }}</div>
                    @endif
                </div>
            </div>

            <div id="fill-blank-options" style="display: none;">
                <div class="mb-3">
                    <label for="fill_blank" class="form-label">Fill in the Blank</label>
                    <textarea id="fill_blank" name="fill_blank" class="form-control" rows="2"></textarea>
                    @if ($errors->has('fill_blank'))
                    <div class="invalid-feedback">{{ $errors->first('fill_blank') }}</div>
                    @endif
                </div>
            </div>

            <div class="mb-3">
                <label for="explanation" class="form-label">Explanation (optional)</label>
                <textarea id="explanation" name="explanation" class="form-control" rows="3"></textarea>
                @if ($errors->has('explanation'))
                <div class="invalid-feedback">{{ $errors->first('explanation') }}</div>
                @endif
            </div>

            <div class="mb-3">
                <label for="hint" class="form-label">Hint (optional)</label>
                <textarea id="hint" name="hint" class="form-control" rows="2"></textarea>
                @if ($errors->has('hint'))
                <div class="invalid-feedback">{{ $errors->first('hint') }}</div>
                @endif
            </div>

            <div class="d-flex justify-content-end">
                <a href="{{ route('questions.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-success ms-2" id="submit-button">Add Question Language</button>
            </div>
            </form>
        </div>
    </div>
</div>
</div>
@endsection

@section('script')
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2();

        const qtype = "{{ $question->qtype->type }}";

        if (qtype === 'multiple_choice') {
            $('#multiple-choice-options').show();
        } else if (qtype === 'true_false') {
            $('#true-false-options').show();
        } else if (qtype === 'fill_blank') {
            $('#fill-blank-options').show();
        }

        $('#language_id').change(function() {
            const languageId = $(this).val();
            const questionId = "{{ $question->id }}";

            if (languageId) {
                $.ajax({
                    url: `/questions_langs/check/${questionId}/${languageId}`,
                    type: 'GET',
                    success: function(response) {
                        if (response.exists) {
                            $('#question-lang-form').attr('action', `/questions_langs/update/${response.data.id}`);
                            $('#submit-button').text('Update Question Language');
                            $('#question').val(response.data.question);
                            $('#option1').val(response.data.option1);
                            $('#option2').val(response.data.option2);
                            $('#option3').val(response.data.option3);
                            $('#option4').val(response.data.option4);
                            $('#option5').val(response.data.option5);
                            $('#option6').val(response.data.option6);
                            $('#hint').val(response.data.hint);
                            $('#explanation').val(response.data.explanation);
                            $('#fill_blank').val(response.data.fill_blank);
                        } else {
                            $('#question-lang-form').attr('action', `{{ route('questions_langs.store', $question->id) }}`);
                            $('#submit-button').text('Add Question Language');
                            $('#question').val('');
                            $('#option1').val('');
                            $('#option2').val('');
                            $('#option3').val('');
                            $('#option4').val('');
                            $('#option5').val('');
                            $('#option6').val('');
                            $('#hint').val('');
                            $('#explanation').val('');
                            $('#fill_blank').val('');
                        }
                    }
                });
            }
        });
    });
</script>
@endsection