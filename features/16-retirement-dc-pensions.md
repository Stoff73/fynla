# Feature Specification: Retirement Module - Defined Contribution Pensions

## Status: Live

## Executive Summary

The Defined Contribution (DC) Pensions feature enables users to record and manage their DC pension arrangements including workplace pensions, personal pensions, SIPPs, and stakeholder pensions. Users can track fund values, contributions (employee and employer), and investment holdings within their pensions. The feature integrates with the Investment module's holdings system for detailed portfolio analysis.

### Elevator Pitch

Track all your DC pensions in one place, from workplace auto-enrolment to personal SIPPs, with clear visibility of contributions, fund values, and underlying investments.

### Problem Statement

UK adults typically accumulate multiple DC pensions throughout their career. Without consolidation, they cannot understand their total pension wealth, track contribution rates, or ensure adequate retirement savings across all schemes.

### Target Audience

- Primary: UK employees with workplace pensions wanting consolidated tracking
- Secondary: SIPP holders managing their own pension investments
- Tertiary: Users with multiple pension pots from previous employers

### Unique Selling Proposition

Comprehensive UK DC pension tracking supporting all pension types with employer contribution tracking for workplace pensions, integrated holdings management using the same system as investments, and contribution monitoring against annual allowance.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| DC pension recording | 60% of users record at least one DC pension | Data analysis |
| Complete contribution data | 70% record contribution details | Data completeness |
| Holdings recorded | 30% record pension holdings | Feature tracking |
| Type accuracy | 95% use correct pension type | Data validation |

---

## User Personas

### Persona 1: Emma - Multiple Workplace Pensions

**Demographics**: 38-year-old with pensions from three previous employers plus current job

**Goals**:
- Track all workplace pensions in one place
- Understand total DC pension value
- See combined employer contributions

**Pain Points**:
- Pension statements arrive at different times
- Cannot easily see total position
- Unsure of old pension values

**Success Criteria**: All four pensions recorded, total value visible, contribution rates tracked.

### Persona 2: James - SIPP Investor

**Demographics**: 45-year-old with self-managed SIPP

**Goals**:
- Track SIPP fund value
- Record individual holdings within SIPP
- Monitor contributions against allowance

**Pain Points**:
- SIPP has many holdings to track
- Contribution allowance complex
- Needs detailed investment view

**Success Criteria**: SIPP recorded with all holdings, contributions tracked.

### Persona 3: Sarah - Stakeholder Pension Holder

**Demographics**: 32-year-old self-employed with stakeholder pension

**Goals**:
- Track personal pension contributions
- Understand projected growth
- See pension in context of other savings

**Pain Points**:
- No employer contribution
- Unsure if contributing enough
- Wants clearer projections

**Success Criteria**: Stakeholder pension recorded with contribution details.

---

## User Stories

### US-01: View All DC Pensions

**As a** user with DC pensions,
**I want to** see all my DC pensions in one view,
**So that I** understand my total DC pension wealth.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I view the Overview tab
- Then I see all my DC pensions listed

**Display Information**:
- Scheme name
- Provider
- Pension type
- Current fund value
- Contribution details
- Expected retirement age

### US-02: Add DC Pension

**As a** user,
**I want to** add a new DC pension,
**So that** my retirement planning is complete.

**Acceptance Criteria**:
- Given I am on the Retirement dashboard
- When I click "Add Pension" and select DC pension type
- Then I can enter pension details

**Required Fields**:
- Scheme/provider name
- Pension type
- Current fund value

**Optional Fields**:
- Provider name (if different from scheme)
- Employee contribution (% or GBP)
- Employer contribution (% or GBP)
- Annual salary (for % calculations)
- Expected retirement age
- Notes

### US-03: Select DC Pension Type

**As a** user with different DC pensions,
**I want to** specify the pension type,
**So that** appropriate fields are shown.

**Acceptance Criteria**:
- Given I am adding a DC pension
- When I select the pension type
- Then appropriate fields display

**Pension Types**:
| Type | Description | Employer Contribution |
|------|-------------|----------------------|
| workplace | Occupational/Workplace Pension | Yes (typical) |
| sipp | Self-Invested Personal Pension | No |
| personal | Personal Pension | No |
| stakeholder | Stakeholder Pension | No |

### US-04: Record Workplace Pension Contributions

**As a** user with workplace pension,
**I want to** record employee and employer contributions,
**So that** total contributions are tracked.

