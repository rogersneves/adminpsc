<?php

use Illuminate\Support\Facades\Route;
use Modules\Psychologists\Http\Controllers\PsychologistsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('psychologists', PsychologistsController::class)->names('psychologists');
});
