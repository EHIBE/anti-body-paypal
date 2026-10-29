---
title: "ADR 0005: Pest over PHPUnit classes"
purpose: Record the decision to write all tests in Pest 5 instead of PHPUnit test classes.
audience: [developers, ai-agents]
status: implemented
related:
  - ../development/testing-strategy.md
  - 0001-vertical-slice-architecture.md
source_of_truth: composer.json (pestphp/pest ^5.3, pestphp/pest-plugin-laravel), tests/Pest.php, phpunit.xml.
last_verified: 2026-10-30
---

# ADR 0005: Pest over PHPUnit classes

> **TL;DR**
> - Decision: every test is a Pest 5 test (`it()` / `test()` closures). Installed: pestphp/pest 5.3.4, pestphp/pest-plugin-laravel 5.0.1.
> - Create tests with `php artisan make:test --pest <Name>`; run with `php artisan test --compact` or `vendor/bin/pest`.
> - Slice tests live in `app/Features/<Slice>/Tests/`, registered as testsuites in `phpunit.xml` and bound to `Tests\TestCase` in `tests/Pest.php` (planned wiring).
> - The prior private project used PHPUnit classes with `#[Test]`; Anti-Body intentionally differs.

## Status

Accepted, 2026-10-30. Implementation status: implemented (Pest installed, `tests/Pest.php` binds `tests/Feature` to `Tests\TestCase`, example tests in `tests/Feature` and `tests/Unit`). Slice testsuites planned.

## Context

| Factor | Detail |
|---|---|
| PRD | Lists Pest as the owner's stack (PRD 13, 22). PRD expected Pest v4; Pest 5.3.4 is installed (PRD drift) |
| Readability | Rule tables (H1 to H10, S1 to S7, decision thresholds) map naturally to Pest datasets |
| Boost | Laravel Boost guidelines in CLAUDE.md say "This project uses Pest" |
| Reference | The prior private project used PHPUnit classes and Mockery with `@phpstan-ignore` comments; the zero-comment rule here favors framework fakes over Mockery |

## Decision

| Rule | Detail |
|---|---|
| Framework | Pest 5 with pest-plugin-laravel |
| Location | `app/Features/<Slice>/Tests/` for slice tests; `tests/Feature`, `tests/Unit` for cross-cutting tests |
| Binding | `pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in(...)` per slice test folder (planned) |
| Fakes | `Http::fake()` for PayPal, Anthropic and Telegram; laravel/ai fakes for models (once installed); `Queue::fake()`, `Notification::fake()`; deterministic only |
| Datasets | Use Pest datasets for rule and threshold tables |
| Database | SQLite `:memory:` (phpunit.xml) |

## Consequences

| Type | Consequence |
|---|---|
| Positive | Short, readable tests; datasets express the PRD tables directly |
| Negative | Slice test folders must be added to both `phpunit.xml` and `tests/Pest.php` for each slice |
| Negative | Some package docs show PHPUnit examples; translate to Pest |

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| PHPUnit classes only | More boilerplate; diverges from Boost project guidance and owner preference |
| Mixed Pest and PHPUnit | Two styles for agents to follow; no benefit |
