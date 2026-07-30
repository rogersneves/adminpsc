<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Financial\Models\FinancialCharge;
use Modules\Patients\Models\Patient;

/**
 * Cobranças do próprio paciente (API v1). Só o dado do dono do token — reusa a
 * mesma noção de "financeiro do paciente" das Fases 5/6.
 */
class ChargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = Patient::query()->where('user_id', $request->user()->id)->first();

        if ($patient === null) {
            return response()->json(['data' => []]);
        }

        $charges = FinancialCharge::query()
            ->where('patient_id', $patient->id)
            ->orderBy('due_date')
            ->get()
            ->map(fn (FinancialCharge $c) => [
                'id' => $c->id,
                'amount' => $c->amount,
                'discount_amount' => $c->discount_amount,
                'total_due' => $c->totalDue(),
                'total_paid' => $c->totalPaid(),
                'due_date' => $c->due_date?->toDateString(),
                'status' => $c->status->value,
                'installment_number' => $c->installment_number,
                'installment_total' => $c->installment_total,
            ]);

        return response()->json(['data' => $charges]);
    }
}
