# Tech Debt Report — Session 118 (30 April 2026)

**Files analysed:** 10 (8 modified + 2 new enums)
**Issues found:** 6
**Severity breakdown:** 0 critical, 2 warnings, 4 suggestions

---

## Critical Issues

None.

---

## Warnings

### W-1. Duplicate dividend-rate match across three call sites

**File / lines:** `app/Services/Tax/TaxStrategyCalculator.php:362-367`, `:403-407`, `:837-842`
**Category:** Duplicate Code

The same `match(band) → dividend rate` block is repeated three times across `buildAssetShiftingSuggestions`, `buildCrossSpouseSuggestions`, and `buildAllowanceRecommendations`. Each lifts `basic_rate` / `higher_rate` / `additional_rate` from `getDividendTax()` with the same fallbacks.

**Suggested fix:** Extract a private helper:
```php
private function dividendRateForBand(string $band): float
{
    $div = $this->taxConfig->getDividendTax();
    return match ($band) {
        'higher' => (float) ($div['higher_rate'] ?? 0.3375),
        'additional' => (float) ($div['additional_rate'] ?? 0.3935),
        default => (float) ($div['basic_rate'] ?? 0.0875),
    };
}
```
Replace the three sites with `$this->dividendRateForBand($band)`.

### W-2. Hardcoded marginal income-tax rates in `bandRateFor()` and income-band generators

**File / lines:** `app/Services/Tax/TaxStrategyCalculator.php:950-957` (existing pre-Phase 2), `:487, :522` (new Phase 2)
**Category:** Convention Violation (Rule #3 — no hardcoded tax values, use TaxConfigService)

```php
return match (...) {
    'basic' => 0.20,
    'higher' => 0.40,
    'additional' => 0.45,
};
```

`getIncomeTax()['bands']` already carries `rate` per band — this match could iterate the bands and pull the appropriate rate from config. Rule #3 says zero hardcoded tax. The new income-band strategies #1 / #2 also use literal `0.60` / `0.45` / `0.40` (lines 487, 522) — same root cause.

**Suggested fix:** Add a `bandRateFromConfig(string $band): float` helper that maps the band key to `$bands[*]['rate']`. The 60% effective rate could be derived as `higher_rate + (personal_allowance_taper_rate)` instead of literal `0.60`.

---

## Suggestions

### S-1. `TaxStrategyCalculator.php` is 988 lines — candidate for split

**File:** `app/Services/Tax/TaxStrategyCalculator.php`
**Category:** Complexity & Maintainability

The calculator now hosts four strategy generators (income-band, allowance, lifecycle, joint-savings) plus two legacy household builders, four allowance-grid builders, and helpers. At 988 lines it's well past the 500-line threshold and growing — Phase 3-5 will add more.

**Suggested fix (deferrable to Phase 4-5):** Extract per-category strategy classes:
- `App\Services\Tax\Strategies\IncomeBandStrategy`
- `App\Services\Tax\Strategies\AllowanceStrategy`
- `App\Services\Tax\Strategies\LifecycleStrategy`
- `App\Services\Tax\Strategies\JointSavingsStrategy`
- `App\Services\Tax\Strategies\HouseholdStrategy` (collapses `buildAssetShiftingSuggestions` + `buildCrossSpouseSuggestions`)

Each implements a small interface (e.g., `recommend(User $user, Context $ctx): array`); `TaxStrategyCalculator` becomes an orchestrator that fans out to the strategy classes and merges results.

### S-2. Magic threshold `> 1000` for "worth recommending"

**File / lines:** `app/Services/Tax/TaxStrategyCalculator.php:306, 791`
**Category:** Convention Violation (no magic numbers)

Both the existing `savings_to_spouse` and the new `isa_topup_vs_psa` use `if ($transferable > 1000)` to decide whether to surface. Same value, two sites, no shared constant.

**Suggested fix:** Add a class-level `private const MIN_TRANSFER_TO_RECOMMEND = 1000.0;` and reference it.

### S-3. Hardcoded Junior Pension £2,880 / £720 values

**File / lines:** `app/Services/Tax/TaxStrategyCalculator.php:644-645`
**Category:** Convention Violation (Rule #3) / Maintainability

```php
$juniorPensionNet = 2880.0;
$juniorPensionUplift = 720.0;
```

These are HMRC-fixed values (£2,880 net + 25% basic-rate relief = £3,600 gross). Could be derived from `pension_allowances` config, or at minimum carry an inline comment citing the HMRC source.

**Suggested fix:** Add a comment explaining the derivation (basic-rate relief × £3,600 gross = £720 uplift, £3,600 − £720 = £2,880 net), or expose them via TaxConfigService as `pension_allowances.junior_pension_net_cap` + `junior_pension_uplift`.

### S-4. `availableAnnualAllowance` ignores tapered AA (deliberate Phase 5 gap)

**File / lines:** `app/Services/Tax/TaxStrategyCalculator.php:906-913`
**Category:** Known limitation (already documented in docblock)

The helper docblock explicitly notes: "does not currently account for tapered AA (Phase 5) or carry-forward (Phase 4)." For high earners (adjusted income > £260k) the actual allowance can be as low as £10k. Strategies #1 / #2 may currently over-suggest contribution sizes for this segment.

**Suggested fix:** No action this session — Phase 5 (#14 Tapered AA) is the deliberate landing place. The docblock already flags it. Mention in CSJTODO so it isn't forgotten.

---

## Frontend / Tests / Enums — Clean

- `StrategyCategory.php` + `StrategyPriority.php` — both `declare(strict_types=1)`, backed string enums with `sortWeight()` helpers. No issues.
- `StrategyRecommendation.php` — clean enum-or-string ctor, public properties remain string-typed for back-compat. No issues.
- `taxStrategy.js` — new getters `recommendations`, `recommendationsByCategory`, `individualRecommendations`, `householdRecommendations`. Legacy getters dropped cleanly.
- `StrategyRecommendationList.vue` + `HouseholdView.vue` — multi-word component names, no hardcoded hex, no banned colours, no icons on banned surfaces. Use `eggshell-500`, `horizon-500`, `neutral-500`, `light-gray`, `spring-600` — all design-system tokens. ISA spelled out as "ISA" (allowed exception). All other acronyms expanded.
- Test files — `declare(strict_types=1)`, `RefreshDatabase` trait, Pest `it()` / `describe()` style. No issues.

---

## Top 3 Most Impactful

1. **W-1** — Extract `dividendRateForBand()` helper. 3 call sites → 1. ~15 min fix, high value (DRY).
2. **W-2** — Read marginal income-tax rates from `getIncomeTax()['bands']` rather than hardcoded `0.20/0.40/0.45`. Rule #3 compliance. ~30 min fix.
3. **S-1** — File split into per-strategy classes. Larger refactor but de-risks Phase 3-5 growth.

## Critical issues blocking commit

None. All changes are committable as-is. The two warnings are DRY/convention improvements; the four suggestions are deferred refactors.

---
*Generated by tech-debt-session skill — session 118, 30 April 2026*
