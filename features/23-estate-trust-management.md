# Feature Specification: Estate Planning Module - Trust Management

## Status: Live (Basic Recording) / Planned (Advanced Strategy)

## Executive Summary

The Trust Management feature enables users to record existing trusts and understand how trusts can be used in estate planning. Users can record trust details including type, value, beneficiaries, and trustees. The feature provides education on trust types and their IHT implications. Advanced trust strategy recommendations are planned for future release.

### Elevator Pitch

Record your existing trusts and understand how trusts can protect assets, provide for beneficiaries, and potentially reduce Inheritance Tax.

### Problem Statement

Trusts are powerful estate planning tools but are complex and poorly understood. Users need to record existing trusts for accurate estate calculations and understand when trusts might benefit their planning. Advanced trust strategy is often needed but requires professional advice.

### Target Audience

- Primary: Users with existing trusts who need to record them
- Secondary: Users interested in understanding trust options
- Tertiary: Users considering trusts for asset protection or IHT planning

### Unique Selling Proposition

Clear trust recording with comprehensive UK trust type support, educational content explaining when each trust type is appropriate, and integration with IHT calculations to show trust impact on estate tax.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Trust recording | 30% of users with trusts record them | Data analysis |
| Trust type selection | 90% use correct trust type | Data validation |
| Educational engagement | 50% view trust explanations | Content tracking |
| Complete trust data | 70% include value and beneficiaries | Data completeness |

---

## User Personas

### Persona 1: Elizabeth - Existing Trust Holder

**Demographics**: 65-year-old with discretionary trust established years ago

**Goals**:
- Record existing trust details
- Understand current trust value
- Include in estate planning

**Pain Points**:
- Trust set up by solicitor, details unclear
- Unsure how trust affects IHT
- Needs to record for complete picture

**Success Criteria**: Trust recorded with type, value, and beneficiaries.

### Persona 2: David - Life Insurance Trust

**Demographics**: 50-year-old with life policy written in trust

**Goals**:
- Record life insurance trust
- Understand benefit of trust
- Confirm policy outside estate

**Pain Points**:
- Not sure if policy is properly in trust
- Wants confirmation of IHT benefit
- Needs to track for planning

**Success Criteria**: Life insurance trust recorded, understands estate exclusion.

### Persona 3: Susan - Considering Trusts

**Demographics**: 55-year-old exploring estate planning options

**Goals**:
- Understand if trusts could help
- Learn about different trust types
- Decide whether to establish trust

**Pain Points**:
- Trusts seem complicated
- Unsure which type appropriate
- Concerned about costs

**Success Criteria**: Understands trust options, can make informed decision.

---

## User Stories

### US-01: View Trust List

**As a** user with trusts,
**I want to** see all my trusts listed,
**So that I** have complete estate picture.

**Acceptance Criteria**:
- Given I am on the Trust Strategy tab
- When I view my trusts
- Then I see all recorded trusts

**Display Information**:
- Trust name
- Trust type
- Current value
- Beneficiaries
- Creation date

### US-02: Add Trust

**As a** user,
**I want to** record a trust,
**So that** it is included in my planning.

**Acceptance Criteria**:
- Given I am on Trust Strategy tab
- When I click "Add Trust"
- Then I can enter trust details

**Required Fields**:
- Trust name
- Trust type
- Current value

**Optional Fields**:
- Creation date
- Initial value
- Beneficiaries
- Trustees
- Notes

### US-03: Select Trust Type

**As a** user recording trust,
**I want to** specify the trust type,
**So that** correct treatment applies.

**Acceptance Criteria**:
- Given I am adding a trust
- When I select trust type
- Then explanation of type displays

**Trust Types**:
| Type | Display Name |
|------|--------------|
| bare | Bare Trust |
| interest_in_possession | Interest in Possession |
| discretionary | Discretionary Trust |
| accumulation_maintenance | Accumulation & Maintenance |
| life_insurance | Life Insurance Trust |
| discounted_gift | Discounted Gift Trust |
| loan | Loan Trust |
| mixed | Mixed Trust |
| settlor_interested | Settlor-Interested Trust |

