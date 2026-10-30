---
title: OWASP Mapping
purpose: Map OWASP Top 10:2025 and OWASP Top 10 for LLM Applications 2025 to concrete Anti-Body controls, planned file paths and tests.
audience: [judges, developers, ai-agents]
status: mixed
related:
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
  - ./threat-model.md
  - ./trust-boundaries.md
  - ./secrets.md
  - ./audit-hash-chain.md
  - ../ai/injection-isolation.md
  - ../development/testing-strategy.md
source_of_truth: feature prompts docs/prompts/feature-prompt-v1-abp-8-finalize-documents.md section 5 and docs/prompts/feature-prompt-v1-abp-9-install-security-checks.md; docs/prd-anti-body-paypal.md sections 9, 12.6, 18; for implemented controls app/Features/Shared/Http/Middleware/SecurityHeaders.php, config/antibody.php, bootstrap/app.php, .github/workflows/, .gitleaks.toml, tests/Architecture/, tests/AccessMatrix/, app/Features/Shared/Tests/Feature/
last_verified: 2026-10-30
---

# OWASP Mapping

> **TL;DR**
> - Every OWASP Top 10:2025 category (A01 to A10) and every OWASP LLM Top 10 2025 category (LLM01 to LLM10) maps to at least one Anti-Body control.
> - The strongest controls are structural: agents cannot approve, payee and amount come only from the DB, and PHP alone decides.
> - Paths in the "Planned paths" column are **planned** slice paths unless the Status column says implemented. The Status column shows what is implemented today.
> - Implemented today (ABP-9): security headers middleware and its config, guest refusal on every authenticated route (access matrix), JSON 401 and 404 for `api/*`, no error detail leaks, CSRF 419, identical login errors, architecture tests that ban raw SQL, `unguard`, `eval`, shell calls, `unserialize`, debug calls and `env()` outside config, Larastan level max, CI audits, secret scanning (hook and gitleaks), pinned and least-privilege workflows, Dependabot.
> - Also implemented earlier: Filament panel login (`app/Providers/Filament/AdminPanelProvider.php`), `#[Fillable]`/`#[Hidden]` on `User`.
> - Known gap BUG-01: `App\Models\User` does not implement `Filament\Models\Contracts\FilamentUser`, so outside `local` Filament answers 403 on every panel page.

## 1. OWASP Top 10:2025