**Acceptance Criteria**:
- Given I am adding a workplace pension
- When I enter contribution details
- Then both employee and employer contributions are saved

**Fields for Workplace**:
- Annual salary
- Employee contribution percentage
- Employer contribution percentage
- Employee contribution GBP (calculated or entered)
- Employer contribution GBP (calculated)

### US-05: Record Personal Pension Contributions

**As a** user with personal/stakeholder pension,
**I want to** record my monthly contributions,
**So that** contribution tracking is accurate.

**Acceptance Criteria**:
- Given I am adding a personal/stakeholder pension
- When I enter contribution amount
- Then monthly contribution is saved

**Fields for Personal/Stakeholder/SIPP**:
- Monthly contribution amount (GBP)
- Contribution frequency (if not monthly)

### US-06: Add Holdings to DC Pension

**As a** user wanting detailed tracking,
**I want to** record individual investments in my pension,
**So that I** can see pension asset allocation.

**Acceptance Criteria**:
- Given I have a DC pension recorded
- When I click to add holdings
- Then I can add individual investments

**Uses**: Same holdings system as Investment module (polymorphic)

### US-07: View DC Pension Summary

**As a** user,
**I want to** see summary of all DC pensions,
**So that I** understand total DC position.

**Acceptance Criteria**:
- Given I have DC pensions recorded
- When I view Overview
- Then I see summary totals

**Summary Display**:
- Total DC fund value
- Number of DC pensions
- Total annual contributions

### US-08: Edit DC Pension

**As a** user,
**I want to** update pension details,
**So that** values stay current.

**Acceptance Criteria**:
- Given I have a pension recorded
- When I click edit
- Then I can modify and save

### US-09: Delete DC Pension

**As a** user,
**I want to** remove pensions no longer held,
**So that** retirement view is current.

**Acceptance Criteria**:
- Given I have a pension recorded
- When I click delete and confirm
- Then pension is removed

### US-10: Upload Pension Statement

**As a** user with pension statement,
**I want to** upload for automatic extraction,
**So that** I do not enter details manually.

**Acceptance Criteria**:
- Given I am adding a pension
- When I upload a statement document
- Then details are extracted for review

---

## Feature Details

### DC Pension Types

**Workplace (Occupational) Pension (workplace)**:
- Employer-sponsored scheme
- Auto-enrolment compliant
- Employer contributions typical
- Salary-linked contribution %
- May be trust-based or contract-based

**Self-Invested Personal Pension (sipp)**:
- Self-directed investment
- Wide investment choice
- No employer contributions
- Higher charges typically
- User manages investments

**Personal Pension (personal)**:
- Contract with pension provider
- Limited investment funds
- Individual contribution only
- Provider manages investments
- May include legacy plans

**Stakeholder Pension (stakeholder)**:
- Government-regulated personal pension
- Capped charges (1.5% reducing to 1%)
- Minimum features guaranteed
- Can accept employer contributions
- Flexible contribution options

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| scheme_name | string | Yes | Non-empty |
| provider_name | string | No | - |
| pension_type | enum | Yes | Valid type |
| current_fund_value | decimal | Yes | Non-negative |
| annual_salary | decimal | If workplace | Positive |
| employee_contribution_percent | decimal | If workplace | 0-100 |
| employer_contribution_percent | decimal | If workplace | 0-100 |
| monthly_contribution_amount | decimal | If personal | Non-negative |
| expected_retirement_age | integer | No | 50-75 |
| notes | text | No | - |

### Contribution Calculations

**For Workplace Pensions**:
```
Monthly Employee = (Annual Salary x Employee %) / 12
Monthly Employer = (Annual Salary x Employer %) / 12
Annual Employee = Annual Salary x Employee %
Annual Employer = Annual Salary x Employer %
```

**For Personal/Stakeholder/SIPP**:
```
Annual Contribution = Monthly Contribution x 12
```

### Holdings Integration

DC Pensions can have holdings using the polymorphic holdings system:
- Same holding structure as Investment accounts
- Asset types: uk_equity, fund, etf, bond, cash, etc.
- Enables pension asset allocation analysis
- Optional - not required for basic tracking

### Annual Allowance Integration

DC contributions count toward:
- GBP 60,000 annual allowance (2025/26)
- Combined with DB accrual for total
- Tracked in Contributions tab
- Warnings when approaching limit

---

## User Flows

### Flow 1: Add Workplace Pension