### US-04: Record Beneficiaries

**As a** user,
**I want to** record trust beneficiaries,
**So that** trust purpose is documented.

**Acceptance Criteria**:
- Given I am adding a trust
- When I enter beneficiaries
- Then they are saved with trust

**Beneficiary Fields**:
- Name
- Relationship
- Percentage interest (if applicable)

### US-05: Record Trustees

**As a** user,
**I want to** record trustees,
**So that** trust management is documented.

**Acceptance Criteria**:
- Given I am adding a trust
- When I enter trustees
- Then they are saved with trust

**Trustee Fields**:
- Name
- Role (trustee, professional trustee)
- Contact information

### US-06: View Trust Type Education

**As a** user unfamiliar with trusts,
**I want to** understand different trust types,
**So that I** know what each is for.

**Acceptance Criteria**:
- Given I am viewing trust types
- When I read explanations
- Then I understand each type's purpose

### US-07: Edit Trust

**As a** user,
**I want to** update trust details,
**So that** values stay current.

**Acceptance Criteria**:
- Given I have a trust recorded
- When I click edit
- Then I can modify and save

### US-08: Delete Trust

**As a** user,
**I want to** remove trusts no longer relevant,
**So that** records are current.

**Acceptance Criteria**:
- Given I have a trust recorded
- When I click delete and confirm
- Then trust is removed

### US-09: View Trust Impact on IHT

**As a** user,
**I want to** understand how trusts affect IHT,
**So that I** see the planning benefit.

**Acceptance Criteria**:
- Given I have trusts recorded
- When I view IHT Planning
- Then trust impact is shown

---

## Feature Details

### Trust Type Explanations

**Bare Trust**:
- Simplest trust type
- Beneficiary has absolute right to assets
- Trust assets taxed as beneficiary's
- Often used for minors
- IHT: PET when created, in beneficiary's estate

**Interest in Possession**:
- Beneficiary has right to income
- Capital passes to remainderman on death
- Common in wills
- IHT: Value in income beneficiary's estate

**Discretionary Trust**:
- Trustees decide distributions
- Flexible but complex
- Useful for vulnerable beneficiaries
- IHT: Subject to relevant property regime (10-year charges)

**Accumulation & Maintenance**:
- For children/grandchildren
- Income can be accumulated
- Must vest by age 25 (rules changed 2006)
- IHT: Depends on creation date

**Life Insurance Trust**:
- Holds life insurance policy
- Proceeds paid to beneficiaries
- Keeps policy outside estate
- IHT: Policy proceeds not in estate

**Discounted Gift Trust**:
- Settlor retains income rights
- Gift at discount for IHT
- Immediate IHT benefit
- Complex planning tool

**Loan Trust**:
- Settlor loans to trust
- Loan repayable to estate
- Growth outside estate
- Useful for IHT planning

**Mixed Trust**:
- Combination of trust types
- Different treatment for different elements
- Complex administration
- Specialist advice needed

**Settlor-Interested Trust**:
- Settlor can benefit
- Income taxed on settlor
- Limited IHT benefit
- Often used with loan trusts

### Trust and IHT

**Relevant Property Regime**:
- Applies to discretionary trusts
- 6% charge on creation (above NRB)
- 6% charge every 10 years (on value above NRB)
- Exit charges on distributions

**Excluded from Estate**:
- Life insurance in trust
- Gifts to bare trusts (after 7 years)
- Certain trust structures

