<!DOCTYPE html>
<html>
<head>
    <title>{{ $exam->name }} - Exam</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .header { background: #0d9488; color: white; padding: 20px; text-align: center; }
        .container { max-width: 800px; margin: 20px auto; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .question { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        .timer { background: #333; color: white; padding: 10px; text-align: center; font-size: 24px; }
        .btn { background: #0d9488; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0f766e; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $exam->name }}</h1>
    </div>
    <div class="container">
        <div class="timer">{{ __('ui.time_left') }} <span id="timer">--:--</span></div>
        <h3>{{ __('ui.exam_started') }}</h3>
        <p>Guest ID: {{ session('guest_id', 'New Guest') }}</p>
        <p>Total Questions: {{ $exam->questions->count() }}</p>
        <p>Duration: {{ $exam->duration }} minutes</p>
        <button class="btn" onclick="alert(@json(__('ui.exam_in_progress')))">{{ __('ui.start_answering') }}</button>
    </div>
    <script>
        let time = {{ $exam->duration * 60 }};
        function updateTimer() {
            let m = Math.floor(time / 60);
            let s = time % 60;
            document.getElementById('timer').innerHTML = m + ':' + (s < 10 ? '0' : '') + s;
            if (time > 0) { time--; setTimeout(updateTimer, 1000); }
        }
        updateTimer();
    </script>
</body>
</html>
