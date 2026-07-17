<?php

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\SchedulingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('schedulings', SchedulingController::class)->names('scheduling');
});
