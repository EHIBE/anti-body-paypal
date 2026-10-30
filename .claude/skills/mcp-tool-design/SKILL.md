---
name: mcp-tool-design
description: "Use whenever designing, adding, changing, or reviewing Anti-Body MCP server tools or the built-in AP agent tools: AntiBodyServer, `/mcp/anti-body`, local `anti-body` stdio server, list_pending_invoices, get_invoice, submit_invoice, propose_payment, get_decision, payment_status, tool input schemas, annotations, error codes, Sanctum agent tokens, H10 agent validity, the `mcp` rate limiter. Enforces least agency (OWASP LLM06): agents propose only. Do not use for Filament or PayPal work."
---

# MCP tool design

Agents can only propose. Only Anti-Body moves money. Spec: [docs/api/mcp-tools.md](../../../docs/api/mcp-tools.md), [docs/api/error-formats.md](../../../docs/api/error-formats.md), [docs/ai/ap-agent.md](../../../docs/ai/ap-agent.md). PRD 9.4, 11.4, 15.

## When to use

- Any change in `app/Features/AgentGateway/**` (planned).
- Adding a tool, argument, output field, or error code.

## Dependencies

laravel/mcp is installed only transitively through Boost. laravel/sanctum is not installed. Both as direct dependencies need owner approval. Check the installed laravel/mcp API (`composer show laravel/mcp`, Boost `search-docs`) before coding.

## Tool contract (PRD 15)

| Tool | Input | Output | Errors | Annotations |
|---|---|---|---|---|
| `list_pending_invoices` | `limit` int 1..50, default 20 | `invoices[]` of id, vendor, number, amount, currency, status | none | ReadOnly, Idempotent |
| `get_invoice` | `invoice_id` uuid | id, vendor, number, amount, currency, due_date, status | `not_found` | ReadOnly, Idempotent |
| `submit_invoice` | `pdf_base64` or `text` (one required), `filename` optional, max 5 MB | invoice_id, status `received` | `invalid_input`, `too_large`, `duplicate_file` | not ReadOnly, not Destructive, OpenWorld false |
| `propose_payment` | `invoice_id` uuid, `note` max 500 optional | decision, reasons[{code,label}], score, payment_status | `not_found`, `extraction_pending`, `rate_limited` | Idempotent |
| `get_decision` | `invoice_id` | decision fields + `owner_explanation` | `not_found` | ReadOnly |
| `payment_status` | `invoice_id` | status, paid_at, payout_item_id_masked | `not_found` | ReadOnly |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Ask: does the tool let an agent approve, reject, override, edit vendors or policy, or set amount or payee? If yes, do not build it.
2. Define the input schema with types, bounds, and required fields. Reject unknown fields.
3. Authorize: Sanctum token, agent `active` (H10), `throttle:mcp` (30 per minute per token).
4. Delegate to one Action (`ProposePaymentAction`, `ReceiveInvoiceAction`, read Actions). The built-in AP agent tools call the same Actions.
5. Return only PRD 15 fields. Mask payout IDs. Never include credentials, raw invoice text, or model text other than `owner_explanation`.
6. Map typed exceptions to the error codes above.
7. Audit every call with actor type `agent` and the agent id.

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\AgentGateway\Data;

final readonly class ProposePaymentData
{
    public function __construct(
        public string $invoiceId,
        public int $agentId,
        public ?string $note,
    ) {}
}
```

## Checklist

- [ ] Tool list equals exactly the six tools (US-07).
- [ ] `propose_payment` returns the same decision on repeat calls; a `note` never changes the score (US-08).
- [ ] Foreign or unknown IDs return `not_found` without leaking existence detail.
- [ ] Server instructions text matches PRD 15.
- [ ] Tests use the laravel/mcp testing helpers with `actingAs($agent)`.

## Common mistakes

- Adding an `amount` argument "for partial payments".
- Returning the full extractor JSON to the agent.
- Using SDK tool-approval prompts as the payment approval.

## Verification

```bash
php artisan test --compact --filter=Mcp
```
Manual: MCP Inspector lists the six tools (planned command in `docs/api/mcp-tools.md`).
