# Feature Specification: Estate Planning Module - Gifting Strategy

## Status: Live

## Executive Summary

The Gifting Strategy feature provides personalised recommendations for reducing IHT through lifetime giving. It analyses the user's assets to determine what is giftable, explains various gift types (annual exemption, PETs, CLTs), calculates potential IHT savings, and generates a prioritised gifting strategy based on the user's circumstances.

### Elevator Pitch

Discover how giving during your lifetime can reduce the Inheritance Tax your family will pay, with a personalised strategy showing what, when, and how much to gift.

### Problem Statement

Gifting is one of the most effective IHT mitigation strategies, but users are often unaware of the rules, exemptions, and 7-year survival period. Without guidance, they may miss opportunities to reduce their estate's tax liability or make gifts that do not achieve the desired tax benefits.

### Target Audience

- Primary: Users with IHT liability looking to reduce estate value
- Secondary: Users with liquid assets wanting to help family now
- Tertiary: Grandparents wanting to gift to grandchildren tax-efficiently

### Unique Selling Proposition

Personalised gifting strategy generated from actual recorded assets, showing immediately giftable amounts, gift types with tax implications, 7-year timeline considerations, and quantified IHT savings.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Gifting strategy views | 50% of users with IHT view | Feature tracking |
| Recommendation engagement | 40% explore specific strategies | Click tracking |
| Liquidity analysis usage | 60% view giftable amounts | Feature tracking |
| Gift type education | 70% view gift type explanations | Content tracking |

---

## User Personas

### Persona 1: Robert - High Estate Value

**Demographics**: 70-year-old with GBP 1.2M estate, significant IHT liability

**Goals**:
- Reduce estate for IHT
- Give to children during lifetime
- Understand 7-year rule

**Pain Points**:
- Not sure how much can give
- Worried about giving too much
- Does not understand gift types

**Success Criteria**: Clear strategy with amounts and timeline.

### Persona 2: Margaret - Regular Gifter

**Demographics**: 65-year-old making annual gifts to grandchildren

**Goals**:
- Use all available exemptions
- Track gifts already made
- Optimise ongoing gifting

**Pain Points**:
- Unsure of exemption limits
- Multiple grandchildren to track
- Wants to be tax-efficient

**Success Criteria**: All exemptions explained, can plan annual giving.

### Persona 3: John - Business Owner

**Demographics**: 58-year-old with business comprising most of estate

**Goals**:
- Understand business property relief
- Plan succession giving
- Know what can gift easily

**Pain Points**:
- Business is illiquid
- Cannot gift business easily
- Needs liquid assets for giving

**Success Criteria**: Understands liquidity constraints, realistic strategy.

---

## User Stories

### US-01: View Liquidity Analysis

**As a** user,
**I want to** see what assets I can gift,
**So that I** understand my gifting capacity.

**Acceptance Criteria**:
- Given I have assets recorded
- When I view Gifting Strategy
- Then I see liquidity breakdown

**Liquidity Categories**:
- Immediately giftable (cash, savings)
- Giftable with planning (investments that can be sold)
- Not easily giftable (property, illiquid assets)

### US-02: View Gift Types

**As a** user unfamiliar with gifting,
**I want to** understand different gift types,
**So that I** know my options.

**Acceptance Criteria**:
- Given I am on Gifting Strategy
- When I view gift types
- Then I see all options explained

**Gift Types**:
- Annual exemption
- Small gifts exemption
- Gifts from income
- Wedding gifts
- Potentially Exempt Transfers (PETs)
- Chargeable Lifetime Transfers (CLTs)

### US-03: View Annual Exemption

**As a** user,
**I want to** understand annual exemption,
**So that I** can use it each year.

**Acceptance Criteria**:
- Given I view annual exemption
- When I see the explanation
- Then I understand limits and rules

**Annual Exemption Details**:
- GBP 3,000 per year
- Can carry forward 1 year if unused
- Not subject to 7-year rule
- Immediately exempt

### US-04: View Small Gifts Exemption

**As a** user making small gifts,
**I want to** understand small gift rules,
**So that I** can give to multiple people.

**Acceptance Criteria**:
- Given I view small gifts
- When I see the explanation
- Then I understand the exemption

**Small Gifts Details**:
- GBP 250 per recipient per year
- Any number of recipients
- Cannot combine with annual exemption to same person

### US-05: View Gifts From Income

**As a** user with surplus income,
**I want to** understand gifts from income,
**So that I** can give tax-free.

