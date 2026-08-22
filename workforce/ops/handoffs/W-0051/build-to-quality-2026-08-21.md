# W-0051 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md`
**Board item:** `workforce/ops/board/W-0051-onboarding-spouse-creates-unmanageable-orphan.md`
(working notes carry the full file:line record)

## Done

**The cause was a conflated fact, not a wrong label.**
`family_members.relationship = 'spouse'` carries a *household* fact — this person is my
spouse — and every reader used it to answer an *account* question: is this row backed by
a linked Fynla account. That fact has its own column, `linked_user_id`, plus
`users.spouse_id` and `SpousePermission`. Writers that could not establish a link still
wrote the relationship, so the interface asserted a link nothing had made and withheld
Edit and Delete on the strength of the assertion.

- **One predicate, appended once.** `FamilyMember::isLinkedAccount()` —
  `linked_user_id` present and that account still live — shipped on every serialisation
  as `is_linked_account`. Front end reads it through
  `resources/js/utils/familyMember.js` (`isLinkedAccount`, `canManageFamilyMember`,
  `familyMemberManagementNotice`). Both components call it; neither branches on the
  relationship string any more.
- **Three linking mechanisms collapsed to one.**
  `FamilyMembersController::handleSpouseCreation()` and
  `OnboardingService::handleSpouseLinking()` now delegate to `SpouseLinkingService`,
  which already claimed in its own docblock to serve both. The endpoint's response
  contract is unchanged.
- **Linking adopts an existing unlinked row instead of adding a second**
  (`SpouseLinkingService::upsertFamilyMemberRow` / `adoptableSpouseRow`), on both sides
  of the household. Fills only; never overwrites; never touches a row linked elsewhere.
- **The spouse email is now required, on both sides** — `required_if` server-side (the
  rule the existing `messages()` entry had been waiting for) and in the modal, because
  an empty string was stripped before the request was sent.
- **`update()` and `destroy()` branch on the link.** This mattered more than the
  buttons: before, deleting the *unlinked* row would have nulled `spouse_id` on both
  users, deleted both `SpousePermission` rows and deleted the reciprocal record on the
  spouse's own account. Editing it rewrote the real spouse's date of birth, gender,
  income and National Insurance number. Restoring Delete without this would have turned
  an unmanageable record into a destructive one.
- **Fyn stops claiming a link it did not make** (`OnboardingChatDirector::spouseAck()`).
- **The repair:** `php artisan family:reconcile-spouse-links`, dry-run by default,
  `--force`, `--user=`. Idempotent. Soft-deletes only, so folded rows are retained per
  `deleted-spouse-visibility.md` §1.

**Tests, all under `DB_DATABASE=laravel_testing_d`:** 14 new (`SpouseFamilyLinkTest`),
8 new (`ReconcileSpouseFamilyLinksTest`), 12 new (`familyMember.test.js`); regression
sweeps 478 / 120 / 47 / 66 passed. `pint` clean.

## Not done, and why

- **No browser verification.** Per the dispatch, a persona-tester closes Rule 14's loop
  independently. Every claim above is DB-level or test-level; none is a UI claim.
- **`family:reconcile-spouse-links --force` was NOT run.** `users.id 20/30` and
  `family_members 25/46/47` are the only live reproduction and the re-verification needs
  them. Row 25 is already editable and deletable through the interface as of this fix,
  so the acceptance is satisfied without destroying the evidence. Dry-run output is in
  the board item; run `--force` **after** re-verification.
- **Nothing committed, no PR, no deploy, no `/m` rebuild** — 554 uncommitted paths from
  other agents share this tree.
- Three adjacent defects were deliberately left and raised instead: **W-0111** (a
  partner's email is collected and silently discarded — needs a CSJ product decision
  first), **W-0112** (the spouse name sync is a no-op because `users` has no `name`
  column), **W-0113** (two Fyn tools write a spouse; only one can link).
- `SpouseLinkingService::linkExistingSpouse()`'s already-linked branch still does not
  guarantee the reciprocal row exists on the spouse's side. Adoption made the reciprocal
  writer safe to call there, but adding the call changes that branch beyond this item.

## What you need that isn't obvious from the artefacts

- **The W-0051 report's "the onboarding family form has no email field" does not hold
  up.** `FamilyInfoStep.vue` embeds the same `FamilyMemberFormModal` as settings and the
  email block is not gated on `context`; both onboarding paths map `family` to that step
  (`OnboardingWizard.vue:421`). The orphan is fully explained by the missing validation
  on both sides. **Please have the re-verification confirm the field renders during
  onboarding** rather than treating its absence as established.
- **The re-verification needs a fresh registration**, not the existing reproduction: the
  spouse email is now required, so the old repro path (save a spouse with no email) is
  supposed to be refused. The new expected journey is: onboarding → Family → Spouse →
  email required → save → the card shows "Account Linked" and no Edit/Delete, and
  `users.spouse_id` is set on both sides.
- **The second thing to verify is the one the report never reached:** an *unlinked*
  spouse row must now show Edit and Delete plus the notice "Their account is not linked,
  so nothing is shared between you yet." The only way to produce one now is Fyn's
  free-text capture, or row 25 on user 20.
- `is_linked_account` is an additive field on every family-member payload. iOS ignores
  unknown keys and `/m` does not decode family members, so it cannot break either.

## Assumptions I made

*Stated as assumptions, not facts.*

- **That requiring the spouse email is what CSJ wants.** The item asked for a deliberate
  decision and this is mine, with reasoning in the board item §Acceptance. It does mean
  a user can no longer record a spouse through the form without creating an account for
  them. If that is wrong, the predicate work stands unchanged and only the `required_if`
  and the modal check come out.
- **That the response contract of `POST /api/user/family-members` is fully described by
  its consumers.** I preserved `created`, `linked`, `spouse_email`, `email_sent`,
  `already_existed`, `record_created` and the 200/201 split. Only `created`, `linked`
  and `spouse_email` are read anywhere I could find; the rest are preserved on the
  assumption something unseen may read them.
- **That `temporary_password` should stay absent from the response.** The service
  returns it and the frontend reads `responseData.temporary_password || null`, so the
  modal has always shown nothing. Adding it now would put a password in a JSON response.
  I left it out; that is a change in behaviour I chose not to make.
- **That the civil-partnership correction is wanted.** Consolidating on the service means
  a civil partnership is no longer forced to "married". It is the better behaviour and
  already live for Fyn, but it is a change on the settings path.

## Surfaces covered / not covered

- **Web** — covered. Both components, the modal, the request, the controller, the
  service, the repair.
- **`/m`** — checked, no counterpart exists. `PersonalInformation.vue:39` renders
  `profile.spouse.name` from `users.spouse_id`; `family_members` is used only at `:201`
  filtered to `is_dependent`, which excludes the spouse. No management screen, no
  Edit/Delete, no linked-account notice, no duplicate-card route. **No `/m` change was
  needed and the `/m` bundle was not rebuilt.**
- **iOS** — checked, no counterpart exists. `PersonalInformationView.swift:77-78`
  renders `profile.spouse` from the same source; `PersonalInformationModels.swift`
  does not decode `family_members`.
- **All paths (Rule 20)** — the settings add path, the onboarding add path (classic
  wizard and life-stage journey, both mapping to `FamilyInfoStep`), the onboarding bulk
  save (`OnboardingService::processFamilyInfo`), Fyn's `capture_spouse_details`, and
  Fyn's free-text spouse capture all now reach the same predicate and the same linking
  service. `create_family_member` is the one path that still cannot link — W-0113.
