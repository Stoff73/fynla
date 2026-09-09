# Fyn wiring Batch G — evidence (F6 re-wire, F26, F3 phantom keys, Rule 12 label)

Branch `fix/fyn-wiring-batch-g` off dev `978d9a3c5`, 9 September 2026. CSJ (09:30): re-wire the consent gate, "Nope" → "No thanks"; reduce David's contribution; fix the silent 404; wire in the ten phantom triggers; replace the grade with months of runway.

| Item | Change | Evidence |
|---|---|---|
| F6 | `profile_review_expenditure` state, constant, helper and corpus entry deleted; `campaign_advice_income` routes SaveTax users to `campaign_intro`, PensionCheck straight on; `nextFromCampaignIntro` Okay → `nextCampaignSection('income')`; bubble label "No thanks"; director label, BS-04/26/27 docblocks and four test files updated; new test for the income→gate routing | Tinker: `getNextStateId('campaign_advice_income')` → `campaign_intro`; bubbles `["Okay","No thanks"]`; Okay → `campaign_dob` for a user with no funnel answers; No thanks → `done`. **Live `/m`** (user 73 at the gate): "Thanks Fay for that information. Now, in order to personalise your tax strategy … I'd like to ask about your pensions, accounts and investments is that okay?" with Okay / No thanks; tapped No thanks → `onboarding_completed = true`, step and campaign cleared |
| F26 David | `peak_earners.json` Cash ISA `monthly_contribution` 833 → 250 (David and Sarah); `PreviewUserSeeder` re-run | The regular-saver rule still fires, now at "£250 a month goes into David's Cash ISA", within regular saver caps |
| F26 404 | `routes/api.php`: `savings` added to the four `/plans/{type}` constraints (`PlanController` already mapped it) | `GET /api/plans/savings` as John → 200 `success: true`; `SavingsPlanRouteTest` |
| F26 sheet | Explained, not changed: the open Fyn panel is a full-height overlay; taps beneath it land on the panel | — |
| F3 phantoms | Nine aliased to existing rules, one (`tax_free_lump_sum`) folded into `approaching_decumulation`, which already computes the PCLS capped at the Lump Sum Allowance from tax config; `RelevantTriggersAreSeededTest` guards every list | Test green |
| Rule 12 label | `SavingsAgent` emits `runway_months` + `target_months`, no `category`; `EmergencyFundCalculator::categorizeAdequacy` deleted; aggregator raw block, `InvestmentPlanService`, `MobileDashboardAggregator` (`emergency_fund_target_months`), `HolisticSavingsSituation.vue`, `InvestmentCurrentSituation.vue`, `planPrintMixin.js`, `/m` `ModuleDetail.vue` read months | `GET /api/plans/investment` as John → `emergency_fund: {runway_months: 0, target_months: 6, total_savings: 0}`; `/m` module detail below |

## Tests

Batch G families (onboarding unit + feature, savings, agents, coordination, plans, mobile unit + feature, Fyn, savings feature, dispatch coverage, constants, the two new tests): 1,878 passed, 2 skipped, 7,060 assertions with ten fixture failures, all in `SavingsAgentTest` and `RecommendationsAggregatorServiceTest` asserting the deleted grade; fixtures moved to runway months and both files rerun: 23 passed. Vitest around the changed views and the `/m` module detail: 21 passed.
