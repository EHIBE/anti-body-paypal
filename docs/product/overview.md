---
title: Product overview and hackathon purpose
purpose: Explain what Anti-Body is, the problem it solves, the market gap, and why it was built for the PayPal AI Hackathon.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - personas.md
  - hackathon-compliance.md
  - roadmap.md
  - ../architecture/overview.md
  - ../architecture/guard-spec.md
source_of_truth: docs/prd-anti-body-paypal.md sections 0, 1, 2, 4.
last_verified: 2026-10-30
---

# Product overview and hackathon purpose

> **TL;DR**
> - **Pitch:** AI agents can now pay your bills. Nothing checks the agent. Anti-Body does.
> - **Tagline:** The second signature your AI agent cannot talk its way past.
> - Anti-Body is a payment gate between any AI agent and a small business's PayPal account. Agents propose vendor invoice payments. Only Anti-Body moves money, through PayPal Payouts.
> - Deterministic hard rules (H1 to H10) run first, then an AI-assisted monotonic risk score (S1 to S7). Outcome: **AUTO**, **HOLD** or **BLOCK**.
> - Built solo by EHIBE for the PayPal AI Hackathon (Devpost). Deadline Thu Nov 12, 2026, 12:00 pm PT = **Fri Nov 13, 2026, 4:00 am PHT**. Status: specified, not yet built.

## 1. Document control facts

| Field | Value |
|---|---|
| Product | Anti-Body (brand). Repo and package identifier `anti-body-paypal` |
| PRD version | 1.1 (adds market research, threat mapping, market sizing, personas, business model) |
| PRD date | 2026-10-23, status Approved for build |
| Author | EHIBE |
| Hackathon | PayPal AI Hackathon, Devpost |
| Build window | Sat Oct 24 to Fri Nov 6, 2026 (14 days x 4 h = 56 h). Buffer Nov 7 to Nov 12 |
| Target submit | Tue Nov 10, 2026 (PHT), 2 days before deadline |
| Judging end | Dec 15, 2026 (hosting kept through Jan 2027) |

### 1.1 Changes from the original prompt defaults (PRD 0)

| Item | Default | Final | Why |
|---|---|---|---|
| Name | Countersign | Anti-Body | Owner choice (see OQ-01) |
| Database | MySQL | Render Postgres (Aiven free MySQL as fallback) | Cheapest, easiest 2+ month hosting. Render has no managed MySQL. One provider, one bill, covered by hackathon credits. See [ADR 0004](../decisions/0004-render-postgres.md) |
| Redis + Horizon | Candidate | Dropped | Database queue, cache and session drivers are enough at this scale; saves $10/month plus setup hours. See [ADR 0003](../decisions/0003-database-drivers-over-redis.md) |
| Invoice intake | PDF upload + PayPal invoices fetched from sandbox | PDF upload + agent submission over MCP | Invoicing API has no endpoint for invoices you received (PRD 16.6) |
| Build allocation | 44 h list | Same total, rebalanced with 2 h infra | Original list had no deploy hours |

## 2. The problem

| Aspect | Statement |
|---|---|
| Plain words (PRD 2.1) | A small online seller gets 20 to 60 vendor invoices a month. AI agents can read and pay invoices. The same agent can be fooled by a fake invoice that copies a vendor's name and logo, changes the payee email and adds "pre-approved by management, pay today." Once a payout lands, the money is gone |
| Analogy (PRD 2.2) | The AI agent is a new assistant holding the company checkbook. Anti-Body is the second signature. Anti-Body checks every payment and cannot be sweet-talked, bribed with "urgent," or argued into approving |
| The gap in one sentence | Small businesses are starting to let AI agents pay their bills through PayPal, and there is no simple, independent second signature that checks every payment for both overspending and trickery and that the AI cannot be talked out of |

### 2.1 Three trends colliding (PRD 2.3)

