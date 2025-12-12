# Feature Specification: Net Worth Module - Overview Dashboard

## Status: Live

## Executive Summary

The Net Worth Overview Dashboard provides a consolidated view of users' complete financial position, combining all assets (properties, savings, investments, pensions) minus all liabilities (mortgages, loans, debts) into a single net worth figure. It includes visualisations showing wealth distribution, comparison with spouse (if linked), and trend tracking over time.

### Elevator Pitch

See your complete financial picture in one place: everything you own minus everything you owe, with clear charts showing where your wealth sits.

### Problem Statement

Users often have financial data scattered across modules and accounts. They need a unified view that shows total assets, total liabilities, and resulting net worth to understand their overall financial health and track progress over time.

### Target Audience

- Primary: All Fynla users wanting consolidated financial view
- Secondary: Married couples wanting combined household view
- Tertiary: Users tracking financial progress over time

### Unique Selling Proposition

Comprehensive UK net worth tracking that automatically aggregates data from all modules, provides spouse comparison when accounts are linked, and shows wealth distribution across asset classes.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Net worth view rate | 80% of users view regularly | Feature tracking |
| Complete data capture | 70% have all asset types | Data analysis |
| Chart engagement | 60% interact with charts | Click tracking |
| Spouse comparison usage | 75% of couples use combined view | Feature tracking |

---

## User Personas

### Persona 1: Michael - Financial Tracker

**Demographics**: 45-year-old tracking financial progress

**Goals**:
- See total net worth
- Track changes over time
- Understand asset distribution

**Pain Points**:
- Data scattered across modules
- Hard to see overall position
- Wants trend visibility

**Success Criteria**: Clear net worth figure, distribution chart, trend data.

### Persona 2: Sarah and James - Married Couple

**Demographics**: Married couple with linked accounts

**Goals**:
- See combined household wealth
- Compare individual positions
- Understand joint assets

**Pain Points**:
- Some assets joint, some individual
- Need both views available
- Want clear comparison

**Success Criteria**: Combined view, individual breakdown, joint asset clarity.

### Persona 3: Rebecca - Wealth Builder

**Demographics**: 35-year-old building wealth

**Goals**:
- Track net worth growth
- See asset allocation
- Identify improvement areas

**Pain Points**:
- Wants to see progress
- Needs clear visualisation
- Wants actionable insights

**Success Criteria**: Clear trend, allocation chart, comparison to goals.

---

## User Stories

### US-01: View Net Worth Summary

**As a** user,
**I want to** see my total net worth,
**So that I** understand my overall financial position.

**Acceptance Criteria**:
- Given I am on Net Worth dashboard
- When I view the overview
- Then I see net worth calculation

**Summary Display**:
- Total Assets
- Total Liabilities
- Net Worth (Assets - Liabilities)

### US-02: View Asset Breakdown

**As a** user,
**I want to** see breakdown of assets,
**So that I** understand where my wealth is.

**Acceptance Criteria**:
- Given I have assets recorded
- When I view asset breakdown
- Then I see totals by category

**Asset Categories**:
- Properties (equity value)
- Savings (cash accounts)
- Investments (portfolio value)
- Pensions (fund values)
- Business interests (when available)
- Chattels (when available)

### US-03: View Liability Breakdown

**As a** user,
**I want to** see breakdown of liabilities,
**So that I** understand my debts.

**Acceptance Criteria**:
- Given I have liabilities recorded
- When I view liability breakdown
- Then I see totals by category

**Liability Categories**:
- Mortgages
- Loans (personal, car, business)
- Credit cards
- Overdrafts
- Student loans
- Other debts

### US-04: View Asset Allocation Chart

**As a** user,
**I want to** see visual asset allocation,
**So that I** quickly understand distribution.

**Acceptance Criteria**:
- Given I have assets recorded
- When I view allocation chart
- Then I see doughnut/pie chart

**Chart Display**:
- Property (percentage and amount)
- Savings (percentage and amount)
- Investments (percentage and amount)
- Pensions (percentage and amount)

