# New Personas - Implementation Tasks

## Status: COMPLETED (16 Jan 2026)

Adding two new preview personas to better represent Fynla's target demographics:

1. **Young Adult Saver** (`young_saver`) - Alex Morgan, 24, single, renting, building savings
2. **Retired Couple** (`retired_couple`) - Patricia & Harold Bennett, 70/72, drawing pensions, gifting

---

## Tasks

### Phase 1: Create Persona JSON Files

- [x] **Create `young_saver.json`**
  - Location: `resources/js/data/personas/young_saver.json`
  - Key data:
    - Alex Morgan, 24, single, Junior Data Analyst, £32,000/year
    - Renting in Manchester (no properties)
    - Cash ISA (£3,200), LISA (£2,400), current account (£850)
    - Workplace pension with NEST (£4,800)
    - Student loan Plan 2 (£42,000)
    - No protection policies
    - Tight budget with focus on saving

- [x] **Create `retired_couple.json`**
  - Location: `resources/js/data/personas/retired_couple.json`
  - Key data:
    - Patricia (70) & Harold (72) Bennett, married, retired
    - Owned home in Tunbridge Wells (£550,000, no mortgage)
    - Patricia: NHS DB pension (£18,500) + State Pension (£11,500)
    - Harold: Civil Service DB pension (£22,000) + State Pension (£11,500)
    - Combined savings/investments ~£295,500
    - 5 grandchildren, active gifting strategy
    - No liabilities
    - IHT exposure focus

### Phase 2: Update Seeder

- [x] **Modify `PreviewUserSeeder.php`**
  - Add `young_saver` and `retired_couple` to persona array
  - Ensure handling for:
    - Single users (no spouse)
    - DB pensions in payment
    - Student loan liability type
    - LISA savings account type
    - Grandchildren as `other_dependent` relationship
  - **Additional changes made:**
    - Added `createGifts()` method for seeding gift records
    - Added support for `spouse_state_pension` in JSON files
    - Added `Gift` model import

- [x] **Update `PreviewController.php`**
  - Added `young_saver` and `retired_couple` to `VALID_PERSONAS`
  - Added metadata entries for both personas

### Phase 3: Testing

- [x] **Run seeder**
  ```bash
  php artisan db:seed --class=PreviewUserSeeder --force
  ```

- [x] **Test API login**
  - `POST /api/preview/login/young_saver` - Working
  - `POST /api/preview/login/retired_couple` - Working

- [x] **Verify data loads** for both personas
  - Savings accounts load correctly
  - Retirement pensions load correctly
  - Liabilities load correctly (student loan)
  - Estate/gifts load correctly
  - Properties load correctly

- [x] **Test each module**
  - Net Worth (properties, liabilities) - Verified
  - Savings (ISA, LISA, accounts) - Verified
  - Investment (portfolios) - Verified
  - Retirement (pensions) - Verified
  - Protection (policies or empty states) - N/A for these personas
  - Estate (IHT calculations, gifting) - Verified

### Phase 4: UI Updates

- [x] **Update Vuex store** (`resources/js/store/modules/preview.js`)
  - Added imports for `young_saver.json` and `retired_couple.json`
  - Added PERSONA_DATA entries for both
  - Added PERSONA_METADATA entries with netWorthRange, focus, description

- [x] **Update PersonaSelectionModal.vue** (landing page modal)
  - Added emoji mappings: young_saver (🎓), retired_couple (👴👵)
  - Added header gradient: cyan for young_saver, rose for retired_couple
  - Added focus badge classes

- [x] **Update PersonaSelector.vue** (dashboard dropdown)
  - Added button color classes for dark variant
  - Added avatar background colors
  - Added emoji mappings

- [x] **Update PersonaIntroModal.vue** (intro modal after selection)
  - Added emoji mappings
  - Added header gradient classes
  - Added key financial concerns for both personas

---

## Young Saver Persona Details

### Alex Morgan

| Field | Value |
|-------|-------|
| Age | 24 |
| Status | Single |
| Job | Junior Data Analyst |
| Employer | DataTech Solutions Ltd |
| Income | £32,000/year |
| Rent | £650/month (shared house) |
| Location | Manchester |

### Financial Summary

| Category | Value |
|----------|-------|
| Cash ISA | £3,200 |
| LISA | £2,400 |
| Current Account | £850 |
| Easy Access Savings | £1,500 |
| Workplace Pension | £4,800 |
| Student Loan | £42,000 |
| **Net Worth** | ~-£34,000 |

### Key Module States

- **Properties**: Empty (renting)
- **Savings**: 4 accounts (Cash ISA, LISA, Current, Easy Access)
- **Investments**: None
- **Retirement**: 1 DC pension (NEST)
- **Protection**: None (or employer death-in-service only)
- **Estate**: Minimal planning needed

---

## Retired Couple Persona Details

### Patricia & Harold Bennett

| Field | Patricia | Harold |
|-------|----------|--------|
| Age | 70 | 72 |
| Former Job | NHS Nurse Manager | Civil Servant (HMRC) |
| DB Pension | £18,500/year | £22,000/year |
| State Pension | £11,500/year | £11,500/year |

### Financial Summary

| Asset | Value |
|-------|-------|
| Main Residence | £550,000 |
| Patricia's ISAs | £110,000 |
| Harold's ISAs | £127,000 |
| Premium Bonds | £50,000 |
| Current Account | £8,500 |
| **Gross Estate** | ~£845,500 |
| **Est. IHT** | ~£138,200 |

### Family

- **Children**: Mark (45), Susan (42)
- **Grandchildren**: Emma (16), Thomas (13), Lucy (11), Sophie (8), William (5)
- **Annual gifting**: £6,000 (£3,000 each) + £2,500 to grandchildren JISAs

### Key Module States

- **Properties**: 1 main residence (owned outright)
- **Savings**: 5 accounts (current, 2x Cash ISA, Premium Bonds, 2x S&S ISA)
- **Investments**: 2 S&S ISAs
- **Retirement**: 2 DB pensions (in payment), 2 State Pensions
- **Protection**: Minimal (spouse benefits from DB pensions)
- **Estate**: IHT planning focus, active gifting

---

## Research Notes

### Young Adult Saving Products (UK 2025)

1. **Lifetime ISA (LISA)** - 25% government bonus, max £4,000/year, for first home or retirement at 60
2. **Cash ISA** - Tax-free interest, £20,000 annual limit
3. **Workplace Pension** - Auto-enrolment minimum: employee 5%, employer 3%
4. **Help to Save** - For those on Universal Credit/Working Tax Credit (not applicable here)
5. **Student Loan Plan 2** - Repay 9% over £27,295, write-off after 30 years

### Retired Couple Considerations (UK 2025/26)

1. **DB Pension Security** - Index-linked (CPI), spouse benefits typically 50%
2. **State Pension** - Full rate £11,500/year (2025/26), triple lock
3. **IHT Changes** - April 2027: Pensions included in estate for IHT
4. **Gifting Rules** - £3,000 annual exemption per person, PET 7-year rule
5. **Care Costs** - Potential concern for couples in 70s
6. **Equity Release** - Option but typically last resort
