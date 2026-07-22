<?php

declare(strict_types=1);

namespace Modules\MedicalRecords\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Modules\MedicalRecords\Models\MedicalRecordAttachment;
use Modules\MedicalRecords\Services\AttachmentStorage;
use Modules\Tenant\Support\CurrentTenant;

class MedicalRecordAttachmentController extends Controller
{
    public function download(
        MedicalRecordAttachment $attachment,
        AttachmentStorage $storage,
        CurrentTenant $currentTenant,
    ): HttpResponse {
        $currentTenant->ownsOrFail($attachment);
        $this->authorize('medicalRecords.view', $attachment->entry->patient);

        $file = $storage->retrieve($attachment);

        return response($file['content'], 200, [
            'Content-Type' => $file['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.addslashes($file['original_name']).'"',
        ]);
    }

    public function destroy(MedicalRecordAttachment $attachment, CurrentTenant $currentTenant): RedirectResponse
    {
        $currentTenant->ownsOrFail($attachment);
        $this->authorize('medicalRecords.create', $attachment->entry->patient);

        $attachment->delete();

        return back()->with('status', 'Anexo removido.');
    }
}
