# Fyn AI Financial Planning Knowledge Upgrade

**Branch:** `fynImprovement`
**Date:** 1 April 2026
**Commits:** f3fe7f3 → e5fc278 (13 commits)

---

## What Was Done

### 1. Financial Knowledge Injection (f3fe7f3)
**File created:** `app/Constants/FinancialPlanningKnowledge.php`

Static knowledge block injected into the system prompt covering 7 domains:
- **Income classifications** — total income, net income, adjusted net income, relevant UK earnings (employment + self-employment ONLY)
- **Pension knowledge** — Annual Allowance, tax relief, Personal Allowance reclaim (60% effective relief at £100k-£125k), relevant UK earnings cap, pension access age, DB pensions, State Pension
- **Investment tax wrappers** — ISA, GIA, onshore/offshore bonds, VCT/EIS/SEIS, pension wrapper, Lifetime ISA
- **Estate planning** — BPR, BADR, PET vs CLT, taper relief, normal expenditure from income, deed of variation
- **Protection concepts** — own vs any occupation, standalone vs accelerated CI, relevant life policies, trust placement
- **Recommendation framework** — 6 module categories and trigger types
- **Affordability rules** — surplus allocation with PA reclaim prioritised, specific £ amounts required

### 2. Income Breakdown in User Profile (f3fe7f3)
**File modified:** `app/Traits/HasAiChat.php`

System prompt now passes individual income types (employment, self-employment, rental, dividend, interest, trust) with labels indicating whether each is "relevant UK earnings" or "not relevant UK earnings". Previously only total income and tax band were passed.

### 3. Account Type Tax Annotations (f3fe7f3)
**File modified:** `app/Traits/HasAiChat.php`

Existing records summary now annotates investment accounts with tax context: ISA(tax-free), GIA(taxable), Onshore Bond(tax-deferred), etc. Savings accounts annotated when ISA.

### 4. Property Records Enhanced (f3fe7f3)
**File modified:** `app/Traits/HasAiChat.php`

Property records now include ownership percentage, mortgage balance, and rental income — previously just address, type, and value.

### 5. Identity & Personality Upgrade (f3fe7f3)
**File modified:** `app/Traits/HasAiChat.php`

- Identity upgraded from "professional financial planning assistant" to "knowledgeable UK financial planner" with specific domain expertise listed
- Personality: connect concepts to user's specific data
- Instructions: never show internal IDs, distinguish joint ownership shares, expanded acronym ban (17 terms), no planning jargon (waterfall, prioritise affordability, etc.), concept blacklist (no AA taper unless >£200k, no carry forward unless needed, etc.)

### 6. Income Definitions Tool Topic (f3fe7f3)
**Files modified:** `app/Agents/CoordinatingAgent.php`, `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`

New `income_definitions` topic added to `get_tax_information` tool. Returns the user's adjusted net income, threshold income, and tapered allowances via `IncomeDefinitionsService::calculate()`.

### 7. Rolling Status Messages (a1dcd1d)
**File modified:** `resources/js/components/Shared/AiChatPanel.vue`

Replaced static "Thinking..." with rotating messages every 2.5s:
1. Processing your request
2. Reviewing your financial data
3. Checking your accounts
4. Analysing your position
5. Running calculations
6. Preparing your response

Smooth fade transition between messages. Timer cleans up on stream end and component unmount.

### 8. Cashflow Surplus Fix (7de845d)
**File modified:** `app/Services/Coordination/CashFlowCoordinator.php`

