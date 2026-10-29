---
paths:
  - 'app/Features/*/Actions/**'
---

# Actions

## Business logic only in VerbNounAction::handle()
Business logic lives only in Actions named VerbNounAction with one public handle() method, typed params and return. Inject collaborators via constructor property promotion (no app() lookups or facades when a dependency fits). Wrap multi-step writes in DB::transaction() and use lockForUpdate() on state transitions. Throw typed exceptions from the slice Exceptions/ folder. Register Actions in the slice service provider.
