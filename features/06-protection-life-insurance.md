# Feature Specification: Protection Module - Life Insurance Policies

## Status: Live

## Executive Summary

The Life Insurance Policies feature enables users to record, manage, and analyse their life insurance coverage. It supports five distinct policy types common in the UK market: Term Life, Whole of Life, Decreasing Term, Level Term, and Family Income Benefit. The feature provides a consolidated view of all life cover with total coverage summaries and premium tracking.

### Elevator Pitch

Record all your life insurance policies in one place, understand your total cover, and see how your protection fits into your overall financial plan.

### Problem Statement

Users often have multiple life insurance policies acquired at different times for different purposes. Without a consolidated view, they cannot easily understand their total coverage, identify gaps or overlaps, or manage premium costs effectively.

### Target Audience

- Primary: UK adults with existing life insurance policies seeking consolidated management
- Secondary: Users evaluating their current coverage as part of financial planning
- Tertiary: Users whose policies were identified during gap analysis who need to track new purchases

### Unique Selling Proposition

Comprehensive UK-specific life insurance tracking that understands the differences between policy types (term vs whole of life, level vs decreasing) and integrates with gap analysis to show whether coverage meets needs.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Policy recording rate | 70% of users with policies record them | Data analysis |
| Complete policy data | 80% of policies have all key fields | Data completeness |
| Premium tracking | 90% of policies have premium recorded | Data analysis |
| Document upload usage | 25% of policies added via document upload | Feature tracking |

---

## User Personas

### Persona 1: Sarah - Multiple Policy Holder

**Demographics**: 42-year-old with mortgage protection, workplace life cover, and personal policy

**Goals**:
- See all policies in one place
- Understand total cover amount
- Track when policies expire
- Manage total premium cost

**Pain Points**:
- Policies acquired over years, details scattered
- Unsure of total coverage
- Forgets when policies renew

**Success Criteria**: All three policies recorded, clear view of total cover and combined premium.

### Persona 2: Marcus - New Policy Buyer

**Demographics**: 35-year-old who just purchased first life insurance

**Goals**:
- Record new policy details accurately
- Understand policy type and what it covers
- Keep digital record of policy document

**Pain Points**:
- Unfamiliar with insurance terminology
- Wants to ensure correct recording
- Has policy document but unsure what details matter

**Success Criteria**: Policy recorded correctly, key details captured, policy accessible for reference.

### Persona 3: Jennifer - Policy Reviewer

**Demographics**: 50-year-old reviewing existing coverage ahead of retirement

**Goals**:
- Review all existing policies
- Understand which are still relevant
- Identify policies approaching expiry
- Decide which to keep, increase, or let lapse

**Pain Points**:
- Some policies may be outdated
- Needs clear timeline view
- Wants to see coverage relative to needs

**Success Criteria**: All policies visible with expiry dates, coverage compared to current needs.

---

## User Stories

### US-01: View Life Insurance Policies

**As a** user with life insurance,
**I want to** see all my life insurance policies in one view,
**So that I** understand my total life cover position.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I view the Policy Overview tab
- Then I see all my life insurance policies listed

**Display Information**:
- Policy type
- Provider name
- Sum assured (cover amount)
- Monthly premium
- Policy start date
- Policy end date (if applicable)
- Policy reference number

### US-02: Add Life Insurance Policy

**As a** user,
**I want to** add a new life insurance policy,
**So that** my protection records are complete.

**Acceptance Criteria**:
- Given I am on the Protection dashboard
- When I click "Add Policy" and select "Life Insurance"
- Then I can enter policy details and save

**Required Fields**:
- Policy type (term/whole_of_life/decreasing_term/level_term/family_income_benefit)
- Provider name
- Sum assured

**Optional Fields**:
- Monthly premium
- Premium frequency (monthly/quarterly/annually)
- Policy start date
- Policy end date
- Policy reference number
- Notes

### US-03: Record Different Policy Types

**As a** user with various life policies,
**I want to** record the specific type of each policy,
**So that** the system understands how the cover works.

**Acceptance Criteria**:
- Given I am adding a life insurance policy
- When I select the policy type
- Then appropriate fields are shown for that type

**Policy Types**:
| Type | Description | Has End Date | Decreasing Cover |
|------|-------------|--------------|------------------|
| term | Term Life Insurance | Yes | No |
| whole_of_life | Whole of Life Policy | No | No |
| decreasing_term | Decreasing Life Policy | Yes | Yes |
| level_term | Level Term Life Policy | Yes | No |
| family_income_benefit | Family Income Benefit | Yes | N/A (pays income) |

