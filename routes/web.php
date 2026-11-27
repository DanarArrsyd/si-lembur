<?php

declare(strict_types=1);

use App\Http\Controllers\Public\OvertimeFormController;
use App\Http\Controllers\Public\StatusCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OvertimeFormController::class, 'show'])->name('home');
Route::post('/lembur/periksa', [OvertimeFormController::class, 'review'])
    ->middleware('throttle:public-overtime-review')
    ->name('public.review');
Route::post('/lembur', [OvertimeFormController::class, 'store'])
    ->middleware('throttle:public-overtime-submit')
    ->name('public.store');
Route::get('/lembur/sukses/{number}', [OvertimeFormController::class, 'success'])
    ->middleware('signed')
    ->name('public.success');
Route::get('/status', [StatusCheckController::class, 'show'])->name('public.status');
Route::get('/status/{number}', [StatusCheckController::class, 'history'])
    ->middleware(['throttle:public-overtime-status', 'signed'])
    ->name('public.status.history');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::inertia('/', 'panel/dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
