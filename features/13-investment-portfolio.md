# Feature Specification: Investment Module - Portfolio Overview

## Status: Live

## Executive Summary

The Portfolio Overview feature provides users with a consolidated view of all their investment accounts, including Stocks & Shares ISAs, General Investment Accounts (GIAs), investment bonds, and specialist accounts like VCTs and EIS. It displays total portfolio value, cost basis, overall gains/losses, and asset allocation across all accounts.

### Elevator Pitch

See all your investments in one place with clear performance metrics, from your ISA to your bonds, with instant understanding of what you own and how it is performing.

### Problem Statement

Users typically have investment accounts spread across multiple providers and account types. Without consolidation, they cannot easily understand their total investment position, overall asset allocation, or combined performance.

### Target Audience

- Primary: UK adults with multiple investment accounts seeking consolidated view
- Secondary: ISA investors wanting to track tax-efficient investments
- Tertiary: Users with specialist investments (VCT, EIS, bonds) requiring comprehensive tracking

### Unique Selling Proposition

Support for all UK investment account types with proper categorisation, integrated ISA tracking, and clear display of costs versus current values for accurate gain/loss visibility.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Account recording rate | 70% of users record at least one account | Data analysis |
| Complete data entry | 60% include cost basis | Data completeness |
| Account type accuracy | 95% use correct account type | Data validation |
| Holdings recorded | 50% record individual holdings | Feature tracking |

---

## User Personas

### Persona 1: Simon - Multi-Platform Investor

**Demographics**: 45-year-old with investments across 4 platforms

**Goals**:
- See total portfolio value across all accounts
- Understand overall asset allocation
- Track combined performance

**Pain Points**:
- Logs into multiple platforms
- Cannot see combined position easily
- Difficult to assess overall allocation

**Success Criteria**: All accounts recorded, single view of total portfolio.

### Persona 2: Lisa - ISA Investor

**Demographics**: 35-year-old maximising ISA allowance

**Goals**:
- Track Stocks & Shares ISA
- Monitor ISA allowance usage
- See ISA performance over time

**Pain Points**:
- Wants ISA highlighted separately
- Needs to track tax-efficient wrapper
- Wants to see if maximising allowance

**Success Criteria**: ISA account tracked, contributes to allowance monitoring.

### Persona 3: Richard - Specialist Investment Holder

**Demographics**: 52-year-old with VCT and EIS investments

**Goals**:
- Record specialist investments
- Track costs and current values
- Understand total tax-advantaged position

**Pain Points**:
- Standard trackers do not support VCT/EIS
- Needs to track for tax purposes
- Values may be illiquid

**Success Criteria**: VCT and EIS accounts recorded correctly.

---

## User Stories

### US-01: View All Investment Accounts

**As a** user with investments,
**I want to** see all my investment accounts in one view,
**So that I** understand my total investment position.

**Acceptance Criteria**:
- Given I am on the Investment dashboard
- When I view the Portfolio Overview tab
- Then I see all my investment accounts listed

**Display Information**:
- Account name/provider
- Account type
- Current value
- Cost basis (if recorded)
- Gain/loss
- Ownership type

### US-02: Add Investment Account

**As a** user,
**I want to** add a new investment account,
**So that** my investment records are complete.

**Acceptance Criteria**:
- Given I am on the Investment dashboard
- When I click "Add Account"
- Then I can enter account details and save

**Required Fields**:
- Provider/platform name
- Account type
- Current value

**Optional Fields**:
- Account nickname
- Cost basis
- Ownership type
- Notes

### US-03: Select Account Type

**As a** user with different investment accounts,
**I want to** specify the account type,
**So that** the system handles it appropriately.

**Acceptance Criteria**:
- Given I am adding an investment account
- When I select the account type
- Then the appropriate type is recorded

**Account Types**:
| Value | Display Name | ISA Eligible |
|-------|--------------|--------------|
| isa | Stocks & Shares ISA | Yes |
| gia | General Investment Account | No |
| nsi | NS&I (National Savings) | No |
| onshore_bond | Onshore Investment Bond | No |
| offshore_bond | Offshore Investment Bond | No |
| vct | Venture Capital Trust (VCT) | No |
| eis | Enterprise Investment Scheme (EIS) | No |
| other | Other | No |

### US-04: View Portfolio Summary

