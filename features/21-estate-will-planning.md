# Feature Specification: Estate Planning Module - Will Planning

## Status: Live

## Executive Summary

The Will Planning feature helps users record their will status, understand intestacy rules if they do not have a will, manage executor information, and record specific bequests. It provides guidance on will importance and integrates with IHT calculations to show the impact of estate distribution on tax liability.

### Elevator Pitch

Document your will status and wishes, or understand what happens to your estate without a will, with clear guidance on why having a will matters.

### Problem Statement

Many UK adults do not have a will or have outdated wills that do not reflect their current wishes. Without proper will planning, estates may not pass as intended and may face higher IHT bills due to suboptimal distribution.

### Target Audience

- Primary: UK adults needing to document will status and bequests
- Secondary: Users without wills who need to understand intestacy rules
- Tertiary: Users updating wills after life changes

### Unique Selling Proposition

Integrated will planning that shows how estate distribution affects IHT, explains UK intestacy rules for those without wills, and provides bequest recording with impact analysis.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Will status recording | 80% of users indicate will status | Data analysis |
| Intestacy education | 60% without wills view intestacy rules | Feature tracking |
| Bequest recording | 40% with wills record bequests | Data analysis |
| Executor recording | 70% with wills record executor | Data completeness |

---

## User Personas

### Persona 1: Helen - Has a Will

**Demographics**: 55-year-old with existing will from 5 years ago

**Goals**:
- Record current will details
- Document executor information
- Record specific bequests
- Check if will needs updating

**Pain Points**:
- Will created years ago
- Circumstances may have changed
- Wants to ensure wishes are clear

**Success Criteria**: Will details recorded, bequests documented, reminder to review.

### Persona 2: Mark - No Will

**Demographics**: 42-year-old who has never made a will

**Goals**:
- Understand what happens without a will
- Learn about intestacy rules
- Motivate to create a will

**Pain Points**:
- Keeps putting off making will
- Unsure what happens without one
- Does not know where to start

**Success Criteria**: Understands intestacy impact, prompted to create will.

### Persona 3: Susan - Specific Bequests

**Demographics**: 68-year-old wanting to leave specific gifts

**Goals**:
- Record charity donations
- Document specific asset bequests
- Understand IHT impact of bequests

**Pain Points**:
- Complex wishes to document
- Multiple beneficiaries
- Wants charity giving to reduce IHT

**Success Criteria**: All bequests recorded with values and recipients.

---

## User Stories

### US-01: Record Will Status

**As a** user,
**I want to** record whether I have a will,
**So that** my estate planning reflects this.

**Acceptance Criteria**:
- Given I am on the Will tab
- When I indicate my will status
- Then it is saved

**Options**:
- Yes, I have a will
- No, I do not have a will
- Prefer not to say

### US-02: Record Will Details

**As a** user with a will,
**I want to** record will details,
**So that** information is documented.

**Acceptance Criteria**:
- Given I indicate I have a will
- When I enter details
- Then they are saved

**Fields**:
- Date will was last updated
- Executor name
- Executor notes/instructions
- Death scenario (for planning)
- Percentage left to spouse

### US-03: View Intestacy Rules

**As a** user without a will,
**I want to** understand intestacy rules,
**So that I** know what happens to my estate.

**Acceptance Criteria**:
- Given I indicate no will
- When I view the Will section
- Then I see intestacy explanation

**Intestacy Display**:
- Rules for married with children
- Rules for married without children
- Rules for single with children
- Rules for single without children
- How user's estate would be distributed

### US-04: Record Bequest

**As a** user with specific wishes,
**I want to** record bequests,
**So that** my wishes are documented.

**Acceptance Criteria**:
- Given I am on the Will tab
- When I add a bequest
- Then it is saved with details

**Bequest Fields**:
- Beneficiary name
- Bequest type
- Amount or percentage
- Description/conditions
- Is charity (for IHT calculation)

### US-05: Select Bequest Type

**As a** user recording bequests,
**I want to** specify bequest type,
**So that** correct treatment applies.

**Acceptance Criteria**:
- Given I am adding a bequest
- When I select type
- Then appropriate fields show

