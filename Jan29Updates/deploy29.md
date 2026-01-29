# Deployment Notes - January 29, 2026

---

## ISA Account Forms - Simplified UI

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Simplified the ISA subscription section in both Cash ISA (Savings) and Stocks & Shares ISA (Investment) add/edit forms by removing the ISA Type dropdown and improving helper text clarity.

### Changes Made

| Change | Description |
|--------|-------------|
| Removed ISA Type dropdown | ISA Type selector removed from blue ISA Subscription box (both forms) |
| Already Subscribed helper text | Added "This includes regular contributions." to clarify what the subscription amount covers |
| Regular Contribution helper text | Changed to "As of {date}, you have {no} contributions remaining for the {tax year} tax year." |
| Added `todaysDate` computed property | Displays current date in UK format (e.g., "29 January 2026") |
| Added `paymentsMadeThisTaxYear` computed | Calculates number of regular payments made since April 6 |
| Added `paymentsRemainingThisTaxYear` computed | Calculates remaining payments for the tax year |
| Fixed ISA allowance calculation | "Planned" contributions now only count remaining payments for rest of tax year, avoiding double-counting with "Already Subscribed" |

### ISA Allowance Calculation Logic

The "Planned" amount in the ISA Allowance tracker now correctly calculates remaining contributions:

1. **Months elapsed** = Current date - April 6 (start of tax year)
2. **Payments made** = Based on contribution frequency:
   - Monthly: months elapsed
   - Quarterly: months elapsed ÷ 3
   - Annually: 1 if 12+ months elapsed, else 0
3. **Payments remaining** = Total payments per year - payments made
4. **Planned amount** = (Payments remaining × contribution amount) + planned lump sum

**Example (January 29, 2026):**
- Tax year started April 6, 2025 → 9 months elapsed
- Monthly £500 contribution → 9 payments already made (included in "Already Subscribed")
- Remaining: 12 - 9 = 3 payments × £500 = £1,500 planned
- Plus any lump sum = total "Planned" amount

### Files Changed (2 files - Included in Build)

**Savings Module:**
```text
resources/js/components/Savings/SaveAccountModal.vue
```

**Investment Module:**
```text
resources/js/components/Investment/AccountForm.vue
```

---

## Expenditure Form - UI Improvements

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Redesigned the expenditure form with improved layout, new segmented control toggles, and separate tabs for user/spouse entry. This applies to both onboarding and the Valuable Info edit mode.

### Changes Made

| Change | Description |
|--------|-------------|
| Inline options cards | "Spouse Expenditure" and "Entry Method" cards now display side by side |
| Improved spacing | Added consistent gaps between info boxes and options cards |
| Segmented control toggles | Replaced checkbox with "Joint/Separate" toggle for spouse expenditure |
| Entry method toggle | Replaced buttons with "Detailed/Simple" segmented control |
| Green active state | Toggle buttons use green background on white container when active |
| Person tabs | When "Separate" is selected, shows tabs for user and spouse instead of side-by-side inputs |
| Tab displays name | Each tab shows the person's name (or linked account name if available) |
| Single input per field | Only one input field shown at a time based on active tab selection |

### Files Changed (1 file - Included in Build)

**User Profile Module:**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

---

## Private Company & Crowdfunding Investments

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Added Private Company and Crowdfunding investment types to the Investment module with comprehensive tracking for company details, investment terms, UK tax relief (EIS/SEIS/SITR/VCT), ownership structure, and exit tracking.

### Changes Made

| Change | Description |
|--------|-------------|
| New account types | Added "Private Company" and "Crowdfunding Investment" to account type dropdown |
| Company details section | Legal name, trading name, registration number, sector, website, crowdfunding platform |
| Investment details section | Investment date, amount, funding round, instrument type, shares, valuations |
| Ownership & legal section | Share class, voting/dividend rights, nominee structure, anti-dilution |
| UK tax relief section | EIS/SEIS/SITR/VCT tracking, certificate numbers, HMRC reference, relief claimed |
| Status & valuation section | Company status, latest valuation, ownership percentage |
| Exit details section | Exit type, date, proceeds, fees, loss relief tracking |
| Auto-calculated fields | Disposal restriction date (3 years from investment for EIS/SEIS) |

### Database Migration

New migration: `2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table.php`

Adds 40+ new columns to `investment_accounts` table for private investment tracking.

### Files Changed (4 files)

**Database:**
```text
database/migrations/2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table.php
```

