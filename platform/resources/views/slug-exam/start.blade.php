<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->name }} - Exam Started | ExamElite</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2563eb; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .content { padding: 30px; }
        .success-box { background: #d1fae5; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #10b981; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $exam->name }}</h1>
            <p>Exam Started Successfully!</p>
        </div>
        <div class="content">
            <div class="success-box">
                ✅ <strong>SLUG URL Working!</strong><br>
                Current URL: /exam/{{ $exam->slug }}/start<br>
                Exam ID: {{ $exam->id }} | Slug: {{ $exam->slug }}
            </div>
            
            <h2>🎯 Exam is Ready</h2>
            <p>Your exam has been initialized successfully.</p>
            <p>Guest ID: {{ session('guest_id') }}</p>
            <p>Exam Result ID: {{ $examResult->id ?? 'N/A' }}</p>
            
            <hr>
            <p><strong>✓ The URL contains SLUG, not ID</strong></p>
            <p><strong>✓ This page will work on refresh</strong></p>
            <p><strong>✓ No JavaScript required</strong></p>
            
            <a href="/" class="btn" style="display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;">Back to Home</a>
        </div>
    </div>
</body>
</html>
