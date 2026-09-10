# F-05 · Spouse invitation succeeds, UI tells the user it failed — HIGH

**Env:** csjones staging · **Surface:** desktop web (and `/m` by omission)
**Repro:** Onboarding step 2 → Add Family Member → Relationship "Spouse" →
supply spouse email → Save.

## What happened

`POST /api/user/family-members` returned **201** with:

```json
{"message":"Invitation sent. Your spouse will be asked to confirm the link before anything is shared.",
 "linked":false, "invitation_pending":true}
```

The backend did the right thing: it sent an invitation and flagged it pending.

The UI discarded that and rendered:

> "Their account is not linked, so nothing is shared between you yet.
> **Add them again with their email address to link the accounts.**"

The email *had* been supplied. The instruction is not just unhelpful, it is wrong —
it tells the user to repeat an action that already succeeded.

## Root cause

`resources/js/utils/familyMember.js:62-75` — `familyMemberManagementNotice()` has
three branches (linked / shared-from-spouse / spouse-not-linked) and **no branch
for a pending invitation**. Any unlinked spouse falls through to the "add them
again" string at line 71.

Deeper: the helper receives only `member`, and `GET /api/user/family-members`
does not include `invitation_pending` on the member object at all — it exists
only on the POST envelope. So pending state is **structurally unrepresentable**
in the list UI. A frontend-only fix is not sufficient.

## Rule 20 dimension

`invitation_pending` is emitted by the backend in several places
(`FamilyMembersController.php:245`, `SpouseLinkingService.php:283,445`) and is
correctly consumed by Fyn's path (`CoordinatingAgent.php:1816`).

`grep -rn "invitation_pending" resources/js/ resources/mobile/` returns **zero
matches**. The invitation-pending concept was implemented once for the chat
surface and never for the form surfaces — two mechanisms, one fix. `/m` has no
equivalent notice at all (`grep "not linked" resources/mobile/` → no matches),
so it is a separate gap rather than the same bug.

## Fix direction

1. Include per-member link state (`invitation_pending`) in the family-members
   list payload.
2. Add the pending branch to `familyMemberManagementNotice()` — surface the
   API's own message ("Invitation sent…") rather than remedial instructions.
3. Give `/m` the same notice from the same helper (Rule 20: one home).

## Related observation — date of birth stored a day early

Entered `1977-07-22`; API stored `1977-07-21T23:00:00.000000Z` (BST, UTC+1).
Derived age (49) is correct, and the **edit form round-trips it correctly as
`1977-07-22`** — verified in-browser. So this is **not** a user-visible defect
on web. Retained only as a watch item for `/m` and native iOS, where timezone
handling differs. Downgraded from finding to observation.
