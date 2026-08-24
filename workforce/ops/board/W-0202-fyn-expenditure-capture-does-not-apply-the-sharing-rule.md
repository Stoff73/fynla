---
id: W-0202
title: Fyn's expenditure capture writes one account at 100% regardless of the household's declared sharing mode — the same 100/0 split W-0190 fixed, through a different door
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T03:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0190, W-0011]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `cycle2-ownership` while fixing **W-0190**. **Deliberately not folded into
that fix** — the reason is in Acceptance below and it is a decision, not an oversight.

### The gap

W-0190 gave the household expenditure sharing rule one home
(`app/Support/SharedExpenditure.php`) and routed both write paths through it:

| Path | Applies the declared sharing rule |
|---|---|
| `OnboardingService::processExpenditure()` | yes (always did) |
| `UserProfileController::updateExpenditure()` / `updateSpouseExpenditure()` | yes (fixed under W-0190) |
| **`CoordinatingAgent::handleSetExpenditure()` — Fyn** | **no** |

`CoordinatingAgent.php:5183-5225` writes the categories to the acting user at 100%,
totals them, mirrors the total into `ExpenditureProfile`, and **never touches the
spouse**. On a household declaring `joint`, a Fyn capture therefore reproduces exactly
the shape W-0190 reports: the whole of a shared cost on one account, nothing on the
other, under a table that says "Joint (50/50) expenditure".

Fyn is reachable from web, `/m` and native, and on `/m` it is the **only** way to edit
expenditure — `resources/mobile/views/Expenditure.vue` is read-only and hands off to Fyn
via `contextualRequest` with `action: 'edit'`. So on `/m` this is the only door.

### Why it was not fixed with W-0190

Three reasons, all of which need a decision rather than a guess:

1. **Fyn's input is genuinely ambiguous where the form's is not.** The expenditure form
   declares in its own subheading that the figures are the household's. A user telling
   Fyn "our food shopping is £600" may mean the household's or their own. Halving
   silently would be wrong half the time, and would look identical to a bug.
2. **Its field list differs from both the others.** `handleSetExpenditure` covers
   `rent`, `utilities` and `charitable_donations`, which `SharedExpenditure::SHARED_FIELDS`
   does not, and omits `regular_savings`, which it does. Routing it through would also
   change **which fields divide** — a second behaviour change riding on the first.
3. ~~**`CoordinatingAgent.php` is 6,500 lines and was modified by another agent** in the
   shared tree at the time.~~ **Stale** — that agent is terminated and the file is free
   (team-lead, 2026-08-22). Reasons 1 and 2 stand.

## DECIDED — team-lead, 2026-08-22

**Use the household's declared mode. Do not halve unconditionally and do not leave
100/0.**

| Declared mode | Fyn's figure means | Behaviour |
|---|---|---|
| `joint` | the household's spending | halve and mirror, same as the form |
| `separate` | the speaker's own | write to the one account at 100% |
| none recorded | unknown | **Fyn asks** — an unanswered question must not become an answer |

The reasoning, recorded because it is the part that generalises: an ambiguous input
must not be resolved by an assumption the user never made — but **this household has
already told us**. `use_separate_expenditure` is a deliberate declaration, so applying
it is not a guess. It is what the expenditure form's subheading does explicitly.

**NOT to be built this cycle.** A conversational-flow change is not a thing to start at
the end of one. Left `queued`.

---

## Reachability check — done before building, as instructed. Read this before implementing.

**The mode is reachable. The third branch is not.**

`handleSetExpenditure(array $input, User $user, bool $isPreview)` has `$user` in scope
and `expenditure_sharing_mode` is a column on `users`, so
`$user->expenditure_sharing_mode` needs **no plumbing at all** — no threading through
the 6,500-line file, no signature change. The first two branches are buildable as
written.

