<?php

use Illuminate\Support\Facades\Route;
// Note: Make sure your Controller file is inside the 'Students' folder locally if you use this namespace
use App\Http\Controllers\Students\StudentAuthController; 
use App\Http\Controllers\Students\StudentsController;
use App\Http\Controllers\Students\MyExamController;
use App\Http\Controllers\Students\StudentExamsController;
use App\Http\Controllers\Students\StudentDashboardController;
use App\Http\Controllers\Students\StudentForgotPasswordController;
use App\Http\Controllers\Students\StudentCourseController;
use App\Http\Controllers\Students\PracticeBuilderController;
use App\Http\Controllers\Students\FlashcardStudyController;
use App\Http\Controllers\Students\PurchasedCourse;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectiveUploadController;

// ====================================================
// PUBLIC / GUEST ROUTES
// ====================================================

// Cart Routes
Route::resource('/cart', CartController::class)->except(['create', 'edit', 'show', 'update']);

// Checkout Routes
Route::resource('checkout', CheckoutController::class)->only(['index', 'store']);
Route::post('/create-razorpay-order', [CheckoutController::class, 'createRazorpayOrder']);

// Coupon Routes
Route::post('/checkout/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.apply_coupon');
Route::post('/checkout/remove-coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.remove_coupon');
Route::post('/checkout/enroll/exam', [CheckoutController::class, 'enrollExam'])->name('checkout.enroll_exam');

// ====================================================
// GUEST AUTH ROUTES
// ====================================================
Route::middleware(['guest', 'guest:student'])->group(function () {
    
    Route::middleware('plan.feature:student_self_registration')->group(function () {
        Route::get('student/signup', [StudentAuthController::class, 'showSignupForm'])->name('student.signup');
        Route::post('student/signup', [StudentAuthController::class, 'signup']);
        Route::get('student/verify-signup', [StudentAuthController::class, 'verifySignupForm'])->name('student.verify');
        Route::post('student/verify-signup', [StudentAuthController::class, 'verifySignup'])->middleware('throttle:10,1')->name('student.verify.post');
        Route::post('student/resend-otp', [StudentAuthController::class, 'resendOtp'])->middleware('throttle:3,1')->name('student.resend_otp');
    });

    // 2. Login
    Route::get('student/signin', [StudentAuthController::class, 'showSigninForm'])->name('student.signin');
    Route::post('student/signin', [StudentAuthController::class, 'signin']);
    
    // 3. Password Reset
    Route::get('student/password/forgot', [StudentForgotPasswordController::class, 'showForgotPasswordForm'])->name('student.password.request');
    Route::post('student/password/send-otp', [StudentForgotPasswordController::class, 'sendOtp'])->name('student.password.sendOtp');
    Route::get('student/password/reset', [StudentForgotPasswordController::class, 'showResetForm'])->name('student.password.reset');
    Route::post('student/password/verify-otp', [StudentForgotPasswordController::class, 'verifyOtp'])->name('student.password.verifyOtp');
});