### US-04: Edit Life Insurance Policy

**As a** user,
**I want to** update policy details when they change,
**So that** my records remain accurate.

**Acceptance Criteria**:
- Given I have a policy recorded
- When I click edit on that policy
- Then I can modify details and save

**Editable Fields**: All fields except system-generated ID

### US-05: Delete Life Insurance Policy

**As a** user,
**I want to** remove policies that are no longer active,
**So that** my protection view reflects current cover only.

**Acceptance Criteria**:
- Given I have a policy recorded
- When I click delete and confirm
- Then the policy is removed from my records

**Confirmation**: "Are you sure you want to delete this policy? This action cannot be undone."

### US-06: View Coverage Summary

**As a** user,
**I want to** see my total life insurance coverage,
**So that I** understand my overall protection level.

**Acceptance Criteria**:
- Given I have life insurance policies recorded
- When I view the coverage summary
- Then I see total cover and total premiums

**Summary Display**:
- Total life cover (sum of all policies)
- Total monthly premiums
- Number of policies
- Coverage by policy type breakdown

### US-07: Upload Policy Document

**As a** user with a policy document,
**I want to** upload it for automatic data extraction,
**So that** I don't have to manually enter all details.

**Acceptance Criteria**:
- Given I want to add a policy
- When I click "Upload Document" and select a file
- Then the system extracts policy details for my review

**Extracted Fields**:
- Provider name
- Policy type
- Sum assured
- Premium amount
- Policy dates
- Reference number

---

## Feature Details

### Policy Type Specifications

**Term Life Insurance (term)**:
- Fixed term coverage (e.g., 10, 15, 20, 25 years)
- Level premium throughout term
- Level sum assured throughout term
- No value at end of term
- End date required

**Whole of Life (whole_of_life)**:
- Lifetime coverage until death
- Guaranteed to pay out eventually
- Usually higher premiums than term
- May have surrender value
- No end date (lifetime)

**Decreasing Term (decreasing_term)**:
- Cover decreases over time
- Typically matches mortgage repayment profile
- Lower premiums than level term
- End date required
- Often used for mortgage protection

**Level Term (level_term)**:
- Fixed sum assured throughout term
- Premium fixed for duration
- Common for family protection
- End date required

**Family Income Benefit (family_income_benefit)**:
- Pays regular income if death occurs during term
- Income paid from death until end of original term
- Sum assured represents total potential payout
- More affordable than equivalent lump sum cover
- End date required

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| policy_type | enum | Yes | Must be valid type |
| provider_name | string | Yes | Non-empty |
| sum_assured | decimal | Yes | Positive number |
| monthly_premium | decimal | No | Positive number |
| premium_frequency | enum | No | monthly/quarterly/annually |
| policy_start_date | date | No | Valid date |
| policy_end_date | date | No* | Valid date, after start |
| policy_reference | string | No | - |
| notes | text | No | - |
| is_in_trust | boolean | No | Default false |
| joint_life | boolean | No | Default false |
| joint_owner_id | foreign key | No | Valid user if joint |

*End date required for term policies, not applicable for whole of life

### Premium Frequency Handling

| Frequency | Display | Monthly Equivalent |
|-----------|---------|-------------------|
| monthly | Monthly | Amount as-is |
| quarterly | Quarterly | Amount / 3 |
| annually | Annual | Amount / 12 |

Premium summary always displays monthly equivalent for comparison.

### Trust Status

**Why Track Trust Status**:
- Policies in trust pay directly to beneficiaries
- Proceeds outside of estate for IHT purposes
- Faster payout (no probate required)

**Display**: Badge or indicator showing "In Trust" status

### Joint Life Policies

**Joint Life First Death**:
- Covers two people, pays on first death
- Common for married couples
- Only one policy record needed
- Links to joint owner

**Joint Life Second Death**:
- Pays on second death
- Used for IHT planning
- Ensures funds available when both gone

---

## User Flows

### Flow 1: Add New Policy Manually

