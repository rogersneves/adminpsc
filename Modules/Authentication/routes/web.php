<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authentication\Http\Controllers\EmailVerificationController;
use Modules\Authentication\Http\Controllers\LoginController;
use Modules\Authentication\Http\Controllers\LogoutController;
use Modules\Authentication\Http\Controllers\MfaChallengeController;
use Modules\Authentication\Http\Controllers\NewPasswordController;
use Modules\Authentication\Http\Controllers\PasswordResetLinkController;
use Modules\Authentication\Http\Controllers\RegisterController;
use Modules\Authentication\Http\Controllers\TotpSetupController;

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/mfa/challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge.create');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('mfa.challenge.store');
    Route::post('/mfa/resend', [MfaChallengeController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('mfa.challenge.resend');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('/security/totp/setup', [TotpSetupController::class, 'create'])->name('totp.setup.create');
        Route::post('/security/totp/setup', [TotpSetupController::class, 'store'])->name('totp.setup.store');
    });
});
