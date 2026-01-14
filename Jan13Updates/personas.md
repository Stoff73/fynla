# Fynla Preview Personas - Complete Documentation

This document provides complete details of all preview personas used in the Fynla financial planning application. These personas are real database records with `is_preview_user=true` that demonstrate different financial planning scenarios.

---

## Overview

| Persona ID | Name | Age | Status | Net Worth Range | Primary Focus |
|------------|------|-----|--------|-----------------|---------------|
| `young_family` | James & Emily Carter | 34/32 | Married | £80k - £120k | Protection gaps, emergency fund |
| `peak_earners` | David & Sarah Mitchell | 48/46 | Married | £1.5m - £2m | Tax efficiency, pension allowances |
| `widow` | Margaret Thompson | 68 | Widowed | £1.4m - £1.6m | IHT planning, gifting strategy |
| `entrepreneur` | Alex Chen | 38 | Single | £800k - £1m | Business protection, single estate |

---

## 1. Young Family - James & Emily Carter

**Tagline:** "Young family building their future"

**Description:** A young married couple in their early 30s with two children, mortgage, and workplace pensions. Good earnings but gaps in protection coverage.

### Primary User: James Carter
| Field | Value |
|-------|-------|
| Email | `preview_young_family@fynla.local` |
| Date of Birth | 15 May 1990 (Age 34) |
| Gender | Male |
| Marital Status | Married |
| Employment Status | Employed |
| Employer | TechCorp Ltd |
| Occupation | Software Developer |
| Annual Income | £62,000 |
| Monthly Expenditure | £4,200 |
| Target Retirement Age | 65 |
| Target Retirement Income | £35,000 |
| Health Status | Yes (good health) |
| Smoking Status | Never |
| Address | 42 Oak Avenue, Birmingham, B15 2TT |

### Spouse: Emily Carter
| Field | Value |
|-------|-------|
| Email | `preview_young_family_spouse@fynla.local` |
| Date of Birth | 22 August 1992 (Age 32) |
| Gender | Female |
| Employment Status | Employed |
| Employer | MediaGroup PLC |
| Occupation | Marketing Manager |
| Annual Income | £48,000 |

### Family Members
| Name | Relationship | Date of Birth | Age | Dependent |
|------|--------------|---------------|-----|-----------|
| Oliver Carter | Child | 10 March 2018 | 6 | Yes |
| Sophie Carter | Child | 25 July 2021 | 3 | Yes |

### Properties
| Property | Type | Value | Purchase Price | Purchase Date | Ownership |
|----------|------|-------|----------------|---------------|-----------|
| Family Home (42 Oak Avenue, Birmingham) | Main Residence | £320,000 | £285,000 | 15 June 2019 | Joint (50/50) |

### Mortgages
| Lender | Property | Outstanding | Original | Type | Rate | Fixed Until | Monthly Payment | Term |
|--------|----------|-------------|----------|------|------|-------------|-----------------|------|
| Nationwide | Family Home | £245,000 | £256,500 | Repayment | 4.89% Fixed | 30 June 2026 | £1,485 | 276 months |

### Savings Accounts
| Account | Provider | Type | Balance | Interest | ISA | Owner |
|---------|----------|------|---------|----------|-----|-------|
| James's Current Account | Lloyds Bank | Current | £3,250 | 0% | No | James |
| Emily's Current Account | Nationwide | Current | £2,180 | 0% | No | Emily |
| Joint Savings Account | Marcus | Instant Access | £8,500 | 4.55% | No | Joint |
| Oliver's Junior ISA | Vanguard | Junior ISA | £2,800 | - | Yes | James |
| Sophie's Junior ISA | Vanguard | Junior ISA | £1,400 | - | Yes | James |

### Investment Accounts
| Account | Provider | Type | Value | Holdings |
|---------|----------|------|-------|----------|
| James's S&S ISA | Vanguard | ISA | £15,000 | Vanguard LifeStrategy 80% (100%) |

**Holdings Detail - James's S&S ISA:**
| Holding | Ticker | ISIN | Units | Cost | Current Price | Value | OCF |
|---------|--------|------|-------|------|---------------|-------|-----|
| Vanguard LifeStrategy 80% | VGLS80 | GB00B4PQW151 | 59 | £235.00 | £255.00 | £15,045 | 0.22% |

