---
id: F-0019
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0019 — Cycle 2: ownership applied on one side only

**Agent:** build-lead (`cycle2-ownership`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0190, W-0187, W-0186, W-0173 · **ID block:** W-0200 – W-0203
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0002-batch-a-ownership-net-worth.md` (the write rule, `SharedOwnership`; the
display rule, `ownership.js`) · `F-0013-batch-f-ownership-boundary.md` (stated vs
inherited share) · `W-0175`'s working notes (the rental figure's one home, and its
ratified correction).

---

## 1. The principle

**Where a record carries an ownership share, every derived figure on both sides of
that record must use it — costs and income alike, for the owner and the
counterparty.**

The application does not disagree about the share. It disagrees about *which side
of a record a figure belongs to*, and it disagrees differently in each module.

### The contradiction that ties the four together

**The cost side reaches the non-recording spouse and the income side does not.**

Sarah Jones is charged her share of the household's property commitments — exactly,
to the pound, £1,235 a month — from properties whose rental income she never
receives. On the same household, a joint expenditure she shares is charged wholly to
her husband. She is an owner for the purposes of what she owes and a stranger for the
purposes of what she earns.

**The application cannot decide whether she is an owner, and it decides differently
per module.**

### The principle in the codebase's own vocabulary

Every one of the four defects is one of exactly two failures, and the codebase
already has a name for the correct form of each:

| Failure | The existing correct mechanism |
|---|---|
| **Reach** — the derived set omits the counterparty's side | `forUserOrJoint` / `forUserWithJointOwner` scopes; `HasJointOwnership` |
| **Fraction** — the share is not applied to a figure derived from the record | `CalculatesOwnershipShare::calculateUserShare` / `calculateUserMortgageShare` |

**Every derived figure must be computed from a reach-complete set, at the user's
fraction.** W-0186 and W-0173 are reach failures. W-0187 and W-0190 are fraction
failures. None of the four needed a new arithmetic; three needed routing to a home
that already existed and one needed a home extracted from the single path it was
trapped in.

### What this is NOT

It is not a tenth share implementation. `F-0002` consolidated nine into two and that
holds. Nothing here re-derives a share. Each fix deletes duplicated arithmetic or
routes a consumer onto the existing home; the count of implementations goes **down**.

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0173 rental income | `PropertyService::annualRentalTaxPosition()` (W-0175's one home, already reach- and fraction-correct) | **route** + delete a surviving duplicate |
| W-0187 protection debt | `CrossModuleAssetAggregator::calculateMortgageTotal()` (two-leg, share-aware, third-party-safe) | **route** |
| W-0186 joint-life reach | none — no mechanism answers "which policies cover this user's life" | **build**, one home |
| W-0190 expenditure share | `OnboardingService::processExpenditure()`'s `$divisor` block — the rule exists but only on the onboarding path | **extend** — extract to one home, route both write paths |

---

## 3. Constraints honoured

- **The Manchester third-party share must keep passing.** Property 20 is
  `tenants_in_common` 40% to David with `joint_owner_id` NULL. The value side is
  right today — David £118,000, Sarah £0, and the £177,000 that must never appear
  does not. Nothing here changes the value side.
- **A third-party counterparty is not a user.** Mike Barrett has no account. Every
  mechanism used here returns a share to the owner and credits the remainder to
  nobody — it does not fall through to the spouse.
- **Rule 6** single record; **Rule 20** one home; **Rule 19** `/m` named explicitly
  per item; **Rules 9/12/15** no acronyms, no scores, no decorative icons.

---

## 4. Status — ALL FOUR DONE

| Item | Outcome | One home |
|---|---|---|
| **W-0173** joint rental income reaches only the recorder | **DONE** · `handoff` → quality-lead | `Listeners\Property\SyncOwnerRentalIncome` on the PropertyStore events → `PropertyService::annualRentalTaxPosition()` |
| **W-0187** protection debt at 100% household + third party | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator::calculateLiabilityTotals()` |
| **W-0186** joint-life policy invisible to the other life | **DONE** · `handoff` → quality-lead | `app/Services/Protection/LifeCoverReach.php` (NEW) |
| **W-0190** joint expenditure split 100/0 | **DONE** · `handoff` → quality-lead | `app/Support/SharedExpenditure.php` (NEW) |

Full per-item detail, file:line and measured figures live in the board files. This is
the index.

### Implementations of the four rules: before and after

| Rule | Before | After |
|---|---|---|
| The user's rental income | 3 (controller inline, profile service, the one home) | **1** |
| What the user owes | 4 (profile summary, gap analyzer, protection agent, the one home) | **1** |
| Which policies cover this life | 0 — nobody asked the question | **1** |
| How a household's spending divides | 1 implemented + 1 path bypassing it | **1**, both paths routed |

**Nothing here added a share implementation.** Three routed onto homes that already
existed; one built the home that did not.

### Raised while working — four new items, all from my block

| Item | Why it is not folded in |
|---|---|
| **W-0200** a life policy cannot name its second life assured | Schema gap. Same class of product call as W-0042; should be decided with it. |
| **W-0201** native never decodes `joint_life` | Pre-existing parity gap, not a regression. Checked: `ProtectionPolicyView` has no Edit control, so no broken button is introduced. |
| **W-0202** Fyn's expenditure capture ignores the sharing rule | **DECIDED by team-lead** — use the household's declared mode. **Not built this cycle**, by instruction. Reachability checked before writing it up: the mode needs no plumbing, but the decision's third branch ("no mode recorded → Fyn asks") is **unbuildable** — the column is `NOT NULL DEFAULT 'joint'`, so the unanswered state does not exist. See the item. |
| **W-0203** a mortgage recorded as a liability was counted twice in protection | **Fixed** with W-0187, filed separately because it is a different cause and a different wrong number. Invisible on this persona — found by reading two code paths against each other. |

Block extended to **W-0226 – W-0230** by team-lead; two more raised, and **both turned
out sharper than I first characterised them**:

| Item | Corrected finding |
|---|---|
| **W-0226** net worth liabilities at 100% | Not merely a missing share. **Its docblock states a reciprocal-records data model the application does not have** — the exact pattern W-0015/F-0002 removed. The code is correct for the model in its comment and wrong for the model in the database, so a reviewer checking code against its own documentation would pass it. The docblock is the load-bearing part of the fix. It does **not** carry W-0203's double count — `:163` skips mortgage-type rows correctly. |
| **W-0227** protection gap panel | I first reported this as "£0.00, so it contradicts nothing today". **Wrong, and measured:** the live persona's panel discloses `mortgage_balance 0, other_debts 0` as the inputs to a need of **£182,500**. And the override does not sit beside the computed figure — `calculateDebtProtectionNeed` **returns early** on it, so a once-typed number silently outranks every mortgage record, permanently, with nothing on screen saying which source won. |

**Correcting my own earlier characterisations rather than letting them stand** — both
were understatements, and W-0227's would have parked a live user-visible contradiction
as a cosmetic note.

### Reported, not fixed — outside these four items

- **`NetWorthService::calculateLiabilitiesBreakdown():132`** reads
  `Liability::where('user_id', …)` at 100% — the same disease in the net worth module.
- **`UserProfileService::$mortgageStore`** is now an unused constructor dependency.
  Removing it means editing two test files another agent had modified in the shared
  tree, so I left it rather than collide.
- **`ProtectionGapPresentationService:80`** still emits `profile->mortgage_balance`, a
  user-entered override, beside the computed share. `0.00` for this household, so it
  contradicts nothing today, but it is two sources for one number.

### The fixture-shaped variant of "a test that shares the code's assumption cannot fail"

**Recorded at team-lead's request, because it is a distinct trap from the other two in
that family and this is the first instance of it.**

`UserProfileService::calculateLiabilitiesSummary()`'s non-mortgage items closure did not
capture `$userId` — `Undefined variable`, a 500 for **any** user with a non-mortgage
liability. I wrote the bug and wrote seven tests over the same method in the same
sitting, and **every one of them passed.**

**Why they could not fail.** The known variants of this family put the shared
misconception in the *assertion*:

| Variant | Where the misconception lives | Why it cannot fail |
|---|---|---|
| The mock (W-0187's `CoverageGapAnalyzerTest`) | the value the test supplies | asserts what the mock was told to say |
| The clamp (`tests/CLAUDE.md` §5) | the value the code can return | the output cannot vary |
| **The fixture — this one** | **the data the test sets up** | **the branch is never entered** |

My fixtures were built from the persona, and the persona holds **zero `liabilities`
rows**. So no test I wrote ever put a non-mortgage liability in front of the method,
the `map()` closure never executed, and a hard `Undefined variable` sat inside a code
path with full green coverage above and below it. **The assertions were fine. The
inputs never reached the defect.**

**This one is harder to see than the other two**, because a mock and a clamp are both
visible in the test file — you can read them and ask what they are hiding. A fixture's
*absence* of a row is invisible: nothing in the test says "and no liabilities exist
here". It was caught by the wider `Unit/Services/Plans` run, whose fixtures happened to
have one.

**The countermeasure is not more assertions, it is asking what the fixture does not
contain.** Before trusting a suite over a method that branches on a collection, list
the shapes that collection can take and check the fixture produces more than one. Here:
mortgages **and** non-mortgages, owner **and** joint owner, populated **and** empty. The
W-0187 test now covers the profile's "other" list on both sides for exactly that reason.

**Corollary worth stating:** a persona-derived fixture inherits the persona's blind
spots. The peak_earners household is rich in properties, mortgages and policies and
holds no liabilities, no business interests and no chattels-with-third-parties — so
every test built from it is silently strong in three areas and silently blind in three
others.

## 5. In flight

**Nothing.** Every edit is applied, linted and covered.

## 6. What the receiver needs, and would not otherwise know

1. **Both migrations are now APPLIED and verified by team-lead.** Neither was applied by
   me: both write to users 16 and 17, which the dispatch forbade.
   - `2026_08_22_000100_sync_rental_income_to_every_owner` (W-0173) — batch 52. Persona
     reads David £14,289.60 / Sarah £8,880.00.
   - `2026_08_22_000200_split_joint_expenditure_recorded_on_one_account` (W-0190) —
     `£2,450 / NULL` → **£1,225 / £1,225**, `expenditure_profiles` synced.

   **The W-0190 backfill deliberately skips a case it cannot read**, and team-lead
   confirmed the narrowness on review: *"a migration that cannot tell two states apart
   must not guess between them."* Two rows holding the same non-zero figure are two
   correct onboarding halves OR one household total mirrored whole, and nothing stored
   distinguishes them. The instinct to fix everything while you are in there is what
   turns a repair into a corruption when one of the states was already right.

2. **W-0173's measured figures deviate from its own Acceptance 2, deliberately.** The
   item asks for salary plus **gross** rent (£130,800 / £162,280). W-0175's ratified
   correction makes the **profit** the right base, so the figures are £128,880 and
   £159,289.60 and the household total is **£288,169.60**, not £293,080. Writing gross
   would have made `/plans/estate` agree with a figure `/valuable-info` had just been
   corrected away from.

3. **W-0187's £182,500 is reached; the item's £170,500 is not, and that is correct.**
   The Manchester mortgage is still stored `joint` 50/50 rather than tenants-in-common
   40% — **W-0172's** item. The share mechanism honours whatever the record says, so
   W-0172's fix moves this figure with no further change here.

4. **W-0186 changed what a policy list contains, so read-only marking was part of the
   fix, not a nicety.** Writes stay scoped to `user_id`; a spouse's edit 404s. Every
   surface says whose record it is instead of offering a button that cannot work.

5. **W-0190 changed the storage semantic of the expenditure columns to "this account's
   share".** The edit form lifts the two shares back into a household figure before
   editing (`ExpenditureForm.beginEditing()`) — **without that, every save would have
   halved again**: £2,450 → £1,225 → £612.50.

6. **W-0173's recalc hangs off the STORE, not the model — and that is load-bearing.**
   The first version was an Eloquent observer, which put `App\Models\Property` inside
   `app/Observers/` and turned the property store-boundary suite red. The boundary was
   right about the mechanism, not just the file: `PropertyStore` is the canonical write
   path, it already emits domain events, and `RecalculatePropertyOutstandingMortgage`
   is the established shape for exactly this job. **No allowlist entry was added** —
   the observer was a parallel mechanism beside one that already worked.

   Two consequences a receiver needs:
   - **`PropertyDeleted` and `PropertyUpdated` were extended.** Delete carried only
     `entityId` and the acting user, so the co-owner — Sarah's side, the whole point of
     W-0173 — was unreachable once the row was gone; it now carries `?int $jointOwnerId`.
     Update's `$changes` is `getDirty()`, the NEW values, so a listener reacting to a
     change of ownership could not see who was *removed*; it now carries
     `array $previous`. Both additive with defaults, and there were **no existing
     listeners on any Property event**, so the blast radius was nil.
   - **A `Property::factory()->create()` no longer syncs** — only store writes do. Every
     real write path goes through the store (which the boundary suite guarantees), so
     user behaviour is unchanged, but a test that arranges a property by factory and
     expects the column to move will not see it. The W-0173 suite drives `PropertyStore`
     end to end for that reason.

7. **The rental backfill migration bypasses the store, knowingly.**
   `2026_08_22_000100` imports `App\Models\Property` and queries it directly. Pest arch
   does not scan migrations so it is not red, but it is a bypass in spirit. It matches
   the allowlist's documented migration-style-backfill category
   (`BackfillPropertyOutstandingMortgage` is the precedent) and it has already run.
   Recorded rather than left to the suite's blind spot.

8. **Assumptions made, stated plainly:**
   - A joint-life policy's second life assured is the linked spouse. It is the only
     answer the schema supports (W-0200).
   - `charitable_donations` does not divide under a joint household — it is a Gift Aid
     input, not a running cost.
   - Mortgage-type `liabilities` rows count with the mortgages, not as "other". This
     removes a pre-existing **double count** in the protection debt need.

## 7. Environment

- Branch `dev`, shared working tree, other agents editing concurrently. **No commits,
  no PR, no deploy, no bundle rebuild, no tool-schema capture.**
- Tests: `DB_DATABASE=laravel_testing_b ./vendor/bin/pest <paths>`.
- Persona household David Jones id 16, Sarah Jones id 17 — **read-only throughout;
  every test uses fixtures.**
- **No browser verification of my own work, by instruction.** Quality certifies.

## 8. Files this batch owns

**New:** `app/Listeners/Property/SyncOwnerRentalIncome.php` ·
`app/Services/Protection/LifeCoverReach.php` · `app/Support/SharedExpenditure.php` ·
`database/migrations/2026_08_22_000100_sync_rental_income_to_every_owner.php` ·
`database/migrations/2026_08_22_000200_split_joint_expenditure_recorded_on_one_account.php` ·
`tests/Feature/Property/JointRentalIncomeReachesBothOwnersTest.php` ·
`tests/Feature/Protection/ProtectionDebtUsesUserShareTest.php` ·
`tests/Feature/Protection/JointLifePolicyReachesBothLivesTest.php` ·
`tests/Feature/UserProfile/JointExpenditureSplitsByDeclaredModeTest.php`

**Modified — backend:** `app/Providers/EventServiceProvider.php` ·
`app/Events/Property/PropertyUpdated.php` · `app/Events/Property/PropertyDeleted.php` ·
`app/Services/Stores/PropertyStore.php` ·
`app/Http/Controllers/Api/PropertyController.php` ·
`app/Http/Controllers/Api/ProtectionController.php` ·
`app/Http/Controllers/Api/UserProfileController.php` ·
`app/Http/Resources/Protection/LifeInsurancePolicyResource.php` ·
`app/Agents/ProtectionAgent.php` · `app/Agents/EstateAgent.php` ·
`app/Services/Shared/CrossModuleAssetAggregator.php` ·
`app/Services/Protection/CoverageGapAnalyzer.php` ·
`app/Services/UserProfile/UserProfileService.php` ·
`app/Services/Onboarding/OnboardingService.php`

**Modified — frontend:** `resources/js/components/Protection/PolicyCard.vue` ·
`resources/js/components/Protection/PolicyDetail.vue` ·
`resources/js/components/UserProfile/ExpenditureForm.vue`

**Modified — `/m`:** `resources/mobile/views/modules/Protection.vue` ·
`resources/mobile/views/modules/ProtectionPolicy.vue`

**Modified — tests:** `tests/Unit/Agents/ProtectionAgentTest.php` ·
`tests/Unit/Agents/ProtectionAgentGoalsTest.php` ·
`tests/Unit/Agents/EstateAgentGoalsTest.php` ·
`tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php` ·
`tests/frontend/components/Protection/PolicyCard.test.js`

## 9. Test evidence

| Run | Result |
|---|---|
| The four new suites | 8 + 7 + 8 + 10 = **33 passing** |
| Onboarding, profile, plans, tiers, gamification, Fyn expenditure | **1,102 passing** (3,973 assertions) |
| Estate, plans, protection, agents | **424** and **275 passing** |
| Net worth, shared, personal accounts, mobile | **172 passing** |
| Property, rental, income families | **59** and **57 passing** |
| Full vitest suite | **754 passing**, 62 files |
| `./vendor/bin/pint` on the touched paths | passed |
