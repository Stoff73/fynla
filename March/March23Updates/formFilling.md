# Form Filling Test — 23 March 2026

**User:** c.jones@csjones.co (Chris Jones)
**Environment:** Production (fynla.org)

Testing every create and edit form in the application.

---

## Property Form

### Property 1: Main Residence (Individual, with Repayment Mortgage) -- PASS

**Step 1 -- Basic Info:**
- Property Type: Main Residence
- Address: 42 Oak Road, Manchester, Greater Manchester, M1 4BT
- Purchase Date: 15/03/2024, Purchase Price: £285,000
- Current Value: £310,000, Valuation Date: 23/03/2026
- Mortgage: checked

**Step 2 -- Ownership:**
- Tenure: Freehold
- Ownership: Individual Owner (100%)

**Step 3 -- Mortgage:**
- Lender: Nationwide, Type: Repayment
- Original: £256,000, Outstanding: £248,000
- Rate: 4.25% Fixed, Monthly: £1,350
- Start: 15/03/2024, End: 15/03/2049

**Step 4 -- Costs:**
- Council Tax: £165, Gas: £75, Electricity: £85, Water: £35, Building Insurance: £25

**Result:** Saved successfully. Shows £310,000 value, £248,000 mortgage, £62,000 equity.

### Property 2: Buy to Let (Tenants in Common 60/40, with Interest Only Mortgage) -- PASS

**Step 1 -- Basic Info:**
- Property Type: Buy to Let
- Address: Flat 12, Victoria Court, Leeds, West Yorkshire, LS1 5DQ
- Purchase Date: 01/09/2022, Purchase Price: £175,000
- Current Value: £195,000
- Mortgage: checked

**Step 2 -- Ownership:**
- Tenure: Freehold
- Ownership: Tenants in Common, Your Share: 60%, Co-Owner: Sarah Williams (Other)
- Split display showed 60%/40% correctly

**Step 3 -- Mortgage:**
- Lender: Halifax, Type: Interest Only
- Original: £131,000, Outstanding: £131,000
- Rate: 5.1% Variable, Monthly: £557
- Start: 01/09/2022, End: 01/09/2047
- Mortgage Ownership: Joint (auto-populated Sarah Williams)

**Step 4 -- Costs:**
- Building Insurance: £30, Service Charge: £150
- Note showed: "Enter 100% of all property costs. These will be split by your ownership percentage (60%)"

**Step 5 -- BTL Details:**
- Monthly Rental Income: £950
- Tenant: James Thompson (j.thompson@email.com)
- Lease: 15/01/2025 to 14/01/2026
- Managing Agent: Leeds Lettings Ltd, Fee: £95/month

**Result:** Saved successfully. Shows full value £195,000, your share £117,000 (60%), mortgage share £78,600, equity £38,400.

---

## Investment Account Form

### Investment 1: Stocks & Shares ISA (Individual, Medium Risk) -- PASS

- Account Type: ISA (Stocks & Shares)
- Provider: Vanguard, Platform: LifeStrategy 80
- Current Value: £12,500
- ISA Subscribed This Year: £2,200
- Regular Contribution: £200/month
- Platform Fee: 0.15% annually
- Risk Level: Medium (overriding Upper-Medium profile)
- ISA allowance tracking showed £20,000 remaining correctly

**Result:** Saved successfully. Monte Carlo projection showing £38,455 at 80% over 10 years.

### Investment 2: General Investment Account (Individual, Upper-Medium Risk) -- PASS

- Account Type: General Investment Account
- Provider: Hargreaves Lansdown
- Current Value: £8,750
- Risk Level: Upper-Medium
- Ownership: Individual (Joint attempted but correctly showed "No spouse linked")

**Result:** Saved successfully. Portfolio total updated to £21,250.

**Note:** Joint ownership requires a linked spouse account. "No spouse linked - add spouse in Family Members" message shown correctly for single user.

---

## Pension Form (DC / Money Purchase)

### Pension 1: Workplace Pension (Occupational, Medium Risk) -- PASS

