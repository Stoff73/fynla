# Full Codebase Tech Debt Report

**Date:** 20 March 2026
**Codebase:** Fynla v0.9.3
**Files scanned:** 590 Vue, 211 Services, 89 Controllers, 88 Models, 74 Form Requests, 29 Stores
**Total issues:** 58
**Previous report:** 6 March 2026 (v0.8.3)

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 6 |
| Warning / Medium | 30 |
| Suggestion / Low | 22 |

| Category | Count |
|----------|-------|
| God Files (>800 lines) | 11 |
| Colour Token Violations | 15 |
| Data Precision (float vs decimal) | 4 |
| Response/Pattern Inconsistency | 6 |
| Dead/Orphaned Code | 6 |
| Missing Validation | 4 |
| Architecture (DB in controllers) | 6 |
| Duplicate Code | 3 |
| Test Coverage Gaps | 3 |

### Improvements Since v0.8.3 Report (6 March)

- All hardcoded tax values now use TaxConfigService (was 30+ instances)
- @keyframes duplication fully resolved (was 8 files)
- ExpenditureForm bugs fixed (section totals, isSectionExpanded)
- Estate narrative strings use config values (was 30 hardcoded instances)
- Budget magic numbers replaced with named constants
- 9 duplicate onboarding components deleted
- Onboarding consolidated to single component set

### Quick Wins (trivial effort, high impact)

1. **Payment/Subscription amount cast** — `integer` → `decimal:2` (5 min, 2 files)
2. **IHTCalculation float casts** — 15 fields `float` → `decimal:2` (15 min, 1 file)
3. **Liability model float casts** — `float` → `decimal:2` (5 min, 1 file)
4. **bugReportService.js** — named export → default object pattern (2 min)
5. **Deduplicate calculateAge** — remove local copies, import from dateFormatter.js (10 min, 2 files)

### High Priority (critical severity)

1. **Duplicate calculateEquity()** — PropertyCalculationService vs PropertyService with incompatible logic
2. **AgentInternalController** — non-standard response format, missing error handling trait
3. **Missing form request validation** — AdvisorController, PaymentController, OnboardingController accept raw input
4. **Payment/Subscription model casts** — `integer` cast on `decimal(10,2)` columns loses pence values

---

## Detailed Findings by Module

### Backend Services (211 services, 9 agents)

**Overall health: Excellent.** All services use `declare(strict_types=1)`, constructor injection, TaxConfigService (no hardcoded tax values), and proper caching.

**Main issue: God files.** 11 services exceed 1000 lines.

| File | Lines | Suggestion |
|------|-------|-----------|
| SavingsActionDefinitionService | 3,675 | Extract evaluators into subdirectory |
| RetirementActionDefinitionService | 2,476 | Extract evaluators |
| RetirementIncomeService | 2,292 | Split into Calculator, Allocator, Simulator, TaxCalculator |
| ProtectionActionDefinitionService | 2,239 | Extract evaluators |
| RetirementStrategyService | 2,141 | Extract AffordabilityChecker, StrategyBuilder |
| InvestmentActionDefinitionService | 1,486 | Extract evaluators |
| OnboardingService | 1,379 | Split by flow type |
| ComprehensiveEstatePlanService | 1,308 | Split trust/gifting/IHT/recommendation |
| UserProfileService | 1,097 | Split profile/relationships/validation |
| ContributionWaterfallService | 1,024 | Consider extraction |
| HouseholdPlanningService | 990 | Consider extraction |

**Critical:** Duplicate `calculateEquity()` in PropertyCalculationService (lines 35-48) vs PropertyService (lines 27-42) with incompatible ownership percentage handling.

**Warning:** Duplicate Monte Carlo implementations — MonteCarloEngine (Shared) vs MonteCarloSimulator (Investment). May be intentional but needs documentation.

---

### Controllers & HTTP (89 controllers, 74 requests)

**Critical: Response format violations**

