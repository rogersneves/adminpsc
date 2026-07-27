<?php

declare(strict_types=1);
use Modules\Guardians\Models\Guardian;
use Modules\MedicalRecords\Models\MedicalRecordAttachment;
use Modules\Patients\Models\Patient;
use Modules\Psychologists\Models\Psychologist;
use Modules\Users\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Master Key
    |--------------------------------------------------------------------------
    |
    | Chave de 32 bytes (base64) usada exclusivamente para cifrar/decifrar as
    | Data Encryption Keys (envelope encryption). Nunca usada para cifrar dado
    | de negócio diretamente. Gerar com: php artisan security:master-key:generate
    |
    | @see docs/04-Seguranca.md
    */
    'master_key' => env('ENCRYPTION_MASTER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Política de sessão
    |--------------------------------------------------------------------------
    */
    'session' => [
        'absolute_minutes' => (int) env('SESSION_ABSOLUTE_TIMEOUT_MINUTES', 480),
        'inactivity_minutes' => (int) env('SESSION_INACTIVITY_TIMEOUT_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA
    |--------------------------------------------------------------------------
    */
    'mfa' => [
        'email_otp_ttl_minutes' => (int) env('MFA_EMAIL_OTP_TTL_MINUTES', 10),
        'email_otp_length' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cabeçalhos de segurança HTTP (Fase 9)
    |--------------------------------------------------------------------------
    |
    | Aplicados a toda resposta web por Modules\Security\Http\Middleware\
    | SecurityHeaders. HSTS só é emitido em requisições https (não faz sentido —
    | e é ignorado pelo navegador — em http de dev). A CSP tem `style-src
    | 'unsafe-inline'` porque tanto o React/shadcn quanto as páginas públicas do
    | CMS (Blade com <style> inline sanitizado) dependem de estilo inline; scripts
    | continuam restritos a 'self' (o bundle Vite é servido do próprio domínio).
    |
    | @see docs/04-Seguranca.md "Superfície OWASP / ASVS"
    */
    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

        'content_security_policy' => env('SECURITY_CSP', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ])),

        'hsts' => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains'),

        'referrer_policy' => 'strict-origin-when-cross-origin',

        'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=()',

        'frame_options' => 'SAMEORIGIN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotação de chaves — registro de contextos recifráveis (Fase 9)
    |--------------------------------------------------------------------------
    |
    | Mapeia cada contexto de envelope encryption para o(s) Model(s) que o usam.
    | O RotateEncryptionKeyJob descobre sozinho, via getCasts() do Model, quais
    | atributos pertencem ao contexto — então só é preciso listar o Model aqui,
    | não cada coluna.
    |
    | Deliberadamente FORA desta lista (a chave rotaciona, mas a recifragem em
    | massa NÃO é automática, é uma pendência documentada):
    |   - `medical_record_content`: MedicalRecordEntry é append-only (update()
    |     lança exceção), não pode ser recifrado in place por save().
    |   - blob do arquivo de anexo em disco (contexto usado direto pelo
    |     AttachmentStorage, não por cast) — recifrar arquivo exige reprocessar
    |     o storage, não um round-trip de atributo.
    | Dado cifrado com a DEK aposentada continua legível normalmente nesses casos;
    | só a migração para a nova versão fica pendente.
    |
    | @see docs/04-Seguranca.md, docs/06-Roadmap.md (Fase 9)
    */
    'encryption_contexts' => [
        'mfa_totp_secret' => User::class,
        'patient_document_number' => Patient::class,
        'patient_birth_date' => Patient::class,
        'patient_address' => Patient::class,
        'patient_phones' => Patient::class,
        'patient_emergency_contacts' => Patient::class,
        'guardian_document_number' => Guardian::class,
        'guardian_phone' => Guardian::class,
        'guardian_address' => Guardian::class,
        'psychologist_professional_registry' => Psychologist::class,
        'medical_record_attachment_path' => MedicalRecordAttachment::class,
        'medical_record_attachment_filename' => MedicalRecordAttachment::class,
    ],
];
