---
title: Commit and Branch Rules
purpose: Define the exact commit message format, branch naming, and git operations allowed for humans and AI agents.
audience: [developers, ai-agents]
status: implemented
related:
  - ai-agents-and-skills.md
  - coding-conventions.md
  - local-setup.md
  - testing-strategy.md
  - ../decisions/0007-local-hooks-and-single-ci-gate.md
source_of_truth: .husky/commit-msg (commit pattern, the only copy), .husky/pre-push (branch pattern), .husky/pre-commit, feature prompt section 8 and 9, git history of branch task/ABP-9-install-security-checks
last_verified: 2026-10-30
---

# Commit and Branch Rules

> **TL;DR**
> - Commit format: `type: [ABP-#] Imperative action`, for example `feat: [ABP-1] Add vendor registry slice`.
> - Branch format: `task/ABP-#-kebab-slug`, for example `task/ABP-8-finalize-documents`.
> - Small logical commits, staging only files changed for that commit. The configured git user `EHIBE` is the only author.
> - No `Co-Authored-By`, no AI attribution, no generated-by lines. Agents never push, merge, rebase, stash, reset, checkout, create branches or amend.
> - Enforced since ABP-9 by Husky hooks (section 6). The exact commit pattern lives only in `.husky/commit-msg`. CI is the backstop; never use `--no-verify` or `HUSKY=0`.

## 1. Commit message format

| Part | Rule | Example |
|---|---|---|
| `type` | One of the allowed types (section 2), lowercase | `feat` |
| `: ` | Colon and one space | |
| `[ABP-#]` | Ticket ID from the feature prompt, square brackets, prefix `ABP` | `[ABP-1]` |
| Action | Imperative, specific, sentence case, no trailing period | `Add vendor registry slice` |
| Length | One line | |
| Body | None by default | |
| Trailers | None. Never `Co-Authored-By`, never "Generated with" lines | |

| Good | Bad | Why bad |
|---|---|---|
| `feat: [ABP-3] Add payout executor with idempotent retry` | `feat: payout stuff` | No ID, vague |
| `fix: [ABP-8] Restore UTF-8 characters in renamed PRD` | `fix(ABP-8): restored chars.` | Wrong bracket form, past tense, period |
| `docs: [ABP-8] Add architecture docs` | `docs: [ABP-8] Add architecture docs\n\nCo-Authored-By: ...` | AI attribution trailer |
| `test: [ABP-5] Cover H1 payee mismatch block` | `test: [abp-5] tests` | Lowercase ID, vague |

## 2. Allowed types

| Type | Use for |
|---|---|
| `feat` | New behavior |
| `fix` | Bug fix |
| `refactor` | Code change without behavior change |
| `perf` | Performance improvement |
| `test` | Tests only |
| `docs` | Documentation only |
| `chore` | Tooling, config, dependencies (approved), renames, agents and skills |

## 3. Branches

| Rule | Value |
|---|---|
| Format | `task/ABP-#-kebab-slug` |
| Example | `task/ABP-8-finalize-documents` |
| Base | `main` |
| Who creates branches | The owner only. Agents work on the branch that is already checked out |
| Pull requests | Target `main`, opened by the owner. Title follows the commit format; the template is `.github/pull_request_template.md` |
| Pushes | Only `task/ABP-#-slug` branches. Never `main`, tags or remote branch deletion (enforced by `.husky/pre-push`) |

## 4. Git operations

| Operation | Owner | AI agent |
|---|---|---|
| `git add <own files>` | Yes | Yes (only files changed for that commit) |
| `git commit` | Yes | Yes, when the feature prompt allows auto-commit |
| `git commit --amend` | Yes | No |
| `git push` | Yes | No |
| `git merge`, `git rebase` | Yes | No |
| `git stash`, `git reset`, `git checkout` | Yes | No |
| Create or delete branches | Yes | No |
| Change `git config` or author | No | No |
| `--no-verify`, skip signing | No | No |

## 5. Commit sizing

| Rule |
|---|
| One logical change per commit (one slice, one doc folder, one rename) |
| Tests pass at every commit |
| Pint clean at every commit that touches PHP |
| Fix a mistake with a new `fix:` commit, never by amending |
| Never commit `.env`, secrets, real names, real emails or real invoices |

## 6. Enforcement (implemented, ABP-9)

Husky registers the hooks in `.husky/` through `npm run prepare` (called by `composer run setup`). The hook files are the single source for patterns; this table links behavior to rules above and does not repeat patterns.

| Hook | Enforces | Related rule |
|---|---|---|
| `.husky/commit-msg` | Subject matches the commit format with the seven allowed types and `[ABP-#]`; `Merge `, `Revert "`, `fixup! ` and `squash! ` subjects are exempt from the format only; any `Co-Authored-By:` trailer (case-insensitive) or "Generated with" line is rejected, also on exempt subjects | Sections 1, 2 |
| `.husky/pre-commit` | Blocks files with both staged and unstaged changes; blocks `.env` and `.env.*` except `.env.example`; scans added staged lines for private keys, Anthropic keys, Telegram bot tokens, PayPal access tokens and non-placeholder values of the known secret variables, printing the file and pattern name but never the value; then runs Rector and Pint on the project and re-stages only already staged paths | Section 5 |
| `.husky/pre-push` | Refuses a detached HEAD, a current or pushed branch not matching `task/ABP-#-slug`, any push to `main`, any non-branch ref (tags) and remote branch deletion; then runs `npm run ci:check` | Section 3 |

| Rule | Detail |
|---|---|
| Bypass | `--no-verify` and `HUSKY=0` are forbidden. They cannot be blocked locally, so CI (`tests.yml`, `security.yml`) repeats the checks and branch protection on `main` is an owner setting ([../decisions/0007-local-hooks-and-single-ci-gate.md](../decisions/0007-local-hooks-and-single-ci-gate.md)) |
| Windows | Commit and push from PowerShell. The hooks reach PHP through `npm run`, and `php` and `composer` are not on the Git Bash `PATH` in this setup |
| Speed | Rector and Pint run on the whole project at each commit |
| Git config | The only local change is `core.hooksPath=.husky/_`, set by `npm run prepare` |
| Gate content | `composer ci:check`; the script list lives only in `composer.json` |
