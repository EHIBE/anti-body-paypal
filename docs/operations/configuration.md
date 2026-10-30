---
title: Configuration and Environment Variables
purpose: List every environment variable and config key Anti-Body uses or plans to use, with purpose, placeholder, secrecy and status.
audience: [developers, judges, ai-agents]
status: mixed
related:
  - deployment-render.md
  - ../security/secrets.md
  - ../development/local-setup.md
source_of_truth: .env.example (implemented keys), docs/prd-anti-body-paypal.md section 24.4 (planned keys)
last_verified: 2026-10-30
---

# Configuration and Environment Variables

> **TL;DR**
> - Implemented keys are the Laravel defaults in `.env.example` plus `APP_NAME=anti-body-paypal`.
> - Planned keys (PRD 24.4) cover PayPal, Anthropic, Telegram, the token HMAC key and demo owner. They are added to `.env.example` with placeholders by the slice that first reads them.
> - Application code reads config through `config()` only. `env()` appears only inside `config/*.php`. Planned project config file: `config/antibody.php`; third-party keys in `config/services.php`.
> - Secrets live only in local `.env` (git-ignored) and Render env vars. `.env.example` holds names and placeholders only.

## 1. Application and framework (implemented)

| Variable | Purpose | `.env.example` value | Production value | Secret | Config key |
|---|---|---|---|---|---|
| `APP_NAME` | Identifier, cache and cookie prefixes, default panel brand | `anti-body-paypal` | `anti-body-paypal` | No | `app.name` |
| `APP_ENV` | Environment | `local` | `production` | No | `app.env` |
| `APP_KEY` | Encryption key | empty (generated) | generated per env | Yes | `app.key` |
| `APP_DEBUG` | Debug pages | `true` | `false` (required) | No | `app.debug` |
| `APP_URL` | Base URL, webhook URLs | `http://localhost:8000` | `https://<render-host>` | No | `app.url` |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Locale | `en`, `en`, `en_US` | same | No | `app.*` |
| `APP_MAINTENANCE_DRIVER` | Maintenance mode store | `file` | `file` | No | `app.maintenance.driver` |
| `BCRYPT_ROUNDS` | Password hashing cost | `12` | `12` | No | `hashing.bcrypt.rounds` |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL` | Logging | `stack`, `single`, `null`, `debug` | `stack`, `stderr` (planned), `null`, `info` | No | `logging.*` |
| `DB_CONNECTION` | Database driver | `sqlite` | `pgsql` | No | `database.default` |
| `DB_URL` | Postgres connection string | not set | Render internal URL | Yes | `database.connections.pgsql.url` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Discrete DB settings (commented out) | commented | unused when `DB_URL` set | `DB_PASSWORD` yes | `database.connections.*` |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN` | Sessions | `database`, `120`, `false`, `/`, `null` | `database`, `120`, `true` (planned), `/`, `null` | No | `session.*` |
| `BROADCAST_CONNECTION` | Broadcasting | `log` | `log` | No | `broadcasting.default` |
| `FILESYSTEM_DISK` | Default disk | `local` (private) | `local` | No | `filesystems.default` |
| `QUEUE_CONNECTION` | Queue driver | `database` | `database` | No | `queue.default` |
| `CACHE_STORE`, `CACHE_PREFIX` | Cache | `database`, commented | `database` | No | `cache.*` |
| `MEMCACHED_HOST`, `REDIS_*` | Unused defaults (Redis dropped, ADR 0003) | defaults | unused | `REDIS_PASSWORD` yes | `database.redis.*` |
| `MAIL_*` | Mail (unused in v1) | `log` mailer, placeholders | `log` | `MAIL_PASSWORD` yes | `mail.*` |
| `AWS_*` | Unused defaults | empty | unused | Yes | `filesystems.disks.s3.*` |
| `VITE_APP_NAME` | Frontend app name | `${APP_NAME}` | same | No | n/a |

## 2. Anti-Body keys (planned, PRD 24.4)

