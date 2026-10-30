---
title: Anti-Body documentation index
purpose: Entry point and reading order for every Anti-Body document, for humans and AI agents.
audience: [judges, owner, developers, ai-agents]
status: mixed
related:
  - prd-anti-body-paypal.md
  - glossary.md
  - product/overview.md
  - architecture/overview.md
  - development/ai-agents-and-skills.md
source_of_truth: docs/prd-anti-body-paypal.md for product behavior; the code under app/, bootstrap/, config/, database/ for what exists today.
last_verified: 2026-10-30
---

# Anti-Body documentation index

> **TL;DR**
> - Anti-Body is a payment gate between AI agents and a small business PayPal account. Agents propose vendor invoice payments. Only Anti-Body moves money. Outcomes are AUTO, HOLD, BLOCK.
> - The PRD (`prd-anti-body-paypal.md`) is the product source of truth. Every other doc derives from the PRD or from the code.
> - As of 2026-10-30 the repository is a Laravel 13.39.0 + Filament 5.11.0 skeleton. Almost every doc describes **planned** behavior. Each doc states its status in front matter.
> - AI agents: read this file, then `../CLAUDE.md` (or `../AGENTS.md`), then the doc for the slice in scope.

## 1. Source-of-truth hierarchy

| Rank | Source | Authoritative for | Rule when sources disagree |
|---|---|---|---|
| 1 | [PRD](prd-anti-body-paypal.md) | Product behavior, rules H1 to H10, signals S1 to S7, thresholds, data model, MCP tools, PayPal and AI specs | PRD wins for intended behavior |
| 2 | Code (`app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`) | What exists and runs today | Code wins for "what is implemented"; a doc that disagrees with code is a doc bug |
| 3 | Docs in `docs/` (this tree) | Explanation, cross-reference, decisions, operations | Docs never override the PRD or the code |
| 4 | Project sections of `../CLAUDE.md` and `../AGENTS.md`, and the agent-facing rule index [`../.ai/rules/index.md`](../.ai/rules/index.md) (15 rules recorded with Laravel Boost `record-rule`) | Agent working rules, current state, pending queue | Must stay consistent with ranks 1 to 3 |

