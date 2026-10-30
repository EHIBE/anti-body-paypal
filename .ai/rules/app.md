---
paths:
  - 'app/**/*.php'
---

# App

## PHP file conventions
Every PHP file starts with declare(strict_types=1); namespace mirrors the path. Zero code comments except PHPDoc type annotations and array shapes. Use #[\Override] on overrides, camelCase variables, explicit parameter and return types, guard clauses with max two nesting levels, curly braces on every control structure. Read config via config(), never env() outside config/.
