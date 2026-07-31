# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

AdminPSC — Sistema de Gestão Clínica para Psicólogos. Laravel 13 + Inertia.js + React 19 + Tailwind
CSS v4 + shadcn/ui, built as a modular monolith with multi-tenant SaaS ambitions from day one.

**Read `docs/` before making architectural decisions.** It is the source of truth for this project,
more detailed than this file:
- `docs/01-Arquitetura.md` — layers, module list, multi-tenant strategy, ADRs.
- `docs/02-Banco-de-Dados.md` — logical schema, UUID/tenant_id conventions, encrypted/searchable fields.
- `docs/03-Padroes-de-Codigo.md` — naming conventions per layer, testing conventions.
- `docs/04-Seguranca.md` — envelope encryption (Master Key/DEK), MFA, session policy, immutable audit log, LGPD.
- `docs/05-UIUX-Design-System.md` — component structure, i18n rules, WCAG 2.2 AA requirements.
- `docs/06-Roadmap.md` — phased delivery plan; check this before starting a new module's business logic.

## Environment quirk: PHP is not on PATH

This is a WAMP setup. PHP 8.4 and Composer are **not** on the default shell PATH. Every `php`/`composer`/
`artisan` command needs the WAMP PHP prepended for the session:

```powershell
$env:Path = "D:\wamp64\bin\php\php8.4.15;$env:Path"
```

MySQL client: `D:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe`. The app database is `adminpsc`
(root / no password, WAMP default), created manually — there is no seeded migration that creates it.
On a fresh machine/checkout, create it first:

```powershell
& "D:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe" -uroot -e "CREATE DATABASE IF NOT EXISTS adminpsc CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
```

## Commands

```bash
# Backend + frontend dev servers, queue worker and logs together (after prepending PHP to PATH)
composer dev

# Just the frontend
npm run dev
npm run build

# Migrations
php artisan migrate
php artisan migrate:fresh   # destructive — confirm with the user first

# Tests (PHPUnit exclusively — no Pest)
composer test
php artisan test
php artisan test --filter=SomeTestName
php artisan test tests/Feature/Path/To/SomeTest.php

# Code style
vendor/bin/pint

# Modules (nwidart/laravel-modules)
php artisan module:list
php artisan module:make {Name}                 # scaffolds a new module with the project's standard subfolders
php artisan module:make-controller {Name} {Module}
php artisan module:make-model {Name} {Module}
php artisan module:make-migration {name} {Module}
# After creating/renaming a module, refresh autoload (composer-merge-plugin merges Modules/*/composer.json):
composer dump-autoload

# Add a shadcn/ui component (writes into resources/js/components/ui, not a runtime dependency)
npx shadcn@latest add {component}

# Seed roles/permissions (idempotent — safe to re-run)
php artisan db:seed

# Envelope encryption master key (put the output in .env as ENCRYPTION_MASTER_KEY)
php artisan security:master-key:generate

# Create a Super Admin (platform-level, no tenant — no public signup form for this on purpose)
php artisan authorization:make-super-admin {email} {name} --password={optional}
```

## Architecture summary

Modular monolith via `nwidart/laravel-modules` — each business area lives under `Modules/{Name}` with
its own isolated `app/`, `routes/`, `config/`, `lang/`, `database/migrations`, `resources/js/{Pages,Components}`.
See `docs/01-Arquitetura.md` for the full rationale (ADR-001).

**The 18 initial modules:** Core, Tenant, Authentication, Authorization, Users, Psychologists, Patients,
Guardians, Scheduling, MedicalRecords, Financial, Payments, Reports, Notifications, CMS, Audit, Security,
Settings.

**Layering inside every module** (`docs/01-Arquitetura.md` has the full table):
`Controller` (orchestration only) → `Action`/`Service` (business rules) → `Repository` (only when it adds
real value — not for trivial CRUD) → `Model`. Cross-cutting: `DTOs`, `Enums`, `Events`, `Jobs`,
`Notifications`, `Policies`, `Rules`, `Traits`, `Exceptions`. **Business rules never live in Controllers
or in React components.**

**Multi-tenant:** single database, `tenant_id` column + Eloquent Global Scope (`Modules\Tenant\Models\
Scopes\TenantScope`) on every Model using `Modules\Tenant\Traits\BelongsToTenant` (ADR-003). Middleware
`resolve.tenant` populates the `Modules\Tenant\Support\CurrentTenant` singleton after `auth`. **`User`
deliberately does not use `BelongsToTenant`** — login must find a user by email before any tenant is
resolved; see the note in `docs/01-Arquitetura.md`. `Patient`, `Guardian`, `Psychologist` (Fase 2), and
`Session`/`WaitingListEntry`/`PsychologistAvailability` (Fase 3) are the real consumers of the strict
scope — **every route that touches one of these models MUST include `resolve.tenant` in its middleware,
or it 500s in real usage.** `resolve.tenant` is registered in `bootstrap/app.php` with explicit priority
(`prependToPriorityList`) to run before `SubstituteBindings` — without that, implicit route-model-binding
(`{psychologist}`, `{session}`, etc.) resolves before the tenant does, no matter where `resolve.tenant`
sits in the route's own middleware array (Fase 3 gotcha below — this is the big one, read it before
adding any new route with a `{tenantScopedModel}` parameter). On top of the scope, any Controller that
receives a `BelongsToTenant` model via route binding also calls
`Modules\Tenant\Support\CurrentTenant::ownsOrFail($model)` explicitly — defense in depth, and the only
way this specific check is actually exercised by PHPUnit (see below for why the scope alone isn't
testable). Cross-tenant isolation for a real business model is covered by
`tests/Feature/Tenant/PatientTenantIsolationTest.php` and `tests/Feature/Scheduling/
SchedulingTenantIsolationTest.php`; middleware resolution itself by `tests/Feature/Tenant/
ResolveTenantTest.php`.
`tenant_id` is in each model's `$fillable` (same precedent as `User`) — safe here because every Action in
this codebase builds explicit attribute arrays and never forwards raw request input into `create()`.

