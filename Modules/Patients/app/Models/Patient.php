<?php

declare(strict_types=1);

namespace Modules\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Guardians\Models\Guardian;
use Modules\Security\Casts\EncryptedJson;
use Modules\Security\Casts\EnvelopeEncrypted;
use Modules\Tenant\Traits\BelongsToTenant;
use Modules\Users\Models\User;

class Patient extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'display_name',
        'email',
        'document_number_encrypted',
        'document_number_hash',
        'birth_date_encrypted',
        'phones_encrypted',
        'emergency_contacts_encrypted',
        'address_encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guardians()
    {
        return $this->hasMany(Guardian::class);
    }

    protected function casts(): array
    {
        return [
            'document_number_encrypted' => EnvelopeEncrypted::class.':patient_document_number',
            'birth_date_encrypted' => EnvelopeEncrypted::class.':patient_birth_date',
            'address_encrypted' => EnvelopeEncrypted::class.':patient_address',
            'phones_encrypted' => EncryptedJson::class.':patient_phones',
            'emergency_contacts_encrypted' => EncryptedJson::class.':patient_emergency_contacts',
        ];
    }
}
