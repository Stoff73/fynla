---
type: handover
mode: session-end
date: 2026-09-22
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-22, Session 2

## Where things stand

Brett's phone-walk feedback (`brettTest/commentsBrett.md`, items 2–12) is built, merged and released: PRs #929–#933 plus yesterday's expenditure refactor #928 went to dev one at a time, each deployed to csjones as a stacked branch and walked before its merge, then dev → main as #934 (main `df14df1e2` == dev `a4ce28120`, ~20:45 BST). Production runs it and was walked live on `/m` with a fresh funnel registration (user 750, purged). csjones is back on dev with both bundles. Nothing is in flight and nothing is unreleased.

## Priorities for the next session

Nothing outstanding. Three small decisions for CSJ, none urgent:

1. **`RequiredCapitalDetail.vue` is dead code** — imported nowhere (0 references), so #928's `account_type_label` switch there is untestable in a browser. Delete it with the other MB-09 dead pages, or wire it in. Ask in one line.
2. **The orphaned Okay gate** — `campaign_verify_announce` and `verifyPromptAnnounce()` (`OnboardingStateMachine.php:832`, `:1414`) are unreachable since #932. CSJ said "let's try this" about removing the tap; once the trial settles, delete both or restore the gate. Not before CSJ says.
3. **Item 7 scope** — the retirement projection, target, age-banded projection and recommendations cards are hidden on the onboarding verify visit only (`Retirement.vue`, predicate `inOnboardingVerify()` in `resources/mobile/store.js`). CSJ 2026-09-22: "hidden during onboarding is fine". Leave unless Brett raises it again.

