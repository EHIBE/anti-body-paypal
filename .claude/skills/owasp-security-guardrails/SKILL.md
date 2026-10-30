---
name: owasp-security-guardrails
description: "Apply the Anti-Body security guardrails mapped to OWASP Top 10:2025 (A01 to A10) whenever writing or reviewing any entry point or security-relevant code: Filament resources and actions, Livewire components, controllers, Form Requests, MCP tools, webhook and Telegram routes, uploads, middleware, policies, rate limiters, config, logging, or error handling. Triggers: authorization, ownership checks, CSRF, CORS, security headers, mass assignment, injection, file upload, rate limit, fail closed, excessive agency. Do not use for pure documentation edits or for money arithmetic alone (use money-and-time-handling)."
---

# OWASP security guardrails

Security first. These rules are binding and win over convenience. Full mapping: [docs/security/owasp-mapping.md](../../../docs/security/owasp-mapping.md). Threats: [docs/security/threat-model.md](../../../docs/security/threat-model.md). Boundaries: [docs/security/trust-boundaries.md](../../../docs/security/trust-boundaries.md).

## When to use

- Adding or changing any entry point: Filament action, Livewire method, controller, MCP tool, webhook, Telegram callback, Artisan command that writes.
- Adding config, middleware, rate limiters, logging, or exception rendering.
- Reviewing a diff before commit.

## Project paths

| Concern | Path | Status |
|---|---|---|
| Panel auth and CSRF middleware | `app/Providers/Filament/AdminPanelProvider.php` | implemented |
| JSON errors for `api/*` and `expectsJson()` | `bootstrap/app.php` | implemented |
| Security headers middleware | `app/Features/Shared/Http/Middleware/SecurityHeaders.php` registered in `bootstrap/app.php` `withMiddleware()` | planned |
| Slice policies | `app/Features/<Slice>/Policies/` | planned |
| Rate limiters `mcp`, `invoice-upload`, `telegram-callback` | `app/Features/Shared/Providers/SharedServiceProvider.php` | planned |

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Write the abuse cases first: tampering, replay, enumeration, excess volume, skipped workflow steps, fake invoices, payee swaps, duplicate submissions, agent argument.
2. Authorize server-side at the entry point (policy, `->authorize()`, H10 for agents). Deny by default.
3. Validate every input: type, format, length, range, allowlist (Form Request, MCP schema, Filament rules) and back each rule with a DB constraint.
4. Delegate to one Action. Convert typed exceptions to safe messages at the boundary.
5. Log the security event without secrets.
6. Test unauthorized access and each abuse case.

## Checklist by OWASP ID

| ID | Must hold |
|---|---|
| A01 Broken Access Control | Ownership on every read, update, delete. `->authorize()` on every Filament action. Livewire IDs `#[Locked]` or re-authorized. Agents only propose. Hard BLOCK never overridable. Telegram only approves or rejects HOLD. Outbound URLs allowlisted, no redirects, no internal or metadata hosts. |
| A02 Security Misconfiguration | `APP_DEBUG=false` outside local. Security headers. CORS restrictive (publish `config/cors.php` only if needed). Minimal client error detail. |
| A03 Supply Chain | No new dependency without approval. Run `composer audit` and `npm audit`. |
| A04 Cryptographic Failures | `Hash`, `Crypt`, `hash_hmac` only. Tokens from `Str::random()` or `random_bytes()`. `hash_equals()` for comparisons. Agent and Telegram tokens stored hashed. |
| A05 Injection | Parameterized queries. `{{ }}` only. No `{!! !!}`, `->html()`, `HtmlString` with invoice or model text. `#[Fillable]` allowlists, never `$request->all()` into create. Invoice text is untrusted data (see prompt-injection-defense). |
| A06 Insecure Design | Server-side limits H1 to H10, `auto_ceiling` $500, budgets, valid transitions, kill switch. Monotonic score. Rate limits: MCP 30/min per token, upload 10/min, Telegram callback 20/min. |
| A07 Authentication Failures | Filament login for owner, Sanctum tokens per agent (planned). CSRF via `PreventRequestForgery` except signature-verified webhooks. Regenerate session on privilege change. No account-existence hints. |
| A08 Integrity Failures | PDF by real content type, 5 MB, renamed, private disk, signed owner routes, SHA-256. No `unserialize` of untrusted data. Webhooks verified. Audit hash chain append-only. |
| A09 Logging Failures | Log failed logins, denied access, rate-limit hits, unverified webhooks, hard BLOCKs, overrides, payee changes. Never log secrets, tokens, keys. Mask payout IDs for agents. |
| A10 Exceptional Conditions | Fail closed. Extraction or LLM failure forces HOLD. Roll back partial writes. No swallowed exceptions. Explicit timeouts and missing-record handling. |

## Illustrative snippet

```php
<?php

declare(strict_types=1);

use App\Features\Approval\Actions\ApproveInvoiceAction;
use App\Features\Invoice\Models\Invoice;
use Filament\Actions\Action;

Action::make('approve')
    ->authorize('approve')
    ->visible(fn (Invoice $record): bool => $record->isHeld())
    ->requiresConfirmation()
    ->action(fn (Invoice $record, ApproveInvoiceAction $approveInvoice): mixed => $approveInvoice->handle($record, auth()->user()));
```

## Common mistakes

- Hiding an action with `->visible()` but not authorizing it.
- Excluding a webhook from CSRF before signature verification exists.
- Returning exception messages from PayPal or Anthropic to clients.
- Trusting a Livewire public property that holds a record ID.

## Verification

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
composer audit
npm audit
```
Grep before finishing: `env(` outside `config/`, `{!!`, `->html(`, `DB::raw`, `unguard`, `$request->all()`, `dd(`, `dump(`.
