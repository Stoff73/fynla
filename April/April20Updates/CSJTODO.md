# CSJTODO — Fynla

*Last updated: 20 April 2026 — session 3 (end-of-session, context-clear)*
*Previous sessions: 20 April 2026 — session 1 (morning), session 2 (afternoon)*

---

## Session 3 (20 April, evening) — Local smoke tests + FR-M10 fix shipped

### Completed This Session

- [x] **All 7 PRD P0 smoke tests run on localhost:8000.** 6/7 PASS, 1 FAIL (FR-M14, documented). Full test transcript with Fyn's exact replies, DB verifications, and per-test user artefact map at `April/April20Updates/smoke-test-results-local.md` (mirrored to `fynlaBrain/April/April20Updates/smoke-test-results-local.md`).
- [x] **Test 1 — FR-M9 preview block:** PASS. `POST /api/ai-chat/onboarding/start` as preview persona returned `403 {"success":false,"reason":"preview_mode"}`. DB verified: preview user's `onboarding_fyn_step` still null.
- [x] **Test 2 — FR-M10 hybrid skip:** PASS **after local fix** (see below). Both DOB-only and marital-only partial captures now trigger the hybrid pre-confirm message. DB verified: state stays on `base_personal`; only the provided field is written.
- [x] **Test 3 — FR-M11 happy path walkthrough:** PASS. Fresh user walked `path_choice → journey_selection → base_personal → base_spouse → base_dependants (No) → base_employment (Employed) → base_work → base_expenditure → asset_capture (family, then Savings) → add_more → "I'm done"`. Final state: `onboarding_completed=1`, URL `/net-worth/cash`. 2 savings accounts + spouse persisted.
- [x] **Test 4 — FR-M12 expenditure sync:** PASS. "My rent is £1500 and utilities are £300" produced Fyn reply with total £1,800 and synced both `ExpenditureProfile.total_monthly_expenditure=1800.00` and `users.monthly_expenditure=1800`.
- [x] **Test 5 — FR-M13 spouse email collision:** PASS. Collision message emitted verbatim per PRD: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"*. State preserved at `base_spouse`; retry with a fresh email succeeded and advanced to `base_dependants`.
- [x] **Test 7 — FR-M15 Trust CLT orphan:** PASS (both cases). Save case: Fyn created `Test Trust` £100k discretionary → `TrustObserver::created` wrote 1 CLT gift @ `gift_value=100000`. Cancel case: `/trusts` → `Add Trust` form → `Cancel` without saving → trust count unchanged (1), CLT count unchanged (1) — no orphan.
- [x] **FR-M10 fix committed + pushed** — commit `7e778e2` on `onboardingFyn`. Files: `app/Services/AI/AiToolDefinitions.php`, `app/Agents/CoordinatingAgent.php`, `app/Services/Onboarding/OnboardingStateMachine.php`, `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php`, plus test results doc. Branch is now 2 commits ahead of `origin/dev`-cut point (the earlier `22a8dbe` + this fix).
- [x] **Regression suite** — 160 onboarding tests pass, 530 assertions, 0 failures after the fix.

### FR-M10 fix root cause and changes (commit `7e778e2`)

- **Why the original shipped code failed manual testing.** `AiToolDefinitions::onboardingExtractionTools` marked both `date_of_birth` and `marital_status` as `required` on the `capture_personal_details` schema. The LLM therefore could not return a single-field call — when the user only provided DOB, it filled `marital_status='single'` as a default, handed the handler a full payload, and the state machine advanced straight past `base_spouse`. The hybrid branch in `buildPersonalPrompt` (which only fires when exactly one field is set on the user) was never reachable via the intended path.
- **Schema tightened.** `capture_personal_details` now has `required: []` and an explicit "only include a field when the user explicitly stated it — do not guess, infer, or default" instruction in the description. Per-field descriptions repeat the rule.
- **Handler split.** `handleCapturePersonalDetails` accepts a single-field payload. Rejects only when BOTH fields are empty. Validation (DOB format + age 18–105, marital enum) runs only for the field present. Saves only the provided field(s).
- **State machine guard.** `nextFromPersonal` now returns `STATE_BASE_PERSONAL` when either field is still empty, so the director re-renders the same state and `buildPersonalPrompt` emits the hybrid pre-confirm variant.
- **Tests.** Five existing `nextFromPersonal` tests updated to pass DOB explicitly (the factory defaults DOB to null). Two new tests assert the partial-capture guard.

### Test 6 — FR-M14 asset-capture off-script suppression: **FAIL**

Full reproduction and failure analysis in `April/April20Updates/smoke-test-results-local.md` §"Test 6". Summary:

