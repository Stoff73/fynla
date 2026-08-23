---
id: W-0190
title: The expenditure table declares "Joint (50/50) expenditure" and then splits the £2,450 household spend 100/0 — the whole of it to David, nothing to Sarah
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md
owner: build-lead
status: queued
severity: high
surfaces: [web, m]
created: 2026-08-22T00:40:00Z
claimed: 2026-08-22T03:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: REJECTED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0140, W-0173]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Cycle 2 journey re-walk, local, both persona accounts, read-only.
**Surface:** `/valuable-info?section=expenditure` → Monthly Expenditure Summary.

Found while verifying **W-0140**, which is **fixed** — the composition is now shown in
full and `/plans/estate` states "Recorded Expenditure: £29,400" for David and
"None recorded" for Sarah. This is a different defect that the new composition table
makes visible for the first time.

### Expected

`users.expenditure_sharing_mode` is **`joint`** on both accounts, and the table says so
in its own subheading. The persona's fifteen categories are **household** spending —
food, transport, school fees, children's activities — totalling **£2,450/month**. Under a
declared 50/50 split that is **£1,225 each**.

### Actual

```
Entry Mode: Detailed Breakdown · Joint (50/50) expenditure

Category                        Sarah      David    Household
  Essential Living                 £0       £750         £750
  Communication & Technology       £0       £120         £120
  Personal & Lifestyle             £0       £300         £300
  Children & Dependents            £0     £1,230       £1,230
  Other Expenses                   £0        £50          £50
  Manual Expenditure Total         £0     £2,450       £2,450
  Financial Commitments        £1,235     £1,916       £3,151
  Total Monthly Expenditure    £1,235     £4,366       £5,601
  Annual Equivalent          £14,820    £52,394      £67,214
```

**Every manual category is £0 for Sarah and 100% to David**, on a table whose own
subheading says the expenditure is joint 50/50. The financial-commitments row beside it
*is* split by ownership (£1,235 / £1,916), so the table applies a share to one row and not
the other.

The declared mode and the arithmetic contradict each other in the same visual block.

### Impact

- **Sarah's monthly expenditure reads £1,235 where £2,460 is due** — her disposable income
  is overstated by **£1,225/month, £14,700 a year**.
- **David's reads £4,366 where £3,141 is due** — understated by the same.
- Both figures drive the affordability statements in `/plans/estate` and the retirement
  and goals modules, so the error propagates in opposite directions on the two accounts of
  one household.
- It is the mirror of **W-0173**: there a joint asset's income reached only the owner;
  here a joint cost is charged only to the recorder. Same principle, opposite direction —
  worth handing to whoever takes W-0173.

**If the intended behaviour is that manual categories belong to whoever typed them**, then
the "Joint (50/50) expenditure" subheading is the defect and must not claim a split that
is not applied — but that reading sits badly with categories like "School Fees" and
"Children & Dependents" being household costs by nature.

### Repro

1. `sarah.jones@example.com` → `/valuable-info?section=expenditure`, wait ~15s.
2. Read the subheading: "Entry Mode: Detailed Breakdown · **Joint (50/50) expenditure**".
3. Read the Sarah column: every manual category **£0**, Manual Expenditure Total **£0**.
4. Read the David column: the full **£2,450**.
5. `php artisan tinker` — `users.17 expenditure_sharing_mode = 'joint'`,
   `monthly_expenditure` NULL; `users.16 monthly_expenditure = 2450.00`, mode `joint`.

### Acceptance

1. The split applied matches the split declared. Under `joint`, household manual
   categories are shared 50/50 and both columns show their share.
2. Manual categories and financial commitments use the same sharing rule, or the table
   states why they differ.
3. Sarah's annual equivalent and disposable income change accordingly, and `/plans/estate`
   and `/m` follow from the same source (Rule 20).
4. Verified in a browser on both accounts, and against a household set to individual
   rather than joint, which must be unaffected.

## Working notes