**Acceptance Criteria**:
- Given I view gifts from income
- When I see the explanation
- Then I understand requirements

**Gifts From Income Rules**:
- Must be from regular income (not capital)
- Must be regular pattern
- Must not affect standard of living
- No upper limit
- Immediately exempt

### US-06: View PET Rules

**As a** user considering larger gifts,
**I want to** understand Potentially Exempt Transfers,
**So that I** know the 7-year rule.

**Acceptance Criteria**:
- Given I view PETs
- When I see the explanation
- Then I understand survival requirements

**PET Details**:
- Gifts to individuals
- Fully exempt if survive 7 years
- Taper relief if die 3-7 years after
- No limit on amount

### US-07: View Personalised Strategy

**As a** user with IHT liability,
**I want to** see recommended gifting strategy,
**So that I** know what to do.

**Acceptance Criteria**:
- Given I have estate and IHT data
- When I view recommendations
- Then I see personalised strategy

**Recommendation Structure**:
- Strategy description
- Amount to gift
- Gift type to use
- IHT saving potential
- Risk assessment
- Implementation steps

### US-08: View IHT Savings Calculation

**As a** user considering gifting,
**I want to** see potential IHT savings,
**So that I** understand the benefit.

**Acceptance Criteria**:
- Given I have IHT liability
- When I view gifting impact
- Then I see potential savings

**Calculation Display**:
- Current IHT liability
- Liability after proposed gifting
- IHT saved
- Net benefit to family

### US-09: View Gifting Timeline

**As a** user planning gifts,
**I want to** see optimal gifting schedule,
**So that I** can plan timing.

**Acceptance Criteria**:
- Given recommendations exist
- When I view timeline
- Then I see when to make gifts

**Timeline Elements**:
- Use annual exemptions each April
- Larger gifts with 7-year timeline
- Age-based considerations

---

## Feature Details

### Liquidity Analysis

**Immediately Giftable**:
- Cash savings accounts
- Easy access savings
- Mature fixed term deposits

**Giftable With Planning**:
- Investment accounts (need to sell)
- ISAs (can withdraw)
- Maturing fixed savings

**Not Easily Giftable**:
- Main residence
- Rental properties
- Business interests
- Illiquid investments

### Gift Type Details

**Annual Exemption (GBP 3,000)**:
- Available every tax year (April 6 - April 5)
- Can carry forward 1 unused year only
- Maximum GBP 6,000 if none used previous year
- Use current year's first, then brought forward

**Small Gifts Exemption (GBP 250)**:
- Per recipient per year
- Unlimited number of recipients
- Cannot give same person annual + small in same year
- Good for grandchildren, friends

**Gifts From Income**:
- From regular surplus income
- Must show pattern of giving
- Normal expenditure exemption
- Keep records for HMRC
- No upper limit

**Wedding Gifts**:
- GBP 5,000 from parent
- GBP 2,500 from grandparent
- GBP 1,000 from anyone else
- Must be in anticipation of marriage

**Potentially Exempt Transfers (PETs)**:
- Any gift to individual
- Falls out of estate after 7 years
- Taper relief applies after 3 years:
  | Years | IHT Rate |
  |-------|----------|
  | 0-3 | 40% |
  | 3-4 | 32% |
  | 4-5 | 24% |
  | 5-6 | 16% |
  | 6-7 | 8% |
  | 7+ | 0% |

**Chargeable Lifetime Transfers (CLTs)**:
- Gifts into most trusts
- Immediate charge if over NRB
- 20% rate (half death rate)
- Recalculated if death within 7 years

### Strategy Generation Logic

**Priority Order**:
1. Use annual exemption (GBP 3,000 + GBP 3,000 brought forward)
2. Consider small gifts (GBP 250 per person)
3. Establish gifts from income pattern
4. Plan larger PETs with 7-year consideration
5. Consider trusts for control

**Amount to Gift**:
- Based on IHT liability
- Limited by liquidity
- Consider age (7-year rule)
- Leave sufficient funds for own needs

**Risk Assessment**:
- Gift too much: May need care funding
- Gift too little: IHT not reduced enough
- Gift too late: 7-year rule not met
- Gift wrong type: May not be exempt

### Personalised Recommendations

**Example Recommendation**:
```
Strategy: Maximise Annual Exemptions
Amount: GBP 6,000 (GBP 3,000 current + GBP 3,000 carried forward)
Type: Annual Exemption
IHT Saving: GBP 2,400 (40% of GBP 6,000)
Risk: Low - immediately exempt
Action: Gift before April 5 to use carried forward amount
```

