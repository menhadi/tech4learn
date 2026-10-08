<?php



use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SeoGeneratorController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NavigationController;

use App\Http\Controllers\UgroupController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StudentAdminController;
use App\Http\Controllers\DemoStudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\StopicController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PassageController;
use App\Http\Controllers\DiffController;
use App\Http\Controllers\QtypeController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionExamAssignmentController;
use App\Http\Controllers\QuestionLangController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PackageTagController;
use App\Http\Controllers\QuestionTagController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamImportExportController;
use App\Http\Controllers\ExamQualityAuditController;
use App\Http\Controllers\ImageCleanupController;
use App\Http\Controllers\ImageConversionController;
use App\Http\Controllers\AiAnswerController;
use App\Http\Controllers\SourceExamImportController;
use App\Http\Controllers\SourceDocumentDiscoveryController;
use App\Http\Controllers\ExamBulkEditorController;
use App\Http\Controllers\AdminBulkEditorController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\MessagingSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentFunnelReportController;
use App\Http\Controllers\StudentActivityController;
use App\Http\Controllers\QuickQuizController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\QuestionLargeImportController;
use App\Http\Controllers\SourceQuestionImportController;
use App\Http\Controllers\SourceQuestionAdapterController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EmailSettingController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\ImageUploadController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\PypPageController;
use App\Http\Controllers\PypSettingsController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\CountersController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\HomepageContentController;
use App\Http\Controllers\WebsitePageController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Students\GuestExamsController;
use App\Http\Controllers\Students\MyExamController;

use App\Http\Controllers\Auth\GoogleController;

require __DIR__ . '/student_routes.php';
$systemHealthRoutes = __DIR__ . '/system_health.php';
if (is_file($systemHealthRoutes)) {
    require $systemHealthRoutes;
}

Route::get('/exam-quality-preview/{audit}/{question}', [ExamQualityAuditController::class, 'preview'])
    ->middleware('signed:relative')
    ->name('exam-quality.question-preview');