- Input at `asset_capture` selection=family: `"My mum is 72"`.
- Fyn produced (verbatim):

    Got it — recording those now.
    Thanks, TestF — I've added your mum (aged 72) to your family profile for protection and estate planning.

    With your £50,000 gross income and £3,500 monthly outgoings, it looks like you have capacity to build protection cover or start growing savings.

    Do you own your home or have a mortgage If so, what's the property address, ownership share (e.g. 50% with spouse), and rough value

- PRD expectation: no property/mortgage/rent/income questions, no `?` in acknowledgment.
- Failure mode: the LLM bypassed the `?`-strip filter by phrasing questions without a trailing `?`. The post-tool queue-and-flush preserved the off-script content because `create_family_member` did fire (tool call positive), so the filter's "swallow only on zero-tool-call" branch didn't apply.

Suggested follow-up (documented, **NOT landed**):

1. Tighten `OnboardingPromptBuilder::assetCaptureInstructions` with a hard constraint: "Acknowledgment MUST be a single sentence of ≤15 words. Do not ask any question, with or without a question mark. Do not mention property, mortgage, rent, income, home, address, value, or any topic outside the current selection."
2. Add a sentence-level keyword filter to `OnboardingChatDirector::handleAssetCaptureTurn`: when `selection !== 'protection'` AND `selection !== 'estate'`, strip any sentence (split on `.|!|\n`) containing `/property|mortgage|rent|income|home|address|ownership|valuation/`. Apply on BOTH post-tool and zero-tool-call content.
3. Add a unit test feeding a mocked LLM stream `"Got it — recording those now. Do you own your home or have a mortgage"` and asserting only the first sentence survives.

Impact: low data-integrity risk (no bad writes), but real UX leak — users see off-script follow-ups mid-family-capture. FR-M14 should ship as a narrow follow-up PR before or alongside the dev deploy.

### Side observations (not in scope for the P0 PR)

- **Family-member form-load race.** Observed in Tests 3 and 6: the front-end occasionally reports *"The form for your family member didn't load in time"* when Fyn fires `fill_form` for `create_family_member` right after a navigation-triggering prior tool call. Tool transition still advances correctly; the record just isn't persisted. Worth a separate investigation pass.
- **Subscription seeder safety.** Running Pest with `RefreshDatabase` wipes `subscription_plans`, which breaks the `?from=fyn` registration flow (OTP completion calls `TrialService::startTrial` → `SubscriptionPlan::findBySlug('standard')` throws). Fix is to always `php artisan db:seed` after running tests. Captured in the test-results doc as an operational note.
- **Dev.sh port drift.** `./dev.sh` started Vite on `:5174` (not `:5173`) in this session per the existing vite config. `public/hot` correctly points there. No action needed, but worth flagging.

### Source of truth for next session

- **`April/April20Updates/smoke-test-results-local.md`** — complete local test transcript, failure analyses, suggested fixes.
- **`April/April20Updates/deploy-PRD-P0.md`** — dev deploy guide. **Needs updating** before deploy: add the 3 extra code files from `7e778e2` (`app/Agents/CoordinatingAgent.php` and `app/Services/AI/AiToolDefinitions.php` and `app/Services/Onboarding/OnboardingStateMachine.php` are already listed; the test file is optional). Also add `April/April20Updates/smoke-test-results-local.md` to the artefact list.
- **`April/April20Updates/PRD-fyn-driven-onboarding.md`** — canonical contract. P0 all done; P1 (FR-S1 to FR-S4) and P2 (FR-N1 to FR-N7) open.

### NOT Done — Outstanding (ordered for the next session)

**Local test results must precede any deploy:**
- [x] Local smoke tests 1–7 run. 6 PASS, 1 FAIL documented.
- [x] FR-M10 fix committed + pushed (`7e778e2`).

**Gate 1: deploy to csjones.co/fynla and re-run smoke tests on dev:**
- [ ] **Update `April/April20Updates/deploy-PRD-P0.md`** to reflect `7e778e2` — run `git diff main..onboardingFyn --name-only -- '*.php'` to regenerate the file list. Mirror the update to vault.
- [ ] **Upload the 10+ PHP files to `~/www/csjones.co/fynla-app/`** via SiteGround File Manager or rsync. Sibling-dir layout per `reference_csjones_sibling_dir`.
- [ ] **SSH in and run:** `cd ~/www/csjones.co/fynla-app && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize && php artisan db:seed --force`. Per `reference_csjones_ssh_access`, key is `~/.ssh/fynlaDev` (passphrase-protected) via plain `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`. Check `ssh-add -l` first.
- [ ] **Re-run smoke tests 1–7 on `https://csjones.co/fynla`**. All seven must pass (including Test 6 if FR-M14 follow-up has landed) before the merge-back. Use the same sequence as the local run. Each test must have Playwright CLICK/FILL/SUBMIT evidence per `critical_browser_testing_law.md`. Verify DB via the prod SSH/tinker rather than MCP (MCP `mysql_query` hits local DB).

