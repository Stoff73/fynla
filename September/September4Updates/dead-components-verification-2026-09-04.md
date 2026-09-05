---
type: report
date: 2026-09-04
tags: [report, w-0540, dead-code, frontend, verification]
branch: chore/board-verification-31-august
verified_against: origin/dev 41771cca0, csjones.co/fynla build of 2026-09-04 09:24
author: session 2, on CSJ's instruction to verify before deleting
---

# Dead components on dev: what is true, why it happened, and what to do about each

CSJ stopped a bulk deletion of 79 "orphaned" Vue components this morning and asked
two things: prove it is actually a defect, and explain why it is happening. Then a
harder question: if the code was built, why was it not mounted, and is it not all
needed? This report answers all three, against `dev` as served on csjones.

## 1. Summary

**It is real, it is larger than the board says, and "dead" does not mean "not needed".**

| Measure | Count |
|---|---|
| Vue components under `resources/js/components` and `resources/mobile/components` | 527 |
| Flagged by the W-0540 guard (substring search) | 79 |
| Unreachable from either Vite entry by walking real imports | 152 |
| Views under `resources/js/views` that no route reaches | 2 of 166 |
| Dead component names found in the compiled csjones bundle | 1 of 152, a name collision with a live view |
| Dead components kept green by a vitest spec | 32 |
| Dead components edited in August or September 2026 | 18 |

The 152 fall into four different stories, and they need four different answers:

| Story | Roughly | What to do |
|---|---|---|
| Superseded by a live rebuild doing the same job | 100 | Delete, after a per-file check that no detail was lost |
| Built, still advertised, no screen, no Fyn tool: the advanced investment analytics | 12 | CSJ decision: re-mount, or retire the claims in Help, README and vault |
| Built and never mounted | 2 | One superseded, one a missing marketing feature |
| Smaller feature gaps and one live dead end | 6 plus 1 | Itemised in section 6 |

Nothing has been deleted. Section 10 lists the decisions that are CSJ's.

## 2. The product these components belong to

Read from the constitution, the public site, the tier seeder and the routers. This
is here because the recommendations depend on it, and because it was missing from
every previous session's reasoning about this item.

**Vision** (`workforce/core/constitution/01-mission.md`, adopted verbatim): every UK
household should plan its money the way the wealthiest families do, seeing business,
pension, property and estate as one living picture, and pay £20 a month for it, not
£20,000 a year. Fyn is what makes that price possible: the reasoning of a £500-an-hour
adviser, always on, never scolding, never selling a product.

**Who** is defined by life stage, not income. The six seeded personas are the market:
student, young saver, young family, entrepreneur, peak earners, retired couple. Households,
not users; the north star is Paid Active Households.

**Why**: three founders with over 40 years in UK financial services saw the planning
tools locked behind professional gatekeepers or too complicated for a kitchen table.
"The sat nav for your financial life." Not FCA-authorised, guidance not advice,
fail-closed in code. No products, no commission, no bank connections, no advertising.

**What**: one dashboard across pensions, investments, savings, property, protection and
estate; eight agents over seven modules plus tax optimisation, every tax value from
configuration; Fyn as the single voice across web, `/m` and native iOS. The side menu
is the product map: Dashboard and Net Worth; Cash Management; Finances (investments,
retirement, property, liabilities, valuables, risk profile, business); Family or
Personal Affairs (protection, will, letter to spouse, trusts, estate, power of attorney);
Planning (holistic plan, tax strategy, plans with PDF export, journeys, what-if, goals,
life events, actions).

**How it is used**: arrive through the Save Tax funnel or the preview personas; register
with an email code; Fyn-led onboarding, forms, or statement upload on Premium; fifteen to
twenty minutes, no bank logins; the dashboard is the "aha moment"; then modules, plans,
what-if levers and the actions list. Spouses link. Phones route to `/m` with the level
wheel. Free is permanent with count caps; Premium is £6.99 a month or £59.99 a year;
the seeder `TierConfigurationSeeder.php` is the contract.

