---
name: secrets-and-pii-hygiene
description: "Use whenever adding or touching configuration, env vars, logging, exception messages, API or MCP responses, seeders, factories, demo PDFs, docs, README, screenshots, or commits in the public Anti-Body repo. Triggers: .env, .env.example, config/services.php, API key, client secret, bot token, webhook secret, agent token, Log::, report(), seed data, demo emails, real names, masking payout IDs. Keeps secrets out of code, logs, errors, responses, docs, seed data, and screenshots. Do not use for unrelated refactors."
---

# Secrets and PII hygiene

The repo is public (C7). Spec: [docs/security/secrets.md](../../../docs/security/secrets.md), [docs/operations/configuration.md](../../../docs/operations/configuration.md). PRD 12.6 and 18.

## When to use

- Adding a config key or env var.
- Writing logs, exception reports, error responses, MCP outputs.
- Writing seeders, factories, demo PDFs, docs, or README text.

## Secret locations (PRD 12.6)

| Secret | Lives in | Never in |
|---|---|---|
| `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET` | Render env vars, local `.env` | Repo, agents, logs, MCP responses |
| PayPal access token | App cache, until expiry minus 5 minutes | Logs |
| `ANTHROPIC_API_KEY` | Render env vars, local `.env` | Repo, logs |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` | Render env vars, local `.env` | Repo, logs |
| `ANTIBODY_TOKEN_HMAC_KEY` | Render env vars, local `.env` | Repo, logs |
| Agent MCP tokens | Hashed in `personal_access_tokens` | Plaintext after creation |
| Telegram approval tokens | Hashed in `approvals.token_hash` | Plaintext after send |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Add the key to a `config/` file read through `env()` there only. Code reads `config('...')`.
2. Add the name to `.env.example` with an empty or placeholder value. Never a real value.
3. Document it in `docs/operations/configuration.md`.
4. Log context without secrets, tokens, full payout IDs, or unnecessary personal data.
5. Mask payout item IDs in agent responses (`payout_item_id_masked`).
6. Seed and demo data use fictional brands and sandbox or `.example` emails only. Never real names, real emails, or real invoices.
7. Before a screenshot or video take, hide `.env`, tokens, and emails (PRD 20 retake risks).

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\Payment\Support;

final class PayoutIdMasker
{
    public function mask(?string $payoutItemId): ?string
    {
        if ($payoutItemId === null) {
            return null;
        }

        return str_repeat('*', max(0, strlen($payoutItemId) - 4)).substr($payoutItemId, -4);
    }
}
```

## Checklist

- [ ] No `env()` outside `config/`.
- [ ] `.env.example` has names only.
- [ ] No secret in `Log::`, exception messages, Filament notifications, or MCP output.
- [ ] Tokens shown once at creation (`antibody:agent-token`), stored hashed.
- [ ] Docs and commits contain no owner real name or email, no keys.

## Common mistakes

- Logging the full PayPal or Anthropic HTTP response including headers.
- Putting a real-looking domain in seed data (see OQ-13).
- Echoing the plaintext token in a second command run.

## Verification

```bash
git grep -nE "(sk-ant-|client_secret|BEGIN PRIVATE KEY|bot[0-9]{6,}:)" -- . ":(exclude)vendor"
git grep -n "env(" -- app routes database
```
