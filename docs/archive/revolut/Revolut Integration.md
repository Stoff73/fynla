# Revolut Integration

[[Home]] > Revolut Integration

Payment processing via Revolut Checkout SDK.

---

## Key Documents

| Doc | Purpose |
|-----|---------|
| [[revolut/implementation-plan]] | Full implementation plan |
| [[revolut/bugFix]] | SDK mismatch, CSP, localhost redirect, processing state fixes |
| [[revolut/revolutLinks]] | Revolut documentation links |
| [[revolut/revolutTestCards]] | Test card numbers for sandbox |

## SDK Reference

| Doc | Topic |
|-----|-------|
| [[revolut/revolut_dev_docs_offline_pack/revolut-checkout-web-guide]] | Web integration guide |
| [[revolut/revolut_dev_docs_offline_pack/embedded-checkout]] | Embedded checkout |
| [[revolut/revolut_dev_docs_offline_pack/direct-initialisation]] | Direct initialisation |

## Key Files

- `resources/js/views/CheckoutPage.vue` — Checkout UI
- `app/Http/Controllers/Api/PaymentController.php` — Payment API
- `app/Services/Payment/RevolutService.php` — Revolut SDK wrapper
- `app/Http/Controllers/Api/WebhookController.php` — Webhook handler
- `resources/js/components/Subscription/PlanSelectionModal.vue` — Plan selection
- `resources/js/components/Subscription/SubscriptionManagement.vue` — Subscription management

## Configuration

| Key | Purpose |
|-----|---------|
| `REVOLUT_API_KEY` | Server-side API key (sk_...) |
| `REVOLUT_PUBLIC_KEY` | Client-side public key (pk_...) |
| `REVOLUT_WEBHOOK_SECRET` | Webhook verification |
| `REVOLUT_SANDBOX` | true/false for sandbox mode |
| `PAYMENT_ENABLED` | Feature flag |
| `VITE_REVOLUT_PUBLIC_KEY` | Frontend public key |
| `VITE_REVOLUT_SANDBOX` | Frontend sandbox flag |

## Pricing

| Plan | Monthly | Annual |
|------|---------|--------|
| Student | £3.99 | £30 |
| Standard | £10.99 | £100 |
| Pro | £19.99 | £200 |
