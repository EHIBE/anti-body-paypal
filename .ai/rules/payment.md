---
paths:
  - 'app/Features/Payment/**'
---

# Payment

## Payouts are idempotent and DB-sourced
Payout amount and payee come only from the DB (invoices.approved_amount_minor and the vendor registry email), never from agent input. One batch per invoice with sender_batch_id = PayPal-Request-Id = "AB-{invoice_id}", stored before the call. Retry only inside `submitting` with the same ids (max 3, 5s/30s/120s); a duplicate-ID response adopts the original batch. Never transition submitting back to queued. Illegal transitions throw and are audited. Timeouts on every PayPal call.