### Pensions
**DC Pensions:**
| Scheme | Provider | Type | Fund Value | Employee % | Employer % | Salary | Retirement Age | Owner |
|--------|----------|------|------------|------------|------------|--------|----------------|-------|
| TechCorp Pension Scheme | Scottish Widows | Workplace | £45,000 | 3% | 3% | £62,000 | 65 | James |
| MediaGroup Pension | Aviva | Workplace | £22,000 | 4% | 5% | £48,000 | 65 | Emily |

**DB Pensions:** None

**State Pension:** Not configured

### Protection Policies
**Life Insurance:**
| Type | Provider | Sum Assured | Premium | Frequency | Start | End | In Trust | Joint |
|------|----------|-------------|---------|-----------|-------|-----|----------|-------|
| Level Term | Legal & General | £350,000 | £32 | Monthly | 01 July 2019 | 01 July 2044 | No | Yes |

**Critical Illness:** None - **GAP IDENTIFIED**

**Income Protection:** None - **GAP IDENTIFIED**

### Liabilities
| Type | Name | Balance | Original | Rate | Monthly | End Date | Lender |
|------|------|---------|----------|------|---------|----------|--------|
| Hire Purchase | Ford Focus Car Finance | £12,000 | £18,000 | 6.9% | £350 | 01 September 2026 | Ford Credit |

### Expenditure Breakdown (Monthly: £4,200)
| Category | Amount |
|----------|--------|
| Housing (Mortgage) | £1,485 |
| Childcare | £800 |
| Food | £600 |
| Transport | £450 |
| Utilities | £250 |
| Entertainment | £200 |
| Insurance | £150 |
| Clothing | £100 |
| Other | £165 |

### Risk Profile
- **James:** Medium risk
- **Emily:** Medium risk
- **Notes:** Young couple with long investment horizon, balanced approach suitable for long-term growth

### Estate Planning
- **Will:** No
- **LPA:** No

### Key Concerns
1. Do we have enough life insurance if something happens?
2. How much should we have in our emergency fund?
3. Are we saving enough for our children's education?
4. When can we afford to move to a bigger house?

---

## 2. Peak Earners - David & Sarah Mitchell

**Tagline:** "High earners approaching retirement"

**Description:** A couple in their late 40s with high incomes, BTL property, substantial investments, and complex pension arrangements including DB schemes.

### Primary User: David Mitchell
| Field | Value |
|-------|-------|
| Email | `preview_peak_earners@fynla.local` |
| Date of Birth | 8 November 1976 (Age 48) |
| Gender | Male |
| Marital Status | Married |
| Employment Status | Employed |
| Employer | Global Finance Corp |
| Occupation | Finance Director |
| Annual Income | £145,000 |
| Monthly Expenditure | £9,500 |
| Target Retirement Age | 60 |
| Target Retirement Income | £75,000 |
| Health Status | Yes (good health) |
| Smoking Status | Never |
| Address | The Willows, 15 Chestnut Lane, Guildford, Surrey, GU1 4RH |

### Spouse: Sarah Mitchell
| Field | Value |
|-------|-------|
| Email | `preview_peak_earners_spouse@fynla.local` |
| Date of Birth | 22 April 1978 (Age 46) |
| Gender | Female |
| Employment Status | Employed |
| Employer | Surrey NHS Trust |
| Occupation | GP Partner |
| Annual Income | £120,000 |

### Family Members
| Name | Relationship | Date of Birth | Age | Dependent | Notes |
|------|--------------|---------------|-----|-----------|-------|
| William Mitchell | Child | 15 September 2007 | 17 | Yes | Year 13, private school |
| Charlotte Mitchell | Child | 28 February 2010 | 14 | Yes | Year 10, private school |

### Properties
| Property | Type | Value | Purchase Price | Purchase Date | Ownership | Rental Income |
|----------|------|-------|----------------|---------------|-----------|---------------|
| The Willows, Guildford | Main Residence | £850,000 | £625,000 | 01 April 2012 | Joint | - |
| Flat 42, Riverside Apartments, London SE1 | Buy-to-Let | £425,000 | £340,000 | 15 October 2018 | Joint | £1,800/month |

### Mortgages
| Lender | Property | Outstanding | Original | Type | Rate | Fixed Until | Monthly Payment | Term |
|--------|----------|-------------|----------|------|------|-------------|-----------------|------|
| HSBC | The Willows | £280,000 | £450,000 | Repayment | 4.29% Fixed | 01 April 2027 | £2,350 | 156 months |
| Barclays | City Centre Flat | £220,000 | £272,000 | Interest Only | 5.19% Tracker | - | £952 | 180 months |

