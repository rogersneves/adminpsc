<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers\Lgpd;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Services\AuditLogger;
use Modules\Security\Actions\Lgpd\PublishLegalDocumentAction;
use Modules\Security\Enums\LegalDocumentType;
use Modules\Security\Models\LegalDocument;
use Modules\Tenant\Support\CurrentTenant;

/**
 * Gestão dos documentos legais do tenant (política de privacidade / termos). Só
 * publica novas versões — nunca edita in place (histórico preservado). Restrito a
 * `manage-legal` (middleware na rota).
 */
class LegalDocumentController extends Controller
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(): Response
    {
        $documents = LegalDocument::query()
            ->orderBy('type')
            ->orderByDesc('version')
            ->get()
            ->map(fn (LegalDocument $doc) => [
                'id' => $doc->id,
                'type' => $doc->type->value,
                'type_label' => $doc->type->label(),
                'title' => $doc->title,
                'version' => $doc->version,
                'is_current' => $doc->is_current,
                'published_at' => $doc->published_at?->toIso8601String(),
            ]);

        return Inertia::render('Lgpd/LegalDocuments/Index', [
            'documents' => $documents,
            'types' => array_map(fn (LegalDocumentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], LegalDocumentType::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        $type = LegalDocumentType::from($request->string('type', LegalDocumentType::PrivacyPolicy->value)->toString());

        $current = LegalDocument::query()
            ->where('type', $type->value)
            ->where('is_current', true)
            ->first();

        return Inertia::render('Lgpd/LegalDocuments/Editor', [
            'type' => $type->value,
            'type_label' => $type->label(),
            'current' => $current ? ['title' => $current->title, 'content' => $current->content, 'version' => $current->version] : null,
        ]);
    }

    public function store(Request $request, PublishLegalDocumentAction $publish, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(LegalDocumentType::class)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $document = $publish(
            $this->tenant->get(),
            LegalDocumentType::from($validated['type']),
            $validated['title'],
            $validated['content'],
        );

        $audit->record(
            'lgpd.legal_document_published',
            actor: $request->user(),
            subject: $document,
            changes: ['type' => $document->type->value, 'version' => $document->version],
            tenantId: $this->tenant->id(),
        );

        return redirect()->route('lgpd.legal-documents.index')
            ->with('status', 'Nova versão publicada. Usuários precisarão aceitá-la.');
    }
}
