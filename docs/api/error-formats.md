---
title: Error Formats
purpose: Define the error shapes returned to HTTP clients, MCP agents and the owner dashboard, and the rule that internals never leak.
audience: [developers, ai-agents]
status: mixed
related:
  - ./http-endpoints.md
  - ./mcp-tools.md
  - ../development/coding-conventions.md
  - ../security/owasp-mapping.md
source_of_truth: bootstrap/app.php (implemented JSON rendering rule). docs/prd-anti-body-paypal.md section 15 (MCP error codes) for planned errors
last_verified: 2026-10-30
---

# Error Formats

> **TL;DR**
> - **Implemented:** `bootstrap/app.php` renders exceptions as JSON when the path matches `api/*` or the request `expectsJson()`. The shape is the Laravel default.
> - **Planned:** typed slice exceptions in `app/Features/<Slice>/Exceptions/`, converted to a safe message at the boundary: JSON error, MCP tool error, or Filament notification.
> - MCP tool errors use a fixed set of `snake_case` codes: `not_found`, `invalid_input`, `too_large`, `duplicate_file`, `extraction_pending`, `rate_limited`, `unauthorized`, `agent_disabled`.
> - Responses never include stack traces, SQL, file paths, secrets, tokens or full PayPal IDs. Full detail goes to logs only (A02, A09).

## 1. HTTP JSON errors (implemented rule)

Source: `bootstrap/app.php`:
```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);
```

| Situation | Status | Body |
|---|---|---|
| Generic error | 500 | `{"message": "Server Error"}` (with `APP_DEBUG=false`) |
| Not found | 404 | `{"message": "Not Found"}` or the model-not-found message |
| Unauthenticated | 401 | `{"message": "Unauthenticated."}` |
| Forbidden | 403 | `{"message": "This action is unauthorized."}` |
| Validation | 422 | `{"message": "<first error>", "errors": {"field": ["message"]}}` |
| Throttled | 429 | `{"message": "Too Many Attempts."}` plus `Retry-After` header |

`APP_DEBUG` must be `false` outside local, otherwise Laravel adds `exception`, `file`, `line` and `trace` keys (A02).

Webhook paths (`/webhooks/paypal`, `/webhooks/telegram`) do not match `api/*`. Their controllers return explicit minimal JSON (for example `{"status": "ok"}`) and never echo the request back.

## 2. MCP tool errors (planned)

A tool error is returned as an MCP tool result with `isError: true` and a structured body:
```json
{
  "error": {
    "code": "not_found",
    "message": "Invoice not found."
  }
}
```

| Code | Tools | Meaning | Message given to the agent |
|---|---|---|---|
| `invalid_input` | all | Schema validation failed, unknown argument (for example `amount`), bad base64, not a PDF | "Invalid input: <field>." |
| `not_found` | `get_invoice`, `propose_payment`, `get_decision`, `payment_status` | No invoice with that ID | "Invoice not found." |
| `too_large` | `submit_invoice` | Decoded file larger than 5 MB | "File exceeds 5 MB." |
| `duplicate_file` | `submit_invoice` | H7: same SHA-256 already received | "Duplicate of invoice <original id>." |
| `extraction_pending` | `propose_payment` | Invoice still `received` | "Invoice is still being read. Try again shortly." |
| `rate_limited` | all | More than 30 requests per minute for this token | "Rate limit reached. Retry later." |
| `unauthorized` | all | Missing or invalid token (normally rejected by `auth:sanctum` with HTTP 401 before the tool runs) | "Unauthorized." |
| `agent_disabled` | all | H10: token revoked or agent inactive | "Agent is disabled." |

A decision is never an error. A BLOCK is a normal `propose_payment` result with `decision: block`.

## 3. Filament errors (planned)

| Situation | Treatment |
|---|---|
| Domain exception in an action (for example an illegal state transition) | Danger notification with the safe message from the exception. Full detail logged |
| Validation | Field errors under the inputs |
| Typed confirmation mismatch (payee change, score-BLOCK override, auto-ceiling change) | Action halts with a field error |
| PayPal unreachable (circuit open) | Banner "PayPal unreachable, payments queued" (PRD 10.2) |
| AI unavailable | Invoice row shows "AI unavailable, held for review" (PRD 10.2) |

## 4. Exception classes (planned)

| Rule | Detail |
|---|---|
| Location | `app/Features/<Slice>/Exceptions/`, for example `IllegalInvoiceTransitionException`, `PayoutSubmissionException`, `ExtractionFailedException`, `WebhookVerificationFailedException` |
| Naming | `<Specific problem>Exception` |
| Catching | Catch only what can be handled. Never swallow. Fail closed: an error never grants access or skips a check (A10) |
| Boundary conversion | Controller, MCP tool or Filament action converts the exception to one of the shapes above |
| Logging | Log context without secrets, tokens, passwords, API keys or unneeded personal data |

## 5. HTTP status mapping summary

| Status | Used for |
|---|---|
| 200 | Success, MCP JSON-RPC responses (including tool errors), webhook accepted or ignored |
| 400 | Malformed webhook body |
| 401 | Missing or invalid credentials (session, bearer token, Telegram secret) |
| 403 | Authenticated but not allowed (H10, invalid signed URL, policy denial) |
| 404 | Missing record |
| 422 | Validation failure |
| 429 | Rate limit |
| 500 | Unexpected error, generic message only |
