<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Http\Requests\ForgotPasswordRequest;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se este e-mail existir na nossa base, enviamos um link de redefinição.');
    }
}