Known drift between the PRD and the installed stack or architecture is tracked in [architecture/overview.md](architecture/overview.md#6-prd-drift).

## 2. Status vocabulary

| Status | Meaning |
|---|---|
| `implemented` | Every statement in the doc describes code that exists in the repository on `last_verified` |
| `planned` | The doc describes behavior that is specified but not built. Paths, class names and commands are targets, not facts |
| `mixed` | Some sections are implemented, some planned. Each planned section is labeled **Status: planned** |

## 3. Reading order

### 3.1 For humans (judges, owner, reviewers)

| Step | Doc | Why |
|---|---|---|
| 1 | [../README.md](../README.md) | Project summary, judge access, run instructions |
| 2 | [product/overview.md](product/overview.md) | Problem, gap, hackathon purpose |
| 3 | [product/personas.md](product/personas.md) | Who Anti-Body is for |
| 4 | [product/demo-script.md](product/demo-script.md) | What the video shows |
| 5 | [architecture/overview.md](architecture/overview.md) | How the system fits together |
| 6 | [architecture/guard-spec.md](architecture/guard-spec.md) | Hard rules, signals, decisions |
| 7 | [security/threat-model.md](security/threat-model.md) | What Anti-Body defends against |
| 8 | [product/hackathon-compliance.md](product/hackathon-compliance.md) | How each rule of the hackathon is met |

### 3.2 For AI coding agents

| Step | Doc | Why |
|---|---|---|
| 1 | `../CLAUDE.md` or `../AGENTS.md` (project sections outside the Boost block) | Binding constraints, current state, pending queue |
| 2 | [../.ai/rules/index.md](../.ai/rules/index.md), then every rule file whose globs cover the paths in scope | Binding path-scoped rules (features, actions, data, models, traits, filament, enums, guard, payment, agent-gateway, audit, tests, app, migrations, routes) |
| 3 | [development/coding-conventions.md](development/coding-conventions.md) | Code style rules that every change must follow |
| 4 | [architecture/vertical-slice-architecture.md](architecture/vertical-slice-architecture.md) | Where code goes |
| 5 | [architecture/slice-catalog.md](architecture/slice-catalog.md) | Which slice owns the responsibility in scope |
| 6 | The topic doc for the task (guard, data model, API, AI, integration, security) | Exact spec |
| 7 | [development/testing-strategy.md](development/testing-strategy.md) | Required tests |
| 8 | [development/ai-agents-and-skills.md](development/ai-agents-and-skills.md) | Which project agent and skill to use |
| 9 | [development/commit-and-branch-rules.md](development/commit-and-branch-rules.md) | Commit format `type: [ABP-#] Action` |

## 4. Doc map

| Area | Doc | Topic | Status |
|---|---|---|---|
| Root | [prd-anti-body-paypal.md](prd-anti-body-paypal.md) | Product requirements (source of truth) | approved spec |
| Root | [glossary.md](glossary.md) | Terms and ID schemes | implemented |
| Product | [product/overview.md](product/overview.md) | Purpose, problem, gap, market, positioning | planned product |
| Product | [product/personas.md](product/personas.md) | Mara, secondary personas, jobs to be done | planned product |
| Product | [product/user-stories.md](product/user-stories.md) | US-01 to US-11, FR traceability | planned |
| Product | [product/hackathon-compliance.md](product/hackathon-compliance.md) | C1 to C16, judging map, M1 to M7 | planned |
| Product | [product/demo-script.md](product/demo-script.md) | 2:50 video script | planned |
| Product | [product/roadmap.md](product/roadmap.md) | Build plan B1 to B9, P1 to P3, risks, roadmap | mixed |
| Architecture | [architecture/overview.md](architecture/overview.md) | Components, stack, PRD drift | mixed |
| Architecture | [architecture/vertical-slice-architecture.md](architecture/vertical-slice-architecture.md) | Slice layout and wiring | planned |
| Architecture | [architecture/slice-catalog.md](architecture/slice-catalog.md) | 13 slices and dependencies | planned |
| Architecture | [architecture/filament-separation.md](architecture/filament-separation.md) | Filament resources and model traits | mixed |
| Architecture | [architecture/request-lifecycles.md](architecture/request-lifecycles.md) | End-to-end flows | planned |
| Architecture | [architecture/state-machines.md](architecture/state-machines.md) | Invoice and payment states | planned |
| Architecture | [architecture/data-model.md](architecture/data-model.md) | Tables, constraints, indexes | mixed |
| Architecture | [architecture/guard-spec.md](architecture/guard-spec.md) | H1 to H10, S1 to S7, thresholds | planned |
| API | [api/mcp-tools.md](api/mcp-tools.md) | MCP server and 6 tools | planned |
| API | [api/http-endpoints.md](api/http-endpoints.md) | Every HTTP route | mixed |
| API | [api/paypal-webhooks.md](api/paypal-webhooks.md) | PayPal webhook receiver | planned |
| API | [api/telegram-webhook.md](api/telegram-webhook.md) | Telegram webhook and callbacks | planned |
| API | [api/error-formats.md](api/error-formats.md) | JSON, MCP and UI error shapes | mixed |
| AI | [ai/extractor.md](ai/extractor.md) | Invoice extractor model | planned |
| AI | [ai/analyst.md](ai/analyst.md) | Risk analyst model | planned |
| AI | [ai/ap-agent.md](ai/ap-agent.md) | Built-in AP agent | planned |
| AI | [ai/injection-isolation.md](ai/injection-isolation.md) | Prompt-injection isolation | planned |
| AI | [ai/eval-set.md](ai/eval-set.md) | 17-invoice eval set | planned |
| Integrations | [integrations/paypal-payouts.md](integrations/paypal-payouts.md) | Payouts API use | planned |
| Integrations | [integrations/paypal-oauth.md](integrations/paypal-oauth.md) | OAuth token handling | planned |
| Integrations | [integrations/idempotency.md](integrations/idempotency.md) | Exactly-once payout | planned |
| Integrations | [integrations/reconcile.md](integrations/reconcile.md) | Reconcile job | planned |
| Integrations | [integrations/paypal-sandbox-setup.md](integrations/paypal-sandbox-setup.md) | Sandbox accounts and app | planned |
| Integrations | [integrations/telegram.md](integrations/telegram.md) | Telegram bot | planned |
| Security | [security/threat-model.md](security/threat-model.md) | Threats and abuse cases | planned |
| Security | [security/trust-boundaries.md](security/trust-boundaries.md) | Trust boundaries | planned |
| Security | [security/owasp-mapping.md](security/owasp-mapping.md) | OWASP Top 10:2025 and LLM Top 10 | planned |
| Security | [security/secrets.md](security/secrets.md) | Secret handling | mixed |
| Security | [security/audit-hash-chain.md](security/audit-hash-chain.md) | Tamper-evident audit log | planned |
| Development | [development/local-setup.md](development/local-setup.md) | Run locally | mixed |
| Development | [development/coding-conventions.md](development/coding-conventions.md) | Code rules | implemented rules |
| Development | [development/testing-strategy.md](development/testing-strategy.md) | Pest tests | mixed |
| Development | [development/adding-a-slice.md](development/adding-a-slice.md) | New slice how-to | planned |
| Development | [development/adding-a-filament-resource.md](development/adding-a-filament-resource.md) | New resource how-to | planned |
| Development | [development/commit-and-branch-rules.md](development/commit-and-branch-rules.md) | Git rules | implemented |
| Development | [development/ai-agents-and-skills.md](development/ai-agents-and-skills.md) | Project agents and skills | implemented |
| Operations | [operations/configuration.md](operations/configuration.md) | Env vars and config | mixed |
| Operations | [operations/deployment-render.md](operations/deployment-render.md) | Render deploy | planned |
| Operations | [operations/runbook.md](operations/runbook.md) | Day-to-day operations | planned |
| Operations | [operations/demo-reset.md](operations/demo-reset.md) | Demo reset | planned |
| Operations | [operations/incident-handling.md](operations/incident-handling.md) | Incidents | planned |
| Decisions | [decisions/0001-vertical-slice-architecture.md](decisions/0001-vertical-slice-architecture.md) | ADR 0001 | accepted |
| Decisions | [decisions/0002-filament-separation-with-model-traits.md](decisions/0002-filament-separation-with-model-traits.md) | ADR 0002 | accepted |
| Decisions | [decisions/0003-database-drivers-over-redis.md](decisions/0003-database-drivers-over-redis.md) | ADR 0003 | accepted |
| Decisions | [decisions/0004-render-postgres.md](decisions/0004-render-postgres.md) | ADR 0004 | accepted |
| Decisions | [decisions/0005-pest-over-phpunit.md](decisions/0005-pest-over-phpunit.md) | ADR 0005 | accepted |
| Decisions | [decisions/0006-readonly-dtos-over-spatie-laravel-data.md](decisions/0006-readonly-dtos-over-spatie-laravel-data.md) | ADR 0006 | accepted |

## 5. Stable ID schemes

IDs come from the PRD and are reused unchanged in every doc. See [glossary.md](glossary.md#id-schemes) for the full list.

| Prefix | Meaning | Defined in |
|---|---|---|
| `FR-01` to `FR-45` | Functional requirements | PRD 8 |
| `H1` to `H10` | Hard rules | PRD 9.1 |
| `S1` to `S7` | Risk signals | PRD 9.2 |
| `US-01` to `US-11` | User stories | PRD 7 |
| `OQ-01` to `OQ-17` | Open questions (OQ-12 to OQ-15 and OQ-17 added by ABP-8; OQ-16 resolved and retired during ABP-8) | PRD 27, [product/roadmap.md](product/roadmap.md#open-questions) |
| `C1` to `C16` | Hackathon compliance items | PRD 5 |
| `M1` to `M7` | Demo success criteria | PRD 4.4 |
| `G1` to `G3`, `P1` to `P3` (goals) | Hackathon and product goals | PRD 4.1, 4.2 |
| `B1` to `B9`, `P1` to `P3` (blocks) | Build and polish blocks | PRD 21 |
| `R1` to `R11` | Risks | PRD 25 |
| `A1` to `A5` | Assumptions to validate | PRD 2.12 |
| `ABP-#` | Ticket IDs for work items and commits | [development/commit-and-branch-rules.md](development/commit-and-branch-rules.md) |

`P1` to `P3` collide: PRD 4.2 uses them for product goals and PRD 21 uses them for polish blocks. Docs always qualify them as "product goal P1" or "polish block P1".

## 6. Keeping docs current

| Trigger | Required doc update |
|---|---|
| A slice, Action, model, route, job, command or config key is built | Change the matching doc section from **planned** to implemented, update `status` and `last_verified` |
| A behavior differs from the PRD | Record the difference in [architecture/overview.md](architecture/overview.md#6-prd-drift) and, if it is a decision, add an ADR in `decisions/` |
| A new env var | Add it to `../.env.example` (placeholder only) and [operations/configuration.md](operations/configuration.md) |
| An open question is answered | Update the OQ row in [product/roadmap.md](product/roadmap.md#open-questions) |
| A build block finishes | Update the pending queue in `../CLAUDE.md` and `../AGENTS.md` and the status column in [product/roadmap.md](product/roadmap.md) |

Every doc keeps the same front matter keys: `title`, `purpose`, `audience`, `status`, `related`, `source_of_truth`, `last_verified`.
