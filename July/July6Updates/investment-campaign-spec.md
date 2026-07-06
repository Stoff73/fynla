# Investment Campaign (Campaign #3) — Spec

*2026-07-06. The design spec for the investment campaign, cloning the savetax/pensioncheck machinery exactly. File:method-grounded against dev `9c9e7d2` (post-#612). Written to be implemented with NO further design decisions — CSJ-blessing items are marked DRAFT. Companion docs: `investment-campaign-plan.md` (the build plan — read AFTER this), `July/July3Updates/campaign-playbook.md` (F1–F15 — BINDING), `July/July6Updates/pensionCampaign.md` + `saveTax.md` (current-state maps), `estate-campaign-spec.md` (campaign #4 sibling — build order is CSJ's call; the two campaigns are independent).*

**Working campaign id / URL: `investmentcheck` → `/investmentcheck`** (≤32 chars). **DRAFT — CSJ confirms name/URL at funnel-build time.**

**State prefix: `campaign3_*`.**

**Guiding principle: minimal questions, maximum value.** §6a maps every question to the advice it unlocks.

---

## 1. The one design principle

One investment section walk, data-presence skip predicates — same walk for new users and savetax/pensioncheck graduates; the delta IS the skip logic (the proven `campaignSections('pensioncheck')` pattern).

## 2. What the engine needs (readiness × strategies)

**Readiness** — `app/Services/Investment/Recommendation/DataReadinessService` (aliased `InvestmentDataReadinessService`; gate: `PrerequisiteGateService::canAnalyseInvestment`, :93):
- **BLOCKING (all four)**: `date_of_birth`; `income` (sum of the 7 `users.annual_*_income` fields > 0); `risk_profile` (`risk_profiles` row exists); `expenditure` (`ExpenditureProfile.total_monthly_expenditure > 0` OR legacy `users.monthly_expenditure`/`annual_expenditure > 0`).
- **WARNING**: `employment_status`, `protection_profile`, `pension_data`, `investment_accounts`. **INFO**: spouse/life events/savings/goals.

**The income + expenditure sections are therefore IN the walk** (unlike the estate campaign) — the composed plan cannot generate without them.

**Risk profile is NOT asked** — it auto-derives: `InvestmentAccountRiskObserver` (+ siblings on User/SavingsAccount/DCPension/FamilyMember/Property/LifeEvent, `EventServiceProvider:81-85`) dispatches `RecalculateRiskProfileJob` (**queued**, 5s debounce) → `RiskPreferenceService::calculateAndSetRiskLevel()` → `AutoRiskCalculator` (9 factors) → `RiskProfile::updateOrCreate(['user_id'], [..., 'is_self_assessed'=>false])`. No Fyn tool captures risk appetite and none is added. **TRAP (spec-level mitigation, binding on the plan):** because the job is queued, the walk's synthesis can run before the row exists → the BLOCKING `risk_profile` check fails → empty composed plan → degrade line. The section-advice/synthesis path for `investmentcheck` MUST synchronously ensure the profile: `if (! RiskProfile::where('user_id',$user->id)->exists()) { app(RiskPreferenceService::class)->calculateAndSetRiskLevel($user); }` before composing (idempotent; mirrors the pensioncheck cache-bust lesson).

**Strategy vocabulary** — `InvestmentStrategySource` (`moduleKey()='investment'`) → `InvestmentAgent::analyze` → `InvestmentRecommendationAdapter` (item `type` = the rec's `definition_key`). Catalogue strategy rows (`InvestmentActionDefinitionSeeder`): `rebalance_to_target` (mechanical; locks on `gia_holdings`+`risk_profile_set`), `reduce_platform_fees` (mechanical; locks on `gia_holdings`), `set_risk_profile` (judgement; locks on `risk_profile_set`; do_before rebalance). Agent keys that can fire as items: `risk_profile_missing`, `no_holdings`, `low_diversification` (score<70), `high_total_fees` (>1.0%), `high_fund_fees` (OCF>0.5%), `high_platform_fees` (>0.8%), `rebalance_portfolio`, `tax_loss_harvesting`, `open_isa` (GIA-no-ISA), `use_isa_allowance`, `consider_bonds` (GIA>£50k no bonds), `isa_allowance_remaining`, `surplus_to_isa`/`surplus_to_pension`/`surplus_to_bond`, `emergency_fund_*`, `switch_savings_rate`. Availability map (`ModuleAvailabilityProvider:52-55`): `gia_holdings` = any non-ISA account owned primary; `risk_profile_set` = risk_profiles row.

Investment items are lump-led (`requiredMonthlyCost` null; `estimated_impact` → `requiredLumpSum`) — **the synthesis's "£X a year" suffix logic degrades naturally, as with pensioncheck.**

## 3. The data delta

| Engine need | Savetax graduate has it? | Campaign action |
|---|---|---|
| DOB (BLOCKING) | YES | Skip |
| Income (BLOCKING) | YES | Skip (`skipSectionIfIncomeKnown` — captured-income columns, NEVER `employment_status`) |
| Expenditure (BLOCKING) | YES | Skip (`skipSectionIfExpenditureKnown`) |
| Risk profile (BLOCKING) | Usually YES (observers fire on their captures) | Auto-derived; synchronous ensure at advice/synthesis (§2) |
| ISA accounts + this-year subscriptions | PARTIAL (savetax captures ISAs; subscriptions asked since #585) | Skip if ISA rows exist; else ask |
| GIA/platform accounts + values | PARTIAL | Skip if non-ISA investment rows exist; else ask |
| **Holdings** (unlock `rebalance_to_target`, diversification, OCF) | **NO — savetax never asks holdings** | **Backfill loop (the `campaign2_pension_pots` pattern): every account with 0 holdings gets asked** |
| Platform/fund fees | NO | Volunteered only (the holdings prompt invites them); fee triggers fire when data exists — fine for v1 |

**Net delta for a savetax graduate: ~1–3 questions** (holdings backfill per account, possibly ISA subscription top-up). A cold new user gets ~6–8 (income, ISA, GIA, holdings, expenditure).

## 4. End-to-end flow

```
NEW USER                                          EXISTING USER
Homepage "Is your money working as hard            Plan-page "Already with Fynla? Log in" →
as you are?" card (DRAFT)                            /login?redirect=/dashboard?openFyn=journey
  → FUNNEL /investmentcheck (6 questions, §7)          &from=investmentcheck
  → PLAN /investmentcheck/plan                       → onboarding/start (from=investmentcheck)
      InvestmentEstimateService: projected               conditional 409 BYPASSED → active_campaign
      portfolio £X by State Pension age                  stamped → step=campaign3_existing_recap
  → register (+funnel_answers) → verify              → "Welcome back… Is that all still right?"
  → dashboard?openFyn=journey&from=investmentcheck   → [Yes] → DELTA WALK
  → onboarding/start → entry base_work (income-first — BLOCKING income)
                BOTH → SAME walk: income → isa → investments → holdings → expenditure
  → per section: capture → verify announce → navigate → pills → confirm → advice → next
  → SYNTHESIS "Here's your investment plan…" (composed INVESTMENT plan; risk ensure first)
  → TERMINAL campaign3_terminal "We've built your investment picture, {first_name}."
      [Take me to my investment plan → /investment]
      (web /investment native — router/index.js:864; /m /investment native — router.js:55;
       ALREADY in ONBOARDING_NAV_ROUTES — no allowlist change needed)
  → POST-CAMPAIGN applyCampaignAffinity('investmentcheck' → 'investment')
```

## 5. The section walk

`CAMPAIGN_SECTION_ORDERS['investmentcheck'] = ['income', 'isa', 'investments', 'holdings', 'expenditure']` — no spouse section (spouse allowances are savetax territory; the investment engine's INFO-only spouse data never gates).

**Section map (`campaignSections('investmentcheck')`):**

| section | entry state | whole-section skip |
|---|---|---|
| income | `base_employment` (reuse) | `skipSectionIfIncomeKnown` (REUSE — the pensioncheck predicate, keyed on captured income columns) |
| isa | `campaign3_isa` | `skipSectionIfIsaKnown` — any savings_accounts `is_isa` row OR `InvestmentAccount::isa()` row exists |
| investments | `campaign3_investments` | `skipSectionIfInvestmentsKnown` — any non-ISA `investment_accounts` row exists (`InvestmentAccountStore::forUserPrimaryOnly` filtered) |
| holdings | `campaign3_holdings` | `skipIfNoHoldingsToFill` — no investment account with zero holdings (entry-skip, the `skipIfNoPensionPotToFill` pattern) |
| expenditure | `base_expenditure` (reuse) | `skipSectionIfExpenditureKnown` (REUSE) |

### Per-state table (copy DRAFT; F1/F9/F15 binding)

**`base_work` / `base_employment` / `base_employment_more`** — reused verbatim, income section identical to pensioncheck's. Turn-1 funnel recap via `buildInvestmentcheckFunnelRecapPrompt` (§7.3) from `buildWorkPrompt`'s campaign branch. Goal line: `"…to get a clear picture of your investments I just need a few more details — this usually takes about {3 + extras} minutes."` The S1 fix (#612) already handles retired/not-working campaign users generically.

**`campaign3_isa`** (NEW; delegated):
- Prompt: `"Let's start with ISAs. **Do you have a Cash ISA or Stocks & Shares ISA? For each, what's the balance — and how much have you put in this tax year?** The this-year figure matters: it counts against your £{isa_allowance} allowance."` (allowance interpolated via `getISAAllowances()['annual_allowance']` — callable prompt, Rule 2).
- Tools: `toolsForFocus('investmentcheck')` (§8). `create_savings_account` (cash ISA — `is_isa`, `isa_subscription_amount`) / `create_investment_account` (S&S ISA — `account_type stocks_shares_isa`, `isa_subscription_current_year`).
- `advance_on_answered_question: true` ("no ISAs" advances).
- Next: `enterCampaignVerify($user, 'isa')`.

**`campaign3_investments`** (NEW; delegated):
- Prompt: `"Now everything outside ISAs. **Any General Investment Accounts, share-dealing platforms, or company share schemes? For each: provider, current value, and roughly what you pay in each month.** If you know the platform fee, tell me that too."`
- Tools: `create_investment_account` (GIA → `personal_investment_account`; the schema's enum covers VCT/EIS/SAYE/RSU etc.), `create_holding`.
- `advance_on_answered_question: true`.
- `record_context: 'investments'` — appendix lists existing accounts with `entity_type: investment_account, entity_id` so updates target rows (plan defines the arm; the Director's `verifyEditRecordContext('investments')` already renders this shape).
- Next: `enterCampaignVerify($user, 'investments')`.

**`campaign3_holdings`** (NEW; delegated, looping — clone of `campaign2_pension_pots`):
- Prompt builder `buildHoldingsPrompt`: names the account — `"**What's inside your {account_name}?** Rough split is fine — for example \"60% global equity fund, 30% bonds, 10% cash\". If you know a fund's ongoing charge, mention it."` (first account with zero holdings via a new narrow reader `InvestmentAccountStore::firstAccountMissingHoldings($user)` — StoreBoundary rule: add the reader, never query the model from the state machine).
- Entry skip: `skipIfNoHoldingsToFill`.
- Tool: `create_holding` (schema: "MAY call multiple times in the same turn"; `account_name` must match — the record-context appendix supplies the exact names).
- **Loop/exit** (`nextFromHoldings`, the `nextFromPensionPots` clone): another account still empty → self; all covered → advance; don't-know tokens (`"not sure"`, `"don't know"`, `"skip"`, `"no idea"`) → advance (never traps).
- Next (on exit): `enterCampaignVerify($user, 'holdings')`.

**`base_expenditure`** — reused verbatim (free_text, `skipIfExpenditureSet`); campaign closure already routes `enterCampaignVerify($user,'expenditure')` on the campaign path.

**`campaign3_existing_recap`** (NEW; bubbles) — clone of `campaign2_existing_recap`; REUSE the generic `nextFromExistingRecap` + `firstCampaignSection` (selection-driven). Builder `buildInvestmentRecapPrompt` — deterministic reads: income line; one line per investment account (`"{name} at {provider} — £X"` via `InvestmentAccountStore::forUserPrimaryOnly`), ISA-subscription line for the current tax year (via `ISATracker::getISAAllowanceStatus` totals — `"£X of your £20,000 ISA allowance used this year"`), risk level line if a profile exists (`"Risk profile: {humanised level}"`). Lead: `"Welcome back, {firstName}. Let's take a proper look at your investments. Here's what I already have from you:"` → `**Is that all still right?**`.

**Advice states**: `campaign3_advice_isa`, `campaign3_advice_investments`, `campaign3_advice_holdings` — `turn_type 'advice'`, `next` = callable-string (PR #504 law). Income/expenditure sections have no investmentcheck advice (silent null).

**`campaign3_terminal`** — `"We've built your investment picture, {first_name}."`, `navigate_to: '/investment'`, next done. `terminalNavigationBubble` arm: `['view_investment', 'Take me to my investment plan', '/investment']`.

### Verify config (`campaignVerifyConfig('investmentcheck')`)

| section | route | entry |
|---|---|---|
| income | `/income` | `base_employment` |
| isa | `/savings` (cash ISAs live there; S&S ISAs also show on /investment — savings chosen because the savetax `sectionLabel` machinery already labels mixed ISA holdings) | `campaign3_isa` |
| investments | `/investment` | `campaign3_investments` |
| holdings | `/investment` | `campaign3_holdings` |
| expenditure | `/expenditure` | `base_expenditure` |

All five routes are ALREADY in `ONBOARDING_NAV_ROUTES` — no mixin change. `/m` Investment.vue refetches on mount AND watches `store.screenRefreshTick` — verify-edit-on-same-screen refresh works out of the box.

## 6. Advice + synthesis

`INVESTMENTCHECK_SECTION_STRATEGY_TYPES` (clone of `PENSIONCHECK_SECTION_STRATEGY_TYPES`; item `type` = `definition_key`, §2 vocabulary):

```php
'isa'         => ['open_isa', 'use_isa_allowance', 'isa_allowance_remaining', 'surplus_to_isa'],
'investments' => ['set_risk_profile', 'risk_profile_missing', 'reduce_platform_fees',
                  'high_total_fees', 'high_platform_fees', 'high_fund_fees', 'consider_bonds'],
'holdings'    => ['no_holdings', 'rebalance_to_target', 'rebalance_portfolio',
                  'low_diversification', 'tax_loss_harvesting'],
// income + expenditure absent — silent (the dea2b8a cross-campaign-leak law:
// non-mapped sections return null; no investmentcheck path reaches tax builders)
```

`buildInvestmentSectionAdvice` — clone of `buildRetirementSectionAdvice` (:1042): **risk-profile synchronous ensure first (§2)**, then `ComposedModulePlanService::forSource(app(InvestmentStrategySource::class), $user)`, ≤2 items, claim-tier prefix, "I've added this to your actions list to come back to later."

**Synthesis**: `buildSynthesisAdvice` gains the `investmentcheck` arm — risk ensure, composed INVESTMENT plan, lead-in `"Here's your investment plan, built from what you told me — in the order I'd tackle it:"`, F4 bullets, FCA signpost, degrade `"That's your investment details saved, {firstName}. You'll find your full investment picture on the next screen."`

### 6a. Why each question earns its place

| Question | Advice it unlocks |
|---|---|
| Income (reused section) | BLOCKING readiness; surplus waterfall (`surplus_to_isa/…`); dividend-tax framing |
| ISA balances + this-year subscriptions | `use_isa_allowance`, `isa_allowance_remaining`, `open_isa`, the `isa_used` 50%/100% milestones; ISATracker figures |
| GIA/platform accounts + values + fees | `gia_holdings` availability → unlocks `rebalance_to_target` + `reduce_platform_fees`; the three fee triggers; `consider_bonds`; `tax_loss_harvesting` |
| Holdings backfill | `no_holdings` clears; diversification + OCF + rebalancing triggers get real data — without holdings the two mechanical strategies stay locked |
| Expenditure (reused section) | BLOCKING readiness; surplus calculation behind every "invest £X more" suggestion |

**Deliberately NOT asked** (v1): stated risk appetite (auto-derived; `set_risk_profile` advice sends users to the module's risk page for the self-assessed version), per-fund OCF interrogation (volunteered only), spouse assets, pensions (pensioncheck territory), emergency fund (the `emergency_fund_*` triggers fire from savings data savetax already captured).

## 7. Funnel + plan page

### 7.1 The six questions (clone the pensioncheck page pair; copy DRAFT)

| # | Key | Question | Options |
|---|---|---|---|
| 1 | `employment` | "What is your employment status?" | as pensioncheck (5 options) |
| 2 | `income` | "What is your annual income?" | the 4 standard bands (`upto_50270`…`over_125140`) |
| 3 | `age` | "How old are you?" | as pensioncheck (`under_30`…`60_plus`) |
| 4 | `invested` | "What do you have today?" (multi) | `cash_savings` ("Just cash savings"), `ss_isa` ("A Stocks & Shares ISA"), `gia` ("A general investment or share-dealing account"), `none` ("Nothing invested yet" — exclusive) |
| 5 | `monthly` | "How much could you put away each month?" | `nothing`, `under_100`, `100_500`, `over_500` |
| 6 | `spouse` | "Do you have a spouse?" | `yes` / `no` |

Answers object: `{ campaign: 'investmentcheck', employment: null, income: null, age: null, invested: [], monthly: null, spouse: null }` — stamp baked in; localStorage `investmentcheck_answers`; utm capture block (S6 pattern).

### 7.2 `InvestmentEstimateService` (app/Services/Marketing/)

Clone `PensionEstimateService` exactly (single `estimate(array): array`; banded midpoints as marketing consts; TaxConfigService only):
- Horizon: years to State Pension age — `getPensionAllowances()['state_pension']['future_spa']` (fallback 67), age midpoints as pensioncheck.
- Present value: NOT asked as a band (question 4 is types-held) — assume 0 present value and project CONTRIBUTIONS ONLY: monthly midpoints `nothing`→0, `under_100`→50, `100_500`→300, `over_500`→750. Growth `REAL_GROWTH_RATE = 0.025` (same conservative figure; same `projectPot` future-value maths — lift the private method wholesale).
- `monthly === 'nothing'` → headline flips to the allowance frame: "You have £{isa_allowance} of tax-free ISA allowance this year — see what using even part of it could do" (plan page alternate hero, the estate zero-case pattern).
- `tax_note` (the `taxReliefNote` analog): GIA-held (`invested` includes `gia`) + higher-rate income → dividend-tax note quoting `dividend_tax.allowance` and the ISA shelter; else the plain "inside an ISA, growth and income stay tax-free" line with `getISAAllowances()['annual_allowance']`.
- Returns `{projected_value, years_to_projection, monthly_assumed, isa_allowance, tax_note, nothing_invested}` — hex-flagged injection `window.INVESTMENTCHECK_ESTIMATE`, `esc()` before `innerHTML`, query values never echoed.

### 7.3 Registration pull-through

- `RegisterRequest` keys: `invested` array + `invested.*` max:30, `monthly` max:20 (`age`, `employment`, `income`, `spouse` already validated). `prepareForValidation` accepts the campaign automatically once the campaign_map entry exists.
- `FunnelAnswersMapper`: `employment` + `spouse` branches fire as today; nothing new.
- Funnel recap: `buildInvestmentcheckFunnelRecapPrompt` — bullets employment/income band/spouse/held types (`$investedMap`: `cash_savings` → "cash savings", `ss_isa` → "a Stocks & Shares ISA", `gia` → "a general investment account"), F12 estimate 3 min + 1 per held type beyond the first. Fired from `buildWorkPrompt`'s campaign branch (entry `base_work` — the same seam pensioncheck uses; the builder is selected by `onboarding_fyn_selection`).
- **`funnelHasAnyAsset` reads `funnel_answers['assets']` — investmentcheck produces `invested`, NOT `assets`. NO state or gate may call `funnelHasAnyAsset` for this campaign** (the pensioncheck `nextFromCampaignDob` lesson — the walk uses data-presence skips exclusively).

## 8. Config + seam wiring

| Seam | Entry |
|---|---|
| `campaign_map` | `'investmentcheck' => ['selection' => 'investmentcheck', 'entry' => 'base_work', 'reentry' => true, 'reentry_entry' => 'campaign3_existing_recap']` |
| `RedirectPhoneToMobile::CAMPAIGN_PREFIXES` | `'investmentcheck'` (P1 lesson) |
| `NextActionsService::CAMPAIGN_AFFINITY` | `'investmentcheck' => 'investment'` (`moduleRoute` already maps `'investment' => '/investment'`) |
| `toolsForFocus` | new arm `'investmentcheck' => ['create_investment_account', 'create_holding', 'create_savings_account']` (+ universal `update_profile`/`update_record`). The existing `'investment'` arm (create_investment_account, create_holding) is advice-mode surface — do NOT modify |
| `ONBOARDING_NAV_ROUTES` | no change (all five verify routes present) |
| `sectionLabel` | `'isa' => 'ISA accounts', 'investments' => 'investment accounts', 'holdings' => 'holdings'` |
| `describeStep` | one label per campaign3 state (S3 lesson) |
| `terminalNavigationBubble` | `'/investment' => ['view_investment', 'Take me to my investment plan', '/investment']` |
| Homepage card | `feature-investmentcheck` — failure-tolerant try/catch; representative persona (`full-time`, `upto_50270`, `40s`, `['ss_isa']`, `100_500`, `no`); headline DRAFT "Is your money working as hard as you are?" CTA "Check my investments" |
| Milestones | `isa_first`, `isa_used` (50/100%), `module_profile` (investment=4) ALREADY SHIPPED — E2E verifies mints. **Known gap (deferred, not in scope): `detectStrategyFirst` only fires for `tax_`-prefixed recommendation ids — completing an investment-module action mints no first-action milestone. Record in the patch notes; do not extend the map unprompted** |
| New tools | **NONE** — `create_investment_account` + `create_holding` cover the whole surface (both have `.xai.md`, both in `WRITE_TOOLS` + `captureToolSet`) |

## 9. Decisions (locked by symmetry — CSJ may override before build)

- **D1 — Re-entry: enabled** (recap entry, generic substrate).
- **D2 — Headline: projected portfolio by State Pension age from monthly contributions** (nothing-invested case flips to the ISA-allowance frame). Name/URL `investmentcheck` DRAFT.
- **D3 — Walk scope: investment-lean + the two BLOCKING reused sections** (income, expenditure). No spouse section, no stated-risk question (auto-derived + synchronous ensure).
- **D4 — Landing: existing `/investment` screen** (native web + /m; free-tier cap nudge shows at 2 accounts — the freemium surface, by design).
- **D5 — Zero new tools; one new store reader** (`firstAccountMissingHoldings`).

## 10. Build inventory → see `investment-campaign-plan.md`

Slices A–D as pensioncheck. The plan carries the full 30-item trap list — read it before writing any code.
