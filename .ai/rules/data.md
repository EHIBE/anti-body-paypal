---
paths:
  - 'app/Features/*/Data/**'
---

# Data

## DTOs are readonly classes, not spatie/laravel-data
Data objects in Data/ are `final readonly class` with promoted constructor properties, built from validated input or decoded JSON arrays. Never deserialize untrusted data into objects. Do not add spatie/laravel-data. Use PHPDoc array shapes for any array property.
