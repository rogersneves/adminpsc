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
`psicologo`, `secretaria`, `financeiro`, `paciente`, `responsavel_legal`. Only `super_admin` and
`admin_clinica` have permissions assigned so far (`manage-users`, `manage-clinic-settings`,
`view-audit-log`, `platform.manage-tenants`) — the rest earn permissions as their modules ship.

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

## What exists vs. what doesn't yet

**Done (Fase 0 through Fase 6):** Laravel + Inertia + React + Tailwind + shadcn/ui wiring; the 18 module
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
role-aware dashboards (`Modules\Reports`). 114 PHPUnit tests, plus six manual end-to-end passes against
real MySQL (one per phase — the first five caught real bugs or real gotchas PHPUnit missed, see the
gotchas sections above — keep doing the manual pass every phase regardless of whether Fase 6 found
nothing new).

**Not built yet:** Notifications, CMS, Settings — those are Fase 7 onward in `docs/06-Roadmap.md`,
re-evaluate architectural/security/LGPD impact before starting each one. Also not built: admin-facing
patient list/management UI, psychologist profile editing, Secretária/Financeiro staff invites, guardian
portal access (Fase 2 deferrals); automatic waiting-list notification when a slot opens (needs Fase 7/
Notifications), editing/removing an existing availability rule beyond delete, a visual calendar UI for
booking (Fase 3 deferrals); patient self-service access to their own medical record (Fase 10/LGPD),
editing/removing a past medical-record version, multiple attachments per entry, automatic `session_id`
population when a session is marked completed (Fase 4 deferrals); real gateway/PIX integration,
"abatimento" as a concept distinct from discount (Fase 5 deferrals); asynchronous PDF/Excel export with a
ready notification (needs Fase 7/Notifications), a dashboard for admin_clinica/financeiro/secretaria, a
psychologist picker in the report filter UI (the backend already accepts `psychologist_id` via query
string — there's just no `<select>` yet, since there's no "list psychologists" endpoint to feed it), and
chart/graph visualizations (Fase 6 deferrals — see its roadmap entry).

Also not yet in place: `lang/` translation files and the React `t()`/`useTranslation` hook described in
`docs/05-UIUX-Design-System.md` (pages currently hardcode Portuguese text as a placeholder — don't copy
that pattern once i18n wiring exists). QR-code image rendering for TOTP setup is also deferred
(`EnableTotp.jsx` shows the secret/URI as text today).
