# Feature Specification: Retirement Module - Defined Benefit Pensions

## Status: Live

## Executive Summary

The Defined Benefit (DB) Pensions feature enables users to record final salary, career average (CARE), and public sector pensions. Unlike DC pensions with fund values, DB pensions promise a specific annual income in retirement. The feature captures accrued benefits, payment ages, inflation linking, spouse benefits, and lump sum options.

### Elevator Pitch

Track your guaranteed pension income from final salary and public sector schemes, understanding exactly what income you will receive in retirement and what your spouse will receive after you.

### Problem Statement

DB pensions are complex with multiple variables: accrued income, normal retirement age, inflation protection, early/late retirement adjustments, spouse benefits, and commutation options. Users need to record these accurately to plan retirement income.

### Target Audience

- Primary: Public sector workers with defined benefit pensions (NHS, Teachers, Civil Service)
- Secondary: Private sector employees with final salary or CARE schemes
- Tertiary: Users with deferred DB pensions from previous employers

### Unique Selling Proposition

Comprehensive UK DB pension tracking with support for all scheme types, inflation protection tracking, spouse pension percentage recording, and integration with retirement income projections.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| DB pension recording | 50% of users with DB pensions record them | Data analysis |
| Complete benefit data | 70% record annual pension amount | Data completeness |
| Spouse benefit recording | 60% record spouse pension | Feature tracking |
| Inflation type recorded | 80% specify inflation linking | Data analysis |

---

## User Personas

### Persona 1: Helen - NHS Pension Holder

**Demographics**: 52-year-old nurse with 25 years NHS service

**Goals**:
- Record accrued NHS pension
- Understand retirement income options
- See how spouse would be protected

**Pain Points**:
- NHS pension scheme complex
- Has 1995 and 2015 scheme benefits
- Unclear on retirement options

**Success Criteria**: NHS pension recorded with annual amount and spouse benefit.

### Persona 2: Michael - Final Salary Pensioner

**Demographics**: 58-year-old with deferred final salary pension from previous employer

**Goals**:
- Track deferred DB pension
- Know when pension becomes payable
- Understand inflation protection

**Pain Points**:
- Left company years ago
- Unsure of current accrued value
- Statement arrives annually

**Success Criteria**: Pension recorded with normal retirement age and inflation type.

### Persona 3: David - Multiple DB Pensions

**Demographics**: 62-year-old with teacher pension and private sector DB pension

**Goals**:
- Consolidate all DB pension information
- Understand total guaranteed income
- Plan retirement timing

**Pain Points**:
- Different schemes have different rules
- Different retirement ages
- Need combined view

**Success Criteria**: Both pensions recorded, total annual pension visible.

---

## User Stories

### US-01: View All DB Pensions

**As a** user with DB pensions,
**I want to** see all my DB pensions in one view,
**So that I** understand my guaranteed retirement income.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I view the Overview tab
- Then I see all my DB pensions listed

**Display Information**:
- Scheme name
- Pension type (final salary, CARE, public sector)
- Annual pension amount
- Normal retirement age
- Inflation protection type
- Spouse pension percentage

### US-02: Add DB Pension

**As a** user,
**I want to** add a defined benefit pension,
**So that** my guaranteed income is tracked.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I click "Add Pension" and select DB type
- Then I can enter pension details

**Required Fields**:
- Scheme name
- Pension type
- Annual pension amount at retirement

**Optional Fields**:
- Normal retirement age
- Inflation protection type
- Spouse pension percentage
- Lump sum entitlement
- Early retirement reduction
- Late retirement increase
- Notes

### US-03: Select DB Pension Type

**As a** user with different DB pensions,
**I want to** specify the pension type,
**So that** system handles it appropriately.

**Acceptance Criteria**:
- Given I am adding a DB pension
- When I select the pension type
- Then type is recorded

**Pension Types**:
| Type | Description |
|------|-------------|
| final_salary | Final Salary Scheme |
| career_average | Career Average (CARE) Scheme |
| public_sector | Public Sector Pension |

### US-04: Record Inflation Protection

**As a** user,
**I want to** record inflation protection type,
**So that** income projections account for increases.

**Acceptance Criteria**:
- Given I am adding a DB pension
- When I select inflation protection
- Then projections account for increases

