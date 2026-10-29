---
title: "ADR 0002: Filament separation with model traits"
purpose: Record the decision to keep Filament resources in app/Filament and define form, table, filter and infolist schemas in slice model traits.
audience: [developers, ai-agents]
status: planned
related:
  - ../architecture/filament-separation.md
  - ../development/adding-a-filament-resource.md
  - 0001-vertical-slice-architecture.md
source_of_truth: This ADR for the decision; docs/architecture/filament-separation.md for the pattern.
last_verified: 2026-10-30
---

# ADR 0002: Filament separation with model traits

> **TL;DR**
> - Decision: Filament resources live in `app/Filament/Resources/<Plural>/` (with `Pages/`, `Schemas/`, `Tables/`). Field, column, filter and infolist definitions live in model traits inside the slice: `Models/Traits/Forms/<Model>FormTrait::getForm()`, `Models/Traits/Tables/<Model>TableTrait::getTableSchema()` and `getFilters()`, `Models/Traits/Infolists/<Model>InfolistTrait::getInfolist()`.
> - Resources call `Model::getForm()`, `Model::getTableSchema()`, `Model::getFilters()`, `Model::getInfolist()`. Context differences use typed boolean flags such as `bool $isOwnerView = false`.
> - Model traits hold UI schema only, never business logic. Every Filament action calls `->authorize()` and delegates to one Action.
> - Status: accepted 2026-10-30. The panel `admin` exists; no resources or traits exist yet.

## Status

Accepted, 2026-10-30. Implementation status: planned. `app/Providers/Filament/AdminPanelProvider.php` (panel `admin` at `/admin`) is implemented and discovers `app/Filament/Resources`, `app/Filament/Pages`, `app/Filament/Widgets`.

## Context

| Factor | Detail |
|---|---|
| Panel | One Filament 5.11.0 panel, `admin`, for the single owner (PRD 19.1 screens: Overview, Invoices, Invoice detail, Approval queue, Vendors, Agents, Policy, Audit log, AP Agent) |
| Reuse | The same columns and fields appear in several places (invoice list, approval queue, recent decisions widget, vendor stats) |
| Slice ownership | The slice that owns a model knows which fields exist and how they display; the panel only arranges them |
| Prior experience | A prior private project (Filament 4, three panels) used model traits with context flags so one schema served several panels |

## Decision

| Rule | Detail |
|---|---|
| Resource location | `app/Filament/Resources/<Plural>/<Model>Resource.php` plus `Pages/`, `Schemas/<Model>Form.php`, `Tables/<Plural>Table.php` |
| Schema location | Static methods in slice model traits under `Models/Traits/Forms/`, `Models/Traits/Tables/`, `Models/Traits/Infolists/` |
| Method names | `getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`, each returning a typed array documented with PHPDoc (`@return array<int, Component>`) |
| Context flags | Typed booleans with camelCase names and defaults, passed as named arguments (`Invoice::getTableSchema(isApprovalQueue: true)`) |
| Reusable components | `app/Filament/Components/` (for example a money column that formats `*_minor` values) |
| Actions | Every Filament action uses `->authorize()` (policy ability) and calls one slice Action. No business logic in resources, pages, widgets or traits |
| Output safety | Never render invoice or model text as HTML in Filament |

## Consequences

| Type | Consequence |
|---|---|
| Positive | One definition per column or field; the approval queue and invoice list stay consistent |
| Positive | Slice owners change their schema without touching the panel |
| Negative | Models import Filament classes, coupling the model to Filament. Accepted because Filament is the only UI |
| Negative | Filament generators produce inline schemas; generated files must be edited to call the trait methods |

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Filament default (schemas inline in `Schemas/` and `Tables/` classes) | Duplicates column definitions across resources and widgets |
| Separate schema classes inside the slice (not traits) | Extra classes and imports for the same result; traits keep the call site `Model::getForm()` short |
| Resources inside slices | Mixes presentation with domain code and complicates panel discovery |
