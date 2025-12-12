# Feature Specification: Retirement Module - Contributions and Allowances

## Status: Live

## Executive Summary

The Contributions and Allowances feature tracks pension contributions against the UK annual allowance and related limits including Money Purchase Annual Allowance (MPAA) and tapered annual allowance for high earners. It monitors carry forward from previous years and warns when users approach or exceed limits.

### Elevator Pitch

Track your pension contributions against UK tax limits to maximise tax relief and avoid unexpected tax charges on excess contributions.

### Problem Statement

UK pension annual allowance rules are complex. Users need to track total contributions (employee, employer, personal) against the GBP 60,000 limit, understand carry forward availability, and know if taper or MPAA applies to their situation.

### Target Audience

- Primary: UK pension contributors wanting to maximise allowances
- Secondary: High earners potentially affected by taper
- Tertiary: Users who have accessed pension flexibly (MPAA applies)

### Unique Selling Proposition

Automated tracking of pension contributions against UK-specific annual allowance rules with carry forward calculation and personalised allowance determination based on user circumstances.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Allowance tracking usage | 60% of pension holders view | Feature tracking |
| Complete contribution data | 70% have current year contributions | Data analysis |
| Carry forward awareness | 40% check carry forward | Feature tracking |
| Warning engagement | 80% act on warnings | Action tracking |

---

## User Personas

### Persona 1: Emma - Allowance Maximiser

**Demographics**: 45-year-old wanting to maximise pension contributions

**Goals**:
- Know remaining annual allowance
- Understand carry forward availability
- Make additional contributions before year end

**Pain Points**:
- Unsure of exact allowance used
- Multiple contribution sources
- Timing of contributions unclear

**Success Criteria**: Clear view of allowance used, remaining, and carry forward.

### Persona 2: Richard - High Earner

**Demographics**: 52-year-old director with income over GBP 260,000

**Goals**:
- Understand tapered annual allowance
- Know his specific reduced limit
- Avoid excess contribution charges

**Pain Points**:
- Taper calculation complex
- Limit varies year to year with income
- Employer contributions hard to control

**Success Criteria**: Personal tapered allowance calculated, contributions tracked against it.

### Persona 3: Sarah - Flexible Access User

**Demographics**: 58-year-old who has taken pension drawdown

**Goals**:
- Understand MPAA restriction
- Know reduced contribution limit
- Plan contributions accordingly

**Pain Points**:
- MPAA rules complex
- Only applies to DC, not DB
- GBP 10,000 limit restrictive

**Success Criteria**: MPAA status tracked, DC contributions against GBP 10,000 limit.

---

## User Stories

### US-01: View Annual Allowance Position

**As a** pension contributor,
**I want to** see my annual allowance status,
**So that I** know how much more I can contribute.

**Acceptance Criteria**:
- Given I am on the Contributions tab
- When I view allowance tracking
- Then I see allowance summary

**Display**:
- Total annual allowance (standard, tapered, or MPAA)
- Contributions this tax year
- Remaining allowance
- Percentage used

### US-02: View Contribution Breakdown

**As a** user with multiple pensions,
**I want to** see contribution breakdown,
**So that I** understand where contributions come from.

**Acceptance Criteria**:
- Given I have pension contributions recorded
- When I view contribution breakdown
- Then I see contributions by source

**Breakdown**:
- Employee contributions (from DC pensions)
- Employer contributions (from workplace pensions)
- Personal contributions (from personal/SIPP)
- DB accrual value (if applicable)
- Total contributions

### US-03: View Carry Forward

**As a** user with unused allowance,
**I want to** see carry forward availability,
**So that I** can make additional contributions.

**Acceptance Criteria**:
- Given previous years had unused allowance
- When I view carry forward section
- Then I see available amounts by year

**Display**:
- Year 1 ago: GBP X available
- Year 2 ago: GBP Y available
- Year 3 ago: GBP Z available
- Total carry forward: GBP Total

### US-04: Receive Allowance Warning

**As a** user approaching limit,
**I want to** receive warnings,
**So that I** avoid excess contribution charges.

**Acceptance Criteria**:
- Given my contributions approach allowance
- When I view Contributions tab
- Then I see warning message

**Warning Thresholds**:
| Usage | Warning |
|-------|---------|
| 80%+ | Approaching limit |
| 95%+ | Near limit |
| 100% | Allowance used |
| >100% | Exceeded - tax charge applies |

