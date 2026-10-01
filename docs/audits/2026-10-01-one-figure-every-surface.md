# One figure, every surface: audit (2026-10-01)

**Why:** CSJ 2026-10-01: every account, projection, calculation, API answer and Fyn inference is computed once on the server. Web, `/m`, iOS, cards and Fyn fetch it and render it as sent, so no two surfaces can ever differ. Memory: `feedback_one_figure_every_surface_fetched`.

**How:** three read-only audits, one per group of modules. Each divergence below names the file:line on each side and the one server field that should feed every surface.

**Status:** Retirement, Investment and Savings audited. Protection, Estate, Net Worth and the dashboard, then Tax, Goals, Actions and Fyn, are being audited.

## Retirement

1. **Projected retirement income.** Four answers under one label:
   - web `NetWorth/PensionList.vue:676-686` uses `income_drawdown[0].total_income` (Monte Carlo cautious drawdown) and falls back to the target;
   - web `Retirement/RetirementIncomeTab.vue:533-537` counts the State Pension twice;
   - `/m` `Retirement.vue:349-360` chains planning → analysis → drawdown;
   - iOS `RetirementModels.swift:45-51` chains the same way, without the guaranteed-income rule;
   - the dashboard uses `RetirementAgent` `projected_retirement_income`.

   **One source:** the planning contract (`RetirementProjectionContractService`, plan 2026-08-10: "the primary projection"), as a server-built headline.
2. **Target income.** Each surface has its own fallback order: web `PensionList.vue:637-646`, web `RetirementTargetCard.vue:150-156`, `/m` `Retirement.vue:361-373`, iOS `RetirementModels.swift:52-56`.
   **One source:** `RequiredCapitalCalculator::calculate()` `required_income` and `income_source`.
3. **Guaranteed income.** Web sums it on the client (`PensionList.vue:625-635`), `/m` reads `analysis.guaranteed_annual_income`, iOS does not show it.
   **One source:** `RetirementAgent::guaranteedAnnualIncome()`.
4. **Surplus or shortfall.** Each surface subtracts on the client: web `:165-167`, `/m` `:392-397`, iOS `RetirementModels.swift:57-60`.
   **One source:** a signed gap computed on the server.
5. **Dashboard retirement card.** iOS `FinancePanelsView.swift:91-93` can show `income_gap` as if it were a pot balance.
6. **Dashboard "% of target".** Computed on each client (`dashboardCards.js:71`, `FinancePanelsView.swift:90`).
   **One source:** a server `progress_percent`.
7. **Pension value today.** Summed on each client.
   **One source:** `current_dc_value`.
8. **Years to retirement.** Three different client rules.
9. **Per-pension value at retirement.** Web shows Monte Carlo p20; `/m` and iOS show `planning_projection.products[].projected_value`.
10. **DC monthly contribution.** iOS `RetirementModels.swift:127-137` computes it in the wrong order; web routed `PensionDetail.vue:156-170` computes it from percentages.
    **One source:** the model's `monthly_contribution` append.
11. **Required capital.** Web falls back to target / 0.047 on the client (`PensionList.vue:671-673`, `FutureValueTab.vue:160-164`).
12. **State Pension details.** Weekly amounts are computed on each client. Web has 35 and 67 typed in (`PensionDetailInline.vue:294,298`). The routed web `PensionDetail.vue` reads fields that do not exist.
13. **Drawing view.** iOS does not decode `drawdown_position`.
14. **Defined benefit spouse %.** Web reads `spouse_pension_percentage`, which does not exist (`PensionList.vue:125`).

## Investment

15. **Total portfolio value.**
    - web and `/m` add up `user_share` on the client;
    - iOS adds up the full value of joint accounts;
    - `/api/investment` sends no total.

    **One source:** `CrossModuleAssetAggregator::calculateInvestmentTotal()`.
16. **Account value and share.** Web computes these itself and assumes 50% when `ownership_percentage` is missing (`InvestmentProjections.vue:631-637`, `AccountSummaryPanel.vue:43-44`). iOS shows the full value.
17. **Returns.** Web computes them on the client (`InvestmentProjections.vue:708-752`) and ignores the server's `annualised_return`.
18. **Fees, OCF and allocation.** Web computes these in five components; `/m` and iOS read the `portfolio` contract.
19. **Monthly contribution.** Web reads the estimator; `/m` and iOS read the raw column.
20. **ISA used and remaining.** Web computes these per account and in the store (with a 20000 fallback); `/m` and iOS read `ISATracker`.

## Savings

21. **Total cash.**
    - web adds it up by account type;
    - `/m` adds up user shares;
    - iOS adds up full joint balances.

    **One source:** `analysis.summary.total_savings`, which is already sent.
22. **Emergency fund runway.** `/m` and iOS divide on the client.
    **One source:** `analysis.emergency_fund.runway_months`.
23. **Emergency fund target.** Web uses a client slider; `/m` and iOS read the controller's figure, built from the raw column; the dashboard has a target of 6 typed in.
24. **Interest.** Web and iOS compute it on the client.
    **One source:** `annual_interest` and `monthly_interest`.

## Net Worth, dashboard, Protection, Estate

