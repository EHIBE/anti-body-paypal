---
title: Hackathon compliance and judging
purpose: Map every official PayPal AI Hackathon requirement (C1 to C16), judging criterion, goal (G1 to G3) and demo success criterion (M1 to M7) to Anti-Body features, evidence and status.
audience: [judges, owner, developers, ai-agents]
status: planned
related:
  - overview.md
  - demo-script.md
  - user-stories.md
  - roadmap.md
  - ../../README.md
source_of_truth: docs/prd-anti-body-paypal.md sections 4, 5, 6; official rules at https://paypalaihackathon.devpost.com/rules.
last_verified: 2026-10-30
---

# Hackathon compliance and judging

> **TL;DR**
> - Hackathon: PayPal AI Hackathon on Devpost. Deadline Thu Nov 12, 2026, 12:00 pm PT = Fri Nov 13, 2026, 4:00 am PHT. Target submit Tue Nov 10, 2026 PHT.
> - 16 compliance items C1 to C16. On 2026-10-30, C12 (English) and C13 (new project, first commit after Oct 1) are met; C8 is met by the MIT `LICENSE` added in ABP-8 (GitHub About still to set); the rest are planned.
> - 5 judging criteria at 20% each. Technological Implementation is the first tie-break.
> - Targets: Top 3 overall, or Best Use of Agentic Commerce, plus Best Use of Render (G2).

## 1. Compliance matrix (PRD 5)

PRD paths `app/Domain/*`, `app/Ai/*` and `app/Mcp/*` map to vertical slices (see [../architecture/slice-catalog.md](../architecture/slice-catalog.md)). The "Where" column below uses the slice paths.

| # | Official requirement | How Anti-Body satisfies it | Where (planned paths) | Status 2026-10-30 |
|---|---|---|---|---|
| C1 | Integrate PayPal developer platform using sandbox | Payouts API, Webhooks + signature verification, Transaction Search, OAuth | `app/Features/Payment/`, `app/Features/Webhook/`, `app/Features/Baseline/`; README "PayPal APIs used" | planned |
| C2 | Integrate an AI tool, model or platform | Claude Haiku 5.5 extractor (model ID UNVERIFIED, OQ-12), Claude Sonnet 5.5 risk analyst, built-in AP agent, MCP server for external agents | `app/Features/Extraction/Ai/`, `app/Features/Guard/Ai/`, `app/Features/AgentGateway/`; README "AI used" | planned |
| C3 | Working prototype | Hosted on Render + local setup | README "Run it", hosted URL | planned |
| C4 | Text description of features | Devpost description built from PRD 1 and PRD 8 | Devpost form | planned |
| C5 | Functional demo: run instructions or hosted URL | Both | README, hosted URL | planned |
| C6 | Name AI tools and how they are used | Table in README | README "AI used" | planned (README section drafted with planned marker) |
| C7 | Public repo with all source, assets, instructions | GitHub `EHIBE/anti-body-paypal` | Repo root | planned (repo visibility not verified) |
| C8 | Open source license visible in About | MIT `LICENSE` file, set in GitHub About | Repo root | partial: `LICENSE` added by ABP-8; GitHub About setting pending |
| C9 | Video under 3 min, public on YouTube, shows it running | Script in [demo-script.md](demo-script.md), target 2:50 | YouTube link in Devpost | planned (polish block P3) |
| C10 | No unlicensed music or third-party trademarks in video | Royalty-free or no music. Fictional vendor names only. PayPal shown only as the platform used | [demo-script.md](demo-script.md) | planned |
| C11 | Test credentials and sandbox logins documented | `README#judge-access` with demo owner login, MCP token, sandbox buyer and vendor emails | README | planned (polish block P2) |
| C12 | English materials | All English | Everywhere | met |
| C13 | New project or significant update | New project, first commit after Oct 1 (initial commit `ae76102`) | Git history | met |
| C14 | Original work, solely owned | Built solo by EHIBE. No thesis code reused (OQ-01) | Repo | met so far; OQ-01 open |
| C15 | Free access for judges until judging ends (Dec 15) | Hosting budgeted through Jan 2027 (PRD 24) | Render | planned |
| C16 | Sponsor prize: uses a Render tool | Deployed on Render web service + Render Postgres | `render.yaml` (not yet created) | planned |

## 2. Judging criteria map (PRD 6)

