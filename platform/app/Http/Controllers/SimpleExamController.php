<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;

class SimpleExamController extends Controller
{
    public function startById($id)
    {
        $exam = Exam::findOrFail($id);
        return $this->showExam($exam);
    }
    
    public function startBySlug($slug)
    {
        $exam = Exam::where('slug', $slug)->firstOrFail();
        return $this->showExam($exam);
    }
    
    private function showExam($exam)
    {
        // Create a simple guest session
        if (!session()->has('guest_id')) {
            session(['guest_id' => 'guest_' . uniqid()]);
        }
        
        // Simple HTML output - no complex views needed
        return "<!DOCTYPE html>
        <html>
        <head>
            <title>" . htmlspecialchars($exam->name) . " - Exam</title>
            <style>
                body { font-family: Arial; padding: 20px; }
                .container { max-width: 800px; margin: auto; }
                .header { background: #0d9488; color: white; padding: 20px; border-radius: 5px; }
                .content { padding: 20px; border: 1px solid #ddd; margin-top: 20px; border-radius: 5px; }
                .btn { background: #0d9488; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . htmlspecialchars($exam->name) . "</h1>
                    <p>Duration: " . $exam->duration . " minutes | Questions: " . $exam->questions->count() . "</p>
                </div>
                <div class='content'>
                    <h2>Exam Guidelines</h2>
                    <ul>
                        <li>Read each question carefully</li>
                        <li>Select the best answer option</li>
                        <li>You can navigate between questions</li>
                        <li>Submit only when you're finished</li>
                    </ul>
                    <p><strong>Guest ID:</strong> " . session('guest_id') . "</p>
                    <p><strong>This page works on refresh because it's plain HTML!</strong></p>
                    <a href='/' class='btn'>Back to Home</a>
                </div>
            </div>
        </body>
        </html>";
    }
}
