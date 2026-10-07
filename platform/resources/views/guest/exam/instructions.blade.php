<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->name }} - Exam Instructions | Examelite</title>
    <meta name="description" content="Practice {{ $exam->name }} with {{ $exam->questions->count() }} questions. Take this free practice exam with detailed solutions.">
    <meta name="keywords" content="{{ $exam->name }}, practice exam, MCQ, online test">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .exam-card { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); overflow: hidden; margin-top: 50px; margin-bottom: 50px; }
        .exam-header { background: linear-gradient(135deg, #4a90e2, #357abd); color: white; padding: 30px; text-align: center; }
        .exam-body { padding: 40px; }
        .instruction-list { list-style: none; padding: 0; }
        .instruction-list li { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; }
        .instruction-list li i { width: 30px; color: #4a90e2; margin-right: 15px; }
        .btn-start { background: linear-gradient(135deg, #4a90e2, #357abd); border: none; padding: 15px; font-size: 18px; font-weight: bold; border-radius: 50px; margin-top: 20px; width: 100%; }
        .btn-start:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="exam-card">
                    <div class="exam-header">
                        <h1 class="h2 mb-2">{{ $exam->name }}</h1>
                        <p class="mb-0 opacity-75">Practice Exam | MCQ Based Test</p>
                    </div>
                    <div class="exam-body">
                        <h4><i class="fas fa-info-circle me-2"></i> Exam Instructions</h4>
                        <ul class="instruction-list">
                            <li><i class="fas fa-question-circle"></i> Total Questions: <strong>{{ $exam->questions->count() }}</strong></li>
                            <li><i class="fas fa-clock"></i> Duration: <strong>{{ $exam->duration ?? 'Unlimited' }} minutes</strong></li>
                            <li><i class="fas fa-check-circle"></i> Each question carries equal marks</li>
                            <li><i class="fas fa-chart-line"></i> Instant results after submission</li>
                            <li><i class="fas fa-user-friends"></i> Guest users: Login after exam to save results</li>
                        </ul>
                        
                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This is a free practice exam. You can take it as a guest. After completion, login to see detailed solutions.
                        </div>
                        
                        <a href="{{ url('/guest/exam/start/' . $exam->slug) }}" class="btn btn-start text-white">
                            <i class="fas fa-play me-2"></i> Start Exam Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
