<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Actions\RequestGatewayChargeAction;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Emite a cobrança no gateway para uma FinancialCharge (marco). Staff apenas
 * (financial.manage) — mesma autorização de registrar pagamento manual.
 */
class GatewayChargeController extends Controller
{
    public function store(
        Request $request,
        FinancialCharge $charge,
        RequestGatewayChargeAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($charge);
        $this->authorize('financial.manage', $charge->patient);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        $action($charge, PaymentMethod::from($validated['method']));

        return back()->with('status', 'Cobrança enviada ao gateway.');
    }
}
