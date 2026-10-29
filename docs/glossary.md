---
title: Glossary
purpose: Define every Anti-Body term, abbreviation and ID scheme used in the PRD, docs and code.
audience: [judges, owner, developers, ai-agents]
status: implemented
related:
  - README.md
  - prd-anti-body-paypal.md
  - architecture/guard-spec.md
  - architecture/vertical-slice-architecture.md
source_of_truth: docs/prd-anti-body-paypal.md Appendix C, extended with architecture terms from docs/architecture/.
last_verified: 2026-10-30
---

# Glossary

> **TL;DR**
> - Product terms (AUTO, HOLD, BLOCK, hard rule, signal) come from PRD sections 9 and Appendix C.
> - Architecture terms (slice, Action, Data object, model trait) come from [architecture/vertical-slice-architecture.md](architecture/vertical-slice-architecture.md).
> - ID schemes (FR-, H, S, US-, OQ-, C, M, R, A, B) are listed at the end and are reused verbatim in every doc.

## 1. Business and fraud terms

| Term | Meaning |
|---|---|
| AP | Accounts payable, the bills a business owes |
| BEC | Business email compromise, fraud by fake or hijacked business email |
| VEC | Vendor email compromise, BEC that pretends to be a supplier |
| Vendor | A supplier the owner pays. Stored in the vendor registry with a verified PayPal email |
| Vendor registry | The owner-maintained list of vendors and their verified PayPal emails (`vendors` table). Only source of a payee |
| Payee | The PayPal email that receives a payout. Always copied from the vendor registry, never from an invoice or agent |
| Claimed payee | The payee email written on an invoice (`invoices.claimed_payee_email`). Compared with the registry by H1 |
| Payee swap / payment-detail change | Attack where an invoice asks to pay a new email or account. Blocked by H1 |
| Ghost vendor | A fake vendor that was never registered. Cannot be paid because only registered vendors match |
| Lookalike domain | Domain one or two characters off a real vendor domain, for example `boxpro-ph.com` vs `boxpro.ph`. Detected by S1 |
| Homoglyph | A character that looks like another (Cyrillic `а` vs Latin `a`, `0` vs `o`). S1 normalizes homoglyphs before comparing |
| Invoice fraud | Paying a fake, duplicated or padded invoice |
| Money protected | Dashboard counter: sum of amounts of BLOCKed invoices (FR-44) |
| Owner | The single human user (persona Mara). Only actor that can approve, reject, override, edit vendors or policy |
| Kahon Goods | Fictional shop owned by the persona Mara Santos |
| BoxPro Supplies, PrintHub, TapeWorks, Filler Co, LensLab Photo | Fictional demo vendors (PRD 3.1, 16.2) |

## 2. Decision terms

| Term | Meaning |
|---|---|
| Proposal | An agent request to pay one invoice (`propose_payment`) |
| AUTO | Decision: pay without owner action. Requires no hard-rule failure, score 0 to 24 and amount at or below the auto ceiling |
| HOLD | Decision: payment waits for owner approval (score 25 to 59, kill switch on, or amount above the auto ceiling, or extraction failed) |
| BLOCK | Decision: payment refused |
| Hard block | BLOCK caused by any of H1 to H8 or H10. Never overridable (`risk_assessments.block_type = hard`) |
| Score block | BLOCK caused by score at least 60. Overridable only in the dashboard with typed confirmation (`block_type = score`) |
| Hard rule | A check in PHP code that no AI or agent can change (H1 to H10) |
| Signal | An additive risk indicator (S1 to S7) that contributes points to the score |
| Score | Integer 0 to 100, the PHP sum of signal points, capped at 100 |
| Monotonic score | Signals only add points. Nothing in invoice text, agent input or AI output can lower the score |
| Auto ceiling | `auto_ceiling` policy value, default and absolute maximum $500.00. No payment above it is auto-paid |
| Max per payment | `max_per_payment` policy value, default $1,500.00. Exceeding it fails H4 |
| Weekly budget | `weekly_budget` policy value, default $3,000.00, per ISO week (UTC, see OQ-14). Exceeding it fails H5 |
| Kill switch | Global pause (`policies.paused`). H9 forces HOLD on every new and pending proposal and stops payout calls |
| Override | Owner approval of a score-BLOCKed invoice in the dashboard with typed confirmation (FR-28) |
| Typed confirmation | Owner must type a confirmation phrase before a sensitive action (payee change, override, auto ceiling change) |
| Approved amount | `invoices.approved_amount_minor`, locked at approval time. The only amount a payout can use |
| HOLD expiry | 72 h by default, then auto-reject with Telegram notice |

