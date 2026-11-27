<?php

declare(strict_types=1);

use App\Http\Controllers\Public\EmployeeLookupController;
use Illuminate\Support\Facades\Route;

Route::get('departments/{department}/employees', EmployeeLookupController::class)
    ->whereNumber('department')
    ->middleware('throttle:60,1');
