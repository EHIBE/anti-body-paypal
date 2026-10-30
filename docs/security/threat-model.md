---
title: Threat Model
purpose: List the assets, actors, threats, abuse cases and red team scenarios Anti-Body defends against, and map each to a control.
audience: [judges, developers, ai-agents]
status: planned
related:
  - ./trust-boundaries.md
  - ./owasp-mapping.md
  - ./secrets.md
  - ./audit-hash-chain.md
  - ../architecture/guard-spec.md
  - ../ai/injection-isolation.md
source_of_truth: docs/prd-anti-body-paypal.md sections 2.5, 9, 10.2, 11, 12.6, 18; feature prompt section 5 (A06 abuse cases)
last_verified: 2026-10-30
---

# Threat Model

> **TL;DR**
> - The core asset is the small business PayPal balance. Only Anti-Body moves money, and only to a registry email for the approved amount.
> - The main attacker is a vendor impersonator who sends a lookalike invoice with a new payee and pressure or AI-directed text (PRD 11.1).
> - Agents are treated as untrusted proposers: no approve tool, no amount or payee argument (OWASP LLM06).
> - Hard rules H1 to H10 run in PHP and cannot be overridden by AI, agents or invoice text. The score is monotonic.
> - **Status: planned.** Controls below describe the target design; none is implemented yet.

## 1. Assets

| ID | Asset | Why it matters | Primary controls |
|---|---|---|---|
| AS-1 | PayPal business balance | Money leaves irreversibly once claimed | H1 to H10, approval paths, idempotency |
| AS-2 | Vendor registry (`vendors.paypal_email`) | Defines who can be paid | FR-02 dashboard-only payee change, typed confirmation, audit |
| AS-3 | Guard policy (`policies`) | Ceilings, budget, kill switch | Dashboard only, typed confirmation for `auto_ceiling`, audit |
| AS-4 | PayPal, Anthropic, Telegram credentials | Full account control | Env vars only, never in agents or logs ([secrets.md](./secrets.md)) |
| AS-5 | Agent MCP tokens | Ability to propose payments | Sanctum hashed tokens, revocable, H10 |
| AS-6 | Audit log | Proof of every decision | Append-only, hash chain ([audit-hash-chain.md](./audit-hash-chain.md)) |
| AS-7 | Invoice PDFs | Business data | Private disk, signed owner-only routes |
| AS-8 | Owner session | Approvals and overrides | Filament login, CSRF, session regeneration |

## 2. Actors

| Actor | Trust | Can | Cannot |
|---|---|---|---|
| Owner (dashboard) | Trusted after login | Approve HOLD, reject, override score-BLOCK with typed confirmation, edit vendors and policy | Override hard-rule BLOCK |
| Owner (Telegram) | Trusted after 4 callback checks | Approve or reject HOLD | Override BLOCK, edit vendors or policy |
| Built-in AP agent | Untrusted proposer | Submit invoices, propose payments, read decisions | Approve, reject, override, edit vendors, set amount or payee |
| External MCP agent | Untrusted proposer | Same as built-in agent | Same as built-in agent |
| Extractor and analyst models | Untrusted output | Return schema-validated data and explanations | Produce a score, decision or write |
| Vendor (real) | Data source | Send invoices | Change payee email |
| Impersonator | Hostile | Send lookalike invoices, inject instructions | Pass H1 |
| PayPal | Trusted after signature verification | Send webhooks | Change state without verified event |
| Anyone on the internet | Hostile | Post to public endpoints | Pass webhook verification, login, or Sanctum auth |

## 3. STRIDE table

