---
title: Deployment on Render
purpose: Describe the planned Render hosting for the hackathon demo, including services, container processes, budget and deploy steps.
audience: [developers, judges, ai-agents]
status: planned
related:
  - configuration.md
  - runbook.md
  - ../decisions/0003-database-drivers-over-redis.md
  - ../decisions/0004-render-postgres.md
  - ../product/hackathon-compliance.md
source_of_truth: docs/prd-anti-body-paypal.md section 24
last_verified: 2026-10-30
---

# Deployment on Render

> **TL;DR**
> - Planned hosting: Render web service `anti-body-paypal-web` (Starter, Docker, Singapore) + Render Postgres `anti-body-paypal-db` (basic-256mb, Singapore). $13/month, covered by the $50 hackathon credit for about 3.8 months.
> - One container runs nginx, php-fpm, `queue:work` and `schedule:work` under supervisord. Pre-deploy: `php artisan migrate --force && php artisan optimize`.
> - Production is the demo environment and uses sandbox PayPal only.
> - Status: planned (B2, P2). No `Dockerfile`, `render.yaml` or CI workflow exists on 2026-10-30.

## 1. Services

| Render service | Plan | Region | Runs | Status |
|---|---|---|---|---|
| `anti-body-paypal-web` | Starter ($7/month), Docker | Singapore (OQ-10) | nginx + php-fpm + `php artisan queue:work` + `php artisan schedule:work` under supervisord | Planned |
| `anti-body-paypal-db` | Postgres basic-256mb ($6/month) | Singapore | Database, private network, PITR | Planned |

## 2. Hosting decision summary (PRD 24.1)

| Option | Monthly | Verdict |
|---|---|---|
| A. Render web Starter + Render Postgres basic-256mb | $13 | Chosen. One provider, private network, always on, Render sponsor prize (C16) |
| B. Render web Starter + Aiven free MySQL | $7 | Fallback |
| C. Render free web + free Postgres | $0 | Rejected: spins down after 15 min idle, free Postgres expires after 30 days |
| D. Laravel Cloud Starter | $5 + usage | Rejected: no Render sponsor prize |
| Redis (Render Key Value 256 MB) | +$10 | Skipped, database drivers suffice ([ADR 0003](../decisions/0003-database-drivers-over-redis.md)) |

Timeline: Option A from Nov 2 (free plan acceptable earlier during build), credits applied at signup via the hackathon claim link. Keep hosting at least through Dec 15 (judging end, C15) and through Jan 2027 for portfolio use.

## 3. Container (planned)

| Item | Plan | Open question |
|---|---|---|
| Base image | Official PHP 8.5 FPM image | OQ-04: exact tag and extensions (`pdo_pgsql`, `intl`, `gd` optional) |
| Extensions | `pdo_pgsql`, `intl`, `mbstring`, `zip`, `bcmath`, `opcache` | OQ-04 |
| Assets | `npm ci && npm run build` in a build stage | |
| Process manager | supervisord with 4 programs: nginx, php-fpm, `queue:work --tries=3 --max-time=3600`, `schedule:work` | |
| Health check | `GET /up` (implemented in `bootstrap/app.php`) | |
| Storage | Private local disk for PDFs; Render disk or object storage for persistence is a decision for B2 | Flag: Render web service filesystem is ephemeral across deploys |

## 4. Deploy steps (planned)

| # | Step |
|---|---|
| 1 | Create `anti-body-paypal-db` in Singapore; copy the internal connection URL |
| 2 | Create `anti-body-paypal-web` from GitHub `EHIBE/anti-body-paypal`, runtime Docker, region Singapore |
| 3 | Set env vars from [configuration.md](configuration.md): `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `DB_CONNECTION=pgsql`, `DB_URL`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, PayPal, Anthropic, Telegram, `ANTIBODY_TOKEN_HMAC_KEY`, demo owner |
| 4 | Pre-deploy command: `php artisan migrate --force && php artisan optimize` |
| 5 | Deploy; check `https://<host>/up` returns 200 |
| 6 | Seed demo: `php artisan db:seed --force` then `php artisan antibody:demo-reset` (Render shell) |
| 7 | Register PayPal webhook `https://<host>/webhooks/paypal` and copy the webhook ID into `PAYPAL_WEBHOOK_ID` |
| 8 | Register Telegram webhook `https://<host>/webhooks/telegram` with the secret token |
| 9 | Create the judge MCP token: `php artisan antibody:agent-token "Judge agent"` and put it in README Judge access (C11) |
| 10 | Verify: log in at `/admin`, upload the BoxPro fake invoice, see BLOCK (US-11) |

## 5. CI auto-deploy (planned)

| Trigger | Action |
|---|---|
| Push to `main` with CI green | Render auto-deploy (PRD 22 step 7) |
| Nightly | Eval suite with `ANTHROPIC_API_KEY` secret (does not deploy) |

## 6. Environments

| Environment | Where | PayPal | Notes |
|---|---|---|---|
| `local` | Developer machine; SQLite today, Docker Compose + Postgres planned | Sandbox | Tunnel for webhooks |
| `testing` | `phpunit.xml`, SQLite `:memory:` | Faked | |
| `production` (demo) | Render | Sandbox only | No live PayPal money (non-goal) |

## 7. Budget

| Item | Monthly | Through Jan 2027 |
|---|---|---|
| Web Starter | $7 | |
| Postgres basic-256mb | $6 | |
| Total | $13 | About $39 for Nov to Jan, inside the $50 credit (R7) |
