---
title: Demo Reset and Judge Commands
purpose: Specify the planned artisan commands that restore, seed and operate the hackathon demo state.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - ../product/demo-script.md
  - ../ai/eval-set.md
  - runbook.md
  - ../development/local-setup.md
source_of_truth: docs/prd-anti-body-paypal.md FR-40 to FR-43 and section 24.5
last_verified: 2026-10-30
---

# Demo Reset and Judge Commands

> **TL;DR**
> - `php artisan antibody:demo-reset` (FR-42) wipes invoices, payments, approvals and audit, keeps vendors, history and policy, and reloads the 6 demo PDFs from `storage/demo/`.
> - `php artisan antibody:agent-token "Judge agent"` prints a fresh MCP token once. `php artisan antibody:audit-verify` checks the hash chain.
> - `php artisan antibody:seed-sandbox-history` (FR-43, Should) sends 5 to 10 real sandbox payouts so Transaction Search has live rows.
> - Status: planned (B9). None of these commands exist on 2026-10-30.
> - The owning slice of `antibody:demo-reset` is an open decision (OQ-18): the command touches several slices and Shared may not depend on any slice under the ABP-9 `SliceIsolationTest`. See [../architecture/slice-catalog.md](../architecture/slice-catalog.md#2-summary).

## 1. Commands

| Command | FR | Slice (planned) | Effect | Idempotent |
|---|---|---|---|---|
| `php artisan antibody:demo-reset` | FR-42 | Owner open (OQ-18, decide in B9) | Restore demo state (section 2) | Yes |
| `php artisan antibody:agent-token "<name>"` | FR-29, C11 | AgentGateway | Create or reuse an `agents` row, issue a Sanctum token, print it once, store only the hash | No (new token each run) |
| `php artisan antibody:audit-verify` | FR-36 | Audit | Recompute every `hash` from `prev_hash` + canonical JSON, report first broken row id | Yes (read-only) |
| `php artisan antibody:seed-sandbox-history` | FR-43 | Baseline | Send 5 to 10 small real sandbox payouts to vendor sandbox emails | No (sends money in sandbox) |
| `php artisan db:seed` | FR-40 | Seeders | Owner, 6 vendors, 6 months of history, policy, agent token | Yes when run on an empty DB |

## 2. What `antibody:demo-reset` keeps and wipes

| Data | Table | Action |
|---|---|---|
| Owner user | `users` | Keep |
| Vendors (6) | `vendors` | Keep |
| Vendor stats | `vendor_stats` | Keep (recomputed from seeded history) |
| Seeded history (6 months) | `invoices` with `source=seed`, `payments` for them | Keep |
| Synced PayPal history | `paypal_transactions` | Keep |
| Policy row | `policies` | Keep, but reset `paused=false` |
| Agents | `agents`, `personal_access_tokens` | Keep |
| Demo invoices | `invoices` where `source` in (`dashboard_upload`, `mcp_submit`) | Wipe |
| Risk assessments | `risk_assessments` for wiped invoices | Wipe |
| Approvals | `approvals` | Wipe |
| Payments | `payments` for wiped invoices | Wipe |
| Webhook events | `webhook_events` | Wipe |
| Audit log | `audit_logs` | Wipe (the only sanctioned audit deletion, PRD 24.5; a new genesis entry `audit.chain_reset` starts the chain) |
| Stored PDFs | private disk uploads for wiped invoices | Delete |
| Demo PDFs | `storage/demo/*.pdf` | Reloaded as ready-to-upload files |

Guard: the command refuses to run when `PAYPAL_MODE` is not `sandbox`.

## 3. Demo PDFs (`storage/demo/`, FR-41)

| # | File (planned name) | Scenario | Expected decision |
|---|---|---|---|
| 1 | `01-printhub-legit.pdf` | Legit recurring invoice | AUTO |
| 2 | `02-tapeworks-legit.pdf` | Legit recurring invoice | AUTO |
| 3 | `03-printhub-price-jump.pdf` | 45% above median | HOLD (approve in Telegram) |
| 4 | `04-boxpro-impersonation.pdf` | Lookalike payee + injection (PRD 11.1, US$680) | BLOCK hard H1 |
| 5 | `05-duplicate.pdf` | Copy of an earlier invoice | BLOCK H7 (or H6) |
| 6 | `06-fillerco-legit-second-agent.pdf` | Legit, for Claude Desktop scene | AUTO |

All PDFs use fictional brands and sandbox or `.example` emails (OQ-13). Exact filenames are decided in B9.

## 4. Judge flow

| Step | Action |
|---|---|
| 1 | Log in at `/admin` with the judge credentials from README "Judge access" |
| 2 | Upload `04-boxpro-impersonation.pdf` | 
| 3 | See red BLOCK card with H1, S1, S4 (US-11) |
| 4 | Optional: `antibody:agent-token` and connect an MCP client |
| 5 | Owner runs `antibody:demo-reset` after judging sessions |
