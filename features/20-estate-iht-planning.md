# Feature Specification: Estate Planning Module - IHT Planning

## Status: Live

## Executive Summary

The IHT (Inheritance Tax) Planning feature provides comprehensive estate analysis and tax calculation for UK users. It calculates current and projected estate values, applies available allowances (NRB, RNRB, spouse exemption, transferable NRB), and determines IHT liability. For married users, it provides combined estate analysis showing liability on both first and second death.

### Elevator Pitch

Understand your potential Inheritance Tax bill and the allowances available to reduce it, with clear visibility of your estate value now and at life expectancy.

### Problem Statement

UK IHT is complex with multiple allowances, transferability rules, and planning opportunities. Users need to understand their estate's exposure to IHT (40% on amounts above allowances) to make informed planning decisions.

### Target Audience

- Primary: UK adults with estates potentially above IHT thresholds
- Secondary: Married couples planning for survivor's IHT position
- Tertiary: Users with non-UK domicile requiring different treatment

### Unique Selling Proposition

Comprehensive UK IHT calculation using actual recorded assets and liabilities, with married couple analysis showing both first and second death scenarios, integrated with gifting and trust strategies.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| IHT calculation usage | 70% of users view IHT tab | Feature tracking |
| Complete estate data | 60% have all asset types recorded | Data analysis |
| Married couple analysis | 80% of married users use couple view | Feature tracking |
| Strategy engagement | 40% explore IHT reduction strategies | Click tracking |

---

## User Personas

### Persona 1: Margaret - Estate Over Threshold

**Demographics**: 68-year-old widow with GBP 600,000 estate

**Goals**:
- Understand IHT liability
- Know available allowances
- Find ways to reduce tax bill

**Pain Points**:
- Uncertain about allowances
- Deceased husband's allowances unclear
- Wants to reduce tax for children

**Success Criteria**: Clear IHT calculation, understands transferable NRB.

### Persona 2: David and Susan - Married Couple

**Demographics**: Married couple, combined estate GBP 1.5M

**Goals**:
- Understand IHT on both deaths
- Plan for survivor position
- Optimise use of allowances

**Pain Points**:
- Complex combined calculation
- Different scenarios confusing
- Want clear action plan

**Success Criteria**: Both death scenarios calculated, strategy recommendations.

### Persona 3: James - Non-UK Domicile

**Demographics**: 45-year-old born outside UK, 10 years UK resident

**Goals**:
- Understand IHT on UK assets
- Know if non-dom rules apply
- Plan for potential deemed domicile

**Pain Points**:
- Different rules for non-dom
- Deemed domicile approaching
- Complex situation

**Success Criteria**: Correct treatment based on domicile status.

---

## User Stories

### US-01: View Estate Summary

**As a** user,
**I want to** see my total estate value,
**So that I** understand my IHT exposure.

**Acceptance Criteria**:
- Given I am on the IHT Planning tab
- When I view the estate summary
- Then I see total estate value

**Display for Single/Widowed**:
- Current estate value
- Projected estate at life expectancy
- Available allowances
- Taxable amount
- IHT liability (40%)

**Display for Married**:
- Combined estate value (both spouses)
- First death scenario
- Second death scenario
- Total IHT payable

### US-02: View IHT Calculation Breakdown

**As a** user,
**I want to** see detailed IHT calculation,
**So that I** understand how liability is determined.

**Acceptance Criteria**:
- Given I have assets and liabilities recorded
- When I view the calculation breakdown
- Then I see step-by-step calculation

**Breakdown Display**:
```
ASSETS
  Properties                    XXX,XXX
  Investments                   XXX,XXX
  Savings                       XXX,XXX
  Pensions (if applicable)      XXX,XXX
  Other Assets                  XXX,XXX
  ---------------------------------
  Total Assets                  XXX,XXX

LIABILITIES
  Mortgages                     XXX,XXX
  Loans                         XXX,XXX
  Other Debts                   XXX,XXX
  ---------------------------------
  Total Liabilities             XXX,XXX

NET ESTATE                      XXX,XXX

ALLOWANCES
  Nil Rate Band                 325,000
  Residence Nil Rate Band       175,000
  Transferable NRB              XXX,XXX
  ---------------------------------
  Total Allowances              XXX,XXX

TAXABLE ESTATE                  XXX,XXX
IHT @ 40%                       XXX,XXX
```

### US-03: View Available Allowances

**As a** user,
**I want to** see what allowances apply to me,
**So that I** understand tax-free amount.

**Acceptance Criteria**:
- Given I have profile information
- When I view allowances
- Then I see all applicable allowances

