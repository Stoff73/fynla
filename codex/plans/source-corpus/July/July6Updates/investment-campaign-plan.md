# Investment Campaign — Implementation Plan

*2026-07-06. The execution plan for `investment-campaign-spec.md` — read the spec FIRST; this plan assumes its decisions verbatim. Grounded against dev `9c9e7d2`. Four slices, one PR each (A→B→C sequential, D is the live gate); stacked branches retargeted to dev before base deletion. Rule 14 governs Slice D. Independent of the estate campaign — either may build first; if BOTH build, land one campaign's A–D fully before starting the other (shared files: state machine, director, config, mixin — sequential avoids merge pain).*

**Binding references:** `campaign-playbook.md` §2 (F1–F15), `pensionCampaign.md` (the template map), `pensioncheck-patch-notes-technical.md`, CLAUDE.md Rules 2/8/9/11/12/14/15/19. Acronym rule: write "General Investment Account" (first use per surface), "Stocks & Shares ISA"; ISA alone is allowed.

**Campaign id `investmentcheck`, states `campaign3_*`, entry `base_work`, terminal → `/investment`.**

---

## Slice A — substrate wiring

**Branch `investmentcheck-a` off dev.**

| # | File | Change |
|---|---|---|
| A1 | `config/onboarding.php` `campaign_map` | `'investmentcheck' => ['selection' => 'investmentcheck', 'entry' => 'base_work', 'reentry' => false]` — **reentry flips true + `reentry_entry => 'campaign3_existing_recap'` in Slice C** (`OnboardingStartCampaignMapTest` asserts state ids exist). |
| A2 | `RedirectPhoneToMobile::CAMPAIGN_PREFIXES` | Add `'investmentcheck'`. |
| A3 | `NextActionsService::CAMPAIGN_AFFINITY` | Add `'investmentcheck' => 'investment'`. |
| A4 | `RegisterRequest` | Add `funnel_answers.invested` (`['nullable','array']`) + `funnel_answers.invested.*` (`['string','max:30']`) + `funnel_answers.monthly` (`['nullable','string','max:20']`). |
| A5 | `ONBOARDING_NAV_ROUTES` | **No change** — all five verify routes already allowlisted. |
| A6 | Tests | MobileScaffoldTest investmentcheck block; CampaignAffinityTest case (`investment` items boosted); register test posting the full funnel object. |

## Slice B — public surfaces

**Branch `investmentcheck-b` off `investmentcheck-a`.**

