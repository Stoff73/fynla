# Feature Specification: Savings Module - ISA Allowance Tracking

## Status: Live

## Executive Summary

The ISA Allowance Tracking feature monitors users' Individual Savings Account usage across both Cash ISAs (Savings module) and Stocks and Shares ISAs (Investment module). It provides a consolidated view of ISA allowance usage for the current tax year, warns users when approaching or exceeding limits, and ensures compliance with UK ISA rules.

### Elevator Pitch

Never accidentally exceed your ISA allowance again. Track your Cash ISA and Stocks & Shares ISA contributions in one place against your annual GBP 20,000 limit.

### Problem Statement

UK individuals have a single ISA allowance that can be split across different ISA types. Without consolidated tracking, users may inadvertently exceed their allowance or fail to maximise tax-efficient saving. Tracking is complicated by the April 6 - April 5 tax year.

### Target Audience

- Primary: UK adults with ISA accounts wanting to track allowance usage
- Secondary: Users planning ISA contributions who need to know remaining allowance
- Tertiary: Users with multiple ISA types needing consolidated view

### Unique Selling Proposition

Cross-module ISA tracking that automatically aggregates Cash ISAs from Savings and Stocks & Shares ISAs from Investments, with clear visualisation of allowance usage and remaining capacity.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| ISA tracking accuracy | 100% correct aggregation | Automated testing |
| Allowance awareness | 80% of ISA holders view tracker | Feature tracking |
| Over-allowance prevention | Zero users exceed via system | Data validation |
| Tax year handling | 100% correct year assignment | Date testing |

---

## User Personas

### Persona 1: Michelle - ISA Maximiser

**Demographics**: 40-year-old using full ISA allowance annually across multiple types

**Goals**:
- Track total ISA usage across Cash and S&S ISAs
- Know exact remaining allowance
- Ensure using full allowance before year end

**Pain Points**:
- Has ISAs with different providers
- Needs to coordinate contributions
- Worried about exceeding limit

**Success Criteria**: Single view of all ISA usage, clear remaining allowance, year-end notification.

### Persona 2: Alex - New ISA User

**Demographics**: 25-year-old with first ISA

**Goals**:
- Understand ISA allowance rules
- Track contributions to single ISA
- Learn about tax year timing

**Pain Points**:
- Unfamiliar with ISA rules
- Unsure about allowance limits
- Does not know tax year dates

**Success Criteria**: Clear education on ISA rules, simple tracking of single ISA.

### Persona 3: Robert - Multi-Account Holder

**Demographics**: 55-year-old with historical ISA portfolio

**Goals**:
- Track this year's contributions (not historical balances)
- Understand difference between balance and contributions
- Monitor allowance usage

**Pain Points**:
- Existing balances much higher than annual allowance
- Needs clarity on new contributions vs historical
- Multiple accounts across years

**Success Criteria**: Clear distinction between balance and current year contributions.

---

## User Stories

### US-01: View Total ISA Allowance

**As a** UK user,
**I want to** see my annual ISA allowance,
**So that I** know my total tax-free saving capacity.

**Acceptance Criteria**:
- Given I am on the ISA tracking section
- When I view the allowance
- Then I see the current tax year allowance (GBP 20,000 for 2025/26)

### US-02: View ISA Allowance Used

**As a** user with ISAs,
**I want to** see how much allowance I have used,
**So that I** know my current position.

**Acceptance Criteria**:
- Given I have ISA accounts recorded
- When I view ISA tracking
- Then I see total used across all ISA types

**Aggregation**:
- Cash ISAs from Savings module
- Stocks & Shares ISAs from Investment module
- Combined total

### US-03: View Remaining ISA Allowance

**As a** user planning contributions,
**I want to** see my remaining allowance,
**So that I** know how much more I can contribute.

**Acceptance Criteria**:
- Given allowance and usage are calculated
- When I view ISA tracking
- Then I see remaining allowance

**Calculation**:
```
Remaining = Annual Allowance - Total Used
```

### US-04: View ISA Breakdown by Type

**As a** user with multiple ISA types,
**I want to** see breakdown by ISA type,
**So that I** understand allocation across types.

**Acceptance Criteria**:
- Given I have different ISA types
- When I view ISA tracking
- Then I see usage by type

