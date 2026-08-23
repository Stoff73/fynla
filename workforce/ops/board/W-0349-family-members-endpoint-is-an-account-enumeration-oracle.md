---
id: W-0349
title: The family-members endpoint is an unthrottled account-enumeration oracle that also creates accounts
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: blocked
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: [csj-decision]
gate: csj
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0347, W-0348]
prior_art_outcome: none
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent

From the W-0344 security review. `POST /api/user/family-members` returns four
distinguishable outcomes for any email address submitted:

| Target state | Response |
|---|---|
| Not registered | `201` + `created: true` — **and a real account is created with a temporary password** |
| Registered, unlinked | `201` + `linked: true` + the full `spouse_user` model (W-0348) |
| Registered, linked elsewhere | `422` "This user is already linked to another spouse" |
| Registered, soft-deleted | `422` "That email address is already in use" |

The route group carries `auth:sanctum` and **no named throttle**
(`routes/api.php:311`).

Two harms, not one. The oracle tells an authenticated caller whether any address holds a
Fynla account and what state it is in; and the first row means **an authenticated user
can cause account records to be created for arbitrary email addresses**, at whatever rate
they like.

## Acceptance

1. A named rate limiter on the route, not the shared per-IP bucket — see the
   `reference_inline_throttle_shares_per_ip_bucket` precedent.
2. The four outcomes collapse to one message that does not reveal registration state.
3. Decide deliberately whether creating an account for a third party from this endpoint
   is intended at all; if it is, it belongs behind the consent flow in W-0347.

## Working notes

(append-only)

- 2026-08-23 — **`blocked` on a CSJ decision, deliberately unclaimed.** team-lead verified
  the four load-bearing lines against the code directly and is escalating tonight.
  **Do not attempt the fix.** `linkExistingSpouse` needs an accept/decline flow — invite,
  token, expiry, notification — touching onboarding, Fyn's `capture_spouse_details` tool
  and the email pipeline. That is specified work, not a patch, and **half-fixing it would
  leave a system that looks gated and is not.**