**As a** user,
**I want to** see summary of total portfolio,
**So that I** understand overall investment position.

**Acceptance Criteria**:
- Given I have investment accounts recorded
- When I view Portfolio Overview
- Then I see summary cards

**Summary Cards**:
- Total portfolio value
- Total cost basis
- Total gain/loss (amount and percentage)
- Number of accounts
- Number of holdings (if recorded)

### US-05: View Asset Allocation

**As a** user,
**I want to** see how my portfolio is allocated,
**So that I** understand diversification.

**Acceptance Criteria**:
- Given I have holdings recorded
- When I view Portfolio Overview
- Then I see asset allocation breakdown

**Display**: Doughnut/pie chart showing allocation by:
- Asset type (equities, bonds, cash, alternatives)
- Geography (UK, US, international)
- Account type

### US-06: Track Investment Account Performance

**As a** user,
**I want to** see gain/loss on each account,
**So that I** understand performance.

**Acceptance Criteria**:
- Given I have accounts with cost basis
- When I view account details
- Then I see gain/loss

**Calculation**:
```
Gain/Loss = Current Value - Cost Basis
Gain/Loss % = ((Current Value - Cost Basis) / Cost Basis) * 100
```

### US-07: Edit Investment Account

**As a** user,
**I want to** update account details,
**So that** values stay current.

**Acceptance Criteria**:
- Given I have an account recorded
- When I click edit
- Then I can modify and save changes

### US-08: Delete Investment Account

**As a** user,
**I want to** remove accounts I no longer have,
**So that** my portfolio view is current.

**Acceptance Criteria**:
- Given I have an account recorded
- When I click delete and confirm
- Then the account is removed

### US-09: Record Ownership Type

**As a** user with joint investments,
**I want to** record ownership type,
**So that** net worth reflects my share.

**Acceptance Criteria**:
- Given I am adding an account
- When I select ownership type
- Then appropriate share is calculated

**Ownership Types**:
- Individual (100%)
- Joint (50% default, configurable)
- Trust

### US-10: Navigate to Account Holdings

**As a** user wanting more detail,
**I want to** click through to see holdings,
**So that I** can view individual investments.

**Acceptance Criteria**:
- Given I have an account in the list
- When I click on the account
- Then I navigate to account detail with holdings

---

## Feature Details

### Account Type Details

**Stocks & Shares ISA (isa)**:
- Tax-free growth and income
- Contributes to GBP 20,000 annual allowance
- Tracks with Cash ISA for combined allowance

**General Investment Account (gia)**:
- Standard taxable account
- No contribution limits
- Subject to CGT and dividend tax

**NS&I (nsi)**:
- Government-backed savings
- Various product types
- Low risk

**Onshore Investment Bond (onshore_bond)**:
- Life insurance wrapper
- Tax-deferred growth
- 5% withdrawal allowance

**Offshore Investment Bond (offshore_bond)**:
- Held offshore jurisdiction
- Tax-deferred until encashment
- Complex tax treatment

**Venture Capital Trust (vct)**:
- Tax relief on investment
- Tax-free dividends
- Minimum 5-year holding

**Enterprise Investment Scheme (eis)**:
- Income tax relief (30%)
- CGT exemption if held 3+ years
- Higher risk investments

**Other**:
- Catch-all for unlisted account types
- User can describe in notes

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| provider_name | string | Yes | Non-empty |
| account_name | string | No | - |
| account_type | enum | Yes | Valid type |
| account_type_other | string | If other | Non-empty |
| current_value | decimal | Yes | Non-negative |
| cost_basis | decimal | No | Non-negative |
| ownership_type | enum | No | individual/joint/trust |
| joint_owner_id | foreign key | If joint | Valid user |
| notes | text | No | - |

### Summary Calculations

**Total Value**:
```
Total = Sum of (current_value) for all accounts
```

**Total Cost Basis**:
```
Total Cost = Sum of (cost_basis) for all accounts where cost_basis is recorded
```

**Total Gain/Loss**:
```
Total Gain/Loss = Total Value - Total Cost Basis
(Only for accounts with cost basis recorded)
```

### Asset Allocation

If holdings are recorded, aggregates by:
- Asset type: uk_equity, us_equity, international_equity, fund, etf, bond, cash, alternative, property
- Account type: ISA vs non-ISA

**Default Chart**: Shows split by asset type

### ISA Integration

