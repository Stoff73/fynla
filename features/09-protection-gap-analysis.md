# Feature Specification: Protection Module - Gap Analysis and Strategy

## Status: Live

## Executive Summary

The Gap Analysis and Strategy feature analyses users' recorded protection policies against their calculated needs, identifies coverage gaps, and provides personalised recommendations for improving protection. It calculates requirements based on UK-specific factors including mortgage debt, income replacement needs, and family circumstances, then compares these against existing coverage.

### Elevator Pitch

Discover whether your insurance coverage truly protects your family by comparing what you have against what you actually need, with clear recommendations for addressing any gaps.

### Problem Statement

Users may have protection policies but lack understanding of whether their coverage is adequate for their specific circumstances. Without analysis, they cannot determine if they are underinsured (risking financial hardship) or overinsured (wasting premium spend).

### Target Audience

- Primary: Users with existing protection who want to validate adequacy
- Secondary: Users with no protection who need to understand what they should have
- Tertiary: Users planning major life changes (mortgage, family) who need updated analysis

### Unique Selling Proposition

Automated, personalised protection needs analysis using actual user data (income, debts, family, existing policies) with UK-specific calculations and actionable recommendations prioritised by importance.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Gap analysis usage | 60% of users view analysis | Feature tracking |
| Recommendation engagement | 40% explore recommendations | Click tracking |
| Coverage improvement | 20% add policies after viewing gaps | Before/after analysis |
| Strategy tab visits | 50% of gap viewers also view strategy | Navigation tracking |

---

## User Personas

### Persona 1: Rachel - Coverage Validator

**Demographics**: 38-year-old with mortgage, two children, some existing policies

**Goals**:
- Understand if existing cover is adequate
- Identify any critical gaps
- Prioritise which gaps to address first

**Pain Points**:
- Unsure if policies purchased years ago still appropriate
- Family has grown since policies taken out
- Mortgage has changed

**Success Criteria**: Clear understanding of gaps, prioritised action list.

### Persona 2: Chris - Protection Newbie

**Demographics**: 28-year-old with first mortgage, no existing protection

**Goals**:
- Understand what protection is needed
- Learn about different policy types
- Get recommendations appropriate to situation

**Pain Points**:
- Overwhelmed by protection options
- Unsure what amounts are reasonable
- Worried about cost

**Success Criteria**: Clear recommendations for essential cover, understands priorities.

### Persona 3: Angela - Life Change Planner

**Demographics**: 35-year-old planning for second child, remortgaging

**Goals**:
- Understand how life changes affect protection needs
- Update coverage recommendations for new situation
- Ensure family will be protected

**Pain Points**:
- Knows circumstances changing, unsure of impact
- Existing cover may become inadequate
- Wants proactive planning

**Success Criteria**: Forward-looking recommendations accounting for planned changes.

---

## User Stories

### US-01: View Protection Needs Analysis

**As a** user,
**I want to** see my calculated protection needs,
**So that I** understand what coverage amounts are appropriate.

**Acceptance Criteria**:
- Given I am on the Gap Analysis tab
- When I view the analysis
- Then I see calculated needs for each protection type

**Needs Displayed**:
- Life insurance needs
- Critical illness needs
- Income protection needs
- Total recommended coverage

### US-02: View Current Coverage

**As a** user,
**I want to** see my existing coverage totalled,
**So that I** can compare against needs.

**Acceptance Criteria**:
- Given I have policies recorded
- When I view Gap Analysis
- Then I see total existing coverage by type

**Coverage Displayed**:
- Total life cover
- Total critical illness cover
- Total income protection (monthly)

### US-03: View Coverage Gaps

**As a** user,
**I want to** see the gap between needs and coverage,
**So that I** understand where I am underinsured.

**Acceptance Criteria**:
- Given needs and coverage are calculated
- When I view Gap Analysis
- Then I see the gap (need minus coverage) for each type

**Gap Display**:
- Life insurance gap (positive = shortfall)
- Critical illness gap
- Income protection gap

### US-04: View Human Capital Calculation

**As a** user,
**I want to** understand how my protection needs are calculated,
**So that I** trust the recommendations.

**Acceptance Criteria**:
- Given I view the Gap Analysis
- When I look at the calculation breakdown
- Then I see the factors used

**Human Capital Factors**:
- Current age
- Expected retirement age
- Annual income
- Income growth assumption
- Discount rate
- Result: Present value of future earnings

### US-05: View Risk Exposure

**As a** user,
**I want to** see my total financial exposure,
**So that I** understand what is at risk.

**Acceptance Criteria**:
- Given I have financial data recorded
- When I view Gap Analysis
- Then I see total exposure and coverage ratio

