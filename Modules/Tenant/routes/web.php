<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\TenantController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tenants', TenantController::class)->names('tenant');
});
