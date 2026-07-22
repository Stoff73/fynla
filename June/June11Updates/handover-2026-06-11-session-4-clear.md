---
type: handover
mode: context-clear
date: 2026-06-11
session: 4
branch: dev
---

# Context Clear Handover — 2026-06-11, Session 4

## Immediate state
Savetax campaign E2E test + all six fixes it produced are **merged to dev (PRs #529, #530, #531), deployed to csjones, and verified live in the browser on both web and `/m`**. Working tree clean (dev @ `d0f7cf6`, up to date with origin; only the two long-standing untracked docs remain). Nothing in flight. **Next phase: Track 2 (coala) — review the spec with CSJ, then write the plan.**

## The thread
1. **Full campaign E2E test on csjones `/m`** (CSJ-directed: dev server, click-through only, phone viewport): funnel → `/savetax/plan` → compact register → verify → `/m` dashboard → Fyn campaign (all 7 sections) → `/tax-strategy`. **Verbatim recording confirmed**: every message persists to `ai_messages`; LLM capture turns carry full `system_prompt` (~17KB) + `assembled_context` + `tool_calls`/`tool_results`; deterministic turns correctly carry none. Test pattern saved to memory (`reference_savetax_campaign_e2e_test_pattern.md`).
2. **PR #529** — the "major flaw" CSJ flagged live: Fyn re-asked "Does your spouse work?" despite the funnel's `spouseIncome='zero'`. Root cause: `FunnelAnswersMapper` never mapped `spouseIncome` → `household_calculation_mode`, and `STATE_CAMPAIGN_SPOUSE_WORK` had no funnel-aware `skip_if`. Both fixed; `applySkipRules` routes transitively to the right follow-up.
3. **PR #530** — Tax Strategy page layout (CSJ direction): strategies ("Move assets to use spouse allowances" / "Recommended actions") now render ABOVE the headroom + allowance blocks, both surfaces. Desktop needed a component split: `HouseholdCoordinationPanel.vue` extracted from `HouseholdView` (which now holds only the two allowance grids).
4. **PR #531** — the four E2E iteration findings, all fixed + live-verified: (1) `create_investment_account` had NO dividend field — added `annual_dividend_income` to BOTH provider schemas + handler feeds `users.annual_dividend_income` (non-ISA only); (2) Marriage Allowance now `available:false` for higher/additional-rate recipients (recipient-band gate via `income_tax.higher_rate_threshold`, mirrored on spouse grids; frontends render "Not available", excluded from headroom); (3) `/savetax/plan` social-proof sentence de-garbled + count-map keys fixed (were dead keys → always 5,000); (4) `buildCaptureAck` gained a `campaign_charitable_giving` entry ("Recorded — around £600 a year through Gift Aid.").

## Files touched (committed this session)
`app/Services/Auth/FunnelAnswersMapper.php`, `app/Services/Onboarding/OnboardingStateMachine.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `app/Agents/CoordinatingAgent.php`, `app/Services/Tax/TaxStrategyCalculator.php`, `app/Services/AI/{Xai,Ai}ToolDefinitions.php`, `public/pages/js/savetax-plan-v4.js` (`?v=10`), `public/pages/savetax-plan{,-v4}.php`, `resources/mobile/views/TaxStrategy.vue`, `resources/js/components/TaxStrategy/{AllowanceCard,AllowanceGrid,HouseholdView,HouseholdCoordinationPanel}.vue`, `resources/js/views/TaxStrategy/TaxStrategyDashboard.vue`, + tests (`CaptureAckTest` new; `CampaignStateMachineBranchTest`, `CampaignSectionFlowTest`, `FunnelAnswersCaptureTest`, `TaxStrategyCalculatorTest`, `CreateInvestmentAccountTest` extended — 14 new cases total).

## What the next Claude needs to know
- **Test users on csjones** (all `@example.com`, password `Password1!`): `emma.savetax0611` (married, trap band, pre-fix run — her transcript SHOWS the spouse-work bug), `oliver.savetax0611b` (married, £110k — proves spouse-skip + MA "Not available"), `daisy.savetax0611c` (single, £105k, £700 dividends — proves dividend capture + Gift Aid ack). Useful for regression checks; their `ai_messages` transcripts are the iteration corpus.
- **`cache:clear` on csjones invalidates live user tokens** — every deploy bounce requires re-login (MFA code via csjones SSH tinker on `EmailVerificationCode`).
- **Playwright + `/m` gotchas**: level-up celebration dialogs intercept clicks mid-chat (dismiss "Keep going" first); stale session cookies bounce public pages to /login — clear via `page.context().clearCookies()`, not just localStorage.
- **Tool-schema edits on dev are plain PHP** (`XaiToolDefinitions` = live provider, keep `AiToolDefinitions` consistent) — the corpus + golden masters live on the `coala` branch only.
- **Desktop has NO Tax Strategy nav entry** (the `/m` menu does) — flagged to CSJ, intentionally not added (Rule #16). CSJ may ask for it in the Planning sidebar group.
- The deterministic campaign copy lives in `OnboardingStateMachine::states()` + the tax engine strategies; only `grouped_extract`/`delegated` turns hit the LLM. Iterating campaign wording = editing state table text, zero prompt cost.

## Pick up from here
1. **Track 2 (coala) — THE next phase (CSJ, this handover's instruction):** spec at `docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md` (v4, built on CSJ's canonical agent flow — `feedback_coala_agent_flow_canonical.md`). First **review the spec with CSJ** (it is still awaiting his approval — do NOT start build work before that), then run `superpowers:writing-plans` to produce the implementation plan.
2. Carried items live in CSJTODO.md (Azlan journey re-test, gamified dashboard eyeball, insights featured judgement call, legacy deploy docs, MEMORY.md size, stale UKTaxes.md vault doc).
