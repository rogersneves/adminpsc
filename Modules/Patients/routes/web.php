<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Patients\Http\Controllers\PatientProfileController;
use Modules\Patients\Http\Controllers\PatientRegistrationController;

Route::middleware('guest')->group(function () {
    Route::get('/c/{tenant:slug}/paciente/registro', [PatientRegistrationController::class, 'create'])
        ->name('patients.register.create');
    Route::post('/c/{tenant:slug}/paciente/registro', [PatientRegistrationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('patients.register.store');
});

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/paciente/perfil', [PatientProfileController::class, 'edit'])->name('patients.profile.edit');
    Route::put('/paciente/perfil', [PatientProfileController::class, 'update'])->name('patients.profile.update');
});
