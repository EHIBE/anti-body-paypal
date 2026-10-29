---
title: Commit and Branch Rules
purpose: Define the exact commit message format, branch naming, and git operations allowed for humans and AI agents.
audience: [developers, ai-agents]
status: implemented
related:
  - ai-agents-and-skills.md
  - coding-conventions.md
source_of_truth: feature prompt section 8 and 9, git history of branch task/ABP-8-finalize-documents
last_verified: 2026-10-30
---

# Commit and Branch Rules

> **TL;DR**
> - Commit format: `type: [ABP-#] Imperative action`, for example `feat: [ABP-1] Add vendor registry slice`.
> - Branch format: `task/ABP-#-kebab-slug`, for example `task/ABP-8-finalize-documents`.
> - Small logical commits, staging only files changed for that commit. The configured git user `EHIBE` is the only author.
> - No `Co-Authored-By`, no AI attribution, no generated-by lines. Agents never push, merge, rebase, stash, reset, checkout, create branches or amend.

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
| Pull requests | Target `main`, opened by the owner |

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
