---
paths:
  - 'app/Features/AgentGateway/**'
---

# Agent Gateway

## Agents can only propose
MCP tools and the built-in AP agent may only list, read, submit invoices and propose payment. No tool approves, rejects, overrides, edits vendors, or accepts amount or payee arguments. Every call checks the agent is active (H10) and is rate limited (30/min per token). The optional `note` is stored as audit data, never parsed, never sent to a model. propose_payment is idempotent and returns the existing decision unchanged. Payout IDs are masked in responses.
