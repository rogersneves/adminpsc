<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Tenant\Support\CurrentTenant;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/dashboard', function (CurrentTenant $currentTenant) {
    $tenant = $currentTenant->get();

    return Inertia::render('Dashboard', [
        'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
    ]);
})->middleware(['auth', 'verified', 'resolve.tenant'])->name('dashboard');
