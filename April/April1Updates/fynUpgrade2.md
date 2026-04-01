# Fyn AI Upgrade Phase 2 — Implementation Plan

**Branch:** `fynImprovement` (new branch from this)
**Date:** 1 April 2026
**Predecessor:** fynUpgrade.md (Phase 1 — knowledge injection, completed)

---

## Objective

Refactor Fyn from a free-form chatbot into a structured financial planning engine that follows the FCA 6-step process, checks KYC data before giving advice, uses the application's decision trees for recommendations, and delivers consistent responses with specific £ amounts.

---

## What Changes

### A. System Prompt — Full Refactor

**Current:** 670-line monolith in `HasAiChat.php` with everything in one string.

**New:** 10 composable layers assembled by `SystemPromptBuilder`, with query-type-aware knowledge retrieval.

```
SystemPromptBuilder
  Layer 1: Core Identity (STATIC)          ~200 tokens
  Layer 2: Compliance & Rules (STATIC)     ~400 tokens
  Layer 3: FCA Process Instructions (STATIC) ~400 tokens
  Layer 4: User Profile (DYNAMIC/user)     ~300 tokens
  Layer 5: Financial Position (DYNAMIC/user) ~400-800 tokens
  Layer 6: Existing Records (DYNAMIC/query) ~200-400 tokens
  Layer 7: Data Completeness (DYNAMIC/user) ~200 tokens
  Layer 8: Query Knowledge (DYNAMIC/query)  ~300-500 tokens
  Layer 9: KYC Check Result (DYNAMIC/query) ~100-200 tokens
  Layer 10: Context & Tools (DYNAMIC/msg)   ~400 tokens
                                            ─────────────
  Total: ~3,100-3,900 tokens (down from ~6,700)
```

### B. Query Classification — Multi-Label, Not Single-Label

Most user queries span multiple areas. "How do I maximise my pension contributions?" is retirement + tax + savings (emergency fund) + affordability. "What should I do with my £50k bonus?" is savings + investment + pension + debt + tax. Single-label classification misses critical context.

**The classifier returns a PRIMARY type plus RELATED types:**

```php
QueryClassifier::classify("How do I maximise my pension contributions?", "/dashboard")
→ {
    primary: "retirement_contribution",
    related: ["tax_optimisation", "savings_emergency", "affordability"],
    modules: ["retirement", "tax", "savings"],
  }
```

**This affects every downstream component:**
- **KYC gates:** check requirements for ALL classified modules, not just the primary
- **Tool sequences:** merge required tools from primary + related types (deduplicated)
- **Knowledge retrieval:** include knowledge domains for ALL matched types
- **Decision tree triggers:** check triggers from ALL matched modules
- **Record filtering:** include records relevant to ALL matched types

**Query types (can overlap — a message may match several):**

| Query Type | Triggers On | Module |
|-----------|------------|--------|
| `protection_cover` | Coverage gaps, life cover, income protection, critical illness | protection |
| `protection_policy` | Policy review, premiums, employer benefits, trust placement | protection |
| `savings_emergency` | Emergency fund, cash buffer, rainy day | savings |
| `savings_accounts` | Account rates, ISA, JISA, FSCS, children's savings | savings |
| `savings_debt` | Debt vs savings, offset mortgage, credit card | savings |
| `retirement_contribution` | Pension contributions, maximise pension, Annual Allowance | retirement |
| `retirement_readiness` | When can I retire, am I on track, retirement income | retirement |
| `retirement_decumulation` | Drawdown, pension access, tax-free lump sum | retirement |
| `investment_portfolio` | Portfolio risk, asset allocation, diversification, rebalancing | investment |
| `investment_fees` | Fund fees, platform fees, total cost | investment |
| `investment_tax` | ISA vs GIA, tax-loss harvesting, bed-and-ISA | investment |
| `estate_iht` | Inheritance Tax, nil rate band, estate value | estate |
| `estate_planning` | Wills, trusts, LPAs, gifting, beneficiaries | estate |
| `goals_progress` | Goal tracking, am I on track, contribution adequacy | goals |
| `tax_optimisation` | ISA allowance, spousal transfers, CGT planning, dividend tax | tax |
| `property` | Property value, equity, mortgage, rental income | property |
| `income` | Income breakdown, tax position, disposable income | income |
| `holistic_health` | Total financial health, full review, what should I focus on | holistic |
| `general` | Net worth, simple factual queries | general |
| `data_entry` | User providing data (I have a pension, I earn X) | data_entry |
| `navigation` | Take me to, show me, go to | navigation |
| `affordability` | Surplus, can I afford, disposable income, budget | cross-cutting |