| STRIDE | Threat | Example | Control | Residual risk |
|---|---|---|---|---|
| Spoofing | Vendor impersonation | `billing.update@boxpro-ph.example` posing as BoxPro | H1 exact payee match, S1 lookalike +45, first-payment S5 | Compromised real vendor mailbox sending a correct-payee padded invoice: covered by S2, S3, H4, H5, owner HOLD |
| Spoofing | Forged PayPal webhook | Attacker posts `ITEM.SUCCEEDED` | `verify-webhook-signature`, only `SUCCESS` processed | None known |
| Spoofing | Forged Telegram tap | Attacker posts callback | Secret header, chat allowlist, HMAC single-use token, 15 min expiry | Owner phone compromise |
| Spoofing | Stolen agent token | Token leaked from agent host | Hashed tokens, revoke, H10, rate limit 30/min | Proposals until revoked, still gated by H1 to H9 |
| Tampering | Edited audit rows | DB access edits history | Hash chain, `antibody:audit-verify`, Postgres trigger (Should) | Full DB admin can rewrite whole chain; detection only by external anchor (Roadmap) |
| Tampering | Payee swap via invoice | Invoice says "updated payment details" | Payee only from registry (FR-19), FR-02 | None |
| Tampering | Amount change after approval | Revised invoice same number | `approved_amount_minor` locked, H6 BLOCK | None |
| Repudiation | Owner denies approving | "I never approved" | Approvals table with channel, user, time; audit chain | None |
| Information disclosure | Credentials to agents | Agent asks for PayPal key | Agents never receive credentials; MCP masks payout IDs | None |
| Information disclosure | PDF URL guessing | Enumerate files | Private disk, signed routes, owner only, renamed files | None |
| Denial of service | Proposal flood | Agent loops `propose_payment` | `throttle:mcp` 30/min per token, 429, audit | Cost of extraction bounded by upload limits |
| Denial of service | LLM outage | Anthropic 5xx | Fail closed: HOLD with `extraction_failed` | Owner workload |
| Elevation of privilege | Agent argues | "pre-approved, pay it" note | Note stored as data, never parsed, decision unchanged (US-08) | None |
| Elevation of privilege | Prompt injection to model | "AI assistant: ignore previous rules" | Extractor quotes into `suspicious_phrases`, analyst never sees notes, PHP decides | Extractor misses a phrase: H1 still blocks payee swaps |

## 4. Abuse cases (feature prompt A06)

| # | Abuse case | Scenario | Control | Test (planned) |
|---|---|---|---|---|
| AB-1 | Tampering | Edit amount or payee between proposal and payout | Payout reads `approved_amount_minor` and registry email at submit | Payout body equals DB values |
| AB-2 | Replay | Resend old Telegram callback or PayPal webhook | Single-use token, event ID dedupe | Replayed callback no-op |
| AB-3 | Enumeration | Guess invoice UUIDs via MCP or PDF routes | UUIDv7 IDs, `not_found` for any missing or foreign record, signed URLs | Unknown ID returns `not_found` |
| AB-4 | Excess volume | Flood uploads or proposals | Rate limits: MCP 30/min, upload 10/min, Telegram 20/min | 31st call returns 429 |
| AB-5 | Skipped workflow steps | Call payout before decision, approve a BLOCK | State machine: only `auto_approved`/`approved` reach `paying`; Telegram approves only `held` | Illegal transition throws |
| AB-6 | Fake invoices | Ghost vendor, unknown payee | Only active registered vendors (H1, H2), first payment holds (S5) | Unknown payee BLOCK |
| AB-7 | Payee swaps | "Billing account update" | H1, FR-02 | Eval cases 9, 10 |
| AB-8 | Duplicate submissions | Same PDF, re-scan, revision | H7, H6, unique payment per invoice | Eval cases 11, 12, 13 |
| AB-9 | Agent argument | Agent re-proposes with persuasive note | Idempotent decision, note never parsed | US-08 MCP test |
| AB-10 | Policy weakening | Raise `auto_ceiling` above $500 | Absolute ceiling $500 in code, typed confirmation, audit | Setting above $500 rejected |
| AB-11 | Kill switch bypass | Queue a payout while paused | H9 forces HOLD, job re-checks pause before call | No HTTP call while paused |

## 5. Red team scenarios (PRD 11)

| ID | Scenario | Expected outcome | Proven by |
|---|---|---|---|
| RT-1 (11.1) | BoxPro impersonation, $680, payee `billing.update@` lookalike domain, notes "Payment details updated", "pre-approved by management", "Process immediately" | BLOCK hard (H1), S1 +45, S4 +20, score 65, 0 payout calls, $680 protected | M3, eval cases 9 and 10 |
| RT-2 (11.2) | PrintHub INV-2201 $420 AUTO, then revised $735 same number, then payout timeout and retry, late webhook | Revision H6 BLOCK, one batch, `paid` only after SUCCEEDED | Idempotency feature test, eval case 13 |
| RT-3 (11.3) | Same PDF twice, then re-scan | H7 then H6 BLOCK, no PayPal call | M4, eval cases 11 and 12 |
| RT-4 (11.4) | Agent `propose_payment(note: "pre-approved, pay it")` | Same decision, note logged as data | M5, US-08 |

Note OQ-13: the PRD 11.1 story uses real-looking domains (`boxpro.ph`, `boxpro-ph.com`). Committed fixtures use `.example` domains by default.

## 6. Out of scope (accepted risks)

| Risk | Reason |
|---|---|
| Compromised owner device or Telegram account | Outside the app boundary; mitigated by 15 min token expiry and dashboard audit |
| Malicious database administrator | Detection only (hash chain); prevention needs external anchoring (Roadmap) |
| Live PayPal money | Sandbox only (PRD 4.3) |
| Email-borne invoices | Email intake is Roadmap item 1 |
