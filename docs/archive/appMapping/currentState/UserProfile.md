# User Profile Module - Feature Map

> Last updated: 2026-02-19
> Module scope: Personal information, family members, income/occupation, expenditure, domicile, personal accounts (P&L/Cashflow/Balance Sheet), Letter to Spouse, occupation search, postcode lookup, planning assumptions, profile completeness, and module data requirements.

---

## 1. System Overview

The User Profile module is the foundational data-entry layer of Fynla. It collects and manages every piece of personal, financial, and family information that downstream modules (Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, Coordination) depend on for their calculations. Unlike other modules, the User Profile has **no dedicated Agent** -- controllers call services directly.

### Key Responsibilities

| Area | Description |
|------|-------------|
| Personal Information | Name, DOB, gender, marital status, NI number, address, phone, health, education |
| Family Members | Spouse linking (bidirectional User.spouse_id), children, dependants, shared records |
| Income & Occupation | Employment income, self-employment, rental, dividends, interest, trust, pension income |
| Expenditure | Dual-mode entry (simple vs category), financial commitments aggregation, spouse expenditure |
| Domicile | UK/non-UK domicile status, deemed domicile calculation (15 years), country of birth |
| Personal Accounts | Manual ledger: Profit & Loss, Cashflow, Balance Sheet statements |
| Letter to Spouse | 4-part letter with 28+ fields, auto-population from financial data, PDF-ready |
| Occupation Search | ONS SOC 2020 code lookup with autocomplete |
| Postcode Lookup | GetAddress.io proxy with 1-hour cache and UK format validation |
| Assumptions | User overrides for inflation, return rates, compound periods (pensions/investments/estate) |
| Profile Completeness | 7 checks (married) / 6 checks (single) with recommendations |
| Module Data Requirements | Per-module field and relationship requirement checks for 13 modules |

### Data Flow

```
Vue Component
  --> userProfileService.js (API wrapper)
    --> API Route (/api/user/...)
      --> Controller (UserProfileController, FamilyMembersController, etc.)
        --> Service (UserProfileService, PersonalAccountsService, etc.)
          --> Model (User, FamilyMember, PersonalAccount, etc.)
            --> MySQL
```

No Agent orchestrator sits between controllers and services in this module. Controllers invoke services directly.

### Line Counts (approximate)

| File | Lines |
|------|-------|
| UserProfileService.php | 1,091 |
| ModuleDataRequirementsService.php | 610 |
| FamilyMembersController.php | 605 |
| userProfile.js (Vuex store) | 614 |
| AssumptionsService.php | 426 |
| UserProfileController.php | 412 |
| PersonalAccountsService.php | 403 |
| LetterToSpouseService.php | 379 |
| ProfileCompletenessChecker.php | 281 |

---

## 2. Database Schema

### 2.1 `users` table (profile-relevant columns)

The `users` table stores the majority of profile data directly. Only summary tables (ExpenditureProfile, LetterToSpouse, etc.) have their own tables.

| Column | Type | Purpose |
|--------|------|---------|
| `first_name` | varchar(255) | First name |
| `surname` | varchar(255) | Surname |
| `name` | varchar(255) | Full display name |
| `email` | varchar(255) | Email (unique) |
| `date_of_birth` | date | DOB (18-105 age range) |
| `gender` | enum | male, female, other, prefer_not_to_say |
| `marital_status` | enum | single, married, divorced, widowed |
| `national_insurance_number` | varchar | Format: AB123456C |
| `phone` | varchar | UK format: +44 or 0 prefix |
| `address_line_1` | varchar(255) | Address line 1 |
| `address_line_2` | varchar(255) | Address line 2 |
| `city` | varchar(255) | City |
| `county` | varchar(255) | County |
| `postcode` | varchar(10) | UK postcode |
| `good_health` | boolean | Health status |
| `smoker` | boolean | Smoker status |
| `education_level` | enum | secondary, a_level, undergraduate, postgraduate, professional, other |
| `charitable_bequest` | boolean | Wishes to leave anything to charity |
| `spouse_id` | FK (users.id) | Bidirectional spouse link |
| `household_id` | FK (households.id) | Household grouping |
| `is_primary_account` | boolean | Primary account holder |
| `is_preview_user` | boolean | Preview persona flag |
| `occupation` | varchar(255) | Job title |
| `employer` | varchar(255) | Employer name |
| `industry` | varchar(255) | Industry |
| `employment_status` | enum | employed, part_time, self_employed, unemployed, retired, student, other |
| `target_retirement_age` | integer | Target retirement age (30-100) |
| `retirement_date` | date | Planned retirement date |
| `payday_day_of_month` | integer | Payday of the month |
| `annual_employment_income` | decimal(12,2) | Employment income |
| `annual_self_employment_income` | decimal(12,2) | Self-employment income |
| `annual_rental_income` | decimal(12,2) | Rental income (calculated from PropertyService) |
| `annual_dividend_income` | decimal(12,2) | Dividend income |
| `annual_interest_income` | decimal(12,2) | Interest income |
| `annual_trust_income` | decimal(12,2) | Trust income |
| `annual_other_income` | decimal(12,2) | Other income |
| `income_needs_update` | boolean | Flag when employment status changes |
| `previous_employment_status` | varchar(50) | Previous status before change |
| `monthly_expenditure` | decimal(12,2) | Simple mode expenditure |
| `annual_expenditure` | decimal(12,2) | Annual expenditure |
| `expenditure_entry_mode` | enum | simple, category |
| `expenditure_sharing_mode` | enum | joint, separate |
| `food_groceries` | decimal(12,2) | Category: food & groceries |
| `transport_fuel` | decimal(12,2) | Category: transport & fuel |
| `healthcare_medical` | decimal(12,2) | Category: healthcare |
| `insurance` | decimal(12,2) | Category: insurance |
| `mobile_phones` | decimal(12,2) | Category: mobile phones |
| `internet_tv` | decimal(12,2) | Category: internet & TV |
| `subscriptions` | decimal(12,2) | Category: subscriptions |
| `clothing_personal_care` | decimal(12,2) | Category: clothing |
| `entertainment_dining` | decimal(12,2) | Category: entertainment |
| `holidays_travel` | decimal(12,2) | Category: holidays |
| `pets` | decimal(12,2) | Category: pets |
| `childcare` | decimal(12,2) | Category: childcare |
| `school_fees` | decimal(12,2) | Category: school fees |
| `school_lunches` | decimal(12,2) | Category: school lunches |
| `school_extras` | decimal(12,2) | Category: school extras |
| `university_fees` | decimal(12,2) | Category: university fees |
| `children_activities` | decimal(12,2) | Category: children activities |
| `gifts_charity` | decimal(12,2) | Category: gifts & charity |
| `regular_savings` | decimal(12,2) | Category: regular savings |
| `other_expenditure` | decimal(12,2) | Category: other |
| `domicile_status` | enum | uk_domiciled, non_uk_domiciled |
| `country_of_birth` | varchar(255) | Country of birth |
| `uk_arrival_date` | date | Date arrived in UK |
| `years_uk_resident` | integer | Calculated years in UK |
| `deemed_domicile_date` | date | Date deemed domiciled (15 years after arrival) |
| `dashboard_widget_order` | JSON | Custom dashboard widget ordering |

### 2.2 `family_members` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `user_id` | FK (users.id) | Owning user |
| `household_id` | FK (households.id) | Household grouping |
| `relationship` | enum | spouse, child, step_child, parent, other_dependent |
| `name` | varchar(255) | Legacy full name field |
| `first_name` | varchar(255) | First name |
| `middle_name` | varchar(255) | Middle name (optional) |
| `last_name` | varchar(255) | Last name |
| `date_of_birth` | date | Date of birth |
| `gender` | enum | male, female, other, prefer_not_to_say |
| `national_insurance_number` | varchar | NI number (format: AB123456C) |
| `annual_income` | decimal(12,2) | Annual income |
| `is_dependent` | boolean | Financial dependant flag |
| `education_status` | enum | pre_school, primary, secondary, further_education, higher_education, graduated, not_applicable |
| `receives_child_benefit` | boolean | Whether child benefit is claimed for this child |
| `notes` | text | Free-text notes |
| `created_at` | timestamp | Auditable |
| `updated_at` | timestamp | Auditable |

