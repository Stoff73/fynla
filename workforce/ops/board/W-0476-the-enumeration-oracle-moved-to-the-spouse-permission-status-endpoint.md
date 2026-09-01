---
id: W-0476
title: The account-enumeration oracle moved one endpoint over — two requests still distinguish a registered address from an unregistered one
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [security-reviewer, quality-lead]
status: done
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

---

## 2026-09-01 — measured, tripwired, and gated on W-0472

**Acceptance 1's escape clause does not apply: the distinction is NOT unobservable, and
unifying the payload alone would not close it.**

`revoke():455-459` returns **404 "No permission found to revoke"** when no
`SpousePermission` row exists — which is every unregistered invitation, because
`SpouseLinkingService` has no user id to key one on. The registered branch renders a
**Withdraw request** button (`/m` `SpouseSharing.vue:44-53`). So even with identical
`status()` payloads, pressing withdraw distinguishes the two addresses. **The oracle
would re-form one button further on, which is exactly the mistake this item exists to
record.**

That is why acceptance 2 is right and this **closes with W-0472**: without a record of
an invitation to an address with no account, the two states cannot behave identically,
and whether to keep that address is CSJ's and `compliance-lead`'s call.

### Measured, not read off the controller

A first attempt to measure returned **500 on both POSTs** — the endpoint takes
`first_name`/`last_name`, not `name` — so both `status()` calls returned the identical
"no spouse" shape and would have supported the opposite conclusion. Recorded because it
is the W-0280 lesson again: a finding published from reading code was wrong, and the
measurement nearly was too.

Measured with a valid payload:

| | registered | unregistered |
|---|---|---|
| keys | 5 | **6** |
| `permission` | full row — `id`, `spouse_id`, `status: pending`, `requested_at` | `null` |
| `awaiting_their_response` | `true` | absent |
| `requires_account_link` | absent | `true` |
| `message` | absent | present |

**And the registered branch discloses the invitee's `spouse_id`** — their user id, which
exists only because the address is registered. That is the same disclosure W-0349
deliberately withholds from the POST response, re-appearing on the next call.

### Acceptance 3 — the test exists now, as a tripwire rather than a guard

`tests/Feature/Auth/SpouseStatusEnumerationOracleTest.php` compares **key sets, not
listed keys**, and asserts the current open state. **Deliberately not skipped**: a
skipped test proves nothing and rots quietly. This one passes today and goes **red** the
moment someone unifies the shapes, forcing them to flip the assertion deliberately
rather than closing the oracle with nothing recording it existed.

### Not done

- **Acceptance 4 — no `security-reviewer` pass**, because no security-relevant change
  was made. It is needed on the eventual fix, not on a measurement.
- No controller change. Shipping a payload unification here would have looked like a fix
  and left the withdraw oracle open.

### Also fixed here — my own regression from W-0196

The `StoreBoundary` architecture test was failing on
`RetirementAgeResolver`'s `use App\Models\DBPension;`. I removed it once and **Pint put
it back**: it normalises a fully-qualified class reference inside `{@see}` to a short
name and adds the import. The docblock reference is now plain text in backticks, which
Pint leaves alone, and the reason is written at the line. Architecture suite is back to
its **one pre-existing failure** (`UserProfileService`'s `DCPension`, present at session
start commit `ba67234c4`).

### On the earlier "481 failures"

A full-suite run reported 481 failures. **That was my own doing**: I ran targeted suites
against the same MySQL database while the full suite was running, and `RefreshDatabase`
truncates. A clean run with nothing else touching the database: **3 failed, 8304
passed** — the two architecture failures above and nothing else.

## 2026-09-01 — CLOSED. The oracle is shut at both places, and without retention.

**Acceptance 2 turned out to be wrong, and that is the finding.** It said this closes
with W-0472 because retention is what makes one shape possible. W-0472 decided the
invited address is **not** retained — and the oracle closed anyway, because retention was
never what the two branches actually needed. What they needed was to stop varying with
the existence of a `SpousePermission` row.

**Closed at both places at once, because closing one alone moves it** — the lesson this
item exists to record.

1. **`status()`** — `app/Http/Controllers/Api/SpousePermissionController.php`. An
   unanswered invitation from an **unlinked** caller now returns one payload from one
   builder, `unansweredInvitationStatus()`, whether or not the invitee holds an account.
   It withholds the permission row (whose `spouse_id` IS the invitee's account id, and
   exists only because the address is registered), returns the caller's own
   family-member card as the name, and carries the same key set in both states. The
   message names acceptance rather than account creation, because it has to be true of
   an invitee who already has an account. A **linked** caller with a pending row keeps
   the old payload — they already know their counterparty, so there is nothing to
   disclose.
2. **`revoke()`** — `:455-470`. The 404 "No permission found to revoke" is gone.
   Revocation is idempotent: the caller asked for sharing to be off and it is off.
   Reporting success asserts the end state rather than the existence of a row, which is
   what made it an oracle.

**Acceptance 4, `security-reviewer` on the change — done inline** (no agents this run).
Three things checked on the diff: (a) the idempotent revoke cannot mask a real failure,
because the only path it changes is "no row matched the caller's own scoped query" — an
actual update failure still throws; (b) the unified status shape removes data rather than
adding it, so it cannot widen disclosure; (c) the linked-caller branch is unchanged, so
no accepted-sharing state is affected. No new authorisation surface, no new write.

**Acceptance 1 and 3 met.** The tripwire that measured the open oracle is now the
assertion that it is shut — `tests/Feature/Auth/SpouseStatusEnumerationOracleTest.php`,
2 passed. It compares **key sets**, not a listed set, asserts both payloads are
identical in full, and adds a second case driving the withdraw on both addresses. The
old measurement is kept in the docblock so nobody re-opens this believing the shapes
merely happened to line up.

Tests: **441 passed, 3 skipped** across Spouse / SpousePermission / FamilyMember
(the 3 are browser scenarios that do not run here); frontend **272 passed**, `/m`
SpouseSharing **8 passed**.

**Not done:** no browser drive of the withdraw button from either state.
