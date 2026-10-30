# Anti-Body (anti-body-paypal): FEATURE PROMPT v1 (ABP-9)

## 1. LOAD
- Read FEATURE DETAILS at the bottom first. They are the bulk of this ticket and binding.
- Read CLAUDE.md and AGENTS.md fully. Load state, pending queue, constraints, known bugs. Their constraints are binding. Project sections live outside the `<laravel-boost-guidelines>` block because Boost regenerates that block.
- Read `.ai/rules/index.md` and every rule file whose globs cover the paths in scope. Run `grep -rin 'keyword' .ai/rules` for the feature keywords (test, arch, phpstan, rector, hook, security, header).
- Read every file in `docs/` fully. `docs/prd-anti-body-paypal.md` is the product source of truth. `docs/development/testing-strategy.md`, `docs/development/commit-and-branch-rules.md`, `docs/development/local-setup.md`, `docs/security/owasp-mapping.md` and `docs/architecture/slice-catalog.md` drive this ticket.
- Read every file related to the feature before any edit: `composer.json`, `package.json`, `phpunit.xml`, `tests/Pest.php`, `tests/TestCase.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `app/Providers/**`, `.gitignore`, `.gitattributes`, `.editorconfig`, `.npmrc`, `.env.example`, `config/app.php`.
- Read the reference project fully, read-only: `C:\Users\ADMIN\Documents\Projects\JPG\scoring-and-event-monitoring-system` (files listed in FEATURE DETAILS). Never edit it.
- List all available skills (`.claude/skills/`, user-level, plugin). Read fully every skill relevant to this feature before using it.
- FEATURE DETAILS empty: propose the top pending-queue item from CLAUDE.md and confirm it.
- State the current project state in one sentence.

## 2. CLARIFY (gate)
- The owner answered "use defaults" to the ABP-9 discovery questions. The decisions are baked into FEATURE DETAILS. Ask only about new ambiguities found while reading.
- Ask numbered questions with a recommended default on each: scope and acceptance criteria, roles and permissions, inputs and validation, data changes, interfaces, UI states, edge cases, abuse cases, security. Skip what the files answer.
- Ticket ID missing from FEATURE DETAILS: ask for it. Prefix is `ABP`.
- Give a proposed approach, no code: files to add and edit, tests to add, skills and agents to use, commit plan.
- STOP until answers or "use defaults".

## 3. PROJECT
- Product: Anti-Body, a payment gate between AI agents and a small business PayPal account. Agents propose vendor invoice payments. Only Anti-Body moves money. Outcomes AUTO, HOLD, BLOCK. PayPal AI Hackathon (Devpost). Repo `EHIBE/anti-body-paypal` (public). Brand "Anti-Body".
- Stack: PHP 8.5.11 (Herd on Windows), Composer 2.9.5, Node 22.23.3, npm 10.9.9, laravel/framework 13.39.0, filament/filament 5.11.0, livewire/livewire 4.4.8, pestphp/pest 5.3.4 with pest-plugin-laravel 5.0.1, laravel/pint 1.32.1, laravel/boost 2.10.4, laravel/mcp 1.1.2 (transitive), Tailwind CSS 4, Vite 8. SQLite locally, SQLite `:memory:` in tests, Render Postgres in production. Queue, cache, session on database drivers. Approved for ABP-9 only: larastan/larastan, phpstan/phpstan-strict-rules, rector/rector, npm husky. Still planned and unapproved: laravel/ai, laravel/sanctum, laravel-notification-channels/telegram, laravel/mcp as a direct dependency, barryvdh/laravel-ide-helper.
- Key paths: `app/Features/<Slice>/` (vertical slices), `app/Features/Shared/` (cross-cutting only), `app/Filament/` (panel UI), `app/Providers/Filament/AdminPanelProvider.php` (panel `admin` at `/admin`), `bootstrap/app.php`, `tests/Architecture/`, `tests/AccessMatrix/`, `tests/Feature/Shared/`, `tests/Fixtures/`, `tests/Support/`, `.github/workflows/`, `.github/dependabot.yml`, `.github/pull_request_template.md`, `.husky/`, `phpstan.neon`, `rector.php`, `pint.json`, `.gitleaks.toml`, `docs/`, `docs/decisions/`, `.claude/agents/`, `.claude/skills/`, `.ai/rules/`.
- Commands: test `php artisan test --compact` | single `php artisan test --compact --filter=Name` or a file path | analyze `composer types:check` (Larastan, level max) | lint `composer lint:check` (Pint test mode, CI form) | format `vendor/bin/pint --dirty --format agent` (local agent form) | refactor `composer refactor:check` (Rector dry run) | audit `composer audit` and `npm audit --audit-level=high` | full gate `composer ci:check`
- Conventions: `declare(strict_types=1)` in every PHP file except the three applied starter migrations. Namespace mirrors path. Actions are `final`, named `VerbNounAction`, one public `handle()`. `Data/` classes are `final readonly`. Concrete slice classes are `final`. String-backed TitleCase enums with `HasLabel` and `HasColor` when shown in UI. Money in integer minor units. UUIDv7 via `HasUuids` except framework tables, `agents`, `audit_logs`. UTC times. `#[Fillable]` and `#[Hidden]`, never `Model::unguard()`. `config()`, never `env()` outside `config/`. `#[\Override]` on overrides. camelCase variables. Tests use the typed `Pest\Laravel` functions (`get()`, `postJson()`, `actingAs()`), never `$this->`, so PHPStan sees types.
- Environment: in the Bash tool `php` and `composer` are not on PATH. Run PHP and Composer from PowerShell. Hooks reach PHP through `npm run`, which uses cmd.exe on Windows. Commit from PowerShell.
- Agents (`.claude/agents/`, all `model: claude-sonnet-5-5`, `effort: medium`): slice-architect (read-only), filament-ui-builder, paypal-integration-engineer, ai-guard-engineer, mcp-gateway-engineer, pest-test-engineer, security-auditor (read-only), money-integrity-reviewer (read-only), code-reviewer (read-only), docs-writer. Built-in: Explore, Plan, general-purpose.
- Skills:
  - owasp-security-guardrails: security headers, CSRF, error leakage, access control tests, every security control in this ticket.
  - secrets-and-pii-hygiene: secret scanning, `.gitleaks.toml`, pre-commit secret guard, `.env.example`, docs.
  - testing-best-practices: test design, naming, deterministic security boundary tests.
  - laravel-best-practices: middleware, config, providers, composer scripts touching Laravel.
  - vertical-slice-scaffold: the Shared slice files (`Http/Middleware`, `Authorization`).
  - webhook-signature-verification: CSRF exclusion rules the security tests assert.
  - mcp-tool-design: agent rows in the access matrix.
  - security-review, code-review, simplify: review the branch diff before the final commit.
  - infer-conventions: only to record the quality-gates rule in `.ai/rules` through `record-rule`.
  - deploying-to-cloud: never (Render, not Laravel Cloud).

## 4. IMPLEMENT

### 4.1 Design first
- Restate every acceptance criterion in FEATURE DETAILS as a testable check before writing code or config.
- Study each reference file, decide applicable or not, and record the decision with a reason in ADR 0007. Apply only what fits a Laravel 13, Filament 5, Livewire, Pest, no-Vue project.
- Place every responsibility in its layer:
  - Local gates: `.husky/commit-msg`, `.husky/pre-commit`, `.husky/pre-push`. Hooks hold only the commit message pattern, the staging guard, the env-file guard and the staged secret scan. Every code check is a project script.
  - Gate definitions: `composer.json` `scripts` is the single source. `package.json` scripts only forward (`prepare`, `pre-commit`, `ci:check`). CI and pre-push call the same `composer ci:check`, so they cannot drift.
  - Tool config: `phpstan.neon`, `rector.php`, `pint.json`, `.gitleaks.toml` at the repo root.
  - Remote gates: `.github/workflows/tests.yml` (jobs `ci` and `postgres`), `.github/workflows/security.yml` (gitleaks, dependency review, zizmor, actionlint), `.github/dependabot.yml`, `.github/pull_request_template.md`.
  - Architecture rules: `tests/Architecture/` with helpers `SliceClasses.php`, `ClassRules.php`, `SliceMap.php` that return violation messages naming the class.
  - Access control coverage: `tests/AccessMatrix/` with `AccessMatrix.php`, `MatrixRow.php`, `ProtectedSurface.php`.
  - Runtime security code: `app/Features/Shared/Http/Middleware/SecurityHeaders.php` (registered in `bootstrap/app.php` `withMiddleware()`), `app/Features/Shared/Authorization/RequiresAbility.php` (attribute the matrix scanner reads), security config keys in `config/antibody.php` read through `config()`.
  - Runtime security tests: `tests/Feature/Shared/`.
  - Tooling self-tests: `tests/Feature/Tooling/` (hooks, workflows, composer scripts, config files).
  - Fixtures proving each scanner detects violations: `tests/Fixtures/`.
- Reuse what exists: `bootstrap/app.php` JSON error rendering, the panel middleware in `AdminPanelProvider`, `.npmrc` `ignore-scripts=true` and `audit=true`, `.editorconfig`, `.gitattributes`.
- Use project skills whenever their purpose matches. Hard constraints win over skill instructions.

### 4.2 SOLID
- Single Responsibility: each hook, script, workflow job, test helper and middleware has one reason to change. Hooks call scripts and never duplicate check lists. `SecurityHeaders` only sets headers. Test helpers only collect facts or violations.
- Open/Closed: new arch rules are new `ClassRules` methods and new `it()` cases. New slices are new `SliceMap` entries. New protected surfaces are new `MatrixRow` rows. Never rewrite existing behavior to add a rule.
- Liskov Substitution: middleware honors the Laravel middleware contract and returns the downstream response. Never replace a header a downstream layer already set.
- Interface Segregation: test helpers expose small static queries (`actions()`, `dataObjects()`, `forbiddenNamespaces()`). No consumer depends on methods it does not call.
- Dependency Inversion: inject `Illuminate\Contracts\Config\Repository` into the middleware through the container with constructor property promotion. No `app()` or `config()` helper lookups inside the middleware body.

### 4.3 Code quality
- DRY: one authoritative list per rule. The gate list lives only in `composer.json`. The commit pattern lives only in `.husky/commit-msg` and is documented by linking to it. The slice dependency map lives only in `tests/Architecture/SliceMap.php`. Header values live only in `SecurityHeaders` and `config/antibody.php`.
- KISS and YAGNI: copy the reference shape, strip what does not apply. No speculative jobs, matrices, coverage gates or mutation runs.
- Guard clauses and early returns. Max two nesting levels. No else after return.
- Small functions with one purpose and few parameters.
- Names reveal intent: `it('keeps every action final with one public handle method')`. Test names state the rule, never the mechanism.
- Strict typing: declared parameter and return types everywhere, PHPDoc array shapes and `class-string` types for helpers, `const array` typed constants, no magic strings repeated across files.
- Prefer immutable values. `final readonly` value objects for matrix rows.
- Talk only to direct collaborators.
- Errors: hooks print the failing file or rule and the fix to stderr, then exit non-zero. Never swallow a tool's exit code. Shell hooks start with `#!/bin/sh` and stay POSIX.
- Return empty arrays, never null, from helpers.
- PHPStan errors are fixed in code. No baseline, no inline `@phpstan-ignore`. An `ignoreErrors` entry needs an identifier, a path and a written reason (as in the reference for `arch()` chains and Eloquent forwarded calls), and is removed when the cause goes away.
- Rector changes to existing code must be reviewed line by line and must not change behavior. Reject any Rector rule that fights a project convention and record the skip in `rector.php` with a reason in ADR 0007.

### 4.4 Data and persistence
- No schema changes in this ticket. Never edit an applied migration. The three starter migrations stay untouched and are excluded from Pint `declare_strict_types`, Rector and the strict-types arch test by exact path.
- Prove engine portability: the `postgres` CI job runs `php artisan migrate:fresh --seed --force` and the full suite on a Postgres service. Verify the job's `DB_CONNECTION=pgsql` env actually overrides the SQLite values in `phpunit.xml` (assert in the job log that the pgsql driver ran).
- `RawSqlTest` scans `app/` and `database/` (excluding `Tests/` and `factories/`) for raw SQL string literals that alias a column to a Postgres reserved word, with a self-test proving a bad alias is caught and a safe one passes.
- Keep `phpunit.xml` on SQLite `:memory:` for local and the `ci` job.

### 4.5 Interfaces and integration
- `composer.json` scripts, exact names and order:
  - `lint`: `pint --parallel`
  - `lint:check`: `pint --parallel --test`
  - `rector`: `rector`
  - `refactor:check`: `rector process --dry-run --no-progress-bar`
  - `types:check`: `phpstan analyse --memory-limit=1G --no-progress`
  - `audit:check`: `@composer audit` then `npm audit --audit-level=high`
  - `fix`: `@rector` then `@lint`
  - `check`: `@composer validate --strict`, `@refactor:check`, `@lint:check`, `@types:check`, `@audit:check`
  - `test`: `@php artisan config:clear --ansi` then `@php artisan test --parallel`
  - `ci:check`: `Composer\\Config::disableProcessTimeout`, `@check`, `npm run build`, `@test`
  - `setup`: keep current steps and add `npm run prepare` after `npm install --ignore-scripts`
- `package.json` scripts: `prepare` = `husky`, `pre-commit` = `composer rector && composer lint`, `ci:check` = `composer ci:check`. `.npmrc` keeps `ignore-scripts=true`, so `prepare` runs only through `composer setup` or `npm run prepare`.
- `composer.json`: `php` constraint `^8.4`. Add `phpstan/extension-installer` to `allow-plugins` only if the installed packages require it, otherwise include extensions explicitly in `phpstan.neon`.
- `phpstan.neon`: includes `vendor/larastan/larastan/extension.neon`, `vendor/nesbot/carbon/extension.neon`, `vendor/phpstan/phpstan-strict-rules/rules.neon`, `vendor/pestphp/pest/extension.neon` (verify each path exists after install). `level: max`. Paths `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, `tests/`. `tmpDir: storage/framework/cache/phpstan`.
- `rector.php`: paths `app`, `bootstrap/app.php`, `config`, `database`, `routes`, `tests`. Skip `bootstrap/cache`, `database/migrations`, `storage`. Cache in `storage/framework/cache/rector` (the shared temp folder breaks when two projects run Rector). `withPhpSets()` and prepared sets `deadCode`, `codeQuality`, `codingStyle`, `typeDeclarations`.
- `pint.json`: preset `laravel`, rule `declare_strict_types: true`, exclude the three starter migrations by path.
- `phpunit.xml`: add testsuites `Architecture` (`tests/Architecture`), `AccessMatrix` (`tests/AccessMatrix`), and add `app/Features/*/Tests/Unit` and `app/Features/*/Tests/Feature` to the `Unit` and `Feature` suites. Keep the env block.
- `tests/Pest.php`: `declare(strict_types=1)`. Bind `Tests\TestCase` with `RefreshDatabase` to `Feature`, `AccessMatrix` and `../app/Features/*/Tests/Feature`. Remove the starter comments, the commented `RefreshDatabase` line, the `toBeOne` expectation and the `something()` function. Architecture and Unit suites run without a database.
- `.gitignore`: add `/storage/framework/cache/phpstan` and `/storage/framework/cache/rector` only if the existing storage `.gitignore` files do not already cover them.
- New config keys: `config/antibody.php` keys `security.csp_report_only` (default true) and `security.hsts_max_age` (default 15552000), read from `ANTIBODY_CSP_REPORT_ONLY` and `ANTIBODY_HSTS_MAX_AGE`. Add both to `.env.example` with placeholder defaults and to `docs/operations/configuration.md`.
- Workflows: every `uses:` pinned to a full 40-character commit SHA with the version in a trailing comment. Reuse the reference SHAs for `actions/checkout`, `shivammathur/setup-php`, `actions/cache` and `actions/setup-node`. Resolve every other SHA from the action's latest release with `git ls-remote` or the GitHub API. Never invent a SHA. Top-level `permissions: contents: read`. Every checkout uses `persist-credentials: false`. `concurrency` cancels superseded runs except on `main`. `timeout-minutes` on every job. Composer cache keyed on `composer.lock`, npm cache on `package-lock.json`.
- Set timeouts on every external call. Retry only idempotent calls, with a limit.
- Cache only with a defined invalidation rule (CI caches keyed on lockfiles).

### 4.6 Completion
- Build everything in FEATURE DETAILS completely, end to end. No stubs, TODOs, or placeholders.
- Edit files in place. No duplicate or parallel files. New files only inside the established structure. `.github/` and `.husky/` are approved new root folders for this ticket. No other new root folder.
- Refactor only what the gates require (strict types, Rector and PHPStan fixes on existing files).
- Run every gate locally until green before the final commit: `composer ci:check` from PowerShell.
- Agents: pest-test-engineer writes the test suites. security-auditor and code-reviewer review the full diff read-only before the last commit. docs-writer updates docs. One writer per file. Verify every agent result against source.

## 5. SECURITY GUARDRAILS (mapped to OWASP Top 10:2025)

### A01 Broken Access Control
- Deny by default. Authorize every entry point server-side with Policies, Filament resource authorization, `->authorize()` on every Filament action, and (once Sanctum is approved) `auth:sanctum` plus H10 on MCP routes.
- The access matrix lists every authenticated route and every `#[RequiresAbility]` class with the actors allowed (`owner`, `agent`, `guest`). The coverage test fails on any protected surface missing from the matrix. Filament (`filament.*`) and Livewire (`livewire.*`) routes are covered by panel tests instead.
- Test that a guest is redirected to `/admin/login` from `/admin` and every panel page, and gets 401 on any `api/*` route even without `Accept: application/json`.
- Agent rows stay planned until Sanctum is approved. Never add an agent row that grants approve, reject, override, vendor edit, amount or payee.
- Check ownership on every record read, update, and delete. Never trust a client-supplied ID or role.
- Outbound requests built from user input: allowlist hosts, block internal and metadata addresses, do not follow redirects.

