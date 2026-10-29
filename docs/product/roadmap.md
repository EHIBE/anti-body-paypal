---
title: Build plan, roadmap, risks and open questions
purpose: Track the 14-day build plan (B1 to B9, P1 to P3) with current status, the cut order, risks R1 to R11, open questions OQ-01 to OQ-17, the post-hackathon roadmap, the business model and the validation plan.
audience: [judges, owner, developers, ai-agents]
status: mixed
related:
  - overview.md
  - hackathon-compliance.md
  - user-stories.md
  - ../architecture/overview.md
source_of_truth: docs/prd-anti-body-paypal.md sections 21, 25, 26, 27, Appendix A, Appendix B. Current status from the repository on 2026-10-30.
last_verified: 2026-10-30
---

# Build plan, roadmap, risks and open questions

> **TL;DR**
> - Build window Sat Oct 24 to Fri Nov 6, 2026: 14 days x 4 h = 56 h (44 build + 12 polish). Buffer Nov 7 to Nov 12. Target submit Tue Nov 10 PHT.
> - Status on 2026-10-30 (Day 7): repository is a Laravel 13.39.0 + Filament 5.11.0 skeleton plus ABP-8 docs, agents and skills. Every build block B1 to B9 and polish block P1 to P3 is pending. The B1 PayPal spike result is not recorded in the repository.
> - The plan expected B1 to B5 done and B6 started by Day 7, so the build is about 6 days (24 h) behind plan. R6 trigger ("2 days behind plan") has fired: apply the cut order FR-32, FR-36, FR-37, FR-43, FR-17.
> - Open questions: OQ-01 to OQ-11 from the PRD, OQ-12 to OQ-15 and OQ-17 added by ABP-8 (OQ-16 raised and resolved during ABP-8).

## 1. Allocation (PRD 21)

| Block | Scope | Hours | Status 2026-10-30 |
|---|---|---|---|
| B1 | PayPal spike (PRD 16.5) | 4 | pending (no spike notes in repo) |
| B2 | Skeleton, Docker, Render deploy, CI | 2 | partial: Laravel + Filament skeleton exists; Docker, Render, CI pending |
| B3 | Data model, state machine, idempotency, payout executor, webhook handler | 6 | pending |
| B4 | Extractor | 5 | pending |
| B5 | Hard rules, scorer, analyst, decision engine | 8 | pending |
| B6 | MCP server + built-in AP agent | 6 | pending |
| B7 | Filament dashboard + approval queue | 5 | pending |
| B8 | Telegram approvals | 4 | pending |
| B9 | Seeders, demo PDFs, baseline sync, eval set, tests | 4 | pending |
| **Build total** | | **44** | |
| P1 | UI polish | 4 | pending |
| P2 | README, deploy hardening, judge access | 4 | partial: README rewritten by ABP-8 with planned markers |
| P3 | Video record, edit, upload, Devpost draft | 4 | pending |
| **Polish total** | | **12** | |
| **Grand total** | | **56** | |

Dependency approvals needed before blocks can start: laravel/sanctum (B6), laravel/ai (B4, B5, B6), laravel/mcp as a direct dependency (B6), laravel-notification-channels/telegram (B8), larastan/larastan (CI, B2).

## 2. Day by day (PRD 21)

