---
id: W-0472
title: The address a user invites their partner on is used once and never stored, so nobody can see who was invited, correct a typo, or re-send
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [product-lead, compliance-lead]
status: gated
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-24T00:55:00Z
claimed: null
blocked_by: [csj-decision]
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0347, W-0349]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while implementing W-0349, 2026-08-24 — a gap the invite-only decision creates, raised against my own change rather than left for a user to find
---

## Intent

CSJ's W-0349 decision (2026-08-23) stopped `POST /api/user/family-members`
creating an account for an unregistered address. It now invites it.

**Before, the address was retained as a side effect**: it became the `email` on
a real `users` row, so the caller could see who they had added. Now the address
is passed to `SpouseLinkingService`, used to send `SpouseInvitation`, and
**dropped** — `family_members` has **no email column**:

```
columns: id, user_id, household_id, linked_user_id, relationship,
         stated_relationship, first_name, middle_name, last_name, name,
         date_of_birth, gender, national_insurance_number, annual_income,
         is_dependent, education_status, receives_child_benefit, notes, ...
```

Measured: after inviting `nobody-w0349@example.com`, the resulting card reads
`email = NULL` — a phantom attribute, the silent variant recorded in
`tests/CLAUDE.md`.

## What that costs the user

- They cannot see **who** they invited.
- A typo is unrecoverable and gives no feedback — the invitation goes to an
  address nobody reads, and the screen looks identical to a correct one.
- There is no re-send, because there is nothing to re-send to.
- `SpousePermissionController::status()` **cannot distinguish** "we have invited
  someone and are waiting" from "we have no address to invite", so it serves one
  sentence covering both. That is honest, and it is less than the user deserves.

## Acceptance

1. **CSJ decides whether the invited address is retained.** It is a data-retention
   question as much as a product one — storing a non-user's email address, for a
   person who has not consented to anything, needs `compliance-lead` as well.
2. If retained: a column or a pending-invitation record, the screen naming the
   invited address, and a re-send that does not become a new enumeration oracle
   (W-0349) or a way to mail somebody repeatedly.
3. If not retained: the current single sentence stands, and this closes as a
   documented limitation rather than sitting open.
4. Whichever way, `/m` matches (Rule 19).

## Working notes

- 2026-08-24 — Raised against my own change while browser-verifying W-0349, rather
  than shipping a screen that cannot describe its own state. The two-branch
  message was written, found to have a branch that could never fire, and removed —
  a branch that cannot execute is worse than the limitation it hides.

---

## 2026-09-01 — acceptance 1 gated; a second defect found and fixed

**Acceptance 1 is not taken.** Storing a non-user's email address, for a person who has
consented to nothing, is a data-retention question needing CSJ **and**
`compliance-lead`. **No column was added and no invitation record was created.**

**Verified in the live schema first, not from the item:** `family_members` still has no
email column, and `SHOW TABLES` returns only `letters_to_spouse` and
`spouse_permissions` — no invitation store exists.

### The second defect, which is unconditional

The API has carried `invitation_pending: true` since W-0349
(`FamilyMembersController:245`), **and no frontend read it** — `grep` across
`resources/js` and `resources/mobile` returned only backend hits.

`FamilyMembers.vue:436-455` branched on `created` and `linked`. The invite path returns
**neither**, so it fell through to *"Family member added successfully!"* — the user
invited someone and was never told an invitation had gone out.

**And the `created` branch could not fire at all.** It set a temporary password and
opened a credentials modal; the controller returns no `created` key and
`SpouseLinkingService` sets `created_new_user => false` unconditionally, because W-0349
stopped this endpoint creating accounts. **A branch that cannot execute is worse than
the limitation it hides** — the same finding this item's own working note records
against an earlier two-branch message.

Replaced with an `invitation_pending` branch naming the address **the user has just
typed**. That discloses nothing — they typed it — and the response still confirms
nothing about whether the address is registered (W-0348, W-0349's enumeration
hardening). It also says the address is not kept, which is true under the current
answer and is the honest thing to say while acceptance 1 is open.

### Tests

`resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` — 2 new: the
message names the address and says an invitation went out, and it is **not** the
generic "added successfully". **Mutation-verified:** restoring the `created` branch
turns both red.

**Regression:** 738 frontend tests.

**Rule 19:** `/m` has the sharing panel and the invitee's answer route
(`router.js:81`) but **no add-family-member form**, so there is no counterpart to carry
this message. Verified in `router.js`, not assumed.

### Left alone deliberately

`spouseCreated` and `temporaryPassword` are now provably always `false`/`null` and feed
`SpouseSuccessModal`'s `is-created` and `temporary-password` props. They are inert — the
modal will always render its linked variant — and removing them means editing that
component's props too. Named here rather than widened into.

### The decision still outstanding, in one line

*Should the invited address be retained so the user can see who they invited, fix a
typo, and re-send?* If yes it needs a column or a pending-invitation record, a screen
that names it, and a re-send that becomes neither an enumeration oracle (W-0349) nor a
way to mail somebody repeatedly.
