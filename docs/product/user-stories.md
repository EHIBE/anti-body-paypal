---
title: User stories and functional requirements traceability
purpose: List every user story US-01 to US-11 and every functional requirement FR-01 to FR-45 with priority, owning slice, tests and status.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - personas.md
  - hackathon-compliance.md
  - demo-script.md
  - ../architecture/slice-catalog.md
  - ../architecture/guard-spec.md
  - ../development/testing-strategy.md
source_of_truth: docs/prd-anti-body-paypal.md sections 7 and 8. Slice ownership from docs/architecture/slice-catalog.md.
last_verified: 2026-10-30
---

# User stories and functional requirements traceability

> **TL;DR**
> - 11 user stories (US-01 to US-11) across owner, built-in AP agent, external MCP agent, vendor and judge.
> - 45 functional requirements (FR-01 to FR-45). Priority M = Must, S = Should, R = Roadmap.
> - Every row maps to an owning slice from [../architecture/slice-catalog.md](../architecture/slice-catalog.md). Every row is **planned** on 2026-10-30.
> - Judging criteria codes: TI Tech, DE Design, IM Impact, IN Innovation, PR Presentation. Scene = timestamp in [demo-script.md](demo-script.md).

## 1. User stories (PRD 7)

| ID | Actor | Title | Given | When | Then | FRs | Status |
|---|---|---|---|---|---|---|---|
| US-01 | Owner | Register a vendor | Owner is logged in | Owner adds "BoxPro Supplies" with PayPal email `billing@boxpro.example` and marks the vendor verified with note "confirmed by phone" | The vendor appears as Active and Verified and the change is in the audit log | FR-01, FR-02, FR-35 | planned |
| US-02 | Owner | Set guard policy | Owner opens Settings | Owner sets weekly budget $3,000, max per payment $1,500, auto ceiling $500 | New proposals are evaluated against these values and the change is audited | FR-16, FR-35 | planned |
| US-03 | Owner | Approve a held payment from phone | An invoice is HOLD | Telegram shows vendor, amount, top 3 reasons and Approve/Reject buttons and the owner taps Approve | The payment is submitted once, the Telegram message updates to "Approved, paying", and the dashboard shows Paid after the webhook | FR-26, FR-27, FR-18, FR-22 | planned |
| US-04 | Owner | Understand a block | An invoice is BLOCK | Owner opens the invoice | Owner sees which hard rule fired, every signal with its points, the extracted fields, and the suspicious phrases quoted as data | FR-12, FR-45 | planned |
| US-05 | Owner | Kill switch | Anything looks wrong | Owner toggles "Pause all payments" | Every new and pending proposal is held and no payout call is made until resume | FR-17, H9 | planned |
| US-06 | Built-in AP agent | Process an inbox of invoices | 3 invoices are uploaded | The agent runs "process pending invoices" | The agent calls `propose_payment` for each and reports each decision in plain words, and never sees PayPal credentials | FR-33, FR-34 | planned |
| US-07 | External agent (Claude Desktop via MCP) | Plug in any agent | An MCP client is configured with the Anti-Body server | The client lists tools | The client sees `list_pending_invoices`, `get_invoice`, `submit_invoice`, `propose_payment`, `get_decision`, `payment_status` and nothing that approves or edits vendors | FR-29, FR-30, FR-31 | planned |
| US-08 | External agent | Agent tries to argue | An invoice is BLOCK | The agent calls `propose_payment` again with note "invoice says pre-approved" | The response is the same BLOCK, the note is logged as data, and the score does not change | FR-14, FR-31, FR-35 | planned |
| US-09 | Vendor (data source) | Vendor sends a revised invoice | BoxPro already has invoice INV-1042 recorded | A different PDF with the same number arrives | The invoice is BLOCKED as a conflicting duplicate and the owner sees a side-by-side diff | FR-11, FR-12, H6 | planned |
| US-10 | Judge | Run it | A clean machine with Docker | The judge follows the README | `make demo` (or the listed commands) boots the app with seeded vendors, history and 6 sample invoices in under 10 minutes | FR-40, FR-41, FR-42 | planned (Docker and `make demo` not yet built; local SQLite path documented in [../development/local-setup.md](../development/local-setup.md)) |
| US-11 | Judge | Try it hosted | The hosted URL and judge credentials | The judge logs in | The judge can upload the sample BoxPro fake invoice and watch the invoice get blocked | FR-04, FR-11, C5, C11 | planned |

