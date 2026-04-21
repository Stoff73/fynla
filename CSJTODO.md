# CSJTODO — Fynla

*Last updated: 21 April 2026 — session 2 (context-clear)*
*Previous sessions: 21 April 2026 — session 1 (morning, EOD for 20 Apr); 20 April 2026 — sessions 1–4*

---

## Session 2 (21 April, afternoon — context-clear) — Fyn persona split spec + plan + PRD

### Completed this session

- [x] **Brainstormed Fyn persona split** via `superpowers:brainstorming`. Wide-scope decision: two personas (`advice`, `data_capture`), intent-driven, structured tool-call handoffs, new `FynPersonaOrchestrator` handling post-onboarding turns only.
- [x] **Design spec written** at `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md`. Committed as `48a30e7`, revised to `11d82e5`, amended post-audit to `455cba3`.
- [x] **40-task implementation plan** produced via `superpowers:writing-plans` at `April/April21Updates/plan-fyn-persona-split.md` (6,037 lines). Has AMENDMENTS block at top post-audit.
- [x] **Extended scope via user feedback** — wide-chat layout + dashboard blur, profile review pauses at dependants + expenditure, spouse skip (raspberry inline link), multi-job capture loop, Full-time bubble rename + Other removal, conversational retraction, journey-tagged handover, prompt clearing at pause boundaries, onboarding memory, resume-from-where-left-off, fact parking with gap-filling follow-ups.
- [x] **Codebase audit** via `feature-dev:code-explorer` + `feature-dev:code-architect` in parallel. 19 conflicts / architectural concerns surfaced.
- [x] **Nine decisions resolved** through rolling user interview: keep director + prompt builder (don't absorb/delete), use existing `LastingPowerOfAttorney` (not new `PowerOfAttorney`), add 3 columns to `wills` table, new `FynOnboardingChat.vue` (separate from post-onboarding `AiChatPanel.vue`), action endpoint replaces sentinel strings, promote existing `QueryClassifier` (no new `FynIntentClassifier`), parking column is the single source of memory (drop `OnboardingMemoryExtractor`), two feature flags only, silent code-correctness fixes applied.
- [x] **Spec + plan amended** against audit. Plan has AMENDMENTS block at the top overriding any conflicting original task body.
- [x] **PRD written** at `April/April21Updates/PRD-fyn-persona-split.md` (404 lines, 9 canonical sections fully populated).
- [x] **All three mirrored to fynlaBrain vault** at `fynlaBrain/April/April21Updates/`.
- [x] **Side tasks:** deleted local DB users `c.jones@csjones.co` (id 134) and `slaterjoneschris@gmail.com` (id 138) plus orphaned subscriptions/conversations/family_members; reverted accidental edit to `deploy/deployRevolut.md` test-results table; fetched verification codes for `test@fynla.org` during user testing.

### Not done — outstanding

**Ready for implementation but NOT started:**
- [ ] **Review PRD + amended plan** one more time with user before implementation kickoff — optional pass; everything material is captured.
- [ ] **Branch for implementation:** `git checkout -b feature/fyn-persona-split` off current `onboardingFyn`. Do NOT execute on `onboardingFyn` directly.
- [ ] **Phase 1 foundation (Tasks 1–3):** `config/fyn.php` flags, `CaptureContext` value object, `HandoffContract` constants. Low-risk, no external dependencies.
- [ ] **Phase 2 persistence (Tasks 4–5 + new Task 4a):** migrations for `ai_messages.persona`, `ai_conversations.persona_state`, factory creation (`AiConversationFactory`, `AiMessageFactory`) as prerequisite.
- [ ] **Phases 3–14:** the remaining 34+ amended tasks — prompt builders, handoff tools, estate tools (Will + LPA), orchestrator + invoker + registry, classifier promotion, controller wiring, onboarding UX overhaul (within director), chat UI, preview mode, rollout.

**Carried from earlier branches (still outstanding — not in this release's scope):**
- [ ] **Gate 1 — onboardingFyn branch to csjones.co/fynla.** Build + upload + SSH cache clear + smoke tests 1/2A/2B/3/4/5/6/6b/7. Must precede merge-back PR `onboardingFyn → dev`. See `April/April20Updates/deploy-PRD-P0.md` (regenerated in 20 April session 4).
- [ ] **Test 5 (FR-M13 spouse collision)** — still pending browser verification on commit `039b258`. xAI API was returning `Connection refused` during attempted verification last session.
- [ ] **Test 1 (FR-M9 preview block)** — still pending post-remap browser re-verification.
- [ ] **DB persistence gap in `HasAiChat::chat()`** — off-script filter operates on SSE stream only; `AiMessage::create` writes raw unfiltered LLM text. Not in persona-split scope, but related. Flag before the next persona-split test cycle.
- [ ] **Family-member `fill_form` load race** — logged session 3 of 20 April. Exists for module-page usage; removed from onboarding after journey remap.

### Known issues

- **Resume-from-where-left-off in production is broken.** Fixed by this release (FR-M20). Until the release ships, returning mid-onboarding users see a fresh first turn.
- **`describeStep()` $user scope bug** in the original plan text — AMENDMENTS §F mandates passing `?User $user` as a parameter. Next Claude must apply this at implementation time; the original task body has the bug.
- **`conversation_id` vs `ai_conversation_id`** — original plan text has the wrong column name throughout. AMENDMENTS §F documents the correct name. Apply at implementation time; pre-existing `AiMessage::$fillable` is authoritative.
- **`CoordinatingAgent::chatWithPromptOverride()` signature** — 8 params, not 5. Tools in `toolsListOverride`, not `allowedTools`. AMENDMENTS §F documents the correct call.
- **`OnboardingStateMachine::getState()` is not public today** — Task 24 test calls it. Either add the method or rewrite the test at implementation time.

### Deploy status

**Production (fynla.org):** Still running `a14f17a` + `062c7c7`. Full Admin Insights CMS live. NO onboardingFyn or persona-split changes on production.

**Dev (csjones.co/fynla):** Still on `88018a5` from 16 April. Sessions 2–4 of 20 April NOT deployed. Session 1 of 21 April (EOD for 20 April) NOT deployed. Session 2 of 21 April (this session) — DOCUMENTATION ONLY, no deployable code produced.

**This session deployed nothing** — all outputs are docs (spec + plan + PRD) and vault mirrors.

### Pending deploy path

```
onboardingFyn @ 455cba3
    → csjones.co/fynla (Gate 1 — still pending from 20 April)
    → merge-back PR `onboardingFyn → dev` (Gate 2 — conflict cross-ref on 6 files)
    → ≥48h dev stability (Gate 3)
    → `dev → main` PR
    → production deploy to fynla.org

feature/fyn-persona-split @ TBD   (NEW — to be branched off onboardingFyn when implementation begins)
    → same gates once implementation is complete
```

---

## Session 4 (20 April, afternoon) — FR-M14 buffered filter + journey remap + partial browser re-verification

### Completed This Session

- [x] **FR-M14 follow-up (`fd3ff44`)** — Buffered sentence-level off-script filter in `OnboardingChatDirector::handleAssetCaptureTurn`. Accumulates content deltas for the whole turn, then splits on sentence boundaries and drops any sentence containing `?` OR any of `property|mortgage|rent|income|home|address|ownership|valuation` (plural-tolerant, word-bounded) when `selection` is not `protection` or `estate`. Prompt tightened in `OnboardingPromptBuilder::assetCaptureInstructions` to explicitly ban questions with or without `?` and to allow empty acknowledgments.
- [x] **FR-M14 companion fix (`fd3ff44`)** — `resources/js/store/modules/aiChat.js` now clears `streamingText` on the `done` SSE event. Prevents the duplicate-assistant-message bug that was exposed by the buffered filter: a successful asset_capture turn followed by the director's `quick_replies` (add_more) event was re-committing the same text via the fallback flush branch.
- [x] **7 new unit tests** in `AssetCaptureOffScriptFilterTest.php` — the Test 6 exact regression payload; questions without `?`; estate/protection whitelist; mixed-sentence partial filtering; word-boundary edge cases (homework). 163/163 onboarding suite passing.
- [x] **Journey remap (`039b258`)** — Design-level correction. `Protecting and growing` journey was mapped to `selection=family`, causing a third family prompt (`asset_capture(family)` asking about parents/adult children) after `base_spouse` + `base_dependants`. Topic contiguity broken: family → employment → expenditure → family again. Remapped: `Protecting and growing → protection`, `Planning your future → retirement`. Removed `family` focus from `OnboardingPromptBuilder::toolsForFocus` and `buildAssetCaptureIntro`. Extended-family capture is module-page-only now, not mid-onboarding.
- [x] **PRD + deploy guide amended** — `April/April20Updates/PRD-fyn-driven-onboarding.md` Scenario 1 rewritten to reflect the correct happy path (expenditure → `asset_capture(protection)`, no family intermission). `April/April20Updates/deploy-PRD-P0.md` smoke Test 3 updated (journey ends at protection, not family) and Test 6 rewritten to exercise the filter via savings focus (family focus no longer reachable). New Test 6b added covering the no-duplicate-assistant-message fix. Both mirrored to vault.
- [x] **Test state-machine fixtures updated** — `StateMachineWalkthroughTest.php` and `OnboardingChatDirectorFixesTest.php` now use `protection` instead of `family` as the visited focus. Walkthrough still terminates at `STATE_DONE` cleanly.
- [x] **Browser verification (commit `039b258`, localhost:8000):**
  - **Test 2 Part A (FR-M10 DOB-only)** — PASS. Typed "12 January 1985" → Fyn replied *"Got it — I have you down as born on 12 January 1985. Are you single, married, in a civil partnership…"*. DB: `dob=1985-01-12, marital=null, step=base_personal`.
  - **Test 2 Part B (FR-M10 marital-only)** — PASS. Typed "I am married" → Fyn replied *"Thanks — I have you noted as married. Could you share your date of birth?"*. DB: `dob=null, marital=married, step=base_personal`.
  - **Test 3 (FR-M11 happy path)** — PASS. Fresh user walked the corrected Protecting-and-growing flow to `asset_capture(protection)` → "I'm done" → navigated to `/protection`. DB: `onboarding_completed=1, step=null`.
  - **Test 4 (FR-M12 expenditure sync)** — PASS. "My rent is £1500 and utilities are £300" → Fyn total £1,800 → both `users.monthly_expenditure=1800` AND `ExpenditureProfile.total_monthly_expenditure=1800.00` updated. URL: `/valuable-info?section=expenditure`.
  - **Test 6 (FR-M14 off-script on savings)** — PASS. "I have a Nationwide cash ISA with £5000" → Fyn ack clean, no property/mortgage/home/income/etc. follow-up. `savings_accounts` row written.
  - **Test 6b (no duplicate message)** — PASS. Exactly one assistant bubble between user message and `add_more` "Anything else…" prompt. DB: single assistant row, no consecutive duplicates.
  - **Test 7 (FR-M15 Trust CLT)** — PASS (both cases). Save: "Add a discretionary trust called Test Trust, initial value £100,000" → 1 trust row + 1 CLT gift via `TrustObserver::created`. Cancel: opened `Add Trust` form, clicked `Cancel` without saving → counts unchanged. No orphan.
- [x] **Commits pushed to `onboardingFyn`** — `fd3ff44` (FR-M14 buffered filter + streaming dup), `039b258` (journey remap), `6211451` (excalidraw auto-update).
- [x] **Vault synced** — `fynlaBrain/Git History/Apr2026/Apr20.md` updated (13 commits across 4 sessions). `fynlaBrain/Git History/Apr2026/Apr2026 Commits.md` total 413→418, Apr20 row rewritten for 4 sessions. `fynlaBrain/Home.md` 2,613→2,618, April row 413→418. `fynlaBrain/April/April Index.md` April 20 section expanded from 2 sessions to 4 with session-4 narrative and new `smoke-test-results-local` wikilink.

### NOT Done — Outstanding

**Blocked this session:**
- [ ] **Test 5 (FR-M13 spouse collision)** — not browser-verified on commit `039b258`. xAI API was returning `Connection refused for URI https://api.x.ai/v1/chat/completions` during attempted verification. Unit tests cover the code path (`SpouseCollisionTest.php`) but end-to-end SSE flow needs real LLM. Retry when API is back up.
- [ ] **Test 1 (FR-M9 preview block)** — not re-run post-remap. It's middleware-level (no LLM needed) and the remap doesn't touch `PreviewWriteInterceptor`. Low risk but not re-verified per `critical_browser_testing_law`.

**Gate 1 — deploy to csjones.co/fynla:**
- [ ] Upload the 11 PHP files listed in the regenerated `deploy-PRD-P0.md` to `~/www/csjones.co/fynla-app/`. Sibling-dir layout.
- [ ] **Run `./deploy/csjones-fynla/build.sh` locally first.** This deploy requires a Vite rebuild because `resources/js/store/modules/aiChat.js` changed in session 4. Upload the resulting `public/build/` directory.
- [ ] SSH in, `cd ~/www/csjones.co/fynla-app`, run `php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize && php artisan db:seed --force`. SSH key `~/.ssh/fynlaDev`; check `ssh-add -l` first.
- [ ] Re-run smoke tests **1, 2A, 2B, 3, 4, 5, 6, 6b, 7** on `https://csjones.co/fynla`. Each must have Playwright CLICK/FILL/SUBMIT evidence per `critical_browser_testing_law`. DB verification via prod SSH tinker, not MCP mysql (which points at local DB).

**Gate 2 — merge-back PR `onboardingFyn → dev`:**
- [ ] **Cross-reference conflict check.** `onboardingFyn` is now 77+ commits behind `origin/dev`. Per `feedback_merge_branch_conflicts`:
  1. `git merge-base dev onboardingFyn` → branch point.
  2. `git diff <base>..dev --name-only` vs `git diff <base>..onboardingFyn --name-only` — cross-reference. `CoordinatingAgent.php` and `AiToolDefinitions.php` are known to be on both lists (session 2 notes). Add `OnboardingChatDirector.php`, `OnboardingPromptBuilder.php`, `OnboardingStateMachine.php`, `aiChat.js` to the manual review list.
- [ ] Open PR `onboardingFyn → dev`, protected branch, only `@Stoff73` can merge.

**Gate 3 — `dev → main` after ≥48h dev stability per CLAUDE.md branch workflow.**

**Pre-existing issues carried (NOT in scope for this release):**
- [ ] **DB persistence gap** — the off-script filter operates on the live SSE stream only. `HasAiChat::chat()` writes the raw LLM text (including any leaked off-script sentences) to `ai_messages.content` UNFILTERED. Conversation rehydrate / history re-open would surface the unfiltered text. Real bug. Fix would apply the same filter to `$fullResponse` before `AiMessage::create` inside `HasAiChat::chat()`. Flagged honestly in previous conversation with CSJ. Not in FR-M14 scope.
- [ ] **Family-member `fill_form` load race** from session 3 — "My mum is 72" on asset_capture(family) produced a Fyn success ack but no `family_members` row (the journey remap removed that entry-point anyway, but the underlying race exists for module-page fill_form usage).

**Should-have (P1, still open, for follow-up PR after dev deploy):**
- [ ] **FR-S1 (F4)** — `handleUpdateRecord` per-entity field allowlist. Replace `getFillable()` boundary with `private const ALLOWED_UPDATE_FIELDS` keyed by the 12 entity types. Omit `settlor` (trust), `start_date`/`term_years` (mortgage), `relationship` (family_member). Security-adjacent. _Touches: `CoordinatingAgent.php:2802-2858`._
- [ ] **FR-S2 (F6)** — partial-capture template for `handleCapturePersonalDetails` and `handleCaptureSpouseDetails`. `composePartialRetryText` already has friendly-map entries. Note: `handleCapturePersonalDetails` accepts partials as of `7e778e2`, so FR-S2 may be partially addressed — verify scope.
- [ ] **FR-S3** — extract `educationStatusForAge` to `OnboardingValueInterpreter` (public static). Remove duplicates from `CoordinatingAgent.php:1075` and `OnboardingChatDirector.php:582`.
- [x] **FR-S4** — shipped as part of FR-M14 (queue-and-flush in session 2, superseded by buffered filter in session 4). Reference entry only.

**Nice-to-have (P2, if time permits):**
- [ ] **FR-N1..FR-N7** — see `April/April20Updates/PRD-fyn-driven-onboarding.md` for details. N1 surfaces employer/occupation in system prompt; N2 adds ExpenditureProfile fallback in `calculateTotalExpenditure`; N3 adds duplicate-name checks on 7 create handlers; N4/N5 add spouse sync for profile + expenditure; N6 adds 7 missing routes to `navigate_to_page` allow-list; N7 adds partial-payload tolerance to estate create handlers.

### Context for Next Session

**Branch:** `onboardingFyn` at HEAD `6211451` (excalidraw auto-update — no code changes in that commit). Clean working tree. Pushed to origin. Still 77+ commits behind `origin/dev`.

**Start here:**
1. Read `April/April20Updates/deploy-PRD-P0.md` end-to-end. File list has been regenerated this session to include `aiChat.js` + the three journey-remap files + `AssetCaptureOffScriptFilterTest.php`. Vite rebuild is now required (was not in session 2).
2. Run `./deploy/csjones-fynla/build.sh` locally. Verify `public/build/` hashes before uploading.
3. Upload the 11 PHP files + `public/build/` to csjones.co/fynla via SiteGround or rsync.
4. SSH + cache clear + `db:seed --force` per deploy guide.
5. **Run smoke tests 1, 2A, 2B, 3, 4, 5, 6, 6b, 7 on `https://csjones.co/fynla`.** Each test needs Playwright CLICK/FILL/SUBMIT evidence; verify DB via SSH tinker. Test 5 needs xAI API to be up — check before starting.
6. If 9/9 green on dev: open `onboardingFyn → dev` merge-back PR with conflict cross-reference for `CoordinatingAgent.php`, `AiToolDefinitions.php`, `OnboardingChatDirector.php`, `OnboardingPromptBuilder.php`, `OnboardingStateMachine.php`, `aiChat.js` (all touched on both branches since branch point).
7. After ≥48h dev stability: `dev → main` PR for production rollout to fynla.org.
8. Then P1 batch (FR-S1 first — narrow, security-adjacent).

**Inherited from the feedback files (still apply):**
- "Browser tested" means CLICK/FILL/SUBMIT in Playwright — not a code diff, not a snapshot. `critical_browser_testing_law.md`.
- Never close the browser without explicit instruction. `feedback_never_close_browser.md`.
- Don't modify .env or DB to work around auth/subscription issues. `feedback_never_touch_env_or_db.md`.
- Deploy guides must list every file from `git diff`, not memory. `feedback_deploy_guide_completeness.md`.
- When blocked, ASK — don't skip. `critical_browser_testing_law.md`.

### Inherited from session-3 (still open, not addressed this session)

- **Subscription seeder safety** — `./vendor/bin/pest` wipes `subscription_plans`. Always `php artisan db:seed` after running tests before any browser interaction.
- **Dev server port drift** — `./dev.sh` may run Vite on `:5174` instead of `:5173`. `public/hot` gets it right; no action.

### Carried from earlier sessions

- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deeper scenarios beyond P0 smoke tests. Largely covered once Gate 1 runs.
- [ ] **Re-enable branch protection on `dev`** — from session 57.
- [ ] **Add `Current State/Insights.md`** to the vault — flagged session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing since 16 April.
- [ ] **NPM `--force` audit** (vite 8 + @capacitor/cli 8 major bumps) — deferred pending iOS regression window.

---

## Outstanding — Tech Debt Deferred

- [ ] `handleSetExpenditure` spouse sync (F11 — FR-N5)
- [ ] `handleUpdateProfile` spouse sync (F10 — FR-N4)
- [ ] 7 entity types missing duplicate-name checks (F9 — FR-N3)
- [ ] DB persistence gap in `HasAiChat::chat()` — off-script filter operates on SSE stream only, not on the `AiMessage::create` write. Conversation rehydrate would surface unfiltered LLM text. Not in current release scope.
- [ ] Family-member `fill_form` load race — observed session 3; not in onboarding scope after journey remap but exists for module-page usage.
- [ ] NPM `--force` audit — deferred.
- [ ] `AutoRiskCalculatorTest` enum truncation — pre-existing.

## Known Issues

- **`handleUpdateRecord` allows LLM to update any fillable field** — FR-S1 (P1). Includes `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`. Security-adjacent.
- **Off-script filter streams clean but stores raw text** — addressed at the UX layer for FR-M14, but a user re-opening a past conversation will see the raw LLM text. Tracked above as DB persistence gap.

## Deploy Status

**Production (fynla.org):** Running commit `a14f17a` (PR #219 Admin Insights CMS) + `062c7c7` (tooling audit). Full Admin Insights CMS live. NO onboardingFyn changes deployed to production yet.

**Dev (csjones.co/fynla):** Running `88018a5` post the 16 April session-1 deploy. Sessions 2 (`0a933fd`), 3 (`7e778e2`, `22220b1`), and 4 (`fd3ff44`, `039b258`, `6211451`) are **NOT yet deployed** to csjones — committed and pushed, awaiting Gate 1 (build + upload + SSH cache clear + db:seed + smoke tests).

**Pending deploy path:**

```
onboardingFyn @ 6211451
    → csjones.co/fynla  (Gate 1: local Vite build + upload 11 PHP + public/build/ + SSH cache clear + smoke tests 1/2A/2B/3/4/5/6/6b/7)
    → merge-back PR `onboardingFyn → dev`  (Gate 2: conflict cross-reference on 6 files)
    → ≥48h dev stability
    → `dev → main` PR  (Gate 3)
    → production deploy to fynla.org
```
