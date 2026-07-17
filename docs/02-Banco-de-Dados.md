# 02 - Banco de Dados

## Convenções globais

- **Banco:** MySQL 8.4, `utf8mb4`/`utf8mb4_0900_ai_ci`.
- **Chave primária:** UUID em toda tabela de negócio (nunca IDs sequenciais expostos). Usar **UUID v7**
  (ordenável por tempo) via `Str::uuid7()`/coluna `uuid` do Laravel, em vez de UUID v4. Motivo: UUID v7
  mantém localidade de inserção no índice B-Tree do InnoDB, evitando a fragmentação de página que UUID
  v4 aleatório causa em tabelas grandes — importante para as tabelas de maior volume (`sessions`,
  `audit_logs`, `notifications`).
- **`tenant_id`:** presente em toda tabela que representa dado de negócio pertencente a um tenant
  (todas exceto tabelas verdadeiramente globais: `tenants`, `encryption_keys` de master, `cms_component_types`
  se globais). FK para `tenants.id`, sempre indexado em conjunto com as colunas mais consultadas
  (ex.: índice composto `(tenant_id, patient_id)`, `(tenant_id, scheduled_at)`).
- **Timestamps:** `created_at`, `updated_at` em todas as tabelas (padrão Eloquent).
- **Soft deletes:** `deleted_at` em toda tabela onde exclusão lógica faz sentido para auditoria/LGPD
  (pacientes, sessões, prontuários, cobranças, usuários). Tabelas de log/auditoria **não** têm
  `deleted_at` — são append-only por design (ver `04-Seguranca.md`).
- **Charset de dados sensíveis:** colunas cifradas são `VARBINARY`/`TEXT` (ciphertext em base64 ou
  binário), nunca `VARCHAR` legível.

## Entidades centrais (modelo lógico)

### `tenants`
`id (uuid, pk)`, `name`, `slug`, `plan`, `status`, `settings (json)`, `created_at`, `updated_at`,
`deleted_at`.

### `users`
`id (uuid, pk)`, `tenant_id (nullable — Super Admin não pertence a tenant)`, `name`, `email`,
`email_verified_at`, `password (hash)`, `mfa_totp_secret (cifrado)`, `mfa_enabled_at`,
`preferred_locale`, `created_at`, `updated_at`, `deleted_at`.
Papéis/permissões: tabelas padrão do `spatie/laravel-permission` (`roles`, `permissions`,
`model_has_roles`, `model_has_permissions`, `role_has_permissions`), com `roles.tenant_id` nullable
para permitir papéis globais (Super Admin) e papéis por tenant.

### `psychologists`
`id (uuid, pk)`, `tenant_id`, `user_id (fk users)`, `professional_registry` (CRP, cifrado),
`specialties (json)`, `default_session_duration_minutes`, `created_at`, `updated_at`, `deleted_at`.

### `patients`
`id (uuid, pk)`, `tenant_id`, `user_id (fk users, nullable até o paciente ativar conta)`,
`display_name`, `email`, `email_verified_at`,
campos opcionais pós-primeiro-acesso (todos cifrados quando sensíveis): `document_number_encrypted`,
`document_number_hash` (HMAC determinístico para busca exata — ver Estratégia de campo pesquisável),
`birth_date_encrypted`, `phones (json, cifrado)`, `emergency_contacts (json, cifrado)`,
`address_encrypted`, `created_at`, `updated_at`, `deleted_at`.

### `guardians`
`id (uuid, pk)`, `tenant_id`, `patient_id (fk patients)`, `name`, `document_number_encrypted`,
`document_number_hash`, `email`, `phone_encrypted`, `address_encrypted`, `relationship` (enum:
mãe, pai, tutor, outro), `created_at`, `updated_at`, `deleted_at`.
Regra de negócio (não de schema): obrigatório ter ao menos 1 `guardian` ativo quando
`patients.birth_date` implica idade < 16 anos — validado em `Patients`/`Guardians` Rules, não em
constraint de banco.

### `psychologist_availabilities`
`id (uuid, pk)`, `tenant_id`, `psychologist_id`, `weekday` (ou `date` para exceções pontuais),
`start_time`, `end_time`, `session_duration_minutes`, `buffer_minutes`, `type` (enum: recorrente,
bloqueio, férias, feriado, particular), `created_at`, `updated_at`, `deleted_at`.

### `sessions` (sessões clínicas — não confundir com sessão HTTP)
`id (uuid, pk)`, `tenant_id`, `patient_id`, `psychologist_id`, `scheduled_at`, `duration_minutes`,
`modality` (enum: presencial, online, domiciliar), `status` (enum: agendada, confirmada, realizada,
cancelada, reagendada, não_compareceu), `charge_id (fk financial_charges, nullable)`,
`medical_record_entry_id (fk medical_record_entries, nullable)`, `rescheduled_from_id (fk sessions,
nullable — histórico de reagendamento)`, `created_at`, `updated_at`, `deleted_at`.
Índice composto `(tenant_id, psychologist_id, scheduled_at)` — usado pelo bloqueio transacional contra
dupla reserva (`SELECT ... FOR UPDATE` dentro de transação ao criar/mover uma sessão).

