# Feature Specification: Investment Module - Holdings Management

## Status: Live

## Executive Summary

The Holdings Management feature enables users to record individual investments (holdings) within their investment accounts. Users can track specific stocks, funds, ETFs, bonds, and other assets with details including quantity, current price, cost basis, and annual fees. This provides detailed portfolio visibility beyond account-level totals.

### Elevator Pitch

Track every stock, fund, and ETF in your portfolio with purchase costs and current values to understand exactly what you own and how each investment is performing.

### Problem Statement

Account-level tracking shows total values but not the composition of portfolios. Users need to track individual holdings to understand diversification, monitor specific investments, and track performance at the holding level.

### Target Audience

- Primary: Investors wanting detailed portfolio tracking at the holding level
- Secondary: DIY investors managing individual stock portfolios
- Tertiary: Users wanting accurate asset allocation data

### Unique Selling Proposition

Support for all UK-relevant asset types (including alternatives and property) with polymorphic architecture allowing the same holdings system to work across Investment Accounts and DC Pensions.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Holdings recorded | 50% of accounts have holdings | Data analysis |
| Average holdings per account | 5+ holdings | Data analysis |
| Cost basis completion | 70% have purchase cost | Data completeness |
| Asset type usage | 80% use correct asset type | Data validation |

---

## User Personas

### Persona 1: Jane - DIY Stock Investor

**Demographics**: 38-year-old with self-managed ISA portfolio of 15 stocks

**Goals**:
- Track each stock individually
- See performance by stock
- Maintain accurate cost basis

**Pain Points**:
- Platform statements hard to aggregate
- Needs per-holding performance
- Tracks dividends reinvested

**Success Criteria**: All holdings recorded with costs, individual performance visible.

### Persona 2: Mike - Fund Investor

**Demographics**: 50-year-old with fund-based portfolio

**Goals**:
- Track fund holdings
- Monitor annual fees
- See overall fund allocation

**Pain Points**:
- Multiple funds across platforms
- Fee impact unclear
- Wants simplified tracking

**Success Criteria**: All funds recorded with fees, total fee cost visible.

### Persona 3: Alex - Multi-Asset Investor

**Demographics**: 42-year-old with diverse portfolio including alternatives

**Goals**:
- Track all asset types
- See comprehensive allocation
- Include alternatives and property

**Pain Points**:
- Some platforms do not show alternatives well
- Wants single view of all assets
- Non-standard asset types

**Success Criteria**: All asset types recorded, accurate allocation view.

---

## User Stories

### US-01: View Holdings for Account

**As a** user with investment account,
**I want to** see all holdings within an account,
**So that I** know what investments are in each account.

**Acceptance Criteria**:
- Given I am viewing an investment account
- When I navigate to Holdings tab
- Then I see all holdings for that account

**Display Information**:
- Investment name
- Asset type
- Quantity
- Current price
- Current value
- Cost basis
- Gain/loss
- Annual fee (if recorded)

### US-02: View All Holdings Across Accounts

**As a** user with multiple accounts,
**I want to** see all holdings across all accounts,
**So that I** understand total portfolio composition.

**Acceptance Criteria**:
- Given I have multiple accounts with holdings
- When I view the Holdings tab on Investment dashboard
- Then I see all holdings aggregated

### US-03: Add Holding to Account

**As a** user,
**I want to** add a new holding to an account,
**So that** my portfolio tracking is detailed.

**Acceptance Criteria**:
- Given I am viewing an investment account
- When I click "Add Holding"
- Then I can enter holding details

**Required Fields**:
- Investment name
- Asset type
- Quantity
- Current price per unit

**Optional Fields**:
- Cost basis (total)
- Purchase price per unit
- Annual fee percentage
- Notes

### US-04: Select Asset Type

**As a** user adding holding,
**I want to** specify the asset type,
**So that** allocation is calculated correctly.

**Acceptance Criteria**:
- Given I am adding a holding
- When I select asset type
- Then it is recorded for allocation

**Asset Types**:
| Value | Display Name |
|-------|--------------|
| uk_equity | UK Equity |
| us_equity | US Equity |
| international_equity | International Equity |
| fund | Fund |
| etf | ETF |
| bond | Bond |
| cash | Cash |
| alternative | Alternative |
| property | Property |
| equity | Equity (generic) |

