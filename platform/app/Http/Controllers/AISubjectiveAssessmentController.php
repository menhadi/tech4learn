<?php

namespace App\Http\Controllers;

use App\Models\ExamStats;
use App\Models\ExamResult;
use App\Models\Question;
use App\Models\Configuration;
use App\Support\AiProvider;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AISubjectiveAssessmentController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    public function assess(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');

        $request->validate(['stat_id' => 'required|exists:exam_stats,id']);
        $stat = ExamStats::with('question')
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->find($request->stat_id);
        
        if (!$stat) {
            return response()->json(['success' => false, 'message' => 'Record not found']);
        }

        // Get student answer from 'answer' column
        $studentAnswer = $stat->answer;
        if (empty($studentAnswer) && $stat->uploaded_answer_path) {
            $studentAnswer = $this->extractText($stat->uploaded_answer_path);
            $stat->extracted_answer_text = $studentAnswer;
            $stat->answer = $studentAnswer;
            $stat->save();
        }

        if (empty($studentAnswer)) {
            return response()->json(['success' => false, 'message' => 'No answer found']);
        }

        $question = Question::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->find($stat->question_id);
        $modelAnswer = $question->si_answer1 ?? '';
        $questionText = $question->question ?? '';
        $maxMarks = floatval($stat->marks ?? 1);
        $settings = getConfiguration();
        
        $providers = AiProvider::available($settings, false, 'subjective_assessment');

        if ($providers === []) {
            return response()->json(['success' => false, 'message' => 'No AI API keys']);
        }

        $prompt = "Score out of $maxMarks. Criteria: Accuracy 40%, Completeness 30%, Grammar 20%, Relevance 10%.\nQ: $questionText\nModel: $modelAnswer\nStudent: $studentAnswer\nReturn JSON: {\"score\": number}";
        $assessments = [];
        foreach ($providers as $provider) {
            $result = $this->callAI($provider, $prompt);
            if (! is_numeric($result)) continue;
            $name = $provider['stored_name'] ?? strtoupper((string) $provider['provider']);
            $assessments[$name] = round(max(0, min($maxMarks, (float) $result)), 2);
        }

        if ($assessments === []) {
            return response()->json(['success' => false, 'message' => 'AI assessment failed']);
        }

        $avgScore = round(array_sum($assessments) / count($assessments), 2);
        
        $stat->ai_assessed = 1;
        $stat->ai_score = $avgScore;
        $stat->ai_providers_used = implode(',', array_keys($assessments));
        $stat->marks_obtained = $avgScore;
        $stat->save();
        
        // Update exam total
        $total = ExamStats::where('exam_result_id', $stat->exam_result_id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->sum('marks_obtained');
        ExamResult::where('id', $stat->exam_result_id)
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->update(['obtained_marks' => $total]);
        
        return response()->json(['success' => true, 'score' => $avgScore, 'provider_count' => count($assessments), 'assessments' => $assessments]);
    }
    
    private function callAI(array $provider, string $prompt)
    {
        try {
            $content = AiProvider::generateText($provider, $prompt, null, 0.3, 200, 60);
            $content = preg_replace('/```(?:json)?|```/i', '', (string) $content);
            if (preg_match('/\{.*\}/s', $content, $match)) $content = $match[0];
            $data = json_decode(trim($content), true);
            return $data['score'] ?? null;
        } catch (\Throwable $e) {
            Log::error(($provider['provider'] ?? 'AI').' subjective assessment error: '.$e->getMessage());
        }
        return null;
    }
    
    private function extractText($path)
    {
        $full = storage_path('app/public/' . $path);
        if (!file_exists($full)) return '';
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if ($ext === 'txt') return file_get_contents($full);
        if ($ext === 'pdf') return shell_exec("pdftotext '$full' - 2>/dev/null");
        if (in_array($ext, ['doc', 'docx'])) return shell_exec("catdoc '$full' 2>/dev/null");
        return '';
    }
    
    public function bulkAssess(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');

        $stats = ExamStats::where('ai_assessed', 0)
            ->whereNotNull('answer')
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->get();
        
        foreach ($stats as $stat) {
            $this->assess(new Request(['stat_id' => $stat->id]));
            sleep(1);
        }
        
        return response()->json(['success' => true, 'total' => count($stats)]);
    }
}
