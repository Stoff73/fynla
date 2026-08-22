---
id: W-0051
title: Onboarding creates a spouse family member with no account link, then declares it a "Linked account" and removes Edit and Delete — permanently unmanageable from the first run through the product
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T12:20:00Z
claimed: 2026-08-21T17:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: [SpouseLinkingService, FamilyMembersController::handleSpouseCreation, OnboardingService::handleSpouseLinking, User::liveSpouse, BackfillWillBequests]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **registration / onboarding sweep**, local
`localhost:8000`, clean isolated browser context, throwaway account
(`pt.throwaway.primary+0821@example.com`, `users.id 20`). Owned by no fix batch.

**Surface:** `/onboarding` step 2, "Family & Dependents".

### Expected

Adding a spouse during onboarding should either

- **link an account** — create or link the spouse's user account, set
  `family_members.linked_user_id` and the reciprocal `users.spouse_id`, exactly as the
  settings path does — or
- **create an ordinary editable family member**, with Edit and Delete available like
  every other relationship.

What it must not do is claim a link that does not exist and then withhold the controls
on the strength of that claim.

### Actual

The onboarding family form has **no email field**. Confirmed against the live DOM — the
form offers only `relationship`, `first_name`, `middle_name`, `last_name`,
`date_of_birth`, `gender`, `is_dependent` and `notes`. Without an email there is
nothing to link an account by.

Adding "Arjun Raman / Spouse" therefore produces:

```
family_members.id 25  relationship=spouse  linked_user_id=NULL
users.id 20           spouse_id=NULL
User::where('first_name','Arjun')->count()  =  0
```

No account was created. No account was linked. But the card renders as:

> **Arjun Raman** · Spouse · Age: 49 years
> *Linked account — edit or delete by logging into the spouse's account*

with **zero buttons**. The child added in the same session — "Meera Raman / Child" —
renders with **Edit** and **Delete**. Verified by DOM inspection of both cards:
`arjun.buttons === []`, `meera.buttons === ['Edit','Delete']`.

So the user is told to manage the record by logging into an account that does not
exist. The row cannot be edited or deleted from any surface. A misspelled name, a
wrong date of birth, or a spouse added by mistake is permanent.

### Root cause

Both components branch on the **relationship string**, never on whether a link exists:

- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue:61` —
  `v-if="member.relationship !== 'spouse'"` gates the Edit/Delete block;
  `:77-81` renders the "Linked account" notice in the `v-else`.
- `resources/js/components/UserProfile/FamilyMembers.vue:96` —
  `v-if="!member.is_shared && member.relationship !== 'spouse'"` gates Edit/Delete;
  `:139-141` renders the same notice.

`family_members.linked_user_id` exists and is exactly the right signal. Neither
component reads it.

**Why the settings path is fine and onboarding is not.** In
`resources/js/components/UserProfile/FamilyMemberFormModal.vue:49-59`, choosing Spouse
or Partner reveals an **Email** field, and the copy at `:38-43` states: "A user account
will be created for your spouse if they don't have one yet. If they already have an
account, it will be linked" and "once added, this linked account can only be edited or
deleted by logging into the spouse's account." On that path the notice is **true** —
a link really is made. Onboarding reuses the notice without the mechanism that earns it.

### Impact

**The link the rest of the product depends on is silently not made.** Joint ownership
(Rule 6), `SpousePermission`, household roll-ups, mirror wills and the whole spouse
half of every persona run key off `users.spouse_id`. A user who adds their spouse in
onboarding reasonably believes the household is set up. It is not, and nothing tells
them so.

**Unmanageable data on the first run through the product.** This is step 2 of
onboarding — the very first thing a new user does. The one relationship they cannot
correct is the one most likely to be entered while still learning the product.

**It also affects the persona runs.** Playbook §1.0 links the spouse via
`/settings/family`, which works. Any tester who instead adds the spouse during
onboarding gets an orphan and a household that never links.

### Repro

1. Register a new account and verify the code. Land on `/onboarding?newUser=1`.
2. Choose any life stage → step 2 "Family & Dependents" → **Add Family Member**.
3. Relationship = **Spouse**, first and last name, date of birth, gender. Note there is
   no email field. Save — `POST /api/user/family-members` returns 201.
4. The card reads "Linked account — edit or delete by logging into the spouse's
   account" and has no Edit or Delete button.
5. `php artisan tinker` — `family_members.linked_user_id` is NULL and
   `users.spouse_id` is NULL. No user exists for that name.
6. Go to `/settings/family` — the same record is still uneditable and undeletable there
   (`FamilyMembers.vue:96`).

### Evidence

- `tests/Persona/20-08-2026_run/pass-a-web/25-web-onboarding-family-spouse-filled.png`
- `tests/Persona/20-08-2026_run/pass-a-web/26-web-onboarding-spouse-linked-account-no-controls.png`
- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue:61, 77-81`
- `resources/js/components/UserProfile/FamilyMembers.vue:96, 139-141`
- `resources/js/components/UserProfile/FamilyMemberFormModal.vue:38-43, 49-59`
- DB state quoted above for `users.id 20`, `family_members.id 25` and `26`

