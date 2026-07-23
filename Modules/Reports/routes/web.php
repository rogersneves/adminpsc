<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\AttendanceReportController;
use Modules\Reports\Http\Controllers\DashboardController;
use Modules\Reports\Http\Controllers\FinancialReportController;
use Modules\Reports\Http\Controllers\SessionsReportController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/relatorios/sessoes', [SessionsReportController::class, 'index'])->name('reports.sessions.index');
    Route::get('/relatorios/sessoes/pdf', [SessionsReportController::class, 'exportPdf'])->name('reports.sessions.pdf');
    Route::get('/relatorios/sessoes/excel', [SessionsReportController::class, 'exportExcel'])->name('reports.sessions.excel');

    Route::get('/relatorios/financeiro', [FinancialReportController::class, 'index'])->name('reports.financial.index');
    Route::get('/relatorios/financeiro/pdf', [FinancialReportController::class, 'exportPdf'])->name('reports.financial.pdf');
    Route::get('/relatorios/financeiro/excel', [FinancialReportController::class, 'exportExcel'])->name('reports.financial.excel');

    Route::get('/relatorios/comparecimento', [AttendanceReportController::class, 'index'])->name('reports.attendance.index');
    Route::get('/relatorios/comparecimento/pdf', [AttendanceReportController::class, 'exportPdf'])->name('reports.attendance.pdf');
    Route::get('/relatorios/comparecimento/excel', [AttendanceReportController::class, 'exportExcel'])->name('reports.attendance.excel');
});