**Exposure Components**:
- Human capital (future earnings)
- Outstanding debts
- Total exposure
- Coverage ratio (coverage / exposure)

### US-06: View Strategy Recommendations

**As a** user,
**I want to** receive personalised recommendations,
**So that I** know what actions to take.

**Acceptance Criteria**:
- Given gaps have been identified
- When I view the Strategy tab
- Then I see prioritised recommendations

**Recommendation Types**:
- Buy new coverage
- Increase existing coverage
- Write policies in trust
- Consider different policy types

### US-07: View Premium Analysis

**As a** user,
**I want to** see my total protection cost,
**So that I** understand premium spend.

**Acceptance Criteria**:
- Given I have policies with premiums recorded
- When I view coverage summary
- Then I see total monthly premium across all policies

### US-08: No Protection Notice

**As a** user with no policies,
**I want to** understand why protection matters,
**So that I** can make informed decisions.

**Acceptance Criteria**:
- Given I have no protection policies recorded
- When I view the Protection dashboard
- Then I see educational content about protection importance

---

## Feature Details

### Life Insurance Needs Calculation

**Components**:
| Component | Calculation | Purpose |
|-----------|-------------|---------|
| Mortgage | Outstanding balance | Clear mortgage on death |
| Other Debts | Total liabilities | Clear all debts |
| Income Replacement | Annual income x Years needed | Support dependants |
| Education | Per child x Cost estimate | Children's education |
| Emergency Fund | 6 months expenses | Immediate cash needs |

**Formula**:
```
Life Insurance Need =
    Outstanding Mortgage
  + Other Debts
  + (Annual Income x Years x Dependant Factor)
  + (Children Count x Education Cost Estimate)
  + (6 x Monthly Expenses)
```

**Dependant Factor**: Based on children's ages and number
**Years of Income**: Typically until youngest child independent (18-25)

### Critical Illness Needs Calculation

**Components**:
| Component | Calculation | Purpose |
|-----------|-------------|---------|
| Mortgage | Outstanding balance | May wish to clear debt |
| Income Buffer | 2-3 years income | Recovery period |
| Care Costs | Estimated adaptation costs | Home modifications, care |

**Formula**:
```
CI Need =
    Outstanding Mortgage
  + (Annual Income x 2)
  + Care Cost Estimate
```

### Income Protection Needs Calculation

**Components**:
| Component | Calculation | Purpose |
|-----------|-------------|---------|
| Income Replacement | 60% of gross income | Standard replacement rate |
| Employer Gap | After sick pay ends | Coverage timing |

**Formula**:
```
IP Need (Monthly) = Annual Gross Income x 0.60 / 12
```

**Considerations**:
- Waiting period should match employer sick pay
- Benefit period ideally to retirement age
- Index-linking recommended

### Human Capital Calculation

**Definition**: Present value of future earnings potential

**Formula**:
```
Human Capital = Sum of (Annual Income x (1 + growth)^n) / (1 + discount)^n
where n = years to retirement
```

**Example**:
- Age 35, retirement 65 = 30 years
- Income GBP 50,000
- Growth 2%
- Discount 4%
- Human Capital approximately GBP 900,000

### Coverage Ratio

**Formula**:
```
Coverage Ratio = (Total Life Cover + Total CI Cover) / (Human Capital + Total Debts)
```

**Interpretation**:
| Ratio | Status |
|-------|--------|
| < 25% | Significantly underinsured |
| 25-50% | Underinsured |
| 50-75% | Moderately covered |
| 75-100% | Well covered |
| > 100% | Potentially over-insured |

### Prioritisation Logic

**Priority 1 (Essential)**:
- Life insurance if have dependants and mortgage
- Income protection if employed/self-employed

**Priority 2 (Important)**:
- Critical illness cover
- Additional life cover above mortgage

**Priority 3 (Recommended)**:
- Writing policies in trust
- Index-linking on IP
- Review of policy terms

### Strategy Recommendations

**Recommendation Structure**:
```
{
  title: "Increase Life Insurance",
  priority: "high",
  reason: "Current cover GBP 200,000 below calculated need",
  action: "Consider additional term life cover of GBP 200,000",
  estimated_cost: "Approximately GBP 15-25/month",
  impact: "Close life insurance gap"
}
```

**Common Recommendations**:
1. Purchase term life insurance
2. Add decreasing term for mortgage
3. Consider standalone critical illness
4. Review income protection waiting period
5. Write life policies into trust
6. Consider family income benefit as alternative

---

## User Flows

### Flow 1: Review Gap Analysis