**Bequest Types**:
| Type | Description |
|------|-------------|
| percentage | Percentage of estate |
| specific_amount | Fixed GBP amount |
| specific_asset | Named asset (e.g., "wedding ring") |
| residuary | Remainder after other bequests |

### US-06: Record Executor Information

**As a** user with a will,
**I want to** record executor details,
**So that** this information is available.

**Acceptance Criteria**:
- Given I have a will
- When I enter executor details
- Then they are saved

**Executor Fields**:
- Executor name
- Relationship (if any)
- Contact information (optional)
- Notes/instructions

### US-07: View Charitable Bequest Impact

**As a** user leaving to charity,
**I want to** see IHT impact,
**So that I** understand tax benefits.

**Acceptance Criteria**:
- Given I record charitable bequest
- When I view IHT calculation
- Then charity exemption is shown

**Charity Rules**:
- Charitable bequests exempt from IHT
- 10%+ to charity reduces rate from 40% to 36%

### US-08: Delete Bequest

**As a** user,
**I want to** remove a bequest,
**So that** records stay current.

**Acceptance Criteria**:
- Given I have a bequest recorded
- When I click delete and confirm
- Then bequest is removed

### US-09: View Death Scenario Impact

**As a** married user,
**I want to** see impact of different scenarios,
**So that I** understand distribution options.

**Acceptance Criteria**:
- Given I am married
- When I select death scenario
- Then calculation adjusts

**Death Scenarios**:
- My death only (spouse survives)
- Both die together (simultaneous)
- My death after spouse

---

## Feature Details

### Will Status Options

**Yes, I Have a Will**:
- Shows will detail fields
- Shows bequest recording
- Shows executor information
- Integrates with IHT calculation

**No, I Do Not Have a Will**:
- Shows intestacy rules explanation
- Shows how estate would be distributed
- Prompts to consider making will
- Still allows bequest recording (as wishes)

**Prefer Not to Say**:
- Minimal display
- No specific guidance
- Respects user privacy

### Intestacy Rules (England & Wales)

**Married With Children**:
- Spouse receives first GBP 322,000 (2025)
- Spouse receives personal belongings
- Remainder split 50/50 between spouse and children

**Married Without Children**:
- Spouse receives entire estate

**Single With Children**:
- Children share estate equally
- Grandchildren inherit parent's share if parent deceased

**Single Without Children (order of priority)**:
1. Parents
2. Siblings (or their children)
3. Half-siblings (or their children)
4. Grandparents
5. Aunts/Uncles (or their children)
6. Crown (bona vacantia)

### Bequest Types

**Percentage of Estate**:
- Expressed as % (e.g., 25%)
- Calculated from net estate
- Paid after specific amounts

**Specific Amount**:
- Fixed GBP sum
- Paid before percentage bequests
- May fail if estate insufficient

**Specific Asset**:
- Named item or property
- Description required
- Valued for IHT purposes

**Residuary**:
- Remainder after other bequests
- Often largest bequest
- Subject to IHT if applicable

### Charitable Giving and IHT

**Charity Exemption**:
- Gifts to UK registered charities exempt from IHT
- Reduces taxable estate

**Reduced Rate (36%)**:
- If 10%+ of net estate goes to charity
- Rate reduces from 40% to 36%
- Must meet specific calculation rules

### Data Fields

**Will Record**:
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| has_will | enum | Yes | yes/no/prefer_not_to_say |
| will_updated_date | date | If has will | Past date |
| executor_name | string | No | - |
| executor_notes | text | No | - |
| death_scenario | enum | No | Options below |
| spouse_percentage | integer | No | 0-100 |

**Bequest Record**:
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| beneficiary_name | string | Yes | Non-empty |
| bequest_type | enum | Yes | Valid type |
| amount | decimal | If specific amount | Positive |
| percentage | decimal | If percentage | 0-100 |
| description | text | If specific asset | Non-empty |
| is_charity | boolean | No | Default false |
| conditions | text | No | - |

---

## User Flows

### Flow 1: Record Will Status

```
Estate Planning Dashboard
    |
    v
Will Tab
    |
    v
"Do you have a will?"
    |
    +--> Yes --> Will Details Form
    |              |
    |              +--> Enter date
    |              +--> Enter executor
    |              +--> Save
    |
    +--> No --> View Intestacy Rules
    |            |
    |            +--> See how estate would be distributed
    |            +--> Prompt to make will
    |
    +--> Prefer not to say --> Minimal display
```

