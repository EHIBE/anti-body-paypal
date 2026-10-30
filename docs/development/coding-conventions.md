---
title: Coding Conventions
purpose: The binding code conventions for every PHP, Filament and test file in Anti-Body, with good and bad examples.
audience: [developers, ai-agents]
status: implemented
related:
  - ../architecture/vertical-slice-architecture.md
  - ../architecture/filament-separation.md
  - testing-strategy.md
  - adding-a-slice.md
  - commit-and-branch-rules.md
  - ../security/owasp-mapping.md
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
source_of_truth: .ai/rules/*.md (path-scoped rules, win on conflict), CLAUDE.md and AGENTS.md project sections and Boost block, feature prompt sections 3 to 5, pint.json, rector.php, phpstan.neon, tests/Architecture/
last_verified: 2026-10-30
---

# Coding Conventions

> **TL;DR**
> - Every PHP file starts with `declare(strict_types=1);`, its namespace mirrors its path, and every parameter and return is typed.
> - Business logic lives only in `VerbNounAction::handle()` inside `app/Features/<Slice>/Actions/`.
> - Money is integer minor units (`*_minor`), time is UTC, IDs are UUIDv7 (`HasUuids`) except framework tables, `agents` and `audit_logs`.
> - No code comments except PHPDoc types and array shapes. Run `vendor/bin/pint --dirty --format agent` after PHP changes.
> - Since ABP-9 the tooling enforces much of this: Larastan level max, Rector, Pint with `declare_strict_types`, and architecture tests (Actions and Data `final`, slice isolation). See section 6.
> - Status: the conventions are in force now. Most of the code they govern is planned.

## 0. Where the rules live

| Source | Scope | Status |
|---|---|---|
| `.ai/rules/index.md` + 15 rule files (`app.md`, `features.md`, `actions.md`, `data.md`, `enums.md`, `models.md`, `traits.md`, `routes.md`, `tests.md`, `guard.md`, `payment.md`, `agent-gateway.md`, `audit.md`, `filament.md`, `migrations.md`) | Path-scoped durable rules; read every file whose globs match the paths in scope before editing | Implemented (recorded via Boost `record-rule` in ABP-8) |
| CLAUDE.md / AGENTS.md project sections | Project summary, constraints, state, queue | Implemented |
| This document | Human-readable catalogue with examples (IDs CV-01..CV-55) | Implemented |

New rules are added only through the Boost `record-rule` tool and only when the owner explicitly asks. If this document and a `.ai/rules` file disagree, the `.ai/rules` file wins and this document is corrected.

## 1. PHP language rules

| ID | Rule | Good | Bad |
|---|---|---|---|
| CV-01 | `declare(strict_types=1);` in every PHP file | `<?php\n\ndeclare(strict_types=1);` | File without the declare |
| CV-02 | Namespace mirrors path | `app/Features/Vendor/Actions/CreateVendorAction.php` → `App\Features\Vendor\Actions` | `App\Actions\Vendor` |
| CV-03 | Explicit parameter and return types | `public function handle(CreateVendorData $data): Vendor` | `public function handle($data)` |
| CV-04 | Constructor property promotion | `public function __construct(private RecordAuditEntryAction $recordAuditEntry) {}` | Property declared then assigned in body |
| CV-05 | No empty zero-parameter `__construct()` unless private | Omit the constructor | `public function __construct() {}` |
| CV-06 | Curly braces on every control structure | `if ($isPaused) { return; }` | `if ($isPaused) return;` |
| CV-07 | `#[\Override]` on overridden methods | `#[\Override] public function register(): void` | Override without attribute |
| CV-08 | camelCase variables and parameters | `$approvedAmountMinor` | `$approved_amount_minor` (snake_case is for DB columns only) |
| CV-09 | Booleans read as questions | `$isPaused`, `$hasHistory`, `$canOverride` | `$pause`, `$history` |
| CV-10 | Enums: string-backed, TitleCase cases | `case AutoApproved = 'auto_approved';` | `case AUTO_APPROVED` |
| CV-11 | Enums shown in UI implement Filament `HasLabel` and `HasColor` | `enum Decision: string implements HasLabel, HasColor` | Label strings built in a resource |
| CV-12 | PHPDoc array shapes for arrays | `/** @return array{decision: string, score: int} */` | `@return array` |
| CV-13 | Zero code comments, PHPDoc types only | PHPDoc `@param list<Signal> $signals` | `// loop over signals` |
| CV-14 | No magic strings or numbers | `InvoiceStatus::Held`, `Policy::AUTO_CEILING_ABSOLUTE_MINOR` | `'held'`, `50000` inline |
| CV-15 | Return empty collections, never null | `return collect();` | `return null;` for "no rows" |
| CV-16 | Guard clauses, max two nesting levels, no `else` after `return` | Early `return` / `throw` | Nested `if` pyramids |

