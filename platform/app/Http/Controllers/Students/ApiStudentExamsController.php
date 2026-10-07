<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Exam;
use App\Models\ExamStat;
use App\Models\ExamResult;
use App\Models\ExamSubjectDuration;
use App\Models\ExamProctorImage;
use App\Models\Language;

class ApiStudentExamsController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantExamQuery()
    {
        return Exam::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    /**
     * Mobile App ke liye Exam Start ya Resume karega.
     * Yeh showGuideline() aur startExam() ka combined logic hai.
     */
    public function startOrResumeExam(Request $request, $id)
    {
        $student = $request->user();
        $studentId = $student->id;
        
        $exam = $this->tenantExamQuery()->with(['questions.subject', 'questions.qtype'])->findOrFail($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        $groupingService = app(\App\Services\ExamGroupingService::class);
        $groupingService->decorate($exam, $exam->questions);

        // --- 1. Guideline Checks (Attempt, Status, Pending) ---

        if ($exam->status !== 'Active') {
            return response()->json(['success' => false, 'message' => 'This exam is not active.'], 403);
        }

        // Check karein ki student ka koi *doosra* exam pending toh nahi hai
        $otherPendingExam = ExamResult::where('student_id', $studentId)
            ->whereNull('end_time')
            ->where('exam_id', '!=', $id) // Current exam ke alawa
            ->first();

        if ($otherPendingExam) {
            return response()->json([
                'success' => false, 
                'message' => 'You have another exam pending. Please finalize it first.',
                'pending_exam_id' => $otherPendingExam->exam_id
            ], 409); // 409 Conflict
        }

        // Check karo ki *current* exam resume karna hai ya naya hai
        $examResult = ExamResult::where('exam_id', $id)
            ->where('student_id', $studentId)
            ->whereNull('end_time')
            ->first();

        if (!$examResult) {
            // Yeh NAYA attempt hai. Check karo attempts bache hain ya nahi.
            if ($exam->attempt_count > 0) {
                $attemptCount = ExamResult::where('exam_id', $id)
                    ->where('student_id', $studentId)
                    ->whereNotNull('end_time')
                    ->count();
                
                if ($attemptCount >= $exam->attempt_count) {
                    return response()->json(['success' => false, 'message' => 'You have no attempts left for this exam.'], 403);
                }
            }
        }
        
        // --- 2. Start Exam Logic (Create/Find Records) ---
        
        if ($exam->questions->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'This exam has no questions.'], 404);
        }

        $totalMarks = $exam->questions->sum('marks');

        $examResult = ExamResult::firstOrCreate(
            [
                'exam_id' => $id,
                'student_id' => $studentId,
                'end_time' => null // Sirf pending exam ko dhoondo
            ],
            [
                'start_time' => Carbon::now(),
                'attempt_time' => Carbon::now(),
                'total_test_time' => $exam->duration,
                'total_question' => $exam->questions->count(),
                'total_marks' => $totalMarks,
                'organization_id' => $exam->organization_id,
            ]
        );

        // --- 3. Timer Logic ---
        $remainingTime = 0;
        if ($exam->duration && $exam->duration > 0) {
            $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
            $currentTime = Carbon::now()->setTimezone(config('app.timezone'));
            $durationInSeconds = $exam->duration * 60;
            $elapsedTime = $startTime->diffInSeconds($currentTime, false);

            if ($elapsedTime < 0) $elapsedTime = 0;

            if ($elapsedTime >= $durationInSeconds) {
                // Time khatam ho gaya hai, force finish
                $this->finalizeExam($examResult, $exam);
                return response()->json(['success' => false, 'message' => 'Your time for this exam has expired.', 'exam_finished' => true], 410); // 410 Gone
            }
            $remainingTime = $durationInSeconds - $elapsedTime;
        }

        // --- 4. Question & Stats Logic ---
        if ($exam->random_question) {
            $exam->questions = $exam->questions->groupBy('exam_group_key')->map->shuffle()->flatten();
        }
        
        $defaultDuration = $remainingTime > 0 ? $remainingTime : ((int) $exam->duration * 60);
        $subjectDurations = $groupingService->durationMap($exam, $exam->questions, $defaultDuration);
        $defaultPerSubjectDuration = count($subjectDurations) > 0
            ? (int) floor($defaultDuration / count($subjectDurations))
            : $defaultDuration;

        $examStats = ExamStat::where('exam_result_id', $examResult->id)
            ->where('student_id', $studentId)
            ->get()
            ->keyBy('question_id');

        foreach ($exam->questions as $index => $question) {
            $correctAnswer = app(\App\Services\QuestionAnswerEvaluator::class)->correctAnswerSnapshot($question);

            $statSubjectTime = $defaultDuration;
            if ($groupingService->mode($exam) !== \App\Services\ExamGroupingService::NONE) {
                $statSubjectTime = $subjectDurations[$question->exam_group_key] ?? $defaultPerSubjectDuration;
            }

            ExamStat::firstOrCreate(
                [ 'exam_result_id' => $examResult->id, 'exam_id' => $id, 'student_id' => $studentId, 'question_id' => $question->id ],
                [ 'organization_id' => $exam->organization_id, 'subject_id' => $question->subject_id, 'exam_section_id' => $question->exam_section_id, 'subject_time' => $statSubjectTime, 'is_section' => $groupingService->groupingMode($exam) === \App\Services\ExamGroupingService::SECTION, 'ques_no' => $index + 1, 'correct_answer' => $correctAnswer, 'marks' => (float) ($question->marks ?? 0), 'negative_marks' => (float) ($question->negative_marks ?? 0), 'opened' => $index === 0, 'attempt_time' => $index === 0 ? Carbon::now() : null ]
            );
        }

        // Refresh $examStats to include newly created ones
        $examStats = ExamStat::where('exam_result_id', $examResult->id)->where('student_id', $studentId)->get()->keyBy('question_id');

        // Prefill answers for resume
        $exam->questions->each(function ($question) use ($examStats, $exam) {
            $evaluator = app(\App\Services\QuestionAnswerEvaluator::class);
            $question->question_type = $evaluator->questionType($question);
            if ($question->question_type === 'fill_blank') {
                $question->fill_blank_count = $evaluator->fillBlankCount($question);
            }

            $question->answer_locked = false;

            if (isset($examStats[$question->id])) {
                $examStat = $examStats[$question->id];
                if ($question->question_type === 'true_false') {
                    $question->prefilled_answer = $examStat->true_false;
                } elseif (in_array($question->question_type, ['multiple_choice_checkbox', 'multiple_choice_radio'], true)) {
                    $question->prefilled_answer = app(\App\Services\QuestionAnswerEvaluator::class)->selectedOptionIndices($question, $examStat);
                } elseif ($question->question_type === 'fill_blank') {
                    $decoded = json_decode((string) $examStat->answer, true);
                    $question->prefilled_answer = is_array($decoded) ? $decoded : [$examStat->answer];
                } else {
                    $question->prefilled_answer = $examStat->answer;
                }
                $question->answer_locked = ! (bool) ($exam->allow_answer_change ?? true) && $examStat->answer_locked_at !== null;
            }

            unset(
                $question->correct_option_indices,
                $question->si_answer1, $question->answer, $question->true_false, $question->fill_blank, $question->fill_blank_config, $question->nat_config, $question->explanation
            );
        });

        $languages = Language::enabledForOrganization($exam->organization_id)->orderBy('name')->get();

        // --- 5. Return JSON Response ---
        return response()->json([
            'success' => true,
            'exam' => $exam,
            'remainingTime' => $remainingTime,
            'examResult' => $examResult,
            'examStats' => $examStats, // App ko pata chal jayega ki kaunse answered hain
            'subjectDurations' => $subjectDurations,
            'languages' => $languages
        ]);
    }

    /**
     * Save Answer (Copied from StudentExamsController)
     * Yeh pehle se hi API-ready hai.
     */
    public function saveAnswer(Request $request)
    {
        $examStat = ExamStat::where('exam_result_id', $request->exam_result_id)
            ->where('student_id', $request->user()->id)
            ->where('question_id', $request->question_id)
            ->first();

        if (! $examStat) {
            return response()->json(['success' => false, 'message' => 'Answer record not found.'], 404);
        }

        $result = app(\App\Services\ExamAnswerPersistenceService::class)->save($examStat, $request->all());

        return response()->json($result, $result['success'] ? 200 : 409);
    }
    /**
     * Submit/Finish Exam (Copied from StudentExamsController)
     * Bas Auth logic change kiya hai.
     */
    public function submitExam(Request $request)
    {
        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('student_id', $request->user()->id) // <-- AUTH LOGIC CHANGE
            ->firstOrFail();

        $exam = $this->tenantExamQuery()->findOrFail($examResult->exam_id);

        // Internal function call
        $resultData = $this->finalizeExam($examResult, $exam);

        return response()->json([
            'success' => true,
            'message' => 'Exam submitted successfully.',
            'result' => $resultData
        ]);
    }

    /**
     * Save Proctor Image (Copied from StudentExamsController)
     * Yeh pehle se hi API-ready hai.
     */
    public function saveProctorImage(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|integer',
            'image' => 'required|image'
        ]);

        $studentId = $request->user()->id; // <-- AUTH LOGIC CHANGE

        $path = $request->file('image')->store('proctor_images', 'public');

        $exam = $this->tenantExamQuery()->findOrFail($request->exam_id);

        ExamProctorImage::create([
            'exam_id' => $request->exam_id,
            'organization_id' => $exam->organization_id,
            'student_id' => $studentId,
            'image_path' => $path
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update Tolerance Count (Copied from StudentExamsController)
     * Bas Auth logic change kiya hai.
     */
    public function updateToleranceCount(Request $request)
    {
        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('student_id', $request->user()->id) // <-- AUTH LOGIC CHANGE
            ->first();

        if ($examResult) {
            $examResult->update(['tolerance_count' => $request->tolerance_count]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Exam result not found.'], 404);
    }

    /**
     * PRIVATE HELPER: Exam ko calculate aur finalize karne ka logic.
     * Yeh logic 'finishExam' se copy kiya gaya hai.
     */
    private function finalizeExam(ExamResult $examResult, Exam $exam)
    {
        $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
        $endTime = Carbon::now()->setTimezone(config('app.timezone'));
        $testTime = $startTime->diffInSeconds($endTime, false);

        if ($testTime < 0) $testTime = 0;

        $examStats = ExamStat::with(['question.qtype'])->where('exam_result_id', $examResult->id)->get();
        $obtainedMarks = 0;
        
        foreach($examStats as $stat) {
            $isCorrect = false;
            $marksObtained = 0;

            $marksToAll = $stat->question && app(\App\Services\QuestionAnswerEvaluator::class)->awardsMarksToAll($stat->question);

            if (($stat->answered || $marksToAll) && $stat->question) {
                $isCorrect = app(\App\Services\QuestionAnswerEvaluator::class)->isCorrect($stat->question, $stat);
            }

            if ($isCorrect) $marksObtained = $stat->marks;
            elseif ($stat->answered && ! $marksToAll && $stat->negative_marks) $marksObtained = -1 * $stat->negative_marks;

            $stat->update(['ques_status' => $isCorrect ? 'R' : 'W', 'marks_obtained' => $marksObtained]);
            $obtainedMarks += $marksObtained;
        }

        $totalAnswered = $examStats->where('answered', true)->count();
        $passingMarks = $examResult->total_marks * ($exam->passing_percentage / 100);
        $result = $obtainedMarks >= $passingMarks ? 'Pass' : 'Fail';
        $percent = $examResult->total_marks > 0 ? ($obtainedMarks / $examResult->total_marks) * 100 : 0;

        $examResult->update([
            'end_time' => $endTime,
            'test_time' => $testTime,
            'obtained_marks' => $obtainedMarks,
            'result' => $result,
            'percent' => $percent,
            'finalized_time' => $endTime,
            'total_answered' => $totalAnswered,
        ]);

        return [
            'exam_result_id' => $examResult->id,
            'result' => $result,
            'percent' => $percent,
            'obtained_marks' => $obtainedMarks,
            'total_marks' => $examResult->total_marks,
            'result_after_finish' => $exam->result_after_finish
        ];
    }
}
