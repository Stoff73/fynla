---
id: W-0464
title: The Free-tier estate teaser runs a second, independent Inheritance Tax calculation that ignores pooling, gifts, charitable exemption, the residence cap and the taper
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, product-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [m, web]
created: 2026-08-23T13:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0154, W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
source: found while verifying W-0154 reached /m — the /m estate screen shows an Inheritance Tax liability only in teaser mode, and tracing where that figure comes from surfaced a second calculation
---

## Intent

`app/Services/Tiers/EstateIhtExposureDetector::detect()` computes an Inheritance Tax
liability of its own:

```php
$threshold = $nrb + $rnrb;                                   // single person, always
$netWorth  = $this->netWorthService->calculateNetWorth($user);   // this user alone
$estimatedLiabilityGbp = max(0.0, ($netWorth - $threshold) * $ihtRate);
```

**This is a second mechanism answering the question `IHTCalculationService` exists to
answer (Rule 20).** It reads its rates from `TaxConfigService` correctly — Rule 2 is
not the problem here — but the model behind it shares nothing with the real one:

| | `IHTCalculationService` | `EstateIhtExposureDetector` |
|---|---|---|
| Household | pools both spouses' estates | the logged-in user alone |
| Allowances | doubled where the household pools | single person, always |
| Gifts in the last 7 years | reduce the band, per member, capped | ignored |
| Charitable legacies | exempt, and can move the rate to 36% | ignored |
| Residence band cap (s8E(2)) | capped at the net value of the home | ignored |
| £2m taper | applied | ignored |
| Business Property Relief | capped relief (W-0091) | ignored |

**Where it is seen.** The `/m` estate screen renders it as *"Estimated Inheritance Tax
liability"* — the headline figure — for every Free user
(`resources/mobile/views/modules/Estate.vue:15-16`). In full (Premium) mode `/m`
shows estate value and composition and **no liability at all**, so this is the only
Inheritance Tax number `/m` ever displays.

**It is not obviously wrong to have a rough teaser.** The figure is deliberately a
simplification for a tier that cannot see the full calculation, and W-0154's fix does
not reach it — a household that is now correctly quoted one figure on web can still
see a different one on `/m` Free. Whether that gap is acceptable is a **product
decision**, which is why this is filed rather than quietly consolidated.

## Acceptance

1. **A decision, recorded:** does the Free teaser show (a) the real household figure,
   (b) a deliberately simplified figure that is labelled as an estimate in terms a
   user can act on, or (c) no figure at all? Today it shows an unlabelled precise-
   looking number derived from a model the application does not otherwise use.
2. If (a) or (b), the arithmetic comes from `IHTCalculationService` — one mechanism,
   with the teaser choosing what to DISPLAY rather than recomputing (Rule 20).
3. Whatever is shown, a married user's teaser and their web figure must not disagree
   without the difference being explained on screen — that is W-0154's acceptance 1
   applied across surfaces.
4. `tax-compliance-reviewer` on the wording: an unqualified "Estimated Inheritance Tax
   liability" of £X is a figure a user may act on.

## Working notes

(append-only)

- 2026-08-23 — Raised while verifying W-0154 reached `/m` (Rule 19). Not fixed in that
  batch deliberately: consolidating it changes what a whole pricing tier is shown, and
  that is CSJ's call rather than a fix-batch decision.