**Display**:
| ISA Type | Amount Used | % of Allowance |
|----------|-------------|----------------|
| Cash ISA | GBP X | X% |
| Stocks & Shares ISA | GBP Y | Y% |
| Total | GBP Z | Z% |

### US-05: Receive Allowance Warning

**As a** user approaching limit,
**I want to** receive a warning,
**So that I** do not accidentally exceed allowance.

**Acceptance Criteria**:
- Given my usage is above threshold
- When I view ISA tracking
- Then I see a warning message

**Warning Thresholds**:
| Usage | Warning |
|-------|---------|
| 80%+ | Approaching limit |
| 95%+ | Near limit |
| 100% | Allowance used |
| >100% | Exceeded (requires action) |

### US-06: View Tax Year Information

**As a** user,
**I want to** see the current tax year,
**So that I** understand the allowance period.

**Acceptance Criteria**:
- Given I am viewing ISA tracking
- When I look at the section
- Then I see current tax year (e.g., "2025/26")

**Display**:
- Current tax year: 2025/26
- Tax year dates: 6 April 2025 - 5 April 2026
- Days remaining in tax year

### US-07: View ISA Progress Visualisation

**As a** user,
**I want to** see visual progress toward allowance,
**So that I** quickly understand my position.

**Acceptance Criteria**:
- Given ISA usage is calculated
- When I view ISA tracking
- Then I see visual progress indicator

**Visualisation**:
- Progress bar showing usage vs allowance
- Colour coding (green < 80%, amber 80-95%, red 95%+)
- Segment colours for different ISA types

### US-08: Track Current Year Contributions Only

**As a** user with historical ISA balances,
**I want to** track only current year contributions,
**So that** historical growth does not affect allowance calculation.

**Acceptance Criteria**:
- Given I have ISA with historical balance
- When system calculates allowance used
- Then it uses contribution amounts, not total balance

**Note**: This is a simplification. Full implementation would track contributions separately from growth. Current implementation uses balance as proxy.

---

## Feature Details

### ISA Allowance (2025/26)

| Allowance Type | Amount | Notes |
|----------------|--------|-------|
| Total ISA Allowance | GBP 20,000 | Combined across all ISA types |
| Cash ISA | Within total | Can use full allowance |
| Stocks & Shares ISA | Within total | Can use full allowance |
| Lifetime ISA | GBP 4,000 | Additional, separate (not tracked) |
| Junior ISA | GBP 9,000 | For under-18s (not tracked) |

### Tax Year Dates

**UK Tax Year**: 6 April to 5 April

**Tax Year Determination**:
```
If current date >= April 6: Tax Year = Current Year / Next Year
If current date < April 6: Tax Year = Previous Year / Current Year
```

Example:
- 1 March 2026: Tax Year 2025/26
- 10 April 2026: Tax Year 2026/27

### ISA Type Integration

**From Savings Module (Cash ISAs)**:
```
Query: savings_accounts WHERE is_isa = true
Sum: current_balance
```

**From Investment Module (S&S ISAs)**:
```
Query: investment_accounts WHERE account_type = 'isa'
Sum: current_value
```

**Combined**:
```
Total ISA Used = Cash ISA Balance + S&S ISA Value
```

### Current Limitations

**Note**: The current implementation uses account balances rather than tracking actual contributions. This means:
- Growth within ISA appears as "used" allowance
- Withdrawals reduce apparent usage (but allowance is actually gone)
- Historical balances included in calculation

**Recommended Future Enhancement**: Track actual contributions separately from balance/value.

### Warning System

**Threshold Calculations**:
```
usage_percentage = (total_isa_used / annual_allowance) * 100
```

**Warning Messages**:
| Percentage | Message |
|------------|---------|
| 80-94% | "You have used 80%+ of your ISA allowance. GBP X remaining." |
| 95-99% | "Almost at your ISA limit! Only GBP X remaining." |
| 100% | "You have used your full ISA allowance for this tax year." |
| >100% | "Warning: Your ISA balances exceed the annual allowance. Please review." |

### Display Location

**Primary Location**: Savings Dashboard - dedicated ISA section or tab

**Secondary Locations**:
- Investment module (for S&S ISA users)
- Main dashboard summary (future enhancement)
- User profile financial summary

---

## User Flows

### Flow 1: Check ISA Allowance Status

