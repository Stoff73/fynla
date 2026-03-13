# Full Codebase Remediation — Design Spec

**Date**: 2026-03-13
**Scope**: 51 findings from comprehensive code review — all severities
**Approach**: 6 phases, parallel agents, review gates between phases

---

## Phase 1: Security & Data Safety (8 fixes)

**Priority**: HIGHEST — data leakage and compliance risks
**Agent team**: security-reviewer (validation) + code agents (implementation)

### Fix #1: orWhere closure scoping (CRITICAL)

**Problem**: `Model::where('user_id', $id)->orWhere('joint_owner_id', $id)` without closure wrapper causes the `orWhere` to escape the surrounding query scope. Combined with soft deletes or additional where clauses, this leaks data across users.

**Fix**: Wrap all `orWhere('joint_owner_id')` patterns in a closure:

```php
// Before (broken)
$accounts = SavingsAccount::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();

// After (fixed)
$accounts = SavingsAccount::where(function ($q) use ($userId) {
    $q->where('user_id', $userId)
      ->orWhere('joint_owner_id', $userId);
})->get();
```

**Scope**: 40+ locations across all 7 module agents, controllers, and services. Use `HasJointOwnership` trait's `scopeForUserOrJoint()` where available; add the scope method where it's missing.

### Fix #4: IDOR via user_id request input (CRITICAL)

**Problem**: Several controllers accept `user_id` from request input with fallback to `auth()->id()`. An attacker can pass another user's ID.

**Fix**: Replace all `$request->input('user_id', auth()->id())` with `auth()->id()`. Never trust client-supplied user IDs in authenticated endpoints.

**Scope**: ~10 controllers.

### Fix #8: NI number encryption (HIGH)

**Problem**: `national_insurance_number` stored as plaintext on User model. Sensitive PII must be encrypted at rest.

**Fix**: Add `encrypted` cast to the `national_insurance_number` attribute in User model `$casts` array. Create a migration to encrypt existing values.

### Fix #20: Plaintext emails in audit trail (MEDIUM)

**Problem**: Audit logs store full email addresses. If audit table is compromised, PII is exposed.

**Fix**: Mask emails in audit entries: `c***@example.com`.

### Fix #21: CSP unsafe-inline (MEDIUM)

**Problem**: Content Security Policy allows `unsafe-inline` for scripts, weakening XSS protection.

**Fix**: Remove `unsafe-inline` from script-src in SecurityHeaders middleware. Use nonces for any inline scripts that remain.

### Fix #22: AI chat rate limiter (MEDIUM)

**Problem**: AI chat endpoint allows 60 requests/minute — too generous for an expensive API call.

**Fix**: Tighten to `throttle:20,1` on the AI chat route.

### Fix #23: Session rotation after MFA (MEDIUM)

**Problem**: Session token not rotated after MFA verification, leaving the pre-MFA token valid.

**Fix**: Call `$request->session()->regenerate()` after successful MFA verification in AuthController.

### Fix #44: Webhook CSRF (LOW)

**Problem**: Webhook endpoints don't validate CSRF. Acceptable only if signature-verified.

**Fix**: Verify that Revolut webhook controller validates `X-Revolut-Signature` header. Document the security model.

---

## Phase 2: Tax Compliance (16 fixes)

**Priority**: HIGH — incorrect tax calculations affect user financial planning
**Agent team**: tax-compliance-reviewer (validation) + code agents (implementation)

### Fix #2: Hardcoded income tax in AssetLocationController (CRITICAL)

**Problem**: Income tax band thresholds hardcoded instead of TaxConfigService.

**Fix**: Inject `TaxConfigService` and replace all hardcoded values with `$this->taxConfig->get('income_tax.*')`.

### Fix #9: CGT rate 28% → 24% (HIGH)

**Problem**: Capital gains tax rate uses 28% (pre-2024 rate). The 2024 Autumn Budget reduced higher-rate CGT to 24%.