### US-05: View Spouse Comparison

**As a** married user with linked account,
**I want to** see comparison with spouse,
**So that I** understand household position.

**Acceptance Criteria**:
- Given I have linked spouse with sharing
- When I view comparison
- Then I see side-by-side view

**Comparison Display**:
- Your assets / Spouse assets
- Your liabilities / Spouse liabilities
- Your net worth / Spouse net worth
- Combined household net worth

### US-06: View Wealth Summary Bar Chart

**As a** married user,
**I want to** see visual wealth comparison,
**So that I** understand relative positions.

**Acceptance Criteria**:
- Given spouse accounts are linked
- When I view wealth chart
- Then I see side-by-side bars

### US-07: View Net Worth Trend

**As a** user tracking progress,
**I want to** see net worth over time,
**So that I** understand my financial trajectory.

**Acceptance Criteria**:
- Given I have historical data
- When I view trend chart
- Then I see net worth changes

**Note**: Current implementation may be limited - requires historical snapshots.

### US-08: Navigate to Asset Details

**As a** user wanting more detail,
**I want to** click through to specific assets,
**So that I** can view or update them.

**Acceptance Criteria**:
- Given I am viewing asset breakdown
- When I click on a category
- Then I navigate to that module

### US-09: View Quick Tabs

**As a** user,
**I want to** access specific asset types via tabs,
**So that I** can quickly view categories.

**Acceptance Criteria**:
- Given I am on Net Worth dashboard
- When I view tabs
- Then I see category tabs

**Tabs Available**:
- Overview (default)
- Retirement
- Property
- Investments
- Cash
- Business Interests (Coming Soon)
- Chattels (Coming Soon)
- Joint History (if linked)

---

## Feature Details

### Net Worth Calculation

**Formula**:
```
Net Worth = Total Assets - Total Liabilities
```

**Asset Aggregation**:
| Source | Value Used |
|--------|------------|
| Properties | Current value (equity = value - mortgage) |
| Savings | Current balance |
| Investments | Current value |
| DC Pensions | Fund value |
| DB Pensions | Capitalised value (annual x 20) |
| State Pension | Not included (no capital value) |

**Liability Aggregation**:
| Source | Value Used |
|--------|------------|
| Mortgages | Current balance |
| All liability types | Current balance |

### Summary Cards

**Total Assets Card**:
- Displays sum of all asset values
- Shows growth indicator (if historical available)
- Links to asset breakdown

**Total Liabilities Card**:
- Displays sum of all liabilities
- Shows reduction indicator (if historical available)
- Links to liability breakdown

**Net Worth Card**:
- Prominent display of net worth
- Largest visual element
- Green if positive, red if negative

### Asset Allocation Chart

**Chart Type**: Doughnut chart

**Segments**:
- Property (typically largest for UK)
- Pensions (second largest typically)
- Investments
- Savings (cash)

**Interaction**:
- Hover/tap for exact values
- Click to navigate to section

### Spouse Comparison

**Individual View**:
- User's assets and liabilities
- User's net worth

**Spouse View**:
- Spouse's assets and liabilities
- Spouse's net worth
- Only visible with permission

**Combined View**:
- Household total assets
- Household total liabilities
- Household net worth
- Joint asset handling

### Joint Asset Handling

**Joint Assets**:
- Each person sees their share (typically 50%)
- Combined view shows full value
- Ownership type determines split

**Tenants in Common**:
- Specific percentage per owner
- Displayed according to share

### Tab Navigation

| Tab | Content |
|-----|---------|
| Overview | Summary and charts |
| Retirement | Pension values and breakdown |
| Property | Property portfolio summary |
| Investments | Investment portfolio summary |
| Cash | Savings account summary |
| Business | Coming Soon placeholder |
| Chattels | Coming Soon placeholder |
| Joint History | Audit trail for joint changes |

---

## User Flows

### Flow 1: View Net Worth

