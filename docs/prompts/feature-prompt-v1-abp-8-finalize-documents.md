# Anti-Body (anti-body-paypal): FEATURE PROMPT v1

## 1. LOAD
- Read FEATURE DETAILS at the bottom first.
- Read CLAUDE.md and AGENTS.md fully. Load state, pending queue, constraints, bugs. Their constraints are binding. Project sections live outside the `<laravel-boost-guidelines>` block because Boost regenerates that block.
- Read `.ai/rules/index.md` and every rule file whose globs cover the paths in scope when `.ai/rules` exists. Run `grep -rin 'keyword' .ai/rules` for the feature keywords.
- Read every file in `docs/` related to the feature. The PRD (`docs/prd-anti-body.md`, renamed to `docs/prd-anti-body-paypal.md` by ABP-8) is the product source of truth.
- Read every file related to the feature before any edit: affected code, callers, dependencies, tests, related docs.
- List all available skills (`.claude/skills/`, user-level, plugin). Read fully every skill relevant to this feature before using it.
- FEATURE DETAILS empty: propose the top pending-queue item from CLAUDE.md and confirm it.
- State the current project state in one sentence.

## 2. CLARIFY (gate)
- Ask numbered questions: scope and acceptance criteria, roles and permissions, inputs and validation, data changes, interfaces, UI states, edge cases, abuse cases, security. Recommended default on each. Skip what the files answer.
- Ticket ID missing from FEATURE DETAILS: ask for it. Prefix is `ABP`.
- Give a proposed approach, no code: files to edit, slices and units to add, skills and agents to use.
- STOP until answers or "use defaults".

## 3. PROJECT
- Product: Anti-Body, a payment gate between AI agents and a small business PayPal account. Agents propose vendor invoice payments. Only Anti-Body moves money. Outcomes are AUTO, HOLD, BLOCK. Built for the PayPal AI Hackathon (Devpost), deadline Fri Nov 13 2026 4:00 am PHT. Repo and package identifier: `anti-body-paypal`. Brand name: Anti-Body.
- Stack: PHP 8.5.11, laravel/framework 13.39.0, filament/filament 5.11.0, livewire/livewire 4.4.8, pestphp/pest 5.3.4 with pest-plugin-laravel 5.0.1, laravel/pint 1.32.1, laravel/boost 2.10.4, laravel/mcp 1.1.2 (transitive via Boost), Tailwind CSS 4, Vite 8. Local DB SQLite, tests SQLite `:memory:`, target Render Postgres. Queue, cache, session on database drivers. Planned, not installed: laravel/ai, laravel/sanctum, larastan/larastan, laravel-notification-channels/telegram. Adding any of them needs approval.
- Key paths: `app/Features/<Slice>/` (vertical slices), `app/Features/Shared/` (cross-cutting only), `app/Filament/` (panel resources, pages, widgets, components), `app/Providers/Filament/AdminPanelProvider.php` (panel `admin` at `/admin`), `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `database/migrations/`, `database/factories/`, `database/seeders/`, `tests/`, `docs/`, `docs/prompts/`, `.claude/agents/`, `.claude/skills/`, `.env.example`.
- Commands: test `php artisan test --compact` | single `php artisan test --compact --filter=TestName` or a file path | analyze `vendor/bin/phpstan analyse` (Larastan level 10, NOT INSTALLED until approved, report as skipped) | lint `vendor/bin/pint --dirty --format agent` | format `vendor/bin/pint --dirty --format agent` | audit `composer audit` and `npm audit`
- Conventions: `declare(strict_types=1)` in every PHP file. Namespace mirrors path. Actions named `VerbNounAction` with one `handle()` method. Enums with TitleCase keys, string-backed, implementing Filament `HasLabel` and `HasColor` when shown in UI. Money in integer minor units (`*_minor`). IDs UUIDv7 via `HasUuids` except framework tables. Times in UTC. Constructor property promotion. Explicit return and parameter types. PHPDoc array shapes for arrays. Curly braces on every control structure. `#[\Override]` on overrides. camelCase variables. Readonly classes in `Data/` for DTOs. Models use `#[Fillable]` and `#[Hidden]` attributes, never `Model::unguard()`. Config via `config()`, never `env()` outside `config/`. Named routes with `route()`. Artisan `make:` with `--no-interaction`. JSON errors for `api/*` or `expectsJson()` per `bootstrap/app.php`.
- Agents (`.claude/agents/`, all `model: claude-sonnet-5-5`, `effort: medium`, created by ABP-8): slice-architect, filament-ui-builder, paypal-integration-engineer, ai-guard-engineer, mcp-gateway-engineer, pest-test-engineer, security-auditor, money-integrity-reviewer, code-reviewer, docs-writer. Until ABP-8 lands: NONE project-level. Built-in: Explore (read-only search), Plan (read-only planning), general-purpose.
- Skills:
  - laravel-best-practices: any Laravel PHP code, queries, jobs, policies, migrations.
  - testing-best-practices: test design, coverage, naming, security boundary tests.
  - tailwindcss-development: Blade or Tailwind styling.
  - deploying-to-cloud: Laravel Cloud only (project deploys to Render, so normally skip).
  - infer-conventions: only when the user explicitly asks to record conventions in `.ai/rules`.
  - security-review: review pending branch changes for vulnerabilities before finishing.
  - code-review and simplify: review and tighten the diff.
  - impeccable, frontend-design, high-end-visual-design, minimalist-ui: Filament theme and UI polish.
  - claude-api: any Anthropic model, laravel/ai prompt, structured output, or model ID work.
  - Project skills added by ABP-8: read each `.claude/skills/*/SKILL.md` and apply when its trigger matches.