### A02 Security Misconfiguration
- `SecurityHeaders` sets, unless already present: `X-Content-Type-Options` `nosniff`, `X-Frame-Options` `DENY`, `Referrer-Policy` `strict-origin-when-cross-origin`, `Permissions-Policy` with camera, microphone and geolocation disabled, and a Content Security Policy.
- CSP is report-only by default (`Content-Security-Policy-Report-Only`) and enforced when `antibody.security.csp_report_only` is false.
- CSP for `/admin` and `livewire/*`: default-src self, script-src self plus `unsafe-inline` and `unsafe-eval` (Filament and Alpine need them), style-src self plus `unsafe-inline`, img-src self plus `data:` and `blob:`, font-src self plus `data:`, connect-src self, frame-ancestors none, base-uri self, form-action self.
- CSP for `api/*` and `webhooks/*`: default-src none, frame-ancestors none, base-uri none, form-action none.
- CSP for every other page: default-src self, script-src self without `unsafe-eval`, frame-ancestors none, base-uri self, form-action self.
- `Strict-Transport-Security` with `max-age` from config and `includeSubDomains`, only on secure requests.
- `config/app.php` `debug` defaults to false. Test it. Production errors return no stack trace, no exception message, no file path.
- Keep CORS restrictive. Publish `config/cors.php` only if a feature needs it.

