# Deploy: Login Fix + Code Review Remediation + Plan Fixes

**Date:** 2026-03-15
**Branch:** `engineUpgrade`
**Scope:** Login 422 bug fix, security hardening, full code review remediation (13 issues), plan endpoint fixes, investment plan cross-module isolation
**Tests:** 1,873 passed, 0 failures

---

## What Changed

### Login Bug Fix (Critical)
- **Root cause:** Challenge tokens stored in Laravel cache. When cache driver resolved to `array` (in-memory only), tokens were lost between requests, causing 422 "Invalid or expired verification session" on every login verification.
- **Fix:** Moved challenge token storage to the `email_verification_codes` database table. Tokens now persist reliably regardless of cache configuration.
- **Security:** Removed `user_id` from pre-auth API responses (login + MFA) to prevent user enumeration. Frontend updated to use `challenge_token` / `mfa_token` exclusively.

### Code Review Remediation (13 issues)
| Severity | Count | Summary |
|----------|-------|---------|
| Critical | 1 | Hardcoded IHT tax values in 4 Vue components |
| High | 4 | Scores in UI, hex colors, sensitive data echo, hardcoded ISA allowance |
| Medium | 7 | Dead code, duplicate CSS, missing arch tests, response format, pagination |

### Plan Endpoint Fixes (3 bugs)
- **Investment plan 500** for all personas — `ConflictResolutionService::priorityRank()` strict type mismatch (string vs int)
- **Investment plan 500** for entrepreneur/young_saver — `ContributionWaterfallService::stepLISA()` passed full LISA array to `number_format()` instead of `annual_allowance`
- **Retirement plan 500** for retired_couple — `RetirementActionDefinitionService` crashed on null profile (no RetirementProfile record for already-retired users)

### Investment Plan Cross-Module Isolation
- **Problem:** Individual investment plan was including pension contributions, savings allowance, and marriage allowance recommendations from spouse/transfer pipeline — these belong in the holistic plan only
- **Fix:** Filtered non-investment `strategy_type`/`scan_type` values (`pension_coordination`, `non_earning_spouse_pension`, `psa_optimisation`, `psa_breach`, `marriage_allowance`) from the investment plan output. Reduced peak_earners from 15 mixed actions to 12 investment-only actions.

### Investment Plan Generic Titles
- **Problem:** Spouse and transfer recommendations displayed as "Recommendation" with empty descriptions in key actions and optional improvements
- **Fix:** `BasePlanService.structureActions()` now maps `headline` → `title` and `explanation` → `description` from spouse optimisation and transfer services

### Property Page Fix
- **Problem:** Property page blank — Vue error `this.properties is not iterable` after PropertyController.store() response format change
- **Fix:** Updated `PropertyList.vue` to handle wrapped `{ success, data: { property } }` response

---

## New Files (create these)

```
database/migrations/2026_03_15_074247_add_challenge_token_to_email_verification_codes_table.php
```

## Modified PHP Files

```
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Models/EmailVerificationCode.php
app/Services/Investment/Recommendation/ConflictResolutionService.php
app/Services/Investment/Recommendation/ContributionWaterfallService.php
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Retirement/RetirementActionDefinitionService.php
app/Services/Retirement/RetirementStrategyService.php
```

## Modified Frontend Files

```
resources/css/app.css
resources/js/components/Estate/IHTCalculationTable.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/LifePolicyStrategy.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue
resources/js/components/Onboarding/steps/JourneyCompletionStep.vue
resources/js/constants/designSystem.js
resources/js/mobile/components/MobileProjectionChart.vue
resources/js/mobile/goals/MilestoneOverlay.vue
resources/js/mobile/views/EstateDetail.vue
resources/js/mobile/views/MobileLoginScreen.vue
resources/js/views/Help.vue
resources/js/views/Investment/AccountHoldingsPanel.vue
resources/js/views/Investment/AccountPerformancePanel.vue
resources/js/views/Login.vue
resources/js/views/Trusts/TrustsDashboard.vue
resources/js/views/Version.vue
```

## Deleted Files

```
resources/js/components/Protection/CoverageAdequacyGauge.vue  (orphaned — never imported)
```

## Modified Test Files

```
tests/Architecture/ApplicationArchitectureTest.php
tests/Feature/Api/CountryTrackingTest.php
tests/Feature/Api/PropertyControllerTest.php
```

---

## SSH Commands After Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds challenge_token column)
php artisan migrate

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Build Locally First

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory + all PHP files listed above.

---

## Detailed Change Log

### Login Fix
| File | Change |
|------|--------|
| `AuthController.php` | DB-based challenge tokens (was cache), removed `user_id` from login/MFA responses, removed `Cache` import |
| `EmailVerificationCode.php` | Added `challenge_token` to fillable/hidden, `findByChallengeToken()` method, `generate()` accepts optional token |
| `Login.vue` | Removed `pendingUserId` ref, updated modal bindings |
| `MobileLoginScreen.vue` | Removed `userId` from verify navigation query |

