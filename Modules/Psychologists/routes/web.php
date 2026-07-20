<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Psychologists\Http\Controllers\PsychologistController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->prefix('psicologos')->group(function () {
    Route::get('/', [PsychologistController::class, 'index'])->name('psychologists.index');
    Route::get('/criar', [PsychologistController::class, 'create'])->name('psychologists.create');
    Route::post('/', [PsychologistController::class, 'store'])->name('psychologists.store');
});
