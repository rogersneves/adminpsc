<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Actions\AttemptLoginAction;
use Modules\Authentication\Exceptions\InvalidCredentialsException;
use Modules\Authentication\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Login');
    }

    public function store(LoginRequest $request, AttemptLoginAction $action): RedirectResponse
    {
        try {
            $method = $action($request->toDto());
        } catch (InvalidCredentialsException) {
            return back()->withErrors(['email' => 'Credenciais inválidas.'])->onlyInput('email');
        }

        return Redirect::route('mfa.challenge.create')->with('mfa_method', $method->value);
    }
}
