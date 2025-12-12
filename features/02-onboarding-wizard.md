# Feature Specification: Onboarding Wizard

## Status: Live

## Executive Summary

The Onboarding Wizard is a 10-step guided process that collects comprehensive financial information from new users. It transforms the potentially overwhelming task of entering all financial data into manageable, logically organised steps. Users can complete all steps or skip any that are not relevant, with the system tracking completion progress.

### Elevator Pitch

A friendly step-by-step guide that helps users enter their complete financial picture in under 30 minutes, with the flexibility to skip and return later.

### Problem Statement

New users face the challenge of entering substantial amounts of financial data before the application can provide meaningful analysis. Without guided structure, users may feel overwhelmed, enter incomplete data, or abandon the process entirely.

### Target Audience

- Primary: Newly registered Fynla users who need to enter their financial information
- Secondary: Existing users revisiting onboarding to add previously skipped information
- Tertiary: Users updating information after significant life changes

### Unique Selling Proposition

Comprehensive financial data collection that feels achievable through clear progress indication, contextual explanations of why each piece of information matters, and the flexibility to skip and return without losing progress.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Wizard completion rate | 70% complete all steps | Step completion tracking |
| Average completion time | Under 30 minutes | Session timing |
| Step abandonment rate | Below 15% per step | Funnel analytics |
| Return rate for skipped steps | 40% return within 7 days | User behaviour tracking |

---

## User Personas

### Persona 1: Thomas - Thorough Planner

**Demographics**: 45-year-old accountant, methodical approach to personal finances

**Goals**:
- Enter all financial information accurately in one session
- Understand why each piece of information is needed
- Have confidence data is being captured correctly

**Pain Points**:
- Frustrated if required to gather documents mid-process
- Wants clear indication of progress
- Needs to understand the purpose of each question

**Current Behaviour**: Prepares documents in advance, completes processes in single sessions.

**Success Criteria**: Completes all 10 steps with comprehensive data in under 45 minutes.

### Persona 2: Claire - Time-Constrained User

**Demographics**: 34-year-old working mother, limited time for administrative tasks

**Goals**:
- Enter essential information quickly
- Skip non-essential steps without penalty
- Return to complete additional steps when time permits

**Pain Points**:
- Cannot commit 30+ minutes in single session
- Needs clear indication of what is essential vs optional
- Frustrated by losing progress

**Current Behaviour**: Completes tasks in short bursts across multiple sessions.

**Success Criteria**: Enters core information (Steps 1-3) in under 10 minutes, returns to complete remaining steps within one week.

### Persona 3: Robert - Recent Life Change

**Demographics**: 58-year-old recently divorced, restructuring finances

**Goals**:
- Enter updated financial situation accurately
- Understand how divorced status affects planning
- Get actionable recommendations quickly

**Pain Points**:
- Some information may be uncertain during transition
- Needs flexibility to update as situation clarifies
- Wants to see planning implications promptly

**Current Behaviour**: Re-evaluating all financial arrangements following life change.

**Success Criteria**: Enters current known information, skips uncertain areas, receives useful initial analysis.

---

## User Stories

### US-01: Welcome and Context

**As a** new user,
**I want to** understand what the onboarding process involves before starting,
**So that I can** prepare appropriately and set realistic time expectations.

**Acceptance Criteria**:
- Given I am a newly registered user
- When I first log in after registration
- Then I see a welcome screen explaining the onboarding process and available features

**Additional Criteria**:
- Welcome screen displays my name
- Clear list of application capabilities
- Security reassurance message
- Single "Continue to Onboarding" button to proceed

### US-02: Personal Information Entry

**As a** user starting onboarding,
**I want to** enter my basic personal details,
**So that** the system can personalise my experience and perform accurate calculations.

**Acceptance Criteria**:
- Given I am on Step 1 of onboarding
- When I enter my personal details and click continue
- Then my information is saved and I proceed to Step 2

