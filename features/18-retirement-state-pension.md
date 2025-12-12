# Feature Specification: Retirement Module - State Pension

## Status: Live

## Executive Summary

The State Pension feature enables users to record their UK State Pension entitlement based on National Insurance contribution record. Users can enter their projected state pension amount, NI years completed, and state pension age. This integrates with overall retirement income planning.

### Elevator Pitch

Track your State Pension entitlement and NI record to ensure your government pension is part of your complete retirement plan.

### Problem Statement

The UK State Pension is a significant component of retirement income, yet many people are uncertain of their entitlement, state pension age, or NI contribution record. Accurate tracking is essential for retirement planning.

### Target Audience

- Primary: UK workers wanting to include State Pension in retirement planning
- Secondary: Users checking NI record completeness
- Tertiary: Users approaching retirement finalising income planning

### Unique Selling Proposition

Simple State Pension tracking integrated with DC and DB pensions for complete retirement income view, with NI years tracking to identify potential gaps before retirement.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| State pension recording | 50% of users record state pension | Data analysis |
| NI years entered | 70% record NI years | Data completeness |
| Forecast amount entered | 80% enter forecast | Data analysis |
| Integrated view usage | 60% view combined retirement | Feature tracking |

---

## User Personas

### Persona 1: Caroline - Full Entitlement Seeker

**Demographics**: 55-year-old with 30 years NI contributions

**Goals**:
- Confirm state pension forecast
- Check NI years toward full entitlement
- Understand state pension age

**Pain Points**:
- Unsure of exact NI record
- Needs to check government forecast
- Wants integrated retirement view

**Success Criteria**: State pension recorded with NI years, integrated with other pensions.

### Persona 2: James - Gaps Identifier

**Demographics**: 48-year-old with career breaks, gaps in NI

**Goals**:
- Record current NI position
- Identify years needed for full pension
- Plan voluntary NI contributions

**Pain Points**:
- Has gaps from self-employment
- Unsure of impact on state pension
- Needs to understand 35-year requirement

**Success Criteria**: Current NI years recorded, gap to 35 years visible.

### Persona 3: Maria - State Pension Age Planner

**Demographics**: 52-year-old planning around state pension age

**Goals**:
- Know exact state pension age
- Plan retirement timing around SPA
- Understand bridge funding need

**Pain Points**:
- SPA keeps changing
- Needs to know years until SPA
- Wants to see income timeline

**Success Criteria**: SPA recorded, years to SPA visible, income timeline clear.

---

## User Stories

### US-01: View State Pension Information

**As a** user,
**I want to** see my state pension details,
**So that I** include it in retirement planning.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I view State Pension section
- Then I see recorded state pension details

**Display Information**:
- Forecast annual amount
- NI years completed
- NI years required (35 for full)
- State pension age
- Years to state pension age

### US-02: Add State Pension

**As a** user,
**I want to** record my state pension forecast,
**So that** it is part of my retirement plan.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I click "Add Pension" and select State Pension
- Then I can enter state pension details

**Fields**:
- Annual pension forecast (or weekly)
- NI years completed
- State pension age
- Notes

### US-03: Enter Pension Forecast Amount

**As a** user,
**I want to** enter my state pension forecast,
**So that** the amount is accurate.

**Acceptance Criteria**:
- Given I am adding state pension
- When I enter forecast amount
- Then it is saved (weekly converted to annual)

**Input Options**:
- Annual amount (direct entry)
- Weekly amount (converted: weekly x 52)

**Current Maximum (2025/26)**: GBP 11,973 annually (GBP 230.25 weekly)

### US-04: Track NI Years

**As a** user,
**I want to** record my NI years,
**So that I** know my progress to full entitlement.

**Acceptance Criteria**:
- Given I am entering state pension
- When I enter NI years completed
- Then progress toward 35 years is shown

**Display**:
- Years completed: X
- Years required: 35
- Years remaining: 35 - X
- Progress percentage

### US-05: Record State Pension Age

**As a** user,
**I want to** record my state pension age,
**So that** I know when it starts.

**Acceptance Criteria**:
- Given I am entering state pension
- When I enter state pension age
- Then years to SPA is calculated

**Current Rules (2025)**:
- Born after April 1960: SPA 66
- Increasing to 67 between 2026-2028
- Planned increase to 68 (timing TBC)

### US-06: View State Pension in Summary

**As a** user,
**I want to** see state pension in retirement summary,
**So that** total retirement income is clear.

**Acceptance Criteria**:
- Given I have state pension recorded
- When I view Retirement Overview
- Then state pension appears with other pensions

### US-07: Edit State Pension

**As a** user,
**I want to** update state pension details,
**So that** forecast stays current.

**Acceptance Criteria**:
- Given I have state pension recorded
- When I click edit
- Then I can modify and save

### US-08: Delete State Pension

**As a** user,
**I want to** remove state pension record,
**So that I** can re-enter if incorrect.

**Acceptance Criteria**:
- Given I have state pension recorded
- When I click delete and confirm
- Then record is removed

### US-09: Receive NI Gap Guidance

**As a** user with NI gaps,
**I want to** understand options,
**So that I** can improve my pension.

**Acceptance Criteria**:
- Given I have less than 35 NI years
- When I view state pension section
- Then I see guidance about gaps

**Guidance**:
- Years needed for full pension
- Option to buy voluntary NI
- Years remaining before SPA to accrue

---

## Feature Details

### State Pension Basics (2025/26)