| File | Issue |
|------|-------|
| `AgentInternalController.php` | Returns `['error' => ...]` not `['success', 'message', 'data']`. Missing `SanitizedErrorResponse` trait. |
| `PostcodeLookupController.php` | Custom `['success', 'error', 'message']` format with inline error codes |
| `AdminController.php` | Inline `Validator::make()` instead of form request classes |

**Critical: Missing form request validation**

| File | Methods |
|------|---------|
| `AdvisorController.php` | `clients()`, `activities()`, `searchClients()` — raw `$request->all()` |
| `PaymentController.php` | Inline `Validator::make()` |
| `OnboardingController.php` | Inline `Validator::make()` in every method |

**Warning: DB facade in controllers** (should be in services)

| File | Lines | Pattern |
|------|-------|---------|
| `PaymentController.php` | 200, 339 | `DB::transaction()` |
| `PreviewController.php` | 276 | `DB::transaction()` |
| `WebhookController.php` | 71 | `DB::transaction()` |
| `DCPensionHoldingsController.php` | 228, 243, 254 | `DB::beginTransaction()` |
| `FamilyMembersController.php` | 266, 391 | `DB::transaction()` (339-line method) |
| `TaxSettingsController.php` | 126, 175, 223, 378 | Multiple `DB::transaction()` |

**Warning: God controllers**

| File | Lines | Issue |
|------|-------|-------|
| `FamilyMembersController.php` | 668 | `handleSpouseCreation()` is 339 lines |
| `AdminController.php` | 600 | Mixes user CRUD, backups, stats |
| `TaxSettingsController.php` | 440 | Multiple DB transactions |

---

### Models & Database (88 models)

**Critical: Currency precision issues**

| File | Issue | Fix |
|------|-------|-----|
| `Payment.php` line 29 | `'amount' => 'integer'` but column is `decimal(10,2)` | Change to `'decimal:2'` |
| `Subscription.php` line 42 | `'amount' => 'integer'` same issue | Change to `'decimal:2'` |
| `Estate/IHTCalculation.php` lines 59-88 | 15 currency fields cast as `float` | Change to `'decimal:2'` |
| `Estate/Liability.php` lines 37-44 | `current_balance`, `monthly_payment` as `float` | Change to `'decimal:2'`/`'decimal:4'` |

**Warning: Seeder idempotency**

| File | Issue |
|------|-------|
| `PreviewUserSeeder.php` lines 91-99 | Deletes and recreates preview users instead of `updateOrCreate()`. Destructive on production. |

**Warning: Missing indexes**

Tables with `joint_owner_id` added in later migrations may not all have composite indexes per CLAUDE.md convention.

**Low: Test coverage gaps**

Only 3 model tests exist for 88 models. Missing tests for: Household, Payment, Subscription, DocumentExtraction, AiConversation.

---

### Vue Components (590 components)

**Clean areas:** No single-word names, no v-if/v-for anti-pattern, no local formatCurrency methods, no custom @keyframes duplication, no hardcoded hex in style blocks.

**Warning: Old colour tokens (15+ files)**

| File | Tokens Used |
|------|-------------|
| `Trusts/TrustCard.vue` | `purple-600`, `blue-100`, `green-100` in style |
| `Trusts/TrustsOverviewCard.vue` | `blue-800`, `purple-600` in style + template |
| `Cash/AccountGroupList.vue` | `red-600`, `green-600` |
| `Cash/AccountSummaryPanel.vue` | `green-600`, `red-600` |
| `Cash/CashActionsPanel.vue` | `green-600`, `blue-600`, `red-600` |
| `UKTaxes/CalculationsTab.vue` | `blue-50/600`, `green-50/600`, `purple-600` |
| `BugReportModal.vue` | `blue-*`, `green-*`, `red-*` |
| `Shared/RiskLevelSelector.vue` | `blue-*`, `green-*`, `purple-*` |
| `Shared/RiskBadge.vue` | `blue-300` |
| `Savings/ISAAllowanceTracker.vue` | `purple-500`, `purple-700` |
| `Savings/CurrentSituation.vue` | `purple-500` |
| `Savings/SaveAccountModal.vue` | `purple-500` |
| `UserProfile/FamilyMembers.vue` | `blue-*`, `green-*`, `purple-*` |
| `Preview/PersonaIntroModal.vue` | `blue-*`, `green-*` |
| `Admin/AdminDashboard.vue` | `purple-100`, `purple-600` |

