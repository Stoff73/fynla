---
id: W-0349
title: The family-members endpoint is an unthrottled account-enumeration oracle that also creates accounts
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: main-inference
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: quality-lead
certification: REJECTED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
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


## Resolution — 2026-08-23

**Fixed at CSJ's direction.** The principle held to throughout: *no account's row is
ever written by another account.*

### What made it possible

A complete consent flow already existed and had never been reachable.
`SpousePermissionController` has had `request` / `accept` / `reject` / `revoke` since
before this defect, `spouse_permissions` already modelled `pending|accepted|rejected`
with `requested_at` / `responded_at`, `SpousePermissionRequest` notification was
written, and `SpouseDataSharing.vue` implemented all five UI states **including the
accept/decline screen**.

**It was mounted nowhere.** Nothing imported it, and the notification email linked to
`/settings/spouse-permission`, a route that did not exist. So consent was
*unobtainable*, and the backend forged it to make the product work — the comment on
`User::hasAcceptedSpousePermission()` said so outright: *"This fixes the persistent
issue where spouse data doesn't display... even though accounts are linked."*

Measured before changing anything: **all 10 `spouse_permissions` rows on the dev
database were `accepted` with `requested_at = NULL`** — every one forged, not one ever
requested.

### The change

- `SpouseLinkingService::linkExistingSpouse()` — the existing-account path writes
  **nothing** to the other account and creates **no link in either direction**. It
  records a pending invitation and notifies the invitee. The caller's own
  `marital_status` and their own family-member card (with `linked_user_id` NULL) are
  all that is written.
- `SpouseLinkingService::establishAcceptedLink()` — new, and the **only** place both
  rows are written. Called from `accept()`, ordered lock on the lower id, collision
  checked on both sides. It deliberately does **not** copy income or the address
  block: the old flow pushed the caller's figures into the other person's account.
- `createSpousePermissionsForCreatedAccount()` — renamed from
  `createSpousePermissions()` so the surviving auto-accept cannot be reused by
  accident. It applies only to an account this call just created, which holds nothing
  the caller has not already seen and whose owner proves control by logging in with
  the emailed credentials.
- `accept()` / `reject()` / `revoke()` no longer require `$user->spouse_id` — under an
  invitation the invitee has no link, and requiring one is why the flow was
  unusable.
- `revoke()` marks the row `rejected` instead of deleting it. Deleting left no record
  of the decision, and an absent row reads as a pre-consent-flow link and is honoured
  — so **revoke silently switched sharing back on**.
- `status()` reports incoming and outgoing pending invitations.
- `SpouseDataSharing.vue` mounted on `/settings/family`; `/settings/spouse-permission`
  now resolves; `/m` gains `SpouseSharing.vue` at `/m/app/spouse-sharing` with the
  same redirect, because phones follow that email into `/m` (Rule 19).
- Fyn's `capture_spouse_details` and `OnboardingService` route through the same
  service and inherit all of it; `CoordinatingAgent` no longer narrates
  "Spouse account linked" over a pending invitation.

### Browser-verified end to end (local, both accounts)

1. Sarah invites `chris@fynla.org` → *"Invitation sent…"*; DB: both `spouse_id` NULL,
   invitee's `marital_status` and income untouched, one `pending` row with
   `requested_at` set, `hasAcceptedSpousePermission()` false.
2. Chris opens the email's `/settings/spouse-permission` → redirects and shows
   *"Permission Request Received"* naming Sarah → **Accept** → both `spouse_id` set,
   `hasReciprocalSpouseLink()` true, both can view.
3. Chris **Revoke** → both lose visibility, row `rejected`, **and both `spouse_id`
   survive** — revoking withdraws visibility, not the marriage, so nil-rate-band
   transfer and household net worth are not silently rewritten.
4. Requester **Cancel Request** withdraws an unanswered invitation.
5. `/m` verified live: the email URL resolves to `/m/app/spouse-sharing` and renders
   the true state.

### Migration

`2026_08_23_120000_backfill_spouse_permissions_for_existing_links` grandfathers
**reciprocal** links only — a one-sided link is exactly what the old flow could forge,
and granting it an accepted row would launder the defect. `requested_at`/`responded_at`
left NULL to mark these as inherited, which is the marker for the consent audit
(acceptance 3). Verified: 12 linked users, 0 lost access.

### Tests

`tests/Feature/Api/SpouseLinkConsentTest.php` — 18 tests written from the attacker's
side, several asserting on **absence** so the next column added to `users` cannot ship
silently. Mutation-checked: re-introducing the illegal write and the raw-model response
turns **9** of them red. Plus `resources/mobile/views/__tests__/SpouseSharing.spec.js`
(6) and `tests/frontend/components/Settings/FamilySettings.spec.js` (2).

### NOT done — still open

- **Acceptance 4, compliance sign-off, has not happened.** This is consent, and that
  gate is CSJ's to raise.
- The `/m` accept/decline branch was **not rendered against a live pending invitation
  in a browser** — neither side exposes an unlink, so a fresh invitation could not be
  staged through the UI without DB surgery. Covered by component tests and by the
  live API test; the other `/m` branches were rendered live. **I COULD NOT BROWSER-TEST
  THIS ONE BRANCH.**
- **W-0350 is untouched** — 53 `spouse_id` consumers, five idioms. It was
  `blocked_by: W-0347`; that block is now lifted and the work is real. The forged
  reciprocity it depended on is gone, so those gates are no longer decorative, but
  they are still inconsistent.
- Creating an account for an unregistered address is **unchanged** — W-0349
  acceptance 3 asks for that to be decided deliberately, and it is a product call.
- Not deployed anywhere.