## 4. IMPLEMENT

### 4.1 Design first
- Restate acceptance criteria as testable checks before writing code.
- Architecture is vertical slice. Each domain lives in `app/Features/<Slice>/` with only the sub-folders it needs: `Actions/`, `Data/`, `Enums/`, `Events/`, `Exceptions/`, `Http/Controllers/`, `Http/Requests/`, `Http/Resources/`, `Jobs/`, `Listeners/`, `Mcp/`, `Ai/`, `Models/`, `Models/Interfaces/`, `Models/Traits/Forms/`, `Models/Traits/Tables/`, `Models/Traits/Infolists/`, `Notifications/`, `Policies/`, `Providers/`, `Routes/`, `Commands/`, `Tests/`.
- Planned slices: Shared, Vendor, Invoice, Extraction, Guard (hard rules, signals, score, decision), Approval, Payment (executor, PayPal client, reconcile), Webhook, Telegram, AgentGateway (MCP server, built-in AP agent), Audit, Policy, Baseline. Never create a new slice without approval. PRD paths `app/Domain/*`, `app/Ai/*`, `app/Mcp/*` map into these slices.
- Place every responsibility in its layer:
  - Entry points: `app/Features/<Slice>/Http/Controllers/` (thin), `app/Features/<Slice>/Mcp/` tools, `app/Features/<Slice>/Commands/`, Filament resources and actions in `app/Filament/`. Each parses, authorizes, delegates to one Action, responds.
  - Validation: Form Requests in `Http/Requests/`, MCP tool input schemas, Filament field rules, plus readonly `Data/` objects built from validated input.
  - Authorization: Policies in `app/Features/<Slice>/Policies/`, Filament resource policies and `->authorize()` on every Filament action, Sanctum token plus H10 for agents.
  - Business logic: Actions in `app/Features/<Slice>/Actions/` only. Never in controllers, Filament resources, Livewire components, models, or Blade.
  - Persistence: Eloquent models in `app/Features/<Slice>/Models/`, migrations in `database/migrations/`, factories in `database/factories/`, seeders in `database/seeders/`.
  - Presentation: Filament stays separate in `app/Filament/Resources/<Plural>/` with `Pages/`, `Schemas/`, `Tables/`. Field, column, filter, and infolist definitions live in model traits as static methods: `Models/Traits/Forms/<Model>FormTrait::getForm()`, `Models/Traits/Tables/<Model>TableTrait::getTableSchema()` and `getFilters()`, `Models/Traits/Infolists/<Model>InfolistTrait::getInfolist()`. Resources call `Model::getForm()`, `Model::getTableSchema()`, `Model::getFilters()`, `Model::getInfolist()`. Pass context flags as typed booleans. Reusable Filament components go in `app/Filament/Components/`. JSON API output uses API Resources in `Http/Resources/`.
