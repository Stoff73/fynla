# CSJTODO — Fynla

*Last updated: 20 April 2026 — session 2 (end-of-session, context-clear)*
*Previous session: 20 April 2026 — session 1 (morning, context-clear)*

---

## Session 2 (20 April, afternoon) — Full PRD P0 batch executed

### Completed This Session

- [x] **FR-M9 (C1 preview isolation)** — `app/Http/Middleware/PreviewWriteInterceptor.php`: added `'api/ai-chat/onboarding'` to `EXCLUDED_ROUTES` so the controller's 403 preview check runs instead of the middleware's fake-success 200. 5 endpoint tests in `tests/Feature/Onboarding/StartOnboardingEndpointTest.php` cover 401/403 (FR-M9 regression guard)/409/503/200 SSE.
- [x] **FR-M10 (G2 hybrid skip)** — new `OnboardingStateMachine::buildPersonalPrompt` callable referenced from `STATE_BASE_PERSONAL.prompt_text`. Adapts to pre-confirm the already-captured field when exactly one of {DOB, marital} is set. 4 new tests appended to `OnboardingStateMachineTest.php`.
- [x] **FR-M11 (G1 feature tests)** — three new files: `StartOnboardingEndpointTest.php` (endpoint branches), `StateMachineWalkthroughTest.php` (path_choice→done via HTTP + DB state simulation for grouped_extract; plus retired branch + add_more loop), `AssetCaptureMultiEntityTest.php` (mocked `CoordinatingAgent` generator with multiple tool_success forwards).
- [x] **FR-M12 (F1 expenditure sync)** — `app/Agents/CoordinatingAgent.php::handleSetExpenditure` now writes `ExpenditureProfile.total_monthly_expenditure` via `updateOrCreate` alongside the existing `users.*` write. 4 unit tests in `tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php`.
- [x] **FR-M13 (F2 spouse collision)** — new `app/Exceptions/SpouseCollisionException.php` + `onboarding_capture_error` SSE event added to `HasAiChat` trait + new `OnboardingChatDirector::emitTerminalError` method. Service throws the new exception; handler converts it to `error_type='spouse_collision'` result; director emits targeted terminal copy and leaves state on `base_spouse`. 3 unit tests in `tests/Unit/Services/Onboarding/SpouseCollisionTest.php`.
- [x] **FR-M14 (F3 off-script prevention)** — tightened `OnboardingPromptBuilder::assetCaptureInstructions` (explicit "Do NOT ask about property, mortgages, …" guardrail); selective content-event filter in `OnboardingChatDirector::handleAssetCaptureTurn` (swallow content with `?` OR in zero-tool-call turn; queue-and-flush preserves post-tool confirmations). 6 unit tests in `tests/Unit/Services/Onboarding/AssetCaptureOffScriptFilterTest.php`.
- [x] **FR-M15 (F5 trust CLT observer)** — new `app/Observers/TrustObserver.php` listens on `Trust::created` and writes the CLT `Gift` only when the trust row actually exists. `handleCreateTrust` no longer writes the Gift directly. Registered in `EventServiceProvider::$observers`. 3 unit tests in `tests/Unit/Observers/TrustObserverTest.php`.
- [x] **Full regression run** — 244 tests pass across `tests/Feature/Onboarding/`, `tests/Unit/Services/Onboarding/`, `tests/Unit/Agents/`, `tests/Feature/Middleware/`, `tests/Feature/Auth/`. 142 additional tests pass across `tests/Unit/Agents/` and `tests/Feature/Estate/`. Zero regressions.
- [x] **Committed + pushed** — two commits on `onboardingFyn`: `0a933fd` (PRD P0 feat) and `22a8dbe` (excalidraw auto-updates). Branch now at HEAD `22a8dbe`.
- [x] **Deploy guide written** — `April/April20Updates/deploy-PRD-P0.md` mirrored in vault. Lists 10 PHP files to upload to csjones.co/fynla (sibling-dir layout, `~/www/csjones.co/fynla-app/`), 7 smoke tests for browser verification, rollback path.
- [x] **Vault synced** — `fynlaBrain/Git History/Apr2026/Apr20.md` updated (3→7 commits, new session-2 summary narrative with per-item detail). `fynlaBrain/April/April Index.md` April 20 section expanded to two sessions + deploy-PRD-P0 + CSJTODO links. `fynlaBrain/Git History/Apr2026/Apr2026 Commits.md` total commits 408→412, Apr20 row updated to 7. `fynlaBrain/Home.md` total 2,608→2,612, April row 408→412.

### Source of truth for next session

**`/Users/CSJ/Desktop/fynla/April/April20Updates/deploy-PRD-P0.md`** — deploy guide for everything session 2 shipped. Smoke tests 1–7 are the gate between merged work and `dev → main` PR.

