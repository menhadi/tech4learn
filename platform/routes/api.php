<?php

use App\Http\Controllers\AdmissionPredictionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Students\ApiStudentAuthController;
use App\Http\Controllers\Students\ApiMyExamController;
use App\Http\Controllers\Students\ApiStudentExamsController;
use App\Http\Controllers\Students\ApiStudentDashboardController;
use App\Http\Controllers\Students\ApiStudentProfileController; // <-- YEH NAYI LINE ADD KARO

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/admission-prediction', AdmissionPredictionController::class)->middleware('throttle:30,1');
// === Public Routes (Login/Signup ke liye token nahi chahiye) ===
Route::post('/student/signup', [ApiStudentAuthController::class, 'signup']);
Route::post('/student/signin', [ApiStudentAuthController::class, 'signin']);


// === Protected Routes (Inko access karne ke liye Token BHEJNA padega) ===
Route::middleware(['auth:student-api', 'student.tenant'])->group(function () {
    
    // Auth routes
    Route::get('/student/me', [ApiStudentProfileController::class, 'getProfile']); // Changed this
    Route::post('/student/signout', [ApiStudentAuthController::class, 'signout']);

    
    // My Exams & Dashboard
    Route::get('/student/my-exams', [ApiMyExamController::class, 'getMyExams']);
    Route::post('/student/finalize-pending', [ApiMyExamController::class, 'finalizePending']);

    // Exam Info
    Route::get('/student/exam-details/{id}', [ApiMyExamController::class, 'getExamDetails']);
    Route::get('/student/check-attempts/{id}', [ApiMyExamController::class, 'checkAttempts']);
    

    // (Actual Exam Taking Process)
    Route::post('/student/exam/start/{id}', [ApiStudentExamsController::class, 'startOrResumeExam']);
    Route::post('/student/exam/save-answer', [ApiStudentExamsController::class, 'saveAnswer']);
    Route::post('/student/exam/submit', [ApiStudentExamsController::class, 'submitExam']);
    Route::post('/student/exam/save-proctor-image', [ApiStudentExamsController::class, 'saveProctorImage']);
    Route::post('/student/exam/update-tolerance', [ApiStudentExamsController::class, 'updateToleranceCount']);


    // (Dashboard, Results & Bookmarks)
    Route::get('/student/dashboard', [ApiStudentDashboardController::class, 'getDashboardStats']);
    Route::get('/student/results', [ApiStudentDashboardController::class, 'getResultsList']);
    Route::get('/student/results/{id}', [ApiStudentDashboardController::class, 'getResultDetail']);
    Route::get('/student/bookmarks', [ApiStudentDashboardController::class, 'getBookmarkList']);
    Route::post('/student/bookmark', [ApiStudentDashboardController::class, 'toggleBookmark']);
    
    
    // =======================================================
    // >> YEH SECTION HUMNE ABHI ADD KIYA HAI <<
    // (Profile Management)
    // =======================================================
    Route::get('/student/profile', [ApiStudentProfileController::class, 'getProfile']);
    Route::post('/student/profile/update', [ApiStudentProfileController::class, 'updateProfile']);
    Route::post('/student/password/change', [ApiStudentProfileController::class, 'updatePassword']);
});