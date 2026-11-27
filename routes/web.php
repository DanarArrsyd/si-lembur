<?php

declare(strict_types=1);

use App\Http\Controllers\Public\EmployeeLookupController;
use App\Http\Controllers\Public\OvertimeFormController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OvertimeFormController::class, 'show'])->name('home');
Route::post('/lembur/periksa', [OvertimeFormController::class, 'preview'])
    ->middleware('throttle:public-overtime-preview')
    ->name('public.overtime.preview');

// ARCHITECTURE.md §5: endpoint publik 60/menit per IP.
Route::get('/api/departments/{department}/employees', EmployeeLookupController::class)
    ->middleware('throttle:public-employee-lookup')
    ->name('public.employees');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::inertia('/', 'panel/dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
