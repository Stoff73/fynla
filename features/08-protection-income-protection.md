# Feature Specification: Protection Module - Income Protection Policies

## Status: Live

## Executive Summary

The Income Protection feature enables users to record and manage income protection (IP) insurance policies. Income protection provides regular income replacement if the policyholder cannot work due to illness or injury. This feature captures key policy details including benefit amounts, waiting periods, and inflation-linking options that are critical for accurate gap analysis.

### Elevator Pitch

Track your income protection insurance to ensure you know exactly how much of your income would be replaced if illness or injury prevented you from working.

### Problem Statement

Income protection is complex with multiple variables (benefit amount, waiting period, benefit period, indexation) that determine the actual protection provided. Users need to record these details accurately to understand whether they have adequate protection and identify gaps requiring attention.

### Target Audience

- Primary: UK adults with existing income protection insurance
- Secondary: Self-employed individuals reliant on personal income protection
- Tertiary: Employed individuals with employer-provided sick pay wanting to understand total coverage

### Unique Selling Proposition

Comprehensive UK income protection tracking that captures all critical variables (waiting period, benefit period, indexation) and integrates with gap analysis to show whether coverage meets the user's specific income replacement needs.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| IP policy recording | 50% of users with IP record it | Data analysis |
| Complete data capture | 80% include waiting period | Data completeness |
| Indexation tracking | 70% record indexation status | Data analysis |
| Integration accuracy | 95% policies used in gap analysis | Feature testing |

---

## User Personas

### Persona 1: James - Self-Employed Professional

**Demographics**: 42-year-old IT consultant, sole earner, income protection essential

**Goals**:
- Record IP policy accurately
- Understand effective coverage (after waiting period)
- Ensure benefit keeps pace with income growth

**Pain Points**:
- Has 90-day waiting period, unsure of impact
- Policy may not have kept pace with income increases
- Needs to understand gap between policy and actual needs

**Success Criteria**: Policy recorded with all details, gap analysis shows true coverage position.

### Persona 2: Sarah - Employed with Employer Benefits

**Demographics**: 35-year-old with employer sick pay plus personal IP policy

**Goals**:
- Record both employer and personal protection
- Understand how they work together
- See total coverage period and amounts

**Pain Points**:
- Employer sick pay is limited duration
- Personal policy has different waiting period
- Unclear on handover between coverages

**Success Criteria**: Both policies recorded, can see how coverage flows over time.

### Persona 3: Michael - Policy Reviewer

**Demographics**: 50-year-old reviewing protection ahead of retirement

**Goals**:
- Record existing IP policy details
- Understand remaining coverage period
- Assess whether cover still appropriate

**Pain Points**:
- Policy purchased years ago
- May be overpaying or underinsured
- Approaching age where IP less relevant

**Success Criteria**: Current policy details clear, understands remaining value.

---

## User Stories

### US-01: View Income Protection Policies

**As a** user with IP cover,
**I want to** see all my income protection policies,
**So that I** understand my income replacement protection.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I view the Policy Overview tab
- Then I see all my income protection policies listed

**Display Information**:
- Provider name
- Monthly benefit amount
- Waiting period (deferred period)
- Whether benefit is index-linked
- Monthly premium
- Policy dates

### US-02: Add Income Protection Policy

**As a** user,
**I want to** add an income protection policy,
**So that** my income replacement cover is recorded.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I click "Add Policy" and select "Income Protection"
- Then I can enter IP policy details

**Required Fields**:
- Provider name
- Monthly benefit amount

**Optional Fields**:
- Waiting period (deferred period in days/weeks)
- Benefit period (years or to age)
- Is index-linked (inflation-adjusted)
- Monthly premium
- Premium frequency
- Policy start date
- Policy end date
- Policy reference
- Notes

### US-03: Record Waiting Period

**As a** user,
**I want to** record my policy's waiting period,
**So that** the system understands when benefits would start.

**Acceptance Criteria**:
- Given I am adding an IP policy
- When I enter the waiting period
- Then this is saved and used in analysis

**Common Waiting Periods**:
- 4 weeks (28 days)
- 8 weeks (56 days)
- 13 weeks (91 days)
- 26 weeks (182 days)
- 52 weeks (365 days)

### US-04: Record Index-Linking Status

