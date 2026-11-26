<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'role:ADMIN,SUPERVISOR'])->prefix('panel')->name('panel.')->group(function () {
    Route::inertia('/', 'dashboard')->name('dashboard');
});

require __DIR__.'/auth.php';