```
Savings Dashboard
    |
    v
View ISA Allowance Section
    |
    v
See Overview:
    |
    +--> Allowance: GBP 20,000
    +--> Used: GBP X
    +--> Remaining: GBP Y
    |
    v
See Progress Bar
    |
    +--> Visual usage indicator
    |
    v
See Breakdown:
    |
    +--> Cash ISA: GBP A
    +--> S&S ISA: GBP B
```

### Flow 2: Approaching Limit Warning

```
Add to Cash ISA balance
    |
    v
Save account update
    |
    v
System recalculates ISA usage
    |
    v
Usage now at 85%
    |
    v
Warning displayed:
    "You have used 85% of your ISA allowance"
```

### Flow 3: Year-End Planning

```
ISA Allowance Section
    |
    v
See remaining allowance: GBP 5,000
    |
    v
See days remaining in tax year: 30 days
    |
    v
Plan final contributions before April 5
```

---

## Edge Cases

### EC-01: No ISA Accounts

**Scenario**: User has no ISA accounts recorded.
**Expected Behaviour**: Display GBP 0 used, GBP 20,000 remaining. Educational message about ISA benefits.

### EC-02: Over Allowance Display

**Scenario**: Total ISA balances exceed GBP 20,000.
**Expected Behaviour**: Display actual total. Warning message. Note that this may include historical growth or may indicate over-subscription requiring HMRC attention.

### EC-03: Tax Year Boundary

**Scenario**: User views on April 5 (last day of tax year).
**Expected Behaviour**: Show current tax year. Note that new allowance available tomorrow.

### EC-04: Tax Year Transition

**Scenario**: User views on April 6 (first day of new tax year).
**Expected Behaviour**: Reset display for new tax year. Previous year usage no longer shown (historical data maintained but display shows new year).

### EC-05: Only One ISA Type

**Scenario**: User has Cash ISA but no S&S ISA.
**Expected Behaviour**: Show Cash ISA usage only. Breakdown shows GBP 0 for S&S ISA.

### EC-06: Negative Allowance Remaining

**Scenario**: Calculated remaining is negative.
**Expected Behaviour**: Display as "Over by GBP X" rather than negative number. Strong warning message.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Annual allowance displays correctly (GBP 20,000) | Yes |
| AC-02 | Cash ISA balances aggregate correctly | Yes |
| AC-03 | S&S ISA values aggregate correctly | Yes |
| AC-04 | Total ISA used is sum of Cash + S&S | Yes |
| AC-05 | Remaining allowance calculates correctly | Yes |
| AC-06 | Warning shows at 80% threshold | Yes |
| AC-07 | Tax year displays correctly | Yes |
| AC-08 | Progress bar reflects usage percentage | Yes |
| AC-09 | Breakdown by ISA type shown | Yes |
| AC-10 | Zero ISA handled gracefully | Yes |

---

## Dependencies

### Upstream Dependencies

- Savings accounts with ISA designation
- Investment accounts with ISA type
- Tax configuration (allowance amount)

### Downstream Dependencies

- Savings dashboard display
- Investment module ISA tracking
- Strategy recommendations

---

## Technical Constraints

1. **Cross-Module Query**: Must aggregate from both Savings and Investment modules
2. **Tax Year Logic**: Must correctly determine tax year from date
3. **Real-time Calculation**: Updates when any ISA account changes
4. **Allowance Source**: Use TaxConfigService for allowance amount

---

## Non-Functional Requirements

### Performance

- Aggregation calculation: Under 500ms
- Display load: Under 1 second
- Real-time update capability

### Accuracy

- Mathematical correctness essential
- Tax year determination must be accurate
- Allowance must match current tax rules

### Accessibility

- Progress bar accessible to screen readers
- Warnings announced appropriately
- Colour not sole indicator of status

---

## UX Considerations

1. **Clear Numbers**: Prominent display of key figures
2. **Progress Visualisation**: Clear visual of usage vs limit
3. **Colour Coding**: Intuitive red/amber/green for status
4. **Tax Year Clarity**: Always show which tax year applies
5. **Days Remaining**: Show countdown to year end (useful for planning)
6. **Educational Content**: Help users understand ISA rules
7. **Type Breakdown**: Clear split between ISA types
8. **Warning Prominence**: Warnings should be noticeable
9. **Action Guidance**: When limit reached, explain implications
