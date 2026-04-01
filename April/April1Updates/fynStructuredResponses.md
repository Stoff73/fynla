# Fyn AI Structured Responses — FCA 6-Step Financial Planning Process

**Branch:** `fynImprovement`
**Date:** 1 April 2026
**Status:** Plan — pending approval

---

## The Problem

Fyn currently generates free-form text responses. The quality depends entirely on the AI model following prompt instructions. This leads to:
- Inconsistent advice (sometimes mentions irrelevant concepts, sometimes misses critical ones)
- No guaranteed data checks before giving advice
- No KYC compliance — Fyn gives pension advice without checking if income data exists
- No structured format — responses vary wildly in structure and completeness
- Decision tree logic is invisible to the AI — it only sees top 5 recommendations, not the reasoning

## The Solution

Implement the **FCA 6-step financial planning process** as structured response schemas. Every Fyn interaction follows a defined process:

1. **Establish** — identify what the user is asking about (query classification)
2. **Gather** — check KYC data completeness, prompt for missing data before ANY advice
3. **Analyse** — fetch required data via tools in a defined sequence
4. **Recommend** — generate advice using decision tree triggers with specific £ amounts
5. **Implement** — offer to execute actions (create accounts, set contributions)
6. **Review** — track what was advised, flag when circumstances change

---

## Architecture

### Query Classification Layer

Every user message is classified into one of these query types before processing:

| Category | Query Types | Module |
|----------|------------|--------|
| **Protection** | Coverage gaps, policy review, premium affordability, dependant needs, employer benefits | protection |
| **Savings** | Emergency fund, account rates, tax wrappers (ISA/JISA), FSCS, debt vs savings, children's savings | savings |
| **Retirement** | Pension contributions, retirement date, Annual Allowance, tax relief, State Pension, decumulation | retirement |
| **Investment** | Portfolio risk, asset allocation, fees, diversification, tax-loss harvesting, ISA/GIA optimisation | investment |
| **Estate** | Inheritance Tax, wills, trusts, gifting, Lasting Power of Attorney, beneficiaries | estate |
| **Goals** | Goal progress, contribution adequacy, deadline feasibility, multi-goal priority | goals |
| **Tax** | ISA allowance, pension carry forward, spousal transfers, Capital Gains Tax, dividend planning | tax_optimisation |
| **Holistic** | Full financial plan, cross-module priority, surplus allocation, scenario modelling | holistic |
| **Property** | Property value, equity, mortgage, rental income, ownership split | property |
| **Income** | Income breakdown, tax position, disposable income, net income | income |
| **General** | Net worth, financial health overview, "what should I focus on" | general |

### KYC Gate (FCA Step 2 — Gather Information)

**Before ANY advice, Fyn must check data completeness.** This maps to the existing `DataReadinessService` per module.

#### Universal KYC Requirements (all advice types)
- Date of birth
- Marital status
- Employment status
- Gross annual income (by type)
- Monthly expenditure

#### Module-Specific KYC

**Protection advice requires:**
- All universal fields
- Number of dependants and ages
- Existing protection policies (or confirmed "none")
- Outstanding debts/liabilities
- Employer benefits (DIS, group IP)

**Savings advice requires:**
- All universal fields
- Existing savings accounts
- ISA subscription amounts this year

**Retirement advice requires:**
- All universal fields
- At least one pension record (DC or DB)
- Target retirement age
- Target retirement income

**Investment advice requires:**
- All universal fields
- Completed risk profile
- At least one investment account

**Estate advice requires:**
- All universal fields
- At least one asset (property, investment, savings, or pension)
- UK residency/domicile status
- Family members recorded

**If KYC data is missing, Fyn must:**
1. List specifically what is missing and why it matters
2. Offer to help enter it: "I can add that for you now — just tell me the details"
3. Navigate to the correct page if the user prefers to enter it themselves
4. NOT give advice until the blocking data is provided

### Structured Response Schemas (FCA Steps 3-4 — Analyse & Recommend)

Each query type has a defined response schema that forces the AI to:
1. Fetch specific data via tool calls
2. Check specific conditions from the decision trees
3. Return advice in a consistent format with £ amounts

#### Example: Pension Contribution Query

