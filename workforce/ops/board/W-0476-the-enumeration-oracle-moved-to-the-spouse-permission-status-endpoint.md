---
id: W-0476
title: The account-enumeration oracle moved one endpoint over — two requests still distinguish a registered address from an unregistered one
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [security-reviewer, quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-24T09:00:00Z
claimed: null
blocked_by: []
gate: security-reviewer
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0349, W-0472]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: quality-lead re-certification, 2026-08-24, finding 1 — raised in answer to the coordinator's own question, "check I did not simply move the oracle"
---

## Intent

W-0349 closed `POST /api/user/family-members`: both branches now return an
identical response, and the Fyn surface withholds `spouse_user_id` for any pending
invitation. **The door that is still open is `GET /api/spouse-permission/status`.**

**The mechanism is structural, not cosmetic.** Only one of the two invitation
branches can create a `SpousePermission` row, because an unregistered address has
no user id to key one on:

| | |
|---|---|
| `SpouseLinkingService:267` — registered | `createPendingSpouseInvitation(...)` |
| `SpouseLinkingService:415-432` — unregistered | **no permission row, and cannot have one** |

`SpousePermissionController::status()` branches on exactly that row's existence:

| Caller invited… | `status()` returns |
|---|---|
| a **registered** address | `awaiting_their_response: true`, `permission: {row}` (`:88-117`) |
| an **unregistered** address | `requires_account_link: true`, `permission: null`, different `message` (`:180-195`) |

**Two requests distinguish any address**: POST it to `/api/user/family-members`,
then GET `/api/spouse-permission/status`.

## The irony, recorded because it shows how the fix passed its own review

The comment at `SpousePermissionController:170-179` reasons carefully that *"this
endpoint cannot tell them apart"* — and **it is right about the branch it is
attached to.** The branch two above it tells them apart perfectly. The care went
into not disclosing the account holder's *name* in the outgoing branch; **the tell
is the existence of the branch, not its contents.**

## How much weaker than the original, stated fairly

- The original was **one request, unthrottled**.
- This needs **two requests**, and is gated by the 5/hour per-user invite throttle
  W-0349 kept (`FamilyMembersController:143-152`).
- **The real mitigation: the probe is not silent.** The address being probed
  receives an email. 120 probes a day, each one announcing itself to the person
  being probed.

## Acceptance

1. `status()` returns the same shape for a pending invitation whether or not the
   invitee holds an account — or the distinction is shown to be unobservable.
2. **Same root cause as W-0472 and they close together.** `family_members` has no
   email column, so there is nowhere to record "invited, waiting" for an address
   with no account — which is why the two branches differ at all. Retention is what
   makes one shape possible.
3. A test that posts an unregistered and a registered address and asserts the two
   `status()` payloads are indistinguishable — key sets compared, not listed.
4. `security-reviewer` on the change.

## Working notes

- 2026-08-24 — Found by `quality-lead` answering the coordinator's explicit question
  *"check I did not simply move the oracle"*. The answer: **not where I looked, but
  yes.** Worth keeping as the reason to ask that question of every closure — the
  fix was correct at the endpoint it named and the disclosure re-formed one call away.
- 2026-08-24 — **Not a reason to reject W-0349.** Its acceptance is about the
  family-members endpoint and that endpoint is genuinely fixed.
