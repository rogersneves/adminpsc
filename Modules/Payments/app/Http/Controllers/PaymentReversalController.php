<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Payments\Actions\ReversePaymentAction;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Support\CurrentTenant;

class PaymentReversalController extends Controller
{
    public function store(
        Payment $payment,
        ReversePaymentAction $action,
        CurrentTenant $currentTenant,
    ): RedirectResponse {
        $currentTenant->ownsOrFail($payment);
        $this->authorize('financial.manage', $payment->charge->patient);

        $action($payment);

        return back()->with('status', 'Pagamento estornado.');
    }
}
