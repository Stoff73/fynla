---
id: W-0461
title: The Rule 2 sweeps swept app/ and exactly one Vue file — nine rate literals stand in the estate frontend, five of them live, one of them a wrong statement of the statutory test
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
claimed_by: null
severity: medium
surfaces: [web]
created: 2026-08-23T06:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0371, W-0431, W-0432, W-0451, W-0452, W-0399-verdict-C4]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found by build-lead (fix-cycle4-figures) while completing F-0033 — sweeping every LINE that reads what W-0451 centralised, per the 2026-08-23 addition to FORMATS.md
---

## Intent

**The Rule 2 charitable family has been swept twice and declared closed twice.
Both sweeps covered `app/` and exactly one Vue file** — `IHTPlanning.vue`, and
only because it was already open for another reason.

W-0431 fixed three messages in `IHTCalculationService`. W-0432 found seven more
across the estate services. The guard both left behind,
`RateLiteralsComeFromConfigurationTest`, moves four rates and asserts on **service
output**. Its companion `CharitableExemptionVersusRateTestTest` does the same.

> **No guard in the codebase moves a configured rate and asserts on a Vue
> template.** So `resources/js/components/Estate/` and
> `resources/js/components/Plans/` sit entirely outside the family that has been
> swept twice — a fourth structural blind spot beside the three W-0432 recorded
> (decimals in arithmetic, literals in comparisons, rates in a `const`).

**This is not a fourth instance of the same miss. It is the same miss at a
different scope:** a fix lands where the defect was noticed and stops at the edge
of the diff, and both sweeps were reading PHP.

### Found how

Completing F-0033 (W-0451 / W-0452), following `FORMATS.md`'s instruction to
*check every LINE that reads the thing you centralised* rather than every layer.
The centralised quantities are the charitable rate, the threshold and the saving;
`grep` for their literal forms across the estate and plans frontends returned
nine hits in seven files.

### Reachability was CHECKED, not assumed — and it splits the list in half

Every component was traced to whether anything mounts it. **Three of the nine sit
in components nothing mounts.** A sweep that greps `resources/` and files
everything it finds over-reports by a third; one that checks reachability gets a
shorter, sharper list. Both halves are recorded, because a dead component is a
landmine rather than a non-issue — the literal becomes live the moment someone
mounts it, and nothing will flag it then.

### LIVE — mounted, and rendering today

| # | Site | Literal | Reaches |
|---|---|---|---|
| 1 | `resources/js/components/Estate/IHTPlanning.vue:246` | *"The **10%** test that decides the reduced rate…"* | `/estate`, whenever the pooled exemption and the rate-test amount differ — **which is true of the peak_earners household this cycle is testing** |
| 2 | `resources/js/components/Plans/Estate/EstateCurrentSituation.vue:83` | *"Threshold for **36%** Rate"* | `/plans/estate` and `/plans/holistic`, mounted by `EstatePlanContent.vue` and `HolisticPlanContent.vue` |
| 3 | `resources/js/components/Plans/Shared/planPrintMixin.js:2290` | *"Threshold for **36%** Rate"* | the printed plan — **the same label, a second time, in a second mechanism** (Rule 20) |
| 4 | `resources/js/components/Estate/IHTPlanning.vue:609` | *"the Home Allowance (up to **£175,000**)"* | `/estate`, when the residence band is zero. **This is the W-0399 verdict's C4 item 4** (`:596` at the time), still standing after F-0031 and F-0032 |
| 5 | `resources/js/components/Estate/TrustPlanningStrategy.vue:52, 165` | *"Tax recalculated at **40%** death rate"*, *"If Death Within 7 Years (**40%**)"* | mounted by `TrustPlanning.vue` |

**Item 1 is the sharpest of the live set** because of where it came from: that
sentence was **written by F-0031** as part of the W-0399 fix, to explain the
statutory distinction the tax reviewer had ruled on — and it hardcoded the
threshold in the same breath. The batch that made three server messages
configuration-driven authored a fourth message with a literal in it, one file over.

