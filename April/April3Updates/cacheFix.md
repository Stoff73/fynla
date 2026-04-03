# Cache Fix — 24-Hour TTL with Centralised Invalidation

**Date:** 3 April 2026
**Branch:** TBD
**Status:** Planned

---

## Problem

Production (`fynla.org`) uses the `file` cache driver. Laravel's file driver does **not** support cache tagging. The app uses `Cache::tags()` in 4 locations — all fail silently:

| Location | What fails |
|----------|-----------|
| `BaseAgent.php:52` | Tagged `remember()` — falls back to non-tagged (line 55) |
| `BaseAgent.php:111` | Tagged `flush()` — but `Cache::forget()` fallback on line 117 covers it |
| `LifeEventMonteCarloObserver.php:52` | Tagged `flush()` — generates warnings in logs |
| `UserProfileController.php:82,91,207,217` | Tagged `flush()` — protection cache not cleared (estate has `forget` fallback) |

Additionally, cache TTLs are fragmented (5 min to 24 hrs) and **no controller clears the dashboard cache when module data changes**. Users can see stale dashboard data for up to 5 minutes.

Redis is **not available** on the SiteGround production server (no PHP extension, no Redis server).

---

## Solution

### 1. Create `CacheInvalidationService`

**New file:** `app/Services/Cache/CacheInvalidationService.php`

A single service responsible for clearing all user-specific caches. Called from every controller that modifies financial or personal data.

```php
class CacheInvalidationService
{
    public function invalidateForUser(int $userId): void
    {
        // Dashboard & alerts
        Cache::forget("dashboard_{$userId}");
        Cache::forget("alerts_{$userId}");
        Cache::forget("mobile_dashboard_{$userId}");

        // Module analysis
        Cache::forget("protection_analysis_{$userId}");
        Cache::forget("savings_analysis_{$userId}");
        Cache::forget("estate_analysis_{$userId}");
        Cache::forget("retirement_analysis_{$userId}");
        Cache::forget("retirement_projection_{$userId}");
        Cache::forget("retirement_income_{$userId}");

        // Agent caches (v1_{agent}_{userId}_{suffix})
        $agents = ['protectionagent', 'savingsagent', 'investmentagent',
                   'retirementagent', 'estateagent', 'goalsagent', 'coordinatingagent'];
        $suffixes = ['analysis', 'recommendations', 'scenarios', 'summary', 'projection'];
        foreach ($agents as $agent) {
            foreach ($suffixes as $suffix) {
                Cache::forget("v1_{$agent}_{$userId}_{$suffix}");
            }
        }

        // Holistic planning
        Cache::forget("holistic_plan_{$userId}");
        Cache::forget("holistic_analysis_{$userId}");

        // Plans (by type)
        $planTypes = ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals'];
        foreach ($planTypes as $type) {
            Cache::forget("plan_{$type}_{$userId}");
        }

        // Net worth
        Cache::forget("net_worth_overview_{$userId}");
        Cache::forget("net_worth_breakdown_{$userId}");

        // Goals projections
        Cache::forget("goals_projection_{$userId}_individual");
        Cache::forget("goals_projection_{$userId}_household");

        // Risk profile
        Cache::forget("user_risk_level_{$userId}");
        Cache::forget("risk_profile_{$userId}");

        // Profile completeness
        Cache::forget("profile_completeness_{$userId}");

        // Investment analytics
        Cache::forget("fee_analysis_{$userId}");

        // Coordinating agent / AI context
        Cache::forget("v1_coordinating_{$userId}_analysis");
        Cache::forget("ai_financial_context_{$userId}");
        Cache::forget("ai_existing_records_{$userId}");
        Cache::forget("ai_income_defs_{$userId}");

        // Module summary (mobile)
        $modules = ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals'];
        foreach ($modules as $module) {
            Cache::forget("module_summary_{$module}_{$userId}");
        }
    }

    public function invalidateForUserAndSpouse(int $userId, ?int $spouseId): void
    {
        $this->invalidateForUser($userId);
        if ($spouseId) {
            $this->invalidateForUser($spouseId);
        }
    }
}
```

### 2. Extend All TTLs to 24 Hours