**Implicit related types (always added):**
- Any contribution/savings advice → always add `affordability` (must check surplus)
- Any tax-relief advice → always add `tax_optimisation`
- Any pension advice → always add `retirement_contribution` + `tax_optimisation` + `affordability`
- Any "maximise" or "best use of money" → always add `savings_emergency` (check emergency fund first)

**Example classifications:**

| User Message | Primary | Related |
|-------------|---------|---------|
| "How do I maximise my pension contributions?" | `retirement_contribution` | `tax_optimisation`, `savings_emergency`, `affordability` |
| "What should I do with my £50k bonus?" | `holistic_health` | `savings_emergency`, `savings_debt`, `retirement_contribution`, `investment_tax`, `affordability` |
| "Do I have enough life cover?" | `protection_cover` | (none — focused query) |
| "What is my Inheritance Tax position?" | `estate_iht` | `property` (estates depend on property data) |
| "Should I pay off my mortgage or invest?" | `savings_debt` | `investment_tax`, `retirement_contribution`, `affordability` |
| "Am I on track for retirement?" | `retirement_readiness` | `retirement_contribution`, `tax_optimisation` |
| "What is my net worth?" | `general` | (none — factual, no advice needed) |
| "I have a new ISA with £10,000" | `data_entry` | (none — just creating a record) |

**When `holistic_health` is primary, ALL modules are related — this is the full review.**

### C. KYC Gates — Data Check Before Advice

Pre-computed in PHP, injected into prompt as `<kyc_status>`. Plain text, no icons.

**Universal requirements (all advice types):**
- Date of birth
- Marital status
- Employment status
- Gross annual income (by type)
- Monthly expenditure

**Module-specific additions:**

| Module | Additional Requirements |
|--------|----------------------|
| Protection | Dependants + ages, existing policies (or "none"), debts/liabilities, employer benefits |
| Savings | Existing savings accounts, ISA subscriptions this year |
| Retirement | At least one pension, target retirement age, target retirement income |
| Investment | Completed risk profile, at least one investment account |
| Estate | At least one asset, UK residency/domicile, family members |
| Holistic | ALL module gates must pass |

**KYC checks ALL classified modules** — if primary is `retirement_contribution` with related `savings_emergency` and `affordability`, KYC checks retirement requirements AND savings requirements AND affordability requirements. A single missing field blocks the entire response if it's blocking for ANY classified type.

**If KYC fails:** Fyn lists what's missing, explains why, offers to help enter it conversationally. Does NOT give advice.

### D. Mandatory Tool Sequences — Merged From All Classified Types

Tool sequences from primary + all related types are merged and deduplicated. For example, "maximise pension" classified as `retirement_contribution` + `tax_optimisation` + `savings_emergency` + `affordability` would merge to:

| Query Type | Required Tool Calls |
|-----------|-------------------|
| `retirement_contribution` | `get_tax_information(pension_allowances)`, `get_tax_information(income_definitions)`, `get_module_analysis(retirement)`, `list_records(dc_pension)` |
| `savings_emergency` | `get_module_analysis(savings)`, `list_records(savings_account)` |
| `investment_tax` | `get_tax_information(isa_allowances)`, `list_records(savings_account)`, `list_records(investment_account)` |
| `protection_cover` | `get_module_analysis(protection)`, `list_records(life_insurance)` |
| `estate_iht` | `get_tax_information(inheritance_tax)`, `get_module_analysis(estate)`, `list_records(property)` |
| `investment_fees` | `get_module_analysis(investment)`, `list_records(investment_account)` |
| `retirement_readiness` | `get_module_analysis(retirement)`, `get_tax_information(pension_allowances)`, `get_tax_information(state_pension)` |
| `holistic_health` | `get_recommendations()`, `get_module_analysis(holistic)`, `generate_financial_plan()` |
| `general` | No mandatory tools — data already in financial context |
| `data_entry` | No tools — use create/update tools directly |
| `navigation` | No tools — use navigate_to_page |

