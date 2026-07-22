<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Modules\Core\Traits\HasUuidPrimaryKey;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Scheduling\Models\Session;
use Modules\Security\Casts\EncryptedJson;
use Modules\Tenant\Traits\BelongsToTenant;

/**
 * Append-only por design (docs/06-Roadmap.md Fase 4): sem updated_at, e update()/
 * delete() lançam exceção — mesmo padrão do Modules\Audit\Models\AuditLog (Fase 1).
 * Uma edição nunca sobrescreve: cria uma nova linha com `version` incrementada,
 * ligada por `previous_version_id`.
 */
class MedicalRecordEntry extends Model
{
    use BelongsToTenant, HasUuidPrimaryKey, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'psychologist_id',
        'session_id',
        'version',
        'previous_version_id',
        'content_encrypted',
        'created_at',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function psychologist()
    {
        return $this->belongsTo(Psychologist::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function previousVersion()
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function attachments()
    {
        return $this->hasMany(MedicalRecordAttachment::class);
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'content_encrypted' => EncryptedJson::class.':medical_record_content',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Bloqueia edição de conteúdo — mas não exclusão: soft delete continua permitido
     * para exclusão administrativa excepcional (docs/02-Banco-de-Dados.md), e
     * `SoftDeletes::delete()` não passa por este método (usa uma query direta), então
     * esse bloqueio não afeta o soft delete.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('medical_record_entries é append-only: registros não podem ser atualizados, só versionados.');
    }
}