**Allowances (2025/26)**:
| Allowance | Amount | Conditions |
|-----------|--------|------------|
| Nil Rate Band (NRB) | GBP 325,000 | Everyone |
| Residence NRB (RNRB) | GBP 175,000 | Home to direct descendants |
| Transferable NRB | Up to GBP 325,000 | From deceased spouse |
| Transferable RNRB | Up to GBP 175,000 | From deceased spouse |
| Spouse Exemption | Unlimited | Transfers to spouse |

### US-04: View Married Couple Analysis

**As a** married user,
**I want to** see combined estate analysis,
**So that I** understand IHT for both deaths.

**Acceptance Criteria**:
- Given I am married with linked spouse
- When I view IHT Planning
- Then I see combined analysis

**Scenarios Shown**:
1. First death (your death): Assets to spouse, no IHT
2. First death (spouse death): Assets to you, no IHT
3. Second death: Combined estate, IHT calculated

### US-05: View Projected Estate

**As a** user planning ahead,
**I want to** see estate at life expectancy,
**So that I** understand future IHT position.

**Acceptance Criteria**:
- Given I have assets with growth assumptions
- When I view projections
- Then I see estate at life expectancy

**Projection Assumptions**:
- Property growth (user configurable or default)
- Investment growth
- Pension growth
- Inflation on allowances (if applicable)

### US-06: View IHT Strategy Cards

**As a** user wanting to reduce IHT,
**I want to** see strategy options,
**So that I can** take action.

**Acceptance Criteria**:
- Given I have IHT liability
- When I view strategy cards
- Then I see available strategies

**Strategy Cards**:
- Will Planning
- Gifting Strategy
- Life Insurance Strategy
- Trust Strategy (Coming Soon)

### US-07: View Spouse Data (Linked)

**As a** married user with linked spouse,
**I want to** see spouse's assets in combined view,
**So that** combined calculation is accurate.

**Acceptance Criteria**:
- Given I have linked spouse with data sharing
- When I view IHT Planning
- Then spouse assets are included

### US-08: Understand Non-UK Domicile Treatment

**As a** non-UK domiciled user,
**I want to** see correct IHT treatment,
**So that** calculation reflects my status.

**Acceptance Criteria**:
- Given I am non-UK domiciled
- When IHT is calculated
- Then only UK assets are included (unless deemed domicile)

---

## Feature Details

### IHT Calculation Logic

**Basic Formula**:
```
Taxable Estate = Total Assets - Total Liabilities - Allowances
IHT = Taxable Estate x 40%
```

**Asset Sources**:
- Properties (current value minus mortgage)
- Savings accounts (current balance)
- Investment accounts (current value)
- Pensions (DC fund value, DB capitalised)
- Business interests (when implemented)
- Chattels (when implemented)
- Gifts within 7 years (Potentially Exempt Transfers)

**Liability Sources**:
- Mortgages
- All 9 liability types from liability records
- Funeral costs estimate (optional)

### Nil Rate Band (NRB)

**Amount**: GBP 325,000 (2025/26)
**Conditions**: Available to everyone
**Transferability**: Unused portion transfers to surviving spouse

### Residence Nil Rate Band (RNRB)

**Amount**: GBP 175,000 (2025/26)
**Conditions**:
- Must leave residence to direct descendants
- Direct descendants = children, grandchildren, step-children
- Property must be in estate at death

**Taper**: Reduces by GBP 1 for every GBP 2 over GBP 2M estate
**Minimum**: GBP 0 when estate exceeds GBP 2.35M

**Transferability**: Unused portion transfers to surviving spouse

### Spouse Exemption

**Amount**: Unlimited
**Effect**: Assets passing to spouse are exempt from IHT
**Implications**:
- First death typically no IHT
- Second death faces combined estate
- Unused NRB transfers to survivor

### Transferable NRB

**How It Works**:
- If first spouse did not use full NRB
- Unused percentage transfers to survivor
- Maximum transfer: 100% (full additional NRB)

**Calculation**:
```
First Spouse Estate: GBP 200,000
First Spouse NRB Used: GBP 200,000
Unused: GBP 125,000 (38.5%)

Survivor Gets: 38.5% x GBP 325,000 = GBP 125,000 additional
```

### Married Couple Calculation

**First Death Scenario**:
- Assume assets pass to spouse
- Spouse exemption applies
- No IHT on first death
- Calculate unused NRB percentage

**Second Death Scenario**:
- Combined estate value
- Own NRB + Transferable NRB
- Own RNRB + Transferable RNRB (if applicable)
- Calculate IHT on combined estate

### Projection Model

**Growth Assumptions**:
- Property: 3% p.a. (default)
- Investments: 5% p.a. (default)
- Pensions: 5% p.a. (default)
- Savings: 2% p.a. (default)

**Time Horizon**:
- Life expectancy based on current age
- Or user-specified planning horizon

---

## User Flows

### Flow 1: View IHT Position (Single)

