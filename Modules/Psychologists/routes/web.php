<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Psychologists\Http\Controllers\PsychologistAvailabilityController;
use Modules\Psychologists\Http\Controllers\PsychologistController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->prefix('psicologos')->group(function () {
    Route::get('/', [PsychologistController::class, 'index'])->name('psychologists.index');
    Route::get('/criar', [PsychologistController::class, 'create'])->name('psychologists.create');
    Route::post('/', [PsychologistController::class, 'store'])->name('psychologists.store');

    Route::get('/{psychologist}/disponibilidade', [PsychologistAvailabilityController::class, 'index'])
        ->name('psychologists.availability.index');
    Route::post('/{psychologist}/disponibilidade', [PsychologistAvailabilityController::class, 'store'])
        ->name('psychologists.availability.store');
});

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::delete('/disponibilidade/{availability}', [PsychologistAvailabilityController::class, 'destroy'])
        ->name('psychologists.availability.destroy');
});
