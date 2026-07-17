<?php

use Illuminate\Support\Facades\Route;
use Modules\MedicalRecords\Http\Controllers\MedicalRecordsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('medicalrecords', MedicalRecordsController::class)->names('medicalrecords');
});
