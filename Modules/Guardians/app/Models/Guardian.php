<?php

declare(strict_types=1);

namespace Modules\Guardians\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Patients\Models\Patient;
use Modules\Security\Casts\EnvelopeEncrypted;
use Modules\Tenant\Traits\BelongsToTenant;

class Guardian extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'name',
        'document_number_encrypted',
        'document_number_hash',
        'email',
        'phone_encrypted',
        'address_encrypted',
        'relationship',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    protected function casts(): array
    {
        return [
            'document_number_encrypted' => EnvelopeEncrypted::class.':guardian_document_number',
            'phone_encrypted' => EnvelopeEncrypted::class.':guardian_phone',
            'address_encrypted' => EnvelopeEncrypted::class.':guardian_address',
        ];
    }
}
