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
# After creating/renaming a module, refresh autoload (composer-merge-plugin merges Modules/*/composer.json):
composer dump-autoload
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

**Multi-tenant:** single database, `tenant_id` column + Eloquent Global Scope on every tenant-owned Model
(ADR-003). Not yet implemented — this is Fase 1 of the roadmap.

**RBAC:** `spatie/laravel-permission` (installed, not yet seeded). Roles and permissions are independent
of each other. 7 initial roles: Super Admin, Admin da Clínica, Psicólogo, Secretária, Financeiro,
Paciente, Responsável Legal.

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

## What exists vs. what doesn't yet

Only the foundation has been built: Laravel + Inertia + React + Tailwind + shadcn/ui wiring, the 18
module skeletons (empty — no Models/Migrations/Controllers of business logic yet), and
`spatie/laravel-permission` installed but not seeded. No encryption, MFA, scheduling, financial, medical
record, CMS, or reporting logic exists yet — follow `docs/06-Roadmap.md` phase by phase, and re-evaluate
architectural/security/LGPD impact before starting each one (see that document's opening note).
