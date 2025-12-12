# Feature Specification: Savings Module - Cash Savings Accounts

## Status: Live

## Executive Summary

The Cash Savings Accounts feature enables users to record, manage, and track all their cash-based savings accounts including easy access accounts, notice accounts, fixed-term deposits, and Cash ISAs. It provides a consolidated view of all savings with interest rate tracking, access type categorisation, and ownership management.

### Elevator Pitch

Track all your savings accounts in one place to see your total cash position, compare interest rates, and understand how much you can access immediately versus what is locked away.

### Problem Statement

Users often have multiple savings accounts across different providers, each with different interest rates and access terms. Without a consolidated view, they cannot easily understand their total cash position, identify underperforming accounts, or ensure they are maximising tax-efficient allowances.

### Target Audience

- Primary: UK adults with multiple savings accounts wanting consolidated tracking
- Secondary: Users building emergency funds who need to track progress
- Tertiary: ISA holders wanting to monitor tax-efficient savings

### Unique Selling Proposition

Comprehensive UK savings tracking with built-in understanding of account types (ISA, notice, fixed), automatic ISA allowance integration, and emergency fund designation for holistic financial planning.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Account recording rate | 80% of users record at least one account | Data analysis |
| Complete data entry | 70% include interest rate | Data completeness |
| ISA tracking accuracy | 95% of Cash ISAs correctly identified | Data validation |
| Emergency fund designation | 40% designate emergency fund accounts | Feature tracking |

---

## User Personas

### Persona 1: Emma - Multi-Account Saver

**Demographics**: 35-year-old with accounts at 4 different banks

**Goals**:
- See all accounts in one place
- Compare interest rates across providers
- Identify best-performing accounts

**Pain Points**:
- Logs into multiple banking apps
- Loses track of total savings
- Forgets notice periods on some accounts

**Success Criteria**: All accounts recorded, can see total savings and compare rates.

### Persona 2: James - Emergency Fund Builder

**Demographics**: 28-year-old building first emergency fund

**Goals**:
- Track progress toward emergency fund goal
- Designate specific accounts as emergency fund
- Understand how much is accessible immediately

**Pain Points**:
- Unsure how much emergency fund should be
- Has some savings but not earmarked properly
- Wants clear progress indication

**Success Criteria**: Accounts designated as emergency fund, progress visible.

### Persona 3: Sarah - ISA Maximiser

**Demographics**: 42-year-old using full ISA allowance annually

**Goals**:
- Track Cash ISA balance
- Monitor ISA allowance usage
- Ensure not exceeding limits

**Pain Points**:
- Has both Cash ISA and Stocks & Shares ISA
- Needs to track combined allowance usage
- Worried about accidentally exceeding limit

**Success Criteria**: Cash ISA tracked, combined allowance visible.

---

## User Stories

### US-01: View All Savings Accounts

**As a** user,
**I want to** see all my savings accounts in one view,
**So that I** understand my total cash savings position.

**Acceptance Criteria**:
- Given I am on the Savings dashboard
- When I view the Cash Overview tab
- Then I see all my savings accounts listed

**Display Information**:
- Bank/provider name
- Account type
- Current balance
- Interest rate
- Access type
- ISA status
- Ownership type

### US-02: Add Savings Account

**As a** user,
**I want to** add a new savings account,
**So that** my savings records are complete.

**Acceptance Criteria**:
- Given I am on the Savings dashboard
- When I click "Add Account"
- Then I can enter account details and save

**Required Fields**:
- Bank/provider name
- Current balance

**Optional Fields**:
- Account name/nickname
- Account type
- Interest rate (AER)
- Access type (immediate/notice/fixed)
- Notice period (if notice account)
- Maturity date (if fixed term)
- Is Cash ISA (yes/no)
- Is emergency fund (yes/no)
- Ownership type

### US-03: Record Account Type

**As a** user,
**I want to** specify what type of savings account I have,
**So that** the system understands my access options.

**Acceptance Criteria**:
- Given I am adding a savings account
- When I select the account type
- Then appropriate fields are shown

**Access Types**:
| Type | Description | Additional Fields |
|------|-------------|-------------------|
| immediate | Easy Access | None |
| notice | Notice Account | Notice period (days) |
| fixed | Fixed Term | Maturity date |

### US-04: Track Interest Rate

