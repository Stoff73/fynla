---
id: W-0020
title: Charitable bequest total checks bequest_type === 'specific', a value the enum cannot hold — cash legacies never reduce the IHT rate
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-21T08:50:00Z
claimed: 2026-08-21T09:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: []
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
reviewers: [tax-compliance-reviewer]
---

## Intent

Found by: persona run `peak_earners`, **premium sweep** (local `localhost:8000`),
while diagnosing W-0023.

**Surface:** backend, affects every surface that reports the IHT rate.

Severity: **high**, and it has a **tax consequence**: the reduced 36% inheritance tax
rate can never be triggered by a charitable **cash legacy**, by any entry route. On a
£409,280 baseline that is a 4-percentage-point difference on the taxable estate.

Flag for `tax-compliance-reviewer` to confirm the UK rule side once the code is fixed.

### Expected

A charitable cash legacy of £10,000 (persona `tests/Persona/peak_earners.md:544`,
Cancer Research UK) should count toward `getCharitableBequestTotal()`, and a legacy of
10%+ of the baseline should move the rate from 40% to 36%.

### Actual

`app/Services/Estate/WillAnalysisService.php:104-108`:

```php
if ($bequest->bequest_type === 'percentage' && $bequest->percentage_of_estate) {
    $total += $netEstate * ($bequest->percentage_of_estate / 100);
} elseif ($bequest->bequest_type === 'specific' && $bequest->specific_amount) {
    $total += (float) $bequest->specific_amount;
}
```

The `elseif` tests for **`'specific'`**. That value does not exist:

```
SHOW COLUMNS FROM bequests LIKE 'bequest_type'
→ enum('percentage','specific_amount','specific_asset','residuary')
```

Validation agrees — `app/Http/Requests/Estate/StoreBequestRequest.php:30` and
`UpdateBequestRequest.php:30` both allow only
`percentage,specific_amount,specific_asset,residuary`.

And the live data agrees — distinct values actually present in `bequests`:

```
percentage   specific_amount   specific_asset
```

So the `elseif` branch is **dead code**. A charitable cash legacy stored correctly as
`specific_amount` is silently skipped and contributes £0 to the charitable total.

Only the `percentage` branch works. A user can therefore reach the 36% rate by
leaving a *percentage* of the estate to charity, but never by leaving a *cash sum* —
which is how most charitable legacies are actually written.

### Interaction with W-0023

These compound. Right now a charitable legacy fails **twice**:

1. Entered via the will builder → never becomes a `Bequest` row at all (W-0023).
2. Entered via the Bequest API as `specific_amount` → the row exists but this method
   skips it (this item).

Both must land before a charitable cash legacy can reduce the rate. Fixing either
alone leaves the outcome broken.

### Evidence

**No screenshot** — backend logic. Evidence is the code, the schema, and the live
distinct values above, all quoted verbatim.

Observed effect, live, after recording the persona's £10,000 charitable legacy:

```
charitable_giving_percent = 0
charitable_deduction      = 0
iht_rate                  = 0.4
```

Report: `reports/R-05-premium-sweep.md`.

### Repro

1. Create a `Bequest` with `bequest_type = 'specific_amount'`, `specific_amount =
   50000`, `beneficiary_name` a registered charity, against a will whose baseline is
   under £500,000.
2. `WillAnalysisService::getCharitableBequestTotal($user, $netEstate)` returns `0`.
3. The IHT rate stays at 40% when it should qualify for 36%.

## Acceptance

- [ ] The comparison uses `'specific_amount'` — the value the enum, the validation and
      the data all actually use.
- [ ] Decide deliberately whether `specific_asset` charitable gifts should count, and
      handle or exclude them explicitly rather than by omission.
- [ ] A charitable cash legacy of 10%+ of the baseline moves the rate to 36%, verified
      end to end against the persona.
- [ ] A test pins this — a string typo against an enum is exactly what a test catches
      and review does not.
- [ ] `tax-compliance-reviewer` confirms the UK baseline/threshold treatment once the
      code is correct. (The **threshold arithmetic** is already right: baseline
      £1,059,280 − £650,000 NRB = £409,280, 10% = £40,928, correctly excluding RNRB.)
- [ ] Grep for other comparisons against `'specific'` or any non-enum bequest type.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found while diagnosing W-0023. Not fixed by me — routed
  to build-lead, flagged for tax-compliance-reviewer verification.
- Worth noting what this does NOT affect: the IHT calculation itself is correct. Gross
  estate, liabilities, NRB×2, RNRB×2, taxable estate and the 40% liability were all
  hand-verified against the persona and the database, and every allowance comes from
  `TaxConfigService`. Only the charitable-rate path is broken.

