# Feature Specification: Savings Module - Emergency Fund Tracking

## Status: Live

## Executive Summary

The Emergency Fund Tracking feature helps users understand whether they have adequate cash reserves to handle unexpected expenses or income loss. It calculates recommended emergency fund levels based on monthly expenditure, tracks actual emergency fund holdings across designated savings accounts, and provides a clear "runway" indicator showing how many months of expenses are covered.

### Elevator Pitch

Know exactly how long your emergency fund would last if you lost your income tomorrow, with clear guidance on how much you should have set aside.

### Problem Statement

Many people are unsure whether their savings would be sufficient to cover an emergency. They need to understand both what an appropriate emergency fund looks like for their situation and how their current savings compare to that target.

### Target Audience

- Primary: UK adults building or maintaining emergency funds
- Secondary: Users who want to validate their emergency savings are adequate
- Tertiary: Financial planners helping clients assess cash reserve adequacy

### Unique Selling Proposition

Dynamic emergency fund calculation based on actual recorded expenditure, with clear runway visualisation and integration with savings accounts to track designated emergency fund holdings separately from other savings goals.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Emergency fund calculation usage | 70% of users view analysis | Feature tracking |
| Expenditure data completion | 60% have expenditure for calculation | Data analysis |
| Emergency fund designation | 40% designate at least one EF account | Data tracking |
| Runway improvement | 20% improve runway within 6 months | Trend analysis |

---

## User Personas

### Persona 1: Tom - Emergency Fund Builder

**Demographics**: 30-year-old starting financial planning journey

**Goals**:
- Understand how much emergency fund he needs
- Track progress toward target
- Know when emergency fund is "done"

**Pain Points**:
- Uncertain what amount is appropriate
- Has savings but not sure if it counts as emergency fund
- Wants clear target to work toward

**Success Criteria**: Clear target displayed, progress tracking visible, knows when adequate.

### Persona 2: Linda - Security Seeker

**Demographics**: 45-year-old wanting financial security

**Goals**:
- Confirm emergency fund is sufficient
- Understand runway in months
- Feel confident about financial cushion

**Pain Points**:
- Worries about job security
- Wants reassurance that prepared
- Needs to know coverage duration

**Success Criteria**: Clear confirmation of adequacy, runway displayed in months.

### Persona 3: Dave - Variable Income

**Demographics**: 38-year-old self-employed consultant

**Goals**:
- Build larger emergency fund (income variable)
- Track progress toward 6+ month target
- Understand relationship to expenditure

**Pain Points**:
- Standard 3-month target not sufficient for situation
- Expenditure varies month to month
- Needs flexible target setting

**Success Criteria**: Can see extended runway target, progress toward 6 months.

---

## User Stories

### US-01: View Emergency Fund Target

**As a** user,
**I want to** see my recommended emergency fund amount,
**So that I** know how much I should have saved.

**Acceptance Criteria**:
- Given I have expenditure data recorded
- When I view the Emergency Fund tab
- Then I see a calculated target amount

**Calculation**:
- Default: 3-6 months of monthly expenditure
- Display range: "GBP X to GBP Y (3-6 months)"

### US-02: View Current Emergency Fund

**As a** user,
**I want to** see my current emergency fund total,
**So that I** know how much I have set aside.

**Acceptance Criteria**:
- Given I have savings accounts marked as emergency fund
- When I view the Emergency Fund tab
- Then I see the total of designated accounts

**Display**:
- Total emergency fund amount
- List of contributing accounts
- Contribution from each account

### US-03: View Emergency Fund Runway

**As a** user,
**I want to** see my runway in months,
**So that I** understand how long my emergency fund would last.

**Acceptance Criteria**:
- Given I have emergency fund and expenditure data
- When I view the Emergency Fund tab
- Then I see runway as number of months

**Calculation**:
```
Runway (months) = Emergency Fund Total / Monthly Expenditure
```

### US-04: View Runway Status Indicator

**As a** user,
**I want to** see a visual indicator of my runway status,
**So that I** quickly understand if I need to save more.

**Acceptance Criteria**:
- Given runway is calculated
- When I view the Emergency Fund tab
- Then I see colour-coded status

