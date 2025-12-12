# Feature Specification: User Profile - Personal Information

## Status: Live

## Executive Summary

The User Profile Personal Information feature manages all non-financial personal data including biographical details, domicile status, health information, family members, and the Letter to Spouse functionality. This data forms the foundation for personalised financial planning calculations including age-based projections, tax jurisdictions, protection needs assessments, and estate planning.

### Elevator Pitch

A comprehensive personal profile that captures everything needed to provide personalised UK financial planning advice, from your age and health to your family structure and wishes.

### Problem Statement

Financial planning calculations require accurate personal data to provide meaningful results. Age affects retirement projections, health status influences protection recommendations, family structure determines estate planning needs, and domicile status determines applicable tax rules.

### Target Audience

- Primary: All Fynla users who need to maintain accurate personal information
- Secondary: Users with family members who need estate and protection planning
- Tertiary: Users with complex domicile situations requiring specific tax treatment

### Unique Selling Proposition

Integrated personal data management that automatically feeds into all financial planning calculations across all five modules, with UK-specific domicile handling and a unique Letter to Spouse feature for important guidance.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Profile completeness | 85% of users complete personal section | Data analysis |
| Family member accuracy | 90% include DOB for all members | Data validation |
| Domicile confirmation | 95% confirm domicile status | Data analysis |
| Letter to Spouse usage | 30% of married users create letter | Feature tracking |

---

## User Personas

### Persona 1: James - Family Man

**Demographics**: 42-year-old with spouse and three children

**Goals**:
- Record all family members accurately
- Ensure estate planning accounts for all children
- Leave guidance for spouse in case of emergency

**Pain Points**:
- Needs to keep children's ages current
- Wants spouse to have clear instructions if something happens

**Success Criteria**: All family members recorded with accurate dates of birth, Letter to Spouse completed.

### Persona 2: Maria - Non-UK Origin

**Demographics**: 35-year-old from Portugal, living in UK for 8 years

**Goals**:
- Correctly record domicile status for tax purposes
- Understand deemed domicile rules
- Ensure calculations use appropriate tax treatment

**Pain Points**:
- Uncertain about domicile vs residence distinction
- Needs clarity on when deemed domicile applies

**Success Criteria**: Domicile status correctly recorded, tax calculations use appropriate rules.

### Persona 3: David - Health-Conscious Planner

**Demographics**: 55-year-old with previous health issues, planning protection

**Goals**:
- Record health status accurately
- Understand how health affects insurance options
- Ensure protection recommendations are realistic

**Pain Points**:
- Previous health conditions affect insurability
- Wants honest assessment of options

**Success Criteria**: Health status recorded, protection recommendations reflect health situation.

---

## User Stories

### US-01: View and Edit Personal Information

**As a** user,
**I want to** view and update my personal details,
**So that** my profile reflects current information for accurate calculations.

**Acceptance Criteria**:
- Given I am on the User Profile page
- When I navigate to the Personal Information section
- Then I see my current details with edit capability

**Fields**:
- Full name
- Email address (display only, change requires separate process)
- Date of birth
- Gender (male/female/other)
- Marital status (single/married/divorced/widowed)
- National Insurance number
- Telephone number
- Address (street, city, county, postcode)

### US-02: Manage Domicile Status

**As a** user,
**I want to** record my domicile status,
**So that** tax calculations use the correct rules for my situation.

**Acceptance Criteria**:
- Given I am on the Domicile section
- When I update my domicile information
- Then this is saved and used in tax calculations

**Fields**:
- Country of birth
- UK domiciled (yes/no)
- If not UK-born: Date arrived in UK
- Years of UK residence (calculated automatically)
- Deemed domicile status (calculated from 15-year rule)

**Additional Criteria**:
- Explanation of domicile vs residence provided
- Impact on tax calculations explained
- Deemed domicile rules applied automatically

### US-03: Record Health Information

**As a** user planning protection cover,
**I want to** record my health and lifestyle status,
**So that** protection recommendations are realistic for my situation.

**Acceptance Criteria**:
- Given I am on the Health Information section
- When I update my health details
- Then this informs protection planning recommendations

**Fields**:
- Current health status:
  - Yes (good health, no conditions)
  - Yes, but previous conditions
  - No, previous conditions exist
  - No, existing conditions
  - No, both previous and existing
- Smoking status:
  - Never smoked
  - Quit recently (within 12 months)
  - Quit long ago (over 12 months)
  - Current smoker

### US-04: Add Family Members

**As a** user with family,
**I want to** add and manage family member records,
**So that** protection needs and estate planning account for them.

**Acceptance Criteria**:
- Given I am on the Family Members section
- When I add a new family member
- Then they appear in my family list

**Required Fields**:
- Full name
- Relationship (spouse/child/step_child/parent/other_dependent)
- Date of birth (required)

**Optional Fields**:
- Whether they are a dependant
- Email (for spouse, enables account linking)

### US-05: Edit Family Member