- Wiring: slice service providers in `Providers/<Slice>ServiceProvider.php` are auto-registered from `AppServiceProvider` by scanning `app/Features`. Slice routes in `Routes/web.php` and `Routes/api.php` are loaded by the `then:` callback in `bootstrap/app.php`. Slice tests in `Tests/` are registered as a testsuite in `phpunit.xml` and bound in `tests/Pest.php`. Wire any of these that do not exist yet in the first slice that needs them.
- Reuse existing units, helpers, and patterns before writing new ones. Search first.
- Use project skills whenever their purpose matches. Hard constraints win over skill instructions.

### 4.2 SOLID
- Single Responsibility: each class, module, and function has one reason to change. Entry points stay thin: parse, authorize, delegate, respond. Business rules never live in entry points, views, Filament resources, or Eloquent models. Model traits hold only UI schema definitions.
- Open/Closed: add behavior through new Actions, new signals, new hard rules, or existing extension points. Change existing logic only where the feature requires it, without altering current behavior.
- Liskov Substitution: subtypes honor parent contracts. No narrowed inputs, widened outputs, surprise exceptions, or empty overrides.
- Interface Segregation: small, role-specific contracts in `Models/Interfaces/` or slice `Contracts`. No consumer depends on methods it does not call.
- Dependency Inversion: inject dependencies through the Laravel service container with constructor property promotion. Register Actions in the slice service provider. No hidden globals, `app()` lookups, or facades inside Actions when a constructor dependency fits.

### 4.3 Code quality
- DRY: one authoritative source per rule. Hard rules H1 to H10, signals S1 to S7, and thresholds live in the Guard slice only. Extract real duplication. Leave coincidental similarity alone.
- KISS and YAGNI: simplest design that fully meets the criteria. No speculative abstractions, options, or layers.
- Guard clauses and early returns. Max two nesting levels. No else after return.
- Small functions with one purpose and few parameters. Group related parameters into a readonly `Data/` object.
- Names reveal intent and use domain terms from the PRD: vendor, invoice, payee, hold, block, payout, approval. Booleans read as questions (is, has, can). No unclear abbreviations.
- Strict typing: declared parameter and return types, no untyped or mixed values, enums for fixed sets (statuses, decisions, channels, actor types), no magic strings or numbers.
- Prefer immutable values. Keep side effects at the edges. Separate commands from queries.
- Talk only to direct collaborators. No long call chains through other objects.
- Errors: throw specific, typed exceptions from `app/Features/<Slice>/Exceptions/`. Catch only what you can handle. Never swallow. Convert to safe user messages at the boundary (Filament notification, JSON error, MCP tool error).
- Return empty collections, never null. Make optional values explicit.

### 4.4 Data and persistence
- Schema changes: new reversible migration only. Never edit an applied one.
- Back every validation rule with a storage constraint: unique, foreign key, not null, check. Keep PRD 14.2 constraints: `vendors.paypal_email` unique, `invoices.file_sha256` unique, `(vendor_id, invoice_number)` unique partial, `payments.invoice_id` and `payments.sender_batch_id` unique, `(source, external_event_id)` unique, `approvals.token_hash` unique.
- Index columns used in lookups, joins, and sorting.
- No per-item queries in loops. Eager load relations. Select only needed columns. Paginate every list.
- Wrap multi-step writes in `DB::transaction()`. Use `lockForUpdate()` on state transitions. Make repeat submissions idempotent.
- Store money as integer minor units. Store time in UTC.
- Enforce invoice and payment state transitions from PRD 14.3. Illegal transitions throw and are audited. Never move a payment from `submitting` back to `queued`.
- Write migrations portable across SQLite (local, tests) and Postgres (Render).

### 4.5 Interfaces and integration
- Match existing response shapes, status codes, and error formats exactly. MCP tool outputs follow PRD section 15.
- Keep public interfaces backward compatible. Ask before any breaking change.
- Wire every integration point: slice routes, slice service provider, policies, Filament navigation, config, scheduler (`routes/console.php`), queued jobs, rate limiters.
- New config keys go in `.env.example` with placeholder values only. Read them through a `config/` file.
- Set timeouts on every external call (PayPal, Anthropic, Telegram). Retry only idempotent calls, with a limit. PayPal payouts retry only with the same `sender_batch_id` and `PayPal-Request-Id`. Move slow work to queued jobs.
- Cache only with a defined invalidation rule. PayPal OAuth token cached until expiry minus 5 minutes.