**2026-08-22 — build-lead (`cycle2-ownership`). Fixed.** Branch document:
`workforce/branches/fixes/F-0019-cycle2-ownership-applied-one-side-only.md`.

### The item's closing question, answered

The item asks whether "the intended behaviour is that manual categories belong to
whoever typed them", in which case the subheading is the defect. **It is not.** The
rule is declared, documented and implemented — in exactly one of the three paths that
write those columns:

`OnboardingService::processExpenditure():499-544` carried twenty-two inline
`/ $divisor` divisions and a comment stating the contract outright: *"For joint/50/50
mode, also update spouse with the same halved expenses. Each account now stores their
50% share of the household total."* So the storage semantic is settled — **each row is
that person's share** — and the subheading is telling the truth about a rule the
profile path simply never ran.

### The visible 100/0 was masking a worse latent defect

`ExpenditureForm.handleSave():2292-2306` → `UserProfileController::updateExpenditure()`
and `updateSpouseExpenditure()`. In joint mode the form sends the **full** household
figures to **both** endpoints, and neither divided. So the same path produces two
different wrong answers depending on whether the second write lands:

| | Stored | Household column reads | For a £2,450 household |
|---|---|---|---|
| **Spouse write lands** | £2,450 **twice** | **£4,900** | a clean **double count** |
| **Spouse write does not land** — this persona | £2,450 / £0 | £2,450 | the visible **100/0** |

**The only reason nobody has seen the double count is that the spouse write never
happened for this household.** The reported symptom is the *lesser* of the two
defects, and it is hiding the greater one.

**That is why the divisor is not optional.** Fixing the visible 100/0 by making the
spouse write reliable — the obvious reading of "the split isn't reaching Sarah" —
without also applying the share would have shipped **£4,900 for a £2,450 household**
into production, on the figure every affordability statement rests on. The defect that
looked like under-recording would have become over-recording, silently, at twice the
magnitude.

Both shapes are the same root cause: the declared share was never applied on the way in.

### One home

**`app/Support/SharedExpenditure.php`** (NEW) — the sharing rule, the mode constants,
the default, and the list of fields that divide. Deliberately shaped like
`SharedOwnership`: normalise on the way **IN**, and every reader then trusts what is
stored. There is no share column on a `users` row — **the row IS the share** — so this
is the only shape where the per-person columns and the Household column can agree.

Routed through it:

| Path | Change |
|---|---|
| `OnboardingService::processExpenditure():498-540` | twenty-two inline divisions deleted; composes from `SharedExpenditure::shareOf()`. Behaviour identical. |
| `UserProfileController::updateExpenditure():215-227` | applies the rule for the first time; the `ExpenditureProfile` mirror now writes the **share**, not the household total, so `ResolvesExpenditure` cannot outrank the column |
| `UserProfileController::updateSpouseExpenditure():460-470` | same rule, and it reads the **acting user's** mode rather than the spouse's row |

`SHARED_FIELDS` is exactly the list the onboarding path already divided, unchanged, so
routing the second path through changes *which path applies the rule* and not *which
fields it applies to*. **`charitable_donations` is deliberately absent** — it is a Gift
Aid input, not a running cost, and halving it would move a tax relief figure.

### Two things this exposed that needed fixing to make the rule safe

1. **The sharing mode was a fact about a row, not about the household.** Nothing
   propagated it: `updateSpouseExpenditure` never receives `use_separate_expenditure`,
   so a household switching to `separate` left the spouse's row still saying `joint` —
   and the two halves of a single save would then have been divided by different rules.
   `updateExpenditure` now writes the mode to a live spouse as well, which is what the
   onboarding path has always done.

2. **The edit form would have halved compounding on every save.** With storage as
   shares, the form's inputs would show the user's £1,225 while its own label says the
   figures are the household's — so the next save would have stored £612.50, then
   £306.25. `ExpenditureForm.beginEditing()` lifts the two stored shares back into one
   household figure before editing (`resources/js/components/UserProfile/ExpenditureForm.vue:2344`),
   used by the Edit button **and** the AI form-fill entry point so both doors behave
   the same. **The lift is addition, not a second copy of the one-half rule** — the
   user's share plus the spouse's share IS the household figure, which is exactly what
   the Household column already displays.

