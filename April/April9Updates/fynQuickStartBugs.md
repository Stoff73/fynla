# Fyn Quick Start Flow — Bug Report

**Date:** 9 April 2026
**Status:** Deferred — CTA hidden, state leak fixed. Root causes documented for later fix.
**Flow:** Landing page "Quick start with Fyn" → Register → Dashboard with Fyn chat → Select journey stage

---

## Issues Found

### 1. Production database missing column/enum values

**`dc_pensions.current_value` column does not exist on production.**
- `LifeStageService::hasPensionValueAbove()` calls `sum('current_value')` on `dc_pensions`
- Fires on EVERY dashboard load for EVERY user via `LifeStageController::getProgress()`
- Error: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'current_value'`
- **Fix:** Run the migration that adds `current_value` to `dc_pensions` on production

**`users.employment_status` enum missing `full_time` value.**
- AI `update_profile` tool sends `full_time` but column rejects it
- Error: `Data truncated for column 'employment_status' at row 1`
- **Fix:** Add `full_time` to the enum, or map `full_time` → `employed` in the tool handler

**`users.plan` enum missing `family` value.**
- Migration `ba6ada5` added it but hasn't been run on production
- Error: `Data truncated for column 'plan'` when Revolut webhook sets plan to `family`
- **Fix:** Run pending migration on production

### 2. Fyn analyses empty data for new users — should not happen

When a new user selects a journey stage, `CoordinatingAgent::chat()` calls `buildFinancialContext()` → `orchestrateAnalysis()` which runs all module agents (Estate, Investment, Retirement, etc.) against a user with zero data. This is wasteful and can cause:

- AI hallucinating financial figures (e.g. "Your £75,000 income..." when user has no income data)
- Module agents erroring on null/empty data
- Misleading personalised advice for someone who hasn't entered anything

**Expected behaviour:**
1. New user selects journey stage (e.g. "Starting out")
2. Fyn responds with generic, stage-appropriate guidance — NO financial analysis, NO numbers
3. Fyn asks the user what they'd like help with first (enter income, set a goal, explore the dashboard)
4. Financial analysis only runs AFTER the user has entered data

**Fix options:**
- A: Add a "has data" check in `buildSystemPrompt()` — if user has no income, no assets, no pensions, skip `orchestrateAnalysis()` and use a lightweight "new user" system prompt instead
- B: Add a `journey_stage` context to the system prompt that tells the AI "this user just registered and has no data — do NOT reference specific financial figures"
- C: Both A and B

### 3. Production SSE stream may fail mid-response

If `orchestrateAnalysis()` hits the `dc_pensions.current_value` error during the system prompt build, the SSE stream may return an incomplete/errored response. The frontend `sendMessage` action catches this gracefully (shows "Connection lost"), but if the error happens during streaming after partial content has been written, the UI may be left in an inconsistent state.

**Fix:** Ensure `buildFinancialContext()` wraps each module analysis in a try/catch so one failing module doesn't crash the entire system prompt build. (This may already exist via `safeModuleAnalysis()` — verify.)

---

## Fixes Applied This Session

1. **Hidden the "Quick start with Fyn" CTA** on the landing page Meet Fyn section — prevents new users from hitting this broken flow until the backend issues are resolved
2. **Cleared aiChat state on registration** — `completeRegistration()` now resets the aiChat Vuex store before navigating to the dashboard, ensuring no prior user's conversation/messages leak to a new session
3. **Cleared aiChat state on login** — same reset in login completion paths

---

## Files to Fix Later

| File | Issue |
|------|-------|
| `app/Services/LifeStage/LifeStageService.php:194` | `sum('current_value')` on `dc_pensions` — column missing on prod |
| `app/Traits/HasAiChat.php:~530` | `buildSystemPrompt` should skip analysis for users with no data |
| `app/Services/AI/SystemPromptBuilder.php` | Add "new user" prompt path that doesn't reference financial figures |
| `database/migrations/` | Pending migration for `dc_pensions.current_value` needs running on production |
| `database/migrations/` | `users.employment_status` enum needs `full_time` added |
| Production server | Run `php artisan migrate` to apply pending migrations |