**Still in Estate**:
- Settlor-interested trusts
- Interest in possession (income beneficiary)
- Bare trust assets (beneficiary's estate)

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| trust_name | string | Yes | Non-empty |
| trust_type | enum | Yes | Valid type |
| current_value | decimal | Yes | Non-negative |
| creation_date | date | No | Past date |
| initial_value | decimal | No | Non-negative |
| beneficiaries | JSON | No | Array |
| trustees | JSON | No | Array |
| notes | text | No | - |

### Integration with IHT

**Life Insurance Trust**:
- Policy value excluded from estate
- Shows in trust list, not IHT assets

**Discretionary Trust**:
- Value shown separately
- Note about 10-year charges

**Other Trusts**:
- Treatment varies by type
- Explanation provided

---

## User Flows

### Flow 1: Add Trust

```
Estate Planning Dashboard
    |
    v
Trust Strategy Tab
    |
    v
Click "Add Trust"
    |
    v
Trust Form
    |
    +--> Enter trust name
    +--> Select trust type
    |    +--> View type explanation
    +--> Enter current value
    +--> Enter creation date
    +--> Add beneficiaries
    +--> Add trustees
    |
    v
Click "Save"
    |
    v
Trust saved
    |
    v
IHT calculation updates (if applicable)
```

### Flow 2: View Trust Education

```
Trust Strategy Tab
    |
    v
View "Trust Types" section
    |
    v
Click on trust type
    |
    v
View detailed explanation:
    - What it is
    - When to use
    - IHT treatment
    - Advantages
    - Disadvantages
```

### Flow 3: Review Trust Impact

```
Trust Strategy Tab
    |
    v
View recorded trusts
    |
    v
See value summary
    |
    v
Navigate to IHT Planning
    |
    v
See trust impact on IHT calculation
```

---

## Edge Cases

### EC-01: Trust Type Unknown

**Scenario**: User unsure of trust type.
**Expected Behaviour**: Allow "mixed" or provide guidance to check trust deed. Recommend professional review.

### EC-02: Trust Value Unknown

**Scenario**: User does not know current trust value.
**Expected Behaviour**: Require value entry. Suggest reviewing annual trust accounts or contacting trustees.

### EC-03: Very Old Trust

**Scenario**: Trust created before 2006 (A&M trust rules).
**Expected Behaviour**: Note that historical rules may apply. Recommend professional review.

### EC-04: Self as Beneficiary

**Scenario**: User is beneficiary of trust they are recording.
**Expected Behaviour**: Note settlor-interested implications. Explain tax treatment.

### EC-05: No Trusts

**Scenario**: User has no trusts but views tab.
**Expected Behaviour**: Show educational content about trusts. Explain when trusts might help. No requirement to add.

### EC-06: Professional Trustee

**Scenario**: Trustee is a company (solicitor firm, bank).
**Expected Behaviour**: Allow recording of company name as trustee. Note professional trustee fees.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Trusts can be added | Yes |
| AC-02 | All 9 trust types available | Yes |
| AC-03 | Trust name and value required | Yes |
| AC-04 | Beneficiaries can be recorded | Yes |
| AC-05 | Trustees can be recorded | Yes |
| AC-06 | Trust type explanations display | Yes |
| AC-07 | Trusts can be edited and deleted | Yes |
| AC-08 | Trust value shows in appropriate place | Yes |
| AC-09 | Life insurance trust excluded from IHT | Yes |
| AC-10 | Educational content accessible | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Life insurance policies (for life insurance trust)
- IHT calculation

### Downstream Dependencies

- Estate planning recommendations
- IHT calculation (trust exclusion)
- Net worth (trust assets)

---

## Technical Constraints

1. **Trust Type Complexity**: Different types have different treatments
2. **IHT Rules**: Must correctly handle trust-specific rules
3. **Beneficiary Storage**: JSON or related table for multiple beneficiaries
4. **Historical Rules**: Pre-2006 trusts have different treatment

---

## Non-Functional Requirements

### Performance

- Trust list load: Under 500ms
- Save operation: Under 1 second
- Education content load: Under 500ms

### Data Integrity

- Trusts linked to user
- Soft delete for audit trail
- Beneficiary data integrity

### Accessibility

- Trust types explained clearly
- Complex concepts in plain language
- Forms properly labelled

---

## UX Considerations

1. **Education First**: Help users understand before recording
2. **Type Selection Help**: Explain each type when selecting
3. **Value Guidance**: Explain where to find trust value
4. **Professional Advice Note**: Emphasise complexity
5. **Beneficiary Cards**: Visual display of beneficiaries
6. **IHT Impact Clarity**: Show how trust affects IHT
7. **Coming Soon Note**: Advanced strategy marked as planned
8. **Quick Reference**: Summary of trust types available
