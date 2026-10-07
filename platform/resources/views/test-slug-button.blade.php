<!DOCTYPE html>
<html>
<head>
    <title>Test Slug Button</title>
</head>
<body>
    <h1>Test Exam Button</h1>
    
    @php
        $exam = App\Models\Exam::find(1569);
    @endphp
    
    <div style="border:1px solid #ddd; padding:20px; margin:20px;">
        <h3>{{ $exam->name }}</h3>
        <p>ID: {{ $exam->id }}</p>
        <p>Slug: {{ $exam->slug }}</p>
        
        <h4>Current Button (OLD - Not working):</h4>
        <a href="/guest/exam/guideline/{{ $exam->slug }}" style="background:red; color:white; padding:10px; display:inline-block;">Start Exam with ID</a>
        
        <h4>Fixed Button (NEW - Working):</h4>
        <a href="/guest/exam/guideline/{{ $exam->slug }}" style="background:green; color:white; padding:10px; display:inline-block;">Start Exam with Slug</a>
    </div>
    
    <p>Click the GREEN button - it should work!</p>
    <p>Click the RED button - it will show homepage (broken)</p>
</body>
</html>