## Acceptance

- [ ] Edit and Delete are gated on **`linked_user_id`** (or an equivalent real link
      signal), never on `relationship === 'spouse'` — in **both**
      `FamilyInfoStep.vue` and `FamilyMembers.vue`, from ONE shared predicate rather
      than two copies (Rule 20).
- [ ] The "Linked account" notice appears only when a link actually exists.
- [ ] Decide deliberately which the onboarding step should do: capture an email and
      link the spouse the way the settings modal does, or create a plain editable
      family member. If it links, it must set the reciprocal `users.spouse_id` and go
      through the same `SpousePermission` flow — a second linking mechanism would be a
      Rule 20 violation.
- [ ] Existing orphan rows (`relationship='spouse'` with `linked_user_id IS NULL`) are
      identified and made editable — they are unmanageable today.
- [ ] If the user adds a spouse in onboarding, the product tells them plainly whether
      the accounts are linked, since everything joint depends on it.
- [ ] `/m` and iOS family surfaces checked for the same branch (Rule 19).
- [ ] Re-verified live in the browser by the persona run, from a fresh registration.

## Working notes

Found on the first pass through onboarding with a throwaway account — no persona data
involved. It is the kind of defect that only shows up when someone actually walks the
new-user journey rather than starting from seeded state, which is precisely why this
sweep was commissioned.

Checked and cleared while investigating: the dates were **not** stored a day early. The
`1977-06-01T23:00:00Z` seen in `tinker` JSON output is Eloquent's `date` cast
serialising to UTC; the raw column holds `1977-06-02` correctly. No date defect — noted
here so nobody re-chases it.

## Addendum — the orphan is not just unmanageable, it guarantees a permanent duplicate

Continuing the sweep past the original finding, I then linked the spouse properly via
`/settings/family` → Add → Spouse (which does expose an Email field). **The linking
mechanism itself works correctly and completely:**

```
users.id 20  spouse_id = 30          users.id 30  spouse_id = 20      (reciprocal)
family_members.id 46  user_id=20  linked_user_id=30                   (forward)
family_members.id 47  user_id=30  linked_user_id=20                   (reciprocal)
SpousePermission  20 -> 30  status=accepted
SpousePermission  30 -> 20  status=accepted                           (auto-accepted both ways)
```

`POST /api/user/family-members` returned 201 with "Spouse account created
successfully. They will receive an email with login instructions." That half of the
flow is **GREEN** and needs no work.

**But the onboarding orphan is still there, and cannot be removed.** The household now
holds two spouse rows for one spouse:

| id | name | relationship | `linked_user_id` |
|---|---|---|---|
| 25 | Arjun Raman | spouse | **NULL** — the onboarding orphan |
| 46 | Arjun Raman | spouse | 30 — the real link |

And `/settings/family` renders **both** as separate cards, each captioned
"**Account Linked**" and each carrying "Linked account — can only be edited or deleted
by logging into the spouse's account". **Neither card has an Edit or a Delete button.**
Verified by DOM count on a fresh page load: `arjunCount: 2`, `linkedNotices: 2`,
`meeraCount: 1` (the child, which does have Edit and Delete).

So the "Account Linked" badge is also driven by the relationship string rather than by
`linked_user_id` — row 25 has no link at all and still claims to be linked.

### Why this raises the severity

The duplicate is reached by following the product's own happy path — add your spouse
during onboarding, then link their account from settings — and once reached there is
**no route back**, on any surface. The household permanently misreports its own
membership, and every downstream consumer that counts spouses or iterates
`family_members` sees two.