### C2 — Hardcoded IHT Tax Values
| File | Change |
|------|--------|
| `IHTCalculationTable.vue` | 16 instances of `formatCurrency(325000)` replaced with `formatCurrency(IHT_NIL_RATE_BAND)` |
| `IHTPlanning.vue` | 20+ instances of `325000`, `650000`, `0.40`, `0.36` replaced with `taxConfig.js` constants |
| `EstateDetail.vue` | Fallback values replaced with `IHT_NIL_RATE_BAND` / `IHT_RESIDENCE_NIL_RATE_BAND` |
| `LifePolicyStrategy.vue` | Removed dead `computePreviewStrategy()` method (97 lines of hardcoded calculations) |

### H1 — Scores Removed from UI
| File | Change |
|------|--------|
| `InvestmentProjections.vue` | Tax efficiency `{{ score }}%` badge replaced with descriptive labels ("Well Sheltered", "Partially Sheltered", etc.), `score-*` CSS classes replaced with `status-*` |
| `CoverageAdequacyGauge.vue` | Deleted — orphaned component, never imported anywhere |

### H2 — Hex Colors Replaced with Design System
| File | Change |
|------|--------|
| `AccountPerformancePanel.vue` | 14 hardcoded hex values replaced with `ASSET_COLORS` lookup |
| `AccountHoldingsPanel.vue` | `#ec4899` and `#94a3b8` replaced with `ASSET_COLORS` |
| `TrustsDashboard.vue` | `#eff6ff` replaced with `@apply bg-savannah-100` |
| `MobileProjectionChart.vue` | `#717171` and `#EEEEEE` replaced with `TEXT_COLORS.muted` / `BORDER_COLORS.default` |
| `MilestoneOverlay.vue` | Hardcoded confetti array replaced with `CONFETTI_COLORS` import |
| `designSystem.js` | Added `ASSET_COLORS` mappings (12 types) and `CONFETTI_COLORS` export |

### H3 — PreviewWriteInterceptor Security
| File | Change |
|------|--------|
| `PreviewWriteInterceptor.php` | Added `SENSITIVE_FIELDS` constant, `$request->all()` changed to `$request->except(self::SENSITIVE_FIELDS)` |

### H4 — Hardcoded ISA Allowance
| File | Change |
|------|--------|
| `RetirementStrategyService.php` | `float $remainingIsaAllowance = 20000` changed to nullable with `TaxConfigService` fallback |

### M3 — Duplicate Keyframes
| File | Change |
|------|--------|
| `app.css` | Added global `@keyframes checkmark-scale` and `@keyframes checkmark-draw` |
| `BudgetingCompletionStep.vue` | Removed duplicate `@keyframes` from scoped styles |
| `JourneyCompletionStep.vue` | Removed duplicate `@keyframes` from scoped styles |

### M4 — Architecture Tests
| File | Change |
|------|--------|
| `ApplicationArchitectureTest.php` | Added strict types tests for `App\Models` and `App\Http\Controllers` |

### M6 — PropertyController Response Format
| File | Change |
|------|--------|
| `PropertyController.php` | `store()` wrapped in standard `{ success, message, data }` envelope |
| `PropertyList.vue` | Updated to handle wrapped response format for GET, POST, and PUT |
| `PropertyControllerTest.php` | Updated assertions for new response structure |
| `CountryTrackingTest.php` | Updated `json('country')` to `json('data.property.country')` |

### M7 — Score Text in Help/Version
| File | Change |
|------|--------|
| `Version.vue` | "adequacy scores (0-100)" → "coverage analysis and recommendations" |
| `Help.vue` | "Diversification scoring" → "Diversification analysis", score description replaced with descriptive text |

### M8 — Query Limits
| File | Change |
|------|--------|
| `EstateController.php` | Added `.limit(100)` to 5 queries |
| `GoalsController.php` | Added `.limit(100)` to goals query |
| `SavingsController.php` | Added `.limit(100)` to 2 queries |

### Plan Endpoint Fixes
| File | Change |
|------|--------|
| `ConflictResolutionService.php` | `priorityRank()` accepts `string\|int` — some recommendations pass integer priority |
| `ContributionWaterfallService.php` | LISA allowance correctly accesses `['lifetime_isa']['annual_allowance']` instead of passing full array to `number_format()` |
| `RetirementActionDefinitionService.php` | Guards against null profile with early return for already-retired users |

### Investment Plan Isolation
| File | Change |
|------|--------|
| `BasePlanService.php` | `structureActions()` maps `headline` → `title`, `explanation` → `description` from spouse/transfer sources |
| `InvestmentPlanService.php` | Filters out non-investment `strategy_type`/`scan_type` values from pipeline output (pension, savings allowance, marriage allowance → holistic plan only) |