**As a** user,
**I want to** record interest rates on my accounts,
**So that I can** compare performance.

**Acceptance Criteria**:
- Given I am adding or editing an account
- When I enter the interest rate
- Then it is saved and displayed with the account

**Display**: Rate shown as percentage (e.g., "4.50%")

### US-05: Designate Emergency Fund

**As a** user building emergency fund,
**I want to** mark certain accounts as emergency fund,
**So that** my emergency fund total is calculated.

**Acceptance Criteria**:
- Given I am adding or editing an account
- When I check "This is an emergency fund account"
- Then this account contributes to emergency fund total

### US-06: Record Cash ISA Status

**As a** user with Cash ISA,
**I want to** identify which accounts are ISAs,
**So that** my ISA allowance usage is tracked.

**Acceptance Criteria**:
- Given I am adding or editing an account
- When I check "This is a Cash ISA"
- Then the balance counts toward ISA allowance usage

### US-07: Edit Savings Account

**As a** user,
**I want to** update account details,
**So that** balances and rates stay current.

**Acceptance Criteria**:
- Given I have an account recorded
- When I click edit
- Then I can modify and save changes

### US-08: Delete Savings Account

**As a** user,
**I want to** remove accounts I no longer have,
**So that** my savings view is current.

**Acceptance Criteria**:
- Given I have an account recorded
- When I click delete and confirm
- Then the account is removed

### US-09: View Savings Summary

**As a** user,
**I want to** see summary of all savings,
**So that I** understand total position quickly.

**Acceptance Criteria**:
- Given I have accounts recorded
- When I view the Cash Overview
- Then I see summary cards with totals

**Summary Cards**:
- Total savings
- Emergency fund total
- Number of accounts
- Average interest rate (weighted)

### US-10: Record Joint Accounts

**As a** user with joint accounts,
**I want to** record ownership type,
**So that** net worth reflects my share.

**Acceptance Criteria**:
- Given I am adding a joint account
- When I select "Joint" ownership
- Then I can specify joint owner

**Ownership Types**:
- Individual
- Joint
- Trust

---

## Feature Details

### Account Types and Access

**Immediate Access (Easy Access)**:
- Withdraw any time without penalty
- Typically lower interest rates
- No notice required
- Best for emergency funds

**Notice Accounts**:
- Must give notice before withdrawal
- Typically 30, 60, 90, or 120 days
- Higher rates than easy access
- Balance between access and return

**Fixed Term**:
- Locked until maturity date
- Typically 1, 2, 3, or 5 years
- Highest rates
- Early withdrawal penalties

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| provider_name | string | Yes | Non-empty |
| account_name | string | No | - |
| current_balance | decimal | Yes | Non-negative |
| interest_rate | decimal | No | 0-100 percentage |
| access_type | enum | Yes | immediate/notice/fixed |
| notice_period_days | integer | If notice | Positive |
| maturity_date | date | If fixed | Future date |
| is_isa | boolean | No | Default false |
| is_emergency_fund | boolean | No | Default false |
| ownership_type | enum | No | individual/joint/trust |
| joint_owner_id | foreign key | If joint | Valid user |

### ISA Integration

**Cash ISA Characteristics**:
- Tax-free interest
- Counts toward GBP 20,000 annual ISA allowance
- Allowance shared with Stocks & Shares ISA
- One Cash ISA subscription per tax year

**System Integration**:
- Cash ISA balances tracked separately
- Combined with S&S ISA for total allowance usage
- Warning when approaching/exceeding limit
- Tax year runs April 6 to April 5

### Emergency Fund Designation

**Purpose**:
- Track funds specifically set aside for emergencies
- Calculate emergency fund runway (months of expenses covered)
- Integrate with Savings module emergency fund analysis

**Behaviour**:
- Multiple accounts can be designated
- All designated account balances summed
- Total compared to monthly expenditure for runway calculation

### Ownership and Joint Accounts

**Individual**:
- 100% owned by user
- Full balance in user's net worth

**Joint**:
- Shared with another person
- 50% default split (configurable)
- Creates reciprocal record if joint owner has Fynla account

**Trust**:
- Held in trust
- Link to trust record if applicable

---

## User Flows

### Flow 1: Add Savings Account

