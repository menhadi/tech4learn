<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Models\Exam;
use App\Models\Package;
use App\Models\ExamStat;
use App\Models\ExamResult;
use App\Models\ExamFeedback;
use App\Models\ExamSubjectDuration;
use App\Models\ExamProctorImage;
use App\Models\QuestionsReport;
use App\Models\Language;
use App\Models\EmailTemplate;
use App\Models\Configuration;
use App\Models\Order;
use App\Services\EmailService;
use App\Services\StudentActivityTracker;
use App\Services\BotTrafficDetector;
use App\Services\StudentLifecycleEmailService;
use App\Services\StudentPostAuthService;

class GuestExamsController extends Controller
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

    public function showGuideline(Request $request, $id)
    {
        return redirect()->route('guest.instructions', ['id' => $id]);
    }

    public function showInstructions($id)
    {
        
        if (! (getConfiguration()->allow_guest_exam_attempts ?? true)) {
            return redirect()->route('student.signin');
        }
        $exam = $this->resolveExam($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');

        if (auth('student')->check()) {
            return redirect()->route('student.instructions', ['id' => $exam->slug ?: $exam->id]);
        }

        if (!$this->validateGuestExamAccess($exam->id)) {
            Log::warning('Guest exam instruction access denied', [
                'exam_id' => $exam->id,
                'exam_slug' => $exam->slug,
                'guest_id' => $this->getGuestId(),
                'tenant_id' => $this->tenantId(),
                'host' => request()->getHost(),
            ]);

            return redirect()->route('courses.index')->with('error', __('You do not have access to this exam.'));
        }

        StudentActivityTracker::track(StudentActivityTracker::GUEST_EXAM_INSTRUCTIONS_OPENED, [
            'organization_id' => $exam->organization_id,
            'guest_id' => $this->getGuestId(),
            'exam_id' => $exam->id,
            'source' => 'guest',
            'metadata' => [
                'exam_slug' => $exam->slug,
            ],
        ]);

        return view('students.guest_exams.exam_instructions', compact('exam'));
    }

    public function translateLanguage(Request $request, $id, $languageId)
    {
        abort_unless(getConfiguration()->allow_guest_exam_attempts ?? true, 403);
        $exam = $this->resolveExam($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        abort_unless($this->validateGuestExamAccess($exam->id), 403);
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
        
        if (! (getConfiguration()->allow_guest_exam_attempts ?? true)) {
            return redirect()->route('student.signin');
        }
        $exam = $this->resolveExam($id);
        abort_unless($exam->canAttemptOnline(), 409, 'This paper is currently available as a PDF download only.');
        $id = $exam->id;

        if (auth('student')->check()) {
            return redirect()->route('student.startExam', ['id' => $exam->slug ?: $exam->id]);
        }

        if (!$this->validateGuestExamAccess($id)) {
            Log::warning('Guest exam start access denied', [
                'exam_id' => $exam->id,
                'exam_slug' => $exam->slug,
                'guest_id' => $this->getGuestId(),
                'tenant_id' => $this->tenantId(),
                'host' => request()->getHost(),
            ]);

            return redirect()->route('courses.index')->with('error', __('You do not have access to this exam.'));
        }

        $languageService = app(\App\Services\ExamLanguageService::class);
        $selectedLanguage = $languageService->resolve($exam, $request->query('lang'));
        $selectedLanguageId = $selectedLanguage?->id;
        $languagePivot = $selectedLanguage
            ? $exam->languages()->whereKey($selectedLanguage->id)->first()?->pivot
            : null;
        if ($selectedLanguage && ! $languageService->isEnglish($selectedLanguage)
            && (! $languagePivot || $languagePivot->translation_status !== 'ready' || ! $languagePivot->translation_approved_at)) {
            return redirect()->route('guest.instructions', ['id' => $exam->slug ?: $exam->id, 'lang' => $selectedLanguage->id])
                ->with('error', 'Please wait for the selected language translation to finish.');
        }
        if ($selectedLanguage) {
            $languageService->rememberPreference($selectedLanguage);
        }
        $exam->load(['questions.subject', 'questions.qtype', 'questions.langs']);
        $groupingService = app(\App\Services\ExamGroupingService::class);
        $groupingService->decorate($exam, $exam->questions);

        if ($exam->questions->isEmpty()) {
            return redirect()->back()->with('error', __('messages.exam_no_questions'));
        }
        
        $guestId = $this->getGuestId();
        $guestIdentity = $this->rememberGuestIdentity($request);
        $botTraffic = BotTrafficDetector::detect($request);
        $totalMarks = $exam->questions->sum('marks');

        $examResult = ExamResult::firstOrCreate(
            [
                'exam_id' => $id,
                'guest_id' => $guestId,
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
                'guest_name' => $guestIdentity['guest_name'],
                'guest_email' => $guestIdentity['guest_email'],
                'guest_ip_address' => $request->ip(),
                'guest_user_agent' => $request->userAgent(),
                'guest_is_bot' => $botTraffic['is_bot'],
                'guest_bot_reason' => $botTraffic['reason'],
            ]
        );

        if ((int) $examResult->language_id !== (int) $selectedLanguageId) {
            $examResult->forceFill(['language_id' => $selectedLanguageId])->save();
        }

        $this->updateGuestAttemptIdentity($examResult, $request, $guestIdentity);
        if ($examResult->guest_is_bot !== $botTraffic['is_bot'] || $examResult->guest_bot_reason !== $botTraffic['reason']) {
            $examResult->forceFill([
                'guest_is_bot' => $botTraffic['is_bot'],
                'guest_bot_reason' => $botTraffic['reason'],
            ])->save();
        }

        StudentActivityTracker::track(StudentActivityTracker::GUEST_EXAM_STARTED, [
            'organization_id' => $exam->organization_id,
            'guest_id' => $guestId,
            'exam_id' => $exam->id,
            'exam_result_id' => $examResult->id,
            'source' => 'guest',
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
            ->where('guest_id', $guestId)
            ->with(['question.subject', 'question.qtype'])
            ->orderBy('ques_no', 'asc')
            ->get();

        // Recover a failed first load that inserted only part of the question set.
        // Never rebuild once a student/guest has answered anything.
        if ($existingStats->isNotEmpty()
            && $existingStats->where('answered', true)->isEmpty()
            && $exam->questions->pluck('id')->diff($existingStats->pluck('question_id'))->isNotEmpty()) {
            ExamStat::where('exam_result_id', $examResult->id)
                ->where('guest_id', $guestId)
                ->delete();
            $existingStats = collect();
        }
        if ($existingStats->isNotEmpty()) {
            $orderedQuestions = collect();
            // ✅ CRITICAL FIX: keyBy('question_id') ensures status mapping works
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
                    'guest_id' => $guestId,
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
                // ✅ Key by question_id for new stats as well
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
                $question->answer_locked = false;
            }

            unset(
                $question->correct_option_indices,
                $question->si_answer1, $question->answer, $question->true_false, $question->fill_blank, $question->fill_blank_config, $question->nat_config, $question->explanation
            );
        });

        $languages = $languageService->available($exam);
        $configuration = getConfiguration();

        return view('students.guest_exams.exam_start', compact('exam', 'remainingTime', 'examResult', 'examStats', 'subjectDurations', 'configuration', 'languages', 'selectedLanguageId'));
    }

    public function examFeedback(Request $request)
    {
        $examResultId = $request->input('exam_result_id');
        $resultAfterFinish = $request->input(urldecode('result_after_finish'));
        $guestId = $this->getGuestId();
        $examResult = ExamResult::query()
            ->with('exam:id,name')
            ->where('guest_id', $guestId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->find($examResultId);

        if ($examResult) {
            $examResult->update([
                'guest_result_prompted_at' => $examResult->guest_result_prompted_at ?: now(),
                'guest_ip_address' => $examResult->guest_ip_address ?: $request->ip(),
                'guest_user_agent' => $examResult->guest_user_agent ?: $request->userAgent(),
            ]);

            StudentActivityTracker::track(StudentActivityTracker::GUEST_RESULT_PROMPTED, [
                'organization_id' => $examResult->organization_id,
                'guest_id' => $guestId,
                'exam_id' => $examResult->exam_id,
                'exam_result_id' => $examResult->id,
                'source' => 'guest',
            ], $request);
        }

        return view('students.guest_exams.exam_feedback', compact('examResultId', 'resultAfterFinish', 'examResult'));
    }

    public function saveAnswer(Request $request)
    {
        $examStat = ExamStat::where('exam_result_id', $request->exam_result_id)
            ->where('guest_id', $this->getGuestId())
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
            'guest_name' => 'nullable|string|max:120',
            'guest_email' => 'nullable|email|max:150',
        ]);

        $guestId = $this->getGuestId();

        $feedbackExamResult = ExamResult::query()
            ->where('guest_id', $guestId)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->findOrFail($request->exam_result_id);

        $this->updateGuestAttemptIdentity($feedbackExamResult, $request, [
            'guest_name' => $request->input('guest_name'),
            'guest_email' => $request->input('guest_email'),
        ]);

        ExamFeedback::create([
            'exam_result_id' => $request->exam_result_id,
            'organization_id' => $feedbackExamResult->organization_id,
            'guest_id' => $guestId,
            'test_instructions' => $request->test_instructions,
            'language_of_questions' => $request->language_of_questions,
            'text_experience' => $request->text_experience,
            'feedback' => $request->feedback ?? '',
        ]);

        return redirect()->back()->with('success', 'Feedback submitted successfully.');
    }

    public function saveGuestContact(Request $request)
    {
        $request->validate([
            'exam_result_id' => 'required|integer',
            'guest_name' => 'nullable|string|max:120',
            'guest_email' => 'nullable|email|max:150',
        ]);

        $guestId = $this->getGuestId();
        $examResult = ExamResult::query()
            ->where('guest_id', $guestId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->findOrFail($request->exam_result_id);

        $this->updateGuestAttemptIdentity($examResult, $request, [
            'guest_name' => $request->input('guest_name'),
            'guest_email' => $request->input('guest_email'),
        ]);

        StudentActivityTracker::track(StudentActivityTracker::GUEST_CONTACT_SAVED, [
            'organization_id' => $examResult->organization_id,
            'guest_id' => $guestId,
            'exam_id' => $examResult->exam_id,
            'exam_result_id' => $examResult->id,
            'source' => 'guest',
            'metadata' => [
                'has_name' => filled($request->input('guest_name')),
                'has_email' => filled($request->input('guest_email')),
            ],
        ], $request);

        app(StudentLifecycleEmailService::class)->sendGuestWelcome($examResult->fresh());
        app(StudentPostAuthService::class)->remember([
            'action' => 'view_result',
            'exam_result_id' => $examResult->id,
            'exam_id' => $examResult->exam_id,
        ]);


        return redirect()->route('student.signin', ['exam_result_id' => $examResult->id]);
    }

    public function finishExam(Request $request)
    {

        $guestId = $this->getGuestId();

        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('guest_id', $guestId)
            ->firstOrFail();

        if (!$examResult) {
            return response()->json(['success' => false, 'message' => 'Exam result not found.'], 404);
        }

        $exam = $this->tenantExamQuery()->findOrFail($examResult->exam_id);

        $startTime = Carbon::parse($examResult->start_time)->setTimezone(config('app.timezone'));
        $endTime = Carbon::now()->setTimezone(config('app.timezone'));
        
        if($endTime->lt($startTime)) {
            $endTime = $startTime;
        }
        
        $testTime = $startTime->diffInSeconds($endTime, false);

        // ✅ FIX FOR TIME ISSUE: Cap time at Max Duration (Handles Network Latency)
        if ($exam->duration > 0) {
            $maxDurationInSeconds = $exam->duration * 60;
            // Agar calculated time Duration se zyada hai, to Duration hi maano
            if ($testTime > $maxDurationInSeconds) {
                $testTime = $maxDurationInSeconds;
            }
        }

        $examStats = ExamStat::with(['question.qtype'])->where('exam_result_id', $examResult->id)->get();
        
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
            'guest_ip_address' => $examResult->guest_ip_address ?: $request->ip(),
            'guest_user_agent' => $examResult->guest_user_agent ?: $request->userAgent(),
        ]);

        StudentActivityTracker::track(StudentActivityTracker::GUEST_EXAM_SUBMITTED, [
            'organization_id' => $examResult->organization_id,
            'guest_id' => $guestId,
            'exam_id' => $examResult->exam_id,
            'exam_result_id' => $examResult->id,
            'source' => 'guest',
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
            return redirect()->route('guest.examFeedback', ['exam_result_id' => $examResult->id, 'result_after_finish' => $exam->result_after_finish]);
        }

        return response()->json(['success' => true]);
    }

    public function saveProctorImage(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|integer',
            'student_id' => 'required|integer',
            'image' => 'required|image'
        ]);

        $path = $request->file('image')->store('proctor_images', 'public');

        $guestId = $this->getGuestId();

        $exam = $this->tenantExamQuery()->findOrFail($request->exam_id);

        ExamProctorImage::create([
            'exam_id' => $request->exam_id,
            'organization_id' => $exam->organization_id,
            'guest_id' => $guestId,
            'image_path' => $path
        ]);

        return response()->json(['success' => true]);
    }

    public function updateToleranceCount(Request $request)
    {

        $guestId = $this->getGuestId();

        $examResult = ExamResult::where('id', $request->exam_result_id)
            ->where('guest_id', $guestId)
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

        $guestId = $this->getGuestId();
        $studentId = Auth::guard('student')->id() ?? null;

        $question = \App\Models\Question::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->findOrFail($request->question_id);

        QuestionsReport::create([
            'organization_id' => $question->organization_id,
            'guest_id' => $guestId,
            'student_id' => $studentId,
            'question_id' => $question->id,
            'subject_id' => $question->subject_id,
            'question_type' => $request->question_type,
            'message' => $request->message ?: '',
        ]);

        return response()->json(['success' => true]);

    }

    private function getGuestId()
    {
        return session('guest_id') ?? request()->cookie('guest_id');
    }

    private function rememberGuestIdentity(Request $request): array
    {
        $name = trim((string) $request->input('guest_name', session('guest_name', '')));
        $email = trim((string) $request->input('guest_email', session('guest_email', '')));

        if ($name !== '') {
            session(['guest_name' => mb_substr($name, 0, 120)]);
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session(['guest_email' => mb_substr($email, 0, 150)]);
        }

        return [
            'guest_name' => session('guest_name'),
            'guest_email' => session('guest_email'),
        ];
    }

    private function updateGuestAttemptIdentity(ExamResult $examResult, Request $request, array $identity = []): void
    {
        $guestName = trim((string) ($identity['guest_name'] ?? session('guest_name', '')));
        $guestEmail = trim((string) ($identity['guest_email'] ?? session('guest_email', '')));

        if ($guestName !== '') {
            session(['guest_name' => mb_substr($guestName, 0, 120)]);
        }

        if ($guestEmail !== '' && filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            session(['guest_email' => mb_substr($guestEmail, 0, 150)]);
        }

        $updates = [
            'guest_ip_address' => $examResult->guest_ip_address ?: $request->ip(),
            'guest_user_agent' => $examResult->guest_user_agent ?: $request->userAgent(),
        ];

        if (session('guest_name') && blank($examResult->guest_name)) {
            $updates['guest_name'] = session('guest_name');
        }

        if (session('guest_email') && blank($examResult->guest_email)) {
            $updates['guest_email'] = session('guest_email');
        }

        $examResult->update($updates);
    }

    private function resolveExam($id)
    {
        return $this->tenantExamQuery()
            ->where(function ($query) use ($id) {
                $query->where('slug', $id)
                    ->orWhere('id', $id);
            })
            ->firstOrFail();
    }

    private function validateGuestExamAccess($examId)
    {
        $guestId = $this->getGuestId();

        if (!$guestId) {
            return false;
        }

        $packageIds = Order::query()
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.guest_id', $guestId)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('orders.organization_id', $tenantId)
                        ->orWhereNull('orders.organization_id');
                });
            })
            ->pluck('order_items.package_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($packageIds)) {
            return false;
        }

        return Package::whereIn('id', $packageIds)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereHas('exams', function ($query) use ($examId) {
                $query->where('exams.id', (int) $examId)
                    ->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']);
            })
            ->exists();
    }
}
