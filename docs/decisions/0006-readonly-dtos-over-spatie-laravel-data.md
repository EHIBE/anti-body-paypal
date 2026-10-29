---
title: "ADR 0006: Readonly DTOs over spatie/laravel-data"
purpose: Record the decision to use plain readonly PHP classes in slice Data/ folders instead of spatie/laravel-data.
audience: [developers, ai-agents]
status: planned
related:
  - ../development/coding-conventions.md
  - ../architecture/vertical-slice-architecture.md
  - 0001-vertical-slice-architecture.md
source_of_truth: This ADR; project conventions in CLAUDE.md and AGENTS.md project sections.
last_verified: 2026-10-30
---

# ADR 0006: Readonly DTOs over spatie/laravel-data

> **TL;DR**
> - Decision: data transfer objects are `final readonly class` declarations in `app/Features/<Slice>/Data/`, built from already-validated input (Form Requests, MCP tool schemas, Filament rules, schema-validated model output).
> - No spatie/laravel-data dependency. Validation stays in Form Requests, MCP schemas and Filament rules.
> - Money fields are `int` minor units; enums are typed; arrays carry PHPDoc array shapes.
> - The prior private project used mutable spatie/laravel-data objects with snake_case properties; Anti-Body intentionally differs.

## Status

Accepted, 2026-10-30. Implementation status: planned (no `Data/` classes exist yet).

## Context

| Factor | Detail |
|---|---|
| Dependencies | Adding packages needs owner approval; spatie/laravel-data is not installed |
| Immutability | Guard inputs (extracted fields, vendor stats, policy values) must not change between rule evaluation and decision. Mutable DTOs allowed mid-flow edits in the reference project |
| Untrusted input | Model output and MCP arguments are decoded to arrays, validated, then mapped into DTOs. Never deserialized into objects (A08) |
| PHP 8.5 | Readonly classes and constructor promotion make plain DTOs short |

## Decision

| Rule | Detail |
|---|---|
| Declaration | `final readonly class <Name>Data` with promoted constructor properties, camelCase names, explicit types |
| Construction | Named static constructors such as `fromValidated(array $validated): self` or `fromExtractorOutput(array $output): self`, with PHPDoc array shapes for the input |
| Contents | No business logic beyond simple derivations; no I/O |
| Money | `int $amountMinor`, never float |
| Time | `CarbonImmutable` in UTC |
| Validation | Before construction: Form Requests (`Http/Requests/`), MCP tool input schemas, Filament field rules, extractor and analyst schema validation |

Illustrative skeleton (planned, not in the codebase):

```php
<?php

declare(strict_types=1);

namespace App\Features\Invoice\Data;

final readonly class ExtractedInvoiceData
{
    /**
     * @param  list<array{description: string, quantity: float, unitPriceMinor: int, totalMinor: int}>  $lineItems
     */
    public function __construct(
        public ?string $invoiceNumber,
        public ?string $claimedPayeeEmail,
        public ?string $currency,
        public ?int $amountMinor,
        public array $lineItems,
        public bool $hasInstructionLikeText,
        public float $confidence,
    ) {}
}
```

## Consequences

| Type | Consequence |
|---|---|
| Positive | No dependency; immutable inputs to the Guard; explicit construction from validated arrays |
| Negative | No automatic request-to-DTO casting or TypeScript generation; mapping code is written by hand |
| Negative | Validation rules and DTO shape must be kept in sync manually; tests cover the mapping |

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| spatie/laravel-data | New dependency; mutable by default; mixes validation with transport |
| Plain arrays | No types; array shapes alone cannot stop a wrong key reaching the Guard |
| Eloquent models as DTOs | Couples domain input to persistence and mass assignment |
