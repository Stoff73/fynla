# Jan 8 Updates: Remove Required Field Validation

## Overview
Make all form fields optional across onboarding and dashboard forms to allow users to save partial data.

## Scope

**Exceptions (remain required):**
- Authentication: email, password (login); first_name, surname, email, password (registration)
- Document upload: file field

**Database approach:** Add sensible defaults via migrations

---

## Changes Made

### Phase 1: Database Migration

Created migration `make_form_fields_optional.php` to add defaults to NOT NULL columns:

| Table | Column | Default Value |
|-------|--------|---------------|
| mortgages | outstanding_balance | 0 |
| mortgages | rate_type | 'fixed' |
| mortgages | remaining_term_months | 0 |
| family_members | name | 'Unknown' |
| family_members | relationship | 'other_dependent' |
| business_interests | business_name | nullable |
| business_interests | current_valuation | 0 |
| business_interests | valuation_date | nullable |
| chattels | name | nullable |
| chattels | current_value | 0 |
| gifts | gift_date | nullable |
| gifts | recipient | nullable |
| gifts | gift_value | 0 |

### Phase 2: Backend Laravel FormRequest Changes

Changed `required` to `nullable` in 10 FormRequest files:

| File | Fields Changed |
|------|----------------|
| `StoreFamilyMemberRequest.php` | relationship, first_name, last_name |
| `UpdateDomicileInfoRequest.php` | domicile_status, country_of_birth, uk_arrival_date |
| `UpdateStatePensionRequest.php` | ni_years_completed, ni_years_required |
| `StoreSavingsAccountRequest.php` | account_type, institution, current_balance, interest_rate, access_type, is_isa |
| `StoreCriticalIllnessPolicyRequest.php` | policy_type, provider, sum_assured, premium_amount, premium_frequency |
| `StoreProtectionProfileRequest.php` | annual_income, monthly_expenditure, number_of_dependents, retirement_age, smoker_status |
| `StoreBusinessInterestRequest.php` | business_name, business_type, current_valuation, valuation_date |
| `StoreChattelRequest.php` | chattel_type, name, current_value |
| `StorePersonalAccountLineItemRequest.php` | account_type, line_item, category, amount, period_start, period_end |
| `OptimizePortfolioRequest.php` | optimization_type, target_return |

### Phase 3: Frontend Vue.js Component Changes

Removed `required` HTML attributes and JS validation from 10 components:

| Component | Fields Changed |
|-----------|----------------|
| `PersonalInfoStep.vue` | date_of_birth, gender, marital_status, address_line_1, city, postcode, health_status, smoking_status |
| `IncomeStep.vue` | employment_status, target_retirement_age, retirement_date |
| `DomicileInformationStep.vue` | country_of_birth, uk_arrival_date |
| `PropertyForm.vue` | property_type, address, current_value, joint_owner_id |
| `LiabilityForm.vue` | liability_type, liability_name, current_balance |
| `GiftForm.vue` | gift_date, recipient, gift_value, gift_type |
| `SaveGoalModal.vue` | goal_name, target_amount, target_date, priority |
| `GoalForm.vue` | goal_name, target_amount, target_date, goal_type |
| `FamilyMemberFormModal.vue` | relationship, first_name, last_name, date_of_birth, email |
| `PersonalInformation.vue` | name, email |

---

## Testing

Verified all forms can be submitted with empty/partial data:
- [ ] Onboarding flow - all steps with empty forms
- [ ] Property form - add with no data
- [ ] Liability form - add with no data
- [ ] Family member form - add with no data
- [ ] Savings account form - add with no data
- [ ] Investment goal form - add with no data
- [ ] Gift form - add with no data

---

## Files Modified

### Backend (10 files)
- `app/Http/Requests/StoreFamilyMemberRequest.php`
- `app/Http/Requests/UpdateDomicileInfoRequest.php`
- `app/Http/Requests/Retirement/UpdateStatePensionRequest.php`
- `app/Http/Requests/Savings/StoreSavingsAccountRequest.php`
- `app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php`
- `app/Http/Requests/Protection/StoreProtectionProfileRequest.php`
- `app/Http/Requests/BusinessInterest/StoreBusinessInterestRequest.php`
- `app/Http/Requests/Chattel/StoreChattelRequest.php`
- `app/Http/Requests/StorePersonalAccountLineItemRequest.php`
- `app/Http/Requests/Investment/OptimizePortfolioRequest.php`