### US-05: Calculate Holding Value

**As a** user tracking holdings,
**I want to** see calculated current value,
**So that I** know what holdings are worth.

**Acceptance Criteria**:
- Given I have entered quantity and price
- When I save or view holding
- Then current value is calculated

**Calculation**:
```
Current Value = Quantity x Current Price Per Unit
```

### US-06: Track Holding Performance

**As a** user,
**I want to** see gain/loss on each holding,
**So that I** understand individual performance.

**Acceptance Criteria**:
- Given holding has cost basis
- When I view holding
- Then gain/loss is shown

**Calculation**:
```
Gain/Loss = Current Value - Cost Basis
Gain/Loss % = ((Current Value - Cost Basis) / Cost Basis) * 100
```

### US-07: Track Annual Fees

**As a** user with funds/ETFs,
**I want to** record annual fees,
**So that I** understand cost drag on returns.

**Acceptance Criteria**:
- Given I am adding a fund/ETF holding
- When I enter annual fee percentage
- Then fee impact can be calculated

**Display**:
- Fee percentage (e.g., 0.25%)
- Fee in GBP (calculated annually)

### US-08: Edit Holding

**As a** user,
**I want to** update holding details,
**So that** prices and quantities stay current.

**Acceptance Criteria**:
- Given I have a holding recorded
- When I click edit
- Then I can modify and save

### US-09: Delete Holding

**As a** user,
**I want to** remove sold holdings,
**So that** portfolio reflects current positions.

**Acceptance Criteria**:
- Given I have a holding recorded
- When I click delete and confirm
- Then holding is removed

### US-10: View Aggregated Allocation

**As a** user,
**I want to** see asset allocation from holdings,
**So that I** understand diversification.

**Acceptance Criteria**:
- Given I have holdings with asset types
- When I view Portfolio Overview
- Then asset allocation reflects holdings

---

## Feature Details

### Asset Type Details

**UK Equity (uk_equity)**:
- Individual UK stocks
- UK-listed shares
- Example: HSBC, Unilever

**US Equity (us_equity)**:
- Individual US stocks
- US-listed shares
- Example: Apple, Microsoft

**International Equity (international_equity)**:
- Non-UK, non-US stocks
- Emerging markets stocks
- Example: Nestle, Toyota

**Fund (fund)**:
- Unit trusts
- OEICs
- Actively managed funds

**ETF (etf)**:
- Exchange-traded funds
- Index trackers
- Example: Vanguard FTSE 100

**Bond (bond)**:
- Corporate bonds
- Government gilts
- Bond funds

**Cash (cash)**:
- Cash within investment account
- Money market funds
- Uninvested cash

**Alternative (alternative)**:
- Commodities
- Infrastructure
- Private equity
- Hedge funds

**Property (property)**:
- REITs
- Property funds
- Property within portfolio

**Equity (equity)**:
- Generic equity category
- Legacy/catch-all

### Data Fields

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| name | string | Yes | Non-empty |
| asset_type | enum | Yes | Valid type |
| quantity | decimal | Yes | Positive |
| current_price | decimal | Yes | Non-negative |
| cost_basis | decimal | No | Non-negative |
| purchase_price | decimal | No | Non-negative |
| annual_fee_percent | decimal | No | 0-10 |
| notes | text | No | - |

### Calculations

**Current Value**:
```
current_value = quantity * current_price
```

**Gain/Loss (if cost basis)**:
```
gain_loss = current_value - cost_basis
gain_loss_percent = ((current_value - cost_basis) / cost_basis) * 100
```

**Annual Fee Amount**:
```
annual_fee_amount = current_value * (annual_fee_percent / 100)
```

### Polymorphic Holdings

Holdings can belong to:
- Investment Account (investment_accounts table)
- DC Pension (dc_pensions table)

**Database Pattern**:
```
holdable_type: 'App\Models\InvestmentAccount' or 'App\Models\DCPension'
holdable_id: ID of parent record
```

This allows pension holdings to use the same holdings management system.

### Aggregation

**By Asset Type**:
- Sum values for each asset type across all holdings
- Display as allocation percentage

**By Geography** (inferred from asset type):
- UK: uk_equity
- US: us_equity
- International: international_equity
- Other: remaining types

**By Account**:
- Sum holdings per account
- Compare to account total value

---

## User Flows