| Cache | Current TTL | New TTL | How |
|-------|------------|---------|-----|
| Dashboard overview | 300s (5 min) | 86400s (24 hrs) | `DashboardController.php:32` |
| Dashboard alerts | 900s (15 min) | 86400s (24 hrs) | `DashboardController.php:55` |
| Mobile dashboard | 300s (5 min) | 86400s (24 hrs) | `MobileDashboardAggregator.php:36` |
| Module summaries | 300s (5 min) | 86400s (24 hrs) | `ModuleSummaryController.php:65` |
| Agent analysis/recs | 3600s (1 hr) | 86400s (24 hrs) | `TaxDefaults::CACHE_TTL_STANDARD` |
| Holistic analysis | 3600s (1 hr) | 86400s (24 hrs) | Uses `CACHE_TTL_STANDARD` |
| Holistic plan | 86400s (24 hrs) | 86400s (24 hrs) | Already 24 hrs via `CACHE_TTL_SIMULATION` |
| Goals projections | 1800s (30 min) | 86400s (24 hrs) | `GoalsProjectionService.php:36` |
| Net worth | 1800s (30 min) | 86400s (24 hrs) | `NetWorthService.php:557` |
| Risk profile | 3600s (1 hr) | 86400s (24 hrs) | `RiskPreferenceService.php:192` |
| Investment analytics | 1800-3600s | 86400s (24 hrs) | Fee, attribution, tax opt, asset location, goal progress controllers |
| Portfolio optimisation | 3600s (1 hr) | 86400s (24 hrs) | `PortfolioOptimizationController.php` |
| Mobile insights | 3600s (1 hr) | 86400s (24 hrs) | `InsightsController.php:34` |
| Monte Carlo (DB) | 24 hrs | **No change** | Already correct |
| Postcode lookup | 3600s (1 hr) | **No change** | Not user-specific |
| Advisor dashboard | 300s (5 min) | **No change** | Advisor-specific |

**AI chat caches — no change:**

| Cache | TTL | Reason |
|-------|-----|--------|
| `ai_financial_context_{$userId}` | 120s (2 min) | Fyn needs live data during conversation |
| `ai_existing_records_{$userId}` | 60s (1 min) | Fyn needs live data during conversation |
| `ai_income_defs_{$userId}` | 120s (2 min) | Fyn needs live data during conversation |
| `ai_tax_info_{$topic}` | 300s (5 min) | Topic-specific, not user-specific |

### 3. Remove All `Cache::tags()` Calls

Replace with `Cache::forget()` or delegate to `CacheInvalidationService`.

| File | Line(s) | Change |
|------|---------|--------|
| `BaseAgent.php` | 50-53 | Remove tagged branch, keep only `Cache::remember()` on line 55 |
| `BaseAgent.php` | 109-112 | Remove tagged flush block (lines 114-127 already handle `forget`) |
| `BaseAgent.php` | 62-68 | Remove `cacheStoreSupportsTagging()` method |
| `LifeEventMonteCarloObserver.php` | 47-59 | Replace `Cache::tags()` flush with `CacheInvalidationService::invalidateForUser()` |
| `UserProfileController.php` | 82, 91, 207, 217 | Replace `Cache::tags()` flush with `CacheInvalidationService::invalidateForUserAndSpouse()` |

### 4. Add `CacheInvalidationService` to All Data-Modifying Controllers

Every controller that does store/update/delete on user financial or personal data must call `invalidateForUser()` or `invalidateForUserAndSpouse()`.

| Controller | Methods needing invalidation | Joint? |
|-----------|------------------------------|--------|
| `SavingsController` | store, update, delete, addGoal, removeGoal, updateInterestRate, updateBalance, deposit, withdraw | Yes (joint_owner_id) |
| `EstateController` | All 9 CRUD methods (gifts, beneficiaries, etc.) | Yes (spouse_id) |
| `RetirementController` | store/update/delete pensions | Yes (spouse) |
| `DCPensionHoldingsController` | store, update, delete, rebalance | No |
| `UserProfileController` | updateProfile, updateFinancialProfile | Yes (spouse) |
| `FamilyMembersController` | linkSpouse, unlinkSpouse, addChild, removeChild | Yes (spouse) |
| `ProtectionController` | CRUD on policies | Yes (spouse) |
| `InvestmentController` | CRUD on accounts + holdings | Yes (joint) |
| `PropertyController` | CRUD on properties + mortgages | Yes (joint) |
| `GoalsController` | CRUD on goals + life events | No |
| `LifeEventController` | CRUD on life events | No |
| `OnboardingService` | completeOnboarding | Yes |
| `TrustController` | store, update, delete | No |
| `WillController` | store, update, delete, addGuardian | No |
| `HolisticPlanningController` | regenerate, dismissAction | No |
| `PlanController` | regenerate | No |