The consequence for this report: **an investment analytics engine is not decoration in
this product. "The way the wealthiest families plan" is the promise.** A component that
delivered part of that promise and fell out of the bundle is a lost feature, not clutter.

## 3. Instruments, and which one to trust

Three instruments were used. They agree where they overlap, and the disagreement is
itself a finding.

**The W-0540 guard** (`tests/Architecture/EveryComponentIsRenderedSomewhereTest.php`)
counts a component alive if its basename appears as a substring in any file under
`resources/js`, `resources/mobile`, `tests/frontend`, `resources/views` or
`public/pages`. That leaks three ways:

- a dead parent keeps its dead child "alive": `Estate/TrustForm.vue` is mentioned only
  by `Estate/TrustPlanning.vue`, which nothing renders either; 34 of the 73 it misses
  are this;
- a spec keeps it "alive": 32 dead components have a passing vitest spec;
- substring collisions: `NetWorth` matches 39 live files, `GoalForm` matches
  `GoalFormModal`, `StrategyCard` matches `DecumulationStrategyCard`.

**An import-graph walk** (`reach.mjs`, scratchpad) starts from the two Vite entries,
`resources/js/app.js` and `resources/mobile/main.js`, follows every `import ... from`,
`import()` and `require()` through the `@` and `@m` aliases, and reports every
component the graph never reaches. It reaches 758 modules. Validated before use:
AppLayout, PublicLayout, MobileChrome, TrustsOverviewCard and TrustCard are all reached;
164 of 166 views are reached; there are no template-literal dynamic imports, no
`import.meta.glob` over components, and no global registration on either entry. Every
one of the guard's 79 is inside its 152, so the guard has no false positives, only misses.

**The compiled bundle on csjones.** All 268 JavaScript chunks the csjones manifest lists
were downloaded and grepped for `name:"X"` and `__name:"X"`. 151 of the 152 are absent.
The one hit, `WhatIfScenarios`, is the live view `views/Planning/WhatIfScenarios.vue`
sharing a name with the dead `Protection/WhatIfScenarios.vue`. Nine live controls are
all present. Neither unrouted view is in the manifest. The component tree and every
import line are identical between `origin/dev` at `41771cca0` and the working branch,
so these are dev's numbers.

Two earlier measurements are withdrawn: the board's 79 was an under-count, not a
count, and the mid-session report of two "guard false positives" was a `comm`
collation artefact, not real.

## 4. Why nothing fails when a component dies

Four properties of the stack combine.

1. **Vue resolves tags by name at runtime.** A template that uses a component it never
   imported renders an empty custom element. The only signal is a console warning, and
   only in dev mode. Production shows nothing and throws nothing.
2. **Vite builds from one entry per bundle** by following imports. A file nothing
   imports is silently left out. The build is green, and smaller.
3. **Vitest mounts components directly**, so a spec keeps a dead component green
   forever.
4. **Lint checks unused imports inside a file**, the opposite direction. Commit
   `9e865394c` on 16 July "cleared whole-range frontend lint" by deleting six imports
   from `NetWorth/InvestmentList.vue`; those six lost their last importer in a commit
   whose purpose was a green CI.

So three ordinary events orphan a component with no signal: a page rewrite that deletes
the parent, a cleanup or lint fix that removes the import, and a feature that is built
and never mounted. Until yesterday no check had ever looked in this direction.

## 5. How the 152 got there

| Added | Count |
|---|---|
| October 2025 | 55 |
| November 2025 | 28 |
| December 2025 to March 2026 | 66 |
| After May 2026 | 3 |

78 of the 152 were last touched in March 2026, which is when the parents went:

