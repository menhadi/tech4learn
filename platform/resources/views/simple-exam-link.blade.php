<!DOCTYPE html>
<html>
<head>
    <title>Simple Exam Links - ExamElite</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .exam-card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d9488; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #0f766e; }
    </style>
</head>
<body>
    <h1>ExamElite - Simple Working Links</h1>
    <p>These links work on refresh because they are plain HTML, not JavaScript.</p>
    
    @php
        $exams = App\Models\Exam::where('status', 'Active')->limit(10)->get();
    @endphp
    
    @foreach($exams as $exam)
    <div class="exam-card">
        <h3>{{ $exam->name }}</h3>
        <p>Duration: {{ $exam->duration }} minutes | Questions: {{ $exam->questions->count() }}</p>
        <p>Slug: {{ $exam->slug }}</p>
        <a href="/simple-exam/{{ $exam->id }}" class="btn">Start Exam (ID based)</a>
        <a href="/simple-exam-slug/{{ $exam->slug }}" class="btn">Start Exam (Slug based)</a>
    </div>
    @endforeach
</body>
</html>