| Day | Date | Hours | Work | Depends on | Definition of done | Status |
|---|---|---|---|---|---|---|
| 1 | Sat Oct 24 | 4 | B1 spike (PRD 16.5) | none | Steps 1 to 5 pass, notes saved | not recorded |
| 2 | Sun Oct 25 | 4 | B2 (2), B3 migrations and enums (2) | Day 1 | App live on Render, CI green, migrations run | partial |
| 3 | Mon Oct 26 | 4 | B3 state machine, executor, webhooks (4) | Day 2 | Pest tests prove no double submit under retry | pending |
| 4 | Tue Oct 27 | 4 | B4 extractor (4) | Day 3 | 5 demo PDFs extract correctly | pending |
| 5 | Wed Oct 28 | 4 | B4 finish (1), B5 hard rules (3) | Day 4 | All 10 hard rules unit-tested | pending |
| 6 | Thu Oct 29 | 4 | B5 signals S1, S2, S5, S6, S7 (4) | Day 5 | Deterministic signals tested | pending |
| 7 | Fri Oct 30 | 4 | B5 analyst + decision (1), B6 MCP server (3) | Day 6 | Score + decision end to end. MCP Inspector lists tools | in progress: ABP-8 docs |
| 8 | Sat Oct 31 | 4 | B6 AP agent (3), B7 start (1) | Day 7 | Claude Desktop proposes payment locally | pending |
| 9 | Sun Nov 1 | 4 | B7 dashboard (4) | Day 8 | Overview, invoice detail, queue, vendors, policy | pending |
| 10 | Mon Nov 2 | 4 | B8 Telegram (4) | Day 9 | Tap Approve pays in sandbox | pending |
| 11 | Tue Nov 3 | 4 | B9 seeders, eval set, CI eval (4) | Day 10 | Eval 16/17 or better, `demo-reset` works | pending |
| 12 | Wed Nov 4 | 4 | P1 UI polish | Day 11 | All states styled, no raw errors visible | pending |
| 13 | Thu Nov 5 | 4 | P2 README, judge access, deploy check | Day 12 | Clean-machine setup under 10 min | pending |
| 14 | Fri Nov 6 | 4 | P3 video + Devpost draft | Day 13 | Video under 3 min public on YouTube, draft saved | pending |
| Buffer | Nov 7 to 12 | n/a | Fixes, final submit target Nov 10 PHT | | Submitted with 2 days spare | pending |

## 3. Pending queue (recommended order)

| Order | Item | Blocks | Ticket |
|---|---|---|---|
| 1 | Run the PayPal spike and record results (Payouts enabled, duplicate `sender_batch_id`, webhook verify) | B1 | to assign |
| 2 | Approve dependencies: sanctum, ai, mcp, telegram channel, larastan | B2 to B8 | to assign |
| 3 | Shared wiring: slice provider discovery, slice route loading, slice testsuites, security headers middleware | B2 | to assign |
| 4 | Data model migrations, enums, state machines, Audit slice | B3 | to assign |
| 5 | Payment executor, PayPal client, webhook handler, reconcile job | B3 | to assign |
| 6 | Extractor | B4 | to assign |
| 7 | Guard: H1 to H10, S1 to S7, analyst, decision | B5 | to assign |
| 8 | MCP server and AP agent | B6 | to assign |
| 9 | Filament dashboard and approval queue | B7 | to assign |
| 10 | Telegram approvals | B8 | to assign |
| 11 | Seeders, demo PDFs, baseline, eval set | B9 | to assign |
| 12 | Polish P1, P2, P3 | P1 to P3 | to assign |

## 4. Cut order (PRD 25, R6)

| Order | Requirement | Pri | Effect of cut |
|---|---|---|---|
| 1 | FR-32 OAuth 2.1 via Passport for remote MCP | S | Use Sanctum bearer and local stdio server for the video (OQ-07, R11) |
| 2 | FR-36 Audit page with verify button | S | Keep `antibody:audit-verify` command only |
| 3 | FR-37 Transaction Search sync | S | Baseline from local seeded history (R2) |
| 4 | FR-43 Sandbox history seeding | S | Same as above |
| 5 | FR-17 Kill switch | S | H9 not demonstrable; keep the policy column |

## 5. Risks (PRD 25)

| # | Risk | Likelihood | Impact | Mitigation | Trigger | Owner |
|---|---|---|---|---|---|---|
| R1 | Payouts not enabled for the sandbox app | Medium | High | Day 1 spike, Discord ask same day, `Http::fake` to keep building, Orders + vault fallback | Spike step 2 fails | EHIBE |
| R2 | Transaction Search permission lag or empty data | Medium | Low | Baseline from local seeded history. Transaction Search is Should | No rows after 9 h | EHIBE |
| R3 | Model mis-extracts payee email | Low | High | Exact-copy instruction, eval exact match 17/17, H1 still blocks mismatches | Eval failure | EHIBE |
| R4 | Laravel AI SDK or MCP API differs from docs | Low | Medium | Pin versions Day 2, read package tests | Build error | EHIBE |
| R5 | Telegram webhook setup issues | Medium | Medium | Dashboard approvals work without Telegram. Telegram is demo polish | Callback not received by Day 10 | EHIBE |
| R6 | School, TVP or client work eats hours | High | High | Buffer Nov 7 to 12. Cut order: FR-32, FR-36, FR-37, FR-43, FR-17 | 2 days behind plan (fired on 2026-10-30) | EHIBE |
| R7 | Render credits not applied | Low | Low | Total cost $13/month fits personal budget | Billing shows charges | EHIBE |
| R8 | Judge cannot run locally | Medium | High | Hosted demo + judge access section + demo-reset | Clean-machine test fails | EHIBE |
| R9 | Name "Anti-Body" overlaps thesis IP | Low | Medium | Use name only, no thesis code. Confirm with co-researchers (OQ-01) | Objection raised | EHIBE |
| R10 | Video over 3 min | Medium | Medium | Script at 2:50, cut 1:42 to 2:00 scene first | Rough cut over 2:55 | EHIBE |
| R11 | Claude Desktop remote connector needs OAuth | Medium | Low | Use local stdio server for the video. Passport OAuth is Should | Remote connect fails | EHIBE |

