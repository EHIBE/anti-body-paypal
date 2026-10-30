---
paths:
  - 'app/Features/Audit/**'
---

# Audit

## Audit log is append-only with a hash chain
Write audit entries only through RecordAuditEntryAction. No updates or deletes of audit_logs from the app. hash = sha256(prev_hash . canonical JSON of the row), canonical JSON = recursively key-sorted with unescaped slashes and unicode, excluding id and hash; chain writes are serialized with a lock. Never put secrets or tokens in audit data. Spec: docs/security/audit-hash-chain.md.