```
Protection Dashboard
    |
    v
Click "Gap Analysis" Tab
    |
    v
View Needs Calculation
    |
    +--> Life Insurance Need: GBP XXX
    +--> Critical Illness Need: GBP XXX
    +--> Income Protection Need: GBP XXX/month
    |
    v
View Current Coverage
    |
    +--> Life Cover: GBP XXX
    +--> CI Cover: GBP XXX
    +--> IP Cover: GBP XXX/month
    |
    v
View Gaps
    |
    +--> Life Gap: GBP XXX
    +--> CI Gap: GBP XXX
    +--> IP Gap: GBP XXX/month
```

### Flow 2: Review Strategy Recommendations

```
Protection Dashboard
    |
    v
Gap Analysis Tab (view gaps)
    |
    v
Click "Strategy" Tab
    |
    v
View Prioritised Recommendations
    |
    +--> Priority 1: "Purchase Life Insurance"
    |    - Recommended amount
    |    - Estimated cost
    |    - Reason
    |
    +--> Priority 2: "Consider Critical Illness"
    |    - etc.
    |
    v
Click on recommendation for details
```

### Flow 3: No Protection Starting Point

```
Protection Dashboard
    |
    v
No policies recorded
    |
    v
Display: "Why Protection Matters"
    |
    +--> Key reasons explained
    +--> Risks of no protection
    |
    v
Button: "View What You Need"
    |
    v
Gap Analysis (shows needs, zero coverage)
    |
    v
Clear recommendations to begin
```

---

## Edge Cases

### EC-01: No Income Recorded

**Scenario**: User has not entered income data.
**Expected Behaviour**: Cannot calculate human capital or income protection needs. Display message requesting income data. Still calculate mortgage-based life needs.

### EC-02: No Debts or Mortgage

**Scenario**: User has no mortgage or other debts.
**Expected Behaviour**: Life need focuses on income replacement and education. May show lower needs but still recommends protection if has dependants.

### EC-03: Single No Dependants

**Scenario**: Single user with no dependants.
**Expected Behaviour**: Life insurance need may be minimal (just debts). Focus recommendations on income protection and critical illness.

### EC-04: Over-Insured

**Scenario**: User's coverage exceeds calculated needs.
**Expected Behaviour**: Display positive (over-insured) status. Note that this is not necessarily bad but review recommended. Suggest not taking more.

### EC-05: Very High Earner

**Scenario**: User earning GBP 500,000+ annually.
**Expected Behaviour**: Human capital calculation accurate. Note that insurance cover may have practical limits (underwriting caps). Recommendations acknowledge limits.

### EC-06: Near Retirement

**Scenario**: User aged 62, retiring at 65.
**Expected Behaviour**: Human capital significantly reduced. Income protection may not be needed. Life insurance needs shift to estate planning focus.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Life insurance needs calculate correctly | Yes |
| AC-02 | Critical illness needs calculate correctly | Yes |
| AC-03 | Income protection needs calculate correctly | Yes |
| AC-04 | Current coverage totals accurately | Yes |
| AC-05 | Gaps display as need minus coverage | Yes |
| AC-06 | Human capital uses correct formula | Yes |
| AC-07 | Coverage ratio calculates correctly | Yes |
| AC-08 | Recommendations display with priorities | Yes |
| AC-09 | Strategy accounts for user circumstances | Yes |
| AC-10 | No-protection message shown when appropriate | Yes |

---

## Dependencies

### Upstream Dependencies

- User profile (age, marital status, dependants)
- Family members (children's ages)
- Income data (annual income)
- Property data (mortgage balances)
- Liability data (other debts)
- All protection policies

### Downstream Dependencies

- Strategy recommendations
- Dashboard summary indicators
- Email notifications (future)

---

## Technical Constraints

1. **Calculation Timing**: Analysis must be current (recalculate on data changes)
2. **Caching**: Consider caching analysis results with invalidation on data changes
3. **Precision**: Financial calculations to 2 decimal places
4. **Performance**: Full analysis in under 2 seconds

---

## Non-Functional Requirements

### Performance

- Gap analysis calculation: Under 2 seconds
- Page load: Under 1 second
- Recommendation generation: Under 1 second

### Accuracy

- Calculations must follow documented formulae
- Results reproducible with same inputs
- Test coverage for calculation edge cases

### Accessibility

- Gap indicators use more than colour alone
- Recommendations screen-reader friendly
- Clear visual hierarchy

---

## UX Considerations

1. **Visual Gap Indicators**: Bar charts showing need vs coverage
2. **Traffic Light System**: Green (covered), amber (partial), red (gap)
3. **Calculation Transparency**: Show workings for trust
4. **Actionable Recommendations**: Clear next steps
5. **Cost Estimates**: Give premium range for recommendations
6. **Priority Badges**: Visual priority indicators
7. **Scenario Planning**: Future enhancement for "what if" analysis
8. **Professional Advice Note**: Recommend seeking advice for complex needs