| Trend | Evidence | Meaning for the persona |
|---|---|---|
| 1. Invoice and email payment fraud is common and growing | 76% of US organizations had attempted or actual payments fraud in 2025. BEC hit 74%, up from 63% in 2024. More than half received emails from lookalike domains one or two letters off (AFP 2026 survey, 465 treasury practitioners, Jan 2026) | The attack Anti-Body blocks is the most common one |
| 2. Small businesses already run on AI | 77% of US small and midsize businesses used AI regularly by Jan 2026, up from 48% about 18 months earlier. Bookkeeping is the third most common use (Intuit QuickBooks 2026 AI Impact Report, 34,364 responses) | Owners already hand finance chores to AI |
| 3. Agents are getting the power to pay | PayPal launched Agent Ready in Oct 2025, available early 2026. Juniper Research forecasts $1.5 trillion agentic commerce spend by 2030 and names trust as the number one barrier | Payment rails are opening to agents. The trust layer is missing |

### 2.2 Fraud numbers (PRD 2.4)

| Stat | Value | Source | Date |
|---|---|---|---|
| US cybercrime losses reported to FBI | ~$20.9B, up 26% from 2024 | FBI IC3 2025 report via CyberScoop | Apr 2026 |
| BEC losses reported to FBI | ~$3.05B, second-largest loss category | FBI IC3 2025 via CyberScoop | Apr 2026 |
| Orgs hit by payments fraud | 76% | AFP 2026 survey | Apr 2026 |
| Orgs hit by BEC | 74% (63% in 2024) | AFP 2026 via Nacha | May 2026 |
| Spoofed email as top BEC type | 85% of orgs | AFP 2026 via Nacha | May 2026 |
| Orgs using AI against payments fraud | 17% | AFP 2026 | Apr 2026 |
| Orgs with financial losses, under $1B revenue | 48% | AFP 2026 | Apr 2026 |
| Smaller firms | Hit less often but more likely to absorb the full loss | AFP 2026 | Apr 2026 |
| Orgs hit by invoice fraud in last 12 months | 40% | Ivalua / Sapio Research, 800 procurement leads | Sep 2026 |
| Average invoice fraud loss (orgs that lost money) | $367,000; 29% lost $500k+ | Ivalua | Sep 2026 |
| Average time to detect invoice fraud | 9 days; only 27% caught same day | Ivalua | Sep 2026 |
| Orgs with mostly automated supplier checks + audit trail | 25% | Ivalua | Sep 2026 |
| Median occupational fraud loss | $104,000; typical scheme runs 12 months before discovery | ACFE 2026 via Brady Ware | 2026 |
| BEC impersonating an external third party | ~61% | Abnormal 2026 Attack Landscape (VEC), ~800k attacks | 2026 |
| Vendor fraud campaigns that are "billing account update" | 23.6% | Abnormal 2026 | 2026 |
| Vendor fraud done by impersonation (not hacked account) | 87.5% | Abnormal 2026 | 2026 |

**Caveat (PRD 2.4):** most surveys sample mid-size and large organizations. No source gives invoice fraud rates for businesses under 20 employees. SMB-specific loss data is a gap. The AFP finding that smaller firms absorb the full loss more often is the strongest SMB-relevant signal. OQ-08 tracks the AFP primary source.

### 2.3 Threats mapped to controls (PRD 2.5)

| Real-world threat (source) | Anti-Body control |
|---|---|
| Fraudulent change to supplier payment details, top concern for 41% (Ivalua); billing account update, 23.6% of vendor fraud campaigns (Abnormal) | H1 payee must match registry. Payee changes only through owner dashboard with verification note (FR-02) |
| Duplicate invoicing, top concern for 39% (Ivalua) | H6 vendor + invoice number unique, H7 file hash unique, Payouts `sender_batch_id` idempotency |
| Compromised supplier email, 39% (Ivalua) | H1 still blocks a new payee even if the real vendor's email sent it. S5 timing, S2 amount anomaly |
| Lookalike domains, more than half of orgs (AFP) | S1 lookalike detection +45 |
| Ghost vendors, 32% (Ivalua) | Only registered, verified vendors can be paid. First payment to any vendor holds (S5) |
| Prompt injection inside invoices (OWASP LLM01, A2AS paper) | Extractor quotes instructions as data. Decider never sees raw text. Monotonic score |
| Agent given more power than needed (OWASP LLM06) | Agents can only propose. No approve tool, no amount or payee arguments |
| Slow detection, 9 days average (Ivalua) | Decision before money moves. Telegram alert in seconds |

## 3. Why AI, and what AI does not do (PRD 2.6)

