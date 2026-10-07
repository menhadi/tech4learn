<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\Exam;
use App\Models\ExamStat;
use App\Models\ExamResult;
use App\Models\ExamFeedback;
use App\Models\ExamSubjectDuration;
use App\Models\ExamProctorImage;
use App\Models\QuestionsReport;
use App\Models\Language;
use App\Models\EmailTemplate;
use App\Models\Configuration;
use App\Services\EmailService;
use App\Services\StudentActivityTracker;

class StudentExamsController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::hostId(request()->getHost()) : null;
    }

    private function tenantExamQuery()
    {
        return Exam::query()
            ->active()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            });
    }

    private function ensureStudentCanAccessExam(Exam $exam): void
    {
        if ($exam->is_student_practice && (int) $exam->created_by_student_id !== (int) Auth::guard('student')->id()) {
            abort(404);
        }
    }

    public function showGuideline(Request $request, $id)
    {
        return redirect()->route('student.instructions', ['id' => $id]);
    }

    public function showInstructions($id)
    {
        $exam = $this->tenantExamQuery()->findOrFail($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        $this->ensureStudentCanAccessExam($exam);
        StudentActivityTracker::track(StudentActivityTracker::EXAM_INSTRUCTIONS_OPENED, [
            'organization_id' => $exam->organization_id,
            'student_id' => Auth::guard('student')->id(),
            'exam_id' => $exam->id,
            'metadata' => [
                'exam_slug' => $exam->slug,
            ],
        ]);

        return view('students.exams.exam_instructions', compact('exam'));
    }

    public function translateLanguage(Request $request, $id, $languageId)
    {
        $exam = $this->tenantExamQuery()->findOrFail($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        $this->ensureStudentCanAccessExam($exam);
        $language = app(\App\Services\ExamLanguageService::class)->available($exam)
            ->firstWhere('id', (int) $languageId);
        abort_unless($language, 404);

        $pivot = $exam->languages()->whereKey($language->id)->first()?->pivot;
        $english = app(\App\Services\ExamLanguageService::class)->isEnglish($language);

        return response()->json([
            'status' => $english || ($pivot?->translation_status === 'ready' && $pivot?->translation_approved_at) ? 'ready' : 'pending',
            'approved' => $english || (bool) $pivot?->translation_approved_at,
        ]);
    }
    public function startExam(Request $request, $id)
    {
        $exam = $this->tenantExamQuery()->with(['questions.subject', 'questions.qtype', 'questions.langs'])->findOrFail($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        abort_unless($exam->isAvailableAt(), 403, 'This exam is outside its scheduled availability.');
        $languageService = app(\App\Services\ExamLanguageService::class);
        $selectedLanguage = $languageService->resolve($exam, $request->query('lang'));
        $selectedLanguageId = $selectedLanguage?->id;
        $languagePivot = $selectedLanguage
            ? $exam->languages()->whereKey($selectedLanguage->id)->first()?->pivot
            : null;
        if ($selectedLanguage && ! $languageService->isEnglish($selectedLanguage)
            && (! $languagePivot || $languagePivot->translation_status !== 'ready' || ! $languagePivot->translation_approved_at)) {
            return redirect()->route('student.instructions', ['id' => $id, 'lang' => $selectedLanguage->id])
                ->with('error', 'Please wait for the selected language translation to finish.');
        }
        if ($selectedLanguage) {
            $languageService->rememberPreference($selectedLanguage);
        }
        $groupingService = app(\App\Services\ExamGroupingService::class);
        $groupingService->decorate($exam, $exam->questions);
        $this->ensureStudentCanAccessExam($exam);

        if ($exam->questions->isEmpty()) {
            return redirect()->back()->with('error', __('messages.exam_no_questions'));
        }
        
        $studentId = Auth::guard('student')->id();
        $totalMarks = $exam->questions->sum('marks');

        $examResult = ExamResult::firstOrCreate(
            [
                'exam_id' => $id,
                'student_id' => $studentId,
                'end_time' => null 
            ],
            [
                'start_time' => Carbon::now(),
                'attempt_time' => Carbon::now(),
                'total_test_time' => $exam->duration,
                'total_question' => $exam->questions->count(),
                'total_marks' => $totalMarks,
                'organization_id' => $exam->organization_id,
                'language_id' => $selectedLanguageId,
            ]
        );

        if ((int) $examResult->language_id !== (int) $selectedLanguageId) {
            $examResult->forceFill(['language_id' => $selectedLanguageId])->save();
        }

        StudentActivityTracker::track(StudentActivityTracker::EXAM_STARTED, [
            'organization_id' => $exam->organization_id,
            'student_id' => $studentId,
            'exam_id' => $exam->id,
            'exam_result_id' => $examResult->id,
            'metadata' => [
                'resumed' => ! $examResult->wasRecentlyCreated,
            ],
        ], $request);

        // --- TIME CALCULATION ---
        $remainingTime = 0;
        if ($exam->duration && $exam->duration > 0) {
            $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
            $currentTime = Carbon::now()->setTimezone(config('app.timezone'));
            $durationInSeconds = $exam->duration * 60;
            $elapsedTime = $startTime->diffInSeconds($currentTime, false);
            if ($elapsedTime < 0) $elapsedTime = 0;
            $remainingTime = $durationInSeconds - $elapsedTime;

            if ($remainingTime <= 0) {
                $request->merge(['exam_result_id' => $examResult->id, 'fromTimeout' => true]);
                return $this->finishExam($request);
            }
        }

        if ($exam->end_date) {
            $examEndTime = Carbon::parse($exam->end_date)->setTimezone(config('app.timezone'));
            $now = Carbon::now()->setTimezone(config('app.timezone'));
            $secondsUntilClose = $now->diffInSeconds($examEndTime, false);

            if ($secondsUntilClose <= 0) {
                 $request->merge(['exam_result_id' => $examResult->id, 'fromTimeout' => true]);
                 return $this->finishExam($request);
            }
            if ($remainingTime == 0 || $remainingTime > $secondsUntilClose) {
                $remainingTime = $secondsUntilClose;
            }
        }

        // --- STATS GENERATION ---
        $existingStats = ExamStat::where('exam_result_id', $examResult->id)
            ->where('student_id', $studentId)
            ->with(['question.subject', 'question.qtype'])
            ->orderBy('ques_no', 'asc')
            ->get();

        // Recover a failed first load that inserted only part of the question set.
        // Never rebuild once a student/guest has answered anything.
        if ($existingStats->isNotEmpty()
            && $existingStats->where('answered', true)->isEmpty()
            && $exam->questions->pluck('id')->diff($existingStats->pluck('question_id'))->isNotEmpty()) {
            ExamStat::where('exam_result_id', $examResult->id)
                ->where('student_id', $studentId)
                ->delete();
            $existingStats = collect();
        }
        if ($existingStats->isNotEmpty()) {
            $orderedQuestions = collect();
            // âœ… CRITICAL FIX: keyBy('question_id') ensures status mapping works
            $statsMap = $existingStats->keyBy('question_id');
            
            foreach ($existingStats as $stat) {
                if ($stat->question) {
                    $orderedQuestions->push($stat->question);
                }
            }
            $exam->setRelation('questions', $orderedQuestions);
            $examStats = $statsMap;
        } else {
            // New Attempt Logic
            if ($exam->random_question) {
                $exam->questions = $exam->questions->groupBy('exam_group_key')->map(function ($qs) {
                    return $qs->shuffle();
                })->flatten();
            }

            $defaultDuration = $remainingTime > 0 ? $remainingTime : ((int) $exam->duration * 60);
            $subjectDurations = $groupingService->durationMap($exam, $exam->questions, $defaultDuration);
            $defaultPerSubjectDuration = count($subjectDurations) > 0
                ? (int) floor($defaultDuration / count($subjectDurations))
                : $defaultDuration;

            $quesNoCounter = 1;
            $newStats = collect();

            \DB::beginTransaction();
            try {
            foreach ($exam->questions as $index => $question) {
                $correctAnswer = app(\App\Services\QuestionAnswerEvaluator::class)->correctAnswerSnapshot($question);

                $statSubjectTime = $defaultDuration;
                if ($groupingService->mode($exam) !== \App\Services\ExamGroupingService::NONE) {
                    $statSubjectTime = $subjectDurations[$question->exam_group_key] ?? $defaultPerSubjectDuration;
                }

                $stat = ExamStat::create([
                    'organization_id' => $exam->organization_id,
                    'exam_result_id' => $examResult->id,
                    'exam_id' => $id,
                    'student_id' => $studentId,
                    'question_id' => $question->id,
                    'subject_id' => $question->subject_id,
                    'exam_section_id' => $question->exam_section_id,
                    'subject_time' => $statSubjectTime,
                    'is_section' => $groupingService->groupingMode($exam) === \App\Services\ExamGroupingService::SECTION,
                    'ques_no' => $quesNoCounter++,
                    'correct_answer' => $correctAnswer,
                    'marks' => (float) ($question->marks ?? 0),
                    'negative_marks' => (float) ($question->negative_marks ?? 0),
                    'opened' => $index === 0,
                    'attempt_time' => $index === 0 ? Carbon::now() : null,
                    'bookmark' => false,
                ]);
                // âœ… Key by question_id for new stats as well
                $newStats->put($question->id, $stat);
            }
            \DB::commit();
            } catch (\Throwable $exception) {
                \DB::rollBack();
                throw $exception;
            }
            $examStats = $newStats;
        }

        // --- PREPARE VIEW ---
        $groupingService->decorate($exam, $exam->questions);
        $defaultDuration = $remainingTime > 0 ? $remainingTime : ((int) $exam->duration * 60);
        $subjectDurations = $groupingService->durationMap($exam, $exam->questions, $defaultDuration);

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
                $question->prefilled_bookmark = $examStat->bookmark;
                $question->answer_locked = ! (bool) ($exam->allow_answer_change ?? true) && $examStat->answer_locked_at !== null;
            } else {
                $question->prefilled_bookmark = false;
            }

            unset(
                $question->correct_option_indices,
                $question->si_answer1, $question->answer, $question->true_false, $question->fill_blank, $question->fill_blank_config, $question->nat_config, $question->explanation
            );
        });

        $languages = $languageService->available($exam);
        $configuration = getConfiguration();

        return view('students.exams.exam_start', compact('exam', 'remainingTime', 'examResult', 'examStats', 'subjectDurations', 'configuration', 'languages', 'selectedLanguageId'));
    }

    public function examFeedback(Request $request)
    {
        $examResultId = $request->input('exam_result_id');
        $resultAfterFinish = $request->input(urldecode('result_after_finish'));
        return view('students.exams.exam_feedback', compact('examResultId', 'resultAfterFinish'));
    }

    public function saveAnswer(Request $request)
    {
        $examStat = ExamStat::where('exam_result_id', $request->exam_result_id)
            ->where('student_id', Auth::guard('student')->id())
            ->where('question_id', $request->question_id)
            ->first();

        if (! $examStat) {
            return response()->json(['success' => false, 'message' => 'Answer record not found.'], 404);
        }

        $result = app(\App\Services\ExamAnswerPersistenceService::class)->save($examStat, $request->all());

        return response()->json($result, $result['success'] ? 200 : 409);
    }
    public function submitExamFeedback(Request $request)
    {
        $request->validate([
            'exam_result_id' => 'required|integer',
            'test_instructions' => 'required|string',
            'language_of_questions' => 'required|string',
            'text_experience' => 'required|string',
            'feedback' => 'nullable|string',
        ]);

        $feedbackExamResult = ExamResult::query()
            ->where('student_id', Auth::guard('student')->id())
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->findOrFail($request->exam_result_id);

        ExamFeedback::create([
            'exam_result_id' => $request->exam_result_id,
            'organization_id' => $feedbackExamResult->organization_id,
            'student_id' => Auth::guard('student')->id(),
            'test_instructions' => $request->test_instructions,
            'language_of_questions' => $request->language_of_questions,
            'text_experience' => $request->text_experience,
            'feedback' => $request->feedback ?? '',
        ]);

        return redirect()->back()->with('success', 'Feedback submitted successfully.');
    }

    public function finishExam(Request $request)
    {
        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('student_id', Auth::guard('student')->id())
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->firstOrFail();

        if (!$examResult) {
            return response()->json(['success' => false, 'message' => 'Exam result not found.'], 404);
        }

        $exam = $this->tenantExamQuery()->findOrFail($examResult->exam_id);

        $finalization = DB::transaction(function () use ($examResult, $exam, $request) {
            $examResult = ExamResult::whereKey($examResult->id)->lockForUpdate()->firstOrFail();
            if ($examResult->end_time !== null) {
                return null;
            }
            $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
            $endTime = Carbon::now()->setTimezone(config('app.timezone'));

            if($endTime->lt($startTime)) {
                $endTime = $startTime;
            }

            $testTime = $startTime->diffInSeconds($endTime, false);

            // âœ… FIX FOR TIME ISSUE: Cap time at Max Duration (Handles Network Latency)
            if ($exam->duration > 0) {
                $maxDurationInSeconds = $exam->duration * 60;
                // Agar calculated time Duration se zyada hai, to Duration hi maano
                if ($testTime > $maxDurationInSeconds) {
                    $testTime = $maxDurationInSeconds;
                }
            }

            $examStats = ExamStat::with(['question.qtype'])->where('exam_result_id', $examResult->id)->lockForUpdate()->get();

            $obtainedMarks = 0;
            $hasManualGrading = false;

            foreach($examStats as $stat) {
                if (! $stat->question) {
                    Log::warning('Exam finalization skipped an orphaned answer record.', [
                        'exam_result_id' => $examResult->id,
                        'exam_stat_id' => $stat->id,
                        'question_id' => $stat->question_id,
                    ]);
                    $stat->update(['ques_status' => 'P', 'marks_obtained' => 0]);
                    $hasManualGrading = true;
                    continue;
                }

                // Check for Subjective Questions
                $isSubjective = false;
                $qTypeChar = 'M';

                if ($stat->question && $stat->question->qtype) {
                    $qTypeChar = $stat->question->qtype->type;
                    $typeName = strtolower($stat->question->qtype->question_type ?? '');

                    if (str_contains($typeName, 'text') || str_contains($typeName, 'subjective') || str_contains($typeName, 'descriptive') || $qTypeChar == 'S') {
                        $isSubjective = true;
                    }
                } else {
                    // If NO options and NO true/false, assume subjective
                    if ($stat->question->correctOptionIndices() === [] && !$stat->question->true_false && !$stat->question->fill_blank) {
                        $isSubjective = true;
                    }
                }

                $marksToAll = $stat->question && app(\App\Services\QuestionAnswerEvaluator::class)->awardsMarksToAll($stat->question);

                if ($isSubjective && ! $marksToAll) {
                    $stat->update(['ques_status' => 'P', 'marks_obtained' => 0]);
                    $hasManualGrading = true;
                    continue;
                }

                // Grading Logic
                $isCorrect = false;
                $marksObtained = 0;
                $finalStatus = 'S';

                if (($stat->answered || $marksToAll) && $stat->question) {
                    $isCorrect = app(\App\Services\QuestionAnswerEvaluator::class)->isCorrect($stat->question, $stat);

                    // 4. Assign Marks
                    if ($isCorrect) {
                        $marksObtained = (float) $stat->marks;
                        $finalStatus = 'R';
                    } else {
                        if ($exam->negative_marking && $stat->negative_marks > 0) {
                            $marksObtained = -1 * (float) $stat->negative_marks;
                        }
                        $finalStatus = 'W';
                    }
                } else {
                    $finalStatus = 'S';
                    $marksObtained = 0;
                }

                $stat->update([
                    'ques_status' => $finalStatus,
                    'marks_obtained' => $marksObtained,
                    'correct_answer' => $stat->correct_answer
                ]);

                $obtainedMarks += $marksObtained;
            }

            $totalAnswered = $examStats->where('answered', true)->count();

            // Result Status
            if ($hasManualGrading) {
                $result = 'Pending';
                $percent = 0;
            } else {
                $passingMarks = $examResult->total_marks * ($exam->passing_percentage / 100);
                $result = $obtainedMarks >= $passingMarks ? 'Pass' : 'Fail';
                $percent = $examResult->total_marks > 0 ? ($obtainedMarks / $examResult->total_marks) * 100 : 0;
            }

            $examResult->update([
                'end_time' => $endTime,
                'test_time' => $testTime,
                'obtained_marks' => $obtainedMarks,
                'result' => $result,
                'percent' => $percent,
                'finalized_time' => $endTime,
                'total_answered' => $totalAnswered,
            ]);

            return [$examResult, $result, $percent, $totalAnswered, $hasManualGrading];
        });
        if ($finalization === null) {
            if ($request->fromTimeout) {
                return redirect()->route('student.examFeedback', ['exam_result_id' => $examResult->id, 'result_after_finish' => $exam->result_after_finish]);
            }
            return response()->json(['success' => true]);
        }
        [$examResult, $result, $percent, $totalAnswered, $hasManualGrading] = $finalization;


        StudentActivityTracker::track(StudentActivityTracker::EXAM_SUBMITTED, [
            'organization_id' => $examResult->organization_id,
            'student_id' => $examResult->student_id,
            'exam_id' => $examResult->exam_id,
            'exam_result_id' => $examResult->id,
            'metadata' => [
                'result' => $result,
                'percent' => $percent,
                'total_answered' => $totalAnswered,
                'from_timeout' => (bool) $request->fromTimeout,
                'manual_grading' => $hasManualGrading,
            ],
        ], $request);

        if ($result !== 'Pending') {
            try {
                $template = EmailTemplate::where('key', 'result_published')->first();
                $config = function_exists('getConfiguration') ? getConfiguration() : Configuration::first();
                
                $student = Auth::guard('student')->user();
                if ($template && $config && $student && filled($student->email)) {
                    $replacements = [
                        '{#studentName#}' => $student->name,
                        '{#examName#}'    => $exam->name,
                        '{#result#}'      => $result, 
                        '{#percent#}'     => number_format($percent, 2),
                        '{#obtainedMarks#}' => $obtainedMarks,
                        '{#totalMarks#}'  => $examResult->total_marks,
                        '{#reportUrl#}'   => route('student.results.view', $examResult->id),
                        '{#siteName#}'    => $config->name,
                        '{#siteEmailContact#}' => $config->email,
                    ];
                    $subject = $template->subject;
                    $html = $template->description;
                    foreach ($replacements as $key => $value) {
                        $subject = str_replace($key, $value, $subject);
                        $html = str_replace($key, $value, $html);
                    }
                    (new EmailService())->sendEmail($student->email, $subject, $html, null, null, $examResult->organization_id);
                }
            } catch (\Throwable $e) {
                Log::error("Result Email Error: " . $e->getMessage());
            }
        }

        if ($request->fromTimeout) {
            return redirect()->route('student.examFeedback', ['exam_result_id' => $examResult->id, 'result_after_finish' => $exam->result_after_finish]);
        }

        return response()->json(['success' => true]);
    }

    public function saveProctorImage(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|integer',
            'image' => 'required|image'
        ]);

        $path = $request->file('image')->store('proctor_images', 'public');

        $exam = $this->tenantExamQuery()->findOrFail($request->exam_id);
        $studentId = Auth::guard('student')->id();

        ExamProctorImage::create([
            'exam_id' => $request->exam_id,
            'organization_id' => $exam->organization_id,
            'student_id' => $studentId,
            'image_path' => $path
        ]);

        return response()->json(['success' => true]);
    }

    public function updateToleranceCount(Request $request)
    {
        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('student_id', Auth::guard('student')->id())
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->firstOrFail();

        if ($examResult) {
            $examResult->update(['tolerance_count' => $request->tolerance_count]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Exam result not found.'], 404);
    }

    public function questionReport(Request $request) {
        
        $request->validate([
            'question_id' => 'required',
            'subject_id' => 'required',
            'question_type' => 'required|string|max:100',
            'message' => 'nullable|string|max:2000',
        ]);

        $question = \App\Models\Question::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->findOrFail($request->question_id);

        QuestionsReport::create([
            'organization_id' => $question->organization_id,
            'student_id' => auth('student')->id(),
            'question_id' => $question->id,
            'subject_id' => $question->subject_id,
            'question_type' => $request->question_type,
            'message' => $request->message ?: '',
        ]);

        return response()->json(['success' => true]);

    }
}