Also relevant:
- `April/April20Updates/PRD-fyn-driven-onboarding.md` — still the canonical contract. P0 all done; P1 (FR-S1 to FR-S4) and P2 (FR-N1 to FR-N7) open.
- `April/April15Updates/fynOnboardFix.md` §20 — delta register (what's shipped vs what's in scope)
- `April/April20Updates/fynComprehensiveCheck.md` — F1-F13 ledger with file:line; useful for P1/P2 context

### NOT Done — Outstanding

**Browser verification (MANDATORY before merging onboardingFyn to dev):**
- [ ] **Smoke tests 1–7** per `April/April20Updates/deploy-PRD-P0.md`. These run on csjones.co/fynla. Each test has explicit CLICK/FILL/SUBMIT steps. Per `critical_browser_testing_law.md`, don't mark done without Playwright interaction evidence.
  - 1 — FR-M9 preview persona blocked with 403 `preview_mode`
  - 2 — FR-M10 partial base_personal (DOB-only then marital-only)
  - 3 — FR-M11 state-machine walkthrough (fresh user, family journey, happy path)
  - 4 — FR-M12 post-onboarding "my rent is £X" shows on dashboard
  - 5 — FR-M13 spouse email collision → friendly terminal message, state preserved
  - 6 — FR-M14 off-script family asset_capture (no property/mortgage questions)
  - 7 — FR-M15 trust cancel → no orphan CLT; trust save → exactly one CLT

**Deploy (depends on smoke tests):**
- [ ] **Upload 10 PHP files to csjones.co/fynla** per deploy guide (only after smoke tests pass locally)
- [ ] **SSH cache clear + optimize + db:seed --force** on csjones.co/fynla
- [ ] **Re-run smoke tests 1–7 on dev server** (not just localhost)
- [ ] **Then and only then**: merge-back PR `onboardingFyn → dev`. Cross-reference `CoordinatingAgent.php` and `AiToolDefinitions.php` per `feedback_merge_branch_conflicts` — both were touched on both branches (branch is 77 commits behind origin/dev).
- [ ] **`dev → main` PR** only after ≥ 48h dev stability per CLAUDE.md branch workflow.

**Should-have (P1, in scope, follow-up PR after P0 green on dev):**
- [ ] **FR-S1 (F4)** — `handleUpdateRecord` per-entity field allowlist. Replace `getFillable()` boundary with a `private const ALLOWED_UPDATE_FIELDS` array on `CoordinatingAgent` keyed by the 12 entity types in `resolveModel()`. Explicitly omit `settlor` from trust, `start_date`/`term_years` from mortgage, `relationship` from family_member. Security-adjacent — the LLM can currently update any fillable field including `Trust.settlor` and `Mortgage.start_date`. _Touches: `CoordinatingAgent.php:2802-2858`._
- [ ] **FR-S2 (F6)** — apply `handleCaptureWorkDetails` partial-capture template to `handleCapturePersonalDetails` and `handleCaptureSpouseDetails`. Director's `composePartialRetryText` already has friendly-map entries for both tools. _Touches: `CoordinatingAgent.php:787`, `CoordinatingAgent.php:883`._
- [ ] **FR-S3** — extract duplicate `educationStatusForAge` to `OnboardingValueInterpreter::educationStatusForAge` (public static). Remove the duplicate from `CoordinatingAgent.php:1075` and `OnboardingChatDirector.php:582`. _Touches: those three files._
- [x] **FR-S4** — selective content-event filter (refinement of F3). ALREADY IMPLEMENTED as part of FR-M14 (see `OnboardingChatDirector::handleAssetCaptureTurn` queue-and-flush pattern). No additional work needed — keep as reference and cross out when merging the PRD record.

