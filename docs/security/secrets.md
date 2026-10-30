---
title: Secrets and PII Handling
purpose: List every secret Anti-Body uses, where each lives, how it is rotated and masked, and the public-repo safety rules.
audience: [developers, owner, ai-agents]
status: mixed
related:
  - ./trust-boundaries.md
  - ./owasp-mapping.md
  - ../operations/configuration.md
  - ../operations/deployment-render.md
  - ../integrations/paypal-oauth.md
  - ../integrations/telegram.md
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
source_of_truth: .husky/pre-commit, .gitleaks.toml, .github/workflows/security.yml; docs/prd-anti-body-paypal.md sections 12.6, 18, 24.4; feature prompt section 5 (A04, A09, Always)
last_verified: 2026-10-30
---

# Secrets and PII Handling

> **TL;DR**
> - Secrets live only in Render env vars and the local git-ignored `.env`. `.env.example` holds names and placeholders only.
> - Agent MCP tokens and Telegram approval tokens are stored only as hashes.
> - Never log secrets, tokens, passwords, PayPal or Anthropic keys, or unnecessary personal data. Mask payout IDs in agent responses.
> - The public repo contains no real names, no real emails, no real invoices and no live keys.
> - **Status: mixed.** `.env` is git-ignored, the pre-commit staged secret scan and the gitleaks CI job are implemented (ABP-9, section 5a). Secret-specific env keys and masking code are planned.

## 1. Secret inventory

| Secret | Env var | Used by (planned) | Lives in | Rotation | Status |
|---|---|---|---|---|---|
| Laravel app key | `APP_KEY` | Encryption, signed URLs, sessions | Render env, `.env` | Rotate with `APP_PREVIOUS_KEYS` to keep old data readable | implemented (key generation) |
| PayPal client ID | `PAYPAL_CLIENT_ID` | `PayPalClient` | Render env, `.env` | Regenerate in developer dashboard, update env, redeploy | planned |
| PayPal client secret | `PAYPAL_CLIENT_SECRET` | `PayPalClient` | Render env, `.env` | Same; then `php artisan cache:forget paypal:oauth:token` | planned |
| PayPal webhook ID | `PAYPAL_WEBHOOK_ID` | Webhook verification | Render env, `.env` | Changes when webhook re-registered | planned |
| PayPal access token | none (cache key `paypal:oauth:token`) | `PayPalClient` | Database cache, encrypted, max 8 h | Expires automatically | planned |
| Anthropic API key | `ANTHROPIC_API_KEY` | Extractor, analyst, AP agent | Render env, `.env` | Rotate in Anthropic console | planned |
| Telegram bot token | `TELEGRAM_BOT_TOKEN` | Telegram slice | Render env, `.env` | `/revoke` in BotFather, re-run `setWebhook` | planned |
| Telegram webhook secret | `TELEGRAM_WEBHOOK_SECRET` | Callback verification | Render env, `.env` | New random value, re-run `setWebhook` | planned |
| Telegram owner chat ID | `TELEGRAM_OWNER_CHAT_ID` | Allowlist | Render env, `.env`, `users.telegram_chat_id` | Change when owner changes | planned |
| Approval token HMAC key | `ANTIBODY_TOKEN_HMAC_KEY` | Telegram approval token hashing | Render env, `.env` | Rotation invalidates open approval buttons (15 min max) | planned |
| Database URL | `DB_URL` | Postgres on Render | Render env (internal URL) | Rotate via Render | planned |
| Demo owner password | `DEMO_OWNER_PASSWORD` | Seeder | Render env, `.env` | Change before sharing judge access | planned |
| Agent MCP tokens | none | Sanctum | Hashed in `personal_access_tokens` | Revoke in Agents resource or command, issue new with `php artisan antibody:agent-token` | planned |
| Telegram approval tokens | none | Approval slice | Hashed in `approvals.token_hash` | Single use, 15 min | planned |

## 2. Rules

