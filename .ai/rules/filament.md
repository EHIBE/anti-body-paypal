---
paths:
  - 'app/Filament/**'
---

# Filament

## Filament resources stay thin and authorized
Resources live in app/Filament/Resources/<Plural>/ with Pages/, Schemas/, Tables/ and call Model::getForm(), getTableSchema(), getFilters(), getInfolist(). Every action calls ->authorize() (and ->visible() where relevant) and delegates to one slice Action; no business logic in resources, pages or Livewire components. Reusable components go in app/Filament/Components/. Never render invoice or model text as HTML.