**Items 2 and 3 are the same label in two mechanisms**, on the card whose figures
F-0033 has just corrected. A user moving between the screen and the printed plan
sees the same wrong rate twice, which reads as corroboration.

### DEAD — in components nothing mounts. Landmines, not non-issues

| # | Site | What is wrong | Why it is worse than a literal |
|---|---|---|---|
| 6 | `EstateOverviewCard.vue:158` | `ihtValue = this.futureTaxableEstate * 0.40` | **A rate in ARITHMETIC, in the frontend.** The same class as the `* 0.04` that started this whole family and the one a prose sweep is structurally blind to. It computes a **displayed Inheritance Tax liability**, so under any configuration move it would show a wrong figure rather than a wrong caption. Mounted by nothing today. |
| 7 | `IHTMitigationStrategies.vue:164` | *"Required charitable bequest: £X (**10% of estate**)"* | **A WRONG STATEMENT OF LAW.** Schedule 1A para 5 compares the donated amount with 10% of the **baseline** — the estate less the available nil rate band, donation added back — not with 10% of the estate. **This is verbatim the defect the 2026-08-23 verdict cleared in `EstatePlanService:517`**, surviving in a Vue component because that batch swept `app/`. |
| 8 | `IHTMitigationStrategies.vue:167` | *"reduces Inheritance Tax rate from **40%** to **36%**"* | two rate literals in the same block |
| 9 | `GiftingTimelineChart.vue:61, 69` | *"**40%** (full rate)"*, *"**24%** (**40%** relief)"* | the seven-year taper table |

**Items 7 and 8 are unreachable twice over**, and the second reason is its own
finding: they sit inside `v-if="strategy.charitable_amount_required"`, and
**`charitable_amount_required` is published by nothing** — `grep` across `app`
and `resources` finds the key only in that one template. **A `v-if` gated on a key
no payload carries is structurally unrenderable**, which is `app/Http/CLAUDE.md`
axis 7 one degree worse than the `MortgageResource` case: there a field was
dropped by a Resource; here it never existed.

## Impact

**Today:** five live labels can contradict the figures beside them the moment
anyone moves a configured rate from the admin screen, and item 1 does so on the
household this cycle is testing. Nobody is told a wrong **amount** — every figure
beside these labels is computed from configuration and is correct. **This is the
same severity shape as W-0399 and W-0433: correct arithmetic, wrong caption.**

**On a configuration move:** item 6 publishes a wrong **figure**, not a caption —
if anything ever mounts it.

**Structurally:** the family has been declared closed twice while nine instances
stood one directory over. **A completion note is load-bearing**, and two of them
now say "closed" about a sweep that never entered `resources/`.

## Acceptance

1. **A guard that moves a configured rate and asserts on a rendered Vue
   template.** Without it this recurs — every existing guard drives PHP and
   asserts on service output, so re-hardcoding any of the nine leaves the whole
   suite green. **This criterion is the item**; the nine fixes are the easy half.
2. The five LIVE instances read their rate from the store's tax-configuration
   getters, as `IHTPlanning.vue:606` already does after F-0032.
3. **Items 2 and 3 converge on ONE source** rather than being edited in lockstep —
   the same label in a component and in a print mixin is Rule 20 in miniature.
4. **Item 7's statement of law corrected to the baseline**, and reviewed as a
   statutory statement rather than as a literal — it is the same sentence the
   2026-08-23 verdict ruled on for `EstatePlanService`.
5. **A decision on the three dead components** (`EstateOverviewCard`,
   `IHTMitigationStrategies`, `GiftingTimelineChart`) — deleted, or mounted and
   fixed. Leaving a wrong statement of law in an unmounted component is a
   deferral, and it should be a recorded one.
6. **`charitable_amount_required` decided**: either published by the service that
   should own it, or the block that gates on it removed. A `v-if` on a key nothing
   emits is dead by construction and reads as live code.
7. `tax-compliance-reviewer` on item 7 only. Items 1–5, 8 and 9 are captions over
   correct figures and need no statutory ruling.

## Working notes

