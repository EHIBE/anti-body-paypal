# Anti-Body (anti-body-paypal)

Project sections below are maintained by hand. Keep them above the Laravel Boost guidelines block, which Boost regenerates.

## Project summary

| Field | Value |
|---|---|
| Product | Anti-Body: a payment gate between AI agents and a small business PayPal account. Agents propose vendor invoice payments. Only Anti-Body moves money. Outcomes: AUTO, HOLD, BLOCK |
| Hackathon | PayPal AI Hackathon (Devpost). Deadline Thu Nov 12, 2026, 12:00 pm PT = Fri Nov 13, 2026, 4:00 am PHT. Target submit Tue Nov 10, 2026 (PHT) |
| Identifiers | Repo and package `anti-body-paypal`, composer `ehibe/anti-body-paypal`, GitHub `EHIBE/anti-body-paypal`, Render `anti-body-paypal-web` and `anti-body-paypal-db`. Brand "Anti-Body". Kept as-is: MCP path `/mcp/anti-body`, local MCP server `anti-body`, `antibody:*` commands, `ANTIBODY_*` env vars, `AB-{invoice_id}` batch IDs |
| Source of truth | Product: `docs/prd-anti-body-paypal.md`. Code wins over docs for what exists. Docs index: `docs/README.md` |
| Stack (installed) | PHP 8.5.11 (composer constraint `^8.4`), laravel/framework 13.39.0, filament/filament 5.11.0 (Livewire 4), pestphp/pest 5.3.4 + pest-plugin-laravel 5.0.1 (paratest 7.27.5 for `--parallel`), laravel/pint 1.32.1, larastan/larastan 3.13.2 on phpstan/phpstan 2.3.4, phpstan/phpstan-strict-rules 2.1.0, rector/rector 2.8.3, husky 9.1.7 (npm), laravel/boost 2.10.4, laravel/mcp 1.1.2 (transitive via Boost), Tailwind CSS 4, Vite 8 |
| Planned, not installed (approval required) | laravel/ai, laravel/sanctum, laravel-notification-channels/telegram, laravel/mcp as a direct dependency, barryvdh/laravel-ide-helper (skipped by owner decision) |
| Data | SQLite locally (`database/database.sqlite`), SQLite `:memory:` in tests, Render Postgres in production. Queue, cache and session on database drivers |
| Commands | Run PHP and Composer from PowerShell (not on the Git Bash PATH). Full gate `composer ci:check` (same command as pre-push and CI). Test `php artisan test --compact` (single: `--filter=Name` or a file path; one suite: `--testsuite=Shared`). Analyze `composer types:check` (Larastan level max, strict rules, no baseline). Lint `composer lint:check` (CI form) or `vendor/bin/pint --dirty --format agent` (local agent form). Refactor check `composer refactor:check`, fix `composer fix`. Audit `composer audit:check`. Hooks `npm run prepare` (sets local `core.hooksPath`). Dev `composer run dev` |

## Docs map

| Need | Read |
|---|---|
| Reading order and doc index | `docs/README.md` |
| Product, personas, user stories, compliance, demo, roadmap | `docs/product/` |
| Architecture, slices, Filament separation, lifecycles, state machines, data model, guard spec | `docs/architecture/` |
| MCP tools, HTTP endpoints, PayPal and Telegram webhooks, error formats | `docs/api/` |
| Extractor, analyst, AP agent, injection isolation, eval set | `docs/ai/` |
| PayPal Payouts, OAuth, idempotency, reconcile, sandbox, Telegram | `docs/integrations/` |
| Threat model, trust boundaries, OWASP mapping, secrets, audit hash chain | `docs/security/` |
| Local setup, conventions, testing, adding a slice or resource, commits, agents and skills | `docs/development/` |
| Configuration, Render deployment, runbook, demo reset, incidents | `docs/operations/` |
| Architecture decision records | `docs/decisions/` |
| Terms and ID schemes | `docs/glossary.md` |
| Path-scoped coding rules | `.ai/rules/index.md` (read every rule whose globs match the paths in scope before editing) |
| Quality gates, hooks, CI | `docs/decisions/0007-local-hooks-and-single-ci-gate.md`, `.claude/skills/quality-gates-and-hooks/SKILL.md`, `.ai/rules/quality-gates.md` |

## Architecture rules (binding)