Natural next move if CSJ has nothing new: the eleven parked mapping PRs (#829–#839) or the iOS section of `CSJTODO.md`.

## Context to load

- `docs/superpowers/plans/2026-09-22-brett-test-batch.md` — the plan every PR resolved against; the item numbers CSJ uses.
- `brettTest/commentsBrett.md` — Brett's original twelve notes (item 1 withdrawn), plus `brettTest/evidence/m-property-equity-csjones.png`.
- `docs/tech-debt-report.md` — today's audit; source of the three decisions above.
- `app/Services/Onboarding/CaptureForms.php` — `expenditureTax()`, `nonEarnerNetContribution()`, `investment()` with `annual_dividend_income`, `spouseAssets()` with the yes/no; the form is the one home for web and `/m`.
- `app/Services/Onboarding/OnboardingStateMachine.php:1187` — `enterCampaignVerify` now returns `campaign_verify_navigate`; the comment block at `:826` explains the orphaned announce state.
- `CSJTODO.md` — pruned today; deploy state and test accounts are current.

## Completed this session

- #928 `chore/expenditure-categories-one-home` (merged a4ce28120): `SharedExpenditure::FREE_CATEGORIES` replaces two private constants; `OnboardingService::processExpenditureInfo` derives both branches from `SHARED_FIELDS` + `charitable_donations` (the separate branch used to drop five categories); `getStepDataFromUser` expenditure fallback derived the same way and fires on any stored category (the wizard showed £0 childcare for a Save Tax user); `RequiredCapitalDetail.vue` reads `account_type_label`. Tests `ExpenditureStepColumnsTest` (3).
- #929 `fix/brett-batch-a` (5df869e70): `BUBBLE_BREAK` never reaches a form turn raw (paragraph break in the lead-in; transcript row clean); `TaxStrategy.vue` "See all your actions" → `m-actions`; job title optional on the work form and in `capture_work_details` corpus (golden masters recaptured); `inOnboardingVerify()` in the `/m` store hides the risk card (`Investment.vue`) and the retirement hero/target/projection/recommendations (`Retirement.vue`) on the verify visit, and `MobileChrome` reads the same predicate; risk wording "any you don't agree with"; expenditure label "What your household spends each month"; spouse dividend-holdings hint "or unknown". Test in `FunnelRecapOnceTest`.
- #930 `feat/m-property-equity` (68d6cb6e0): "Equity £x" under each mortgaged property on `NetWorthCategory.vue` (share of value minus share of mortgage; web `PropertyCard` already had it).
- #931 `feat/brett-fyn-forms` (de59fb751): "Dividends it pays you each year" on the GIA and Other kinds of the investment form (`create_investment_account` already stored it); non-working spouse form asks "Dividends they receive each year" and a yes/no "They pay the non-earner maximum into it (£2,880 a year)" whose figure is `relevant_earnings_minimum × (1 − tax_relief.basic_rate)` from `TaxConfigService` via `CaptureForms::nonEarnerNetContribution()`; handler writes `spouse_pension_input_annual` (yes → figure, no → 0); corpus schemas (Anthropic + xAI) and both golden-master sets recaptured. Tests in `CaptureFormsTest`, `CaptureSpouseNonWorkingAssetsTest`.
- #932 `feat/verify-without-okay` (38b397968): `enterCampaignVerify` → `campaign_verify_navigate`; the capture end's "No" carries the navigation event in the same turn; 45 test assertions repointed; property announce test asserts the navigate prompt + navigation event.
- #933 `feat/savetax-expenditure-tax-only` (ba56c1d8a): `expenditureTax()` (childcare, donations, Gift Aid; no total; `base` = expenditure; `lead_in` "Two things that change your tax." read generically by the Director); `handleCaptureMonthlyExpenditure` leaves the total alone when none is sent; `expenditureAck()` path-aware; `spouseAssetsAck` repeats dividends and the contribution. Tests in `CaptureFormsTest`, `ExpenditureCaptureFormTurnTest` (one-box/category cases moved to a pension-check user), `SpouseSectionNoVerifyPageTest`.
- Release #934 (main `df14df1e2`): backup `~/release-backups/2026-09-22c/`; rsync 263 files incl. fyn-memory and both bundles; autoload 8,665 classes; no migration; corpus validates; log clean. Walked live as user 750 (funnel: part-time, £50,271–£100,000, non-working spouse, investments + pension): every item above observed on fynla.org except property equity (no property on the account; verified on csjones as user 397). Account purged (RetentionPurgeService, forceDelete, pending_registrations, token sweep; remaining 0).
- Memory: `feedback_fix_adjacent_defects_in_path` (new, CSJ 19:03 ruling), `reference_csjones_campaign_walk_seed_account` (new: tinker recipe, funnel key `pension` singular, accounts left), `project_release_2026_09_22` extended with #934.

## Verification state

- Quality Gate: green on #928–#933 at merge (Unit, Feature, Architecture, Integration, Eval, lint, builds, frontend, browser-smoke). The iOS `test-and-build` workflow is red on every PR including yesterday's; not a gate.
- Release PR #934: fast checks green at merge; the Unit and Feature suites were still running on the merged run at handover — check `gh pr checks 934` if anything looks off tomorrow.
- csjones walks: users 419 (batch A, item by item), 397 (equity), 420 (forms and acks), 421 (no-Okay navigation, tax-only expenditure, wizard prefill over API + web).
- Production walk: user 750 as listed above; log clean at 19:4x–20:0x UTC.
- Not verified: native iOS (untouched); the typed (non-form) client path for the tax-only expenditure form (the corpus `capture_monthly_expenditure` schema still requires `monthly_total` for the LLM path; every live client sends forms).

## Decisions and dead ends

- CSJ 2026-09-22 17:50: job title optional; try removing the Okay tap; hide the risk profile during onboarding ("an action for later"); equity on both surfaces; hide the retirement projection (onboarding-only, confirmed 19:03); non-working spouse contribution as a yes/no at the non-earner maximum rather than an amount; dividend input for main user and spouse "when entering shares".
- CSJ 19:03: Save Tax expenditure keeps the three tax fields and drops the monthly total ("we are only looking at the save tax stuff, it can be in actions"). Option (a) dropping the whole step was rejected because those fields feed yesterday's threshold lines.
- CSJ 19:03: a defect found while building or walking is fixed in the same batch, never reported as "adjacent". Saved as memory. The CLAUDE.md "report adjacent issues" line is about unrelated scope only.
- Bubble-break fix chosen as a paragraph break in the form lead-in rather than splitting the form turn into content bubbles: one line, one bubble, transcript clean. The free-text branch still splits into bubbles.
- A form variant must carry `'base'` (the state's form name) or the Director refuses the posted form (found when the tax form silently wrote nothing).
- `funnel_answers['assets']` uses `pension` singular; `pensions` skips the section silently (cost one walk).
- csjones has no faker: seed users with `User::unguarded(fn () => User::create([...]))`, no `name` column.
- `.user.ini` files stay on both servers (harmless, superseded by `AppServiceProvider::boot`).
- The excalidraw diagrams and `workforce/ops/log/*` in the working tree were dirty at session start (Excalidraw re-save; Myrtle brief hook failing with `account_inactive`). Not mine; left uncommitted.

## Things that will bite you

- csjones test accounts 419–421 and 397 exist with `Password1!`; 419 and 421 have `life_stage = accumulation` set by hand; 420 has childcare £600 set by hand for the prefill check. Purge or reuse freely.
- The Myrtle brief hook appends to `workforce/ops/log/` every session start and fails on Slack (`account_inactive`); the tree will look dirty again tomorrow.
- `gh pr checks` reports `mergeStateStatus` UNKNOWN/BLOCKED briefly after a base moves; poll the checks, not the status.
- The local `public/build` and `public/m-build` hold the PRODUCTION build (last run). A local `/m` check needs the mobile bundle rebuilt for the local base path.

## Tech debt deferred

See `docs/tech-debt-report.md` (3 warnings, 4 suggestions): dead `RequiredCapitalDetail.vue`; orphaned announce state; `CoordinatingAgent` 6,979 lines / `handleSetExpenditure` 203; spouse acks build the same clauses twice (`OnboardingChatDirector:6423`, `:6479`); childcare hint written three times in `CaptureForms` (`:1267`, `:1291`, `:1345`); `expenditureColumns()` / `expenditureColumnsOf()` walk the same list; `nonEarnerNetContribution()` uses `app()` in a static class.

## Branch and deploy state

- Branch: dev at a4ce28120 (tree == main df14df1e2); working tree dirty only with the pre-existing excalidraw and workforce-log changes plus untracked `chrisMapping/` and brief reports
- Unpushed commits: none (this handover commit follows)
- Deploy status: fynla.org and csjones both run the tip; backups `~/release-backups/2026-09-22{a,b,c}/` on production

Back to [[September Index]]