### Savings Accounts
| Account | Provider | Type | Balance | Interest | ISA | Owner |
|---------|----------|------|---------|----------|-----|-------|
| David's Current Account | HSBC | Current | £8,450 | 0% | No | David |
| Sarah's Current Account | Barclays | Current | £6,280 | 0% | No | Sarah |
| Joint Current Account | Nationwide | Current | £4,500 | 0% | No | Joint |
| David's Cash ISA | Nationwide | Cash ISA | £22,500 | 4.25% | Yes | David |
| Sarah's Cash ISA | Nationwide | Cash ISA | £22,500 | 4.25% | Yes | Sarah |
| Premium Bonds | NS&I | Premium Bonds | £50,000 | - | No | Joint |

### Investment Accounts
| Account | Provider | Type | Value | Risk | Owner |
|---------|----------|------|-------|------|-------|
| David's S&S ISA | Hargreaves Lansdown | ISA | £95,000 | High | David |
| Sarah's S&S ISA | Hargreaves Lansdown | ISA | £85,000 | Medium | Sarah |
| Joint GIA | AJ Bell | GIA | £95,000 | Upper Medium | Joint |
| VCT Holdings | Various | VCT | £30,000 | High | David |

**Holdings Detail - David's S&S ISA (£95,000):**
| Holding | Ticker | ISIN | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|-------|------|---------------|-------|------------|-----|
| Fundsmith Equity | FUND | GB00B41YBW71 | 351 | £85.50 | £99.86 | £35,051 | 36.8% | 0.95% |
| Scottish Mortgage IT | SMT | GB00BLDYK618 | 2,500 | £8.40 | £10.00 | £25,000 | 26.3% | 0.34% |
| Vanguard FTSE All-World | VWRL | IE00B3RBWM25 | 318 | £93.00 | £109.99 | £34,977 | 36.9% | 0.22% |

**Holdings Detail - Sarah's S&S ISA (£85,000):**
| Holding | Ticker | ISIN | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|-------|------|---------------|-------|------------|-----|
| Vanguard LifeStrategy 80 | VGLS80 | GB00B4PQW151 | 333 | £225.00 | £255.00 | £84,915 | 100% | 0.22% |

