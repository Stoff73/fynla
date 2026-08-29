---
id: W-0521
title: Eight services inject TaxConfigService and never use it
mission: null
branch: chore/w-0521-remove-dead-taxconfig-injections
owner: null
reviewers: [tax-compliance-reviewer]
status: in_progress
claimed_by: null
severity: low
surfaces: [web, m, ios]
created: 2026-08-29T11:00:00Z
claimed: 2026-08-29T11:00:00Z
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0520, W-0501]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: CSJ, 2026-08-29 — the TaxConfigService cluster from the W-0520 sweep
---

## Intent

Eight services take `TaxConfigService` in their constructor and never reference it. The
worry — CSJ's, and the right one — is **Rule 2**: a service that asked for the tax
configuration and does not use it may be getting its tax values from somewhere else.

**It is not. Checked, and stated so the question does not have to be asked again.**

Two groups, established by walking each file's history for the last commit whose blob
referenced `$this->taxConfig`:

### Used once, then legitimately consolidated away (4)

Each stopped deriving a tax figure by hand and started reading the engine that owns it.
The injection was left behind by the consolidation; **no capability was lost.**

| service | what it did | what replaced it |
|---|---|---|
| `EstateActionDefinitionService` | read `nil_rate_band` / `residence_nil_rate_band` / `standard_rate` and estimated the estate by hand | **W-0501** — calls `IHTCalculationService::calculate()` and reads `iht_liability` |
| `EstateIhtExposureDetector` | same three values, plus its own NRB+RNRB threshold test | calls the calculator; "exposed" is now the engine's own answer rather than a second threshold that could disagree with the figure printed beside it |
| `GoalAssignmentService` | `getStampDuty()` bands inside its own `calculateSDLT()` | reads `total_sdlt` from the SDLT calculator |
| `InvestmentPlanService` | `getPensionAllowances()` and `getGiftingExemptions()` for surplus recommendations | `b3b04e2cc` moved recommendations to DB-driven action definitions |

### Never used at all (4)

`LifePolicyStrategyService`, `SavingsDataReadinessService`, `RetirementPlanService`,
`SavingsPlanService`. Each was checked for a tax question answered by other means:

- **`LifePolicyStrategyService`** takes the Inheritance Tax liability as its
  `$coverAmount` argument; every "IHT" in the file is descriptive copy. Its numeric
  literals are premium tables and a joint-life discount, not tax.
- **`SavingsDataReadinessService`** asks no tax question. `TaxConfigService` was its ONLY
  constructor argument; the empty constructor goes with it. It carries `ResolvesIncome`,
  which resolves the configuration itself where it is genuinely needed.
- **`RetirementPlanService`** reads `$data['annual_allowance']` from data handed to it.
- **`SavingsPlanService`** contains no tax arithmetic.

**No hardcoded tax values were found in any of the eight** — a scan for nil-rate-band,
personal-allowance, band-threshold and rate literals came back empty. Rule 2 holds.

## Resolution — 2026-08-29

All eight injections removed. 19 lines deleted, no behaviour touched. None of the eight is
constructed by hand anywhere in `app/`, `tests/` or `database/`, so the container resolves
them all and no call site changes.

**Verification.** 868 passed across Estate, Savings, Goals, Plans and Architecture.

## Also fixed here — a red test on `dev`

`tests/Feature/Goals/SharedGoalIsOneWholeGoalTest` was **failing on clean `dev`** before
this branch, reporting `create_goal is missing is_essential` from the Fyn tool catalogue.

**The catalogue is fine and the capability works.** `AiToolDefinitions::getTools()` returns
the flat `{name, description, parameters}` shape; the test read only
`$tool['function']['parameters']['properties']` and `$tool['input_schema']['properties']`,
found neither, and reported an empty property list as missing fields. Probed live: the tool
carries `name, target_amount, target_date, is_essential, ownership_type, joint_owner_id,
priority, goal_type, monthly_contribution`.

A **Decoy** in the `test-failure-forensics` sense — it inspects a shape the code does not
produce, so it can fail for a reason that has nothing to do with its subject. The fallback
chain now covers all three shapes, so it fails when the FIELDS are missing rather than when
the wrapper differs.

Fixed rather than reported because a red test on `dev` blocks everyone and the change is
one expression in a test file.

## Reported, not fixed

- **52 more unused private injections remain** from the W-0520 sweep, outside the
  `TaxConfigService` cluster. Highest-signal: `RetirementAgent` (6), `GoalsAgent` (3),
  `ComprehensiveProtectionPlanService` (3), `EstateActionDefinitionService` (`MortgageStore`,
  `PropertyStore` — orphaned by the same W-0501 change as its `TaxConfigService`).
- **`0.047` as a fallback investment return appears in at least three places** —
  `EstateProjectionService::getFallbackGrowthRate()`, `LifeCoverCalculator`, and
  `LifePolicyStrategyService::FALLBACK_INVESTMENT_RETURN_RATE`. Not a tax value, so not
  Rule 2, but it is one assumption with three homes.