**Inflation Types**:
| Type | Description | Typical Application |
|------|-------------|---------------------|
| cpi | Consumer Prices Index | Public sector, post-1997 |
| rpi | Retail Prices Index | Older schemes |
| fixed | Fixed Percentage | Some private schemes |
| none | No Inflation Protection | Some private schemes |

### US-05: Record Spouse Pension

**As a** user with spouse,
**I want to** record spouse pension percentage,
**So that** estate planning accounts for this.

**Acceptance Criteria**:
- Given I am adding a DB pension
- When I enter spouse pension percentage
- Then this is saved

**Common Values**: 50%, 66.67%, 100%

### US-06: Record Lump Sum Option

**As a** user,
**I want to** record lump sum entitlement,
**So that I** understand retirement options.

**Acceptance Criteria**:
- Given I am adding a DB pension
- When I enter lump sum details
- Then options are recorded

**Fields**:
- Automatic lump sum (if any)
- Commutation factor (trade pension for lump sum)
- Maximum commutable lump sum

### US-07: Record Retirement Age Options

**As a** user,
**I want to** record early/late retirement factors,
**So that I** can model different retirement ages.

**Acceptance Criteria**:
- Given I am adding a DB pension
- When I enter retirement factors
- Then these are saved for projections

**Fields**:
- Normal retirement age
- Early retirement factor (reduction per year early)
- Late retirement factor (increase per year late)

### US-08: View DB Pension Summary

**As a** user,
**I want to** see summary of guaranteed income,
**So that I** understand total DB income.

**Acceptance Criteria**:
- Given I have DB pensions recorded
- When I view Overview
- Then I see total annual DB income

**Display**:
- Total annual pension (sum of all DB)
- Number of DB schemes
- Inflation protection summary

### US-09: Edit DB Pension

**As a** user,
**I want to** update pension details,
**So that** benefits stay current.

**Acceptance Criteria**:
- Given I have a pension recorded
- When I click edit
- Then I can modify and save

### US-10: Delete DB Pension

**As a** user,
**I want to** remove pensions no longer relevant,
**So that** retirement view is current.

**Acceptance Criteria**:
- Given I have a pension recorded
- When I click delete and confirm
- Then pension is removed

---

## Feature Details

### DB Pension Types

**Final Salary (final_salary)**:
- Pension based on final pensionable salary
- Accrual rate (e.g., 1/60th, 1/80th per year)
- Historically common in private sector
- Now closed to new members in most schemes

**Career Average (career_average)**:
- Pension based on average salary throughout career
- Each year's salary revalued to retirement
- Current public sector model (post-2015)
- Growing in private sector

**Public Sector (public_sector)**:
- Government-backed pension schemes
- Examples: NHS, Teachers, Civil Service, Police, Fire
- Usually linked to CPI inflation
- Guaranteed by government

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| scheme_name | string | Yes | Non-empty |
| pension_type | enum | Yes | Valid type |
| annual_pension | decimal | Yes | Positive |
| normal_retirement_age | integer | No | 50-75 |
| inflation_protection | enum | No | cpi/rpi/fixed/none |
| fixed_increase_percent | decimal | If fixed | 0-10 |
| spouse_pension_percent | decimal | No | 0-100 |
| lump_sum_entitlement | decimal | No | Non-negative |
| commutation_factor | decimal | No | Positive |
| early_retirement_factor | decimal | No | Percentage |
| late_retirement_factor | decimal | No | Percentage |
| notes | text | No | - |

### Annual Allowance Impact

DB pension accrual counts toward annual allowance:
```
DB Input = (Opening Pension x 16) - (Closing Pension x 16)
Plus CPI adjustment
```

This calculation is complex and typically provided on pension statements.

### Capitalised Value

For net worth and estate planning, DB pensions can be capitalised:
```
Capitalised Value = Annual Pension x Multiplier
Typical Multiplier = 20 (varies by age, scheme)
```

Note: This is indicative only. Cash equivalent transfer value (CETV) is the true transfer value.

### Spouse Pension Rules

**On Death Before Retirement**:
- Varies by scheme
- May be different from post-retirement

**On Death After Retirement**:
- Spouse pension as recorded percentage
- Usually continues for spouse's lifetime
- May reduce on spouse's death

---

## User Flows

### Flow 1: Add Final Salary Pension

