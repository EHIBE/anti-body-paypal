<!-- Title: type: [ABP-#] Imperative action, for example feat: [ABP-3] Add payout executor -->

## What and why

<!-- What changed, and why. A few sentences. -->

## How I tested it

<!-- Steps a reviewer can repeat. composer ci:check must be green. -->

## Security and money checklist

- [ ] No secrets, real names, real emails or real invoices in code, docs, fixtures or screenshots
- [ ] Every new protected route or `#[RequiresAbility]` class is in the access matrix
- [ ] Payout amount and payee come only from the database
- [ ] Docs updated, and nothing unbuilt is marked implemented

## Screenshots

<!-- Only if something visible changed. Otherwise delete this section. -->

## Notes for the reviewer

<!-- Anything risky or unfinished. Otherwise delete this section. -->
