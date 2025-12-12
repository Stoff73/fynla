# Feature Specification: User Profile - Financial Information

## Status: Live

## Executive Summary

The User Profile Financial Information feature consolidates all financial data views including income and occupation, expenditure tracking, asset summaries, liability overviews, and generated financial statements (Profit and Loss, Cash Flow, Balance Sheet). This provides users with a comprehensive view of their financial position from a personal accounting perspective.

### Elevator Pitch

Your complete financial picture in one place: income, spending, assets, debts, and professional financial statements that show exactly where you stand.

### Problem Statement

Users need to understand their financial position from multiple angles: how much they earn, how much they spend, what they own, what they owe, and whether they are building or depleting wealth. Without consolidated views and standard financial statements, this understanding remains fragmented.

### Target Audience

- Primary: All Fynla users wanting to understand their complete financial position
- Secondary: Users tracking income and expenditure for budgeting purposes
- Tertiary: Users who want professional-style financial statements for planning or adviser discussions

### Unique Selling Proposition

Automatically generated personal financial statements (Profit and Loss, Cash Flow, Balance Sheet) that transform entered data into professional accounting views, alongside detailed income and expenditure tracking.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Income section completeness | 80% enter income details | Data analysis |
| Expenditure tracking | 60% maintain expenditure records | Data analysis |
| Financial statement views | 40% view generated statements | Feature tracking |
| Data accuracy | 90% of statements balance correctly | Calculation verification |

---

## User Personas

### Persona 1: Sophie - Budget-Conscious Planner

**Demographics**: 32-year-old marketing professional, focused on financial control

**Goals**:
- Track all income sources accurately
- Understand monthly expenditure breakdown
- Identify areas to reduce spending

**Pain Points**:
- Loses track of subscription costs
- Wants consolidated view of all spending
- Needs to understand surplus/deficit position

**Success Criteria**: Complete expenditure tracked, monthly surplus calculated, identifies saving opportunities.

### Persona 2: Richard - Business Owner

**Demographics**: 48-year-old company director with multiple income streams

**Goals**:
- Record employment and dividend income separately
- Understand combined household finances
- Have financial statements for adviser meetings

**Pain Points**:
- Complex income structure (salary, dividends, rental)
- Needs clear documentation for planning discussions
- Wants professional presentation of finances

**Success Criteria**: All income types recorded, financial statements generated for adviser review.

### Persona 3: Emma - Financial Clarity Seeker

**Demographics**: 40-year-old professional wanting better financial understanding

**Goals**:
- Understand net worth trend over time
- See how assets and liabilities compare
- Learn whether building or depleting wealth

**Pain Points**:
- Unclear on overall financial progress
- Wants simple view of complex situation
- Needs confidence in financial direction

**Success Criteria**: Balance sheet shows clear position, trend indicates wealth building.

---

## User Stories

### US-01: Record Income and Occupation

**As a** user,
**I want to** record my employment status and income sources,
**So that** retirement projections and protection needs can be calculated accurately.

**Acceptance Criteria**:
- Given I am on the Income and Occupation section
- When I enter my income details
- Then they are saved and used in calculations

**Fields**:
- Employment status (employed/part_time/self_employed/retired/unemployed/other)
- Employer/business name
- Industry sector
- Annual employment income (salary)
- Self-employment income
- Dividend income
- Rental income
- Other income
- Total income (calculated)

### US-02: Record Monthly Expenditure

**As a** user,
**I want to** record my monthly spending by category,
**So that** emergency fund targets and retirement needs can be calculated.

**Acceptance Criteria**:
- Given I am on the Expenditure section
- When I enter amounts across spending categories
- Then totals are calculated automatically

**Categories**:
- Food and groceries
- Transport and fuel
- Healthcare and medical
- Insurance premiums
- Mobile phones
- Internet and TV subscriptions
- Other subscriptions
- Clothing and personal care
- Entertainment and dining
- Holidays and travel
- Pet expenses
- Childcare
- School fees
- School lunches and extras
- Children's activities
- Other expenses