**As a** user,
**I want to** record whether my benefit is inflation-linked,
**So that** projections account for benefit increases.

**Acceptance Criteria**:
- Given I am adding an IP policy
- When I indicate whether benefit is index-linked
- Then this is saved and used in projections

**Indexation Options**:
- Yes - benefit increases with inflation
- No - benefit remains fixed

### US-05: Record Benefit Period

**As a** user,
**I want to** record how long benefits would be paid,
**So that** I understand total protection duration.

**Acceptance Criteria**:
- Given I am adding an IP policy
- When I enter the benefit period
- Then this is saved and shown in policy details

**Benefit Period Options**:
- Fixed years (1, 2, 5 years)
- To specific age (55, 60, 65, State Pension Age)
- Unlimited (pays until recovery or death)

### US-06: Edit Income Protection Policy

**As a** user,
**I want to** update IP policy details,
**So that** my records reflect current terms.

**Acceptance Criteria**:
- Given I have an IP policy recorded
- When I click edit
- Then I can modify and save changes

### US-07: Delete Income Protection Policy

**As a** user,
**I want to** remove IP policies no longer in force,
**So that** my protection view is current.

**Acceptance Criteria**:
- Given I have an IP policy recorded
- When I click delete and confirm
- Then the policy is removed

### US-08: View IP Coverage Summary

**As a** user,
**I want to** see my total income protection coverage,
**So that I** understand overall income replacement level.

**Acceptance Criteria**:
- Given I have IP policies recorded
- When I view coverage summary
- Then I see total monthly benefit and combined premium

---

## Feature Details

### Income Protection Basics

**What IP Covers**:
- Unable to work due to illness or injury
- Own occupation (cannot do your specific job) OR
- Suited occupation (cannot do any job you could reasonably do)
- Typically replaces 50-65% of gross income

**Key Variables**:
| Variable | Impact | Typical Range |
|----------|--------|---------------|
| Benefit Amount | Monthly income replacement | 50-65% of income |
| Waiting Period | Time before payments start | 4-52 weeks |
| Benefit Period | How long payments continue | 2 years to retirement |
| Indexation | Benefit increase mechanism | RPI/CPI/Fixed/None |

### Waiting Period (Deferred Period)

**Why It Matters**:
- Shorter waiting = higher premium
- Must survive waiting period before benefits start
- Should align with emergency fund and employer sick pay
- Common to match to employer sick pay duration

**Recommendation Logic**:
- 4 weeks: Self-employed with no sick pay
- 13 weeks: Employed with 3 months sick pay
- 26 weeks: Employed with 6 months sick pay

### Index-Linking (Indexation)

**Types**:
- RPI-linked: Increases with Retail Prices Index
- CPI-linked: Increases with Consumer Prices Index
- Fixed increase: Increases by set percentage annually
- None: Benefit stays fixed

**Premium Impact**: Index-linked premiums may also increase
**Inflation Protection**: Maintains purchasing power over long claim

### Benefit Period

**Options**:
| Type | Description | Best For |
|------|-------------|----------|
| Short-term (1-2 years) | Limited duration | Lower premiums, budget cover |
| Medium-term (5 years) | Moderate duration | Balance of cost and cover |
| Long-term (to age 55-65) | Until retirement | Comprehensive protection |
| To State Pension Age | Until state pension starts | Full working life cover |

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| provider_name | string | Yes | Non-empty |
| monthly_benefit | decimal | Yes | Positive number |
| waiting_period_days | integer | No | 0-365 |
| benefit_period_years | integer | No | Positive or null |
| benefit_end_age | integer | No | 50-75 |
| is_index_linked | boolean | No | Default false |
| monthly_premium | decimal | No | Positive number |
| premium_frequency | enum | No | monthly/quarterly/annually |
| policy_start_date | date | No | Valid date |
| policy_end_date | date | No | Valid date |
| policy_reference | string | No | - |
| notes | text | No | - |

### Premium to Expenditure

IP premiums should be tracked as financial commitments:
- Regular premium expense
- Category: Insurance premiums
- Impact on monthly expenditure calculations

---

## User Flows

### Flow 1: Add Income Protection Policy