**But `users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT
'joint'`.** It cannot be null. Every user row has had a mode since the moment it was
created. **The "no mode recorded → Fyn asks" branch can never fire**, because there is
no such state to detect.

**And the consequence is sharper than a dead branch.** The default means
**joint-by-declaration and joint-by-never-having-been-asked are indistinguishable**. A
married user who has never opened the expenditure form, never seen the toggle and never
formed a view reads as having declared `joint` — identically to one who chose it.

Live distribution on the dev database, which shows the shape rather than a
counter-example: **19 users, all `joint`, none `separate`, 12 with a spouse.** Nobody
has ever chosen `separate`. Every value is the default.

So on the decision's own terms — *"an unanswered question must not become an answer"* —
**the schema already turned the unanswered question into an answer, before Fyn ever
sees it.** Reading the column and calling it a declaration would inherit that, and the
"ask" branch that was meant to prevent exactly this is unreachable.

**This does NOT invalidate the decision; it identifies what has to be built first.**
Three options, for team-lead / CSJ:

1. **Make the unanswered state expressible** — the column becomes nullable, or a
   companion `expenditure_sharing_mode_declared_at` records that someone chose. Then
   the third branch works as decided and Fyn asks the users who have never said. This
   is the option that matches the decision as written.
2. **Treat the default as a declaration and disclose it** — Fyn states what it is doing
   ("I've split that across you both, as your household is set to share expenditure"),
   so the assumption is visible at the point it is made rather than silent. Cheaper,
   and consistent with the form, which shows "Joint (50/50) expenditure" on screen while
   the user types.
3. **Have Fyn ask whenever the household has a spouse and the figure is a category
   total**, ignoring the column for this purpose. Most conversational, most friction.

**Note this also touches the shipped W-0190 fix, in the form's favour.** The profile
path now halves for any married user whose mode is the default. That is defensible
where Fyn's would not be, because **the form discloses it**: the subheading reads
"Joint (50/50) expenditure" and the toggle is visible and set, at the moment of entry.
Fyn has no equivalent disclosure. The difference between the two surfaces is disclosure,
not arithmetic.

---

### Acceptance

1. **The unanswered state is made expressible, or the default is disclosed** — see the
   three options above. **This must be settled first**; branch three of the decision is
   unbuildable until it is.
2. `handleSetExpenditure` composes from `SharedExpenditure` — Rule 20, one home, and it
   is the last path that does not.
3. **Do not change which fields divide while changing which path divides.** The lists
   differ — `handleSetExpenditure` covers `rent`, `utilities` and `charitable_donations`
   which `SharedExpenditure::SHARED_FIELDS` does not, and omits `regular_savings` which
   it does. If routing forces both, that is two behaviour changes and it splits into two
   items. (`rent` and `utilities` are household costs by nature, so the reconciliation
   is worth doing — separately.)
4. Verified from Fyn on web AND `/m`, on both accounts of a linked household. **`/m` is
   the one that matters**: its expenditure screen is read-only, so Fyn is the only
   expenditure edit door there.

---

## Update — 2026-08-23, build-lead (`fix-cycle4-goals-expenditure`)

**Criterion 2's mechanism now exists, and criterion 3's obstacle is gone. Criterion 1 is
still the blocker, and this item stays `queued`.**

W-0412 built `app/Services/Expenditure/HouseholdExpenditureWriter` — one household payload
in, both accounts' shares derived from it and written in one transaction, both
`ExpenditureProfile` rows synced, both caches invalidated. `UserProfileController`'s two
expenditure endpoints route through it now.

- **Criterion 2** becomes a single call per path:
  `app(HouseholdExpenditureWriter::class)->write($user, $updateData);` at
  `CoordinatingAgent::handleSetExpenditure`, and the same at the `update_profile`
  `section: expenditure` simple-total path — **which is a fourth door this item does not
  currently name, and has the identical shape.**