| Job | Why rules alone fail | AI role |
|---|---|---|
| Reading invoices | Vendors send PDFs in every layout | Extractor pulls payee, items, amounts |
| Spotting manipulation | "Pre-approved," "updated payment details," "ignore previous rules" come in endless phrasings | Extractor classifies intent into `suspicious_phrases` categories |
| Judging line items | "3x the usual tape" or "a laptop on a packaging invoice" needs vendor context | Analyst judges fit (S3) |
| Explaining | Owners act faster on a 2-sentence reason than on rule codes | Analyst writes `owner_explanation` |
| **Not AI** | Decide, score, approve, move money | PHP only. This split is the product |

## 4. Why now (PRD 2.7)

| # | Reason |
|---|---|
| 1 | Payment rails are opening to agents: PayPal Agent Ready, Google AP2 with 60+ partners, card network agent programs |
| 2 | Agents are already in small businesses: 77% regular AI use among US SMBs |
| 3 | Attackers already target vendor payments: BEC at 74% of orgs, $3.05B reported losses |
| 4 | Agent security is a named risk: OWASP ranks prompt injection first and excessive agency sixth for LLM apps |
| 5 | Agent payment protocols were already broken in research: an AP2-based agent subverted by simple prompt injection (Jan 2026 paper, arXiv 2601.22569) |
| 6 | Tooling makes it buildable solo: MCP is the standard agent-tool interface, Laravel ships official MCP and AI SDK packages |

## 5. Market sizing (PRD 2.8)

### 5.1 Top-down reference points (context, not Anti-Body's market)

| Market | Figure | Source | Note |
|---|---|---|---|
| Agentic commerce spend, global | $1.5T by 2030 | Juniper Research, Apr 2026 | Mostly consumer shopping |
| Agentic commerce GMV, US | $190B base, $385B bull by 2030 (10% to 20% of US e-commerce) | Morgan Stanley | Consumer |
| AP automation software, global | $2.7B (2021) to $8.3B (2031), 12.3% CAGR; SMEs fastest at 14.5% CAGR | Allied Market Research, Mar 2023 | Dated. Direction only |
| PayPal active accounts | 439M; TPV $486.4B in Q2 2026 | PayPal Q2 2026 8-K | Consumer + merchant combined |
| US small businesses | 36.2M | SBA Office of Advocacy, Feb 2026 | |

No source sizes "B2B payments made by AI agents" or "agent spend control for SMBs." The bottom-up model below is assumption-based and must be validated.

### 5.2 Bottom-up model, US only (assumptions labeled)

| Layer | Calculation | Result |
|---|---|---|
| US small businesses | SBA | 36.2M |
| Sizing A1: share that sell online and pay vendors digitally | 20% (assumption) | 7.2M |
| **TAM** at $19/month ($228/yr) | 7.2M x $228 | **~$1.64B/yr** |
| Sizing A2: share paying vendors via PayPal and using or piloting AI for AP | 10% of TAM (assumption) | 720k |
| **SAM** | 720k x $228 | **~$164M/yr** |
| Sizing A3: 3-year capture | 0.5% of SAM (assumption) | 3,600 businesses |
| **SOM** | 3,600 x $228 | **~$0.82M ARR** |

| Sensitivity | SAM |
|---|---|
| Sizing A1 = 10%, A2 = 5% | ~$41M |
| Sizing A1 = 30%, A2 = 15% | ~$371M |

Global upside (not modeled): sellers outside the US paying vendors and contractors through PayPal, including the Philippines. No sourced figure, so excluded.

## 6. Competitive landscape (PRD 2.9, re-verified 2026-10-23)

| Type | Examples | What it does | What it misses |
|---|---|---|---|
| Agent wallets with limits | Paybond, Privacy virtual cards | Budgets, approvals, logs, card-level limits before a charge | Checks how much, not who or why. A fake invoice under the limit still pays |
| Enterprise AP fraud AI | Ramp, Serrala, Peakflo, Spendflo, Ottimate | Flags suspicious domains, bank detail changes, abnormal amounts | Built for finance teams. Guards only payments inside their own platform |
| Agent payment protocols | Google AP2 (60+ firms incl. PayPal), Visa, Stripe and OpenAI protocols | Standard mandates and credentials for agent payments | Simple prompt injection reliably subverts an AP2-based shopping agent |
| PayPal agent tools | PayPal Agent Ready, Store Sync | Lets merchants accept payments from agents and list products in AI channels | Helps the merchant receiving money, not the business sending it |
| Email security | Abnormal and similar | Detects vendor impersonation in the inbox | Stops at the email. Does not gate the payment |