| Area | Rule |
|---|---|
| Slices | Every domain lives in `app/Features/<Slice>/` with only the sub-folders it needs. Cross-slice use only along the edges in `tests/Architecture/SliceMap.php` and only through public namespaces (Actions, Data, Enums, Events, Exceptions, Contracts); Shared depends on no slice (enforced by `SliceIsolationTest`). Planned slices: Shared, Vendor, Invoice, Extraction, Guard, Approval, Payment, Webhook, Telegram, AgentGateway, Audit, Policy, Baseline. No new slice without approval. PRD paths `app/Domain/*`, `app/Ai/*`, `app/Mcp/*` map into these slices |
| Layers | Entry points (thin controllers, MCP tools, commands, Filament resources) parse, authorize, delegate to one Action, respond. Business logic only in `Actions/` (`VerbNounAction::handle()`). Validation in Form Requests, MCP schemas, Filament rules, then readonly `Data/` objects |
| Filament | Resources in `app/Filament/Resources/<Plural>/` with `Pages/`, `Schemas/`, `Tables/`. Field, column, filter and infolist definitions in model traits (`getForm()`, `getTableSchema()`, `getFilters()`, `getInfolist()`) with typed boolean context flags. `->authorize()` on every action |
| Wiring | Planned: `AppServiceProvider` auto-registers slice `Providers/*ServiceProvider.php`; `bootstrap/app.php` `then:` loads slice `Routes/web.php` (web middleware) and `Routes/api.php` (api middleware, no prefix). Implemented: every slice keeps unit and feature tests in `app/Features/<Slice>/Tests/{Unit,Feature}` with its own `<testsuite name="<Slice>">` and `<source>` exclude in `phpunit.xml`; `tests/Pest.php` binds `TestCase` + `RefreshDatabase` to `AccessMatrix` and `../app/Features/*/Tests/Feature`. Root `tests/` holds only `Architecture`, `AccessMatrix`, `Fixtures`, `Support` |
| Guard | H1 to H10, S1 to S7 and thresholds live only in the Guard slice. Score is monotonic, summed in PHP. AI never scores or decides. Hard-rule BLOCK never overridable. Auto ceiling $500 absolute |
| Money and payouts | Integer minor units (`*_minor`), UTC times. Amount and payee only from the DB. One batch per invoice, `sender_batch_id` = `PayPal-Request-Id` = `AB-{invoice_id}`. Never `submitting` back to `queued`. Row locks on transitions |
| Agents | Agents only propose. No tool approves, rejects, overrides, edits vendors, or accepts amount or payee. Agent `note` is data, never parsed or sent to a model |
| Code style | `declare(strict_types=1)` (Pint `declare_strict_types`, except the 3 applied starter migrations), `final` Actions with one public `handle()`, `final readonly` Data, `final` concrete slice classes (enforced by `StructureTest`), camelCase variables, `#[\Override]`, `#[Fillable]`/`#[Hidden]` (never `Model::unguard()`), UUIDv7 via `HasUuids` (except framework tables, `agents`, `audit_logs`), string-backed TitleCase enums with `HasLabel`/`HasColor`, zero code comments except PHPDoc types and array shapes, `config()` never `env()` outside `config/` |

## Current state (2026-10-30)

- Code: Laravel 13 skeleton plus Filament 5 panel `admin` at `/admin` (`app/Providers/Filament/AdminPanelProvider.php`). `app/Features/Shared` holds `Http/Middleware/SecurityHeaders.php` (global), `Authorization/RequiresAbility.php` and the Shared tests. No domain slices or migrations, no Dockerfile, `render.yaml` or Makefile.
- Quality gates (ABP-9): Husky hooks in `.husky/` (commit-msg, pre-commit, pre-push), `composer ci:check`, `phpstan.neon` (max), `rector.php`, `pint.json`, `.gitleaks.toml`, `.github/workflows/tests.yml` (`ci`, `postgres`) and `security.yml` (`gitleaks`, `dependency-review`, `zizmor`, `actionlint`), Dependabot, PR template. Suites: `Shared`, `Architecture`, `AccessMatrix`. Branch protection on `main` is an owner step (ADR 0007).
- Docs: full documentation set in `docs/` (ABP-8). Every unbuilt item is marked planned.
- Agent tooling: 10 project agents in `.claude/agents/`, 11 project skills in `.claude/skills/`, 15 path-scoped rules in `.ai/rules/` (ABP-8).
- Schedule: PRD Day 7 of 14. Build blocks B1 to B9 not started in code. B1 PayPal spike results are not recorded in the repo.

