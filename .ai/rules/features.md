---
paths:
  - 'app/Features/**'
---

# Features

## Vertical slices live in app/Features/<Slice>
Every domain lives in app/Features/<Slice>/ using only the sub-folders it needs: Actions, Data, Enums, Events, Exceptions, Http/Controllers, Http/Requests, Http/Resources, Jobs, Listeners, Mcp, Ai, Models (+ Interfaces, Traits/Forms, Traits/Tables, Traits/Infolists), Notifications, Policies, Providers, Routes, Commands, Tests. Planned slices only: Shared, Vendor, Invoice, Extraction, Guard, Approval, Payment, Webhook, Telegram, AgentGateway, Audit, Policy, Baseline. Never create a new slice without owner approval. Shared holds cross-cutting code only. See docs/architecture/vertical-slice-architecture.md.