**Status Levels**:
| Runway | Colour | Status |
|--------|--------|--------|
| < 3 months | Red | Insufficient |
| 3-6 months | Amber | Adequate |
| 6+ months | Green | Healthy |

### US-05: View Emergency Fund Gap

**As a** user building emergency fund,
**I want to** see the gap between current and target,
**So that I** know how much more to save.

**Acceptance Criteria**:
- Given target and current are calculated
- When I view Emergency Fund tab
- Then I see the gap amount

**Display**:
- Gap to minimum (3 months)
- Gap to recommended (6 months)
- Percentage toward target

### US-06: Track Emergency Fund Progress

**As a** user,
**I want to** see progress toward my target,
**So that I** feel motivated to continue saving.

**Acceptance Criteria**:
- Given I have a target and current amount
- When I view Emergency Fund tab
- Then I see progress indicator

**Display**:
- Progress bar showing percentage complete
- Current amount vs target
- Milestone markers (3 months, 6 months)

### US-07: View Emergency Fund Composition

**As a** user with multiple accounts,
**I want to** see which accounts make up my emergency fund,
**So that I** understand where my emergency cash is.

**Acceptance Criteria**:
- Given I have multiple EF accounts
- When I view Emergency Fund tab
- Then I see breakdown by account

**Display**:
- List of designated accounts
- Balance of each
- Percentage of total each represents
- Access type (immediate/notice/fixed)

### US-08: Receive No-Data Guidance

**As a** user without expenditure data,
**I want to** understand why calculation cannot be made,
**So that I** know what information to provide.

**Acceptance Criteria**:
- Given I have no expenditure data
- When I view Emergency Fund tab
- Then I see message explaining what is needed

**Message**: "To calculate your emergency fund target, please enter your monthly expenditure in your profile."

---

## Feature Details

### Emergency Fund Calculation

**Target Calculation**:
```
Minimum Target = Monthly Expenditure x 3
Recommended Target = Monthly Expenditure x 6
```

**Monthly Expenditure Source**:
- User profile expenditure section
- Sum of all expense categories
- If spouse linked, option to use household expenditure

**Current Emergency Fund**:
```
Total = Sum of (balance) for all accounts where is_emergency_fund = true
```

### Runway Calculation

**Formula**:
```
Runway (months) = Emergency Fund Total / Monthly Expenditure
```

**Display**:
- Round to 1 decimal place (e.g., "4.5 months")
- Cap display at 24 months (show "24+ months" beyond)

### Status Thresholds

| Months | Status | Colour | Icon |
|--------|--------|--------|------|
| 0-1 | Critical | Red | Warning |
| 1-3 | Low | Orange | Caution |
| 3-6 | Adequate | Amber | OK |
| 6-12 | Healthy | Green | Check |
| 12+ | Excellent | Green | Star |

### Fallback Calculation

If no expenditure data:
- Option 1: Display message requesting data
- Option 2: Use income-based estimate (30% of income as expenses)
- Option 3: Use UK average expenditure

**Preferred**: Display message requesting actual expenditure data

### Access Considerations

**Immediate Access Accounts**:
- Best for emergency fund
- Full amount counted

**Notice Accounts**:
- May be suitable if notice period acceptable
- Consider flagging longer notice periods
- Still counted in total

**Fixed Term Accounts**:
- Not ideal for emergency fund
- Can count but display warning
- Suggest moving to accessible account

### Integration Points

**Savings Accounts**:
- Emergency fund designation on account form
- Balance changes update EF total automatically

**User Expenditure**:
- Monthly total from profile
- Changes in expenditure update runway

**Dashboard**:
- Emergency fund runway shown on main savings dashboard
- Quick status indicator visible

---

## User Flows

### Flow 1: View Emergency Fund Analysis

```
Savings Dashboard
    |
    v
Click "Emergency Fund" Tab
    |
    v
View Analysis
    |
    +--> Target: GBP X - GBP Y (3-6 months)
    +--> Current: GBP Z
    +--> Runway: X.X months
    +--> Status: [Colour indicator]
    |
    v
View Progress Bar
    |
    +--> Shows progress toward 6-month target
    |
    v
View Contributing Accounts
    |
    +--> List of designated accounts
    +--> Balance of each
```