| Criterion (20% each) | What judges look for | Features that earn it | Video scene | Target score |
|---|---|---|---|---|
| Technological Implementation (1st tie-break) | Thorough, non-trivial PayPal + AI use | Payouts with idempotency, verified webhooks, state machine, Transaction Search baseline, MCP server, isolated extractor and scorer, Pest + Larastan level 10 | 0:15, 0:50, 1:40 | 9 |
| Design | Complete, coherent product | Filament dashboard, decision cards with reasons, Telegram approval, empty and error states | 0:15, 2:10 | 8 |
| Potential Impact | Specific problem, real audience, solved on screen | Persona, AP fraud stats, blocked impersonation with money saved counter | 0:00, 0:50 | 8 |
| Innovation | Novel vs existing concepts | Agent-proof gate outside the agent, injection isolation, monotonic scoring, any-agent MCP | 0:50, 2:40 | 9 |
| Presentation | End-to-end demo, clear who and why | Tight 2:50 script, one villain, one counter | Whole video | 9 |

Larastan is not installed on 2026-10-30 (needs approval). The Technological Implementation claim "Larastan level 10" stays planned until Larastan is installed and passes.

## 3. Hackathon goals (PRD 4.1)

| ID | Goal | Evidence when met | Status |
|---|---|---|---|
| G1 | Pass Stage 1 (theme fit and real PayPal + AI use) with zero doubt | C1, C2, C6 satisfied with working sandbox payouts and AI calls | planned |
| G2 | Target Top 3 overall, or Best Use of Agentic Commerce, plus Best Use of Render | Judging result | planned |
| G3 | A judge can run the repo locally in under 10 minutes, or use the hosted demo with test credentials | M7 measured on a clean machine; C11 | planned |

## Demo success criteria

| ID | Criterion | Target | Verified by | Status |
|---|---|---|---|---|
| M1 | Legit invoices auto-paid end to end in sandbox | 2 of 2 in the video, payout shows SUCCESS | Demo scene 0:30 to 0:42; sandbox dashboard | planned |
| M2 | Price-jump invoice held, then approved from Telegram | Payout sent within 30 s of tap | Demo scene 0:42 to 0:52 | planned |
| M3 | BoxPro impersonation invoice | BLOCK, 0 payout API calls made | Eval case 9; Pest test asserting `Http::assertNothingSent` to Payouts | planned |
| M4 | Duplicate invoice | BLOCK, 0 payout API calls made | Eval cases 11 and 12 | planned |
| M5 | Agent argues "pre-approved" | Decision unchanged | US-08 MCP test | planned |
| M6 | Eval set (17 invoices) | 100% correct on hard-rule cases, at least 90% correct decision overall | [../ai/eval-set.md](../ai/eval-set.md) | planned |
| M7 | Repo setup | Under 10 min on a clean machine following README | Clean-machine timing (Day 13 definition of done) | planned |

## 5. Submission checklist

| Item | Requirement IDs | Owner action | Status |
|---|---|---|---|
| Public GitHub repo `EHIBE/anti-body-paypal` with source, assets, instructions | C7 | Confirm visibility public before submit | planned |
| MIT license in repo and GitHub About | C8 | Set GitHub About license | partial |
| README with judge access, run instructions, PayPal APIs used, AI used | C5, C6, C11 | Fill hosted URL, credentials, MCP token instructions | planned |
| Hosted demo on Render, alive until at least Dec 15 | C3, C15, C16 | Deploy `anti-body-paypal-web` + `anti-body-paypal-db` | planned |
| Video under 3 min on YouTube, public | C9, C10 | Record per [demo-script.md](demo-script.md) | planned |
| Devpost description from PRD 1 and 8 | C4 | Draft in polish block P3 | planned |
| No secrets, real names, real emails or real invoices in repo or video | C10, PRD 18 | Review before submit | ongoing |

## 6. Sources

| Source | URL |
|---|---|
| Hackathon overview, prizes, judges, criteria | https://paypalaihackathon.devpost.com/ |
| Official rules | https://paypalaihackathon.devpost.com/rules |
| Resources (PayPal APIs, AI toolkit) | https://paypalaihackathon.devpost.com/resources |
| FAQs | https://paypalaihackathon.devpost.com/details/faqs |
| Render credits ($50) | https://paypalaihackathon.devpost.com/details/render |
| PayPal developer blog | https://developer.paypal.com/community/blog/PayPal_AI_Hackathon |