### 4.6 Completion
- Build the feature completely, end to end. No stubs, TODOs, or placeholders.
- Edit files in place. No duplicate or parallel files. New files only inside the established structure.
- Refactor only what the feature needs.
- Agents: use project agents by name. Read-only explorers in parallel. One writer per file. Verify every agent result against source.

## 5. SECURITY GUARDRAILS (mapped to OWASP Top 10:2025)

### A01 Broken Access Control
- Deny by default. Authorize every entry point server-side with Policies, Filament resource authorization, `->authorize()` on every Filament action, `auth:sanctum` plus agent `active` check (H10) on MCP routes.
- Check ownership on every record read, update, and delete. Never trust a client-supplied ID or role.
- Enforce role checks on every action, including hidden UI actions, Livewire methods, queued jobs, and webhook endpoints.
- Agents can only propose. No MCP or AP agent tool approves, rejects, overrides, edits vendors, or accepts amount or payee arguments. Payout amount and payee come only from the DB (`approved_amount_minor`, registry email).
- Hard-rule BLOCK is never overridable. Score BLOCK override only in the dashboard with typed confirmation. Telegram can only approve or reject HOLD.
- Outbound requests built from user input: allowlist hosts, block internal and metadata addresses, do not follow redirects.

### A02 Security Misconfiguration
- Keep `APP_DEBUG` off outside local. Use secure defaults for every new setting.
- Set security headers through a Shared middleware registered in `bootstrap/app.php` `withMiddleware()`. Keep CORS restrictive through `config/cors.php` (publish only if needed).
- Return minimal error detail to clients. Full detail goes to logs only.

### A03 Software Supply Chain Failures
- No new dependencies without approval. Respect `composer.lock` and `package-lock.json`.
- Run `composer audit` and `npm audit` and report any vulnerable dependency the feature touches.

### A04 Cryptographic Failures
- Use the framework's vetted hashing and encryption only (`Hash`, `Crypt`, `hash_hmac`). No custom crypto.
- Generate tokens with `Str::random()` or `random_bytes()`. Compare secrets with `hash_equals()`.
- Store Telegram approval tokens and agent tokens only as hashes. Encrypt sensitive data at rest where the project already does. Load secrets from environment only.

### A05 Injection
- Parameterize every query. No string-built queries or `DB::raw` with user input.
- Encode output for its context. Use `{{ }}` in Blade, never `{!! !!}` with user or invoice content. Never render invoice or model text as HTML in Filament.
- Never pass user input to shell commands, file paths, templates, headers, or dynamic code.
- Allowlist fillable fields with `#[Fillable]`. Block mass assignment. Never `Model::unguard()` or `$request->all()` into create or update.
- Validate every input on the server: type, format, length, range, allowlist. Client validation is convenience only.
- Prompt injection (OWASP LLM01): invoice content is untrusted data. The extractor quotes instruction-like text into `suspicious_phrases`. Free-text invoice fields never reach the analyst. Agent `note` is stored as data, never parsed, never sent to a model. Schema-validate every model output before use.

### A06 Insecure Design
- List abuse cases before coding: tampering, replay, enumeration, excess volume, skipped workflow steps, fake invoices, payee swaps, duplicate submissions, agent argument.
- Enforce business limits server-side: H1 to H10, `auto_ceiling` $500 absolute, `max_per_payment`, `weekly_budget`, valid state transitions, kill switch.
- Score is monotonic: signals only add points. AI never outputs the score or the decision. PHP sums points and decides.
- Rate limit sensitive and expensive actions with `RateLimiter::for()`: MCP 30 per minute per token, upload 10 per minute, Telegram callback 20 per minute.

### A07 Authentication Failures
- Use the existing authentication only: Filament panel login for the owner, Sanctum bearer tokens per agent once installed.
- Protect every state-changing request with CSRF through the panel and web middleware (`PreventRequestForgery`). Exclude only signature-verified webhook routes.
- Regenerate sessions on privilege change. Give no hints that reveal whether an account exists.

