<?php

declare(strict_types=1);

namespace Modules\Psychologists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Security\Casts\EnvelopeEncrypted;
use Modules\Tenant\Traits\BelongsToTenant;
use Modules\Users\Models\User;

class Psychologist extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'professional_registry_encrypted',
        'specialties',
        'default_session_duration_minutes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities()
    {
        return $this->hasMany(PsychologistAvailability::class);
    }

    protected function casts(): array
    {
        return [
            'professional_registry_encrypted' => EnvelopeEncrypted::class.':psychologist_professional_registry',
            'specialties' => 'array',
            'default_session_duration_minutes' => 'integer',
        ];
    }
}
