---
type: handover
mode: session-end
date: 2026-10-02
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-10-02, Session 1

## Where things stand

Four releases went out today; fynla.org runs main `cf0cbbc06`. They are #1047 (items 7 and 7a retirement work, decumulation how-to), #1053 (one income figure everywhere, Fyn's income and recommendations, item 16, two how-tos), and the hotfix #1055 (the demo households' protection profile). Item 7 and item 16 are crossed off. Item 7a has one job left before it can close: **one take-home figure** (the START HERE line at the top of 7a in `todoCurrent/TODO.md`). Local and csjones are on `dev`, clean apart from CSJ's own files.

## Priorities for the next session

1. **Item 7a, START HERE: one take-home figure** (CSJ: "make sure the income fix is first todo, so we can get 7a completed to move on").
   - The Income tab's figure (`UserProfileService::buildIncomeOccupation` -> `UKTaxCalculator::calculateDetailedNetIncome`) is the one home. Fix it first:
     1. "other income" is never passed in (`UserProfileService.php` around `:650`), so it is never taxed on the Income tab;
     2. National Insurance is charged on earnings past State Pension age (the calculator has no age input; SSCBA 1992 s6(3); Class 4 stops from the 6 April after). `RetirementDrawdownPosition::nationalInsurance` already has the right rule: move it into the one home, do not copy it.
   - Then `RetirementDrawdownPosition::income` (`:98`) reads the Income page's parts and total (`IncomeDefinitionsService`) and the Income tab's Income Tax, National Insurance and take-home. Today it adds its own parts (interest estimated from balances) and prices tax with `TaxStrategyMath::incomeTaxLiability`.
   - Watch: the Tax plan (`TaxStrategyMath::taxableIncomeFor`) estimates interest from balances when none is recorded; the Income page does not. Decide on evidence which one is right and make them one, citing the source (Rule 23); do not ask CSJ to choose.
   - Tax-compliance review, walk csjones web 1440 + /m 390 (460 retiree, Bennett and Mitchell demos), release, walk fynla.org.
   - CSJ clarified today: income is entered from many sources; a pension drawn is one line among them. The Retirement box must show the Income page's income, not its own.