### The resulting table

| | Sarah | David | Household |
|---|---|---|---|
| Manual Expenditure Total | **£1,225** | **£1,225** | £2,450 |
| Financial Commitments | £1,235 | £1,916 | £3,151 |
| Total Monthly | **£2,460** | **£3,141** | £5,601 |
| Annual Equivalent | **£29,520** | **£37,692** | £67,214 |

The manual row and the commitments row beside it now apply the same kind of rule —
each account carries its own share — which was Acceptance 2. Sarah's annual equivalent
moves by **+£14,700** and David's by **−£14,700**, exactly the figures the item names.

### Existing rows — the migration is written and NOT applied

`database/migrations/2026_08_22_000200_split_joint_expenditure_recorded_on_one_account.php`

**Deliberately narrow, because only one case is readable with certainty.** Two rows
holding the same non-zero figure are **indistinguishable**: they might be two correct
halves written by the onboarding path, or one household total mirrored whole by the
profile path. Halving those blindly would corrupt every household that onboarded
correctly. So the migration touches only the shape the defect produces — **one account
carrying manual spending and the other carrying none** — and leaves the ambiguous case
alone. It syncs `ExpenditureProfile` too, because `ResolvesExpenditure` prefers that
row over the column and a stale household total there would outrank the share.

**APPLIED by team-lead 2026-08-22 and verified by them against the persona:**
`£2,450 / NULL` → **£1,225 / £1,225**, with `expenditure_profiles` synced. Sarah's
disposable income down £14,700 a year and David's up the same — the figures this item
asked for. **W-0190 now reads fixed on the persona.**

It was written and deliberately left Pending by me because applying it writes to users
16 and 17, which this dispatch forbade. (Its sibling,
`2026_08_22_000100_sync_rental_income_to_every_owner` for W-0173, has since been
applied — batch 52 — and the persona's rental figures are now correct, so the pattern
is established.)

#### What the existing rows actually look like, and what the migration can and cannot tell apart

Unlike the rental case, **the stored data is wrong in two different directions
depending on which path recorded it**, and the two are not always distinguishable.

| Shape | Written by | Distinguishable? |
|---|---|---|
| One account populated, the other empty | profile path, spouse write absent | **yes** — no other path produces it |
| Both accounts equal and non-zero | **either** the onboarding path (two correct halves) **or** the profile path (one household total mirrored whole) | **no** |
| Both empty | nothing recorded | trivially, nothing to do |

**The second row is the problem and it cannot be resolved from the data.** £1,225 on
each account is a correct pair of halves. £2,450 on each account is a household total
counted twice. But £1,225/£1,225 is *also* what a £1,225 household double-counted looks
like — there is no stored figure, flag or timestamp that says which path wrote it, and
`expenditure_sharing_mode` is `joint` in both cases.

**So the migration handles only the first row and skips the second, and says so in its
docblock.** Halving the ambiguous case blindly would corrupt every household that
onboarded correctly — turning right answers into half-answers to fix a different
household's double count.

> **A migration that cannot tell two states apart must not guess between them.**
> — team-lead, 2026-08-22, confirming the narrowness on review. Worth carrying to the
> next backfill: the instinct to "fix everything while we are in here" is precisely
> what turns a repair into a corruption when one of the states was already correct.

**Verified against the live persona before writing it:** user 16 holds fifteen
populated categories totalling `monthly_expenditure = 2450.00`,
`annual_expenditure = 29400.00`, and an `expenditure_profiles` row at `2450.00`; user
17 holds `NULL` throughout with no `expenditure_profiles` row. Unambiguously the first
shape, so the migration handles it.

#### OPEN QUESTION — carried deliberately, not closed

**How many households sit in the ambiguous shape is unanswered.** It needs a count
against production, not this dev database.