**Gate 2: merge-back PR `onboardingFyn → dev`:**
- [ ] **Cross-reference conflict check.** `onboardingFyn` is now 77+ commits behind `origin/dev`. Per `feedback_merge_branch_conflicts`:
  1. `git merge-base dev onboardingFyn` → find branch point.
  2. `git diff <base>..dev --name-only` vs `git diff <base>..onboardingFyn --name-only` — cross-reference. `CoordinatingAgent.php` and `AiToolDefinitions.php` are on BOTH lists per session-2 notes; add a manual post-merge diff check for both files.
- [ ] **Open PR `onboardingFyn → dev`.** Only `@Stoff73` can merge (protected branch).

**Gate 3: FR-M14 follow-up PR (can run in parallel with Gate 2):**
- [ ] **New branch off `onboardingFyn` (or off `dev` post-merge):** `fr-m14-offscript-tighten`.
- [ ] **Prompt tighten** in `OnboardingPromptBuilder::assetCaptureInstructions`.
- [ ] **Sentence-level keyword filter** in `OnboardingChatDirector::handleAssetCaptureTurn`.
- [ ] **Unit test** for the filter.
- [ ] **Local re-run of Test 6 only** to confirm the fix before PR.

**Gate 4: `dev → main` PR for production:**
- [ ] Only after ≥48h of dev stability per CLAUDE.md branch workflow.
- [ ] Production deploy to fynla.org follows the standard layout (not sibling-dir).

**Should-have (P1, still open, in scope for follow-up):**
- [ ] **FR-S1 (F4)** — `handleUpdateRecord` per-entity field allowlist. Replace `getFillable()` boundary with `private const ALLOWED_UPDATE_FIELDS` keyed by the 12 entity types in `resolveModel()`. Omit `settlor` from trust, `start_date`/`term_years` from mortgage, `relationship` from family_member. Security-adjacent. _Touches: `CoordinatingAgent.php:2802-2858`._
- [ ] **FR-S2 (F6)** — apply `handleCaptureWorkDetails` partial-capture template to `handleCapturePersonalDetails` and `handleCaptureSpouseDetails`. Note: `handleCapturePersonalDetails` now accepts partials as of `7e778e2`, so FR-S2 may already be partially addressed. Verify scope. `composePartialRetryText` already has friendly-map entries for both tools. _Touches: `CoordinatingAgent.php:787`, `CoordinatingAgent.php:883`._
- [ ] **FR-S3** — extract duplicate `educationStatusForAge` to `OnboardingValueInterpreter::educationStatusForAge` (public static). Remove duplicates from `CoordinatingAgent.php:1075` and `OnboardingChatDirector.php:582`.
- [x] **FR-S4** — already shipped as part of FR-M14 (queue-and-flush). Keep in PRD as reference; note the off-script filter gap documented in Test 6.