### 2.3 `households` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `household_name` | varchar(255) | Household display name |
| `notes` | text | Notes |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2.4 `personal_accounts` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `user_id` | FK (users.id) | Owning user |
| `account_type` | enum | profit_and_loss, cashflow, balance_sheet |
| `period_start` | date | Period start (defaults to tax year start: Apr 6) |
| `period_end` | date | Period end (defaults to tax year end: Apr 5) |
| `line_item` | varchar(255) | Description of the entry |
| `category` | enum | income, expense, asset, liability, equity, cash_inflow, cash_outflow |
| `amount` | decimal(12,2) | Amount (can be negative) |
| `notes` | text | Notes |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2.5 `expenditure_profiles` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `user_id` | FK (users.id) | Owning user |
| `monthly_housing` | float | Housing costs |
| `monthly_utilities` | float | Utilities |
| `monthly_food` | float | Food |
| `monthly_transport` | float | Transport |
| `monthly_insurance` | float | Insurance |
| `monthly_loans` | float | Loan payments |
| `monthly_discretionary` | float | Discretionary spending |
| `total_monthly_expenditure` | float | Total monthly spend |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2.6 `letters_to_spouse` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `user_id` | FK (users.id) | Owning user |
| **Part 1: Immediate Actions** | | |
| `immediate_actions` | text | Step-by-step immediate actions |
| `executor_name` | varchar(255) | Executor name |
| `executor_contact` | varchar(255) | Executor contact details |
| `attorney_name` | varchar(255) | Attorney name |
| `attorney_contact` | varchar(255) | Attorney contact |
| `financial_advisor_name` | varchar(255) | Financial advisor name |
| `financial_advisor_contact` | varchar(255) | Financial advisor contact |
| `accountant_name` | varchar(255) | Accountant name |
| `accountant_contact` | varchar(255) | Accountant contact |
| `immediate_funds_access` | text | How to access funds immediately |
| `employer_hr_contact` | varchar(255) | Employer HR contact |
| `employer_benefits_info` | text | Employer benefits information |
| **Part 2: Accounts & Assets** | | |
| `password_manager_info` | text | Password manager details |
| `phone_plan_info` | text | Phone plan information |
| `bank_accounts_info` | text | Bank accounts (auto-populated) |
| `investment_accounts_info` | text | Investment accounts (auto-populated) |
| `insurance_policies_info` | text | Insurance policies (auto-populated) |
| `real_estate_info` | text | Real estate (auto-populated) |
| `vehicles_info` | text | Vehicles information |
| `valuable_items_info` | text | Valuable items |
| `cryptocurrency_info` | text | Cryptocurrency information |
| `liabilities_info` | text | Liabilities (auto-populated) |
| `recurring_bills_info` | text | Recurring bills |
| **Part 3: Long-term Plans** | | |
| `estate_documents_location` | text | Where estate documents are stored |
| `beneficiary_info` | text | Beneficiary details (auto-populated) |
| `children_education_plans` | text | Education plans (auto-populated) |
| `financial_guidance` | text | Financial guidance notes (auto-populated) |
| `social_security_info` | text | State Pension / social security |
| **Part 4: Funeral & Final Wishes** | | |
| `funeral_preference` | enum | burial, cremation, not_specified |
| `funeral_service_details` | text | Funeral service details |
| `obituary_wishes` | text | Obituary preferences |
| `additional_wishes` | text | Additional final wishes |
| `additional_boxes` | JSON | Up to 10 custom boxes [{title, content}] |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2.7 `occupation_codes` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `soc_code` | varchar | ONS SOC 2020 4-digit unit group code |
| `title` | varchar(255) | Job title or occupation name |
| `unit_group` | varchar(255) | SOC 2020 unit group description |
| `minor_group` | varchar(255) | SOC 2020 minor group (3-digit) |
| `sub_major_group` | varchar(255) | SOC 2020 sub-major group (2-digit) |
| `major_group` | varchar(255) | SOC 2020 major group (1-digit) |
| `is_primary` | boolean | Is this the primary title for the SOC code |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2.8 `user_assumptions` table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Auto-increment |
| `user_id` | FK (users.id) | Owning user |
| `assumption_type` | enum | pensions, investments, estate_planning |
| `inflation_rate` | decimal(5,2) | Inflation rate override |
| `return_rate` | decimal(5,2) | Return rate override |
| `compound_periods` | integer | Compounding frequency override |
| `property_growth_rate` | decimal(5,2) | Estate planning: property growth rate |
| `investment_growth_method` | enum | monte_carlo, custom |
| `custom_investment_rate` | decimal(5,2) | Custom investment growth rate |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## 3. Models

### 3.1 FamilyMember

**File:** `app/Models/FamilyMember.php`

| Aspect | Detail |
|--------|--------|
| Traits | `Auditable`, `HasFactory` |
| Fillable | user_id, household_id, relationship, name, first_name, middle_name, last_name, date_of_birth, gender, national_insurance_number, annual_income, is_dependent, education_status, receives_child_benefit, notes |
| Casts | date_of_birth (date), annual_income (decimal:2), is_dependent (boolean), receives_child_benefit (boolean) |
| Relationships | `user()` BelongsTo User, `household()` BelongsTo Household |
| Accessors | `getFullNameAttribute()` -- concatenates first_name, middle_name, last_name |
| Relationship Enum | spouse, child, step_child, parent, other_dependent |

### 3.2 Household

**File:** `app/Models/Household.php`

| Aspect | Detail |
|--------|--------|
| Traits | `HasFactory` |
| Fillable | household_name, notes |
| Relationships | `users()` HasMany User, `familyMembers()` HasMany FamilyMember, `properties()` HasMany Property, `businessInterests()` HasMany BusinessInterest, `chattels()` HasMany Chattel, `cashAccounts()` HasMany CashAccount, `investmentAccounts()` HasMany InvestmentAccount, `trusts()` HasMany Trust |

The Household model is a grouping entity that ties linked spouses to their shared asset collections. When a spouse is created or linked, both users are placed in the same household.

### 3.3 PersonalAccount

**File:** `app/Models/PersonalAccount.php`

| Aspect | Detail |
|--------|--------|
| Traits | `HasFactory` |
| Fillable | user_id, account_type, period_start, period_end, line_item, category, amount, notes |
| Casts | period_start (date), period_end (date), amount (decimal:2) |
| Relationships | `user()` BelongsTo User |
| Account Types | profit_and_loss, cashflow, balance_sheet |
| Categories | income, expense, asset, liability, equity, cash_inflow, cash_outflow |

PersonalAccount is a manual ledger where users can add supplementary line items that are not captured by other modules. The three statement types (P&L, Cashflow, Balance Sheet) combine automated data from the user's financial profile with these manual entries.

### 3.4 ExpenditureProfile

**File:** `app/Models/ExpenditureProfile.php`

| Aspect | Detail |
|--------|--------|
| Traits | `HasFactory` |
| Fillable | user_id, monthly_housing, monthly_utilities, monthly_food, monthly_transport, monthly_insurance, monthly_loans, monthly_discretionary, total_monthly_expenditure |
| Casts | All float |
| Relationships | `user()` BelongsTo User |

ExpenditureProfile provides high-level category summaries. The detailed 20-category breakdown is stored directly on the `users` table. This model is primarily used when `updateExpenditure` writes a `total_monthly_expenditure` summary.

### 3.5 LetterToSpouse

**File:** `app/Models/LetterToSpouse.php`

| Aspect | Detail |
|--------|--------|
| Table | `letters_to_spouse` |
| Traits | `HasFactory` |
| Fillable | 28 text/varchar fields across 4 parts, plus `additional_boxes` (JSON) |
| Casts | created_at (datetime), updated_at (datetime), additional_boxes (array) |
| Relationships | `user()` BelongsTo User |
| Parts | Part 1: Immediate Actions (12 fields), Part 2: Accounts & Assets (11 fields), Part 3: Long-term Plans (5 fields), Part 4: Funeral & Final Wishes (4 fields + additional_boxes) |

The letter is auto-created with defaults from the user's financial data on first view. The `additional_boxes` JSON field supports up to 10 custom user-defined sections with `{title, content}` objects.

### 3.6 OccupationCode

**File:** `app/Models/OccupationCode.php`

| Aspect | Detail |
|--------|--------|
| Traits | `HasFactory` |
| Fillable | soc_code, title, unit_group, minor_group, sub_major_group, major_group, is_primary |
| Casts | is_primary (boolean) |
| Static Methods | `search(string $query, int $limit = 10)` |

The `search()` method requires a minimum of 3 characters. It uses LIKE queries with a priority ordering that favours starts-with matches over mid-word matches:
1. `title LIKE 'query%'` (priority 0)
2. `title LIKE '% query%'` (priority 1)
3. All other matches (priority 2)

