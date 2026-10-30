---
name: audit-hash-chain
description: "Use whenever writing or reviewing code that records or verifies Anti-Body audit entries: decisions, approvals, overrides, vendor and payee changes, policy changes, payout calls, webhooks, agent calls, rate-limit hits, or the `antibody:audit-verify` command and the Filament audit log page. Triggers: audit_logs, prev_hash, hash, RecordAuditEntryAction, canonical JSON, tamper-evident, append-only, verify chain. Enforces a single write path and a verifiable SHA-256 chain. Do not use for application logs (Log::) only."
---

# Audit hash chain

Every decision is logged and tamper-evident (FR-35, FR-36). Spec: [docs/security/audit-hash-chain.md](../../../docs/security/audit-hash-chain.md), [docs/architecture/data-model.md](../../../docs/architecture/data-model.md).

## When to use

- Any Action that makes a decision, approval, override, vendor, payee or policy change, payout call, or webhook processing step.
- Any change in `app/Features/Audit/**` (planned).

## Project paths (planned)

| Unit | Path |
|---|---|
| Model | `app/Features/Audit/Models/AuditLog.php` (bigint id, no update or delete) |
| Writer | `app/Features/Audit/Actions/RecordAuditEntryAction.php` (only write path) |
| Verifier | `app/Features/Audit/Actions/VerifyAuditChainAction.php` |
| Command | `app/Features/Audit/Commands/VerifyAuditChainCommand.php` -> `antibody:audit-verify` |
| Actor enum | `app/Features/Audit/Enums/AuditActorType.php`: `Owner`, `Agent`, `System`, `PayPal`, `Telegram` |

## Hash definition (decision D8, OQ-17)

`hash = sha256((prev_hash ?? '') . canonicalJson(row))`

- Fields: `actor_type`, `actor_id`, `action`, `subject_type`, `subject_id`, `data`, `prev_hash`, `created_at` (UTC, ISO 8601). Exclude `id` and `hash`.
- Canonical JSON: keys sorted recursively, `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`.
- First row: `prev_hash = null`.

## Steps

Before step 1: read `.ai/rules/index.md` and every rule file whose globs match the paths in scope. Those rules are binding.

1. Call `RecordAuditEntryAction::handle()` from the Action that performs the change, inside the same transaction when possible.
2. Serialize writers: acquire `Cache::lock('audit-chain')`, read the last row inside the transaction, compute the hash, insert.
3. Use action names like `decision.made`, `payout.submitted`, `vendor.payee_changed`, `webhook.unverified`.
4. Never update or delete audit rows through the app. Postgres trigger rejecting `UPDATE` and `DELETE` is a Should (PRD 18).
5. Verify by recomputing every hash in id order; report the first broken id.

## Illustrative snippet

```php
<?php

declare(strict_types=1);

namespace App\Features\Audit\Actions;

final class CanonicalizeAuditPayloadAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): string
    {
        return json_encode($this->sortKeys($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortKeys(array $value): array
    {
        ksort($value);

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->sortKeys($item) : $item, $value);
    }
}
```

## Checklist

- [ ] One write path. No `AuditLog::create()` outside the Audit slice.
- [ ] No secrets or plaintext tokens in `data`. Agent `note` stored as data.
- [ ] `created_at` set explicitly in UTC before hashing.
- [ ] Concurrency test: two writers produce a valid chain.
- [ ] Tamper test: editing one row makes verification fail at that id.

## Common mistakes

- Hashing with unsorted keys, so verification fails after a JSON column round-trip.
- Letting the DB default `created_at` after the hash was computed.
- Writing audit entries outside the transaction, leaving entries for rolled-back changes.

## Verification

```bash
php artisan test --compact --filter=Audit
php artisan antibody:audit-verify
```
`antibody:audit-verify` is planned.
