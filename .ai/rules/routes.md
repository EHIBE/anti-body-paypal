---
paths:
  - 'app/Features/*/Routes/**'
---

# Routes

## Slice routes are auto-loaded; webhooks verify signatures
Slice Routes/web.php loads with the web middleware and Routes/api.php with the api middleware (no api/v1 prefix) via the then: callback in bootstrap/app.php; never add feature routes to routes/web.php. Use named routes and route(). Webhook routes (/webhooks/paypal, /webhooks/telegram) are outside CSRF only because they verify signatures (PayPal verify-webhook-signature, Telegram secret header + chat allowlist + single-use HMAC token).
