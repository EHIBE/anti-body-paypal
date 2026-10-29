---
title: OWASP Mapping
purpose: Map OWASP Top 10:2025 and OWASP Top 10 for LLM Applications 2025 to concrete Anti-Body controls, planned file paths and tests.
audience: [judges, developers, ai-agents]
status: planned
related:
  - ./threat-model.md
  - ./trust-boundaries.md
  - ./secrets.md
  - ./audit-hash-chain.md
  - ../ai/injection-isolation.md
  - ../development/testing-strategy.md
source_of_truth: feature prompt docs/prompts/feature-prompt-v1-abp-8-finalize-documents.md section 5; docs/prd-anti-body-paypal.md sections 9, 12.6, 18
last_verified: 2026-10-30
---

# OWASP Mapping

> **TL;DR**
> - Every OWASP Top 10:2025 category (A01 to A10) and every OWASP LLM Top 10 2025 category (LLM01 to LLM10) maps to at least one Anti-Body control.
> - The strongest controls are structural: agents cannot approve, payee and amount come only from the DB, and PHP alone decides.
> - All file paths are **planned** slice paths; none exists yet. Status column shows what is implemented today.
> - Implemented today: Filament panel login and CSRF middleware (`app/Providers/Filament/AdminPanelProvider.php`), JSON errors for `api/*` (`bootstrap/app.php`), `#[Fillable]`/`#[Hidden]` on `User`.

## 1. OWASP Top 10:2025

| ID | Category | Anti-Body controls | Planned paths | Tests (planned) | Status |
|---|---|---|---|---|---|
| A01 | Broken Access Control | Deny by default. Policies per slice; `->authorize()` on every Filament action; `auth:sanctum` + H10 on MCP; agents can only propose (no approve, amount or payee args); hard-rule BLOCK not overridable; score BLOCK override dashboard-only with typed confirmation; Telegram approves HOLD only; outbound URLs never built from user input | `app/Features/*/Policies/`, `app/Filament/Resources/*`, `app/Features/AgentGateway/Mcp/`, `app/Features/Approval/Actions/` | Unauthorized user gets 403; revoked agent gets error; Telegram approve on BLOCK rejected; MCP tool list has no approve tool (US-07) | Panel auth implemented; rest planned |
| A02 | Security Misconfiguration | `APP_DEBUG=false` outside local; security headers middleware in `app/Features/Shared/Http/Middleware/` registered in `bootstrap/app.php` `withMiddleware()`; restrictive CORS (publish `config/cors.php` only if needed); minimal client error detail | `bootstrap/app.php`, `config/app.php` | Header test on panel and webhook routes; production error response has no stack trace | JSON error rendering implemented; headers planned |
| A03 | Software Supply Chain Failures | No new dependencies without approval; `composer.lock` and `package-lock.json` respected; `composer audit` and `npm audit` each feature; CI runs audits (planned) | `composer.json`, `package.json`, `.github/workflows/` (planned) | CI audit step | Audits run manually; CI planned |
| A04 | Cryptographic Failures | Framework `Hash`, `Crypt`, `hash_hmac` only; tokens from `Str::random()` / `random_bytes()`; `hash_equals()` comparisons; agent and Telegram tokens stored as hashes; secrets from env only; HTTPS on Render | `app/Features/Telegram/`, `app/Features/Audit/` | Token stored hashed; comparison uses `hash_equals` | planned |
| A05 | Injection | Eloquent parameterized queries; no `DB::raw` with input; Blade `{{ }}` only; invoice text never rendered as HTML in Filament; `#[Fillable]` allowlists, never `Model::unguard()` or `$request->all()`; server-side validation of type, format, length, range; prompt injection handled per LLM01 | `app/Features/*/Http/Requests/`, `app/Features/*/Models/` | Malicious invoice text rendered escaped; mass assignment test | `#[Fillable]` on User implemented; rest planned |
| A06 | Insecure Design | Abuse cases AB-1 to AB-11 ([threat-model.md](./threat-model.md)); H1 to H10; absolute `auto_ceiling` $500; `max_per_payment`; `weekly_budget`; state machine; kill switch; monotonic score; `RateLimiter::for()` for `mcp` 30/min per token, `invoice-upload` 10/min, `telegram-callback` 20/min | `app/Features/Guard/`, `app/Features/Policy/`, `app/Features/Shared/Providers/` | Monotonicity property test; each hard rule pass/fail; 31st MCP call 429 | planned |
| A07 | Authentication Failures | Filament panel login for owner; Sanctum bearer tokens per agent (planned package); CSRF via `PreventRequestForgery`; exclude only signature-verified webhook routes (`api` middleware group, no session); regenerate session on privilege change; no account-existence hints; Filament MFA if available (OQ-06) | `app/Providers/Filament/AdminPanelProvider.php` | Login failure message is generic; CSRF missing returns 419 | Login + CSRF implemented |
| A08 | Software or Data Integrity Failures | Uploads: real content type `application/pdf`, 5 MB, rename, private disk, signed owner routes, SHA-256; JSON decoded to arrays then mapped to readonly `Data/` objects; PayPal webhook signature verification, `SUCCESS` only, dedupe; Telegram secret, chat, single-use HMAC token, 15 min; append-only audit hash chain via Audit slice only | `app/Features/Invoice/`, `app/Features/Webhook/`, `app/Features/Audit/` | Unverified webhook not processed; non-PDF rejected; chain verify detects edit | planned |
| A09 | Security Logging and Alerting Failures | Log failed logins, denied access, validation abuse, rate-limit hits, unverified webhooks, hard-rule BLOCKs, overrides, payee changes; audit decisions, approvals, vendor changes, payout calls, webhooks; never log secrets or tokens; mask payout IDs to agents | `app/Features/Audit/Actions/RecordAuditEntryAction.php`, `config/logging.php` | Log fake contains no secrets; BLOCK writes audit entry | planned |
| A10 | Mishandling of Exceptional Conditions | Fail closed: extraction failure or LLM outage forces HOLD; roll back partial writes with `DB::transaction()`; no swallowed exceptions; typed slice exceptions; payout timeout keeps `submitting` with same idempotency key; duplicate-ID adopts batch; reconcile resolves late webhooks | `app/Features/*/Exceptions/`, `app/Features/Payment/` | LLM fake throws -> HOLD; timeout -> `submitting` | planned |

