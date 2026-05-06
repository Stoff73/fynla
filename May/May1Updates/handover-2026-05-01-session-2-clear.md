---
type: handover
mode: context-clear
date: 2026-05-01
session: 2
branch: fix/persona-split-review-fixes
previous_session: 2026-05-01-session-1
pr: "#239 → feature/fyn-persona-split"
---

# Context Clear Handover — 2026-05-01, Session 2

## Immediate state

PR #239 is open against `feature/fyn-persona-split`. 14 commits resolving 9 P0s + 18 P1s from `branch-review-fyn-persona-split.md`. All touched-area Pest tests GREEN (143/143). Working tree clean.

## The thread

- Session 1 produced [[branch-review-fyn-persona-split]] — a 6-agent eval-reviewer audit of `feature/fyn-persona-split` that returned FAIL across 5 of 6 slices (8 P0s, 32 majors, 5 RED CI tests).
- User asked for "fix all issues across this review" with a dedicated branch + PR back to `feature/fyn-persona-split` for record.
- I worked the recommended fix order in §"Recommended fix order" of the review: tests GREEN first → P0 contract fixes → real-money fixes → user-copy → UI cleanups → P1 batches.
- Each P0/P1 group landed as a focused commit with a body explaining what changed and why, plus a regression test where the fix had a stable contract to assert.
- Final commit pushed and PR #239 opened with a full table of P0/P1/M items mapped to commits, plus an outstanding test plan (browser smoke + full-suite Pest) the user owns.

## What shipped today

14 commits on `fix/persona-split-review-fixes`:

- `353e863` fix(tests): align TaxStrategyCalculatorTest with 23f68ec strategy contract — 5 RED unblocked
- `b1965b9` fix(advice-fyn): close write-tool leak (P0.2) — 6 capture_* tools added, guard test auto-enumerates
- `80c4189` fix(eval): drop is_eval_user, rename eval_user_id, repoint purge (P0.1)
- `1874e98` fix(eval): make resetPersonaIfMutating shape-aware (P0.4)
- `48d0cc0` fix(advice-fyn): wrapStream drops every non-delegate handoff event (P0.9)
- `00a52ab` fix(spouse): lowercase email before lookup (P0.8)
- `65f22f4` fix(vue+tax): drop $listeners, scope ISA subscription to current year (P0.7 + P0.6)
- `9a89f44` fix(eval): back-fill tool result strings from ai_messages metadata (P0.3)
- `7ff64b3` refactor(support): rename to XaiFunctionCallLeakStripper (P0.10)
- `aca5466` fix(ux): expand acronyms, currencyMixin, replace unicode glyphs (M1-M6)
- `33ff535` fix(tax+security): hardcoded rates, civil_partnership, HMAC fail-loud, glob escape (M7-M10, M14, M19, M22)
- `6f1180e` fix(readiness): align completeness_percent + Investment loadMissing (M12, M13)
- `104e24d` fix(plumbing): retention chunking, audit logging, FormRequest, defence-in-depth (M15-M22)
- `5320f64` fix(fyn-chat): strip every SVG icon from Fyn chat surfaces (M24) + close M23

## Files touched (recently committed)

39 files changed across the branch. Highlights:

- `app/Services/Tax/Strategies/*` — IncomeBand, AssetShifting, JointSavings, Lifecycle, NonEarnerSpousePension, PensionAACarryForward, TaperedAnnualAllowance
- `app/Services/Tax/TaxStrategyMath.php`, `app/Services/AI/AdviceFyn.php`, `app/Services/AI/AuditChainService.php`
- `app/Services/Onboarding/SpouseLinkingService.php`, `app/Services/Onboarding/OnboardingStateMachine.php`
- `app/Console/Commands/Eval{Record,Show,Purge}Command.php`, `app/Listeners/Eval/EvalTraceListener.php`, `app/Services/Eval/EvalBypassGate.php`
- `app/Services/{Estate,Investment/Recommendation,Protection,Retirement,Savings}/*DataReadinessService.php`
- `app/Models/EvalRecordingSession.php`, `app/Models/PensionInputHistory.php`
- `app/Http/Controllers/Api/AiChatController.php`, `app/Http/Requests/AI/SendAiChatMessageRequest.php` (new)
- `app/Constants/{TaxDefaults,QuerySchemas}.php`, `app/Jobs/AiAuditRetentionJob.php`
- `resources/js/components/{Shared/AiChatPanel,Shared/AiMessageContent,Public/StaticFynChat,Fyn/FynOnboardingChat,Admin/EvalRecordings,Admin/eval/*}.vue`
- `resources/js/views/Public/SaveTaxCampaignPage.vue`, `resources/js/store/modules/aiChat.js`
- 3 new migrations: `2026_05_06_*` (drop `is_eval_user`, rename `eval_user_id`, add `(operation, created_at)` index)
- `app/Support/AssistantContentSanitiser.php` → `XaiFunctionCallLeakStripper.php` (rename)
- New tests: `AdviceFynWrapStreamHandoffDropTest`, `EvalRecordCommandResultEnrichmentTest`, `TaxStrategyMathTest` + extensions to existing eval / spouse / advice tests