```json
{
  "query_type": "retirement_contribution",
  "kyc_check": {
    "passed": true,
    "missing": [],
    "data_fetched": {
      "income_definitions": true,
      "pension_allowances": true,
      "existing_pensions": true,
      "surplus": true,
      "emergency_fund": true
    }
  },
  "analysis": {
    "relevant_uk_earnings": 100000,
    "total_income": 108755,
    "annual_allowance": 60000,
    "current_contributions": 0,
    "remaining_allowance": 60000,
    "pa_reclaim_applicable": true,
    "pa_reclaim_amount": 7400,
    "monthly_surplus": 3307.60,
    "emergency_fund_months": 0,
    "emergency_fund_target": 26058
  },
  "recommendations": [
    {
      "priority": 1,
      "action": "Contribute £617/month to SIPP to reclaim Personal Allowance",
      "amount_monthly": 617,
      "amount_annual": 7400,
      "tax_relief": "60% effective (40% higher rate + 20% PA restoration)",
      "tax_saving": 4440,
      "trigger": "pa_reclaim"
    },
    {
      "priority": 2,
      "action": "Build emergency fund to £26,058",
      "amount_monthly": 2172,
      "target": 26058,
      "timeline_months": 12,
      "trigger": "emergency_fund_critical"
    },
    {
      "priority": 3,
      "action": "Direct remaining £519/month to ISA or further pension",
      "amount_monthly": 519,
      "trigger": "surplus_allocation"
    }
  ],
  "caveats": [
    "Tax rules based on 2025/26 tax year",
    "Investments can go down as well as up",
    "Consider speaking with a regulated financial adviser"
  ]
}
```

### Decision Tree Integration

The 130+ ActionDefinition triggers map to query types. When Fyn answers a question, the structured schema specifies which triggers to check:

| Query Type | Triggers to Check |
|-----------|------------------|
| Pension contributions | `employer_match`, `contribution_increase`, `tax_relief`, `annual_allowance_exceeded`, `salary_sacrifice_available`, PA reclaim |
| Emergency fund | `emergency_fund_critical`, `emergency_fund_low`, `emergency_fund_building`, `emergency_fund_excess` |
| ISA planning | `isa_not_maxed`, `cash_isa_recommended`, `excess_cash_isa_available`, `use_isa_allowance` |
| Life cover | `life_insurance_gap`, `dependants_no_life_cover`, `mortgage_no_decreasing_term`, `policy_not_in_trust` |
| Income protection | `income_protection_gap`, `self_employed_no_ip`, `ip_any_occupation_definition`, `ip_short_benefit_period` |
| Inheritance Tax | `iht_exceeds_nrb`, `policy_not_in_trust`, `gifts_pet_window`, `no_will`, `no_lpa` |
| Investment fees | `high_total_fees`, `high_fund_fees`, `high_platform_fees` |
| Portfolio risk | `risk_profile_missing`, `rebalance_portfolio`, `low_diversification` |
| Retirement readiness | `approaching_decumulation`, `income_gap`, `ni_gaps`, `state_pension_no_forecast` |
| Debt management | `debt_rate_exceeds_savings`, `offset_mortgage_better` |
| Children's savings | `child_no_jisa`, `child_jisa_allowance_remaining`, `child_turning_18` |
| Property | ownership split, mortgage data, rental income, RNRB eligibility |
| Tax optimisation | `spousal_transfer_beneficial`, `cgt_allowance_unused`, `high_dividend_in_gia`, `pension_carry_forward_available` |

### Implementation Actions (FCA Step 5)

After giving advice, Fyn offers to execute:
- `create_savings_account` — open emergency fund / Cash ISA
- `create_pension` — set up SIPP contribution
- `create_investment_account` — open ISA / move GIA to ISA
- `create_protection_policy` — record new policy
- `create_goal` — set savings target
- `update_record` — change existing contribution amounts
- `set_expenditure` — update spending data
- `create_family_member` — add dependant for protection calc

### Review Tracking (FCA Step 6)

- Log what advice was given and when
- Flag when data changes that affects previous advice (income change, new dependant, property purchase)
- Prompt for annual review: "It's been 12 months since we reviewed your protection — would you like to check if your cover is still adequate?"

---

## Required Tool Calls Per Query Type

Each schema specifies mandatory tool calls BEFORE generating advice:

| Query Type | Required Tool Calls |
|-----------|-------------------|
| Pension contributions | `get_tax_information(pension_allowances)`, `get_tax_information(income_definitions)`, `get_module_analysis(retirement)`, `list_records(dc_pension)` |
| Emergency fund | `get_module_analysis(savings)`, `list_records(savings_account)` |
| ISA planning | `get_tax_information(isa_allowances)`, `list_records(savings_account)`, `list_records(investment_account)` |
| Life cover | `get_module_analysis(protection)`, `list_records(life_insurance)` |
| Inheritance Tax | `get_tax_information(inheritance_tax)`, `get_module_analysis(estate)`, `list_records(property)` |
| Investment fees | `get_module_analysis(investment)`, `list_records(investment_account)` |
| Retirement readiness | `get_module_analysis(retirement)`, `get_tax_information(pension_allowances)`, `get_tax_information(state_pension)` |
| Net worth | (already in financial context — no tool call needed) |
| Full plan | `get_recommendations()`, `generate_financial_plan()` |

