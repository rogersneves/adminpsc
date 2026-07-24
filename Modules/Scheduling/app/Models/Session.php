<?php

declare(strict_types=1);

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Enums\SessionModality;
use Modules\Scheduling\Enums\SessionStatus;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Tabela real: `clinical_sessions` (não `sessions` — colide com a tabela de sessão HTTP
 * do Laravel). Ver docs/02-Banco-de-Dados.md.
 */
class Session extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'clinical_sessions';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'psychologist_id',
        'scheduled_at',
        'duration_minutes',
        'modality',
        'status',
        'rescheduled_from_id',
        'reminder_sent_at',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function psychologist()
    {
        return $this->belongsTo(Psychologist::class);
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'modality' => SessionModality::class,
            'status' => SessionStatus::class,
            'reminder_sent_at' => 'datetime',
        ];
    }
}
