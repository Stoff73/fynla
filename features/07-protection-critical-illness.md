# Feature Specification: Protection Module - Critical Illness and Disability Policies

## Status: Live

## Executive Summary

This feature enables users to record and manage Critical Illness (CI) policies, Disability insurance, and Sickness/Illness policies. These protections provide financial support when the policyholder suffers a serious illness, accident, or prolonged sickness. The feature supports the three main CI policy structures (standalone, accelerated, additional) and two disability coverage types.

### Elevator Pitch

Track your illness and disability protection in one place to ensure you are covered if serious health issues prevent you from working or living normally.

### Problem Statement

Users may have various illness-related protections through different policies but lack visibility into what conditions would trigger payouts and how their coverage compares to their needs. Understanding the differences between standalone CI, accelerated CI, and disability cover is complex.

### Target Audience

- Primary: UK adults with existing critical illness or disability insurance
- Secondary: Users evaluating illness protection as part of comprehensive planning
- Tertiary: Users with employer-provided sickness benefits wanting to track coverage

### Unique Selling Proposition

Clear categorisation of UK illness protection types with explanations of how each works, integrated into overall protection analysis and gap identification.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| CI policy recording | 60% of users with CI policies record them | Data analysis |
| Policy type accuracy | 85% correctly identify policy type | Data review |
| Complete data entry | 75% have cover amount and premium | Data completeness |
| Integration with gap analysis | 90% CI policies feed into analysis | Feature testing |

---

## User Personas

### Persona 1: David - Critical Illness Policy Holder

**Demographics**: 45-year-old with standalone CI policy purchased years ago

**Goals**:
- Record existing CI policy details
- Understand what type of cover he has
- See if coverage is adequate for current situation

**Pain Points**:
- Unsure of exact policy type (accelerated vs standalone)
- Has not reviewed policy since purchase
- Unclear on claim conditions

**Success Criteria**: Policy correctly recorded, type identified, integrated into protection analysis.

### Persona 2: Lisa - Multiple Protection Holder

**Demographics**: 38-year-old with life insurance plus accelerated CI benefit

**Goals**:
- Record both life and CI elements
- Understand how accelerated CI affects life cover
- Track combined protection

**Pain Points**:
- Unsure how to record accelerated CI (separate or combined?)
- Wants to see total critical illness cover

**Success Criteria**: Both elements recorded appropriately, relationship understood.

### Persona 3: Tom - Employer Benefits Recipient

**Demographics**: 32-year-old with workplace sickness and disability benefits

**Goals**:
- Record employer-provided illness benefits
- Understand gap between employer and personal cover
- Track what happens if unable to work

**Pain Points**:
- Employer benefits complex to understand
- Unsure of benefit amounts and durations
- Wants consolidated view with personal policies

**Success Criteria**: Employer and personal policies both recorded, combined view available.

---

## User Stories

### US-01: View Critical Illness Policies

**As a** user with CI cover,
**I want to** see all my critical illness policies,
**So that I** understand my protection against serious illness.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I view the Policy Overview tab
- Then I see all my critical illness policies listed

**Display Information**:
- Policy type (standalone/accelerated/additional)
- Provider name
- Sum assured
- Monthly premium
- Premium frequency
- Policy dates

### US-02: Add Critical Illness Policy

**As a** user,
**I want to** add a critical illness policy,
**So that** my illness protection is recorded.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I click "Add Policy" and select "Critical Illness"
- Then I can enter CI policy details

**Required Fields**:
- Policy type
- Provider name
- Sum assured

**Optional Fields**:
- Monthly premium
- Premium frequency
- Policy start date
- Policy end date
- Policy reference
- Notes

### US-03: Record CI Policy Type

**As a** user with CI cover,
**I want to** record the specific type of CI policy,
**So that** I understand how it interacts with other cover.

**Acceptance Criteria**:
- Given I am adding a CI policy
- When I select the policy type
- Then I understand what each type means

**Policy Types**:
| Type | Description | Relationship to Life Cover |
|------|-------------|---------------------------|
| standalone | Standalone CI | Separate from life cover |
| accelerated | Accelerated CI | Early payment from life sum |
| additional | Additional CI | Extra payment on top of life |

### US-04: View Disability Policies

**As a** user with disability cover,
**I want to** see all my disability policies,
**So that I** understand my protection against accidents/disabilities.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I view the Policy Overview tab
- Then I see all my disability policies listed

### US-05: Add Disability Policy