**As a** user,
**I want to** update family member details,
**So that** information remains accurate.

**Acceptance Criteria**:
- Given I have family members recorded
- When I click edit on a family member
- Then I can update their details

### US-06: Remove Family Member

**As a** user,
**I want to** remove a family member from my profile,
**So that** my family structure is accurate.

**Acceptance Criteria**:
- Given I have a family member recorded
- When I click delete and confirm
- Then the family member is removed

**Additional Criteria**:
- Confirmation required before deletion
- Warning if family member is referenced elsewhere (beneficiary, etc.)

### US-07: Link Spouse Account

**As a** married user,
**I want to** link my spouse's Fynla account,
**So that** we can share financial data and view combined position.

**Acceptance Criteria**:
- Given I am adding or editing spouse details
- When I enter their email address
- Then a link request is initiated

**Additional Criteria**:
- If spouse has Fynla account: Link request sent
- If spouse has no account: Account created and invitation sent
- Link must be accepted by spouse
- See Spouse and Family Management spec for full linking details

### US-08: Create Letter to Spouse

**As a** married user,
**I want to** write guidance for my spouse,
**So that** they have clear instructions if something happens to me.

**Acceptance Criteria**:
- Given I am on the Letter to Spouse section
- When I enter text and save
- Then the letter is stored securely

**Content Suggestions**:
- Important contacts (solicitor, accountant, adviser)
- Location of important documents
- Account access information
- Wishes for various matters
- Financial priorities and instructions

**Additional Criteria**:
- Rich text editing available
- Auto-save to prevent loss
- Only visible to user and linked spouse (with permission)
- Last updated date displayed

### US-09: View Letter to Spouse (as Spouse)

**As a** linked spouse with appropriate permissions,
**I want to** view my partner's Letter to Spouse,
**So that I** have the guidance they intended.

**Acceptance Criteria**:
- Given I have a linked account with view permission
- When I access my spouse's Letter to Spouse
- Then I can read their guidance

---

## Feature Details

### Personal Information Section

**Core Fields**:
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Full name | Text | Yes | Non-empty |
| Email | Email | Yes | Valid format, unique |
| Date of birth | Date | Yes | Past date, reasonable age |
| Gender | Select | Yes | male/female/other |
| Marital status | Select | Yes | single/married/divorced/widowed |
| NI Number | Text | No | UK NI format |
| Telephone | Text | No | Valid phone format |
| Street address | Text | No | - |
| City | Text | No | - |
| County | Text | No | - |
| Postcode | Text | No | UK postcode format |

### Domicile Section

**Domicile Types**:
- **UK Domiciled**: Born in UK or acquired UK domicile
- **Non-UK Domiciled**: Born outside UK, not acquired UK domicile
- **Deemed Domiciled**: Non-UK domiciled but resident 15 of last 20 years

**Tax Implications**:
- UK domiciled: Worldwide assets subject to UK IHT
- Non-UK domiciled: Only UK assets subject to UK IHT
- Deemed domiciled: Treated as UK domiciled for IHT from deemed date

**Calculation Logic**:
```
If years_of_uk_residence >= 15 AND NOT uk_domiciled:
    deemed_domicile_status = true
    deemed_domicile_date = arrival_date + 15 years
```

### Health Information Section

**Health Status Impact**:
- Affects protection insurance availability
- Influences premium estimates
- Determines which policy types are realistic

**Smoking Status Impact**:
- Smoker premiums typically 50-100% higher
- "Quit recently" may still attract smoker rates
- "Quit long ago" (12+ months) usually qualifies for non-smoker

### Family Members Section

**Relationship Types**:
| Value | Display | Notes |
|-------|---------|-------|
| spouse | Spouse | Enables account linking |
| child | Child | Affects RNRB eligibility |
| step_child | Step Child | May affect RNRB |
| parent | Parent | For recording purposes |
| other_dependent | Other Dependent | Affects protection needs |

**Why Date of Birth is Required**:
- Children's ages determine education cost calculations
- Ages determine when dependants cease being dependants
- Spouse age affects retirement planning
- Essential for accurate protection needs calculation

### Letter to Spouse Section

**Purpose**: Provides structured guidance for spouse in event of user's death or incapacity.

**Content Areas**:
1. Important Contacts
   - Family solicitor
   - Accountant
   - Financial adviser
   - Insurance companies

2. Document Locations
   - Will location
   - Life insurance policies
   - Property deeds
   - Investment statements

3. Account Information
   - Bank accounts
   - Investment platforms
   - Pension providers
   - Online account access hints (not passwords)

4. Wishes and Instructions
   - Funeral preferences
   - Asset distribution wishes beyond will
   - Messages for family members

5. Financial Priorities
   - Immediate actions to take
   - Bills and obligations
   - Insurance claims to file

**Security**:
- Stored encrypted in database
- Only accessible by user and linked spouse with permissions
- Not included in standard data exports

---

## User Flows

### Flow 1: Update Personal Details

