<?php

use Illuminate\Support\Facades\Route;
use Modules\MedicalRecords\Http\Controllers\MedicalRecordsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('medicalrecords', MedicalRecordsController::class)->names('medicalrecords');
});
