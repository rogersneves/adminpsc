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
resolved; see the note in `docs/01-Arquitetura.md`. `Patient`, `Guardian`, and `Psychologist` (Fase 2) are
the first real consumers of the strict scope — **every route that touches one of these models MUST
include `resolve.tenant` in its middleware, or it 500s in real usage** (see the gotcha below — PHPUnit
will not catch a missing `resolve.tenant`, only manual/browser testing will).
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

## What exists vs. what doesn't yet

**Done (Fase 0 + Fase 1 + Fase 2):** Laravel + Inertia + React + Tailwind + shadcn/ui wiring; the 18
module skeletons; `Tenant` model/scope/middleware; full registration → email verification → login → MFA
(email OTP + TOTP) → session-timeout-guarded dashboard flow (`Modules\Authentication`); envelope
encryption primitives (`EnvelopeEncrypted`, `EncryptedJson`, `searchHash`, all in `Modules\Security`);
RBAC seeded (`Modules\Authorization`); immutable audit log wired to Laravel's native auth events
(`Modules\Audit`); tenant-scoped patient self-registration + optional-profile-with-guardian-rule
(`Modules\Patients`, `Modules\Guardians`); admin-created psychologist accounts (`Modules\Psychologists`).
36 PHPUnit tests, plus two manual end-to-end passes against real MySQL (one per phase — both caught real
bugs PHPUnit missed, see the gotchas sections above).

**Not built yet:** Scheduling, MedicalRecords, Financial, Payments, Reports, Notifications, CMS,
Settings — those are Fase 3 onward in `docs/06-Roadmap.md`, re-evaluate architectural/security/LGPD
impact before starting each one. Also not built: admin-facing patient list/management UI, psychologist
profile editing, Secretária/Financeiro staff invites, guardian portal access — all explicitly deferred,
see the Fase 2 entry in `docs/06-Roadmap.md` for the reasoning.

Also not yet in place: `lang/` translation files and the React `t()`/`useTranslation` hook described in
`docs/05-UIUX-Design-System.md` (pages currently hardcode Portuguese text as a placeholder — don't copy
that pattern once i18n wiring exists). Excel/PDF export packages (`maatwebsite/excel`,
`barryvdh/laravel-dompdf`) are intentionally **not installed yet** — Fase 6 (Reports). QR-code image
rendering for TOTP setup is also deferred (`EnableTotp.jsx` shows the secret/URI as text today).