| ID | Category | Anti-Body controls | Planned paths | Tests (planned) | Status |
|---|---|---|---|---|---|
| A01 | Broken Access Control | Deny by default. Policies per slice; `->authorize()` on every Filament action; `auth:sanctum` + H10 on MCP; agents can only propose (no approve, amount or payee args); hard-rule BLOCK not overridable; score BLOCK override dashboard-only with typed confirmation; Telegram approves HOLD only; outbound URLs never built from user input | `app/Features/*/Policies/`, `app/Filament/Resources/*`, `app/Features/AgentGateway/Mcp/`, `app/Features/Approval/Actions/` | Unauthorized user gets 403; revoked agent gets error; Telegram approve on BLOCK rejected; MCP tool list has no approve tool (US-07). Implemented: `GuestAccessTest` (guest gets the `/admin/login` redirect or JSON 401 on authenticated routes), `tests/AccessMatrix` (every `#[RequiresAbility]` class and authenticated route needs a matrix row; agents never granted approve, reject, override, vendor, amount or payee) | Guest refusal and matrix scanner implemented (rows empty until a protected surface exists); rest planned. Known gap BUG-01 (`FilamentUser`) |
| A02 | Security Misconfiguration | `APP_DEBUG=false` outside local; security headers middleware `app/Features/Shared/Http/Middleware/SecurityHeaders.php` appended globally in `bootstrap/app.php` (baseline headers, CSP report-only by default with machine, panel and page policies, HSTS on secure requests; values only in that file and `config/antibody.php`, env `ANTIBODY_CSP_REPORT_ONLY`, `ANTIBODY_HSTS_MAX_AGE`); restrictive CORS (publish `config/cors.php` only if needed); minimal client error detail | `app/Features/Shared/Http/Middleware/SecurityHeaders.php`, `bootstrap/app.php`, `config/antibody.php`, `config/app.php` | Implemented: `SecurityHeadersTest`, `ErrorResponseTest` (500 with no message, class or path when debug is off), `ApplicationDebugTest` (config default `false`) | Headers, report-only CSP and error rendering implemented. Enforcing CSP (`ANTIBODY_CSP_REPORT_ONLY=false`) and production `APP_DEBUG=false` are deployment steps, planned (B2) |
| A03 | Software Supply Chain Failures | No new dependencies without approval; `composer.lock` and `package-lock.json` respected; `composer audit` and `npm audit --audit-level=high` inside `composer check`; CI `dependency-review` on pull requests; Dependabot for github-actions, composer and npm; every GitHub Action pinned to a full commit SHA; workflows audited by zizmor and actionlint | `composer.json`, `package.json`, `.github/workflows/tests.yml`, `.github/workflows/security.yml`, `.github/dependabot.yml` | CI audit step; `dependency-review` job | Implemented (ABP-9). Requires the owner to protect `main` with these checks ([../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md)) |
| A04 | Cryptographic Failures | Framework `Hash`, `Crypt`, `hash_hmac` only; tokens from `Str::random()` / `random_bytes()`; `hash_equals()` comparisons; agent and Telegram tokens stored as hashes; secrets from env only; HTTPS on Render | `app/Features/Telegram/`, `app/Features/Audit/` | Token stored hashed; comparison uses `hash_equals` | planned |
| A05 | Injection | Eloquent parameterized queries; no `DB::raw` with input; Blade `{{ }}` only; invoice text never rendered as HTML in Filament; `#[Fillable]` allowlists, never `Model::unguard()` or `$request->all()`; server-side validation of type, format, length, range; prompt injection handled per LLM01 | `app/Features/*/Http/Requests/`, `app/Features/*/Models/` | Malicious invoice text rendered escaped; mass assignment test. Implemented: `tests/Architecture/RawSqlTest.php` and `ForbiddenCalls` fail on `DB::raw`, `DB::statement`, `DB::unprepared`, `whereRaw`, `selectRaw`, `orderByRaw`, `havingRaw` (allowlist empty) and `Model::unguard()` | `#[Fillable]` on User and the raw SQL and `unguard` bans implemented; rest planned |
| A06 | Insecure Design | Abuse cases AB-1 to AB-11 ([threat-model.md](./threat-model.md)); H1 to H10; absolute `auto_ceiling` $500; `max_per_payment`; `weekly_budget`; state machine; kill switch; monotonic score; `RateLimiter::for()` for `mcp` 30/min per token, `invoice-upload` 10/min, `telegram-callback` 20/min | `app/Features/Guard/`, `app/Features/Policy/`, `app/Features/Shared/Providers/` | Monotonicity property test; each hard rule pass/fail; 31st MCP call 429 | planned |
| A07 | Authentication Failures | Filament panel login for owner; Sanctum bearer tokens per agent (planned package); CSRF via `PreventRequestForgery`; exclude only signature-verified webhook routes (`api` middleware group, no session); regenerate session on privilege change; no account-existence hints; Filament MFA if available (OQ-06) | `app/Providers/Filament/AdminPanelProvider.php` | Implemented: `PanelLoginTest` (identical error for unknown email and wrong password), `CsrfProtectionTest` (missing token returns 419) | Login + CSRF implemented and tested. Known gap BUG-01: the owner can log in but Filament answers 403 outside `local` until `User` implements `FilamentUser` (tracked for B2 and B7) |
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
| `env()` outside config | Forbidden; read through `config()`. Enforced by `tests/Architecture/HygieneTest.php` |
| Debug output in production | `APP_DEBUG=false`, no `dd()`, `dump()`, `ray()`. Debug calls enforced by `tests/Architecture/HygieneTest.php` |
| Secrets in commits | Pre-commit secret scan, `.env` guard, gitleaks over full history in CI ([../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md)) |
| Workflow tampering | Least-privilege `permissions`, SHA-pinned actions, no `pull_request_target`, zizmor and actionlint in CI |
| Unsigned file URLs | PDFs only via `URL::temporarySignedRoute()` for the owner |
| Path traversal | Never pass user input to `Storage` paths; files renamed to UUIDs |
| Float money math | Integer minor units only ([money skill](../../.claude/skills/money-and-time-handling/SKILL.md)) |
| Race conditions | `lockForUpdate()` on state transitions |
| Stale queued models | Jobs re-read with lock before acting |
| Webhook routes outside CSRF | Allowed only with signature or secret verification |
| Real names, emails, keys in the public repo | Forbidden ([secrets.md](./secrets.md)) |
