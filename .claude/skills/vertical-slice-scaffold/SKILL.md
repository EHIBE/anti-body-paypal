---
name: vertical-slice-scaffold
description: "Use whenever creating or extending an Anti-Body vertical slice under `app/Features/<Slice>/`: adding Actions, Data objects, Enums, Events, Exceptions, Jobs, Listeners, Models, Policies, Commands, Routes, slice service providers, or slice tests, or wiring provider auto-discovery in AppServiceProvider, route loading in bootstrap/app.php, testsuites in phpunit.xml, and Pest bindings. Triggers: new slice, new Action, app/Features, VerbNounAction, readonly Data, slice provider, Routes/api.php, slice Tests. Do not use for Filament resource layout alone (use filament-model-traits)."
paths:
  - "app/Features/**"
  - "bootstrap/app.php"
  - "app/Providers/AppServiceProvider.php"
  - "phpunit.xml"
  - "tests/Pest.php"
---

# Vertical slice scaffold

Spec: [docs/architecture/vertical-slice-architecture.md](../../../docs/architecture/vertical-slice-architecture.md), [docs/architecture/slice-catalog.md](../../../docs/architecture/slice-catalog.md), [docs/development/adding-a-slice.md](../../../docs/development/adding-a-slice.md), [docs/development/coding-conventions.md](../../../docs/development/coding-conventions.md).

## When to use

- First code in a planned slice: Shared, Vendor, Invoice, Extraction, Guard, Approval, Payment, Webhook, Telegram, AgentGateway, Audit, Policy, Baseline.
- New units inside an existing slice.
- Never create a slice outside this list without owner approval.

## Folder tree (only the folders a slice needs)

```
app/Features/<Slice>/
  Actions/            VerbNounAction, one public handle()
  Ai/                 laravel/ai agents (planned dependency)
  Commands/           Artisan commands, registered in the slice provider
  Data/               final readonly DTOs
  Enums/              string-backed, TitleCase cases, HasLabel/HasColor when in UI
  Events/  Listeners/  Exceptions/  Jobs/  Notifications/  Policies/
  Http/Controllers/  Http/Requests/  Http/Resources/
  Mcp/
  Models/  Models/Interfaces/  Models/Traits/{Forms,Tables,Infolists}/
  Providers/<Slice>ServiceProvider.php
  Routes/web.php  Routes/api.php
  Tests/
```

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Generate with Artisan and move into the slice: `php artisan make:class`, `make:model`, `make:enum`, `make:job`, `make:event`, `make:exception`, `make:policy`, `make:request`, `make:resource`, `make:command`, `make:migration`, `make:factory`, all with `--no-interaction`. Fix namespaces to mirror the path.
2. Add `declare(strict_types=1);` to every file.
3. Register Actions in `Providers/<Slice>ServiceProvider.php` and console commands in its `boot()`.
4. First slice only: add provider auto-discovery to `AppServiceProvider::register()` (scan `app/Features` for `*ServiceProvider.php`), slice route loading to the `then:` callback in `bootstrap/app.php` (`Routes/web.php` with `web`, `Routes/api.php` with `api`, no prefix), a factory name resolver if models live outside `App\Models`, a slice testsuite in `phpunit.xml`, and the Pest binding in `tests/Pest.php`.
5. Migrations, factories, seeders stay in `database/`.
6. Write Pest tests in `Tests/` for every Action.

## Illustrative skeleton

```php
<?php

declare(strict_types=1);

namespace App\Features\Vendor\Actions;

use App\Features\Audit\Actions\RecordAuditEntryAction;
use App\Features\Vendor\Data\CreateVendorData;
use App\Features\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;

final class CreateVendorAction
{
    public function __construct(private RecordAuditEntryAction $recordAuditEntry) {}

    public function handle(CreateVendorData $data): Vendor
    {
        return DB::transaction(function () use ($data): Vendor {
            $vendor = Vendor::query()->create($data->toAttributes());
            $this->recordAuditEntry->handle(action: 'vendor.created', subject: $vendor);

            return $vendor;
        });
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Features\Vendor\Data;

final readonly class CreateVendorData
{
    public function __construct(
        public string $name,
        public string $paypalEmail,
        public string $currency,
        public ?string $verificationNote,
    ) {}

    /**
     * @return array{name: string, paypal_email: string, currency: string, verification_note: ?string}
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'paypal_email' => mb_strtolower(trim($this->paypalEmail)),
            'currency' => $this->currency,
            'verification_note' => $this->verificationNote,
        ];
    }
}
```

## Checklist

- [ ] Business logic only in Actions. Entry points parse, authorize, delegate, respond.
- [ ] Dependencies injected via constructor promotion. No `app()` inside Actions.
- [ ] Models use `HasUuids` (UUIDv7), `#[Fillable]`, `#[Hidden]`, `casts()`; no `Model::unguard()`.
- [ ] Typed exceptions in `Exceptions/`. Empty collections, never null.
- [ ] `#[\Override]` on overridden framework methods.
- [ ] No code comments except PHPDoc type annotations.

## Common mistakes

- Calling another slice's model directly for writes instead of its Action.
- Snake_case variables (the reference project used them; this project uses camelCase).
- Using spatie/laravel-data (not installed; this project uses readonly DTOs).

## Verification

```bash
php artisan about
php artisan route:list --except-vendor
php artisan test --compact --testsuite=<Slice>
vendor/bin/pint --dirty --format agent
```
