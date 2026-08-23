---
id: F-0032
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-23T04:00:00Z
status: active
---


**Rule 22 handover for this agent:** [`HANDOVER-fix-cycle4-wills-2026-08-23.md`](HANDOVER-fix-cycle4-wills-2026-08-23.md) — what is not on the board, the branches this persona cannot reach, and the dead ends.
# F-0032 — Cycle 4: rate literals, and the charitable denominator

**Agent:** build-lead (`fix-cycle4-wills`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0432 (the sweep), W-0433 (the denominator) · **ID block:** W-0451 – W-0460
**Predecessor, read first:** `F-0031-cycle4-charitable-figures.md` — W-0431 fixed
three rate messages in one method; its tax-compliance gate then found that four
more instances existed and that **two of them were not literals in prose at all.**

---

## 1. THE HEADLINE LESSON — the config-key assertion would have passed against all three defects

**Stated first because it is the most useful thing in this batch, and because it
happened to me an hour after I wrote the decoy check into `F-0031`.**

`ContributionWaterfallService` had **zero test coverage**. My first draft of its
test asserted that the configured Lifetime ISA bonus rate moved when the
configuration moved:

```php
expect(app(TaxConfigService::class)->getISAAllowances()['lifetime_isa']['government_bonus_rate'])
    ->toBe(MOVED_LISA_BONUS);
```

**That proves the config key is readable. It says nothing whatever about whether
the waterfall reads it.** A test named after a service it never calls — the
**Decoy** variant of `tests/CLAUDE.md` §4, in a brand-new file, written by the
person who had just documented the variant.

**It is not carelessness, it is structural.** The natural way to test *"is this
value configuration-driven?"* is to assert the configuration value — and **that
assertion passes against every defect it is meant to catch.**

Rewriting it to drive the real `allocate()` **immediately found a third hardcoded
`25%` I had not patched**, in the headline explanation the user actually reads
(`:210`), beside the arithmetic (`:155`) and the two decision-trace entries.
Patching two of three would have shipped a card contradicting its own
explanation.

> **The config-key assertion would have passed against all three defects.**

**The check that follows from it:** when testing that a value is
configuration-driven, the test must call **the code that consumes it**, not the
accessor that supplies it. Asserting the supply is asserting the thing that was
never broken.

---

## 2. Prior art

Checked 2026-08-23 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| every rate literal | `TaxConfigService::getCharitableReducedRate()` / `getCharitableThresholdPercent()` / `getISAAllowances()` — all existing, all already the one home | **route** ×9 |
| the charitable percentage | `EstatePlanService::charitablePercentage()`, already measuring against the baseline **with a docblock saying so** | **route** — the Inheritance Tax card onto the definition that existed |
| the second threshold home | `PlanConfigService::getCharitableGivingThreshold()` reading its own config key | **retire** — delegate to the tax configuration |

**Nothing here added a mechanism.** Every fix routes a consumer onto a home that
already existed, and one deletes a home that should not have.

---

## 3. A scope error in the dispatch, flagged rather than followed

The dispatch placed `ContributionWaterfallService` in
**`app/Services/Retirement/`** — a directory it fenced in the same message
("coordinate with me before touching anything in `app/Services/Retirement/`").

The file is at **`app/Services/Investment/Recommendation/`**. Checked before
opening anything. **Following the asserted path would have walked me into a live
agent's fenced directory looking for a file that was never there.**

---

## 4. What changed, in the dispatch's priority order

| | Change |
|---|---|
| **1. `EstatePlanService:517`** — a wrong statement of law, live | *"Reduced rate of 36% applies: **10% or more of the estate above the nil rate band** is left to charity."* Schedule 1A compares against the **baseline**, and "the estate above the nil rate band" is the baseline in plain words — the same quantity `WillAnalysisService:55` computes. Rates and threshold from configuration. **This renders on `/plans/estate` and in printed plans.** |
| **2. `WillAnalysisService:74`** — a rate in ARITHMETIC | `$baseline * ($standardRate − $reducedRate)`. At a 31% reduced rate the true differential is 9 points, so `* 0.04` understated the saving **by more than half**, in a figure a user acts on. |
| **3. `PlanConfigService:168`** — a second config home | Delegates to `TaxConfigService`. A statutory threshold is not a plan preference. |
| **4. `WillAnalysisService:348/351-353`** | The eighth `?? 0.36` routed; the threshold from configuration; **five Rule 9 "IHT" instances spelled out.** |
| **5. `GiftingStrategy:213/219`, `IHTPlanning.vue:599`, waterfall ×3** | A **ninth** `?? 0.36`; three literals in one `<li>`; three in one waterfall step. |
| **6. W-0433** | The percentage measured against the **baseline**: 0.6% → **0.81%**. |

### The completion note that was hiding the ninth instance

`TaxConfigService`'s docblock recorded the `?? 0.36` duplication as consolidated
across six sites. **Two sites were still standing.** The note is corrected in the
same change, because:

> **A completion note is load-bearing. If a consolidation leaves a survivor, the
> note is the thing that hides it** — a reader checking whether the duplication
> was dealt with finds a docblock saying it was, and stops.

### Deliberately NOT changed, and flagged instead

**`potential_saving` still uses the baseline as its base.** The baseline
approximates the taxable estate — it deducts the nil rate band but not the
residence band or the charitable gift. **This fix makes the RATE come from
configuration; it does not re-derive the formula**, because that would move a
published figure inside a Rule 2 pass. Raised as the reviewer's **Q2**.

---

## 5. A possible seventh axis — the control-flow literal

**`GiftingStrategy:214` hardcoded the threshold inside the gate that decides
whether the recommendation is produced at all:**

```php
if ($charitablePercent < 10 && $currentIHTLiability > 0) {
```

**The literal governs whether a user is ADVISED, not what a sentence says.** Move
`charity_threshold_percent` to 12% and the calculation changes, the message
changes — and a household between 10% and 12% is silently never told it could
qualify, because the recommendation is not generated.

**Neither sweep pass finds it.** It is not a percentage in prose (no `%`, no
user-facing string) and it is not a rate in arithmetic (it multiplies nothing —
it is a comparison). `app/Http/CLAUDE.md`'s axes are all about a value's journey
between layers; **this is a value deciding whether a journey happens.**

Recorded in W-0432 for the reviewer to see. **Not asserted as an axis** — that is
team-lead's and the reviewer's call, and one instance is an observation, not a
pattern.

---

## 6. Tests

| File | Cases | Guards |
|---|---|---|
| `tests/Unit/Services/Estate/RateLiteralsComeFromConfigurationTest.php` | 5 | **both passes** — a rate in arithmetic and a rate in prose; the Lifetime ISA bonus **through the real `allocate()`**; one home for the threshold |
| `tests/Unit/Services/Estate/CharitableExemptionVersusRateTestTest.php` | +2 | the percentage **reconciles** with the baseline and amount it is published beside, and does **not** measure against the net estate |
| `tests/Unit/Services/Plans/EstatePlanRefactorTest.php` | +1 | the statement of law — **the only assertion that has ever existed on that string's content** |

### Every rate moves, and none of them is a value anything else uses

**41% / 31% / 12% / 35%.** F-0031's guard moved two of three and silently
certified the third; this one has four rates and moves all four. **A guard that
moves a subset of its inputs certifies the remainder.**

Fixtures are chosen so no wrong answer lands on a right one: the potential saving
goes **£27,000 → £67,500** (not a multiple), the Lifetime ISA bonus **£1,000 →
£1,400** on an unchanged £4,000 allowance.

### The denominator is asserted as a RECONCILIATION, not a literal

`percent ÷ 100 × baseline == rate_test_amount`. On many households the baseline
is a round fraction of the net estate, so a wrong denominator lands on a
plausible number; a reconciliation is right whatever the household. The companion
case asserts the two denominators **give different answers here**, so it cannot
silently stop discriminating.

### Six mutations, each disjoint

| Mutation | Red |
|---|---|
| `$baseline * 0.04` restored | the arithmetic case |
| plan-config second home restored | the one-home case |
| net-estate denominator restored | the 2 W-0433 cases |
| the wrong statement of law restored | the statement-of-law case |
| waterfall bonus re-hardcoded in arithmetic | the Lifetime ISA case |
| waterfall bonus re-hardcoded **in prose only**, arithmetic left correct | the Lifetime ISA case |

**The last two are the pair worth having.** They prove the test sees the figure
and the sentence independently — which is exactly what the first draft could not
do.

### The exclusion list survived into the guard

`NetWorthAnalyzer`'s "over 30% of total wealth", `LifePolicyStrategyService`'s
"60% equities, 40% bonds", `ScenarioService`'s descriptions and the waterfall's
own `$remaining * 0.25` allocation heuristic are **not tax values** and are
untouched. **Rule 2 governs tax values, not every number in a sentence** — a
guard written to "no percentage literals" would manufacture defects and then
defend them with a green suite.

**Suites:** `Unit/Services/Estate`, `Feature/Estate`, `Unit/Services/Plans`,
`Feature/Plans`, `Unit/Services/Investment` → **770 passed (2468 assertions)**.
Vitest Estate → **23 passed**. Pint clean. `DB_DATABASE=laravel_testing_t`.

---

## 7. A fourth instance of the join defect — the first one prevented

Writing `charitableThresholdLabel` I reached for `ihtData.charitable_baseline`.
**It was published by the service, carried on `calculation`, and absent from the
component's hand-written mapping** — the same allowlist that dropped
`charitable_rate_test_amount` in F-0031.

**Checked before committing rather than after the browser found it.** Fourth
instance in two batches, first one caught in advance. The tell did the work:
*a key the view-model needs that the payload's mapping does not enumerate.*

---

## 8. What is owed

- **`tax-compliance-reviewer` — routed, not yet reported.** Nothing moves to
  `handoff` until it does. Two questions passed rather than answered:
  **Q2** whether the baseline is the right base for `potential_saving`, and
  whether leaving the plans-screen threshold key **seeded but inert** carries any
  tax risk.
- **Product, not tax — escalated to CSJ by team-lead:** whether retiring the
  plans-screen threshold control needs a product sign-off. **A statutory value
  should not be settable from two admin screens, but one of them is now inert and
  someone owns that decision.**
### The rendered page — done 2026-08-23, with what it could NOT show

Tab established as **Sarah Jones (17)** per token before reading. Caches cleared.

**`/plans/estate`** — `Standard Inheritance Tax rate of 40% applies.` ·
`Threshold for 36% Rate: 10%` (now from `TaxConfigService`) · *"Increase
charitable giving by £13,928 to qualify for the reduced 36% Inheritance Tax rate
and save £9,571."*

**`/estate`** — the household/second-death pair renders, the percentage reads
**0.8%** (was 0.6%), and **`/\bIHT\b/` is false across the whole page.**

Screenshots `160-` and `161-`.

### FOUR THINGS THE BROWSER DID NOT EXERCISE — stated rather than implied

**A green screenshot of a page that never entered the branch would be worse than
no screenshot**, so each is named:

1. **The corrected statement of law** — the batch's top-priority fix. This estate
   is on the **standard** branch, so *"Reduced rate … of the estate above the nil
   rate band"* **never renders on this household.** Correct in law per the gate;
   unexercised by the page. Both true.
2. **`IHTPlanning.vue:599`** — inside `v-if="!secondDeathData?.mitigation_strategies"`.
   The server supplies strategies here, so the block is **suppressed entirely**.
3. **The `const` unvalued-gifts sentence** — no unvalued charitable gift on this
   household.
4. **The C2 profile branch** — no profile percentage with zero recorded bequests.

All four are covered by tests only.

### What the browser found that no test could

**`/plans/estate` shows a charitable rate of 4.2% on a page whose own Net Estate
row reads £1,728,780, while `/estate` shows 0.8% for the same household in the
same session.** One surface disagreeing with itself. Raised to HIGH as **W-0452**,
and **my original filing of it was backwards** — the numerator is identical and
the denominators differ 5×, not the reverse.

## 9. In flight

**Nothing.** Every edit applied, linted, covered and mutation-tested. Scratch
files outside the repository. No commit, no PR, no git lock.