### Flow 1: Add Holding to Account

```
Investment Dashboard
    |
    v
Click on Account
    |
    v
Account Detail Page
    |
    v
Click "Add Holding"
    |
    v
Holding Form
    |
    +--> Enter investment name
    +--> Select asset type
    +--> Enter quantity
    +--> Enter current price
    +--> Enter cost basis (optional)
    +--> Enter annual fee (optional)
    |
    v
Click "Save"
    |
    v
Holding added
    |
    v
Account value updates
```

### Flow 2: View All Holdings

```
Investment Dashboard
    |
    v
Click "Holdings" Tab
    |
    v
View All Holdings Across Accounts
    |
    +--> Sorted by value (default)
    +--> Shows account for each holding
    +--> Shows asset type
    +--> Shows value and performance
```

### Flow 3: Update Holding Prices

```
Account Detail or Holdings Tab
    |
    v
Find holding to update
    |
    v
Click "Edit"
    |
    v
Update current price
    |
    v
Click "Save"
    |
    v
Value recalculates
    |
    v
Performance updates
```

---

## Edge Cases

### EC-01: Zero Quantity

**Scenario**: User tries to enter quantity of 0.
**Expected Behaviour**: Validation error - quantity must be positive. If sold, delete the holding.

### EC-02: Very Small Quantities

**Scenario**: User owns 0.00123 of a share (fractional).
**Expected Behaviour**: Accept fractional quantities to 6 decimal places.

### EC-03: Very High Price

**Scenario**: Share price is GBP 50,000 (e.g., Berkshire).
**Expected Behaviour**: Accept high prices. No upper limit validation.

### EC-04: No Cost Basis

**Scenario**: User does not know original cost.
**Expected Behaviour**: Allow blank. Cannot calculate gain/loss. Note "Cost not recorded".

### EC-05: Fee Higher Than Expected

**Scenario**: User enters 5% annual fee.
**Expected Behaviour**: Accept but may want warning for unusually high fees (>3%).

### EC-06: Holdings Exceed Account Value

**Scenario**: Sum of holdings exceeds account total value.
**Expected Behaviour**: Allow discrepancy (may be timing). Note difference for user review.

### EC-07: Cash Holding

**Scenario**: User records uninvested cash in account.
**Expected Behaviour**: Cash asset type, price always 1.0, quantity equals value.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Holdings can be added to accounts | Yes |
| AC-02 | All 10 asset types available | Yes |
| AC-03 | Name, type, quantity, price required | Yes |
| AC-04 | Value calculates as quantity x price | Yes |
| AC-05 | Gain/loss calculates when cost present | Yes |
| AC-06 | Annual fees can be recorded | Yes |
| AC-07 | Holdings editable and deletable | Yes |
| AC-08 | Aggregated view across accounts works | Yes |
| AC-09 | Asset allocation derives from holdings | Yes |
| AC-10 | Holdings contribute to account total | Yes |

---

## Dependencies

### Upstream Dependencies

- Investment accounts (parent records)
- DC pensions (alternative parent for pension holdings)

### Downstream Dependencies

- Portfolio asset allocation
- Net worth calculations
- Investment performance (future)
- Rebalancing (future)

---

## Technical Constraints

1. **Polymorphic Relationship**: Holdings use morphs for flexible parent
2. **Decimal Precision**: Quantity to 6 places, price to 4 places
3. **No Auto-Pricing**: Prices require manual update
4. **Currency**: All values in GBP

---

## Non-Functional Requirements

### Performance

- Holdings list load: Under 1 second
- Value calculations: Under 500ms
- Allocation chart: Under 500ms

### Data Integrity

- Holdings linked to parent (account or pension)
- Cascading delete if parent deleted
- Soft delete preferred

### Accessibility

- Data table with proper headers
- Sort and filter options accessible
- Asset type icons have text alternatives

---

## UX Considerations

1. **Table View**: Sortable table of holdings
2. **Quick Price Update**: Edit price without full form
3. **Performance Colours**: Visual gain/loss indicators
4. **Asset Type Icons**: Visual icons for asset types
5. **Group by Account**: Option to group holdings by account
6. **Total Row**: Summary totals at bottom of table
7. **Search/Filter**: Find specific holdings
8. **Import Option**: Future consideration for bulk import
9. **Chart Allocation**: Visual breakdown by type
