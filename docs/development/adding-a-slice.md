---
title: Adding a Slice
purpose: Step-by-step checklist for creating or extending a vertical slice under app/Features without breaking the architecture rules.
audience: [developers, ai-agents]
status: mixed
related:
  - ../architecture/vertical-slice-architecture.md
  - ../architecture/slice-catalog.md
  - adding-a-filament-resource.md
  - testing-strategy.md
  - coding-conventions.md
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
source_of_truth: docs/architecture/vertical-slice-architecture.md, feature prompt section 4.1, phpunit.xml, tests/Pest.php, tests/Architecture/SliceMap.php
last_verified: 2026-10-30
---

# Adding a Slice

> **TL;DR**
> - Only the 13 planned slices may exist: Shared, Vendor, Invoice, Extraction, Guard, Approval, Payment, Webhook, Telegram, AgentGateway, Audit, Policy, Baseline. A new slice needs owner approval.
> - Create only the sub-folders the slice needs. Use `php artisan make:* --no-interaction`, then move the class into the slice and fix its namespace.
> - The first slice wires provider auto-discovery (`AppServiceProvider`), route auto-discovery (`bootstrap/app.php` `then:`), and the factory resolver. Test wiring is already in place (section 5): each slice adds one testsuite entry to `phpunit.xml`.
> - Status: mixed. Only `app/Features/Shared/` exists (security headers middleware, `RequiresAbility` attribute, tests); its test suite is registered. Every other slice and the provider and route wiring are planned.

## 1. Pre-flight

| # | Check |
|---|---|
| 1 | The slice is in [../architecture/slice-catalog.md](../architecture/slice-catalog.md). If not, stop and ask the owner |
| 2 | Read `.ai/rules/index.md`, every rule file whose globs cover the paths in scope (at least `features.md`, `app.md`, plus `actions.md`, `data.md`, `enums.md`, `models.md`, `traits.md`, `routes.md`, `tests.md`, `migrations.md` as relevant), run `grep -rin '<keyword>' .ai/rules`, and read the CLAUDE.md project sections |
| 3 | Search for an existing Action, Data object or enum that already covers the need (`Grep` in `app/Features`) |
| 4 | List the PRD IDs the slice implements (FR-, H, S, US-) |
| 5 | Load skills `vertical-slice-scaffold`, `laravel-best-practices`, `testing-best-practices` |

## 2. Folder skeleton

Create only what is needed:

```
app/Features/<Slice>/
  Actions/            VerbNounAction.php (one public handle())
  Data/               readonly DTOs
  Enums/              string-backed, TitleCase cases, HasLabel/HasColor when shown
  Events/  Listeners/
  Exceptions/         typed exceptions
  Http/Controllers/   thin controllers
  Http/Requests/      Form Requests
  Http/Resources/     API Resources
  Jobs/
  Mcp/                MCP tools (AgentGateway only)
  Ai/                 laravel/ai agents (Extraction, Guard, AgentGateway)
  Models/
  Models/Interfaces/
  Models/Traits/Forms/  Models/Traits/Tables/  Models/Traits/Infolists/
  Notifications/
  Policies/
  Providers/<Slice>ServiceProvider.php
  Routes/web.php  Routes/api.php
  Commands/
  Tests/
```

## 3. Steps

