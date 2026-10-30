---
name: money-and-time-handling
description: "Use whenever writing or reviewing Anti-Body code that stores, compares, sums, formats, or sends money amounts or currencies, or that computes dates, intervals, weeks, expiries, or schedules: `*_minor` columns, approved_amount_minor, weekly_budget (H5, S7), max_per_payment (H4), auto_ceiling, vendor median and MAD (S2), interval timing (S5), due date sanity (H8), HOLD expiry (72 h), Telegram token expiry (15 min), reconcile age, PayPal amount strings, Carbon, timezone, ISO week. Do not use for non-numeric UI copy."
---

# Money and time handling

Spec: [docs/architecture/data-model.md](../../../docs/architecture/data-model.md), [docs/architecture/guard-spec.md](../../../docs/architecture/guard-spec.md), [docs/development/coding-conventions.md](../../../docs/development/coding-conventions.md).

## Why this skill exists

Money and time errors are silent and expensive. PRD P1 requires the approved amount and currency exactly once. Several guard rules (H4, H5, H8, S2, S5, S7) and expiries depend on correct arithmetic and week boundaries. One skill keeps one rule set.

## Money rules

| Rule | Detail |
|---|---|
| Storage | Integer minor units in `*_minor` bigint columns (cents for USD). Never float or decimal columns for amounts. |
| Currency | ISO 4217 `char(3)`. Allowed list `["USD"]` (`policies.allowed_currencies`). Compare as uppercase strings. |
| Parsing | Extractor returns decimal major units. Convert once in PHP with string math (`bcmul` or integer split), reject more than 2 decimals for USD, reject negatives and zero (H8). |
| Arithmetic | Integer `+`, `-`, comparisons only. Medians and MAD computed on integers; z-score uses floats only for the ratio, never for stored amounts. |
| PayPal boundary | Format minor to a decimal string (`"680.00"`) only when building the payout body. |
| Display | Format minor to currency text in Filament through one reusable component. |
| Defaults | `auto_ceiling_minor` 50000, `max_per_payment_minor` 150000, `weekly_budget_minor` 300000. |

## Time rules

| Rule | Detail |
|---|---|
| Storage | UTC timestamps. App timezone UTC. |
| Week (H5, S7) | ISO week computed in UTC (`CarbonImmutable::now('UTC')->startOfWeek(CarbonInterface::MONDAY)`). Owner timezone PHT is OQ-14. |
| Expiries | HOLD 72 h (`policies.hold_expiry_hours`), Telegram token 15 min, PayPal token expiry minus 5 min, reconcile rows older than 2 min. |
| Tests | Freeze time with `$this->travelTo()`. Never depend on the wall clock. |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Name every money field `*Minor` in PHP and `*_minor` in the DB.
2. Convert at the edges only (extractor input, PayPal output, UI output).
3. Use `CarbonImmutable` and explicit UTC.
4. Test boundaries: exact ceiling, ceiling plus one cent, week start and end, expiry minus and plus one second.

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\Shared\Support;

use App\Features\Shared\Exceptions\InvalidMoneyAmountException;

final class MinorUnitConverter
{
    public function fromMajorString(string $amount): int
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidMoneyAmountException($amount);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function toMajorString(int $amountMinor): string
    {
        return intdiv($amountMinor, 100).'.'.str_pad((string) ($amountMinor % 100), 2, '0', STR_PAD_LEFT);
    }
}
```

## Checklist

- [ ] No `float` type on any amount property or column.
- [ ] No `round()`, `number_format()` on stored money outside display.
- [ ] Weekly sum includes `approved + submitting + submitted + paid`.
- [ ] All timestamps UTC; tests freeze time.

## Common mistakes

- `(int) ($amount * 100)` on floats (680.29 becomes 68028).
- Using local time for the weekly budget.
- Comparing currencies case-sensitively against lowercase input.

## Verification

```bash
php artisan test --compact --filter=Money
git grep -nE "float \$[a-zA-Z]*([Aa]mount|Minor)" -- app
```
