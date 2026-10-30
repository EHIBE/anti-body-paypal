---
paths:
  - 'app/Features/*/Tests/**'
---

# Tests

## Pest tests live inside the slice
Each slice keeps its unit and feature tests in app/Features/<Slice>/Tests/Unit and Tests/Feature, registered as its own `<testsuite name="<Slice>">` and excluded from `<source>` in phpunit.xml; tests/Pest.php binds Tests\TestCase with RefreshDatabase to ../app/Features/*/Tests/Feature. Only cross-cutting suites (Architecture, AccessMatrix) and Fixtures and Support live in root tests/. Use typed Pest\Laravel functions, never $this->. Cover happy path, edge cases, invalid input, unauthorized access, abuse cases and failure paths. Fake HTTP with Http::fake(), AI with laravel/ai fakes, queues and notifications with framework fakes. Deterministic only; never weaken tests to pass.
