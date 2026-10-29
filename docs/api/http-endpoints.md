---
title: HTTP Endpoints
purpose: List every HTTP route Anti-Body serves, with middleware, authentication, CSRF handling, rate limits and status codes.
audience: [developers, ai-agents, judges]
status: mixed
related:
  - ./mcp-tools.md
  - ./paypal-webhooks.md
  - ./telegram-webhook.md
  - ./error-formats.md
  - ../architecture/vertical-slice-architecture.md
  - ../security/trust-boundaries.md
source_of_truth: Code for implemented routes (bootstrap/app.php, app/Providers/Filament/AdminPanelProvider.php). docs/prd-anti-body-paypal.md sections 8, 12, 15, 16, 18 for planned routes
last_verified: 2026-10-30
---

# HTTP Endpoints

> **TL;DR**
> - **Implemented today:** the Filament panel under `/admin` (with its login page) and the Laravel health check `/up`. Everything else is **planned**.
> - Planned public endpoints: `POST /webhooks/paypal`, `POST /webhooks/telegram`, and the MCP endpoint `/mcp/anti-body`.
> - Webhook routes skip CSRF because they live in slice `Routes/api.php` files under the `api` middleware group. Each one verifies a signature or secret instead.
> - Invoice PDFs are served only to the authenticated owner through a signed, expiring route.
> - There is no public REST API for agents. Agents use MCP only.

## 1. Route table

| Method | Path | Purpose | Middleware | Auth | CSRF | Rate limit | Status |
|---|---|---|---|---|---|---|---|
| GET | `/up` | Health check | Laravel default | none | n/a | none | implemented (`bootstrap/app.php` `health: '/up'`) |
| GET | `/` | Laravel welcome page from `routes/web.php` | `web` | none | n/a | none | implemented (skeleton default; to be replaced or redirected to `/admin`, planned) |
| GET, POST | `/admin/login` | Owner login | Filament panel stack (see section 2) | guest | yes | Filament login throttling | implemented |
| GET, POST | `/admin/*` | Owner dashboard: Overview, Invoices, Approval queue, Vendors, Agents, Policy, Audit log, AP Agent (PRD 19.1) | Filament panel stack + `Authenticate` | owner session | yes (`PreventRequestForgery`) | `invoice-upload` 10/min on upload action (planned) | panel implemented, screens planned |
| GET | `/admin/invoices/{invoice}/file` (name `invoices.file`, path illustrative) | Stream the stored PDF for preview (FR-45) | `web`, `auth`, `signed` | owner session + valid signature | n/a (GET) | none | planned |
| POST | `/webhooks/paypal` | Receive PayPal payout webhooks (FR-21) | `api` | PayPal signature verified by postback (`/v1/notifications/verify-webhook-signature`) | excluded (api group) | none (PayPal retries) | planned |
| POST | `/webhooks/telegram` | Receive Telegram updates and inline-button callbacks (FR-26, FR-27) | `api`, `throttle:telegram-callback` | `X-Telegram-Bot-Api-Secret-Token` header + allowlisted chat ID + single-use token | excluded (api group) | 20/min | planned (path chosen by ABP-8, PRD leaves it unspecified) |
| POST, GET | `/mcp/anti-body` | MCP web server (FR-29) | `api`, `auth:sanctum`, H10 agent-active check, `throttle:mcp` | Sanctum bearer token per agent | excluded (bearer token, no cookies) | 30/min per token | planned |

Route names follow `resource.action` and every link is generated with `route()`.

## 2. Filament panel middleware (implemented)

Source: `app/Providers/Filament/AdminPanelProvider.php`.

| Order | Middleware |
|---|---|
| 1 | `EncryptCookies` |
| 2 | `AddQueuedCookiesToResponse` |
| 3 | `StartSession` |
| 4 | `AuthenticateSession` |
| 5 | `ShareErrorsFromSession` |
| 6 | `PreventRequestForgery` (CSRF) |
| 7 | `SubstituteBindings` |
| 8 | `DisableBladeIconComponents` |
| 9 | `DispatchServingFilamentEvent` |
| auth | `Filament\Http\Middleware\Authenticate` |

Panel ID `admin`, path `admin`, login enabled, primary color Amber (PRD 19.4 asks for deep green; the color change is planned for P1).

## 3. Slice route loading (planned)

Contract decision D6. `bootstrap/app.php` gains a `then:` callback that scans `app/Features/*/Routes/`:

| File | Group | Prefix | Why |
|---|---|---|---|
| `Routes/web.php` | `web` middleware | none | Session, cookies, CSRF |
| `Routes/api.php` | `api` middleware | none | Keeps PRD paths exact: `/webhooks/paypal`, `/webhooks/telegram`, `/mcp/anti-body`. No `api/v1` prefix |

| Route | Slice file |
|---|---|
| `/webhooks/paypal` | `app/Features/Webhook/Routes/api.php` |
| `/webhooks/telegram` | `app/Features/Telegram/Routes/api.php` |
| `/mcp/anti-body` | `app/Features/AgentGateway/Routes/api.php` |
| Signed invoice file | `app/Features/Invoice/Routes/web.php` |

Note: `bootstrap/app.php` renders JSON errors when `$request->is('api/*') || $request->expectsJson()`. Webhook and MCP paths do not start with `api/`, so their controllers return explicit JSON responses (see [error formats](./error-formats.md)).

## 4. Rate limiters (planned, A06)

| Name | Limit | Key | On limit |
|---|---|---|---|
| `mcp` | 30 per minute | Sanctum token ID | 429, MCP error `rate_limited`, audit entry |
| `invoice-upload` | 10 per minute | owner user ID | Filament danger notification, upload rejected |
| `telegram-callback` | 20 per minute | Telegram chat ID, falling back to IP | 429 to Telegram, audit entry |

All limiters are defined with `RateLimiter::for()` in the owning slice service provider.

## 5. Status codes

| Endpoint | Code | When |
|---|---|---|
| `/webhooks/paypal` | 200 | Event stored (verified or not). Processing runs in a queued job. Responding fast stops PayPal retries (PRD 16.4) |
| `/webhooks/paypal` | 400 | Body is not JSON or the event ID is missing |
| `/webhooks/paypal` | 200 | Duplicate event ID (ignored, idempotent) |
| `/webhooks/telegram` | 200 | Update accepted or ignored (Telegram only needs a 2xx) |
| `/webhooks/telegram` | 401 | Secret header missing or wrong |
| `/webhooks/telegram` | 429 | Rate limit |
| `/mcp/anti-body` | 200 | JSON-RPC response, including tool-level errors |
| `/mcp/anti-body` | 401 | Missing or invalid bearer token |
| `/mcp/anti-body` | 403 | Agent disabled or token revoked (H10) |
| `/mcp/anti-body` | 429 | Rate limit |
| Signed file route | 403 | Invalid or expired signature, or not the owner |
| Signed file route | 404 | Invoice or file missing |

## 6. Security notes

| Rule | Applies to |
|---|---|
| Never follow redirects, never fetch URLs from input | All outbound calls made while handling these routes |
| Store raw webhook payloads as data, never deserialize into objects | Both webhook routes |
| Minimal error detail in responses, full detail in logs only | All routes (A02) |
| No secrets, tokens or full payout IDs in responses | All routes (A09) |
