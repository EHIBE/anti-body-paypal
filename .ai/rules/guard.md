---
paths:
  - 'app/Features/Guard/**'
---

# Guard

## Guard owns hard rules, signals and thresholds
Hard rules H1-H10, signals S1-S7 and decision thresholds are defined only in the Guard slice (single source of truth). Hard rules are pure PHP, no AI. Score is monotonic: signals only add points, floor 0, cap 100, summed in PHP. AI never outputs the score or the decision; the analyst only picks the S3 band. Hard-rule BLOCK is never overridable; H9 kill switch forces HOLD; auto ceiling is $500 absolute. Spec: docs/architecture/guard-spec.md.
