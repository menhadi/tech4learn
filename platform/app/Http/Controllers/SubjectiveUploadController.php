<?php

namespace App\Http\Controllers;

use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubjectiveUploadController extends Controller
{
    public function upload(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');

        try {
            $request->validate([
                'answer_file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,txt|max:10240',
                'question_id' => 'required|exists:questions,id',
                'exam_result_id' => 'required|exists:exam_results,id',
            ]);
            
            $file = $request->file('answer_file');
            $questionId = $request->input('question_id');
            $examResultId = $request->input('exam_result_id');
            $tenantId = class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::hostId($request->getHost()) : null;
            $studentId = Auth::guard('student')->id();

            $examResult = \App\Models\ExamResult::query()
                ->where('id', $examResultId)
                ->where('student_id', $studentId)
                ->when($tenantId, function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                })
                ->first();

            if (! $examResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid exam result for this student.',
                ], 403);
            }
            
            $stat = \App\Models\ExamStats::where('exam_result_id', $examResultId)
                ->where('question_id', $questionId)
                ->when($tenantId, function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                })
                ->first();

            if (! $stat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Question answer record was not found for this exam.',
                ], 404);
            }

            $fileName = time() . '_' . $questionId . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('student_answers', $fileName, 'public');
            
            Log::info('File uploaded: ' . $path);

            $stat->uploaded_answer_path = $path;
            $stat->save();
            Log::info('Updated exam_stats: ' . $stat->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Answer uploaded successfully!',
                'path' => $path
            ]);
            
        } catch (\Exception $e) {
            Log::error('Upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
