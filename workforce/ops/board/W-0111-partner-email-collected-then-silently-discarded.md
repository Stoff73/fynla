---
id: W-0111
title: Adding a Partner asks for an email address "to create or link their account", then silently discards it — no account, no link, no error
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: gated
severity: medium
surfaces: [web, m]
created: 2026-08-21T18:40:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [SpouseLinkingService, FamilyMembersController::store, StoreFamilyMemberRequest]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 04-voice]
---

## Intent

Found while fixing W-0051, by reading the store path rather than the symptom. Not
reported by a persona run — nobody has walked the Partner branch.

### Actual

`FamilyMemberFormModal.vue:55` reveals the Email field for **spouse or partner**,
under the label "Email Address \*" and the helper text "Used to create or link
their account".

For a partner, none of that happens:

- `FamilyMembersController::store()` routes to the linking service only when
  `relationship === 'spouse'`. A partner falls through to the plain insert.
- `StoreFamilyMemberRequest` validates `email` as a well-formed address, so it
  survives into `validated()`.
- `FamilyMember::create([...$data, ...])` then drops it, because `email` is not
  in `FamilyMember::$fillable` and `Model::preventSilentlyDiscardingAttributes()`
  is **not** enabled (`AppServiceProvider.php:208` enables only
  `preventLazyLoading`).

So the address is typed, validated, accepted, returned 201 — and is nowhere. No
user account, no `linked_user_id`, no row, no error, no message.

### Impact

The user is told an account will be created or linked, sees the request succeed,
and has no way to discover that nothing happened. It is the same disease W-0051
fixed for spouses — the interface asserting an account fact the mechanism never
established — surviving on the relationship the fix did not cover.

Also note the copy is **wrong on the merits** even if it worked: the modal's own
warning two lines above says a partner "is not a legally recognised relationship
for UK tax purposes", and `CoordinatingAgent::handleCreateFamilyMember()` maps
`partner` to the `other_dependent` database value. Whether a partner should be a
linkable account at all is a product decision, not an implementation gap.

### Evidence

- `resources/js/components/UserProfile/FamilyMemberFormModal.vue:50-70` — the
  field and the promise
- `app/Http/Controllers/Api/FamilyMembersController.php:70` — `spouse` only
- `app/Models/FamilyMember.php:24-42` — `email` is not fillable
- `app/Providers/AppServiceProvider.php:208` — strict-attribute mode is off, so
  the discard is silent
- `app/Agents/CoordinatingAgent.php:4699-4703` — `partner` → `other_dependent`

## Acceptance

- [ ] **A decision first, before any code:** does a partner get a linked Fynla
      account, or not? Ask CSJ — this is product, not implementation.
- [ ] If yes: partners route through `SpouseLinkingService` like every other
      link, never a second mechanism (Rule 20), and the copy stays.
- [ ] If no: the Email field is not shown for a partner and the copy that
      promises an account goes with it.
- [ ] Either way, nothing accepts a field it intends to discard.
- [ ] Web and `/m` (Rule 19 — `/m` has no family-member form today; confirm and
      state that rather than assuming it).

## Working notes

Raised by `fix-batch-I` from the W-0051 ID block (W-0111–W-0120). Deliberately not
fixed inside W-0051: that item is about spouses and the link predicate, and
answering "should a partner have an account" inside a bug fix would be inventing
a product decision (Rule 16).


---

## Working notes — fix-batch-I, 2026-08-21 (append-only)

### Investigating this found something worse first

Adding a partner did not merely lose the email — **it returned HTTP 500.**
`family_members.relationship` is an enum of four values and the form offers six;
`partner` and `step_child` both raised `SQLSTATE[01000] 1265 Data truncated`
under strict mode. Raised as **W-0114** (high) and fixed there. The email
question could not be answered coherently while adding a partner failed outright,
so W-0114 went first.

### The decision

The team lead's binding constraint was: *the promise the copy makes and what the
code does must end up in agreement — if the email genuinely cannot link at that
point, the copy must stop saying it will.*

**Answer taken: a partner does not get an account.** The email field and its
promise are removed for every relationship except spouse.

Reasoning, since this is a product call made under an explicit constraint rather
than a free choice:

- The modal's own copy two lines above already warns that a partner "is not a
  legally recognised relationship for UK tax purposes" — unmarried partners
  cannot share allowances, transfer the nil rate band, or use the spouse
  exemption. Offering to link their finances contradicts the warning beside it.
- `family_members.relationship` has no `partner` value at all (W-0114); a partner
  is stored as `other_dependent`. Building account linking on top of a
  relationship the schema does not recognise would be building on sand.
- Removing a promise is reversible. Creating accounts for partners is a
  commitment to a whole spouse-permission and data-sharing surface for a
  relationship the tax model does not support, and that is CSJ's to want, not
  mine to infer from a form field.

If CSJ does want partners linkable, the mechanism is already there —
`SpouseLinkingService` — and the work is the schema and the permission model, not
the plumbing.

### What changed

- `resources/js/components/UserProfile/FamilyMemberFormModal.vue` — the Email
  field reveals for **spouse only**. It stays add-only (W-0051): `PUT` cannot
  link an account, so offering it on an edit would discard it silently, which is
  this same defect wearing a different hat.
- `app/Http/Requests/StoreFamilyMemberRequest.php` —
  `prohibited_unless:relationship,spouse` on `email`, with a message that
  explains why rather than just refusing. **Nothing accepts a field it intends to
  discard**, which was this item's acceptance criterion.

### Tests

`tests/Feature/Api/FamilyMemberRelationshipAliasTest.php` — **10 passed**,
including a `describe('W-0111 — only a spouse takes an email')` block: a partner
email is refused rather than accepted-and-dropped, a child email likewise, and a
partner with no email still saves. The refusal asserts that no `family_members`
row and no `users` row were created — the point being that nothing was half-done.

### Not done

The Fyn tool path ignores an `email` sent with a non-spouse relationship rather
than refusing it. Left as-is: the v2 tool schema now states "Null for every other
relationship", and a stray field from a model is not a user being shown a promise.
Noted rather than hidden.


---

## Ruling — partner linking is unsupported BY DESIGN, not unbuilt

Team lead, 2026-08-21, confirming the recommendation and giving the reason it is
not a Rule 16 deviation:

> **The system has already decided this and only the copy disagrees.** The
> modal's own warning two lines above says a partner is not legally recognised
> for UK tax, and `CoordinatingAgent` maps `partner` → `other_dependent`. So the
> promise "to create or link their account" is false by the application's own
> design, not by accident. **Removing a false promise is making copy match
> behaviour.** Building partner account-linking would be the new feature, and
> that is CSJ's call, not something to smuggle into a defect fix.

**Recorded deliberately so nobody re-adds the field in six months believing it
was an oversight.** It was not an oversight and it is not a gap in the backlog.
The Email field is absent for a partner because partners do not have linked
accounts in this product — a decision, taken, with a reason.

If CSJ later wants partner accounts, it is a **feature request**, and it starts
from the tax-recognition question — whether the product should model a
relationship HMRC does not recognise as one that shares allowances, transfers the
nil rate band, or claims the spouse exemption — not from this form field. The
plumbing (`SpouseLinkingService`) would be the least of it; the work is the
schema and the permission model.