**Calculated Values**:
- Total monthly expenditure
- Total annual expenditure

### US-03: View Asset Summary

**As a** user,
**I want to** see a summary of all my assets,
**So that I** understand the total value of what I own.

**Acceptance Criteria**:
- Given I am on the Assets section of my profile
- When I view the section
- Then I see a summary of all asset types with totals

**Asset Categories**:
- Properties (with current values)
- Savings accounts (with current balances)
- Investment accounts (with current values)
- Pension values (DC fund values, DB capitalised values)
- Business interests (when available)
- Chattels and valuables (when available)

**Display**:
- Individual items listed within categories
- Category totals
- Overall total assets

### US-04: View Liability Summary

**As a** user,
**I want to** see a summary of all my liabilities,
**So that I** understand the total amount I owe.

**Acceptance Criteria**:
- Given I am on the Liabilities section of my profile
- When I view the section
- Then I see all debts with totals

**Liability Categories**:
- Mortgages
- Secured loans
- Personal loans
- Credit cards
- Overdrafts
- Hire purchase/car finance
- Student loans
- Business loans
- Other debts

**Display**:
- Individual items listed
- Category totals
- Overall total liabilities
- Interest rates where recorded

### US-05: View Profit and Loss Statement

**As a** user,
**I want to** see a Profit and Loss statement,
**So that I** understand my income versus expenses.

**Acceptance Criteria**:
- Given I am on the Financial Statements section
- When I view Profit and Loss
- Then I see income minus expenses equals surplus/deficit

**Statement Structure**:
```
INCOME
  Employment Income         XX,XXX
  Self-Employment Income    XX,XXX
  Dividend Income           XX,XXX
  Rental Income             XX,XXX
  Other Income              XX,XXX
  -------------------------
  TOTAL INCOME              XX,XXX

EXPENDITURE
  Essential Expenses        XX,XXX
  Lifestyle Expenses        XX,XXX
  Family Expenses           XX,XXX
  Financial Expenses        XX,XXX
  -------------------------
  TOTAL EXPENDITURE         XX,XXX

  -------------------------
  NET SURPLUS/(DEFICIT)     XX,XXX
```

### US-06: View Cash Flow Statement

**As a** user,
**I want to** see a Cash Flow statement,
**So that I** understand money coming in and going out.

**Acceptance Criteria**:
- Given I am on the Financial Statements section
- When I view Cash Flow
- Then I see inflows and outflows with net position

**Statement Structure**:
```
CASH INFLOWS
  Employment Income         XX,XXX
  Self-Employment Income    XX,XXX
  Investment Income         XX,XXX
  Rental Income             XX,XXX
  Other Income              XX,XXX
  -------------------------
  TOTAL INFLOWS             XX,XXX

CASH OUTFLOWS
  Living Expenses           XX,XXX
  Housing Costs             XX,XXX
  Debt Repayments           XX,XXX
  Savings/Investment        XX,XXX
  Insurance Premiums        XX,XXX
  -------------------------
  TOTAL OUTFLOWS            XX,XXX

  -------------------------
  NET CASH FLOW             XX,XXX
```

### US-07: View Balance Sheet

**As a** user,
**I want to** see a Balance Sheet,
**So that I** see my assets minus liabilities equals net worth.

**Acceptance Criteria**:
- Given I am on the Financial Statements section
- When I view Balance Sheet
- Then I see assets, liabilities, and net worth

