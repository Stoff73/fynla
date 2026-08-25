# Fynla Database Seeding & Migration Guide

This document provides comprehensive instructions for database seeding and migrations. **All Claude instances must follow this guide when working with database changes.**

## Quick Reference

### After Fresh Migration (Most Common)

```bash
# Run migrations then seed everything
php artisan migrate
php artisan db:seed
```

### After Pulling New Code

```bash
# Run any new migrations
php artisan migrate

# Re-seed required data (safe to run multiple times)
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Refresh Preview Personas Only

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Seeder Categories

### Phase 1: Required Seeders (MUST RUN)

These seeders are **required for the application to function**. They must always be run after migrations.

| Seeder | Table(s) | Purpose |
|--------|----------|---------|
| `TaxConfigurationSeeder` | `tax_configurations` | UK tax rates, allowances, thresholds (2025/26) |
| `TaxProductReferenceSeeder` | `tax_product_references` | Tax treatment info for ISAs, GIAs, bonds, etc. |
| `ActuarialLifeTablesSeeder` | `actuarial_life_tables` | ONS life expectancy tables for estate/retirement projections |
| `AdminUserSeeder` | `users` | Creates admin account (admin@fps.com) and demo user |
| `PreviewUserSeeder` | `users`, `properties`, `savings_accounts`, etc. | Creates preview personas with full financial data |

**Run individually:**
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Phase 2: Optional Seeders (Development/Testing)

These seeders create additional test data. They are optional and typically only used in development/staging environments.

| Seeder | Purpose |
|--------|---------|
| `HouseholdSeeder` | Creates household records for multi-user testing |
| `TestUsersSeeder` | Creates basic test user accounts |
| `ComprehensiveDemoDataSeeder` | Creates comprehensive demo data for testing |

**Run individually:**
```bash
php artisan db:seed --class=HouseholdSeeder --force
php artisan db:seed --class=TestUsersSeeder --force
```

---

## Common Scenarios

### Scenario 1: Fresh Database Setup

```bash
# Drop all tables and re-migrate
php artisan migrate:fresh

# Seed everything
php artisan db:seed
```

### Scenario 2: Tax Status Tab Not Showing Data

**Symptom:** Tax Status tab in Investment/Savings detail views is empty or shows errors.

**Cause:** `TaxProductReferenceSeeder` hasn't been run.

**Fix:**
```bash
php artisan db:seed --class=TaxProductReferenceSeeder --force
```

**Verify:**
```bash
php artisan tinker --execute="echo App\Models\TaxProductReference::count();"
# Should output: 50
```

### Scenario 3: Tax Calculations Returning Errors

**Symptom:** API returns "No active tax configuration found" or tax calculations fail.

**Cause:** `TaxConfigurationSeeder` hasn't been run.

**Fix:**
```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

**Verify:**
```bash
php artisan tinker --execute="echo App\Models\TaxConfiguration::where('is_active', true)->count();"
# Should output: 1
```

### Scenario 4: Preview Personas Not Working

**Symptom:** Preview login fails or personas have missing/incorrect data.

**Fix:**
```bash
# Delete existing preview users and re-seed
php artisan db:seed --class=PreviewUserSeeder --force
```

**Verify:**
```bash
php artisan tinker --execute="echo App\Models\User::where('is_preview_user', true)->count();"
# Should output: 7 (4 primary users + 3 spouses)
```

### Scenario 5: Life Expectancy/Retirement Projections Failing

**Symptom:** Estate or retirement calculations fail with missing life expectancy data.

**Fix:**
```bash
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
```

### Scenario 6: Admin/Demo Login Not Working

**Symptom:** Cannot log in with admin@fps.com or demo@fps.com.

**Fix:**
```bash
php artisan db:seed --class=AdminUserSeeder --force
```

**Verify:**
```bash
php artisan tinker --execute="echo App\Models\User::where('email', 'admin@fps.com')->exists() ? 'exists' : 'missing';"
# Should output: exists
```

---

## Preview Personas

The application includes four preview personas for demonstration purposes:

| Persona ID | Primary User | Spouse | Scenario |
|------------|--------------|--------|----------|
| `young_family` | James Carter | Emily Carter | Young couple, mortgage, workplace pensions |
| `peak_earners` | David Mitchell | Sarah Mitchell | High earners, multiple properties, SIPP + NHS pension |
| `widow` | Margaret Thompson | Robert (deceased) | Estate planning focus |
| `entrepreneur` | Alex Chen | None | SIPP, business interests |

### Persona Data Files

Located in: `resources/js/data/personas/`

- `young_family.json`
- `peak_earners.json`
- `widow.json`
- `entrepreneur.json`

