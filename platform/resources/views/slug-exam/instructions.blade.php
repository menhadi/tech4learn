<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $exam->name }} - Instructions | ExamElite</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #059669; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        .content { padding: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background: #059669; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #047857; }
        .info-box { background: #fef3c7; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $exam->name }}</h1>
            <p>Exam Instructions</p>
        </div>
        <div class="content">
            <div class="info-box">
                <strong>URL with SLUG:</strong><br>
                /exam/{{ $exam->slug }}/instructions
            </div>
            
            <h2>📝 Instructions</h2>
            <ul>
                <li>Total Questions: <strong>{{ $exam->questions->count() }}</strong></li>
                <li>Duration: <strong>{{ $exam->duration }} minutes</strong></li>
                <li>Passing Percentage: <strong>{{ $exam->passing_percentage }}%</strong></li>
                <li>No negative marking</li>
                <li>Each question carries equal marks</li>
            </ul>
            
            <a href="{{ url('/exam/' . $exam->slug . '/start') }}" class="btn">
                🚀 Start Exam Now
            </a>
        </div>
    </div>
</body>
</html>
