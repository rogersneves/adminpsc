<?php

use Illuminate\Support\Facades\Route;
use Modules\Guardians\Http\Controllers\GuardiansController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('guardians', GuardiansController::class)->names('guardians');
});
