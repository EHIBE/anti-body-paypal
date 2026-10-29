---
name: webhook-signature-verification
description: "Use whenever writing or reviewing inbound webhook or callback handling in Anti-Body: PayPal webhooks (`POST /webhooks/paypal`, verify-webhook-signature, event dedupe, payout event mapping) and Telegram updates and inline-button callbacks (`POST /webhooks/telegram`, secret header, allowlisted chat ID, single-use HMAC approval token, 15 minute expiry, replay). Triggers: webhook route, CSRF exclusion, webhook_events table, signature, callback_query, token_hash, replay, Webhooks Simulator. Do not use for outbound PayPal payout calls (use payment-idempotency-and-state-machine)."
---

# Webhook signature verification

Nothing from outside changes state until it is verified. Spec: [docs/api/paypal-webhooks.md](../../../docs/api/paypal-webhooks.md), [docs/api/telegram-webhook.md](../../../docs/api/telegram-webhook.md), [docs/integrations/telegram.md](../../../docs/integrations/telegram.md). PRD 10.1, 10.2, 16.4, 18.

## When to use

- Any change in `app/Features/Webhook/**` or `app/Features/Telegram/**` (planned).
- Any CSRF exclusion or `withoutMiddleware` on a route.

## Project paths (planned)

| Unit | Path |
|---|---|
| PayPal route | `app/Features/Webhook/Routes/api.php` -> `POST /webhooks/paypal` |
| PayPal intake | `app/Features/Webhook/Actions/ReceivePayPalWebhookAction.php` |
| PayPal processing | `app/Features/Webhook/Jobs/ProcessPayPalWebhookJob.php` |
| Telegram route | `app/Features/Telegram/Routes/api.php` -> `POST /webhooks/telegram` |
| Telegram callback | `app/Features/Telegram/Actions/HandleTelegramCallbackAction.php` |
| Event store | `webhook_events` with unique `(source, external_event_id)` |

## PayPal steps

Before step 1 (PayPal and Telegram): read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Read the raw body and the PayPal transmission headers. Do not trust the JSON before verification.
2. Store the event row first (`verified=false`). Duplicate `(source, external_event_id)` returns 200 and stops.
3. Respond 200 fast. Verify in a queued job: POST headers, `PAYPAL_WEBHOOK_ID`, and the raw body to `/v1/notifications/verify-webhook-signature` with a timeout.
4. Process only `verification_status = SUCCESS`. On `FAILURE` keep `verified=false`, do not process, alert the owner, audit.
5. Map item events per PRD 10.1. Unknown `payout_batch_id`: look up by `sender_batch_id` from the resource, attach, re-process.
6. Remember: the Webhooks Simulator sends events that fail verification. Test with real sandbox payouts.

## Telegram steps

1. Verify the secret header with `hash_equals()` against `TELEGRAM_WEBHOOK_SECRET`. The header name is UNVERIFIED in the PRD (OQ-05); confirm in the Telegram Bot API docs before coding.
2. Accept updates only from `TELEGRAM_OWNER_CHAT_ID` (allowlist).
3. Callback data carries an approval token. Hash it, look up `approvals.token_hash` (unique), check `token_expires_at` (15 minutes) and single use.
4. Telegram may approve or reject HOLD only. Never BLOCK, vendors, or policy.
5. Replayed or expired token: answer "already handled", no state change, audit.
6. Rate limit `telegram-callback` at 20 per minute.

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\Telegram\Actions;

use App\Features\Telegram\Exceptions\InvalidTelegramSecretException;

final class VerifyTelegramSecretAction
{
    public function handle(?string $receivedSecret): void
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret');

        if ($receivedSecret === null || ! hash_equals($expectedSecret, $receivedSecret)) {
            throw new InvalidTelegramSecretException;
        }
    }
}
```

## Checklist

- [ ] Webhook routes outside CSRF only because signature verification runs first.
- [ ] Dedupe by unique index, not by application check alone.
- [ ] Secrets compared with `hash_equals()`. Approval tokens stored only as hashes.
- [ ] No processing in the HTTP request beyond store and enqueue.
- [ ] Tests: verified, unverified, duplicate, out of order (PayPal); valid, expired, replayed, wrong chat (Telegram).

## Common mistakes

- Parsing and acting on the PayPal JSON before verification.
- Comparing secrets with `===`.
- Storing the plaintext approval token.

## Verification

```bash
php artisan test --compact --filter=Webhook
php artisan test --compact --filter=Telegram
```
