<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Public\EmployeeLookupController;
use App\Http\Controllers\Public\OvertimeFormController;
use App\Http\Controllers\Public\StatusCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OvertimeFormController::class, 'show'])->name('home');
Route::post('/lembur/periksa', [OvertimeFormController::class, 'preview'])
    ->middleware('throttle:public-overtime-preview')
    ->name('public.overtime.preview');
Route::post('/lembur', [OvertimeFormController::class, 'submit'])
    ->middleware('throttle:public-overtime-submit')
    ->name('public.overtime.submit');
Route::get('/lembur/sukses/{number}', [OvertimeFormController::class, 'success'])
    ->middleware('signed')
    ->name('public.success');

Route::get('/status', [StatusCheckController::class, 'show'])->name('public.status');
Route::get('/api/status', [StatusCheckController::class, 'index'])
    ->middleware('throttle:public-overtime-status')
    ->name('public.status.index');

// ARCHITECTURE.md §5: endpoint publik 60/menit per IP.
Route::get('/api/departments/{department}/employees', EmployeeLookupController::class)
    ->middleware('throttle:public-employee-lookup')
    ->name('public.employees');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