**Backend:**
```text
app/Models/Investment/InvestmentAccount.php
app/Http/Controllers/Api/InvestmentController.php
```

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
```

---

## Rebuild Required: YES

Frontend Vue components changed. Full rebuild required.

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## Verification

After deployment, verify:

1. **Cash ISA Form** (Savings module):
   - Navigate to Net Worth > Cash tab
   - Click "Add Account" and select "Cash ISA"
   - Verify ISA Type dropdown is NOT present in the blue ISA Subscription box
   - Verify "Already Subscribed" helper text shows: "Amount already contributed to this account for 2025/26 tax year, including 9 regular payments."
   - Verify "Regular Contribution" helper text shows: "As of 29 January 2026, you have 3 contributions remaining for the 2025/26 tax year."

2. **Stocks & Shares ISA Form** (Investment module):
   - Navigate to Net Worth > Investments tab
   - Click "Add Account" and select "ISA (Stocks & Shares)"
   - Verify ISA Type dropdown is NOT present in the blue ISA Subscription box
   - Verify "Already Subscribed" helper text shows: "Amount already contributed to this account for 2025/26 tax year, including 9 regular payments."
   - Verify "Regular Contribution" helper text shows: "As of 29 January 2026, you have 3 contributions remaining for the 2025/26 tax year."

3. **ISA Allowance Calculation** (both forms):
   - Enter £500 monthly regular contribution
   - Verify "Planned" amount in allowance tracker shows ~£1,500 (3 remaining months × £500)
   - NOT £6,000 (full year × £500) - this would be double-counting
   - Add a £2,000 planned lump sum
   - Verify "Planned" amount increases to ~£3,500 (£1,500 + £2,000)

4. **Expenditure Form - UI Layout** (Onboarding):
   - Start onboarding as a married user (e.g., James Carter persona)
   - Navigate to the expenditure step
   - Verify spacing between "Why this matters" and "Note" info boxes
   - Verify "Spouse Expenditure" and "Entry Method" cards display side by side
   - Verify both cards have segmented control toggles (not checkboxes/buttons)
   - Verify active toggle option shows green background on white container

5. **Expenditure Form - Toggles** (Onboarding):
   - Toggle "Spouse Expenditure" between Joint and Separate
   - Verify Joint mode shows single inputs, Separate mode shows person tabs
   - Toggle "Entry Method" between Detailed and Simple
   - Verify Detailed shows category breakdown, Simple shows single total input
   - Verify spacing is maintained when toggling between modes

6. **Expenditure Form - Person Tabs** (Onboarding):
   - Set "Spouse Expenditure" to Separate
   - Verify tabs appear showing user name and spouse name
   - Click between tabs to verify each shows different input fields
   - Enter values for user, switch to spouse tab, enter values for spouse
   - Verify values are retained when switching between tabs

7. **Expenditure Form** (Valuable Info Edit):
   - Go to Dashboard > Valuable Info > Expenses
   - Click Edit
   - Verify same UI layout and behaviour as onboarding

8. **Private Company Investment** (Investment module):
   - Navigate to Net Worth > Investments tab
   - Click "Add Account" and select "Private Company"
   - Verify Provider, Country, Platform fields are hidden
   - Verify Company Details section appears with Legal Name, Trading Name, Registration Number, Sector, Website
   - Verify Investment Details section with Date, Amount, Funding Round, Instrument Type, Shares, Valuations
   - Verify Ownership & Legal section with Share Class, Holding Structure, Voting/Dividend Rights checkboxes
   - Verify UK Tax Relief section (blue box) with Relief Type dropdown, Certificate fields
   - Verify Status & Valuation section with Company Status, Latest Valuation

9. **Crowdfunding Investment** (Investment module):
   - Click "Add Account" and select "Crowdfunding Investment"
   - Verify all Private Company sections appear
   - Verify additional "Crowdfunding Platform" dropdown appears in Company Details section
   - Submit with Seedrs as platform and verify it saves

10. **EIS Tax Relief** (Investment module):
    - Add a Private Company investment
    - Select "EIS (30% relief)" as Tax Relief Type
    - Enter investment date
    - Save the investment
    - Edit the investment and verify Disposal Restriction Date is auto-calculated (3 years from investment date)

11. **Exit Tracking** (Investment module):
    - Edit a Private Company investment
    - Change Company Status to "Exited"
    - Verify Exit Details section appears
    - Verify Exit Type, Date, Gross Proceeds, Fees, Net Proceeds fields
    - Verify Loss Relief Eligible and Negligible Value Claim checkboxes

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Restore previous PHP files from backup
3. If database migration was run, rollback with:
   ```bash
   php artisan migrate:rollback --step=1
   ```

---