25. **Net worth: three server engines.**
    - `MobileDashboardAggregator::calculateNetWorth` (`:360-470`; it adds cash accounts and counts liabilities at full balance, including mortgage-type rows);
    - `NetWorthService::calculateNetWorth` (`/api/net-worth/overview`; liabilities at the user's share);
    - `Estate\NetWorthAnalyzer::calculateNetWorth` (`/api/estate/net-worth`).

    Web `WealthSummary.vue:251` also re-derives net worth as assets − liabilities.
    **One source:** `NetWorthService`.
26. **Total owed.** Web sums `/api/estate` liabilities on the client (`LiabilitiesList.vue:203-206`); `/m` reads `assets-summary-detailed.liabilities.total_value` (full balance); iOS reads overview `total_liabilities` (the user's share).
    **One source:** `NetWorthService`'s share-correct liabilities total.
27. **Dashboard retirement value.** iOS never decodes `guaranteed_income` and shows £0 where web and `/m` show an income a year.
28. **Equity.** The list rows compute the user's equity on the client; the detail screens show the server's `equity`, which is the full value less the full mortgages (its docblock is wrong).
    **One source:** server `user_equity` and `full_equity`.
29. **Ownership share shown to a joint owner.** iOS prints the stored `ownership_percentage` for a business, which is the primary owner's share. Web labels the property share with the primary owner's percentage.
    **One source:** server `user_share_percent`.
30. **Mortgage on the property detail.** Web multiplies by the property's ownership percentage and ignores `mortgage_user_share`; `/m` and iOS read the stored `outstanding_mortgage` column, which can be 0.
    **One source:** `mortgage_user_share`.
31. **Annual premium.** Web and iOS re-annualise on the client, treating weekly as ×12; `/m` reads the server's `annual_premium` (weekly ×52).
32. **Protection cover.** The dashboard reads `total_coverage`; the module reads `coverage_gaps.totals.cover`. iOS also falls back to `/analyze` and a client-built gap list, and does not decode `cover_position`.
33. **Estate value.** `/m` and iOS read `NetWorthAnalyzer` (the third engine), with a client fallback; web reads the Inheritance Tax engine's `net_estate`.
34. **Projected Inheritance Tax (married), on one web screen.** The tile is from the server; the table's projected, −5 and +5 year columns are worked out on the client (4.7% growth typed in, and `nrb_from_spouse || 325000` can grant a nil rate band the server did not).
35. **Dashboard rings.** Web and `/m` compute them on the client; iOS has 0.72 and 0.85 typed in and a "Trend" that is always 0. The emergency fund target is typed in as 6, although the server sends it.
36. **Web Inheritance Tax blocks that can never show.** They read `ihtData.iht_liability`, which is never set, with `|| 500000` typed in (`IHTPlanning.vue:627-851`).
37. **Gifts.** Web filters gifts by date and sums them on the client; `/m` and iOS show a count; the gifting strategy shows a third liability figure.

## Tax Strategy, allowances, Goals, Actions and Fyn

38. **ISA allowance used: five server rules.**
    - `TaxStrategyMath::estimateIsaSubscriptionsThisYear` (Tax Strategy tile, plan items, how-tos, thresholds);
    - `ISATracker` (Savings on `/m` and iOS, the Fyn pointer and savings tool);
    - `InvestmentAgent.php:186-208` (Fyn's investment tool);
    - `TaxOptimizationAnalyzer.php:201-230` (web tax efficiency);
    - the web `ISAAllowanceTracker.vue:142-203` client re-derivation.

    They differ on the ledger, the tax-year filter, the balance fallback, the Lifetime ISA, and whether the allowance comes from a stored row or live config.
    **One source:** `ISATracker`.
39. **Web tax headline.** It falls back to a client sum of a different list (`TaxYearHeader.vue:40-55`).
40. **Web headroom.** It adds up allowances that cannot be added (`AllowanceGrid.vue:79-81`); `/m` and iOS show a count.
41. **iOS Tax Strategy.** It does not decode `counted_in_total`, `conflict_note` or `affordable_this_year`, so a budget-capped pension tile reads "Fully used".
42. **Annual Allowance taper: three rules.** `TaxStrategyMath:284-310`, `IncomeDefinitionsService:381-388`, and `AnnualAllowanceChecker:182-196` (which has `/2` typed in and no floor).
43. **Personal Allowance: two rules** (`TaxStrategyMath:231-273`, `IncomeDefinitionsService:371-378`). Fyn's tax band ignores the taper (`AdvicePromptBuilder:1347-1373`).
44. **Fyn income.** Fyn reads a raw sum of the user columns (`ResolvesIncome`); the screens read `IncomeDefinitionsService`.
45. **Retirement projected income: four sources, Fyn included.** Fixed by `RetirementHeadline`; `RetirementAgent`'s summary now reads it.
46. **Net worth.** Fyn reads uncached `NetWorthService`; the pages read the cached one; the dashboard reads its own engine.
47. **Goal status label.** iOS and Fyn compute their own; web and `/m` read `status_label`.
48. **Goal progress and amount remaining.** Web and Fyn re-derive them.
49. **Life event totals.** Computed on the client while the server sends `summary`. Fyn's "upcoming" list includes events that have already happened (W-0207).
50. **Fyn's ranked recommendations.** They come from a different pipeline from the actions list (`CoordinatingAgent::orchestrateAnalysis` against `RecommendationsAggregatorService`).
