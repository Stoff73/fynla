# F-0009 — Batch I: Onboarding spouse orphan

**Owner:** build-lead (agent `fix-batch-I`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0051 · **Raised:** W-0111, W-0112, W-0113

**ID block allocated to this agent: W-0111 – W-0120.** New items take the next number
from that block — never scan the board for a free one (`FORMATS.md`).

**Status at time of writing: W-0051 DONE and handed to `quality-lead`.** Nothing is in
flight. This document is the seed a replacement agent would be started from
(CLAUDE.md Rule 22).

---

## 1. The dispatch, verbatim

> You are `fix-batch-I`. One high-severity item that makes a household permanently
> un-fixable through the user interface.
>
> `workforce/ops/board/W-0051-onboarding-spouse-creates-unmanageable-orphan.md` —
> status `claimed` to you.
>
> Onboarding creates a spouse `FamilyMember` with **no email field**, so no account
> link is possible — then labels that card **"Linked account"** and removes its Edit
> and Delete controls. When the user later links their spouse properly through
> `/settings/family`, they are left with **two undeletable spouse cards, both captioned
> "Account Linked"**, only one of which is real. There is no route out of that state
> through the interface.
>
> **Live reproduction, do not delete:** `users.id 20` (Priya Raman, premium) and
> `users.id 30` (her spouse) are the only live reproduction of this defect. Keep them
> until your fix lands and is verified. `family_members` 25, 26, 46 and 47 and two
> `SpousePermission` rows are on the teardown list for later — not yours to action.
>
> **What is already known and verified — do not re-derive it.** Spouse linking through
> `/settings/family` is **verified GREEN** by the tester: reciprocal `spouse_id`,
> reciprocal `FamilyMember`, and `SpousePermission` **auto-accepted both ways** with no
> manual accept step when the app itself creates the spouse account. **The linking path
> works. The onboarding path is what is broken**, and it is broken by creating a record
> that claims a relationship it never established.
>
> Two related facts that shape the fix: a project memory says any flow completion flips
> `onboarding_completed` and nulls `onboarding_fyn_step`, and gaps belong to
> `PrerequisiteGateService`, not a second onboarding pass. And **Rule 20 is the heart of
> this item** — a spouse relationship is expressed by at least three things (a
> `FamilyMember` row, `users.spouse_id`, a `SpousePermission` row) and onboarding writes
> only the first while the UI reads it as though all three were present. **Find every
> mechanism that creates or displays a spouse relationship and converge them on one.**
> Editing the label so it stops saying "Linked account" is a symptom fix and is not
> acceptable on its own.
>
> **Rules that bind you:** Rule 9 (no acronyms in user-facing text) · Rule 15 (no
> decorative icons; settings and forms are ASK CSJ surfaces, default is no icon) ·
> Rule 19 (web AND `/m`; check whether `/m` renders family members and fix it there too
> or say explicitly that it has no counterpart) · Rule 20 (one behaviour, one home, all
> surfaces and all paths, fresh AND resumed onboarding) · British spelling · **never
> edit `.env` or DB rows to work around a bug** — orphan repair is a migration or an
> artisan command, written and reviewable; `app/Console/Commands/BackfillWillBequests.php`
> is the precedent and it proved idempotency against real data before running.
>
> Rule 6 governs joint assets. `fix-batch-A` consolidated joint-ownership write and
> display logic today into `SharedOwnership` (write) and `ownership.js` (display) —
> read `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md` before touching
> anything that touches ownership, and do not add a fourth ownership mechanism.
> `August/August19Updates/spec/deleted-spouse-visibility.md` §1's retention-versus-
> visibility rule still governs the linked-accounts area.
>
> **Environment.** Test database `laravel_testing_d`:
> `DB_DATABASE=laravel_testing_d ./vendor/bin/pest <paths>`. `pgrep -f "vendor/bin/pest"`
> before running — others are on `_a`, `_b`, `_c`, `_e`. Vitest 5000 ms timeouts under
> parallel load are contention; re-run in isolation, never raise the timeout.
> Migrations: `php artisan migrate --path=...` only, never bare `migrate`, never
> `migrate:fresh`/`migrate:refresh`; five untracked `2026_08_21_*` migrations already
> exist, pick a distinct timestamp. Laravel `:8000` and Vite `:5173` are up. **Do not
> rebuild the `/m` bundle — ask.** **Do not create, delete or provision users**, and do
> not delete users 20 or 30; a throwaway account for a test is a Pest factory, not the
> live database. The formatter deletes an unreferenced `use` at the moment it runs —
> import and first usage in the SAME edit.
>
> **What you do NOT do:** do not commit, do not open a PR, do not deploy (554
> uncommitted paths from five other agents share this tree) · do not browser-verify your
> own work, a persona-tester closes Rule 14's loop independently · no colour or palette
> changes, parked by CSJ.
>
> **Deliverables.** (1) The item fixed at the mechanism level, not the label level;
> existing orphaned households repairable by a reviewable command or migration; targeted
> tests green under `DB_DATABASE=laravel_testing_d`; `pint` clean. (2) W-0051 →
> `status: handoff`, `handoff_to: quality-lead`, append-only working notes with file:line
> evidence, decisions, reasoning, and what you deliberately did not fix. New defects get
> new board items in FORMATS.md shape from **W-0111–W-0120**. (3) Branch document
> `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md`. (4) A concise report:
> the real mechanism-level cause, what changed, test output, `/m` coverage, and anything
> needing a CSJ decision with a recommendation.
>
> **Report the moment you are blocked — do not sit idle.** Rule 22: at ~900k, stop,
> write the handover into your branch document, return it.

### Amendments received since

None.

---

## 2. Prior-art check (charter §11 — six sources, three outcomes)

| Source | Result |
|---|---|
| `registry/capabilities.md` | nothing on spouse linking |
| Code | **`SpouseLinkingService`** — a complete linking service already existed, and its docblock (`:22-25`) already claimed the controller used it. It did not. Plus `User::liveSpouse()`/`liveSpouseId()` as the precedent for "linked, but only while the account is live" |
| Custom artisan commands | none for family data; `BackfillWillBequests` is the repair-command shape |
| Open PRs / in-flight branches | nothing touching `family_members` |
| Vault | `v083/03-AUTH-SECURITY.md`, `Auth.md`, `v083/09-MODULES.md` |
| `.claude/skills`, `.claude/agents` | nothing |

**Outcome: `extend`.** The adequate mechanism existed and was extended; no fourth
mechanism was written. Recorded in the item's frontmatter.

---

## 3. The mechanism-level cause

`family_members.relationship = 'spouse'` carries two different facts at once:

- a **household** fact — this person is my spouse;
- an **account** fact — this row is backed by a linked Fynla account.

The account fact has its own column, `linked_user_id`, plus `users.spouse_id` and
`SpousePermission`. **Every reader branched on the household fact to answer the account
question, and every writer that could not establish a link still wrote the household
fact.** The interface therefore asserted a link nothing had made and withheld Edit and
Delete on the strength of the assertion. Changing the label would have left this intact.

Three mechanisms implemented linking where the codebase believed it had one, and they
had diverged — which is the Rule 20 failure, not a footnote to it:

1. `FamilyMembersController::handleSpouseCreation()` — its own ~250-line copy, forcing
   `marital_status = 'married'` and so demoting a civil partnership.
2. `SpouseLinkingService::linkOrCreateSpouse()` — the canonical service, used only by
   Fyn.
3. `OnboardingService::handleSpouseLinking()` — a third copy which, given an email for
   an account that did not exist yet, created **neither** the account nor the link.

And three writers could produce the unlinked row: `store()` with no email (the rule was
never written, though `messages()` already carried `email.required_if` for it),
`OnboardingChatDirector::createSpouseFamilyMember()` (free text, by design), and
`CoordinatingAgent::handleCreateFamilyMember()` (no email parameter at all).

**Two consequences nobody had reported, both worse than the original defect once Edit
and Delete were restored** — found by reading `update()` and `destroy()` rather than the
symptom:

- `destroy()` branched on `relationship === 'spouse' && $user->spouse_id`. Deleting the
  *unlinked* row would have nulled `spouse_id` on both users, deleted both
  `SpousePermission` rows, and deleted the reciprocal record on the spouse's own
  account — dismantling a real, separate link on behalf of a row that linked nothing.
- `update()` had the same shape: correcting a typo on the unlinked record rewrote the
  real spouse account's date of birth, gender, income and National Insurance number.

Restoring the buttons without fixing those two would have turned an unmanageable record
into a destructive one.

---

## 4. What changed

### One predicate, one home

`FamilyMember::isLinkedAccount()` (`app/Models/FamilyMember.php:129-198`) —
`linked_user_id` present **and** that account still live. Liveness is part of the rule
because `linked_user_id` survives the partner deleting their account for retention
(`deleted-spouse-visibility.md` §1), and pointing a user at an account that no longer
exists strands them exactly as NULL did.

**Appended** — `$appends = ['age', 'is_linked_account']` — so it crosses the API
boundary once and web, `/m` and iOS all read the same boolean. Resolved without lazy
loading via a cached explicit query (the `User::liveSpouse()` shape); rows with a NULL
`linked_user_id`, which is every non-spouse member, never touch the database.

Front end: `resources/js/utils/familyMember.js` — `isLinkedAccount`,
`canManageFamilyMember`, `familyMemberManagementNotice`. Modelled on `ownership.js`.

### Files

| File | Change |
|---|---|
| `app/Models/FamilyMember.php` | `isLinkedAccount()`, `liveLinkedUser()`, appended `is_linked_account` |
| `app/Http/Controllers/Api/FamilyMembersController.php` | `handleSpouseCreation()` delegates to `SpouseLinkingService`; `show()`, `update()`, `destroy()` branch on the link, not the relationship |
| `app/Services/Onboarding/SpouseLinkingService.php` | `createFamilyMemberRow` → `upsertFamilyMemberRow`; `adoptableSpouseRow()`; reciprocal side adopts too; the "already linked" branch upserts instead of returning the first row it finds |
| `app/Services/Onboarding/OnboardingService.php` | `handleSpouseLinking()` delegates; splits the single `name` into parts for the service |
| `app/Services/Onboarding/OnboardingChatDirector.php` | `spouseAck()` stops claiming a link that was not made |
| `app/Services/UserProfile/UserProfileService.php` | the spouse email comes from the linked account, not `users.spouse_id`; the virtual spouse row sets `is_linked_account` explicitly |
| `app/Http/Requests/StoreFamilyMemberRequest.php` | `required_if:relationship,spouse` on `email` — the rule the existing message was waiting for |
| `app/Console/Commands/ReconcileSpouseFamilyLinks.php` | **new** — the repair |
| `resources/js/utils/familyMember.js` | **new** — the front-end home |
| `resources/js/components/Onboarding/steps/FamilyInfoStep.vue` | reads the util |
| `resources/js/components/UserProfile/FamilyMembers.vue` | reads the util |
| `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | spouse email required client-side; the field is add-only |
| `tests/Feature/Api/SpouseFamilyLinkTest.php` | **new** — 14 tests |
| `tests/Feature/Console/ReconcileSpouseFamilyLinksTest.php` | **new** — 8 tests |
| `tests/frontend/utils/familyMember.test.js` | **new** — 12 tests |

### The repair

`php artisan family:reconcile-spouse-links` — dry-run by default, `--force`, `--user=`.
Adopts a lone orphan onto the live link; folds a duplicate into the linked row (filling
gaps only, never overwriting) and soft-deletes it, so the row is retained as §1
requires; leaves a spouse record alone where there is no live link, because that record
is now an ordinary editable one and that is the correct end state. Idempotent by
construction.

Dry-run against the live development database, 2026-08-21:

```
| 20 | pt.throwaway.primary+0821@example.com | record 25 folded into 46 and retired |
Orphan rows adopted onto the live link: 0
Duplicate rows folded into the linked row and retired: 1
Spouse rows left as ordinary records (no live account link): 0
```

**`--force` was deliberately not run** — see §6.

---

## 5. Decisions taken, and why (do not re-litigate)

1. **Onboarding captures the email and links** rather than creating a plain editable
   family member. The form already had the field and the copy; the settings path is
   verified green; `users.spouse_id` is what everything joint keys off; and an
   editable-but-unlinked spouse would still leave the household unlinked while the user
   reasonably believed it was set up. An unlinked spouse row remains **possible** (Fyn
   free-text capture, legacy data) and is now honest, manageable and labelled.
2. **The email field is add-only.** `PUT` cannot link an account and
   `UpdateFamilyMemberRequest` does not accept `email`, so offering the field on an edit
   would have discarded it silently — the same disease. The card's notice tells the user
   to add the spouse again with their email; the upsert adopts the existing record, so
   that advice is true and produces exactly one row.
3. **Behaviour on a genuinely linked row is unchanged.** No Edit, no Delete, and the API
   still unlinks both sides when the linked record itself is deleted. The item asked for
   the gate to read the link, not for the linked-row contract to change.
4. **The trashed-email and self-link checks stay in the controller.** This surface
   answers those with a field-level validation error the form highlights; the service
   raises a collision. They are response shaping, not a second linking mechanism.
5. **Two behaviour changes fall out of the consolidation, both corrections:** a civil
   partnership is no longer demoted to married, and onboarding with an email for a
   not-yet-existing account now creates and links it.

---

## 6. Environment state a replacement depends on

- **`users.id 20` (Priya Raman) and `users.id 30` (Arjun Raman) must stay.** They are
  the only live reproduction. `family_members` 25 (the orphan), 46 (the real link on
  Priya's side) and 47 (the reciprocal on Arjun's side) are intact and untouched.
- **`family:reconcile-spouse-links --force` has NOT been run** and should not be until
  the persona re-verification is done. Row 25 is already editable and deletable through
  the interface as of this fix, so the acceptance is met without destroying the
  evidence. The dry-run output above is what `--force` will do.
- Nothing was committed. Nothing was deployed. No PR. No `/m` rebuild. No migration was
  written — the repair is a command, so no timestamp was claimed from the untracked
  `2026_08_21_*` set.
- No `.env` change, no hand-edited database row, no user created or deleted.

---

## 7. Test evidence

All under `DB_DATABASE=laravel_testing_d`, run after `pgrep -f "vendor/bin/pest"`
showed other batches active on their own databases.

| Suite | Result |
|---|---|
| `tests/Feature/Api/SpouseFamilyLinkTest.php` (new) | **14 passed, 57 assertions** |
| `tests/Feature/Console/ReconcileSpouseFamilyLinksTest.php` (new) | **8 passed, 27 assertions** |
| `tests/Feature/AI/DirectWrite/` + `tests/Feature/Onboarding/` | **478 passed, 1725 assertions** |
| `tests/Feature/UserProfile` + `tests/Feature/Household` + `tests/Unit/Models` + `FamilyMembersControllerTest` + both new suites | **120 passed, 317 assertions** |
| `tests/Unit/Services/Onboarding/SpouseCollisionTest.php`, `tests/Feature/Onboarding/SpouseSkipTest.php`, `tests/Feature/Fyn/FamilyMemberNameTest.php`, `DeletedSpouseVisibilityTest` | **47 passed, 126 assertions** |
| `tests/frontend/utils/familyMember.test.js` (new) + `ownership` + `PolicyFormModal` + `/m` `PersonalInformation.spec.js` | **66 passed** |

`./vendor/bin/pint` clean on every touched PHP file (one autofix on
`UserProfileService.php`, an unused closure `use ($user)` left by the email change).

One test assertion was corrected rather than a fix widened: "editing the linked record
renames the spouse account" fails because `users` has no `name` column. Pre-existing,
raised as **W-0112**, and the test now asserts the four fields that do sync, with a
comment naming the item.

---

## 8. Rule 19 — `/m` and iOS

Checked, not assumed. **Neither surface has a counterpart to fix.**

- **`/m`** — `resources/mobile/views/PersonalInformation.vue:39` renders
  `profile.spouse.name`, sourced from `users.spouse_id`, i.e. the real link. Its only
  use of `family_members` is `:201`, filtered to `is_dependent`, which excludes the
  spouse. There is no family-member management screen, no Edit/Delete, no linked-account
  notice, and no route on which the duplicate spouse row would have appeared.
- **iOS** — `PersonalInformationView.swift:77-78` renders `profile.spouse` from the same
  source. `PersonalInformationModels.swift:14-24` does not decode `family_members` at
  all, and its explicit `CodingKeys` ignore unknown keys, so the additive
  `is_linked_account` field cannot break decoding.
- The backend half of the fix is shared and reaches all three surfaces: one linking
  mechanism, link-gated update and delete, the email sourced from the linked account,
  and the required spouse email.

---

## 9. In flight

**Nothing.** W-0051 is complete and handed to `quality-lead`
(`workforce/ops/handoffs/W-0051/build-to-quality-2026-08-21.md`).

## 10. Not started

Nothing from this dispatch. Three defects found on the way are on the board as
**W-0111** (a partner's email is collected and silently discarded), **W-0112** (the
spouse name sync is a no-op) and **W-0113** (two Fyn tools write a spouse and only one
can link). None is claimed.

## 11. Dead ends already ruled out — do not re-walk

- **"The onboarding form has no email field"** — it does.
  `FamilyInfoStep.vue` embeds the same `FamilyMemberFormModal` the settings page uses,
  and its email block (`:55`) is not gated on `context`. Both the classic wizard and the
  life-stage journey map the `family` step to `FamilyInfoStep` (`OnboardingWizard.vue:421`).
  The orphan is fully explained by the missing validation on both sides, not by a missing
  field: an empty string is stripped from the payload before it is sent
  (`FamilyMemberFormModal.vue` `handleSubmit`), so the request arrived with no `email`
  key and `store()`'s `isset($data['email'])` was false. The W-0051 report's DOM
  observation appears to be a mis-read; **the persona re-verification should confirm the
  field renders** rather than anyone re-deriving the cause.
- **Financial double-counting from the duplicate spouse** — there is none. Every
  household consumer either asks a boolean or filters the spouse out; the table is in
  W-0051's working notes. Two non-financial consumers did see two spouses
  (`AdvicePromptBuilder.php:429-430`, `DataExportService.php:140`) and are fixed by the
  reconciliation, not by a change of their own.
- **The dates were not stored a day early** — already cleared in the item; Eloquent's
  `date` cast serialising to UTC. Not re-chased.

---

# Batch I, second dispatch — W-0113, W-0111, W-0112 (+ W-0114 found on the way)

**Status: all four DONE and handed to `quality-lead`.** Nothing in flight.

## 12. The dispatch, verbatim

> **1. Report, briefly.** what the mechanism-level cause actually was, whether the fix
> converged the existing spouse mechanisms onto one or added a service beside them, how
> existing orphaned households get repaired, your test evidence, and what `/m` coverage
> you did or did not reach.
>
> **The one I most want to hear about is W-0113 — "two Fyn tools write a spouse and only
> one can link".** That is a golden-rule finding and possibly the actual root cause of
> W-0051 rather than a sibling of it. If onboarding, `create_family_member` and the
> settings path are three ways of expressing one relationship, say so plainly, because
> it changes whether W-0051 is fixed or merely patched at one entrance.
>
> **2. Your next batch is your own three findings — W-0111, W-0112, W-0113.** Claim all
> three on the board at dispatch (`owner`, `claimed`, `claimed_by: fix-batch-I`) before
> you start, not after.
>
> Take them in this order, because it is cause-before-symptom:
>
> - **W-0113 first** — two Fyn tools writing a spouse where only one can link. If this
>   is the shared mechanism, fixing it may collapse the other two. **Rule 20 governs the
>   acceptance: converging them onto one source that all callers read is PART of the
>   fix, not a follow-up.** Editing both tools to behave the same way is explicitly a
>   violation, not a fix. Note this is now the fourth same-shape finding today across
>   four different modules, so do not treat it as a local tidy-up.
> - **W-0111** — a Partner's email collected "to create or link their account" and then
>   silently discarded. That is the **silent-discard disease** the board is full of
>   today: policy end dates validated and dropped, a typed holding value overwritten, a
>   stated ownership share of 100 rewritten to 50. Same class, and worse here because
>   the interface **tells the user what the field is for** before throwing it away.
>   Whatever you do, the promise the copy makes and what the code does must end up in
>   agreement — if the email genuinely cannot link at that point, the copy must stop
>   saying it will.
> - **W-0112** — editing a linked spouse's name never reaching `users.name` on their
>   account. Check whether this is the same broken edge as W-0113 rather than a third
>   thing.
>
> **Everything from your original dispatch still binds**, and two clauses matter more
> now: `laravel_testing_d` and `pgrep -f "vendor/bin/pest"` before every run (four other
> agents are testing; contention gives 0-assertion failures and deadlocks that look
> exactly like code failures); **do not create, delete or provision users, and do not
> delete users 20 or 30** — Arjun (30) is now premium with an active subscription and
> `persona-passA3` is driving both accounts in a live browser right now, generating a
> mirror will onto Arjun, adding gifts and deleting bequests. If you need those accounts
> in a particular state, say so and I will sequence you against the tester.
>
> Migrations `--path=` only, no bare migrate, no destructive migrate variants. No
> commits, no PR, no deploy, no `/m` bundle rebuild — ask. No browser verification of
> your own work. New defects from **W-0111–W-0120**, seven numbers left. Report the
> moment you are blocked. Rule 22 at ~900k.

## 13. Answers to the two questions asked

**Converged or added beside?** Converged. `SpouseLinkingService` already existed and its
own docblock already claimed the controller used it — it did not. Three implementations
became one; nothing was written beside them.

**Is W-0113 the root cause of W-0051?** No — a sibling, and W-0051 is fixed rather than
patched at one entrance. They sit on opposite sides of the boundary: W-0051's dead end
came from the **read** side (an unlinked row displayed as linked and stripped of its
controls), fixed at the one place every surface reads, which is entrance-independent.
W-0113 is a remaining **write**-side divergence. Full reasoning in W-0113's working
notes.

## 14. What changed, by item

| Item | Mechanism-level change |
|---|---|
| **W-0113** | `CoordinatingAgent::linkSpouseAccount()` — THE one path from a Fyn tool to a spouse link, entering `SpouseLinkingService`. `create_family_member` and `capture_spouse_details` both call it; each keeps its own input contract and response shape, neither keeps its own idea of what linking means. Tool schemas v2 on both providers with an `email` parameter. |
| **W-0112** | `SpouseLinkingService::FAMILY_MEMBER_TO_USER_COLUMNS` + `userAttributesFrom()` — one declared correspondence between the two tables. `last_name → surname` is why it needs a home. The controller sync, spouse-user creation and the reciprocal row all read it. |
| **W-0111** | Answered: a partner does not get an account. Email is spouse-only in the modal and `prohibited_unless:relationship,spouse` in the request — nothing accepts a field it intends to discard. |
| **W-0114** | `FamilyMember::RELATIONSHIP_ALIASES` / `resolveRelationship()` / `composeRelationshipNotes()` — the model owns the column, so it owns the translation. `CoordinatingAgent`'s inline `match` pair deleted. |

## 15. Two bugs nobody reported, found by a drift guard

The W-0112 test pinning the declared map against what creation actually writes **failed
on its first run**:

1. A spouse created with a **middle name** got it on their family-member card and not on
   their own account — the creation list was hand-written and had never included it.
2. `createReciprocalFamilyMember()` built the spouse's view of their partner by
   splitting the **derived** display name on spaces, losing the middle name and
   mis-splitting double-barrelled surnames — for the one record the spouse sees of their
   partner. `name` is derived *from* those columns, so splitting it back apart was lossy
   by construction.

Both fixed. Recorded because the argument for writing that kind of test is that it finds
what nobody thought to look for.

## 16. Test evidence

All under `DB_DATABASE=laravel_testing_d`, `pgrep`-checked before each run.

| Suite | Result |
|---|---|
| `tests/Feature/AI/DirectWrite/CreateFamilyMemberTest.php` | **12 passed** — includes the two-entrances-one-outcome assertion |
| `tests/Feature/Api/SpouseFamilyLinkTest.php` | **17 passed** |
| `tests/Feature/Api/FamilyMemberRelationshipAliasTest.php` (new) | **10 passed** |
| `tests/Feature/Console/ReconcileSpouseFamilyLinksTest.php` | **8 passed** |
| Batch sweep: Api + DirectWrite + Onboarding + UserProfile + Fyn + Unit/Onboarding + both golden masters | see §18 |
| Frontend: `familyMember.test.js` + `/m` `PersonalInformation.spec.js` | **16 passed** |

`php artisan fyn:procedural:validate` — 101 procedures, `create_family_member v2` active
on both providers. `pint` clean on every touched file.

## 17. Collision reported, not absorbed — golden-master re-record

W-0113's schema change forced a tool-catalogue golden-master re-record, and the capture
is **whole-catalogue**. At capture time the tree also held uncommitted corpus edits to
`fyn-memory/procedural/tool_schema/savings/create_pension.{md,xai.md}` that were not yet
in the fixtures, so those are now pinned in my capture. Reported to the team lead
immediately, with the re-run command.

Not reverted, deliberately: reverting leaves the gate RED for everyone, and stashing
another agent's file to capture around them means touching their working tree. As
captured, the fixtures match the corpus on disk and the gate is green and truthful.

**Anyone editing the tool_schema corpus this session hits the same collision** — that
gate is global and the capture is all-or-nothing. Cheapest fix is to nominate one agent
to capture last, after the corpus settles.

## 18. Environment state

- **Users 20 and 30 untouched.** No user created, deleted or provisioned. `passA3` was
  driving both accounts throughout; I never read or wrote them after the W-0051 dry-run.
- **`family:reconcile-spouse-links --force` still NOT run.** Waiting on the team lead to
  sequence it after passA3 clears 20/30.
- No migration written. **W-0114 deliberately did not add enum values** — see its
  working notes for the blast radius that decision avoids.
- Nothing committed, no PR, no deploy, no `/m` rebuild.

## 19. In flight

**Nothing.**

## 20. Not started

Nothing from this dispatch. **W-0114 leaves one open question for CSJ**: should `partner`
and `step_child` become real enum values? Not taken here because `step_child` is stored
as `child` today and every `where('relationship','child')` across estate, protection,
plans and intestacy would silently stop counting step-children. Six numbers left in the
block (W-0115–W-0120).

## 21. Dead ends — do not re-walk

- **`capture_spouse_details` is not in `getTools()`.** It lives in the `onboarding`
  group, reachable only via `toolsListOverride` (grouped extract) or by name through
  `allowedToolsOverride`, which `HasAiChat` widens with `onboardingExtractionTools()`
  before filtering. That widening is why both tools are live on the inline-capture path.
- **`users.name` is not writable and fails silently.** `isFillable('name') === false`,
  `Schema::hasColumn('users','name') === false`, and an update carrying it succeeds
  while dropping it. Probed directly; do not re-derive.
- **`partner`/`step_child` 500 rather than degrade.** `strict => true` on the MySQL
  connection, so 1265 is fatal. Probed through the real endpoint.

---

# Batch I, third dispatch — the spec miss, and W-0114's amendment

## 22. The two findings the team lead asked to be stated in these terms

**On W-0051's `update()` and `destroy()`.** Both branched on
`relationship === 'spouse' && $user->spouse_id` rather than on the link the row
actually carries. The item asked for Edit and Delete to be restored to the orphan.
**Restoring them without fixing those two would have turned an unmanageable record into
a destructive one:**

- deleting the *unlinked* row would have nulled `spouse_id` on **both** users, deleted
  **both** `SpousePermission` rows, and deleted the reciprocal record on the spouse's own
  account — dismantling a real, separate link on behalf of a row that linked nothing;
- editing it would have rewritten the real spouse account's date of birth, gender, income
  and National Insurance number.

Neither was in the defect report. **It is a defect report that would have been closed
green and shipped a worse bug than the one it described** — which is the argument for
reading the enforcing code rather than the symptom, every time.

**On W-0112's drift guard.** I added a test pinning the declared field map against what
creation actually writes, expecting it to pass. It failed on its first run and produced
two real bugs nobody had reported: a spouse's `middle_name` never reached their own
account, and `createReciprocalFamilyMember()` was building the spouse's view of their
partner by splitting the **derived** display name on spaces — losing middle names and
mis-splitting double-barrelled surnames. **A guard that catches something the day it is
written is worth more than the fix it was guarding.**

## 23. A miss of mine, recorded rather than smoothed over

`fix-batch-E` found **three red tests in my files**, and I did not.
`resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` covers
`FamilyMembers.vue` — the component whose Edit/Delete gate I had just rewritten — and I
never ran it.

**Why I missed it.** I searched `tests/frontend/` for specs touching the family
components and found none. The Vue component specs do not live there; they live beside
the components, under `resources/js/components/__tests__/`. My search scoped to one of
the two locations and I treated the empty result as "no coverage exists" rather than as
"I have not found the coverage".

**The lesson worth keeping**, because it nearly cost the claim: the spec is the front-end
half of the `is_linked_account` predicate. Had it stayed red, "W-0051 is fixed rather
than patched" would not have stood up — the predicate's own tests would have been
failing while I argued it was entrance-independent.

**Search both locations for a Vue component's tests:**

```
grep -rl "<ComponentName>" resources tests | grep -E "\.(spec|test)\.js$"
```

**What was actually wrong.** All three failures were the intended behaviour meeting a
spec that encoded the old rule. The fixture spouse had no `linked_user_id` and no
`is_linked_account` — it described the W-0051 orphan — while the tests around it asserted
the behaviour of a *linked* account (no Edit, no Delete, and "the first Edit button
belongs to the child"). Once the component read the link instead of the relationship, the
unlinked fixture spouse correctly became manageable and the index-based assertions moved.

Fixed honestly rather than by loosening assertions:

- the spouse fixture now carries `linked_user_id: 30` and `is_linked_account: true`, so
  it describes the linked spouse the tests always meant;
- `offers edit and delete actions only for editable non-spouse members` is renamed
  `withholds edit and delete from a linked account, not from a relationship` — the
  relationship was never the rule, and leaving the old name would re-encode the thing
  W-0051 removed;
- four new tests cover the case the spec never had: an unlinked spouse gets Edit and
  Delete, shows no "Account Linked" badge, carries the not-linked notice, and opens in
  edit mode.

**18 tests pass**, up from 14. Every spec touching the components I changed now runs:
`resources/js/components/__tests__/UserProfile/` (8 files) plus
`tests/frontend/utils/familyMember.test.js` and the Protection modal — **88 passed**.

## 24. W-0114 amendment — the alias is stored, never displayed

My first cut mapped the relationship for storage and let the card render the stored
value. I called that "consistent but not lovely". The team lead was right that this
understates it: **the application would be telling a user that their partner is a
dependent** — a false statement about their household, in the software's own voice, the
same class of thing removed from wills and powers of attorney today.

So: **alias for the column, plus one additive nullable column holding what the user
actually chose**, and the display reads that.

`family_members.stated_relationship` — nullable, 32 chars, display only.

- The enum keeps its four values, so every existing `where('relationship', 'child')`
  across estate, protection, plans, intestacy and the shared-children logic behaves
  exactly as before.
- **NULL means "as stated equals as stored"**, so there is no backfill and every existing
  row is already correct.
- **Nothing branches on it.** That is what keeps this additive rather than semantic, and
  it means widening the enum later makes this column redundant rather than wrong.
- **Not `notes`.** That column is the user's own free text, and a system fact parked
  inside it gets edited away by the person it describes. The mapping note still goes to
  `notes` for a human reading the record; the machine fact has its own column.

One home on each side: `FamilyMember::getDisplayRelationshipAttribute()`, appended as
`display_relationship` on every serialisation, and `familyMemberRelationshipLabel()` in
`resources/js/utils/familyMember.js`.

**There were four formatters for this one thing.** `FamilyMembers.vue` used
`replace('_', ' ')`; `FamilyInfoStep.vue` used a hardcoded label map;
`SaveAccountModal.vue` uses a different map that says "Dependant";
`RiskFactorDetailPage.vue` uses replace-plus-title-case. The first two are deleted and
converged. The other two are raised as **W-0115** rather than reached into — Risk and
Savings belong to other batches and I could not verify the Risk payload's shape end to
end. `RiskFactorDetailPage` can still print "Other Dependent" for a partner flagged as a
dependant, which is a real if narrow instance of the same false statement.

**Migration:** `2026_08_21_200000_add_stated_relationship_to_family_members_table.php`,
run with `--path=`. Additive, so no reseed was needed and none was run.

**16 tests** on `FamilyMemberRelationshipAliasTest`, including one asserting the partner
label does not contain the word "dependent" — the specific harm, pinned as the assertion.

## 25. Decisions carried in from the team lead

1. **W-0111 — partner linking is unsupported BY DESIGN, not unbuilt.** Recorded on the
   item in those words, with the reasoning, so nobody re-adds the field believing it was
   an oversight. If CSJ wants partner accounts it is a feature request starting from the
   tax-recognition question, not from this form field.
2. **The spouse email requirement stays**, and stays cheap to reverse: only `required_if`
   and the modal check come out; the predicate work stands.
3. **`family:reconcile-spouse-links --force` still NOT run.** Awaiting the lead's word.
   The tester has been re-scoped onto David (16) and Sarah (17), so 20 and 30 are
   freeing up, but nothing has been confirmed. Dry-run result banked: one change, record
   25 folded into 46 and retired.
4. **No tool-schema golden-master capture in this dispatch.** The W-0113 capture happened
   before that constraint was issued and was reported at the time. Nothing here changes a
   tool schema — `stated_relationship` is persisted by the handler, not declared in the
   `create_family_member` parameters — so no re-capture was needed or done.

## 26. Consent work — noted, not touched

The team lead flagged that the consent record is being split into `cookies_analytics` and
`cookies_affiliate`, and that **W-0155** covers there being no user-reachable way to
withdraw after accepting. Not mine to build.

The connection to W-0111 is real and worth writing down, because it is the same principle
twice: **the record must be able to answer what the user was actually told and what they
actually chose.** A single `cookies = true` row cannot say which of two materially
different activities the visitor agreed to. A form field labelled "Used to create or link
their account" that creates and links nothing cannot say what the user was promised
either. Both are fixed by making the record and the copy tell the same story — and
`stated_relationship` is the third instance in this batch: the row now records what the
user chose, not only what the schema could hold.

---

# Batch I — the reconcile, executed

## 27. A test that pins a bug is worse than no test

The team lead asked for this stated plainly, and it deserves to be.

`tests/Feature/AI/DirectWrite/CreateFamilyMemberTest.php` contained a test called
**`create_family_member persists a spouse without email`**. It created a spouse through
the tool, asserted `success` was true and that a `family_members` row existed, and passed
— on a household with `linked_user_id` NULL, `users.spouse_id` NULL, and no account
anywhere. It asserted W-0113 as correct behaviour.

**A test that pins a bug is worse than no test**, because it makes the bug look
deliberate to everyone who reads it afterwards. Anyone auditing this file would have seen
a named, green, apparently intentional assertion that a spouse needs no email — and
concluded the unlinked household was a design choice rather than a defect. No-coverage
announces itself as a gap; wrong-coverage announces itself as a decision.

It is now `refuses a spouse with no email rather than writing an unlinked row`, with a
docblock recording what it used to assert and why the tool was wrong rather than the
assertion. The same applies to the front-end spec renamed in §23: leaving
`offers edit and delete actions only for editable non-spouse members` on a suite that no
longer works that way would have re-encoded, in a test name, exactly the rule W-0051
removed.

## 28. `family:reconcile-spouse-links --force` — run, with the actual result

Authorised by the team lead once `persona-passA3` moved to the persona household
(David 16, Sarah 17). Sequence followed as instructed: state captured, dry-run re-run to
confirm it still said one change, then `--force`, then the real state read back.

**Dry-run, re-confirmed immediately before the write** — unchanged from hours earlier
despite the tester having been through those accounts:

```
| 20 | pt.throwaway.primary+0821@example.com | record 25 folded into 46 and retired |
Orphan rows adopted onto the live link: 0
Duplicate rows folded into the linked row and retired: 1
Spouse rows left as ordinary records (no live account link): 0
```

**`--force` output — identical**, which is the property the dry-run exists to give:

```
Reconciling.
| 20 | pt.throwaway.primary+0821@example.com | record 25 folded into 46 and retired |
Orphan rows adopted onto the live link: 0
Duplicate rows folded into the linked row and retired: 1
Spouse rows left as ordinary records (no live account link): 0
```

**The actual database state afterwards, read back rather than assumed:**

| Row | Before | After |
|---|---|---|
| `family_members` 25 — the onboarding orphan | live, `linked_user_id` NULL | **`deleted_at = 2026-08-21 19:00:44`** — retained, not destroyed |
| `family_members` 46 — the real link | `linked_user_id = 30`, date of birth 1977-06-02 | unchanged; nothing was folded in, because it already held every field the orphan had |
| `family_members` 47 — Arjun's reciprocal | `linked_user_id = 20` | untouched |
| `family_members` 26 — Meera, the child | live | untouched |
| `users.spouse_id` | 20 → 30, 30 → 20 | **unchanged, still reciprocal** |
| `SpousePermission` | 2 rows | **2 rows** |
| `users` 20 and 30 | live | **both live** |

**Live spouse rows for user 20: 1.** The duplicate card is gone from the interface, and
the household reports its own membership correctly for the first time since the defect
was created.

**Idempotency confirmed on real data, not only in tests.** Re-running the dry-run
immediately afterwards reports nothing to do:

```
Orphan rows adopted onto the live link: 0
Duplicate rows folded into the linked row and retired: 0
Spouse rows left as ordinary records (no live account link): 0
```

Worth noting what did **not** happen, because it is the part that could have gone wrong
silently: the fold copies only fields the keeper is missing, and row 46 was missing none,
so **no value was overwritten**. Had the command been written to merge rather than
fill-gaps, it would have replaced the date of birth the user confirmed through the
linking form with the one from the older, unverified row — and the output would have
looked identical either way.

**Nothing else in the database was touched.** No user created, deleted or provisioned.
Users 20 and 30 are intact, including the premium subscription provisioned on 30.

---

# Batch I — W-0115, and why "the command reported success" is not evidence

## 29. Fill-gaps versus merge: two implementations, identical output, one corrupting data

The team lead asked for this in these words, and it is the reason a repair command needs
reading rather than trusting.

`ReconcileSpouseFamilyLinks::foldInto()` copies **only fields the keeper is missing**. On
the live repair, `family_members` 46 was missing none — so nothing was copied, and **no
value was overwritten**.

**Had it been written to merge rather than fill-gaps, it would have replaced the date of
birth the user confirmed through the linking form with the one from the older, unverified
orphan — and the command output would have looked identical either way.**

```
Duplicate rows folded into the linked row and retired: 1
```

That line is printed by both implementations. It is printed on a correct run and on a
silently corrupting one. **Two implementations, identical output, one of them quietly
overwriting a user's confirmed data.** The next person writing a repair command will
reach for merge without thinking — it reads as the more helpful option — and the reason
not to is invisible in everything the command says about itself.

So: **"the command reported success" is not evidence.** The evidence is the row, read
back afterwards, compared against what it held before. That is why §28 records a
before/after table rather than the command's own summary.

The same argument applies to the idempotency claim. A repair command proved idempotent
only by its own test suite has not been proved idempotent — the fixtures are chosen by
the person who wrote the logic, and they share its assumptions. **It was re-run against
the real database immediately after the write and reported nothing to do.** That is the
half that counts.

## 30. W-0115 — `SaveAccountModal.vue` done, Risk stopped at the boundary

**Half one is complete.** Details in the item's working notes. Two things worth carrying
here:

- **The casing trap the acceptance predicted, met in the first file.** The label helper
  returns lowercase because the family cards apply `capitalize` themselves. This call
  site renders into an `<option>` inside a `<select>`, which does not reliably take that
  styling — converging it naively would have shipped "(child)" where the user previously
  read "(Child)". `familyMemberRelationshipTitle()` exists for that: one home for the
  words, one for the casing rule, surfaces choose the form they need.
- **Both spec locations were searched** before concluding no test covers the file —
  `tests/frontend/` and `resources/js/components/__tests__/`. That is §23's lesson
  applied rather than restated.

**Half two is stopped, deliberately, and the reason matters more than the fix.**

`RiskFactorDetailPage.vue` needs a **payload** change, not a formatter change.
`AutoRiskCalculator::calculateDependantsFactor()` hand-builds the list with
`->get(['first_name', 'relationship'])` and maps to `['name', 'relationship']`. It is not
a `FamilyMember` serialisation, so no appended attribute reaches the client, and the
partial select does not even load `stated_relationship`.

**Converging the Vue formatter alone would change nothing** — the client would fall back
to `relationship` and still print "Other Dependent" for a partner. **It would look like a
fix and not be one**, which is precisely how a second display mechanism gets created
while closing an item about there being four.

### The coupling check the team lead made a condition

Instruction: *"if it turns out the risk factors are built from anything the estate or
retirement services also feed, stop there and I will sequence it properly."*

Checked, and the answer is **yes at file level, no at method level**:

| Scope | Coupling |
|---|---|
| `AutoRiskCalculator` (the class) | `NetWorthService` injected; `PensionStore` called directly at `:130` — **a retirement store, feeding `calculateCapacityForLoss`**. `NetWorthService` itself pulls `PensionStore`, `PropertyStore` and `CrossModuleAssetAggregator`. |
| `calculateDependantsFactor()` (the method being changed) | **`FamilyMember` only.** No `NetWorthService`, no `PensionStore`, no estate service, nothing cross-module. |

So the literal stop condition is met — a risk factor *is* built from a retirement store —
even though it is not the factor being edited and the change cannot reach it. **Stopped
and reported rather than deciding the technicality did not apply**, because the whole
point of the condition was that the team lead sequences this against `iht-audit`, not
that I assess my own blast radius.

The change, held ready and not applied:

```php
// app/Services/Risk/AutoRiskCalculator.php, calculateDependantsFactor()
->get(['first_name', 'relationship', 'stated_relationship'])   // load the column
...
'relationship' => $d->display_relationship,                     // send what was chosen
```

Two lines. The size was never the question; the location was.

---

# Batch I — W-0115 closed, and the argument against consolidating on the majority

## 31. All four formatters are gone

`grep -rn "formatRelationship" resources/js resources/mobile` returns nothing.

| Formatter | Was | Now |
|---|---|---|
| `FamilyMembers.vue` | `replace('_', ' ')` | shared helper |
| `FamilyInfoStep.vue` | hardcoded label map | shared helper |
| `SaveAccountModal.vue` | a different map, saying "Dependant", with a dead `step_child` branch | shared helper (title form) |
| `RiskFactorDetailPage.vue` | replace + title case | shared helper (title form) |

### The Risk half needed the service, not the component

`AutoRiskCalculator::calculateDependantsFactor()` hand-builds its rows, so:

- `stated_relationship` was added to the select **because `display_relationship`
  is computed from it** — a partial select without it would have silently fallen
  back to the stored enum and printed "Other Dependent" for someone's partner
  regardless of what the component did;
- `display_relationship` was added **alongside** `relationship` rather than
  replacing it, so anything that needs to branch keeps the raw value while the
  client renders what the user chose.

Converging the component alone would have looked like a fix and not been one.
That distinction was the whole reason for stopping to ask rather than proceeding.

## 32. Majority is not a source of truth; it is a headcount

Of the four formatters, the ONE in the Savings modal had the spelling **right** —
"Dependant", the British noun — and the family cards, the onboarding step and the
Risk page all had it **wrong**.

**Consolidating on what most call sites did would have propagated the error into
the only place that was correct**, and it would have looked like a tidy-up while
doing it. Three-to-one is not evidence. The rule in `CLAUDE.md` is evidence:
user-facing text is British, "dependent" is the adjective, the noun is
"dependant".

Recorded on `FamilyMember::RELATIONSHIP_WORDS` itself, where the next person
consolidating something will read it, rather than only here.

**The wording lives in the backend accessor**, so web, `/m` and native inherit
one spelling without a second edit — which is the entire argument for computing
display strings on the server rather than formatting them per client. The column
keeps `other_dependent` because it is code.

`FamilyMemberFormModal.vue:36` — the dropdown label the user picks from — went
with it. Leaving the input saying "Other Dependent" while the card said "Other
Dependant" would have created a fresh inconsistency in the act of removing one.

## 33. The front end deliberately does not know the words

`familyMemberRelationshipLabel()` passes the server value through and falls back
to the column's own words. **There is no wording map on the client**, and a test
pins the fallback returning "other dependent" — *precisely because the fallback is
not supposed to know better*. Adding a client-side map would have recreated, in
the same batch, the copy-in-lockstep failure the batch removed. Documented in the
util so the next person does not "fix" it.

## 34. Instructions observed

- **`persona-passA3` was not told anything.** The team lead is handling it; a
  label changing under a tester mid-entry would read as a defect.
- **Screenshot-bearing reports already filed were not amended.** They use the old
  spelling and were accurate when taken.

## 35. Evidence

| Suite | Result |
|---|---|
| `tests/Feature/Api/FamilyMemberRelationshipAliasTest.php` | **18 passed** — including one that drives the real `AutoRiskCalculator` and asserts a partner reaches the risk payload as "partner" and **not** containing "dependent" |
| `tests/Feature/Risk/`, `tests/Unit/Services/Risk/`, `AutoRiskCalculatorEnhancementTest`, plus the family suites | **110 passed, 0 failures** |
| Frontend: `tests/frontend/utils/` + `resources/js/components/__tests__/UserProfile/` | **129 passed** across 11 files |

`pint` clean. No spec exists for `RiskFactorDetailPage` or `SaveAccountModal` —
**both** spec locations searched each time, per §23.