### US-05: Calculate Tapered Allowance

**As a** high earner,
**I want** system to calculate my tapered allowance,
**So that I** know my specific limit.

**Acceptance Criteria**:
- Given my income exceeds threshold
- When allowance is calculated
- Then tapered amount is shown

**Taper Rules (2025/26)**:
- Threshold income: GBP 200,000
- Adjusted income: GBP 260,000
- Taper: GBP 1 reduction per GBP 2 over GBP 260,000
- Minimum: GBP 10,000

### US-06: Track MPAA Status

**As a** user who accessed pension flexibly,
**I want to** track MPAA status,
**So that I** know my reduced limit.

**Acceptance Criteria**:
- Given I have triggered MPAA
- When system calculates allowance
- Then MPAA limit (GBP 10,000) is applied to DC

**MPAA Rules**:
- Triggered by flexible access (drawdown, UFPLS, etc.)
- Applies to DC contributions only
- DB can still use full allowance
- GBP 10,000 limit (2025/26)

### US-07: View Tax Year Summary

**As a** user,
**I want to** see which tax year applies,
**So that I** understand timing.

**Acceptance Criteria**:
- Given I am viewing contributions
- When I view the section
- Then current tax year is clear

**Display**:
- Current tax year: 2025/26
- Tax year dates: 6 April 2025 - 5 April 2026
- Days remaining in tax year

### US-08: Enter Manual Contribution

**As a** user with contributions not from recorded pensions,
**I want to** enter additional contributions,
**So that** tracking is complete.

**Acceptance Criteria**:
- Given I have contributions not in recorded pensions
- When I add manual contribution
- Then it is included in total

**Use Case**: Lump sum contribution to existing pension not yet updated in system.

---

## Feature Details

### Annual Allowance (2025/26)

**Standard Allowance**: GBP 60,000

**Tapered Allowance (High Earners)**:
- Threshold Income > GBP 200,000 AND
- Adjusted Income > GBP 260,000
- Taper: GBP 1 reduction per GBP 2 over GBP 260,000
- Minimum: GBP 10,000

**MPAA (Flexible Access Triggered)**: GBP 10,000 (DC only)

### Contribution Sources

**From DC Pensions**:
```
Annual Employee = Monthly Employee x 12
Annual Employer = Monthly Employer x 12
Or: Annual Salary x Contribution %
```

**From DB Pensions**:
```
DB Input = Change in Pension x 16 (plus CPI adjustment)
```
Note: Complex calculation typically provided on statement.

**Manual Entries**: User-entered lump sums or adjustments.

### Carry Forward Rules

**Availability**:
- Unused allowance from previous 3 tax years
- Must use current year allowance first
- Must have been UK pension scheme member in carry forward year
- Oldest year used first

**Calculation**:
```
Available Carry Forward =
  (Year 1 Allowance - Year 1 Used) +
  (Year 2 Allowance - Year 2 Used) +
  (Year 3 Allowance - Year 3 Used)
```

### Taper Calculation

**Step 1**: Calculate Threshold Income
```
Threshold Income =
  Total Income -
  Personal Pension Relief -
  Deductible Payments
```

**Step 2**: Calculate Adjusted Income
```
Adjusted Income =
  Threshold Income +
  Employer Pension Contributions +
  Personal Pension Relief Added Back
```

**Step 3**: Calculate Tapered Allowance
```
If Adjusted Income > GBP 260,000:
  Reduction = (Adjusted Income - 260,000) / 2
  Tapered Allowance = Max(60,000 - Reduction, 10,000)
Else:
  Full GBP 60,000 allowance
```

### MPAA Rules

**Trigger Events**:
- Taking income via flexi-access drawdown
- Uncrystallised funds pension lump sum (UFPLS)
- Certain small pot payments
- NOT triggered by: 25% tax-free cash alone, annuity purchase

**Effect**:
- DC contributions limited to GBP 10,000
- DB contributions can still use remaining standard allowance
- Total cannot exceed GBP 60,000 (or tapered amount)

### Tax Year Handling

**Current Tax Year**: April 6 to April 5
**Determination**:
```
If current date >= April 6: Tax Year = Year / Year+1
If current date < April 6: Tax Year = Year-1 / Year
```

---

## User Flows

### Flow 1: Check Annual Allowance Position

