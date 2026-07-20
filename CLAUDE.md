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
resolved; see the note in `docs/01-Arquitetura.md`. No business Model uses the strict scope yet (nothing
exists to scope until Fase 2's `Patient` etc.) — the trait/scope/middleware are built and ready.

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
per-context DEK stored in `encryption_keys`). Reusable Eloquent cast: `Modules\Security\Casts\
EnvelopeEncrypted::class.':context-name'` — currently used for `User::mfa_totp_secret`, meant to be
reused for PII fields in Fase 2. Full key rotation/versioning is still Fase 9 — this phase only has a
single active DEK per context.

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

## What exists vs. what doesn't yet

**Done (Fase 0 + Fase 1):** Laravel + Inertia + React + Tailwind + shadcn/ui wiring; the 18 module
skeletons; `Tenant` model/scope/middleware; full registration → email verification → login → MFA
(email OTP + TOTP) → session-timeout-guarded dashboard flow (`Modules\Authentication`); envelope
encryption primitive (`Modules\Security`); RBAC seeded (`Modules\Authorization`); immutable audit log
wired to Laravel's native auth events (`Modules\Audit`). 26 PHPUnit tests, plus a manual end-to-end pass
against real MySQL.

**Not built yet:** everything business-domain (Psychologists, Patients, Guardians, Scheduling,
MedicalRecords, Financial, Payments, Reports, Notifications, CMS, Settings) — those are Fase 2 onward in
`docs/06-Roadmap.md`, re-evaluate architectural/security/LGPD impact before starting each one.

Also not yet in place: `lang/` translation files and the React `t()`/`useTranslation` hook described in
`docs/05-UIUX-Design-System.md` (pages currently hardcode Portuguese text as a placeholder — don't copy
that pattern once i18n wiring exists). Excel/PDF export packages (`maatwebsite/excel`,
`barryvdh/laravel-dompdf`) are intentionally **not installed yet** — Fase 6 (Reports). QR-code image
rendering for TOTP setup is also deferred (`EnableTotp.jsx` shows the secret/URI as text today).
