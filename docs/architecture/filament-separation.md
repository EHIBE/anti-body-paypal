---
title: Filament Separation with Model Traits
purpose: Define how the Filament 5 owner dashboard is kept separate from slice business logic, and how form, table, filter and infolist schema live in model traits.
audience: [developers, ai-agents]
status: mixed
related:
  - ./vertical-slice-architecture.md
  - ./slice-catalog.md
  - ../development/adding-a-filament-resource.md
  - ../decisions/0002-filament-separation-with-model-traits.md
  - ../prd-anti-body-paypal.md
  - ../../.ai/rules/index.md
source_of_truth: This document for the pattern; .ai/rules/index.md for the agent-facing rule index (filament and traits rules). docs/prd-anti-body-paypal.md section 19 for screens, states and visual direction. app/Providers/Filament/AdminPanelProvider.php for the panel.
last_verified: 2026-10-30
---

# Filament Separation with Model Traits

> **TL;DR**
> - **Implemented:** one Filament 5.11.0 panel, id `admin`, path `/admin`, login page, primary color Amber, discovery of `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets`.
> - **Planned:** resources in `app/Filament/Resources/<Plural>/` with `Pages/`, `Schemas/`, `Tables/` sub-folders. Resources hold no business logic and no field definitions.
> - Field, column, filter and infolist definitions live in slice model traits as static methods: `getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`. Resources call `Model::getForm()` and friends.
> - Context changes (for example the approval queue versus the full invoice list) pass typed camelCase booleans, never strings.
> - Every Filament action calls one slice Action and has `->authorize()`. Invoice or model text is never rendered as HTML.
> - AI agents also follow the filament and traits rules listed in [.ai/rules/index.md](../../.ai/rules/index.md).

## 1. Panel (implemented)

| Setting | Value | File |
|---|---|---|
| Panel id | `admin` (default panel) | `app/Providers/Filament/AdminPanelProvider.php` |
| Path | `/admin` | same |
| Auth | `->login()`, `Authenticate` auth middleware | same |
| Primary color | `Color::Amber` (PRD 19.4 asks for deep green; change planned in P1) | same |
| Discovery | `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets` | same |
| Default widgets | `AccountWidget`, `FilamentInfoWidget` (replaced by FR-44 widgets, planned) | same |
| Middleware | Includes `PreventRequestForgery` (CSRF) and `AuthenticateSession` | same |
| Brand name | Falls back to `APP_NAME` (`anti-body-paypal`); `->brandName('Anti-Body')` planned in B7 | same |
| Multi-factor auth | Not configured; OQ-06 | n/a |

## 2. Folder layout (planned)

```
app/Features/<Slice>/Models/
├── <Model>.php
└── Traits/
    ├── Forms/<Model>FormTrait.php           getForm()
    ├── Tables/<Model>TableTrait.php         getTableSchema(), getFilters()
    └── Infolists/<Model>InfolistTrait.php   getInfolist()

app/Filament/
├── Resources/<Plural>/
│   ├── <Model>Resource.php
│   ├── Pages/        List<Plural>.php, Create<Model>.php, Edit<Model>.php, View<Model>.php
│   ├── Schemas/      <Model>Form.php, <Model>Infolist.php
│   └── Tables/       <Plural>Table.php
├── Pages/            GuardPolicySettings.php, ApAgent.php, Dashboard (Overview)
├── Widgets/          MoneyProtectedWidget.php, AutoPaidWidget.php, HeldWidget.php, BlockedWidget.php, WeeklyBudgetUsedWidget.php
└── Components/       reusable fields, columns, badges (for example MoneyColumn, DecisionBadge)
```

## 3. Responsibility split

| Concern | Lives in | Example |
|---|---|---|
| Which fields, columns, filters and entries exist | Model trait static methods | `Vendor::getForm()` |
| How a resource composes them | `Schemas/` and `Tables/` classes | `VendorForm::configure($schema)` |
| Navigation, model binding, pages, query scoping | `<Model>Resource.php` | `getEloquentQuery()` |
| What a button does | One slice Action injected into the action closure | `ChangeVendorPayeeEmailAction::handle()` |
| Who may press a button | Policy + `->authorize()` on the action, `->visible()` for UX only | `->authorize('overrideBlock')` |
| Reusable visuals | `app/Filament/Components/` | `DecisionBadge` |