## Pending queue (PRD section 21 order)

| # | Block | Scope | Hours | Status |
|---|---|---|---|---|
| 1 | B1 | PayPal spike (PRD 16.5): OAuth, $5 payout, duplicate `sender_batch_id`, webhook through tunnel, signature verify, show item, Transaction Search permission | 4 | pending (results not recorded) |
| 2 | B2 | Skeleton wiring, Docker, Render deploy, CI | 2 | partial (Filament installed, CI and gates done in ABP-9; Docker, Render, slice provider and route wiring pending) |
| 3 | B3 | Data model, state machine, idempotency, payout executor, webhook handler | 6 | pending |
| 4 | B4 | Extractor | 5 | pending |
| 5 | B5 | Hard rules, scorer, analyst, decision engine | 8 | pending |
| 6 | B6 | MCP server + built-in AP agent | 6 | pending |
| 7 | B7 | Filament dashboard + approval queue | 5 | pending |
| 8 | B8 | Telegram approvals | 4 | pending |
| 9 | B9 | Seeders, demo PDFs, baseline sync, eval set, tests | 4 | pending |
| 10 | P1 | UI polish | 4 | pending |
| 11 | P2 | README judge access, deploy hardening | 4 | pending |
| 12 | P3 | Video, Devpost draft | 4 | pending |

Cut order if behind (PRD R6): FR-32, FR-36, FR-37, FR-43, FR-17.

## Constraints

- No dependency changes, destructive commands or new slices without approval. Local environment only. Sandbox PayPal only.
- Commits: `type: [ABP-#] Imperative action` (types feat, fix, refactor, perf, test, docs, chore), enforced by `.husky/commit-msg`. Branches `task/ABP-#-kebab-slug`, enforced by `.husky/pre-push` (no push to `main`). Never bypass hooks (`--no-verify`, `HUSKY=0`); fix the cause. Commit from PowerShell. Author is the configured git user only. No `Co-Authored-By`, no AI attribution. Agents never push, merge, rebase, stash, reset, checkout, create branches or amend.
- Zero code comments (PHPDoc types excepted). No debug code, commented-out blocks, or test data outside seeders and factories. No regressions.
- Public repo: no secrets, real names, real emails or real invoices. Fictional vendors and sandbox emails only.
- Mark docs for unbuilt code as planned. Never claim something exists that does not.

## Known bugs and open issues

| ID | Issue | Default until resolved |
|---|---|---|
| OQ-12 | `claude-haiku-5-5` (PRD extractor and AP agent model) is not in Anthropic's published model list as of 2026-09-25 | Configure via `ANTIBODY_EXTRACTOR_MODEL`; fallback `claude-haiku-4-5` (no `effort` parameter on Haiku 4.5) |
| OQ-13 | PRD 11.1 uses real-looking domains (`boxpro.ph`, `boxpro-ph.com`) | Demo data uses `.example` and sandbox emails |
| OQ-14 | H5 ISO week timezone (owner is in PHT) | UTC |
| OQ-15 | Telegram token expires in 15 min, HOLD lasts 72 h | Dashboard-only approval after token expiry |
| OQ-17 | Canonical JSON for the audit hash chain | Recursively key-sorted, unescaped slashes and unicode, excluding `id` and `hash` |
| BUG-01 | `AppModelsUser` does not implement `FilamentModelsContractsFilamentUser`. Outside `APP_ENV=local` Filament answers 403 on every panel page after login, so production `/admin` is unusable | Fix in B2 or B7 with `canAccessPanel()` for the owner (behavior change, needs owner approval) |
| BUG-02 | No trusted proxies are configured, so behind Render's TLS proxy `isSecure()` is false: HSTS is never sent and generated URLs may be `http`; `SESSION_SECURE_COOKIE` is unset | Configure `trustProxies` and `SESSION_SECURE_COOKIE=true` in B2 with an `X-Forwarded-Proto` test |
| OQ-18 | `antibody:demo-reset` was assigned to Shared, but Shared may not depend on any slice (ABP-9 `SliceIsolationTest`) | Owner decides the owning slice in B9 |

Open questions OQ-01 to OQ-11 remain as listed in PRD section 27.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
