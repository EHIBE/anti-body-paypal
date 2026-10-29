---
title: Adding a Filament Resource
purpose: Step-by-step checklist for adding a Filament 5 resource that keeps UI schema in model traits and business logic in slice Actions.
audience: [developers, ai-agents]
status: planned
related:
  - ../architecture/filament-separation.md
  - adding-a-slice.md
  - coding-conventions.md
  - testing-strategy.md
source_of_truth: docs/architecture/filament-separation.md, app/Providers/Filament/AdminPanelProvider.php
last_verified: 2026-10-30
---

# Adding a Filament Resource

> **TL;DR**
> - Resources live in `app/Filament/Resources/<Plural>/` with `Pages/`, `Schemas/`, `Tables/`. The `admin` panel discovers them automatically.
> - Field, column, filter and infolist definitions live in model traits: `getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`, with typed boolean context flags.
> - Every action calls `->authorize()` and delegates to one slice Action. Never put business logic in the resource.
> - Status: planned. The panel exists (`/admin`, login, Amber primary); no resource exists yet.

## 1. Panel facts (implemented)

| Item | Value | File |
|---|---|---|
| Panel ID | `admin` (default) | `app/Providers/Filament/AdminPanelProvider.php` |
| Path | `/admin` | same |
| Auth | `->login()`, `Authenticate` auth middleware | same |
| Primary color | `Color::Amber` (PRD 19.4 deep green is planned for P1) | same |
| Discovery | `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets` | same |
| CSRF | `PreventRequestForgery` in panel middleware | same |

## 2. Steps

| # | Step | Command or file |
|---|---|---|
| 1 | Confirm the model exists in its slice (`app/Features/<Slice>/Models/<Model>.php`) and has a Policy | [adding-a-slice.md](adding-a-slice.md) |
| 2 | Generate the resource | `php artisan make:filament-resource Vendor --model-namespace="App\\Features\\Vendor\\Models" --view --no-interaction` (check `php artisan make:filament-resource --help` for the exact Filament 5 options) |
| 3 | Check layout | `app/Filament/Resources/Vendors/VendorResource.php`, `Pages/`, `Schemas/VendorForm.php`, `Schemas/VendorInfolist.php`, `Tables/VendorsTable.php` |
| 4 | Create the model traits | `app/Features/Vendor/Models/Traits/Forms/VendorFormTrait.php` (`getForm()`), `Tables/VendorTableTrait.php` (`getTableSchema()`, `getFilters()`), `Infolists/VendorInfolistTrait.php` (`getInfolist()`) |
| 5 | Use the traits in the model | `use VendorFormTrait; use VendorTableTrait; use VendorInfolistTrait;` |
| 6 | Point the schema classes at the traits | `VendorForm::configure()` returns `$schema->components(Vendor::getForm())`; `VendorsTable::configure()` uses `->columns(Vendor::getTableSchema())->filters(Vendor::getFilters())` |
| 7 | Add actions | Each action: `->authorize('<ability>')`, `->requiresConfirmation()` for destructive or money-moving actions, closure injects the slice Action and calls `handle()` |
| 8 | Add typed confirmation where required | FR-02 payee change, FR-28 score-BLOCK override, auto ceiling change: a `TextInput` that must equal a fixed phrase, validated server-side in the Action |
| 9 | Notifications | Convert typed exceptions to `Notification::make()->danger()` with a safe message at the boundary |
| 10 | Navigation | `$navigationIcon` (Heroicon enum), `$navigationGroup`, `$navigationSort` |
| 11 | Tests | Livewire tests in the model's slice `Tests/Filament/` |
| 12 | Verify | `php artisan test --compact app/Features/Vendor/Tests`, `vendor/bin/pint --dirty --format agent`, open `/admin/vendors` |

## 3. Trait skeleton (illustrative, planned)

```php
<?php

declare(strict_types=1);

namespace App\Features\Vendor\Models\Traits\Tables;

use App\Features\Vendor\Enums\VendorStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

trait VendorTableTrait
{
    /**
     * @return list<TextColumn>
     */
    public static function getTableSchema(bool $isOwnerView = true): array
    {
        return [
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('paypal_email')->searchable()->visible($isOwnerView),
            TextColumn::make('status')->badge(),
            TextColumn::make('verified_at')->dateTime()->sortable(),
        ];
    }

    /**
     * @return list<SelectFilter>
     */
    public static function getFilters(): array
    {
        return [
            SelectFilter::make('status')->options(VendorStatus::class),
        ];
    }
}
```

## 4. Action skeleton inside a resource table (illustrative, planned)

```php
Action::make('changePayeeEmail')
    ->authorize('changePayeeEmail')
    ->schema(Vendor::getPayeeChangeForm())
    ->requiresConfirmation()
    ->action(function (array $data, Vendor $record, ChangeVendorPayeeEmailAction $changeVendorPayeeEmail): void {
        $changeVendorPayeeEmail->handle($record, ChangeVendorPayeeEmailData::fromValidated($data));
    });
```

## 5. Planned resources and pages (PRD 19.1)

| Screen | Type | Folder | Model slice |
|---|---|---|---|
| Overview | Dashboard + 5 stat widgets + recent decisions table | `app/Filament/Pages/`, `app/Filament/Widgets/` | Invoice, Payment, Policy |
| Invoices | Resource (list + view), Upload action | `app/Filament/Resources/Invoices/` | Invoice |
| Invoice detail | Custom view page (decision card, signals, extracted fields, PDF preview, timeline, approve/reject/override) | `app/Filament/Resources/Invoices/Pages/ViewInvoice.php` | Invoice, Guard, Approval |
| Approval queue | Resource list filtered to `held` | `app/Filament/Resources/Approvals/` or a tab on Invoices | Approval |
| Vendors | Resource | `app/Filament/Resources/Vendors/` | Vendor |
| Agents | Resource (create, show token once, revoke) | `app/Filament/Resources/Agents/` | AgentGateway |
| Policy | Settings page | `app/Filament/Pages/GuardPolicySettings.php` | Policy |
| Audit log | Read-only resource, "verify chain" action | `app/Filament/Resources/AuditLogs/` | Audit |
| AP Agent | Custom page, "Process pending invoices" + transcript | `app/Filament/Pages/ApAgent.php` | AgentGateway |

## 6. Done checklist

| # | Item |
|---|---|
| 1 | Resource contains no business logic and no inline schema arrays beyond calling model traits |
| 2 | Every action has `->authorize()`; hidden actions are authorized server-side too |
| 3 | Invoice and model text is never rendered as HTML (no `->html()`, no `{!! !!}`) |
| 4 | PDF preview uses a signed, owner-only route (A08) |
| 5 | Lists paginate and eager load (`->modifyQueryUsing(fn ($query) => $query->with('vendor'))`) |
| 6 | Empty, loading, AUTO, HOLD, BLOCK hard, BLOCK score, Paid, Failed, Submitting states styled per PRD 19.2 |
| 7 | Livewire tests cover list, view, each action, and unauthorized access |
