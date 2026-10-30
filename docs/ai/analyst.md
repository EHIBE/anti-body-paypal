---
title: Risk Analyst
purpose: Specify the AI analyst that judges line-item fit (S3) and writes the owner-facing explanation from typed fields only.
audience: [developers, ai-agents, judges]
status: planned
related:
  - ./extractor.md
  - ./injection-isolation.md
  - ./eval-set.md
  - ../architecture/guard-spec.md
source_of_truth: docs/prd-anti-body-paypal.md sections 8.5 (FR-13 to FR-15), 9.2 (S3), 9.5, 17.1, 17.4, 17.7
last_verified: 2026-10-30
---

# Risk Analyst

> **TL;DR**
> - **Status: planned.** It will live in the Guard slice (`app/Features/Guard/Ai/RiskAnalyst.php`).
> - Model `claude-sonnet-5-5` is confirmed ($2 / $10 per M tokens).
> - The analyst sees **typed fields only**: vendor stats, line items, the signal list with points, and phrase categories. It never sees invoice notes, terms or `suspicious_phrases` text.
> - It returns `line_item_assessment` (which maps to S3: +0, +8 or +15) and a 2 to 3 sentence `owner_explanation` (FR-15).
> - It never outputs a score or a decision. PHP sums the points and decides.

## 1. Model and settings

| Setting | Value |
|---|---|
| Model | `claude-sonnet-5-5` via `ANTIBODY_ANALYST_MODEL` (confirmed in Anthropic's model list) |
| Output | Structured output, schema in section 3 |
| Max tokens | 600 |
| Caching | `#[CacheInstructions]` |
| Timeout | 60 s |
| Scaffold | `php artisan make:agent RiskAnalyst --structured` (laravel/ai, planned, approval needed) |
| Caller | `EvaluateInvoiceAction` in the Guard slice, after deterministic signals are computed |

## 2. Input (typed only)

| Input | Shape | Source |
|---|---|---|
| Vendor typical items | `[{name, median_qty, median_unit_price_minor}]` | `vendor_stats.typical_items` |
| Vendor stats | `sample_size`, `median_amount_minor`, `mad_amount_minor`, `median_interval_days` | `vendor_stats` |
| This invoice's line items | `[{description, qty, unit_price_minor}]` | `invoices.line_items` |
| Signal list | `[{id, points, detail}]` from S1, S2, S4, S5, S6, S7 | PHP |
| Phrase categories | for example `["payment_detail_change", "authority_claim", "urgency"]` | categories only from `invoices.suspicious_phrases` |
| Hard-rule violations | `["H1", ...]` | PHP |

Line-item descriptions are short structured values from the extractor. Free-text notes and terms never reach the analyst (PRD 9.5 step 2).

## 3. Output schema (verbatim from PRD 17.4)

```json
{
  "line_item_assessment": "normal|unusual|implausible",
  "line_item_reason": "string max 200",
  "owner_explanation": "string max 400"
}
```

| Output | Use |
|---|---|
| `line_item_assessment` | PHP maps to S3 points: `normal` +0, `unusual` +8, `implausible` +15. Any other value fails validation |
| `line_item_reason` | Shown as the S3 detail |
| `owner_explanation` | Stored in `risk_assessments.owner_explanation`, shown on the decision card, in Telegram and via `get_decision`. Rendered as escaped text, never HTML (A05, LLM05) |

## 4. Instruction outline (PRD 17.4)

1. Input is typed fields only: vendor typical items, this invoice's items, signal list with points, phrase categories.
2. Judge only whether line items fit this vendor's history.
3. Explain the decision for a busy owner in 2 to 3 sentences. Name the top reasons.
4. You do not decide and you do not score. Do not suggest approving.

Example explanation for the BoxPro impersonation (PRD 11.1): "Payee email does not match BoxPro's verified address and uses a lookalike domain. The invoice contains urgency and authority language. Treat as impersonation."

## 5. Failure handling

| Failure | Behaviour |
|---|---|
| Invalid output | Retry once, then treat as unavailable |
| Timeout or 5xx | Scoring falls back to deterministic signals only (S3 contributes 0) and the decision is forced to HOLD (PRD 10.2). The explanation is replaced by a fixed PHP-generated line listing the reason codes |
| Output tries to change points beyond S3 bands | Impossible by design: PHP reads only the enum value |

## 6. Cost (PRD 17.7)

~1,500 input and ~300 output tokens at $2 / $10 per M = ~$0.006 per invoice. Combined with the extractor: ~$0.007 (PRD Haiku 5.5 prices) or ~$0.0115 (Haiku 4.5 fallback, OQ-12).