**As a** user,
**I want to** add a disability policy,
**So that** my accident/disability protection is recorded.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I click "Add Policy" and select "Disability Insurance"
- Then I can enter disability policy details

**Required Fields**:
- Coverage type (accident_only/accident_and_sickness)
- Provider name
- Benefit amount

**Optional Fields**:
- Payment frequency (weekly/monthly/lump_sum)
- Premium amount
- Premium frequency
- Policy dates
- Policy reference

### US-06: View Sickness and Illness Policies

**As a** user with sickness cover,
**I want to** see all my sickness/illness policies,
**So that I** understand my short-term illness protection.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I view the Policy Overview tab
- Then I see all my sickness policies listed

### US-07: Add Sickness/Illness Policy

**As a** user,
**I want to** add a sickness and illness policy,
**So that** my short-term illness protection is recorded.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I click "Add Policy" and select "Sickness and Illness"
- Then I can enter policy details

**Required Fields**:
- Provider name
- Benefit amount

**Optional Fields**:
- Benefit frequency (weekly/monthly/lump_sum)
- Premium amount
- Policy dates
- Policy reference

### US-08: Edit Illness Policies

**As a** user,
**I want to** update illness policy details,
**So that** my records remain accurate.

**Acceptance Criteria**:
- Given I have an illness policy recorded
- When I click edit
- Then I can modify and save changes

### US-09: Delete Illness Policies

**As a** user,
**I want to** remove policies no longer in force,
**So that** my protection view is current.

**Acceptance Criteria**:
- Given I have an illness policy recorded
- When I click delete and confirm
- Then the policy is removed

---

## Feature Details

### Critical Illness Policy Types

**Standalone Critical Illness**:
- Separate policy from life insurance
- Pays out independently on CI diagnosis
- Life cover unaffected by CI claim
- Typically costs more than accelerated
- Provides comprehensive protection

**Accelerated Critical Illness**:
- Combined with life insurance
- CI claim accelerates (advances) part or all of life sum
- If CI claimed, life cover reduced or nil
- More affordable than standalone
- Trade-off between CI and death benefit

**Additional Critical Illness**:
- Also combined with life insurance
- CI pays in addition to life cover
- Both benefits available
- Premium higher than accelerated
- Full protection for both events

### Disability Policy Types

**Accident Only**:
- Covers accidents only (not illness)
- Typically pays lump sum or weekly benefit
- Does not cover gradual illness or degenerative conditions
- Often cheaper than full disability cover
- Good for high-risk occupations

**Accident and Sickness**:
- Covers both accidents and sickness
- Broader protection
- May have exclusions for pre-existing conditions
- More comprehensive coverage
- Higher premiums than accident-only

### Sickness/Illness Policies

**Purpose**: Short-term illness cover, often employer-provided
**Typical Duration**: Limited period (e.g., 13, 26, or 52 weeks)
**Benefit Type**: Weekly or monthly income replacement
**Common Sources**: Employer sick pay schemes, private policies

### Data Fields - Critical Illness

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| policy_type | enum | Yes | standalone/accelerated/additional |
| provider_name | string | Yes | Non-empty |
| sum_assured | decimal | Yes | Positive number |
| monthly_premium | decimal | No | Positive number |
| premium_frequency | enum | No | monthly/quarterly/annually |
| policy_start_date | date | No | Valid date |
| policy_end_date | date | No | Valid date, after start |
| policy_reference | string | No | - |
| notes | text | No | - |

### Data Fields - Disability

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| coverage_type | enum | Yes | accident_only/accident_and_sickness |
| provider_name | string | Yes | Non-empty |
| benefit_amount | decimal | Yes | Positive number |
| benefit_frequency | enum | No | weekly/monthly/lump_sum |
| monthly_premium | decimal | No | Positive number |
| premium_frequency | enum | No | monthly/quarterly/annually |
| policy_start_date | date | No | Valid date |
| policy_end_date | date | No | Valid date |
| policy_reference | string | No | - |
| notes | text | No | - |

### Data Fields - Sickness/Illness

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| provider_name | string | Yes | Non-empty |
| benefit_amount | decimal | Yes | Positive number |
| benefit_frequency | enum | No | weekly/monthly/lump_sum |
| monthly_premium | decimal | No | Positive number |
| policy_start_date | date | No | Valid date |
| policy_end_date | date | No | Valid date |
| policy_reference | string | No | - |
| notes | text | No | - |

---

## User Flows

### Flow 1: Add Critical Illness Policy