```
Retirement Dashboard
    |
    v
Click "Add Pension"
    |
    v
Select "Defined Benefit"
    |
    v
Select Type: "Final Salary"
    |
    v
Pension Form
    |
    +--> Enter scheme name
    +--> Enter annual pension amount
    +--> Enter normal retirement age
    +--> Select inflation protection
    +--> Enter spouse pension %
    +--> Enter lump sum (optional)
    |
    v
Click "Save"
    |
    v
Pension saved
```

### Flow 2: Add Public Sector Pension

```
Retirement Dashboard
    |
    v
Click "Add Pension"
    |
    v
Select "Defined Benefit"
    |
    v
Select Type: "Public Sector"
    |
    v
Pension Form
    |
    +--> Enter scheme name (e.g., "NHS Pension")
    +--> Enter annual pension amount
    +--> Enter normal retirement age
    +--> Select CPI inflation
    +--> Enter spouse pension %
    |
    v
Click "Save"
```

### Flow 3: Upload Pension Statement

```
Retirement Dashboard
    |
    v
Click "Upload Document"
    |
    v
Select pension statement
    |
    v
System extracts:
    - Scheme name
    - Accrued pension
    - Normal retirement age
    - Spouse benefit
    |
    v
Review and confirm
    |
    v
Pension created
```

---

## Edge Cases

### EC-01: Multiple Tranches Same Scheme

**Scenario**: User has NHS 1995, 2008, and 2015 scheme benefits.
**Expected Behaviour**: Can record as separate pensions or combined. Notes field for explanation. System sums totals.

### EC-02: Unknown Inflation Type

**Scenario**: User unsure of inflation protection.
**Expected Behaviour**: Allow blank. Note that projections may be inaccurate without this.

### EC-03: Zero Annual Pension

**Scenario**: User wants to record scheme with minimal accrued benefit.
**Expected Behaviour**: Allow small amounts. GBP 0 not allowed (would be no pension).

### EC-04: Very High Pension

**Scenario**: Senior executive with GBP 100,000+ annual pension.
**Expected Behaviour**: Accept high values. No upper limit validation.

### EC-05: Early Retirement Already Taken

**Scenario**: User retired early and already receiving reduced pension.
**Expected Behaviour**: Record current pension amount (post-reduction). Note that this is in payment.

### EC-06: Pension Not Yet Accrued

**Scenario**: User wants to record future pension expectation.
**Expected Behaviour**: This feature is for accrued benefits. Projections handle future accrual.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | DB pensions can be added | Yes |
| AC-02 | All 3 pension types available | Yes |
| AC-03 | Annual pension amount required | Yes |
| AC-04 | Inflation protection options work | Yes |
| AC-05 | Spouse pension % can be recorded | Yes |
| AC-06 | Lump sum options can be recorded | Yes |
| AC-07 | Total DB income calculates correctly | Yes |
| AC-08 | Pensions editable and deletable | Yes |
| AC-09 | Document upload extracts data | Yes |
| AC-10 | DB income shows in retirement summary | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Document upload service

### Downstream Dependencies

- Retirement summary (total income)
- Contributions tab (annual allowance)
- Projections tab (future income)
- Estate planning (capitalised value)
- Spouse income planning

---

## Technical Constraints

1. **Currency**: All amounts in GBP
2. **Annual Basis**: Pensions stored as annual amount
3. **No Fund Value**: DB pensions have income, not fund
4. **Capitalisation**: For net worth, use multiplier (default 20)

---

## Non-Functional Requirements

### Performance

- Pension list load: Under 1 second
- Save operation: Under 1 second
- Summary calculation: Under 500ms

### Data Integrity

- Pensions linked to user_id
- Soft delete preferred
- Annual pension validation positive

### Accessibility

- Inflation types explained
- Spouse pension clearly labelled
- Complex fields have help text

---

## UX Considerations

1. **Type Selection**: Explain differences between DB types
2. **Public Sector Presets**: Common schemes could have defaults
3. **Annual Pension Prominence**: Key number displayed prominently
4. **Inflation Clarity**: Explain impact of inflation type
5. **Spouse Benefit Visibility**: Important for estate planning
6. **Statement Upload**: Encourage upload for accuracy
7. **Retirement Age Context**: Show impact on amount
8. **Lump Sum Trade-off**: Explain commutation impact