```
Savings Dashboard
    |
    v
Click "Add Account"
    |
    v
Account Form Opens
    |
    +--> Enter provider name
    +--> Enter account name (optional)
    +--> Enter current balance
    +--> Enter interest rate
    +--> Select access type
    |    +--> If notice: Enter notice period
    |    +--> If fixed: Enter maturity date
    +--> Check if ISA
    +--> Check if emergency fund
    +--> Select ownership type
    |
    v
Click "Save"
    |
    v
Account saved
    |
    v
Summary cards update
    |
    v
ISA allowance updates (if ISA)
```

### Flow 2: Update Account Balance

```
Savings Dashboard
    |
    v
Find account in list
    |
    v
Click "Edit"
    |
    v
Update balance field
    |
    v
Click "Save"
    |
    v
Totals recalculate
```

### Flow 3: Review Savings Position

```
Savings Dashboard
    |
    v
Cash Overview Tab
    |
    v
View Summary Cards
    |
    +--> Total Savings: GBP XXX
    +--> Emergency Fund: GBP XXX
    +--> Accounts: X
    |
    v
View Account List
    |
    +--> Each account with balance, rate, type
    |
    v
Compare rates across accounts
```

---

## Edge Cases

### EC-01: Zero Balance Account

**Scenario**: User wants to record account with GBP 0 balance.
**Expected Behaviour**: Allow zero balance. Account appears in list. Does not contribute to totals.

### EC-02: Very High Interest Rate

**Scenario**: User enters 15% interest rate.
**Expected Behaviour**: Accept the value (may be legitimate promotional rate). No upper limit validation but could flag as unusual.

### EC-03: Fixed Account Past Maturity

**Scenario**: User records fixed account with maturity date in the past.
**Expected Behaviour**: Allow recording. Display "Matured" status. Suggest updating account type to immediate.

### EC-04: Notice Period Zero

**Scenario**: User selects notice account but enters 0 days.
**Expected Behaviour**: Validation error - notice accounts must have notice period. Suggest immediate access instead.

### EC-05: Joint Account Without Fynla Partner

**Scenario**: Joint account but partner does not use Fynla.
**Expected Behaviour**: Record as joint, enter partner name manually. No reciprocal record created. Note limitation.

### EC-06: Multiple ISAs Same Provider

**Scenario**: User has two Cash ISAs at same bank.
**Expected Behaviour**: Allow multiple records. Both contribute to ISA allowance. Note that only one subscription per year allowed.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Savings accounts can be added | Yes |
| AC-02 | Provider name and balance required | Yes |
| AC-03 | All access types supported | Yes |
| AC-04 | Notice period captured for notice accounts | Yes |
| AC-05 | Maturity date captured for fixed accounts | Yes |
| AC-06 | ISA status can be recorded | Yes |
| AC-07 | Emergency fund designation works | Yes |
| AC-08 | Total savings calculates correctly | Yes |
| AC-09 | Accounts can be edited and deleted | Yes |
| AC-10 | Joint ownership can be recorded | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Spouse linking (for joint accounts)

### Downstream Dependencies

- Emergency Fund analysis
- ISA Allowance tracking (cross-module)
- Net Worth calculations
- Balance Sheet

---

## Technical Constraints

1. **Currency**: All amounts in GBP
2. **Interest Rate**: Store as decimal (e.g., 4.5 for 4.5%)
3. **Date Handling**: Maturity dates validated as future
4. **Balance Updates**: No automatic updates (user manual entry)

---

## Non-Functional Requirements

### Performance

- Account list load: Under 500ms
- Save operation: Under 1 second
- Summary calculation: Under 500ms

### Data Integrity

- Accounts linked to user_id
- Soft delete for audit trail
- Balance history not maintained (current only)

### Accessibility

- Form fields properly labelled
- Interest rate input accepts decimal
- Clear visual distinction between account types

---

## UX Considerations

1. **Account Cards**: Visual cards showing key info at glance
2. **Balance Prominence**: Current balance most visible element
3. **Rate Comparison**: Easy visual comparison of interest rates
4. **Access Indicators**: Icons or badges for access type
5. **ISA Badge**: Clear indicator when account is ISA
6. **Emergency Fund Badge**: Visual marker for EF accounts
7. **Maturity Warning**: Highlight approaching maturity dates
8. **Quick Edit**: Update balance without full form
9. **Sort Options**: Sort by balance, rate, or provider