## 2. Laravel rules

| ID | Rule | Good | Bad |
|---|---|---|---|
| CV-20 | Config through `config()`; `env()` only inside `config/` | `config('antibody.extractor_model')` | `env('ANTIBODY_EXTRACTOR_MODEL')` in an Action |
| CV-21 | Models use `#[Fillable]` and `#[Hidden]` attributes | `#[Fillable(['name', 'paypal_email'])]` | `Model::unguard()`, `$request->all()` into `create()` |
| CV-22 | Casts via `casts()` method | `protected function casts(): array` | `protected $casts` property |
| CV-23 | Named routes with `route()` | `route('webhooks.paypal')` | Hard-coded `'/webhooks/paypal'` |
| CV-24 | Artisan `make:` with `--no-interaction` | `php artisan make:model Vendor --no-interaction` | Hand-written boilerplate |
| CV-25 | Prefer `Model::query()` over `DB::` | `Invoice::query()->where(...)` | `DB::table('invoices')` with user input |
| CV-26 | Eager load, no per-item queries in loops, paginate lists | `->with('vendor')->paginate(20)` | Query inside `foreach` |
| CV-27 | Multi-step writes in `DB::transaction()`, `lockForUpdate()` on state transitions | See [../integrations/idempotency.md](../integrations/idempotency.md) | Two writes without a transaction |
| CV-28 | JSON errors for `api/*` or `expectsJson()` | Already set in `bootstrap/app.php` | Custom HTML errors on API routes |
| CV-29 | Inject dependencies through the container; no `app()` inside Actions | Constructor promotion | `app(PayPalClient::class)` in `handle()` |

## 3. Architecture rules

