---
paths:
  - 'app/Features/*/Enums/**'
---

# Enums

## String-backed TitleCase enums with Filament contracts
Use enums for every fixed set (statuses, decisions, channels, actor types). Enums are string-backed with TitleCase cases (e.g. InvoiceStatus::AutoApproved = 'auto_approved') and implement Filament HasLabel and HasColor when shown in UI. No magic strings for these values anywhere else.
