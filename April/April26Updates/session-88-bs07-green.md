---
tags:
  - april-2026
  - bug-fix
  - feature
  - sprint-0
  - bs-nn
date: 2026-04-26
session: 88
---

# Session 88 — BS-07 GREEN + Goal cache observer fix

Walked BS-07 (dispatch flips to AdviceFyn after onboarding_completed) end-to-end via canonical Quick start with Fyn flow. Discovered a real user-facing bug while doing it — the dashboard "Goals & Life Events" chart was empty post-onboarding even though the goal was in the DB — and fixed it before claiming GREEN.

## BS-07 walk

Fresh user **Cassidy Greenwood** (`bs07d@example.com`, User #360, AiConversation #79).

| Step | Input | DB state after |
|---|---|---|
| Register + MFA | code 820842 | user created, onboarding_fyn_step=`path_choice` |
| Welcome-back resume | (after openFyn=journey re-entry) | one welcome-back row written (Issue 87-A did NOT reproduce) |
| `path_choice` → `journey_choice` | "Continue" → "Follow a journey" | step=`journey_choice` |
| `journey_choice` → `base_personal` | "Building Foundations" | path=`building_foundations`, step=`base_personal` |
| `base_personal` → `base_dependants` | "I was born on 5 May 1985 and I'm single" | dob=1985-05-05, marital=`single`, step skipped base_spouse, =`base_dependants` |
| `base_dependants` → `profile_review_family` | "No" | step=`profile_review_family` |
| `profile_review_family` → `base_employment` | "Looks correct" | step=`base_employment` |
| `base_employment` → `base_work` | "Full-time" | employment_status=`full_time`, step=`base_work` |
| `base_work` → `base_employment_more` | "I work for Globex Corp as a Product Manager earning £75,000 a year" | employer=Globex Corp, occupation=Product Manager, annual_employment_income=75000 |
| `base_employment_more` → `base_expenditure` | "No, that's everything" | step=`base_expenditure` |
| `base_expenditure` → `profile_review_expenditure` | "I spend about £2,500 a month" | monthly_expenditure=2500, step=`profile_review_expenditure` |
| `profile_review_expenditure` → `asset_capture` | "Looks correct" | step=`asset_capture` |
| `asset_capture` → `add_more` | "I want to save £15,000 for an emergency fund by 2028" | Goal #1103 created (Emergency Fund, 15000, 2028-12-31) |
| `add_more` → terminal | "I'm done" | onboarding_completed=true, step=null, path=null |

Terminal route: `/goals` (per session 86 spec amendment — "Building Foundations" terminal module is Goals, not /dashboard).

## AdviceFyn dispatch verification

After `/goals` landing, sent `"What's my net worth?"` via the chat input.

**Response (ai_messages id 192):** factual content message — *"Your current net worth is £0 — that's total assets (£0) minus total liabilities (£0). This is because we don't have details of your savings, investments, property, pensions, or debts yet. It could be worth exploring adding those to see your full position. What would you like to add first, like a savings account or your home?"*

**Verifications:**
- Zero `quick_replies` bubbles in DOM (no Continue / Something else / Yes / No / Looks correct buttons)
- Plain content layout, no onboarding wide-chat
- No `onboarding_field_captured` events
- Backend dispatch logic at `AiChatController::sendMessage:174-182` resolves `$inOnboarding=false` → routes to `$this->adviceFyn->handle(...)` ✓

## Bug-fix-in-loop — empty Goals chart on dashboard

While walking the test, the dashboard "Goals & Life Events" chart was visibly empty after onboarding completed even though Cassidy had just created a goal. Per CLAUDE.md Rule #15 LOOP UNTIL CORRECT, this routes through the plan's bug-fix-in-loop path — fixed before claiming GREEN.

### Root cause (two layers)

1. **Backend cache never invalidated.** `Goal::class` had no observer registered in `EventServiceProvider`. The chart's data source `/api/goals/projection` is built by `GoalsProjectionService::generateProjection()` which wraps in `Cache::remember(..., 24h)`. The chart had already mounted and fetched a stale empty projection during onboarding (before the goal was created), and Goal::create never invalidated the cache.

2. **Frontend Vuex never refreshed.** The aiChat `onboarding_complete` SSE handler set pending navigation to /dashboard or the journey's terminal module — but Vue Router silently no-ops on same-route navigation. No remount fired and Vuex `projectionData` stayed stale.

### Fix

**Backend** — new `app/Observers/GoalCacheObserver.php` mirrors `LifeEventMonteCarloObserver` pattern:

```php
public function created(Goal $goal): void { $this->clearCache($goal); }
public function updated(Goal $goal): void { $this->clearCache($goal); }
public function deleted(Goal $goal): void { $this->clearCache($goal); }

private function clearCache(Goal $goal): void {
    if ($goal->user_id) {
        $this->projectionService->clearCache($goal->user_id);
        $this->cacheInvalidation->invalidateForUser($goal->user_id);
    }
    if ($goal->joint_owner_id) {
        $this->projectionService->clearCache($goal->joint_owner_id);
        $this->cacheInvalidation->invalidateForUser($goal->joint_owner_id);
    }
}
```

Registered on `Goal::class` in `EventServiceProvider`.

**Frontend** — `resources/js/store/modules/aiChat.js` `onboarding_complete` handler now also dispatches:

```javascript
dispatch('auth/fetchUser', null, { root: true }).catch(() => {});
dispatch('goals/fetchProjection', null, { root: true }).catch(() => {});
dispatch('goals/fetchDashboardOverview', null, { root: true }).catch(() => {});
dispatch('netWorth/refreshNetWorth', null, { root: true }).catch(() => {});
```

`.catch(() => {})` matches established convention at `aiChat.js:737-738` (onboarding_layout_change handler).

### Verification

Re-walked from fresh user — `/goals` page shows Emergency Fund goal card (£15,000, 0% complete) and the projection chart marker plotted at age 43 / year 2028. Dashboard chart shows the same Emergency Fund marker with proper net worth growth projection (income £75k - expenditure £30k/year = £45k surplus driving wealth bars from £0 to £1.2M over the projection horizon).

Pest sweep: **486 passing / 1605 assertions / 0 failures** (95.10s). New observer does not regress baseline.

## Issues from session 87

- **Issue 87-A (duplicate welcome-back assistant message)**: did NOT reproduce in session 88. Cassidy's resume produced exactly one welcome-back row. Closed for now; will reopen if it surfaces again.
- **Issue 87-B (subscription_plans + tax_configurations wiped)**: did NOT reproduce. Session-start `php artisan db:seed --force` is the standard practice. Static-code analysis of `phpunit.xml` showed it lacks `DB_DATABASE` override (Pest tests would hit the primary `laravel` DB during a sweep), but mid-session evidence of an actual wipe was absent. Stayed inside scope; did not change phpunit.xml without active reproduction.

## Files changed

- `app/Observers/GoalCacheObserver.php` (new, 46 lines)
- `app/Providers/EventServiceProvider.php` (+3: import + observer registration)
- `resources/js/store/modules/aiChat.js` (+11: onboarding_complete dispatches)
- `tests/Browser/scenarios/BS-07-dispatch-flips-after-onboarding.php` (+88: delivery note docblock)
- `docs/sprint-0-verification/BS-07/01-dashboard-after-onboarding.png` (new)
- `docs/sprint-0-verification/BS-07/02-goals-page-after-im-done.png` (new)
- `docs/sprint-0-verification/BS-07/01-welcome-back.png` (deleted — session 87 partial)
- `docs/sprint-0-verification/BS-07/02-add-more-terminal.png` (deleted — session 87 partial)
- `tech-debt-report.md` (regenerated — 0 issues across 4 changed code files)

## Commits

- `285dfd5` — fix(goals): cache invalidation observer + dashboard refresh on onboarding completion (BS-07 GREEN)
- `4ea2d38` — docs: session 88 tech-debt report — 0 issues across 4 changed files

Both pushed to `origin/feature/fyn-persona-split`.

## Next session 89

S0.16b Batch 3 remaining: **BS-10, 13, 15, 17, 18, 19, 21, 22, 23**. BS-05 stays deferred per session 86. Sprint 0 work continues to stay local on `feature/fyn-persona-split` until S0.17 verification rollup is complete.

## Related

- [[April/April24Updates/plan/10-sprint-0-plan|Sprint 0 plan]] (gitignored — vault-only)
- [[April/April26Updates/CSJTODO|CSJTODO]]
- [[April/April26Updates/tech-debt-report-session-88|Tech debt report]]
- [[Architecture/v083/10-NEW-SYSTEMS|AI Chat architecture]]
- [[Current State/GoalsLifeEvents|Goals & Life Events module]]