```
Retirement Dashboard
    |
    v
Click "Contributions" Tab
    |
    v
View Allowance Summary
    |
    +--> Your Allowance: GBP 60,000
    +--> Contributions: GBP 35,000
    +--> Remaining: GBP 25,000
    +--> Status: 58% used
    |
    v
View Contribution Breakdown
    |
    +--> Employee: GBP 12,000
    +--> Employer: GBP 18,000
    +--> Personal: GBP 5,000
```

### Flow 2: Check Carry Forward

```
Contributions Tab
    |
    v
View Carry Forward Section
    |
    v
See Available by Year:
    |
    +--> 2024/25: GBP 20,000 unused
    +--> 2023/24: GBP 15,000 unused
    +--> 2022/23: GBP 10,000 unused
    +--> Total: GBP 45,000 available
    |
    v
Consider additional contribution
```

### Flow 3: High Earner Taper Check

```
Contributions Tab
    |
    v
System checks income against thresholds
    |
    v
Income triggers taper
    |
    v
Display adjusted allowance:
    |
    +--> Standard: GBP 60,000
    +--> Your Allowance: GBP 30,000 (tapered)
    +--> Contributions: GBP 25,000
    +--> Remaining: GBP 5,000
```

---

## Edge Cases

### EC-01: No Recorded Pensions

**Scenario**: User has no pensions recorded.
**Expected Behaviour**: Show GBP 0 contributions. Full allowance available. Note to add pensions for tracking.

### EC-02: Contributions Exceed Allowance

**Scenario**: Total contributions exceed annual allowance.
**Expected Behaviour**: Show exceeded status. Explain annual allowance charge (marginal rate on excess). Suggest reviewing contributions.

### EC-03: MPAA and High Earner

**Scenario**: User triggered MPAA and is also high earner.
**Expected Behaviour**: MPAA (GBP 10,000) applies to DC. Remaining allowance (up to tapered amount) available for DB.

### EC-04: No Carry Forward Membership

**Scenario**: User was not pension scheme member in previous year.
**Expected Behaviour**: That year's carry forward not available. Note requirement.

### EC-05: DB Accrual Unknown

**Scenario**: User has DB pension but no annual statement yet.
**Expected Behaviour**: Estimate or exclude DB from calculation. Note uncertainty.

### EC-06: Mid-Year Pension Start

**Scenario**: User started pension mid tax year.
**Expected Behaviour**: Track contributions from start date. Full year allowance still applies.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Annual allowance displays correctly | Yes |
| AC-02 | Contributions aggregate from all pensions | Yes |
| AC-03 | Remaining allowance calculates | Yes |
| AC-04 | Carry forward displays by year | Yes |
| AC-05 | Tapered allowance calculates for high earners | Yes |
| AC-06 | MPAA status can be tracked | Yes |
| AC-07 | Warnings display at thresholds | Yes |
| AC-08 | Tax year displays correctly | Yes |
| AC-09 | Manual contributions can be added | Yes |
| AC-10 | Percentage used calculates correctly | Yes |

---

## Dependencies

### Upstream Dependencies

- DC pension contribution data
- DB pension data
- User income data (for taper)
- Tax configuration (allowance amounts)

### Downstream Dependencies

- Strategy recommendations
- Tax planning
- Year-end planning reminders

---

## Technical Constraints

1. **Tax Year Boundaries**: Must handle April 6 correctly
2. **Allowance Source**: Use TaxConfigService for amounts
3. **Taper Calculation**: Complex, needs income data
4. **MPAA Flag**: User must indicate if triggered

---

## Non-Functional Requirements

### Performance

- Allowance calculation: Under 1 second
- Carry forward calculation: Under 1 second
- Page load: Under 2 seconds

### Accuracy

- Allowance calculations must be correct
- Taper formula must match HMRC rules
- Carry forward must use correct years

### Accessibility

- Progress indicators have text
- Warnings announced to screen readers
- Complex rules have explanations

---

## UX Considerations

1. **Summary First**: Key numbers prominent
2. **Progress Visual**: Clear allowance usage indicator
3. **Carry Forward Clarity**: Show by year with expiry
4. **Taper Explanation**: Help users understand if applies
5. **MPAA Guidance**: Clear explanation of trigger and impact
6. **Warning Prominence**: Unmissable warnings near limit
7. **Tax Year Display**: Always show which year applies
8. **Year-End Prompt**: Remind of upcoming deadline
