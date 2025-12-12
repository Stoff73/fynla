# Feature Specification: Net Worth Module - Properties and Mortgages

## Status: Live

## Executive Summary

The Properties and Mortgages feature enables comprehensive tracking of property assets including main residence, secondary residences, and buy-to-let properties. Users can record property details, associated mortgages, ownership arrangements, and view tax calculations including Stamp Duty, Capital Gains Tax, and rental income tax. The feature supports joint ownership with automatic reciprocal record creation for linked spouse accounts.

### Elevator Pitch

Track your property portfolio with full mortgage details, ownership shares, equity calculations, and UK-specific tax estimates for stamp duty, capital gains, and rental income.

### Problem Statement

Property is typically the largest asset for UK households. Users need comprehensive tracking including purchase details, current valuations, mortgage terms, ownership arrangements, and understanding of tax implications for each property type.

### Target Audience

- Primary: UK homeowners wanting to track property and mortgage
- Secondary: Property investors with multiple properties
- Tertiary: Couples with joint property ownership needing accurate split

### Unique Selling Proposition

Full UK property lifecycle tracking from purchase (with SDLT calculation) through ownership (mortgage tracking, rental income) to potential sale (CGT estimation), with automatic joint owner synchronisation.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Property recording | 75% of homeowners record property | Data analysis |
| Mortgage data complete | 80% include mortgage details | Data completeness |
| Ownership tracking | 90% specify correct ownership type | Data validation |
| Tax calculator usage | 40% use tax calculators | Feature tracking |

---

## User Personas

### Persona 1: Emma - Homeowner with Mortgage

**Demographics**: 38-year-old with main residence and mortgage

**Goals**:
- Track property value and mortgage
- Understand equity position
- See amortisation schedule

**Pain Points**:
- Mortgage statement hard to understand
- Unsure of exact equity
- Wants to track progress

**Success Criteria**: Property and mortgage recorded, equity visible, amortisation clear.

### Persona 2: David - Property Investor

**Demographics**: 52-year-old with main home and two buy-to-lets

**Goals**:
- Track all properties
- Monitor rental income
- Understand CGT exposure

**Pain Points**:
- Multiple properties to track
- Different mortgage terms
- Tax implications complex

**Success Criteria**: All properties recorded, rental income tracked, CGT estimates available.

### Persona 3: Sarah and James - Joint Owners

**Demographics**: Married couple with jointly owned home

**Goals**:
- Track joint property correctly
- Both see same information
- Understand individual shares

**Pain Points**:
- Need consistent view
- Changes should sync
- Both need access

**Success Criteria**: Joint property shows for both, changes sync, ownership clear.

---

## User Stories

### US-01: View Property List

**As a** property owner,
**I want to** see all my properties listed,
**So that I** have overview of property portfolio.

**Acceptance Criteria**:
- Given I am on Property section
- When I view property list
- Then I see all my properties

**Display Information**:
- Property address
- Property type
- Current value
- Outstanding mortgage
- Equity
- Ownership type

### US-02: Add Property

**As a** user,
**I want to** add a property,
**So that** it is included in my net worth.

**Acceptance Criteria**:
- Given I am on Property section
- When I click "Add Property"
- Then I can enter property details

**Property Fields**:
- Address (street, city, postcode)
- Property type
- Purchase price
- Purchase date
- Current value
- Valuation date
- Ownership type
- Rental income (if BTL)

### US-03: Select Property Type

**As a** user with different properties,
**I want to** specify property type,
**So that** correct treatment applies.

**Acceptance Criteria**:
- Given I am adding a property
- When I select property type
- Then appropriate options show

**Property Types**:
| Type | Description | Tax Treatment |
|------|-------------|---------------|
| main_residence | Main Residence | PPR relief for CGT |
| secondary_residence | Second Home | No PPR, CGT applies |
| buy_to_let | Buy to Let | Rental income taxed, CGT applies |

### US-04: Add Mortgage to Property

**As a** property owner with mortgage,
**I want to** add mortgage details,
**So that** equity is calculated correctly.

**Acceptance Criteria**:
- Given I am adding or editing property
- When I add mortgage details
- Then mortgage is linked to property

**Mortgage Fields**:
- Lender name
- Original loan amount
- Current balance
- Interest rate
- Rate type
- Mortgage type
- Term (years)
- Monthly payment
- Start date
- End date

### US-05: Select Mortgage Type

**As a** user with mortgage,
**I want to** specify mortgage type,
**So that** amortisation is correct.

**Acceptance Criteria**:
- Given I am adding mortgage
- When I select mortgage type
- Then correct calculation applies

**Mortgage Types**:
| Type | Description |
|------|-------------|
| repayment | Repayment Mortgage |
| interest_only | Interest Only |
| mixed | Part Repayment / Part Interest Only |