### A08 Software or Data Integrity Failures
- Validate uploads by real content type (`application/pdf`) and size (5 MB max). Rename on save. Store on a private disk. Serve only to the owner through signed routes. Hash bytes with SHA-256.
- Never deserialize untrusted data into objects. Decode JSON into arrays and map into `Data/` objects.
- Verify every PayPal webhook via `/v1/notifications/verify-webhook-signature`. Process only `SUCCESS`. Dedupe by event ID. Verify Telegram secret header, allowlisted chat ID, single-use HMAC token, 15 minute expiry.
- Audit log is append-only with a hash chain (`prev_hash`, `hash`). Write only through the Audit slice logger Action.

### A09 Security Logging and Alerting Failures
- Log security events: failed logins, denied access, validation abuse, rate-limit hits, unverified webhooks, hard-rule BLOCKs, overrides, payee changes. Audit decisions, approvals, vendor changes, payout calls, webhooks.
- Never log secrets, tokens, passwords, PayPal or Anthropic keys, or unnecessary personal data. Mask payout IDs in agent responses.

### A10 Mishandling of Exceptional Conditions
- Fail closed. An error never grants access or skips a check. Extraction failure or LLM outage forces HOLD.
- Roll back partial writes on failure. No swallowed exceptions.
- Handle timeouts, empty results, and missing records explicitly. Payout timeout keeps `submitting` and retries with the same idempotency key. Duplicate-ID response adopts the original batch. Reconcile job resolves late webhooks.

### Always
- Collect and expose only the data the feature needs.
- Keep secrets out of code, logs, errors, responses, docs, seed data, and screenshots.
- Laravel, Filament, Livewire, and agent risks to avoid: Livewire public properties trusted without `#[Locked]` or re-authorization, Filament actions without `->authorize()` or `->visible()` checks, `env()` outside config, debug output in production, unsigned file URLs, path traversal through `Storage` with user paths, float money math, race conditions without row locks, queued jobs acting on stale models without refresh, webhook routes outside CSRF without signature checks, excessive agency (OWASP LLM06), insecure model output handling (OWASP LLM05), real names, emails, or keys in the public repo.

## 6. VERIFY
- Add Pest tests in `app/Features/<Slice>/Tests/`: happy path, edge cases, invalid input, unauthorized access, abuse cases, failure paths. Fake HTTP with `Http::fake()`, AI with laravel/ai fakes, queues and notifications with framework fakes. Deterministic only.
- Run tests, Larastan at level 10 (report skipped while not installed), Pint, `composer audit`, `npm audit`. Zero new failures or violations.
- Never weaken tests or suppress errors to pass.

## 7. DOCUMENT
- Update related docs in `docs/` and the project sections of CLAUDE.md and AGENTS.md (state, queue, bugs) in place.

## 8. REPORT
- Per changed block: short prose on core logic and control flow.
- Files changed, skills and agents used, verification results.
- Commit messages, one line each, exact format:
  - Format: `type: [ID-#] Action item here`
  - Example: `feat: [ABP-1] Add vendor registry slice`
  - type is a conventional type: feat, fix, refactor, perf, test, docs, chore.
  - ID comes from FEATURE DETAILS. Action item is imperative and specific.
- Commits: auto-commit is allowed. Make as many small, logical commits as needed, each in the exact format above. Stage only files you changed for that commit.
- Authorship: the configured git user (EHIBE) is the only author. No `Co-Authored-By` trailers, no AI attribution, no generated-by lines. Never change git config or author.

## 9. HARD CONSTRAINTS
- No assumptions. Flag every ambiguity.
- Auto-commit allowed per section 8. No co-authoring and no AI attribution ever.
- No other git write operations: push, merge, rebase, stash, reset, checkout, branch creation, amend.
- No dependency changes or destructive commands without approval. Local environment only. Sandbox PayPal only.
- Zero code comments. Exception: PHPDoc type annotations and array shapes the analyzer needs.
- No debug code, commented-out blocks, or test data outside seeders and factories.
- No regressions.

---

