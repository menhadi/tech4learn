<!DOCTYPE html>
<html>
<head>
    <title>Test Exam Links</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .working { background: #d1fae5; padding: 15px; margin: 10px; border-radius: 5px; border-left: 4px solid #10b981; }
        .broken { background: #fee2e2; padding: 15px; margin: 10px; border-radius: 5px; border-left: 4px solid #ef4444; }
        a { display: inline-block; margin-top: 10px; background: #0d9488; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Test Exam Links - Exam 1569</h1>
    
    @php
        $exam = App\Models\Exam::find(1569);
    @endphp
    
    <div class="working">
        <h2>✅ Working URLs (Using SLUG)</h2>
        <p>Slug: <strong>{{ $exam->slug }}</strong></p>
        <p><a href="/guest/exam/guideline/{{ $exam->slug }}">Guideline Page (Slug)</a></p>
        <p><a href="/guest/exam/instructions/{{ $exam->slug }}">Instructions Page (Slug)</a></p>
        <p><a href="/guest/exam/start/{{ $exam->slug }}">Start Exam (Slug)</a></p>
    </div>
    
    <div class="broken">
        <h2>❌ Broken URLs (Using ID - Should redirect or fail)</h2>
        <p><a href="/guest/exam/guideline/{{ $exam->id }}">Guideline Page (ID) - {{ $exam->id }}</a></p>
        <p><a href="/guest/exam/instructions/{{ $exam->id }}">Instructions Page (ID)</a></p>
        <p><a href="/guest/exam/start/{{ $exam->id }}">Start Exam (ID)</a></p>
    </div>
    
    <div class="working">
        <h2>📋 Your Course Page Button Should Point To:</h2>
        <code>/guest/exam/guideline/{{ $exam->slug }}</code>
    </div>
</body>
</html>