**Holdings Detail - Joint GIA (£95,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| iShares Core MSCI World | SWDA | IE00B4L5Y983 | ETF | 625 | £68.00 | £80.00 | £50,000 | 52.6% | 0.20% |
| Vanguard UK Govt Bond | VGOV | IE00B42WWV65 | Bond | 1,316 | £19.50 | £19.00 | £25,004 | 26.3% | 0.12% |
| iShares Physical Gold | SGLN | IE00B4ND3602 | Alternative | 84 | £195.00 | £238.00 | £19,992 | 21.1% | 0.12% |

### Pensions
**DC Pensions:**
| Scheme | Provider | Type | Fund Value | Employee % | Employer % | Salary | Monthly Contrib | Retirement Age | Owner |
|--------|----------|------|------------|------------|------------|--------|-----------------|----------------|-------|
| Global Finance Corp Pension | Fidelity | Workplace | £180,000 | 8% | 8% | £145,000 | - | 60 | David |
| David's SIPP | AJ Bell | SIPP | £320,000 | - | - | - | £2,000 | 60 | David |

**SIPP Holdings (£320,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| Vanguard Global Equity | VHVG | IE00BKX55S42 | Fund | 4,211 | £32.50 | £38.00 | £160,018 | 50% | 0.23% |
| BlackRock Corporate Bond | SLXX | IE0032895942 | Bond | 800 | £125.00 | £120.00 | £96,000 | 30% | 0.18% |
| L&G UK Property | LGUKP | GB00BK35DT11 | Property | 50,000 | £1.35 | £1.28 | £64,000 | 20% | 0.68% |

**DB Pensions:**
| Scheme | Type | Accrued Annual | NRA | Lump Sum | Inflation | Spouse Benefit | Years | Owner |
|--------|------|----------------|-----|----------|-----------|----------------|-------|-------|
| NHS Pension Scheme | Public Sector | £35,000 | 60 | £105,000 | CPI | 50% | 18 | Sarah |

**State Pension:**
- Forecast: £11,502/year (£221.20/week)
- State Pension Age: 67
- Qualifying Years: 30 (8 remaining)

### Protection Policies
**Life Insurance:**
| Type | Provider | Sum Assured | Premium | Frequency | Start | End | In Trust | Joint |
|------|----------|-------------|---------|-----------|-------|-----|----------|-------|
| Level Term | Vitality | £500,000 | £85 | Monthly | 01 January 2020 | 01 January 2040 | Yes | Yes |

**Critical Illness:**
| Type | Provider | Sum Assured | Premium | Frequency | Start | End |
|------|----------|-------------|---------|-----------|-------|-----|
| Standalone | Legal & General | £200,000 | £125 | Monthly | 01 January 2020 | 01 January 2040 |

**Income Protection:** None

### Liabilities
| Type | Name | Balance | Original | Rate | Monthly | End Date | Lender |
|------|------|---------|----------|------|---------|----------|--------|
| Personal Loan | School Fees Loan | £25,000 | £40,000 | 3.9% | £750 | 01 September 2025 | Barclays |

### Trusts
| Name | Type | Created | Initial Value | Current Value | Settlor | Beneficiaries | Trustees |
|------|------|---------|---------------|---------------|---------|---------------|----------|
| Mitchell Children's Education Trust | Discretionary | 01 September 2020 | £150,000 | £185,000 | David Mitchell | William, Charlotte | David, Sarah, Barclays Trustee Services |

**Trust Purpose:** Education funding for William and Charlotte including university fees, accommodation, and living expenses.

### Chattels
| Item | Type | Value | Purchase Price | Purchase Date | Owner |
|------|------|-------|----------------|---------------|-------|
| Contemporary Art Collection | Art | £35,000 | £22,000 | 15 June 2018 | Joint |
| 1967 Jaguar E-Type Series 1 | Classic Vehicle | £85,000 | £45,000 | 20 April 2015 | David |
| Sarah's Engagement Ring | Jewelry | £18,000 | £12,000 | 01 December 2004 | Sarah |
| Georgian Writing Desk | Antique | £8,500 | £6,200 | 10 March 2019 | Joint |
| First Edition Book Collection | Collectible | £4,500 | £2,800 | 01 September 2016 | David |
| BMW X5 xDrive40i | Vehicle | £42,000 | £65,000 | 01 March 2022 | Joint |

### Expenditure Breakdown (Monthly: £9,500)
| Category | Amount |
|----------|--------|
| Housing (Mortgages) | £3,302 |
| School Fees | £2,800 |
| Food | £800 |
| Transport | £600 |
| Entertainment | £500 |
| Holidays | £500 |
| Utilities | £400 |
| Insurance | £350 |
| Other | £248 |

### Risk Profile
- **David:** Upper Medium
- **Sarah:** Medium
- **Notes:** High earners with substantial wealth, David prefers growth-focused approach while Sarah is more balanced given NHS pension security

### Estate Planning
**Will Status:** Yes - Mirror wills prepared by Henderson & Co Solicitors

**David's Will:**
- Executor: Sarah Mitchell & Barclays Wealth
- Spouse Primary Beneficiary: Yes (100%)
- Secondary Bequests:
  - William Mitchell: 50% of estate (at age 25)
  - Charlotte Mitchell: 50% of estate (at age 25)
  - Cancer Research UK: £10,000

**Sarah's Will:**
- Executor: David Mitchell & Barclays Wealth
- Spouse Primary Beneficiary: Yes (100%)
- Secondary Bequests:
  - William Mitchell: 50% of estate (at age 25)
  - Charlotte Mitchell: 50% of estate (at age 25)
  - British Heart Foundation: £10,000

**LPA:** Yes

### Key Concerns
1. Are we on track for retirement at 60?
2. How do we optimise our tax position with tapered pension allowance?
3. Should we pay off the mortgage or invest more?
4. What is the best strategy for the BTL property?

---

## 3. Widow - Margaret Thompson

**Tagline:** "Retired widow with estate concerns"

**Description:** A 68-year-old retired headteacher who was widowed 2 years ago. Multiple properties, substantial investments, and complex estate planning needs.

### Primary User: Margaret Thompson
| Field | Value |
|-------|-------|
| Email | `preview_widow@fynla.local` |
| Date of Birth | 14 March 1956 (Age 68) |
| Gender | Female |
| Marital Status | Widowed |
| Employment Status | Retired |
| Former Employer | Gloucestershire County Council |
| Occupation | Retired Headteacher |
| Annual Trust Income | £15,000 |
| Monthly Expenditure | £3,200 |
| Health Status | Yes with previous conditions |
| Smoking Status | Never |
| Address | Rose Cottage, 8 Church Lane, Bourton-on-the-Water, Gloucestershire, GL54 2BY |

### Deceased Spouse: Robert Thompson
| Field | Value |
|-------|-------|
| Date of Birth | 22 June 1954 |
| Date of Death | 15 November 2022 |
| Notes | Transferred NRB (£325,000) and RNRB (£175,000) to Margaret |

### Family Members
| Name | Relationship | Date of Birth | Age | Notes |
|------|--------------|---------------|-----|-------|
| Andrew Thompson | Child | 20 May 1982 | 42 | Eldest son, lives in London |
| Catherine Williams | Child | 12 August 1984 | 40 | Daughter, lives locally with 2 children |
| Richard Thompson | Child | 30 January 1988 | 36 | Youngest son, lives in Bristol |
| Emily Williams | Grandchild | 15 April 2014 | 10 | Catherine's daughter |
| James Williams | Grandchild | 22 September 2017 | 7 | Catherine's son |
| Sophie Thompson | Grandchild | 10 March 2016 | 8 | Andrew's daughter |
| Oliver Thompson | Grandchild | 5 July 2019 | 5 | Andrew's son |
| Lucy Thompson | Grandchild | 28 November 2018 | 6 | Richard's daughter |

### Properties
| Property | Type | Value | Purchase Price | Purchase Date | Ownership | Notes |
|----------|------|-------|----------------|---------------|-----------|-------|
| Rose Cottage, Bourton-on-the-Water | Main Residence | £625,000 | £280,000 | 01 May 2002 | Individual | Mortgage paid off |
| Seaside Cottage, Padstow, Cornwall | Secondary Residence | £285,000 | £175,000 | 15 June 2008 | Individual | Holiday cottage - family use only |

### Mortgages
None - both properties fully owned.

### Savings Accounts
| Account | Provider | Type | Balance | Interest | ISA |
|---------|----------|------|---------|----------|-----|
| Margaret's Current Account | Lloyds Bank | Current | £4,850 | 0% | No |
| Cash ISA | Nationwide | Cash ISA | £85,000 | 4.5% | Yes |
| NS&I Direct Saver | NS&I | NSI | £50,000 | 4.0% | No |

### Investment Accounts
| Account | Provider | Type | Value | Risk |
|---------|----------|------|-------|------|
| Stocks & Shares ISA | Hargreaves Lansdown | ISA | £220,000 | Lower Medium |
| Offshore Bond | Quilter International | Offshore Bond | £150,000 | Lower Medium |
| General Investment Account | Hargreaves Lansdown | GIA | £180,000 | Medium |

**Holdings Detail - S&S ISA (£220,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| Vanguard LifeStrategy 40 | VGLS40 | GB00B3TYHH97 | Fund | 550 | £175.00 | £200.00 | £110,000 | 50% | 0.22% |
| iShares Corporate Bond | SLXX | IE0032895942 | Bond | 550 | £125.00 | £120.00 | £66,000 | 30% | 0.20% |
| Vanguard UK Equity Income | VUKE | IE00B3X1LR22 | Fund | 1,467 | £26.50 | £30.00 | £44,010 | 20% | 0.22% |

**Holdings Detail - GIA (£180,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| City of London IT | CTY | GB0001990497 | UK Equity | 13,636 | £3.85 | £4.40 | £59,998 | 33.3% | 0.38% |
| Finsbury Growth & Income | FGT | GB0003434919 | Fund | 6,667 | £7.80 | £9.00 | £60,003 | 33.3% | 0.65% |
| Bankers IT | BNKR | GB00BYYJD365 | UK Equity | 54,545 | £0.95 | £1.10 | £60,000 | 33.3% | 0.48% |

### Pensions
**DC Pensions:** None

**DB Pensions:**
| Scheme | Type | Accrued Annual | NRA | Lump Sum | Inflation | Years | Status |
|--------|------|----------------|-----|----------|-----------|-------|--------|
| Teachers' Pension Scheme | Public Sector | £28,000 | 60 | £84,000 (taken) | CPI | 35 | Already in payment |

**State Pension:**
- Forecast: £11,502/year (£221.20/week)
- State Pension Age: 66
- Status: **Already receiving** (full new state pension)

### Protection Policies
**Life Insurance:**
| Type | Provider | Sum Assured | Premium | Frequency | Start | In Trust | Joint |
|------|----------|-------------|---------|-----------|-------|----------|-------|
| Whole of Life | Aviva | £100,000 | £150 | Monthly | 01 January 2010 | Yes | No |

**Note:** Life insurance written in trust for IHT purposes - pays outside estate.

### Liabilities
None.

### Gifts (Within Last 7 Years)
| Type | Recipient | Relationship | Amount | Date | Status |
|------|-----------|--------------|--------|------|--------|
| PET | Andrew Thompson | Child | £15,000 | 15 December 2022 | Within 7 years |
| PET | Catherine Williams | Child | £15,000 | 01 March 2023 | Within 7 years |
| PET | Richard Thompson | Child | £15,000 | 15 June 2023 | Within 7 years |
| Annual Exemption | Various grandchildren | - | £3,000 | 06 April 2023 | Exempt |
| Annual Exemption | Various grandchildren | - | £3,000 | 06 April 2024 | Exempt |

**Total PETs within 7 years:** £45,000

### Trusts
| Name | Type | Created | Initial Value | Current Value | Settlor | Beneficiaries | Trustees |
|------|------|---------|---------------|---------------|---------|---------------|----------|
| Thompson Family Discretionary Trust | Discretionary | 01 June 2015 | £200,000 | £245,000 | Robert Thompson | Margaret, Andrew, Catherine, Richard | Smithson Solicitors LLP, Andrew Thompson |

**Trust Purpose:** Provide supplementary income for Margaret during her lifetime, with remainder to children. Keeps assets outside her estate for IHT purposes.

### Chattels
| Item | Value | Notes |
|------|-------|-------|
| Antique Furniture Collection | £25,000 | Various Georgian and Victorian pieces |
| Jewellery | £15,000 | Including late husband's watch collection |

### Expenditure Breakdown (Monthly: £3,200)
| Category | Amount |
|----------|--------|
| Holidays | £500 |
| Food | £400 |
| Housing (Council Tax, Maintenance) | £400 |
| Gifts & Charity | £400 |
| Utilities | £300 |
| Entertainment | £300 |
| Insurance | £250 |
| Other | £250 |
| Health | £200 |
| Transport | £200 |

### IHT Profile
| Field | Value |
|-------|-------|
| Available NRB | £325,000 |
| Available RNRB | £175,000 |
| Transferred NRB (from Robert) | £325,000 |
| Transferred RNRB (from Robert) | £175,000 |
| **Total Available Threshold** | **£1,000,000** |
| Gross Estate Value | ~£1,595,000 |
| Current IHT Estimate | ~£238,000 |

### Risk Profile
- **Margaret:** Lower Medium
- **Notes:** Retired widow focused on capital preservation and income, cautious approach with estate planning priorities

### Estate Planning
**Will Status:** Yes - Updated following Robert's death

**Executor:** Andrew Thompson & Smithson Solicitors LLP

**Bequests:**
| Beneficiary | Type | Amount/% | Priority | Conditions |
|-------------|------|----------|----------|------------|
| Cotswold Care Hospice | Specific Amount | £25,000 | 1 | In memory of Robert |
| St Lawrence Church, Bourton | Specific Amount | £5,000 | 1 | - |
| Catherine Williams | Specific Asset | Rose Cottage | 1 | To retain family home in the village |
| Andrew Thompson | Percentage | 40% | 2 | - |
| Catherine Williams | Percentage | 40% | 2 | - |
| Grandchildren's Education Trust | Percentage | 15% | 2 | Held in trust until age 21 |

**LPA:** Yes (Health and Finance) - Attorneys: Andrew and Catherine

### Key Concerns
1. How much IHT will my children have to pay?
2. Should I gift money now or wait?
3. Is my will up to date after my husband's passing?
4. What is the best way to help grandchildren with house deposits?

---

## 4. Entrepreneur - Alex Chen

**Tagline:** "Self-made tech entrepreneur"

**Description:** A 38-year-old single tech consultancy owner with business interests, SIPP, and key person insurance considerations.

### Primary User: Alex Chen
| Field | Value |
|-------|-------|
| Email | `preview_entrepreneur@fynla.local` |
| Date of Birth | 18 September 1986 (Age 38) |
| Gender | Male |
| Marital Status | Single |
| Employment Status | Self-Employed |
| Business Name | Chen Tech Consulting Ltd |
| Occupation | Owner/Director |
| Annual Income | £180,000 |
| Monthly Expenditure | £5,500 |
| Target Retirement Age | 55 |
| Target Retirement Income | £50,000 |
| Health Status | Yes (good health) |
| Smoking Status | Never |
| Address | Penthouse 3, The Lofts, 42 Deansgate, Manchester, M3 4LQ |

### Spouse
None - Single

### Family Members
| Name | Relationship | Date of Birth | Age | Notes |
|------|--------------|---------------|-----|-------|
| Wei Chen | Parent (Father) | 14 February 1958 | 66 | May need care support in future |
| Mei Chen | Parent (Mother) | 22 July 1961 | 63 | In good health |

### Properties
| Property | Type | Value | Purchase Price | Purchase Date | Ownership |
|----------|------|-------|----------------|---------------|-----------|
| City Centre Penthouse, Manchester | Main Residence | £380,000 | £320,000 | 01 March 2020 | Individual |

### Mortgages
| Lender | Property | Outstanding | Original | Type | Rate | Fixed Until | Monthly Payment | Term |
|--------|----------|-------------|----------|------|------|-------------|-----------------|------|
| Metro Bank | City Centre Penthouse | £190,000 | £256,000 | Repayment | 4.49% Fixed | 01 March 2027 | £1,350 | 204 months |

### Savings Accounts
| Account | Provider | Type | Balance | Interest |
|---------|----------|------|---------|----------|
| Alex's Current Account | Starling Bank | Personal Current | £5,680 | 0% |
| Chen Tech Consulting - Business Current | Tide | Business Current | £48,500 | 0% |
| Chen Tech Consulting - Business Reserve | Tide | Business Savings | £75,000 | 3.75% |
| Personal Notice Account | Aldermore | 90-Day Notice | £40,000 | 5.1% |

### Investment Accounts
| Account | Provider | Type | Value | Risk |
|---------|----------|------|-------|------|
| Stocks & Shares ISA | Interactive Investor | ISA | £95,000 | High |
| General Investment Account | AJ Bell | GIA | £45,000 | High |

**Holdings Detail - S&S ISA (£95,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| Baillie Gifford American | USA | GB00BYPJS636 | Fund | 2,500 | £10.20 | £12.00 | £30,000 | 31.6% | 0.51% |
| Scottish Mortgage IT | SMT | GB00BLDYK618 | UK Equity | 2,500 | £8.40 | £10.00 | £25,000 | 26.3% | 0.34% |
| Polar Capital Tech | PCT | IE00B3QXJP52 | Fund | 667 | £25.50 | £30.00 | £20,010 | 21.1% | 0.79% |
| iShares MSCI World Tech | WTCH | IE00BGV5VR99 | ETF | 400 | £42.50 | £50.00 | £20,000 | 21.1% | 0.40% |

**Holdings Detail - GIA (£45,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation |
|---------|--------|------|------|-------|------|---------------|-------|------------|
| Nvidia | NVDA | US67066G1040 | US Equity | 13 | £800.00 | £1,200.00 | £15,600 | 33.3% |
| Microsoft | MSFT | US5949181045 | US Equity | 40 | £310.00 | £375.00 | £15,000 | 33.3% |
| Amazon | AMZN | US0231351067 | US Equity | 85 | £145.00 | £176.47 | £15,000 | 33.3% |

### Pensions
**DC Pensions:**
| Scheme | Provider | Type | Fund Value | Employee % | Employer % | Salary | Monthly Contrib | Retirement Age |
|--------|----------|------|------------|------------|------------|--------|-----------------|----------------|
| Chen Tech Consulting Pension | Nest | Workplace | £45,000 | 5% | 10% | £180,000 | - | 55 |
| Alex's SIPP | AJ Bell | SIPP | £185,000 | - | - | - | £3,333 | 55 |

**SIPP Holdings (£185,000):**
| Holding | Ticker | ISIN | Type | Units | Cost | Current Price | Value | Allocation | OCF |
|---------|--------|------|------|-------|------|---------------|-------|------------|-----|
| Vanguard Global All Cap | VAFTGAG | IE00BD3QJN10 | Fund | 2,313 | £35.00 | £40.00 | £92,520 | 50% | 0.23% |
| HSBC FTSE All-World | HMWO | IE00B4X9L533 | ETF | 1,850 | £26.00 | £30.00 | £55,500 | 30% | 0.13% |
| Fundsmith Equity | FUND | GB00B41YBW71 | Fund | 370 | £85.50 | £99.86 | £36,948 | 20% | 0.95% |

**DB Pensions:** None

**State Pension:**
- Forecast: £9,628/year (£185.15/week)
- State Pension Age: 68
- Qualifying Years: 12 (23 remaining)
- **Notes:** Reduced forecast due to self-employment gaps

### Protection Policies
**Life Insurance:**
| Type | Provider | Sum Assured | Premium | Frequency | Start | End | In Trust | Notes |
|------|----------|-------------|---------|-----------|-------|-----|----------|-------|
| Level Term | Zurich | £200,000 | £18 | Monthly | 01 January 2021 | 01 January 2046 | No | Personal life cover |
| Level Term | Legal & General | £500,000 | £95 | Monthly | 01 June 2022 | 01 June 2032 | No | Key person insurance - paid by company |

**Critical Illness:** None

**Income Protection:** None

### Liabilities
| Type | Name | Balance | Rate | Notes |
|------|------|---------|------|-------|
| Business Loan | Director's Loan to Company | £35,000 | 0% | Loan from company - repayable |

### Business Interests
| Business | Type | Co. Number | Ownership | Valuation | Revenue | Profit | Dividend Income | BPR Eligible |
|----------|------|------------|-----------|-----------|---------|--------|-----------------|--------------|
| Chen Tech Consulting Ltd | Limited Company | 12345678 | 60% | £750,000 | £520,000 | £280,000 | £60,000 | Yes |
| TechAngel Ventures LLP | LLP | OC456789 | 25% | £400,000 | £180,000 | £95,000 | £0 | No (investment-holding) |

**Chen Tech Consulting Ltd Details:**
- Industry: Technology
- Employees: 8
- VAT Registered: Yes (GB123456789)
- Trading Status: Trading
- Acquisition Date: 01 April 2018
- Acquisition Cost: £50,000
- Description: IT consultancy specialising in cloud infrastructure and digital transformation. 40% owned by business partner Marcus Wong.

**TechAngel Ventures LLP Details:**
- Industry: Financial Services
- Ownership: 25%
- Description: Angel investment syndicate. Passive investment vehicle - not qualifying for BPR as primarily investment-holding.
- Notes: Joint venture with 3 other tech entrepreneurs. Focuses on early-stage tech startups.

### Chattels
| Item | Value | Notes |
|------|-------|-------|
| Porsche 911 (2022 992 Carrera S) | £85,000 | - |
| Art Collection | £25,000 | Contemporary art pieces |
| Watch Collection | £35,000 | Rolex, Omega, IWC |

### Expenditure Breakdown (Monthly: £5,500)
| Category | Amount |
|----------|--------|
| Housing (Mortgage) | £1,350 |
| Entertainment | £800 |
| Transport | £800 |
| Food | £600 |
| Holidays | £500 |
| Insurance | £350 |
| Clothing | £300 |
| Gym & Wellness | £200 |
| Utilities | £200 |
| Other | £400 |

### Business Protection Status
| Item | Status |
|------|--------|
| Succession Plan | None in place |
| Buy-Sell Agreement | Not established |
| Key Person Insurance | £500,000 exists but no succession trigger |
| Shareholder Protection | Needs discussion |

### Risk Profile
- **Alex:** High
- **Notes:** Single tech entrepreneur with high risk tolerance, aggressive growth strategy, slightly more conservative for pension given early retirement goal

### Estate Planning
**Will Status:** No - **GAP IDENTIFIED**

**LPA:** No - **GAP IDENTIFIED**

### Key Concerns
1. What happens to my business if something happens to me?
2. How do I balance pension contributions vs. business investment?
3. Should I buy more property or diversify investments?
4. How do I plan for parents who may need care?

---

## Technical Notes

### Database Structure
- Each persona creates a primary `User` record with `is_preview_user=true` and `preview_persona_id` set
- Spouses are created as separate users with `preview_persona_id` suffixed with `_spouse`
- Users are linked via `spouse_id` (bidirectional)
- All related records (properties, pensions, policies, etc.) are created with appropriate `user_id` references

### Single-Record Architecture
Joint assets use a single-record pattern:
- ONE record stores the FULL value (not split)
- `joint_owner_id` links to the secondary owner
- `ownership_percentage` indicates each owner's share
- NO reciprocal records are created

### Owner Detection Logic
For assets that need spouse assignment:
1. Explicit `owner: "spouse"` flag in JSON
2. Account/pension name contains spouse's first name
3. Scheme name contains spouse's employer
4. Annual salary matches spouse's income (within 1%)

### Seeding Commands
```bash
# Seed all preview users
php artisan db:seed --class=PreviewUserSeeder --force

# Delete existing and reseed (destructive)
php artisan tinker --execute="App\Models\User::where('is_preview_user', true)->delete();"
php artisan db:seed --class=PreviewUserSeeder --force
```

### Login Credentials
Preview users bypass email verification. Login via:
```
POST /api/preview/login/{persona_id}
```
Where `persona_id` is one of: `young_family`, `peak_earners`, `widow`, `entrepreneur`
