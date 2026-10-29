---
title: Personas and jobs to be done
purpose: Describe the primary and secondary personas, their jobs to be done, the journey with and without Anti-Body, and the pain quantified.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - overview.md
  - user-stories.md
  - demo-script.md
source_of_truth: docs/prd-anti-body-paypal.md section 3.
last_verified: 2026-10-30
---

# Personas and jobs to be done

> **TL;DR**
> - Primary persona: Mara Santos, 31, owner of the fictional eco packaging shop "Kahon Goods", about 20 vendors paid by PayPal.
> - Secondary personas: the agent builder (in scope via MCP, US-07), the bookkeeper or VA (partial), the agency owner (roadmap).
> - Mara's fear: the agent pays a scammer, pays twice, or pays a padded invoice while she sleeps.
> - The persona is illustrative and built from research (OQ-09: replace with a real seller quote if obtained).

## 1. Primary persona: Mara Santos, owner of "Kahon Goods" (PRD 3.1)

| Attribute | Detail |
|---|---|
| Profile | 31, owner of a small e-commerce shop selling eco packaging and home goods online |
| Team | Owner, 2 part-time staff, 1 freelance VA |
| Size | ~US$25k monthly sales (assumption) |
| Vendors | ~20: box supplier (BoxPro Supplies), printing (PrintHub), tape and filler, 3 product makers, a photographer, the VA |
| How Mara pays | PayPal business balance to vendor PayPal emails |
| Invoice volume | 25 to 40 a month (assumption) |
| Current workflow | Invoices land in email. Mara or the VA checks and pays in PayPal. Mara is testing an AI agent for this |
| Time cost today | 3 to 5 hours a month on invoices (assumption) |
| Past scare | Received a fake "updated payment details" email last year. Almost paid it |
| Fear | The agent pays a scammer, pays twice, or pays a padded invoice while Mara sleeps |
| Tech comfort | Uses Shopify-style tools, Canva, ChatGPT. Not a developer |
| Loss without Anti-Body | Mara's time (no agent) or Mara's safety (agent with full access). Smaller firms absorb the full loss more often (AFP 2026) |

The persona is illustrative and built from the research in [overview.md](overview.md#22-fraud-numbers-prd-24). Replace with a real seller quote before the video if one is obtained (OQ-09).

## 2. Secondary personas (PRD 3.2)

| Persona | Who | Why the persona cares | In scope for hackathon |
|---|---|---|---|
| The agent builder | Developer building an AP or ops agent for clients | Needs a safe way to let the agent pay without holding PayPal keys | Yes, via MCP (US-07) |
| The bookkeeper / VA | Freelancer managing payables for 3 to 5 small clients | Liability if a fake invoice gets paid on the bookkeeper's watch | Partial, owner role only. Multi-client is roadmap |
| The agency owner | Runs a small agency paying 30+ freelancers | Many payees, many payee-change requests | Roadmap (multi-owner, bulk) |

Non-human actors are covered in [user-stories.md](user-stories.md): the built-in AP agent (US-06), external MCP agents (US-07, US-08), vendors as a data source (US-09) and judges (US-10, US-11).

## 3. Jobs to be done (PRD 3.3)

| # | When | I want | So I can |
|---|---|---|---|
| J1 | A vendor invoice arrives | It paid on time without me touching it | Focus on selling |
| J2 | Something looks off | To be asked first with the reason in one line | Decide in seconds from my phone |
| J3 | I let an AI agent pay | A guarantee the agent cannot be tricked into paying a stranger | Trust the agent at night |
| J4 | I review my month | To see what was paid, held and blocked | Prove nothing slipped |

J1 to J4 are labels added by the docs for cross-reference; the PRD lists the jobs without IDs.

## 4. Journey today vs with Anti-Body (PRD 3.4)

| Step | Today (manual or raw agent) | With Anti-Body | Requirement |
|---|---|---|---|
| Invoice arrives | Email, PDF. Easy to fake | Uploaded or submitted by agent. Hashed and extracted | FR-04, FR-05, FR-06, FR-08 |
| Check vendor | Owner eyeballs name and logo | Payee checked against verified registry in code | H1, FR-01 |
| Check amount | Owner remembers "usual" or doesn't | Compared with vendor history automatically | S2, FR-38 |
| Spot pressure tricks | Depends on how tired the owner is | Flagged every time, treated as data | FR-09, S4 |
| Decide | Owner or agent alone | AUTO, HOLD or BLOCK with reasons | FR-16 |
| Pay | Manual PayPal send, can double-send | Payouts with idempotency, exactly once | FR-18, FR-20 |
| Confirm | Check PayPal later | Webhook-confirmed, Telegram "Paid" | FR-21, FR-22, FR-26 |
| Audit | Scroll PayPal history | Hash-chained log with every decision | FR-35 |

## 5. Pain quantification (PRD 3.5, illustrative)

| Pain | Estimate | Basis |
|---|---|---|
| Time on invoices | 3 to 5 h/month | Assumption from persona |
| One successful vendor impersonation | Loses the full invoice amount, often unrecoverable | AFP: small firms more likely to absorb full loss |
| Detection delay without a gate | ~9 days average | Ivalua 2026 |
| Duplicate payment | 1x invoice amount until vendor refunds | Industry pattern, no SMB figure found |
| Anti-Body cost per invoice | ~$0.007 in AI calls (PRD 17.7). ~$0.0115 if the extractor falls back to `claude-haiku-4-5` (OQ-12) | [../ai/extractor.md](../ai/extractor.md) |

## 6. Override authority by actor (summary of PRD 9.4)

| Actor | Can | Cannot |
|---|---|---|
| Owner (dashboard) | Approve HOLD, reject anything, override score-BLOCK with typed confirmation, edit vendors and policy | Override hard-rule BLOCK |
| Owner (Telegram) | Approve or reject HOLD | Override any BLOCK, edit vendors or policy |
| Built-in AP agent | Submit invoices, propose payments, read decisions | Approve, reject, override, edit vendors, set amount or payee |
| External MCP agent | Same as built-in agent | Same as built-in agent |
| AI models (extractor, analyst) | Return structured data and explanations | Produce a score, a decision or any write |

Full rules: [../architecture/guard-spec.md](../architecture/guard-spec.md).
