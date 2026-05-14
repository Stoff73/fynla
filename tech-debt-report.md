# Tech Debt Report — Session 2026-05-14 (PR #292 post-merge)

**Files analysed:** 3
- `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php`
- `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`
- `tests/Unit/Services/Investment/Rebalancing/TaxAwareRebalancerCgtAllowanceTest.php`

**Issues found:** 6 (1 new observation + 5 carry-forward from 2026-05-13 session 3 audit)
**Severity breakdown:** 0 critical · 3 warnings · 3 suggestions

PR #292 itself is **clean** — no new debt introduced by the diff. The findings below either carry forward from the prior audit (still open, not yet addressed) or are new sibling observations about the same files.

## Critical Issues

None. The PR #292 fix itself is the canonical fail-loud pattern from PR #289/#291, applied cleanly with mirror-shape helpers + 6 fresh Pest cases.

## Warnings

### Warning #1 — `RebalancingCalculationController::getAccountRebalancing` is a 154-line god-method (CARRY-FORWARD)

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php:312-465`
- **Category:** Complexity & Maintainability
- **What's wrong:** Method does account lookup, risk-profile resolution (user + custom), target-allocation lookup, threshold setup, empty-holdings branch, drift analysis, conditional rebalancing calculation, and conditional CGT optimisation — all in one body. Same finding as previous audit; PR #292 touched lines 442-453 (within this method) but didn't extract.
- **Suggested fix:** Extract `resolveAccountRiskProfile(InvestmentAccount $account, User $user): array` (currently lines 336-371) into a service or model method. Same suggestion as 2026-05-13 audit; deferred to a dedicated refactor PR.

### Warning #2 — `RebalancingCalculationController.php` is 634 lines (CARRY-FORWARD)

- **File:** `app/Http/Controllers/Api/Investment/RebalancingCalculationController.php`
- **Category:** Complexity & Maintainability
- **What's wrong:** Past the 500-line guideline. Houses both portfolio-level rebalancing actions (calculate, compare CGT, within-allowance, drift) and account-level (`getAccountRebalancing`, `updateRebalancingThreshold`) — two responsibilities in one class.
- **Suggested fix:** Split into `RebalancingCalculationController` (portfolio-level) + new `AccountRebalancingController` (account-level). Would also unblock Warning #1 — the account methods become smaller in isolation.

### Warning #3 — `TaxAwareRebalancer.php` is 606 lines (NEW)

- **File:** `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php`
- **Category:** Complexity & Maintainability
- **What's wrong:** New observation — service file is also past the 500-line guideline. Sibling pattern to Warning #2. Houses 4 public methods (`optimizeForCGT`, `compareStrategies`, `rebalanceWithinCGTAllowance`) plus 8 private helpers, including CGT calculation, tax-loss harvesting identification, summary generation, and strategy comparison.
- **Suggested fix:** Lower priority than Warning #2 — service file is more cohesive than the controller (all CGT-flavoured). Candidate extraction: `TaxLossHarvestingIdentifier` for the `identifyTaxLossHarvesting` block (lines 256-324, ~69 lines including docblock). Flag only — not yet acutely painful.

## Suggestions

### Suggestion #4 — Tax-config mutation block duplicated in test files (CARRY-FORWARD)

- **File:** `tests/Unit/Services/Investment/Rebalancing/TaxAwareRebalancerCgtAllowanceTest.php:113-116, 128-131` (and sibling `TaxAwareRebalancerCgtRateTest.php:116-122, 161-166`)
- **Category:** Duplicate Code
- **What's wrong:** The "load active TaxConfiguration, unset a key, save" pattern now appears 4 times across 2 test files. Still suggestion territory (rule of three not yet breached for an *extracted* helper — but very close).
- **Suggested fix:** When a third CGT-fail-loud sibling test file arrives (e.g. higher-rate-band, or trustees-rate), extract `unsetCgtConfigKey(string $key): void` into a shared test helper or trait.

### Suggestion #5 — `optimizeSellOrder` docblock promises an unimplemented step 3 (CARRY-FORWARD)

- **File:** `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:166-195`
- **Category:** Dead & Redundant Code (stale docblock)
- **What's wrong:** Docblock at lines 168-172 lists three strategies — "1. Sell loss-making positions first / 2. Sell positions with smallest gains / 3. Consider holding period (longer-held assets first if tax benefits)". The implementation at lines 178-195 only does steps 1 and 2 — `sortBy('gain_or_loss')` for losses and `sortBy('gain_or_loss')` for gains. No holding-period tiebreaker.
- **Suggested fix:** Drop "3. Consider holding period (longer-held assets first if tax benefits)" from the docblock. Either that, or actually implement the tiebreaker using `holding_period_days` from the `cgt_data` block — but UK CGT post-30-October-2024 has no holding-period rate distinction, so deletion is the truer fix.

### Suggestion #6 — `resolveTaxRate` and `resolveCgtAllowance` share an extractable shape (NEW)

- **File:** `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:559-605`
- **Category:** Duplicate Code (mild)
- **What's wrong:** Both helpers follow the same "caller-supplied option wins → config key present? → return; else throw `taxConfigError`" shape. Two methods, ~13 lines each, identical structure.
- **Suggested fix:** **Hold for now.** The pair is intentional: each carries a distinct error message and a distinct domain (rate vs. allowance), and the explicit per-key shape is easier to grep for during audits. Extract only if a third resolver lands in the same service (e.g. higher-rate CGT, trustees' rate) — at that point a `resolveOrThrow(array $options, string $optionKey, array $cgtConfig, string $configKey, string $reason): float` helper becomes worth the indirection.

---

## Summary

Top 3 most impactful:

1. **Warning #1 + Warning #2** — Same-file follow-up: extract `resolveAccountRiskProfile()` + split controller into portfolio/account pair. Unblocks the next REVIEW §4 tasks in the area without touching new tax logic.
2. **Warning #3 (new)** — Sibling 500-line breach on `TaxAwareRebalancer.php`. Lower priority than #1/#2 because the service is more cohesive, but worth tracking.
3. **Suggestion #5** — Drop the unimplemented step-3 promise from the `optimizeSellOrder` docblock. Two-line edit; eliminates future "why doesn't this work?" debugging.

**Nothing blocks commit.** PR #292 is already merged into `dev` at `23c3c18`; this report is the deferred audit per the 2026-05-13 session 3 handover. Findings #1/#2/#4/#5 are explicit carry-forwards; #3 and #6 are new observations.

*Generated by tech-debt-session skill — 2026-05-14*
