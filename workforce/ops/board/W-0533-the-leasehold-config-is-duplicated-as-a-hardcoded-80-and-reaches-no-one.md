---
id: W-0533
title: The leasehold and tenure configuration has no consumer — and the one calculation that should read it hardcodes its threshold as 80 and is itself rendered nowhere
mission: board-verification-31-august
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
severity: medium
surfaces: [web, m]
created: 2026-09-04
source: found while working W-0498, 2026-09-01; sharpened 2026-09-04
prior_art_checked: 2026-09-04
prior_art_found: [W-0463, W-0498]
prior_art_outcome: extends — W-0498 proved this cluster's other half was a Rule 20 duplicate, not dead config. So is this half.
constitution_refs: [07-quality-bar]
---

## Intent

W-0498 closed the `joint_ownership_types` half of `property_ownership` and left a
finding: **`tenure_types` and `leasehold_reform` have no consumer anywhere in
`app/`.** They were deliberately not registered in `UNIMPLEMENTED_RULES`, because
that would have recorded them as decided when they were not.

Verified 2026-09-04, and the finding as stated was **understated**. This is not
config waiting for a feature. The feature exists, holds the same number, and
reaches nobody.

## Evidence

**The configuration** — `database/seeders/TaxConfigurationSeeder.php:1106-1132`,
live on the active tax configuration:

```php
'leasehold_reform' => [
    'ground_rent_abolished_date' => '2022-06-30',
    'valuation_thresholds' => [
        'difficult_to_mortgage'    => 80,   // years remaining
        'significant_value_loss'   => 60,
    ],
],
'tenure_types' => ['freehold' => [...], 'leasehold' => [...]],
```

**The accessors** — `TaxConfigService::getLeaseholdReform():789` and
`getLeaseholdValuationWarnings(int $remainingYears):799`, which reads the two
thresholds and returns warnings. Neither is called from anywhere in `app/` or
`resources/`; the only other hits are the docblock at `:761` and W-0498's own
test file.

**The data is real.** `properties` carries `tenure_type`, `lease_remaining_years`
and `lease_expiry_date` (`app/Models/Property.php:38-40`), and
`PropertyForm.vue` collects them — so users have answered this.

**The duplicate.** `app/Services/Property/PropertyCalculationService.php:19-23`:

```php
if ($property->tenure_type !== 'leasehold') { return false; }
return $property->lease_remaining_years !== null && $property->lease_remaining_years < 80;
```

**80 is the configured `difficult_to_mortgage` threshold, hardcoded.** This is
Rule 2 and the W-0498 shape exactly: the config is not orphaned, it is *copied*.

**And even the copy is inert.** Its only caller is
`Property::isLeaseholdExpiringAttribute():210`, and `is_leasehold_expiring` is
read by nothing — no Resource, no view-model, no component on either surface. A
user with a 62-year lease is told nothing, on any surface, by any of it.

## Acceptance

1. `isLeaseholdExpiring()` reads the threshold from
   `getLeaseholdValuationWarnings()` rather than the literal `80`, so there is one
   number (Rules 2, 20).
2. The `significant_value_loss` band (60) is not silently dropped — either both
   bands reach the user or the config records why only one does.
3. The warning reaches a screen on **web and `/m`** (Rule 19), or a decision is
   recorded here that it should not, in which case the accessors join
   `UNIMPLEMENTED_RULES` with that reasoning attached.
4. `ConfiguredRulesHaveConsumersTest` covers `property_ownership` — the audit is
   already written into that file at `:62` and adding the area to `GUARDED_AREAS`
   turns it red today.
5. `tenure_types` is separately decided: it is a **label and description** cluster,
   not a tax rule, and may belong in the form rather than the tax configuration.