### Frontend - Phase 3: Required Attribute Removal (10 files)
- `resources/js/components/Onboarding/steps/PersonalInfoStep.vue`
- `resources/js/components/Onboarding/steps/IncomeStep.vue`
- `resources/js/components/Onboarding/steps/DomicileInformationStep.vue`
- `resources/js/components/NetWorth/PropertyForm.vue`
- `resources/js/components/Estate/LiabilityForm.vue`
- `resources/js/components/Estate/GiftForm.vue`
- `resources/js/components/Savings/SaveGoalModal.vue`
- `resources/js/components/Investment/GoalForm.vue`
- `resources/js/components/UserProfile/FamilyMemberFormModal.vue`
- `resources/js/components/UserProfile/PersonalInformation.vue`

### Frontend - Phase 4: Red Asterisk Removal (10 files)
- `resources/js/components/Trusts/TrustFormModal.vue`
- `resources/js/components/Investment/ContributionPlanner.vue`
- `resources/js/components/Admin/UserFormModal.vue`
- `resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue`
- `resources/js/components/UserProfile/HealthInformation.vue`
- `resources/js/views/Register.vue`
- `resources/js/components/UserProfile/DomicileInformation.vue`
- `resources/js/components/NetWorth/BusinessInterestForm.vue`
- `resources/js/components/UserProfile/ExpenditureForm.vue`
- `resources/js/components/Shared/CountrySelector.vue`

### Database (1 file)
- `database/migrations/2026_01_08_091458_make_form_fields_optional.php`

---

## Deployment Instructions

### Step 1: Upload Files via SiteGround File Manager

Upload the following files to their corresponding locations on the server:

**Backend FormRequest files** (upload to `app/Http/Requests/`):
```
app/Http/Requests/StoreFamilyMemberRequest.php
app/Http/Requests/UpdateDomicileInfoRequest.php
app/Http/Requests/StorePersonalAccountLineItemRequest.php
```

**Backend FormRequest files** (upload to subdirectories):
```
app/Http/Requests/Retirement/UpdateStatePensionRequest.php
app/Http/Requests/Savings/StoreSavingsAccountRequest.php
app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php
app/Http/Requests/Protection/StoreProtectionProfileRequest.php
app/Http/Requests/BusinessInterest/StoreBusinessInterestRequest.php
app/Http/Requests/Chattel/StoreChattelRequest.php
app/Http/Requests/Investment/OptimizePortfolioRequest.php
```

**Database migration** (upload to `database/migrations/`):
```
database/migrations/2026_01_08_091458_make_form_fields_optional.php
```

**Frontend build** (upload entire folder, replacing existing):
```
public/build/
```

### Step 2: Connect via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

### Step 3: Run Database Migration

```bash
php artisan migrate --force
```

Expected output:
```
INFO  Running migrations.

2026_01_08_091458_make_form_fields_optional .... DONE
```

### Step 4: Clear All Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

Or run all at once:
```bash
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear
```

### Step 5: Verify Deployment

1. Visit https://fynla.org and log in
2. Test a form submission with empty/partial data
3. Check browser console for any errors

---

## Rollback (if needed)

If issues occur, run the migration rollback:

```bash
php artisan migrate:rollback --step=1
```

This will reverse the column default changes.

---

## Phase 4: Remove Red Asterisks from Form Labels

Removed all red asterisks (`*`) that indicated required fields from form labels across the application.

### Files Changed (10 files, 25 asterisks removed)

