<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Services\StudentActivityTracker;

class ExamPDFController extends Controller
{
    public function download($exam_id)
    {
        try {
            // Get exam details
            $exam = DB::table('exams')->where('id', $exam_id)->first();
            
            if (!$exam) {
                return response()->json(['error' => 'Exam not found'], 404);
            }
            
            // Get questions for this exam
            $questions = DB::table('questions')
                ->join('exam_questions', 'questions.id', '=', 'exam_questions.question_id')
                ->where('exam_questions.exam_id', $exam_id)
                ->where('questions.status', 'active')
                ->select('questions.*')
                ->orderBy('exam_questions.id')
                ->get();
            
            if ($questions->isEmpty()) {
                return response()->json(['error' => 'No questions found for this exam'], 404);
            }
            
            // Prepare data for view
            $data = [
                'exam' => $exam,
                'questions' => $questions,
                'title' => $exam->name,
                'total_questions' => count($questions),
                'generated_date' => date('F d, Y')
            ];
            
            // Generate PDF
            $pdf = PDF::loadView('pdf.exam_questions', $data);
            $pdf->setPaper('A4', 'portrait');
            
            // Set PDF options for better rendering
            $pdf->getDomPDF()->setOption('defaultFont', 'DejaVu Sans');
            $pdf->getDomPDF()->setOption('isHtml5ParserEnabled', true);
            $pdf->getDomPDF()->setOption('isRemoteEnabled', true);
            
            // Download the PDF
            $filename = preg_replace('/[^a-zA-Z0-9]/', '_', $exam->name) . '_Questions.pdf';
            StudentActivityTracker::trackPdfDownload([
                'organization_id' => $exam->organization_id ?? null,
                'exam_id' => $exam->id,
                'metadata' => ['document_name' => $exam->name, 'document_context' => 'exam_questions', 'delivery' => 'download'],
            ]);
            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            \Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }
}