**Rate Types**:
| Type | Description |
|------|-------------|
| fixed | Fixed Rate |
| variable | Variable Rate |
| tracker | Tracker Rate |
| discount | Discounted Rate |
| mixed | Mixed Rate |

### US-06: View Amortisation Schedule

**As a** user with repayment mortgage,
**I want to** see amortisation schedule,
**So that I** understand payment breakdown.

**Acceptance Criteria**:
- Given I have repayment mortgage
- When I view amortisation
- Then I see month-by-month breakdown

**Schedule Display**:
- Month number
- Monthly payment
- Interest portion
- Principal portion
- Remaining balance

### US-07: Record Joint Ownership

**As a** user with joint property,
**I want to** record joint ownership,
**So that** shares are calculated correctly.

**Acceptance Criteria**:
- Given I am adding joint property
- When I select joint ownership
- Then I can specify joint owner

**Ownership Types**:
| Type | Description |
|------|-------------|
| individual | Individual (100% yours) |
| joint | Joint Ownership (50/50 default) |
| tenants_in_common | Tenants in Common (custom split) |
| trust | Held in Trust |

### US-08: Sync Joint Property with Spouse

**As a** user with linked spouse,
**I want** joint property to appear for both,
**So that** we have consistent view.

**Acceptance Criteria**:
- Given I add joint property with linked spouse
- When property is saved
- Then spouse also sees the property

### US-09: Calculate Stamp Duty

**As a** user considering purchase,
**I want to** calculate SDLT,
**So that I** understand purchase costs.

**Acceptance Criteria**:
- Given I am viewing a property
- When I use SDLT calculator
- Then I see stamp duty amount

**SDLT Calculation**:
- First-time buyer rates (if applicable)
- Additional property surcharge (3%)
- Current SDLT bands

### US-10: Calculate Capital Gains Tax

**As a** user considering sale,
**I want to** estimate CGT,
**So that I** understand tax on sale.

**Acceptance Criteria**:
- Given I am viewing non-PPR property
- When I use CGT calculator
- Then I see estimated tax

**CGT Calculation**:
- Gain = Sale price - Purchase price - Costs
- Annual exemption applied
- Rate based on income tax band
- PPR relief if applicable

### US-11: Track Rental Income

**As a** BTL owner,
**I want to** track rental income,
**So that** income is recorded.

**Acceptance Criteria**:
- Given I have BTL property
- When I enter rental income
- Then it is saved and displayed

**Rental Fields**:
- Monthly rental income
- Annual rental income (calculated)

### US-12: Edit Property and Mortgage

**As a** user,
**I want to** update property details,
**So that** values stay current.

**Acceptance Criteria**:
- Given I have property recorded
- When I click edit
- Then I can modify and save

### US-13: Delete Property

**As a** user,
**I want to** remove sold properties,
**So that** portfolio is current.

**Acceptance Criteria**:
- Given I have property recorded
- When I click delete and confirm
- Then property is removed

---

## Feature Details

### Property Types

**Main Residence**:
- Primary home for residence purposes
- Principal Private Residence (PPR) relief for CGT
- RNRB applies if left to descendants
- Only one main residence allowed

**Secondary Residence**:
- Second home, holiday home
- No PPR relief
- 3% additional SDLT on purchase
- CGT applies on sale

**Buy to Let**:
- Investment property for rental
- Rental income taxable
- Mortgage interest relief restricted
- 3% additional SDLT on purchase
- CGT applies on sale

### Mortgage Data Model

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| lender_name | string | No | - |
| original_amount | decimal | No | Positive |
| current_balance | decimal | Yes | Non-negative |
| interest_rate | decimal | No | 0-20 |
| rate_type | enum | No | Valid type |
| mortgage_type | enum | No | Valid type |
| term_years | integer | No | 1-40 |
| monthly_payment | decimal | No | Positive |
| start_date | date | No | Past/present |
| end_date | date | No | After start |

### Equity Calculation

```
Property Equity = Current Value - Outstanding Mortgage(s)
```

For joint ownership:
```
Your Share = Property Equity x Ownership Percentage
```

### SDLT Calculator (2025/26)

**Standard Rates**:
| Band | Rate |
|------|------|
| Up to GBP 250,000 | 0% |
| GBP 250,001 - GBP 925,000 | 5% |
| GBP 925,001 - GBP 1,500,000 | 10% |
| Over GBP 1,500,000 | 12% |

**Additional Property Surcharge**: +3% on each band

**First-Time Buyer Relief**:
- 0% up to GBP 425,000
- 5% on GBP 425,001 - GBP 625,000
- No relief if over GBP 625,000

### CGT Calculator

**Calculation**:
```
Gain = Sale Price - Purchase Price - Purchase Costs - Selling Costs - Improvements
Taxable Gain = Gain - Annual Exemption (GBP 3,000 for 2025/26)
Tax = Taxable Gain x CGT Rate (18% basic / 24% higher for residential)
```

