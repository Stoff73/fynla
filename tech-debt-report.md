# Tech Debt Report — Session 2026-05-13 (post-PR #291 merge)

**Files analysed:** 3
**Issues found:** 7
**Severity breakdown:** 1 critical, 4 warnings, 2 suggestions

Scope: the three files touched in PR #291 (`audit-rebalancing-cgt-rates → dev`).

```
app/Services/Investment/Rebalancing/TaxAwareRebalancer.php
app/Http/Controllers/Api/Investment/RebalancingCalculationController.php
tests/Unit/Services/Investment/Rebalancing/TaxAwareRebalancerCgtRateTest.php
```

PR #291 made the CGT **rate** fail-loud (Wave 2.5 BADR-sibling pattern). The same files contain CGT **allowance** sibling fallbacks that were left in their pre-existing soft-fallback shape (`?? 3000`). Those are the dominant finding here — the rate fix surfaced an exact-twin sibling on the same lines of code.

---

## Critical Issues

### 1. Hardcoded `3000` literal caps `allowance_used` in the API response (Rule #3 violation)

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php:456`
- **Category:** Convention violations (Key Rule #3 — no hardcoded tax values)

```php
'allowance_used' => min($cgtResult['cgt_analysis']['total_gains'] ?? 0, 3000),
```

This is **not** a fallback — it's a hardcoded `min(... , 3000)` that caps the displayed allowance-used figure at £3,000 regardless of what the active TaxConfig says. If HMRC raises (or has already raised) the CGT annual exempt amount in a future tax year, this line will silently understate the figure in the API response while the service-level calculation (now routed through `TaxConfigService`) returns the correct value. The response and the underlying calculation diverge by a fixed literal.

`TaxAwareRebalancer::calculateTotalCGT` already returns the correct `allowance_used` at line 236 — using `min($cgtAllowance, $netGainsAfterCarryforward)` — so the controller is duplicating that logic with a hardcoded constant. This is also dead-ish code (the wider response at line 453 uses `$cgtResult['cgt_analysis']` directly; only this one field re-derives the figure).

**Suggested fix:** drop the `min(... , 3000)` and use the service's already-computed value.

```php
'allowance_used' => $cgtResult['cgt_analysis']['allowance_used'] ?? 0,
```

---

## Warnings

### 2. Sibling pattern: CGT allowance still uses `?? 3000` soft-fallback on the same lines REVIEW #29 fixed the rate

- **Files:**
  - `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:46` (inside `optimizeForCGT`)
  - `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:468` (inside `rebalanceWithinCGTAllowance`)
- **Category:** Inconsistency with existing patterns / Rule #3

```php
$cgtAllowance = $options['cgt_allowance'] ?? (float) ($cgtConfig['annual_exempt_amount'] ?? 3000);
$taxRate      = $this->resolveTaxRate($options, $cgtConfig);    // <-- now fail-loud
```

PR #291 made `tax_rate` fail-loud on the line immediately below. The CGT **allowance** sibling on the line above still soft-falls-back to a hardcoded `3000` literal. Same fail-loud philosophy applies: if `annual_exempt_amount` is missing from the active TaxConfiguration the seeder is broken, and silently using a stale `3000` understates taxable gains the same way `0.10` rate understated liability before #291.

Note the elsewhere-in-Investment pattern at `app/Services/Investment/Recommendation/UserContextBuilder.php:80,239` uses `?? TaxDefaults::CGT_ANNUAL_EXEMPT` — the canonical named-constant fallback. Bare `?? 3000` is the worst of both worlds: not fail-loud, not even routed through `TaxDefaults`.

**Suggested fix:** extract `resolveCgtAllowance(array $options, array $cgtConfig): float` matching the shape of `resolveTaxRate()`. Either throw `FinancialCalculationException::taxConfigError('annual_exempt_amount', ...)` for full parity with the rate fix, or at minimum route through `TaxDefaults::CGT_ANNUAL_EXEMPT`. Same Wave 2.5 BADR-sibling pattern.

### 3. Controller-level `?? 3000` allowance fallback duplicated at 4 sites

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php:118, 225, 291, 447`
- **Category:** Duplicate code / Rule #3

```php
$cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;  // line 118
$cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;  // line 225
$cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;  // line 291
'cgt_allowance' => $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000,  // line 447
```

Four identical hardcoded `?? 3000` fallbacks. PR #291 already moved `tax_rate` defaults from the controller (`?? 0.20`) to `?? null` so the service handles the lookup centrally. The same pattern should apply to `cgt_allowance` — once Warning #2 is fixed, the controller can pass `null` (or omit `cgt_allowance` from `$cgtOptions`) and let the service do the resolution. The four controller-level fallbacks then become dead code.

**Suggested fix:** after fixing Warning #2, replace these four sites with `$validated['cgt_allowance'] ?? null` (matching the `tax_rate` pattern at lines 121, 228, 294, 448 introduced by this PR).

