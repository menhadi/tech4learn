<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Str;

class SlugExamController extends Controller
{
    // Show guidelines page
    public function guideline($slug)
    {
        $exam = Exam::where('slug', $slug)->firstOrFail();
        
        return view('slug-exam.guideline', compact('exam'));
    }
    
    // Show instructions page
    public function instructions($slug)
    {
        $exam = Exam::where('slug', $slug)->firstOrFail();
        
        return view('slug-exam.instructions', compact('exam'));
    }
    
    // Start exam
    public function start($slug)
    {
        $exam = Exam::where('slug', $slug)->firstOrFail();
        
        // Create guest session
        if (!session()->has('guest_id')) {
            session(['guest_id' => 'guest_' . Str::random(10)]);
        }
        
        $guestId = session('guest_id');
        
        // Create exam result
        $examResult = ExamResult::firstOrCreate(
            [
                'exam_id' => $exam->id,
                'guest_id' => $guestId,
                'end_time' => null,
                'organization_id' => $exam->organization_id
            ],
            [
                'start_time' => now(),
                'total_question' => $exam->questions->count(),
                'total_marks' => $exam->questions->sum('marks')
            ]
        );
        
        return view('slug-exam.start', compact('exam', 'examResult'));
    }
}