- Pension Type: Occupational (Workplace)
- Scheme: Tech Corp Workplace Pension
- Provider: Scottish Widows, Policy: WP789456
- Current Fund Value: £18,500
- Annual Salary: £35,000
- Employee Contribution: 5%, Employer Contribution: 3%
- Expected Return: 5% p.a.
- Risk Level: Medium
- Salary sacrifice checkbox available (not checked)

**Result:** Saved successfully. Shows retirement projections: Target Income £28,583, Required capital £608,138. Monte Carlo chart rendering. Warning shown: "Defined Contribution fund depletes at age 67".

---

## Protection Policy Form

### Life Insurance (Level Term, In Trust, Mortgage Coverage) -- PASS

- Policy Type: Life Insurance
- Life Policy Type: Level Term
- Provider: Legal & General, Policy: LG-456789
- Sum Assured: £300,000, Premium: £28/monthly
- Policy Term: 25 years
- In Trust: Yes
- Mortgage coverage: Yes
- Beneficiary: not set (single user)

**Result:** Saved successfully. Gap analysis rendered showing:
- Debt Protection: £300k cover vs £379k debt (£79k shortfall, medium)
- Income Replacement: £26,250/yr shortfall (low)
- Critical Illness: £70,000 shortfall (medium)
- Sickness: £11,325/yr shortfall after SSP (low)
- Disability: £17,500/yr shortfall (low)
- Affordability: 1.0% of income (recommended 5-10%)
- Coverage Summary: 79% debt coverage

---

## Liability Form

### Personal Loan (Car Loan) -- PASS

- Liability Type: Personal Loan
- Name: Car Loan - Ford Focus
- Balance: £8,500, Monthly Payment: £275
- Interest Rate: 6.9% p.a.
- Maturity: 01/09/2028

**Result:** Saved successfully. Total liabilities updated to 3 (2 mortgages + 1 personal loan), £387,500 total, £2,182/mo.

---

## Personal Valuable / Chattel Form

### Vehicle (Ford Focus ST) -- PASS

- Type: Vehicle (button grid selection)
- Name: Ford Focus ST
- Make: Ford, Model: Focus ST, Year: 2021, Registration: MN21 XYZ
- Current Value: £15,500, Purchase Price: £24,000, Purchase Date: 15/06/2021
- Ownership: Individual (100%)

**Result:** Saved successfully. CGT Exempt badge showing (wasting asset). Vehicle details (make/model/year/registration) displayed correctly.

---

## Life Event Form

### Wedding (Expense, Likely) -- PASS

- Event Name: Wedding
- Event Type: Wedding (from grouped dropdown -- Expense Events)
- Amount: £15,000
- Expected Date: 20/09/2027
- Certainty: Likely (button group selection)
- Description: Venue and catering costs
- Show in projection chart: Yes
- Show in household view: Yes

**Result:** Saved successfully. Shows as expense event with "Likely" badge, "1 year" countdown. Summary updated: Expected Expenses £15,000, Net Impact -£15,000. Edit and Delete buttons present.

---

## Property Edit Form

### Edit Main Residence (Change Current Value) -- PASS

- Opened edit form for 42 Oak Road
- All fields pre-filled correctly (address, purchase date/price, mortgage checkbox, etc.)
- Changed Current Value from £310,000 to £320,000
- Clicked Save Property

**Result:** Saved successfully. Detail view updated: Full Property Value £320,000. Value Change updated from £25,000 (8.77%) to £35,000 (12.28%). All other fields preserved.

---

## Pension Form (DB / Final Salary)

### Pension 2: Defined Benefit (Final Salary, Deferred) -- PASS

- Employer: Previous Corp Pension
- Scheme Status: Deferred, Scheme Type: Final Salary
- Annual Income at Retirement: £4,200
- Service Years: 3, Pensionable Salary: £28,000
- Accrual Rate: 1/60, Revaluation Rate: 2.5% p.a.
- Notes: Deferred from previous employer 2020-2023
- Important disclaimer shown about DB transfer advice