- 2026-08-21 build-lead: **FIXED**, browser-verified, tax-compliance-reviewer
  done. Handing to quality-lead. **Read the "not fixed" list at the bottom — the
  reviewer found four more errors in this same path that I did not action.**

  **The one-word fix.** `WillAnalysisService.php:156` now compares
  `'specific_amount'`. Grepped every `bequest_type` comparison across `app/`,
  `resources/js/`, `resources/mobile/`, `ios-native/` and `database/`: no other
  comparison against a non-enum value survives (acceptance 6 satisfied).

  **The one-word fix was NOT sufficient, and the board's own acceptance 3 would
  have failed.** tax-compliance-reviewer found — and I verified at
  `IHTCalculationService.php:1311-1313` — that the Inheritance Tax engine never
  called `getCharitableBequestTotal()` at all. `determineIHTRate()` read
  `IHTProfile.charitable_giving_percent`, a planning figure the user types on
  their profile, and nothing derived it from bequests. So the board's observed
  symptom (`charitable_giving_percent = 0`, `charitable_deduction = 0`,
  `iht_rate = 0.4` after recording the legacy) was caused by THIS disconnect, not
  by the enum typo. Two answers to "what is going to charity", never speaking
  (Rule 20).

  **Fixed:** `determineIHTRate()` (`:1330-1341`) now takes the total from
  `WillAnalysisService::getCharitableBequestTotal()`. Precedence is stated in the
  code: **the recorded will wins**, because the will is the instrument HMRC
  reads; the profile percentage remains the answer for a user with no bequests
  recorded. `charitable_deduction` flows from the same value, so the rate and the
  exemption cannot disagree.

  **Also fixed, same bug class, one hop downstream (reviewer F19).**
  `EstatePlanService.php:476` compared the analysis status against `'qualifies'`
  — a value `analyzeCharitableBequests()` cannot emit (it returns
  `below`/`at`/`above`). The estate plan therefore always read "Standard rate
  applies". It was unreachable before, since only a percentage gift could
  qualify; **my fix made it reachable**, so it landed here rather than later.
  `EstatePlanService.php:536`'s `current_percentage` read another key the
  analysis never emits and was pinned at 0.0 — now computed.

  **Charity detection consolidated to one home (reviewer F10/F11).**
  `WillAnalysisService::isCharitableBequest()` was a near-identical private copy
  of `Bequest::isCharitable()` — and had already drifted: the service's list
  treated **`'trust'`** as a charity indicator, so a "Smith Family Trust" or a
  "Will Trust" counted toward the charitable total. A gift into a family trust is
  a chargeable transfer, not an exempt one, so unlike every other defect here
  this one erred in the **unsafe** direction. The duplicate is deleted; the model
  method is the one home and carries a docblock saying why `'trust'` must not
  come back.

  **Rule 2 — the configured threshold was inert.**
  `charity_threshold_percent` is seeded (`TaxConfigurationSeeder.php:330`) and
  rendered in the admin Tax Settings screen as though it governs the calculation,
  and was read by nothing. Two new accessors —
  `TaxConfigService::getCharitableReducedRate()` and
  `getCharitableThresholdPercent()` — following the existing
  `getCLTLifetimeRate()` precedent, now read by `WillAnalysisService` and
  `IHTCalculationService`. That also removes two of the seven duplicated
  `?? 0.36` fallbacks the reviewer counted.

  **Baseline now uses the AVAILABLE nil rate band (reviewer F2).**
  `analyzeCharitableBequests()` re-derived a single £325,000 band while
  `IHTCalculationService` used the combined figure — two thresholds for one
  household. It now takes `$nrbAvailable` from the caller
  (`EstateAgent.php:194`), which passes `nrb_available`.

  **Unvalued charitable gifts no longer produce a wrong instruction.** The
  reviewer's sharpest point, and I agree with it: excluding `specific_asset` and
  `residuary` is a defensible NUMERIC decision, but this method's output is an
  *instruction* — "Increase charitable giving by £40,928 to qualify". For a user
  whose will already leaves a share of residue to charity, that tells them to give
  away five figures to buy a relief they may already hold. Understating is only
  "safe" when the output is a computation. `hasUnvaluedCharitableGifts()`
  (`WillAnalysisService.php:107`) now suppresses the instruction and returns
  `UNVALUED_CHARITABLE_GIFTS_MESSAGE` instead: we say we cannot put a figure on
  it and that a solicitor can. Silence over a wrong number. The exclusion comment
  was also corrected — it previously implied these gifts do not qualify; they DO
  qualify under IHTA 1984 s.23/Sch 1A, we simply cannot value them here.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).** Will
  builder driven end to end on a throwaway married pair: £10,000 to Cancer
  Research UK → `getCharitableBequestTotal()` returned **10000** (it returned 0
  before). Throwaway users deleted; **David (16) and Sarah (17) not modified.**

  **The pin the item asked for**, plus the end-to-end acceptance:
  `tests/Unit/Services/Estate/WillAnalysisCharitableBequestTest.php` — 13 tests.
  It scans the service source for every `bequest_type` comparison and asserts
  each literal is one the validation rules allow; it moves the configured reduced
  rate to 0.30 and asserts the analysis follows (a plain assertion against the
  same config element cannot detect a fallback and would not have pinned Rule 2);
  and three end-to-end cases prove `iht_rate` moves to the reduced rate from
  bequests alone with `charitable_giving_percent = 0`.

  **NOT FIXED — reported, needs its own items. All found by
  tax-compliance-reviewer, all pre-existing, all in this same path:**
  - **F3 (High):** the baseline ignores the s.18 spouse exemption and IHT-exempt
    assets. `EstateAgent::buildAssetSummary()` does not `reject(is_iht_exempt)`,
    unlike `IHTCalculationService.php:109-110`. For a married user leaving
    everything to their spouse the true Sch 1A baseline is ~nil, but we compute
    the whole estate less the band and tell them to give 10% of it.
  - **F4 (High):** `potential_saving = baseline * 0.04` is shipped to the user as
    a £ figure and matches neither the tax saved (~£31,105 on a £409,280
    baseline) nor the net cost (−£9,823). It is wrong by roughly a factor of two
    because the gift leaves the estate before the reduced rate applies.
  - **F9 (High):** charity determination is name-substring matching in
    production. `beneficiary_type` and `charity_registration_number` are never
    populated by any write path, so both structured checks are dead outside
    tests. "The Donkey Sanctuary" and "RNLI" are missed; "Cancer Consultants Ltd"
    is a false positive. Needs a form field, so it is a product decision.
  - **F14 (Medium):** `wills.spouse_bequest_percentage` is not updated by
    `markComplete()`, so a will leaving 60/40 still renders "100% to spouse".
  - **F8, F7, F17, F20, F21:** Sch 1A components not modelled; a £0/£0/£0 message
    on a sub-threshold estate; cache invalidation not spouse-aware on a mirror
    completion; `TaxSettingsController.php:330` reads `reduced_rate` where the
    key is `reduced_rate_charity`, so its hardcoded `0.36` fires 100% of the time
    in the admin panel for this very value.

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.

