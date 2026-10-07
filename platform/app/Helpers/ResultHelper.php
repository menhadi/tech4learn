<?php

namespace App\Helpers;

use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\ExamResultDetail;

class ResultHelper
{
    public static function computeResultDetails($result, $id)
    {
        // 1. Total unique students count
        $totalStudents = ExamResult::where('exam_id', $result->exam_id)
            ->when($result->organization_id, fn ($query) => $query->where('organization_id', $result->organization_id))
            ->distinct('student_id')
            ->count('student_id');

        // 2. Statistics Calculation from ExamStat table
        $examStats = ExamStat::selectRaw('
            SUM(CASE WHEN answered = 1 AND ques_status = "R" THEN 1 ELSE 0 END) as correctQuestions,
            SUM(CASE WHEN answered = 1 AND ques_status = "W" THEN 1 ELSE 0 END) as incorrectQuestions,
            SUM(CASE WHEN answered = 1 AND ques_status = "R" THEN marks_obtained ELSE 0 END) as rightMarks
        ')
            ->where('exam_result_id', $id)
            ->first();

        $correctQuestions = $examStats->correctQuestions ?? 0;
        $incorrectQuestions = $examStats->incorrectQuestions ?? 0;
        $rightMarks = $examStats->rightMarks ?? 0;
        $leftQuestions = $result->total_question - $result->total_answered;
        $leftQuestionMarks = 0; // Keeping 0 as per previous fix

        // ===================================================================
        // ✅✅✅ IMPROVED RANKING LOGIC (Score + Time Based) ✅✅✅
        // ===================================================================
        
        // Query: Pehle Percent (High to Low), fir Time (Low to High)
        $studentBestScores = ExamResult::where('exam_id', $result->exam_id)
            ->when($result->organization_id, fn ($query) => $query->where('organization_id', $result->organization_id))
            ->selectRaw('student_id, MAX(percent) as highest_percent, MIN(total_test_time) as min_time')
            ->groupBy('student_id')
            ->orderBy('highest_percent', 'desc') // 1. Score Zyaada
            ->orderBy('min_time', 'asc')         // 2. Time Kam (Tie-Breaker)
            ->get();

        $rank = 0;
        $previous_percent = null;
        $previous_time = null;
        $current_rank = 0;

        foreach ($studentBestScores as $score) {
            $current_rank++; 
            
            // Rank tabhi change hoga jab Score ya Time different ho
            // Agar koi student same marks same time par hai, to same rank milegi
            if ($previous_percent != $score['highest_percent'] || $previous_time != $score['min_time']) {
                $rank = $current_rank;
            }
            
            if ($score['student_id'] == $result->student_id) {
                break; 
            }
            
            $previous_percent = $score['highest_percent'];
            $previous_time = $score['min_time'];
        }
        // ===================================================================

        $negativeMarks = 0; 

        // Time Formatting
        $formattedTotalTestTime = self::formatTime($result->total_test_time);
        $formattedTestTime = self::formatTime($result->test_time);

        // Subject-wise Report Logic
        $subjectReports = ExamStat::selectRaw('
            subject_id,
            COUNT(*) as total_questions,
            SUM(CASE WHEN answered = 1 AND ques_status = "R" THEN 1 ELSE 0 END) as correct_questions,
            SUM(CASE WHEN answered = 1 AND ques_status = "W" THEN 1 ELSE 0 END) as incorrect_questions,
            SUM(CASE WHEN answered = 1 AND ques_status = "R" THEN marks_obtained ELSE 0 END) as marks_scored,
            SUM(CASE WHEN answered = 1 AND ques_status = "W" THEN marks_obtained ELSE 0 END) as negative_marks,
            COUNT(*) - SUM(answered) as unattempted_questions,
            (COUNT(*) - SUM(answered)) * MAX(marks) as unattempted_marks,
            SUM(time_taken) as total_time_taken
        ')
            ->where('exam_result_id', $id)
            ->groupBy('subject_id')
            ->get()
            ->map(function ($report) {
                $report->formatted_time_taken = self::formatTime($report->total_time_taken);
                return $report->only([
                    'subject_id',
                    'total_questions',
                    'correct_questions',
                    'incorrect_questions',
                    'marks_scored',
                    'negative_marks',
                    'unattempted_questions',
                    'unattempted_marks',
                    'formatted_time_taken',
                    'total_time_taken'
                ]);
            })
            ->toArray();

        // Question-wise Report Logic
        $questionReports = ExamStat::where('exam_result_id', $id)
            ->with(['question.diff', 'question.qtype'])
            ->get()
            ->map(function ($report) {
                $report->formatted_time_taken = self::formatTime($report->time_taken);
                return $report;
            })
            ->toArray();

        // Save computed details
        return ExamResultDetail::create([
            'organization_id' => $result->organization_id,
            'exam_id' => $result->exam_id,
            'exam_result_id' => $id,
            'total_students' => $totalStudents,
            'correct_questions' => $correctQuestions,
            'incorrect_questions' => $incorrectQuestions,
            'right_marks' => $rightMarks,
            'left_questions' => $leftQuestions,
            'left_question_marks' => $leftQuestionMarks,
            'rank' => $rank,
            'negative_marks' => $negativeMarks,
            'formatted_total_test_time' => $formattedTotalTestTime,
            'formatted_test_time' => $formattedTestTime,
            'subject_reports' => json_encode($subjectReports),
            'question_reports' => json_encode($questionReports),
        ]);
    }

    public static function formatTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