Note: `light-blue-*`, `light-gray`, `light-pink-*` are approved and NOT violations.

---

### Frontend Stores & Services (29 stores, 36 services)

**Warning: Orphaned module** — `guidance.js` (392 lines, 11 actions) never dispatched.

**Warning: Duplicate utility** — `calculateAge` in FamilyMembers.vue and FamilyInfoStep.vue instead of importing from `dateFormatter.js`.

**Warning: Hardcoded tax fallbacks** — `taxConfig.js` contains fallback values needing audit.

**Low: Dead code**

| File | Issue |
|------|-------|
| `portfolioOptimizationService.js` lines 133-148 | `getCorrelationMatrix()`, `getDiversificationMetrics()` never called |
| `storage.js` | Entire utility never imported |
| `dashboard.js` state lines 8-10 | `isPreviewMode`, `previewData` never set |

---

## Recommended Action Plan

### Immediate (this session or next)

| # | Task | Files | Effort |
|---|------|-------|--------|
| 1 | Fix Payment/Subscription amount casts to `decimal:2` | 2 models | 5 min |
| 2 | Fix IHTCalculation float casts to `decimal:2` | 1 model | 15 min |
| 3 | Fix Liability float casts | 1 model | 5 min |
| 4 | Add `SanitizedErrorResponse` to AgentInternalController | 1 controller | 30 min |
| 5 | Deduplicate calculateAge (import from util) | 2 Vue files | 10 min |

### Short-term (this month)

| # | Task | Files | Effort |
|---|------|-------|--------|
| 6 | Consolidate duplicate calculateEquity() | 2 services | 1 hr |
| 7 | Create form requests for Advisor/Payment/Onboarding | 6 new files | 3 hrs |
| 8 | Colour token migration (old palette → design system) | 15 Vue files | 3 hrs |
| 9 | Extract FamilyMembersController spouse logic to service | 1 controller | 4 hrs |
| 10 | Fix PreviewUserSeeder to use updateOrCreate | 1 seeder | 20 min |

### Backlog

| # | Task | Files | Effort |
|---|------|-------|--------|
| 11 | Extract ActionDefinitionService evaluators (x6) | 6 services | 2 days |
| 12 | Split RetirementIncomeService | 1 → 4 services | 1 day |
| 13 | Split AdminController into 3 focused controllers | 1 → 3 controllers | 3 hrs |
| 14 | Extract DB transactions from controllers to services | 6 controllers | 4 hrs |
| 15 | Add model tests for high-value models | 5+ test files | 2 hrs |
| 16 | Remove orphaned guidance.js store module | 1 store | 10 min |
| 17 | Document Monte Carlo engine differences | 2 services | 15 min |

---

## Overall Health Assessment

Fynla's codebase is in **good shape for a v0.9.3 product**. The architecture is sound — clear separation of concerns with Agents, Services, Controllers, and Models. All 211 services use strict types, constructor injection, and TaxConfigService. The frontend follows consistent patterns with currencyMixin, proper component naming, and no v-if/v-for anti-patterns.

The main debt is **god files** (11 services over 1000 lines) and **incomplete colour palette migration** (15 Vue files with old tokens). The currency precision issues (float casts on decimal columns) are the most impactful quick wins — they directly affect financial accuracy. The controller-level issues (missing form requests, DB facade usage) are architectural cleanliness items that should be addressed before v1.0.

**Compared to the 6 March v0.8.3 report:** the codebase has improved significantly. Tax values now use TaxConfigService throughout. @keyframes duplication is resolved. Estate hardcoded rates are fixed. Budget magic numbers are replaced with named constants. 9 duplicate onboarding components have been deleted. The remaining debt is structural (file sizes, colour tokens) rather than functional.

---
*Generated by tech-debt-full skill — 20 March 2026*
