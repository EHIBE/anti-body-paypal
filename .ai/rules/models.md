---
paths:
  - 'app/Features/*/Models/**'
---

# Models

## Model attributes, IDs, money and time
Models use #[Fillable] and #[Hidden] attributes; never Model::unguard() or $request->all() into create/update. Domain IDs are UUIDv7 via HasUuids (agents and audit_logs keep bigint per PRD). Money is integer minor units in *_minor columns, never floats. Times are UTC. Models hold no business rules; model traits hold only Filament schema definitions.
