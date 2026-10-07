<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaveSubjectiveAnswerController extends Controller
{
    public function save(Request $request)
    {
        try {
            $request->validate([
                'answer_text' => 'required|string',
                'question_id' => 'required|exists:questions,id',
                'exam_result_id' => 'required|exists:exam_results,id',
            ]);
            
            $questionId = $request->input('question_id');
            $examResultId = $request->input('exam_result_id');
            $answerText = $request->input('answer_text');
            
            $stat = \App\Models\ExamStats::where('exam_result_id', $examResultId)
                ->where('question_id', $questionId)
                ->first();
            
            if ($stat) {
                $stat->answer = $answerText;
                $stat->save();
                Log::info('Answer saved for question ' . $questionId);
                return response()->json(['success' => true, 'message' => 'Answer saved']);
            } else {
                return response()->json(['success' => false, 'message' => 'Exam stat not found']);
            }
            
        } catch (\Exception $e) {
            Log::error('Save answer error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
