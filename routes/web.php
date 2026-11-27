<?php

declare(strict_types=1);

use App\Http\Controllers\Public\EmployeeLookupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/form')->name('home');

// ARCHITECTURE.md §5: endpoint publik 60/menit per IP.
Route::get('/api/departments/{department}/employees', EmployeeLookupController::class)
    ->middleware('throttle:60,1')
    ->name('public.employees');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::inertia('/', 'panel/dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