## 3. PayPal and payment terms

| Term | Meaning |
|---|---|
| Payouts | PayPal API that sends money from a business balance to recipients by email (`POST /v1/payments/payouts`) |
| Payout batch | One Payouts request. Anti-Body sends one batch per invoice |
| Payout item | One recipient inside a batch. Anti-Body batches have exactly one item |
| `sender_batch_id` | Caller-chosen batch ID. Anti-Body uses `AB-{invoice_uuid}`. PayPal rejects reuse within 30 days |
| `PayPal-Request-Id` | Idempotency header. Anti-Body sets it to the same value as `sender_batch_id` |
| `payout_batch_id`, `payout_item_id` | PayPal-assigned IDs. Item IDs are masked in agent responses |
| Idempotency | Sending the same request twice has the effect of sending it once |
| Duplicate-ID adoption | When a retry returns a duplicate `sender_batch_id` error, the executor follows the returned link and adopts the original batch |
| Reconcile | Scheduled job (every 5 min) that asks PayPal for the status of payments stuck in `submitting` or `submitted` for more than 2 min |
| Webhook | HTTP callback from PayPal or Telegram to Anti-Body |
| Webhook signature verification | Posting received headers, webhook ID and raw body to `/v1/notifications/verify-webhook-signature`. Only `SUCCESS` is processed |
| Transaction Search | PayPal reporting API (`GET /v1/reporting/transactions`) used to seed vendor baselines |
| Sandbox | PayPal test environment at `https://api-m.sandbox.paypal.com`. The only environment Anti-Body uses |
| Unclaimed | Payout sent to an email without a PayPal account. Payment state `unclaimed` |
| PayPal held | PayPal held the payout item for review. Payment state `paypal_held` |
| Circuit breaker | After repeated PayPal 5xx or connect errors, payout submission pauses 10 min |

## 4. Baseline and statistics terms

| Term | Meaning |
|---|---|
| Baseline | Per-vendor history stats in `vendor_stats` (FR-38) |
| Median | Middle value of past amounts for a vendor |
| MAD | Median absolute deviation: median of absolute differences from the median |
| Modified z-score | `(amount - median) / (1.4826 x MAD)`. Used by S2 |
| Median interval | Median days between paid invoices for a vendor. Used by S5 |
| Typical items | Per-vendor list of `{name, median_qty, median_unit_price_minor}` used by the analyst for S3 |
| Minor units | Integer cents. All money columns end in `_minor` (US$12.34 is `1234`) |

## 5. AI terms

| Term | Meaning |
|---|---|
| MCP | Model Context Protocol, standard way for AI agents to call tools |
| MCP server | Anti-Body's agent interface (`AntiBodyServer`), web at `/mcp/anti-body`, local stdio server `anti-body` |
| Extractor | AI model (`claude-haiku-5-5`, UNVERIFIED, OQ-12) that turns an invoice PDF into schema-locked JSON |
| Analyst | AI model (`claude-sonnet-5-5`) that judges line items (S3) and writes the owner explanation from typed fields only |
| Built-in AP agent | Laravel AI SDK agent inside Anti-Body that processes pending invoices through the same Actions as MCP |
| External agent | Any MCP client, for example Claude Desktop |
| Prompt injection | Hidden text that tricks an AI into following instructions it should ignore (OWASP LLM01) |
| Excessive agency | Giving an agent more power than needed (OWASP LLM06) |
| Suspicious phrases | Extractor output `suspicious_phrases[]`: quoted text with a category (`urgency`, `authority_claim`, `payment_detail_change`, `ai_directed_instruction`, `other`) |
| `instruction_like_text` | Extractor boolean flag: the document contains instruction-like text |
| Injection isolation | Design where free-text invoice content never reaches the analyst or the decision (PRD 9.5) |
| Eval set | 17 labeled invoices used to measure extraction and decisions (PRD 17.6) |
| Prompt version | `risk_assessments.prompt_version`, records which prompt produced an assessment |

