---
id: W-0347
title: CRITICAL — spouse linking writes the other person's account, and forges their consent, on one party's say-so
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: main-inference
status: gated
severity: critical
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: []
gate: compliance-lead
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0278, W-0344]
prior_art_outcome: none
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent

Found by a `security-reviewer` pass commissioned for W-0344, which was expected to
confirm a narrow question and instead found the mechanism underneath it.

**Unclaimed and gated to CSJ deliberately.** It is outside every scope issued in cycle 4,
it spans onboarding, the family-members API and Fyn's capture tool, and it should not be
absorbed into a fix batch.

### The defect

`app/Services/Onboarding/SpouseLinkingService.php:226-254` — `linkExistingSpouse()`
writes the **named account's** `users` row, not only the caller's:

- `$lockedSpouse->spouse_id = $currentUser->id` — the target now names the caller as
  their spouse
- `$lockedSpouse->marital_status = $maritalStatus` — overwritten to `married`
- `$lockedSpouse->annual_employment_income = $data['annual_income']` — the caller sets
  the target's income
- five address fields copied in where the target's are blank

`createSpousePermissions()` (`:476-486`) then writes `status => 'accepted'`,
`responded_at => now()` on **both** `spouse_permissions` rows. **No request. No
acceptance. Nothing from the target.**

**The only precondition is that the target's `spouse_id` is NULL** — every unlinked
account.

**Reachable at `POST /api/user/family-members`** (`routes/api.php:311` → `:331-333`,
`auth:sanctum` only). `StoreFamilyMemberRequest::authorize()` returns `true`; the target
`email` is validated as `required_if:relationship,spouse` plus `email` and nothing more.
**No proof of control of that address at any layer.** Also reachable through
`CoordinatingAgent.php:1866` (`capture_spouse_details`) and `OnboardingService.php:346`.

### The same request returns the target's entire user row

`app/Http/Controllers/Api/FamilyMembersController.php:216-222` returns
`'spouse_user' => $spouseUser`, a fully hydrated Eloquent `User`. `$hidden` strips
password, MFA secret and national insurance number. **Everything else ships**: email,
date of birth, address, phone, occupation, employer, every `annual_*_income` column,
monthly and annual expenditure plus all 21 category columns, health status, smoking
status, domicile.

### Why it invalidates every gate in the application

| Gate | Survives a raw one-sided `spouse_id`? | Survives this endpoint? |
|---|---|---|
| raw `spouse_id` / `->spouse` | no | no |
| `liveSpouseId()` / `liveSpouse()` | no | no |
| `hasReciprocalSpouseLink()` | **yes** | **no — the server wrote the target's side** |
| `hasAcceptedSpousePermission()` | **yes** | **no — the permission rows are forged `accepted`** |

**Until this lands, the reciprocity and permission gates are decorative**, including the
one added in W-0344. They remain the right rules; the write path underneath them is what
is broken.

## Acceptance

1. **No account's row is ever written by another account.** Linking becomes a request the
   named party accepts or declines; `spouse_permissions` returns to meaning what its
   status column says.
2. Nothing about the target is disclosed before they accept — see **W-0348**.
3. Existing links established under the old flow are reviewed: they carry `accepted`
   rows nobody accepted.
4. Compliance sign-off — this is consent, not a bug fix.

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
