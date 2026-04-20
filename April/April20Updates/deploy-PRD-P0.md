---
tags: [deploy, onboardingFyn, PRD, P0]
date: 2026-04-20
session: 2
status: ready-for-dev
---

# Deploy guide — PRD FR-M9 through FR-M15 (all P0)

**Branch:** `onboardingFyn` (HEAD `22a8dbe`)
**Source PRD:** `April/April20Updates/PRD-fyn-driven-onboarding.md`
**Target dev:** `csjones.co/fynla` (Laravel app at `~/www/csjones.co/fynla-app/`, sibling-dir layout)
**Target prod:** `fynla.org` — NOT YET. Dev needs browser verification first, then dev → main PR.

> **IMPORTANT — dev server branch awareness.** csjones.co/fynla is already running `onboardingFyn` (per CSJTODO session 1). Uploading this build OVERWRITES commit `88018a5` with commit `22a8dbe`. That is the intended behaviour for this deploy.

## What ships

7 Must-have items from the PRD — all tested, all P0.

| Item | Summary |
|------|---------|
| FR-M9 (C1) | `api/ai-chat/onboarding` added to `PreviewWriteInterceptor::EXCLUDED_ROUTES` |
| FR-M10 (G2) | Hybrid `base_personal` prompt — pre-confirms the already-captured field |
| FR-M11 (G1) | Feature tests (endpoint branches + state-machine walkthrough + multi-entity) |
| FR-M12 (F1) | `handleSetExpenditure` writes `ExpenditureProfile.total_monthly_expenditure` |
| FR-M13 (F2) | `SpouseCollisionException` + `emitTerminalError` + `onboarding_capture_error` SSE |
| FR-M14 (F3) | Asset-capture off-script guardrail (prompt + content filter) |
| FR-M15 (F5) | `TrustObserver::created` moves CLT auto-creation out of `handleCreateTrust` |

34 new tests, 0 regressions. Full regression run: 244 passing.

## Files to upload

All paths relative to `~/www/csjones.co/fynla-app/` on the server.

### PHP — backend

Upload these from the local repo via SiteGround File Manager or `rsync`:

```
app/Agents/CoordinatingAgent.php
app/Exceptions/SpouseCollisionException.php          (NEW)
app/Http/Middleware/PreviewWriteInterceptor.php
app/Observers/TrustObserver.php                      (NEW)
app/Providers/EventServiceProvider.php
app/Services/Onboarding/OnboardingChatDirector.php
app/Services/Onboarding/OnboardingPromptBuilder.php
app/Services/Onboarding/OnboardingStateMachine.php
app/Services/Onboarding/SpouseLinkingService.php
app/Traits/HasAiChat.php
```

### Tests — backend

(Not strictly needed on the server, but commit matches the repo layout. Skip if SiteGround chokes on file count.)

```
tests/Feature/Onboarding/AssetCaptureMultiEntityTest.php       (NEW)
tests/Feature/Onboarding/StartOnboardingEndpointTest.php       (NEW)
tests/Feature/Onboarding/StateMachineWalkthroughTest.php       (NEW)
tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php (NEW)
tests/Unit/Observers/TrustObserverTest.php                     (NEW)
tests/Unit/Services/Onboarding/AssetCaptureOffScriptFilterTest.php (NEW)
tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php
tests/Unit/Services/Onboarding/SpouseCollisionTest.php         (NEW)
```

### Frontend

**No frontend changes.** No Vite rebuild needed. No `public/build/` upload.

### Migrations / config / routes

- **No migrations.** No `php artisan migrate` step.
- **No config changes.** No `php artisan config:clear` needed for this deploy (routine clear is still fine — see below).
- **No route changes.** Routes already exist from prior deploys.

## SSH commands (dev — csjones.co)

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

Then seed (safe — no destructive ops):

```bash
php artisan db:seed --force
```

## Smoke test on dev (MANDATORY before signing off)

All journeys must be tested in the browser on `https://csjones.co/fynla`. Per `critical_browser_testing_law.md`, "tested" means CLICK/FILL/SUBMIT in Playwright.

### Test 1 — FR-M9 preview block
1. Open `https://csjones.co/fynla` in incognito, pick a preview persona from the landing page.
2. Once logged in as the persona, trigger the "Quick start with Fyn" CTA (or hit `/onboarding/start` via the chat widget if CTA is routed differently for preview).
3. **Expect:** `POST /api/ai-chat/onboarding/start` returns `403` with body `{success: false, reason: 'preview_mode'}`. Preview persona seeded data remains untouched.

