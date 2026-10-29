---
paths:
  - 'app/Features/*/Models/Traits/**'
---

# Traits

## Filament schema lives in model traits
Field, column, filter and infolist definitions live in model traits as static methods: Traits/Forms/<Model>FormTrait::getForm(), Traits/Tables/<Model>TableTrait::getTableSchema() and getFilters(), Traits/Infolists/<Model>InfolistTrait::getInfolist(). Each returns an array of components with a PHPDoc return type. Pass context as typed camelCase booleans (e.g. bool $isApprovalQueue = false). No queries with side effects or business logic in these traits.
