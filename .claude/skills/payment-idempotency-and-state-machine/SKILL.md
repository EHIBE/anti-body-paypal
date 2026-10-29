---
name: payment-idempotency-and-state-machine
description: "Use whenever writing or reviewing code that creates payments, submits PayPal payouts, retries, reconciles, maps payout webhooks to states, or changes invoice or payment status in Anti-Body. Triggers: sender_batch_id, PayPal-Request-Id, submitting, submitted, queued, paid, reversed, unclaimed, paypal_held, reconcile job, lockForUpdate, state transition, duplicate payout, timeout retry, approved_amount_minor. Enforces PRD 10 and 14.3: exactly-once payouts and legal transitions only. Do not use for guard scoring or UI-only changes."
---

# Payment idempotency and state machine

One invoice, at most one PayPal batch, ever. Spec: [docs/architecture/state-machines.md](../../../docs/architecture/state-machines.md), [docs/integrations/idempotency.md](../../../docs/integrations/idempotency.md), [docs/integrations/reconcile.md](../../../docs/integrations/reconcile.md), [docs/integrations/paypal-payouts.md](../../../docs/integrations/paypal-payouts.md).

## When to use

- Any change in `app/Features/Payment/**`, `app/Features/Webhook/**`, `app/Features/Approval/Actions/**` (planned).
- Any code that writes `invoices.status` or `payments.status`.

## Invoice transitions (PRD 14.3)

| From | To | Trigger |
|---|---|---|
| new | `received` | Upload or `submit_invoice` |
| `received` | `extracted` / `held` | Extractor success / failed twice |
| `extracted` | `auto_approved` / `held` / `blocked` | Decision |
| `held` | `approved` / `rejected` | Owner approve / reject or expiry |
| `blocked` (score) | `approved` | Owner override in dashboard |
| `blocked` (hard) | none | Terminal |
| `auto_approved`, `approved` | `paying` | Payment created |
| `paying` | `paid` / `payment_failed` / `paypal_attention` | Payment `paid` / `failed` or `reversed` / `unclaimed` or `paypal_held` |

## Payment transitions

| From | To | Trigger |
|---|---|---|
| new | `queued` | Approval |
| `queued` | `submitting` | Job under row lock |
| `submitting` | `submitted` / `failed` | 201 or duplicate-ID adoption / non-retryable 4xx |
| `submitted` | `paid` / `failed` / `unclaimed` / `paypal_held` | Item events or reconcile |
| `paid` | `reversed` | ITEM.RETURNED, REFUNDED |
| `unclaimed` | `paid` / `reversed` | Later item event |

Never `submitting` to `queued`.

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Model statuses as string-backed enums with an `allowedTransitions()` map. Illegal transitions throw a typed exception from the slice `Exceptions/` and are audited.
2. Transition inside `DB::transaction()` after `lockForUpdate()` on the row. Re-check the current status after the lock.
3. Create the payment with `sender_batch_id = 'AB-'.$invoice->id` and `paypal_request_id` equal, amount from `approved_amount_minor`, payee copied from the vendor registry, before any HTTP call.
4. Submit in a queued job. On timeout or 5xx keep `submitting`, increment `attempts`, retry with the same IDs up to 3 times (5 s, 30 s, 120 s).
5. Duplicate `sender_batch_id` response: follow the link, adopt `payout_batch_id`, move to `submitted`.
6. Webhooks and reconcile apply PRD 10.1 mapping. Reconcile every 5 minutes for rows in `submitting` or `submitted` older than 2 minutes.
7. Show "Paid" only when the status is `paid`.

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\Payment\Actions;

use App\Features\Payment\Enums\PaymentStatus;
use App\Features\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;

final class MarkPaymentSubmittingAction
{
    public function handle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedPayment->transitionTo(PaymentStatus::Submitting);

            return $lockedPayment;
        });
    }
}
```

## Checklist

- [ ] `payments.invoice_id` and `payments.sender_batch_id` unique in the migration.
- [ ] Amount and payee never read from request, agent, or model input.
- [ ] Queued jobs refresh models before acting.
- [ ] Every transition and payout call audited.
- [ ] Tests: timeout then duplicate-ID yields one row, adopted batch, `submitted`; illegal transition throws.

## Common mistakes

- Generating a new `sender_batch_id` on retry.
- Marking `paid` on the 201 response.
- Using the `PAYMENT.PAYOUTSBATCH.SUCCESS` event to set item states (batch events carry no item data).

## Verification

```bash
php artisan test --compact --filter=Payment
php artisan test --compact --filter=Idempotency
```
