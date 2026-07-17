<?php

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\SchedulingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('schedulings', SchedulingController::class)->names('scheduling');
});