## 2. OWASP Top 10 for LLM Applications 2025

| ID | Category | Anti-Body controls | Planned paths | Tests (planned) | Status |
|---|---|---|---|---|---|
| LLM01 | Prompt Injection | Invoice content is untrusted data; extractor quotes instruction-like text into `suspicious_phrases` with category; analyst never sees notes or terms; S4 +20 on any pressure phrase; PHP decides; agent `note` never sent to a model ([../ai/injection-isolation.md](../ai/injection-isolation.md)) | `app/Features/Extraction/Ai/`, `app/Features/Guard/` | Eval cases 9 and 10 BLOCK; injected phrase appears in `suspicious_phrases` | planned |
| LLM02 | Sensitive Information Disclosure | Models receive only invoice data and typed fields, no credentials, no other invoices; agents never receive credentials; payout IDs masked | `app/Features/AgentGateway/Http/Resources/` | MCP response contains masked ID only | planned |
| LLM03 | Supply Chain | Official `laravel/ai` and `laravel/mcp` packages only (approval needed); pinned versions; model IDs from config; OQ-12 tracks unverified `claude-haiku-5-5` | `composer.json`, `config/` | Version pin check | planned |
| LLM04 | Data and Model Poisoning | No model training (non-goal); vendor baselines computed in PHP from paid history only; owner overrides audited (FR-39) | `app/Features/Baseline/` | Rejected/blocked invoices excluded from stats | planned |
| LLM05 | Improper Output Handling | Schema-validate every model output; enum allowlist; length caps; owner explanation rendered escaped as plain text; output never executed, never used as a URL, query or path | `app/Features/Extraction/Data/`, `app/Features/Guard/Data/` | Invalid extractor JSON retried once then HOLD; HTML in explanation escaped | planned |
| LLM06 | Excessive Agency | Agents can only propose; MCP tools per PRD 15 only; no approve, vendor edit, amount or payee args; SDK tool approval not used for payments; built-in AP agent uses the same gate | `app/Features/AgentGateway/Mcp/Tools/`, `app/Features/AgentGateway/Ai/` | Tool list equals 6 allowed tools (US-07) | planned |
| LLM07 | System Prompt Leakage | System prompts contain no secrets or thresholds an attacker could exploit; prompts versioned (`prompt_version`); security does not depend on prompt secrecy | `app/Features/Extraction/Ai/`, `app/Features/Guard/Ai/` | Prompt files contain no env values | planned |
| LLM08 | Vector and Embedding Weaknesses | Not applicable: no RAG, no embeddings, no vector store in scope | n/a | n/a | n/a |
| LLM09 | Misinformation | Model never decides; explanation is labeled AI-written and sits beside the rule and signal list with points; payee email copied exactly, eval requires 17 of 17 exact match | `app/Filament/` invoice view | Eval exact-match payee | planned |
| LLM10 | Unbounded Consumption | Rate limits per agent token; max tokens (1,500 extractor, 600 analyst); AP agent max 12 steps; 5 MB upload cap; timeouts 60 s; cost estimate per invoice tracked; budget under $10 for the build | `config/` AI settings | Rate limit test; agent step cap | planned |

## 3. Laravel, Filament, Livewire and agent risk checklist (feature prompt section 5 "Always")

| Risk | Rule |
|---|---|
| Livewire public properties | Mark identity properties `#[Locked]`; re-authorize in every action method |
| Filament actions | Every action has `->authorize()` or `->visible()` backed by a policy |
| `env()` outside config | Forbidden; read through `config()` |
| Debug output in production | `APP_DEBUG=false`, no `dd()`, `dump()`, `ray()` |
| Unsigned file URLs | PDFs only via `URL::temporarySignedRoute()` for the owner |
| Path traversal | Never pass user input to `Storage` paths; files renamed to UUIDs |
| Float money math | Integer minor units only ([money skill](../../.claude/skills/money-and-time-handling/SKILL.md)) |
| Race conditions | `lockForUpdate()` on state transitions |
| Stale queued models | Jobs re-read with lock before acting |
| Webhook routes outside CSRF | Allowed only with signature or secret verification |
| Real names, emails, keys in the public repo | Forbidden ([secrets.md](./secrets.md)) |
