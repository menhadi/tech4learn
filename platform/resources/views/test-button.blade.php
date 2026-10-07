<!DOCTYPE html>
<html>
<head>
    <title>Test Exam Button</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d9488; color: white; text-decoration: none; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Test Exam Button</h1>
    
    @php
        $exams = App\Models\Exam::where('status', 'Active')->get();
    @endphp
    
    @if($exams->count() > 0)
        <h2>Available Exams:</h2>
        @foreach($exams as $exam)
            <div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <strong>{{ $exam->name }}</strong> (ID: {{ $exam->id }})
                <br><br>
                <a href="/guest/exam/guideline/{{ $exam->slug }}" class="btn">
                    Start Exam (Direct Link)
                </a>
                <br><br>
                <small>URL: /guest/exam/guideline/{{ $exam->slug }}</small>
            </div>
        @endforeach
    @else
        <p class="error">No active exams found.</p>
    @endif
    
    <hr>
    <h3>Debug Info:</h3>
    <pre>
Routes:
@php
    $routes = Route::getRoutes();
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'guest/exam')) {
            echo $route->uri() . " → " . ($route->getName() ?: 'unnamed') . "\n";
        }
    }
@endphp
    </pre>
</body>
</html>