### `waiting_list_entries`
`id (uuid, pk)`, `tenant_id`, `patient_id`, `psychologist_id`, `desired_period (json)`, `status`,
`created_at`, `updated_at`.

### `medical_record_entries`
Separado do cadastro do paciente, nunca sobrescrito — cada edição gera uma nova versão.
`id (uuid, pk)`, `tenant_id`, `patient_id`, `psychologist_id`, `session_id (nullable)`,
`version`, `previous_version_id (fk medical_record_entries, nullable)`,
`content_encrypted` (anotações/objetivos/plano terapêutico, cifrado), `encryption_key_version`,
`created_at` (imutável — sem `updated_at` porque nunca é atualizado, só versionado), `deleted_at`
(soft delete apenas para exclusão administrativa excepcional, permanece auditado).

### `medical_record_attachments`
`id (uuid, pk)`, `tenant_id`, `medical_record_entry_id`, `file_path_encrypted`, `original_filename_encrypted`,
`mime_type`, `size_bytes`, `encryption_key_version`, `created_at`, `deleted_at`.

### `financial_charges`
`id (uuid, pk)`, `tenant_id`, `patient_id`, `session_id (nullable — cobrança pode ser avulsa)`,
`amount`, `discount_amount`, `fine_amount`, `interest_amount`, `due_date`,
`status` (enum: em_aberto, pago, vencido, parcial, cancelado, estornado), `installment_number`,
`installment_total`, `created_at`, `updated_at`, `deleted_at`.

### `financial_payments`
`id (uuid, pk)`, `tenant_id`, `charge_id`, `amount`, `paid_at`, `method` (enum: dinheiro, cartão,
transferência, pix — pix reservado para integração futura), `gateway_reference (nullable)`,
`reversed_at (nullable)`, `created_at`, `updated_at`.

### `encryption_keys`
Ver detalhamento em `04-Seguranca.md`. `id (uuid, pk)`, `tenant_id (nullable — chave pode ser global
ou por tenant)`, `context` (enum: medical_records, patients_pii, guardians_pii...), `version` (int),
`wrapped_dek` (DEK cifrada pela Master Key), `status` (ativa, aposentada), `activated_at`,
`retired_at (nullable)`.

### `audit_logs`
Append-only (ver `04-Seguranca.md`). `id (uuid, pk)`, `tenant_id (nullable — login de Super Admin)`,
`actor_user_id`, `action` (enum/string: login, logout, auth_failure, create, update, delete, export,
download, financial_change, schedule_change, medical_record_change, admin_change),
`auditable_type`, `auditable_id`, `changes (json, antes/depois quando aplicável)`, `ip_address`,
`user_agent`, `created_at` (sem `updated_at`/`deleted_at`).

### `cms_pages` / `cms_page_versions`
`cms_pages`: `id (uuid, pk)`, `tenant_id`, `slug`, `current_version_id`, `published_at`.
`cms_page_versions`: `id (uuid, pk)`, `page_id`, `grapesjs_data (json)`, `html_rendered`,
`created_by`, `created_at`.

### `lgpd_consents`
`id (uuid, pk)`, `tenant_id`, `user_id`, `consent_type` (política de privacidade, termos de uso,
compartilhamento de dados clínicos), `document_version`, `accepted_at`, `ip_address`.

## Estratégia de campo pesquisável híbrido

Para colunas cifradas que precisam suportar busca exata (ex.: CPF de paciente), guardar duas colunas:
1. `<campo>_encrypted` — valor cifrado com AES-256-GCM (envelope encryption, ver `04-Seguranca.md`),
   usado para exibição/descriptografia.
2. `<campo>_hash` — HMAC-SHA256 determinístico do valor normalizado, usando uma chave de indexação
   derivada (não a DEK de conteúdo), indexado no banco. Buscas exatas comparam o hash do termo buscado
   contra essa coluna; nunca fazem `LIKE` sobre texto cifrado (impossível) nem sobre texto puro
   (não existe texto puro em repouso).
Busca parcial/fuzzy sobre campos sensíveis não é suportada por padrão (trade-off aceito em favor de
não expor dados em texto puro); se necessário no futuro, avaliar tokenização com hashes por n-grama,
registrado como ADR à parte quando/se for implementado.

## Índices de performance

- `sessions (tenant_id, psychologist_id, scheduled_at)` — agenda e checagem de conflito.
- `sessions (tenant_id, patient_id, scheduled_at)` — histórico do paciente.
- `financial_charges (tenant_id, patient_id, status)` — dashboards financeiros.
- `audit_logs (tenant_id, auditable_type, auditable_id)` — trilha por registro.
- `audit_logs (tenant_id, created_at)` — consultas por período.
- Toda tabela com `tenant_id` + soft delete: índice composto incluindo `deleted_at` quando queries
  filtram ativos com frequência.