- **Criterion 3 is resolved without reconciling the field lists.** The writer mirrors to
  the spouse **only** the fields `SharedExpenditure` actually divides, so `rent`,
  `utilities` and `charitable_donations` stay whole on the acting row and the household
  still sums correctly. Routing Fyn therefore does **not** force a second behaviour change,
  and the "two items" split this criterion anticipated is not needed. (`rent` and
  `utilities` never persisting at all from the form is separately raised as **W-0413**.)
- **One extra step the implementation will need, not currently in this item.**
  `handleSetExpenditure` recomposes the monthly total from every **stored** category, and
  under a shared household those stored values are **halves**. They must be read back to
  household scale (`÷ SharedExpenditure::JOINT_SHARE`) before the total is recomposed, or
  one named category arrives at household scale and the twenty-one untouched ones at half
  scale and the total is neither figure.

**Criterion 1 remains unsettled and is why this was reverted rather than shipped.** The
routing was built during F-0029 and backed out on finding this item; see
`workforce/branches/fixes/F-0029-cycle4-goals-and-expenditure-split.md` §4.4. Current
behaviour is now pinned by a test —
`tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php`, *"writes the acting
account at 100% and leaves the spouse untouched"* — so that when this item is built the
change surfaces as a deliberate red test rather than a silent diff. **It pins the current
behaviour; it does not endorse it, and the docblock says so.**

- 2026-08-24 — **CSJ chose option 1: make the unanswered state expressible first.** Built.
  - **Migration** `2026_08_24_080000_record_when_a_household_actually_declared_its_expenditure_sharing`
    adds `users.expenditure_sharing_mode_declared_at`, nullable, **no backfill**. NULL is
    the correct value for all 19 rows: none of them was a declaration, and backfilling a
    timestamp would recreate the very defect the column exists to remove, one layer up.
  - **A companion timestamp rather than a nullable enum**, deliberately. Nullable would
    carry the same meaning and would also change what every existing reader receives —
    `SharedExpenditure::isShared()`, `UserResource`, `OnboardingService`, the profile
    controller and the writer all read that column and all treat it as always-present.
    This is additive: no reader changes behaviour, and the new column answers the question
    the old one cannot — *was this chosen, and when*.
  - **`UserProfileController` stamps it**, and only where the toggle is actually submitted
    — the one place a person has been shown the choice, beside a subheading reading
    "Joint (50/50) expenditure" while they type.
  - **`handleSetExpenditure` now asks** when there is a live spouse and no declaration,
    and writes NOTHING on that turn. Measured the day it shipped: **13 of 13
    spouse-holding users on dev had never declared**, so this is the branch that runs for
    all of them — which is exactly why the 100/0 write had to stop being silent.
  - **Criterion 2 done too, now that 1 is closed:** the write routes through
    `HouseholdExpenditureWriter`, the mechanism W-0412 built. One household figure,
    divided once, both rows in one transaction.
  - **Reason 2 of "why it was not fixed with W-0190" dissolved rather than being solved.**
    The worry was that routing would change WHICH fields divide. It does not: Fyn divided
    *nothing* before, so routing makes it match the other two paths exactly.
    `rent`, `utilities` and `charitable_donations` stay whole because `SHARED_FIELDS` says
    so, which is a question for whoever owns that list, not for this path.
  - **The subtlety that made this more than a one-line call:** stored figures are the
    account's SHARE, so a partial edit recomputing the total from storage would divide the
    untouched categories a second time — decaying a household's spending towards zero one
    Fyn turn at a time. The household total is reconstituted before the writer divides it
    again. **Mutation-tested: removing the reconstitution gives £212.50 where £325 is
    right**, and the guard reddens.
  - **Browser-verified** on `david.jones@example.com`: saved the expenditure form and
    `expenditure_sharing_mode_declared_at` was stamped `2026-08-24 07:58:44`, with the
    225/225 halves untouched. Restored to NULL afterwards, since that save was a test.
