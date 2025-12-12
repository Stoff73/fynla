# Tax Configuration Update Guide

**Version**: 1.0
**Last Updated**: November 2025
**Target Audience**: System Administrators, Financial Advisors with Admin Access

---

## Table of Contents

1. [Annual Tax Update Process](#annual-tax-update-process)
2. [Tax Research Sources](#tax-research-sources)
3. [Creating a New Tax Year](#creating-a-new-tax-year)
4. [Testing New Tax Configuration](#testing-new-tax-configuration)
5. [Activating New Tax Year](#activating-new-tax-year)
6. [Rollback Procedure](#rollback-procedure)
7. [Troubleshooting](#troubleshooting)
8. [FAQs](#faqs)

---

## Annual Tax Update Process

### Timeline

The UK tax year runs from **April 6 to April 5**. Follow this timeline for updating tax configuration:

| Phase | Timeline | Activity |
|-------|----------|----------|
| **Research** | January - February | Monitor HMRC announcements, budget changes |
| **Prepare** | March 1 - March 31 | Create new tax year config, update values |
| **Test** | April 1 - April 5 | Test calculations, verify accuracy |
| **Activate** | April 6 (00:01) | Activate new tax year |
| **Monitor** | April 6 - April 30 | Monitor for issues, verify calculations |

### Step-by-Step Annual Update

**Step 1: Research New Tax Values (January - March)**

Monitor official announcements:
- HMRC website for rate changes
- UK Government Budget announcements (usually March)
- Professional tax updates from accountancy bodies

**Step 2: Access Admin Panel (March)**

1. Log in to Fynla application with admin credentials
2. Navigate to **Admin Panel** (admin icon in top navigation)
3. Click **Tax Configuration** in the sidebar

**Step 3: Create New Tax Year (March 1-31)**

See [Creating a New Tax Year](#creating-a-new-tax-year) section below for detailed instructions.

**Step 4: Test Configuration (April 1-5)**

See [Testing New Tax Configuration](#testing-new-tax-configuration) section below.

**Step 5: Activate on April 6**

See [Activating New Tax Year](#activating-new-tax-year) section below.

**Step 6: Monitor (April 6-30)**

- Check user calculations for accuracy
- Monitor support tickets for tax-related issues
- Verify all modules using new values

---

## Tax Research Sources

### Official Sources (PRIMARY)

1. **HMRC Official Website**
   - URL: https://www.gov.uk/government/organisations/hm-revenue-customs
   - Check: Annual tax rate announcements
   - Section: "Tax rates and allowances"

2. **UK Government Budget Documents**
   - URL: https://www.gov.uk/government/publications
   - Check: Spring Budget (usually March)
   - Look for: "Red Book" (Budget report)

3. **GOV.UK Tax Guidance**
   - Income Tax: https://www.gov.uk/income-tax-rates
   - IHT: https://www.gov.uk/inheritance-tax
   - Pensions: https://www.gov.uk/tax-on-your-private-pension
   - ISAs: https://www.gov.uk/individual-savings-accounts

### Professional Resources (SECONDARY)

4. **ICAEW (Institute of Chartered Accountants)**
   - URL: https://www.icaew.com
   - Tax Faculty publications

5. **CIOT (Chartered Institute of Taxation)**
   - URL: https://www.tax.org.uk
   - Technical guidance and updates

6. **STEP (Society of Trust and Estate Practitioners)**
   - URL: https://www.step.org
   - Estate planning and IHT updates

### Historical Archives

7. **UK Tax Rate History**
   - The Tax Foundation: International tax data
   - HMRC Archives: Historical rates (past 10 years)
   - Professional tax guides: Tolley's, Bloomsbury

---

## Creating a New Tax Year

### Method 1: Duplicate Existing Tax Year (RECOMMENDED)

This is the fastest method as it copies all values from the current active year.

**Steps**:

1. **Navigate to Tax Configuration**
   - Admin Panel → Tax Configuration

2. **Select Current Active Tax Year**
   - Find the row with green "ACTIVE" badge (e.g., "2025/26")

3. **Click Duplicate Button**
   - Click the "Duplicate" button (copy icon) on the right
   - System will create new tax year configuration

4. **Update Tax Year and Dates**
   - Tax Year: `2026/27` (format: YYYY/YY)
   - Effective From: `2026-04-06`
   - Effective To: `2027-04-05`

5. **Update Changed Values**
   - Review each section and update values that changed
   - See "Common Changes" section below

6. **Add Notes**
   - Document key changes in the Notes field
   - Example: "Increased personal allowance to £13,000. NRB unchanged at £325,000."

7. **Save Configuration**
   - Click "Save" button
   - Status will be "Inactive" until you activate it on April 6

### Method 2: Create From Scratch

Only use this if starting from a completely different tax regime.

**Steps**:

1. **Click "Create New Tax Year"** button
2. **Fill in all fields manually** (see structure below)
3. **Save configuration**

### Common Changes Year-to-Year

Values that **typically change**:
- ✅ Personal Allowance (usually increases with inflation)
- ✅ Income Tax Bands (thresholds may increase)
- ✅ National Insurance Thresholds
- ✅ ISA Annual Allowance (occasionally increases)
- ✅ Capital Gains Tax Annual Exempt Amount
- ✅ Dividend Tax Allowance
- ✅ State Pension Amount

Values that **rarely change**:
- ⏸️ IHT Nil Rate Band (frozen since 2009)
- ⏸️ IHT Residence Nil Rate Band (frozen until 2028)
- ⏸️ IHT Rate (40% - unchanged for decades)
- ⏸️ Pension Annual Allowance (occasional changes)
- ⏸️ Income Tax Rates (20%, 40%, 45% - stable)

Values that **never change**:
- 🔒 Tax Year Structure (April 6 - April 5)
- 🔒 Spouse Exemption Rules
- 🔒 PET 7-Year Rule
- 🔒 Carry Forward Rules (3 years for pensions)

### Configuration Structure Reference

```json
{
  "income_tax": {
    "personal_allowance": 12570,
    "bands": [
      {"name": "Basic Rate", "threshold": 0, "rate": 0.20},
      {"name": "Higher Rate", "threshold": 37700, "rate": 0.40},
      {"name": "Additional Rate", "threshold": 125140, "rate": 0.45}
    ]
  },
  "national_insurance": {
    "class_1": {
      "employee": {
        "primary_threshold": 12570,
        "upper_earnings_limit": 50270,
        "main_rate": 0.08,
        "additional_rate": 0.02
      }
    }
  },
  "isa": {
    "annual_allowance": 20000,
    "lifetime_isa": {
      "annual_allowance": 4000
    }
  },
  "pension": {
    "annual_allowance": 60000,
    "mpaa": 10000,
    "tapered_annual_allowance": {
      "threshold_income": 200000,
      "adjusted_income": 260000,
      "minimum_allowance": 10000
    }
  },
  "inheritance_tax": {
    "nil_rate_band": 325000,
    "residence_nil_rate_band": 175000,
    "standard_rate": 0.40
  },
  "capital_gains_tax": {
    "annual_exempt_amount": 3000,
    "rates": {
      "residential": {
        "basic_rate": 0.18,
        "higher_rate": 0.24
      }
    }
  }
}
```

---

## Testing New Tax Configuration

**CRITICAL**: Never activate a new tax year without thorough testing.

### Pre-Activation Testing Checklist

#### 1. Admin Panel Review

- [ ] Open the new tax year in admin panel
- [ ] Verify all sections have values (no missing fields)
- [ ] Check JSON is valid (no syntax errors)
- [ ] Review notes for accuracy

#### 2. Spot-Check Key Values

Compare against official HMRC sources:

| Value | Location | Verify Against |
|-------|----------|----------------|
| Personal Allowance | `income_tax.personal_allowance` | HMRC Income Tax page |
| NRB | `inheritance_tax.nil_rate_band` | HMRC IHT page |
| RNRB | `inheritance_tax.residence_nil_rate_band` | HMRC IHT page |
| ISA Allowance | `isa.annual_allowance` | HMRC ISA page |
| Pension Annual Allowance | `pension.annual_allowance` | HMRC Pension page |

#### 3. Test Calculations (Before Activation)

**Method**: Temporarily activate new tax year in development/staging environment.

**Test Cases**:

**A. Income Tax Calculation**
- Test user with £50,000 salary
- Expected: Basic rate up to £50,270, higher rate above
- Formula: (£50,000 - £12,570) × 20% = £7,486

**B. IHT Calculation**
- Test estate worth £500,000
- Expected: (£500,000 - £325,000) × 40% = £70,000

**C. ISA Allowance Tracking**
- Test user with £15,000 in Cash ISA
- Expected: £5,000 remaining allowance

**D. Pension Annual Allowance**
- Test user with £50,000 pension contribution
- Expected: £10,000 remaining allowance

#### 4. Module-by-Module Verification

**Protection Module**:
- [ ] Human capital calculation uses correct income tax rates
- [ ] Recommendations reflect current tax efficiency

**Savings Module**:
- [ ] ISA allowance tracker shows correct £20,000 limit
- [ ] Emergency fund calculations accurate

**Investment Module**:
- [ ] CGT calculations use correct annual exempt amount
- [ ] Dividend tax calculations accurate

**Retirement Module**:
- [ ] Pension annual allowance correct (£60,000)
- [ ] Tapered allowance threshold accurate (£260,000)
- [ ] MPAA correct (£10,000)

**Estate Module**:
- [ ] IHT calculations use correct NRB (£325,000)
- [ ] RNRB applied correctly (£175,000)
- [ ] Gifting exemptions accurate

#### 5. Edge Case Testing

Test unusual scenarios:
- [ ] User with income above taper threshold (£100,000+)
- [ ] User with both Cash ISA and S&S ISA
- [ ] User exceeding pension annual allowance
- [ ] Estate with RNRB taper (above £2M)

---

## Activating New Tax Year

**TIMING**: Activate on April 6 at 00:01 AM (or earliest convenient time on April 6).

### Activation Steps

1. **Navigate to Tax Configuration**
   - Log in as admin
   - Admin Panel → Tax Configuration

2. **Locate New Tax Year Row**
   - Find the row for new tax year (e.g., "2026/27")
   - Status should show "Inactive"

3. **Click Activate Button**
   - Click green "Activate" button
   - Confirm activation in modal dialog

4. **Verify Activation**
   - New tax year should show green "ACTIVE" badge
   - Previous tax year should now show "Inactive"
   - Only ONE tax year should be active at any time

5. **Immediate Checks**
   - Open a user dashboard
   - Check Protection module shows updated calculations
   - Check Estate module shows updated IHT values
   - Verify ISA allowance tracker resets for new year

### What Happens on Activation

**Automatic Changes**:
- All services immediately use new tax configuration
- Previous tax year automatically deactivated
- TaxConfigService returns new values
- All module calculations update automatically

**No Manual Changes Needed**:
- ✅ Services auto-update (no code deploy required)
- ✅ User dashboards refresh automatically
- ✅ Cached calculations auto-invalidate

**User Experience**:
- Users see updated calculations on next page load
- No action required from users
- Historical data remains unchanged (preserves accuracy)

---

## Rollback Procedure

If you discover errors in the new tax year configuration after activation, you can quickly rollback.

### When to Rollback

Rollback immediately if:
- ❌ Major calculation errors detected
- ❌ Wrong tax rates causing incorrect advice
- ❌ System errors due to malformed JSON
- ❌ Critical values missing

### Rollback Steps

1. **Navigate to Tax Configuration**
   - Admin Panel → Tax Configuration

2. **Locate Previous Tax Year**
   - Find the recently deactivated tax year (e.g., "2025/26")

3. **Click Activate**
   - Click "Activate" button on previous tax year
   - Confirm activation

4. **Verify Rollback**
   - Previous tax year should show "ACTIVE"
   - New tax year should show "Inactive"

5. **Fix the Issue**
   - Edit the new tax year configuration
   - Correct the erroneous values
   - Save changes

6. **Re-Test**
   - Follow [Testing New Tax Configuration](#testing-new-tax-configuration) again
   - Verify corrections

7. **Re-Activate (When Ready)**
   - Activate new tax year again
   - Monitor closely for 24 hours

### Rollback Impact

**User Impact**:
- Minimal - calculations revert to previous tax year
- Historical data preserved
- No data loss

**System Impact**:
- Cached calculations auto-invalidate
- Services immediately use rollback config
- No code deployment needed

---

## Troubleshooting

### Issue 1: Services Not Using New Tax Configuration

**Symptom**: After activation, calculations still show old values.

**Possible Causes**:
1. Cache not cleared
2. User needs to refresh browser
3. Background jobs still using old config

**Solutions**:
```bash
# Clear application cache
php artisan cache:clear

# Clear config cache
php artisan config:clear

# Restart queue workers
php artisan queue:restart
```

**Verification**:
```bash
# Check active tax year in database
mysql -u root -e "SELECT tax_year, is_active FROM tax_configurations WHERE is_active = 1"

# Should return exactly ONE row with is_active = 1
```

### Issue 2: Validation Errors When Saving

**Symptom**: "The config_data.income_tax.bands.0.threshold field is required."

**Cause**: Missing required fields in JSON structure.

**Solution**: Ensure all required fields present:
- Income tax bands: `name`, `threshold`, `rate`
- NI thresholds: `primary_threshold`, `upper_earnings_limit`
- Pension: `adjusted_income_threshold` (for taper calculation)

**Example Fix**:
```json
// ❌ WRONG
"bands": [
  {"rate": 0.20, "min": 0, "max": 37700}
]

// ✅ CORRECT
"bands": [
  {"name": "Basic Rate", "threshold": 0, "rate": 0.20}
]
```

### Issue 3: Cannot Activate New Tax Year

**Symptom**: "Only one tax year can be active at a time."

**Cause**: Another tax year is already active.

**Solution**:
1. System should automatically deactivate old year
2. If not, manually deactivate old year first
3. Then activate new year

### Issue 4: Calculations Seem Incorrect

**Symptom**: IHT liability showing £0 when should be higher.

**Diagnosis Steps**:

1. **Verify Active Tax Year**:
   ```bash
   mysql -u root -e "SELECT tax_year, is_active FROM tax_configurations"
   ```

2. **Check Specific Values**:
   ```bash
   mysql -u root -e "SELECT JSON_EXTRACT(config_data, '$.inheritance_tax.nil_rate_band') FROM tax_configurations WHERE is_active = 1"
   ```

3. **Test TaxConfigService**:
   ```bash
   php artisan tinker
   >>> app(\App\Services\TaxConfigService::class)->getInheritanceTax()
   ```

4. **Check Service Implementation**:
   - Verify service uses TaxConfigService (not hardcoded values)
   - Check for typos in config key names

### Issue 5: Admin Panel Won't Load Tax Configuration

**Symptom**: Blank page or spinner never stops.

**Possible Causes**:
1. Malformed JSON in config_data
2. Database connection issue
3. Permission issue

**Solutions**:

1. **Check Laravel Logs**:
   ```bash
   tail -n 50 storage/logs/laravel.log
   ```

2. **Validate JSON**:
   ```bash
   mysql -u root -e "SELECT tax_year, JSON_VALID(config_data) FROM tax_configurations"
   ```

3. **Check Permissions**:
   ```bash
   # Verify user is admin
   mysql -u root -e "SELECT id, email, is_admin FROM users WHERE is_admin = 1"
   ```

### Issue 6: ISA Allowance Not Resetting

**Symptom**: ISA allowance tracker still shows previous year's usage.

**Cause**: ISATracker service caching old tax year.

**Solution**:
```bash
# Clear cache
php artisan cache:clear

# Check ISA tracking records
mysql -u root -e "SELECT user_id, tax_year, total_used FROM isa_allowance_tracking"
```

---

## FAQs

### Q1: How far in advance should I create next tax year?

**A**: Create configuration in March, but don't activate until April 6. This allows time for testing without affecting current users.

### Q2: Can I edit an active tax year?

**A**: Yes, but be cautious. Changes take effect immediately and affect all user calculations. Better to:
1. Deactivate current year
2. Edit values
3. Test thoroughly
4. Re-activate

### Q3: How many tax years should I keep in the system?

**A**: Recommended: Keep current year + 5 previous years. This allows:
- Historical analysis
- User journey tracking
- Audit trail

### Q4: What if the UK changes the tax year structure?

**A**: Highly unlikely (tax year unchanged since 1800s), but if it happens:
1. Update validation rules in `StoreTaxConfigurationRequest`
2. Update date calculations in `TaxConfigService`
3. Create migration for date format changes

### Q5: Can users see historical tax years?

**A**: No, users always see calculations based on the active tax year. Historical years are for admin reference and rollback only.

### Q6: What happens to data created under old tax year?

**A**: Historical data is preserved with original calculations. Only new calculations use the new tax year. This ensures audit trail accuracy.

### Q7: Do I need to notify users about tax year changes?

**A**: Recommended but not required. Consider:
- In-app notification on April 6
- Email to active users highlighting key changes
- Blog post explaining major rate changes

### Q8: How do I handle mid-year tax changes (emergency budgets)?

**A**:
1. Edit active tax year configuration immediately
2. Add note documenting the emergency change
3. Test calculations thoroughly
4. Monitor user reports closely

### Q9: Can I have multiple admins managing tax config?

**A**: Yes, any user with `is_admin = true` can access tax configuration. Use audit logs to track changes.

### Q10: What if I accidentally delete a tax year?

**A**: Tax years with `is_active = true` cannot be deleted (system protection). For inactive years:
1. Check database backups
2. Restore from backup if needed
3. Or recreate from official sources

---

## Support & Resources

### Internal Documentation
- `CLAUDE.md` - Developer guide and architecture
- `DATABASE_SCHEMA_GUIDE.md` - Database structure
- `taxCentralplan.md` - Tax configuration project plan

### External Resources
- HMRC: https://www.gov.uk/government/organisations/hm-revenue-customs
- Tax Rates: https://www.gov.uk/income-tax-rates
- ISAs: https://www.gov.uk/individual-savings-accounts
- Pensions: https://www.gov.uk/tax-on-your-private-pension
- IHT: https://www.gov.uk/inheritance-tax

### Technical Support
- Laravel Framework: https://laravel.com/docs
- TaxConfigService Code: `app/Services/TaxConfigService.php`
- Admin Routes: `routes/api.php` (search for `tax-settings`)
- Admin Controller: `app/Http/Controllers/Admin/TaxConfigurationController.php`

---

**Document Version**: 1.0
**Last Updated**: November 2025
**Next Review**: January 2026 (before 2026/27 tax year creation)

---

🤖 Built with [Claude Code](https://claude.com/claude-code)
