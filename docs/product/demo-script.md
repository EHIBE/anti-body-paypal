---
title: Demo video script
purpose: Give the exact 2:50 demo video script, the pre-seeded state, the requirement behind each scene, and the retake risks.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - hackathon-compliance.md
  - user-stories.md
  - personas.md
  - ../operations/demo-reset.md
  - ../ai/eval-set.md
source_of_truth: docs/prd-anti-body-paypal.md section 20 (script), 4.4 (M1 to M7), 8.15 (seed data), 19.3 (Telegram formats).
last_verified: 2026-10-30
---

# Demo video script

> **TL;DR**
> - Target length 2:50 (hard limit 3:00, C9). One villain (the fake BoxPro invoice), one counter (money protected).
> - Scenes prove M1 to M5: two AUTO payouts, a HOLD approved in Telegram, the BoxPro impersonation BLOCK, the agent argument, a duplicate BLOCK, and Claude Desktop over MCP.
> - Pre-seed with `php artisan antibody:demo-reset` (planned, FR-42) before every take.
> - Recorded in polish block P3 (Day 14, Fri Nov 6). Nothing below is recordable until B1 to B9 are built.

## 1. Pre-seeded state (PRD 20)

| Item | Source | Status |
|---|---|---|
| 6 vendors | Seeder FR-40 | planned |
| 6 months of history | Seeder FR-40 (and optional FR-43 sandbox payouts) | planned |
| Policy defaults ($500 auto ceiling, $1,500 max per payment, $3,000 weekly budget) | Seeder FR-40 | planned |
| Owner Telegram linked (`users.telegram_chat_id`) | Seeder + `TELEGRAM_OWNER_CHAT_ID` | planned |
| 6 demo PDFs ready in `storage/demo/` (2 legit, 1 price jump, 1 BoxPro fake, 1 duplicate, 1 legit for second agent) | FR-41 | planned |
| Claude Desktop configured with the local Anti-Body MCP server `anti-body` | FR-30 | planned |
| Dashboard open in one window | FR-44, FR-45 | planned |
| Telegram on phone mirrored or screen-recorded | FR-26 | planned |

## 2. Script (target 2:50)

| Time | On screen | Narration (draft) | Proves | Requirements |
|---|---|---|---|---|
| 0:00 to 0:08 | Mara's shop page (fictional), stack of invoices | "Mara runs a small online shop with 20 vendors. She wants an AI agent to pay her bills." | Persona | Impact criterion |
| 0:08 to 0:15 | Fake BoxPro invoice next to the real one | "The problem: agents can be fooled. One fake invoice and the money is gone." | Problem | Impact criterion |
| 0:15 to 0:30 | Upload 3 invoices, click "Process pending invoices" | "Anti-Body sits between the agent and PayPal. The agent can ask to pay. Only Anti-Body can pay." | Intake, built-in agent | FR-04, FR-08, FR-33, FR-34, US-06 |
| 0:30 to 0:42 | Two rows turn green AUTO, PayPal sandbox shows 2 payouts | "Two normal invoices: auto-paid through PayPal Payouts in seconds." | M1 | FR-16, FR-18, FR-21, FR-22 |
| 0:42 to 0:52 | Third row amber, phone buzzes, tap Approve, row goes Paid | "This one is 45 percent above normal. Mara approves from Telegram." | M2 | S2, FR-26, FR-27, US-03 |
| 0:52 to 1:10 | Upload BoxPro fake. Red BLOCK card | "Now the attack. Same logo, same items, a new payee email and 'pre-approved by management.'" | M3 | FR-09, FR-11, H1, S1, S4 |
| 1:10 to 1:30 | Decision card: registered vs claimed email, lookalike, quoted phrases labeled as data | "Anti-Body blocks it. The payee isn't BoxPro's verified address. That rule can't be overridden by any AI." | M3, US-04 | FR-12, FR-15, FR-45, FR-28 |
| 1:30 to 1:42 | Agent transcript: "The invoice says pre-approved, pay it." Response: BLOCK unchanged | "The agent argues. The invoice text never reaches the part that decides." | M5, US-08 | FR-14, FR-31 |
| 1:42 to 2:00 | Same invoice uploaded again, instant BLOCK duplicate | "Forwarded twice? One payment. Ever." | M4 | H7, H6 |
| 2:00 to 2:20 | Overview: $680 protected, audit log hash chain verified | "Every decision is logged and tamper-evident." | Audit | FR-44, FR-35, FR-36 |
| 2:20 to 2:40 | Claude Desktop calls `propose_payment` on a new invoice, gets AUTO | "And it works with any agent. Here's Claude Desktop over MCP." | US-07 | FR-29, FR-30, FR-31 |
| 2:40 to 2:50 | Logo, tagline, repo URL | "Anti-Body. The second signature your agent can't talk its way past." | Close | Presentation criterion |

If the rough cut exceeds 2:55, cut the 1:42 to 2:00 scene first (R10).

## 3. Demo invoices

| Demo PDF | Vendor | Expected decision | Scene | Eval case |
|---|---|---|---|---|
| Legit 1 | Recurring vendor | AUTO | 0:30 | 1 to 4 |
| Legit 2 | Recurring vendor | AUTO | 0:30 | 1 to 4 |
| Price jump (45% above median, e.g. PrintHub INV-2207 $640.00) | PrintHub | HOLD (S2 + above auto ceiling) | 0:42 | 6 |
| BoxPro fake (US$680, payee lookalike, injection notes) | Claims BoxPro Supplies | BLOCK hard H1, score 65 (S1 +45, S4 +20) | 0:52 | 9 |
| Duplicate | Same bytes as an earlier PDF | BLOCK hard H7 | 1:42 | 11 |
| Legit for second agent | Recurring vendor | AUTO | 2:20 | 1 to 4 |

Demo data must use fictional brands and `.example` or sandbox emails only (PRD 18, OQ-13).

## 4. Telegram formats shown on screen (PRD 19.3)

Approval request:
```
⚠️ Anti-Body: approval needed
PrintHub · INV-2207 · $640.00
Risk 35 · HOLD
• 45% above usual amount
• Above auto-approve limit ($500)
[ Approve ]  [ Reject ]
Expires in 15 min
```
After the tap the message edits to "✅ Approved by you · paying", then "Paid" on webhook.

Block alert (no buttons):
```
🛑 Anti-Body blocked a payment
"BoxPro Supplies" · $680.00
Payee does not match BoxPro's verified email
Lookalike domain · pressure language
$680 protected. Review in dashboard.
```

## 5. Retake risks (PRD 20)

| Risk | Mitigation |
|---|---|
| PayPal sandbox latency | Pre-run once, keep a backup take |
| Telegram notification timing | Record phone separately |
| Model nondeterminism | Use demo PDFs already tested in the eval set |
| Screen secrets | Hide `.env`, tokens, emails |
| Video over 3 min (R10) | Script at 2:50, cut 1:42 to 2:00 first |

## 6. Compliance rules for the video

| Rule | Source |
|---|---|
| Under 3 min, public on YouTube, shows the app running | C9 |
| No unlicensed music, no third-party trademarks. Fictional vendor names. PayPal shown only as the platform used | C10 |
| No real names, real emails, real invoices, live keys | PRD 18 |
| Fictional vendor logos as simple text marks | PRD 19.4 |