### 5. Clean Up Existing Scattered `Cache::forget()` Calls

Once `CacheInvalidationService` handles all invalidation, the individual `Cache::forget()` calls in controllers become redundant. They can be removed to keep things clean — the service handles everything.

**Exception:** Keep `Cache::forget()` calls that are NOT user-financial-data related:
- `MFAController` — security tokens
- `GDPRController` — deletion codes
- `AdvisorImpersonationMiddleware` — impersonation tokens
- `PostcodeLookupController` — external API cache

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Services/Cache/CacheInvalidationService.php` | Centralised cache invalidation |

## Files to Modify

| File | Change |
|------|--------|
| `app/Constants/TaxDefaults.php` | Change `CACHE_TTL_STANDARD` from 3600 to 86400 |
| `app/Agents/BaseAgent.php` | Remove `Cache::tags()` branches + `cacheStoreSupportsTagging()` |
| `app/Observers/LifeEventMonteCarloObserver.php` | Replace `Cache::tags()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/UserProfileController.php` | Replace `Cache::tags()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/DashboardController.php` | TTL 300 -> 86400, 900 -> 86400 |
| `app/Http/Controllers/Api/SavingsController.php` | Replace scattered `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/EstateController.php` | Replace scattered `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/RetirementController.php` | Add `CacheInvalidationService` call |
| `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` | Replace scattered `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/FamilyMembersController.php` | Replace scattered `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/HolisticPlanningController.php` | Replace scattered `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/Plans/PlanController.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/PortfolioOptimizationController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/Investment/PerformanceAttributionController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/Investment/TaxOptimizationController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/Investment/FeeImpactController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/Investment/GoalProgressController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/Investment/AssetLocationController.php` | TTL + invalidation |
| `app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php` | TTL 300 -> 86400 |
| `app/Http/Controllers/Api/V1/Mobile/InsightsController.php` | TTL 3600 -> 86400 |
| `app/Http/Controllers/Api/Estate/TrustController.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Http/Controllers/Api/Estate/WillController.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Services/Goals/GoalsProjectionService.php` | TTL 1800 -> 86400 |
| `app/Services/NetWorth/NetWorthService.php` | TTL 1800 -> 86400 |
| `app/Services/Risk/RiskPreferenceService.php` | TTL 3600 -> 86400 |
| `app/Services/Mobile/MobileDashboardAggregator.php` | TTL 300 -> 86400 |
| `app/Services/Onboarding/OnboardingService.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Services/Retirement/RetirementProjectionService.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Services/Investment/InvestmentProjectionService.php` | Add `CacheInvalidationService` call |
| `app/Services/Estate/LpaService.php` | Replace `forget()` with `CacheInvalidationService` |
| `app/Agents/ProtectionAgent.php` | Replace `forget()` with `CacheInvalidationService` |

**Total: 1 new file, ~30 modified files**

---

## What Does NOT Change

- **Monte Carlo DB cache** — already 24 hrs with its own `clearUserCache()` in `MonteCarloSimulator.php`
- **AI chat caches** — kept at 1-2 min TTLs for live conversation accuracy
- **Postcode lookup** — external API, not user-specific
- **Advisor dashboard** — advisor-specific cache
- **MFA / GDPR / security caches** — not financial data
- **`TaxDefaults::CACHE_TTL_SIMULATION`** — already 86400 (24 hrs)

---

## How It Works After the Fix

1. User loads dashboard -> data calculated, cached for 24 hours
2. User updates a savings account -> `CacheInvalidationService::invalidateForUser()` clears ALL caches for that user (and spouse if applicable)
3. User loads dashboard again -> fresh calculation, cached for a new 24 hours
4. User does nothing for 23 hours -> dashboard still serves from cache (fast)
5. Monte Carlo runs once per 24 hours per simulation key (DB-backed, unchanged)

**Result:** Zero unnecessary recalculation. Instant invalidation on data change. Works with the file cache driver. No Redis required.