**Fix**: Replace hardcoded 28% with `$this->taxConfig->get('capital_gains_tax.higher_rate')`. Verify TaxConfigurationSeeder has correct 24% value for 2025/26.

### Fix #10: Missing PA taper in simplified path (HIGH)

**Problem**: UKTaxCalculator simplified calculation path doesn't apply Personal Allowance taper for incomes above £100,000.

**Fix**: Add PA taper logic: reduce PA by £1 for every £2 above £100,000 threshold (from TaxConfigService).

### Fix #11: Hardcoded basic rate band £37,700 (HIGH)

**Problem**: Basic rate band hardcoded in multiple services.

**Fix**: Replace with `$this->taxConfig->get('income_tax.basic_rate_band')` in all locations.

### Fix #24: LISA bonus 25% (MEDIUM)

**Problem**: Lifetime ISA 25% government bonus is hardcoded. Correct today but should be configurable.

**Fix**: Add `lisa_bonus_rate` to TaxConfigService ISA section; reference it instead of hardcoding.

### Fix #25: Missing higher-rate dividend band (MEDIUM)

**Problem**: Simplified calculator doesn't handle dividend tax at higher rate properly.

**Fix**: Add dividend higher-rate band calculation using TaxConfigService dividend rates.

### Fix #26: Pension LTA references (MEDIUM)

**Problem**: Lifetime Allowance was abolished April 2024. References still exist in code and UI.

**Fix**: Remove LTA checks, LTA-based warnings, and LTA UI displays. Replace with new Lump Sum Allowance (LSA: £268,275) and Lump Sum and Death Benefit Allowance (LSDBA: £1,073,100) from TaxConfigService.

### Fix #27: CGT AEA £6,000 → £3,000 (MEDIUM)

**Problem**: Annual Exempt Amount hardcoded as £6,000. It dropped to £3,000 for 2024/25+.

**Fix**: Replace with `$this->taxConfig->get('capital_gains_tax.annual_exempt_amount')`. Verify seeder has £3,000.

### Fix #28: State pension triple lock (MEDIUM)

**Problem**: Triple lock floor uses fixed 2.5%. Should be configurable for projection sensitivity analysis.

**Fix**: Add `state_pension.triple_lock_floor` to TaxConfigService/PlanConfiguration. Reference in RetirementProjector.

### Fix #29: Marriage allowance (MEDIUM)

**Problem**: Marriage Allowance transfer (£1,260 to spouse) not modelled in tax optimisation.

**Fix**: Add Marriage Allowance calculation to tax optimisation recommendations when one spouse is non-taxpayer.

### Fix #38: Intestacy threshold (LOW)

**Problem**: Intestacy threshold hardcoded as £250,000. Changed to £322,000 from January 2024.

**Fix**: Add to TaxConfigService estate section. Update EstateDistributionService.

### Fix #39: RNRB downsizing addition (LOW)

**Problem**: Residence nil-rate band downsizing addition not calculated when main residence was sold/downsized after July 2015.

**Fix**: Add downsizing calculation to IHT calculator using TaxConfigService thresholds.

### Fix #40: RNRB taper threshold (LOW)

**Problem**: £2M RNRB taper threshold hardcoded.

**Fix**: Replace with `$this->taxConfig->getInheritanceTax()['rnrb_taper_threshold']`.

### Fix #41: Child benefit HICBC (LOW)

**Problem**: High Income Child Benefit Charge not modelled. Affects tax planning for families.

**Fix**: Add HICBC calculation (1% of benefit per £200 above £60,000, full clawback at £80,000) using TaxConfigService thresholds.

### Fix #42: Scottish income tax bands (LOW)

**Problem**: Scottish income tax uses different bands/rates (starter, basic, intermediate, higher, advanced, top). Not supported.

**Fix**: Add Scottish tax band data to TaxConfigService. UKTaxCalculator checks user's tax jurisdiction.

