<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExamPrintController;

// Exam print route - simple, no conflicts
Route::get('/exam-print/{slug}', [ExamPrintController::class, 'print']);