### A03 Software Supply Chain Failures
- Install only the four approved dev dependencies. Respect `composer.lock` and `package-lock.json`. Commit both lockfiles.
- `composer audit` and `npm audit --audit-level=high` run in `composer ci:check`, therefore on every push and every CI run.
- `composer validate --strict` runs first in the gate.
- Dependabot: ecosystems `github-actions`, `composer`, `npm`, weekly, `cooldown.default-days: 5`, one group per ecosystem.
- `actions/dependency-review-action` on pull requests fails on high or critical advisories.
- Every GitHub Action pinned to a commit SHA. zizmor and actionlint lint the workflows on every push and pull request.

### A04 Cryptographic Failures
- Use the framework's vetted hashing and encryption only (`Hash`, `Crypt`, `hash_hmac`). No custom crypto.
- Generate tokens with `Str::random()` or `random_bytes()`. Compare secrets with `hash_equals()`.
- Load secrets from environment only. HSTS over HTTPS.

### A05 Injection
- Arch rules forbid `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, `havingRaw` and `DB::statement` in `App` outside an explicit allowlist (empty today). Any future allowlist entry needs a reason and a test proving bound parameters.
- Arch rules forbid `Model::unguard` and `Model::unguarded` in `App`.
- Arch rules forbid `eval`, `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen` and `unserialize` in `App`.
- Encode output for its context. Blade `{{ }}` only. Never render user or invoice content as raw HTML.
- Validate every input on the server. Client validation is convenience only.
- Hooks quote every variable and never pass file names to `eval` or unquoted command substitution. Use `-z` and `xargs -0` for staged paths.

### A06 Insecure Design
- Abuse cases for the gates: bypassing hooks (`--no-verify`, `HUSKY=0`), pushing straight to `main`, committing a partly staged file so unchecked code lands, committing `.env` or a live key, a malicious pull request editing workflows to exfiltrate secrets, a dependency with a known advisory, an action tag moved to malicious code, an AI co-author trailer.
- CI is the backstop for any skipped local hook. Branch protection on `main` (owner setting) makes CI required.
- pre-push refuses a branch that does not match `^task/ABP-[0-9]+-[a-z0-9-]+$` and refuses any push to `main`.
- Workflows never use `pull_request_target`. No secrets are available to pull request jobs.
- Rate limit sensitive and expensive actions with `RateLimiter::for()` when features add them.

### A07 Authentication Failures
- Use the existing Filament panel login only.
- Test that a state-changing web request without a CSRF token returns 419 (`PreventRequestForgery`).
- Give no hints that reveal whether an account exists. Test that the panel login error is identical for an unknown email and a wrong password.

### A08 Software or Data Integrity Failures
- commit-msg enforces `^(feat|fix|refactor|perf|test|docs|chore): \[ABP-[0-9]+\] [^ ].*$` on the first non-comment line and exempts subjects starting with `Merge `, `Revert "`, `fixup! ` and `squash! `.
- commit-msg rejects any line matching `Co-Authored-By:` (case-insensitive) or `Generated with` so the configured git user stays the only author.
- pre-commit refuses a staged file that also has unstaged changes, before any tool runs, and lists the files.
- pre-commit refuses staged `.env` and `.env.*` files except `.env.example`.
- pre-commit scans added lines of the staged diff for: private key headers (`BEGIN` followed by `PRIVATE KEY`), Anthropic keys (`sk-ant-`), Telegram bot tokens (8 to 10 digits, colon, 35 URL-safe characters), PayPal access tokens (`A21AA` prefix), and non-empty assignments to `APP_KEY`, `PAYPAL_CLIENT_SECRET`, `ANTHROPIC_API_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `ANTIBODY_TOKEN_HMAC_KEY`, `DEMO_OWNER_PASSWORD`. Print the file and pattern name, never the matched value.
- pre-commit then runs `npm run pre-commit` (Rector, Pint) and re-stages only the paths that were already staged. Nothing else is staged.
- gitleaks scans full history (`fetch-depth: 0`) on push and pull request with `.gitleaks.toml` extending the default rules plus the project patterns above, and allowlisting `.env.example` placeholders and test fixtures by path.
- Never deserialize untrusted data into objects.

### A09 Security Logging and Alerting Failures
- Hooks and CI never echo secrets, tokens or env values. CI logs print no `.env` contents.
- Never log secrets, tokens, passwords, PayPal or Anthropic keys, or unnecessary personal data.

### A10 Mishandling of Exceptional Conditions
- Fail closed. A hook or gate step that errors blocks the commit, push or merge. `composer` scripts stop on the first failing step.
- Test that an unexpected exception on a JSON route returns 500 with a generic message and no exception text, with `app.debug` false.
- Test that an unknown `api/*` route returns a JSON 404.
- No swallowed exceptions. Handle a missing tool (`sh`, `php`, `npm`) in a hook with a clear message and a non-zero exit.

### Always
- Collect and expose only the data the feature needs.
- Keep secrets out of code, logs, errors, responses, docs, workflows, fixtures and seed data. Use GitHub encrypted secrets only when a later ticket needs one.
- Laravel, Filament, Livewire and CI risks to avoid: `env()` outside config, `dd`, `dump`, `var_dump`, `ray` left in code, Filament actions without `->authorize()`, Livewire public properties trusted without `#[Locked]`, unpinned or tag-pinned actions, `pull_request_target`, `persist-credentials: true`, workflow-level write permissions, `--no-verify`, `HUSKY=0`, `git add -A` or `git add .` inside hooks, real names, emails or keys in the public repo.

## 6. VERIFY
- Add Pest tests for every rule in FEATURE DETAILS: happy path, violation detected (fixture self-tests), edge cases, unauthorized access, abuse cases, failure paths. Deterministic only, no network.
- Run `composer ci:check` from PowerShell until green: validate, Rector dry run, Pint test, PHPStan level max, audits, build, full test suite in parallel. Zero new failures or violations.
- Prove each hook works by real attempts on the branch: a bad commit subject is refused, a co-author trailer is refused, a partly staged file is refused, a staged fake key in a scratch file is refused, then a valid commit passes. Undo the scratch attempts without any forbidden git command (unstage with `git restore --staged`, delete the scratch file).
- Never weaken tests or suppress errors to pass.

## 7. DOCUMENT
- Update related docs in `docs/` and the project sections of CLAUDE.md and AGENTS.md (state, queue, commands, constraints) in place. Mark nothing as implemented that is not.

## 8. REPORT
- Per changed block: short prose on core logic and control flow.
- Files changed, skills and agents used, verification results with exact gate output summaries.
- Commit messages, one line each, exact format:
  - Format: `type: [ID-#] Action item here`
  - Example: `chore: [ABP-9] Add Husky commit-msg hook`
  - type is a conventional type: feat, fix, refactor, perf, test, docs, chore.
  - ID comes from FEATURE DETAILS. Action item is imperative and specific.
- Commits: auto-commit is allowed. Make as many small, logical commits as needed, each in the exact format above. Stage only files you changed for that commit, by explicit path.
- Authorship: the configured git user (EHIBE) is the only author. No `Co-Authored-By` trailers, no AI attribution, no generated-by lines. Never change the author.

## 9. HARD CONSTRAINTS
- No assumptions. Flag every ambiguity.
- Auto-commit allowed per section 8. No co-authoring and no AI attribution ever.
- No other git write operations: push, merge, rebase, stash, reset, checkout, branch creation, amend.
- Git config: the only allowed change is the local `core.hooksPath` that `npm run prepare` (Husky) sets. Nothing else.
- Never bypass hooks with `--no-verify` or `HUSKY=0`. Fix the cause the hook reports.
- No dependency changes beyond the four approved dev dependencies. No destructive commands without approval. Local environment only. Never edit the reference project.
- Never change GitHub repository settings. Document the branch protection steps for the owner.
- Zero code comments. Exception: PHPDoc type annotations and array shapes the analyzer needs. YAML and shell files carry no comments except the version comment after a pinned SHA.
- No debug code, commented-out blocks, or test data outside seeders, factories and `tests/Fixtures`.
- No regressions.

---

## FEATURE DETAILS (write here)
- ID: ABP-9
- Task: Install security checks
- Branch: task/ABP-9-install-security-checks (already checked out)
- Owner intent (highest priority): study fully how the reference project `C:\Users\ADMIN\Documents\Projects\JPG\scoring-and-event-monitoring-system` installs its checks (GitHub workflow, Rector, PHPStan, Husky hooks, architecture and access tests) that run before a branch can be pushed, and apply every applicable check to Anti-Body. Apply only what fits. Make it full, make it secure, add many automated tests.
- Reference files to read fully (read-only): `.github/workflows/tests.yml`, `.github/dependabot.yml`, `.github/pull_request_template.md`, `.husky/commit-msg`, `.husky/pre-commit`, `.husky/pre-push`, `phpstan.neon`, `rector.php`, `pint.json`, `phpunit.xml`, `composer.json` scripts, `package.json` scripts, `.npmrc`, `tests/Pest.php`, `tests/Architecture/*`, `tests/AccessMatrix/*`, `tests/Support/*`, `tests/Fixtures/AccessMatrix/*`, `tests/Feature/Shared/SecurityHeadersTest.php`, `tests/Feature/Shared/GuestApiResponseTest.php`, `tests/Feature/Shared/ProblemJsonRendererTest.php`, `tests/Feature/Shared/RateLimitersTest.php`, `app/Shared/Http/Middleware/SecurityHeaders.php`, `docs/20-testing.md`, ADR-32 in `docs/25-adrs.md`.
- Out of scope (not applicable): Vue, Inertia, Wayfinder, PWA, `vp check`, `vue-tsc`, Vitest, the MySQL job, ide-helper, Reverb and broadcasting tests, `deploy/` scripts, coverage minimums, mutation testing, the nightly eval job (needs laravel/ai).
- Owner decisions (all defaults accepted):
  1. Install dev dependencies larastan/larastan, phpstan/phpstan-strict-rules, rector/rector (Composer) and husky (npm). Record exact installed versions from `composer show` and `npm ls` in docs.
  2. Allow only the local `core.hooksPath` change made by `npm run prepare`. `composer setup` runs `npm run prepare`.
  3. Larastan at `level: max` with strict rules, Carbon and Pest extensions, no baseline, paths `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, `tests/`.
  4. Add `declare(strict_types=1)` to every PHP file missing it (app, bootstrap, config, database factories and seeders, public/index.php, routes, tests) except the three applied starter migrations.
  5. Set the `composer.json` PHP constraint to `^8.4`.
  6. Skip ide-helper.
  7. pre-commit: partial-staging guard, `.env` guard, staged secret scan, Rector then Pint, re-stage only already staged paths.
  8. commit-msg: ABP pattern with the seven types, reject `Co-Authored-By` and `Generated with`, exempt merge, revert, fixup and squash subjects.
  9. pre-push: refuse pushes to `main` and branches not matching `task/ABP-#-slug`, then `npm run ci:check`.
  10. `composer ci:check`: validate strict, Rector dry run, Pint test, PHPStan, `composer audit`, `npm audit --audit-level=high`, `npm run build`, `php artisan test --parallel`.
  11. CI `tests.yml`: job `ci` runs `composer setup` then `composer ci:check` on SQLite. Job `postgres` uses service `postgres:17` with a health check, installs `pdo_pgsql`, runs `migrate:fresh --seed --force` and `php artisan test --compact` on pgsql. Triggers: push to `main` and every pull request.
  12. CI `security.yml`: gitleaks with `.gitleaks.toml`, `actions/dependency-review-action` on pull requests, zizmor and actionlint. No CodeQL (no PHP support).
  13. App security code and tests: `SecurityHeaders` middleware in the Shared slice registered globally, `config/antibody.php` security keys, tests for headers per surface (panel, api, public, HTTPS only HSTS, report-only switch), no error detail leak with debug off, guest redirect from `/admin`, guest 401 on `api/*`, JSON 404 on unknown `api/*`, CSRF 419, generic login error, `app.debug` default false.
  14. Architecture tests:
      - `HygieneTest`: no `dd`, `ddd`, `dump`, `var_dump`, `ray`, `env` in `App`. `App` uses strict types. No dangerous functions or raw query methods (section 5 A05). No `Model::unguard`.
      - `StructureTest` with `ClassRules`: Actions `final`, end in `Action`, exactly one public `handle()`. `Data/` classes `final readonly`. Concrete slice classes `final`. Enums string-backed. No PHP files in `app/Services`, `app/Repositories`, `app/Actions`, `app/Jobs`, `app/Policies`, `app/Http/Controllers` except the base `Controller.php`, `app/Models` except `User.php`.
      - `SliceIsolationTest` with `SliceMap`: allowed dependencies taken from `docs/architecture/slice-catalog.md` for the 13 planned slices. Internal namespaces (`Http`, `Models`, `Policies`, `Providers`, `Listeners`, `Jobs`, `Mcp`, `Ai`, `Routes`, `Commands`) of another slice are never usable. Public namespaces (`Actions`, `Data`, `Enums`, `Events`, `Exceptions`, `Contracts`) are usable only along a declared edge. Shared never depends on another slice. Every slice in the map is in the planned list and every slice folder under `app/Features` is in the map.
      - `RawSqlTest`: Postgres reserved word aliases.
      - Fixture self-tests in `tests/Fixtures/` proving each scanner reports a planted violation.
      - Make Actions and `Data/` classes `final` the binding convention. Update `.ai/rules/actions.md`, `.ai/rules/data.md` and `docs/development/coding-conventions.md` accordingly.
  15. Access matrix: actors `owner`, `agent`, `guest`. `RequiresAbility` attribute in `app/Features/Shared/Authorization/`. Coverage tests fail on missing abilities and on authenticated non-Filament routes missing from the matrix, with fixture self-tests. Guest gets 401 or login redirect on every protected route. Agent rows planned until Sanctum.
  16. Tooling self-tests in `tests/Feature/Tooling/`: every workflow `uses:` is SHA-pinned, top-level permissions are `contents: read`, every checkout sets `persist-credentials: false`, no `pull_request_target`. Dependabot covers the three ecosystems with cooldown. `composer ci:check` contains every gate in order and `package.json` forwards to it. Hook files exist, are executable in git (`git update-index --chmod=+x` is allowed for them), contain no `--no-verify`, `HUSKY=0`, `git add -A` or `git add .`. Run `.husky/commit-msg` with `sh` through `Illuminate\Support\Facades\Process` against temp message files: valid subjects pass, invalid types, missing ID, lowercase ID, trailing-space action and co-author trailers fail, exempt subjects pass. A repository secret scan test over `app`, `bootstrap`, `config`, `database`, `routes`, `resources`, `tests`, `docs`, `.github`, `.env.example` finds no live key patterns.
  17. No coverage gate and no mutation testing now. Record both as future options in the testing doc.
  18. Docs and tooling: add `docs/decisions/0007-local-hooks-and-single-ci-gate.md` (decision, applicability table of every reference check with applied or dropped and why, consequences, alternatives). Update `docs/development/testing-strategy.md` (layers, suites, gate table, CI jobs, local hooks), `docs/development/local-setup.md` (`npm run prepare`, commit from PowerShell), `docs/development/commit-and-branch-rules.md` (hooks enforce the format, never bypass), `docs/security/owasp-mapping.md` (statuses for A02, A03, A05, A08), `docs/operations/configuration.md` (new keys), `docs/README.md` index, `README.md` CI badge. Update CLAUDE.md and AGENTS.md command table, stack, current state and pending queue. Add project skill `.claude/skills/quality-gates-and-hooks/SKILL.md` (trigger, gate list by link, fix-the-cause steps, never bypass). Record `.ai/rules/quality-gates.md` through the Boost `record-rule` tool covering `tests/**`, `.husky/**`, `.github/**`, `phpstan.neon`, `rector.php`, `pint.json`, `composer.json`.
  19. Do not touch GitHub settings. Document in ADR 0007 and the testing doc the owner steps to protect `main`: require pull requests, require the `ci`, `postgres`, `gitleaks`, `dependency-review` and `zizmor` checks, block force pushes and deletions.
  20. This prompt lives at `docs/prompts/feature-prompt-v1-abp-9-install-security-checks.md`.
- Suggested commit plan (adjust if needed): add strict types to existing files, install and configure Larastan, install and configure Rector and Pint rules, add composer gate scripts, install Husky and add hooks, add GitHub workflows and Dependabot and PR template, add SecurityHeaders middleware, add architecture tests, add access matrix tests, add security and tooling tests, update docs, add skill and rule.