| ID | Rule | Reference |
|---|---|---|
| CV-30 | Business logic only in Actions (`VerbNounAction`, one public `handle()`) | [../architecture/vertical-slice-architecture.md](../architecture/vertical-slice-architecture.md) |
| CV-31 | Entry points (controllers, MCP tools, commands, Filament actions) parse, authorize, delegate to one Action, respond | same |
| CV-32 | Readonly classes in `Data/` for DTOs | [../decisions/0006-readonly-dtos-over-spatie-laravel-data.md](../decisions/0006-readonly-dtos-over-spatie-laravel-data.md) |
| CV-33 | Typed exceptions in `app/Features/<Slice>/Exceptions/`, converted to safe messages at the boundary | [../api/error-formats.md](../api/error-formats.md) |
| CV-34 | Filament UI schema lives in model traits (`getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`) | [../architecture/filament-separation.md](../architecture/filament-separation.md) |
| CV-35 | H1 to H10, S1 to S7 and thresholds live only in the Guard slice | [../architecture/guard-spec.md](../architecture/guard-spec.md) |
| CV-36 | No new slice, base folder or dependency without approval | [../architecture/slice-catalog.md](../architecture/slice-catalog.md) |
| CV-37 | Actions and Data classes are `final` (Data also `readonly`); concrete slice classes are `final`; an Action exposes exactly one public `handle()` and its name ends in `Action`. Enforced by `tests/Architecture/StructureTest.php` | [testing-strategy.md](testing-strategy.md#3-cross-cutting-suites-implemented) |
| CV-38 | The `Models`, `Http`, `Policies`, `Providers`, `Listeners`, `Jobs`, `Mcp`, `Ai`, `Routes` and `Commands` namespaces of a slice are internal. Another slice reaches the slice only through its public namespaces (`Actions`, `Data`, `Enums`, `Events`, `Exceptions`, `Contracts`) along an edge declared in `tests/Architecture/SliceMap.php`. Enforced by `tests/Architecture/SliceIsolationTest.php` | [../architecture/slice-catalog.md](../architecture/slice-catalog.md) |

## 4. Data and money rules

| ID | Rule | Good | Bad |
|---|---|---|---|
| CV-40 | Money in integer minor units, column suffix `_minor` | `amount_minor = 68000` for US$680.00 | `amount = 680.00` float |
| CV-41 | Currency ISO 4217 text | `'USD'` | `'$'` |
| CV-42 | Times in UTC | `now('UTC')`, `timestamp` columns | Local PHT timestamps |
| CV-43 | IDs UUIDv7 via `HasUuids` (except framework tables, `agents`, `audit_logs`) | `use HasUuids;` | Auto-increment on `invoices` |
| CV-44 | Schema changes only in new reversible migrations | New migration with `down()` | Editing an applied migration |
| CV-45 | Every validation rule backed by a DB constraint | `unique` index on `vendors.paypal_email` | Uniqueness checked only in PHP |
| CV-46 | Migrations portable across SQLite and Postgres | Raw partial index valid on both | Postgres-only syntax |

Example of a planned Action that follows CV-01 to CV-16 and CV-40:

```php
<?php

declare(strict_types=1);

namespace App\Features\Guard\Actions;

use App\Features\Guard\Data\GuardOutcome;
use App\Features\Guard\Data\SignalResult;

final readonly class SumRiskScoreAction
{
    private const int SCORE_CAP = 100;

    /**
     * @param  list<SignalResult>  $signalResults
     */
    public function handle(array $signalResults): int
    {
        $points = array_sum(array_map(
            static fn (SignalResult $signalResult): int => max(0, $signalResult->points),
            $signalResults,
        ));

        return min(self::SCORE_CAP, $points);
    }
}
```

## 5. Security rules (summary)

The full mapping is in [../security/owasp-mapping.md](../security/owasp-mapping.md).

| ID | Rule |
|---|---|
| CV-50 | `->authorize()` on every Filament action; Policies for every model |
| CV-51 | `{{ }}` only in Blade; never `{!! !!}` with invoice or model text |
| CV-52 | Livewire public properties holding IDs use `#[Locked]` and are re-authorized |
| CV-53 | Secrets only in env; never logged, returned or committed |
| CV-54 | Compare secrets with `hash_equals()`; generate tokens with `Str::random()` or `random_bytes()` |
| CV-55 | Agent tools never accept amount or payee arguments |

## 6. Tooling

| Tool | Command | Status |
|---|---|---|
| Pint | `vendor/bin/pint --dirty --format agent` (local); `composer lint:check` (CI form) | Implemented (laravel/pint 1.32.1, `pint.json`: Laravel preset plus `declare_strict_types`; the three applied starter migrations are excluded) |
| Rector | `composer rector` (apply), `composer refactor:check` (dry run) | Implemented (rector/rector 2.8.3, `rector.php`, migrations and architecture fixtures skipped) |
| Larastan | `composer types:check` | Implemented (larastan/larastan v3.13.2, level max, strict rules, no baseline, `phpstan.neon`) |
| Pest | `php artisan test --compact` | Implemented (Pest 5.3.4) |
| Audits | `composer audit:check` (`composer audit` and `npm audit --audit-level=high`) | Implemented |
| Whole gate | `composer ci:check` | Implemented; run by the pre-push hook and CI ([../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md)) |

| Rule | Detail |
|---|---|
| PHPStan findings | Fix them in code. No baseline, no inline `@phpstan-ignore`. A config `ignoreErrors` entry needs an identifier, a path and a written reason |
| Rector vs convention | No Rector rule fights a convention so far. If one does, add a skip with a reason in `rector.php` |
| Starter files | `declare(strict_types=1)` is on every PHP file except the three applied starter migrations; do not edit applied migrations (CV-44) |
| Hooks | `.husky/pre-commit` runs Rector and Pint on the project; never bypass the hooks ([commit-and-branch-rules.md](commit-and-branch-rules.md#6-enforcement-implemented-abp-9)) |