### 4. `getAccountRebalancing` is a god-method (≈154 lines)

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php:317–470`
- **Category:** Complexity & maintainability

The method mixes:
- account lookup + authorisation
- risk-profile resolution (uses 4 private helpers: `mapRiskStringToLevel`, `getRiskLabel`, `getTargetAllocationForRiskLevel`, `convertAllocationToHoldingWeights`)
- target allocation derivation
- drift analysis dispatch
- rebalancing calculation
- CGT optimisation for taxable accounts
- response assembly

The risk/target-allocation block (lines 336–376) is the obvious extraction — it's pure business logic about which risk profile applies to which account, with zero HTTP concerns.

**Suggested fix:** extract `resolveAccountRiskProfile(InvestmentAccount $account, User $user): array` into a service (or onto `InvestmentAccount` as a method). Mirrors the recently-established pattern of pushing logic out of controllers and into services/agents.

### 5. File is 639 lines — past the 500-line guideline

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`
- **Category:** Complexity & maintainability

Seven HTTP actions plus four private helpers. Two separable concerns:

- portfolio-level rebalancing actions (`calculateRebalancing`, `calculateFromOptimization`, `compareCGTStrategies`, `rebalanceWithinCGTAllowance`)
- account-level drift + threshold management (`getAccountRebalancing`, `updateRebalancingThreshold`, `analyzeDrift`)

**Suggested fix:** consider splitting into `RebalancingCalculationController` (portfolio actions) and `AccountRebalancingController` (account-level operations). Low priority — flagging because the file crossed the 500-line threshold during this PR's edits and the split would also make Warning #4 easier.

---

## Suggestions

### 6. Tax-config mutation block duplicated across two test cases

- **File:** `tests/Unit/Services/Investment/Rebalancing/TaxAwareRebalancerCgtRateTest.php:116–122` and `161–166`
- **Category:** Duplicate code (within file)

```php
$activeConfig = TaxConfiguration::where('is_active', true)->first();
$config = $activeConfig->config_data;
unset($config['capital_gains_tax']['basic_rate']);
$activeConfig->update(['config_data' => $config]);

$service = new TaxAwareRebalancer(new TaxConfigService);
```

Same 5-line setup repeated. Two occurrences is the borderline of "extract it" — fine to leave today, but if a third sibling test arrives (for `higher_rate`, `annual_exempt_amount`, etc.) extract `unsetCgtConfigKey(string $key): TaxAwareRebalancer` to the test file's top-level helpers (alongside `rebalanceTestHoldings`).

### 7. `optimizeSellOrder` docblock promises holding-period logic that the implementation doesn't deliver

- **File:** `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:165–194`
- **Category:** Dead/redundant code (documentation drift)

```php
/**
 * Strategy:
 * 1. Sell loss-making positions first (to offset gains)
 * 2. Sell positions with smallest gains (to maximize allowance usage)
 * 3. Consider holding period (longer-held assets first if tax benefits)
 */
```

Steps 1 and 2 are implemented (`sortBy('gain_or_loss')`). Step 3 is not — the method never references `holding_period_days` or `cgt_data.holding_period_days` despite `calculateCGTForSellActions` populating it at line 156. The promise has been sitting in the docblock long enough that it now looks like an unfinished feature.

**Suggested fix:** drop step 3 from the docblock. Holding-period sensitivity is unlikely to matter under post-30-October-2024 CGT (the rate is flat across holding periods for non-residential assets). If it ever becomes relevant, raise it as a new task.

---

## Summary

- **1 critical** — the hardcoded `3000` cap on `allowance_used` in the controller response. Diverges from service-side truth; should use the service's already-computed value.
- **2 warnings (sibling Rule #3)** — the same `?? 3000` allowance soft-fallback that this PR fixed for the rate. Service-level (2 sites) and controller-level (4 sites) both want the Wave 2.5 fail-loud pattern.
- **2 warnings (maintainability)** — `getAccountRebalancing` god-method + 639-line file. Not blocking, but the file is now above the 500-line guideline.
- **2 suggestions** — test helper extraction and a stale docblock step.

**Top 3 most impactful:**

1. **Critical #1 (controller line 456)** — fix in the next PR; trivial one-line change with no behaviour risk.
2. **Warning #2 + #3 (sibling allowance pattern)** — natural follow-up PR to #291. Same blast radius (small), same pattern (Wave 2.5 BADR-sibling), and would close out the allowance side of the same fail-loud story.
3. **Warning #4 (`getAccountRebalancing` extraction)** — defer unless the controller is being edited for another reason. Worth a slot on CSJTODO.

**Blocking issues for next PR/commit:** none. PR #291 is merged and these are sibling improvements, not regressions introduced by the PR.

---
*Generated by tech-debt-session skill — 2026-05-13 session 3*
