<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tech4LearnReadController;

Route::prefix('tech4learn/v1')->middleware('throttle:30,1')->group(function () {
    Route::get('/status', [Tech4LearnReadController::class, 'status']);
    Route::get('/exams', [Tech4LearnReadController::class, 'exams']);
    Route::get('/learners/{learner}/results', [Tech4LearnReadController::class, 'results']);
});