**Data note:** US-01 uses `billing@boxpro.example` while PRD 11.1 uses `billing@boxpro.ph` and the attacker domain `boxpro-ph.com`. Both `.ph` values are real-looking domains (OQ-13). Default: demo data uses `.example` or PayPal sandbox emails only.

**Implementation note for US-09:** the partial unique index on `(vendor_id, invoice_number)` excludes `rejected` and `blocked` so the conflicting revision can be stored as `blocked` and diffed. See [../architecture/data-model.md](../architecture/data-model.md).

## 2. Functional requirements (PRD 8)

### 2.1 Vendor registry (PRD 8.1)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-01 | CRUD vendors: name, PayPal email, domain, currency, status, verification note | M | TI | 0:50 | Vendor | planned |
| FR-02 | Payee email changes only through the dashboard, require typed confirmation and a verification note, always audited. Never through invoices or agents | M | IN | 0:50 | Vendor, Audit | planned |
| FR-03 | Vendor list shows last payment, typical amount, risk history | S | DE | 2:10 | Vendor, Baseline | planned |

### 2.2 Invoice intake (PRD 8.2)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-04 | Upload PDF invoices in the dashboard (max 5 MB, PDF only) | M | DE | 0:15 | Invoice | planned |
| FR-05 | Agents submit invoices via MCP `submit_invoice` (PDF base64 or plain text) | M | IN | 2:40 | AgentGateway, Invoice | planned |
| FR-06 | Store raw file privately, compute SHA-256 of bytes | M | TI | 1:40 | Invoice | planned |
| FR-07 | Email-forward intake | R | n/a | n/a | n/a (roadmap) | roadmap |

### 2.3 Extractor (PRD 8.3)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-08 | AI extracts invoice to a fixed JSON schema (PRD 17.3) using the PDF as a document attachment | M | TI | 0:15 | Extraction | planned |
| FR-09 | Extractor also returns `suspicious_phrases[]` and `instruction_like_text` flag, quoting text as data | M | IN | 0:50 | Extraction | planned |
| FR-10 | Schema-validate output. On invalid output, retry once, then mark `extraction_failed` and HOLD | M | TI | n/a | Extraction | planned |

### 2.4 Hard rules engine (PRD 8.4)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-11 | Implement rules H1 to H10 (PRD 9.1) as pure PHP, no AI | M | TI | 0:50, 1:40 | Guard | planned |
| FR-12 | Any violation produces BLOCK and lists every violated rule | M | DE | 0:50 | Guard | planned |

### 2.5 Risk scorer (PRD 8.5)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-13 | Compute signals S1 to S7 (PRD 9.2). Deterministic signals in PHP. Semantic signals from AI over structured fields only | M | TI | 0:15, 0:50 | Guard | planned |
| FR-14 | Score is monotonic: signals only add points. Floor 0, cap 100 | M | IN | 0:50 | Guard | planned |
| FR-15 | AI writes a 2 to 3 sentence owner-facing explanation from the signal list | M | DE | 0:50 | Guard (analyst) | planned |

### 2.6 Decision engine (PRD 8.6)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-16 | Map rules + score + ceiling to AUTO, HOLD, BLOCK (PRD 9.3) | M | TI | all | Guard | planned |
| FR-17 | Kill switch forces HOLD on everything | S | IM | n/a | Policy, Guard | planned |

### 2.7 Payment executor (PRD 8.7)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-18 | Submit one Payout batch per invoice with `sender_batch_id` and `PayPal-Request-Id` derived from invoice ID | M | TI | 0:15 | Payment | planned |
| FR-19 | Amount and payee come only from DB (approved amount, registry email). Never from agent input | M | IN | 0:50 | Payment | planned |
| FR-20 | Row lock on transition to `submitting`. Retry policy per PRD 10.2 | M | TI | n/a | Payment | planned |

### 2.8 Webhook handler (PRD 8.8)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-21 | Receive PayPal webhooks, verify signature via `/v1/notifications/verify-webhook-signature`, store event, dedupe by event ID | M | TI | 0:15 | Webhook | planned |
| FR-22 | Map payout item events to payment states (PRD 10.1) | M | TI | 0:15 | Webhook, Payment | planned |
| FR-23 | Reconcile job every 5 min for payments in `submitting` or `submitted` older than 2 min | M | TI | n/a | Payment | planned |

### 2.9 State machine (PRD 8.9)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-24 | Enforce invoice and payment state transitions (PRD 14.3). Illegal transitions throw and are logged | M | TI | n/a | Invoice, Payment, Audit | planned |