Model traits hold only UI schema definitions. A trait never queries, writes, or calls an Action.

## 4. Model trait skeleton (illustrative, planned)

```php
<?php

declare(strict_types=1);

namespace App\Features\Invoice\Models\Traits\Tables;

use App\Features\Guard\Enums\Decision;
use App\Features\Invoice\Enums\InvoiceStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

trait InvoiceTableTrait
{
    /**
     * @return list<TextColumn>
     */
    public static function getTableSchema(bool $isApprovalQueue = false): array
    {
        return [
            TextColumn::make('vendor.name')->label('Vendor')->searchable(),
            TextColumn::make('invoice_number')->label('Invoice')->searchable(),
            TextColumn::make('amount_minor')
                ->label('Amount')
                ->formatStateUsing(fn (?int $state, self $record): string => number_format(($state ?? 0) / 100, 2).' '.$record->currency),
            TextColumn::make('status')->badge(),
            TextColumn::make('latestAssessment.score')->label('Risk')->visible($isApprovalQueue),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ];
    }

    /**
     * @return list<SelectFilter>
     */
    public static function getFilters(bool $isApprovalQueue = false): array
    {
        return [
            SelectFilter::make('status')->options(InvoiceStatus::class)->hidden($isApprovalQueue),
            SelectFilter::make('latestAssessment.decision')->label('Decision')->options(Decision::class),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Features\Invoice\Models;

use App\Features\Invoice\Models\Traits\Forms\InvoiceFormTrait;
use App\Features\Invoice\Models\Traits\Infolists\InvoiceInfolistTrait;
use App\Features\Invoice\Models\Traits\Tables\InvoiceTableTrait;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'submitted_by_agent_id', 'file_path', 'file_sha256', 'status'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasUuids;
    use InvoiceFormTrait;
    use InvoiceInfolistTrait;
    use InvoiceTableTrait;
}
```

Extracted fields and `approved_amount_minor` are written only by named Actions through explicit attribute assignment, never through a fillable array built from request input.

## 5. Resource skeleton for Filament 5 (illustrative, planned)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices;

use App\Features\Invoice\Models\Invoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    #[\Override]
    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Tables;