```
Estate Planning Dashboard
    |
    v
IHT Planning Tab
    |
    v
View Estate Summary
    |
    +--> Total Assets: GBP 750,000
    +--> Total Liabilities: GBP 100,000
    +--> Net Estate: GBP 650,000
    |
    v
View Allowances
    |
    +--> NRB: GBP 325,000
    +--> RNRB: GBP 175,000
    +--> Total: GBP 500,000
    |
    v
View Liability
    |
    +--> Taxable: GBP 150,000
    +--> IHT @ 40%: GBP 60,000
```

### Flow 2: View IHT Position (Married)

```
Estate Planning Dashboard
    |
    v
IHT Planning Tab
    |
    v
View Combined Estate
    |
    +--> Your Estate: GBP 800,000
    +--> Spouse Estate: GBP 500,000
    +--> Combined: GBP 1,300,000
    |
    v
View First Death Scenario
    |
    +--> Assets to spouse: exempt
    +--> IHT on first death: GBP 0
    |
    v
View Second Death Scenario
    |
    +--> Combined Estate: GBP 1,300,000
    +--> Allowances (2x NRB + 2x RNRB): GBP 1,000,000
    +--> Taxable: GBP 300,000
    +--> IHT: GBP 120,000
```

### Flow 3: Explore Strategy

```
IHT Planning Tab
    |
    v
View IHT Liability: GBP 120,000
    |
    v
View Strategy Cards
    |
    v
Click "Gifting Strategy"
    |
    v
Navigate to Gifting Strategy Tab
    |
    v
View personalised recommendations
```

---

## Edge Cases

### EC-01: Estate Below Threshold

**Scenario**: Estate is below combined NRB + RNRB.
**Expected Behaviour**: Show GBP 0 IHT liability. Positive message that no IHT expected.

### EC-02: Estate Exceeds RNRB Taper

**Scenario**: Estate over GBP 2M triggers RNRB taper.
**Expected Behaviour**: Calculate reduced RNRB. Show tapered amount used.

### EC-03: No Property (No RNRB)

**Scenario**: User has no residential property.
**Expected Behaviour**: RNRB does not apply. NRB only in allowances.

### EC-04: Non-UK Domicile

**Scenario**: User indicates non-UK domicile.
**Expected Behaviour**: Calculate IHT on UK assets only. Note different treatment.

### EC-05: Deemed Domicile

**Scenario**: Non-UK domiciled but 15+ years UK resident.
**Expected Behaviour**: Apply deemed domicile rules. Worldwide assets subject to IHT.

### EC-06: Pension Not In Estate

**Scenario**: User has DC pension with nominated beneficiary.
**Expected Behaviour**: Note that pensions often outside estate. Option to include/exclude.

### EC-07: Widowed User

**Scenario**: User's spouse has died, leaving unused NRB.
**Expected Behaviour**: Allow recording of transferable NRB. Include in user's allowances.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Total estate value calculates correctly | Yes |
| AC-02 | All asset types included | Yes |
| AC-03 | All liability types included | Yes |
| AC-04 | NRB applied correctly | Yes |
| AC-05 | RNRB applied when qualifying | Yes |
| AC-06 | RNRB taper calculated for large estates | Yes |
| AC-07 | Spouse exemption applied for married | Yes |
| AC-08 | Combined calculation for married couples | Yes |
| AC-09 | Projections calculate with growth | Yes |
| AC-10 | Strategy cards link to relevant sections | Yes |

---

## Dependencies

### Upstream Dependencies

- All asset records (properties, savings, investments, pensions)
- All liability records
- User profile (marital status, domicile)
- Spouse linking and data sharing
- Tax configuration (IHT rates and bands)

### Downstream Dependencies

- Will planning recommendations
- Gifting strategy recommendations
- Life insurance recommendations
- Trust recommendations

---

## Technical Constraints

1. **Tax Configuration**: IHT rates from TaxConfigService
2. **Complex Calculations**: Multiple scenarios require accurate logic
3. **Spouse Data**: Must handle shared and separate data
4. **Real-time Calculation**: Updates when any asset changes

---

## Non-Functional Requirements

### Performance

- IHT calculation: Under 2 seconds
- Page load: Under 2 seconds
- Projection calculation: Under 3 seconds

### Accuracy

- Calculations must match HMRC rules
- Allowances must be current
- Taper must calculate correctly

### Accessibility

- Tables have proper headers
- Numbers formatted for clarity
- Complex concepts explained

---

## UX Considerations

1. **Summary First**: Key liability number prominent
2. **Breakdown Expandable**: Detail available but not overwhelming
3. **Married Toggle**: Easy switch between individual and combined view
4. **Allowance Explanation**: Help users understand each allowance
5. **Visual Indicators**: Charts showing estate composition
6. **Strategy Links**: Clear path to action
7. **Positive Messaging**: Celebrate when no IHT due
8. **Professional Advice Note**: Recommend adviser for complex situations