Untracked dirs `fyn/`, `personas/`, `prompts/`, `tools/` are eval test artefacts (created by `Artisan::call('preview:reset', ...)` during test runs); intentionally NOT committed.

## What's in flight (NOT done)

- **M11** — income-basis inconsistency in `AssetShiftingBundleStrategy`, `CrossSpouseBundleStrategy`, `JointSavingsStrategy` (raw `annual_employment_income` vs composed `taxableIncomeFor`). Deliberately deferred; needs an HMRC-rule analysis for which basis is correct per strategy. Not blocking the review's main verdict but should land before this branch goes to `dev`.
- **Browser smoke testing** of the £75k user persona on `/tax-strategy` — `feedback_smoke_must_verify_amounts.md` (issued today) explicitly requires this. The fix branch is structurally correct but no Playwright run has verified the £ amounts surface correctly per the user's actual profile.
- **Full `./vendor/bin/pest` sweep** — only touched-area tests (143) verified. Three migrations + multiple service rewrites warrant a full-suite run before the PR merges.
- **Re-record any eval recordings** whose `result_path` previously graded falsely-`success` (the P0.3 back-fill changes the recorded shape; old fixtures may now mismatch).
- **Pre-existing RED test** noted: `EvalAuthControllerTest > "reset endpoint runs preview:reset for the persona"` was already failing on parent commit `614867bc` — separate issue not regressed by this branch.

## Deploy status

**Nothing to deploy.** Work was on a fix branch off `feature/fyn-persona-split`, not `dev` or `main`. PR #239 is open for review/merge into `feature/fyn-persona-split`. Per `feedback_no_deploy_recommendations.md`, CSJ owns the merge timing.

## Tech debt found this session

The session was itself a debt-reduction sweep against a comprehensive review — every change has a referenced review item. No new debt introduced; M11 deferred (see above) is the one item left from the original review.

## Known issues / blockers

- M11 (income-basis) — see above. Affects users with mixed income types.
- Pre-existing flaky `TaxStrategyCalculatorTest > benchmark` assertion (50ms threshold; passes ~25ms in isolation, intermittent under full-suite warm-up). Not introduced by this branch.
- Pre-existing `EvalAuthControllerTest > "reset endpoint"` RED on parent — separate.

## Rules reinforced this session

No new memory files written this session. All operations followed existing rules:
- LOOP UNTIL CORRECT (CLAUDE.md Rule #15) — diagnose → fix → re-verify until tests GREEN per the plan
- No deploy recommendations on a fix branch
- TaxConfigService for every tax value (M7-M9)
- Rule #14 icon ban on Fyn chat surfaces (M24)
- Rule #6 currencyMixin (M2)
- Rule #10 acronym expansion (M1, M5, M6)
- Eval canonical 0.2 — no mirror user, Sanctum bypass token IS the mechanism (P0.1)

## Next session should

1. Pull `fix/persona-split-review-fixes` and run the **full** `./vendor/bin/pest` suite. Note any pre-existing reds vs new ones.
2. If the user wants browser smoke now: drive Playwright through the £75k persona on `/tax-strategy`, verify the £ amounts on each card against the persona's actual profile (per `feedback_smoke_must_verify_amounts.md`).
3. M11 follow-up: read `IncomeBandStrategy` (uses composed `taxableIncomeFor`) and compare against `AssetShifting`, `CrossSpouse`, `JointSavings` (raw `annual_employment_income`). Decide canonical basis per HMRC rule per strategy. Marriage Allowance, in particular, keys off TAXABLE income at HMRC — so `AssetShifting:42` may need updating.
4. If review of PR #239 is clean: CSJ merges the PR into `feature/fyn-persona-split`. The branch can then progress toward `dev` (after M11 + browser smoke).

## Context hints

- Active branch type: **mixed** (test fixes + service/controller refactors + Vue UI sweep + 3 migrations)
- Behind `origin/dev`: `feature/fyn-persona-split` was 259 commits ahead per session 1 review; this branch adds 14 more
- Uncommitted: none (working tree clean; untracked test artefacts left as-is)
- Last commit: `5320f64` fix(fyn-chat): strip every SVG icon from Fyn chat surfaces (M24) + close out M23
- PR: [#239](https://github.com/Stoff73/fynla/pull/239) → `feature/fyn-persona-split`
