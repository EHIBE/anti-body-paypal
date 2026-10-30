---
paths:
  - 'tests/**'
  - '.husky/**'
  - '.github/**'
  - 'phpstan.neon'
  - 'rector.php'
  - 'pint.json'
  - 'composer.json'
---

# Quality Gates

## One gate, never bypassed
composer.json scripts are the only list of checks: `composer ci:check` = check (validate --strict, Rector dry run, Pint test, PHPStan max, composer + npm audit), npm run build, tests in parallel. pre-push and CI both call it; hooks hold only the commit pattern (.husky/commit-msg), staging, env-file and secret guards. Never use --no-verify or HUSKY=0; fix the cause. No PHPStan baseline or inline ignores; an ignoreErrors entry needs an identifier, a path and a written reason. Every workflow `uses:` is pinned to a full SHA with a version comment, permissions contents: read, persist-credentials: false, no pull_request_target. New slices register their own phpunit testsuite. Skill: .claude/skills/quality-gates-and-hooks.
