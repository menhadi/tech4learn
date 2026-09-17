<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tech4LearnReadController;
use App\Http\Controllers\Tech4LearnPlatformController;
use App\Http\Controllers\Tech4LearnWorkspaceController;
use App\Http\Controllers\Tech4LearnContentController;
use App\Http\Controllers\Tech4LearnAuthoringController;
use App\Http\Controllers\Tech4LearnStudentController;
use App\Http\Controllers\Tech4LearnProctorController;
use App\Http\Controllers\Tech4LearnResultController;
use App\Http\Controllers\Tech4LearnDocumentController;
use App\Http\Controllers\Tech4LearnTranslationController;

// Separate from authoring's shared-IP 30/minute allowance. The T4L server
// also limits each authenticated grant; every call requires the central credential.
Route::prefix('tech4learn/v1')->withoutMiddleware('throttle:api')->middleware('throttle:6000,1,t4l-student:')->group(function () {
    Route::post('/student/{org}/{action}', [Tech4LearnStudentController::class, 'attempt'])->where('action','prepare|history|start|answer|submit|result|media|visibility|proctor');
});

Route::prefix('tech4learn/v1')->withoutMiddleware('throttle:api')->middleware('throttle:1800,1,t4l-result-media:')->group(function () {
    Route::get('/results/{org}/learners/{learner}/attempts/{attempt}/media/{stat}/{asset}', [Tech4LearnResultController::class, 'media']);
});
Route::prefix('tech4learn/v1')->withoutMiddleware('throttle:api')->middleware('throttle:1800,1,t4l-authoring-media:')->group(function () {
    Route::get('/central/questions/{id}/media/{asset}', [Tech4LearnContentController::class, 'centralMedia']);
    Route::get('/translations/{org}/exams/{exam}/languages/{language}/media/{question}/{asset}', [Tech4LearnTranslationController::class, 'media']);
    Route::get('/authoring/{org}/questions/{id}/media/{asset}', [Tech4LearnAuthoringController::class, 'questionMedia']);
    Route::get('/authoring/{org}/packages/{id}/media/{asset}', [Tech4LearnAuthoringController::class, 'packageMedia']);
});
Route::prefix('tech4learn/v1')->middleware('throttle:30,1')->group(function () {
    Route::get('/central/choices/{kind}', [Tech4LearnAuthoringController::class, 'centralChoices']);
    Route::get('/central/taxonomy/{kind}/{id}', [Tech4LearnContentController::class, 'centralTaxonomy']);
    Route::post('/central/taxonomy/{kind}/{id}', [Tech4LearnContentController::class, 'centralTaxonomyWrite']);
    Route::get('/central/questions/{id}', [Tech4LearnContentController::class, 'centralDetail']);
    Route::post('/central/questions', [Tech4LearnContentController::class, 'centralWrite']);
    Route::post('/central/questions/{id}', [Tech4LearnContentController::class, 'centralWrite']);
    Route::post('/central/questions/{id}/image', [Tech4LearnContentController::class, 'centralImageWrite']);
    Route::get('/central/packages/{id}/media/{asset}', [Tech4LearnContentController::class, 'centralPackageMedia']);
    Route::post('/central/packages/{id}/image', [Tech4LearnContentController::class, 'centralPackageImageWrite']);
    Route::post('/central/exams/{id}/actions/{action}', [Tech4LearnContentController::class, 'centralExamAction']);
    Route::get('/central/exams/{id}/questions', [Tech4LearnAuthoringController::class, 'centralExamQuestions']);
    Route::get('/central/exams/{exam}/translations/{language}/media/{question}/{asset}', [Tech4LearnTranslationController::class, 'centralMedia']);
    Route::get('/central/exams/{exam}/translations/{language}', [Tech4LearnTranslationController::class, 'centralReview']);
    Route::get('/translations/{org}/exams/{exam}/languages/{language}', [Tech4LearnTranslationController::class, 'review']);
    Route::get('/documents/{org}/exams/{exam}/{type}', [Tech4LearnDocumentController::class, 'read'])->where('type','questions|solutions');
    Route::get('/documents/{org}/exams/{exam}/{type}/status', [Tech4LearnDocumentController::class, 'documentStatus'])->where('type','questions|solutions');
    Route::get('/results/{org}/learners/{learner}/attempts', [Tech4LearnResultController::class, 'attempts']);
    Route::get('/results/{org}/learners/{learner}/attempts/{attempt}', [Tech4LearnResultController::class, 'review']);
    Route::post('/results/{org}/learners/{learner}/attempts/{attempt}', [Tech4LearnResultController::class, 'save']);
    Route::get('/review/{org}/learners/{learner}/attempts', [Tech4LearnProctorController::class, 'attempts']);
    Route::get('/review/{org}/learners/{learner}/attempts/{attempt}/captures/{capture?}', [Tech4LearnProctorController::class, 'captures']);
    Route::get('/authoring/{org}/exams/{id}/questions', [Tech4LearnAuthoringController::class, 'examQuestions']);
    Route::post('/authoring/{org}/packages/{id}/image', [Tech4LearnAuthoringController::class, 'packageImageWrite']);
    Route::post('/authoring/{org}/exams/{id}/actions/{action}', [Tech4LearnAuthoringController::class, 'examAction']);
    Route::get('/authoring/{org}/taxonomy/{kind}/{id}', [Tech4LearnAuthoringController::class, 'taxonomy']);
    Route::post('/authoring/{org}/taxonomy/{kind}/{id}', [Tech4LearnAuthoringController::class, 'saveTaxonomy']);
    Route::post('/authoring/{org}/taxonomy/languages/{id}/disable', [Tech4LearnAuthoringController::class, 'disableLanguage']);
    Route::get('/authoring/{org}/choices/{kind}', [Tech4LearnAuthoringController::class, 'choices']);
    Route::post('/authoring/{org}/questions', [Tech4LearnAuthoringController::class, 'create']);
    Route::post('/authoring/{org}/questions/{id}/image', [Tech4LearnAuthoringController::class, 'questionImageWrite']);
    Route::get('/authoring/{org}/questions/{id}', [Tech4LearnAuthoringController::class, 'question']);
    Route::post('/authoring/{org}/questions/{id}', [Tech4LearnAuthoringController::class, 'save']);
    Route::get('/content/{org}/questions', [Tech4LearnContentController::class, 'questions']);
    Route::get('/content/{org}/transfers', [Tech4LearnContentController::class, 'history']);
    Route::post('/content/{org}/transfer', [Tech4LearnContentController::class, 'transfer']);
    Route::get('/workspace/status', [Tech4LearnWorkspaceController::class, 'health']);
    Route::post('/workspace/{org}/restrictions', [Tech4LearnWorkspaceController::class, 'restrict']);
    Route::post('/workspace/{org}/launch', [Tech4LearnWorkspaceController::class, 'launch']);
    Route::get('/platform/status', [Tech4LearnPlatformController::class, 'status']);
    Route::get('/platform/exams', [Tech4LearnPlatformController::class, 'exams']);
    Route::post('/platform/validate-exams', [Tech4LearnPlatformController::class, 'validateExams']);
    Route::get('/platform/organisations/{org}/exams', [Tech4LearnPlatformController::class, 'exams']);
    Route::post('/platform/organisations/{org}/learners/{learner}', [Tech4LearnPlatformController::class, 'connect']);
    Route::get('/platform/organisations/{org}/learners/{learner}/results', [Tech4LearnPlatformController::class, 'results']);
    Route::get('/status', [Tech4LearnReadController::class, 'status']);
    Route::get('/exams', [Tech4LearnReadController::class, 'exams']);
    Route::get('/learners/{learner}/results', [Tech4LearnReadController::class, 'results']);
});
