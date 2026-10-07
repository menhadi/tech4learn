<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\InstallationController;


Route::get('/', [InstallationController::class, 'iwelcome'])->name('installer.iwelcome');
Route::get('installer/database', [InstallationController::class, 'database'])->name('installer.database');
Route::get('installer/information', [InstallationController::class, 'information'])->name('installer.information');
Route::post('/installer/database', [InstallationController::class, 'database'])->name('installer.database.post');
Route::post('/installer/information', [InstallationController::class, 'information'])->name('installer.information.post');
Route::get('/check-write-permission', [InstallationController::class, 'checkWritePermission']);
Route::get('/check-symlink', [InstallationController::class, 'checkSymlink']);