Route::middleware(['guest', 'guest:student'])->group(function () {
    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
    Route::get('password/forgot', [ForgotPasswordController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('password/send-otp', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:5,1')->name('password.sendOtp');
    Route::post('password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('password.verifyOtp');
    Route::get('password/reset', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
});

Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

//Language Translation
Route::get('index/{locale}', [App\Http\Controllers\HomeController::class, 'lang']);

// Language Switcher
Route::get('lang-swap/{locale}', [App\Http\Controllers\LanguagePreferenceController::class, 'update'])
    ->name('lang.swap');

Route::post('/student-activity', [StudentActivityController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('student.activity.store');


Route::middleware('plan.feature:public_website')->group(function () {
    Route::get('/', [WebsiteController::class, 'dashboard'])->name('home');
    Route::get('/quick-quiz/options', [QuickQuizController::class, 'options'])->name('quick-quiz.options');
    Route::get('/quick-quiz/history', [QuickQuizController::class, 'history'])->name('quick-quiz.history');
    Route::post('/quick-quiz/activity', [QuickQuizController::class, 'activity'])->middleware('throttle:30,1')->name('quick-quiz.activity');
    Route::post('/quick-quiz/start', [QuickQuizController::class, 'start'])->middleware('throttle:10,1')->name('quick-quiz.start');
    Route::get('/quick-quiz/{publicId}', [QuickQuizController::class, 'show'])->name('quick-quiz.show');
    Route::post('/quick-quiz/{publicId}/answer', [QuickQuizController::class, 'answer'])->middleware('throttle:30,1')->name('quick-quiz.answer');
    Route::get('/quick-quiz/{publicId}/questions/{question}/explanation', [QuickQuizController::class, 'explanation'])->name('quick-quiz.explanation');
    Route::get('/about', [WebsiteController::class, 'about'])->name('about');
    Route::get('/header-search', [WebsiteController::class, 'headerSearch'])->name('website.header.search');
    Route::get('/courses', [WebsiteController::class, 'courses'])->name('courses.index');
    Route::get('/course-detail/{id}/flashcards', [WebsiteController::class, 'guestFlashcards'])->name('website.flashcards.show');
    Route::get('/course-detail/{id}/flashcards/leaderboard', [WebsiteController::class, 'flashcardLeaderboard'])->name('website.flashcards.leaderboard');
    Route::post('/course-detail/flashcards/cards/{flashcard}/report', [WebsiteController::class, 'reportFlashcard'])->name('website.flashcards.report');
    Route::post('/course-detail/flashcards/cards/{flashcard}/track', [WebsiteController::class, 'trackGuestFlashcard'])->name('website.flashcards.track');
    Route::get('/course-detail/{id}/exams', [WebsiteController::class, 'courseExams'])->name('courses.exams');
    Route::get('/course-detail/{id}', [WebsiteController::class, 'coursesdetail'])->name('courses.detail');
    Route::get('/previous-year-papers/{package}', [PypPageController::class, 'index'])->name('pyp.index');
    Route::get('/previous-year-papers/{package}/analysis', [PypPageController::class, 'analysis'])->name('pyp.analysis');
    Route::get('/previous-year-papers/{package}/subjects/{subject}', [PypPageController::class, 'subject'])->name('pyp.subject');
    Route::get('/previous-year-papers/{package}/subjects/{subject}/topics/{topic}', [PypPageController::class, 'topic'])->name('pyp.topic');
    Route::get('/previous-year-papers/{package}/subjects/{subject}/topics/{topic}/subtopics/{subtopic}', [PypPageController::class, 'subtopic'])->name('pyp.subtopic');
    Route::get('/exam-detail/{slug}', [WebsiteController::class, 'examLanding'])->name('exam.detail');
    Route::get('/for-institutes', [WebsiteController::class, 'forInstitutes'])->name('website.forInstitutes');
    Route::post('/for-institutes', [WebsiteController::class, 'storeInstituteLead'])->name('website.forInstitutes.store');
    Route::get('/contact', [WebsiteController::class, 'contact'])->name('contact');
    Route::get('/pages/{slug}', function ($slug) {
        return redirect()->route('page.show', ['slug' => $slug], 301);
    })->name('page.legacy');
    Route::post('/contact-store', [ContactController::class, 'store'])->name('contact.store');
    Route::get('/exam-groups/{group?}/{category?}/{subcategory?}', [WebsiteController::class, 'exams'])->name('website.exams.index');
});

Route::middleware(['auth', 'checkPageRights'])->group(function () {
    Route::post('exams/publish-pdfs', [\App\Http\Controllers\ExamPdfPublicationController::class, 'store'])->name('exams.publish-pdfs');
    Route::post('official-exam-sources/{officialExamSource}/run', [\App\Http\Controllers\OfficialExamSourceController::class, 'run'])->name('official-exam-sources.run');
    Route::resource('official-exam-sources', \App\Http\Controllers\OfficialExamSourceController::class);
    Route::get('/pyp-pages', [PypSettingsController::class, 'index'])->name('pyp-pages.index');
    Route::put('/pyp-pages', [PypSettingsController::class, 'update'])->name('pyp-pages.update');
});

//Google Controller
Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])
    ->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

Route::middleware('plan.feature:guest_exams')->group(function () {
    Route::get('/guest/exam/guideline/{id}', [GuestExamsController::class, 'showGuideline'])->name('guest.guideline');
    Route::get('/guest/exam/instructions/{id}', [GuestExamsController::class, 'showInstructions'])->name('guest.instructions');
    Route::post('/guest/exam/{id}/languages/{languageId}/translate', [GuestExamsController::class, 'translateLanguage'])->name('guest.exams.languages.translate');
    Route::get('/guest/exam/start/{id}', [GuestExamsController::class, 'startExam'])->name('guest.startExam');
    Route::get('/guest/questions/{questionId}/language/{languageId}', [QuestionController::class, 'getLangData'])->name('guest.questions.langData');
    Route::post('/guest/student/save-answer', [GuestExamsController::class, 'saveAnswer'])->name('guest.saveAnswer');
    Route::post('/guest/student/finish-exam', [GuestExamsController::class, 'finishExam'])->name('guest.finishExam');

    Route::get('/guest/student/exam-feedback', [GuestExamsController::class, 'examFeedback'])->name('guest.examFeedback');
    Route::post('/guest/student/save-contact', [GuestExamsController::class, 'saveGuestContact'])->name('guest.saveContact');
    Route::post('/guest/student/submit-exam-feedback', [GuestExamsController::class, 'submitExamFeedback'])->name('guest.submitExamFeedback');
    Route::post('/guest/student/save-proctor-image', [GuestExamsController::class, 'saveProctorImage'])->name('guest.saveProctorImage');
    Route::get('/guest/exam/check-attempts/{id}', [MyExamController::class, 'checkAttempts'])->name('guest.exam.checkAttempts');
    Route::post('/guest/student/update-tolerance-count', [GuestExamsController::class, 'updateToleranceCount'])->name('guest.updateToleranceCount');
    Route::post('/guest/student/report', [GuestExamsController::class, 'questionReport'])->name('guest.questionReport.store');
});

// Coupon Route
Route::post('/apply-coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.applyCoupon');

// ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ NEW ROUTE: Razorpay Order Creation (Added Here) ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦
Route::post('/create-razorpay-order', [CheckoutController::class, 'createRazorpayOrder'])->name('checkout.createRazorpayOrder');

//Update User Details
Route::post('/update-profile/{id}', [App\Http\Controllers\HomeController::class, 'updateProfile'])->name('updateProfile');
Route::post('/update-password/{id}', [App\Http\Controllers\HomeController::class, 'updatePassword'])->name('updatePassword');


Route::middleware(['auth'])->group(function () {
    Route::get('stopics/get-topics/{id}', [StopicController::class, 'getTopics'])->name('stopics.get-topics');
});

Route::middleware(['auth', 'checkPageRights'])->group(function () {
    Route::get('source-exams', [SourceExamImportController::class, 'index'])->name('source-exams.index');
    Route::get('source-question-import', [SourceQuestionImportController::class, 'index'])->name('source-question-import.index');
    Route::post('source-question-import', [SourceQuestionImportController::class, 'store'])->name('source-question-import.store');
    Route::post('source-question-import/crawl', [SourceQuestionImportController::class, 'crawlStore'])->name('source-question-import.crawl.store');
    Route::post('source-question-import/audit', [SourceQuestionImportController::class, 'auditStore'])->name('source-question-import.audit.store');
    Route::post('source-question-import/adapters', [SourceQuestionAdapterController::class, 'store'])->name('source-question-adapters.store');
    Route::get('source-question-import/adapters/{adapter}/edit', [SourceQuestionAdapterController::class, 'edit'])->name('source-question-adapters.edit');
    Route::patch('source-question-import/adapters/{adapter}', [SourceQuestionAdapterController::class, 'update'])->name('source-question-adapters.update');
    Route::delete('source-question-import/adapters/{adapter}', [SourceQuestionAdapterController::class, 'destroy'])->name('source-question-adapters.destroy');
    Route::post('source-question-import/{run}/retry', [SourceQuestionImportController::class, 'retry'])->name('source-question-import.retry');
    Route::get('source-question-import/{run}', [SourceQuestionImportController::class, 'show'])->name('source-question-import.show');
    Route::get('source-question-import/{run}/paper/preview', [SourceQuestionImportController::class, 'paperPreview'])->name('source-question-import.paper.preview');
    Route::get('source-question-import/{run}/paper/edit', [SourceQuestionImportController::class, 'paperEdit'])->name('source-question-import.paper.edit');
    Route::get('source-question-import/{run}/status', [SourceQuestionImportController::class, 'status'])->name('source-question-import.status');
    Route::delete('source-question-import/{run}', [SourceQuestionImportController::class, 'destroy'])->name('source-question-import.destroy');
    Route::post('source-question-import/items/{item}/publish', [SourceQuestionImportController::class, 'publish'])->name('source-question-import.publish');
    Route::post('source-question-import/items/{item}/repair', [SourceQuestionImportController::class, 'repair'])->name('source-question-import.audit.repair');
    Route::patch('source-question-import/items/{item}', [SourceQuestionImportController::class, 'updateItem'])->name('source-question-import.item.update');
    Route::get('source-question-import/items/{item}', [SourceQuestionImportController::class, 'item'])->name('source-question-import.item');
    Route::get('source-exams/discover', [SourceDocumentDiscoveryController::class, 'index'])->name('source-exams.discover');
    Route::post('source-exams/discover/scan', [SourceDocumentDiscoveryController::class, 'scan'])->name('source-exams.discover.scan');
    Route::post('source-exams/discover/local', [SourceDocumentDiscoveryController::class, 'scanLocal'])->name('source-exams.discover.local');
    Route::post('source-exams/discover/create', [SourceDocumentDiscoveryController::class, 'store'])->name('source-exams.discover.store');
    Route::get('exams/bulk-edit', [ExamBulkEditorController::class, 'index'])->name('exams.bulk-editor');
    Route::put('exams/bulk-edit', [ExamBulkEditorController::class, 'update'])->name('exams.bulk-editor.update');
    Route::get('bulk-edit/{resource}', [AdminBulkEditorController::class, 'index'])->name('admin.bulk-editor.index');
    Route::put('bulk-edit/{resource}', [AdminBulkEditorController::class, 'update'])->name('admin.bulk-editor.update');
    Route::middleware(\App\Http\Middleware\EnsureGoogleSheetsAdmin::class)
        ->prefix('admin/google-sheets')->name('admin.google-sheets.')->group(function () {
            Route::get('{resource}', [\App\Http\Controllers\GoogleSheetSyncController::class, 'show'])->name('show');
            Route::post('{resource}/export', [\App\Http\Controllers\GoogleSheetSyncController::class, 'export'])->name('export');
            Route::post('{resource}/sync', [\App\Http\Controllers\GoogleSheetSyncController::class, 'sync'])->name('sync');
        });
    Route::get('source-exams/create', [SourceExamImportController::class, 'create'])->name('source-exams.create');
    Route::post('source-exams', [SourceExamImportController::class, 'store'])->name('source-exams.store');
    Route::post('source-exams/process-selected', [SourceExamImportController::class, 'processSelected'])->name('source-exams.process-selected');
    Route::post('source-exams/publish-selected', [SourceExamImportController::class, 'publishSelected'])->name('source-exams.publish-selected');
    Route::post('source-exams/audit-selected', [SourceExamImportController::class, 'auditSelected'])->name('source-exams.audit-selected');
    Route::delete('source-exams/{sourceExam}', [SourceExamImportController::class, 'destroy'])->name('source-exams.destroy');
    Route::get('source-exams/{sourceExam}/preview', [SourceExamImportController::class, 'previewPaper'])->name('source-exams.preview');
    Route::get('source-exams/{sourceExam}', [SourceExamImportController::class, 'show'])->name('source-exams.show');
    Route::get('source-exams/{sourceExam}/drafts/{draft}/preview', [SourceExamImportController::class, 'previewDraft'])->name('source-exams.drafts.preview');
    Route::get('source-exams/{sourceExam}/drafts/{draft}/source-page', [SourceExamImportController::class, 'sourceDraftPage'])->name('source-exams.drafts.source-page');
    Route::patch('source-exams/{sourceExam}/drafts/{draft}/crop', [SourceExamImportController::class, 'cropDraftImage'])->name('source-exams.drafts.crop');
    Route::get('source-exams/{sourceExam}/drafts/{draft}/edit', [SourceExamImportController::class, 'editDraft'])->name('source-exams.drafts.edit');
    Route::patch('source-exams/{sourceExam}/drafts/{draft}', [SourceExamImportController::class, 'updateDraft'])->name('source-exams.drafts.update');
    Route::patch('source-exams/{sourceExam}/drafts/{draft}/structured-content', [SourceExamImportController::class, 'reviewStructuredContent'])->name('source-exams.drafts.structured-content');
    Route::post('source-exams/{sourceExam}/publish', [SourceExamImportController::class, 'publish'])->name('source-exams.publish');
    Route::post('source-exams/{sourceExam}/retry', [SourceExamImportController::class, 'retry'])->name('source-exams.retry');

    Route::middleware('plan.feature:exam_quality_audit')->group(function () {
    Route::get('ai-answers', [AiAnswerController::class, 'index'])->name('ai-answers.index');
    Route::get('image-cleanup', [ImageCleanupController::class, 'index'])->name('image-cleanup.index');
    Route::get('image-cleanup/exams/search', [ImageCleanupController::class, 'examSearch'])->name('image-cleanup.exams.search');
    Route::get('image-cleanup/exams/selection-summary', [ImageCleanupController::class, 'selectionSummary'])->name('image-cleanup.exams.selection-summary');
    Route::get('image-cleanup/exams/{exam}/images', [ImageCleanupController::class, 'images'])->name('image-cleanup.exams.images');
    Route::post('image-cleanup', [ImageCleanupController::class, 'store'])->name('image-cleanup.store');
    Route::post('image-cleanup/{run}/stop', [ImageCleanupController::class, 'stop'])->name('image-cleanup.stop');
    Route::post('image-cleanup/{run}/retry', [ImageCleanupController::class, 'retry'])->name('image-cleanup.retry');
    Route::post('image-cleanup/{run}/reprocess-paper', [ImageCleanupController::class, 'reprocessPaper'])->name('image-cleanup.reprocess-paper');
    Route::post('image-cleanup/{run}/items/{item}/reprocess', [ImageCleanupController::class, 'reprocessItem'])->name('image-cleanup.items.reprocess');
    Route::post('image-cleanup/{run}/publish', [ImageCleanupController::class, 'publish'])->name('image-cleanup.publish');
    Route::post('image-cleanup/{run}/reject', [ImageCleanupController::class, 'reject'])->name('image-cleanup.reject');
    Route::post('image-cleanup/publish-runs', [ImageCleanupController::class, 'publishRuns'])->name('image-cleanup.publish-runs');
    Route::get('image-cleanup/{run}', [ImageCleanupController::class, 'show'])->name('image-cleanup.show');
    Route::middleware('plan.feature:exam_quality_ai')->group(function () {
        Route::get('content-normalization', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'index'])->name('content-normalization.index');
        Route::get('content-normalization/exams/search', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'examSearch'])->name('content-normalization.exams.search');
        Route::get('content-normalization/exams/selection-summary', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'selectionSummary'])->name('content-normalization.exams.selection-summary');
        Route::post('content-normalization/runs', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'store'])->name('content-normalization.runs.store');
        Route::post('content-normalization/runs/{run}/step', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'step'])->name('content-normalization.runs.step');
        Route::post('content-normalization/runs/{run}/restore', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'startRestore'])->name('content-normalization.runs.restore');
        Route::post('content-normalization/runs/{run}/restore-step', [\App\Http\Controllers\ContentNormalizationAdminController::class, 'restoreStep'])->name('content-normalization.runs.restore-step');
    });
    Route::get('image-converter', [ImageConversionController::class, 'index'])->name('image-converter.index');
    Route::post('image-converter/preview', [ImageConversionController::class, 'preview'])->name('image-converter.preview');
    Route::post('image-converter', [ImageConversionController::class, 'store'])->name('image-converter.store');
    Route::post('image-converter/items/{item}/retry', [ImageConversionController::class, 'retry'])->name('image-converter.items.retry');
    Route::get('ai-answers/exams/search', [AiAnswerController::class, 'examSearch'])->name('ai-answers.exams.search');
    Route::post('ai-answers', [AiAnswerController::class, 'store'])->name('ai-answers.store');
    Route::post('ai-answers/publish', [AiAnswerController::class, 'publish'])->name('ai-answers.publish');
    Route::post('ai-answers/papers/publish', [AiAnswerController::class, 'publishPapers'])->name('ai-answers.papers.publish');
    Route::get('ai-answers/questions/{question}/versions', [AiAnswerController::class, 'versions'])->name('ai-answers.questions.versions');
    Route::patch('ai-answers/versions/{version}/restore', [AiAnswerController::class, 'restoreVersion'])->name('ai-answers.versions.restore');
    Route::post('ai-answers/{run}/retry', [AiAnswerController::class, 'retry'])->name('ai-answers.retry');
    Route::get('ai-answers/{run}', [AiAnswerController::class, 'show'])->name('ai-answers.show');
    Route::patch('ai-answers/drafts/{draft}/approve', [AiAnswerController::class, 'approve'])->name('ai-answers.drafts.approve');
    Route::get('exam-quality', [ExamQualityAuditController::class, 'index'])->name('exam-quality.index');
    Route::get('exam-quality/exams/search', [ExamQualityAuditController::class, 'examSearch'])->name('exam-quality.exams.search');
    Route::post('exam-quality', [ExamQualityAuditController::class, 'store'])->name('exam-quality.store');
    Route::get('exam-quality/releases', [ExamQualityAuditController::class, 'releaseIndex'])->name('exam-quality.releases.index');
    Route::post('exam-quality/{audit}/start', [ExamQualityAuditController::class, 'startNow'])->name('exam-quality.start');
    Route::post('exam-quality/{audit}/stop', [ExamQualityAuditController::class, 'stop'])->name('exam-quality.stop');
    Route::post('exam-quality/papers/publish', [ExamQualityAuditController::class, 'publishSelectedPapers'])->name('exam-quality.papers.publish');
    Route::get('exam-quality/{audit}/preview', [ExamQualityAuditController::class, 'previewPaper'])->name('exam-quality.preview');
    Route::get('exam-quality/{audit}', [ExamQualityAuditController::class, 'show'])->name('exam-quality.show');
    Route::patch('exam-quality/findings/{finding}', [ExamQualityAuditController::class, 'updateFinding'])->name('exam-quality.findings.update');
    Route::post('exam-quality/repairs/batch', [ExamQualityAuditController::class, 'queueRepairBatch'])->name('exam-quality.repairs.batch');
    Route::post('exam-quality/{audit}/repairs/review', [ExamQualityAuditController::class, 'reviewRepairBatch'])->name('exam-quality.repairs.batch-review');
    Route::post('exam-quality/{audit}/repairs/publish', [ExamQualityAuditController::class, 'publishRepairBatch'])->name('exam-quality.repairs.batch-publish');
    Route::get('exam-quality/releases/{release}', [ExamQualityAuditController::class, 'showRelease'])->name('exam-quality.releases.show');
    Route::patch('exam-quality/releases/{release}/restore', [ExamQualityAuditController::class, 'restoreRepairRelease'])->name('exam-quality.releases.restore');
    Route::post('exam-quality/{audit}/repairs', [ExamQualityAuditController::class, 'queueRepairs'])->name('exam-quality.repairs.queue');
    Route::get('exam-quality/repairs/{draft}', [ExamQualityAuditController::class, 'showRepair'])->name('exam-quality.repairs.show');
    Route::get('exam-quality/repairs/{draft}/preview', [ExamQualityAuditController::class, 'previewRepair'])->name('exam-quality.repairs.preview');
    Route::get('exam-quality/repairs/{draft}/source-page', [ExamQualityAuditController::class, 'sourcePage'])->name('exam-quality.repairs.source-page');
    Route::patch('exam-quality/repairs/{draft}/crop', [ExamQualityAuditController::class, 'cropRepairImage'])->name('exam-quality.repairs.crop');
    Route::patch('exam-quality/versions/{version}/restore', [ExamQualityAuditController::class, 'restoreVersion'])->name('exam-quality.versions.restore');
    Route::patch('exam-quality/repairs/{draft}', [ExamQualityAuditController::class, 'updateRepair'])->name('exam-quality.repairs.update');
    Route::patch('exam-quality/repairs/{draft}/structured-content', [ExamQualityAuditController::class, 'reviewStructuredContent'])->name('exam-quality.repairs.structured-content');
    Route::get('exam-quality/repairs/{draft}/structured-content/{item}/source', [ExamQualityAuditController::class, 'structuredContentSource'])->name('exam-quality.repairs.structured-content-source');
    Route::patch('exam-quality/repairs/{draft}/publish', [ExamQualityAuditController::class, 'publishRepair'])->name('exam-quality.repairs.publish');
    Route::patch('exam-quality/repairs/{draft}/reject', [ExamQualityAuditController::class, 'rejectRepair'])->name('exam-quality.repairs.reject');
    Route::patch('exam-quality/repairs/{draft}/retry', [ExamQualityAuditController::class, 'retryRepair'])->name('exam-quality.repairs.retry');
    });

    Route::middleware('plan.feature:public_website')->group(function () {
        Route::get('/homepage-content', [HomepageContentController::class, 'index'])->name('homepage-content.index');
        Route::resource('/features', FeaturesController::class);
        Route::resource('/counters', CountersController::class);
        Route::resource('/testimonial', TestimonialController::class);
        Route::resource('/websitepages', WebsitePageController::class);
        Route::post('/website/title/update', [TitleController::class, 'update_title'])->name('website.title.update');
        Route::get('configurations/website', [ConfigurationController::class, 'editWebsiteSettings'])->name('configurations.website');
        Route::put('configurations/website', [ConfigurationController::class, 'updateWebsiteSettings'])->name('configurations.website.update');
        Route::get('navigation', [NavigationController::class, 'index'])->name('navigation.index');
        Route::post('navigation', [NavigationController::class, 'store'])->name('navigation.store');
        Route::post('navigation/defaults', [NavigationController::class, 'initializeDefaults'])->name('navigation.defaults');
        Route::put('navigation/{navigation}', [NavigationController::class, 'update'])->name('navigation.update');
        Route::delete('navigation/{navigation}', [NavigationController::class, 'destroy'])->name('navigation.destroy');
        Route::post('navigation/reorder', [NavigationController::class, 'reorder'])->name('navigation.reorder');
        Route::post('navigation/toggle', [NavigationController::class, 'toggle'])->name('navigation.toggle');
        Route::post('navigation/footer-headings', [NavigationController::class, 'updateFooterHeadings'])->name('navigation.footer-headings');
    });

    Route::resource('/payment-gateway', PaymentGatewayController::class)->middleware('platform.admin');

    Route::middleware('plan.feature:paid_packages')->group(function () {
        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [OrdersController::class, 'show'])->name('orders.show');
        Route::post('/orders/{id}/update-status', [OrdersController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::get('/transactions', [OrdersController::class, 'transactions'])->name('transactions.index');
        Route::resource('coupons', CouponController::class);
    });

    Route::middleware('plan.feature:reports')->group(function () {
        Route::get('/sales-reports', [OrdersController::class, 'salesReports'])->name('sales-reports.index');
        Route::get('/student-funnel', [StudentFunnelReportController::class, 'index'])->name('results.student-funnel');
        Route::post('/student-funnel/exclusions', [StudentFunnelReportController::class, 'storeExclusion'])->name('results.student-funnel.exclusions.store');
        Route::delete('/student-funnel/exclusions/{exclusion}', [StudentFunnelReportController::class, 'destroyExclusion'])->name('results.student-funnel.exclusions.destroy');
    });

    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard'); 
    Route::get('exams/{exam}/view', [ExamController::class, 'view'])->name('exams.view');
    Route::resource('groups', GroupController::class);

    Route::resource('category', CategoryController::class);
    Route::get('subcategories', [CategoryController::class, 'subcategories'])->name('subcategories.index');
    Route::post('subcategories', [CategoryController::class, 'storeSubcategory'])->name('subcategories.store');
    Route::put('subcategories/{category}', [CategoryController::class, 'update'])->name('subcategories.update');
    Route::delete('subcategories/{category}', [CategoryController::class, 'destroy'])->name('subcategories.destroy');

    Route::resource('ugroups', UgroupController::class);
    Route::get('ugroups/{id}/rights', [UgroupController::class, 'getPageRights']);
    Route::post('pagerights', [UgroupController::class, 'storePageRights'])->name('pagerights.store');

    Route::get('/download-template', [ImportExportController::class, 'downloadTemplate'])->name('questions.downloadTemplate');
    Route::post('question/import', [ImportExportController::class, 'import'])->middleware('plan.limit:questions')->name('questions.import');
    Route::post('question/import/large', [QuestionLargeImportController::class, 'initialize'])->middleware('plan.limit:questions')->name('questions.large-import.initialize');
    Route::post('question/import/large/{uploadId}/chunk', [QuestionLargeImportController::class, 'uploadChunk'])->name('questions.large-import.chunk');
    Route::post('question/import/large/{uploadId}/finalize', [QuestionLargeImportController::class, 'finalize'])->name('questions.large-import.finalize');
    Route::get('question/import/large/{uploadId}/status', [QuestionLargeImportController::class, 'status'])->name('questions.large-import.status');
    Route::get('question/import/large/{uploadId}/errors', [QuestionLargeImportController::class, 'errors'])->name('questions.large-import.errors');
    Route::get('/pagerights/{ugroup_id}', [UgroupController::class, 'getPageRights']);
    Route::resource('users', UserController::class);
    
    // Student Routes

    // ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ NEW ROUTES: Student Import/Export Added Here ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦
    Route::get('students/export', [StudentAdminController::class, 'export'])->name('students.export');
    Route::post('students/import', [StudentAdminController::class, 'import'])->middleware('plan.limit:students')->name('students.import');
    Route::get('students/download-template', [StudentAdminController::class, 'downloadTemplate'])->name('students.downloadTemplate');

    Route::get('students/{student}/identity', [StudentAdminController::class, 'identity'])->name('students.identity');
    Route::resource('students', StudentAdminController::class);
    Route::post('students/remove', [StudentAdminController::class, 'bulkRemove'])->name('students.remove');
    Route::post('students/bulk-assign-group', [StudentAdminController::class, 'bulkAssignGroup'])->name('students.bulkAssignGroup'); 
    Route::middleware('platform.admin')->group(function () {
        Route::get('demo-students', [DemoStudentController::class, 'index'])->name('demo-students.index');
        Route::post('demo-students/generate', [DemoStudentController::class, 'generate'])->name('demo-students.generate');
        Route::post('demo-students/delete-batch', [DemoStudentController::class, 'deleteBatch'])->name('demo-students.deleteBatch');
        Route::delete('demo-students/{student}', [DemoStudentController::class, 'destroy'])->name('demo-students.destroy');
    });

    Route::post('subjects/bulk-delete', [SubjectController::class, 'bulkDestroy'])->name('subjects.bulkDestroy');
    Route::resource('subjects', SubjectController::class);

    Route::get('sections/by-groups', [SectionController::class, 'byGroups'])->name('sections.byGroups');
    Route::post('sections/bulk-delete', [SectionController::class, 'bulkDestroy'])->name('sections.bulkDestroy');
    Route::resource('sections', SectionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('topics/subjects-by-groups', [TopicController::class, 'getSubjectsByGroup'])->name('topics.getSubjectsByGroup');
    Route::post('topics/bulk-delete', [TopicController::class, 'bulkDestroy'])->name('topics.bulk-destroy');
    Route::resource('topics', TopicController::class);
    
    // Sub-topics Resource
    Route::get('stopics/get-subjects-by-group', [StopicController::class, 'getSubjectsByGroup'])->name('stopics.get-subjects-by-group');
    Route::get('stopics/get-topics-by-subject', [StopicController::class, 'getTopicsBySubject'])->name('stopics.get-topics-by-subject');
    Route::post('stopics/bulk-delete', [StopicController::class, 'bulkDestroy'])->name('stopics.bulk-destroy');
    Route::resource('stopics', StopicController::class);
    
    Route::resource('languages', LanguageController::class);
    Route::resource('passages', PassageController::class);
    Route::get('diffs', [DiffController::class, 'index'])->name('diffs.index');
    Route::put('diffs/{diff}', [DiffController::class, 'update'])->middleware('platform.admin')->name('diffs.update');
    Route::get('qtypes', [QtypeController::class, 'index'])->name('qtypes.index');
    Route::put('qtypes/{qtype}', [QtypeController::class, 'update'])->middleware('platform.admin')->name('qtypes.update');
    Route::get('questions/get-subjects-by-group', [QuestionController::class, 'getSubjectsByGroup'])->name('questions.get-subjects-by-group');
    Route::get('questions/get-sections-by-group', [QuestionController::class, 'getSectionsByGroup'])->name('questions.get-sections-by-group');
    Route::get('questions/{question}/exam-assignments', [QuestionExamAssignmentController::class, 'index'])->name('questions.exam-assignments.index');
    Route::put('questions/{question}/exam-assignments', [QuestionExamAssignmentController::class, 'update'])->name('questions.exam-assignments.update');
    Route::resource('questions', QuestionController::class);

    Route::post('questions/remove', [QuestionController::class, 'remove'])->name('questions.remove');
    Route::get('questions/{question}/langs/create', [QuestionLangController::class, 'create'])->name('questions_langs.create');
    Route::post('questions/{question}/langs', [QuestionLangController::class, 'store'])->name('questions_langs.store');
    Route::get('/questions_langs/check/{questionId}/{languageId}', [QuestionLangController::class, 'check']);
    Route::post('/questions_langs/update/{id}', [QuestionLangController::class, 'update'])->name('questions_langs.update');
    
    Route::get('/packages/categoryLevel3', [PackageController::class, 'categoryLevel3'])->name('packages.categoryLevel3');
    Route::resource('package-tags', PackageTagController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('question-tags', QuestionTagController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::middleware('plan.feature:flashcards')->group(function () {
        Route::get('flashcards', [FlashcardController::class, 'index'])->name('flashcards.index');
        Route::post('flashcards', [FlashcardController::class, 'store'])->name('flashcards.store');
        Route::get('flashcards/{flashcardSet}', [FlashcardController::class, 'show'])->name('flashcards.show');
        Route::post('flashcards/{flashcardSet}/ai-generate', [FlashcardController::class, 'generateAiCards'])->name('flashcards.ai-generate');
        Route::put('flashcards/{flashcardSet}', [FlashcardController::class, 'update'])->name('flashcards.update');
        Route::delete('flashcards/{flashcardSet}', [FlashcardController::class, 'destroy'])->name('flashcards.destroy');
        Route::get('flashcards/{flashcardSet}/cards/create', [FlashcardController::class, 'createCard'])->name('flashcards.cards.create');
        Route::post('flashcards/{flashcardSet}/cards/from-questions', [FlashcardController::class, 'createCardsFromQuestions'])->name('flashcards.cards.from-questions');
        Route::post('flashcards/{flashcardSet}/cards/add-selected', [FlashcardController::class, 'addSelectedQuestions'])->name('flashcards.cards.add-selected');
        Route::post('flashcards/{flashcardSet}/cards/link-by-scope', [FlashcardController::class, 'linkQuestionsByScope'])->name('flashcards.cards.link-by-scope');
        Route::post('flashcards/{flashcardSet}/cards', [FlashcardController::class, 'storeCard'])->name('flashcards.cards.store');
        Route::post('flashcards/{flashcardSet}/cards/bulk-status', [FlashcardController::class, 'bulkUpdateCards'])->name('flashcards.cards.bulk-status');
        Route::get('flashcards/{flashcardSet}/cards/template', [FlashcardController::class, 'downloadCardTemplate'])->name('flashcards.cards.template');
        Route::get('flashcards/{flashcardSet}/cards/export', [FlashcardController::class, 'exportCards'])->name('flashcards.cards.export');
        Route::post('flashcards/{flashcardSet}/cards/import', [FlashcardController::class, 'importCards'])->name('flashcards.cards.import');
        Route::get('flashcards/{flashcardSet}/cards/{flashcard}/edit', [FlashcardController::class, 'editCard'])->name('flashcards.cards.edit');
        Route::put('flashcards/{flashcardSet}/cards/{flashcard}', [FlashcardController::class, 'updateCard'])->name('flashcards.cards.update');
        Route::delete('flashcards/{flashcardSet}/cards/{flashcard}', [FlashcardController::class, 'destroyCard'])->name('flashcards.cards.destroy');
    });
    Route::resource('packages', PackageController::class);
    
    Route::post('/packages/{package}/toggle-status', [PackageController::class, 'toggleStatus'])->name('packages.toggleStatus');

    // Exam Routes
    Route::get('filters/dependent-options', [ExamController::class, 'dependentFilterOptions'])->name('filters.dependent-options');
    Route::get('exams/import-export', [ExamImportExportController::class, 'index'])->name('exams.import.index');
    Route::get('exams/export/workbook', [ExamImportExportController::class, 'export'])->name('exams.export');
    Route::post('exams/import/preview', [ExamImportExportController::class, 'preview'])->name('exams.import.preview');
    Route::post('exams/import/apply', [ExamImportExportController::class, 'apply'])->name('exams.import.apply');
    Route::get('exams/{exam}/quality-sources/{source}/download', [ExamImportExportController::class, 'downloadSource'])->name('exams.sources.download');

    Route::get('exams/packages', [ExamController::class, 'getExamPackages'])->name('exams.getExamPackages');
    Route::get('exams/packages/exams', [ExamController::class, 'getExamExams'])->name('exams.getExamExam');
    Route::get('exams/{exam}/paper/preview', [ExamController::class, 'previewPaper'])->name('exams.paper.preview');
    Route::get('exams/{exam}/paper/edit', [ExamController::class, 'editPaper'])->name('exams.paper.edit');
    Route::patch('exams/{exam}/paper/publish', [ExamController::class, 'publishPaper'])->name('exams.paper.publish');
    Route::get('exams/{exam}/paper/questions/{draft}/edit', [ExamController::class, 'editPaperQuestion'])->name('exams.paper.questions.edit');
    Route::get('exams/{exam}/paper/questions/{draft}/source-page', [ExamController::class, 'paperDraftSourcePage'])->name('exams.paper.questions.source-page');
    Route::patch('exams/{exam}/paper/questions/{draft}/crop', [ExamController::class, 'cropPaperQuestion'])->name('exams.paper.questions.crop');
    Route::patch('exams/{exam}/paper/questions/{draft}/remove-image', [ExamController::class, 'removePaperQuestionImage'])->name('exams.paper.questions.remove-image');
    Route::patch('exams/{exam}/paper/questions/{draft}', [ExamController::class, 'updatePaperQuestion'])->name('exams.paper.questions.update');
    Route::patch('exams/{exam}/paper/questions/{draft}/publish', [ExamController::class, 'publishPaperQuestion'])->name('exams.paper.questions.publish');
    Route::resource('exams', ExamController::class);
    Route::get('exams/{exam}/analytics', [ExamController::class, 'analytics'])->name('exams.analytics');
    Route::get('exams/{exam}/subjects', [ExamController::class, 'getSubjects'])->name('exams.getSubjects');
    Route::middleware('plan.feature:reports')->group(function () {
        Route::get('exams/reports', [ExamController::class, 'show'])->name('exams.reports');
        Route::get('exams/study-card-reports', [ExamController::class, 'studyCardReports'])->name('exams.studyCardReports');
        Route::patch('exams/reports/{report}/status', [ExamController::class, 'updateReportStatus'])->name('exams.updateReportStatus');
    });

    Route::post('exams/{exam}/set-section-wise-timer', [ExamController::class, 'setSectionWiseTimer'])->name('exams.setSectionWiseTimer');
    Route::post('/exams/{exam}/toggle-status', [ExamController::class, 'toggleStatus'])->name('exams.toggleStatus');
    Route::post('/exams/{exam}/toggle-result', [ExamController::class, 'toggleResultStatus'])->name('exams.toggleResult');
    Route::patch('/exams/{exam}/attempt-limit', [ExamController::class, 'updateAttemptLimit'])->name('exams.updateAttemptLimit');
    Route::get('/exams/{exam}/solution-pdf', [App\Http\Controllers\ExamPrintController::class, 'adminSolutionDownload'])->middleware('throttle:10,1')->name('exams.solutionPdf');

    Route::prefix('exam-documents')->name('exam-documents.')->group(function () {
        Route::get('/', [App\Http\Controllers\ExamDocumentController::class, 'index'])->name('index');
        Route::post('/{exam}/languages/{language}/translate', [App\Http\Controllers\ExamDocumentController::class, 'translate'])->name('translate');
        Route::post('/{exam}/languages/{language}/approve', [App\Http\Controllers\ExamDocumentController::class, 'approve'])->name('approve');
        Route::post('/{exam}/languages/{language}/generate', [App\Http\Controllers\ExamDocumentController::class, 'generate'])->name('generate');
        Route::patch('/{exam}/languages/{language}/automation', [App\Http\Controllers\ExamDocumentController::class, 'automation'])->name('automation');
    });

    Route::get('exams/{exam}/add-questions', [ExamController::class, 'addQuestions'])->name('exams.addQuestions');
    Route::post('/exams/{exam}/toggle-question', [ExamController::class, 'toggleQuestion'])->name('exams.toggleQuestion');
    Route::post('/exams/{exam}/bulk-add-questions', [ExamController::class, 'bulkAddQuestions'])->name('exams.bulkAddQuestions');
    Route::get('exams/{exam}/view-questions', [ExamController::class, 'viewQuestions'])->name('exams.viewQuestions');
    Route::post('exams/{exam}/sections', [ExamController::class, 'storeSection'])->name('exams.sections.store');
    Route::put('exams/{exam}/sections/{section}', [ExamController::class, 'updateSection'])->name('exams.sections.update');
    Route::delete('exams/{exam}/sections/{section}', [ExamController::class, 'destroySection'])->name('exams.sections.destroy');
    Route::post('exams/{exam}/question-sections', [ExamController::class, 'assignQuestionSections'])->name('exams.sections.assign');
    
    // ==========================================
    // ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ RESULT ROUTES (With Manual Evaluation)
    // ==========================================
    Route::middleware('plan.feature:reports')->group(function () {
        Route::get('/results', [ResultController::class, 'index'])->name('results.index');
        Route::get('/results/view/{id}', [ResultController::class, 'view'])->name('results.view');
        Route::get('/results/{id}/evaluate', [ResultController::class, 'evaluate'])->name('results.evaluate');
        Route::post('/results/{id}/save-evaluation', [ResultController::class, 'saveEvaluation'])->name('results.saveEvaluation');
        Route::get('/results/{id}/feedback', [ResultController::class, 'viewFeedback'])->name('results.feedback');
    });

    Route::get('configurations/logo-favicon', [ConfigurationController::class, 'editLogoFavicon'])->name('configurations.logo-favicon');
    Route::post('configurations/logo-favicon', [ConfigurationController::class, 'updateLogoFavicon'])->name('configurations.updateLogoFavicon');
Route::middleware('platform.admin')->group(function () {
    Route::get('configurations/analytics', [\App\Http\Controllers\GoogleAnalyticsController::class, 'index'])->name('configurations.analytics');
    Route::put('configurations/analytics', [\App\Http\Controllers\GoogleAnalyticsController::class, 'update'])->middleware('throttle:10,1')->name('configurations.analytics.update');
    Route::get('saas', [\App\Http\Controllers\SaasController::class, 'index'])->name('saas.index');
    Route::get('saas/question-sharing', [\App\Http\Controllers\SaasQuestionSharingController::class, 'index'])->name('saas.question-sharing.index');
    Route::post('saas/question-sharing/share', [\App\Http\Controllers\SaasQuestionSharingController::class, 'shareToOrganizations'])->name('saas.question-sharing.share');
    Route::post('saas/question-sharing/copy-to-master', [\App\Http\Controllers\SaasQuestionSharingController::class, 'copyToMaster'])->name('saas.question-sharing.copy-to-master');
    Route::put('saas/plans/{plan}', [\App\Http\Controllers\SaasController::class, 'updatePlan'])->name('saas.plans.update');
    Route::post('saas/plans', [\App\Http\Controllers\SaasController::class, 'storePlan'])->name('saas.plans.store');
    Route::put('saas/organizations/{organization}', [\App\Http\Controllers\SaasController::class, 'updateOrganization'])->name('saas.organizations.update');
    Route::get('saas/organizations/{organization}/website-settings', [ConfigurationController::class, 'editOrganizationWebsiteSettings'])->name('saas.organizations.website');
    Route::put('saas/organizations/{organization}/website-settings', [ConfigurationController::class, 'updateOrganizationWebsiteSettings'])->name('saas.organizations.website.update');
    Route::post('saas/organizations', [\App\Http\Controllers\SaasController::class, 'storeOrganization'])->name('saas.organizations.store');
    Route::post('saas/organizations/{organization}/attendance-onboarding', [\App\Http\Controllers\SaasController::class, 'retryAttendanceOnboarding'])->middleware('throttle:10,1')->name('saas.organizations.attendance-onboarding');
    Route::post('saas/organization-users/assign', [\App\Http\Controllers\SaasController::class, 'assignUser'])->name('saas.organization-users.assign');
    Route::post('saas/organizations/{organization}/admin-users/{user}/attendance', [\App\Http\Controllers\SaasController::class, 'retryAttendanceAdministrator'])->middleware('throttle:10,1')->name('saas.organizations.admin-users.attendance');
    Route::post('saas/organizations/{organization}/admin-users', [\App\Http\Controllers\SaasController::class, 'storeOrganizationAdmin'])->name('saas.organizations.admin-users.store');
    Route::post('saas/platform-admins', [\App\Http\Controllers\SaasController::class, 'storePlatformAdmin'])->name('saas.platform-admins.store');
    Route::patch('saas/admin-users/{user}/email', [\App\Http\Controllers\SaasController::class, 'updateAdministratorEmail'])->name('saas.admin-users.email.update');
});
    Route::get('configurations/general', [ConfigurationController::class, 'editGeneral'])->name('configurations.general');
    Route::put('configurations/general', [ConfigurationController::class, 'updateGeneral'])->name('configurations.update');
    Route::middleware('plan.feature:sms_messaging')->group(function () {
        Route::get('configurations/messaging', [MessagingSettingsController::class, 'edit'])->name('configurations.messaging');
        Route::put('configurations/messaging', [MessagingSettingsController::class, 'update'])->name('configurations.messaging.update');
    });

    Route::middleware('plan.feature:ai_settings')->group(function () {
        Route::get('configurations/ai', [ConfigurationController::class, 'editAiSettings'])->name('configurations.ai');
        Route::put('configurations/ai', [ConfigurationController::class, 'updateAiSettings'])->name('configurations.ai.update');
    });

    Route::get('question/import-export', [ImportExportController::class, 'index'])->name('questions.importExport');
    
    Route::get('question/export', [ImportExportController::class, 'export'])->name('questions.export');
    Route::get('/get-topics-by-subject/{subjectId}', [ImportExportController::class, 'getTopicsBySubject']);
    Route::get('/get-subtopics-by-topic/{topicId}', [ImportExportController::class, 'getSubtopicsByTopic']);
    Route::get('/export-questions/{subjectId}/{topicId}/{subtopicId}', [ImportExportController::class, 'exportQuestions']);
    Route::get('/get-subjects-by-group', [ImportExportController::class, 'getSubjectsByGroup']);
    
    // ======== EMAIL SYSTEM UPDATES ========
    Route::middleware('plan.feature:email_messaging')->group(function () {
        Route::resource('email-templates', EmailTemplateController::class);
        Route::resource('email-settings', EmailSettingController::class);
        Route::post('email-settings/test', [EmailSettingController::class, 'sendTestEmail'])->name('email-settings.test');
        Route::get('send-email', [EmailSettingController::class, 'sendEmailForm'])->name('send-email-form');
        Route::post('send-email', [EmailSettingController::class, 'sendEmail'])->name('send-email');
        Route::get('/students-email/search', [EmailSettingController::class, 'searchStudents'])->name('students.email.search');
    });

    Route::middleware('plan.feature:sms_messaging')->group(function () {
        Route::resource('sms-templates', SmsTemplateController::class);
    });
    Route::post('/upload-image', [ImageUploadController::class, 'upload'])->name('upload-image');
});

Route::middleware(['auth','permission:system.update'])
    ->prefix('admin/update')->as('admin.update.')
    ->group(function () {
        Route::get('/',      [\App\Http\Controllers\UpdateController::class,'index'])->name('index');
        Route::get('/check', [\App\Http\Controllers\UpdateController::class,'check'])->name('check');
        Route::post('/apply',[\App\Http\Controllers\UpdateController::class,'apply'])->name('apply');
    });

Route::get('/storage/{path}', function ($path) {
    $base = realpath(storage_path('app/public'));
    $full = realpath(storage_path('app/public/' . ltrim((string) $path, '/\\')));
    abort_unless(
        $base !== false
        && $full !== false
        && is_file($full)
        && str_starts_with(strtolower($full), strtolower($base . DIRECTORY_SEPARATOR)),
        404
    );
    $mime = @mime_content_type($full) ?: 'application/octet-stream';
    return response()->file($full, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=604800' 
    ]);
})->where('path', '.*');


// ==========================================
// AI QUESTION GENERATOR ROUTES
// ==========================================
Route::middleware(['auth', 'checkPageRights', 'plan.feature:ai_generator'])->group(function () {
    Route::post('/subjects/by-groups', [App\Http\Controllers\ImportExportController::class, 'getSubjectsByGroups'])->name('subjects.by.groups');
    Route::post('/topics/by-subject', [App\Http\Controllers\ImportExportController::class, 'getTopicsBySubject'])->name('topics.by.subject');
    Route::post('/subtopics/by-topic', [App\Http\Controllers\ImportExportController::class, 'getSubtopicsByTopic'])->name('subtopics.by.topic');
    Route::get('/question/ai-generator', [App\Http\Controllers\ImportExportController::class, 'showAiGenerator'])->name('ai.generator.form');
    Route::post('/question/ai-generator', [App\Http\Controllers\ImportExportController::class, 'runAiGenerator'])->name('ai.generator.run');
});

Route::middleware(['auth', 'checkPageRights', 'plan.feature:ai_translation'])->group(function () {
    Route::post('/ai/translate', [App\Http\Controllers\AITranslationController::class, 'translate'])->name('ai.translate');
    Route::post('/exams/{exam}/languages/{language}/prepare', [App\Http\Controllers\AdminExamLanguageController::class, 'prepare'])->name('exams.languages.prepare');
});

Route::middleware(['auth', 'checkPageRights', 'plan.feature:ai_content_generation'])->group(function () {
    Route::post('/ai-content/generate', [App\Http\Controllers\AIContentController::class, 'generate'])->name('ai.content.generate');
});

Route::middleware(['auth', 'checkPageRights', 'plan.feature:ai_subjective_analysis'])->group(function () {
    Route::post('/ai/subjective/bulk-assess', [App\Http\Controllers\AISubjectiveAssessmentController::class, 'bulkAssess'])->name('ai.subjective.bulk');
});

Route::middleware(['auth', 'checkPageRights', 'plan.feature:ai_regeneration'])->group(function () {
    Route::post('/ai-regenerator/regenerate', [App\Http\Controllers\AIQuestionRegeneratorController::class, 'regenerate'])->name('ai.regenerator.regenerate');
});

// PDF Print Route
Route::post('exam-print/{id}/intent', [App\Http\Controllers\ExamPrintController::class, 'downloadIntent'])->middleware('throttle:10,1')->name('exam.print.intent');
Route::post('exam-solutions/{id}/activity', [App\Http\Controllers\ExamPrintController::class, 'solutionActivity'])->middleware('throttle:30,1')->name('exam.solution.activity');
Route::get('exam-print/{id}/download', [App\Http\Controllers\ExamPrintController::class, 'download'])->middleware('throttle:15,1')->name('exam.print.download');
Route::get('exam-print/{id}', [App\Http\Controllers\ExamPrintController::class, 'print'])->name('exam.print');

Route::get('/robots.txt', function () {
    $host = request()->getHost();
    $isStagingHost = str_ends_with($host, 'examways.org') || str_contains($host, 'staging');

    $content = "User-agent: *\n";

    if ($isStagingHost) {
        $content .= "Disallow: /\n";
        return response($content, 200)->header('Content-Type', 'text/plain');
    }

    $content .= "Allow: /\n";
    $content .= "Disallow: /admin\n";
    $content .= "Disallow: /student\n";
    $content .= "Disallow: /checkout\n";
    $content .= "Disallow: /cart\n";
    $content .= "Disallow: /exam/start\n";
    $content .= "Disallow: /guest/exam/start\n";
    $content .= "Disallow: /exam-print/\n";
    $content .= "Disallow: /student/exam-solutions/\n";
    $content .= "Sitemap: " . url('/sitemap.xml') . "\n";

    return response($content, 200)->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $tenantIdForSitemap = class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;

    $urls = collect([
        ['loc' => url('/'), 'lastmod' => now()->toAtomString(), 'priority' => '1.0'],
        ['loc' => url('/courses'), 'lastmod' => now()->toAtomString(), 'priority' => '0.9'],
        ['loc' => url('/about'), 'lastmod' => now()->toAtomString(), 'priority' => '0.5'],
        ['loc' => url('/contact'), 'lastmod' => now()->toAtomString(), 'priority' => '0.5'],
        ['loc' => url('/for-institutes'), 'lastmod' => now()->toAtomString(), 'priority' => '0.6'],
    ]);

    if (\Illuminate\Support\Facades\Schema::hasTable('groups')) {
        \App\Models\Group::when($tenantIdForSitemap, fn ($q) => $q->where('organization_id', $tenantIdForSitemap))->get()->each(function ($group) use (&$urls) {
            $groupName = is_array($group->group_name)
                ? ($group->group_name['en'] ?? reset($group->group_name))
                : $group->group_name;

            $decoded = is_string($groupName) ? json_decode($groupName, true) : null;
            if (is_array($decoded)) {
                $groupName = $decoded['en'] ?? reset($decoded);
            }

            $slug = \Illuminate\Support\Str::slug($groupName);

            if ($slug) {
                $urls->push([
                    'loc' => url('/exam-groups/' . $slug),
                    'lastmod' => optional($group->updated_at)->toAtomString() ?: now()->toAtomString(),
                    'priority' => '0.8',
                ]);
            }
        });
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('category')) {
        \App\Models\Category::whereNotNull('slug')->when($tenantIdForSitemap, fn ($q) => $q->where('organization_id', $tenantIdForSitemap))->get()->each(function ($category) use (&$urls) {
            $parent = !empty($category->parent_id) ? \App\Models\Category::find($category->parent_id) : null;

            $loc = ($parent && $parent->slug)
                ? url('/exam-groups/all/' . $parent->slug . '/' . $category->slug)
                : url('/exam-groups/all/' . $category->slug);

            $urls->push([
                'loc' => $loc,
                'lastmod' => optional($category->updated_at)->toAtomString() ?: now()->toAtomString(),
                'priority' => $parent ? '0.7' : '0.8',
            ]);
        });
    }

    \App\Models\Package::where('status', 1)
        ->when($tenantIdForSitemap, fn ($q) => $q->where('organization_id', $tenantIdForSitemap))
        ->whereNotNull('slug')
        ->get()
        ->each(function ($package) use (&$urls) {
            $urls->push([
                'loc' => route('courses.detail', $package->slug),
                'lastmod' => optional($package->updated_at)->toAtomString() ?: now()->toAtomString(),
                'priority' => '0.9',
            ]);

            $pyp = app(\App\Services\PypContentService::class);
            if ($pyp->enabledFor($package)) {
                $dataset = $pyp->dataset($package);
                $settings = $pyp->settings();
                $packageSetting = $pyp->packageSetting($package);
                if (! ($packageSetting?->indexable ?? true)) {
                    return;
                }
                $minimum = (int) $settings['min_questions'];
                $urls->push([
                    'loc' => route('pyp.index', $package->slug),
                    'lastmod' => $dataset['updated_at'],
                    'priority' => '0.9',
                ]);
                if ($settings['analysis_enabled'] && ($packageSetting?->analysis_mode ?? 'historical') !== 'disabled') {
                    $urls->push([
                        'loc' => route('pyp.analysis', $package->slug),
                        'lastmod' => $dataset['updated_at'],
                        'priority' => '0.8',
                    ]);
                }
                if ($settings['subject_pages']) {
                    foreach ($dataset['subjects'] as $subject) {
                        if ($subject['unique_questions'] < $minimum) continue;
                        $urls->push([
                            'loc' => route('pyp.subject', [$package->slug, $subject['token']]),
                            'lastmod' => $dataset['updated_at'],
                            'priority' => '0.8',
                        ]);
                    }
                }
                if ($settings['subject_pages'] && $settings['topic_pages']) {
                    $subjects = collect($dataset['subjects'])->keyBy('id');
                    foreach ($dataset['topics'] as $topic) {
                        if ($topic['unique_questions'] < $minimum || ! $subjects->has($topic['subject_id'])) continue;
                        $urls->push([
                            'loc' => route('pyp.topic', [$package->slug, $subjects[$topic['subject_id']]['token'], $topic['token']]),
                            'lastmod' => $dataset['updated_at'],
                            'priority' => '0.7',
                        ]);
                    }
                }
                if ($settings['subject_pages'] && $settings['topic_pages'] && $settings['subtopic_pages']) {
                    $subjects = collect($dataset['subjects'])->keyBy('id');
                    $topics = collect($dataset['topics'])->keyBy('id');
                    foreach ($dataset['subtopics'] as $subtopic) {
                        $topic = $topics->get($subtopic['topic_id']);
                        $subject = $topic ? $subjects->get($topic['subject_id']) : null;
                        if ($subtopic['unique_questions'] < $minimum || ! $topic || ! $subject) continue;
                        $urls->push([
                            'loc' => route('pyp.subtopic', [$package->slug, $subject['token'], $topic['token'], $subtopic['token']]),
                            'lastmod' => $dataset['updated_at'],
                            'priority' => '0.6',
                        ]);
                    }
                }
            }
        });

    \App\Models\Exam::whereNotNull('slug')
        ->when($tenantIdForSitemap, fn ($q) => $q->where('organization_id', $tenantIdForSitemap))
        ->get()
        ->each(function ($exam) use (&$urls) {
            $urls->push([
                'loc' => route('exam.detail', $exam->slug),
                'lastmod' => optional($exam->updated_at)->toAtomString() ?: now()->toAtomString(),
                'priority' => '0.8',
            ]);
        });

    \App\Models\WebsitePage::whereNotNull('short_title')
        ->when($tenantIdForSitemap, fn ($q) => $q->where('organization_id', $tenantIdForSitemap))
        ->get()
        ->each(function ($page) use (&$urls) {
            $decoded = is_string($page->short_title) ? json_decode($page->short_title, true) : null;
            $slug = is_array($page->short_title)
                ? ($page->short_title['en'] ?? null)
                : (($decoded['en'] ?? null) ?: $page->short_title);

            if ($slug) {
                $urls->push([
                    'loc' => route('page.show', $slug),
                    'lastmod' => optional($page->updated_at)->toAtomString() ?: now()->toAtomString(),
                    'priority' => '0.6',
                ]);
            }
        });

    $xml = view('website.sitemap', ['urls' => $urls->unique('loc')->values()])->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');


Route::get('/admin/seo', [SeoGeneratorController::class, 'dashboard'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.dashboard');
Route::get('/admin/seo/google/connect', [\App\Http\Controllers\SeoIntegrationController::class, 'redirectToGoogle'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.google.connect');
Route::get('/admin/seo/google/callback', [\App\Http\Controllers\SeoIntegrationController::class, 'handleGoogleCallback'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.google.callback');
Route::post('/admin/seo/google/property', [\App\Http\Controllers\SeoIntegrationController::class, 'selectProperty'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.google.property');
Route::post('/admin/seo/google/sync', [\App\Http\Controllers\SeoIntegrationController::class, 'sync'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.google.sync');
Route::delete('/admin/seo/google', [\App\Http\Controllers\SeoIntegrationController::class, 'disconnect'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.google.disconnect');

Route::post('/admin/seo/generate', [SeoGeneratorController::class, 'generate'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.generate');

Route::get('/admin/seo/bulk-generator', [SeoGeneratorController::class, 'bulkForm'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.bulk');

Route::post('/admin/seo/bulk-generator', [SeoGeneratorController::class, 'bulkGenerate'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_seo'])->name('admin.seo.bulk.generate');

Route::get('/admin/ai-content/bulk-generator', [SeoGeneratorController::class, 'bulkContentForm'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_content_generation'])->name('admin.ai-content.bulk');

Route::post('/admin/ai-content/bulk-generator', [SeoGeneratorController::class, 'bulkContentGenerate'])->middleware(['auth', 'checkPageRights', 'plan.feature:ai_content_generation'])->name('admin.ai-content.bulk.generate');
Route::middleware('auth')->group(function () {
    Route::get('/attendance', [\App\Http\Controllers\AttendanceBridgeController::class, 'workspace'])->name('attendance.workspace');
    Route::get('/attendance/context', [\App\Http\Controllers\AttendanceBridgeController::class, 'context'])->name('attendance.context');
    Route::get('/attendance/records', [\App\Http\Controllers\AttendanceBridgeController::class, 'records'])->name('attendance.records');
    Route::match(['GET','POST','PATCH'],'/attendance/api/{path}',[\App\Http\Controllers\AttendanceBridgeController::class,'gateway'])->where('path','.*')->name('attendance.gateway');
});

Route::middleware('plan.feature:public_website')->get('/{slug}', [WebsiteController::class, 'show'])
    ->where('slug', '^(?!(admin|api|auth|cart|checkout|contact|contact-store|course-detail|courses|exam-detail|exam-groups|guest|index|lang-swap|login|logout|password|previous-year-papers|register|results|student|students|storage|uploads|vendor|robots\.txt|sitemap\.xml)$).+$')
    ->name('page.show');