**2026-08-23 — build-lead (`fix-cycle4-figures`).** Filed from F-0033, not fixed
in it. **Not folded into that batch deliberately:** neither W-0451 nor W-0452
names these files, both items are held at `claimed` awaiting a statutory gate, and
widening a diff into two surfaces the gate was not asked about is how a cheap
review becomes an expensive one. The same reasoning F-0032 used for W-0451 itself.

**Reachability for all nine was traced before filing** — `grep` for each
component's name across `resources/js`, excluding its own definition. Recorded
because the reachability is half the finding and because asserting it without
checking is what this cycle keeps punishing.

Related and NOT part of this item: `app/Services/Estate/GiftingStrategy.php:227`
computes the charitable rate saving as a fifth mechanism, in a method
(`recommendOptimalGiftingStrategy`) with **zero production callers**. Recorded in
F-0033 §7.

## Addendum — 2026-08-23, tax-compliance-reviewer verdict on W-0451/W-0452, condition C3

**The gate independently found the same family and added a tenth instance, in
PHP, that this item's frontend-only framing did not reach.**

### Tenth instance — admin-facing, and named by its own consolidation note

**`app/Http/Controllers/Api/TaxSettingsController.php:330`**

```php
'reduced_rate' => sprintf('%g%% (if 10%%+ to charity)', ((float) ($iht['reduced_rate'] ?? 0.36)) * 100),
```

**Two literals in one line** — a `?? 0.36` fallback and a hardcoded `10%+`
threshold — **inside the admin screen that displays the tax settings themselves.**
The one place a hardcoded rate contradicts the very configuration being rendered.

**It was named by `TaxConfigService::getCharitableReducedRate()`'s own note as one
of four survivors, and three of the four were fixed.** The note did not record
that this one was left standing — corrected 2026-08-23 under C4, which now names
it explicitly as the known survivor.

Admin-facing, so **lower severity than the five live user-facing instances above**,
but it belongs to this item's family and is listed here so the next sweep does not
find it and assume the family was never swept.

### Severity of item 2 restated by the gate

**`IHTPlanning.vue:609` (`up to £175,000`) is W-0399's C4 item 4 RE-OPENED, not
new.** It has now been named by **two verdicts** and survived **three batches** —
F-0031 named it, F-0032 edited the `<li>` directly above it, F-0033 edited the
same block again. The residence nil-rate band is frozen only until April 2028.

### And a check that degraded as it was documented

`getCharitableReducedRate()`'s note prescribes `grep -rn '?? 0.36' app/` as the
durable check. **That grep now returns five hits, four of which are the comments
written to explain the fixes — two of them inside the note itself.** The note is
corrected to filter comments, and the observation is recorded because it is a
fourth way a completion claim stops a reader looking:

> **A grep-based check degrades as the fix it checks for gets documented.**

It took one cycle to appear.


## Rolled under W-0463 — 2026-08-23