Results are limited and return: id, title, soc_code, unit_group.

### 3.7 UserAssumption

**File:** `app/Models/UserAssumption.php`

| Aspect | Detail |
|--------|--------|
| Traits | `HasFactory` |
| Fillable | user_id, assumption_type, inflation_rate, return_rate, compound_periods |
| Casts | inflation_rate (decimal:2), return_rate (decimal:2), compound_periods (integer) |
| Relationships | `user()` BelongsTo User |
| Types | pensions, investments, estate_planning |

Estate planning assumptions additionally support: `property_growth_rate`, `investment_growth_method` (monte_carlo or custom), and `custom_investment_rate`.

---

## 4. Controllers

### 4.1 UserProfileController

**File:** `app/Http/Controllers/Api/UserProfileController.php` (412 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `getProfile` | GET `/api/user/profile` | Returns complete profile via `UserProfileService::getCompleteProfile()`. Loads 13 relationships (household, spouse, familyMembers, properties, mortgages, liabilities, businessInterests, chattels, cashAccounts, investmentAccounts.holdings, dcPensions, dbPensions, statePension). Returns personal_info, household, spouse, income_occupation, expenditure, family_members, domicile_info, assets_summary, liabilities_summary, net_worth. |
| `updatePersonalInfo` | PUT `/api/user/profile/personal` | Uses `UpdatePersonalInfoRequest`. Updates user fields. Returns updated user. |
| `updateIncomeOccupation` | PUT `/api/user/profile/income-occupation` | Uses `UpdateIncomeOccupationRequest`. Recalculates rental income from PropertyService. Clears protection cache for user and spouse. |
| `updateExpenditure` | PUT `/api/user/profile/expenditure` | Inline validation for 22 expenditure fields. Maps `use_simple_entry` to `expenditure_entry_mode` (simple/category) and `use_separate_expenditure` to `expenditure_sharing_mode` (joint/separate). Creates/updates ExpenditureProfile. |
| `updateDomicileInfo` | PUT `/api/user/profile/domicile` | Uses `UpdateDomicileInfoRequest`. Calculates deemed domicile status. Clears estate cache for user and spouse. Returns domicile_info. |
| `getUserById` | GET `/api/users/{userId}` | Spouse-only access gate (`spouse_id !== userId` returns 403). Returns spouse user data. |
| `getFinancialCommitments` | GET `/api/user/financial-commitments` | Optional query param: `ownership_filter` (all, joint_only, individual_only). Returns commitments across 6 categories with monthly totals. |
| `getSpouseFinancialCommitments` | GET `/api/user/spouse/financial-commitments` | Calls `getFinancialCommitments` on spouse user. Returns 404 if no spouse. |
| `updateDashboardWidgetOrder` | PUT `/api/user/dashboard-widget-order` | Validates array of widget IDs (net_worth, affordability, retirement, investment, tax, estate, protection, trusts, admin_taxes). Stores as JSON. |
| `updateSpouseExpenditure` | PUT `/api/users/{userId}/expenditure` | Spouse-only access gate. Same validation as `updateExpenditure`. Updates spouse's expenditure data. |

**Cache invalidation patterns:**
- Income update: clears `protection` + `user_{id}` tags for user and spouse
- Domicile update: clears `estate` + `user_{id}` tags, plus `estate_analysis_{id}` and `profile_completeness_{id}` for both user and spouse

### 4.2 FamilyMembersController

**File:** `app/Http/Controllers/Api/FamilyMembersController.php` (605 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET `/api/user/family-members` | Returns user's own members + spouse's children (de-duplicated). Marks each record with `is_shared` (boolean) and `owner` (self/spouse). Adds `email` for spouse-type members. |
| `store` | POST `/api/user/family-members` | Uses `StoreFamilyMemberRequest`. If relationship is `spouse` with email, delegates to `handleSpouseCreation`. For children with linked spouse, checks for duplicates across both users. Constructs `name` from first/middle/last parts. |
| `show` | GET `/api/user/family-members/{id}` | Returns single member. Adds spouse email if applicable. |
| `update` | PUT `/api/user/family-members/{id}` | Uses `UpdateFamilyMemberRequest`. Syncs spouse User record fields: name, date_of_birth, gender, annual_income (mapped to annual_employment_income), national_insurance_number. Clears protection cache. |
| `destroy` | DELETE `/api/user/family-members/{id}` | If deleting a spouse: deletes reciprocal FamilyMember on spouse's account, clears bidirectional spouse_id on both users. |

**handleSpouseCreation (private method):** Handles two scenarios:

1. **Existing user account**: Links both users bidirectionally (`spouse_id`), sets both to `marital_status = 'married'`, creates bidirectional SpousePermission records (status: `accepted`), creates reciprocal FamilyMember records, copies address if spouse has none, sends `SpouseAccountLinked` email.

2. **No existing account**: Creates new User with temporary random password, `must_change_password = true`, `is_primary_account = false`, copies address from current user, creates reciprocal FamilyMember records, sends `SpouseAccountCreated` email with temporary password.

**Duplicate child detection:** When a user with a linked spouse adds a child, the controller checks for existing children with matching `first_name`, `last_name`, and `date_of_birth` across both the user's and spouse's records.

### 4.3 PersonalAccountsController

**File:** `app/Http/Controllers/Api/PersonalAccountsController.php` (189 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET `/api/user/personal-accounts` | Returns all PersonalAccount records grouped by `account_type`, ordered by `period_start` descending. |
| `calculate` | POST `/api/user/personal-accounts/calculate` | Calculates all three statements (P&L, Cashflow, Balance Sheet). Accepts optional `start_date`, `end_date` (default: current calendar year), and `as_of_date` (default: now). If user has linked spouse with accepted permission, also calculates spouse's statements. |
| `storeLineItem` | POST `/api/user/personal-accounts/line-item` | Uses `StorePersonalAccountLineItemRequest`. Creates manual entry with auto-defaulted tax year dates. |
| `updateLineItem` | PUT `/api/user/personal-accounts/line-item/{id}` | Uses `UpdatePersonalAccountLineItemRequest`. Scoped to user's records. |
| `deleteLineItem` | DELETE `/api/user/personal-accounts/line-item/{id}` | Scoped to user's records. |

### 4.4 LetterToSpouseController

**File:** `app/Http/Controllers/Api/LetterToSpouseController.php` (180 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `exists` | GET `/api/user/letter-to-spouse/exists` | Checks if the letter has user-entered content (not just auto-generated). Inspects 15 user-editable fields (executor_name, executor_contact, attorney_name, attorney_contact, financial_advisor_name, financial_advisor_contact, accountant_name, accountant_contact, password_manager_info, estate_documents_location, vehicles_info, cryptocurrency_info, funeral_service_details, obituary_wishes, additional_wishes). Also checks `funeral_preference !== 'not_specified'`. Returns `{has_content: bool}`. |
| `show` | GET `/api/user/letter-to-spouse` | Gets or creates letter via `LetterToSpouseService::getOrCreateLetter()`. Auto-populates defaults on first view. |
| `showSpouse` | GET `/api/user/letter-to-spouse/spouse` | Read-only view of spouse's letter. Returns 404 if no spouse. Includes `spouse_name` and `read_only: true`. |
| `update` | PUT `/api/user/letter-to-spouse` | Validates all 28+ fields. String fields max 10,000 chars. `funeral_preference` enum: burial, cremation, not_specified. `additional_boxes` array max 10 items, each with required title (max 255) and content (max 10,000). |

### 4.5 OccupationController

**File:** `app/Http/Controllers/Api/OccupationController.php` (44 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `search` | GET `/api/occupations/search?q={query}` | Requires minimum 3 characters. Returns max 10 results with id, title, soc_code, unit_group. Uses `OccupationCode::search()` static method. |

### 4.6 PostcodeLookupController

**File:** `app/Http/Controllers/Api/PostcodeLookupController.php` (258 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `lookup` | GET `/api/postcode-lookup/{postcode}` | Validates UK postcode format (regex: `/^([A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2})$/i`). Normalises to uppercase with proper spacing. Checks cache (1-hour TTL, key: `postcode_lookup_{POSTCODE}`). Calls GetAddress.io Autocomplete API. Parses address strings into structured format: line_1, line_2, city, county, postcode, display. Handles 404 (not found), 401 (auth error), 429 (rate limit) responses. |

The route is throttled at 30 requests per minute (`throttle:30,1`).

### 4.7 AssumptionsController

**File:** `app/Http/Controllers/Api/Settings/AssumptionsController.php` (122 lines)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET `/api/settings/assumptions` | Returns all assumptions for 3 types: pensions, investments, estate_planning. Each includes current value, default, risk_level, fees, years_to_retirement, total_value. |
| `update` | PUT `/api/settings/assumptions/{type}` | Type must be: pensions, investments, or estate_planning. Estate planning uses different validation (property_growth_rate, investment_growth_method, custom_investment_rate). Supports `reset: true` to restore defaults. |

---

## 5. Agent

The User Profile module has **no dedicated Agent**. Unlike other modules (Protection, Savings, Investment, etc.) that use an Agent as an orchestrator between controllers and services, the User Profile controllers call services directly. This is because the User Profile module is a CRUD-oriented data layer rather than an analysis-driven module.

---

## 6. Services

### 6.1 UserProfileService

**File:** `app/Services/UserProfile/UserProfileService.php` (1,091 lines)

**Dependencies:** CrossModuleAssetAggregator, UKTaxCalculator, ChildBenefitService

| Method | Access | Description |
|--------|--------|-------------|
| `getCompleteProfile(User)` | public | Master profile assembly. Loads 13 relationships, calculates assets/liabilities summary, builds income_occupation with tax breakdown, returns net_worth. |
| `updatePersonalInfo(User, array)` | public | Direct update + fresh. |
| `updateIncomeOccupation(User, array)` | public | Recalculates rental income from PropertyService before update. Always overrides `annual_rental_income` with calculated total. |
| `updateDomicileInfo(User, array)` | public | Calculates `years_uk_resident`, sets `deemed_domicile_date` (15 years after arrival for non-UK domiciled). Clears deemed_domicile_date if status changes. |
| `getFinancialCommitments(User, string)` | public | Aggregates monthly commitments across 6 categories (see Deep Dive section 18). |
| `buildIncomeOccupation(User)` | private | Comprehensive tax calculation using `UKTaxCalculator::calculateDetailedNetIncome()`. Includes: rental breakdown, pension income, pension contributions, Child Benefit + HICBC, expenditure breakdown, disposable income. |
| `getExpenditureBreakdown(User)` | private | Dual-mode: sums 20 category fields when `expenditure_entry_mode === 'category'`, otherwise uses `monthly_expenditure`. Adds financial commitments total. Returns monthly_manual, monthly_commitments, monthly, annual. |
| `calculateAssetsSummary(User)` | private | Uses `CrossModuleAssetAggregator::getAssetBreakdown()` for cash, investments, properties. Manually calculates business interests and chattels (with ownership percentage). Includes pension fund values. |
| `calculateLiabilitiesSummary(User)` | private | Combines mortgages from both Mortgage table and Estate\Liability table (type='mortgage'). Categorises other liabilities separately. |
| `getFamilyMembersWithSharing(User)` | private | Deduplicates children shared between linked spouses. Creates virtual spouse FamilyMember from User record if no FamilyMember record exists. |
| `calculateAnnualRentalIncome(User)` | private | Uses `PropertyService::calculateTaxPosition()` as single source of truth. Includes properties where user is primary owner OR joint owner. |
| `calculateAnnualPensionIncome(User)` | private | Sums DB pensions (accrued_annual_pension) and State Pension (if already_receiving). |
| `calculateAnnualPensionContributions(User)` | private | Sums employee contributions from workplace/occupational/auto_enrolment DC pensions only (not SIPPs). |
| `shouldIncludeByOwnership(bool, string)` | private | Helper for ownership filter: all, joint_only, individual_only. |

**Key business rule:** Rental income is ALWAYS recalculated from PropertyService. The `annual_rental_income` field in the request payload is ignored and overwritten with the calculated value. This ensures rental income stays consistent with property data.

### 6.2 PersonalAccountsService

**File:** `app/Services/UserProfile/PersonalAccountsService.php` (403 lines)

**Traits:** `CalculatesOwnershipShare`

| Method | Description |
|--------|-------------|
| `calculateProfitAndLoss(User, Carbon, Carbon)` | Income (5 sources) minus Expenses (mortgage payments, property expenses, living expenses). Returns period, income items, expense items, net_profit_loss. |
| `calculateCashflow(User, Carbon, Carbon)` | Cash inflows (5 sources) minus outflows (mortgages, property expenses, living expenses, pension contributions). Returns net_cashflow. |
| `calculateBalanceSheet(User, Carbon)` | Assets (cash accounts, investments, properties, business interests, chattels, DC pensions) minus liabilities (mortgages, other liabilities). All joint assets use `CalculatesOwnershipShare` trait to determine user's share. Returns total_assets, total_liabilities, equity, net_worth. |

The `CalculatesOwnershipShare` trait provides `calculateUserShare()` and `calculateUserMortgageShare()` methods that determine the user's ownership portion of joint assets based on `ownership_percentage` (or default 50%).

### 6.3 LetterToSpouseService

**File:** `app/Services/UserProfile/LetterToSpouseService.php` (379 lines)

| Method | Access | Description |
|--------|--------|-------------|
| `getOrCreateLetter(User)` | public | Returns existing letter or creates one with auto-populated defaults. |
| `updateLetter(User, array)` | public | Gets or creates letter, then updates with provided data. |
| `createWithDefaults(User)` | private | Calls `generateDefaultData()` to build initial content. |
| `generateImmediateActions(User)` | private | Generates 8-step checklist: (1) Contact executor, (2) Notify employer HR, (3) Access joint bank accounts, (4) Contact financial advisor, (5) Contact life insurance companies [if has protection], (6) Keep mobile active, (7) Register death, (8) Obtain 10+ death certificates. |
| `generateImmediateFundsInfo(User)` | private | Lists joint savings accounts with balances. Notes that individual accounts may be frozen until probate. |
| `generateBankAccountsInfo(User)` | private | Lists all savings accounts with institution, type, ownership, balance. Placeholder for sort code/account number. |
| `generateInvestmentAccountsInfo(User)` | private | Lists investment accounts with provider, type, ownership, value. |
| `generateInsurancePoliciesInfo(User)` | private | Lists life insurance, critical illness, and income protection policies with provider, policy number, sum assured. |
| `generateRealEstateInfo(User)` | private | Lists properties with address, type, ownership, value, outstanding mortgage. Placeholder for title deeds location. |
| `generateLiabilitiesInfo(User)` | private | Lists mortgages and other liabilities with outstanding balance and monthly payment. |
| `generateBeneficiaryInfo(User)` | private | Lists dependent family members with relationship and age. |
| `generateEducationPlansInfo(User)` | private | Lists children with age and placeholder for education plans. |
| `generateFinancialGuidanceInfo(User)` | private | Includes household income figure and generic guidance topics (State Pension, survivor benefits, withdrawal strategies, rebalancing, IHT planning). Advises waiting 6 months before major decisions. |

### 6.4 ProfileCompletenessChecker

**File:** `app/Services/UserProfile/ProfileCompletenessChecker.php` (281 lines)

| Method | Access | Description |
|--------|--------|-------------|
| `checkCompleteness(User)` | public | Returns completeness_score (0-100), is_complete, missing_fields, all_checks, recommendations, is_married. |

**Married user checks (7):**

| Check | Condition |
|-------|-----------|
| `spouse_linked` | `spouse_id` is not null |
| `dependants` | Has spouse OR has dependent children (checks both user and spouse's children) |
| `domicile_info` | `domicile_status` and `country_of_birth` are not null |
| `income` | Any income field > 0 |
| `expenditure` | `monthly_expenditure` or `annual_expenditure` > 0 |
| `assets` | Has at least one of: Property, SavingsAccount, InvestmentAccount, DCPension, BusinessInterest, Chattel, CashAccount, EstateAsset |
| `protection_plans` | Has ProtectionProfile AND (has_no_policies OR has at least one policy) |

**Single user checks (6):** Same as above minus `spouse_linked`.

Each check includes: required, filled (boolean), message, priority (high/medium), link (route path).

### 6.5 ModuleDataRequirementsService

**File:** `app/Services/UserProfile/ModuleDataRequirementsService.php` (610 lines)

Provides per-module data requirements for 13 modules: dashboard, protection, savings, investment, retirement, estate, net_worth, trusts, properties, liabilities, business_interests, chattels, profile.

| Method | Access | Description |
|--------|--------|-------------|
| `getRequirementsForModule(User, string)` | public | Returns all_requirements, filled, missing, completion_percentage for a specific module. |
| `getModuleFromRoute(string)` | public | Maps route path to module name via ROUTE_MODULE_MAP. |
| `isFieldFilled(User, string)` | private | Special cases: `income_needs_update` (returns false if flag is true), `annual_employment_income` (also checks pension income sources for retired users), `domicile_status` (rejects null, empty, 'not_set'), numeric fields allow 0 as valid. |
| `isRelationshipFilled(User, string)` | private | 14 relationship checks. Properties is "filled" if user has properties OR pays rent. DC pensions is "filled" if user has DC, DB, or is retired. Spouse filled for non-married users. |
| `isSpouseRequirementFilled(User)` | private | Returns true for single/divorced/widowed. Married users need `spouse_id` set. |

Each requirement includes: key, type (field/relationship), label, why (plain-English explanation), link (route path), status (filled/missing).

### 6.6 AssumptionsService

**File:** `app/Services/Settings/AssumptionsService.php` (426 lines)

**Dependencies:** RiskPreferenceService

**Constants:**
- `DEFAULT_INFLATION_RATE`: 2.0%
- `DEFAULT_COMPOUND_PERIODS`: 12 (monthly)
- `DEFAULT_RETIREMENT_AGE`: 68
- `DEFAULT_PROPERTY_GROWTH_RATE`: 3.0%
- `DEFAULT_INVESTMENT_GROWTH_METHOD`: monte_carlo

| Method | Access | Description |
|--------|--------|-------------|
| `getAssumptions(int userId)` | public | Returns pensions, investments, estate_planning assumptions. |
| `getTypeAssumptions(User, string)` | public | Returns current/default values, risk_level, fees, years_to_retirement, total_value, has_overrides flag. |
| `getEstateAssumptions(User)` | public | Estate-specific: inflation, property growth, investment_growth_method, custom rate. |
| `updateAssumptions(int, string, array)` | public | Updates or deletes override. If all values null, deletes the UserAssumption record. |
| `resetAssumptions(int, string)` | public | Deletes UserAssumption record, returns defaults. |
| `getDefaults(User, string)` | public | Gets return rate from `RiskPreferenceService::getReturnParameters()` based on user's main risk level. Falls back to 5.0% if lookup fails. |
| `calculateWeightedFees(User, string)` | public | Calculates value-weighted average fees (platform, OCF, advisory) across all pensions or investment accounts. OCF is weighted from individual holdings. |
| `getYearsToRetirement(User)` | public | Uses `target_retirement_age` or pension retirement age or default 68. |

---

## 7. Validation Requests

### 7.1 UpdatePersonalInfoRequest

**File:** `app/Http/Requests/UpdatePersonalInfoRequest.php`

| Field | Rules | Notes |
|-------|-------|-------|
| `first_name` | sometimes, string, max:255 | |
| `surname` | sometimes, string, max:255 | |
| `email` | sometimes, email, max:255, unique:users (ignoring self) | |
| `date_of_birth` | sometimes, nullable, date, 18-105 age range | `before_or_equal: now()-18y`, `after: now()-105y` |
| `gender` | sometimes, nullable, in: male, female, other, prefer_not_to_say | |
| `marital_status` | sometimes, nullable, in: single, married, divorced, widowed | |
| `national_insurance_number` | sometimes, nullable, regex: `/^[A-Z]{2}[0-9]{6}[A-Z]{1}$/` | Format: AB123456C |
| `address_line_1` | sometimes, nullable, string, max:255 | |
| `address_line_2` | sometimes, nullable, string, max:255 | |
| `city` | sometimes, nullable, string, max:255 | |
| `county` | sometimes, nullable, string, max:255 | |
| `postcode` | sometimes, nullable, regex: UK postcode format | `/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i` |
| `phone` | sometimes, nullable, regex: UK phone | `/^(\+44\|0)[0-9]{10}$/` |
| `good_health` | sometimes, nullable, boolean | |
| `smoker` | sometimes, nullable, boolean | |
| `education_level` | sometimes, nullable, in: secondary, a_level, undergraduate, postgraduate, professional, other | |
| `charitable_bequest` | sometimes, nullable, boolean | |

### 7.2 UpdateDomicileInfoRequest

**File:** `app/Http/Requests/UpdateDomicileInfoRequest.php`

| Field | Rules | Notes |
|-------|-------|-------|
| `domicile_status` | required, in: uk_domiciled, non_uk_domiciled | |
| `country_of_birth` | required, string, max:255 | |
| `uk_arrival_date` | required_if:domicile_status,non_uk_domiciled, nullable, date, before_or_equal:today | |

### 7.3 UpdateIncomeOccupationRequest

**File:** `app/Http/Requests/UpdateIncomeOccupationRequest.php`

| Field | Rules | Notes |
|-------|-------|-------|
| `occupation` | sometimes, nullable, string, max:255 | |
| `employer` | sometimes, nullable, string, max:255 | |
| `industry` | sometimes, nullable, string, max:255 | |
| `employment_status` | sometimes, nullable, in: employed, part_time, self_employed, unemployed, retired, student, other | |
| `target_retirement_age` | sometimes, nullable, integer, 30-100 | |
| `retirement_date` | sometimes, nullable, date | |
| `annual_employment_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `annual_self_employment_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `annual_rental_income` | sometimes, nullable, numeric, 0-9999999999.99 | Note: overridden by calculated value |
| `annual_dividend_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `annual_interest_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `annual_trust_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `annual_other_income` | sometimes, nullable, numeric, 0-9999999999.99 | |
| `income_needs_update` | sometimes, nullable, boolean | |
| `previous_employment_status` | sometimes, nullable, string, max:50 | |

### 7.4 StoreFamilyMemberRequest

**File:** `app/Http/Requests/StoreFamilyMemberRequest.php`

| Field | Rules | Notes |
|-------|-------|-------|
| `relationship` | required, in: spouse, child, step_child, parent, other_dependent | |
| `email` | nullable, email, max:255 | Required for spouse creation |
| `name` | nullable, string, max:255 | Optional -- constructed from name parts |
| `first_name` | required, string, max:255 | |
| `middle_name` | nullable, string, max:255 | |
| `last_name` | required, string, max:255 | |
| `date_of_birth` | nullable, date, before_or_equal:today, after: now()-105y | |
| `gender` | nullable, in: male, female, other, prefer_not_to_say | |
| `national_insurance_number` | nullable, regex: `/^$\|^[A-Z]{2}[0-9]{6}[A-Z]{1}$/` | Empty or valid NI |
| `annual_income` | nullable, numeric, 0-9999999999.99 | |
| `is_dependent` | sometimes, boolean | |
| `education_status` | nullable, in: pre_school, primary, secondary, further_education, higher_education, graduated, not_applicable | |
| `receives_child_benefit` | nullable, boolean | |
| `notes` | nullable, string, max:1000 | |

**Custom validation (withValidator):**
- Spouse must be at least 16 years old
- Child not in education must be 18 or younger
- Child in education (pre_school through higher_education) must be 22 or younger

### 7.5 UpdateFamilyMemberRequest

**File:** `app/Http/Requests/UpdateFamilyMemberRequest.php`

Same fields as StoreFamilyMemberRequest but all rules use `sometimes` instead of `required`. Same custom age validation applies.

### 7.6 StorePersonalAccountLineItemRequest

**File:** `app/Http/Requests/StorePersonalAccountLineItemRequest.php`

| Field | Rules | Notes |
|-------|-------|-------|
| `account_type` | nullable, in: profit_and_loss, cashflow, balance_sheet | |
| `period_start` | required, date | Auto-defaults to current UK tax year start (Apr 6) |
| `period_end` | nullable, date, after_or_equal:period_start | Auto-defaults to current UK tax year end (Apr 5) |
| `line_item` | required, string, max:255 | |
| `category` | required, in: income, expense, asset, liability, equity, cash_inflow, cash_outflow | |
| `amount` | required, numeric, -9999999999.99 to 9999999999.99 | |
| `notes` | nullable, string, max:1000 | |

The `prepareForValidation()` method sets default period dates to the current UK tax year (April 6 to April 5) if not provided.

---

## 8. Vuex Store

**File:** `resources/js/store/modules/userProfile.js` (614 lines)

**Namespace:** `userProfile` (namespaced: true)

### State

```javascript
{
  profile: null,            // Complete profile object from getProfile
  personalInfo: null,       // Formatted personal info
  familyMembers: [],        // Array of family member objects
  incomeOccupation: null,   // Income/occupation data
  personalAccounts: {
    profitAndLoss: null,
    cashflow: null,
    balanceSheet: null,
  },
  spouseAccounts: null,     // Spouse's personal accounts (same structure)
  loading: false,
  error: null,
}
```

**Security:** Financial data is stored in memory only -- never persisted to localStorage. All financial data is fetched fresh from the API on each session.

### Getters

| Getter | Description |
|--------|-------------|
| `profile` | Returns profile state |
| `user` | Delegates to `auth/user` root getter (most up-to-date user object) |
| `domicileInfo` | Returns `profile.domicile_info` |
| `personalInfo` | Returns personalInfo state |
| `familyMembers` | Returns familyMembers array |
| `spouse` | 3-fallback resolution: (1) Find member with `relationship === 'spouse'` in familyMembers, (2) Check `currentUser.spouse` loaded relationship, (3) Fallback to basic object with just `spouse_id`. Always adds `id: currentUser.spouse_id`. |
| `incomeOccupation` | Returns incomeOccupation state |
| `totalAnnualIncome` | Returns `incomeOccupation.total_annual_income` or 0 |
| `personalAccounts` | Returns personalAccounts state |
| `spouseAccounts` | Returns spouseAccounts state |
| `loading` | Returns loading state |
| `error` | Returns error state |
| `juniorIsaEligibleChildren` | Filters familyMembers for child/step_child/other_dependent under 18. Used by Savings module to determine Junior ISA eligibility. |

### Actions

| Action | API Call | Side Effects |
|--------|----------|------------|
| `fetchProfile` | `getProfile()` | Commits: setProfile, setPersonalInfo, setIncomeOccupation, setFamilyMembers |
| `updatePersonalInfo(data)` | `updatePersonalInfo(data)` | Formats date_of_birth to yyyy-MM-dd. Commits: setPersonalInfo + `auth/setUser` (root). |
| `updateIncomeOccupation(data)` | `updateIncomeOccupation(data)` | Builds client-side total_annual_income from individual income fields. Commits: setIncomeOccupation + `auth/setUser` (root). |
| `updateExpenditure(data)` | `updateExpenditure(data)` | Re-fetches full profile on success. |
| `updateDomicile(data)` | `updateDomicile(data)` | Commits `auth/setUser` (root), re-fetches full profile. |
| `updateSpouseExpenditure({spouseId, expenditureData})` | `updateSpouseExpenditure(spouseId, data)` | Re-fetches full profile on success. |
| `fetchFamilyMembers` | `getFamilyMembers()` | Commits: setFamilyMembers |
| `addFamilyMember(data)` | `createFamilyMember(data)` | Commits: addFamilyMember (push to array) |
| `updateFamilyMember({id, data})` | `updateFamilyMember(id, data)` | Commits: updateFamilyMember (splice by ID) |
| `deleteFamilyMember(id)` | `deleteFamilyMember(id)` | Commits: removeFamilyMember (filter by ID) |
| `calculatePersonalAccounts(params)` | `calculatePersonalAccounts(params)` | Commits: setPersonalAccounts (includes spouse data if available) |
| `addLineItem(data)` | `createLineItem(data)` | Re-dispatches `calculatePersonalAccounts` |
| `updateLineItem({id, data})` | `updateLineItem(id, data)` | Re-dispatches `calculatePersonalAccounts` |
| `deleteLineItem(id)` | `deleteLineItem(id)` | Re-dispatches `calculatePersonalAccounts` |

### Mutations

| Mutation | Description |
|----------|-------------|
| `setProfile` | Sets complete profile |
| `setPersonalInfo` | Sets personal info |
| `setFamilyMembers` | Sets family members array |
| `setIncomeOccupation` | Sets income/occupation |
| `setPersonalAccounts` | Sets P&L, cashflow, balanceSheet + spouse data |
| `addFamilyMember` | Pushes to array |
| `updateFamilyMember` | Finds by ID and splices |
| `removeFamilyMember` | Filters out by ID |
| `setLoading` | Sets loading flag |
| `setError` | Sets error message |
| `resetState` | Resets all state to initial values (memory-only, no localStorage cleanup needed) |

---

## 9. API Service (Frontend)

### 9.1 userProfileService.js

**File:** `resources/js/services/userProfileService.js`

| Method | HTTP | Endpoint |
|--------|------|----------|
| `getProfile()` | GET | `/user/profile` |
| `updatePersonalInfo(data)` | PUT | `/user/profile/personal` |
| `updateIncomeOccupation(data)` | PUT | `/user/profile/income-occupation` |
| `updateExpenditure(data)` | PUT | `/user/profile/expenditure` |
| `updateDomicile(data)` | PUT | `/user/profile/domicile` |
| `updateSpouseExpenditure(spouseId, data)` | PUT | `/users/{spouseId}/expenditure` |
| `getFamilyMembers()` | GET | `/user/family-members` |
| `createFamilyMember(data)` | POST | `/user/family-members` |
| `getFamilyMember(id)` | GET | `/user/family-members/{id}` |
| `updateFamilyMember(id, data)` | PUT | `/user/family-members/{id}` |
| `deleteFamilyMember(id)` | DELETE | `/user/family-members/{id}` |
| `getPersonalAccounts()` | GET | `/user/personal-accounts` |
| `calculatePersonalAccounts(params)` | POST | `/user/personal-accounts/calculate` |
| `createLineItem(data)` | POST | `/user/personal-accounts/line-item` |
| `updateLineItem(id, data)` | PUT | `/user/personal-accounts/line-item/{id}` |
| `deleteLineItem(id)` | DELETE | `/user/personal-accounts/line-item/{id}` |
| `getFinancialCommitments()` | GET | `/user/financial-commitments` |
| `getSpouseFinancialCommitments()` | GET | `/user/spouse/financial-commitments` |
| `getProfileCompleteness()` | GET | `/user/profile/completeness` |
| `getSpouse()` | GET | `/user/spouse` |
| `updateSpouse(spouseId, data)` | PUT | `/users/{spouseId}` |
| `updateCharitableBequest(value)` | PUT | `/user/profile/personal` (sends `{charitable_bequest: value}`) |

### 9.2 assumptionsService.js

**File:** `resources/js/services/assumptionsService.js`

| Method | HTTP | Endpoint |
|--------|------|----------|
| `getAssumptions()` | GET | `/settings/assumptions` |
| `updateAssumptions(type, data)` | PUT | `/settings/assumptions/{type}` |
| `resetAssumptions(type)` | PUT | `/settings/assumptions/{type}` (sends `{reset: true}`) |

---

## 10. Frontend Components

All 26 components are located in `resources/js/components/UserProfile/`.

### 10.1 Personal & Health Information

| Component | File | Description |
|-----------|------|-------------|
| **PersonalInformation** | `PersonalInformation.vue` | Main personal info form. Fields: first name, surname, email, DOB, gender, marital status, NI number, address (with postcode lookup), phone, education level. Uses postcode lookup for address autocomplete. |
| **HealthInformation** | `HealthInformation.vue` | Health status fields: good_health (boolean toggle), smoker (boolean toggle). Used by Protection module for premium calculations. |

### 10.2 Income & Occupation

| Component | File | Description |
|-----------|------|-------------|
| **IncomeOccupation** | `IncomeOccupation.vue` | Occupation details (with SOC 2020 autocomplete), employer, industry, employment status, target retirement age. Income fields: employment, self-employment, rental (read-only, calculated), dividends, interest, trust, other. Tax summary display. |
| **TaxSummaryCard** | `TaxSummaryCard.vue` | Displays gross income, income tax, National Insurance, total deductions, net income, effective tax rate. Uses detailed_tax_breakdown from the API. |
| **TaxIncomeCard** | `TaxIncomeCard.vue` | Per-income-source tax breakdown. Shows how each income type (employment, self-employment, rental, dividends, etc.) is taxed individually. |

### 10.3 Expenditure

| Component | File | Description |
|-----------|------|-------------|
| **ExpenditureSection** | `ExpenditureSection.vue` | Top-level expenditure container. Manages dual-mode toggle (simple vs category). Contains ExpenditureForm, ExpenditureOverview, and financial commitments. Handles spouse expenditure in separate mode. |
| **ExpenditureForm** | `ExpenditureForm.vue` | Category-level expenditure input form. 20 individual category fields grouped into sections (Essentials, Lifestyle, Children, Other). |
| **ExpenditureCategoryCard** | `ExpenditureCategoryCard.vue` | Individual category card showing monthly amount, trend indicator. |
| **ExpenditureGridRow** | `ExpenditureGridRow.vue` | Single row in the expenditure grid. Shows category label, monthly amount, annual total. |
| **ExpenditureExpandableGridRow** | `ExpenditureExpandableGridRow.vue` | Expandable row showing a category with sub-items (e.g., property costs breaking down into mortgage, council tax, utilities). |
| **ExpenditureOverview** | `ExpenditureOverview.vue` | Summary view showing total monthly/annual expenditure, split between manual expenditure and financial commitments. Pie chart breakdown. |

### 10.4 Family Members

| Component | File | Description |
|-----------|------|-------------|
| **FamilyMembers** | `FamilyMembers.vue` | List of family members with add/edit/delete. Displays spouse, children, parents, dependants. Shows shared members from spouse with "Shared" badge. |
| **FamilyMemberFormModal** | `FamilyMemberFormModal.vue` | Modal form for adding/editing family members. Dynamically shows fields based on relationship type (email for spouse, education_status for children, receives_child_benefit). Age validation feedback. |

### 10.5 Domicile

| Component | File | Description |
|-----------|------|-------------|
| **DomicileInformation** | `DomicileInformation.vue` | Domicile status (UK/non-UK), country of birth, UK arrival date (shown if non-UK). Displays deemed domicile calculation (15 years after arrival). |

### 10.6 Personal Accounts

| Component | File | Description |
|-----------|------|-------------|
| **PersonalAccounts** | `PersonalAccounts.vue` | Tab container for the three financial statements. Manages period selection and calculates all statements. |
| **IncomeStatementTab** | `IncomeStatementTab.vue` | Income Statement (P&L) display. Shows income items, expense items, net profit/loss. |
| **CashFlowTab** | `CashFlowTab.vue` | Cashflow statement display. Shows inflows, outflows, net cashflow. |
| **BalanceSheetTab** | `BalanceSheetTab.vue` | Balance Sheet display. Shows assets, liabilities, equity/net worth. |
| **ProfitAndLossView** | `ProfitAndLossView.vue` | Detailed P&L view with manual line item management. |
| **CashflowView** | `CashflowView.vue` | Detailed cashflow view with manual line item management. |
| **BalanceSheetView** | `BalanceSheetView.vue` | Detailed balance sheet view with manual line item management. |

### 10.7 Assets & Liabilities Summary

| Component | File | Description |
|-----------|------|-------------|
| **AssetsOverview** | `AssetsOverview.vue` | Summary of all assets: cash, investments, properties, business interests, chattels, pensions. Shows count and total per category. |
| **LiabilitiesOverview** | `LiabilitiesOverview.vue` | Summary of all liabilities: mortgages (from both Mortgage and Liability tables), other liabilities. Shows items with monthly payments. |

### 10.8 Spouse & Letter

| Component | File | Description |
|-----------|------|-------------|
| **SpouseDataSharing** | `SpouseDataSharing.vue` | Manages spouse permission status. Shows linked/unlinked state. Controls data sharing preferences. |
| **LetterToSpouse** | `LetterToSpouse.vue` | 4-part letter editor. Part 1: Immediate Actions (executor, attorney, financial advisor, accountant, funds access, employer). Part 2: Accounts & Assets (password manager, phone, banks, investments, insurance, real estate, vehicles, valuables, crypto, liabilities, bills). Part 3: Long-term Plans (estate documents, beneficiaries, education, financial guidance, social security). Part 4: Funeral (preference, service, obituary, additional wishes, custom boxes). Supports read-only spouse view. |

### 10.9 Settings

| Component | File | Description |
|-----------|------|-------------|
| **Settings** | `Settings.vue` | Settings page with links to security, privacy, and assumptions settings. |

---

## 11. Frontend Routing

| Path | Name | Component | Auth | Description |
|------|------|-----------|------|-------------|
| `/profile` | UserProfile | `views/UserProfile.vue` | Yes | Main profile page with all profile sections |
| `/preview/profile` | (unnamed) | `views/UserProfile.vue` | Yes | Preview mode profile |
| `/settings` | Settings | `views/Settings.vue` | Yes | Settings hub |
| `/settings/security` | SecuritySettings | `views/Settings/SecuritySettings.vue` | Yes | Security settings |
| `/settings/privacy` | PrivacySettings | `views/Settings/PrivacySettings.vue` | Yes | Privacy settings |
| `/settings/assumptions` | AssumptionsSettings | `views/Settings/AssumptionsSettings.vue` | Yes | Planning assumptions editor |

The profile page uses hash-based section navigation (`/profile#family`, `/profile#income-occupation`, `/profile#domicile`, `/profile#personal`) referenced by ProfileCompletenessChecker recommendations.

---

## 12. Cross-Module Integration

The User Profile module is consumed by every other module in the system. It provides foundational data without depending on any other module's analysis output.

### Data Consumed BY Other Modules

| Consumer Module | Data Used | Purpose |
|----------------|-----------|---------|
| **Protection** | income, spouse, dependants, mortgages, liabilities, occupation, health, smoker | Protection needs analysis, premium estimation, income replacement |
| **Savings** | monthly_expenditure, income, savings accounts, junior ISA eligible children | Emergency fund calculation, ISA allowance tracking |
| **Investment** | DOB, income, target_retirement_age, risk profile | Asset allocation, time horizon, tax-efficient wrapper selection |
| **Retirement** | DOB, target_retirement_age, income, expenditure, DC/DB/State pensions | Pension projection, income replacement ratio, drawdown planning |
| **Estate Planning** | domicile_status, marital_status, properties, investments, business interests, chattels, family members, trusts | IHT calculation, estate valuation, RNRB eligibility, spouse exemption |
| **Goals & Life Events** | income, expenditure, assets, family members, DOB | Goal affordability, life event timeline |
| **Coordination** | All profile data | Coordinating Agent aggregates all module outputs |

### Data Pulled FROM Other Modules

| Source Module | Data | Purpose |
|--------------|------|---------|
| **Property** | PropertyService::calculateTaxPosition() | Rental income calculation (always recalculated, never user-editable) |
| **Risk** | RiskPreferenceService::getMainRiskLevel(), getReturnParameters() | Default return rates for assumptions |
| **Tax** | UKTaxCalculator::calculateDetailedNetIncome() | Income tax and NI breakdown |
| **Benefits** | ChildBenefitService::calculateChildBenefitPosition() | Child Benefit and HICBC |
| **Shared** | CrossModuleAssetAggregator::getAssetBreakdown() | Assets summary for profile |
| **Protection** | Protection policies (life, CI, IP, disability, sickness) | Financial commitments, letter to spouse auto-population |
| **Savings** | SavingsAccount contributions | Financial commitments |
| **Investment** | InvestmentAccount contributions | Financial commitments |
| **Estate** | Liabilities | Financial commitments, liabilities summary |

---

## 13. Profile Completeness

The ProfileCompletenessChecker provides a scored assessment used on the dashboard to guide users through data entry.

### Scoring

- Each check is worth equal weight
- Score = (passed checks / total checks) * 100
- Married users: 7 checks (max 100 = 7/7)
- Single users: 6 checks (max 100 = 6/6)
- `is_complete` is true when score >= 100

### Check Details

| Check | Priority | Applies To | Link |
|-------|----------|-----------|------|
| `spouse_linked` | high | Married only | /profile#family |
| `dependants` | medium (married) / high (single) | All | /profile#family |
| `domicile_info` | medium | All | /profile#domicile |
| `income` | high | All | /profile#income-occupation |
| `expenditure` | medium | All | /profile#personal |
| `assets` | high | All | /net-worth |
| `protection_plans` | high | All | /protection |

### Recommendation Generation

Missing checks are sorted by priority (high > medium > low). Each generates a plain-English recommendation string that is displayed to the user on the dashboard.

---

## 14. Seeder Data

### OccupationCodeSeeder

**File:** `database/seeders/OccupationCodeSeeder.php`

Seeds the `occupation_codes` table with ONS SOC 2020 classification data. Each record has a `soc_code` (4-digit unit group), `title`, `unit_group` description, and hierarchy fields (minor_group, sub_major_group, major_group). The `is_primary` flag marks the canonical title for each SOC code.

### PreviewUserSeeder

**File:** `database/seeders/PreviewUserSeeder.php`

Seeds 6 preview personas with pre-populated profile data:

| Persona | Users | Key Profile Data |
|---------|-------|------------------|
| young_family | James & Emily Carter | Married, children, workplace pensions, mortgage |
| peak_earners | David & Sarah Mitchell | Married, multiple properties, SIPP + NHS pension |
| widow | Margaret Thompson | Widowed, estate planning focus |
| entrepreneur | Alex Chen | Self-employed, SIPP, business interests |
| young_saver | John Morgan | Single, emergency fund, first-time savings |
| retired_couple | Robert & Patricia Williams | Retired, decumulation, estate planning |

All preview users have `is_preview_user = true` and are completely isolated from real users.

---

## 15. API Routing (Complete Route List)

All routes require `auth:sanctum` middleware.

### User Profile Group (`/api/user/`)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/user/profile` | UserProfileController@getProfile |
| PUT | `/api/user/profile/personal` | UserProfileController@updatePersonalInfo |
| PUT | `/api/user/profile/income-occupation` | UserProfileController@updateIncomeOccupation |
| PUT | `/api/user/profile/expenditure` | UserProfileController@updateExpenditure |
| PUT | `/api/user/profile/domicile` | UserProfileController@updateDomicileInfo |
| GET | `/api/user/profile/completeness` | ProfileCompletenessController@check |
| GET | `/api/user/financial-commitments` | UserProfileController@getFinancialCommitments |
| GET | `/api/user/spouse/financial-commitments` | UserProfileController@getSpouseFinancialCommitments |
| PUT | `/api/user/dashboard-widget-order` | UserProfileController@updateDashboardWidgetOrder |

### Letter to Spouse (`/api/user/letter-to-spouse`)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/user/letter-to-spouse` | LetterToSpouseController@show |
| GET | `/api/user/letter-to-spouse/exists` | LetterToSpouseController@exists |
| GET | `/api/user/letter-to-spouse/spouse` | LetterToSpouseController@showSpouse |
| PUT | `/api/user/letter-to-spouse` | LetterToSpouseController@update |

### Family Members (`/api/user/family-members`)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/user/family-members` | FamilyMembersController@index |
| POST | `/api/user/family-members` | FamilyMembersController@store |
| GET | `/api/user/family-members/{id}` | FamilyMembersController@show |
| PUT | `/api/user/family-members/{id}` | FamilyMembersController@update |
| DELETE | `/api/user/family-members/{id}` | FamilyMembersController@destroy |

### Personal Accounts (`/api/user/personal-accounts`)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/user/personal-accounts` | PersonalAccountsController@index |
| POST | `/api/user/personal-accounts/calculate` | PersonalAccountsController@calculate |
| POST | `/api/user/personal-accounts/line-item` | PersonalAccountsController@storeLineItem |
| PUT | `/api/user/personal-accounts/line-item/{id}` | PersonalAccountsController@updateLineItem |
| DELETE | `/api/user/personal-accounts/line-item/{id}` | PersonalAccountsController@deleteLineItem |

### Spouse Data Access (general auth group)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/users/{userId}` | UserProfileController@getUserById |
| PUT | `/api/users/{userId}/expenditure` | UserProfileController@updateSpouseExpenditure |

### Settings (`/api/settings`)

| Method | Endpoint | Controller@Method |
|--------|----------|-------------------|
| GET | `/api/settings/assumptions` | AssumptionsController@index |
| PUT | `/api/settings/assumptions/{type}` | AssumptionsController@update |

### Utilities

| Method | Endpoint | Controller@Method | Extra Middleware |
|--------|----------|-------------------|-----------------|
| GET | `/api/postcode-lookup/{postcode}` | PostcodeLookupController@lookup | `throttle:30,1` |
| GET | `/api/occupations/search` | OccupationController@search | -- |

**Total: ~25 endpoints**

---

## 16. Key Constants

### Employment Status Values
```
employed, part_time, self_employed, unemployed, retired, student, other
```

### Family Relationship Types
```
spouse, child, step_child, parent, other_dependent
```

### Education Status Values
```
pre_school, primary, secondary, further_education, higher_education, graduated, not_applicable
```

### Domicile Status Values
```
uk_domiciled, non_uk_domiciled
```

### Gender Values
```
male, female, other, prefer_not_to_say
```

### Marital Status Values
```
single, married, divorced, widowed
```

### Education Level Values
```
secondary, a_level, undergraduate, postgraduate, professional, other
```

### Personal Account Types
```
profit_and_loss, cashflow, balance_sheet
```

### Personal Account Categories
```
income, expense, asset, liability, equity, cash_inflow, cash_outflow
```

### Funeral Preference Values
```
burial, cremation, not_specified
```

### Assumption Types
```
pensions, investments, estate_planning
```

### Investment Growth Methods (Estate Planning)
```
monte_carlo, custom
```

### Dashboard Widget IDs
```
net_worth, affordability, retirement, investment, tax, estate, protection, trusts, admin_taxes
```

### Default Assumptions
```
Inflation Rate: 2.0%
Compound Periods: 12 (monthly)
Retirement Age: 68
Property Growth Rate: 3.0%
Investment Growth Method: monte_carlo
Default Return Rate: From risk profile (fallback: 5.0%)
```

### Expenditure Categories (20 fields on users table)
```
food_groceries, transport_fuel, healthcare_medical, insurance, mobile_phones,
internet_tv, subscriptions, clothing_personal_care, entertainment_dining,
holidays_travel, pets, childcare, school_fees, school_lunches, school_extras,
university_fees, children_activities, gifts_charity, regular_savings, other_expenditure
```

### Expenditure Entry Modes
```
simple    -- single monthly_expenditure field
category  -- 20 individual category fields summed
```

### Expenditure Sharing Modes
```
joint     -- shared expenditure between spouses
separate  -- each spouse tracks independently
```

### Financial Commitment Categories
```
retirement, properties, investments, savings, protection, liabilities
```

### UK Validation Patterns
```
NI Number:  /^[A-Z]{2}[0-9]{6}[A-Z]{1}$/
Postcode:   /^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i
Phone:      /^(\+44|0)[0-9]{10}$/
```

---

## 17. Known Issues

1. **Rental income not user-editable:** The `annual_rental_income` field submitted by the user is always overwritten with the calculated value from PropertyService. If no BTL properties exist, rental income will be zero regardless of what the user enters. This is by design but can confuse users who have rental income not captured in the Property module.

2. **Expenditure profile table vs users table:** The detailed 20-category expenditure breakdown is stored directly on the `users` table, while the `expenditure_profiles` table stores only high-level summary categories (housing, food, utilities, etc.). The ExpenditureProfile record is mainly populated as a side effect of `updateExpenditure` with zeroed categories and just the total. The two storage locations are somewhat redundant.

3. **Spouse family member record inconsistency:** When spouses are linked, the system creates reciprocal FamilyMember records. However, `getFamilyMembersWithSharing` includes fallback logic to create a "virtual" spouse FamilyMember from the User record if the FamilyMember record is missing. This suggests the reciprocal record creation is not always reliable.

4. **Child deduplication relies on exact match:** Duplicate child detection between linked spouses uses exact string matching on first_name, last_name, and date_of_birth. Name spelling differences (e.g., "Alfie" vs "Alfred") or missing DOB would result in duplicate children appearing.

5. **Occupation search LIKE performance:** The `OccupationCode::search()` method uses `LIKE '%query%'` with `ORDER BY CASE` expressions. On large occupation code tables, this could be slow. No database index is used for the text search.

6. **Letter to Spouse auto-population is one-time:** The letter defaults are generated only on first creation (`createWithDefaults`). If the user adds new financial data after the letter is created, the auto-populated sections are not refreshed. Users must manually update the letter.

---

## 18. Deep Dive: Financial Commitments & Expenditure System

The User Profile integrates with the expenditure and financial commitments system to provide a complete picture of a user's outgoings. This includes:

- **Dual expenditure modes**: Simple (single annual figure) and Category-based (20+ itemised categories)
- **Six financial commitment sources**: Mortgages, protection premiums, savings contributions, investment contributions, pension contributions, and loan repayments
- **Ownership-aware calculations**: Joint assets contribute proportional amounts based on ownership percentage

See **ExpenditureIncome.md** for the complete system documentation including the expenditure breakdown service, financial commitments aggregation, frequency normalisation, and tax integration.

### Key API Endpoints
| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/user/financial-commitments` | Get user's aggregated financial commitments |
| `GET` | `/api/user/spouse-financial-commitments` | Get spouse's financial commitments |
| `PUT` | `/api/user/expenditure` | Update expenditure (simple or category mode) |
