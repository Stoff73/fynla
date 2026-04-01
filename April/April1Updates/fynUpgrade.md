# Plan: Fyn AI Financial Planning Knowledge Upgrade

## Context

Fyn currently tells users it's a "financial planning assistant" but lacks the financial knowledge to act as one. The system prompt passes user data (income total, account list, top 5 recommendations) but doesn't explain what that data MEANS. For example:
- Fyn can't distinguish "relevant UK income" from rental/dividend income for pension contribution advice
- Fyn doesn't understand ISA vs GIA tax treatment differences
- Fyn doesn't know pension Annual Allowance taper rules
- The 150+ recommendation triggers in the ActionDefinitionServices are invisible to the AI — it only sees the top 5 results, not the reasoning framework

The goal is to make Fyn think and respond like a qualified UK financial planner by injecting structured financial knowledge into the prompt, passing detailed income breakdowns, and upgrading the system identity.

---

## Files to Change

| File | Action | Purpose |
|------|--------|---------|
| `app/Constants/FinancialPlanningKnowledge.php` | **CREATE** | Static financial knowledge constant class (~1,650 tokens) |
| `app/Traits/HasAiChat.php` | MODIFY | Inject knowledge block, expand income breakdown, annotate account types, upgrade identity |
| `app/Agents/CoordinatingAgent.php` | MODIFY | Add `income_definitions` topic to tax info tool |
| `app/Services/AI/AiToolDefinitions.php` | MODIFY | Add `income_definitions` to topic enum |
| `app/Services/AI/XaiToolDefinitions.php` | MODIFY | Add `income_definitions` to topic enum |

---

## Implementation Steps

### Step 1: Create `app/Constants/FinancialPlanningKnowledge.php`

New final class following the `TaxDefaults.php` pattern. Contains static string constants for each knowledge domain, exposed via `getSystemPromptKnowledge(): string`.

**Knowledge domains (bullet format for token efficiency):**

1. **Income Classifications** (~300 tokens) — 5 HMRC income definitions (total, net, adjusted net, threshold, adjusted), "relevant UK income" for pensions (employment + self-employment ONLY — NOT rental, dividend, interest, trust, pension income), dividend vs savings income tax treatment, Personal Savings Allowance
2. **Pension Knowledge** (~350 tokens) — Annual Allowance taper (dual test: threshold income AND adjusted income), MPAA trigger, carry forward, tax relief mechanisms (net pay vs relief at source), salary sacrifice NI savings, relevant UK income cap, Lump Sum Allowance
3. **Investment Tax Wrappers** (~300 tokens) — ISA (tax-free, counts for IHT), GIA (CGT/dividend/savings allowances), onshore bond (5% withdrawals, top-slicing), offshore bond (gross roll-up), VCT/EIS/SEIS (relief rates, holding periods), pension wrapper (relief in, taxed out, 25% tax-free)
4. **Estate Planning Concepts** (~250 tokens) — BPR (100%/50%, 2yr min), BADR (10% CGT, £1m lifetime), PET vs CLT, taper relief, normal expenditure from income, deed of variation
5. **Protection Concepts** (~150 tokens) — Own vs any occupation, standalone vs accelerated CI, relevant life policies, trust placement for IHT
6. **Recommendation Framework** (~300 tokens) — The 6 module categories and types of triggers (emergency fund, tax efficiency, fees, coverage gaps, surplus waterfall ISA→Pension→Bond, employer match, NI gaps, IHT planning). Explains that recommendations are ranked by urgency with decision traces

**Key rule in the knowledge block:** "These are conceptual explanations. Always retrieve current thresholds and rates using the get_tax_information tool — never quote figures from this knowledge section."

### Step 2: Modify `buildUserProfile()` in HasAiChat.php (line 686-693)

Expand the income section from just total + tax band to include individual income type breakdown:

- Keep total annual income and tax band (unchanged)
- Add breakdown when user has more than just employment income:
  - Employment, Self-employment, Rental, Dividend, Interest, Other, Trust
  - Only show non-zero types
  - Skip breakdown if 100% employment income (saves tokens)
- ~100 extra tokens worst case (all 7 types)

### Step 3: Inject knowledge block into `buildSystemPrompt()` (line 496-498)

Add `<financial_knowledge>` XML block between `</regulatory_compliance>` and `<user_profile>`. This placement ensures the AI reads financial concepts BEFORE seeing user data.

```php
$financialKnowledge = FinancialPlanningKnowledge::getSystemPromptKnowledge();
```

Insert into the heredoc at the appropriate position.

### Step 4: Annotate existing records with tax context

In `buildExistingRecordsSummary()`, add brief tax labels to account types:
- Investment accounts: "Stocks & Shares ISA (tax-free)", "GIA (taxable)", "Onshore Bond (tax-deferred)" etc.
- Savings accounts: annotate Cash ISA as "(tax-free)"
- Small `formatAccountType()` helper method using match expression

### Step 5: Upgrade identity and personality blocks

**Identity** (line 461-462): Enhance from "professional financial planning assistant" to explicitly state Fyn thinks like a qualified financial planner with UK tax, pension, estate, and investment knowledge.

**Personality**: Add directive to connect concepts to the user's specific data (never explain ISA rules in the abstract — explain what they mean for THIS user's ISA).

### Step 6: Add `income_definitions` tool topic

**CoordinatingAgent.php**: Add new case `'income_definitions'` in `handleTaxInformation()` that calls `IncomeDefinitionsService::calculate($userId)`. This returns the user's adjusted net income, threshold income, and tapered allowances — critical for pension and High Income Child Benefit Charge advice.

**Both tool definition files**: Add `'income_definitions'` to the `topic` enum and update the description.

---

## Token Budget

| Component | Tokens |
|-----------|--------|
| Current static prompt | ~2,500 |
| Current dynamic sections | ~1,000-3,000 |
| **New: Financial knowledge block** | **~1,650** |
| **New: Income breakdown (worst case)** | **~100** |
| **New: Account type annotations** | **~50** |
| **New: Identity/personality updates** | **~50** |
| **Total new** | **~1,850** |
| **Estimated total prompt** | ~5,350-7,350 |

Well within context windows for both Haiku (200k) and Grok (131k).

---

## Verification

1. **Local test**: Start dev server, log in as `john@example.com`, open Fyn chat
2. **Test income knowledge**: Ask "What types of income do I have and how are they taxed?" — Fyn should reference the specific income types from the user profile breakdown
3. **Test pension knowledge**: Ask "Can I contribute more to my pension?" — Fyn should understand relevant UK income limits, Annual Allowance, and use `get_tax_information` tool for current thresholds
4. **Test ISA knowledge**: Ask "What's the difference between my ISA and my GIA?" — Fyn should explain tax treatment differences using the user's actual account data
5. **Test estate knowledge**: Ask "How can I reduce my inheritance tax?" — Fyn should reference estate planning concepts (BPR, PET, trusts) and the user's estate data
6. **Test income_definitions tool**: Ask "What is my adjusted net income?" — Fyn should call `get_tax_information` with topic `income_definitions` and return calculated values
7. **Test recommendation awareness**: Ask "Why are you recommending I increase my pension contribution?" — Fyn should be able to explain the decision logic (employer match, tax relief at marginal rate)
8. **Token monitoring**: Check log output for total prompt token count — should be under 8,000