use App\Features\Approval\Actions\ApproveInvoiceAction;
use App\Features\Invoice\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table, bool $isApprovalQueue = false): Table
    {
        return $table
            ->columns(Invoice::getTableSchema(isApprovalQueue: $isApprovalQueue))
            ->filters(Invoice::getFilters(isApprovalQueue: $isApprovalQueue))
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->authorize('approve')
                    ->requiresConfirmation()
                    ->action(fn (Invoice $record, ApproveInvoiceAction $approveInvoice) => $approveInvoice->handle($record, auth()->user())),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated();
    }
}
```

`Schemas/InvoiceInfolist.php` follows the same shape: `return $schema->components(Invoice::getInfolist());`.

## 6. Context flags

| Flag | Used by | Effect |
|---|---|---|
| `bool $isApprovalQueue = false` | `Invoice::getTableSchema()`, `getFilters()` | Shows risk score column, hides the status filter (queue is pre-filtered to `held`) |
| `bool $isPayeeChange = false` | `Vendor::getForm()` | Shows the typed confirmation field and the required verification note (FR-02) |
| `bool $isReadOnly = false` | `AuditLog::getInfolist()` | Audit entries are always read-only; flag reserved for shared components |

Rules: flags are camelCase typed booleans with a default, passed by name at the call site. Never pass role strings or panel ids.

## 7. Screens (PRD 19.1)

| Screen | Filament type | Planned location | Slice Actions used | FR / US |
|---|---|---|---|---|
| Overview | Dashboard page, 5 stat widgets + recent decisions table | `app/Filament/Pages/Dashboard.php`, `app/Filament/Widgets/*` | Guard and Payment query Actions | FR-44 |
| Invoices | Resource (list + view), upload action | `app/Filament/Resources/Invoices/` | Invoice `ReceiveInvoiceAction` | FR-04, FR-45 |
| Invoice detail | Custom view page: decision card, signals with points, extracted fields, PDF preview (iframe, signed route), timeline, approve/reject/override | `Resources/Invoices/Pages/ViewInvoice.php` | Approval Actions | FR-45, US-04, US-09 |
| Approval queue | Resource list filtered to `held`, one-by-one decisions, no bulk actions | `Resources/Invoices/Pages/ListHeldInvoices.php` | Approval Actions | FR-25 |
| Vendors | Resource | `app/Filament/Resources/Vendors/` | Vendor Actions | FR-01 to FR-03, FR-39, US-01 |
| Agents | Resource: create agent, show token once, revoke | `app/Filament/Resources/Agents/` | AgentGateway Actions | FR-29, H10 |
| Policy | Settings page: ceilings, budget, pause toggle | `app/Filament/Pages/GuardPolicySettings.php` | Policy Actions | US-02, US-05, FR-17 |
| Audit log | Resource (read-only), filter, verify chain | `app/Filament/Resources/AuditLogs/` | Audit `VerifyAuditChainAction` | FR-35, FR-36 |
| AP Agent | Custom page: "Process pending invoices" button + transcript | `app/Filament/Pages/ApAgent.php` | AgentGateway AP agent | FR-34, US-06 |

## 8. UI states (PRD 19.2)

| State | Treatment |
|---|---|
| Empty (no invoices) | Message "No invoices yet. Upload one or connect an agent." plus Upload button |
| Loading (extraction, scoring) | Inline progress row "Reading invoice", "Checking rules", "Scoring" |
| AUTO | Green badge "Auto-approved", payment chip |
| HOLD | Amber badge, "Waiting for you" plus Approve/Reject |
| BLOCK hard | Red badge "Blocked: rule", lock icon, no override button |
| BLOCK score | Red outline badge "Blocked: risk", override button with typed confirmation |
| Paid | Green check, PayPal item ID masked |
| Failed | Red, reason, "What to do" line |
| Submitting | Spinner "Confirming with PayPal" |
| Submitted, not yet paid | "Submitted, awaiting confirmation" (never "Paid" before the `paid` state, PRD 11.2) |
| PayPal unreachable | Banner "PayPal unreachable, payments queued" (PRD 10.2) |
| AI unavailable | "AI unavailable, held for review" (PRD 10.2) |

Enums shown in the UI implement Filament `HasLabel` and `HasColor` so badges take label and color from one place.

## 9. Visual direction (PRD 19.4)

| Element | Direction |
|---|---|
| Theme | Filament default theme with one custom primary color: deep green for trust |
| Status colors | Amber for HOLD, red for BLOCK, green for AUTO and Paid |
| Decision card | The hero: big badge, score dial, top 3 reasons in plain words, details collapsed |
| Money protected | Large number on Overview |
| Logos | Fictional vendor logos as simple text marks. No real brand logos |

## 10. Security rules for Filament code

| Rule | Reason |
|---|---|
| `->authorize()` on every action, including hidden ones | A01: hidden UI is not authorization |
| Never render invoice text, suspicious phrases, notes or model output as HTML; no `->html()`, no `{!! !!}` | A05: invoice content is attacker-controlled |
| Suspicious phrases shown with the label "found in invoice, treated as data" | PRD 11.1 step 8 |
| PDF preview only through a signed, owner-only route on the private disk | A08, PRD 18 |
| Livewire public properties holding IDs are `#[Locked]` or re-authorized | Feature prompt section 5 |
| Override of score-BLOCK and change of payee email or `auto_ceiling` require typed confirmation | FR-02, FR-28, PRD 9.3 |
| Paginate every table | Feature prompt 4.4 |
