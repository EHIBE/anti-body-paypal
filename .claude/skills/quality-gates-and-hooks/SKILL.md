---
name: quality-gates-and-hooks
description: "Use whenever a commit, push, or CI run fails, or before changing the Anti-Body quality gates: Husky hooks (.husky/commit-msg, pre-commit, pre-push), composer gate scripts (ci:check, check, test, audit:check), phpstan.neon, rector.php, pint.json, phpunit.xml testsuites, tests/Pest.php, .gitleaks.toml, .github/workflows, dependabot, architecture tests and the access matrix. Triggers: commit blocked, push blocked, PHPStan error, Rector dry run fails, Pint fails, gitleaks finding, zizmor or actionlint finding, new slice testsuite, pinned action SHA, ignoreErrors. Fix the cause; never bypass. Do not use for feature logic itself."
---

# Quality gates and hooks

One gate, defined once, run everywhere. Decision and applicability table: [docs/decisions/0007-local-hooks-and-single-ci-gate.md](../../../docs/decisions/0007-local-hooks-and-single-ci-gate.md). Layers and suites: [docs/development/testing-strategy.md](../../../docs/development/testing-strategy.md). Rule: [.ai/rules/quality-gates.md](../../../.ai/rules/quality-gates.md).

## When to use

- A hook refused a commit or push, or a CI job is red.
- Adding a slice (it needs its own phpunit testsuite), a protected route or `#[RequiresAbility]` class (it needs an access matrix row), or a raw SQL call (it needs an allowlist reason and a bound-parameter test).
- Changing any gate file listed in the description.

## Where each list lives (single source)

| What | Only here |
|---|---|
| Gate steps and their order | `composer.json` `scripts` (`ci:check`, `check`, `test`, `audit:check`) |
| Commit subject pattern and trailer ban | `.husky/commit-msg` |
| Staging, env-file and staged secret guards | `.husky/pre-commit` |
| Branch and ref rules for pushes | `.husky/pre-push` |
| Repository secret patterns | `.gitleaks.toml` (the repository scan test reads it) |
| Slice dependency map | `tests/Architecture/SliceMap.php` |
| Protected surfaces and allowed actors | `tests/AccessMatrix/AccessMatrix.php` |
| Header values | `app/Features/Shared/Http/Middleware/SecurityHeaders.php`, `config/antibody.php` |

`package.json` scripts only forward. `pre-push` runs `npm run ci:check`, and the CI `ci` job runs `composer ci:check`, so local and CI cannot drift.

## Steps when a gate fails

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope.

1. Read the hook or tool message. It names the file, rule or pattern.
2. Reproduce the single step from PowerShell (`php` and `composer` are not on the Git Bash PATH): `composer refactor:check`, `composer lint:check`, `composer types:check`, `composer audit:check`, `php artisan test --compact <path>`.
3. Fix the cause in the code:
   - Rector or Pint: run `composer fix`, review every changed line, keep behaviour.
   - PHPStan: fix the type. No baseline, no `@phpstan-ignore`, no inline `@var` override. An `ignoreErrors` entry needs an identifier, a path and a `# Reason:` line, and is removed when the cause goes away.
   - Partly staged file: stage the rest with `git add <file>` or undo the unstaged part.
   - Secret or `.env` refused: remove the value, keep it in `.env`, use a `your-...` placeholder in examples, `git restore --staged <file>`.
   - Commit subject: `type: [ABP-#] Imperative action` with a type from the hook. Remove any `Co-Authored-By` or "Generated with" line.
   - Push refused: work on `task/ABP-#-slug`; changes reach `main` through a pull request.
4. Rerun the narrow step, then `composer ci:check` before pushing.
5. Commit from PowerShell so the hooks reach PHP through `npm run`.

## Checklist for gate changes

- [ ] New check added to `composer.json` scripts only, never to a hook or workflow.
- [ ] Every workflow `uses:` pinned to a 40-character SHA with a `# version` comment, resolved with `git ls-remote`, never invented.
- [ ] Top-level `permissions: contents: read`; every checkout `persist-credentials: false`; no `pull_request_target`; `timeout-minutes` on every job.
- [ ] New slice: `<testsuite name="<Slice>">` with `app/Features/<Slice>/Tests` and a matching `<source>` `<exclude>`.
- [ ] A new scanner rule comes with a fixture self-test in `tests/Fixtures/` proving it reports a planted violation.
- [ ] No comments in YAML or shell files except the version comment after a pinned SHA.

## Common mistakes

- `--no-verify` or `HUSKY=0`. Forbidden; CI repeats every check anyway.
- `git add -A` or `git add .` inside a hook. The hook re-stages only already staged paths.
- Writing a realistic key in a test or doc. Build fake keys at runtime by concatenation.
- Adding a PHPStan baseline to get green.
- Editing an applied migration to satisfy Pint or Rector. The three starter migrations are excluded by exact path.

## Verification

```powershell
composer ci:check
```

Owner-only GitHub settings (branch protection and required checks) are listed in ADR 0007. Agents never change repository settings.