| Rule | Detail |
|---|---|
| Env only | Read secrets through `config/*.php` with `env()`; application code uses `config()` only |
| `.env.example` | Names and placeholders only (for example `PAYPAL_CLIENT_SECRET=`). Never real values |
| `.env` | Git-ignored (implemented in `.gitignore`) |
| Shown once | Agent tokens are printed once at creation and never again |
| Comparison | `hash_equals()` for tokens and secrets |
| Generation | `Str::random()` or `random_bytes()` |
| Outbound URL logging | Never log Telegram API URLs (token in path) or Authorization headers |
| Errors | Exception messages returned to clients contain no secrets or upstream bodies |
| Screenshots and video | Hide `.env`, tokens and emails (PRD 20 retake risks) |

## 3. Never-log list

| Item | Why |
|---|---|
| `PAYPAL_CLIENT_SECRET`, access tokens, `Authorization` headers | Full PayPal account access |
| `ANTHROPIC_API_KEY` | Billing and data access |
| `TELEGRAM_BOT_TOKEN`, Telegram API URLs | Bot takeover |
| `TELEGRAM_WEBHOOK_SECRET`, `ANTIBODY_TOKEN_HMAC_KEY` | Forge callbacks |
| Agent bearer tokens, approval tokens (plaintext) | Replay |
| Passwords | Account takeover |
| Full PDF contents, full PayPal payloads in app logs | Unnecessary personal data; payloads are kept in `webhook_events.payload` only |

## 4. Masking

| Value | Where | Mask |
|---|---|---|
| `payout_item_id` | MCP `payment_status` response | Last 4 chars visible, rest `*` (for example `************7Q2K`) |
| `payout_batch_id` | MCP responses | Not returned |
| Payee email | Agent responses | Not returned; reasons use labels |
| Owner Telegram chat ID | Logs | Not logged |

## 5. PII

| Data | Stored | Minimization |
|---|---|---|
| Owner name, email | `users` | Required for login |
| Vendor PayPal emails | `vendors` | Required to pay; sandbox only |
| Invoice PDFs | Private disk | Raw PDF deletion after 90 days is Roadmap |
| Webhook payloads | `webhook_events.payload` | Needed for audit and reprocessing |

## 5a. Secret scanning (implemented, ABP-9)

| Layer | What it does | Where |
|---|---|---|
| Pre-commit hook | Blocks `.env` and `.env.*` files except `.env.example`; scans only the added staged lines for private keys, Anthropic keys, Telegram bot tokens, PayPal access tokens and non-placeholder values of the known secret variables. Prints file and pattern name, never the value | `.husky/pre-commit` |
| gitleaks in CI | Scans the full history on every push to `main` and every pull request with default rules plus project rules; placeholders starting `your-`, `change-me` or `generate-` are allowed; `.env.example` and `tests/Fixtures/` are allowlisted | `.github/workflows/security.yml` job `gitleaks`, `.gitleaks.toml` |
| Dependency review | Fails pull requests that add dependencies with high severity advisories | `security.yml` job `dependency-review` |

The hook can be bypassed locally (`--no-verify`, `HUSKY=0`), which is forbidden; gitleaks in CI is the backstop. Decision record: [../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md).

If a secret is committed or pushed: rotate it first (section 1 lists how). Rewriting history is an owner decision and never replaces rotation. See [../operations/incident-handling.md](../operations/incident-handling.md).

## 6. Public repo safety (PRD 18, C10)

| Rule | Detail |
|---|---|
| Names | Fictional brands only (Kahon Goods, BoxPro Supplies, PrintHub, TapeWorks, Filler Co, LensLab Photo). Author shown as EHIBE only |
| Emails | Placeholder `.example` or sandbox emails provided via env, never real personal emails (OQ-13) |
| Invoices | Generated demo PDFs only |
| Keys | No live keys ever; sandbox keys never committed |
| Local paths | No machine-specific or client project paths in committed docs |