### E. Decision Tree Binding — Merged From All Classified Types

Triggers from primary + all related types are merged. The AI checks ALL matched triggers and references the results in its advice:

| Query Type | Triggers |
|-----------|---------|
| `retirement_contribution` | `employer_match`, `contribution_increase`, `tax_relief`, `annual_allowance_exceeded`, PA reclaim (income £100k-£125k) |
| `savings_emergency` | `emergency_fund_critical`, `emergency_fund_low`, `emergency_fund_building`, `emergency_fund_excess` |
| `savings_accounts` | `rate_below_market`, `fixed_maturity_warning`, `cash_isa_recommended`, `fscs_breach`, `child_no_jisa` |
| `savings_debt` | `debt_rate_exceeds_savings`, `offset_mortgage_better` |
| `protection_cover` | `life_insurance_gap`, `income_protection_gap`, `critical_illness_gap`, `dependants_no_life_cover`, `self_employed_no_ip` |
| `investment_portfolio` | `risk_profile_missing`, `rebalance_portfolio`, `low_diversification` |
| `investment_fees` | `high_total_fees`, `high_fund_fees`, `high_platform_fees` |
| `investment_tax` | `open_isa`, `use_isa_allowance`, `consider_bonds`, `isa_not_maxed` |
| `estate_iht` | `iht_exceeds_nrb`, `policy_not_in_trust`, `gifts_pet_window`, `no_will`, `no_lpa` |
| `tax_optimisation` | `spousal_transfer_beneficial`, `cgt_allowance_unused`, `high_dividend_in_gia`, `pension_carry_forward_available` |
| `holistic_health` | ALL triggers across all modules, ranked by priority |

### F. Holistic Health — Special Query Type

When the user asks about total financial health, Fyn follows this priority order:

