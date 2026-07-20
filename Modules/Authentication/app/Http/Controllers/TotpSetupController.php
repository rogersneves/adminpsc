<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Actions\ConfirmTotpAction;
use Modules\Authentication\Actions\EnableTotpAction;
use Modules\Authentication\Exceptions\InvalidMfaChallengeException;
use Modules\Authentication\Http\Requests\ConfirmTotpRequest;

class TotpSetupController extends Controller
{
    public function create(Request $request, EnableTotpAction $action): Response
    {
        $setup = $action($request->user());

        return Inertia::render('Security/EnableTotp', [
            'secret' => $setup->secret,
            'otpauthUri' => $setup->otpauthUri,
        ]);
    }

    public function store(ConfirmTotpRequest $request, ConfirmTotpAction $action): RedirectResponse
    {
        try {
            $action($request->user(), $request->string('code')->toString());
        } catch (InvalidMfaChallengeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        return redirect()->route('dashboard')->with('status', 'Autenticação por TOTP ativada.');
    }
}