The frontend scope gap recorded here is folded into **W-0463** acceptance 2. This item
found that both Rule 2 sweeps read PHP and one Vue file; W-0463 adds the other half —
a configured rule with NO consumer emits no literal to find and no output to move, so
it is invisible to `RateLiteralsComeFromConfigurationTest` as well as to a grep.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev` — ALL NINE instances stand, the
  acceptance's own criterion 1 is not met, and instance 6 has GROWN.**

  **The five LIVE instances, each still literal:**
  1. `IHTPlanning.vue:246` — *"The **10%** test that decides the reduced rate…"*
  2. `EstateCurrentSituation.vue:83` — *"Threshold for **36%** Rate"*
  3. `planPrintMixin.js:2290` — the same label, second mechanism (Rule 20). The comment at
     `EstateCurrentSituation.vue:103` now acknowledges the duplicate and it is still a duplicate.
  4. `IHTPlanning.vue:621` — *"the Home Allowance (up to **£175,000**)"*
  5. `TrustPlanningStrategy.vue:52` and `:165` — *"40% death rate"*, *"If Death Within 7 Years (40%)"*

  **The four DEAD instances also stand**, in components nothing mounts:
  `IHTMitigationStrategies.vue:164` (still the wrong statement of law — "10% **of estate**") and
  `:167`; `GiftingTimelineChart.vue:61`/`:69`.

  **Instance 6 is now WORSE than filed.** `EstateOverviewCard.vue` carries
  `this.futureTaxableEstate * 0.40` at **:158 AND :169** — two sites, not one. A rate in
  arithmetic in the frontend, computing a displayed liability, and still the class a prose sweep
  is structurally blind to.

  **Criterion 1 — "a guard that moves a configured rate and asserts on a rendered Vue template" —
  is NOT met, and this criterion IS the item.** The nearest thing,
  `tests/frontend/components/Estate/IHTPlanningRateLabel.test.js`, belongs to W-0132: it calls
  `IHTPlanning.computed.ihtRateLabel` directly, deliberately not through a mount, and asserts on
  the FIELD the label reads. It moves no configured rate and renders no template. Re-hardcoding
  any of the nine still leaves the whole suite green.

  **Convergence worth recording:** W-0432 was re-measured the same day and its only two survivors
  are this item's instances 4 and the admin-facing `TaxSettingsController:330` tenth instance. The
  charitable rate family is now down to ONE remaining set, owned here.

- 2026-08-31 build-lead: **CLOSED — all nine instances resolved and criterion 1 met.**

  **Criterion 1, which the item says IS the item — a guard that moves a configured rate and
  asserts on a RENDERED Vue template — now exists:**
  `tests/frontend/components/Estate/RateLiteralsInRenderedTemplates.test.js`. It mounts the
  components with 31% standard / 29% reduced / £190,000 residence band and asserts on
  `wrapper.text()`. **Mutation-verified:** re-hardcoding `£175,000`, `Threshold for 36% Rate`
  (screen), `Threshold for 36% Rate` (print) and `40% death rate` each turns a distinct test red.

  **The five LIVE instances (criterion 2):**
  1. `IHTPlanning.vue:246` — now `{{ charitableThresholdLabel }}`, derived from the payload's
     own `charitable_threshold` / `charitable_baseline`.
  2. `EstateCurrentSituation.vue:83` — now `{{ charitableThresholdRateLabel }}`.
  3. `planPrintMixin.js:2291` — calls the same composer. **Criterion 3 met:** both mechanisms
     import `charitableThresholdRateLabel` from `resources/js/utils/estateRateLabels.js`, the
     one source. The `EstateCurrentSituation.vue:103` comment that recorded the duplicate as
     outstanding was corrected in the same edit.
  4. `IHTPlanning.vue:621` — now `{{ formatCurrency(ihtResidenceNilRateBand) }}`. The W-0399
     C4 item 4 survivor, named by two verdicts, is gone.
  5. `TrustPlanningStrategy.vue:52` and `:165` — both now `{{ ihtStandardRateLabel }}`,
     a computed over the `ihtStandardRate` getter.

  **Criteria 5 and 6 — CSJ decided 2026-08-31: delete all three dead components.** Reachability
  re-confirmed by grep across `resources/js` and `resources/mobile` — zero references to any of
  them outside their own definitions. Deleted with their tests:
  `EstateOverviewCard.vue` (instance 6, the rate in arithmetic, which had GROWN to :158 and :169),
  `IHTMitigationStrategies.vue` (instances 7 and 8 — the wrong statement of law goes with the
  file, and criterion 6's `charitable_amount_required` `v-if` with it; grep confirmed the key was
  published by nothing), and `GiftingTimelineChart.vue` (instance 9).
  **Criterion 7 is moot** — item 7's statement of law was deleted rather than re-worded, so there
  is no statutory statement left to rule on.

  **The tenth instance, `TaxSettingsController:330`, was WORSE than filed.** It read
  `$iht['reduced_rate']`, but `TaxConfigurationSeeder:329` seeds `reduced_rate_charity` — the key
  never matched, so the `?? 0.36` literal was the ONLY thing producing that figure. The admin Tax
  Settings screen was ignoring the admin's own setting. Both the key and the hardcoded `10%+`
  threshold are fixed; the threshold now reads `charity_threshold_percent`.

  Commit `ad048def1`. 1,234 frontend tests green; 137 tax-config PHP tests green.
