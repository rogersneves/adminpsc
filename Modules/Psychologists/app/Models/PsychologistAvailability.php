<?php

declare(strict_types=1);

namespace Modules\Psychologists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Psychologists\Enums\AvailabilityType;
use Modules\Tenant\Traits\BelongsToTenant;

class PsychologistAvailability extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'psychologist_id',
        'weekday',
        'date',
        'start_time',
        'end_time',
        'session_duration_minutes',
        'buffer_minutes',
        'type',
    ];

    public function psychologist()
    {
        return $this->belongsTo(Psychologist::class);
    }

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'date' => 'date',
            'session_duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'type' => AvailabilityType::class,
        ];
    }
}