When updating persona data:
1. Edit the JSON file
2. Run `php artisan db:seed --class=PreviewUserSeeder --force`

### Persona Data Structure

Each persona JSON file contains:
- `user` - Primary user profile (DOB, income, address, etc.)
- `spouse` - Spouse profile (if applicable)
- `family_members` - Children, parents, etc.
- `properties` - Property records with mortgages
- `savings_accounts` - Cash savings, ISAs
- `investment_accounts` - ISAs, GIAs with holdings
- `dc_pensions` - Defined contribution pensions with holdings
- `db_pensions` - Defined benefit pensions
- `state_pension` - State pension forecast
- `life_insurance_policies` - Protection policies
- `liabilities` - Non-mortgage debts
- `risk_profile` - Main risk level and notes

---

## Database Tables Reference

### Reference Tables (Seeded)

| Table | Seeder | Records |
|-------|--------|---------|
| `tax_configurations` | TaxConfigurationSeeder | 1 (active year) |
| `tax_product_references` | TaxProductReferenceSeeder | ~50 |
| `actuarial_life_tables` | ActuarialLifeTablesSeeder | ~44 |

### User Data Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts |
| `spouse_links` | Links users to their spouse |
| `family_members` | Children, parents, etc. |
| `properties` | Property records |
| `mortgages` | Mortgage records |
| `savings_accounts` | Cash savings |
| `investment_accounts` | Investment wrappers |
| `investment_holdings` | Holdings within accounts |
| `dc_pensions` | Defined contribution pensions |
| `dc_pension_holdings` | Holdings within DC pensions |
| `db_pensions` | Defined benefit pensions |
| `state_pensions` | State pension forecasts |
| `life_insurance_policies` | Life insurance |
| `critical_illness_policies` | CI cover |
| `income_protection_policies` | IP cover |
| `liabilities` | Non-mortgage debts |
| `risk_profiles` | Investment risk preferences |

---

## Production Deployment

### Initial Deployment

```bash
# Run migrations
php artisan migrate --force

# Seed all required data
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan db:seed --class=TaxProductReferenceSeeder --force
php artisan db:seed --class=ActuarialLifeTablesSeeder --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
```

### Updating Tax Year

When updating to a new tax year:

1. Update `TaxConfigurationSeeder` with new rates
2. Set `is_active = true` for new year, `false` for old
3. Run: `php artisan db:seed --class=TaxConfigurationSeeder --force`

---

## Troubleshooting

### "Table doesn't exist" Errors

```bash
php artisan migrate
```

### "Column doesn't have a default value" Errors

This usually means a seeder is missing required fields. Check the model's `$fillable` array matches the seeder data.

### Seeder Runs But Data Not Appearing

1. Check you used `--force` flag
2. Verify the seeder actually creates records:
   ```bash
   php artisan tinker --execute="echo App\Models\TaxConfiguration::count();"
   ```
3. Clear application cache:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

### Preview User Login Fails

```bash
# Check user exists
php artisan tinker --execute="App\Models\User::where('email', 'preview_young_family@fynla.local')->first();"

# Re-seed if needed
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Seeder Checklist for Claude

When working on database changes, always verify:

- [ ] Migrations run without errors: `php artisan migrate`
- [ ] All required seeders have been run:
  - [ ] `TaxConfiguration::count() >= 1`
  - [ ] `TaxProductReference::count() >= 50`
  - [ ] Life expectancy tables populated
  - [ ] Admin user exists (admin@fps.com)
  - [ ] Preview users exist (7 users total)
- [ ] Preview personas work:
  - [ ] Login via `/api/preview/login/young_family`
  - [ ] Check all four personas have data
- [ ] Tax Status tab shows data in Investment/Savings views
- [ ] Server restarted after seeding: `pkill -f "php artisan serve" && php artisan serve`

---

## Symptoms of Missing Seeders

| Missing Seeder | Symptom |
|----------------|---------|
| TaxConfigurationSeeder | "No active tax configuration found" error, tax calculations fail |
| TaxProductReferenceSeeder | Tax Status tab empty in Investment/Savings views |
| ActuarialLifeTablesSeeder | Estate/retirement projections fail, life expectancy errors |
| AdminUserSeeder | Cannot log in with admin@fps.com or demo@fps.com |
| PreviewUserSeeder | Preview login fails, personas have no data |

---

## Version History

| Date | Change |
|------|--------|
| 2025-12-16 | Initial documentation created |
| 2025-12-16 | Added TaxProductReferenceSeeder to DatabaseSeeder |
| 2025-12-16 | Added risk profile data to preview personas |
| 2025-12-31 | Moved AdminUserSeeder and PreviewUserSeeder to required seeders |