**Full New State Pension**: GBP 230.25 per week (GBP 11,973 annually)
**Minimum Years for Any Pension**: 10 years NI
**Years for Full Pension**: 35 years NI

**Pro-rata Calculation**:
```
If NI years >= 35: Full pension
If NI years 10-34: (NI years / 35) x Full pension
If NI years < 10: No state pension
```

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| annual_forecast | decimal | Yes | Positive |
| weekly_forecast | decimal | No | Positive (alternative) |
| ni_years_completed | integer | No | 0-50 |
| state_pension_age | integer | No | 60-68 |
| notes | text | No | - |

### State Pension Age Determination

Based on date of birth:
| Birth Date | State Pension Age |
|------------|-------------------|
| Before April 1960 | 66 |
| April 1960 - March 1961 | 66 (transitioning) |
| After March 1961 | 67 (by 2028) |
| After April 1977 | 68 (proposed) |

**System Behaviour**: Could auto-calculate from DOB, but allow manual override as rules change.

### Years to State Pension

**Calculation**:
```
Years to SPA = State Pension Age - Current Age
```

Where current age is calculated from user's date of birth.

### NI Progress Tracking

**Display Elements**:
- Years completed (user entered)
- Years required (35)
- Gap (35 - completed, if positive)
- Percentage complete (completed / 35 x 100)

**Visual**: Progress bar toward 35 years

### Integration Points

**Retirement Summary**:
- State pension annual amount added to total income
- Shown separately from DC/DB pensions
- Noted as guaranteed government income

**Projections**:
- State pension included from SPA
- Creates "bridge" need if retiring before SPA

**Estate Planning**:
- State pension has no capital value (stops on death)
- Spouse may inherit some state pension

---

## User Flows

### Flow 1: Add State Pension

```
Retirement Dashboard
    |
    v
Click "Add Pension"
    |
    v
Select "State Pension"
    |
    v
State Pension Form
    |
    +--> Enter forecast amount (weekly or annual)
    +--> Enter NI years completed
    +--> Enter state pension age
    |
    v
Click "Save"
    |
    v
State pension saved
    |
    v
Summary updates
    |
    v
NI progress shown
```

### Flow 2: Check State Pension Forecast

```
User checks gov.uk
    |
    v
Obtains forecast from Check Your State Pension
    |
    v
Returns to Fynla
    |
    v
Edit State Pension
    |
    v
Enter updated forecast
    |
    v
Save
```

### Flow 3: Review NI Position

```
Retirement Dashboard
    |
    v
View State Pension
    |
    v
See NI years: 28 of 35
    |
    v
See gap: 7 years needed
    |
    v
See guidance on voluntary contributions
    |
    v
Plan to work 7 more years or buy NI
```

---

## Edge Cases

### EC-01: Already Receiving State Pension

**Scenario**: User is retired and receiving state pension.
**Expected Behaviour**: Allow recording. Note as "In Payment". Include in current income rather than future projections.

### EC-02: No NI Contributions

**Scenario**: User has no UK NI record (lived abroad).
**Expected Behaviour**: Allow 0 NI years. Show that no state pension expected. Note options (spouse NI, returning to UK).

### EC-03: Over 35 NI Years

**Scenario**: User has 40 NI years.
**Expected Behaviour**: Accept 40 years. Note that additional years do not increase new state pension. May have Additional State Pension from pre-2016.

### EC-04: Protected Payment

**Scenario**: User has protected payment from old state pension system.
**Expected Behaviour**: Record total forecast (includes protected payment). Notes field for explanation.

### EC-05: Forecast Above Maximum

**Scenario**: User enters forecast above current maximum.
**Expected Behaviour**: Accept (may have protected payment). Soft warning that this exceeds standard full amount.

### EC-06: State Pension Age Uncertainty

**Scenario**: User unsure of their SPA.
**Expected Behaviour**: Provide link to gov.uk checker. Allow best estimate. Note that this may change.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | State pension can be added | Yes |
| AC-02 | Annual or weekly amount entry | Yes |
| AC-03 | Weekly converted to annual correctly | Yes |
| AC-04 | NI years can be recorded | Yes |
| AC-05 | Progress toward 35 years shows | Yes |
| AC-06 | State pension age can be entered | Yes |
| AC-07 | Years to SPA calculates | Yes |
| AC-08 | State pension shows in summary | Yes |
| AC-09 | Edit and delete work | Yes |
| AC-10 | NI gap guidance displays | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- User date of birth (for age calculation)

### Downstream Dependencies

- Retirement summary (total income)
- Projections (income timeline)
- Bridge calculation (gap before SPA)

---

## Technical Constraints

1. **Currency**: All amounts in GBP
2. **Weekly to Annual**: Multiply by 52
3. **Age Calculation**: From user DOB
4. **Maximum Validation**: Soft warning, not hard limit

---

## Non-Functional Requirements

### Performance

- State pension load: Under 500ms
- Save operation: Under 1 second
- Progress calculation: Under 100ms

### Data Integrity

- Single state pension per user (only one UK SP)
- Linked to user_id
- Soft delete if removed

### Accessibility

- Progress bar has text alternative
- NI years clearly labelled
- Guidance text readable

---

## UX Considerations

1. **Weekly vs Annual Toggle**: Easy to enter either
2. **NI Progress Visual**: Clear progress bar
3. **Gap Emphasis**: Highlight years needed
4. **SPA Prominence**: Clear display of age
5. **Gov.uk Reference**: Link to check forecast
6. **Simple Entry**: Only essential fields
7. **Guidance**: Help for those with gaps
8. **Integration**: Show with other pensions