**Statement Structure**:
```
ASSETS
  Current Assets
    Cash and Savings        XX,XXX
    Investments             XX,XXX
    -------------------------
    Total Current Assets    XX,XXX

  Fixed Assets
    Property                XX,XXX
    Pensions                XX,XXX
    Other Fixed Assets      XX,XXX
    -------------------------
    Total Fixed Assets      XX,XXX

  -------------------------
  TOTAL ASSETS              XX,XXX

LIABILITIES
  Current Liabilities
    Credit Cards            XX,XXX
    Overdrafts              XX,XXX
    -------------------------
    Total Current           XX,XXX

  Long-term Liabilities
    Mortgages               XX,XXX
    Loans                   XX,XXX
    -------------------------
    Total Long-term         XX,XXX

  -------------------------
  TOTAL LIABILITIES         XX,XXX

  -------------------------
  NET WORTH                 XX,XXX
```

### US-08: View Spouse Expenditure

**As a** user with linked spouse,
**I want to** see combined household expenditure,
**So that I** understand total family spending.

**Acceptance Criteria**:
- Given I have a linked spouse account with sharing enabled
- When I view expenditure
- Then I see both individual and combined totals

---

## Feature Details

### Income and Occupation Section

**Employment Status Options**:
| Value | Display | Notes |
|-------|---------|-------|
| employed | Employed | Full-time employee |
| part_time | Part Time | Part-time employee |
| self_employed | Self Employed | Business owner/contractor |
| retired | Retired | No longer working |
| unemployed | Unemployed | Seeking employment |
| other | Other | Other situations |

**Income Categories**:
| Category | Description | Used For |
|----------|-------------|----------|
| Employment | Salary, wages | Protection needs, retirement |
| Self-Employment | Business income | Human capital calculations |
| Dividend | Investment dividends | Tax planning |
| Rental | Property income | Cash flow |
| Other | Miscellaneous | Total income |

### Expenditure Section

**Category Groupings**:

**Essential Expenses**:
- Food and groceries
- Transport and fuel
- Healthcare and medical
- Insurance premiums

**Lifestyle Expenses**:
- Entertainment and dining
- Holidays and travel
- Clothing and personal care
- Subscriptions

**Family Expenses**:
- Childcare
- School fees
- School lunches and extras
- Children's activities
- Pet expenses

**Communication**:
- Mobile phones
- Internet and TV

**Other**:
- Other expenses

### Asset Summary

**Data Sources**:
- Properties: From property records
- Savings: From savings account records
- Investments: From investment account records
- Pensions: DC fund values + DB capitalised values
- Business: From business interest records (when available)
- Chattels: From chattel records (when available)

**Calculations**:
- Property equity = Current value - Mortgage balance
- Investment value = Sum of all holding values
- Pension value = DC funds + (DB annual * capitalisation factor)

### Liability Summary

**Data Sources**:
- Mortgages: From property mortgage records
- Loans: From liability records
- Credit cards: From liability records
- All 9 liability types pulled from database

**Display Information**:
- Liability name
- Current balance
- Interest rate (if recorded)
- Monthly payment (if recorded)

### Financial Statements

**Generation Frequency**:
- Statements regenerated on each view
- Use current data from all sources
- No historical statement storage (real-time calculation)

**Profit and Loss Logic**:
```
Total Income = Employment + Self-Employment + Dividend + Rental + Other
Total Expenditure = Sum of all expenditure categories
Net Surplus/Deficit = Total Income - Total Expenditure
```

**Cash Flow Logic**:
```
Cash Inflows = All income sources
Cash Outflows = Expenditure + Debt repayments + Savings contributions
Net Cash Flow = Inflows - Outflows
```

**Balance Sheet Logic**:
```
Total Assets = Properties + Savings + Investments + Pensions + Other
Total Liabilities = Mortgages + Loans + Credit Cards + Other Debts
Net Worth = Total Assets - Total Liabilities
```

---

## User Flows

### Flow 1: Update Income Details

```
User Profile Page
    |
    v
Income and Occupation Section
    |
    v
Click "Edit"
    |
    v
Update employment status and income figures
    |
    v
Click "Save"
    |
    v
Totals recalculated
    |
    v
Success message displayed
```

### Flow 2: Record Monthly Expenditure