```
Dashboard
    |
    v
Click "Net Worth" or navigate
    |
    v
Net Worth Dashboard
    |
    v
View Overview Tab
    |
    +--> Total Assets: GBP XXX
    +--> Total Liabilities: GBP XXX
    +--> Net Worth: GBP XXX
    |
    v
View Asset Allocation Chart
    |
    +--> Property: X%
    +--> Pensions: X%
    +--> Investments: X%
    +--> Savings: X%
```

### Flow 2: View Spouse Comparison

```
Net Worth Dashboard (Linked Accounts)
    |
    v
View Wealth Summary Chart
    |
    +--> Your bar: GBP XXX
    +--> Spouse bar: GBP XXX
    |
    v
View Combined Total
    |
    +--> Household Net Worth: GBP XXX
```

### Flow 3: Navigate to Detail

```
Net Worth Dashboard
    |
    v
Click "Investments" in chart or breakdown
    |
    v
Investment module opens
    |
    v
View full investment details
```

---

## Edge Cases

### EC-01: No Assets Recorded

**Scenario**: User has not recorded any assets.
**Expected Behaviour**: Show GBP 0 total. Prompt to add assets. Still functional if liabilities exist.

### EC-02: Negative Net Worth

**Scenario**: Liabilities exceed assets.
**Expected Behaviour**: Display negative net worth clearly. Use red/warning colour. No error - valid state.

### EC-03: Spouse Not Sharing

**Scenario**: Linked spouse has not granted view permission.
**Expected Behaviour**: Show user's data only. Note that spouse data not visible. Do not show spouse comparison.

### EC-04: Very Large Values

**Scenario**: User has multi-million pound net worth.
**Expected Behaviour**: Format appropriately (GBP 2.5M). Charts scale correctly.

### EC-05: Partial Data

**Scenario**: User has some categories but not others.
**Expected Behaviour**: Calculate with available data. Chart shows existing categories only.

### EC-06: Joint Asset Only

**Scenario**: All user's assets are joint with spouse.
**Expected Behaviour**: Show 50% (or specified share) in individual view. Full value in combined view.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Total assets calculates correctly | Yes |
| AC-02 | Total liabilities calculates correctly | Yes |
| AC-03 | Net worth equals assets minus liabilities | Yes |
| AC-04 | Asset breakdown by category | Yes |
| AC-05 | Liability breakdown by type | Yes |
| AC-06 | Asset allocation chart displays | Yes |
| AC-07 | Spouse comparison shows when linked | Yes |
| AC-08 | Joint assets handled correctly | Yes |
| AC-09 | Tab navigation works | Yes |
| AC-10 | Click-through to modules works | Yes |

---

## Dependencies

### Upstream Dependencies

- All asset records (properties, savings, investments, pensions)
- All liability records
- Spouse linking and permissions
- Joint account tracking

### Downstream Dependencies

- Main dashboard summary
- Estate planning (IHT calculation)
- Financial statements

---

## Technical Constraints

1. **Aggregation Performance**: Must sum across multiple tables efficiently
2. **Real-time Calculation**: Updates when any component changes
3. **Spouse Data Security**: Only show with permission
4. **Large Number Handling**: Support values into millions

---

## Non-Functional Requirements

### Performance

- Net worth calculation: Under 1 second
- Page load: Under 2 seconds
- Chart rendering: Under 500ms

### Accuracy

- Calculations must be mathematically correct
- Joint assets must split correctly
- No double-counting of assets

### Accessibility

- Charts have data table alternatives
- Numbers formatted clearly
- Clear visual hierarchy

---

## UX Considerations

1. **Net Worth Prominence**: Largest, most visible number
2. **Positive Framing**: Celebrate positive net worth
3. **Chart Interaction**: Hover/tap for details
4. **Spouse Toggle**: Easy switch between views
5. **Navigation Clarity**: Clear path to details
6. **Responsive Design**: Works on mobile
7. **Colour Consistency**: Consistent category colours
8. **Progress Indication**: Show growth where possible