**Required Fields**:
- Full name
- Date of birth
- Gender (male/female/other)
- Marital status (single/married/divorced/widowed)
- National Insurance number (optional)
- Telephone number
- Full address (street, city, postcode)

### US-03: Family Member Entry

**As a** user with family,
**I want to** add my spouse/partner, children, and other family members,
**So that** protection needs and estate planning can account for dependants.

**Acceptance Criteria**:
- Given I am on Step 2 of onboarding
- When I add family members with their details
- Then they are saved and appear in my family member list

**Additional Criteria**:
- Spouse entry offers account linking option
- Children require date of birth for age calculations
- Relationship type must be selected (spouse/child/step_child/parent/other_dependent)
- Multiple family members can be added

### US-04: Domicile Information

**As a** user,
**I want to** confirm my residence and domicile status,
**So that** tax calculations use the correct rules for my situation.

**Acceptance Criteria**:
- Given I am on Step 3 of onboarding
- When I confirm my UK domicile status
- Then this information is saved for tax calculations

**Additional Criteria**:
- Country of birth selection
- UK domicile confirmation (yes/no)
- If non-UK origin: date of arrival in UK
- Automatic calculation of years of UK residence
- Explanation of why domicile matters for tax

### US-05: Asset Entry

**As a** user,
**I want to** add my properties, savings, investments, and pensions,
**So that** my net worth and estate value can be calculated.

**Acceptance Criteria**:
- Given I am on Step 4 of onboarding
- When I add assets using the appropriate forms
- Then each asset is saved and reflected in totals

**Asset Types**:
- Properties (with optional mortgage)
- Savings accounts
- Investment accounts
- Pensions (DC, DB, State)
- Business interests (placeholder)
- Chattels and valuables (placeholder)

### US-06: Liability Entry

**As a** user,
**I want to** add my debts and liabilities,
**So that** my net worth accurately reflects what I owe.

**Acceptance Criteria**:
- Given I am on Step 5 of onboarding
- When I add liabilities using the form
- Then each liability is saved and reflected in totals

**Liability Types**:
- Mortgages (if not added with properties)
- Personal loans
- Credit card balances
- Car finance/hire purchase
- Student loans
- Overdrafts
- Business loans
- Other debts

### US-07: Protection Policy Entry

**As a** user with insurance policies,
**I want to** record my existing protection policies,
**So that** coverage gaps can be identified.

**Acceptance Criteria**:
- Given I am on Step 6 of onboarding
- When I add protection policies or confirm I have none
- Then my protection status is recorded

**Policy Types**:
- Life insurance (5 sub-types)
- Critical illness cover (3 sub-types)
- Income protection
- Disability insurance
- Sickness and illness policies

**Additional Criteria**:
- Option to confirm "I have no protection policies"
- Each policy captures provider, cover amount, premium, dates

### US-08: Income Details

**As a** user,
**I want to** record my income sources,
**So that** retirement projections and protection needs can be calculated.

**Acceptance Criteria**:
- Given I am on Step 7 of onboarding
- When I enter my income details
- Then my income information is saved

**Income Categories**:
- Employment status
- Employer/business name
- Industry sector
- Employment income (salary)
- Self-employment income
- Dividend income
- Other income sources

### US-09: Expenditure Entry

**As a** user,
**I want to** record my monthly spending,
**So that** emergency fund targets and retirement needs can be calculated.

**Acceptance Criteria**:
- Given I am on Step 8 of onboarding
- When I enter amounts across spending categories
- Then totals are calculated automatically

**Spending Categories**:
- Food and groceries
- Transport and fuel
- Healthcare
- Insurance premiums
- Mobile phones
- Internet and TV subscriptions
- Other subscriptions
- Clothing and personal care
- Entertainment and dining
- Holidays and travel
- Pet expenses
- Childcare
- School fees and extras
- Children's activities
- Other expenses

### US-10: Will Information

**As a** user,
**I want to** indicate whether I have a will,
**So that** estate planning recommendations can be tailored.

**Acceptance Criteria**:
- Given I am on Step 9 of onboarding
- When I indicate my will status
- Then this is recorded for estate planning

