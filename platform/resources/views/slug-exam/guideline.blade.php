<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->name }} - Guidelines | ExamElite</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #0d9488; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .content { padding: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #0d9488; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #0f766e; }
        ul { line-height: 1.8; }
        .slug-info { background: #e0f2fe; padding: 10px; border-radius: 5px; margin: 15px 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $exam->name }}</h1>
            <p>Exam Guidelines</p>
        </div>
        <div class="content">
            <div class="slug-info">
                ✅ SLUG URL: /exam/{{ $exam->slug }}/guideline
            </div>
            
            <h2>📋 Important Guidelines</h2>
            <ul>
                <li>Read each question carefully before answering</li>
                <li>Select the best answer option</li>
                <li>You can navigate between questions</li>
                <li>Do not refresh during the exam</li>
                <li>Submit only when you are finished</li>
            </ul>
            
            <p><strong>Duration:</strong> {{ $exam->duration }} minutes</p>
            <p><strong>Total Questions:</strong> {{ $exam->questions->count() }}</p>
            
            <a href="{{ url('/exam/' . $exam->slug . '/instructions') }}" class="btn">
                Proceed to Instructions →
            </a>
        </div>
    </div>
</body>
</html>