### Flow 2: Add Bequest

```
Will Tab
    |
    v
Click "Add Bequest"
    |
    v
Bequest Form
    |
    +--> Enter beneficiary name
    +--> Select bequest type
    |    +--> Percentage: Enter %
    |    +--> Specific Amount: Enter GBP
    |    +--> Specific Asset: Describe asset
    |    +--> Residuary: No amount needed
    +--> Check "Is charity" if applicable
    +--> Enter conditions (optional)
    |
    v
Click "Save"
    |
    v
Bequest added to list
    |
    v
IHT calculation updates
```

### Flow 3: View Intestacy Distribution

```
Will Tab (No Will Selected)
    |
    v
View Intestacy Rules Section
    |
    v
Based on user's status:
    |
    +--> Married with children
    |    |
    |    v
    |    Your estate distribution:
    |    - Spouse: First GBP 322,000 + 50%
    |    - Children: 50% shared
    |
    +--> Other scenarios similarly displayed
```

---

## Edge Cases

### EC-01: Total Bequests Exceed 100%

**Scenario**: User enters percentage bequests totalling over 100%.
**Expected Behaviour**: Validation warning that total exceeds 100%. Allow save but highlight issue.

### EC-02: Specific Amounts Exceed Estate

**Scenario**: Specific amount bequests exceed estate value.
**Expected Behaviour**: Show warning that bequests may partially fail. Explain abatement rules.

### EC-03: No Children (RNRB Impact)

**Scenario**: User has no direct descendants.
**Expected Behaviour**: Note that RNRB may not apply. Explain conditions.

### EC-04: Will Date in Future

**Scenario**: User enters future date for will.
**Expected Behaviour**: Validation error - will date must be past.

### EC-05: Charitable Bequest Just Under 10%

**Scenario**: Charity bequest is 9.5% of estate.
**Expected Behaviour**: Note proximity to 36% rate threshold. Suggest increasing to 10%.

### EC-06: Spouse Not UK Domicile

**Scenario**: User's spouse is non-UK domiciled.
**Expected Behaviour**: Note that unlimited spouse exemption may not apply. Complex rules apply.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Will status can be recorded | Yes |
| AC-02 | Will details captured when yes | Yes |
| AC-03 | Intestacy rules display when no will | Yes |
| AC-04 | Intestacy varies by circumstance | Yes |
| AC-05 | Bequests can be added | Yes |
| AC-06 | All bequest types supported | Yes |
| AC-07 | Charity bequest marked separately | Yes |
| AC-08 | Charity bequests affect IHT calc | Yes |
| AC-09 | Bequests can be deleted | Yes |
| AC-10 | Executor details can be recorded | Yes |

---

## Dependencies

### Upstream Dependencies

- User profile (marital status, children)
- Family members (for intestacy calculation)
- All assets (for bequest valuation)

### Downstream Dependencies

- IHT calculation (charity exemption, bequest values)
- Estate planning recommendations

---

## Technical Constraints

1. **Bequest Calculation**: Must handle different types correctly
2. **Intestacy Rules**: Must match current England & Wales law
3. **Charity Rules**: Must implement 10% threshold correctly
4. **Scotland/NI**: Note different rules apply

---

## Non-Functional Requirements

### Performance

- Page load: Under 1 second
- Save operation: Under 1 second
- IHT recalculation: Under 2 seconds

### Data Integrity

- Bequests linked to user
- Will status properly recorded
- Soft delete for bequests

### Accessibility

- Intestacy rules clearly structured
- Bequest form properly labelled
- Complex concepts explained

---

## UX Considerations

1. **Status First**: Clear will status question
2. **Intestacy Education**: Explain rules in plain language
3. **Personalised Distribution**: Show how user's estate would divide
4. **Bequest Cards**: Visual cards for each bequest
5. **Charity Highlight**: Emphasise tax benefits of charity
6. **Total Tracking**: Show total of all bequests
7. **Reminder**: Prompt to review will periodically
8. **Legal Note**: Advise using solicitor for actual will
