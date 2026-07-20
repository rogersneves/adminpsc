<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Actions\AttemptLoginAction;
use Modules\Authentication\Actions\VerifyMfaChallengeAction;
use Modules\Authentication\Enums\MfaMethod;
use Modules\Authentication\Exceptions\InvalidMfaChallengeException;
use Modules\Authentication\Http\Requests\MfaChallengeRequest;
use Modules\Users\Models\User;

class MfaChallengeController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $user = $this->pendingUser();

        if ($user === null) {
            return Redirect::route('login');
        }

        return Inertia::render('MfaChallenge', [
            'method' => session('mfa.method'),
        ]);
    }

    public function store(MfaChallengeRequest $request, VerifyMfaChallengeAction $action): RedirectResponse
    {
        $user = $this->pendingUser();

        if ($user === null) {
            return Redirect::route('login');
        }

        $method = MfaMethod::from(session('mfa.method'));

        try {
            $action($user, $method, $request->string('code')->toString());
        } catch (InvalidMfaChallengeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        return Redirect::intended(route('dashboard'));
    }

    public function resend(AttemptLoginAction $action): RedirectResponse
    {
        $user = $this->pendingUser();

        if ($user !== null && session('mfa.method') === MfaMethod::EmailOtp->value) {
            $action->sendEmailOtp($user);
        }

        return back()->with('status', 'Um novo código foi enviado para o seu e-mail.');
    }

    private function pendingUser(): ?User
    {
        $id = session('mfa.pending_user_id');

        return $id ? User::query()->find($id) : null;
    }
}