```
Protection Dashboard
    |
    v
Click "Add Policy"
    |
    v
Select "Life Insurance"
    |
    v
Policy Form Opens
    |
    +--> Select policy type
    +--> Enter provider name
    +--> Enter sum assured
    +--> Enter premium details (optional)
    +--> Enter dates (optional)
    +--> Enter reference (optional)
    |
    v
Click "Save"
    |
    +--> [Validation Error] --> Display error
    |
    v
Policy saved
    |
    v
Return to Policy Overview
    |
    v
New policy visible in list
```

### Flow 2: Add Policy via Document Upload

```
Protection Dashboard
    |
    v
Click "Upload Document"
    |
    v
Select/Drag policy document
    |
    v
System processes document
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
Policy saved
    |
    v
Return to Policy Overview
```

### Flow 3: Edit Existing Policy

```
Protection Dashboard
    |
    v
Policy Overview Tab
    |
    v
Find policy in list
    |
    v
Click "Edit"
    |
    v
Form opens with current data
    |
    +--> Make changes
    |
    v
Click "Save"
    |
    v
Changes saved
    |
    v
Return to Policy Overview
```

---

## Edge Cases

### EC-01: Whole of Life with End Date

**Scenario**: User tries to enter end date for Whole of Life policy.
**Expected Behaviour**: End date field disabled or hidden for Whole of Life. Tooltip explains why.

### EC-02: Term Policy without End Date

**Scenario**: User tries to save term policy without end date.
**Expected Behaviour**: Validation error requesting end date. Explain why it matters.

### EC-03: Very High Sum Assured

**Scenario**: User enters sum assured of GBP 10,000,000.
**Expected Behaviour**: Accept the value (legitimate for high earners). No upper limit validation.

### EC-04: Policy End Date in Past

**Scenario**: User records a policy that has already expired.
**Expected Behaviour**: Allow recording but display "Expired" status. Exclude from current coverage totals.

### EC-05: Duplicate Policy Entry

**Scenario**: User accidentally adds same policy twice.
**Expected Behaviour**: No automatic duplicate detection (same provider/amount could be separate policies). User must manage manually.

### EC-06: Family Income Benefit Sum Assured

**Scenario**: User unsure whether to enter annual benefit or total potential payout.
**Expected Behaviour**: Form guidance indicates to enter total sum assured. Help text explains this represents total potential payout if death occurred immediately.

### EC-07: Zero Premium Policy

**Scenario**: Workplace policy with no employee contribution.
**Expected Behaviour**: Allow GBP 0 premium. Note that employer-paid policies are valid.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | All 5 policy types can be selected | Yes |
| AC-02 | Provider name and sum assured required | Yes |
| AC-03 | Premium and dates optional | Yes |
| AC-04 | End date required for term policies only | Yes |
| AC-05 | Policies display in list view | Yes |
| AC-06 | Total coverage calculates correctly | Yes |
| AC-07 | Policies can be edited | Yes |
| AC-08 | Policies can be deleted with confirmation | Yes |
| AC-09 | Document upload extracts policy data | Yes |
| AC-10 | Trust status can be recorded | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication (policies belong to user)
- Document Upload feature (for AI extraction)

### Downstream Dependencies

- Gap Analysis (compares policies to needs)
- Strategy recommendations (based on coverage)
- Estate Planning (policies in trust affect IHT)
- Coverage summary (aggregates all policies)

---

## Technical Constraints

1. **Currency**: All amounts stored in GBP
2. **Date Format**: Dates stored as DATE type, displayed as DD/MM/YYYY
3. **Decimal Precision**: Sum assured and premium to 2 decimal places
4. **File Upload**: Supported formats: PDF, PNG, JPG (see Document Upload spec)

---

## Non-Functional Requirements

### Performance

- Policy list load: Under 1 second
- Policy save: Under 1 second
- Coverage calculation: Under 500ms

### Data Integrity

- Policies linked to user_id
- Soft delete preferred (maintain audit trail)
- Premium frequency stored for accurate totals

### Security

- Policy data accessible only to owner
- Document uploads scanned for security

---

## UX Considerations

1. **Policy Cards**: Visual cards for each policy with key info visible
2. **Type Icons**: Different visual indicator per policy type
3. **Status Badges**: "In Trust", "Expired", "Joint" badges where applicable
4. **Premium Display**: Always show monthly equivalent for easy comparison
5. **Help Text**: Explain each policy type when selecting
6. **Quick Actions**: Edit/Delete accessible from list view
7. **Confirmation**: Require confirmation for deletions
8. **Expiry Warnings**: Highlight policies expiring within 6 months
