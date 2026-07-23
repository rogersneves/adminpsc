<?php

declare(strict_types=1);

namespace Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Financial\Models\FinancialCharge;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Sem SoftDeletes de propósito: um pagamento nunca é apagado, só estornado
 * (reversed_at). Ver ReversePaymentAction.
 */
class Payment extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey;

    protected $table = 'financial_payments';

    protected $fillable = [
        'tenant_id',
        'charge_id',
        'amount',
        'paid_at',
        'method',
        'gateway_reference',
        'reversed_at',
    ];

    public function charge()
    {
        return $this->belongsTo(FinancialCharge::class, 'charge_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'method' => PaymentMethod::class,
        ];
    }
}
