<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Authentication\Actions\RegisterClinicAdminAction;
use Modules\Authentication\Http\Requests\RegisterRequest;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Register');
    }

    public function store(RegisterRequest $request, RegisterClinicAdminAction $action): RedirectResponse
    {
        $action($request->toDto());

        return redirect()->route('verification.notice');
    }
}
