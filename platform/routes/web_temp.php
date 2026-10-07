<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\Students\GuestExamsController;
use App\Http\Controllers\Students\MyExamController;
use App\Http\Controllers\ExamPrintController;

// ==========================================
// PUBLIC ROUTES
// ==========================================
Route::get('/', [WebsiteController::class, 'index'])->name('home');
Route::get('/exams', [WebsiteController::class, 'exams'])->name('website.exams.index');

// ==========================================
// DIRECT EXAM ROUTES WITH SLUG (NO VALIDATION)
// ==========================================
Route::get('/exam/{exam:slug}/guideline', function(App\Models\Exam $exam) {
    return view('students.guest_exams.exam_guideline', compact('exam'));
})->name('exam.guideline');

Route::get('/exam/{exam:slug}/instructions', function(App\Models\Exam $exam) {
    return view('students.guest_exams.exam_instructions', compact('exam'));
})->name('exam.instructions');

Route::get('/exam/{exam:slug}/start', function(App\Models\Exam $exam) {
    if (!session('guest_id')) {
        session(['guest_id' => 'guest_' . uniqid()]);
    }
    
    $guestId = session('guest_id');
    $totalMarks = $exam->questions->sum('marks');
    
    $examResult = App\Models\ExamResult::firstOrCreate(
        ['exam_id' => $exam->id, 'guest_id' => $guestId, 'end_time' => null],
        ['start_time' => now(), 'total_question' => $exam->questions->count(), 'total_marks' => $totalMarks]
    );
    
    $remainingTime = $exam->duration * 60;
    $examStats = App\Models\ExamStat::where('exam_result_id', $examResult->id)->get()->keyBy('question_id');
    
    return view('students.guest_exams.exam_start', compact('exam', 'remainingTime', 'examResult', 'examStats'));
})->name('exam.start');

// ==========================================
// PRINT ROUTE
// ==========================================
Route::get('/exam-print/{exam:slug}', [ExamPrintController::class, 'print'])->name('exam.print');

// ==========================================
// ADMIN ROUTES (Keep your existing ones)
// ==========================================
// Include your existing admin routes here
// Route::resource('exams', ExamController::class);
