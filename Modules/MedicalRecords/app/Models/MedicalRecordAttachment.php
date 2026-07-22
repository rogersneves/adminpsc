<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Security\Casts\EnvelopeEncrypted;
use Modules\Tenant\Traits\BelongsToTenant;

class MedicalRecordAttachment extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'medical_record_entry_id',
        'file_path_encrypted',
        'original_filename_encrypted',
        'mime_type',
        'size_bytes',
        'created_at',
    ];

    public function entry()
    {
        return $this->belongsTo(MedicalRecordEntry::class, 'medical_record_entry_id');
    }

    protected function casts(): array
    {
        return [
            'file_path_encrypted' => EnvelopeEncrypted::class.':medical_record_attachment_path',
            'original_filename_encrypted' => EnvelopeEncrypted::class.':medical_record_attachment_filename',
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
