---
paths:
  - 'database/migrations/**'
---

# Migrations

## Portable, reversible, constrained migrations
Schema changes are new reversible migrations only; never edit an applied migration. Migrations must run on SQLite (local, tests) and Postgres (Render). Back every validation rule with a storage constraint (unique, foreign key, not null, check) and keep PRD 14.2 constraints, including the H6 partial unique index on invoices (vendor_id, invoice_number) excluding rejected and blocked. Index lookup, join and sort columns.
