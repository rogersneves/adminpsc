<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Actions\RecordPaymentAction;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Http\Requests\StorePaymentRequest;
use Modules\Tenant\Support\CurrentTenant;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        FinancialCharge $charge,
        RecordPaymentAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($charge);
        $this->authorize('financial.manage', $charge->patient);

        $action(
            $charge,
            (float) $request->input('amount'),
            PaymentMethod::from($request->string('method')->toString()),
            $request->filled('paid_at')
                ? CarbonImmutable::parse($request->string('paid_at')->toString())
                : CarbonImmutable::now(),
            $request->input('gateway_reference'),
        );

        return back()->with('status', 'Pagamento registrado.');
    }
}
