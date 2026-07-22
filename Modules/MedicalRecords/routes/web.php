<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\MedicalRecords\Http\Controllers\MedicalRecordAttachmentController;
use Modules\MedicalRecords\Http\Controllers\MedicalRecordController;

Route::middleware(['auth', 'verified', 'resolve.tenant'])->group(function () {
    Route::get('/meus-pacientes', [MedicalRecordController::class, 'myPatients'])->name('medical-records.my-patients');
    Route::get('/pacientes/{patient}/prontuario', [MedicalRecordController::class, 'show'])->name('medical-records.show');
    Route::post('/pacientes/{patient}/prontuario', [MedicalRecordController::class, 'store'])->name('medical-records.store');

    Route::get('/prontuario/anexos/{attachment}/download', [MedicalRecordAttachmentController::class, 'download'])
        ->name('medical-records.attachments.download');
    Route::delete('/prontuario/anexos/{attachment}', [MedicalRecordAttachmentController::class, 'destroy'])
        ->name('medical-records.attachments.destroy');
});