S&S ISA accounts (account_type = 'isa'):
- Flagged as ISA in display
- Value contributes to ISA allowance tracking
- Combined with Cash ISA for total allowance

---

## User Flows

### Flow 1: Add Investment Account

```
Investment Dashboard
    |
    v
Click "Add Account"
    |
    v
Account Form Opens
    |
    +--> Enter provider name
    +--> Select account type
    |    +--> If "other": Enter description
    +--> Enter current value
    +--> Enter cost basis (optional)
    +--> Select ownership type
    |
    v
Click "Save"
    |
    v
Account saved
    |
    v
Portfolio summary updates
```

### Flow 2: Review Portfolio

```
Investment Dashboard
    |
    v
Portfolio Overview Tab
    |
    v
View Summary Cards
    |
    +--> Total Value: GBP XXX
    +--> Total Cost: GBP XXX
    +--> Total Gain/Loss: GBP XXX (+X%)
    |
    v
View Asset Allocation Chart
    |
    v
View Account List
    |
    +--> Each account with value and performance
```

### Flow 3: Navigate to Holdings

```
Portfolio Overview Tab
    |
    v
Find account in list
    |
    v
Click on account name
    |
    v
Account Detail page
    |
    v
View Holdings Tab
    |
    +--> Individual investments within account
```

---

## Edge Cases

### EC-01: No Cost Basis

**Scenario**: User adds account without cost basis.
**Expected Behaviour**: Cannot calculate gain/loss for that account. Display value only. Note "Cost basis not recorded" in gain/loss column.

### EC-02: Zero Value Account

**Scenario**: User wants to record account with GBP 0 value.
**Expected Behaviour**: Allow zero value. Account appears in list with GBP 0.

### EC-03: Negative Gain (Loss)

**Scenario**: Current value less than cost basis.
**Expected Behaviour**: Display as negative gain (loss). Red colour coding. Correct percentage calculation.

### EC-04: Very Large Portfolio

**Scenario**: User has GBP 10M+ portfolio.
**Expected Behaviour**: Handle large numbers without overflow. Consider abbreviated display (GBP 10.5M).

### EC-05: Account Type "Other" Without Description

**Scenario**: User selects "Other" but does not describe.
**Expected Behaviour**: Validation prompts for description if account_type_other is empty when other selected.

### EC-06: VCT/EIS With No Cost Basis

**Scenario**: Tax-advantaged investment without cost tracking.
**Expected Behaviour**: Important to track cost for VCT/EIS tax relief. Prompt user that cost basis recommended.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Investment accounts can be added | Yes |
| AC-02 | All 8 account types available | Yes |
| AC-03 | Provider and value required | Yes |
| AC-04 | Total portfolio value calculates correctly | Yes |
| AC-05 | Gain/loss calculates when cost basis present | Yes |
| AC-06 | Asset allocation chart displays | Yes |
| AC-07 | S&S ISA contributes to allowance tracking | Yes |
| AC-08 | Accounts can be edited and deleted | Yes |
| AC-09 | Click-through to holdings works | Yes |
| AC-10 | Ownership type affects net worth share | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Spouse linking (for joint accounts)

### Downstream Dependencies

- Holdings management
- ISA allowance tracking
- Net Worth calculations
- Asset allocation analysis

---

## Technical Constraints

1. **Currency**: All values in GBP
2. **Precision**: Values to 2 decimal places
3. **Large Numbers**: Handle values up to billions
4. **No Auto-Updates**: Values require manual updates

---

## Non-Functional Requirements

### Performance

- Account list load: Under 1 second
- Summary calculation: Under 500ms
- Chart rendering: Under 500ms

### Data Integrity

- Accounts linked to user_id
- Soft delete preferred
- Value history not maintained (current only)

### Accessibility

- Chart has data table alternative
- Account types explained
- Clear visual hierarchy

---

## UX Considerations

1. **Summary First**: Key numbers prominent
2. **Account Cards**: Visual cards for each account
3. **Performance Colours**: Green for gains, red for losses
4. **Type Badges**: Clear indicator of account type
5. **ISA Highlight**: Visual distinction for ISA accounts
6. **Quick Update**: Easy to update values
7. **Chart Interaction**: Hover/tap for allocation detail
8. **Sort Options**: Sort by value, performance, or name
9. **Filter Options**: Filter by account type