- 2026-08-21 build-lead: **cross-reference — F9 is now tracked on `W-0037`**, per
  team-lead's ruling that they are one defect seen from two ends (no write path
  populates `beneficiary_type`, so charitable status is inferred by substring-
  matching a beneficiary's name). W-0037 has been widened to name all four hops
  and raised to **high**, carrying F9's rationale. The remaining eight unactioned
  findings above (F3, F4, F7, F8, F14, F17, F20, F21) stay recorded here.

- 2026-08-21 build-lead: **a ninth consequence of this item's fix, found while
  building W-0046 and fixed there.** Making the Inheritance Tax rate read the
  recorded bequests meant every cache key derived from the old inputs went stale:
  `iht_calculations` is keyed on hashes of assets and liabilities only, so a user
  could record a charitable legacy, qualify for the reduced rate, and keep being
  served 40% from cache until their assets happened to change — this fix silently
  defeated. Closed by `IHTCalculationService::charitableBequestFingerprint()`
  (`:1535`), folded into the key in both `generateHashes()` and
  `saveCalculation()`. The pin was verified to fail without it
  (`Failed asserting that 0.4 is identical to 0.36`).

  **The generalisable rule, worth carrying beyond this item:** a fix that makes a
  calculation read a NEW input invalidates every cache key derived from the old
  inputs. Changing what a calculation depends on is a cache-key change.

- 2026-08-31 build-lead: **ALREADY FIXED — verified in the code.** `WillAnalysisService:206` now filters on `bequest_type = 'specific_amount'`, the value the enum can actually hold, and `:209`/`:224` use `['percentage','specific_asset','residuary']`. The charitable set itself comes from `Bequest::isCharitable()` rather than a beneficiary-type guess. A charitable cash legacy now counts toward the total, so the 10% test can reach the reduced rate. The comment at `:265` records the dead branch the enum never supported.
