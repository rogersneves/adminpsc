<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// GET /dashboard agora é registrada por Modules\Reports\Http\Controllers\DashboardController
// (Fase 6) — não redeclarar aqui, senão esta closure vence por ordem de registro.
