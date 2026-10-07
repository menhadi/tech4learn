<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->name }} - Take Exam | Examelite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">{{ $exam->name }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-user-friends me-2"></i>
                            Guest Mode - Your progress will be saved after login
                        </div>
                        
                        <form method="POST" action="{{ url('/guest/exam/submit/' . $exam->slug) }}">
                            @csrf
                            @foreach($questions as $question)
                            <div class="question-box mb-4 p-3 border rounded">
                                <h5>Question {{ $loop->iteration }}</h5>
                                <p>{{ $question->question ?? $question->text }}</p>
                                <div class="options">
                                    @if($question->option1)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="1" id="q{{ $question->id }}_1">
                                        <label class="form-check-label" for="q{{ $question->id }}_1">{{ $question->option1 }}</label>
                                    </div>
                                    @endif
                                    @if($question->option2)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="2" id="q{{ $question->id }}_2">
                                        <label class="form-check-label" for="q{{ $question->id }}_2">{{ $question->option2 }}</label>
                                    </div>
                                    @endif
                                    @if($question->option3)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="3" id="q{{ $question->id }}_3">
                                        <label class="form-check-label" for="q{{ $question->id }}_3">{{ $question->option3 }}</label>
                                    </div>
                                    @endif
                                    @if($question->option4)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="4" id="q{{ $question->id }}_4">
                                        <label class="form-check-label" for="q{{ $question->id }}_4">{{ $question->option4 }}</label>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                            
                            {{ $questions->links() }}
                            
                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-check-circle me-2"></i> Submit Exam
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