| # | Step | Command or file |
|---|---|---|
| 1 | Model + migration + factory + seeder | `php artisan make:model Vendor --migration --factory --seeder --no-interaction`, then move `app/Models/Vendor.php` to `app/Features/Vendor/Models/Vendor.php` and set `namespace App\Features\Vendor\Models;`. Migrations stay in `database/migrations/`, factories in `database/factories/`, seeders in `database/seeders/` |
| 2 | Model rules | `declare(strict_types=1);`, `use HasUuids;` (UUIDv7), `#[Fillable([...])]`, `#[Hidden([...])]` when needed, `casts()` method, typed relations with generics |
| 3 | Migration rules | Reversible `down()`, portable SQLite + Postgres, money `bigInteger('*_minor')`, `char('currency', 3)`, constraints from [../architecture/data-model.md](../architecture/data-model.md) |
| 4 | Enum | `php artisan make:enum Features/Vendor/Enums/VendorStatus --string --no-interaction` (verify the generated path; move if Laravel places it elsewhere) |
| 5 | Data object | `php artisan make:class Features/Vendor/Data/CreateVendorData --no-interaction`, then make it `final readonly class` with promoted typed properties |
| 6 | Action | `php artisan make:class Features/Vendor/Actions/CreateVendorAction --no-interaction`; one public `handle()`, constructor-injected collaborators, `DB::transaction()` for multi-step writes, audit through `RecordAuditEntryAction` |
| 7 | Exceptions | `php artisan make:exception Features/Vendor/Exceptions/PayeeEmailChangeNotConfirmedException --no-interaction` |
| 8 | Policy | `php artisan make:policy VendorPolicy --model=App\\Features\\Vendor\\Models\\Vendor --no-interaction`, move to `app/Features/Vendor/Policies/`, register with `Gate::policy()` in the slice provider (do not rely on auto-discovery for non-standard paths) |
| 9 | Form Request (HTTP entry points only) | `php artisan make:request ...` moved to `Http/Requests/` |
| 10 | Job | `php artisan make:job ...` moved to `Jobs/`; jobs call one Action and refresh models (`$model->fresh()`) before acting |
| 11 | Command | `php artisan make:command ...` moved to `Commands/`; signature prefix `antibody:` |
| 12 | Service provider | `php artisan make:provider VendorServiceProvider --no-interaction`, move to `Providers/`. Register Actions as singletons in `register()`, policies, rate limiters and commands in `boot()`. Do not add it to `bootstrap/providers.php`: `AppServiceProvider` discovers it |
| 13 | Routes | `Routes/web.php` (web middleware) or `Routes/api.php` (api middleware, no `api/v1` prefix). Named routes, `throttle:` limiters |
| 14 | Filament UI | Follow [adding-a-filament-resource.md](adding-a-filament-resource.md) |
| 15 | Tests | `php artisan make:test --pest VendorRegistryTest --no-interaction`, move to `app/Features/Vendor/Tests/Feature/` (database or HTTP) or `Tests/Unit/` (no database); register the suite (section 5) |
| 16 | Docs | Update the slice row in [../architecture/slice-catalog.md](../architecture/slice-catalog.md) and set `status: implemented` where code now exists |
| 17 | Verify | `php artisan test --compact --testsuite=Vendor`, `php artisan test --compact --testsuite=Architecture`, `vendor/bin/pint --dirty --format agent`, then `composer ci:check` |

## 4. One-time wiring (done by the first slice)

| Wiring | File | Planned implementation |
|---|---|---|
| Provider auto-discovery | `app/Providers/AppServiceProvider.php` | `register()` scans `app_path('Features')` recursively for `*ServiceProvider.php`, builds the class name from the PSR-4 path, and calls `$this->app->register($className)` when `class_exists()` |
| Route auto-discovery | `bootstrap/app.php` | `withRouting(then: ...)` iterates `app/Features/*/Routes/`; `web.php` → `Route::middleware('web')->group(...)`, `api.php` → `Route::middleware('api')->group(...)` |
| Factory resolver | `app/Providers/AppServiceProvider.php` | `Factory::guessFactoryNamesUsing()` maps `App\Features\<Slice>\Models\<Model>` to `Database\Factories\<Model>Factory`; models also declare `/** @use HasFactory<VendorFactory> */` |
| Strict models | `app/Providers/AppServiceProvider.php` | `Model::shouldBeStrict(! app()->isProduction())` (never `Model::unguard()`) |
| Security headers middleware | `app/Features/Shared/Http/Middleware/SecurityHeaders.php` + `bootstrap/app.php` `withMiddleware()` | Implemented (ABP-9, global append), see [../security/owasp-mapping.md](../security/owasp-mapping.md) A02 |

## 5. Test suite registration

**Status: implemented for the Shared slice (ABP-9).** Each slice registers once; Pest binding is by glob and needs no per-slice edit.

| File | Addition |
|---|---|
| `phpunit.xml` | `<testsuite name="Vendor"><directory>app/Features/Vendor/Tests</directory></testsuite>` and `<exclude><directory>app/Features/Vendor/Tests</directory></exclude>` inside `<source>` |
| `tests/Pest.php` | None. `->in('AccessMatrix', '../app/Features/*/Tests/Feature')` already binds `Tests\TestCase` and `RefreshDatabase` to every slice `Tests/Feature` folder. `Tests/Unit` tests run without a database |
| `tests/Architecture/SliceMap.php` | Already lists all 13 slices and their allowed edges; edit it only together with [../architecture/slice-catalog.md](../architecture/slice-catalog.md) |
| Check | `ToolConfigTest` (ABP-9) fails when a slice `Tests` folder is not registered; the architecture suite fails when a folder under `app/Features` is not in the slice map |

## 6. Done checklist

| # | Item |
|---|---|
| 1 | No business logic in controllers, MCP tools, Filament resources, Livewire, models or Blade |
| 2 | Every entry point authorizes (Policy, `->authorize()`, H10 on MCP) |
| 3 | Every validation rule has a DB constraint |
| 4 | State transitions use `lockForUpdate()` and throw on illegal moves |
| 5 | Audit entries written for decisions, approvals, vendor changes, payout calls, webhooks |
| 6 | Tests cover happy path, edge, invalid input, unauthorized, abuse, failure |
| 7 | New config keys in `config/antibody.php` (planned) and `.env.example` with placeholders |
| 8 | Zero code comments, Pint clean, tests green |
