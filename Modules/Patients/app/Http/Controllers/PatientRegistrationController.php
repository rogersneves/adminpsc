<?php

declare(strict_types=1);

namespace Modules\Patients\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Patients\Actions\RegisterPatientAction;
use Modules\Patients\Http\Requests\RegisterPatientRequest;
use Modules\Tenant\Models\Tenant;

class PatientRegistrationController extends Controller
{
    public function create(Tenant $tenant): Response
    {
        abort_unless($tenant->status === 'active', 404);

        return Inertia::render('Patients/Register', [
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
        ]);
    }

    public function store(RegisterPatientRequest $request, Tenant $tenant, RegisterPatientAction $action): RedirectResponse
    {
        abort_unless($tenant->status === 'active', 404);

        $action($tenant, $request->toDto());

        return redirect()->route('verification.notice');
    }
}
