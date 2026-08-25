---
tags:
  - april-2026
  - tech-debt
  - sprint-0
date: 2026-04-26
session: 95
---

# Session 95 Tech Debt Report

**Scope:** All session-95 changes across 11 changed files (3 production code, 6 BS-NN docblocks, 2 commits to feature/fyn-persona-split).

**Result:** **0 issues across all changed files.**

## Files audited

### Production code (3)
1. `app/Models/AiConversation.php` — added `scopeOnboarding(Builder)` filtering on `metadata->source = 'fyn_onboarding'`. Single-purpose, tested via downstream call sites. Mirrors the existing `scopeActive` + `scopeForUser` patterns in the same file. No duplication, no dead code.
2. `app/Services/Onboarding/OnboardingChatDirector.php` — replaced `where('title', 'Onboarding')` with `->onboarding()` scope call (1 line); added 4 cases to the `describeStep` match expression. Clean.
3. `app/Http/Controllers/Api/AiChatController.php` — same scope replacement in the `startOnboarding` resume branch. Clean.

### Test docs (6)
- `tests/Browser/scenarios/BS-{01,02,04,06,07,10}-*.php` — session-95 GREEN delivery notes appended to existing docblocks. No test code change.

## Convention compliance

- ✅ PSR-12 (verified via Pint pass at 113.62s baseline)
- ✅ `declare(strict_types=1);` already present in all touched files
- ✅ No hardcoded tax values introduced (none of the changed code touches tax)
- ✅ No design-system violations (no UI changes)
- ✅ No acronyms in user-facing text (no UI changes)
- ✅ No emoji / decorative icons (per Rule #14)
- ✅ Full British English spelling in user-facing strings (no new strings)
- ✅ No CSS / hex hardcoding (no CSS changes)

## Duplication check

The new `scopeOnboarding` consolidates two prior copies of the title filter:
- `OnboardingChatDirector::getOnboardingStatus` line 478 (pre-fix)
- `AiChatController::startOnboarding` resume branch line 294 (pre-fix)

Both call sites now share a single source of truth (`AiConversation::scopeOnboarding`). Net duplication delta: −1 (one duplicate path eliminated, no new ones introduced).

## Pest baseline

```
Tests:    529 passed (1968 assertions)
Duration: 113.62s
```

No regressions. Fixes are additive (new scope helper + new match arm cases).

## Carry-forward

None. Session 95 introduced zero new tech debt.
