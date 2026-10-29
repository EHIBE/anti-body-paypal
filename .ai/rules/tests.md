---
paths:
  - 'app/Features/*/Tests/**'
---

# Tests

## Pest tests live inside the slice
Each slice keeps Pest tests in app/Features/<Slice>/Tests/, registered as a testsuite in phpunit.xml and bound to Tests\TestCase in tests/Pest.php. Cover happy path, edge cases, invalid input, unauthorized access, abuse cases and failure paths. Fake HTTP with Http::fake(), AI with laravel/ai fakes, queues and notifications with framework fakes. Deterministic only; never weaken tests to pass.
