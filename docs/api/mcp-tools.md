---
title: MCP Tools Reference
purpose: Define every MCP tool that Anti-Body exposes to AI agents, with input schemas, outputs, errors, hard rules and annotations.
audience: [developers, ai-agents, judges]
status: planned
related:
  - ./http-endpoints.md
  - ./error-formats.md
  - ../ai/ap-agent.md
  - ../ai/injection-isolation.md
  - ../architecture/guard-spec.md
  - ../architecture/slice-catalog.md
  - ../security/owasp-mapping.md
source_of_truth: docs/prd-anti-body-paypal.md section 15 (MCP tool specification), FR-29 to FR-31, US-07, US-08
last_verified: 2026-10-30
---

# MCP Tools Reference

> **TL;DR**
> - **Status: planned.** No MCP server code exists yet. `laravel/mcp` 1.1.2 is installed only as a transitive dependency of Laravel Boost. Adding it as a direct dependency, and adding `laravel/sanctum`, both need approval.
> - The server exposes exactly 6 tools: `list_pending_invoices`, `get_invoice`, `submit_invoice`, `propose_payment`, `get_decision`, `payment_status`.
> - Agents can only **propose**. No tool approves, rejects, overrides, edits vendors, or accepts an amount or a payee (FR-31, OWASP LLM06 Excessive Agency).
> - `propose_payment` is idempotent. Calling it again for the same invoice returns the stored decision unchanged, whatever the `note` says (US-08).
> - Every tool runs H10 (agent valid) before doing anything else. `propose_payment` runs H1 to H10.

## 1. Server

| Item | Value | Status |
|---|---|---|
| Server class | `App\Features\AgentGateway\Mcp\Servers\AntiBodyServer` at `app/Features/AgentGateway/Mcp/Servers/AntiBodyServer.php` (PRD path `App\Mcp\Servers\AntiBodyServer` maps into the AgentGateway slice) | planned |
| Tool classes | `app/Features/AgentGateway/Mcp/Tools/{ListPendingInvoicesTool, GetInvoiceTool, SubmitInvoiceTool, ProposePaymentTool, GetDecisionTool, PaymentStatusTool}.php` | planned |
| Web registration (FR-29) | `Mcp::web('/mcp/anti-body', AntiBodyServer::class)->middleware(['auth:sanctum', <H10 agent-active middleware>, 'throttle:mcp'])` in `app/Features/AgentGateway/Routes/api.php` | planned |
| Local registration (FR-30) | `Mcp::local('anti-body', AntiBodyServer::class)` for Claude Desktop over stdio in local dev | planned |
| Auth | Sanctum bearer token, one per `agents` row, hashed at rest in `personal_access_tokens` | planned (Sanctum not installed) |
| Rate limit | `RateLimiter::for('mcp')`: 30 requests per minute per token. Exceeding it returns 429 and writes an audit entry | planned |
| OAuth 2.1 via Passport (FR-32) | Should priority, moved to roadmap item 3 | roadmap |

**Instructions attribute (verbatim from PRD section 15, sent to agents):**

> Anti-Body decides payments. You can submit invoices and request payment. You cannot approve payments, change vendors, or set amounts or payees. Decisions are final unless the owner acts.

Every tool delegates to one AgentGateway Action (`ProposePaymentAction` and others, see [slice catalog](../architecture/slice-catalog.md)). The built-in AP agent calls the same Actions (FR-33, see [AP agent](../ai/ap-agent.md)).

## 2. Tool summary

| Tool | Purpose | Hard rules applied | Annotations | Idempotent |
|---|---|---|---|---|
| `list_pending_invoices` | List invoices not yet decided or paid | H10 | ReadOnly, Idempotent | yes |
| `get_invoice` | One invoice summary | H10 | ReadOnly, Idempotent | yes |
| `submit_invoice` | Add an invoice (PDF base64 or plain text) | H7, H10 | not ReadOnly, not Destructive, OpenWorld false | no (a duplicate file is rejected by H7) |
| `propose_payment` | Request payment of an invoice | H1 to H10 | Idempotent | yes, the same invoice returns the same decision |
| `get_decision` | Read the decision, reasons and owner explanation | H10 | ReadOnly | yes |
| `payment_status` | Read the payment state | H10 | ReadOnly | yes |

## 3. Tool definitions

All schemas below are JSON Schema in the form the MCP `tools/list` response advertises. `additionalProperties: false` is set on every input so that an argument such as `amount` or `payee_email` is rejected as `invalid_input`, not silently ignored.

