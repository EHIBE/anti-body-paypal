---
name: filament-model-traits
description: "Use whenever creating or changing Anti-Body Filament 5 resources, pages, tables, forms, filters, or infolists. Enforces the separation: resources live in `app/Filament/Resources/<Plural>/` with Pages/, Schemas/, Tables/, while field, column, filter, and infolist definitions live as static methods in model traits (`<Model>FormTrait::getForm()`, `<Model>TableTrait::getTableSchema()` and `getFilters()`, `<Model>InfolistTrait::getInfolist()`) with typed camelCase context flags. Triggers: make:filament-resource, Schema, Table, infolist, decision card, approval queue, vendor registry UI. Do not use for business logic."
paths:
  - "app/Filament/**"
  - "app/Features/*/Models/Traits/**"
---

# Filament model traits

Spec: [docs/architecture/filament-separation.md](../../../docs/architecture/filament-separation.md), [docs/development/adding-a-filament-resource.md](../../../docs/development/adding-a-filament-resource.md). Screens: PRD 19.

## When to use

- Adding a resource for Vendors, Invoices, Approval queue, Agents, Audit log, or the Policy settings page and AP agent page (all planned).
- Adding or changing a column, field, filter, infolist entry.

## Layout

| Piece | Path |
|---|---|
| Resource | `app/Filament/Resources/<Plural>/<Model>Resource.php` |
| Pages | `app/Filament/Resources/<Plural>/Pages/` |
| Form schema wrapper | `app/Filament/Resources/<Plural>/Schemas/<Model>Form.php` |
| Infolist wrapper | `app/Filament/Resources/<Plural>/Schemas/<Model>Infolist.php` |
| Table wrapper | `app/Filament/Resources/<Plural>/Tables/<Plural>Table.php` |
| Definitions | `app/Features/<Slice>/Models/Traits/{Forms,Tables,Infolists}/<Model>{Form,Table,Infolist}Trait.php` |
| Reusable components | `app/Filament/Components/` (for example `MoneyColumn`, `DecisionBadge`) |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Generate: `php artisan make:filament-resource <Model> --no-interaction` (check flags with `--help`), then point `$model` at the slice model.
2. Write the trait static methods. Return plain arrays of components with PHPDoc `@return array<int, Component>` style types.
3. Use the model's traits (`use VendorFormTrait; use VendorTableTrait; use VendorInfolistTrait;`).
4. Wrappers call the model: `$schema->components(Vendor::getForm(isPayeeChange: false))`, `$table->columns(Vendor::getTableSchema())->filters(Vendor::getFilters())`.
5. Actions in tables or pages inject the slice Action and call `handle()`, with `->authorize()`.
6. Scope queries in `getEloquentQuery()`; eager load; paginate.

## Illustrative skeleton

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
     * @return array<int, TextColumn>
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
     * @return array<int, SelectFilter>
     */
    public static function getFilters(): array
    {
        return [
            SelectFilter::make('status')->options(VendorStatus::class),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Vendors\Tables;

use App\Features\Vendor\Models\Vendor;
use Filament\Tables\Table;

final class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(Vendor::getTableSchema())
            ->filters(Vendor::getFilters())
            ->defaultSort('name');
    }
}
```

## Checklist

- [ ] Traits hold only UI schema definitions. No queries with side effects, no business rules.
- [ ] Context flags are typed camelCase booleans with defaults.
- [ ] Enums shown in UI implement `HasLabel` and `HasColor`.
- [ ] Invoice and model text rendered as plain text. No `->html()` or `HtmlString`.
- [ ] Every action authorized. Hard BLOCK has no override action.
- [ ] Livewire tests in the owning slice `Tests/`.

## Common mistakes

- Defining columns inline in the resource (breaks reuse).
- Using Filament 4 namespaces. In Filament 5 layout components are under `Filament\Schemas\Components\`, actions under `Filament\Actions\`, infolist entries under `Filament\Infolists\Components\`. Confirm with Boost `search-docs`.
- Snake_case flags such as `$is_admin` (the reference style; this project uses camelCase).

## Verification

```bash
php artisan test --compact --filter=Resource
vendor/bin/pint --dirty --format agent
```
