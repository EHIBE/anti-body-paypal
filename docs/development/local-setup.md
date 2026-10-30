---
title: Local Setup
purpose: Boot Anti-Body on a developer or judge machine, with the implemented SQLite path today and the planned Docker path later.
audience: [developers, judges, ai-agents]
status: mixed
related:
  - ../operations/configuration.md
  - ../operations/demo-reset.md
  - ../integrations/paypal-sandbox-setup.md
  - ../integrations/telegram.md
  - testing-strategy.md
source_of_truth: composer.json `scripts`, .env.example, docs/prd-anti-body-paypal.md sections 23 and 24.3
last_verified: 2026-10-30
---

# Local Setup

> **TL;DR**
> - Implemented today: SQLite + `composer run setup` + `composer run dev`. The Filament panel is at `/admin`.
> - Create the owner login with `php artisan make:filament-user`.
> - Planned: Docker Compose (app + Postgres), `antibody:demo-reset`, a tunnel for PayPal and Telegram webhooks, seeded demo data.
> - Target M7: a clean machine reaches a working demo in under 10 minutes by following the README.

## 1. Prerequisites

| Tool | Version | Status | Check command |
|---|---|---|---|
| PHP | 8.5.x (verified with 8.5.11) | Required | `php -v` |
| PHP extensions | `pdo_sqlite`, `mbstring`, `openssl`, `intl`, `fileinfo`, `zip` | Required | `php -m` |
| PHP extension `pdo_pgsql` | any | Planned (Render Postgres parity) | `php -m` |
| Composer | 2.x | Required | `composer -V` |
| Node.js + npm | Node 20+ (Vite 8 requirement) | Required | `node -v` |
| SQLite | bundled with PHP `pdo_sqlite` | Required | n/a |
| Git | any | Required | `git --version` |
| Docker + Docker Compose | latest | Planned | `docker compose version` |
| Tunnel (`cloudflared` or `ngrok`) | latest | Planned (webhooks) | `cloudflared --version` |

## 2. Implemented path (SQLite)

**Status: implemented.** The scripts below come from `composer.json` `scripts`.

| Step | Command | What the command does |
|---|---|---|
| 1 | `git clone https://github.com/EHIBE/anti-body-paypal.git && cd anti-body-paypal` | Gets the source |
| 2 | `composer run setup` | Runs `composer install`, copies `.env.example` to `.env` when `.env` is missing, `php artisan key:generate`, `php artisan migrate --force`, `npm install --ignore-scripts`, `npm run build` |
| 3 | `touch database/database.sqlite` (only if migrate reports a missing file) | `DB_CONNECTION=sqlite` uses `database/database.sqlite` |
| 4 | `php artisan make:filament-user --no-interaction --name="Demo Owner" --email="owner@example.test" --password="<choose-a-strong-password>"` | Creates the owner login for the `admin` panel |
| 5 | `composer run dev` | Runs `php artisan dev` with the Composer process timeout disabled (local server, queue, logs, Vite) |
| 6 | Open `http://localhost:8000/admin` | Filament login (`APP_URL=http://localhost:8000` in `.env.example`) |

Alternative to step 5 when `php artisan dev` is not wanted: run `php artisan serve`, `php artisan queue:listen --tries=1` and `npm run dev` in three terminals.

## 3. Environment variables for local work

The full list lives in [../operations/configuration.md](../operations/configuration.md). Local defaults from `.env.example`:

| Variable | Local value | Note |
|---|---|---|
| `APP_NAME` | `anti-body-paypal` | Identifier; Filament brand name "Anti-Body" is planned for B7 |
| `APP_ENV` | `local` | |
| `APP_DEBUG` | `true` | Local only. Must be `false` everywhere else |
| `DB_CONNECTION` | `sqlite` | |
| `QUEUE_CONNECTION` | `database` | Run a worker for queued jobs |
| `CACHE_STORE` | `database` | |
| `SESSION_DRIVER` | `database` | |

**Status: planned.** The five secrets the README asks a judge to fill (PRD 23 step 6): `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `ANTHROPIC_API_KEY`, `ANTIBODY_TOKEN_HMAC_KEY`. Telegram variables are optional.

## 4. Planned path (Docker Compose + Postgres)

**Status: planned (B2).** No `Dockerfile`, `docker-compose.yml` or `Makefile` exists yet.

| Step | Planned command | Source |
|---|---|---|
| 1 | `cp .env.example .env` and fill 5 variables | PRD 23 step 6 |
| 2 | `docker compose up -d` (services: app, Postgres) | PRD 24.3 |
| 3 | `docker compose exec app php artisan migrate --seed` | PRD 23 step 6 |
| 4 | `docker compose exec app php artisan antibody:demo-reset` | FR-42 |
| 5 | Start a tunnel: `cloudflared tunnel --url http://localhost:8000` | PRD 13, 24.3 |
| 6 | Register `https://<tunnel-host>/webhooks/paypal` in the PayPal sandbox app | [../integrations/paypal-sandbox-setup.md](../integrations/paypal-sandbox-setup.md) |
| 7 | Optional: register `https://<tunnel-host>/webhooks/telegram` with Telegram `setWebhook` | [../integrations/telegram.md](../integrations/telegram.md) |
| 8 | `make demo` wrapper for steps 2 to 4 | US-10 |

## 5. MCP client (planned)

**Status: planned (B6).** Claude Desktop connects to the local stdio server `anti-body` (FR-30). The config snippet and MCP Inspector command are in [../api/mcp-tools.md](../api/mcp-tools.md).

## 6. Verify the install

| Check | Command | Expected |
|---|---|---|
| Tests pass | `php artisan test --compact` | All green |
| Style | `vendor/bin/pint --dirty --format agent` | No changes needed |
| Routes | `php artisan route:list --except-vendor` | `/admin` panel routes, `/up` health |
| Health | `curl http://localhost:8000/up` | HTTP 200 |

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| `Unable to locate file in Vite manifest` | `npm run build` or keep `npm run dev` running |
| `database.sqlite does not exist` | `touch database/database.sqlite` then `php artisan migrate` |
| Filament assets missing | `php artisan filament:upgrade` (runs on `post-autoload-dump`) |
| Queued jobs never run | Start `php artisan queue:listen` or use `composer run dev` |