### Flow 2: Designate Account as Emergency Fund

```
Savings Dashboard
    |
    v
Click on savings account
    |
    v
Click "Edit"
    |
    v
Check "This is an emergency fund account"
    |
    v
Click "Save"
    |
    v
Emergency Fund tab updates
    |
    v
Total recalculates
    |
    v
Runway recalculates
```

### Flow 3: Improve Emergency Fund

```
Emergency Fund Tab
    |
    v
See current runway: 2 months (Red)
    |
    v
See gap: GBP X to reach 3 months
    |
    v
Add to existing EF account
    |
    v
Return to Emergency Fund tab
    |
    v
See updated runway: 3 months (Amber)
```

---

## Edge Cases

### EC-01: No Expenditure Data

**Scenario**: User has not entered any expenditure.
**Expected Behaviour**: Display message: "Enter your monthly expenditure to calculate your emergency fund target." Provide link to expenditure section.

### EC-02: Zero Expenditure

**Scenario**: User entered GBP 0 for all categories.
**Expected Behaviour**: Cannot calculate runway (division by zero). Display message suggesting expenditure seems incomplete.

### EC-03: No Emergency Fund Accounts

**Scenario**: User has savings but none marked as emergency fund.
**Expected Behaviour**: Display GBP 0 emergency fund. Suggest designating some savings as EF. Show full savings total for reference.

### EC-04: Very High Runway

**Scenario**: Emergency fund covers 30 months of expenses.
**Expected Behaviour**: Display "24+ months" runway. Status: Excellent. Note that funds beyond EF needs could be invested.

### EC-05: Fixed Term Only Emergency Fund

**Scenario**: All EF accounts are fixed term with 1+ year maturity.
**Expected Behaviour**: Calculate total normally but display warning: "Your emergency fund is in fixed-term accounts. Consider keeping 1-3 months in accessible accounts."

### EC-06: Notice Period Warning

**Scenario**: Emergency fund in accounts with 90-day notice.
**Expected Behaviour**: Calculate normally but note: "X% of your emergency fund requires notice to access. Consider your immediate access needs."

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Target calculates as 3-6 months of expenditure | Yes |
| AC-02 | Current EF sums designated accounts | Yes |
| AC-03 | Runway calculates correctly | Yes |
| AC-04 | Status indicator shows correct colour | Yes |
| AC-05 | Gap to target displays correctly | Yes |
| AC-06 | Progress bar accurate | Yes |
| AC-07 | Contributing accounts listed | Yes |
| AC-08 | No-data message shown when appropriate | Yes |
| AC-09 | Designating account updates totals | Yes |
| AC-10 | Spouse expenditure option works | Yes |

---

## Dependencies

### Upstream Dependencies

- User expenditure data (profile)
- Savings accounts with EF designation
- Spouse linking (for household calculation option)

### Downstream Dependencies

- Savings dashboard summary
- Net worth calculations
- Strategy recommendations

---

## Technical Constraints

1. **Calculation Precision**: Round runway to 1 decimal place
2. **Zero Handling**: Guard against division by zero
3. **Real-time Updates**: Recalculate when expenditure or savings change
4. **Performance**: Calculation under 500ms

---

## Non-Functional Requirements

### Performance

- Emergency fund calculation: Under 500ms
- Page load: Under 1 second
- Real-time update on data change

### Data Integrity

- EF total matches sum of designated accounts
- Runway mathematically correct
- Status thresholds consistently applied

### Accessibility

- Colour indicators have text alternatives
- Progress bar accessible to screen readers
- Status levels announced clearly

---

## UX Considerations

1. **Visual Progress**: Clear progress bar toward target
2. **Colour Coding**: Intuitive red/amber/green status
3. **Months Display**: Runway in months more meaningful than amount
4. **Gap Focus**: Emphasise gap rather than just current amount
5. **Milestone Markers**: Show 3-month and 6-month targets on progress bar
6. **Celebration**: Positive feedback when target reached
7. **Guidance**: Tips for improving emergency fund
8. **Account Visibility**: Easy to see which accounts are designated
9. **Quick Actions**: One-click to edit account EF status