---

## Implementation Approach

### Phase 1: Query Classification + KYC Gates
- Add query classifier to `HasAiChat` that categorises user messages
- Before calling the AI model, run KYC check for the classified query type
- If data is missing, return a structured "data needed" response without calling the AI
- Fyn asks: "Before I can advise on your pension, I need to know your [missing fields]. Would you like me to help you enter them?"

### Phase 2: Mandatory Tool Sequences
- Define per-query-type tool call sequences in `FinancialPlanningKnowledge.php` or a new `QuerySchemas.php`
- System prompt instructs: "For [query type], you MUST call these tools before responding"
- Validate that required tools were called before accepting the response

### Phase 3: Response Schema Enforcement
- Define JSON schemas per query type
- Use structured output mode (xAI/Anthropic both support it)
- Post-process: validate the structured JSON, render into the chat UI with consistent formatting

### Phase 4: Decision Tree Binding
- Map each query type to its relevant ActionDefinition triggers
- Include trigger results in the structured response
- Fyn explains recommendations using the decision trace, not its own reasoning

### Phase 5: Review System
- Store structured advice responses in a new `ai_advice_log` table
- Track: query type, recommendations given, amounts, trigger keys, date
- Periodic check: flag when user's data has changed since last advice
- Annual review prompts based on advice date

---

## Files to Create/Modify

| File | Action | Purpose |
|------|--------|---------|
| `app/Constants/QuerySchemas.php` | **CREATE** | Define all query types, KYC requirements, required tool calls, and response schemas |
| `app/Services/AI/QueryClassifier.php` | **CREATE** | Classify user messages into query types |
| `app/Services/AI/KycGateChecker.php` | **CREATE** | Check data completeness per query type, return missing fields |
| `app/Services/AI/StructuredResponseValidator.php` | **CREATE** | Validate AI responses against schemas |
| `app/Traits/HasAiChat.php` | MODIFY | Add KYC pre-check before AI call, inject query-type-specific instructions |
| `app/Constants/FinancialPlanningKnowledge.php` | MODIFY | Add per-query-type tool call requirements |
| `app/Agents/CoordinatingAgent.php` | MODIFY | Add advice logging for review system |
| `database/migrations/*_create_ai_advice_log.php` | **CREATE** | Advice tracking table |

---

## KYC Data Prompts (Example Flows)

### User asks: "How much pension contribution should I make?"
```
Fyn: Before I can advise on your pension contributions, I need a few details:

1. ❌ Employment income — I need to know your salary to calculate relevant UK earnings for tax relief
2. ❌ Monthly expenditure — I need this to work out your monthly surplus
3. ✅ Date of birth — confirmed
4. ✅ Marital status — confirmed

Would you like me to help you enter your income now? I can do it right here in the chat.
```

### User asks: "Do I have enough life cover?"
```
Fyn: To assess your life cover, I need:

1. ❌ Number of dependants — this determines how much cover you need
2. ❌ Outstanding mortgage/debts — these need covering if something happens to you
3. ✅ Income — £100,000 employment (confirmed)
4. ✅ Existing policies — none recorded

Would you like to add your family members first? I can help with that now.
```

---

## Estimated Effort

| Phase | Effort | Dependencies |
|-------|--------|-------------|
| Phase 1: Query classification + KYC | 1-2 sessions | None |
| Phase 2: Mandatory tool sequences | 1 session | Phase 1 |
| Phase 3: Response schema enforcement | 2-3 sessions | Phase 2 |
| Phase 4: Decision tree binding | 1-2 sessions | Phase 3 |
| Phase 5: Review system | 1 session | Phase 4 |
| **Total** | **6-9 sessions** | |

---

## Success Criteria

1. Fyn NEVER gives advice without checking KYC data first
2. Fyn ALWAYS fetches required data via tools before responding
3. Every response includes specific £ amounts from the decision tree calculations
4. Responses follow a consistent format per query type
5. Fyn offers to help enter missing data conversationally
6. Advice is logged and reviewable
7. Users are prompted for annual review