---

## User Flows

### Flow 1: View Gifting Strategy

```
Estate Planning Dashboard
    |
    v
Gifting Strategy Tab
    |
    v
View Liquidity Analysis
    |
    +--> Immediately giftable: GBP 150,000
    +--> With planning: GBP 200,000
    +--> Not giftable: GBP 450,000
    |
    v
View Recommended Strategies
    |
    +--> Strategy 1: Annual exemption (Priority: High)
    +--> Strategy 2: Gifts from income (Priority: Medium)
    +--> Strategy 3: PET to children (Priority: Consider)
```

### Flow 2: Explore Gift Type

```
Gifting Strategy Tab
    |
    v
Click on "Annual Exemption"
    |
    v
Detailed View
    |
    +--> What is it: GBP 3,000 per year
    +--> Rules: Carry forward, timing
    +--> Your situation: GBP 6,000 available
    +--> IHT saving: GBP 2,400
    +--> Action: Gift before April 5
```

### Flow 3: Calculate Gift Impact

```
Gifting Strategy Tab
    |
    v
View Current IHT: GBP 120,000
    |
    v
View Strategy: Gift GBP 100,000 to children
    |
    v
If survive 7 years:
    |
    +--> Estate reduced by GBP 100,000
    +--> IHT reduced by GBP 40,000
    +--> New IHT: GBP 80,000
```

---

## Edge Cases

### EC-01: No Liquid Assets

**Scenario**: All user's assets are property and illiquid.
**Expected Behaviour**: Show limited gifting options. Suggest other strategies (downsizing, equity release).

### EC-02: No IHT Liability

**Scenario**: User's estate is below IHT threshold.
**Expected Behaviour**: Note no IHT saving from gifting. May still want to gift for other reasons.

### EC-03: Elderly User (Over 80)

**Scenario**: User unlikely to survive 7 years.
**Expected Behaviour**: Focus on immediately exempt gifts. Note 7-year rule risk. Consider life insurance instead.

### EC-04: Gift Already Made

**Scenario**: User has already used annual exemption this year.
**Expected Behaviour**: Show carried forward only. Recommend wait until April 6.

### EC-05: Insufficient Surplus Income

**Scenario**: User's income equals expenses.
**Expected Behaviour**: Gifts from income not available. Focus on capital gifting.

### EC-06: Non-UK Beneficiaries

**Scenario**: User wants to gift to non-UK residents.
**Expected Behaviour**: UK IHT rules apply to UK domiciled donor regardless. Note recipient's tax position may differ.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Liquidity analysis displays correct categories | Yes |
| AC-02 | All gift types explained | Yes |
| AC-03 | Annual exemption rules correct | Yes |
| AC-04 | PET 7-year rule explained | Yes |
| AC-05 | Taper relief rates correct | Yes |
| AC-06 | Personalised recommendations generate | Yes |
| AC-07 | IHT savings calculate correctly | Yes |
| AC-08 | Recommendations prioritised | Yes |
| AC-09 | Timeline shows optimal timing | Yes |
| AC-10 | Risk assessment included | Yes |

---

## Dependencies

### Upstream Dependencies

- All asset records (for liquidity analysis)
- IHT calculation (for savings impact)
- User age (for 7-year consideration)
- Income data (for gifts from income)

### Downstream Dependencies

- Estate planning recommendations
- Year-end reminders
- Gift tracking (future enhancement)

---

## Technical Constraints

1. **Tax Rules**: Gift types must match HMRC rules
2. **Taper Relief**: Rates must be accurate
3. **Liquidity Assessment**: Must correctly categorise assets
4. **Age Consideration**: 7-year rule relevance varies by age

---

## Non-Functional Requirements

### Performance

- Strategy generation: Under 2 seconds
- Page load: Under 1 second
- IHT recalculation: Under 2 seconds

### Accuracy

- Gift exemptions must match current law
- IHT calculations must be correct
- Taper relief must use correct rates

### Accessibility

- Gift types clearly explained
- Strategy cards readable
- Complex rules in plain language

---

## UX Considerations

1. **Liquidity First**: Show what can be gifted
2. **Gift Type Education**: Explain each type clearly
3. **Personalised Strategy**: Recommendations specific to user
4. **Savings Emphasis**: Show IHT benefit clearly
5. **Risk Awareness**: Note 7-year rule impact
6. **Timeline Visual**: Show optimal timing
7. **Action Orientation**: Clear next steps
8. **Professional Advice**: Note complexity requires adviser