```
Protection Dashboard
    |
    v
Click "Add Policy"
    |
    v
Select "Income Protection"
    |
    v
Policy Form Opens
    |
    +--> Enter provider name
    +--> Enter monthly benefit amount
    +--> Enter waiting period
    +--> Select benefit period option
    +--> Indicate if index-linked
    +--> Enter premium details
    +--> Enter dates
    |
    v
Click "Save"
    |
    v
Policy saved
    |
    v
Appears in Policy Overview
    |
    v
Gap Analysis updated
```

### Flow 2: Review IP Coverage

```
Protection Dashboard
    |
    v
Policy Overview Tab
    |
    v
View IP policies
    |
    v
Click on specific policy
    |
    v
See full details:
  - Monthly benefit
  - Waiting period
  - Benefit duration
  - Indexation status
  - Premium cost
```

### Flow 3: Compare to Income

```
Protection Dashboard
    |
    v
Gap Analysis Tab
    |
    v
View Income Protection Gap
    |
    v
See:
  - Current monthly income
  - Recommended replacement (60%)
  - Current IP coverage
  - Gap (if any)
  - Waiting period implications
```

---

## Edge Cases

### EC-01: Zero Waiting Period

**Scenario**: User has day-one cover with no waiting period.
**Expected Behaviour**: Allow 0 days waiting period. Display as "Day 1" or "No waiting period".

### EC-02: Multiple IP Policies

**Scenario**: User has employer IP plus personal IP policy.
**Expected Behaviour**: Both recorded separately. Total monthly benefit summed. Note that claims may interact (insurers may reduce for other income).

### EC-03: Waiting Period Longer Than Employer Sick Pay

**Scenario**: 26-week waiting period but employer only pays 12 weeks.
**Expected Behaviour**: Gap analysis should highlight the 14-week gap between sick pay ending and IP starting.

### EC-04: Very High Benefit Amount

**Scenario**: User enters benefit of GBP 20,000/month.
**Expected Behaviour**: Accept the value. Most insurers cap at 60-70% of income but user may have historical policy or special terms.

### EC-05: Benefit Period Past Retirement

**Scenario**: Benefit period extends beyond user's planned retirement age.
**Expected Behaviour**: Note the discrepancy but accept. IP typically ceases at retirement but recorded as per policy terms.

### EC-06: Index-Linked But Amount Fixed

**Scenario**: User ticks index-linked but enters specific amount.
**Expected Behaviour**: Treat as current benefit amount. Projections should apply inflation increase if index-linked.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | IP policies can be added | Yes |
| AC-02 | Provider and benefit amount required | Yes |
| AC-03 | Waiting period can be recorded | Yes |
| AC-04 | Benefit period can be recorded | Yes |
| AC-05 | Index-linking status can be set | Yes |
| AC-06 | IP policies display in Policy Overview | Yes |
| AC-07 | Total IP coverage calculates correctly | Yes |
| AC-08 | IP policies can be edited | Yes |
| AC-09 | IP policies can be deleted | Yes |
| AC-10 | IP coverage feeds into Gap Analysis | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- User income data (for gap analysis comparison)
- Protection module framework

### Downstream Dependencies

- Gap Analysis (compares IP to income needs)
- Strategy recommendations
- Premium summary
- Financial commitments tracking

---

## Technical Constraints

1. **Currency**: All amounts in GBP
2. **Waiting Period Units**: Store in days for consistency
3. **Benefit Period**: Store as years or target age
4. **Premium Frequency**: Standardise to monthly for comparison

---

## Non-Functional Requirements

### Performance

- Policy operations: Under 1 second
- Coverage calculations: Under 500ms

### Data Integrity

- Policies linked to user_id
- Waiting period validated as reasonable range
- Benefit amount validated as positive

### Accessibility

- Form fields properly labelled
- Waiting period explained clearly
- Index-linking concept explained

---

## UX Considerations

1. **Waiting Period Selector**: Common periods as dropdown plus custom option
2. **Benefit Explanation**: Help text explaining what IP covers
3. **Income Comparison**: Show benefit as percentage of recorded income
4. **Waiting Period Visual**: Timeline showing waiting period relative to sick pay
5. **Index-Linking Note**: Explain benefit of inflation protection
6. **Premium Display**: Show monthly cost with annual equivalent
7. **Policy Cards**: Key info (benefit, waiting period) visible at glance
8. **Gap Indicator**: Visual indicator if IP coverage below recommended level
