# Revolut Sandbox Test Cards

Source: https://developer.revolut.com/docs/guides/accept-payments/get-started/test-implementation/test-cards

For all cards: use any 3-digit CVV and any future expiry date (MM/YY).

---

## Successful Payments

| Card PAN | Brand |
|----------|-------|
| `4929 4205 7359 5709` | Visa |
| `5281 4388 0180 4148` | Mastercard |

These can also be used to test charging a saved payment method.

---

## Error Cases

| Card PAN | Brand | Case | Decline Reason | Payment State |
|----------|-------|------|----------------|---------------|
| `4242 4242 4242 4242` | Visa | 3DS verification error | `customer_challenge_failed` | `failed` |
| `4929 5736 3812 5985` | Visa | Insufficient funds | `insufficient_funds` | `declined` |
| `4532 3367 4387 4205` | Visa | Expired card | `expired_card` | `declined` |
| `2720 9988 3777 9594` | Mastercard | Do Not Honour | `do_not_honour` | `declined` |
| `5215 6741 1512 7070` | Mastercard | Issuer needs additional verification | `customer_challenge_failed` | `failed` |
| `2223 0000 1047 9399` | Mastercard | Stuck in processing state | None | `authorisation_started` |

### Notes

- **3DS error (4242...):** Orders under £25.00 GBP (or €30.00 equivalent) are exempt from 3DS, so they will succeed. To trigger the 3DS failure, use amounts of at least 2500 pence (£25) for GBP or 3000 cents (€30) for EUR.
- **Do Not Honour (2720...):** Customer's bank declines due to internal reasons (fraud rules, temporary holds).
- **Stuck processing (2223...):** Useful for testing timeout/long-processing handling.
