<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\Payments\Models\Payment;
use Modules\Tenant\Support\CurrentTenant;

class PaymentReceiptController extends Controller
{
    public function download(Payment $payment, CurrentTenant $currentTenant): Response
    {
        $currentTenant->ownsOrFail($payment);
        $this->authorize('financial.view', $payment->charge->patient);

        $charge = $payment->charge;

        return Pdf::loadView('reports::pdf.receipt', [
            'payment' => $payment,
            'charge' => $charge,
            'patient' => $charge->patient,
            'session' => $charge->session,
        ])->download("recibo-{$payment->id}.pdf");
    }
}
