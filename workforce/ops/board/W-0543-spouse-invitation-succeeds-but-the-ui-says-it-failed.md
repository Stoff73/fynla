---
id: W-0543
title: A spouse invitation succeeds and the UI tells the user it failed and to add them again
mission: new-user-run-2026-09-07
branch: fix/board-w0550-w0543-w0544-w0542-w0545-w0546-w0547
owner: build-lead
reviewers: [build-lead, design-lead]
status: done
severity: high
surfaces: [web, m]
created: 2026-09-09
source: new-user run, csjones 2026-09-07
prior_art_checked: 2026-09-09
prior_art_found: [W-0051, W-0112, W-0113]
prior_art_outcome: extends — those cover spouse creation and linking mechanics; this is the invitation-pending state never reaching the UI
constitution_refs: [07-quality-bar]
---

## Intent

Onboarding step 2 -> Add Family Member -> Spouse -> supply email -> Save.

`POST /api/user/family-members` returns **201**:

```json
{"message":"Invitation sent. Your spouse will be asked to confirm the link before anything is shared.",
 "linked":false, "invitation_pending":true}
```

The backend did the right thing. The UI discarded it and rendered:

> "Their account is not linked, so nothing is shared between you yet.
> **Add them again with their email address to link the accounts.**"

The email *had* been supplied. The instruction is wrong — it tells the user to
repeat an action that already succeeded.

`resources/js/utils/familyMember.js` — `familyMemberManagementNotice()` has
branches for linked / shared-from-spouse / spouse-not-linked and **none for a
pending invitation**, so every unlinked spouse falls through to the "add them
again" string.

Deeper: the helper receives only `member`, and `GET /api/user/family-members`
does not carry `invitation_pending` on the member at all — it exists only on the
POST envelope. **Pending state is structurally unrepresentable in the list UI**,
so a frontend-only fix is not sufficient.

`grep -rn "invitation_pending" resources/js/ resources/mobile/` -> **zero
matches**, while `CoordinatingAgent.php:1816` consumes it correctly. Implemented
once for the chat surface, never for the form surfaces. See W-0549.

## Acceptance

- [ ] The family-members list payload carries per-member link state.
- [ ] A pending invitation renders the API's own message, not remedial instructions.
- [ ] `/m` shows the same notice from the same helper (Rule 20 — one home).
- [ ] A test pins the pending branch.

## Outcome — done, 2026-09-10

Confirmed live: the profile page had handled `invitation_pending` on the POST since
W-0472, but `FamilyInfoStep.vue` (onboarding) branched on `linked` only, and after any
refresh both surfaces read the list, which could not express a pending invitation, so
`familyMemberManagementNotice` fell through to "Add them again".

- `UserProfileService::getFamilyMembersWithSharing` — each own row carries
  `invitation_pending`: relationship spouse, no live linked account, and a `pending`
  `SpousePermission` sent by this user (the row `SpouseLinkingService::
  createPendingSpouseInvitation` writes). Test: `FamilyMembersControllerTest`
  "flags an unlinked spouse row while this user has an unanswered invitation out" and
  the negative case.
- `utils/familyMember.js` — pending branch in the notice ("Invitation sent. They will
  appear as linked once they accept…") and `spouseInvitationSentMessage(email)`, the one
  home for the moment-of-send message; `FamilyMembers.vue` and `FamilyInfoStep.vue` both
  use it (the profile page's inline copy moved into the helper). Test:
  `utils/__tests__/familyMember.spec.js`.
- `/m` has no family-members surface (no counterpart), so nothing to mirror.
- Not browser-driven end to end: a live invitation needs two accounts with an unanswered
  request; the list flag and the notice are pinned by tests, and the onboarding branch
  mirrors the profile page's proven one.