```
User Profile Page
    |
    v
Personal Information Section
    |
    v
Click "Edit"
    |
    v
Form becomes editable
    |
    +--> Update fields as needed
    |
    v
Click "Save"
    |
    +--> [Validation Error] --> Display error, stay on form
    |
    v
Changes saved
    |
    v
Success message displayed
```

### Flow 2: Add Family Member

```
User Profile Page
    |
    v
Family Members Section
    |
    v
Click "Add Family Member"
    |
    v
Family Member Form appears
    |
    +--> Enter name, relationship, DOB
    |
    +--> If spouse: Enter email for linking
    |
    v
Click "Save"
    |
    v
Family member added to list
    |
    +--> If spouse with email: Linking process initiated
```

### Flow 3: Complete Letter to Spouse

```
User Profile Page
    |
    v
Letter to Spouse Section
    |
    v
Click "Edit Letter" or "Create Letter"
    |
    v
Rich text editor opens
    |
    +--> Enter guidance across categories
    |
    +--> Content auto-saves periodically
    |
    v
Click "Save"
    |
    v
Letter saved with timestamp
    |
    v
Confirmation displayed
```

---

## Edge Cases

### EC-01: Date of Birth Change

**Scenario**: User entered incorrect DOB during registration.
**Expected Behaviour**: Allow change, recalculate all age-dependent values (retirement projections, etc.), display confirmation of impact.

### EC-02: Marital Status Change

**Scenario**: User changes from married to divorced.
**Expected Behaviour**: Prompt about spouse account link (unlink?), update estate planning assumptions, recalculate IHT (spouse exemption no longer applies).

### EC-03: Non-UK Domicile Selection

**Scenario**: User indicates they are not UK domiciled.
**Expected Behaviour**: Display explanation of limited IHT scope, calculate IHT only on UK assets, check for deemed domicile qualification.

### EC-04: Family Member with No DOB

**Scenario**: User tries to save family member without date of birth.
**Expected Behaviour**: Validation error prevents save, explanation of why DOB is required.

### EC-05: Spouse Already Has Account

**Scenario**: User enters spouse email, and account already exists.
**Expected Behaviour**: Send link request to existing account, do not create duplicate.

### EC-06: Spouse Email Same as User

**Scenario**: User enters their own email as spouse email.
**Expected Behaviour**: Validation error, cannot link to own account.

### EC-07: Letter to Spouse for Single User

**Scenario**: Single user attempts to access Letter to Spouse.
**Expected Behaviour**: Section hidden or displays note that feature is for married users. Could alternatively allow for any designated person.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Personal information can be viewed and edited | Yes |
| AC-02 | Date of birth calculates age automatically | Yes |
| AC-03 | Domicile status can be set and affects calculations | Yes |
| AC-04 | Deemed domicile calculated from 15-year rule | Yes |
| AC-05 | Health status captured with defined options | Yes |
| AC-06 | Family members can be added with required DOB | Yes |
| AC-07 | Family members can be edited and deleted | Yes |
| AC-08 | Spouse account linking can be initiated | Yes |
| AC-09 | Letter to Spouse can be created and saved | Yes |
| AC-10 | Letter to Spouse visible only to user and linked spouse | Yes |

---

## Dependencies

### Upstream Dependencies

- Registration and Authentication (user must exist)
- Onboarding Wizard (initial data source)

### Downstream Dependencies

- All modules use age calculations from DOB
- Protection Module uses health status
- Estate Planning uses domicile status
- Estate Planning uses family members for beneficiaries
- Spouse and Family Management uses family member data

---

## Technical Constraints

1. **Email Uniqueness**: Email addresses must remain unique across users
2. **Date Validation**: Dates must be validated as sensible (not future DOB, etc.)
3. **NI Number Format**: UK National Insurance number format validation
4. **Postcode Format**: UK postcode format validation
5. **Letter Encryption**: Letter to Spouse should be encrypted at rest

---

## Non-Functional Requirements

### Performance

- Profile load time: Under 1 second
- Save operation: Under 1 second
- Family member list: Handles 20+ members without degradation

### Security

- Personal data encrypted at rest
- Letter to Spouse accessible only to authorised users
- Audit trail for sensitive data changes

### Data Privacy

- Compliance with UK GDPR
- Clear data retention policies
- Right to deletion supported

### Accessibility

- All form fields properly labelled
- Error messages clear and specific
- Keyboard navigation throughout

---

## UX Considerations

1. **Section Organisation**: Logical grouping of related information
2. **Progressive Disclosure**: Advanced fields (domicile) can be collapsed
3. **Inline Help**: Explanations for complex fields (domicile, NI number format)
4. **Validation Feedback**: Real-time validation as user types
5. **Save Confirmation**: Clear feedback when changes saved
6. **Edit Mode**: Clear distinction between view and edit states
7. **Family Member Cards**: Visual cards for each family member
8. **Letter Editor**: Rich text with formatting options for clarity
