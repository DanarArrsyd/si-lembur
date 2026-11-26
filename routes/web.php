<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/form')->name('home');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::inertia('/', 'panel/dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