Worth checking as part of the fix: whether estate, protection-gap and household
roll-ups double-count a duplicated spouse, and whether the reciprocal row
`family_members.id 47` (`first_name` "Priya", on the spouse's account) is complete.

Evidence:
`tests/Persona/20-08-2026_run/pass-a-web/30-web-settings-family-spouse-link-filled.png`,
`31-web-settings-family-duplicate-spouse-after-link.png`.

Additional acceptance:

- [ ] Linking a spouse reconciles with any existing unlinked `relationship='spouse'`
      row rather than adding a second one — update the orphan in place, or refuse the
      add and explain why.
- [ ] The "Account Linked" badge reads `linked_user_id`, not the relationship string.
- [ ] Household consumers are checked for double-counting where two spouse rows exist.


---

## Working notes — fix-batch-I, 2026-08-21 (append-only)

### Prior art (six sources, before a line was written)

| Source | Found |
|---|---|
| `registry/capabilities.md` | nothing on spouse linking |
| Code | **`app/Services/Onboarding/SpouseLinkingService.php`** — a complete linking service whose own docblock (`:22-25`) already claims it serves "both the FamilyMembers UI flow (through `FamilyMembersController::handleSpouseCreation`) and the Fyn onboarding director". It did not. Also `User::liveSpouse()`/`liveSpouseId()` (`app/Models/User.php:437-483`) — the existing precedent for "the link, but only while the account is live" |
| Custom artisan commands | none for family data; `app/Console/Commands/BackfillWillBequests.php` is the shape precedent for a reviewable repair |
| Open PRs / in-flight branches | nothing touching `family_members` |
| Vault | `v083/03-AUTH-SECURITY.md`, `Auth.md`, `v083/09-MODULES.md` |
| `.claude/skills` / `.claude/agents` | nothing |

**Outcome: extend.** `SpouseLinkingService` is the adequate mechanism; the two
inline copies were folded into it rather than a fourth being written.

### The mechanism-level cause

`family_members.relationship = 'spouse'` carries two different facts at once:

- a **household** fact — this person is my spouse;
- an **account** fact — this row is backed by a linked Fynla account.

The account fact has its own column, `linked_user_id`, and two further
expressions, `users.spouse_id` and `SpousePermission`. **Every reader branched on
the household fact to answer the account question, and every writer that could
not establish a link still wrote the household fact.** So the interface asserted
a link that nothing had made, and withheld Edit and Delete on the strength of the
assertion. Editing the label would have left the cause untouched.

Three writers could produce the unlinked row, and three linking mechanisms
existed where the codebase believed it had one:

| Mechanism | State before |
|---|---|
| `FamilyMembersController::store()` | spouse **without** an email fell straight through to a plain insert — `linked_user_id` NULL. The email rule was never written; `messages()` already carried `email.required_if` for a rule that did not exist (`StoreFamilyMemberRequest.php:93`) |
| `FamilyMembersController::handleSpouseCreation()` | its own ~250-line copy of linking, diverged: forced `marital_status = 'married'`, demoting a civil partnership |
| `OnboardingService::handleSpouseLinking()` | a third copy, diverged furthest: given an email for an account that did not exist yet it created **neither** the account nor the link, just an unlinked row |
| `OnboardingChatDirector::createSpouseFamilyMember()` | free-text capture, no email by design — and `spouseAck()` (`:5633`) told the user "I've added X **and linked the two of you**" over a row that linked nobody |
| `CoordinatingAgent::handleCreateFamilyMember()` | accepts `relationship: 'spouse'`, has no email parameter at all — raised as **W-0113** |

Two more consequences nobody had reported, both found by reading the enforcing
code rather than the symptom, and both **worse than the original defect once
Edit and Delete were restored**:

- **`destroy()` (`:602` before the fix)** branched on `relationship === 'spouse'
  && $user->spouse_id`. Deleting the *unlinked* row would have nulled `spouse_id`
  on **both** users, deleted **both** `SpousePermission` rows and deleted the
  reciprocal record on the spouse's own account — tearing down a real, separate
  link on behalf of a row that never linked anything.
- **`update()` (`:542` before the fix)** had the same shape: correcting a typo on
  the unlinked record rewrote the real spouse account's date of birth, gender,
  income and National Insurance number.

### What changed

**One predicate, one home** — `FamilyMember::isLinkedAccount()`
(`app/Models/FamilyMember.php:129-198`). `linked_user_id` present **and** that
account still live. Liveness is part of the rule: `linked_user_id` survives the
partner deleting their account for retention
(`August/August19Updates/spec/deleted-spouse-visibility.md` §1), and telling a
user to manage a record inside an account that no longer exists strands them
exactly as NULL did. Resolved without lazy loading (cached explicit query, the
`User::liveSpouse()` shape) because it is **appended** — `$appends = ['age',
'is_linked_account']` — so it crosses the API boundary once and every surface
reads the same boolean instead of re-deriving it.

Front end: `resources/js/utils/familyMember.js` — `isLinkedAccount`,
`canManageFamilyMember`, `familyMemberManagementNotice`. Both components now call
it; neither branches on the relationship string.

| File | Before | After |
|---|---|---|
| `FamilyInfoStep.vue:47` | `relationship === 'spouse' && member.email` | `isLinkedAccount(member)` |
| `FamilyInfoStep.vue:64` | `relationship !== 'spouse'` | `canManageFamilyMember(member)` |
| `FamilyMembers.vue:86` | `relationship === 'spouse' && member.email` | `isLinkedAccount(member)` |
| `FamilyMembers.vue:97` | `!is_shared && relationship !== 'spouse'` | `canManageFamilyMember(member)` |
| `FamilyMembers.vue:139` | two hardcoded notices | `familyMemberManagementNotice(member)` |

**Linking consolidated onto `SpouseLinkingService`.** `handleSpouseCreation` now
delegates (`FamilyMembersController.php:121-192`); `OnboardingService::handleSpouseLinking`
now delegates (`OnboardingService.php:308-350`). The response contract of the
endpoint is unchanged (`created`, `linked`, `spouse_email`, `email_sent`,
`already_existed`, `record_created`, and the 200/201 split). The trashed-email and
self-link checks stay in the controller because this surface answers those with a
field-level validation error the form highlights; the linking itself does not.
Two behaviour changes fall out, both corrections: a civil partnership is no longer
demoted to married, and onboarding with an email for a not-yet-existing account
now actually creates and links it.

**Reconciliation, so linking cannot produce a second card.**
`SpouseLinkingService::upsertFamilyMemberRow()` and `createReciprocalFamilyMember()`
adopt an existing spouse row — one already pointing at this spouse, or one
pointing at nobody — instead of inserting. Adoption only fills; a field the caller
did not supply never wipes one the row holds. A row pointing at a *different*
account is somebody else's link and is never touched. `linkExistingSpouse()`'s
"already linked" branch previously returned whatever spouse row it found first,
which on this household returned the orphan and reported "already linked" over
the top of it — it now goes through the same upsert.

**The email is required for a spouse, on both sides.**
`StoreFamilyMemberRequest.php:36` `required_if:relationship,spouse` — the rule the
existing message had been waiting for — and `FamilyMemberFormModal.vue:455`
client-side, because an empty string is stripped from the payload before it is
sent, so the request arrived carrying no email at all. The email field is now
**add-only**: `PUT` cannot link an account and would have discarded it silently.
To link a spouse already on file, add them again with their email — the upsert
adopts the existing record, which is what the card's new notice tells the user.

**Fyn stops claiming a link it did not make.**
`OnboardingChatDirector::spouseAck()` reads the same predicate.

**The repair.** `php artisan family:reconcile-spouse-links` — dry-run by default,
`--force` to apply, `--user=` to scope. Adopts a lone orphan onto the live link;
folds a duplicate into the linked row (filling only gaps) and soft-deletes it, so
the row is retained exactly as §1 requires; leaves a spouse record alone when
there is no live account to link to, because that record is now an ordinary
editable one, which is the correct end state and not a defect. Idempotent by
construction. Dry-run against the live database reports precisely one change:

```
| 20 | pt.throwaway.primary+0821@example.com | record 25 folded into 46 and retired |
Orphan rows adopted onto the live link: 0
Duplicate rows folded into the linked row and retired: 1
Spouse rows left as ordinary records (no live account link): 0
```

**`--force` was deliberately NOT run.** `users.id 20/30` and
`family_members 25/46/47` are the only live reproduction of this defect and the
re-verification depends on them. Row 25 is editable and deletable through the
interface as of this fix, so the acceptance is met without destroying the
evidence. Run `--force` after the persona re-verification, not before.

### Acceptance

- [x] Edit and Delete gated on the link, from ONE shared predicate in both
      components — `resources/js/utils/familyMember.js`.
- [x] The "Linked account" notice appears only when a link exists.
- [x] **Decision: onboarding captures the email and links**, through the same
      single mechanism as settings. Reasons: the form already had the field and
      the copy; the settings path is verified green; `users.spouse_id` is what
      everything joint, `SpousePermission`, household roll-ups and mirror wills
      key off; and an editable-but-unlinked spouse would still leave the
      household unlinked while the user reasonably believed it was set up. An
      unlinked spouse row remains **possible** (Fyn free-text capture, legacy
      data) and is now honest, manageable, and labelled as not linked.
- [x] Existing orphans identified and made editable — by the predicate
      immediately, and repairable by `family:reconcile-spouse-links`.
- [x] The product says plainly whether the accounts are linked — the card notice
      and Fyn's acknowledgement.
- [x] `/m` and iOS checked (Rule 19) — see below.
- [ ] Re-verified live in the browser from a fresh registration — **persona-tester**,
      not self-certified.
- [x] Linking reconciles with an existing unlinked row rather than adding a second.
- [x] The "Account Linked" badge reads the link, not the relationship string.
- [x] Household consumers checked for double-counting — see below.

### Rule 19 — `/m` and iOS

**Neither surface has a counterpart to fix, and both were checked rather than
assumed.**

- `/m` — `resources/mobile/views/PersonalInformation.vue:39` renders
  `profile.spouse.name`, sourced from `users.spouse_id` via
  `UserProfileService::getCompleteProfile()`, i.e. the real link. Its only use of
  `family_members` is `:201`, filtered to `is_dependent`, which excludes the
  spouse. There is **no** family-member management screen, no Edit/Delete, no
  linked-account notice, and no route on which a duplicate spouse row would have
  appeared. `grep -rn "family_member\|spouse" resources/mobile/` — two hits, both
  quoted above. **No `/m` change needed; no `/m` rebuild requested.**
- **iOS** — `ios-native/Fynla/Features/Profile/PersonalInformationView.swift:77-78`
  renders `profile.spouse` from the same source.
  `PersonalInformationModels.swift:14-24` does not decode `family_members` at
  all, and its explicit `CodingKeys` ignore unknown keys, so the additive
  `is_linked_account` field cannot break decoding. **No iOS change needed.**
- The backend fixes (link-gated update/delete, correct email source, required
  spouse email, one linking mechanism) are shared and reach all three surfaces.

### Double-counting — checked, nothing to change

Every household consumer of spouse rows either asks a boolean or filters the
spouse out, so a duplicated spouse never moved a figure:

| Consumer | Why it is immune |
|---|---|
| `IntestacyCalculator.php:29` | `count() > 0` |
| `ProfileCompletenessChecker.php:158-165` | excludes `spouse`; uses `liveSpouseId()` |
| `IHTCalculationService.php:1441` · `HouseholdPlanningService.php:280,917` | `exists()` on descendants |
| `ProtectionPlanService` · `RetirementPlanService` · `InvestmentPlanService` · `EstatePlanService` · `RecommendationPersonaliser` | children only |
| `NonEarnerSpousePensionStrategy.php:178-183` | `first()` |

Two non-financial consumers **did** see two spouses and are fixed by the
reconciliation rather than by a change of their own:
`AdvicePromptBuilder.php:429-430`, which lists every family member into Fyn's
household context, and `DataExportService.php:140`, the subject-access export.

### Deliberately NOT fixed

- **The `name` sync to the linked spouse account is a no-op** — `users` has no
  `name` column; it is an appended accessor over `first_name`/`middle_name`/
  `surname` (`User.php:107-114`, `:343-359`), so `fill()` discards it. Verified
  empirically today. Pre-existing, unrelated to the link predicate → **W-0112**.
- **A partner's email is collected and silently discarded** → **W-0111**.
- **`create_family_member` cannot link a spouse** → **W-0113**.
- `linkExistingSpouse()`'s already-linked branch still does not guarantee the
  reciprocal row exists on the spouse's side. Adoption made
  `createReciprocalFamilyMember()` safe to call there, but adding the call
  changes that branch's behaviour beyond this item's scope. Noted, not done.


---

## Repair executed — 2026-08-21T19:00:44Z

`php artisan family:reconcile-spouse-links --force`, authorised by the team lead once
`persona-passA3` moved off users 20/30 onto the persona household.

Dry-run re-confirmed immediately before the write (unchanged despite the tester having
been through those accounts), `--force` produced identical output, and the state was read
back rather than assumed:

- **`family_members` 25 — the onboarding orphan — `deleted_at = 2026-08-21 19:00:44`.**
  Soft-deleted, so the row is retained exactly as
  `August/August19Updates/spec/deleted-spouse-visibility.md` §1 requires.
- `family_members` 46 unchanged — nothing was folded in, because it already held every
  field the orphan had, so **no value was overwritten**.
- `family_members` 47 (the reciprocal on Arjun's account) and 26 (Meera, the child)
  untouched.
- `users.spouse_id` still reciprocal 20 ↔ 30. Both `SpousePermission` rows intact. Both
  users live, including the premium subscription on 30.

**Live spouse rows for user 20: 1.** The permanent duplicate is gone and the household
reports its own membership correctly.

Idempotency confirmed against real data: an immediate re-run reports nothing to do.

Full account in `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §28.
