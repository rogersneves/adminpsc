<?php

use Illuminate\Support\Facades\Route;
use Modules\Guardians\Http\Controllers\GuardiansController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('guardians', GuardiansController::class)->names('guardians');
});