**RBAC:** `spatie/laravel-permission`, seeded via `Modules\Authorization\Database\Seeders\
RolesAndPermissionsSeeder` (called from `DatabaseSeeder`). 7 roles: `super_admin`, `admin_clinica`,
`psicologo`, `secretaria`, `financeiro`, `paciente`, `responsavel_legal`. Seeded permissions:
`super_admin` gets all (`manage-users`, `manage-clinic-settings`, `view-audit-log`,
`platform.manage-tenants`, `manage-financial`, `manage-cms`, `manage-legal`, `manage-scheduling`);
`admin_clinica` gets all but `platform.manage-tenants`; `financeiro` gets `manage-financial` (Fase 5).
`manage-cms` (Fase 8) and `manage-legal` (Fase 10, LGPD documents) are on `super_admin`/`admin_clinica`.
**Fase 11 finally exercises two long-seeded-but-unused permissions:** `manage-clinic-settings` gates
`/configuracoes` (per-tenant settings) and `platform.manage-tenants` gates `/plataforma/tenants`
(Super-Admin cross-tenant management). **The "marcos" work then activated the last idle role:**
`manage-scheduling` (units/secretaries marco) is on `super_admin`/`admin_clinica`/**`secretaria`** — the
first real permission the `secretaria` role has held since Fase 1. The remaining roles earn permissions as
their modules ship.

**Authentication flow (Fase 1, done):** registration (`POST /register`) creates a `Tenant` + `User`
(role `admin_clinica`) and logs the user in immediately without MFA (see the ADR-style comment in
`RegisterClinicAdminAction` for why that's intentional), gated behind email verification
(`middleware('verified')`) for real app access. Every subsequent login (`POST /login`) requires a second
factor before `Auth::login()` runs: email OTP by default (code cached, not a DB table — see `AttemptLoginAction`),
or TOTP once the user has enabled it (`GET/POST /security/totp/setup`, via `pragmarx/google2fa-laravel`).
Session has an absolute timeout and an inactivity timeout enforced by `Modules\Security\Http\Middleware\
EnsureSessionIsValid` (global `web` middleware, config in `config/security.php`). All of this is exercised
end-to-end by `tests/Feature/Authentication/*` and `tests/Feature/Tenant/ResolveTenantTest.php`.

**Envelope encryption:** `Modules\Security\Services\EncryptionService` (AES-256-GCM, Master Key wraps a
per-context DEK stored in `encryption_keys`). Two reusable Eloquent casts:
`Modules\Security\Casts\EnvelopeEncrypted::class.':context-name'` for scalar strings (used by
`User::mfa_totp_secret`, `Patient`/`Guardian` CPF/address/birth_date, `Psychologist` CRP), and
`Modules\Security\Casts\EncryptedJson::class.':context-name'` for structured values (`Patient::phones`,
`Patient::emergency_contacts` — serializes to JSON, then encrypts). Exact-match search on an encrypted
field (e.g. find a patient by CPF) uses `EncryptionService::searchHash()` — an HMAC-SHA256 keyed off the
Master Key, stored in a sibling `*_hash` column, never a `LIKE` on ciphertext or plaintext. Full key
rotation/versioning is still Fase 9 — this phase only has a single active DEK per context.

**Patients/Psychologists/Guardians (Fase 2, done):** patient self-registers under a specific clinic via
`GET/POST /c/{tenant:slug}/paciente/registro` (tenant resolved by **route-model-binding on `slug`**, not
by the `resolve.tenant` middleware — there's no authenticated user yet to resolve a tenant from). Same
"auto-login without MFA, gated by `verified`" pattern as clinic admin registration. Optional profile
fields (CPF, phone, address, birth date, emergency contacts) go through `GET/PUT /paciente/perfil`
(`Modules\Patients\Http\Controllers\PatientProfileController`); submitting a `birth_date` that implies
age < 16 requires a guardian — either already on file or included in the same request's `guardians[]`
array — enforced by `Modules\Guardians\Rules\PatientRequiresGuardianIfMinor`. Guardians are contact-only
records (no `user_id`, no login — the `responsavel_legal` role stays seeded but unused). Psychologists are
created by `admin_clinica`/`super_admin` (`POST /psicologos`, gated by the existing `manage-users`
permission — no new permission needed), which sends a password-reset link instead of a temporary password.

**Scheduling (Fase 3, done):** availability rules (`Modules\Psychologists\Models\PsychologistAvailability`,
managed by the psychologist via `GET/POST /psicologos/{psychologist}/disponibilidade`) feed
`Modules\Scheduling\Services\AvailabilityCalculator`, which computes bookable slots on-the-fly (no slot
table) for a rolling window (`config('scheduling.booking_horizon_days')`, default 30). Booking
(`POST /agenda/{psychologist}/reservar`) goes through `Modules\Scheduling\Actions\BookSessionAction`,
which locks the `Psychologist` row (`lockForUpdate()`) and re-validates the exact slot inside the
transaction before inserting — that's the double-booking guard, not a lock on the (not-yet-existing)
session row. Cancel/reschedule enforce a minimum-notice window
(`config('scheduling.minimum_reschedule_notice_hours')`, default 24h,
`Modules\Scheduling\Traits\EnsuresMinimumNotice`); reschedule never mutates the original row — it cancels
it and books a new one linked via `rescheduled_from_id`. **The clinical-session table is called
`clinical_sessions`, not `sessions`** — `sessions` is already Laravel's own HTTP session table
(`SESSION_DRIVER=database`). The Eloquent model is still `Modules\Scheduling\Models\Session`
(`protected $table = 'clinical_sessions'`).

**MedicalRecords (Fase 4, done):** the clinical record (`Modules\MedicalRecords\Models\
MedicalRecordEntry`) is append-only, deliberately separate from `Patient` — `update()` is overridden to
throw (same pattern as `AuditLog`), so editing means creating a new row (`version` incremented,
`previous_version_id` linking to the prior row), never mutating one in place; fields omitted from a new
version inherit the previous version's value. `delete()` is **not** overridden — soft delete stays
available for the documented "exclusão administrativa excepcional" case, because
`SoftDeletes::runSoftDelete()` updates via a raw query builder call (`$this->newModelQuery()->update()`),
bypassing `Model::update()` entirely — only override the method the framework actually routes through for
the behavior you want to block. Content (`notes`/`therapeutic_objectives`/`therapeutic_plan`) is one JSON
blob per version via `EncryptedJson` (same envelope-encryption primitives as Fase 1/2, no changes needed).
Attachments (`Modules\MedicalRecords\Models\MedicalRecordAttachment`, one per version) encrypt the whole
file in memory (`Modules\MedicalRecords\Services\AttachmentStorage`, via the existing
`EncryptionService` — byte-safe on PHP strings, no streaming, 10MB cap) and store it under a random UUID
path on the private `local` disk; the original filename is encrypted too. "Psicólogo responsável" is
derived, not a stored assignment: any psychologist with an existing `Session` (Fase 3) for that patient
has read/write access, modeling shared care within a clinic without a separate case-assignment table.
Authorization is `Gate::define('medicalRecords.view'|'medicalRecords.create', [MedicalRecordPolicy::class,
'view'|'create'])` rather than `Gate::policy()`, because the decision is over a `(User, Patient)` pair, not
over an already-existing `MedicalRecordEntry` instance. Patients do **not** access their own record this
phase — that's deferred to Fase 10 as a formal LGPD access-request flow, not self-service.

**Financial/Payments (Fase 5, done):** cobrança (`Modules\Financial\Models\FinancialCharge`) and payment
(`Modules\Payments\Models\Payment`) are deliberately separate Models in separate modules —
`FinancialCharge` is **not** append-only (needs normal `update()` for status transitions, late-fee
recalculation, discount edits), but `Payment` is never edited or deleted: reversal is `reversed_at`,
never `delete()`, so "this charge had a payment that got reversed" stays distinguishable from "this
charge was never paid." A charge's `status` is never stored as independent truth — it's always
recomputed from its non-reversed payments by `Modules\Financial\Services\ChargeStatusCalculator`: paid
total ≥ total due → `pago`; partially covered → `parcial`; had a payment that's now fully reversed →
`estornado` (distinct from `em_aberto`/`vencido`, which never had any payment); `cancelado` is terminal
and never recalculated over. Installments (`CreateChargeAction`) generate N independent
`financial_charges` rows — there's no "installment plan" table in the documented schema, just
`installment_number`/`installment_total` for display — amount and discount are split in integer cents
with the last installment absorbing the rounding remainder, due dates spaced by a month. Late fees follow
the common Brazilian convention (2% flat fine + 1%/month pro-rata interest, `config/financial.php`,
`FINANCIAL_LATE_FINE_PERCENT`/`FINANCIAL_LATE_INTEREST_PERCENT_PER_MONTH`) recalculated (not accumulated)
daily by `php artisan financial:apply-late-fees`, scheduled via `configureSchedules()` in
`FinancialServiceProvider` (nwidart's own module-scheduling hook — see the Fase 5 gotcha below).
`RecordPaymentAction`/`ReversePaymentAction` lock the `FinancialCharge` row with `lockForUpdate()` before
recalculating status, same pattern as `BookSessionAction` (Fase 3). Authorization is
`Gate::define('financial.view'|'financial.manage', [FinancialPolicy::class, ...])`, same non-`Gate::policy`
shape as `MedicalRecordPolicy` (Fase 4): a psychologist who has treated the patient gets **read-only**
access; only `manage-financial` (`super_admin`/`admin_clinica`/`financeiro`) can create a charge,
record/reverse a payment, edit a discount, or cancel — **`financeiro` is seeded since Fase 1 and this is
the first permission it's ever actually been given.** `PaymentGatewayInterface` (`Modules\Payments\
Contracts`) is only ever a contract — no implementation, no container binding — per the roadmap's
explicit "no real integration yet"; `pix` exists as a `PaymentMethod` case but is still just a manual
staff-recorded entry, same as cash/card/transfer.

**Reports/Dashboards (Fase 6, done):** three separate psychologist-facing reports — Sessions, Financial,
Attendance (`Modules\Reports\Actions\Build{Sessions,Financial,Attendance}ReportAction`) — each with an
Inertia filter+table page and a PDF (`barryvdh/laravel-dompdf`) and Excel (`maatwebsite/excel`) export
sharing the same Action, generated **synchronously in the request** (no queue, no polling) — the
architecture lists PDF/Excel generation as Job work, but the Notifications module that would announce
"your file is ready" doesn't exist until Fase 7, so async generation with nothing to notify would be
half-built; revisit when Notifications ships. No new tables anywhere in this phase — everything is
computed on-the-fly from `clinical_sessions`/`financial_charges`/`financial_payments`.
`Modules\Reports\Support\PsychologistPatientScope` derives the psychologist's "book" from `Session`
(same pattern as `MedicalRecordPolicy`/`FinancialPolicy`, Fases 4/5): `admin_clinica`/`super_admin` see
the whole tenant (or one psychologist via an optional filter); `psicologo` only ever sees their own book.
Patient-facing "sessões" and "situação financeira" reuse Fase 3's `/minhas-sessoes` and Fase 5's
`/pacientes/{patient}/financeiro` outright rather than rebuilding — the only change needed was extending
`FinancialPolicy::view` to allow `$actor->id === $patient->user_id`, which closes the Fase 5 deferral
("portal do paciente pro próprio financeiro") for free since `Ledger.jsx` already hides every management
control when `canManage` is `false`. "Recibos" is a PDF per `Payment`
(`Modules\Payments\Http\Controllers\PaymentReceiptController`), authorized by the same `financial.view`
ability, listing the one `Session` linked to the underlying charge when present (the schema only supports
charge→session as 0-or-1, not a N:N table). Dashboards
(`Modules\Reports\Http\Controllers\DashboardController`, now owning `GET /dashboard` instead of the old
top-level closure) only compute real data for `psicologo` and `paciente` — the only roles the roadmap
bullet names; every other role keeps the generic welcome card. "Pacientes ativos/inativos" and
"aniversariantes" require decrypting `Patient::birth_date_encrypted` in a PHP loop — there's no `_hash`
column for month/day the way `document_number` has one for exact search, so this can't be pushed into
SQL; acceptable at single-clinic scale, not something to "fix" by adding a search hash unless volume
actually becomes a problem.

**Notifications (Fase 7, done):** first phase where a business module actually dispatches a domain
Event per `docs/03-Padroes-de-Codigo.md`'s "Eventos para efeitos colaterais" rule — through Fase 6,
Actions only mutated state directly. `CancelSessionAction`/`RescheduleSessionAction` (`Modules\
Scheduling`), `CreateChargeAction` (`Modules\Financial`), and `RecordPaymentAction`/`ReversePaymentAction`
(`Modules\Payments`) now dispatch `SessionWasCancelled`/`SessionWasRescheduled`/`ChargeWasCreated`/
`PaymentWasRecorded`/`PaymentWasReversed`; Listeners in `Modules\Notifications\Listeners` consume them
and send the matching Notification — Scheduling/Financial/Payments never import anything from
`Modules\Notifications`, same low-coupling direction as the doc describes. Every Notification extends
`Modules\Notifications\Notifications\TenantNotification` (`ShouldQueue` + `SerializesModels`), whose
`via()` reads `config('notifications.channels.default')` (`mail,database` by default, env-configurable)
instead of each subclass hardcoding a channel — adding SMS/WhatsApp later is adding the channel name to
that config and a `toSms()`/`toWhatsApp()` method per class, no refactor of the 8 existing Notifications
or their Listeners. Two of the 8 (`SessionReminderNotification`, `ChargeDueSoonNotification`/
`ChargeOverdueNotification`) are sent by polling console commands
(`notifications:send-session-reminders` hourly, `notifications:send-charge-reminders` daily — registered/
scheduled in `NotificationsServiceProvider` exactly like Fase 5's `ApplyLateChargeFees`), not by an Event,
since a reminder isn't a reaction to something that just happened — idempotency comes from dedicated
tracking columns (`clinical_sessions.reminder_sent_at`, `financial_charges.due_soon_reminder_sent_at`/
`overdue_reminder_sent_at`), added via migrations that live in `Modules\Notifications\database\
migrations` even though they alter Scheduling's/Financial's tables, because the sole reason those columns
exist is Notifications' own dedup logic. The `database` channel's `notifications` table uses
`$table->uuidMorphs('notifiable')`, not the framework-default `morphs()` (which generates a
bigint `notifiable_id`) — same class of fix as `model_has_roles` in Fase 1, needed because `User` has a
UUID PK. No `tenant_id` on that table on purpose — isolation comes entirely from the
`$user->notifications()` relation, the same reasoning that already justifies `User` skipping
`BelongsToTenant`. Cancel/reschedule notify **both** patient and psychologist (`SessionPolicy` already
lets either one trigger those actions since Fase 3), not just whoever acted. Registration/e-mail
confirmation deliberately did **not** get folded into this architecture — Fase 1's native
`MustVerifyEmail`/`sendEmailVerificationNotification()` flow already covers it and refactoring a working,
tested flow into the new base class for its own sake wasn't worth the risk. `/notificacoes` (list,
mark-one-read, mark-all-read) plus a `NotificationBell` in `Dashboard.jsx` and a global
`unreadNotificationsCount` Inertia shared prop (`HandleInertiaRequests`) round out the module; there's no
shared authenticated layout in this codebase yet (every page still builds its own wrapper), so the bell
only appears on the Dashboard for now, not on every authenticated page.

**CMS (Fase 8, done):** tenant-scoped public pages (`Modules\CMS\Models\Page`, table `cms_pages`,
`BelongsToTenant`, **not encrypted** — a public page is public by definition, no PII at rest) edited in a
GrapesJS visual builder embedded in an Inertia/React page (`resources/js/Pages/CMS/Editor.jsx`, GrapesJS
mounted in a `useEffect` over a ref — it's vanilla-JS, not React). Uses `grapesjs-preset-webpage`, **not
the `grapesjs-preset-newsletter`** named in the roadmap (newsletter is table-based email layout; webpage
is the correct preset for public web pages — same "resolve ambiguity toward the robust decision" call as
ADR-005). Nine custom blocks (`resources/js/cms/blocks.js`: Banner, Hero, Cards, FAQ, Depoimentos, Botão,
Formulário, Contato, Rodapé), each with self-contained inline styles so they render cleanly on the public
page (plain Blade, **not** the app's Tailwind bundle). Raw-HTML import is disabled in the editor
(`modalImportButton: false`) per the roadmap's "no manual HTML editing." A page stores `html`/`css` (the
sanitized publish artifacts served to visitors) separately from `project_data` (the GrapesJS editor state
for reopening — never exposed publicly); `slug` is unique per tenant, `status` is `PageStatus`
(`rascunho`/`publicada`), `is_home` is enforced single-per-tenant in the Action. **HTML/CSS is sanitized
on save (not just on render) by `Modules\CMS\Services\HtmlSanitizer`** — defense-in-depth stored-XSS
barrier: even though users edit via blocks (no raw HTML), the output is served in `{!! $html !!}` on a
public page. Allowlist of tags+attributes via DOMDocument (safer than regex), strips
`<script>`/`<iframe>`/`on*` handlers/`javascript:` & `data:text/html` URLs while preserving the
classes/ids/inline-styles that pair with the CSS blob; CSS sanitized separately (`@import`/`expression()`/
`javascript:` removed). Admin CRUD (`/cms/paginas...`, Inertia) is gated by the new `manage-cms`
permission via `PagePolicy` (registered with `Gate::policy` — here the resource **is** the `Page`, unlike
the `(User, Patient)`-pair policies of Fases 4/5) plus `resolve.tenant` + `CurrentTenant::ownsOrFail` on
the bound `Page`. Public rendering is **server-side Blade** (`cms::public.show`), **not Inertia** — it's
user-designed HTML, not a React screen: guest routes `GET /c/{tenant:slug}` (home) and
`GET /c/{tenant:slug}/p/{pageSlug}` (by slug), tenant resolved via `{tenant:slug}` binding (no
`resolve.tenant` in guest context), `Page` queried manually with `withoutTenantScope()` +
explicit `where('tenant_id')` (same reasoning as public patient registration, Fase 2). Only `publicada`
pages are served; draft/inactive-tenant → 404.

**Security/Audit hardening (Fase 9, done):** three things. (1) **Security headers** —
`Modules\Security\Http\Middleware\SecurityHeaders` appended to the `web` group in `bootstrap/app.php`,
emitting CSP / `X-Content-Type-Options` / `X-Frame-Options` / `Referrer-Policy` / `Permissions-Policy`
always and HSTS **only over https** (`$request->isSecure()`). Fully config-driven
(`config/security.php` → `headers`, toggle `SECURITY_HEADERS_ENABLED`); CSP keeps `style-src
'unsafe-inline'` (React/shadcn + the CMS public Blade pages need inline styles) but `script-src 'self'`.
It never overwrites a header a route already set. (2) **Encryption key rotation** — closes the Fase
1/ADR-006 deferral. `EncryptionService::rotate($context)` retires the active DEK and creates the next
active version in a `lockForUpdate` transaction; old ciphertext stays readable via the retired DEK
(the version has always travelled inside the bundle). `RotateEncryptionKeyJob` re-encrypts old rows to
the new version in the background, **auto-discovering which attributes belong to the context from the
model's `getCasts()`** (registry `security.encryption_contexts` maps context→model only, not columns).
Command `php artisan security:rotate-key {context?} {--sync}`. **`medical_record_content` and the
on-disk attachment blobs are deliberately NOT auto-re-encrypted** (MedicalRecordEntry is append-only so
`update()` throws; the file blob is encrypted by `AttachmentStorage` directly, not a cast) — their keys
can still rotate, only the bulk migration is deferred. (3) **Audit coverage** —
`Modules\Audit\Listeners\RecordDomainAuditEvents` consumes the same 5 domain events Notifications does
(Scheduling cancel/reschedule, Financial charge, Payment record/reverse) and writes `audit_logs` rows;
it's **synchronous on purpose** (the `AuditLogger` reads actor from `auth()->user()` and IP/UA from
`Request`, all request-context — queueing would lose them). Plus `throttle:30,1` on report PDF/Excel
exports and the payment-receipt download (rate limiting was login/MFA/reset only before).

**LGPD (Fase 10, done):** lives in `Modules\Security` under an `Lgpd` namespace (docs/04 documents LGPD
inside the Security chapter — no 19th module invented). Four pieces. (1) **Versioned legal documents** —
`LegalDocument` (`legal_documents`, `BelongsToTenant`, types `privacy_policy`/`terms_of_use` via
`LegalDocumentType`): `PublishLegalDocumentAction` never edits in place — it creates the next `version`
as `is_current` and retires the prior (history preserved), gated by the new `manage-legal` permission.
(2) **Append-only consent** — `Consent` (`lgpd_consents`, `update()`/`delete()` throw like `AuditLog`;
**note `protected $table = 'lgpd_consents'`** since the class is `Consent`); `RecordConsentAction` stores
type/version/timestamp/IP/UA and audits `lgpd.consent_recorded`. (3) **Re-consent gating** —
`EnsureLgpdConsent` appended to the `web` group: if the user's tenant has an `is_current` required
document they haven't accepted (`ConsentChecker`), redirect to `/lgpd/consentimento`. **Opt-in per
clinic**: no current document → no-op, so it doesn't touch existing flows/tests (none seed documents).
Publishing a new version silently re-triggers gating (the accepted version is no longer current). The
gating middleware exempts by **path** (`lgpd/consentimento`, `logout`, `login`, `email/verify*`) not
`routeIs()` — group middleware can run before the route is fully resolved. **A subtle consequence: an
admin who publishes the first legal document is then gated by their own document on the very next
request** — correct behaviour, but it means the "publish twice over HTTP" path can't reach the second
publish without accepting in between (the supersede test drives `PublishLegalDocumentAction` directly for
that reason). (4) **Art. 18 access/portability** (closes the Fase 4 deferral) — `/lgpd/meus-dados` +
`/lgpd/meus-dados/download` build the subject's own data via `BuildPersonalDataExportAction` (decrypted
PII + sessions + charges + consents), the download audited (`lgpd.data_exported`) and throttled. Plus
**irreversible anonymization** — `AnonymizePatientAction` + `php artisan lgpd:anonymize-patient {id}
--force` replaces PII with markers, nulls encrypted columns/hashes, stamps `anonymized_at` (new column
added to `patients` by a Security-module migration, Fase-7 precedent), soft-deletes, and cascades to
guardians + the login account, keeping the row for retention duties; audited `lgpd.patient_anonymized`,
idempotent. Note `medical_record_content` is deliberately left out of anonymization (append-only +
encrypted — same class of deferral as the Fase 9 rotation).

**SaaS productization (Fase 11, done):** the phase that finally gives the `Settings` module a body. (1)
**Plans** are a config catalogue (`config/plans.php`, not a table — plans are platform catalogue, not
tenant data): `trial`/`basico`/`profissional`, each with `max_psychologists`/`max_patients` (`null` =
unlimited). `Modules\Settings\Services\PlanLimits` enforces them; `RegisterPsychologistAction` calls
`assertCanAddPsychologist` before creating anything and `PsychologistController` converts the
`PlanLimitReachedException` into a validation error on `plan`. **Real payment/PIX billing stays a future
milestone** — "billing" here is subscription state + trial + limit enforcement, no gateway. (2)
**Provisioning** — `ProvisionTenantAction` is the single "how a tenant is born" (default plan, trial
window from `config('plans.trial_days')`, unique slug); `RegisterClinicAdminAction` was refactored to use
it so self-signup and Super-Admin creation land identically. New column `tenants.trial_ends_at`
(`plan`/`status`/`settings` already existed since Fase 1). (3) **Per-tenant config** —
`Modules\Settings\Services\TenantSettings` reads from `tenants.settings` (JSON) **with fallback to global
`config/*`**: a tenant that never touched a key inherits the default, nothing duplicated in the DB. A
registry of known keys (scheduling booking horizon + minimum reschedule notice; branding display name +
primary colour), edited at `/configuracoes` (`manage-clinic-settings`). **This closes the old "session
timeout / minimum notice configurable per tenant" deferrals** (Fases 1/3): scheduling consumers
(`AgendaController`, `EnsuresMinimumNotice`) now read `TenantSettings->current(...)`. **Settings are sent
and validated nested** (`scheduling[booking_horizon_days]`) so Laravel's `'scheduling.x'` dot-rules work,
then flattened with `Arr::dot()` before `TenantSettings::set()` (whose registry is keyed by dot-strings) —
don't send flat literal-dotted field names, they won't validate as nested. (4) **Platform tenant
management** — `/plataforma/tenants` (`PlatformTenantController`) is the **first route to use
`platform.manage-tenants`** (seeded since Fase 1, unused until now): Super Admin lists/provisions/changes
plan+status of any tenant, **without `resolve.tenant`** (Super Admin is cross-tenant and has no tenant of
their own; `Tenant` isn't `BelongsToTenant` so binding works unscoped). Per-tenant **branding** is a lazy
Inertia shared prop (`branding` in `HandleInertiaRequests`). ADR-003 (column isolation) was re-evaluated
and **kept** — documented as ADR-007 in `docs/01`; physical isolation stays a per-client future trigger,
not the default topology.

**Marcos pós-Fase-11 (vision items, done):** two of `docs/06-Roadmap.md`'s unnumbered "Marcos futuros".
**(1) Multi-unit + secretaries.** `Modules\Settings\Models\Unit` (`units`, per-tenant branches) + CRUD
(`/unidades`, `manage-clinic-settings`); a `unit_user` **pure pivot** (composite PK `(unit_id, user_id)`,
**no own `id`** — a uuid PK with no default breaks `belongsToMany::sync()` on strict MySQL) assigns
psychologists/secretaries to units; `clinical_sessions.unit_id` (nullable) is stamped by
`BookSessionAction` from the psychologist's unit. The dormant `secretaria` role is now live: permission
`manage-scheduling`, invite via `/secretarias` (`InviteSecretaryAction`, same reset-link pattern as
psychologists), and **unit scoping** via `Modules\Settings\Services\UnitScope` — `unitIdsFor()` returns
`null` for admin (all units) or the secretary's assigned unit ids, driving the read-only
`/agenda-unidade`. Psychologists get a units multiselect on their create form. **(2) Convênios +
teleconsulta.** `Modules\Financial\Models\HealthPlan` (`health_plans`) + CRUD (`/convenios`,
`manage-financial`); the patient picks their plan on `/paciente/perfil` (`patients.health_plan_id`), and
a charge **inherits the patient's plan** (`financial_charges.health_plan_id`, set in `CreateChargeAction`).
Teleconsulta is `clinical_sessions.meeting_url` (nullable): psychologist/staff set the link
(`POST /sessoes/{id}/teleconsulta`, authorized by `SessionPolicy::markStatus`, editable from the unit
agenda) and the patient sees "Entrar na teleconsulta" on `/minhas-sessoes` — **no video integration, the
link is entered manually** (same spirit as Fase 5's manual PIX). **E-signature and NFe are contracts only**
(`Modules\MedicalRecords\Contracts\SignatureProviderInterface`,
`Modules\Payments\Contracts\InvoiceIssuerInterface`) — no implementation/binding, exactly like
`PaymentGatewayInterface`: they need a contracted provider (Clicksign/D4Sign; Focus NFe/eNotas). New
cross-module migrations live in the module whose feature owns the column (Settings owns `units`/
`unit_user`/`clinical_sessions.unit_id`; Financial owns `health_plans` + the `patients`/`financial_charges`
FKs; Scheduling owns `meeting_url`) — same precedent as Notifications/Security altering other modules'
tables.

**Public REST API (marco, foundation done):** `laravel/sanctum` (Bearer tokens). The
`personal_access_tokens` migration was published and edited to **`uuidMorphs('tokenable')`** (not
`morphs`) because `User` has a UUID PK — same class of fix as `model_has_roles` (Fase 1) and the
`notifications` table (Fase 7); with `morphs` (bigint `tokenable_id`) a token would never match a user.
A `sanctum` guard was added to `config/auth.php` (`driver: sanctum`). **Tokens are minted by the user
themselves in the authenticated web area (`/api-tokens`, `ApiTokenController`) — there is NO API login
endpoint**, so the API can't bypass the app's mandatory MFA; the plaintext token is shown once (via flash)
then only the hash remains. The API lives in a **central `routes/api.php`** (registered via
`withRouting(api:)` in `bootstrap/app.php`), under `/api/v1` with `auth:sanctum` + `throttle:60,1` +
`resolve.tenant`; controllers in `app/Http/Controllers/Api/V1/`. **Every endpoint reuses the SAME
Actions/queries as the Inertia controllers** — `POST /api/v1/sessions` calls the exact same
`BookSessionAction` (Fase 3 double-booking lock included), proving the Fase-0 Actions/Services↔HTTP
decoupling. **`ResolveTenant` now reads `$request->user()` instead of `Auth::user()`** so it's
guard-agnostic — the tenant resolves from the token owner on API requests and from the session on web
requests (same middleware, both guards). v1 endpoints: `GET /me`, `GET /psychologists`, `GET /sessions`,
`POST /sessions`, `GET /charges`. Testing gotcha: Sanctum's `RequestGuard` caches the resolved user within
one test process, so a "revoked token now fails" test must call `$this->app['auth']->forgetGuards()`
between the two HTTP calls to simulate separate real requests (`ApiV1Test`).

**Payment gateways (marco, foundation done):** a provider-agnostic layer over `PaymentGatewayInterface`
(the Fase-5 stub, **refactored to be charge-centric** — `createCharge(FinancialCharge, method)` +
`verifyWebhook`/`parseWebhook`; the old `charge(Payment)/refund(Payment)` shape was wrong because the
`Payment` only exists AFTER confirmation). The active driver comes from `config('payments.default')`
resolved by `PaymentGatewayManager` (Laravel `Manager`), bound to the interface in
`PaymentsServiceProvider`. **`NullGateway` is the default** (no external call — the charge just awaits
manual `RecordPaymentAction`, i.e. the pre-marco behaviour, and it's the deterministic test driver);
**`AsaasGateway` is the reference adapter** (BR: PIX/boleto/card, Laravel HTTP client, key from
`config/payments.php`). Flow: `RequestGatewayChargeAction` creates the charge at the provider and stores
`gateway`/`gateway_charge_id`/`payment_url`/`pix_payload` on `financial_charges` (new columns); the
customer pays; the provider hits the **public webhook `POST /webhooks/payments/{driver}`** (CSRF-exempt
via `validateCsrfTokens(except: ['webhooks/*'])` in `bootstrap/app.php`, origin checked by the driver's
`verifyWebhook`, throttled); `HandleGatewayWebhookAction` reconciles. **Two things that matter in the
webhook action:** it runs in **guest context** (no session → no `CurrentTenant`), so it finds the charge
with `withoutTenantScope()` and then **`CurrentTenant::set($charge->tenant)`** before calling
`RecordPaymentAction` (whose `lockForUpdate` query is tenant-scoped); and it's **idempotent** — Asaas
sends `PAYMENT_RECEIVED` then `PAYMENT_CONFIRMED`, so it dedups by `Payment.gateway_reference`
(= provider payment id) before recording. Because reconciliation reuses `RecordPaymentAction`, it
dispatches `PaymentWasRecorded` and Notifications/Audit fire for free (Fases 7/9). Adding a provider =
add a `create{X}Driver()` to the manager + a driver class; per-tenant encrypted credentials and gateway
`refund` are documented deferrals. Testing: drive the Null path directly; fake the Asaas HTTP with
`Http::fake` (assert request shape) + `Http::assertSent`; the webhook idempotency test posts the same
payload twice and asserts one `Payment` (`PaymentGatewayTest`).

**Frontend:** Inertia pages live in `resources/js/Pages` (root) or `Modules/{Name}/resources/js/Pages`
(per module). shadcn/ui components are copied into `resources/js/components/ui` (lowercase, per the
shadcn CLI convention — see `components.json`) and customized directly, not installed as a runtime
dependency. Path alias `@/*` → `resources/js/*` (configured in `jsconfig.json` and `vite.config.js`).

## Known deviation from the original spec

The project brief specified **Inertia.js v2**. By the time this project was scaffolded (2026), Inertia
v3 was the current stable release (built-in HTTP client, simplified Vite-driven SSR, `Inertia::optional()`/
`defer()`/`merge()` improvements) and is what Laravel 13 projects install by default
(`inertiajs/inertia-laravel` resolved to `^3.1`, `@inertiajs/react` to `^3.6`). Per the project's own
"resolve ambiguity toward the most robust technical decision" directive, v3 was used instead of pinning
back to v2. Flagged here in case there was a specific reason v2 was required.

## Gotchas hit during Fase 1 (worth knowing before you touch this again)

- **`spatie/laravel-permission` + `WithoutModelEvents` in a seeder = silently broken cache.** The
  package invalidates its 24h permission/role cache via Eloquent `saved`/`deleted` model events. Laravel's
  default `DatabaseSeeder` scaffold uses `use WithoutModelEvents;`, which suppresses ALL model events —
  including that invalidation — so `findOrCreate()` starts returning stale (empty) results and duplicate-key
  errors follow. `DatabaseSeeder` here does **not** use that trait; don't add it back. Also worth knowing:
  `RolesAndPermissionsSeeder` explicitly calls `PermissionRegistrar::forgetCachedPermissions()` at the top,
  which is the package's own documented defensive practice for reseeding.
- **`spatie/laravel-permission`'s default migration assumes integer PKs.** `model_has_roles`/
  `model_has_permissions.model_id` is generated as `unsignedBigInteger`. Since `User` uses a UUID PK, that
  migration (`database/migrations/2026_07_17_185933_create_permission_tables.php`) was edited to use
  `$table->uuid($columnNames['model_morph_key'])` instead. **SQLite (the test DB) didn't catch this** — it
  doesn't enforce column types the way MySQL does — the bug only surfaced against real MySQL. If you add
  another UUID-keyed model that gets roles/permissions, this is already handled; if you add a *new* pivot
  table anywhere referencing a UUID-PK model, remember MySQL will enforce the type and SQLite won't.
- **`Carbon::diffInMinutes()` (and the other `diffInX` methods) return a *signed* value by default in this
  Carbon version**, not absolute. `$future->diffInMinutes($past)` is negative unless you pass
  `absolute: true`. `EnsureSessionIsValid` does this correctly now — copy that pattern, not the naive one.
- **Laravel's `HasUuids` trait already generates UUID v7** (`Str::uuid7()`) in this Laravel version —
  there is no separate `HasVersion7Uuids` trait (that name doesn't exist here). `HasVersion4Uuids` is the
  opt-in for the *old* random-UUID behavior. `Modules\Core\Traits\HasUuidPrimaryKey` just wraps `HasUuids`.
- **`route()` / Ziggy is not installed.** Auth pages use plain path strings (`post('/login')`, etc.), not
  a `route()` JS helper. Don't reach for `route()` in a new page without installing `tightenco/ziggy` first.

## Gotchas hit during Fase 2

- **`app()->runningInConsole()` is `true` during every PHPUnit run** (Laravel's `isRunningUnitTests()`
  feeds into it), which makes `TenantScope`'s "skip the scope in console" branch fire on every test —
  including `$this->get(...)`/`$this->post(...)` feature-test HTTP calls. This means **a route missing
  `resolve.tenant` in its middleware will pass every PHPUnit test and still 500 in the real app** the
  moment it touches a `BelongsToTenant` model, because `CurrentTenant` was never populated. This exact bug
  shipped once (`/paciente/perfil` routes) and only the manual `php artisan serve` smoke test caught it —
  the 36-test suite was green the whole time. **When adding a route that touches `Patient`, `Guardian`,
  `Psychologist`, or any future `BelongsToTenant` model, always add `resolve.tenant` to its middleware AND
  do a manual smoke test — don't trust the test suite alone for this specific class of bug.**
- **A model's `$fillable` array silently drops keys it doesn't list — including ones you pass explicitly
  in `create([...])`.** `tenant_id` was missing from `Patient`/`Guardian`/`Psychologist::$fillable`;
  `Model::create(['tenant_id' => $tenant->id, ...])` silently ignored it, and `BelongsToTenant`'s
  creating-hook fallback (pull from `CurrentTenant`) only saves you if a tenant is already resolved — it
  isn't, during guest routes like patient registration. Fixed by adding `tenant_id` to `$fillable`
  (same precedent as `User`), which is safe here specifically because every Action in this codebase
  builds explicit attribute arrays rather than forwarding `$request->all()`/`->validated()` wholesale into
  `create()`. If that stops being true anywhere, mass-assignable `tenant_id` becomes a real risk again.
- **The base `App\Http\Controllers\Controller` ships empty in this Laravel version** — no
  `AuthorizesRequests`, no `ValidatesRequests`. `$this->authorize(...)` inside a controller throws
  "Call to undefined method" until you add `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;`
  to that base class (already done). Don't re-add it per-controller.

## Gotchas hit during Fase 3

- **The big one: `SubstituteBindings` (implicit route-model-binding) is priority-sorted to run before ANY
  custom middleware, regardless of where you put that middleware in the route's own array.** Laravel
  merges route-group middleware with a fixed internal `$middlewarePriority` list before running it, and
  `SubstituteBindings` sits high in that list; an unlisted middleware like `resolve.tenant` — even written
  as `Route::middleware(['auth','verified','resolve.tenant'])` — still gets sorted to run *after* it. The
  practical effect: any route with an implicitly-bound `BelongsToTenant` parameter (`{psychologist}`,
  `{session}`, `{availability}`) throws `UnresolvedTenantException` for **every** legitimate user, not
  just cross-tenant ones — it's a full outage for that route, not a silent security gap. **PHPUnit cannot
  catch this either way**: `runningInConsole()` is always true in tests, so the scope's console-bypass
  branch fires and the query just quietly succeeds unscoped — tests stay green whether the ordering bug is
  present or not. This one only surfaces by actually hitting the route
  (`php artisan serve` + curl/browser) — it did, on `/psicologos/{psychologist}/disponibilidade`, and 500'd.
  Fixed once, at the root, in `bootstrap/app.php`:
  ```php
  $middleware->prependToPriorityList(
      before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
      prepend: \Modules\Tenant\Http\Middleware\ResolveTenant::class,
  );
  ```
  This didn't need to be repeated per-route — it fixed every existing and future route with an implicit
  tenant-scoped binding in one place. If you ever add another middleware that must run before route model
  binding resolves, it needs the same treatment.
- **Relatedly:** don't rely on the global scope alone to protect a route that receives a tenant-scoped
  model via implicit binding — add `CurrentTenant::ownsOrFail($model)` explicitly in the controller (see
  `AgendaController`, `WaitingListController`). It's cheap, it's the only form of this check PHPUnit can
  actually exercise, and it doesn't depend on getting the middleware-priority ordering right forever.
- **Table name collisions with Laravel's own tables are a real risk, not just a naming-clash annoyance.**
  `sessions` (clinical) vs `sessions` (Laravel's HTTP session table, `SESSION_DRIVER=database`) would have
  been a hard collision — caught before running the migration, not after. When naming a new table, grep
  `database/migrations/` for the name first if there's any chance a Laravel subsystem might already use it
  (`sessions`, `cache`, `jobs`, `failed_jobs`, `notifications`, `migrations` are the obvious ones already
  in this app).

## Gotchas hit during Fase 4

- **Laravel's default `throttle:X,Y` middleware keys by `domain + IP` only, not by route.** Every route
  decorated with `throttle:10,1` (`/register`, `/login`, `/mfa/challenge`, `/reset-password` —
  `Modules\Authentication\routes\web.php`) shares **one** bucket for a given client IP, not one bucket
  per route. This is correct, working rate-limiting — it isn't a Fase 4 bug — but it means any
  multi-account manual/smoke-test script (register admin, log in twice, create two psychologists, reset
  two passwords, log in as patient, log in again as psychologist...) burns through that shared budget
  fast and gets a real `429` that looks exactly like a broken auth flow (redirect target and status code
  don't obviously distinguish "rate limited" from "invalid credentials"). If a manual verification script
  starts seeing unauthenticated-looking `302`s to `/login` or a `429`, check the `cache` table
  (`CACHE_STORE=database`) before assuming the application regressed — `TRUNCATE TABLE cache;` between
  login cycles in test scripts sidesteps it without touching the real limiter config.
- **A throwaway test script scraping links out of `storage/logs/laravel.log` must track a byte offset,
  not just re-read the whole file.** The log is never truncated between runs, so "read the whole file and
  take the last regex match" silently returns a **stale** link/token/OTP code from a previous run once
  the log has accumulated enough history — indistinguishable from a real bug because both a stale token
  and a genuine auth failure produce ordinary-looking redirects. Capture `filesize($logFile)` immediately
  before triggering the mail-sending action, then only search bytes appended after that offset.

## Gotchas hit during Fase 5

- **`decimal` Eloquent casts return strings, not floats or ints.** `FinancialCharge::$amount` etc. are
  cast `decimal:2`, so `$charge->amount` is the string `"100.00"`, not a float — arithmetic on it without
  an explicit `(float)` cast either throws (`TypeError` on strict math funcs) or silently does the wrong
  thing. Every place that computes with these fields casts explicitly first (see
  `FinancialCharge::totalDue()`/`totalPaid()`, `ChargeStatusCalculator`, `ApplyLateChargeFees`) — copy
  that, don't read `$charge->amount` straight into arithmetic.
- **Splitting a monetary total across N installments in floating point loses or invents cents.**
  `100 / 3` isn't representable exactly in binary floating point, and naively rounding each share to 2
  decimals can make the parts not sum back to the original total. `CreateChargeAction::split()` converts
  to integer cents first (`(int) round($total * 100)`), divides with `intdiv`, and adds the remainder
  entirely to the last installment — exact by construction, no float rounding drift regardless of how
  many parts.
- **A module's `configureSchedules()` hook (from `Nwidart\Modules\Support\ModuleServiceProvider`) is real
  and auto-invoked** — it's not template boilerplate left commented out for decoration. `FinancialServiceProvider`
  uncomments it to schedule `financial:apply-late-fees` daily; the base class calls it via `registerCommands()`
  as long as the method exists (checked with `method_exists`), so overriding it is enough — no extra
  registration anywhere else needed. Confirmed by reading `vendor/nwidart/laravel-modules/src/Support/
  ModuleServiceProvider.php` before relying on it, since every other module still has it commented out.
- **The Fase 4 log-offset and throttle-clearing smoke-test helpers generalize cleanly.** Reused verbatim
  (`logOffset`/`logSince`/`clearThrottle`/`loginAndMfa`) for the Fase 5 manual smoke test with zero
  changes needed — confirms those weren't one-off fixes but the right general pattern for any future
  phase's curl-based manual verification script.

## Gotchas hit during Fase 6

- **The full test suite crashes the PHP process on this WAMP/Windows (ZTS) build once it's long enough —
  at `Reports\ExportsSmokeTest` (the Excel/`maatwebsite-excel`+`zipstream` writer), `Fatal error:
  Premature end of PHP process` at a 16MB `fread` on the zip stream.** It is **not** OOM (memory sits ~112MB,
  and `-d memory_limit=1024M` doesn't help) and **not** a code regression — the export test passes in
  isolation (`php artisan test tests/Feature/Reports` → green). It's a hard crash of the ZTS Windows PHP
  worker when the Excel writer runs late in a long sequential run; once the suite grew past ~220 tests the
  export test lands past that threshold and the process dies, aborting everything after it. **Workaround
  for a clean green: run the suite in two batches** so no single process lives long enough —
  e.g. everything except Reports in one `php artisan test <dirs…>`, and `tests/Unit/Reports
  tests/Feature/Reports` in another. Both pass; the crash is purely single-process longevity, same family
  as the `pail`/`composer dev` Windows issue below. (CI on Linux/NTS would not hit this.)
- **`php artisan pail` doesn't work on this WAMP/Windows setup — it needs the `pcntl` extension, which
  doesn't exist on Windows PHP builds.** Running `composer dev` (which wraps `server`/`queue`/`logs`/`vite`
  in one `concurrently --kill-others` call) starts all four, `pail` immediately throws `RuntimeException:
  The [pcntl] extension is required to run Pail.`, and `--kill-others` tears down the other three
  processes too — so the whole dev stack dies within a couple seconds, not just log tailing. For manual
  verification on this machine, start `php artisan serve` and `npm run dev` directly in the background
  instead of going through `composer dev`; skip `php artisan pail` entirely (or run it separately and
  ignore its failure) rather than debugging why the server/queue/vite processes keep vanishing.
- **First phase where the manual `php artisan serve` smoke test didn't turn up a new bug.** Worth noting
  precisely because every phase through Fase 5 did — Fase 6 built entirely on already-battle-tested
  primitives (`resolve.tenant`, `CurrentTenant::ownsOrFail`, `Gate::define` policies, the log-offset/
  throttle-clearing smoke-test helpers) rather than introducing new cross-cutting mechanisms, which is
  probably why. Still do the manual pass every phase regardless — it's cheap insurance, and the absence of
  a finding this time doesn't mean the next new mechanism won't need it.

## Gotchas hit during Fase 7

- **An Inertia shared prop and a page-controller prop with the same top-level key silently collide —
  the page's own prop wins, the shared one just disappears on that page, no error anywhere.**
  `HandleInertiaRequests::share()` originally added `'notifications' => ['unreadCount' => ...]`; the new
  `Notifications/Index` page renders `Inertia::render('Notifications/Index', ['notifications' =>
  $paginatedList])` — same key. Inertia merges shared + page props with page props taking precedence, so
  on that one page `props.notifications` became the paginated list and `unreadCount` vanished with no
  warning; every other page was fine. PHPUnit didn't catch it either (no test asserted the shared prop's
  shape). Only caught by actually reading the real JSON payload from a live HTTP response during the
  manual smoke test. Fixed by renaming the shared prop to a flat, unrelated key
  (`unreadNotificationsCount`) instead of nesting it under `notifications` — the real lesson: **don't
  give a globally-shared Inertia prop the same top-level key as any page-specific prop, current or
  future; a flat, specifically-named key is collision-proof by construction, a nested namespace isn't.**
- **`AvailabilityCalculator` slots are a fixed grid anchored to each rule's `start_time`, not "anything
  inside the window."** `sliceIntoSlots()` walks from `start_time` in fixed `duration + buffer` steps;
  a requested `scheduled_at` is only bookable if it lands exactly on one of those computed slot starts.
  A rule `09:00–12:00` with 50-minute sessions and no buffer only ever produces slots at 9:00, 9:50, and
  10:40 — booking at 10:00 throws `SlotNoLongerAvailableException` even though 10:00 is well inside the
  window, because it was simply never a slot in the first place. This isn't new behavior (Fase 3), but
  it bit the Fase 7 smoke-test script directly (picked an arbitrary round time instead of aligning it to
  the rule's `start_time`) — when writing ad-hoc booking data for a script/seed, always set the
  availability rule's `start_time` to exactly the time you intend to book, exactly like the existing
  PHPUnit tests already do (see `CancelRescheduleSessionTest`), rather than assuming any time within the
  window works.
- **`QUEUE_CONNECTION=database` in the real app (vs. `sync` in `phpunit.xml`) means `->notify()` only
  queues a job — it does not send anything until a worker drains it.** Every `TenantNotification` is
  `ShouldQueue`, so a manual/tinker-driven smoke test that calls an Action and immediately checks the
  `notifications` table or the mail log will find nothing, not because the code is broken but because
  the job is still sitting in the `jobs` table. Run `php artisan queue:work --stop-when-empty` (or
  `--once` per job) between triggering the action and checking results. PHPUnit never surfaces this
  because `phpunit.xml` forces `QUEUE_CONNECTION=sync`, executing queued jobs inline — another case
  (like `runningInConsole()` masking tenant-scope bugs) where the test environment's convenience setting
  hides real-environment behavior; the manual MySQL pass is what actually exercises the real queue path.
- **`Illuminate\Notifications\Notifiable`'s `notifications()`/`unreadNotifications()` work out of the
  box once the `notifications` table exists with the right key type — no custom wiring needed** — but
  the migration must use `uuidMorphs`, not `morphs` (see architecture note above); getting that wrong
  wouldn't fail loudly, it would just never match any row (`notifiable_id` type mismatch against a UUID
  string).
- **Windows PowerShell 5.1's `Invoke-WebRequest -Method PATCH` is unreliable for this project's manual
  HTTP smoke tests — it can silently succeed server-side while the client hangs to a 30s timeout, and a
  literal retry can then report a false `405 Method Not Allowed`** (almost certainly `Invoke-WebRequest`
  re-issuing the PATCH against whatever `back()` redirected to, rather than the intended URL — a known
  rough edge in the old .NET Framework HTTP client PowerShell 5.1 runs on, not a bug in the route). Real
  `curl.exe` (present on this machine at `/mingw64/bin/curl.exe`, i.e. callable from the Bash tool) issued
  the exact same `PATCH /notificacoes/{id}/lida` request cleanly and got the expected `302`. **For any
  future manual smoke test that needs a real `PATCH`/`PUT`/`DELETE` against a running `php artisan
  serve`, reach for `curl.exe` via Bash first, not `Invoke-WebRequest`** — cookie-jar (`-c`/-b`) plus
  reading the `XSRF-TOKEN` cookie for an `X-XSRF-TOKEN` header reproduces the same session/CSRF handling
  `Invoke-WebRequest -SessionVariable` gives you, without the verb quirk.
- **`User::$fillable` not listing `email_verified_at` means a script that does
  `User::create(['email_verified_at' => now(), ...])` silently drops it, leaving the user unverified** —
  the exact same class of bug as the Fase 2 `tenant_id`-missing-from-`$fillable` gotcha, rediscovered
  while hand-writing a smoke-test fixture that (unlike the app's own registration Actions) tried to
  mass-assign a field the Model doesn't allow. Not a bug in the app itself (no real flow does this), just
  a reminder that `$fillable` gaps are invisible until something outside the normal Action-built
  attribute-array path tries to use `create()` directly.

## Gotchas hit during Fase 8

- **A stale `php artisan serve` from a previous session, still holding a port, silently serves OLD code
  and makes every new route 404 — indistinguishable from a real routing bug.** The Fase 8 manual smoke
  test 404'd on `/c/{tenant:slug}` AND on the Fase 2 patient-registration route that had worked for
  phases, yet `app('router')->getRoutes()->match($request)` in a fresh `tinker` matched both correctly.
  The tell: routing works in a fresh CLI bootstrap but 404s over HTTP → the HTTP server is running
  different (older) code than your files. Cause here was a leftover `serve` process bound to the port
  from an earlier session; a new `serve` on the same port either loses the bind or you're hitting the old
  one. **Before trusting a `serve`-based smoke test, confirm the server has your new routes** (hit a
  route that only exists on this branch and expect its real status, e.g. `/cms/paginas` → `302`, not
  `404`), or just start on a fresh port and kill stray `php.exe` first. This is the same class as the
  Fase 6 `composer dev`/`pail` note — the WAMP/Windows dev-server story is where these bite.
- **A skeleton module route (`Route::resource(...)`/`apiResource(...)` pointing at a controller that was
  later deleted) breaks `php artisan route:list` globally, not just its own row.** `route:list`
  eagerly reflects every route's controller class; one missing class throws `ReflectionException` and
  aborts the whole listing. Several untouched module skeletons still have these (`Modules\Audit`'s
  `AuditController` is the one that surfaced). It does **not** affect the running app (string controllers
  are resolved lazily, only on hit), so it's latent tech debt, not a live bug — but it means `route:list`
  is unusable until those are cleaned. To inspect routes meanwhile, iterate `app('router')->getRoutes()`
  in `tinker` and read `->uri()`/`->getName()` (doesn't reflect controllers). CMS's own skeleton
  (`CMSController` + its `cms` resource/apiResource routes) was removed as part of this phase.
- **GrapesJS lands in the main JS bundle because `app.jsx` globs pages with `{ eager: true }`.** Every
  page under `resources/js/Pages` is imported eagerly into one bundle, so importing `grapesjs` +
  `grapesjs-preset-webpage` in `CMS/Editor.jsx` pushed the shared `app-*.js` from a few hundred KB to
  ~1.5MB (421KB gzip) — it now loads on every page, not just the editor. Not fixed this phase (making the
  glob lazy is an app-wide change touching every page's load behavior); flagged as a deferral. If bundle
  size becomes a real problem, the targeted fix is a dynamic `import()` for the editor, not flipping the
  whole glob to lazy.
- **`DOMDocument` needs an explicit UTF-8 hint or it mangles accented content**, which matters because
  the UI is Portuguese. `HtmlSanitizer::sanitizeHtml` prepends `<?xml encoding="UTF-8">` and loads with
  `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD` (so it doesn't inject `<html><body>` wrappers) inside a
  known `<div id="__cms_root__">` sentinel it then unwraps — without the encoding hint, `loadHTML`
  assumes Latin-1 and "Início"/"Sessão" come out corrupted. Confirmed intact by the real-MySQL smoke
  test (the rendered page kept "Bem-vindo" and accented text).

## Gotchas hit during Fase 9

- **Key rotation re-encryption cannot go through `save()` on an append-only model.** The
  `RotateEncryptionKeyJob` re-encrypts by round-tripping an attribute (`$m->x = $m->x`, get decrypts with
  the old DEK version, set re-encrypts with the active one) and calling `saveQuietly()`. But
  `MedicalRecordEntry::update()` is overridden to throw (append-only, Fase 4), so any save on an existing
  row throws — the round-trip approach is structurally incompatible with it. That's why
  `medical_record_content` is deliberately absent from `config('security.encryption_contexts')`: the key
  still rotates (old data stays readable on the retired DEK), only the automatic bulk migration is
  skipped. Same reasoning for the on-disk attachment blobs (encrypted by `AttachmentStorage` directly,
  not via a cast, so there's no attribute to round-trip). When a future phase needs those actually
  migrated, it needs a bespoke path (raw builder write for the append-only row, storage reprocess for
  the file), not this Job.
- **Auditing (and anything reading request context) must NOT be `ShouldQueue`.** `AuditLogger` pulls the
  actor from `auth()->user()` and IP/User-Agent from `Request` — all request-scoped. The Notifications
  listeners on these exact same events ARE queued (they only need the model), but the audit listener
  (`RecordDomainAuditEvents`) is intentionally synchronous; queueing it would record null actor/IP. Two
  listeners on one event with opposite queue semantics is fine and deliberate — don't "consolidate" them.
- **Re-encrypting one context must not disturb the model's other encrypted columns.** A model like
  `Patient` has five independent contexts. The Job filters `getCasts()` to attributes ending in exactly
  `:{context}` and only round-trips those, so rotating `patient_phones` bumps `phones_encrypted` to the
  new version while `address_encrypted` etc. stay on their own (unchanged) active version — asserted by
  `RotateEncryptionKeyJobTest::test_job_only_touches_attributes_of_the_rotated_context`. If you widen the
  attribute match, you'd re-encrypt (and dirty) unrelated columns on every rotation.
- **A `config/*.php` that imports classes with `use` is fine — Pint will even add the imports for you.**
  `config/security.php` references model FQCNs in the rotation registry; Pint's `fully_qualified_strict_types`
  fixer rewrote the inline `\Modules\...::class` into `use` imports at the top of the config file. That's
  valid (config files are plain PHP) and doesn't interfere with `config:cache`. Don't "fix" it back to
  inline FQCNs — Pint will just redo it.

## Gotchas hit during Fase 10

- **A model whose class name doesn't match its table needs `$table` — and the failure only shows at
  runtime, never in a green unit test.** `Consent` maps to `consents` by convention, but the table is
  `lgpd_consents` (namespaced with the rest of the LGPD schema). Missing `protected $table` surfaced as
  `SQLSTATE… no such table: consents` — but only through the HTTP path, because the global
  `EnsureLgpdConsent` middleware is the first thing that queries `Consent` on a real request. The model
  itself instantiates fine; nothing complains until a query runs. Set `$table` whenever the table name
  isn't the plural of the class.
- **A global consent-gating middleware makes the admin who publishes a legal document its own first
  gated user.** After `PublishLegalDocumentAction` creates the first `is_current` document, that admin's
  next request has an unaccepted required document, so `EnsureLgpdConsent` redirects them to the consent
  screen. This is correct, but it means an end-to-end "publish v1 then publish v2 over HTTP" flow can't
  reach the second publish — the second request 302s to `/lgpd/consentimento` instead. Tests that need to
  exercise multi-version behaviour drive `PublishLegalDocumentAction` directly rather than through two
  HTTP posts (see `LegalDocumentPublishingTest::test_publishing_again_supersedes_the_current_version`).
- **Group middleware can run before the route is resolved, so gate on `$request->is(path)`, not
  `routeIs()`.** `EnsureLgpdConsent` is appended to the `web` group and must exempt the consent screen,
  logout, and login to avoid a redirect loop. `$request->routeIs('lgpd.consent.*')` is unreliable there
  (the route binding may not be attached yet at that point in the pipeline); path matching
  (`$request->is('lgpd/consentimento')`) is always available and is what the middleware uses.
- **The consent gate is deliberately opt-in per tenant, which is also why it didn't break the other 168
  tests.** `ConsentChecker::pendingFor` only considers documents that actually exist and are
  `is_current`; a tenant with no published documents yields an empty pending list, so the middleware is a
  no-op. That's both the intended product behaviour (a clinic that hasn't written a privacy policy isn't
  forced to) and the reason adding a global `web` middleware didn't cascade failures across the suite —
  no existing test seeds a `legal_documents` row. If you ever make consent mandatory-by-default, expect
  to touch a lot of tests.

## Gotchas hit during Fase 11

- **Dot-notation validation rules expect NESTED input, not a literal dotted field name.** The settings
  form validates `'scheduling.booking_horizon_days' => [...]`, which Laravel reads as
  `scheduling[booking_horizon_days]` (nested). So the Inertia form must send nested data
  (`{ scheduling: { booking_horizon_days } }`), and `$request->validate` returns it nested too. But
  `TenantSettings`'s registry is keyed by dot-strings, so the controller flattens the validated array with
  `Arr::dot()` before `set()`. Sending flat literal-dotted field names instead would silently fail
  validation (Laravel looks for a nested `scheduling` key that isn't there). Keep the two representations
  straight: nested over the wire + for validation, dot-flat for the settings store.
- **`TenantSettings` deliberately does not persist a value that equals the config default — it stores only
  what the tenant actually changed, and everything else falls through to `config/*`.** `get()` returns the
  stored value if present else the config fallback; `set()` writes the given keys into the JSON. This
  keeps the `tenants.settings` blob small and means changing a global default in `config/scheduling.php`
  still moves every tenant that never overrode it. Don't "hydrate" all defaults into the JSON on tenant
  creation — that would freeze each tenant on the creation-time defaults and defeat the fallback.
- **Refactoring `RegisterClinicAdminAction` to depend on `ProvisionTenantAction` crosses a module
  boundary (Authentication → Settings) — that's the intended direction.** Settings owns "how a tenant is
  born" now, and both the self-signup (Authentication) and the Super-Admin console (Settings) call the
  same Action, so they can't drift. If you add another tenant-creation entry point, route it through
  `ProvisionTenantAction` too rather than re-inlining `Tenant::create`.
- **Plan-limit enforcement lives in the Action, not the FormRequest.** `RegisterPsychologistAction` throws
  `PlanLimitReachedException` (a domain exception, before any row is written) and the controller catches
  it into a `ValidationException` on the `plan` key. A FormRequest rule couldn't express "count existing
  psychologists vs the tenant's plan limit" cleanly, and putting it in the Action keeps the rule reusable
  from a future API/CLI path. The count uses `withoutTenantScope()` + explicit `where('tenant_id')`
  because the Action may run with or without a resolved `CurrentTenant`.

## What exists vs. what doesn't yet

**Done (Fase 0 through Fase 11):** Laravel + Inertia + React + Tailwind + shadcn/ui wiring; the 18 module
skeletons; `Tenant` model/scope/middleware; full registration → email verification → login → MFA
(email OTP + TOTP) → session-timeout-guarded dashboard flow (`Modules\Authentication`); envelope
encryption primitives (`EnvelopeEncrypted`, `EncryptedJson`, `searchHash`, all in `Modules\Security`);
RBAC seeded (`Modules\Authorization`); immutable audit log wired to Laravel's native auth events
(`Modules\Audit`); tenant-scoped patient self-registration + optional-profile-with-guardian-rule
(`Modules\Patients`, `Modules\Guardians`); admin-created psychologist accounts (`Modules\Psychologists`);
psychologist availability + on-the-fly slot calculation + transactional booking + cancel/reschedule with
minimum notice + waiting list (`Modules\Scheduling`); append-only versioned clinical record with encrypted
content and encrypted file attachments, access derived from treatment history
(`Modules\MedicalRecords`); charge/payment modeling with installments, discounts, late fees, reversal, and
a recomputed-not-stored status machine (`Modules\Financial`, `Modules\Payments`); three psychologist
reports with PDF/Excel export, patient self-service access to sessions/financial situation/receipts, and
role-aware dashboards (`Modules\Reports`); domain Events on Scheduling/Financial/Payments consumed by a
pluggable-channel Notifications module (mail + database today, 8 Notification classes, 2 polling reminder
commands, `/notificacoes` + unread-count bell) (`Modules\Notifications`); GrapesJS-edited, per-tenant
public pages with server-side-sanitized HTML/CSS, `manage-cms`-gated admin CRUD, and guest Blade
rendering at `/c/{tenant:slug}` (`Modules\CMS`); security-headers middleware, envelope-encryption key
rotation (`security:rotate-key` + `RotateEncryptionKeyJob`), domain-event audit coverage, and
export/receipt rate limiting (`Modules\Security`/`Modules\Audit`, Fase 9); versioned per-tenant legal
documents, append-only consent with global re-consent gating, Art. 18 self-service data
access/portability, and irreversible patient anonymization (`Modules\Security` `Lgpd` namespace, Fase 10);
config-defined plans with limit enforcement, tenant provisioning with trial, per-tenant settings over a
config-fallback layer, Super-Admin cross-tenant management, and per-tenant branding (`Modules\Settings`,
Fase 11); the full AdminPSC visual identity (brand tokens, Manrope, dark mode, per-tenant primary colour,
authenticated app shell with role-aware sidebar + theme toggle across all pages); and two roadmap
"marcos" — multi-unit + scoped secretaries, and convênios + teleconsulta. 220 PHPUnit tests, plus manual
end-to-end passes against real MySQL (per phase and per marco — most caught a real bug or a real gotcha
PHPUnit missed, see the gotchas sections above — keep doing the manual pass regardless of whether a given
increment turns up nothing new).

**All eleven numbered phases (Fase 0–11) of `docs/06-Roadmap.md` are complete, plus the first two
"Marcos futuros".** What remains is the rest of the roadmap's "Marcos futuros" (unnumbered vision: real
payment gateways/PIX, e-signature + NFe — contracts exist, no provider —, mobile app, public REST API,
SMS/WhatsApp channels) plus the per-phase/per-marco deferrals below. Not built:
admin-facing patient
list/management UI, psychologist profile editing, Secretária/Financeiro staff invites, guardian portal
access (Fase 2 deferrals); automatic waiting-list notification when a slot opens (the module exists now,
but nothing wires `waiting_list_entries` to it yet), editing/removing an existing availability rule beyond
delete, a visual calendar UI for booking (Fase 3 deferrals); patient self-service access to their own
medical record (Fase 10/LGPD), editing/removing a past medical-record version, multiple attachments per
entry, automatic `session_id` population when a session is marked completed (Fase 4 deferrals); real
gateway/PIX integration, "abatimento" as a concept distinct from discount (Fase 5 deferrals);
asynchronous PDF/Excel export with a ready notification (Notifications now exists, but Reports' exports
still run synchronously — nobody's revisited that decision yet), a dashboard for
admin_clinica/financeiro/secretaria, a psychologist picker in the report filter UI (Fase 6 deferrals — see
its roadmap entry); SMS/WhatsApp notification channels (architecture is ready, no gateway contracted),
per-user/tenant notification preferences (needs Settings), a notification bell on pages other than
Dashboard (no shared authenticated layout exists yet to hang it on), a session reminder for the
psychologist (only the patient gets `SessionReminderNotification` today) (Fase 7 deferrals); live
submission of the Formulário/Contato CMS blocks (persistent lead capture needs LGPD consent — deferred to
Fase 10; today Formulário is design-only and Contato uses `mailto:`), code-splitting the GrapesJS editor
out of the main bundle, an in-admin site preview, CMS page versioning, an auto-generated public nav menu,
and a dedicated media upload (images currently embed as data-URIs via GrapesJS's Asset Manager) (Fase 8
deferrals); DB-level audit hardening (`GRANT INSERT,SELECT` without `UPDATE`/`DELETE` on `audit_logs` —
Plesk-env dependent), bulk re-encryption of `medical_record_content` (append-only) and on-disk attachment
blobs on rotation, auth/queue metrics (needs a metrics backend like Pulse), per-tenant DEK rotation
(contexts use a global `tenant_id`-null DEK today), audit coverage for obligatory actions that still lack
a domain Event (medical-record creation, the export/download action itself, CMS publish/delete, logical
deletion — infra ready, just needs the event dispatched/listened), security headers on thrown-exception
responses (an "append" middleware only decorates the normal return path; 404/403/500 rendered by the
exception handler skip it — cover those at the web-server layer or with a terminating handler), and a
backup/restore runbook (Fase 9 deferrals); physical post-retention deletion (only soft-delete +
anonymization exist; definitive physical erasure per CFP retention windows stays a documented manual
process), wiring LGPD consent into the CMS Formulário/Contato blocks for lawful lead capture, batch/
retention-driven anonymization (one patient at a time today), a public-facing view of the current legal
documents on the CMS site, PDF (not just JSON) "my data" export, and anonymization/erasure of
`medical_record_content` (append-only + encrypted — needs a dedicated process, same class as the Fase 9
rotation deferral) (Fase 10 deferrals); real payment gateway/PIX + recurring billing (future milestone),
`max_patients` enforcement on patient self-signup (the limit is defined and displayed but only
psychologist creation is actually blocked), blocking access when a trial expires or a tenant is
`suspended` (status is stored/shown but no middleware bars login/use yet — a product decision for when
real billing exists), applying the tenant's branding colour to the visual theme (the `branding` prop is
exposed but pages don't consume it in a layout yet), per-tenant **session-timeout** config
(`EnsureSessionIsValid` runs in the `web` group before `resolve.tenant`, so a per-tenant timeout override
needs the tenant resolved earlier — the scheduling knobs are already per-tenant), physical data isolation
(ADR-007, documented not implemented), and billing/invoice screens + plan-change history (Fase 11
deferrals).

Also not yet in place: `lang/` translation files and the React `t()`/`useTranslation` hook described in
`docs/05-UIUX-Design-System.md` (pages currently hardcode Portuguese text as a placeholder — don't copy
that pattern once i18n wiring exists). QR-code image rendering for TOTP setup is also deferred
(`EnableTotp.jsx` shows the secret/URI as text today).