**Options**:
- Yes, I have a will (with date and executor name)
- No, I do not have a will
- Prefer not to say

### US-11: Trust Information

**As a** user with trusts,
**I want to** record any trusts I have established,
**So that** IHT calculations can account for lifetime transfers.

**Acceptance Criteria**:
- Given I am on Step 10 of onboarding
- When I add trust information or skip
- Then the onboarding process completes

**Trust Details**:
- Trust name
- Trust type
- Creation date
- Initial value
- Current value
- Beneficiaries
- Trustees

### US-12: Step Skipping

**As a** user who cannot complete a step,
**I want to** skip and return later,
**So that I** can still access the application without providing all information immediately.

**Acceptance Criteria**:
- Given I am on any onboarding step
- When I click "Skip" or "Skip for now"
- Then I proceed to the next step without error
- And the skipped step is marked for later completion

---

## Feature Details

### Step Structure

| Step | Title | Purpose | Skip Impact |
|------|-------|---------|-------------|
| Welcome | Welcome Screen | Context setting | Cannot skip |
| 1 | Personal Information | User identification, age calculations | Core calculations affected |
| 2 | Family & Beneficiaries | Protection needs, estate planning | Some recommendations limited |
| 3 | Domicile | Tax jurisdiction | May use UK defaults |
| 4 | Assets | Net worth, estate value | Analysis limited |
| 5 | Liabilities | Net worth, estate value | Overstates net worth |
| 6 | Protection | Gap analysis | Cannot analyse coverage |
| 7 | Income | Projections, protection needs | Some calculations unavailable |
| 8 | Expenditure | Emergency fund, retirement | Uses estimates |
| 9 | Will | Estate distribution | Assumes intestacy |
| 10 | Trusts | IHT planning | Not included in IHT |

### Progress Tracking

- Visual progress bar showing completion percentage
- Step indicators showing completed, current, and remaining steps
- Summary of what information has been entered
- Reminder of skipped steps with easy return path

### Data Validation

Each step performs:
- Required field validation
- Format validation (dates, numbers, email)
- Logical validation (dates in past/future as appropriate)
- Real-time feedback before form submission

### Contextual Help

Each step includes:
- Explanation of why this information is needed
- Examples of how it affects calculations
- Tips for finding the information (e.g., "Check your latest P60")
- Links to relevant help content

---

## User Flows

### Flow 1: Complete Sequential Completion

```
Registration Complete
    |
    v
Welcome Screen
    |
    v
Step 1: Personal Information --> Complete
    |
    v
Step 2: Family & Beneficiaries --> Complete
    |
    v
Step 3: Domicile --> Complete
    |
    v
Step 4: Assets --> Complete
    |
    v
Step 5: Liabilities --> Complete
    |
    v
Step 6: Protection --> Complete
    |
    v
Step 7: Income --> Complete
    |
    v
Step 8: Expenditure --> Complete
    |
    v
Step 9: Will --> Complete
    |
    v
Step 10: Trusts --> Complete
    |
    v
Main Dashboard (100% Profile Complete)
```

### Flow 2: Partial Completion with Skips

```
Registration Complete
    |
    v
Welcome Screen
    |
    v
Step 1: Personal Information --> Complete
    |
    v
Step 2: Family --> Skip (no family)
    |
    v
Step 3: Domicile --> Complete
    |
    v
Step 4: Assets --> Add property only
    |
    v
Step 5: Liabilities --> Skip (will return)
    |
    v
Step 6: Protection --> Skip
    |
    v
Step 7: Income --> Complete
    |
    v
Step 8: Expenditure --> Skip
    |
    v
Step 9: Will --> Complete
    |
    v
Step 10: Trusts --> Skip
    |
    v
Main Dashboard (45% Profile Complete)
    |
    v
Prompt to complete remaining steps
```

### Flow 3: Return to Complete Skipped Steps