Fallback for R1 (PRD 16.5): if Payouts are permanently unavailable, switch money movement to Orders v2 with a vaulted payment token (merchant-initiated). Same gate, different executor class. Requires owner confirmation.

## Open questions

| ID | Question | Default until answered | Source | Status |
|---|---|---|---|---|
| OQ-01 | "Anti-Body" is also the thesis name with co-researchers. Confirm no objection and that no thesis code or assets are reused (hackathon requires sole ownership) | Use name only, no thesis code | PRD 27 | open |
| OQ-02 | Does the PH-registered developer account get Payouts in sandbox without a support ticket | Find out Day 1 | PRD 27 | open (spike not recorded) |
| OQ-03 | Exact patch versions for Livewire 4, Pest 4, Larastan 3.9, Pint, Sanctum, Telegram channel | Lock on Day 2 with `composer show` | PRD 27 | partly answered: Pest 5.3.4, pest-plugin-laravel 5.0.1, Pint 1.32.1, Livewire 4 via Filament 5.11.0. Larastan, Sanctum, Telegram channel not installed |
| OQ-04 | Official PHP 8.5 Docker image tag and extensions needed (pdo_pgsql, intl, gd optional) | Confirm Day 2 | PRD 27 | open |
| OQ-05 | Telegram webhook secret header name and callback flow | Confirm in Telegram Bot API docs Day 10 | PRD 27 | open |
| OQ-06 | Filament v5 multi-factor auth config | Confirm Day 9, otherwise strong password only | PRD 27 | open |
| OQ-07 | Claude Desktop remote MCP with bearer token vs OAuth | Local stdio for video | PRD 27 | open |
| OQ-08 | AFP 2026 survey primary source for the 17% and 76% figures | Cite secondary until found | PRD 27 | open |
| OQ-09 | Can a real small seller give a one-line quote for the video | Use fictional persona | PRD 27 | open |
| OQ-10 | Render region availability for Singapore on Starter | Confirm at service creation | PRD 27 | open |
| OQ-11 | Sandbox payout to an email without a sandbox account returns UNCLAIMED as expected | Test Day 1 optional step | PRD 27 | open |
| OQ-12 | `claude-haiku-5-5` (extractor and AP agent model) is not in Anthropic's model list cached 2026-09-25. Confirm the model ID and pricing | Configure through `ANTIBODY_EXTRACTOR_MODEL`. Fallback `claude-haiku-4-5` ($1 / $5 per M tokens); cost per invoice rises from ~$0.007 to ~$0.0115 | ABP-8 | open |
| OQ-13 | PRD 11.1 uses real-looking domains `boxpro.ph` and `boxpro-ph.com` for demo emails | Demo data uses `.example` or PayPal sandbox emails only | ABP-8 | open |
| OQ-14 | Which timezone defines the H5 ISO week (owner is in PHT) | UTC ISO week | ABP-8 | open |
| OQ-15 | Telegram approval token expires in 15 min while HOLD lives 72 h. How does the owner approve from Telegram after expiry | After token expiry, approval is dashboard-only | ABP-8 | open |
| OQ-16 | Boost `record-rule` tool unavailable during ABP-8 | n/a | ABP-8 | resolved 2026-10-30: Boost reconnected, `.ai/rules` created (index [../../.ai/rules/index.md](../../.ai/rules/index.md)) |
| OQ-17 | Confirm the canonical JSON definition used by the audit hash chain | Recursively key-sorted JSON with `JSON_UNESCAPED_SLASHES` and `JSON_UNESCAPED_UNICODE`, over actor_type, actor_id, action, subject_type, subject_id, data, prev_hash, created_at. See [../security/audit-hash-chain.md](../security/audit-hash-chain.md) | ABP-8 | open |