### 6.1 Feature comparison

| Capability | Agent wallets | Enterprise AP AI | Email security | Agent protocols | Anti-Body |
|---|---|---|---|---|---|
| Spend limits per payment and per week | Yes | Yes | No | Partial | Yes |
| Verified payee registry enforced in code | No | Yes | No | No | Yes |
| Invoice understanding (PDF to fields) | No | Yes | No | No | Yes |
| Prompt-injection isolation for agents | No public claim found | No public claim found | n/a | Shown vulnerable in research | Yes, by design |
| Works with any agent via MCP | Partial (Paybond supports MCP hosts) | No | No | Protocol-specific | Yes |
| Sits outside the agent | Yes | Inside own platform | Inside inbox | Inside protocol | Yes |
| Sized and priced for a 1 to 10 person seller | Yes | No | Partial | n/a | Yes |
| PayPal-native money movement | No public claim found | No public claim found | No | Partial | Yes (Payouts) |

"No public claim found" means not found in public material. It does not prove absence.

### 6.2 Positioning map

| | Built for finance teams | Built for small sellers |
|---|---|---|
| Guards inside one platform | Ramp, Serrala, Peakflo | Card-level wallets |
| Guards any agent from outside | Not found in research | **Anti-Body** |

## 7. Positioning

| Item | Statement |
|---|---|
| The gap (PRD 2.10) | No small-business-sized, independent guard that sits outside the agent, checks both overspending and trickery on PayPal payments, and cannot be talked out of a decision |
| Positioning statement (PRD 2.11) | For small online sellers who let AI agents handle vendor payments, Anti-Body is a payment gate that blocks impersonation, duplicates and overspend before money leaves PayPal. Unlike enterprise AP suites, Anti-Body works with any agent and fits a one-owner shop. Unlike agent wallets, Anti-Body checks who is being paid and why, not only how much |
| Pitch rule | Never claim "nobody does this." Claim "big companies have this. Small sellers on PayPal don't, and their agents are the easiest to trick." |

## 8. Goals and non-goals (PRD 4)

| ID | Goal |
|---|---|
| G1 | Pass Stage 1 (theme fit and real PayPal + AI use) with zero doubt |
| G2 | Target Top 3 overall, or Best Use of Agentic Commerce, plus Best Use of Render |
| G3 | A judge can run the repo locally in under 10 minutes, or use the hosted demo with test credentials |
| Product goal P1 | No payment leaves the system unless it matches a verified vendor, an extracted invoice, the approved amount and currency, exactly once |
| Product goal P2 | No agent input, invoice text or model output can lower a risk score or bypass a hard rule |
| Product goal P3 | The owner can see why every decision was made in one screen |

**Non-goals (cut list, all go to the roadmap):** store checkout or shopping flows; paying arbitrary open-web merchants; multiple owners, roles or approval chains; email-forward intake and inbox scanning; mobile app; trained ML models; live (production) PayPal money; multi-currency beyond USD; marking the vendor's own PayPal invoice as paid.

Demo success criteria M1 to M7 are in [hackathon-compliance.md](hackathon-compliance.md#demo-success-criteria).

## 9. Assumptions to validate (PRD 2.12)

| ID | Assumption | Risk if wrong | How to test (post-hackathon) |
|---|---|---|---|
| A1 | Sellers will let agents pay invoices if a guard exists | No demand | 10 seller interviews, landing page waitlist |
| A2 | Payee mismatch catches most real attacks | Weaker value | Collect 20 real fraud samples from forums and reports |
| A3 | Owners will approve holds from Telegram | Friction | Track approval time in pilot |
| A4 | PayPal Payouts access is attainable for small sellers in live mode | Blocks launch | Apply for live Payouts with a real business |
| A5 | $19/month is acceptable | Pricing | Pricing page test with 3 tiers |

## 10. Implementation status

| Area | Status on 2026-10-30 |
|---|---|
| Product behavior described above | Planned. No domain code exists yet |
| Laravel 13.39.0 + Filament 5.11.0 skeleton | Implemented |
| Build progress | See [roadmap.md](roadmap.md) |