**Nice-to-have (P2, if time permits):**
- [ ] **FR-N1 (F7)** — surface `users.employer` + `users.occupation` in `SystemPromptBuilder::buildUserProfile`.
- [ ] **FR-N2 (F8)** — `SystemPromptBuilder::calculateTotalExpenditure` fallback to `ExpenditureProfile.total_monthly_expenditure`.
- [ ] **FR-N3 (F9)** — duplicate-name checks on 7 create handlers. Template: `handleCreateSavingsAccount:1531`.
- [ ] **FR-N4 (F10)** — `handleUpdateProfile` spouse-linked-user sync.
- [ ] **FR-N5 (F11)** — `handleSetExpenditure` spouse sync for household budget.
- [ ] **FR-N6 (F12)** — add 7 missing routes to `navigate_to_page` allow-list.
- [ ] **FR-N7 (F13)** — `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance.

### Context for Next Session

**Branch:** `onboardingFyn` at HEAD `7e778e2` (this session's commit), clean working tree, pushed to origin. Still 77+ commits behind `origin/dev` (unchanged — merge attempt is gated on Gate 1 passing on csjones.co/fynla).

**Start here (read order):**

1. **`April/April20Updates/smoke-test-results-local.md`** — the definitive account of what passed, what failed, why, and what the test users look like. Read it end-to-end before running Gate 1.
2. **`April/April20Updates/deploy-PRD-P0.md`** — the dev deploy guide. Refresh the file list (`git diff main..onboardingFyn --name-only`) before running.
3. **This CSJTODO** for the ordered gate list.

**Then act on Gate 1:**

1. Regenerate the deploy file list.
2. SSH to csjones.co/fynla, upload the files, clear caches, seed, rerun smoke tests 1–7 via Playwright.
3. Only after that turns green: open the merge-back PR.

**Hard rules inherited from this session:**

- **`./vendor/bin/pest` wipes the local DB.** Always `php artisan db:seed` after running tests before any browser interaction.
- **Preview users bypass the subscription middleware; real test users do not.** When creating fresh test users, register via the landing page "Quick start with Fyn" CTA — the OTP completion triggers `TrialService::startTrial` which creates the `subscription` row needed for `onTrial()` to be true.
- **Don't trust session cookies after signing in as a different user** — sessionStorage has the Bearer token, but the Laravel session cookie may still identify a different user. Sign out via the UI "Sign Out" button, not by clearing storage.
- **Clicking bubble buttons in the Fyn chat can silently fail** (observed in Test 3 at the "I'm done" step). Typing the same text and pressing Enter is a reliable fallback.
- **Icons remain banned on Fyn chat, dashboard cards, and detail views.** Side nav is the only allowed surface per CLAUDE.md §14.

**Important warnings inherited from feedback files (still apply):**

- Don't skip smoke tests. Don't mark [x] without Playwright evidence. (`critical_browser_testing_law.md`)
- If `./dev.sh` is needed, run via `run_in_background: true`. It follows `tail -f` indefinitely.
- SSH to csjones.co uses `~/.ssh/fynlaDev` passphrase-protected key — check `ssh-add -l` before probing. Ask CSJ for the passphrase once if not unlocked; don't run ssh-keygen.
- The csjones.co/fynla dev server may currently be running an older `onboardingFyn` build (`88018a5` or earlier per session-2 notes). Deploying this session's `7e778e2` overwrites it — **intended**.
- Deploy guides belong in BOTH the repo (`/April/April{N}Updates/`) and the vault (`/fynlaBrain/April/April{N}Updates/`). Never just one.

### Carried from earlier sessions

- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deeper scenarios beyond the 4 bug-fix happy path. (Largely addressed by the Gate 1 re-run once it happens.)
- [ ] **Re-enable branch protection on `dev`** — carried from session 57.
- [ ] **Add `Current State/Insights.md`** to the vault — flagged session 62.
- [ ] **`AutoRiskCalculatorTest` enum truncation** — pre-existing since 16 April, still open.
- [ ] **NPM `--force` audit** (vite 8 + @capacitor/cli 8 major bumps) — deferred pending iOS regression window.

---

## Outstanding — Tech Debt Deferred

- [ ] `handleSetExpenditure` spouse sync (F11 — in release scope as Nice-to-have FR-N5)
- [ ] `handleUpdateProfile` spouse sync (F10 — in release scope as Nice-to-have FR-N4)
- [ ] 7 entity types missing duplicate-name checks (F9 — in release scope as Nice-to-have FR-N3)
- [ ] Family-member `fill_form` load race — observed in Tests 3 and 6 of session 3. Non-blocking. Needs a separate investigation.
- [ ] FR-M14 off-script filter — see Test 6 section above. Follow-up PR.
- [ ] NPM `--force` audit — deferred.
- [ ] `AutoRiskCalculatorTest` enum truncation — pre-existing.

## Known Issues

- **FR-M14 off-script suppression leaks** — Fyn emits property/mortgage follow-ups in `asset_capture` selection=family despite the prompt guardrail and the `?`-strip filter. Fix scope + approach documented in test-results doc. Not blocking the other six items.
- **`handleUpdateRecord` allows LLM to update any fillable field** — tracked as FR-S1 (P1). Includes `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`. Security-adjacent.

## Deploy Status

**Production (fynla.org):** Running commit `a14f17a` (PR #219 Admin Insights CMS) + `062c7c7` (tooling audit). Full Admin Insights CMS live. NO onboardingFyn changes deployed to prod yet.

**Dev (csjones.co/fynla):** Running `88018a5` post the session-1 deploy. Session 2's `0a933fd` and session 3's `7e778e2` are **NOT yet deployed** — committed + pushed but awaiting Gate 1 (csjones.co deploy + re-run smoke tests 1–7).

**Pending deploy path:**

```
onboardingFyn @ 7e778e2
    → csjones.co/fynla  (Gate 1: upload + SSH cache clear + db:seed + re-run smoke tests)
    → merge-back PR `onboardingFyn → dev`  (Gate 2: conflict cross-reference required)
    → ≥48h dev stability
    → `dev → main` PR  (Gate 4)
    → production deploy to fynla.org
```