| Date | Commit | What it replaced |
|---|---|---|
| 2026-02-24 | `c135176e3` goals cleanup | the old goals components |
| 2026-03-04 | `e2230efc5` holistic plan rewrite | `Investment/PlanSections/*` |
| 2026-03-20 | `717d24f97` onboarding form cleanup | deleted `JourneyCompletionStep.vue` |
| 2026-03-22 | `c4e6caf70` UK Taxes page deleted | `Dashboard/TaxOptimisationCard` and kin |
| 2026-03-23 to 25 | dashboard nav restructure, batch 9 | the old `Dashboard/*` cards |
| 2026-03-26 | `9d0e73697` unify completeness | `ProfileCompletenessAlert` on the protection view |
| 2025-12-15 | `3665b4feb` Net Worth redesign | hid the portfolio tabs, "components still available for detail views" |
| 2026-07-16 | `9e865394c` lint clearance | cut those tabs' imports |

Four dead-code sweeps, on 26 February, 9 April, 18 April and 6 July, each deleted what
someone noticed and built no guard. The 12 May conventions review counted "106
candidates, at least 30 confirmed" and wrote "should be deleted", but was research-only
and never became a board item.

## 6. Findings by cluster, each with a recommendation

### 6.1 Superseded by a live rebuild

The March rebuilds replaced these with live components doing the same job and left the
old ones behind. Confirmed pairings:

| Dead | Live replacement |
|---|---|
| `Retirement/DrawdownSimulator`, `AnnualAllowanceTracker`, `IncomeProjectionChart`, `AccumulationChart`, `TaxBreakdownCard`, `RequiredCapitalDetail`, `StrategiesTab`, `StrategyCard` | pension list tabs: `RetirementIncomeTab`, `DecumulationStrategyCard`, `PensionPotProjectionChart`, `CapitalAdequacyTab`, `FutureValueTab` |
| `Investment/PlanSections/*` (7) | `Plans/Investment/InvestmentPlanContent` and its sections |
| `Goals/GoalMilestoneTracker`, `GoalContributionStreak`, `GoalsList`, `GoalsAnalysis`, `GoalsByModule`, `GoalCard`, `GoalCountdown`, `ChartTypeToggle`, `EventIconsOverlay`, `EventTooltip` | `Goals/GoalsDashboard`, `GoalsOverview`, `GoalProgressBar`, `GoalDetailInline`, `GoalsProjectionChart` |
| `Investment/RebalancingActions`, `RebalancingCalculator`, `CGTHarvestingOpportunities`, `FeeSavingsCalculator`, `TaxFees`, `BedAndISATransfers` | `AccountRebalancingPanel`, `TaxEfficiencyPanel` with `HarvestLossModal`, `FeeBreakdown`, `AccountFeesPanel`, `BedAndISAWizardModal` |
| `Protection/GapAnalysis`, `ProtectionOverviewCard`, `RecommendationCard`, `CoverageGapChart` | `Protection/ProtectionModuleOverview` |
| `Estate/LifePolicyStrategy` | `Estate/LifeCoverRecommendations` |
| `Dashboard/SpousalOptimisations`, `CrossModuleInsights`, `TaxOptimisationCard` | `TaxStrategy/HouseholdCoordinationPanel`, the holistic plan, the tax strategy page |
| `Dashboard/*` cards, `Gamification/LevelCard` | `views/GamifiedDashboard.vue`, which `Dashboard.vue` renders unconditionally and which carries the level pie and bar |
| `Risk/CapacityForLossSection`, `TimeHorizonSection`, `RiskFactorsPanel` | `Risk/RiskProfilePage`, `RiskFactorDetailPage`, `FactorBreakdownCard` |
| `NetWorth/Property/PropertyTaxCalculator` | `NetWorth/Property/PropertyFinancials` (stamp duty) |
| `WhatIf/ScenarioDetail`, `Protection/WhatIfScenarios`, `ScenarioBuilder`, `Investment/WhatIfScenariosBuilder` | `Planning/WhatIfDashboard`, `WhatIfScenarioDetailView`, `ModuleComparison` |
| `Legal/StrategyDisclaimer` | the site-wide disclaimer in `AppFooter` and `AiChatPanel` |
| `Public/CalculatorCard` | the server-rendered `public/pages/calculators.php` |
| `Admin/Admin*Actions`, `*ActionModal` | `Admin/AdminPanel` and its tabs |