1. **Liquidity** — emergency fund adequacy (liquid assets vs 3-6 months' expenses)
2. **High-interest debt** — repayment before investment
3. **Protection gaps** — life, income, critical illness coverage
4. **Pension contributions** — employer match, tax relief, PA reclaim at £100k-£125k
5. **ISA allowance** — use it or lose it (tax year sensitive)
6. **Further investment/pension** — surplus allocation beyond ISA
7. **Estate planning** — IHT, wills, LPAs, gifting strategies
8. **Goal funding** — savings targets and life event preparation

All module KYC gates must pass. Cross-module conflicts resolved. Response as numbered priority list with £ amounts.

### G. RAG-Like Retrieval

Three levels of filtering based on ALL classified types (primary + related):

1. **Knowledge retrieval** — union of knowledge domains from all classified types. "Maximise pension" gets pension + tax + savings + affordability knowledge, not just pension.
2. **Recommendation retrieval** — recommendations from all matched modules. Pension question with related savings gets retirement AND savings recommendations.
3. **Record retrieval** — records relevant to all matched types. Pension question with affordability gets pension records AND savings records (for emergency fund check).

### H. Response Rules

- NEVER use emoji, icons, tick marks, or Unicode symbols. Plain text only.
- Joint ownership: always name BOTH owners with shares. "Your share is 50% (£875,000) and the other half is owned by your wife, Sarah."
- NEVER show internal record IDs
- NEVER use acronyms (17 banned terms)
- NEVER use planning jargon (waterfall, prioritise affordability, etc.)
- NEVER mention concepts that don't apply to this user
- Always give specific £ amounts with numbered action steps
- Always use `get_tax_information` for figures — never from memory

### I. Review System

- Log advice in `ai_advice_log` table (query type, recommendations, amounts, trigger keys, date)
- Flag when user data changes that affects previous advice
- Prompt for annual review: "It's been 12 months since we reviewed your protection"

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Services/AI/SystemPromptBuilder.php` | Orchestrates all 10 layers into assembled prompt |
| `app/Services/AI/Prompts/CoreIdentity.php` | Layer 1: identity, security, scope, personality, response format |
| `app/Services/AI/Prompts/ComplianceRules.php` | Layer 2: FCA compliance, hedging, acronyms, no-icons, joint ownership |
| `app/Services/AI/Prompts/FcaProcessInstructions.php` | Layer 3: 6-step process, tool usage, data creation guidance |
| `app/Services/AI/Prompts/QueryKnowledge.php` | Layer 8: per-domain knowledge retrieval |
| `app/Services/AI/QueryClassifier.php` | Classify user messages into query types |
| `app/Services/AI/KycGateChecker.php` | Check data completeness per query type |
| `app/Services/AI/StructuredResponseValidator.php` | Validate AI responses against schemas |
| `app/Constants/QuerySchemas.php` | Define all query types, KYC requirements, tool sequences, trigger mappings |
| `database/migrations/*_create_ai_advice_log.php` | Advice tracking table |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Traits/HasAiChat.php` | Replace `buildSystemPrompt()` with builder call, add classify + KYC steps to `chat()` |
| `app/Constants/FinancialPlanningKnowledge.php` | Split into per-domain methods callable by `QueryKnowledge.php` |
| `app/Agents/CoordinatingAgent.php` | Add advice logging after tool execution |

## Files to Remove/Archive

| File | Reason |
|------|--------|
| None removed — all content moves to new files | Backward compatible, old method becomes thin wrapper |

---

## Implementation Phases

### Phase 1: Prompt Refactor (2 sessions)
- Create `SystemPromptBuilder` with 10-layer assembly
- Extract static layers (CoreIdentity, ComplianceRules, FcaProcessInstructions) from HasAiChat
- Move dynamic builders (user profile, financial context, records, completeness) into builder
- Rewire `HasAiChat.buildSystemPrompt()` to call builder
- **Test:** existing conversations still produce same quality responses

### Phase 2: Query Classification + KYC (2 sessions)
- Create `QueryClassifier` (keyword/route matching in PHP)
- Create `KycGateChecker` (per-query-type data checks)
- Create `QuerySchemas` (defines all query types, requirements, tool sequences)
- Wire into `chat()` method — classify before AI call, inject KYC result
- **Test:** missing data prompts user instead of giving advice

### Phase 3: Knowledge RAG + Record Filtering (1-2 sessions)
- Split `FinancialPlanningKnowledge` into per-domain retrieval methods
- Create `QueryKnowledge` that maps query types to domains
- Filter existing records to query-relevant subset
- Filter recommendations to query-relevant modules
- **Test:** pension question only sees pension knowledge and records

### Phase 4: Mandatory Tool Sequences (1 session)
- Define required tool calls per query type in `QuerySchemas`
- Inject `<required_tools>` block into prompt for each query type
- Inject `<relevant_triggers>` block with decision tree conditions
- **Test:** Fyn calls required tools before responding

### Phase 5: Decision Tree Binding (1-2 sessions)
- Map all 130+ ActionDefinition triggers to query types
- Include trigger results in structured response
- Fyn references trigger calculations, not its own reasoning
- **Test:** advice matches what the recommendation engine calculates

### Phase 6: Review System (1 session)
- Create `ai_advice_log` migration and model
- Log structured advice after each response
- Add data change detection (flag when income, assets etc. change)
- Add annual review prompt logic
- **Test:** advice logged, review prompts appear after data changes

**Total: 8-10 sessions**

---

## Token Budget

| Component | Current | Refactored |
|-----------|---------|-----------|
| Static (identity, security, compliance, format) | ~2,500 | ~1,000 |
| Financial knowledge | ~1,800 (all) | ~300-500 (query-relevant) |
| User profile + surplus | ~300 | ~300 |
| Financial position | ~800 | ~400 (query-relevant modules) |
| Existing records | ~400 | ~200 (query-relevant records) |
| Data completeness | ~300 | ~200 (KYC result only) |
| Actions + creation guidance | ~600 | ~400 |
| Query-specific (triggers, tools) | 0 | ~300 |
| **Total** | **~6,700** | **~3,100-3,300** |

**50% token reduction with MORE relevant context.**

---

## Success Criteria

1. Fyn NEVER gives advice without checking KYC data first
2. Fyn ALWAYS calls required tools before responding (per query type)
3. Every response includes specific £ amounts from decision tree calculations
4. Responses follow consistent format — numbered steps, no icons, no jargon
5. Joint ownership always names both owners with shares
6. Only query-relevant knowledge, records, and recommendations in prompt
7. Holistic health follows the 8-step priority order with liquidity and affordability checks
8. Fyn offers to help enter missing data conversationally
9. Advice is logged and reviewable
10. System prompt is under 4,000 tokens (down from 6,700)