// ====================================================
// AUTHENTICATED STUDENT ROUTES (DO NOT TOUCH - Original Logic)
// ====================================================
Route::middleware('auth:student')->group(function () {
        
    // Dashboard & Profile
    Route::get('student/leaderboard', [StudentDashboardController::class, 'leaderboard'])->name('student.leaderboard');
    Route::get('student/dashboard', [StudentDashboardController::class, 'dashboard'])->name('student.dashboard');
    Route::get('student/quick-quizzes', [StudentDashboardController::class, 'quickQuizzes'])->name('student.quick-quizzes');
    
    // Profile Routes
    Route::get('student/profile', [StudentsController::class, 'showProfile'])->name('student.profile');
    Route::match(['get', 'post'], 'student/edit-profile', [StudentsController::class, 'editProfile'])->name('student.editProfile');
    Route::match(['get', 'post'], 'student/change-password', [StudentsController::class, 'changePassword'])->name('student.changePassword');

    // Purchased Courses
    Route::get('/student/courses', [StudentCourseController::class, 'index'])->name('student.courses.index');
    Route::post('/student/courses/{package}/enroll', [StudentCourseController::class, 'enroll'])->name('student.courses.enroll');
    Route::get('/purchased-course', [PurchasedCourse::class, 'purchasedcourse'])->name('student.orders.index');
    Route::get('/student/orders/{id}', [PurchasedCourse::class, 'show'])->name('student.orders.show');
    Route::get('/thank-you', function () {
        return view('website.thank-you');
    });

    // Question Details
    Route::get('/questions/{questionId}/language/{languageId}', [QuestionController::class, 'getLangData'])->name('questions.langData');

    // Exams Flow
    Route::get('student/my-exams', [MyExamController::class, 'index'])->name('student.myexams');
    Route::get('student/my-exams/packages/{package}/exams', [MyExamController::class, 'packageExams'])->name('student.myexams.packages.exams');
    Route::get('student/exam-solutions/{id}/download', [\App\Http\Controllers\ExamPrintController::class, 'solutionDownload'])->middleware('throttle:10,1')->name('student.exam.solution.download');
    Route::post('student/my-exams/select-group', [MyExamController::class, 'selectGroup'])->name('student.myexams.selectGroup');
    Route::post('student/my-exams/packages/{package}/hide', [MyExamController::class, 'hidePackage'])->name('student.myexams.packages.hide');
    Route::post('student/my-exams/packages/{package}/restore', [MyExamController::class, 'restorePackage'])->name('student.myexams.packages.restore');
    Route::get('student/practice-builder', [PracticeBuilderController::class, 'index'])->name('student.practice-builder.index');
    Route::post('student/practice-builder', [PracticeBuilderController::class, 'store'])->name('student.practice-builder.store');
    Route::middleware('plan.feature:flashcards')->group(function () {
        Route::get('student/flashcards', [FlashcardStudyController::class, 'index'])->name('student.flashcards.index');
        Route::get('student/flashcards/{package}', [FlashcardStudyController::class, 'show'])->name('student.flashcards.show');
        Route::get('student/flashcards/{package}/leaderboard', [FlashcardStudyController::class, 'leaderboard'])->name('student.flashcards.leaderboard');
        Route::post('student/flashcards/cards/{flashcard}/track', [FlashcardStudyController::class, 'track'])->name('student.flashcards.track');
        Route::post('student/flashcards/cards/{flashcard}/review', [FlashcardStudyController::class, 'review'])->name('student.flashcards.review');
        Route::post('student/flashcards/cards/{flashcard}/report', [FlashcardStudyController::class, 'report'])->name('student.flashcards.report');
    });

    Route::get('/exam-details/{id}', [MyExamController::class, 'getExamDetails']);
    Route::get('exam/guideline/{id}', [StudentExamsController::class, 'showGuideline'])->name('student.guideline');
    Route::get('exam/instructions/{id}', [StudentExamsController::class, 'showInstructions'])->name('student.instructions');
    Route::post('exam/{id}/languages/{languageId}/translate', [StudentExamsController::class, 'translateLanguage'])->name('student.exams.languages.translate');
    Route::get('exam/start/{id}', [StudentExamsController::class, 'startExam'])->name('student.startExam');
    
    Route::post('student/save-answer', [StudentExamsController::class, 'saveAnswer'])->name('student.saveAnswer');
    Route::post('student/finish-exam', [StudentExamsController::class, 'finishExam'])->name('student.finishExam');
    Route::post('/subjective-upload', [SubjectiveUploadController::class, 'upload'])
        ->middleware('plan.feature:ai_subjective_analysis')
        ->name('student.subjective-upload');

    // Finalize Pending Exam
    Route::post('student/finalize-pending', [MyExamController::class, 'finalizePending'])->name('student.finalizePending');

    // Results
    Route::get('student/results', [StudentDashboardController::class, 'showResults'])->name('student.results');
    Route::get('/student/results/{id}', [StudentDashboardController::class, 'viewResult'])->name('student.results.view');
    
    // Bookmarks
    Route::post('student/bookmark-question', [StudentDashboardController::class, 'bookmarkQuestion'])->name('student.bookmarkQuestion');
    Route::get('student/bookmarks', [StudentDashboardController::class, 'showBookmarks'])->name('student.bookmarks');
    Route::get('student/view-bookmarks/{exam}', [StudentDashboardController::class, 'viewBookmarks'])->name('student.viewBookmarks');
    
    // Feedback & Proctoring
    Route::get('student/exam-feedback', [StudentExamsController::class, 'examFeedback'])->name('student.examFeedback');
    Route::post('student/submit-exam-feedback', [StudentExamsController::class, 'submitExamFeedback'])->name('student.submitExamFeedback');
    Route::post('student/save-proctor-image', [StudentExamsController::class, 'saveProctorImage'])->name('student.saveProctorImage');
    Route::get('exam/check-attempts/{id}', [MyExamController::class, 'checkAttempts'])->name('exam.checkAttempts');
    Route::post('/student/update-tolerance-count', [StudentExamsController::class, 'updateToleranceCount'])->name('student.updateToleranceCount');

    //Report a question
    Route::post('/student/report', [StudentExamsController::class, 'questionReport'])->name('student.questionReport.store');

    // Other Pages
    Route::get('student/help', function () { return view('students.help'); })->name('student.help');
    
    // Signout
    Route::post('student/signout', [StudentAuthController::class, 'signout'])->name('student.signout');
});
