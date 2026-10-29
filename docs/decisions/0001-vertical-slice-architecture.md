---
title: "ADR 0001: Vertical slice architecture"
purpose: Record the decision to organize Anti-Body code as vertical slices under app/Features/<Slice>/.
audience: [developers, ai-agents, judges]
status: planned
related:
  - ../architecture/vertical-slice-architecture.md
  - ../architecture/slice-catalog.md
  - ../development/adding-a-slice.md
  - 0002-filament-separation-with-model-traits.md
source_of_truth: This ADR for the decision; docs/architecture/vertical-slice-architecture.md for the layout rules.
last_verified: 2026-10-30
---

# ADR 0001: Vertical slice architecture

> **TL;DR**
> - Decision: every domain lives in `app/Features/<Slice>/` with only the sub-folders the slice needs (Actions, Data, Enums, Models, Policies, Routes, Tests, and so on).
> - 13 planned slices: Shared, Vendor, Invoice, Extraction, Guard, Approval, Payment, Webhook, Telegram, AgentGateway, Audit, Policy, Baseline. No new slice without owner approval.
> - PRD paths `app/Domain/*`, `app/Ai/*`, `app/Mcp/*` and the PRD `GateService` and `AuditLogger` map into slices and Actions.
> - Status: accepted 2026-10-30. Not yet implemented (no `app/Features` folder exists).

## Status

Accepted, 2026-10-30. Implementation status: planned. First slice to land wires provider discovery, route loading and slice testsuites.

## Context

| Factor | Detail |
|---|---|
| Domain shape | Anti-Body has distinct domains with clear boundaries: vendor registry, invoice intake, AI extraction, guard (rules, signals, decision), approvals, payments, webhooks, Telegram, agent gateway (MCP + AP agent), audit, policy, baseline |
| Security | Guard rules H1 to H10 and signals S1 to S7 must have one authoritative home (DRY). Agents must reach the gate only through a narrow surface |
| Team | Solo developer plus AI coding agents. Agents work best when all code for a task sits in one folder with a predictable layout |
| Prior experience | The pattern was used successfully in a prior private project (Laravel 12, Filament 4) and is adapted here |
| PRD | The PRD (section 12, 13) names `app/Domain/*`, `app/Ai/*`, `app/Mcp/*` and a `GateService`, written before the architecture was fixed |

## Decision

| Rule | Detail |
|---|---|
| Location | `app/Features/<Slice>/`, namespace mirrors path (`App\Features\<Slice>\...`) |
| Allowed sub-folders | `Actions/`, `Data/`, `Enums/`, `Events/`, `Exceptions/`, `Http/Controllers/`, `Http/Requests/`, `Http/Resources/`, `Jobs/`, `Listeners/`, `Mcp/`, `Ai/`, `Models/`, `Models/Interfaces/`, `Models/Traits/Forms/`, `Models/Traits/Tables/`, `Models/Traits/Infolists/`, `Notifications/`, `Policies/`, `Providers/`, `Routes/`, `Commands/`, `Tests/` |
| Business logic | Only in `Actions/`, one public `handle()` per `VerbNounAction` |
| Shared code | `app/Features/Shared/` for cross-cutting code only |
| Outside slices | Filament resources in `app/Filament/` ([ADR 0002](0002-filament-separation-with-model-traits.md)), migrations, factories and seeders in `database/` |
| Wiring | Slice service providers auto-registered by `AppServiceProvider` scanning `app/Features`. Slice `Routes/web.php` and `Routes/api.php` loaded by the `then:` callback in `bootstrap/app.php`. Slice `Tests/` registered as testsuites in `phpunit.xml` and bound in `tests/Pest.php` |
| PRD mapping | `app/Domain/PayPal/*` to Payment and Webhook slices; `app/Ai/*` to `Ai/` folders in Extraction, Guard and AgentGateway; `app/Mcp/*` to `AgentGateway/Mcp/`; `GateService` to Actions (`ReceiveInvoiceAction`, `ExtractInvoiceAction`, `EvaluateInvoiceAction`, `ProposePaymentAction`); `AuditLogger` to `RecordAuditEntryAction` |

## Consequences

| Type | Consequence |
|---|---|
| Positive | One folder per domain. Each Guard rule has one home. Agents and reviewers find code by slice name |
| Positive | Slice tests sit next to the code they test |
| Positive | Provider and route auto-discovery means adding a slice needs no edits to `bootstrap/providers.php` or `routes/` |
| Negative | Laravel generators (`make:model`, `make:test`) target default folders; files must be moved or generated with explicit namespaces |
| Negative | Factory name guessing must be configured to map `App\Features\X\Models\Foo` to `Database\Factories\FooFactory` |
| Negative | Cross-slice calls are allowed through Actions, so dependency direction must be watched ([../architecture/slice-catalog.md](../architecture/slice-catalog.md)) |
| Naming collision | The Policy slice (guard settings) shares a word with Laravel authorization `Policies/`. Docs always say "Policy slice" vs "authorization policy" |

## Alternatives considered

| Alternative | Why rejected |
|---|---|
| Laravel default layered layout (`app/Models`, `app/Http`, `app/Services`) | Spreads one domain across many folders; Guard logic would drift into controllers and services |
| PRD layout `app/Domain/*`, `app/Ai/*`, `app/Mcp/*` | Splits by technology (AI, MCP) instead of by domain; extractor and analyst would live far from the rules they serve |
| Modules package (for example nwidart/laravel-modules) | New dependency, heavier than needed for a 14-day solo build |
