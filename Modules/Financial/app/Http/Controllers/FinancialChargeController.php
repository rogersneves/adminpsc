<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Modules\Financial\Actions\CancelChargeAction;
use Modules\Financial\Actions\CreateChargeAction;
use Modules\Financial\Actions\UpdateChargeDiscountAction;
use Modules\Financial\Exceptions\ChargeHasPaymentException;
use Modules\Financial\Http\Requests\StoreChargeRequest;
use Modules\Financial\Http\Requests\UpdateChargeDiscountRequest;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Support\CurrentTenant;

class FinancialChargeController extends Controller
{
    public function store(
        StoreChargeRequest $request,
        Patient $patient,
        CreateChargeAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($patient);
        $this->authorize('financial.manage', $patient);

        $session = $request->filled('session_id')
            ? Session::query()->where('patient_id', $patient->id)->find($request->input('session_id'))
            : null;

        $action(
            $patient,
            (float) $request->input('amount'),
            CarbonImmutable::parse($request->string('due_date')->toString()),
            (int) ($request->input('installment_total') ?? 1),
            (float) ($request->input('discount_amount') ?? 0),
            $session,
        );

        return back()->with('status', 'Cobrança criada.');
    }

    public function update(
        UpdateChargeDiscountRequest $request,
        FinancialCharge $charge,
        UpdateChargeDiscountAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($charge);
        $this->authorize('financial.manage', $charge->patient);

        try {
            $action($charge, (float) $request->input('discount_amount'));
        } catch (ChargeHasPaymentException $exception) {
            return back()->withErrors(['discount_amount' => $exception->getMessage()]);
        }

        return back()->with('status', 'Desconto atualizado.');
    }

    public function destroy(
        FinancialCharge $charge,
        CancelChargeAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($charge);
        $this->authorize('financial.manage', $charge->patient);

        try {
            $action($charge);
        } catch (ChargeHasPaymentException $exception) {
            return back()->withErrors(['charge' => $exception->getMessage()]);
        }

        return back()->with('status', 'Cobrança cancelada.');
    }
}
