<?php

use Illuminate\Support\Facades\Route;
use Modules\Patients\Http\Controllers\PatientsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('patients', PatientsController::class)->names('patients');
});