- Was calculating surplus from GROSS income — now uses `DisposableIncomeAccessor` (same net figure shown on user's income tab)
- Was clamping to `max(0.0, ...)` hiding shortfalls — now returns actual value (positive or negative)

### 9. "Other Income" Removed (d64f518)
**Files modified:** `resources/js/components/UserProfile/IncomeOccupation.vue`, `resources/js/components/Onboarding/steps/IncomeStep.vue`, `app/Traits/HasAiChat.php`

Removed the catch-all "Other Income" field from income tab, onboarding, and AI prompt. All income must be categorised into the 6 proper types: Employment, Self-employment, Rental, Dividend, Interest, Trust. Pension income and Child Benefit are auto-calculated.

### 10. Chat Scroll — User Message to Top (e5fc278)
**File modified:** `resources/js/components/Shared/AiChatPanel.vue`

When user sends a message, it scrolls to the top of the chat panel with the thinking indicator below. Added 60vh spacer div at bottom of message containers so the last message can always scroll to the top. Browser tested: `msgTopInContainer = -2` (position 0).

---

## Bug Fixes from Production Testing

| # | Issue | Fix |
|---|-------|-----|
| BI-1 | Fyn not picking up rental income | Income breakdown with all types |
| BI-2 | Fyn showing internal IDs (ID 375) | Explicit "never show IDs" instruction |
| BI-3 | Property total not split by ownership | Ownership %, mortgage, rental in records |
| BI-4 | Fyn using acronyms (AEA) | 17 banned acronyms listed |
| BI-5 | Can't distinguish employment vs other income | Relevant UK earnings labels |
| BI-6 | No affordability check | Affordability rules with surplus check |
| BI-7 | Mentions irrelevant concepts (taper, MPAA) | Concept blacklist + removed from knowledge |
| BI-8 | Doesn't flag PA reclaim at £100k | 60% effective relief highlighted |
| BI-9 | Surplus showing £0 (clamped) | Removed max(0.0, ...) clamp |
| BI-10 | Surplus from gross income not net | Uses DisposableIncomeAccessor |
| BI-11 | Missing income sources in calculation | Superseded by BI-10 |
| BI-12 | "Other Income" catch-all exists | Removed from form, onboarding, AI |
| BI-13 | Fyn saying "waterfall", "prioritise" | Banned jargon terms in instructions |
| BI-14 | Simultaneous split instead of smart allocation | PA reclaim first, emergency fund in parallel |
| BI-15 | User message doesn't scroll to top | 60vh spacer + streaming watcher scroll |

---

## Files Changed Summary

| File | Changes |
|------|---------|
| `app/Constants/FinancialPlanningKnowledge.php` | **NEW** — 7 knowledge domains |
| `app/Traits/HasAiChat.php` | Identity, instructions, income breakdown, property records, account annotations, personality |
| `app/Agents/CoordinatingAgent.php` | income_definitions tool topic |
| `app/Services/AI/AiToolDefinitions.php` | income_definitions enum |
| `app/Services/AI/XaiToolDefinitions.php` | income_definitions enum |
| `app/Services/Coordination/CashFlowCoordinator.php` | DisposableIncomeAccessor for surplus |
| `resources/js/components/Shared/AiChatPanel.vue` | Rolling status, scroll-to-top, 60vh spacer |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Removed Other Income |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Removed Other Income |

---

## Test User

`fyntest@example.com` / `password` on local dev:
- Employment: £100,000, Rental: £5,400, Dividend: £2,000
- Properties: 7 The Green (main res, £1.75m, 50% joint, £350k mortgage) + 19 Worth Court (BTL, £180k, 50% joint, £900/mo rent)
- SIPP: £0, GIA: £60,000
- Spouse: Sarah Test (50/50 joint ownership)
- Monthly surplus: £3,307.60

---

## Verification Checklist

- [x] Income breakdown shows in prompt with relevant UK earnings labels
- [x] PA reclaim flagged for £100k income (60% effective relief)
- [x] Surplus uses net income from income tab (£3,307.60)
- [x] Property records show ownership %, mortgage, rental
- [x] No internal IDs in responses
- [x] No acronyms (AEA, CGT, etc.)
- [x] No irrelevant concepts (taper, carry forward, salary sacrifice)
- [x] No planning jargon (waterfall, prioritise affordability)
- [x] Specific £ amounts in contribution advice
- [x] Smart allocation: PA reclaim + emergency fund in parallel
- [x] Rolling status messages while thinking
- [x] User message scrolls to top of chat on send
- [x] "Other Income" removed from income tab and onboarding
