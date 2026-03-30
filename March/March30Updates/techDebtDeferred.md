# Tech Debt Deferred — 30 March 2026

Items identified in the full tech debt audit that require larger refactoring and are deferred to future sprints.

## God Class Decomposition (CRITICAL — Large effort)

### Backend Services (>2,000 lines)

| Service | Lines | Suggested Decomposition |
|---------|-------|------------------------|
| `app/Services/Savings/SavingsActionDefinitionService.php` | 3,675 | Extract evaluators to strategy classes with EvaluatorRegistry |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | 2,701 | Same pattern — extract per-trigger evaluator methods |
| `app/Services/Protection/ProtectionActionDefinitionService.php` | 2,349 | Same pattern |
| `app/Services/Retirement/RetirementIncomeService.php` | 2,292 | Extract income source calculators (pension, state, other) |
| `app/Services/Retirement/RetirementStrategyService.php` | 2,141 | Extract strategy generators by type |

**Root cause:** N+1 pattern of adding private evaluator methods for each action definition trigger condition. Each new trigger adds 50-100 lines.

**Recommended approach:** Create `BaseActionDefinitionService` with shared evaluation loop, then per-module `EvaluatorRegistry` mapping trigger names to focused evaluator classes.

### Backend Services (1,000-2,000 lines)

| Service | Lines |
|---------|-------|
| `app/Services/Estate/IHTCalculationService.php` | 1,574 |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | 1,486 |
| `app/Services/Onboarding/OnboardingService.php` | 1,389 |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | 1,308 |
| `app/Services/UserProfile/UserProfileService.php` | 1,099 |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php` | 1,024 |

### God Controllers (>500 lines)

| Controller | Lines | Suggested Split |
|-----------|-------|-----------------|
| `app/Http/Controllers/Api/InvestmentController.php` | 1,067 | HoldingsController, GoalsController, AccountsController |
| `app/Http/Controllers/Api/RetirementController.php` | 788 | PensionsController, IncomeController, ProjectionsController |
| `app/Http/Controllers/Api/PreviewController.php` | 672 | Borderline — monitor |
| `app/Http/Controllers/Api/FamilyMembersController.php` | 669 | Borderline — monitor |

### God Vue Components (>2,000 lines)

| Component | Lines | Suggested Split |
|-----------|-------|-----------------|
| `resources/js/views/Public/CalculatorsPage.vue` | 2,432 | Extract each calculator to own component with dynamic imports |
| `resources/js/views/Dashboard.vue` | 2,124 | Extract card components (NetWorthCard, ProtectionCard, etc.) |

---

## Float-to-Decimal Migration (HIGH — Medium effort, blocked)

**Issue:** 60+ financial fields across 12 models use `'float'` cast instead of `'decimal:2'`/`'decimal:4'`. Float precision is inadequate for financial calculations.

**Blocked by:** Laravel's `decimal:2` cast returns strings in JSON serialisation. Tests assert numeric values, so changing casts breaks 50+ assertions. Requires updating API Resource classes to cast back to numbers.

**Models affected:** Holding, IHTProfile, Asset, Gift, Liability, IHTCalculation, ExpenditureProfile, ProtectionProfile, RecommendationTracking, InvestmentGoal, RiskProfile, RebalancingAction

**Approach:**
1. Update API Resource classes to `(float)` cast decimal fields
2. Change model casts from `'float'` to `'decimal:2'`/`'decimal:4'`
3. Update test assertions
4. Estimated effort: 1 full sprint

---

## Test Coverage (HIGH — Large effort)

**Current:** 41 of 214 services tested (19%)
**Target:** 85 of 214 (40%)

**Priority services needing tests:**
- `HolisticPlanner` — cross-module coordination
- `NetWorthAnalyzer` — critical financial calculation
- `MobileDashboardAggregator` — mobile data pipeline
- `RevolutService` — payment processing
- `DataPurgeService` — GDPR compliance
- All 5 Coordination services
- `IHTCalculationService` — estate tax calculations

**Estimated effort:** 20+ hours

---

## Off-Palette Tailwind Colours (MEDIUM — Medium effort)

**Issue:** 30+ files in the Risk module and other areas use non-palette Tailwind classes (red-*, blue-*, teal-*, green-*) that should map to design system tokens.

**Key files:**
- `CapacityForLossSection.vue` — red-400/500, blue-400/500, teal-400/500
- `TimeHorizonSection.vue` — red-700/400, blue-400/600, green-400/600
- Various Trust, Cash, Admin components

**Note:** Risk-level badge colours are intentionally kept per CLAUDE.md policy. Only non-badge decorative elements need fixing.

**Estimated effort:** 3-4 hours

---

## Hardcoded Hex in SVG/Styles (MEDIUM — High effort)

**Issue:** 40+ instances of hardcoded hex colours in inline SVGs, chart configs, and scoped styles.

**Key files:**
- `JourneyMap.vue` — 15+ hex values in SVG
- `FocusAreaSelection.vue` — 10+ hex values
- `LetterToSpouse.vue` — 20+ in print styles (acceptable exception)
- Various chart components

**Note:** Print styles and chart configs where Tailwind cannot be applied may need CSS custom properties tied to designSystem.js.

**Estimated effort:** 6-8 hours

---

## DB Facade in Controllers (MEDIUM — Medium effort)

**Issue:** 8 controllers use `DB::transaction()` directly instead of delegating to services.

**Files:** FamilyMembersController, RetirementController, InvestmentController, PaymentController, WebhookController, DCPensionHoldingsController, TaxSettingsController, PreviewController

**Estimated effort:** 4-6 hours

---

## Architecture: EstateController Bypasses EstateAgent (MEDIUM)

**Issue:** EstateController directly injects services (NetWorthAnalyzer, CashFlowProjector, ComprehensiveEstatePlanService) instead of routing through EstateAgent like other modules.

**Estimated effort:** 4-6 hours

---

## NPM Vulnerabilities (CRITICAL — Medium effort, needs careful testing)

**Issue:** 9 high-severity CVEs in transitive dependencies (flatted, happy-dom, picomatch, serialize-javascript, tar). 2 moderate (brace-expansion, capacitor-native-biometric).

**Blocked by:** `npm audit fix --force` may break vite-plugin-pwa and Capacitor. Requires testing PWA and iOS mobile app after update.

**Estimated effort:** 8 hours (including mobile testing)

---

## league/commonmark CVE (LOW — Blocked by Laravel 10)

**Issue:** 2 CVEs in league/commonmark v2.3-2.8. Fix requires v2.9+ which needs Laravel 11.

**Blocked by:** Laravel 10 constrains commonmark to ^2.2.1 (max 2.8.x).

**Approach:** Resolve when upgrading to Laravel 11.

---

## Vuex State Bloat (LOW — Medium effort)

**Issue:** investment.js has 28 state properties, netWorth.js has 36. Monte Carlo state (3 objects) should be extracted. UI state mixed with data state.

**Estimated effort:** 8 hours

---

## Summary

| Category | Items | Total Effort |
|----------|-------|-------------|
| God class decomposition | 5 critical + 6 high + 4 controllers + 2 components | 40-60 hours |
| Float→decimal migration | 12 models, 60+ fields | 8-12 hours |
| Test coverage (19%→40%) | 44 new test files | 20+ hours |
| Off-palette colours | 30+ files | 3-4 hours |
| Hardcoded hex | 40+ instances | 6-8 hours |
| DB facade in controllers | 8 controllers | 4-6 hours |
| NPM vulnerabilities | 11 packages | 8 hours |
| Other (state bloat, arch) | 3 items | 10-12 hours |
| **Total** | | **~100-130 hours** |
