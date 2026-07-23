<?php

declare(strict_types=1);

namespace Modules\Financial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Financial\Enums\ChargeStatus;
use Modules\Patients\Models\Patient;
use Modules\Payments\Models\Payment;
use Modules\Scheduling\Models\Session;
use Modules\Tenant\Traits\BelongsToTenant;

class FinancialCharge extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'session_id',
        'amount',
        'discount_amount',
        'fine_amount',
        'interest_amount',
        'due_date',
        'status',
        'installment_number',
        'installment_total',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'charge_id');
    }

    public function totalDue(): float
    {
        return round(
            (float) $this->amount - (float) $this->discount_amount
                + (float) $this->fine_amount + (float) $this->interest_amount,
            2,
        );
    }

    public function totalPaid(): float
    {
        return round((float) $this->payments()->whereNull('reversed_at')->sum('amount'), 2);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => ChargeStatus::class,
            'installment_number' => 'integer',
            'installment_total' => 'integer',
        ];
    }
}
