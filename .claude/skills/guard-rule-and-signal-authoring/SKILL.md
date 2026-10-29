---
name: guard-rule-and-signal-authoring
description: "Use whenever adding, changing, testing, or reviewing an Anti-Body hard rule (H1 to H10), risk signal (S1 to S7), score sum, decision threshold, policy default, override rule, or kill switch in the Guard slice. Triggers: EvaluateInvoiceAction, DecideOutcomeAction, HardRule, Signal, score, monotonic, AUTO HOLD BLOCK, block_type, auto_ceiling, Levenshtein lookalike, modified z-score, MAD, risk_assessments. Keeps one authoritative source per rule and a monotonic, additive, capped score. Do not use for extractor prompt wording alone (use prompt-injection-defense)."
paths:
  - "app/Features/Guard/**"
  - "app/Features/Policy/**"
---

# Guard rule and signal authoring

Spec: [docs/architecture/guard-spec.md](../../../docs/architecture/guard-spec.md). PRD 9.

## Why this skill exists

The guard is the product's core claim: deterministic rules no AI can override and a score that can only go up. Rules and signals get added and tuned often. This skill keeps every change in one place (DRY), deterministic, and monotonic.

## Planned layout

| Unit | Path |
|---|---|
| Rule contract | `app/Features/Guard/Models/Interfaces/HardRule.php` (or slice `Contracts`) |
| Rules | `app/Features/Guard/Rules/PayeeMatchRule.php` ... one class per H1 to H10 (folder name confirmed in the slice plan) |
| Signal contract | `app/Features/Guard/Models/Interfaces/RiskSignal.php` |
| Signals | one class per S1 to S7 |
| Evaluation | `app/Features/Guard/Actions/EvaluateInvoiceAction.php` |
| Decision | `app/Features/Guard/Actions/DecideOutcomeAction.php` |
| Enums | `GuardDecision` (`Auto`, `Hold`, `Block`), `BlockType` (`Hard`, `Score`), `HardRuleCode`, `SignalCode` |

## Decision order (PRD 9.3)

| Condition | Decision |
|---|---|
| Any of H1 to H8, H10 failed | BLOCK hard |
| H9 kill switch on | HOLD |
| Score at least 60 | BLOCK score |
| Score 25 to 59 | HOLD |
| Amount above auto ceiling | HOLD at least |
| Score 0 to 24 and amount at or below auto ceiling | AUTO |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Give the rule or signal an ID that matches the PRD. A new ID (H11, S8) needs an owner-approved PRD change first.
2. Implement as a pure class: typed `Data/` input, typed result (`passed`, `code`, `detail` or `points`, `detail`). No DB writes, no HTTP, no model calls (S3 reads the analyst result passed in).
3. Points are non-negative constants. Never subtract.
4. Register the class in the Guard slice provider (tagged or ordered list).
5. `EvaluateInvoiceAction` collects all violations (FR-12 lists every violated rule), sums points, caps at 100, floors at 0, calls `DecideOutcomeAction`, writes `risk_assessments` and audits.
6. Write tests: pass, fail, each boundary, monotonicity, decision row.

## Illustrative skeleton

```php
<?php

declare(strict_types=1);

namespace App\Features\Guard\Actions;

use App\Features\Guard\Data\SignalResultData;

final class SumRiskScoreAction
{
    private const int MaximumScore = 100;

    /**
     * @param  list<SignalResultData>  $signalResults
     */
    public function handle(array $signalResults): int
    {
        $totalPoints = array_sum(array_map(fn (SignalResultData $result): int => max(0, $result->points), $signalResults));

        return min(self::MaximumScore, $totalPoints);
    }
}
```

## Checklist

- [ ] Rule or signal defined once, in Guard only. Thresholds read from `Policy` or Guard constants, never duplicated in UI or MCP code.
- [ ] H1 compares trimmed, case-insensitive emails against Active vendors only.
- [ ] H6 respects the partial unique index excluding `rejected` and `blocked`; constraint violation maps to H6 BLOCK.
- [ ] S2 new vendor gives +0 (S5 covers first payment).
- [ ] S7 fires only between 80 and 100 percent of the weekly budget.
- [ ] LLM outage: deterministic signals only and forced HOLD.
- [ ] Monotonicity test: for any signal set, adding a signal never lowers the score.

## Common mistakes

- Letting a "trusted vendor" signal subtract points.
- Short-circuiting after the first violated rule (owner must see every violation).
- Reading thresholds from the agent request.

## Verification

```bash
php artisan test --compact --filter=Guard
```
Eval set: [docs/ai/eval-set.md](../../../docs/ai/eval-set.md).