```
User Profile Page
    |
    v
Expenditure Section
    |
    v
Click "Edit"
    |
    v
Enter amounts for each category
    |
    +--> Totals calculate in real-time
    |
    v
Click "Save"
    |
    v
Monthly and annual totals saved
    |
    v
Emergency fund calculations updated
```

### Flow 3: View Balance Sheet

```
User Profile Page
    |
    v
Financial Statements Section
    |
    v
Click "Balance Sheet" tab
    |
    v
System aggregates all asset and liability data
    |
    v
Balance Sheet displayed with net worth
```

---

## Edge Cases

### EC-01: No Income Recorded

**Scenario**: User has not entered any income.
**Expected Behaviour**: Income section shows GBP 0. Profit and Loss shows GBP 0 income. Warning that projections may be inaccurate.

### EC-02: No Expenditure Recorded

**Scenario**: User has not entered expenditure.
**Expected Behaviour**: Emergency fund calculations use estimated expenditure based on income percentage. Note displayed that actuals preferred.

### EC-03: Spouse with Different Expenditure

**Scenario**: Both spouses have entered expenditure.
**Expected Behaviour**: Show individual totals and combined household total. Avoid double-counting shared expenses.

### EC-04: Negative Net Worth

**Scenario**: Liabilities exceed assets.
**Expected Behaviour**: Balance Sheet shows negative net worth clearly. No error, as this is a valid financial state.

### EC-05: Pension Valuation for DB

**Scenario**: DB pension has no fund value, only annual income promised.
**Expected Behaviour**: Capitalise DB pension (e.g., annual x 20) for Balance Sheet purposes. Note methodology used.

### EC-06: Missing Asset Data

**Scenario**: User has properties but hasn't entered current values.
**Expected Behaviour**: Use purchase price if current value not available. Flag as potentially outdated.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Employment status and income can be recorded | Yes |
| AC-02 | Total income calculates automatically | Yes |
| AC-03 | All expenditure categories available | Yes |
| AC-04 | Monthly and annual totals calculate | Yes |
| AC-05 | Asset summary aggregates all asset types | Yes |
| AC-06 | Liability summary shows all debt types | Yes |
| AC-07 | Profit and Loss statement generates correctly | Yes |
| AC-08 | Cash Flow statement generates correctly | Yes |
| AC-09 | Balance Sheet shows assets - liabilities = net worth | Yes |
| AC-10 | Spouse expenditure combined when applicable | Yes |

---

## Dependencies

### Upstream Dependencies

- Property records (for asset/liability values)
- Savings account records
- Investment account records
- Pension records
- Liability records
- Spouse linking (for combined views)

### Downstream Dependencies

- Emergency Fund calculations (uses expenditure)
- Retirement projections (uses income)
- Protection needs (uses income for human capital)
- All modules displaying net worth

---

## Technical Constraints

1. **Real-time Calculation**: Statements must calculate from current data
2. **Currency Formatting**: All amounts in GBP with proper formatting
3. **Decimal Handling**: Financial calculations to 2 decimal places
4. **Large Numbers**: Handle values into millions without overflow
5. **Negative Display**: Clear formatting for negative values (brackets or minus)

---

## Non-Functional Requirements

### Performance

- Statement generation: Under 2 seconds
- Expenditure save: Under 1 second
- Asset aggregation: Under 1 second

### Accuracy

- All calculations must balance
- No rounding errors in totals
- Audit trail for data sources

### Accessibility

- Tables accessible to screen readers
- Clear headers and row labels
- Sufficient contrast for financial figures

---

## UX Considerations

1. **Category Organisation**: Expenditure grouped logically
2. **Running Totals**: Show totals updating as user enters values
3. **Comparison Views**: Easy comparison of income vs expenditure
4. **Statement Formatting**: Professional appearance matching accounting standards
5. **Export Option**: Consider PDF/print capability for statements
6. **Help Text**: Explain what each statement shows and why it matters
7. **Visual Indicators**: Colour coding for positive/negative values
8. **Trend Information**: Future enhancement to show historical comparison
