<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tech4LearnReadController;
use App\Http\Controllers\Tech4LearnPlatformController;
use App\Http\Controllers\Tech4LearnWorkspaceController;

Route::prefix('tech4learn/v1')->middleware('throttle:30,1')->group(function () {
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