```
Main Dashboard
    |
    v
Click "Complete Your Profile" or User Profile
    |
    v
See list of incomplete sections
    |
    v
Click specific section (e.g., "Add Expenditure")
    |
    v
Relevant form opens
    |
    v
Complete and save
    |
    v
Return to profile with updated completion percentage
```

---

## Edge Cases

### EC-01: Browser Session Loss Mid-Wizard

**Scenario**: User's browser crashes or closes during onboarding.
**Expected Behaviour**: Upon return, user resumes from the last completed step. Data entered on incomplete step may be lost.
**Mitigation**: Auto-save form data to local storage, restore on return.

### EC-02: Very Long Family List

**Scenario**: User has large extended family to enter.
**Expected Behaviour**: No limit on family members. List scrolls appropriately. Performance remains acceptable.

### EC-03: No Assets or Liabilities

**Scenario**: User genuinely has no assets or significant liabilities.
**Expected Behaviour**: Allow proceeding without adding any items. Note that recommendations will be limited.

### EC-04: Non-UK Domicile

**Scenario**: User indicates they are not UK domiciled.
**Expected Behaviour**: Display note that some calculations may not apply. Allow proceeding with UK-based analysis for UK assets.

### EC-05: Conflicting Data Entry

**Scenario**: User enters married status in Step 1 but adds no spouse in Step 2.
**Expected Behaviour**: Display prompt suggesting spouse addition. Allow proceeding without spouse if user confirms.

### EC-06: Returning After Long Absence

**Scenario**: User starts onboarding, abandons for 6 months, returns.
**Expected Behaviour**: Show current completion status. Suggest reviewing previously entered data for currency.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Welcome screen displays user name and feature list | Yes |
| AC-02 | All 10 steps are accessible in sequence | Yes |
| AC-03 | Each step can be completed or skipped | Yes |
| AC-04 | Progress bar updates accurately | Yes |
| AC-05 | Data persists between steps | Yes |
| AC-06 | Completed wizard redirects to dashboard | Yes |
| AC-07 | Skipped steps can be returned to from profile | Yes |
| AC-08 | Spouse entry offers account linking | Yes |
| AC-09 | Asset entry supports all asset types | Yes |
| AC-10 | Liability entry supports all 9 liability types | Yes |
| AC-11 | Protection entry supports all 5 policy types | Yes |
| AC-12 | Expenditure calculates totals automatically | Yes |

---

## Dependencies

### Upstream Dependencies

- Registration and Authentication (user must be registered and logged in)
- Database tables for all entity types (users, properties, pensions, etc.)

### Downstream Dependencies

- Main Dashboard (receives users completing onboarding)
- All module dashboards (use data entered during onboarding)
- User Profile (displays and allows editing of onboarding data)

---

## Technical Constraints

1. **Form State**: Multi-step wizard must maintain state across steps
2. **Validation**: Server-side validation required for all inputs
3. **File Uploads**: Asset documents may require file upload capability
4. **Spouse Linking**: Email validation and account lookup required
5. **Address Lookup**: Consider UK postcode lookup integration

---

## Non-Functional Requirements

### Performance

- Step transition time: Under 500ms
- Form save time: Under 1 second
- Asset/liability add time: Under 1 second

### Usability

- Mobile-responsive design for all steps
- Clear indication of required vs optional fields
- Autosave capability to prevent data loss
- Keyboard navigation support

### Accessibility

- WCAG 2.1 AA compliance
- Screen reader compatible form labels
- Sufficient colour contrast
- Focus management between steps

---

## UX Considerations

1. **Progressive Disclosure**: Only show fields relevant to user's situation
2. **Smart Defaults**: Pre-populate sensible defaults where appropriate
3. **Validation Timing**: Validate on blur, not on every keystroke
4. **Help Placement**: Contextual help near relevant fields
5. **Step Summary**: Show summary of entered data before proceeding
6. **Exit Warning**: Warn if leaving with unsaved changes
7. **Completion Celebration**: Positive feedback upon wizard completion
8. **Return Path**: Clear navigation to return and complete skipped steps
