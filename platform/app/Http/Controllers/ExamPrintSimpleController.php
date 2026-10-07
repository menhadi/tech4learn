<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamPrintSimpleController extends Controller
{
    public function print($id)
    {
        $exam = DB::table('exams')->where('id', $id)->first();
        
        if (!$exam) {
            return "Exam not found";
        }
        
        $questions = DB::table('questions')
            ->join('exam_questions', 'questions.id', '=', 'exam_questions.question_id')
            ->where('exam_questions.exam_id', $id)
            ->select('questions.*')
            ->orderBy('exam_questions.id')
            ->get();
        
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($exam->name) . '</title>
            <style>
                @page {
                    size: A4;
                    margin: 3cm 3.5cm 2.5cm 3.5cm;
                }
                
                body {
                    font-family: "Times New Roman", Georgia, serif;
                    margin: 0;
                    padding: 0;
                    line-height: 1.6;
                }
                
                .container {
                    max-width: 100%;
                    margin: 0 auto;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 50px;
                    padding-bottom: 25px;
                    border-bottom: 2px solid #333;
                }
                
                .exam-title {
                    font-size: 28px;
                    font-weight: bold;
                    margin-bottom: 12px;
                }
                
                .question {
                    margin-bottom: 45px;
                    page-break-inside: avoid;
                }
                
                .question-number {
                    font-weight: bold;
                    font-size: 18px;
                    margin-bottom: 15px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #ccc;
                }
                
                .question-text {
                    margin-bottom: 22px;
                    font-size: 16px;
                    line-height: 1.7;
                }
                
                .options {
                    margin-left: 25px;
                    margin-top: 15px;
                }
                
                .option {
                    margin: 12px 0;
                    font-size: 15px;
                }
                
                .option-letter {
                    font-weight: bold;
                    display: inline-block;
                    width: 35px;
                }
                
                .footer {
                    text-align: center;
                    margin-top: 60px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #888;
                }
                
                .action-buttons {
                    text-align: center;
                    margin-bottom: 40px;
                    position: sticky;
                    top: 20px;
                }
                
                .btn {
                    display: inline-block;
                    padding: 10px 28px;
                    margin: 0 8px;
                    background: #2c5282;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                }
                
                @media print {
                    .action-buttons {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="action-buttons">
                    <button class="btn" onclick="window.print()">📄 Save as PDF</button>
                    <button class="btn" onclick="window.close()">❌ Close</button>
                </div>
                
                <div class="header">
                    <div class="exam-title">' . htmlspecialchars($exam->name) . '</div>
                    <div>Total Questions: ' . $questions->count() . '</div>
                </div>';
        
        $counter = 1;
        foreach ($questions as $q) {
            $html .= '<div class="question">';
            $html .= '<div class="question-number">Question ' . $counter . '</div>';
            $html .= '<div class="question-text">' . nl2br(htmlspecialchars($q->question)) . '</div>';
            $html .= '<div class="options">';
            if ($q->option1) $html .= '<div class="option"><span class="option-letter">A.</span> ' . htmlspecialchars($q->option1) . '</div>';
            if ($q->option2) $html .= '<div class="option"><span class="option-letter">B.</span> ' . htmlspecialchars($q->option2) . '</div>';
            if ($q->option3) $html .= '<div class="option"><span class="option-letter">C.</span> ' . htmlspecialchars($q->option3) . '</div>';
            if ($q->option4) $html .= '<div class="option"><span class="option-letter">D.</span> ' . htmlspecialchars($q->option4) . '</div>';
            $html .= '</div></div>';
            $counter++;
        }
        
        $html .= '<div class="footer">ExamElite</div>';
        $html .= '</div></body></html>';
        
        return response($html)->header('Content-Type', 'text/html');
    }
}
