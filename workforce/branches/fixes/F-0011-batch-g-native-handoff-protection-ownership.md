---
id: F-0011
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T19:15:00Z
status: active
---

# F-0011 — Batch G, second tranche: native will handoff, protection sources, ownership orphans

**Owner:** build-lead (agent `fix-batch-G`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0044 (`handoff`), W-0033 (`handoff`), W-0043 (**lost to `fix-batch-F`
mid-batch — see §5**), W-0141 + W-0142 raised

First tranche of this batch is `F-0008-batch-g-lpa.md` (W-0100, the Lasting Power of
Attorney generator). This document covers the three items dispatched after it.

**Status: W-0044 and W-0033 code-complete, tests green, Pint clean, both at `handoff` →
quality-lead. W-0043's acceptance 2 is complete and recorded here, but the item is no
longer mine.** No commit, no PR, no deploy, no browser or simulator verification.
Nothing is in flight — §4 says so explicitly.

---

## 1. W-0044 — the native app had no route to the Will Builder

### What was wrong, and the trap underneath it

`WebHandoffDestination` in `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift`
carried five cases; the PHP enum carries six. The missing one was `estate_will`, so a
native user could not reach the Will Builder by any path — the screen W-0019 and W-0024
govern was unreachable from the app those fixes were supposed to serve.

**The trap is that adding the case naively would not have worked and would have looked
like it had.** Swift derives an enum's raw value from the case name, so `case estateWill`
puts `"estateWill"` on the wire. `IssueWebHandoffRequest` validates against the PHP enum,
whose backing value is `"estate_will"` — a 422 at runtime, from Swift that reads
correctly. The five original cases worked only because their names happen to be single
lowercase words; the first multi-word destination is where that runs out. Every case now
carries an explicit raw value.

### The drift mechanism, which is the real finding

Acceptance 2 asked for a sweep on the theory that "if one drifted, the mechanism permits
drift". It does, and worse than expected: **the test written to catch this was the same
kind of copy as the thing it was watching.**
`WebHandoffClientTests.exposesOnlyTheServerAllowlistedSemanticDestinations` names "the
server allowlist" and asserts a frozen literal. It stayed green through the entire drift.

Fixed on both sides. `tests/Feature/Auth/WebHandoffTest.php` now asserts the PHP enum's
values with a failure message naming the Swift file to update, asserts every value is
snake_case, and asserts the server rejects `estateWill` — the exact string an unmirrored
Swift enum sends. Adding a seventh destination now fails a PHP test that tells you where
to go.

### Files

| File | Change |
|---|---|
| `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift` | `estateWill` + explicit raw values + the doc that explains why |
| `ios-native/Fynla/Features/Estate/EstateView.swift` | handoff button in the planning card, owns its own in-flight/error state |
| `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift` | one new closure param |
| `ios-native/Fynla/App/AppRootView.swift` | `openWillPlanning()`, mirroring `openAdmin()` |
| `ios-native/FynlaTests/WebHandoffClientTests.swift` | allowlist updated + 2 new tests |
| `tests/Feature/Auth/WebHandoffTest.php` | 4 new tests, the drift catcher |

### Verification, and its limit

`xcodebuild -scheme Fynla-Staging -destination 'generic/platform=iOS'` → **BUILD
SUCCEEDED**. `build-for-testing` → **TEST BUILD SUCCEEDED** (so the new Swift tests
compile). PHP: **12 passed, 55 assertions**.

**The Swift tests were not run and the button was never pressed.** Team-lead scoped this
to code plus compile-level coverage and explicitly did not ask for simulator work;
`Fynla-Staging` points at csjones, not local, so an end-to-end exercise was not available
either. **I COULD NOT TEST THE BUTTON.** Acceptance 3 stays open.

### W-0110, treated together as instructed

Same enum, same absence, the other estate instrument. I did **not** add a Lasting Power
of Attorney destination: it would be a case with no caller, because `/api/estate` returns
no `lpa_info` block for a status row to render, and the entry point is a product decision
W-0110 already routes to product-lead. What W-0044 gives W-0110 is that the drift now
fails a test, so its future case cannot go missing the same way. Doing it twice was the
thing to avoid; doing it early would have been speculative.

---

## 2. W-0033 — which source drives protection advice

### The decision, and why it is not a preference

**The protection profile is authoritative. The dead branches are deleted.** The enforcing
layer decided it, not me: `RecommendationEngine.php:185,232` generates the advice from
`$profile->smoker_status`, `ProtectionDataReadinessService.php:199,396` gates on it, and
`RetirementActionDefinitionService.php:1656` and `DecumulationPlanner.php:183` read the
same profile field for the same fact. Nothing anywhere reads `users.smoking_status` for
protection.

**An independent second reason: the two sources are not interchangeable.**
`users.smoking_status` is `enum('never','quit_recent','quit_long_ago','yes')`;
`protection_profiles.smoker_status` is a **boolean**. `users.health_status` is
`enum('yes','yes_previous','no_previous','no_existing','no_both')`; the profile's is
`in(excellent,good,fair,poor)`. Repointing the reads would have been a vocabulary
translation, and would have put the plan's summary out of step with the engine writing
the advice beside it.

### The pattern question — answered, and it is an incident

`grep -rn 'isset($user->' app/` returns **only these two reads**, both in this file. I
also checked the other user reads in the same method: `$user->name` is a real accessor
(`User::$appends` + `getNameAttribute()`), and `gender`, `occupation`, `education_level`,
`marital_status`, `date_of_birth` are all real columns. It shares a *shape* with W-0006 —
both invent column names — but there is no spread.

### One live change, and the defect it uncovered

With the dead branch gone, a **missing** smoking answer rendered as "Non-smoker" and a
missing health answer as "Good". Both now say "Not provided", matching the idiom this
same method already uses for an absent date of birth. Nothing downstream branches on the
exact strings.

That change is currently invisible, and finding out why is the more valuable half:
`protection_profiles.smoker_status` is `tinyint(1) NOT NULL DEFAULT '0'` and
`health_status` is `varchar(255) NOT NULL DEFAULT 'good'`. **An unanswered question
cannot be stored.** `StoreProtectionProfileRequest:37-38` validates both as `nullable`,
so the request layer models the unknown and the column erases it — on the two fields that
most affect the price and adequacy of life cover, in the favourable direction. Raised as
**W-0141**, with a characterisation test pinning the column definitions.

**F-0005 respected** — `ProfileEnums::EDUCATION_LEVEL_LABELS` untouched, and a new test
asserts it still drives the education label, since W-0033 edits the lines directly above
it.

### Verification

`ComprehensiveProtectionPlanProfileSourceTest` **6 passed**; wider regression across
`tests/Unit/Services/Protection/`, `ProtectionApiTest`, `ProtectionWorkflowTest` —
**133 passed, 388 assertions**. Pint clean. Not browser-verified.

---

## 3. W-0043 — the sweep, complete, on an item I no longer hold

Acceptance 2 asked for a sweep "across every shared-asset table, not just mortgages".
Done, read-only, nothing written. Orphan = shared ownership type + no `joint_owner_id` +
no `joint_owner_name`, soft-deletes excluded.

| Table | Result |
|---|---|
| `mortgages` | **1 orphan** — `id=7, user_id=14, joint, 50.00`; the known one |
| `properties` | 0 |
| `chattels` | 0 |
| `savings_accounts` | 5 shared, **0** without a linked owner |
| `investment_accounts` | 2 shared, **0** without a linked owner |
| `business_interests`, `liabilities` | 0 shared rows at all |

The last four have no `joint_owner_name` column, so they are counted separately rather
than lumped in — F-0002 records joint-with-no-linked-owner as deliberately first-class
there. It turns out not to be in use locally anyway.

### The premise correction, which outlives the sweep

W-0043's Intent says the orphan "is the data W-0025's new counterparty rule now prevents
being created". **It does not.** `SharedOwnership::namesCounterparty()` is called from
exactly two places — `StoreChattelRequest:84` and `UpdateChattelRequest:101`. F-0002 §3
describes it as "the chattel/property/mortgage counterparty rule"; only the chattel third
was wired up. Mortgage and property forms accept `joint` with both counterparty fields
nullable and no cross-field check, and **Fyn can orphan a property and a mortgage in one
call** (`CoordinatingAgent:3489-3491`, `:3539-3541` writes a null `joint_owner_id` and
never writes `joint_owner_name`, `:3579-3581` hands the same null to the auto-created
mortgage) — the likeliest origin of `mortgages.id = 7`.

So the class is **open**: clean today, re-openable tomorrow. Raised as **W-0142**,
deliberately unfixed — `fix-batch-J` is live inside `SharedOwnership` and editing it in
parallel is the collision team-lead warned about.

### For whoever takes the CSJ decision

F-0002 §3 recorded that "W-0015's 'preserve a deliberate 100/0' is NOT implemented".
`fix-batch-J` reversed exactly that at 18:54 under **W-0040** — CSJ has ruled a stated
100/0 **is** individual ownership. Converting `mortgages.id = 7` to individual at 100 is
now a coherent option in a way F-0002 said it was not. **Decide on the new ruling, not on
F-0002's line.**

---

## 4. IN FLIGHT

**Nothing.** No half-finished edit, no running process, no uncommitted experiment beyond
the completed files in §6. A replacement agent starts at §7.

---

## 5. Two collisions with other agents — both reported, neither fought

1. **`app/Support/SharedOwnership.php` has 3 red tests that are not mine.** The file was
   rewritten at **18:54:58** under W-0040 ("supplied beats inherited"), after I had read
   it. `tests/Unit/Support/SharedOwnershipTest.php` fails 3 tests that assert the **old**
   rule (`applyTo(['ownership_percentage' => 100], 'joint')` → 50.0). Reproduces in
   isolation in 0.81s, so it is a real failure and not contention. **Do not attribute it
   to this batch, and do not fix it** — `fix-batch-J` owns that class and has presumably
   not reached its tests yet. Reported to team-lead 19:00.

2. **W-0043 was claimed by two agents.** I claimed all three items at dispatch,
   18:20:00Z. The board now reads `claimed_by: fix-batch-F` against **my** timestamp,
   with `branch: F-0007-batch-f-analytics-consent.md`. I had already set the status to
   `gated` before noticing; **I reverted it to `claimed` and stopped writing to the
   item.** §3 exists so fix-batch-F does not repeat the sweep. Reported to team-lead.

---

## 6. Files changed

| File | Item |
|---|---|
| `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift` | W-0044 |
| `ios-native/Fynla/Features/Estate/EstateView.swift` | W-0044 |
| `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift` | W-0044 |
| `ios-native/Fynla/App/AppRootView.swift` | W-0044 |
| `ios-native/FynlaTests/WebHandoffClientTests.swift` | W-0044 |
| `tests/Feature/Auth/WebHandoffTest.php` | W-0044 |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | W-0033 |
| `tests/Unit/Services/Protection/ComprehensiveProtectionPlanProfileSourceTest.php` | W-0033 (new) |
| `workforce/ops/board/W-0141-*.md`, `W-0142-*.md` | new items |

**Nothing under `app/Support/`, nothing in the will builder, nothing in
`resources/mobile/`.** No migrations. No data written. No users created or deleted.

---

## 7. NOT STARTED, in priority order

1. **W-0142** — the counterparty guard on properties, mortgages and the Fyn path.
   Blocked behind `fix-batch-J`; re-read `SharedOwnership.php` first, it is not the file
   F-0002 describes any more.
2. **W-0141** — the protection profile NOT NULL defaults. Needs compliance-lead on
   whether assuming "non-smoker, good health" is defensible before any migration.
3. **W-0044 acceptance 3** — simulator verification of the will handoff button.
4. **W-0043 acceptance 1 and 3** — CSJ's decision and any repair migration. Not mine.

---

## 8. Environment state

- Test database `laravel_testing_c` throughout. `pgrep` before every run; between 0 and 7
  other Pest processes were live, and the one suspicious result was re-run in isolation
  before being believed.
- Xcode build artefacts went to the session scratchpad, not `ios-native/build/`.
- No migrations, no seeders, no `.env`, no production query, no `/m` bundle rebuild.

---

## Renumbered F-0009 → F-0011 by team-lead, 2026-08-21

**This document was written first and still moved.** `fix-batch-G` and `fix-batch-I` both
closed out within an hour, both read the directory, both saw `F-0008` as the highest, and
both took `F-0009`. The Archivist found them live.

**`FORMATS.md`'s collision rule decides it by inbound references, not by who wrote first:
the cheap thing to move is the file, not the citations.** Batch G's had 3 board items and
2 handoff notes pointing at it; batch I's had 13 references. So this one renumbered and
batch I keeps `F-0009`. **The instinct is first-come-first-served and the rule deliberately
says otherwise.**

**The causal finding, which matters more than this rename:** `F-NNNN` is the only
identifier space where the number is **chosen by the agent, at close-out, by reading the
directory** — rather than issued by the coordinator at dispatch. That is the same
`ls`-then-write race the work-item ledger was built to stop, in the one space nobody
extended it to. **Second F-collision today**, after the two `F-0005` documents this
afternoon.