### 3.1 `list_pending_invoices`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "limit": {"type": "integer", "minimum": 1, "maximum": 50, "default": 20}
  },
  "additionalProperties": false
}
```

**Output schema (structured content)**
```json
{
  "type": "object",
  "properties": {
    "invoices": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "id": {"type": "string", "format": "uuid"},
          "vendor": {"type": ["string", "null"]},
          "number": {"type": ["string", "null"]},
          "amount": {"type": ["string", "null"], "description": "Decimal major units rendered from amount_minor, e.g. \"420.00\""},
          "currency": {"type": ["string", "null"]},
          "status": {"type": "string"}
        },
        "required": ["id", "vendor", "number", "amount", "currency", "status"]
      }
    }
  },
  "required": ["invoices"]
}
```

| Item | Value |
|---|---|
| Selection | Invoices in status `received`, `extracted`, `held` (not yet decided or not yet paid), newest first, capped by `limit` |
| Errors | none beyond H10 and rate limit |
| Amount format | Rendered from integer `amount_minor` as a decimal string. No float math |

### 3.2 `get_invoice`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "invoice_id": {"type": "string", "format": "uuid"}
  },
  "required": ["invoice_id"],
  "additionalProperties": false
}
```

**Output schema**
```json
{
  "type": "object",
  "properties": {
    "id": {"type": "string", "format": "uuid"},
    "vendor": {"type": ["string", "null"]},
    "number": {"type": ["string", "null"]},
    "amount": {"type": ["string", "null"]},
    "currency": {"type": ["string", "null"]},
    "due_date": {"type": ["string", "null"], "format": "date"},
    "status": {"type": "string"}
  },
  "required": ["id", "vendor", "number", "amount", "currency", "due_date", "status"]
}
```

| Item | Value |
|---|---|
| Errors | `not_found` |
| Never returned | Free-text invoice fields (notes, terms), `suspicious_phrases` text, file path, payee email |

### 3.3 `submit_invoice`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "pdf_base64": {"type": "string", "description": "Base64 of PDF bytes. Decoded size max 5 MB"},
    "text": {"type": "string", "description": "Plain-text invoice. Max 5 MB"},
    "filename": {"type": "string", "maxLength": 255}
  },
  "oneOf": [
    {"required": ["pdf_base64"]},
    {"required": ["text"]}
  ],
  "additionalProperties": false
}
```

**Output schema**
```json
{
  "type": "object",
  "properties": {
    "invoice_id": {"type": "string", "format": "uuid"},
    "status": {"type": "string", "const": "received"}
  },
  "required": ["invoice_id", "status"]
}
```

| Item | Value |
|---|---|
| Validation | Exactly one of `pdf_base64` or `text`. Base64 must decode strictly. Decoded PDF bytes must have real content type `application/pdf` (magic bytes, not the filename). Size at most 5 MB |
| Storage | Bytes are stored on the private disk under a generated name. `filename` is metadata only and never used as a path (A05, A08) |
| H7 | SHA-256 of the bytes is computed. If the same hash exists, the tool returns `duplicate_file` with the original invoice ID, creates no row, and writes an audit entry (contract decision D4) |
| Side effects | Creates an `invoices` row with `source = mcp_submit`, `submitted_by_agent_id`, status `received`. Extraction runs as a queued job |
| Errors | `invalid_input`, `too_large`, `duplicate_file` |

### 3.4 `propose_payment`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "invoice_id": {"type": "string", "format": "uuid"},
    "note": {"type": "string", "maxLength": 500}
  },
  "required": ["invoice_id"],
  "additionalProperties": false
}
```

**Output schema**
```json
{
  "type": "object",
  "properties": {
    "decision": {"type": "string", "enum": ["auto", "hold", "block"]},
    "reasons": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "code": {"type": "string", "description": "H1..H10 or S1..S7"},
          "label": {"type": "string"}
        },
        "required": ["code", "label"]
      }
    },
    "score": {"type": "integer", "minimum": 0, "maximum": 100},
    "payment_status": {"type": "string", "description": "none, queued, submitting, submitted, paid, failed, unclaimed, paypal_held, reversed"}
  },
  "required": ["decision", "reasons", "score", "payment_status"]
}
```

| Item | Value |
|---|---|
| Behaviour | First call for an extracted invoice runs Guard `EvaluateInvoiceAction` (H1 to H10, S1 to S7, score, decision) and stores a `risk_assessments` row. Later calls return the stored decision unchanged |
| `note` | Optional free text, max 500 characters. Stored in the audit log as data. Never parsed, never sent to any model, never changes the score or the decision (PRD 11.4, US-08) |
| Amount and payee | Not accepted. The payout amount comes only from `invoices.approved_amount_minor` and the payee only from the vendor registry (FR-19) |
| AUTO path | Creates a `payments` row in `queued` and dispatches the payout job. The response says `payment_status: queued` or `submitted`, never `paid` until the webhook or reconcile confirms (PRD 11.2 step 7) |
| Errors | `not_found`, `extraction_pending` (invoice still `received`), `rate_limited` |