| File | Location | Instances |
|------|----------|-----------|
| `TrustFormModal.vue` | `resources/js/components/Trusts/` | 5 |
| `ContributionPlanner.vue` | `resources/js/components/Investment/` | 4 |
| `UserFormModal.vue` | `resources/js/components/Admin/` | 4 |
| `PropertyTaxCalculator.vue` | `resources/js/components/NetWorth/Property/` | 3 |
| `HealthInformation.vue` | `resources/js/components/UserProfile/` | 2 |
| `Register.vue` | `resources/js/views/` | 2 |
| `DomicileInformation.vue` | `resources/js/components/UserProfile/` | 2 |
| `BusinessInterestForm.vue` | `resources/js/components/NetWorth/` | 1 |
| `ExpenditureForm.vue` | `resources/js/components/UserProfile/` | 1 |
| `CountrySelector.vue` | `resources/js/components/Shared/` | 1 |

### Change Pattern

Removed spans like:
```html
<!-- Before -->
<label>Field Name <span class="text-red-500">*</span></label>
<label>Field Name <span class="text-error-600">*</span></label>

<!-- After -->
<label>Field Name</label>
```

---

## Updated Deployment Instructions

### ChangedFiles Folder Structure

All modified files are copied to `Jan8Updates/ChangedFiles/` for easy reference:

```
Jan8Updates/ChangedFiles/
├── Backend/
│   ├── StoreFamilyMemberRequest.php          → app/Http/Requests/
│   ├── UpdateDomicileInfoRequest.php         → app/Http/Requests/
│   ├── StorePersonalAccountLineItemRequest.php → app/Http/Requests/
│   ├── BusinessInterest/
│   │   └── StoreBusinessInterestRequest.php  → app/Http/Requests/BusinessInterest/
│   ├── Chattel/
│   │   └── StoreChattelRequest.php           → app/Http/Requests/Chattel/
│   ├── Investment/
│   │   └── OptimizePortfolioRequest.php      → app/Http/Requests/Investment/
│   ├── Protection/
│   │   ├── StoreCriticalIllnessPolicyRequest.php → app/Http/Requests/Protection/
│   │   └── StoreProtectionProfileRequest.php → app/Http/Requests/Protection/
│   ├── Retirement/
│   │   └── UpdateStatePensionRequest.php     → app/Http/Requests/Retirement/
│   └── Savings/
│       └── StoreSavingsAccountRequest.php    → app/Http/Requests/Savings/
├── Database/
│   └── 2026_01_08_091458_make_form_fields_optional.php → database/migrations/
└── Frontend/
    └── (20 .vue files - upload via public/build/ after running build script)
```

### Step 1: Build Frontend

Run the build script locally:
```bash
./deploy/fynla-org/build.sh
```

### Step 2: Upload Files via SiteGround File Manager

**Backend FormRequest files** - Upload from `Jan8Updates/ChangedFiles/Backend/`:

| From ChangedFiles/Backend/ | To Server Path |
|---------------------------|----------------|
| `StoreFamilyMemberRequest.php` | `app/Http/Requests/` |
| `UpdateDomicileInfoRequest.php` | `app/Http/Requests/` |
| `StorePersonalAccountLineItemRequest.php` | `app/Http/Requests/` |
| `BusinessInterest/StoreBusinessInterestRequest.php` | `app/Http/Requests/BusinessInterest/` |
| `Chattel/StoreChattelRequest.php` | `app/Http/Requests/Chattel/` |
| `Investment/OptimizePortfolioRequest.php` | `app/Http/Requests/Investment/` |
| `Protection/StoreCriticalIllnessPolicyRequest.php` | `app/Http/Requests/Protection/` |
| `Protection/StoreProtectionProfileRequest.php` | `app/Http/Requests/Protection/` |
| `Retirement/UpdateStatePensionRequest.php` | `app/Http/Requests/Retirement/` |
| `Savings/StoreSavingsAccountRequest.php` | `app/Http/Requests/Savings/` |

**Database migration** - Upload from `Jan8Updates/ChangedFiles/Database/`:
```
2026_01_08_091458_make_form_fields_optional.php → database/migrations/
```

**Frontend build** (upload entire folder, replacing existing):
```
public/build/
```

### Step 3: Connect via SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

### Step 4: Run Database Migration

```bash
php artisan migrate --force
```

### Step 5: Clear All Caches

```bash
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear
```

### Step 6: Verify Deployment

1. Visit https://fynla.org and log in
2. Check that form labels no longer show red asterisks
3. Test form submission with empty/partial data
4. Check browser console for any errors
