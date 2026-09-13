<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tech4LearnNativeController;
use App\Http\Controllers\Tech4LearnLibraryController;
Route::middleware(['web','throttle:30,1'])->group(function(){
    Route::get('/tech4learn/launch',[Tech4LearnNativeController::class,'launch']);
    Route::post('/tech4learn/launch',[Tech4LearnNativeController::class,'accept']);
});
Route::middleware(['web','auth','throttle:30,1'])->group(function(){
    Route::get('/tech4learn/library',[Tech4LearnLibraryController::class,'index']);
    Route::post('/tech4learn/library/{kind}/{id}/copy',[Tech4LearnLibraryController::class,'copy']);
});
