<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - Exam Questions</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            margin: 20px;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #0d9488;
        }
        
        .exam-title {
            font-size: 28px;
            font-weight: bold;
            color: #0d9488;
            margin-bottom: 10px;
        }
        
        .exam-meta {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .exam-meta span {
            margin: 0 10px;
            padding: 0 10px;
            border-right: 1px solid #ddd;
        }
        
        .exam-meta span:last-child {
            border-right: none;
        }
        
        .question {
            margin-bottom: 35px;
            page-break-inside: avoid;
            padding: 15px;
            background: #f9fafb;
            border-left: 4px solid #0d9488;
            border-radius: 8px;
        }
        
        .question-number {
            font-weight: bold;
            font-size: 16px;
            color: #0d9488;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #cbd5e1;
        }
        
        .question-text {
            font-size: 14px;
            margin-bottom: 15px;
            padding-left: 10px;
            color: #1e293b;
            line-height: 1.5;
        }
        
        .options {
            margin-left: 25px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        
        .option {
            margin: 8px 0;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .option-letter {
            font-weight: bold;
            display: inline-block;
            width: 30px;
            color: #0d9488;
        }
        
        .marks {
            font-size: 12px;
            color: #10b981;
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px dashed #e2e8f0;
            font-weight: 500;
        }
        
        .explanation {
            margin-top: 12px;
            padding: 10px;
            background: #f0fdf4;
            border-left: 3px solid #10b981;
            font-size: 12px;
            color: #166534;
            border-radius: 4px;
        }
        
        .explanation strong {
            color: #065f46;
        }
        
        footer {
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @page {
            margin: 2cm;
            footer: html_footer;
        }
        
        .watermark {
            position: fixed;
            bottom: 50px;
            right: 50px;
            opacity: 0.1;
            font-size: 60px;
            color: #0d9488;
            transform: rotate(-30deg);
        }
        
        .badge {
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="exam-title">{{ $exam->name }}</div>
        <div class="exam-meta">
            <span><i class="fa-regular fa-file-lines"></i> Total Questions: {{ $total_questions }}</span>
            <span><i class="fa-regular fa-clock"></i> Duration: {{ $exam->duration ?? 180 }} Minutes</span>
            <span><i class="fa-regular fa-calendar"></i> Generated: {{ $generated_date }}</span>
        </div>
    </div>
    
    @foreach($questions as $index => $q)
    <div class="question">
        <div class="question-number">
            Question {{ $index + 1 }} @if($q->marks) <span class="badge">Marks: {{ $q->marks }}</span> @endif
        </div>
        
        <div class="question-text">
            {!! nl2br(e($q->question)) !!}
        </div>
        
        @if($q->option1 || $q->option2 || $q->option3 || $q->option4 || $q->option5 || $q->option6)
        <div class="options">
            @if($q->option1 && trim($q->option1) != '')
                <div class="option">
                    <span class="option-letter">A.</span> {!! nl2br(e($q->option1)) !!}
                </div>
            @endif
            @if($q->option2 && trim($q->option2) != '')
                <div class="option">
                    <span class="option-letter">B.</span> {!! nl2br(e($q->option2)) !!}
                </div>
            @endif
            @if($q->option3 && trim($q->option3) != '')
                <div class="option">
                    <span class="option-letter">C.</span> {!! nl2br(e($q->option3)) !!}
                </div>
            @endif
            @if($q->option4 && trim($q->option4) != '')
                <div class="option">
                    <span class="option-letter">D.</span> {!! nl2br(e($q->option4)) !!}
                </div>
            @endif
            @if($q->option5 && trim($q->option5) != '')
                <div class="option">
                    <span class="option-letter">E.</span> {!! nl2br(e($q->option5)) !!}
                </div>
            @endif
            @if($q->option6 && trim($q->option6) != '')
                <div class="option">
                    <span class="option-letter">F.</span> {!! nl2br(e($q->option6)) !!}
                </div>
            @endif
        </div>
        @endif
        
        @if($q->explanation && trim($q->explanation) != '')
        <div class="explanation">
            <strong>📝 Explanation:</strong> {!! nl2br(e($q->explanation)) !!}
        </div>
        @endif
    </div>
    @endforeach
    
    <footer>
        Generated by ExamElite | {{ $generated_date }} | Page {PAGE_NUM} of {PAGE_COUNT}
    </footer>
</body>
</html>