```
Protection Dashboard
    |
    v
Click "Add Policy"
    |
    v
Select "Critical Illness"
    |
    v
Policy Form Opens
    |
    +--> Read policy type explanations
    +--> Select type (standalone/accelerated/additional)
    +--> Enter provider name
    +--> Enter sum assured
    +--> Enter premium details (optional)
    +--> Enter dates (optional)
    |
    v
Click "Save"
    |
    v
Policy saved, appears in list
```

### Flow 2: Add Disability Policy

```
Protection Dashboard
    |
    v
Click "Add Policy"
    |
    v
Select "Disability Insurance"
    |
    v
Policy Form Opens
    |
    +--> Select coverage type (accident_only/accident_and_sickness)
    +--> Enter provider name
    +--> Enter benefit amount
    +--> Select benefit frequency
    +--> Enter premium and dates
    |
    v
Click "Save"
    |
    v
Policy saved
```

### Flow 3: Add Sickness Policy

```
Protection Dashboard
    |
    v
Click "Add Policy"
    |
    v
Select "Sickness and Illness"
    |
    v
Policy Form Opens
    |
    +--> Enter provider name
    +--> Enter benefit amount
    +--> Select benefit frequency
    +--> Enter premium and dates
    |
    v
Click "Save"
    |
    v
Policy saved
```

---

## Edge Cases

### EC-01: Accelerated CI Recording

**Scenario**: User has life insurance with accelerated CI and is unsure how to record it.
**Expected Behaviour**: Record as two separate policies (Life Insurance + CI with "accelerated" type). Help text explains the relationship. Gap analysis understands both are from same premium.

### EC-02: Employer-Paid Policies

**Scenario**: Employer provides disability cover with no employee premium contribution.
**Expected Behaviour**: Allow GBP 0 premium. Record employer name as provider.

### EC-03: Benefit Amount Ambiguity

**Scenario**: User unsure whether to enter weekly or annual benefit amount.
**Expected Behaviour**: Form clearly labels "Benefit Amount" with frequency selector. If weekly selected, display shows weekly amount and calculates annual equivalent.

### EC-04: Zero Cover Amount

**Scenario**: User tries to enter GBP 0 cover amount.
**Expected Behaviour**: Validation error - cover amount must be positive.

### EC-05: Lifetime Sickness Policy

**Scenario**: Sickness policy has no end date (continuous cover).
**Expected Behaviour**: Allow blank end date. Policy treated as ongoing.

### EC-06: Multiple CI Policies

**Scenario**: User has standalone CI and accelerated CI from different providers.
**Expected Behaviour**: Both recorded as separate policies. Total CI cover = sum of both.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | All 3 CI policy types selectable | Yes |
| AC-02 | CI policies require type, provider, sum assured | Yes |
| AC-03 | Both disability coverage types available | Yes |
| AC-04 | Disability policies require type, provider, benefit | Yes |
| AC-05 | Sickness policies can be recorded | Yes |
| AC-06 | All policies display in Policy Overview | Yes |
| AC-07 | Benefit frequencies correctly handled | Yes |
| AC-08 | Edit functionality works for all policy types | Yes |
| AC-09 | Delete with confirmation works | Yes |
| AC-10 | Total CI cover calculates correctly | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Protection module framework

### Downstream Dependencies

- Gap Analysis (assesses CI coverage adequacy)
- Strategy recommendations
- Premium summary calculations
- Financial commitments (premiums as expenditure)

---

## Technical Constraints

1. **Currency**: All monetary values in GBP
2. **Frequency Conversion**: System must handle benefit frequency conversions for analysis
3. **Premium Tracking**: Premiums must feed into expenditure tracking
4. **Multiple Policy Types**: Must support recording multiple policies of same type

---

## Non-Functional Requirements

### Performance

- Policy operations: Under 1 second
- List rendering: Under 500ms for 20+ policies

### Data Integrity

- All policies linked to user_id
- Proper foreign key relationships
- Audit trail maintained

### Accessibility

- Form labels properly associated
- Policy type explanations screen-reader friendly
- Clear visual distinction between policy types

---

## UX Considerations

1. **Policy Type Education**: Clear explanations when selecting CI type
2. **Frequency Clarity**: Always show benefit period (weekly/monthly/annually)
3. **Relationship Notes**: For accelerated CI, note impact on life cover
4. **Employer vs Personal**: Allow noting source of cover
5. **Coverage Summary**: Show total CI cover across all types
6. **Premium Impact**: Show combined premium for all illness cover
7. **Quick Reference**: Policy cards show key info at a glance
8. **Status Indicators**: Show active vs expired policies clearly
