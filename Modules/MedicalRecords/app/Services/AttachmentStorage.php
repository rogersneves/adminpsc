<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\MedicalRecords\Models\MedicalRecordAttachment;
use Modules\Security\Services\EncryptionService;

/**
 * O conteúdo do arquivo inteiro é cifrado em memória com o EncryptionService já
 * existente (Fase 1) e salvo no disco privado padrão do Laravel sob um nome aleatório
 * — sem relação com o nome original, que também fica cifrado (pode ser sensível).
 *
 * Cifrar em memória (não em streaming) limita isso a arquivos pequenos — ver o limite
 * de tamanho na FormRequest de upload, não aqui. docs/06-Roadmap.md Fase 4.
 */
class AttachmentStorage
{
    private const DISK = 'local';

    private const DIRECTORY = 'medical-record-attachments';

    private const CONTEXT = 'medical_record_attachment_file';

    public function __construct(private readonly EncryptionService $encryption) {}

    public function store(UploadedFile $file): array
    {
        $storagePath = self::DIRECTORY.'/'.Str::uuid7()->toString();

        $encryptedContent = $this->encryption->encrypt($file->get(), self::CONTEXT);

        Storage::disk(self::DISK)->put($storagePath, $encryptedContent);

        return [
            'path' => $storagePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    public function retrieve(MedicalRecordAttachment $attachment): array
    {
        $encryptedContent = Storage::disk(self::DISK)->get($attachment->file_path_encrypted);

        return [
            'content' => $this->encryption->decrypt($encryptedContent, self::CONTEXT),
            'original_name' => $attachment->original_filename_encrypted,
            'mime_type' => $attachment->mime_type,
        ];
    }
}