**Recommendation:** delete, directory by directory, but only after a per-file diff read
that asks one question: does the dead file contain a rule, a figure, a caption or a
state the live replacement lacks? W-0376 was exactly a dead site carrying its own copy
of a rule. Any such finding becomes a board item before the file goes. The 32 specs
that mount dead components go with them, and two constants specs that pin dead file
paths, `constants/__tests__/assetTypes.spec.js` and `retirementAge.spec.js`, need
their lists edited. `FreemiumCopyContractTest.php:25` pins `CalculatorCard` and needs
its entry moved to `deleted` with the counts adjusted, as the handover already noted.

Not verified: the `UserProfile/*` cluster (balance sheet, cash flow, income statement,
tax summary). `views/UserProfile.vue` and `views/ValuableInfo.vue` today render personal
information, income, expenditure, the letter and the risk summary; I did not find a live
balance sheet or profit-and-loss statement. Treat that cluster as section 6.6, a
product decision, not as superseded.

### 6.2 Lost but still advertised: the advanced investment analytics

These were built end to end and today have no screen, no Fyn tool and no `/m` surface:

`Investment/EfficientFrontier`, `CorrelationMatrix`, `PortfolioOptimizer`,
`PortfolioOptimization`, `PerformanceAttribution`, `BenchmarkComparison`,
`PerformanceLineChart`, `AssetLocationOptimizer`, `WrapperOptimizer`,
`AllocationComparison`, `GeographicAllocationMap`, `MonteCarloResults`.

The backend is live and maintained:

| Engine | Files | Last commit | Test files |
|---|---|---|---|
| `Services/Investment/Analytics` | Markowitz optimiser, efficient frontier, covariance and correlation, portfolio statistics | 2026-05-28 | 2 |
| `Services/Investment/Performance` | alpha and beta, benchmark comparator, attribution analyser | 2026-06-05 | 1 |
| `Services/Investment/AssetLocation` | asset location optimiser, tax drag, account type recommender | 2026-07-06 | 3 |
| `Services/Investment/Rebalancing` | drift, tax-aware rebalancer | 2026-08-14 | 3 |

Routes at `routes/api.php:651-763` serve efficient frontier, correlation matrix,
minimum variance, maximum Sharpe, target return, risk parity, asset location,
performance attribution and multi-benchmark comparison. Nothing under `app/Services/AI`
names any of them, so Fyn cannot surface them either.

The promises are still live: `views/Help.vue:313` "Risk metrics (Alpha, Beta, Sharpe
Ratio, Volatility, Max Drawdown, VaR)"; `Help.vue:350` "Efficient Frontier: Optimal
portfolio allocation analysis"; README "Advanced features: Shipped: Portfolio
optimisation, Monte Carlo"; the vault overview lists efficient frontier as a key
investment calculation.

How it fell out: the December 2025 Net Worth redesign hid the portfolio tabs behind a
comment at `InvestmentList.vue:178`, "Hidden from dashboard, components still available
for detail views". Four of those five tabs later got detail routes (`holdings-detail`,
`tax-efficiency`, `fees-detail`, `strategy-detail`). The fifth, "Optimisation", never
did. The July lint fix then deleted the imports. Nobody decided to drop the feature.

**Recommendation:** this is CSJ's decision, and it is a product decision, not a
cleanup one. Two honest options.

- **A. Re-mount as an "Optimisation" detail route**, the missing fifth sibling of the
  four that exist, reusing the commented scaffold. Costs before it can ship: 15 of the
  152 dead files carry Rule 12 score strings, 11 carry emoji or glyphs, 4 carry tax
  literals, and the whole cluster predates the design system, so this is a rebuild on
  the old code, not a re-import. It also needs `compliance-lead` review: "optimal
  portfolio" and "maximise Sharpe" are closer to a recommendation than anything else
  in the app.
- **B. Retire the claims now** in `Help.vue`, the README and the vault overview, keep
  the backend and its tests, and record the analytics as a parked capability in
  `workforce/core/registry/capabilities.md`. The components can then be deleted with
  the backend intact for a later re-mount.