**Team-lead's position, 2026-08-22:** not running it now. This is a pre-launch codebase
— 49 signups, four completed onboardings, no real estate data — so the ambiguous
population is almost certainly zero, and a production query is not worth the risk while
a batch is mid-flight.

**If that count is ever found to be non-zero, the decision is:** leave those households
alone, or find a discriminator outside the expenditure columns. An audit trail of which
path wrote them would be one; a `..._declared_at` style marker would be another.
**What is not available is guessing** — see the quoted rule above.

### Tests

`tests/Feature/UserProfile/JointExpenditureSplitsByDeclaredModeTest.php` — **10
passing, 33 assertions**, driven through the real endpoints with a real Premium user.

1. A household entered as joint charges each spouse **half** — the harm.
2. **Nothing is uncharged**: the two halves sum to the household figure, and neither
   column is the whole of it.
3. **Both accounts move together when the household figure moves** — the answer is not
   a constant that happens to match.
4. `separate` stores the whole of it.
5. A user with no spouse stores the whole of it even under a joint mode.
6. The sharing mode propagates to the spouse.
7–10. `SharedExpenditure` divides only money, keeps a partial update partial, leaves a
   null alone rather than writing a zero over a category nobody mentioned, and treats an
   unset mode as joint.

Regression green: `Feature/Onboarding`, `Unit/Services/Onboarding`,
`UserProfileControllerTest`, `UserProfileServiceTest`, `Unit/Services/UserProfile`,
`PlanExpenditureCompositionTest`, `CoordinatingAgentHandleSetExpenditureTest`,
`IncomeExpenditureAwardTest`, `Feature/Tiers` — **1,102 passing (3,973 assertions)**.
Full vitest suite — **754 passing, 62 files**. `./vendor/bin/pint` on the touched paths:
passed.

### Surfaces

`/m`'s expenditure screen (`resources/mobile/views/Expenditure.vue`) is **read-only**
and renders `/api/user/profile`, so it inherits the fix and now shows this account's
share — consistent with its financial-commitments figure, which was always the
account's share. Its labels are neutral ("Category entries plus financial
commitments") and make no household-versus-share claim, so **no `/m` wording change is
needed**. Checked, not assumed. `ios-native/` reads the same endpoint.

### The one path NOT fixed, and why — raised as W-0202

**`CoordinatingAgent::handleSetExpenditure():5183-5225` — Fyn — still writes one
account at 100% and never touches the spouse.** On a joint household that reproduces
exactly this defect through a different door, and on `/m` it is the **only** door,
because the `/m` screen edits through Fyn.

I did not fold it in, for three reasons that are decisions rather than oversights:

1. **Fyn's input is genuinely ambiguous where the form's is not.** The form declares in
   its own subheading that the figures are the household's; "our food shopping is £600"
   said to Fyn may mean either. Halving silently would be wrong half the time and would
   look identical to a bug.
2. **Its field list differs from both the others** — it covers `rent`, `utilities` and
   `charitable_donations` and omits `regular_savings` — so routing it through would
   also change which fields divide.
3. `CoordinatingAgent.php` is 6,500 lines and was modified by another agent in the
   shared tree.

**W-0202** carries the evidence and the decision needed. Flagged to team-lead as the
one question this instance could not answer for itself.

### One qualification on this fix, found while answering W-0202's reachability question

`users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT 'joint'`
— it cannot be null. So **joint-by-declaration and joint-by-never-having-been-asked are
indistinguishable**, and the profile path now halves for a married user whose mode is
simply the default. On the dev database all 19 users read `joint` and none `separate`:
every value is the default.

**That is defensible here and would not be on a silent surface**, and the difference is
disclosure rather than arithmetic. The expenditure form shows "Joint (50/50)
expenditure" in its own subheading with the toggle visible and set, at the moment the
user types the figures — so the rule being applied is on screen. Fyn has no equivalent,
which is the substance of **W-0202**.

**Not done: browser verification, by instruction.**