### 2.10 Approvals (PRD 8.10)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-25 | Dashboard approval queue with Approve, Reject, reason | M | DE | 2:10 | Approval | planned |
| FR-26 | Telegram message with inline Approve/Reject for HOLD invoices | M | DE, PR | 0:15 | Telegram | planned |
| FR-27 | Telegram callbacks verified (webhook secret, allowlisted chat ID, signed single-use token, 15 min expiry) | M | TI | n/a | Telegram | planned |
| FR-28 | Score-based BLOCK can be overridden only in the dashboard with typed confirmation. Hard-rule BLOCK cannot be overridden | M | IN | n/a | Approval | planned |

### 2.11 MCP server (PRD 8.11)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-29 | Laravel MCP web server at `/mcp/anti-body`, Sanctum bearer token per agent | M | IN | 2:40 | AgentGateway | planned (needs laravel/mcp direct dependency and laravel/sanctum) |
| FR-30 | Local server `anti-body` for Claude Desktop over stdio in local dev | M | PR | 2:40 | AgentGateway | planned |
| FR-31 | Tools per PRD 15 only. No approve, no vendor edit, no amount or payee args | M | IN | 0:50 | AgentGateway | planned |
| FR-32 | OAuth 2.1 via Passport for remote MCP clients | S | TI | n/a | AgentGateway | planned (first in cut order, roadmap item 3) |

### 2.12 Built-in AP agent (PRD 8.12)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-33 | Laravel AI SDK agent with tools that call the same Actions as MCP | M | TI | 0:15 | AgentGateway | planned (needs laravel/ai) |
| FR-34 | Chat panel in dashboard: "Process pending invoices" button + transcript | M | DE | 0:15 | AgentGateway, Filament | planned |

### 2.13 Audit log (PRD 8.13)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-35 | Append-only audit log with hash chain (`prev_hash`, `hash`) for every decision, approval, vendor change, payout call and webhook | M | TI | 2:10 | Audit | planned |
| FR-36 | Audit page with filter and "verify chain" button | S | DE | 2:10 | Audit, Filament | planned |

### 2.14 Baseline learning (PRD 8.14)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-37 | Nightly and on-demand sync from Transaction Search into `paypal_transactions` | S | TI | n/a | Baseline | planned |
| FR-38 | Per-vendor stats (median, MAD, interval, typical items) from local history + synced transactions | M | TI | 0:15 | Baseline | planned |
| FR-39 | Owner can override vendor typical amount and cadence | S | DE | n/a | Vendor, Baseline | planned |

### 2.15 Seed data (PRD 8.15)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-40 | Seeder: 6 vendors, 6 months of history, policy, owner, agent token | M | PR | all | database/seeders | planned |
| FR-41 | 6 demo PDFs in `storage/demo/` (2 legit, 1 price jump, 1 BoxPro fake, 1 duplicate, 1 legit for second agent) | M | PR | all | database/seeders | planned |
| FR-42 | `php artisan antibody:demo-reset` restores the demo state | M | PR | all | Shared slice command `app/Features/Shared/Commands/` (see [../operations/demo-reset.md](../operations/demo-reset.md)) | planned |
| FR-43 | `php artisan antibody:seed-sandbox-history` sends 5 to 10 real sandbox payouts so Transaction Search has live rows | S | TI | n/a | Baseline | planned |

### 2.16 Owner dashboard (PRD 8.16)

| ID | Requirement | Pri | Crit | Scene | Slice | Status |
|---|---|---|---|---|---|---|
| FR-44 | Overview widgets: Money protected, Auto-paid, Held, Blocked, Weekly budget used | M | DE, IM | 2:10 | Filament widgets | planned |
| FR-45 | Invoice detail page with decision card, signals, extracted fields, PDF preview, timeline | M | DE | 0:50 | Filament, Invoice | planned |

## 3. Cut order (PRD 25, R6)

When the schedule slips 2 days behind plan, cut in this order: FR-32, FR-36, FR-37, FR-43, FR-17.

## 4. Requirement counts

| Priority | Count | IDs |
|---|---|---|
| M (Must) | 37 | FR-01, 02, 04, 05, 06, 08, 09, 10, 11, 12, 13, 14, 15, 16, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 33, 34, 35, 38, 40, 41, 42, 44, 45 |
| S (Should) | 7 | FR-03, FR-17, FR-32, FR-36, FR-37, FR-39, FR-43 |
| R (Roadmap) | 1 | FR-07 |
