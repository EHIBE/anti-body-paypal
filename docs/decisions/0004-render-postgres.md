---
title: "ADR 0004: Render Postgres for production"
purpose: Record the decision to host Anti-Body on a Render web service with Render Postgres, keeping SQLite for local development and tests.
audience: [developers, ai-agents, judges]
status: planned
related:
  - 0003-database-drivers-over-redis.md
  - ../operations/deployment-render.md
  - ../architecture/data-model.md
source_of_truth: docs/prd-anti-body-paypal.md sections 0, 13, 24.
last_verified: 2026-10-30
---

# ADR 0004: Render Postgres for production

> **TL;DR**
> - Decision: production runs on Render: `anti-body-paypal-web` (Starter, Docker, Singapore) and `anti-body-paypal-db` (Postgres basic-256mb, Singapore). $7 + $6 = $13/month, covered by the $50 hackathon credit for about 3.8 months.
> - Local development uses SQLite (`database/database.sqlite`); tests use SQLite `:memory:`. Migrations must be portable across SQLite and Postgres.
> - Fallback: Aiven free MySQL. Rejected: Render free tier, Laravel Cloud (no Render sponsor prize).
> - Status: accepted. SQLite local and test setup implemented. Render services, Dockerfile and `render.yaml` planned.

## Status

Accepted, 2026-10-30 (decision first made in PRD v1.1). Implementation status: local SQLite implemented; Render planned (from Nov 2 per PRD 24.1, free plan acceptable during build).

## Context (PRD 24.1, researched for 2+ months)

| Option | Monthly | 2 months | Notes |
|---|---|---|---|
| A. Render web Starter + Render Postgres basic-256mb (chosen) | $7 + $6 = $13 | $26 | One provider, private network, Postgres PITR, always on, Render sponsor prize (C16) |
| B. Render web Starter + Aiven free MySQL | $7 + $0 = $7 | $14 | Cheapest, keeps MySQL. 1 GB storage, no time limit, region cannot be chosen, unused services may be powered off |
| C. Render free web + free Postgres | $0 | n/a | Free web spins down after 15 min idle (about a minute to wake) which breaks webhooks and first impressions. Free Postgres expires after 30 days |
| D. Laravel Cloud Starter | $5 + usage | varies | Good Laravel fit, no Render sponsor prize |
| Redis (Render Key Value 256 MB) | +$10 | +$20 | Skipped ([ADR 0003](0003-database-drivers-over-redis.md)) |

Render native runtimes do not include PHP, so deployment uses Docker (base image tag OQ-04).

## Decision

| Item | Value |
|---|---|
| Web service | `anti-body-paypal-web`, Starter, Docker, region Singapore (OQ-10). Runs nginx + php-fpm + `queue:work` + `schedule:work` under supervisord |
| Database | `anti-body-paypal-db`, Postgres basic-256mb, Singapore |
| Pre-deploy command | `php artisan migrate --force && php artisan optimize` |
| Environments | `local` (SQLite today; Docker Compose with Postgres planned), `production` demo on Render with sandbox PayPal only |
| Hosting period | At least through Dec 15, 2026 (judging end, C15) and through Jan 2027 for portfolio use |

## Consequences

| Type | Consequence |
|---|---|
| Positive | One provider, one bill, private network, PITR, sponsor prize eligibility |
| Negative | SQLite and Postgres differ: partial indexes, JSON (`jsonb` on Postgres, text on SQLite), check constraints, case sensitivity. Migrations use only portable features or guarded raw statements ([../architecture/data-model.md](../architecture/data-model.md)) |
| Negative | The PRD 14.2 "DB role without UPDATE or DELETE on `audit_logs`" and the Postgres trigger (S) apply only on Postgres |
| Negative | Tests on SQLite may miss Postgres-only behavior; CI plans a Postgres service container (PRD 22) |

## Alternatives considered

See the context table. MySQL was the original default; Render has no managed MySQL, which drove the switch to Postgres.