My recommendation is B immediately, because the Help text is a live false promise to a
paying user today, followed by A as a specified mission if the vision's "wealthiest
families" claim is meant literally. What must not happen is a third option: deleting
the components on a count and leaving the promises in place.

### 6.3 Built and never mounted

- `Gamification/LevelCard.vue` (6 June): the plan said mount it on the web dashboard.
  `GamifiedDashboard.vue` now renders the level pie and progress bar from the same
  store. **Superseded. Delete.**
- `Insights/InsightCtaPanel.vue` (17 July): the Stage 5 migration comment says it swaps
  the default "Register free" call to action for the campaign-specific one when an
  article is linked to a campaign. It is not wired, so that swap never happens on any
  article. **Mount it. This is a missing marketing feature, `growth-lead` owns it.**

### 6.4 A live dead end: journey completion

`OnboardingWizard.vue:248` renders `<JourneyCompletionStep>`, a file deleted on 20 March
in `717d24f97`. The route is live: `/planning/journeys` renders `JourneyCard`, which
links to `/onboarding/journey/:journey`, and finishing the last step sets
`showJourneyCompletion` at line 1344. From then the user should see nothing, and the
`@next` handler that would move them on can never fire.

**Not browser-verified.** Reproduce on csjones by starting any journey from Planning,
completing its steps, and watching the final screen.

**Recommendation:** raise as a new board item, high, web and `/m` in scope. Fix by
either restoring a completion step or routing straight to the dashboard on completion,
and cover it with a vitest that mounts the wizard in journey mode and asserts the
completion state renders something.

### 6.5 Dead branches inside live templates

- `ProtectionDashboard.vue:6` renders `ProfileCompletenessAlert` with no import and no
  data; the `v-if` is always false. Harmless. **Delete the lines.**
- `InvestmentList.vue:179-218`, the commented tab block. **Resolve with 6.2**, then
  delete or restore.

A scan of every reached `.vue` for PascalCase tags with no import found only these and
the journey step, plus one `<YAML>` inside a string literal. The hole is small today.

### 6.6 Smaller feature gaps needing a product answer

| Dead file | What it did | Live today | Recommendation |
|---|---|---|---|
| `NetWorth/Property/AmortizationScheduleView` | mortgage amortisation schedule | only a mention in `LiabilityForm` | Decide. Cheap to re-mount on the mortgage detail, which `/m` already has as a route |
| `Savings/InterestRateComparisonChart` | user's rate against seeded market rates | rates are admin-maintained, 9 backend consumers, no user chart | Decide. The website promises "smart benchmarking"; if a recommendation surfaces the rates by other words, delete; not verified |
| `Preview/KeepDataOrFreshModal` | "keep it as a starting point?" on preview-to-register | no equivalent | Delete. Preview writes never persist, so there is nothing to keep |
| `Savings/MissingDataCard`, `SavingsDecisionPath` | savings guidance cards | `SavingsRecommendations`, `SavingsModuleOverview` | Likely superseded; confirm by reading |
| `UserProfile/*` statements | balance sheet, cash flow, P and L, tax summary | not found live | Decide. If financial statements are still a feature, they need a home |
| README "liquidity ladder" | `LiquidityAnalyzer.php` exists | no view, live or dead | Fix the README or build the view |
| README "human capital" | `RecommendationEngine.php` | `ProtectionCurrentSituation`, `ProtectionModuleOverview` | Delivered. No action |

### 6.7 Two unrouted views

`views/Investment/AccountPerformancePanel.vue` and `PortfolioStrategyPanel.vue` are the
only views no route reaches. Their siblings `AccountSummaryPanel`, `AccountHoldingsPanel`,
`AccountFeesPanel` and `AccountRebalancingPanel` are routed. **Check whether the
per-account performance and strategy tabs were meant to exist; route them or delete
them with 6.2.**

## 7. The guard

W-0540's acceptance names a guard, and the guard is the item. Two changes:

1. **Replace the substring search with the import-graph walk.** The scratchpad
   `reach.mjs` is 30 lines and needs no dependency; port it into the Pest test or run
   it from vitest. A guard that passes dead subtrees is not a guard.
2. **Add the reverse check**: every PascalCase tag used in a reached template must be
   imported or registered in that file. That is the check that would have caught the
   journey dead end and the protection alert, and it is the one Vue itself never
   performs in production.

Prove both by mutation before closing: remove one import and watch each go red.

## 8. Corrections to the record

- `workforce/ops/board/W-0540-*.md` says 79 and describes the wide haystack as if it
  were in use; the guard uses the narrow one and the true count is 152 plus two views.
- `handover/September/04/handover-2026-09-04-session-1.md` and `CSJTODO.md` frame the
  item as "delete the 79, blocked on approval for `git rm`". Withdrawn. The item is a
  per-cluster decision list, and roughly a dozen of the files are a lost feature.
- The May 12 review already knew. It should be cited on the board item so the next
  reader does not rediscover it.

## 9. What was verified, how, and what was not

Verified: the component tree and import graph of `origin/dev` at `41771cca0` are
identical to the working branch; csjones serves `dev` at that commit with a web build
of 2026-09-04 09:24; 151 of 152 dead names are absent from that build and all nine
live controls are present; the walker reaches every known-live layout and view; the
history of every parent deletion is from `git log`; the lint commit diff is quoted from
`git show`.

Not verified: the journey dead end in a browser; whether savings market rates reach a
user through a recommendation; the `UserProfile` statements' replacement; whether the
32 specs assert anything a live component does not already cover. Nothing in this
report was tested by clicking. Every claim above the line is from code, history or the
served bundle.

## 10. Decisions that are CSJ's, in order

1. **The analytics cluster (6.2):** re-mount as an Optimisation detail, or retire the
   claims now and park the capability. Recommendation: retire the claims today, then
   decide on the re-mount as a specified mission.
2. **Financial statements (6.6):** is the balance sheet, cash flow and P and L view
   still a feature? If yes it needs a home; if no, delete the `UserProfile` cluster.
3. **Amortisation schedule and rate benchmarking (6.6):** re-mount or drop.
4. **Approve the superseded deletions (6.1)** on the condition in that section, a
   per-file read before each directory goes, findings to the board first.
5. **Approve the two new board items:** the journey dead end (6.4), and the campaign
   CTA panel (6.3).
6. **Approve the guard change (7).**

Until 1 to 3 are answered, nothing in 6.2, 6.6 or 6.7 should be deleted. 6.1, 6.3 and
6.5 can proceed on approval of 4 and 5.

## Appendix A: the 152 by directory

| Directory | Unreachable | Of which the guard flagged |
|---|---:|---:|
| Actions | 2 | 2 |
| Admin | 6 | 3 |
| Dashboard | 21 | 15 |
| Estate | 17 | 3 |
| Gamification | 1 | 1 |
| Goals | 10 | 8 |
| Insights | 1 | 1 |
| Investment | 40 | 20 |
| Journey | 1 | 1 |
| Legal | 1 | 1 |
| NetWorth | 3 | 2 |
| Onboarding | 3 | 2 |
| Plans | 2 | 2 |
| Preview | 1 | 0 |
| Protection | 8 | 2 |
| Public | 1 | 1 |
| Retirement | 9 | 2 |
| Risk | 3 | 3 |
| Savings | 5 | 3 |
| Shared | 5 | 1 |
| UserProfile | 11 | 6 |
| WhatIf | 1 | 0 |

## Appendix B: the full list

G = flagged by the current guard; W = found only by the import-graph walk.