```
Retirement Dashboard
    |
    v
Click "Add Pension"
    |
    v
Select "Defined Contribution"
    |
    v
Select Type: "Workplace"
    |
    v
Pension Form
    |
    +--> Enter scheme name
    +--> Enter provider name
    +--> Enter fund value
    +--> Enter annual salary
    +--> Enter employee contribution %
    +--> Enter employer contribution %
    |
    v
Click "Save"
    |
    v
Pension saved
    |
    v
Summary updates
```

### Flow 2: Add SIPP with Holdings

```
Retirement Dashboard
    |
    v
Click "Add Pension"
    |
    v
Select "Defined Contribution"
    |
    v
Select Type: "SIPP"
    |
    v
Pension Form
    |
    +--> Enter SIPP details
    +--> Enter fund value
    +--> Enter monthly contribution
    |
    v
Click "Save"
    |
    v
SIPP saved
    |
    v
Click on SIPP
    |
    v
Add Holdings
    |
    +--> Add individual investments
```

### Flow 3: Upload Pension Statement

```
Retirement Dashboard
    |
    v
Click "Upload Document"
    |
    v
Select pension statement file
    |
    v
System extracts details
    |
    v
Review extracted data
    |
    +--> Correct any errors
    |
    v
Click "Save"
    |
    v
Pension created
```

---

## Edge Cases

### EC-01: Zero Fund Value

**Scenario**: New pension with no value yet.
**Expected Behaviour**: Allow GBP 0 fund value. Pension appears in list.

### EC-02: No Employer Contribution

**Scenario**: Workplace pension with 0% employer contribution.
**Expected Behaviour**: Accept 0% employer. This is unusual but possible.

### EC-03: Very High Contributions

**Scenario**: Total contributions exceed annual allowance.
**Expected Behaviour**: Record contributions. Flag in allowance tracking. No hard block.

### EC-04: Missing Salary for Workplace

**Scenario**: User does not enter salary for workplace pension.
**Expected Behaviour**: Cannot calculate contribution amounts from percentages. Allow percentage entry but show calculated values as "Unknown".

### EC-05: Multiple Pensions Same Provider

**Scenario**: User has two pensions with same provider.
**Expected Behaviour**: Allow multiple records. Distinguish by scheme name or notes.

### EC-06: Workplace Without Employer Name

**Scenario**: User does not know scheme name.
**Expected Behaviour**: Allow generic name (e.g., "Previous Employer Pension"). Provider name may suffice.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | DC pensions can be added | Yes |
| AC-02 | All 4 pension types available | Yes |
| AC-03 | Workplace shows contribution % fields | Yes |
| AC-04 | Personal shows monthly contribution field | Yes |
| AC-05 | Fund value required | Yes |
| AC-06 | Holdings can be added to pension | Yes |
| AC-07 | Total DC value calculates correctly | Yes |
| AC-08 | Pensions editable and deletable | Yes |
| AC-09 | Document upload extracts data | Yes |
| AC-10 | Contributions integrate with allowance tracking | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Holdings system (polymorphic)
- Document upload service

### Downstream Dependencies

- Retirement summary
- Contributions tab (allowance tracking)
- Projections tab (growth calculations)
- Portfolio analysis (if holdings recorded)
- Net worth calculations

---

## Technical Constraints

1. **Currency**: All values in GBP
2. **Polymorphic Holdings**: Uses holdable morph for holdings
3. **Contribution Types**: Different fields for workplace vs personal
4. **Annual Allowance**: Contributions feed into allowance tracking

---

## Non-Functional Requirements

### Performance

- Pension list load: Under 1 second
- Save operation: Under 1 second
- Holdings load: Under 500ms

### Data Integrity

- Pensions linked to user_id
- Holdings linked via morph
- Soft delete preferred

### Accessibility

- Form fields labelled clearly
- Pension type differences explained
- Contribution fields contextual

---

## UX Considerations

1. **Type Selection**: Clear explanation of each pension type
2. **Dynamic Fields**: Show relevant fields for pension type
3. **Contribution Calculator**: Auto-calculate from salary and %
4. **Fund Value Prominence**: Most important number visible
5. **Holdings Optional**: Clear that holdings are optional
6. **Statement Upload**: Prominent upload option
7. **Multiple Pensions**: Easy to add additional pensions
8. **Employer Highlight**: Show employer contribution clearly (free money)
9. **Retirement Age**: Link to projections impact
