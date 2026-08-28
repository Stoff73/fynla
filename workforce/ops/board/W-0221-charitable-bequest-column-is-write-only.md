---
id: W-0221
title: users.charitable_bequest is now read by nothing and can still be written — a write-only column with a live endpoint
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: build-lead
status: done
certification: CERTIFIED 2026-08-25 quality-lead — see ops/handoffs/quality-lead/pr716-certification-2026-08-25.md; merged to dev in 88e9d08ce (PR #716)
claimed_by: brett-2026-08-25
severity: low
surfaces: [web]
created: 2026-08-22T07:56:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: ["W-0132 — removed the last two readers, both halves now with quality-lead", "W-0154 — the calculation reads pooled household bequests, which is the correct source"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: raised by team-lead after verifying cycle2-audit's W-0132 work; the agent asked for an id rather than riding a schema change inside a display fix
---

## Intent

**`users.charitable_bequest` is no longer read by anything in the application. It can
still be written.**

W-0132 removed the last two readers — the estate toggle's client-side model and the
family card — and the calculation now reads the recorded bequests, which is the
instrument. `cycle2-audit` correctly declined to drop the column inside a display fix.

**But "read by nothing" is not the whole state.** Verified 2026-08-22:

- **`UpdatePersonalInfoRequest:79` still validates and accepts it**, so `PATCH` on the
  personal-information endpoint can set it. **A live write path into a column nothing
  consumes.**
- `User.php:178` still casts it.
- `userProfileService.updateCharitableBequest()` is unreferenced but present.
- `EstateAgent:727,754` use the string `'charitable_bequest'` as a **category label** —
  **not** this column. Do not remove those.

**Why a write-only column is worse than an unused one.** It accepts data, returns
success, and discards it — which is the shape of half the defects on this board. And the
next feature wanting an answer about charity will find a column with a plausible name, a
cast, and a working endpoint, and will read it — reintroducing the fourth mechanism
W-0132 has just removed.

## Acceptance

- [x] The write path is closed **before or with** the column being dropped — a column
      dropped while its endpoint still accepts the field trades a silent discard for a
      500. — **TWO paths closed, in the same commit as the drop.** The 500 premise
      turned out not to hold on this path; see the 2026-08-25 note.
- [x] The unreferenced frontend service method goes with it.
- [x] `EstateAgent`'s two category-label strings are **untouched** — different thing,
      same name. — and four more label sites found beyond the two named, all left.
- [x] Migration only, no behaviour change. Nothing reads it, so nothing should move. — 870 tests green, no figure moves.
- [x] Check `/m` and native for any reader before dropping (none found at raise time). — re-checked; none.

## Working notes

(append-only)

- 2026-08-22 team-lead: raised on `cycle2-audit`'s request. **Its judgement not to ride a
  schema change inside a display fix was right** — that is how a migration lands in a
  batch nobody expects one in.
- 2026-08-22 team-lead: the agent's report said "read by nothing in the application",
  which is **accurate**. The write path is an addition to that finding, not a correction
  to it — found by grepping for the column while verifying its work.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **DONE — both write paths closed and the column dropped, in one commit.**

  **The item named one write path. There were two.**
  `app/Services/Onboarding/OnboardingService::processFamilyInfo():263-267` also wrote
  the column, from the family step. Found by sweeping for the column name rather than
  trusting the list — and it matters, because closing only the named path and dropping
  the column would have left onboarding writing to a column that no longer existed.

  **The write was real, and I checked rather than assumed.** `charitable_bequest` is
  absent from `User::$fillable`, which looks like mass assignment would already skip
  it. It does not: `$fillable` is **empty**, so the explicit `$guarded` list governs,
  and this column was not on it. Confirmed live — `$user->update(['charitable_bequest'
  => true])` persisted `true`. Live data: 16 NULL and one `false`, so a write had
  landed at some point. That one value is destroyed by the drop; nothing read it.

  **Acceptance 1's premise does not hold on this path, and the reason is worth
  keeping.** The item reasoned that dropping the column while the endpoint still
  accepted the field would trade a silent discard for a **500**. Measured, it does not.
  Eloquent's `isGuarded()` calls **`isGuardableColumn()`, which consults the live table
  schema** — once the column is gone, `isFillable('charitable_bequest')` returns false
  and mass assignment silently skips it. I proved this by re-adding the validation rule
  after the drop and re-running the file: **it stayed green.** The 500 risk is real for
  a query-builder write; it is not real for `$model->update()`.

  That finding invalidated my first guard. The endpoint test I had written asserted
  `assertOk()` and would have passed with the rule present or absent — a test that
  cannot fail. **It is kept, with its rationale corrected to what actually protects
  the path, and the rule's removal is now guarded directly** by asserting
  `rules()` has no `charitable_bequest` key. That one **is** mutation-verified: putting
  the rule back turns it red.

  **What changed:**
  - `UpdatePersonalInfoRequest:79` — rule removed, with a note saying not to re-add it.
  - `OnboardingService::processFamilyInfo()` — the second write removed.
  - `User.php:178` — the boolean cast removed.
  - `userProfileService.updateCharitableBequest()` — removed; it had no callers.
  - `2026_08_25_140000_drop_the_charitable_bequest_column_nothing_reads` — the drop.
    Rollback verified: `down()` restores the shape (not the contents, which nothing
    read), re-up drops it again, 17 users intact throughout.

  **Category labels left alone, and there are more than the two named.** `EstateAgent
  :826,868` plus `EstateRecommendationAdapter:38,53,72`,
  `RecommendationPersonaliser:296,461` and `EstatePlanService:142,189,212` all use
  `'charitable_bequest'` as a recommendation CATEGORY. Different thing, same name — none
  touched.

  **The W-0132 guard tests needed thought, not deletion.** Four tests set the column to
  the value that would give the WRONG answer, so an endpoint consulting it could not
  pass. With the column gone the decoy cannot be set — and does not need to be, because
  a column that does not exist cannot be read, which is stronger than any fixture. The
  positive cases are kept; the case whose only value was the decoy is **repurposed into
  the regrowth guard** (`Schema::hasColumn('users','charitable_bequest')` is false),
  which is what this item was really protecting against.

  **The frontend decoy is KEPT deliberately.** `FamilyMembers.spec.js` sets
  `auth.user.charitable_bequest = false` beside a will holding a legacy. The server can
  no longer send that key at all, which is exactly what makes it a guard on the
  COMPONENT: a card rewritten to read it would get `undefined`, fall to falsy, and print
  "No" on a will holding a legacy — the original defect, reachable without the column.
  Comments updated to say so.

  **Verification:** Api + Estate + UserProfile 870 tests / 4,950 assertions green;
  Onboarding filter 950 / 3,348; Architecture 177 / 4,296; frontend 32 / 32. Pint clean.
  No figure moves for any user.

  **Not done:** `database/schema/mysql-schema.sql` still lists the column. It is a
  generated squash artifact and the ordering is still correct — a fresh install loads
  the dump, then runs this migration, and ends in the right state. Regenerating it is a
  `schema:dump`, which is a bigger action than this item and would sweep in every other
  pending change; left deliberately.