- G `Actions/ActionSummaryCard.vue`
- G `Actions/RecommendationFilters.vue`
- G `Admin/AdminInvestmentActions.vue`
- G `Admin/AdminProtectionActions.vue`
- G `Admin/AdminRetirementActions.vue`
- W `Admin/InvestmentActionModal.vue`
- W `Admin/ProtectionActionModal.vue`
- W `Admin/RetirementActionModal.vue`
- W `Dashboard/ActionsOverviewCard.vue`
- G `Dashboard/AffordabilityOverviewCard.vue`
- W `Dashboard/AlertsPanel.vue`
- G `Dashboard/AreasToCompleteCard.vue`
- G `Dashboard/AreasToConsiderCard.vue`
- G `Dashboard/CrossModuleInsights.vue`
- W `Dashboard/DashboardCard.vue`
- G `Dashboard/DashboardSparkline.vue`
- W `Dashboard/EmptyDashboard.vue`
- G `Dashboard/GoalsCard.vue`
- G `Dashboard/GoalsOverviewCard.vue`
- G `Dashboard/GoalsProjectionChartDashboard.vue`
- G `Dashboard/GoalsProjectionChartMini.vue`
- G `Dashboard/HouseholdNetWorth.vue`
- G `Dashboard/InvestmentsOverviewCard.vue`
- G `Dashboard/LifeTimelineCard.vue`
- W `Dashboard/NetWorthOverviewCard.vue`
- W `Dashboard/NetWorthSummary.vue`
- G `Dashboard/ProfileCompletionCards.vue`
- G `Dashboard/SpousalOptimisations.vue`
- G `Dashboard/TaxOptimisationCard.vue`
- W `Estate/AssetForm.vue`
- G `Estate/AssetsLiabilities.vue`
- W `Estate/CashFlow.vue`
- W `Estate/CashFlowProjectionChart.vue`
- G `Estate/EstateProjectionComparison.vue`
- W `Estate/GiftCard.vue`
- W `Estate/GiftForm.vue`
- W `Estate/GiftingStrategy.vue`
- W `Estate/IHTLiabilityGauge.vue`
- W `Estate/LifePolicyStrategy.vue`
- W `Estate/NRBRNRBTracker.vue`
- W `Estate/NetWorth.vue`
- W `Estate/NetWorthWaterfallChart.vue`
- G `Estate/PensionAmendmentBanner.vue`
- W `Estate/TrustForm.vue`
- W `Estate/TrustPlanning.vue`
- W `Estate/TrustPlanningStrategy.vue`
- G `Gamification/LevelCard.vue`
- G `Goals/ChartTypeToggle.vue`
- G `Goals/EventIconsOverlay.vue`
- W `Goals/EventTooltip.vue`
- W `Goals/GoalCard.vue`
- G `Goals/GoalContributionStreak.vue`
- G `Goals/GoalCountdown.vue`
- G `Goals/GoalMilestoneTracker.vue`
- G `Goals/GoalsAnalysis.vue`
- G `Goals/GoalsByModule.vue`
- G `Goals/GoalsList.vue`
- G `Insights/InsightCtaPanel.vue`
- W `Investment/AccountStrategyCard.vue`
- W `Investment/AllocationComparison.vue`
- W `Investment/AssetAllocationChart.vue`
- G `Investment/AssetLocationOptimizer.vue`
- W `Investment/BedAndISATransfers.vue`
- G `Investment/BenchmarkComparison.vue`
- W `Investment/BondWrapperInfoModal.vue`
- W `Investment/CGTHarvestingOpportunities.vue`
- G `Investment/ContributionPlanner.vue`
- W `Investment/CorrelationMatrix.vue`
- W `Investment/EfficientFrontier.vue`
- G `Investment/FeeSavingsCalculator.vue`
- W `Investment/GeographicAllocationMap.vue`
- W `Investment/GoalForm.vue`
- G `Investment/GoalProjection.vue`
- W `Investment/ISAOptimizationStrategy.vue`
- W `Investment/InvestmentOverviewCard.vue`
- G `Investment/InvestmentReadinessGate.vue`
- W `Investment/MonteCarloResults.vue`
- G `Investment/PerformanceAttribution.vue`
- G `Investment/PerformanceLineChart.vue`
- G `Investment/PlanSections/ActionPlanSection.vue`
- G `Investment/PlanSections/CurrentSituationSection.vue`
- G `Investment/PlanSections/FeeAnalysisSection.vue`
- G `Investment/PlanSections/GoalProgressSection.vue`
- G `Investment/PlanSections/RecommendationsSection.vue`
- G `Investment/PlanSections/RiskAnalysisSection.vue`
- G `Investment/PlanSections/TaxStrategySection.vue`
- W `Investment/PortfolioOptimization.vue`
- W `Investment/PortfolioOptimizer.vue`
- G `Investment/PortfolioOverview.vue`
- W `Investment/RebalancingActions.vue`
- G `Investment/RebalancingCalculator.vue`
- W `Investment/StrategyRecommendationCard.vue`
- G `Investment/TaxFees.vue`
- W `Investment/TaxOptimization.vue`
- W `Investment/TaxOptimizationOverview.vue`
- W `Investment/TaxOptimizationRecommendations.vue`
- G `Investment/WhatIfScenariosBuilder.vue`
- G `Investment/WrapperOptimizer.vue`
- G `Journey/JourneyProgressHero.vue`
- G `Legal/StrategyDisclaimer.vue`
- W `NetWorth/NetWorthOverview.vue`
- G `NetWorth/Property/AmortizationScheduleView.vue`
- G `NetWorth/Property/PropertyTaxCalculator.vue`
- G `Onboarding/FocusAreaGrid.vue`
- G `Onboarding/JourneyPreview.vue`
- W `Onboarding/ProfileReviewPanel.vue`
- G `Plans/Estate/EstateJointView.vue`
- G `Plans/Investment/AccountFeeProjectionChart.vue`
- W `Preview/KeepDataOrFreshModal.vue`
- W `Protection/CoverageGapChart.vue`
- G `Protection/CoverageTimelineChart.vue`
- W `Protection/GapAnalysis.vue`
- G `Protection/PremiumBreakdownChart.vue`
- W `Protection/ProtectionOverviewCard.vue`
- W `Protection/RecommendationCard.vue`
- W `Protection/ScenarioBuilder.vue`
- W `Protection/WhatIfScenarios.vue`
- G `Public/CalculatorCard.vue`
- W `Retirement/AccumulationChart.vue`
- W `Retirement/AnnualAllowanceTracker.vue`
- W `Retirement/DrawdownSimulator.vue`
- W `Retirement/IncomeProjectionChart.vue`
- G `Retirement/RequiredCapitalDetail.vue`
- G `Retirement/SalarySacrificeDisplay.vue`
- W `Retirement/StrategiesTab.vue`
- W `Retirement/StrategyCard.vue`
- W `Retirement/TaxBreakdownCard.vue`
- G `Risk/CapacityForLossSection.vue`
- G `Risk/RiskFactorsPanel.vue`
- G `Risk/TimeHorizonSection.vue`
- G `Savings/InterestRateComparisonChart.vue`
- G `Savings/MissingDataCard.vue`
- W `Savings/SaveGoalModal.vue`
- G `Savings/SavingsDecisionPath.vue`
- W `Savings/SavingsGoals.vue`
- W `Shared/ISAAllowanceSummary.vue`
- G `Shared/InfoGuideButton.vue`
- W `Shared/InfoTooltip.vue`
- W `Shared/PostcodeLookup.vue`
- W `Shared/ProfileCompletenessAlert.vue`
- G `UserProfile/AssetsOverview.vue`
- G `UserProfile/BalanceSheetTab.vue`
- W `UserProfile/BalanceSheetView.vue`
- G `UserProfile/CashFlowTab.vue`
- W `UserProfile/CashflowView.vue`
- W `UserProfile/DomicileInformation.vue`
- G `UserProfile/IncomeStatementTab.vue`
- G `UserProfile/LiabilitiesOverview.vue`
- W `UserProfile/PersonalAccounts.vue`
- W `UserProfile/ProfitAndLossView.vue`
- G `UserProfile/TaxSummaryCard.vue`
- W `WhatIf/ScenarioDetail.vue`

Unrouted views: `views/Investment/AccountPerformancePanel.vue`, `views/Investment/PortfolioStrategyPanel.vue`.
