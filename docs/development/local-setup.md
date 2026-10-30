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
  - commit-and-branch-rules.md
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
source_of_truth: composer.json `scripts`, package.json `scripts`, .husky/, .env.example, docs/prd-anti-body-paypal.md sections 23 and 24.3
last_verified: 2026-10-30
---

# Local Setup

> **TL;DR**
> - Implemented today: SQLite + `composer run setup` + `composer run dev`. The Filament panel is at `/admin`.
> - Create the owner login with `php artisan make:filament-user`.
> - `composer run setup` also registers the Git hooks (`npm run prepare`). The full local gate is `composer ci:check`; the hooks run it before every push. On Windows, commit and push from PowerShell. See section 7.
> - Planned: Docker Compose (app + Postgres), `antibody:demo-reset`, a tunnel for PayPal and Telegram webhooks, seeded demo data.
> - Target M7: a clean machine reaches a working demo in under 10 minutes by following the README.

## 1. Prerequisites

| Tool | Version | Status | Check command |
|---|---|---|---|
| PHP | 8.5.x (verified with 8.5.11; `composer.json` requires `^8.4`, CI runs 8.5) | Required | `php -v` |
| PHP extensions | `pdo_sqlite`, `mbstring`, `openssl`, `intl`, `fileinfo`, `zip` | Required | `php -m` |
| PHP extension `pdo_pgsql` | any | Planned (Render Postgres parity) | `php -m` |
| Composer | 2.x | Required | `composer -V` |
| Node.js + npm | Node 20+ (Vite 8 requirement; CI runs Node 22) | Required | `node -v` |
| SQLite | bundled with PHP `pdo_sqlite` | Required | n/a |
| Git | any | Required | `git --version` |
| Docker + Docker Compose | latest | Planned | `docker compose version` |
| Tunnel (`cloudflared` or `ngrok`) | latest | Planned (webhooks) | `cloudflared --version` |

## 2. Implemented path (SQLite)

**Status: implemented.** The scripts below come from `composer.json` `scripts`.

| Step | Command | What the command does |
|---|---|---|
| 1 | `git clone https://github.com/EHIBE/anti-body-paypal.git && cd anti-body-paypal` | Gets the source |
| 2 | `composer run setup` | Runs `composer install`, copies `.env.example` to `.env` when `.env` is missing, `php artisan key:generate`, `php artisan migrate --force`, `npm install --ignore-scripts`, `npm run prepare` (registers the Husky Git hooks), `npm run build` |
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
| Tests pass | `php artisan test --compact` | All green. One test is skipped outside the CI `postgres` job (`DatabaseDriverTest`) |
| Style | `vendor/bin/pint --dirty --format agent` | No changes needed |
| Full gate | `composer ci:check` | Validate, Rector dry run, Pint test mode, Larastan, audits, asset build, parallel tests |
| Routes | `php artisan route:list --except-vendor` | `/admin` panel routes, `/up` health |
| Health | `curl http://localhost:8000/up` | HTTP 200 |

## 7. Tooling and Git hooks

**Status: implemented (ABP-9).** Decision record: [../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md).

| Tool | Installed version | Config file |
|---|---|---|
| larastan/larastan (on phpstan/phpstan 2.3.4) | v3.13.2 | `phpstan.neon` (level max) |
| phpstan/phpstan-strict-rules | 2.1.0 | included from `phpstan.neon` |
| rector/rector | 2.8.3 | `rector.php` |
| laravel/pint | 1.32.1 | `pint.json` |
| husky (npm) | 9.1.7 | `.husky/` |
| pestphp/pest with paratest | 5.3.4 with v7.27.5 (enables `--parallel`) | `phpunit.xml`, `tests/Pest.php` |
| gitleaks (CI and optional local run) | 8.30.1 | `.gitleaks.toml` |

The larastan, strict-rules, rector and husky dev dependencies were approved for ABP-9 only.

| Task | Command | Defined in |
|---|---|---|
| Fix style and refactors | `composer fix` (Rector, then Pint) | `composer.json` scripts |
| Check only (CI form) | `composer check` | same |
| Whole gate | `composer ci:check` (also `npm run ci:check`) | same, `package.json` |
| Types only | `composer types:check` | same |
| Register hooks again | `npm run prepare` | `package.json` |

The list of checks inside each script lives only in `composer.json`. Do not copy it into other docs.

| Hook | Runs on | What it does (details in the file) |
|---|---|---|
| `.husky/commit-msg` | `git commit` | Validates the subject format and rejects attribution trailers. Format rules: [commit-and-branch-rules.md](commit-and-branch-rules.md) |
| `.husky/pre-commit` | `git commit` | Guards against partly staged files and `.env` files, scans added lines for secrets, runs Rector and Pint, re-stages already staged paths |
| `.husky/pre-push` | `git push` | Allows only `task/ABP-#-slug` branches, never `main`, tags or remote branch deletion, then runs `npm run ci:check` |

| Rule | Detail |
|---|---|
| Hooks registration | `.npmrc` sets `ignore-scripts=true`, so hooks register only through `composer run setup` or `npm run prepare`. The only local Git config change is `core.hooksPath=.husky/_` |
| Windows | Commit and push from PowerShell. The hooks reach PHP through `npm run` (`cmd.exe`); `php` and `composer` are not on the Git Bash `PATH` in this setup |
| Missing tools | Each hook checks its own tools (`git`, `npm`, `grep`, `awk`, `xargs` as needed) and exits non-zero with a message |
| Bypass | Never use `--no-verify` or `HUSKY=0`. CI runs the same gate and is the backstop |
| Speed | Rector and Pint run on the whole project at each commit, so commits take longer than with staged-file linters |
| CI | `.github/workflows/tests.yml` and `.github/workflows/security.yml`. See [testing-strategy.md](testing-strategy.md#8-local-hooks-and-ci-pipeline) |

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| Hook says `php` or `composer` not found (Windows) | Commit and push from PowerShell |
| Hooks do not run after a fresh clone | `npm run prepare` (or `composer run setup`) |
| Pre-commit blocks on a file with staged and unstaged changes | `git add` the rest of the file, or undo the unstaged part |
| Pre-commit blocks on a secret finding | Remove the value; use a `your-...` placeholder. The hook prints the file and pattern name, never the value |
| `Unable to locate file in Vite manifest` | `npm run build` or keep `npm run dev` running |
| `database.sqlite does not exist` | `touch database/database.sqlite` then `php artisan migrate` |
| Filament assets missing | `php artisan filament:upgrade` (runs on `post-autoload-dump`) |
| Queued jobs never run | Start `php artisan queue:listen` or use `composer run dev` |