## 7. Post-hackathon roadmap (PRD 26, ordered by value)

| # | Item | Related |
|---|---|---|
| 1 | Email-forward intake with DKIM/SPF checks as an extra signal (most invoices arrive by email) | FR-07 |
| 2 | Vendor verification workflow: out-of-band confirmation link sent to the old registered email before any payee change | FR-02 |
| 3 | OAuth 2.1 MCP auth via Passport for remote agents (wider client support) | FR-32, OQ-07 |
| 4 | Multi-owner roles and approval chains (teams) | Non-goal 4.3 |
| 5 | Mark vendor PayPal invoice as paid via a vendor-side integration (closes the loop) | PRD 16.6 |
| 6 | Multi-currency with FX sanity checks | H3 |
| 7 | Cancel unclaimed payouts after 30 days automatically | PRD 10.2 |
| 8 | Live mode with PayPal Payouts access approval | A4 |
| 9 | Learned thresholds per vendor from owner decisions | S2, S5 |
| 10 | Slack and WhatsApp approval channels | FR-26 |
| 11 | Stripe and bank-rail executors behind the same gate | Payment slice |

Also deferred: raw PDFs deleted after 90 days (PRD 18 data retention).

## 8. Business model (PRD Appendix A, post-hackathon hypothesis)

### 8.1 Pricing hypothesis (A5, not validated)

| Tier | Price | Includes | Who |
|---|---|---|---|
| Free | $0 | 20 invoices/month, 1 agent, dashboard approvals | Trial, tiny sellers |
| Starter | $19/month | 200 invoices, 3 agents, Telegram approvals, audit export | Primary persona |
| Growth | $49/month | 1,000 invoices, unlimited agents, multi-user, priority support | Agencies, bookkeepers |

### 8.2 Unit economics per Starter customer per month (assumptions labeled)

| Item | Value |
|---|---|
| AI cost (40 invoices x $0.007) | ~$0.28 (~$0.46 with the OQ-12 fallback) |
| Hosting share (200 customers on an $85 instance + $40 DB) | ~$0.63 |
| Subscription payment processing (~3%) | ~$0.57 |
| **Gross margin** | **~92%** |

### 8.3 Go-to-market, first 12 months

| Channel | Why | First action |
|---|---|---|
| PayPal developer community + Discord | Same audience as the hackathon | Post open-source repo and demo |
| MCP server directories | Agent builders discover tools there | List the Anti-Body MCP server |
| Shopify, WooCommerce and Etsy seller communities | Primary persona lives there | Short video of the BoxPro block |
| Bookkeeper and VA networks (incl. PH freelancer groups) | Each one brings 3 to 5 clients | Partner discount |
| Content: "how to let an AI agent pay invoices safely" | Search intent will grow with agent adoption | 3 posts + LinkedIn series |

### 8.4 Moat over time

| # | Moat |
|---|---|
| 1 | Vendor history and decision data per business sharpen scoring the longer Anti-Body runs |
| 2 | Shared, anonymized fraud signals across customers (known bad payee emails and domains). Roadmap, privacy review needed |
| 3 | Being the default safe MCP payment tool agents are configured with |

### 8.5 Key metrics after launch (hypotheses)

| Metric | Target year 1 |
|---|---|
| Invoices processed per active customer per month | 30+ |
| Share auto-approved without owner action | 70%+ |
| Median owner time to approve a HOLD | Under 5 min |
| Confirmed fraud blocked | Tracked, reported monthly |
| False-block rate (owner overrides score-BLOCK) | Under 5% |

## 9. Validation plan after Nov 13 (PRD Appendix B)

| Week | Activity | Success signal | Assumption |
|---|---|---|---|
| 1 | Interview 10 small sellers who pay vendors via PayPal | 6 of 10 describe invoice fraud fear or a near miss | A1 |
| 2 | Show demo video + ask for pilot | 3 agree to a sandbox pilot | A1 |
| 3 to 4 | Pilot with real (anonymized) invoices in sandbox | Extraction accuracy 95%+ on their invoices | A2, A3 |
| 5 | Pricing page test with 3 tiers | 20+ signups to waitlist | A5 |
| 6 | Apply for live PayPal Payouts access | Approval or clear requirements | A4 |