### Test 2 — FR-M10 hybrid skip (partial base_personal)
1. Register a fresh user via `/register?from=fyn` → verify OTP → dashboard → Fyn opens.
2. Through path_choice + journey_selection, reach base_personal.
3. Type only a DOB ("12 January 1985"). The handler should write it. Fyn should then re-ask *only* for marital status with "Got it — I have you down as born on 12 January 1985..." phrasing.
4. Type only a marital status ("married"). Fyn should write marital, then pre-confirm with "Thanks — I have you noted as married. Could you share your date of birth?"

### Test 3 — FR-M11 state-machine walkthrough (happy path)
1. Fresh user, journey=Protecting and growing, marital=married.
2. Walk base_personal → base_spouse → base_dependants ("No") → base_employment ("Employed") → base_work (employer/role/income) → base_expenditure (£4000) → asset_capture (family, then Savings) → add_more → "I'm done" → `/net-worth/cash`.
3. Verify each transition in the chat UI.

### Test 4 — FR-M12 post-onboarding expenditure sync
1. Completed user, Fyn chat.
2. Type "My rent is £1500 and utilities are £300".
3. Fyn says "Expenditure updated..." AND `/valuable-info?section=expenditure` shows £1800/month.
4. Dashboard expenditure widget (reads `ExpenditureProfile.total_monthly_expenditure`) now shows £1800.

### Test 5 — FR-M13 spouse email collision
1. Fresh user A, reach base_spouse, supply `a@example.com` for a made-up spouse. Link succeeds.
2. Fresh user B (different household), reach base_spouse, supply same `a@example.com`.
3. Fyn emits: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"* (no generic retry).
4. State stays on base_spouse — next reply with a different email succeeds.

### Test 6 — FR-M14 off-script suppression (family focus)
1. Reach asset_capture with selection=family.
2. Type "My mum is 72".
3. Fyn records the family member via `create_family_member`. Output does NOT contain property/mortgage/rent/income questions. No `?` in Fyn's acknowledgment (it should be a statement or nothing — director may even suppress all content this turn).

### Test 7 — FR-M15 Trust CLT orphan prevention
1. Completed user, Fyn chat. Type "Add a discretionary trust called Test Trust, initial value £100,000".
2. Fyn responds with `fill_form` action. Trust form opens prefilled.
3. **Cancel** the form (do not save).
4. Query DB: `SELECT COUNT(*) FROM gifts WHERE user_id=? AND gift_type='clt'` → should be **0**.
5. Repeat flow, but this time **save** the trust form.
6. Query DB: should be **1** CLT gift with `gift_value=100000`.

## Rollback

If any smoke test fails:

```bash
# On server
cd ~/www/csjones.co/fynla-app
git fetch origin
git reset --hard 88018a5   # previous known-good commit
# Then re-upload public/build/ from the 88018a5 local build if Vite assets drifted.
# This deploy ships no frontend changes, so no Vite rebuild is needed for rollback either.
php artisan cache:clear && php artisan optimize
```

Or simply re-upload the pre-`22a8dbe` versions of the 10 PHP files from local. There are no schema changes to undo.

## What's NOT in this deploy

- **Production (fynla.org).** This deploy is dev-only. Prod deploy happens after ≥48h dev stability via a separate `dev → main` PR (per CLAUDE.md branch workflow).
- **P1/P2 items.** FR-S1, FR-S2, FR-S3, FR-S4 (P1) and FR-N1 through FR-N7 (P2) are still open. They land in subsequent PRs.
- **Browser E2E from CI.** FR-M11 includes feature tests but not Playwright. Manual browser verification at steps 1–7 above is the gate.
- **Merge to `dev` branch.** `onboardingFyn` is still 77 commits behind `origin/dev` (per CSJTODO). A separate merge-back PR is needed after dev testing passes. Cross-reference `CoordinatingAgent.php` and `AiToolDefinitions.php` per `feedback_merge_branch_conflicts` — both were touched on both branches.

## Sign-off checklist

- [ ] 10 PHP files uploaded to `~/www/csjones.co/fynla-app/`
- [ ] SSH cache/config/view/route clears + `optimize` run
- [ ] `php artisan db:seed --force` run
- [ ] Smoke tests 1–7 all pass
- [ ] Laravel log (`storage/logs/laravel.log`) checked for errors from this session
- [ ] CSJ notified with results