2. **Item 8: Investment module review, then its how-tos** (24 definitions). Same shape as items 6 and 7.
3. **Item 8a: smoker and health status** (deferred by CSJ until item 8's investment cards are done). The old onboarding already stores `users.smoking_status` / `users.health_status`; the enhanced annuity card reads `protection_profiles.smoker_status` / `health_status`. One home first, then any input.
4. **DECISION (CSJ), still open:** what a retired person who has not started drawing sees on the Retirement page. The pot chart says "the same £0 each year" and "drawn down from today" while it only rises (csjones walk account 405; screenshot `walk-release-1002/cs-web-405-retirement.png`). CSJ found the first explanation unclear; explain with the screenshot and what the page shows, not code terms.

Note: the prompt hook prints "CURRENT ITEM: 8." because it reads whole-number items only; 7a comes first (CSJ).

## Context to load

- `todoCurrent/TODO.md` — the order of work; item 7a's START HERE line, its Found lines, items 8 and 8a.
- `app/Services/UserProfile/UserProfileService.php:619` — `buildIncomeOccupation`, the Income tab's tax and take-home (the one home to fix); `incomeAndTaxFor` above it.
- `app/Services/Retirement/RetirementDrawdownPosition.php:98` — the Retirement income box that must read the one figure; `nationalInsurance` holds the correct State Pension age rule.
- `app/Services/UKTaxCalculator.php:34` — `calculateDetailedNetIncome` (no other income, no age input).
- `docs/superpowers/specs/2026-10-01-retirement-drawing-view-design.md` — what the drawing view promises (sections 2 and 3), so the change keeps CSJ's agreed design.

## Completed this session

- Release g (#1047, main `594781913`): items 7/7a (retirement cards, drawing view figure on the dashboard, care costs out, every surface reads server figures), decumulation how-to approved.
- #1048: one rule for ISA allowance used (item 16), one home for each taper (audits 42, 43), no "0 months" investment emergency card, investment cards in pounds.
- #1050: Fyn's income and band are the Income page's and the Tax plan's (audit 44); Fyn's recommendations are the actions list on all four paths (`NextActionsService::forModel`; audit 50); recommendation-routing procedure v2; two how-tos approved.
- #1051 + #1052: every income reader uses the Income page's total (`resolveGrossAnnualIncome`), bands from `TaxStrategyMath::incomeTaxBandFor`, earnings from `relevantEarningsFor`, rent is the rental profit, take-home helper reads the Income tab; typed-in CGT fallbacks removed.
- Release h (#1053, main `e4f991d87`) and hotfix release i (#1055, main `cf0cbbc06`): #1054 gives every demo persona a protection profile.
- Patch notes `October/October1Updates/patch-notes-2026-10-01.md` + PDF cover releases g, h and i. Item 7 and item 16 crossed off.

## Verification state

- Touched-path Pest tests green at each merge (591 + 381 + 242 for the income work; 199 for Fyn; 32 investment).
- Walked csjones web 1440 + /m 390: 405 (decumulation card, Fyn recommendations after marking an action done), 460 (Fyn income, module checks unlocked, Income tab £9,000), Mitchell demo.
- Walked fynla.org: Mitchell demo web + /m (figures, protection £700,000 after the hotfix, Investment 3 actions, ISA card in pounds); Bennett demo web (Income tab £30,000 = Income definitions, £26,514 take-home).
- Not verified: Fyn on fynla.org (demo sessions cannot open Fyn); the decumulation, consolidation and enhanced annuity cards on fynla.org (no live account qualifies); iOS (CI only).

## Decisions and dead ends

- CSJ: smoker and health are needed; check the old onboarding first; deferred until item 8's investment cards are done (item 8a).
- CSJ: all three retirement how-tos approved (decumulation, consolidation, enhanced annuity).
- `TaxActionDefinitionService` is called by no app code; its salary-only bands and invented £200 never reach a user. Left in place; removal is CSJ's call.
- Fyn's recommendations: the Holistic Plan page still uses the orchestration's own list (its action plan needs the engines' amounts), left as it is.
- Protection keeps its own earnings-versus-continuing-income split on purpose (earnings stop on death); only its total and rent moved to the one figure.

## Things that will bite you

- **`PreviewUserSeeder` on fynla.org rebuilds the demo households from scratch.** Anything a page visit creates disappears; check the demo dashboards straight after any release that reseeds.
- Production release scripts: when CSJ asks in the message, run the script; when CSJ sends `! bash <script>` and no output arrives, it did not run (check `origin/main`). The auto-mode classifier refused a self-started prod script once today.
- csjones: a stale session cookie sends the homepage to /login (clear cookies); the cookie banner blocks "See our demo" until "Continue Without Cookies"; `php artisan cache:clear` signs out walk sessions.
- Unsaved `User` models now work in `IncomeDefinitionsService::calculateFor` and `TaxStrategyMath` (no property, vest or savings lookups; only saved users cached).
- Walk accounts on csjones: 405 `pensioncheck-e2e-m-0916@example.com` (now has an extra £40,000 SIPP, "Walk SIPP Two", and "Beneficiary Designations Review" marked done), 459/460 `item7-walk-a@` / `item7-walk-b@example.com`, all `Password1!`; codes via csjones tinker.

## Tech debt deferred

From `docs/tech-debt-report.md` (2026-10-02):
- Four services still inject an unused `UKTaxCalculator` (`GoalAffordabilityService:23`, `GoalsProjectionService`, `FinancialForecastService`, `UserContextBuilder:38`).
- Own band rules remain: `HouseholdPlanningService:613/637`, `UserContextBuilder:356`, `TaxOptimisationService:474`.
- `TaxActionDefinitionService` dead (typed-in 20/40/45, £200 fallback).
- `PlanningProgressService` percentile builds the income figure per user on a cache miss (watch).
- Unused `Cache` imports in `InvestmentAgent`, `HasAiChat` (pre-existing).

## Branch and deploy state

- Branch: `dev`, pushed; no unpushed commits.
- fynla.org: main `cf0cbbc06`. csjones: `dev`.
- CSJ's own uncommitted files left alone: two excalidraw diagrams, the September 30 handover edit, workforce logs. Three abandoned agent worktrees from 2026-10-01 under `.claude/worktrees/` still hold uncommitted changes.
