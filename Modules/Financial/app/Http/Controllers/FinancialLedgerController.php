<?php

declare(strict_types=1);

namespace Modules\Financial\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;
use Modules\Payments\Models\Payment;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Support\CurrentTenant;

class FinancialLedgerController extends Controller
{
    public function show(Request $request, Patient $patient, CurrentTenant $currentTenant): Response
    {
        $currentTenant->ownsOrFail($patient);
        $this->authorize('financial.view', $patient);

        $charges = FinancialCharge::query()
            ->where('patient_id', $patient->id)
            ->with(['payments' => fn ($query) => $query->orderByDesc('paid_at')])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (FinancialCharge $charge) => [
                'id' => $charge->id,
                'amount' => (float) $charge->amount,
                'discount_amount' => (float) $charge->discount_amount,
                'fine_amount' => (float) $charge->fine_amount,
                'interest_amount' => (float) $charge->interest_amount,
                'total_due' => $charge->totalDue(),
                'total_paid' => $charge->totalPaid(),
                'due_date' => $charge->due_date->toDateString(),
                'status' => $charge->status->value,
                'installment_number' => $charge->installment_number,
                'installment_total' => $charge->installment_total,
                'payments' => $charge->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'method' => $payment->method->value,
                    'reversed_at' => $payment->reversed_at?->toIso8601String(),
                ]),
            ]);

        $availableSessions = Session::query()
            ->where('patient_id', $patient->id)
            ->where('status', SessionStatus::Realizada)
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn (Session $session) => [
                'id' => $session->id,
                'scheduled_at' => $session->scheduled_at->toIso8601String(),
            ]);

        return Inertia::render('Financial/Ledger', [
            'patient' => ['id' => $patient->id, 'display_name' => $patient->display_name],
            'charges' => $charges,
            'availableSessions' => $availableSessions,
            'canManage' => $request->user()->can('financial.manage', $patient),
        ]);
    }
}