### Fix #43: Welsh income tax (LOW)

**Problem**: Welsh Rate of Income Tax not modelled (currently same as England/NI but technically separate).

**Fix**: Add Welsh flag to UKTaxCalculator for future-proofing. Currently applies same rates.

---

## Phase 3: Backend Logic (4 fixes)

**Priority**: HIGH — logic bugs and API inconsistency
**Agent team**: code-reviewer (validation) + code agents (implementation)
**Runs in parallel with Phase 4**

### Fix #3: RetirementAgent scenario comparison (CRITICAL)

**Problem**: When user is on track (all scenarios positive), `min()` picks the "best" scenario by selecting the smallest surplus — which is actually the worst positive outcome.

**Fix**: When all outcomes are positive, use `max()` to select the scenario with the largest surplus. When mixed, current logic (minimizing shortfall) is correct.

### Fix #12: PropertyController response envelope (HIGH)

**Problem**: PropertyController returns raw arrays instead of the standard `{success, message, data}` envelope used by all other controllers.

**Fix**: Wrap all PropertyController responses in `response()->json(['success' => true, 'message' => '...', 'data' => $result])`.

### Fix #33: Constructor readonly (MEDIUM)

**Problem**: 33+ controllers use `private` without `readonly` on constructor-injected dependencies.

**Fix**: Add `readonly` keyword to all constructor-injected service/agent properties across controllers.

### Fix #45: Date formatting consistency (LOW)

**Problem**: Inconsistent date formats across API responses (some ISO 8601, some Y-m-d).

**Fix**: Ensure all API Resources use `->toIso8601String()` for date fields.

---

## Phase 4: Frontend Compliance (8 fixes)

**Priority**: MEDIUM — design system violations and code quality
**Agent team**: premium-ui-designer (validation) + code agents (implementation)
**Runs in parallel with Phase 3**

### Fix #5: Score displays — Rule 13 (CRITICAL per design rules)

**Problem**: FinancialHealthScore.vue, PortfolioHealthDisplay.vue, DiversificationScore, and score badges display numerical scores.

**Fix**: Replace score displays with descriptive text, specific metrics (currency/percentages), and actionable guidance. Remove FinancialHealthScore.vue if orphaned.

### Fix #6: Hardcoded hex and banned tokens (CRITICAL per design rules)