| # | File | Change |
|---|---|---|
| B1 | `app/Services/Marketing/InvestmentEstimateService.php` (NEW) | Clone `PensionEstimateService` exactly: `AGE_MIDPOINTS` reused values; `MONTHLY_MIDPOINTS` (`nothing`→0, `under_100`→50, `100_500`→300, `over_500`→750); horizon = `getPensionAllowances()['state_pension']['future_spa']` (fallback 67) − age midpoint; lift `projectPot()` verbatim (2.5% real, monthly compounding, PV=0); `nothing_invested` flag when monthly==='nothing'; `tax_note` branches per spec §7.2 using `dividend_tax.allowance` (fallback 500), `income_tax.higher_rate_threshold` (50270), `getISAAllowances()['annual_allowance']`; returns spec §7.2 shape. |
| B2 | `public/pages/investmentcheck.php` + `js/investmentcheck.js` (NEW) | Clone the pensioncheck funnel pair; 6 questions per spec §7.1; `invested` multi with exclusive `none` (copy `togglePension`'s exclusivity handling); campaign stamp baked in; localStorage `investmentcheck_answers`; query-param handoff (`invested` comma-joined); utm capture block. |
| B3 | `public/pages/investmentcheck-plan.php` + `js/investmentcheck-plan.js` (NEW) | Clone the pensioncheck plan pair: representative fallback (`full-time, upto_50270, 40s, ['ss_isa'], 100_500, no`); `window.INVESTMENTCHECK_ESTIMATE` HEX-flag injection; `esc()` everywhere; hero "On course for an investment pot of roughly £X by age {spa}" with the `nothing_invested` alternate hero (ISA-allowance frame, spec §7.2); register card + `signup_source`; login link → `/login?redirect=…&from=investmentcheck` shape; verify hand-off; `?v=1` cache-busters. Disclaimer block: "projection based on your answers, not a guaranteed outcome or regulated financial forecast" (the pensioncheck wording). |
| B4 | `routes/web.php` | Clone the block: plan-before-funnel, `redirect.authed`, before the catch-all. |
| B5 | `public/pages/index.php` | `feature-investmentcheck` card — try/catch tolerant, representative figure, DRAFT copy per spec §8, sibling visual pattern only. |
| B6 | Tests | `InvestmentEstimateServiceTest` (projection maths vs seeded config; nothing-invested flag; tax_note branches; unknown bands default); `InvestmentcheckRoutesTest` (ordering, no SPA catch-all, redirect.authed, XSS no-echo). |

## Slice C — the walk

**Branch `investmentcheck-c` off `investmentcheck-b`.**

### C1. New tools: NONE

`create_investment_account` + `create_holding` + `create_savings_account` cover the surface (all `.xai.md` live, all in `WRITE_TOOLS` + `captureToolSet`). **No golden-master re-record needed** — if ANY schema description is touched (don't), re-record both providers.

### C2. Store reader (StoreBoundary rule)

`app/Services/Stores/InvestmentAccountStore.php`: add narrow readers `firstAccountMissingHoldings(User $user): ?InvestmentAccount` and `hasAccountsMissingHoldings(User $user): bool` (primary-owned accounts with `holdings()->count() === 0`; follow `PensionStore::firstDcPensionMissingPotValue`'s shape and docblock style). The state machine and prompt builders use ONLY these.

### C3. State machine

1. Constants: `STATE_CAMPAIGN3_ISA/_INVESTMENTS/_HOLDINGS/_EXISTING_RECAP/_ADVICE_ISA/_ADVICE_INVESTMENTS/_ADVICE_HOLDINGS/_TERMINAL` (8).
2. `CAMPAIGN_SECTION_ORDERS['investmentcheck'] = ['income', 'isa', 'investments', 'holdings', 'expenditure']`.
3. `campaignSections('investmentcheck')`: income → `base_employment` + REUSE `skipSectionIfIncomeKnown`; expenditure → `base_expenditure` + REUSE `skipSectionIfExpenditureKnown`; the three new sections + predicates per spec §5 (`skipSectionIfIsaKnown` — savings `is_isa` OR `InvestmentAccount::isa()` exists; `skipSectionIfInvestmentsKnown` — non-ISA investment rows; `skipIfNoHoldingsToFill` — via the C2 reader).
4. `campaignVerifyConfig('investmentcheck')` per spec §5 (income `/income`, isa `/savings`, investments `/investment`, holdings `/investment`, expenditure `/expenditure`).
5. States in `inCodeStates()`: prompts verbatim from spec §5; `campaign3_isa` prompt is a CALLABLE (interpolates `getISAAllowances()['annual_allowance']` — Rule 2, never hardcode £20,000); `campaign3_investments` carries `record_context: 'investments'` (the Director appendix arm for investment_accounts — new, C4.6); `campaign3_holdings` = the `campaign2_pension_pots` clone: callable prompt `buildHoldingsPrompt` (via C2 reader; name the account), entry `skip_if skipIfNoHoldingsToFill`, looping `next` `nextFromHoldings` (self while accounts remain empty; don't-know tokens exit — copy `nextFromPensionPots`'s token list verbatim), `advance_on_answered_question: true`.
6. `campaign3_existing_recap` — clone campaign2's (same bubble ids; generic `nextFromExistingRecap`/`firstCampaignSection` reused); builder `buildInvestmentRecapPrompt` per spec §5 (income line, account lines via `InvestmentAccountStore::forUserPrimaryOnly`, ISA-allowance-used line via `ISATracker::getISAAllowanceStatus($user->id, taxYear)` — wrap in try/catch, omit the line on throw; risk-level line when a `risk_profiles` row exists).
7. `buildInvestmentcheckFunnelRecapPrompt` + `campaignWelcomeFor('investmentcheck')` = `"welcome to Fynla — let's get your money working properly."`; wire from `buildWorkPrompt`'s campaign branch keyed on selection (the exact pensioncheck seam :1622-1640).
8. `campaign3_terminal` (prompt/navigate per spec) + `nextFromCampaignSynthesis` arm → `STATE_CAMPAIGN3_TERMINAL`.
9. `sectionLabel`: `'isa' => 'ISA accounts'`, `'investments' => 'investment accounts'`, `'holdings' => 'holdings'`.
10. **Corpus lockstep** — add all DATA states to `fyn-onboarding.v1.md` in `inCodeStates()` order; `{ branch: … }` markers for callables; golden master green untouched.

### C4. Director

1. `INVESTMENTCHECK_SECTION_STRATEGY_TYPES` per spec §6.
2. `buildInvestmentSectionAdvice` — clone `buildRetirementSectionAdvice` with **the risk-profile synchronous ensure FIRST** (spec §2: `if (! RiskProfile::where('user_id',$user->id)->exists()) app(RiskPreferenceService::class)->calculateAndSetRiskLevel($user);` — wrap in try/catch, log on failure, proceed; the composed plan's `set_risk_profile` unlock card covers the failure case) → `ComposedModulePlanService::forSource(app(InvestmentStrategySource::class), $user)`; route in `buildSectionAdvice`'s selection switch; non-mapped sections null.
3. `buildSynthesisAdvice` `investmentcheck` arm — **same risk ensure**, composed INVESTMENT plan, lead-in/degrade per spec §6. Investment items are lump-led (`estimatedAnnualTaxSaved` null) — the £-suffix logic stays naturally silent; do NOT special-case.
4. `terminalNavigationBubble` `'/investment'` arm.
5. `describeStep` labels: "capturing your ISAs", "capturing your investment accounts", "capturing what's inside your accounts", "reviewing what we already have", "pulling your investment plan together".
6. `captureRecordContextAppendix` — add the `'investments'` arm: list primary investment accounts (`entity_type: investment_account, entity_id: N — "{name}" at {provider}, current value £X, {holdings count} holdings`) + the steering line for updates-not-duplicates AND for `create_holding`'s `account_name` matching ("when adding holdings, account_name must exactly match one of the names above").
7. `verifyEditFocus`: `'isa' => 'investmentcheck'`, `'investments' => 'investmentcheck'`, `'holdings' => 'investmentcheck'` (existing `'investments' => 'investment'` case — CHECK: `verifyEditFocus` currently maps section `'investments'` (savetax) to `'investment'`; the savetax mapping MUST stay — key the new mappings on the SECTIONS ONLY THIS CAMPAIGN USES (`isa`, `holdings`) and leave `'investments'` as-is: the `'investment'` focus arm (create_investment_account, create_holding) is sufficient for investmentcheck verify-edits too). `verifyEditRecordContext`/`Snapshot`: `'isa'` (savings ISA rows + investment ISA rows with ids), `'holdings'` (accounts + holding counts); `'recap'` case gains the selection branch (accounts + ISA usage for investmentcheck).
8. `toolsForFocus` (`OnboardingPromptBuilder`) — `'investmentcheck' => ['create_investment_account', 'create_holding', 'create_savings_account']` (the #610 arm law).

### C5. Flip re-entry on (`campaign_map` — deferred from A1)

### C6. Tests

- `Investmentcheck*` unit suites: section order; each skip (ISA-row present/absent, non-ISA present/absent, holdings-missing loop states, income/expenditure reuse); verify config; recap builder (account lines, allowance line, risk line present/absent, both leads); funnel recap; `nextFromHoldings` loop + don't-know exit; `buildHoldingsPrompt` names the right account.
- `CampaignReentry{Start,Dispatch,Exit}` investmentcheck cases (409 bypass, stamp, pause/terminal clears, completed_at guard, pause-resume, mid-walk bare-start resume).
- `InvestmentcheckSynthesisTurnTest` — synthesis mirrors composed investment plan; **risk-ensure test**: user with no risk_profiles row + an investment account → synthesis creates the row synchronously and the plan composes (bind the queue to sync/fake in the test).
- Record-context appendix test (accounts listed with ids; create_holding steering line).
- Characterisation pins: savetax + pensioncheck orders/configs byte-identical.

**Slice C done =** full suite green; corpus validators exit 0; zero golden-master fixture diffs.

## Slice D — the live E2E gate (csjones, real xAI, Playwright — Rule 14)

Deploy the stacked branch (build script, upload both bundles, cache clears, bundle-contains-change grep). Walks:

| Walk | GREEN definition |
|---|---|
| **D1 fresh funnel walk** | `/investmentcheck` (phone deep-link) → 6/6 funnel → plan £ (hand-check the projection maths vs seeded config) → register+MFA → Fyn funnel recap → income → ISA (capture a Cash ISA + this-year subscription) → investments (a GIA with value + monthly) → holdings loop (fund split lands as `holdings` rows) → expenditure → synthesis mirrors `/investment` composed plan → terminal → `/investment` renders the accounts + risk card. DB: investment_accounts (canonical types — `stocks_shares_isa`→`isa`, `personal_investment_account`→`gia`), holdings rows, `isa_subscription_current_year`, `users.annual_dividend_income` accumulation if a taxable dividend was stated, **risk_profiles row exists** (the ensure), awards, `isa_first` milestone |
| **D2 existing-user delta** | Standing user via `?from=investmentcheck` → recap (income/expenditure NOT re-asked) → holdings backfill fires for any empty account → synthesis → terminal → advice normal after |
| **D3 integrity** | Two re-entries: completed_at byte-identical, award count 1, active_campaign cleared; recap-edit → confirm → gap walk continues |
| **D4 regression** | One savetax + one pensioncheck fresh walk — zero bleed both directions (esp. `verifyEditFocus('investments')` unchanged for savetax) |
| **D5 targeted** | "no ISAs"/"nothing invested" advances honestly (no phantom rows); joint-ISA attempt refused with the UK-law line; holdings don't-know exits the loop; free-tier account cap (2) behaviour when D1 creates a 3rd account — capture the honest at-cap message (freemium by design); pause → bare reopen resumes at parked step |

Fix rounds on-branch, each re-verified. Exit per Rule 14 only.

## The trap table (check EVERY row before each PR)

| # | Trap | Guard |
|---|---|---|
| 1 | No `toolsForFocus` arm → security refusals | C4.8; live-verify a capture per delegated section |
| 2 | `funnelHasAnyAsset` reads `assets` — this campaign produces `invested` | NO gate may call it (grep before PR) |
| 3 | Income skip keyed on `employment_status` (mapper-seeded) | REUSE `skipSectionIfIncomeKnown` (captured-income columns) — do not write a new predicate |
| 4 | `CAMPAIGN_PREFIXES` | A2 + tests |
| 5 | `ONBOARDING_NAV_ROUTES` | Already covered — assert in a test anyway |
| 6 | RegisterRequest keys | A4 + feature test |
| 7 | UserResource omissions | No new users columns — if any appear, expose them |
| 8 | Corpus/in-code drift | C3.10; golden master locally before push |
| 9 | Schema descriptions govern the model | No schema edits planned; if `update_profile`'s income_occupation description gap bites (it names only `annual_employment_income`), that's a SEPARATE fix — flag, don't bundle |
| 10 | **Queued risk-profile job → empty synthesis** | The synchronous ensure (C4.2/C4.3) + the sync-queue test; D1 asserts the row |
| 11 | Model hallucinates params | `create_investment_account` handler already rejects joint ISAs; holdings allocation >100% — trust the normaliser, D5 spot-check |
| 12 | Duplicate creates | `record_context 'investments'` arm (C4.6) |
| 13 | Advice recursion (PR #504) | synthesis next = callable-string; copy campaign2 advice shapes |
| 14 | `describeStep` | C4.5 |
| 15 | `sectionLabel` | C3.9 |
| 16 | Silent empty synthesis | Degrade line + test |
| 17 | Terminal web route | `/investment` native (router:864) — routes test |
| 18 | /m store staleness | Generic since #612; D-walk asserts pills mid-re-entry |
| 19 | Pill literals | Don't rename bubbles |
| 20 | Web `**` literal | Pre-existing — out of scope |
| 21 | PreviewWriteInterceptor | No new auth POSTs |
| 22 | Plan-page XSS | HEX injection + `esc()` + B6 assertion |
| 23 | CDN caching | Standard headers; nothing new |
| 24 | Homepage card throw | try/catch + test |
| 25 | Free-tier account cap (2 investment accounts) | By design — D5 captures the at-cap voice; do NOT raise caps |
| 26 | Cache-busters | `?v=1` new; bump on edits |
| 27 | Key length | `investmentcheck` = 15 — fine |
| 28 | Stacked-PR order | Retarget before deleting bases |
| 29 | Queue-dependent effects | Trap 10 IS this — plus milestones mint on dashboard read; D1 asserts |
| 30 | Corpus validators | Exit 0 before deploy |

## Testing process & gates (the quality ladder — every slice climbs it in order)

**Gate 0 — pre-code (per slice).** Re-read the spec section the slice implements + playbook §2 (F1–F15) + this plan's trap table. Sweep every trap row against the slice's file list. Confirm the branch stacks correctly.

**Gate 1 — tests with the code (per slice).** Write the slice's listed tests alongside the implementation, never after the PR. Run targeted:
```bash
./vendor/bin/pest tests/Unit/Services/Marketing/InvestmentEstimateServiceTest.php  # B
./vendor/bin/pest tests/Feature/PublicPages/InvestmentcheckRoutesTest.php          # B
./vendor/bin/pest tests/Unit/Services/Onboarding/ --filter=Investmentcheck         # C
./vendor/bin/pest tests/Feature/AI/CampaignReentryStartTest.php tests/Feature/AI/CampaignReentryDispatchTest.php tests/Feature/AI/CampaignReentryExitTest.php  # C
./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php  # C — corpus lockstep
```
Pint every touched PHP file. Slice C: corpus validators exit 0; **zero golden-master fixture diffs** (no new tools — any tool-schema fixture diff is a bug, full stop).

**Gate 2 — review passes (per slice PR, before merge).** The canonical review path:
- `/code-review` on every slice PR — ALL findings reported with confidence + severity; fix or catalogue every one before merge.
- `pr-review-toolkit:pr-test-analyzer` on every slice.
- `pr-review-toolkit:silent-failure-hunter` on Slice C (the risk-profile synchronous ensure's try/catch is a designed silent-degrade — it must LOG and be pinned by the sync-queue test, not swallowed invisibly; also the holdings loop exits and the ISATracker try/catch in the recap builder).
- `security-reviewer` MANDATORY on Slice B (public pages XSS, register payloads) and Slice A's middleware change.
- `tax-compliance-reviewer` MANDATORY on Slice B1 (`InvestmentEstimateService` — ISA allowance/dividend allowance/thresholds via TaxConfigService keys only, no hardcoded £20,000/£500) and on `campaign3_isa`'s callable prompt (the interpolated allowance).

**Gate 3 — full suite (per slice merge).** `./vendor/bin/pest` — 0 failures, expected skips only; record the count in the PR body (baseline at plan time: 5,506 + ~30 skips). Architecture suite green (StoreBoundary — the C2 readers are the sanctioned access path; no direct `InvestmentAccount::` queries from the state machine).

**Gate 4 — deploy verification (before any D walk).** csjones: branch checkout; `./deploy/csjones-fynla/build.sh` ONLY; upload both bundles preserve-old-chunks; cache clears (never `optimize`/`route:cache`); **bundle-contains-change grep** on `public/m-build/assets/main-*.js`; `db:seed` only if seeders changed (none planned). **Queue check specific to this campaign:** confirm how csjones runs queues (`ps aux | grep queue:work` or the scheduler) — the risk-profile observer job is queued; the synchronous ensure makes the walk independent of it, but D1's "risk_profiles row exists" assertion should note WHICH path created it.

**Gate 5 — the browser gate (Slice D — Rule 14 loop, NON-NEGOTIABLE).**
- The law: "browser tested" = clicked, filled, submitted, verified in Playwright. Every D-table `[x]` maps to an interaction. No completion report before ALL walks run. Untestable items = "I COULD NOT TEST THIS", never "verified".
- **Runbook** (per `verify-m` — the desktop→/m bridge does NOT fire on cold automated navs):
  - Fresh-user walks: drive the real funnel (`/m?to=%2Finvestmentcheck`), register a disposable user, verification code via SSH tinker (`PendingRegistration::where('email',…)->first()?->verification_code`).
  - Existing-user walks: `/m/app/login` + MFA via tinker (`EmailVerificationCode::where('user_id',$u->id)->latest()->first()->code`). Dismiss level-up dialogs before asserting.
  - Backend assertions per step — for THIS campaign: `InvestmentAccount::where('user_id',$id)->get(['account_type','current_value','isa_subscription_current_year'])` (canonical types: `isa`/`gia`), `Holding::count()` per account (the backfill), `RiskProfile::where('user_id',$id)->exists()` (+ `is_self_assessed=false` = the auto/ensure path), `$u->annual_dividend_income` accumulation, `$u->onboarding_completed_at` byte-identity, `$u->active_campaign`, terminal award count stays 1.
  - Transcript ambiguity → read `ai_messages`. 202-queued → tinker cache-lock trick.
- **The loop**: RED → `systematic-debugging` with file:line evidence → fix on-branch → redeploy (Gate 4 repeats incl. bundle grep) → **re-walk from D1**. Exit only on all-GREEN or a surfaced CSJ decision.
- **Test-user hygiene**: soft-delete disposables after the gate; never mutate the standing test user beyond the walk's own writes; record data drift in the patch notes.

**Gate 6 — regression matrix (inside Slice D).** D4 mandatory: one savetax + one pensioncheck fresh walk on the deployed branch. Unit mirror: the byte-identity characterisation pins live in Slice C so cross-campaign drift fails before the browser. Extra pin for this campaign: `verifyEditFocus('investments')` stays `'investment'` (savetax verify-edit unchanged — trap C4.7).

**Gate 7 — post-merge duties.** Admin-merge each slice; retarget stacked PRs before deleting bases; csjones back on dev after the final merge (HEAD + bundle confirmed); `investmentcheck-patch-notes-technical.md` + `-feature-notes-user.md` to the day's Updates folder (repo + vault); CSJTODO updated; audit-doc file inventories extended if surfaces moved.

## Done

Slices A–C merged to dev, csjones D-gate GREEN through every gate above, patch + feature notes written, CSJTODO updated, prod untouched (release window extends — CSJ's call). If the estate campaign builds next, start from ITS plan — do not interleave.