## 6. Architecture terms

| Term | Meaning |
|---|---|
| Vertical slice | A domain folder `app/Features/<Slice>/` that holds every layer the domain needs |
| Shared slice | `app/Features/Shared/`, cross-cutting code only (middleware, base classes, shared enums) |
| Action | Class named `VerbNounAction` with one public `handle()` method. Only home of business logic |
| Data object | Readonly class in a slice `Data/` folder used as a DTO, built from validated input |
| Model trait | Trait in `Models/Traits/{Forms,Tables,Infolists}/` that returns Filament schema arrays from static methods (`getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`) |
| Context flag | Typed boolean parameter on a model-trait method that changes the schema for a context, for example `bool $isOwnerView = false` |
| Slice service provider | `Providers/<Slice>ServiceProvider.php`, auto-registered by `AppServiceProvider` scanning `app/Features` |
| Slice routes | `Routes/web.php` and `Routes/api.php` inside a slice, loaded by the `then:` callback in `bootstrap/app.php` |
| Entry point | Controller, MCP tool, console command or Filament action. Parses, authorizes, delegates to one Action, responds |
| Audit hash chain | Append-only `audit_logs` where `hash = sha256(prev_hash . canonical_json(row))` |
| Policy (slice) | Guard policy settings: ceilings, budget, pause (`policies` table). Not the same as a Laravel authorization Policy class |
| Policy (Laravel) | Authorization class in a slice `Policies/` folder |
| Boost | Laravel Boost, the MCP server and guideline generator used by coding agents in this repo |
| `.ai/rules` | Committed, path-scoped coding rules recorded through the Boost `record-rule` tool. Index: [../.ai/rules/index.md](../.ai/rules/index.md) |

## ID schemes

| Prefix | Range | Meaning | Defined in |
|---|---|---|---|
| `FR-` | FR-01 to FR-45 | Functional requirements, priority M (Must), S (Should), R (Roadmap) | PRD 8 |
| `H` | H1 to H10 | Hard rules | PRD 9.1 |
| `S` | S1 to S7 | Risk signals | PRD 9.2 |
| `US-` | US-01 to US-11 | User stories (Given/When/Then) | PRD 7 |
| `OQ-` | OQ-01 to OQ-17 | Open questions. OQ-12 to OQ-15 and OQ-17 added by ABP-8; OQ-16 retired (resolved) | PRD 27, [product/roadmap.md](product/roadmap.md#open-questions) |
| `C` | C1 to C16 | Hackathon compliance requirements | PRD 5 |
| `M` | M1 to M7 | Measurable demo success criteria | PRD 4.4 |
| `G` | G1 to G3 | Hackathon goals | PRD 4.1 |
| Product goal `P` | P1 to P3 | Product goals | PRD 4.2 |
| Build block `B` | B1 to B9 | Build blocks (44 h) | PRD 21 |
| Polish block `P` | P1 to P3 | Polish blocks (12 h) | PRD 21 |
| `R` | R1 to R11 | Risks | PRD 25 |
| `A` | A1 to A5 | Assumptions to validate (market model also uses A1 to A3 for sizing assumptions, PRD 2.8) | PRD 2.12 |
| ADR | 0001 to 0006 | Architecture decision records | [decisions/](decisions/0001-vertical-slice-architecture.md) |
| `ABP-` | ABP-1 upward | Ticket IDs | [development/commit-and-branch-rules.md](development/commit-and-branch-rules.md) |
| Judging criteria abbreviations | TI, DE, IM, IN, PR | Technological Implementation, Design, Potential Impact, Innovation, Presentation | PRD 8 |
