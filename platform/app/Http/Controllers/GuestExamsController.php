<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;

class GuestExamsController extends Controller
{
    // Show exam instructions - works with slug
    public function showInstructions($slug)
    {
        // Find by slug
        $exam = Exam::where('slug', $slug)->first();
        
        if (!$exam) {
            abort(404, 'Exam not found');
        }
        
        // Store exam in session for guest tracking
        session()->put('current_exam_id', $exam->id);
        
        // Check if view exists
        if (view()->exists('guest.exam.instructions')) {
            return view('guest.exam.instructions', compact('exam'));
        }
        
        // Fallback view if the guest view doesn't exist
        return view('exam.instructions', compact('exam'));
    }
    
    // Start exam - works with slug
    public function startExam($slug)
    {
        // Find by slug
        $exam = Exam::where('slug', $slug)->first();
        
        if (!$exam) {
            abort(404, 'Exam not found');
        }
        
        // Get questions for this exam
        $questions = $exam->questions()->paginate(1);
        
        // Check if view exists
        if (view()->exists('guest.exam.start')) {
            return view('guest.exam.start', compact('exam', 'questions'));
        }
        
        // Fallback view
        return view('exam.start', compact('exam', 'questions'));
    }
    
    // Show exam guideline - works with slug
    public function showGuideline($slug)
    {
        // Find by slug
        $exam = Exam::where('slug', $slug)->first();
        
        if (!$exam) {
            abort(404, 'Exam not found');
        }
        
        // Check if view exists
        if (view()->exists('guest.exam.guideline')) {
            return view('guest.exam.guideline', compact('exam'));
        }
        
        // Fallback to instructions if guideline doesn't exist
        return redirect()->to("/guest/exam/instructions/{$slug}");
    }
    
    // Finish exam - keep as is
    public function finishExam(Request $request)
    {
        // Your existing finish exam logic
        return redirect()->route('guest.examFeedback');
    }
    
    // Exam feedback - keep as is
    public function examFeedback()
    {
        if (view()->exists('guest.exam.feedback')) {
            return view('guest.exam.feedback');
        }
        return view('exam.feedback');
    }
    
    // Submit feedback - keep as is
    public function submitExamFeedback(Request $request)
    {
        return redirect()->back()->with('success', 'Feedback submitted');
    }
}