## FEATURE DETAILS (write here)
- ID: ABP-8
- Feature: Finalize documents
- Branch: task/ABP-8-finalize-documents (already checked out)
- Scope (owner's words are the priority, cover all of them fully and in depth):
  - Read every file in `docs/` fully before writing.
  - Add Markdown docs that fully explain the entire project for the PayPal AI Hackathon: purpose, problem, persona, design, architecture, API, data, security, AI, operations. Research which docs a project like this needs. Make them as detailed and complete as possible, without missing details from the PRD or the code.
  - Make every doc AI-optimized: YAML front matter (title, purpose, audience, status planned or implemented, related docs, last verified date), a TL;DR at the top, one topic per file, stable IDs (FR-, H, S, US-, OQ-) reused from the PRD, tables over prose, explicit source-of-truth statements, no vague pronouns, relative cross links, mermaid diagrams where flow matters.
  - Planned docs layout (adjust only with reason): `docs/README.md` (index and reading order for humans and agents), `docs/product/` (overview and hackathon purpose, personas, user stories, hackathon compliance and judging, demo script, roadmap), `docs/architecture/` (overview, vertical slice architecture, slice catalog with responsibilities and dependencies, Filament separation with model traits, request lifecycles, state machines, data model, guard spec), `docs/api/` (MCP tools reference, HTTP endpoints, PayPal webhooks, Telegram webhook and callbacks, error formats), `docs/ai/` (extractor, analyst, built-in AP agent, injection isolation, eval set), `docs/integrations/` (PayPal Payouts, OAuth, idempotency, reconcile, sandbox setup, Telegram), `docs/security/` (threat model, trust boundaries, OWASP Top 10:2025 and OWASP LLM Top 10 mapping, secrets, audit hash chain), `docs/development/` (local setup, coding conventions, testing strategy, how to add a slice, how to add a Filament resource with model traits, commit and branch rules, AI agents and skills guide), `docs/operations/` (configuration and env vars, deployment on Render, runbook, demo reset, incident handling), `docs/decisions/` (ADRs: vertical slice, Filament separation with model traits, database drivers over Redis, Render Postgres, Pest over PHPUnit, readonly DTOs over spatie/laravel-data), `docs/glossary.md`.
  - Document the vertical slice architecture exactly as section 4.1 defines it, studied from `C:\Users\ADMIN\Documents\Work\Freelance\payment-gateway` (`app/Features/Payout`, `app/Features/BankInformation`, `app/Filament/Resources/Payouts`, `app/Filament/Agent/Resources/BankInformation`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `.ai/guidelines/laravel.md`). Show the folder tree, the model trait pattern with an illustrative skeleton, provider and route auto-discovery, and slice tests. Note where this project intentionally differs from the reference (Pest, camelCase, readonly DTOs, no `Model::unguard()`, Laravel 13, Filament 5).
  - Mark every doc section that describes unbuilt code as planned. Never claim something exists that does not.
  - Rewrite `README.md` from the Laravel default into the project README per PRD section 23, with planned items marked.
  - Add project sections to CLAUDE.md and AGENTS.md outside the Boost block: project summary, architecture rules, docs map, current state, pending queue (PRD build blocks B1 to B9 and P1 to P3), constraints, known bugs. Record durable architecture rules in `.ai/rules` through the Boost `record-rule` tool.
  - Create project agents in `.claude/agents/`, each with `model: claude-sonnet-5-5` and `effort: medium`, least-privilege tools, a precise description of when to use it, and its checklist: slice-architect (read-only), filament-ui-builder, paypal-integration-engineer, ai-guard-engineer, mcp-gateway-engineer, pest-test-engineer, security-auditor (read-only), money-integrity-reviewer (read-only), code-reviewer (read-only), docs-writer. Research the roster first and justify any change.
  - Create project skills in `.claude/skills/<name>/SKILL.md`, security first: owasp-security-guardrails, prompt-injection-defense, payment-idempotency-and-state-machine, webhook-signature-verification, secrets-and-pii-hygiene, audit-hash-chain, mcp-tool-design, vertical-slice-scaffold, filament-model-traits. Research what else the project needs and justify additions. Each skill has a clear trigger description, steps, checklists, and project-specific paths.
  - Rename from `anti-body` to `anti-body-paypal` where it is an identifier: `package-lock.json` name, `package.json` name if added, composer `name`, `APP_NAME` in `.env.example`, PRD repo `EHIBE/anti-body`, Render services `anti-body-web` and `anti-body-db`, PRD filename to `docs/prd-anti-body-paypal.md` with links updated. Keep the brand "Anti-Body" and the MCP path `/mcp/anti-body`.
  - No application code changes beyond the rename. Commit in small logical steps per section 8.