**Problem**: 12+ components use hardcoded hex (#E83E6D, #1F2A44, #333) and banned tokens (gray-*, primary-*).

**Fix**: Replace all with Tailwind palette tokens per fynlaDesignGuide.md v1.2.0.

### Fix #19: Router guard inconsistency (HIGH)

**Problem**: Some mobile routes check `to.meta.requiresAuth` directly. Vue Router child routes don't inherit parent meta.

**Fix**: Change all auth checks to `to.matched.some(r => r.meta.requiresAuth)`.

### Fix #34: Non-palette spinner colors (MEDIUM)

**Problem**: Loading spinners use non-palette colors.

**Fix**: Standardize to `border-horizon-200 border-t-raspberry-500` per CLAUDE.md Rule 12.

### Fix #35: Local currencyMixin redefinition (MEDIUM)

**Problem**: 3 components define local `formatCurrency()` instead of using currencyMixin.

**Fix**: Remove local definitions, import currencyMixin.

### Fix #36: Vuex action error handling (MEDIUM)

**Problem**: Several Vuex actions don't catch API errors, causing unhandled promise rejections.

**Fix**: Add try/catch with appropriate error state mutations.

### Fix #48: localStorage direct usage (LOW)

**Problem**: Components use `localStorage` directly instead of a wrapper.

**Fix**: Create `utils/storage.js` wrapper. Update consumers.

### Fix #49: $refs vs reactive data (LOW)

**Problem**: Some components use `$refs` for data that should be reactive.

**Fix**: Refactor to use `data()` properties or computed properties where appropriate.

---

## Phase 5: Database & Performance (5 fixes)

**Priority**: MEDIUM — schema integrity and query performance
**Agent team**: database-optimizer (validation) + code agents (implementation)
**Runs after Phases 1-3**

### Fix #13: FK constraint on joint_owner_id (HIGH)

**Problem**: `savings_accounts.joint_owner_id` has no foreign key constraint.

**Fix**: Migration adding FK constraint to `users.id` with `ON DELETE SET NULL`.

### Fix #14: double → decimal columns (HIGH)

**Problem**: 19 financial columns on users table use `double` type. Floating point causes rounding errors.

**Fix**: Migration to alter columns from `double` to `decimal(15,2)`.

### Fix #30: N+1 eager loading (MEDIUM)

**Problem**: Agent `analyze()` methods make redundant queries for related models.

**Fix**: Add `->with()` eager loading for known relationships in each agent's data fetching.

### Fix #31: Indexes on joint_owner_id (MEDIUM)

**Problem**: `joint_owner_id` columns across tables lack indexes, causing slow lookups.

**Fix**: Migration adding indexes to all `joint_owner_id` columns.

### Fix #32: JSON virtual columns (MEDIUM)

**Problem**: JSON columns on MySQL 8 can't be indexed directly.

**Fix**: Add generated virtual columns for frequently queried JSON paths, with indexes.

---

## Phase 6: Architecture & Housekeeping (14 fixes)

**Priority**: LOW — maintainability and developer experience
**Agent team**: code agents in parallel
**Runs last**

### Fix #7: .env.example sync (CRITICAL per ops)

Update with: CEREBRAS_API_KEY, CEREBRAS_CHAT_MODEL, ANALYTICS_ENABLED, PLAUSIBLE_DOMAIN, VITE_PLAUSIBLE_DOMAIN, FCM_PROJECT_ID, FCM_PRIVATE_KEY, FCM_CLIENT_EMAIL.

### Fix #15: Missing tests for critical services

Add test suites for: IHTCalculationService, UKTaxCalculator, RetirementIncomeService.

### Fix #16: Duplicate PortfolioAnalyzer

Identify canonical version, remove duplicate, update imports.

### Fix #17: Orphaned Vue components

Identify and remove components imported nowhere.

### Fix #18: Anthropic config in services.php

Add `anthropic` config block to `config/services.php`.

### Fix #37: Source maps in production

Set `build.sourcemap: false` in vite.config.js for production builds.

### Fix #46: Oversized Vue components

Split components exceeding 500 lines into focused sub-components.

### Fix #47: Missing JSDoc

Add JSDoc to 20+ undocumented service functions.

### Fix #50: Protection module test gaps

Add edge case tests for Protection module services.

### Fix #51: Unused npm dependencies

Audit and remove unused packages from package.json.

---

## Execution Order

```
Phase 1 (Security) ──────────────────→ Phase 5 (Database)
                                          ↓
Phase 2 (Tax) ───────────────────────→ Phase 6 (Housekeeping)
                                          ↑
Phase 3 (Backend) ──┐                    │
                     ├── parallel ────────┘
Phase 4 (Frontend) ─┘
```

**After Phase 5**: `php artisan db:seed` to verify migrations
**After Phase 6**: Full test suite `./vendor/bin/pest`
**Final**: Build web app `./deploy/fynla-org/build.sh`

---

## Review Gates

Each phase ends with a specialized reviewer agent validating the changes:
- Phase 1: security-reviewer
- Phase 2: tax-compliance-reviewer
- Phase 3: code-reviewer
- Phase 4: premium-ui-designer review
- Phase 5: database-optimizer review
- Phase 6: Final test run

## Risk Mitigation

- All work in worktrees to isolate changes
- Each phase committed separately for easy rollback
- Database migrations are additive (no drops)
- Test suite run after each phase
- `php artisan db:seed` after any migration phase