**PPR Relief**: Full exemption if property was main residence throughout ownership.

### Joint Ownership Sync

**Process**:
1. User adds joint property with spouse selected
2. System creates reciprocal property record for spouse
3. Changes to either record sync to other
4. Audit trail records who made changes

**Mortgage Sync**:
- Mortgage ownership type syncs with property
- Joint mortgage appears for both owners

---

## User Flows

### Flow 1: Add Property with Mortgage

```
Property Section
    |
    v
Click "Add Property"
    |
    v
Property Form
    |
    +--> Enter address
    +--> Select property type
    +--> Enter purchase details
    +--> Enter current value
    +--> Select ownership type
    |    +--> If joint: Select spouse
    |
    v
Add Mortgage Section
    |
    +--> Enter lender
    +--> Enter current balance
    +--> Enter interest rate
    +--> Select mortgage type
    +--> Enter monthly payment
    |
    v
Click "Save"
    |
    v
Property and mortgage saved
    |
    v
If joint: Spouse's account updated
```

### Flow 2: View Property Details

```
Property List
    |
    v
Click on property
    |
    v
Property Detail View
    |
    +--> Property summary
    +--> Mortgage details
    +--> Equity calculation
    |
    v
View Amortisation Schedule
    |
    v
View Tax Calculators
    |
    +--> SDLT Calculator
    +--> CGT Calculator
```

### Flow 3: Calculate SDLT

```
Property Detail
    |
    v
Click "SDLT Calculator"
    |
    v
Enter purchase price (or use current)
    |
    v
Select buyer type (FTB, additional property)
    |
    v
View calculation:
    |
    +--> Standard SDLT: GBP XXX
    +--> Additional surcharge: GBP XXX
    +--> Total: GBP XXX
```

---

## Edge Cases

### EC-01: Property with Multiple Mortgages

**Scenario**: Property has first and second charge mortgages.
**Expected Behaviour**: Allow multiple mortgages per property. Sum for equity calculation.

### EC-02: Interest-Only No Amortisation

**Scenario**: Interest-only mortgage has no principal repayment.
**Expected Behaviour**: Balance stays constant in projection. No amortisation schedule.

### EC-03: Joint Property Unlinked Spouse

**Scenario**: Joint owner does not have Fynla account.
**Expected Behaviour**: Record joint owner name manually. No sync possible. Note limitation.

### EC-04: Property Value Lower Than Mortgage

**Scenario**: Negative equity situation.
**Expected Behaviour**: Calculate and display negative equity. No error - valid state.

### EC-05: BTL No Rental Income

**Scenario**: BTL property vacant or income not entered.
**Expected Behaviour**: Allow GBP 0 rental. Property still tracked.

### EC-06: Scotland/Wales SDLT

**Scenario**: Property is in Scotland (LBTT) or Wales (LTT).
**Expected Behaviour**: Note different tax applies. Calculator may use SDLT as approximation or implement regional rates.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Properties can be added | Yes |
| AC-02 | All 3 property types available | Yes |
| AC-03 | Mortgage details can be added | Yes |
| AC-04 | Equity calculates correctly | Yes |
| AC-05 | All ownership types supported | Yes |
| AC-06 | Joint property syncs to spouse | Yes |
| AC-07 | Amortisation schedule displays | Yes |
| AC-08 | SDLT calculator works | Yes |
| AC-09 | CGT calculator works | Yes |
| AC-10 | Properties can be edited and deleted | Yes |

---

## Dependencies

### Upstream Dependencies

- User authentication
- Spouse linking (for joint ownership)
- Tax configuration (SDLT rates, CGT rates)

### Downstream Dependencies

- Net Worth calculation
- IHT calculation (property values)
- Balance Sheet

---

## Technical Constraints

1. **Currency**: All values in GBP
2. **Tax Rates**: Must use TaxConfigService
3. **Joint Sync**: Requires careful transaction handling
4. **Audit Trail**: Joint changes must be logged

---

## Non-Functional Requirements

### Performance

- Property list load: Under 1 second
- Save operation: Under 1 second
- Amortisation calculation: Under 500ms
- Tax calculations: Under 500ms

### Data Integrity

- Properties linked to user
- Mortgages linked to property
- Joint sync must be atomic
- Audit trail maintained

### Accessibility

- Forms properly labelled
- Tax calculations explained
- Address fields accessible

---

## UX Considerations

1. **Property Cards**: Visual cards showing key info
2. **Equity Prominence**: Clear display of equity
3. **Mortgage Summary**: Key terms visible
4. **Tax Calculator Access**: Easy to find
5. **Joint Indicator**: Clear badge for joint properties
6. **Address Format**: UK address layout
7. **Amortisation Clarity**: Clear schedule display
8. **Update Prompt**: Remind to update valuations