**Nice-to-have (P2, if time permits):**
- [ ] **FR-N1 (F7)** — surface `users.employer` + `users.occupation` in `SystemPromptBuilder::buildUserProfile` (after the employment status line). Removes post-onboarding hedging about work details. _Touches: `app/Services/AI/SystemPromptBuilder.php:180-279`._
- [ ] **FR-N2 (F8)** — `SystemPromptBuilder::calculateTotalExpenditure` fallback to `ExpenditureProfile.total_monthly_expenditure` after the `users.*` checks. Mirrors `KycGateChecker::checkUniversalRequirements` order. _Touches: `SystemPromptBuilder.php:963-974`._
- [ ] **FR-N3 (F9)** — duplicate-name checks on 7 create handlers (`create_trust`, `create_family_member`, `create_business_interest`, `create_estate_asset`, `create_estate_liability`, `create_estate_gift`, `create_chattel`) following the `handleCreateSavingsAccount:1531` template. _Touches: 7 handlers in `CoordinatingAgent.php`._
- [ ] **FR-N4 (F10)** — `handleUpdateProfile` spouse-linked-user sync. Mirror non-identity fields to the linked spouse user when `household_id` is set. _Touches: `CoordinatingAgent.php:2946`._
- [ ] **FR-N5 (F11)** — `handleSetExpenditure` spouse sync for household budget. Same household mirror pattern as F10, applied to expenditure. _Touches: `CoordinatingAgent.php:2748`._
- [ ] **FR-N6 (F12)** — add missing routes to `navigate_to_page` allow-list: `/estate/inheritance-tax`, `/settings/privacy`, `/risk-profile/levels`, `/risk-profile/factor/:factor`, `/planning/what-if/:id`, `/actions/:planType/:actionId`, `/plans/goal/:goalId`. _Touches: `app/Services/AI/AiToolDefinitions.php:60`._
- [ ] **FR-N7 (F13)** — `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance (apply the work-capture template). _Touches: `CoordinatingAgent.php:2097`, `CoordinatingAgent.php:2170`._

### Context for Next Session

**Branch:** `onboardingFyn` at HEAD `22a8dbe`, clean working tree, pushed to origin. 77 commits behind origin/dev (unchanged — no merge attempted this session).

**Start here:**
1. Read `April/April20Updates/deploy-PRD-P0.md` end-to-end — it's the gate for every subsequent step.
2. Run smoke tests 1–7 on `http://localhost:8000` first (dev server, seeded). Use `php artisan tinker` to fetch verification codes for `john@example.com` per CLAUDE.md Authentication for Testing.
3. If local smoke tests pass, deploy to csjones.co/fynla per the guide (sibling-dir layout, `ssh-add -l` first to verify `fynlaDev` key is unlocked per `reference_csjones_ssh_access`).
4. Re-run smoke tests 1–7 on https://csjones.co/fynla. Production/dev parity is mandatory per `feedback_always_test_locally`.
5. If both pass, open the `onboardingFyn → dev` merge-back PR — cross-reference `CoordinatingAgent.php` and `AiToolDefinitions.php` diffs because those were touched on BOTH branches since the branch point.
6. After that PR merges + 48h dev stability: `dev → main` PR for production rollout.
7. THEN start on the Should-have batch (FR-S1 first — it's security-adjacent and narrowly scoped).

**Important warnings inherited from feedback files:**
- Don't skip smoke tests. Don't mark [x] without Playwright evidence. (`critical_browser_testing_law.md`)
- Don't rebuild Vite for this deploy. No frontend changes in this commit.
- When SSH'ing to csjones.co, check `ssh-add -l` first for `fynlaDev`; ask CSJ for the passphrase only once if not unlocked.
- Fyn chat is a banned icon surface — any prompt text changes must remain icon-free.
- Dev server (csjones.co/fynla) is currently on `onboardingFyn` per session-1 CSJTODO — uploading this deploy OVERWRITES commit `88018a5` with `22a8dbe`. Intended.

### Carried from earlier sessions

- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deeper scenarios beyond the 4 bug-fix happy path. (Partially addressed by the P0 smoke tests above once run.)
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md`** to the vault — flagged session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing.

---

## Outstanding — Tech Debt Deferred

- [ ] `handleSetExpenditure` spouse sync (F11 — in release scope as Nice-to-have FR-N5)
- [ ] `handleUpdateProfile` spouse sync (F10 — in release scope as Nice-to-have FR-N4)
- [ ] 7 entity types missing duplicate-name checks (F9 — in release scope as Nice-to-have FR-N3)
- [ ] NPM `--force` audit (vite 8 + @capacitor/cli 8 major bumps) — deferred pending iOS regression window
- [ ] `AutoRiskCalculatorTest` enum truncation — pre-existing since 16 April, not related to this work

## Known Issues

- **`handleUpdateRecord` allows LLM to update any fillable field** — tracked as F4/FR-S1 in PRD (P1). Includes `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`. Security-adjacent.
- **Post-onboarding "I have a spouse whose email is already in use" now produces a clean error** (FR-M13 fixed) but not yet browser-verified on csjones.co. Smoke test 5.
- **`handleCapturePersonalDetails` + `handleCaptureSpouseDetails` still all-or-nothing** — FR-S2 fix pending.

## Deploy Status

**Production (fynla.org):** Running commit `a14f17a` (PR #219 Admin Insights CMS) + `062c7c7` (tooling audit). Full Admin Insights CMS live. NO onboardingFyn changes deployed to prod yet.

**Dev (csjones.co/fynla):** Running `88018a5` post the session-1 deploy (4 bug fixes from 16 April Fyn test). The session-2 P0 batch (`0a933fd`) is **NOT yet deployed** — it's committed + pushed but awaiting smoke tests + deploy by the next session.

**Pending deploy path:** `onboardingFyn @ 22a8dbe → csjones.co/fynla` (after local smoke tests pass) → **merge-back** `onboardingFyn → dev` (after csjones smoke tests pass, with conflict cross-reference) → `dev → main` (after ≥48h dev stability) → production deploy to fynla.org.