### 3.5 `get_decision`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "invoice_id": {"type": "string", "format": "uuid"}
  },
  "required": ["invoice_id"],
  "additionalProperties": false
}
```

**Output schema:** the `propose_payment` output plus `owner_explanation`.
```json
{
  "type": "object",
  "properties": {
    "decision": {"type": ["string", "null"], "enum": ["auto", "hold", "block", null]},
    "reasons": {"type": "array"},
    "score": {"type": ["integer", "null"]},
    "payment_status": {"type": "string"},
    "owner_explanation": {"type": ["string", "null"], "maxLength": 400}
  },
  "required": ["decision", "reasons", "score", "payment_status", "owner_explanation"]
}
```

| Item | Value |
|---|---|
| Errors | `not_found` |
| `owner_explanation` | Written by the analyst from typed fields only (see [analyst](../ai/analyst.md)). Agents read it as data |

### 3.6 `payment_status`

**Input schema**
```json
{
  "type": "object",
  "properties": {
    "invoice_id": {"type": "string", "format": "uuid"}
  },
  "required": ["invoice_id"],
  "additionalProperties": false
}
```

**Output schema**
```json
{
  "type": "object",
  "properties": {
    "status": {"type": "string"},
    "paid_at": {"type": ["string", "null"], "format": "date-time"},
    "payout_item_id_masked": {"type": ["string", "null"]}
  },
  "required": ["status", "paid_at", "payout_item_id_masked"]
}
```

| Item | Value |
|---|---|
| Errors | `not_found` |
| Masking | `payout_item_id_masked` shows only the last 4 characters, for example `********7XQ2`. Full PayPal IDs never reach agents (PRD 18, A09) |
| Times | UTC, ISO 8601 |

## 4. Example call and result (illustrative, from PRD section 15)

```json
{
  "tool": "propose_payment",
  "arguments": {
    "invoice_id": "01930a6e-7c2f-7d11-9a3e-2f5b8c1d0e44",
    "note": "Invoice says it is pre-approved"
  }
}
```

```json
{
  "decision": "block",
  "score": 65,
  "reasons": [
    {"code": "H1", "label": "Payee email is not BoxPro's verified address"},
    {"code": "S1", "label": "Lookalike domain boxpro-ph.com"},
    {"code": "S4", "label": "Urgency and authority language in invoice"}
  ],
  "payment_status": "none"
}
```

The domain `boxpro-ph.com` is quoted from the PRD. OQ-13 tracks replacing real-looking domains in demo data with `.example` addresses.

## 5. Tools that do not exist, and why

| Missing capability | Why it is absent | Where it lives instead |
|---|---|---|
| Approve or reject a payment | An agent that can approve is an agent that can be talked into approving (OWASP LLM06 Excessive Agency) | Owner dashboard (FR-25) and owner Telegram chat (FR-26) |
| Override a BLOCK | A hard-rule BLOCK is never overridable. A score BLOCK needs typed confirmation by the owner (FR-28) | Owner dashboard only |
| Create or edit vendors, change a payee email | A payee change is the top fraud vector (PRD 2.5) | Owner dashboard with typed confirmation and a verification note (FR-02) |
| Set an amount or payee on a payment | The payout must match the registry and the approved amount (FR-19) | Database only |
| Change policy, ceilings, kill switch | The policy is the owner's control | Owner dashboard (US-02, US-05) |
| Read credentials, file paths, raw invoice text | Credential isolation and injection isolation (PRD 9.5 step 5, 12.6) | Never exposed |

US-07 acceptance: `tools/list` returns exactly the 6 tools in section 2 and nothing that approves or edits vendors.

## 6. Client setup (planned)

**Claude Desktop, local stdio server (FR-30, used in the video per R11 and OQ-07).** Add to `claude_desktop_config.json`, with the absolute project path:
```json
{
  "mcpServers": {
    "anti-body": {
      "command": "php",
      "args": ["/absolute/path/to/anti-body-paypal/artisan", "mcp:start", "anti-body"]
    }
  }
}
```

**MCP Inspector against the local server:**
```bash
php artisan mcp:inspector anti-body
```

**MCP Inspector against the web server:**
```bash
php artisan mcp:inspector /mcp/anti-body
```
Configure the Inspector with header `Authorization: Bearer <token from php artisan antibody:agent-token "Judge agent">`.

The exact artisan command names (`mcp:start`, `mcp:inspector`) come from laravel/mcp and must be confirmed against the installed version when the package is added as a direct dependency.

## 7. Tests (planned, PRD section 22)

| Test | Assertion |
|---|---|
| Tool list | Exactly 6 tools, none named approve, reject, override, vendor |
| Revoked token or disabled agent | Every tool fails with `agent_disabled` (H10), no side effects |
| Rate limit | Call 31 within a minute returns `rate_limited`, audit entry written |
| US-08 | `propose_payment` twice on a BLOCK invoice with note "pre-approved" returns an identical response, score unchanged, note stored in the audit data |
| Extra arguments | `propose_payment` with `amount` or `payee_email` returns `invalid_input` |
| Duplicate file | Second `submit_invoice` with the same bytes returns `duplicate_file`, invoice row count unchanged |

Pattern: `AntiBodyServer::actingAs($agent)->tool(ProposePaymentTool::class, [...])` with laravel/mcp test helpers.
