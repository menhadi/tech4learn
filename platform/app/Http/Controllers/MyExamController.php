<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MyExamController extends Controller
{
    public function checkAttempts($identifier)
    {
        // Find by slug first, then by ID
        $exam = Exam::where('slug', $identifier)->first();
        if (!$exam && is_numeric($identifier)) {
            $exam = Exam::find($identifier);
        }
        
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }
        
        $attemptCount = 0;
        if (Auth::check()) {
            $attemptCount = \App\Models\ExamResult::where('exam_id', $exam->id)
                ->where('user_id', Auth::id())
                ->count();
        }
        
        return response()->json([
            'success' => true,
            'attempts' => $attemptCount,
            'max_attempts' => $exam->attempt_count ?? 3
        ]);
    }
}
