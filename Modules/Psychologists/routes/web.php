<?php

use Illuminate\Support\Facades\Route;
use Modules\Psychologists\Http\Controllers\PsychologistsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('psychologists', PsychologistsController::class)->names('psychologists');
});