**Result:** Saved successfully. Shows as "Final Salary" with £4,200 annual pension. Retirement projections updated: Target Income £31,733, Required capital £675,160.

---

## Business Interest Form (6-step)

### Business: Sole Trader (CJ Web Solutions) -- PASS

**Step 1 -- Basic Info:**
- Business Name: CJ Web Solutions, Type: Sole Trader, Status: Trading
- Industry: Technology
- Description: Freelance web development and consulting

**Step 2 -- Ownership:** Individual Owner (100%)

**Step 3 -- Valuation:**
- Current Valuation: £25,000, Method: Self Assessed

**Step 4 -- Financials:**
- Annual Revenue: £45,000, Annual Profit: £12,000

**Step 5 -- Tax:** Skipped (defaults)

**Step 6 -- Exit Planning:**
- Acquired: 15/01/2023, Cost Basis: £5,000
- Business Property Relief (BPR) Eligible: Yes

**Result:** Saved successfully. Shows Sole Trader, Trading, £25,000 valuation. "Business Relief Eligible" badge displayed (100% IHT relief). Edit and Delete buttons present.

---

## Trust Form

### Trust: Discretionary (Jones Family Trust) -- PASS

- Trust Name: Jones Family Trust
- Trust Type: Discretionary Trust
- Creation Date: 01/06/2025
- Initial Value: £10,000, Current Value: £10,500
- Beneficiaries: Future children
- Trustees: Chris Jones, Margaret Jones (mother)
- Purpose: Education fund for future children
- Active: Yes

**Result:** Saved successfully. Shows RPT badge, Active status, creation date, trustees, beneficiaries. UK Trust Types Guide available.

---

## Delete Item

### Delete Life Event (Wedding) -- PASS

- Clicked Delete button on Wedding life event
- Confirmation dialog appeared: "Are you sure you want to delete 'Wedding'? This action cannot be undone."
- Confirmed deletion
- Event removed, summary updated to £0 / 0 events, empty state shown

---

## Summary

### Forms Tested (Create)

| Form | Type/Ownership | Result |
|------|---------------|--------|
| Property (Main Residence) | Individual, Freehold, Repayment Mortgage | PASS |
| Property (Buy to Let) | Tenants in Common 60/40, Interest Only Mortgage, BTL Details | PASS |
| Investment (S&S ISA) | Individual, Medium Risk, ISA tracking | PASS |
| Investment (GIA) | Individual, Upper-Medium Risk | PASS |
| Pension (DC Workplace) | Occupational, Employee/Employer %, Salary sacrifice | PASS |
| Pension (DB Final Salary) | Deferred, Accrual rate, Revaluation | PASS |
| Protection (Level Term Life) | In Trust, Mortgage coverage, Gap analysis | PASS |
| Liability (Personal Loan) | Car loan, Interest rate, Maturity date | PASS |
| Personal Valuable (Vehicle) | Make/Model/Year/Reg, CGT exempt badge | PASS |
| Life Event (Wedding) | Expense type, Likely certainty, Projection settings | PASS |
| Business Interest (Sole Trader) | 6-step form, BPR eligible, Exit planning | PASS |
| Trust (Discretionary) | Beneficiaries, Trustees, Purpose | PASS |
| Savings Account (Easy Access) | Emergency fund, Interest rate (onboarding) | PASS |
| Goal (House Deposit) | Target amount, Target date (onboarding) | PASS |

### Forms Tested (Edit)

| Form | Action | Result |
|------|--------|--------|
| Property Edit | Changed current value £310k to £320k | PASS |

### Delete

| Item | Result |
|------|--------|
| Life Event (Wedding) | PASS -- confirmation dialog, item removed |

### Not Tested (Low Priority)

- State Pension form (simple -- forecast amount + qualifying years)
- Gift form (simple -- date, recipient, value, type)
- Family Member form (user is single, no spouse to add)
- Edit investment account (tested earlier with chris@fynla.org)
- Edit pension (tested earlier with chris@fynla.org)

**Total: 14 create forms tested, 1 edit form tested, 1 delete tested. All PASS. 0 bugs found during form filling.**

