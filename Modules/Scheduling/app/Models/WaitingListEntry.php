<?php

declare(strict_types=1);

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Tenant\Traits\BelongsToTenant;

class WaitingListEntry extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'psychologist_id',
        'desired_period',
        'status',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function psychologist()
    {
        return $this->belongsTo(Psychologist::class);
    }

    protected function casts(): array
    {
        return [
            'desired_period' => 'array',
        ];
    }
}
