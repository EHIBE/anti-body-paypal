---
title: "ADR 0003: Database drivers over Redis"
purpose: Record the decision to run queue, cache and session on Laravel database drivers instead of Redis and Horizon.
audience: [developers, ai-agents, judges]
status: implemented
related:
  - 0004-render-postgres.md
  - ../operations/configuration.md
  - ../operations/deployment-render.md
source_of_truth: docs/prd-anti-body-paypal.md sections 0, 13, 24.1; .env.example (QUEUE_CONNECTION, CACHE_STORE, SESSION_DRIVER).
last_verified: 2026-10-30
---

# ADR 0003: Database drivers over Redis

> **TL;DR**
> - Decision: `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`. No Redis, no Horizon.
> - Saves about $10/month (Render Key Value 256 MB) and setup hours. Volume is tens of invoices a month.
> - Status: implemented in `.env.example` and the default migrations (`cache`, `jobs`, `sessions` tables). Queue workers and scheduled jobs that use the drivers are planned.

## Status

Accepted, 2026-10-30 (decision first made in PRD v1.1, 2026-10-23). Implementation status: implemented for configuration; jobs and the worker process are planned.

## Context

| Factor | Detail |
|---|---|
| Load | One owner, 25 to 40 invoices a month, a handful of jobs per invoice (extraction, scoring, payout, Telegram), a reconcile job every 5 min |
| Cost | Render Key Value 256 MB costs about $10/month; total budget target is $13/month (PRD 24.1) |
| Locks | Payout transitions need row locks (`lockForUpdate()`); audit chain writes need a lock (`Cache::lock('audit-chain')`); the database cache store supports atomic locks through the `cache_locks` table |
| PayPal token | OAuth token cached until expiry minus 5 minutes; the database cache store is sufficient |

## Decision

| Concern | Driver | Table(s) |
|---|---|---|
| Queue | `database` | `jobs`, `job_batches`, `failed_jobs` |
| Cache and locks | `database` | `cache`, `cache_locks` |
| Session | `database` | `sessions` |
| Worker | `php artisan queue:work` under supervisord in the Render container (planned) | n/a |
| Scheduler | `php artisan schedule:work` in the same container (planned) | n/a |

## Consequences

| Type | Consequence |
|---|---|
| Positive | One fewer service to run, pay for and secure |
| Positive | Tests use SQLite `:memory:` with `QUEUE_CONNECTION=sync` and `CACHE_STORE=array` (phpunit.xml) |
| Negative | No Horizon dashboard; failed jobs inspected through `failed_jobs` and `php artisan queue:failed` |
| Negative | Higher latency than Redis; acceptable at this volume |
| Revisit trigger | Sustained queue backlog or lock contention, or many customers post-hackathon |

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Redis + Horizon | +$10/month and setup hours for no benefit at this scale |
| `sync` queue in production | Payout and extraction would run inside web requests; webhooks must respond 200 fast (PRD 16.4) |
