<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(): Response
    {
        return Inertia::render('VerifyEmail');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->markEmailAsVerified();

            event(new Verified($request->user()));
        }

        return redirect()->route('login')->with('status', 'E-mail verificado! Já pode entrar.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('login');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Um novo link de verificação foi enviado.');
    }
}
