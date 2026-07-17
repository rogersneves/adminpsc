<?php

use Illuminate\Support\Facades\Route;
use Modules\Patients\Http\Controllers\PatientsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('patients', PatientsController::class)->names('patients');
});