| Variable | Purpose | Placeholder for `.env.example` | Secret | Planned config key | Read by slice |
|---|---|---|---|---|---|
| `PAYPAL_MODE` | `sandbox` only in v1 | `sandbox` | No | `services.paypal.mode` | Payment |
| `PAYPAL_CLIENT_ID` | REST app client ID | `your-sandbox-client-id` | Yes | `services.paypal.client_id` | Payment |
| `PAYPAL_CLIENT_SECRET` | REST app secret | `your-sandbox-client-secret` | Yes | `services.paypal.client_secret` | Payment |
| `PAYPAL_WEBHOOK_ID` | Webhook ID for verify-webhook-signature | `your-webhook-id` | Yes (treat as secret) | `services.paypal.webhook_id` | Webhook |
| `ANTHROPIC_API_KEY` | Extractor, analyst, AP agent | `your-anthropic-api-key` | Yes | laravel/ai provider config (planned) | Extraction, Guard, AgentGateway |
| `ANTIBODY_EXTRACTOR_MODEL` | Extractor model ID | `claude-haiku-5-5` (UNVERIFIED, OQ-12; fallback `claude-haiku-4-5`) | No | `antibody.extractor_model` | Extraction |
| `ANTIBODY_ANALYST_MODEL` | Analyst model ID | `claude-sonnet-5-5` | No | `antibody.analyst_model` | Guard |
| `TELEGRAM_BOT_TOKEN` | Bot API token | `your-telegram-bot-token` | Yes | `services.telegram-bot-api.token` | Telegram |
| `TELEGRAM_WEBHOOK_SECRET` | `X-Telegram-Bot-Api-Secret-Token` value (header name UNVERIFIED, OQ-05) | `generate-a-random-secret` | Yes | `services.telegram-bot-api.webhook_secret` | Telegram |
| `TELEGRAM_OWNER_CHAT_ID` | Allowlisted chat for approvals | `123456789` | No (but private) | `services.telegram-bot-api.owner_chat_id` | Telegram |
| `ANTIBODY_TOKEN_HMAC_KEY` | HMAC key for single-use Telegram approval tokens | `generate-32-random-bytes-base64` | Yes | `antibody.token_hmac_key` | Approval, Telegram |
| `DEMO_OWNER_EMAIL` | Seeded owner login | `owner@example.test` | No | `antibody.demo.owner_email` | Shared seeders |
| `DEMO_OWNER_PASSWORD` | Seeded owner password | `change-me` | Yes | `antibody.demo.owner_password` | Shared seeders |

## 3. Planned non-env config values (`config/antibody.php`)

| Key | Default | Source |
|---|---|---|
| `antibody.paypal.base_url` | `https://api-m.sandbox.paypal.com` | PRD 16.1 |
| `antibody.paypal.connect_timeout_seconds` / `antibody.paypal.timeout_seconds` / `antibody.paypal.oauth_timeout_seconds` | 5 / 20 / 10 (planned values, confirm in B1; see [../integrations/paypal-payouts.md](../integrations/paypal-payouts.md)) | A10, 4.5 |
| `antibody.paypal.retry_backoff_seconds` | `[5, 30, 120]` | PRD 10.2 |
| `antibody.paypal.circuit_breaker_minutes` | 10 | PRD 10.2 |
| `antibody.paypal.token_cache_margin_seconds` | 300 | PRD 16.1 |
| `antibody.extractor.max_tokens` / `timeout_seconds` | 1500 / 60 | PRD 17.1, 17.2 |
| `antibody.analyst.max_tokens` | 600 | PRD 17.1 |
| `antibody.ap_agent.max_steps` | 12 | PRD 17.1 |
| `antibody.uploads.max_kilobytes` | 5120 | FR-04 |
| `antibody.approval.token_ttl_minutes` | 15 | FR-27 |
| `antibody.reconcile.stale_after_minutes` | 2 | FR-23 |
| `antibody.rate_limits.mcp_per_minute` / `upload_per_minute` / `telegram_callback_per_minute` | 30 / 10 / 20 | PRD 18 |

Guard policy values (`auto_ceiling_minor` 50000, `max_per_payment_minor` 150000, `weekly_budget_minor` 300000, `allowed_currencies` `["USD"]`, `paused`, `hold_expiry_hours` 72) live in the `policies` table row, not in env. The absolute auto ceiling $500.00 is a Guard slice constant. See [../architecture/guard-spec.md](../architecture/guard-spec.md).

## 4. Rules

| # | Rule |
|---|---|
| 1 | New keys go into `.env.example` with placeholders only, in the same commit as the code that reads them |
| 2 | Read keys through `config()`, never `env()` outside `config/` |
| 3 | `php artisan config:show antibody` to inspect values locally (never paste output containing secrets) |
| 4 | Production runs `php artisan optimize` (config cache), so `env()` outside config returns null |
| 5 | Secure defaults: `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `PAYPAL_MODE=sandbox` outside local |
