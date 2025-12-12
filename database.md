# Fynla Database Documentation

Complete database schema documentation for the Fynla Financial Planning System.

**Database**: MySQL 8.0+
**Total Tables**: 60
**Last Updated**: December 10, 2025

---

## Table of Contents

1. [User & Authentication](#user--authentication)
2. [Family & Household](#family--household)
3. [Protection Module](#protection-module)
4. [Savings Module](#savings-module)
5. [Investment Module](#investment-module)
6. [Retirement Module](#retirement-module)
7. [Estate Planning Module](#estate-planning-module)
8. [Net Worth & Properties](#net-worth--properties)
9. [Tax Configuration](#tax-configuration)
10. [Document Upload](#document-upload)
11. [System Tables](#system-tables)
12. [Form Input Mappings](#form-input-mappings)

---

## User & Authentication

### users

Main user account table containing personal information, income, expenditure, and system settings.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| name | varchar(255) | Yes | Full name |
| email | varchar(255) | Yes | Email address (unique) |
| email_verified_at | timestamp | No | When email was verified |
| password | varchar(255) | Yes | Hashed password |
| must_change_password | tinyint(1) | Yes | Force password change on login |
| is_admin | tinyint(1) | Yes | Administrator flag |
| role | enum | Yes | 'user' or 'admin' |
| remember_token | varchar(100) | No | Session remember token |

**Personal Details:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| date_of_birth | date | No | Date of birth |
| gender | enum | No | 'male', 'female', 'other' |
| marital_status | enum | No | 'single', 'married', 'divorced', 'widowed' |
| national_insurance_number | varchar(13) | No | UK NI number |
| phone | varchar(255) | No | Phone number |
| occupation | varchar(255) | No | Job title |
| employer | varchar(255) | No | Employer name |
| industry | varchar(255) | No | Industry sector |
| employment_status | enum | No | 'employed', 'part_time', 'self_employed', 'retired', 'unemployed', 'other' |
| education_level | enum | No | 'secondary', 'a_level', 'undergraduate', 'postgraduate', 'professional', 'other' |

**Health Information:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| health_status | enum | No | 'yes', 'yes_previous', 'no_previous', 'no_existing', 'no_both' |
| smoking_status | enum | Yes | 'never', 'quit_recent', 'quit_long_ago', 'yes' |

**Address:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| address_line_1 | varchar(255) | No | Street address |
| address_line_2 | varchar(255) | No | Additional address |
| city | varchar(255) | No | City |
| county | varchar(255) | No | County |
| postcode | varchar(10) | No | UK postcode |

**Domicile:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| domicile_status | enum | No | 'uk_domiciled', 'non_uk_domiciled' |
| country_of_birth | varchar(255) | No | Birth country |
| uk_arrival_date | date | No | Date arrived in UK |
| years_uk_resident | int | No | Years living in UK |
| deemed_domicile_date | date | No | Deemed domicile date |

**Retirement Planning:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| target_retirement_age | tinyint unsigned | No | Planned retirement age |
| retirement_date | date | No | Expected retirement date |

**Income:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| annual_employment_income | decimal(15,2) | No | Employment income |
| annual_self_employment_income | decimal(15,2) | No | Self-employment income |
| annual_rental_income | decimal(15,2) | No | Rental property income |
| annual_dividend_income | decimal(15,2) | No | Dividend income |
| annual_interest_income | double | Yes | Interest income |
| annual_other_income | decimal(15,2) | No | Other income sources |

**Expenditure (Monthly):**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| monthly_expenditure | double | No | Simple total monthly spend |
| annual_expenditure | double | No | Simple total annual spend |
| food_groceries | double | Yes | Food and groceries |
| transport_fuel | double | Yes | Transport and fuel |
| healthcare_medical | double | Yes | Healthcare costs |
| insurance | double | Yes | Insurance premiums |
| mobile_phones | double | Yes | Mobile phone bills |
| internet_tv | double | Yes | Internet and TV subscriptions |
| subscriptions | double | Yes | Other subscriptions |
| clothing_personal_care | double | Yes | Clothing and personal care |
| entertainment_dining | double | Yes | Entertainment and dining |
| holidays_travel | double | Yes | Holidays and travel |
| pets | double | Yes | Pet expenses |
| childcare | double | Yes | Childcare costs |
| school_fees | double | Yes | School fees |
| school_lunches | decimal(10,2) | Yes | School lunches |
| school_extras | decimal(10,2) | Yes | School extras |
| university_fees | decimal(10,2) | Yes | University fees |
| children_activities | double | Yes | Children's activities |
| gifts_charity | double | Yes | Gifts and charity |
| charitable_bequest | tinyint(1) | No | Leaving to charity in will |
| regular_savings | double | Yes | Regular savings amount |
| other_expenditure | double | Yes | Other expenditure |
| expenditure_entry_mode | enum | Yes | 'simple' or 'category' |
| expenditure_sharing_mode | enum | Yes | 'joint' or 'separate' |

**Spouse & Household:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| spouse_id | bigint unsigned | No | Linked spouse user ID |
| household_id | bigint unsigned | No | Household group ID |
| is_primary_account | tinyint(1) | Yes | Primary household account |

**Onboarding:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| onboarding_completed | tinyint(1) | Yes | Onboarding finished |
| onboarding_focus_area | enum | No | 'estate', 'protection', 'retirement', 'investment', 'tax_optimisation' |
| onboarding_current_step | varchar(255) | No | Current step name |
| onboarding_skipped_steps | json | No | Array of skipped steps |
| onboarding_started_at | timestamp | No | When onboarding started |
| onboarding_completed_at | timestamp | No | When onboarding completed |
| liabilities_reviewed | tinyint(1) | Yes | Liabilities step completed |

---

### password_reset_tokens

Password reset functionality.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| email | varchar(255) | Yes | User email (primary key) |
| token | varchar(255) | Yes | Reset token |
| created_at | timestamp | No | Token creation time |

---

### personal_access_tokens

API authentication tokens (Laravel Sanctum).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| tokenable_type | varchar(255) | Yes | Model type |
| tokenable_id | bigint unsigned | Yes | User ID |
| name | varchar(255) | Yes | Token name |
| token | varchar(64) | Yes | Hashed token (unique) |
| abilities | text | No | Token permissions |
| last_used_at | timestamp | No | Last usage time |
| expires_at | timestamp | No | Expiration time |

---

### spouse_permissions

Manages data sharing permissions between linked spouse accounts.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Requesting user |
| spouse_id | bigint unsigned | Yes | Spouse user |
| status | enum | Yes | 'pending', 'accepted', 'rejected' |
| requested_at | timestamp | No | Request timestamp |
| responded_at | timestamp | No | Response timestamp |

---

## Family & Household

### households

Groups related user accounts together.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| household_name | varchar(255) | No | Household identifier |
| notes | text | No | Additional notes |

---

### family_members

Records family members and dependents.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| household_id | bigint unsigned | No | Household ID |
| relationship | enum | Yes | 'spouse', 'child', 'parent', 'other_dependent' |
| first_name | varchar(255) | Yes | First name |
| middle_name | varchar(255) | No | Middle name |
| last_name | varchar(255) | Yes | Last name |
| name | varchar(255) | Yes | Full name (computed) |
| date_of_birth | date | No | Date of birth |
| gender | enum | No | 'male', 'female', 'other', 'prefer_not_to_say' |
| national_insurance_number | varchar(13) | No | NI number |
| annual_income | decimal(15,2) | No | Annual income |
| is_dependent | tinyint(1) | Yes | Financial dependent flag |
| education_status | enum | No | 'pre_school', 'primary', 'secondary', 'further_education', 'higher_education', 'graduated', 'not_applicable' |
| notes | text | No | Additional notes |

**Form Input Mapping:**
- First Name field → `first_name`
- Middle Name field → `middle_name`
- Last Name field → `last_name`
- Relationship dropdown → `relationship`
- Date of Birth picker → `date_of_birth`
- Gender dropdown → `gender`
- Is Dependent checkbox → `is_dependent`
- Education Status dropdown → `education_status`

---

### joint_account_logs

Audit trail for changes to jointly owned assets.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | User making change |
| joint_owner_id | bigint unsigned | Yes | Joint owner ID |
| loggable_type | varchar(255) | Yes | Model type (polymorphic) |
| loggable_id | bigint unsigned | Yes | Record ID |
| changes | json | Yes | Change details |
| action | varchar(255) | Yes | Action type (e.g., 'update') |

---

### letters_to_spouse

Letter to spouse feature - emergency instructions for surviving spouse.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| immediate_actions | text | No | Immediate steps to take |
| executor_name | varchar(255) | No | Executor name |
| executor_contact | varchar(255) | No | Executor contact |
| attorney_name | varchar(255) | No | Solicitor/attorney name |
| attorney_contact | varchar(255) | No | Solicitor contact |
| financial_advisor_name | varchar(255) | No | Financial advisor name |
| financial_advisor_contact | varchar(255) | No | FA contact |
| accountant_name | varchar(255) | No | Accountant name |
| accountant_contact | varchar(255) | No | Accountant contact |
| immediate_funds_access | text | No | How to access funds |
| employer_hr_contact | varchar(255) | No | HR contact details |
| employer_benefits_info | text | No | Employment benefits info |
| password_manager_info | text | No | Password manager access |
| phone_plan_info | text | No | Phone plan details |
| bank_accounts_info | text | No | Bank account details |
| investment_accounts_info | text | No | Investment account details |
| insurance_policies_info | text | No | Insurance policy details |
| real_estate_info | text | No | Property information |
| vehicles_info | text | No | Vehicle information |
| valuable_items_info | text | No | Valuables information |
| cryptocurrency_info | text | No | Crypto holdings info |
| liabilities_info | text | No | Debt information |
| recurring_bills_info | text | No | Recurring payments |
| estate_documents_location | text | No | Where documents are stored |
| beneficiary_info | text | No | Beneficiary details |
| children_education_plans | text | No | Education plans |
| financial_guidance | text | No | Financial advice |
| social_security_info | text | No | State pension info |
| funeral_preference | enum | Yes | 'burial', 'cremation', 'not_specified' |
| funeral_service_details | text | No | Funeral wishes |
| obituary_wishes | text | No | Obituary preferences |
| additional_wishes | text | No | Other wishes |

---

## Protection Module

### protection_profiles

User's protection planning profile and context.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID (unique) |
| annual_income | decimal(15,2) | Yes | Annual income for calculations |
| monthly_expenditure | decimal(10,2) | Yes | Monthly spending |
| mortgage_balance | decimal(15,2) | Yes | Outstanding mortgage |
| other_debts | decimal(15,2) | Yes | Other debts total |
| number_of_dependents | int | Yes | Number of dependents |
| dependents_ages | json | No | Array of dependent ages |
| retirement_age | int | Yes | Target retirement age |
| occupation | varchar(255) | No | Occupation |
| smoker_status | tinyint(1) | Yes | Smoker flag |
| health_status | varchar(255) | Yes | Health status |
| has_no_policies | tinyint(1) | Yes | No protection policies flag |

---

### life_insurance_policies

Life insurance policy records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| policy_type | enum | Yes | 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term' |
| provider | varchar(255) | Yes | Insurance company |
| policy_number | varchar(255) | No | Policy reference |
| sum_assured | decimal(15,2) | Yes | Cover amount |
| start_value | decimal(15,2) | No | Initial value (for decreasing) |
| decreasing_rate | decimal(5,4) | No | Annual decrease rate |
| premium_amount | decimal(10,2) | Yes | Premium amount |
| premium_frequency | enum | Yes | 'monthly', 'quarterly', 'annually' |
| policy_start_date | date | No | Policy start date |
| policy_term_years | int | No | Policy term in years |
| policy_end_date | date | No | Policy end date |
| indexation_rate | decimal(5,4) | No | Annual increase rate |
| in_trust | tinyint(1) | Yes | Written in trust |
| is_mortgage_protection | tinyint(1) | Yes | Mortgage protection policy |
| beneficiaries | text | No | Beneficiary details |

**Form Input Mapping:**
- Policy Type dropdown → `policy_type`
- Provider field → `provider`
- Policy Number field → `policy_number`
- Sum Assured field → `sum_assured`
- Premium Amount field → `premium_amount`
- Premium Frequency dropdown → `premium_frequency`
- Start Date picker → `policy_start_date`
- End Date picker → `policy_end_date`
- Term Years field → `policy_term_years`
- In Trust checkbox → `in_trust`
- Mortgage Protection checkbox → `is_mortgage_protection`

---

### critical_illness_policies

Critical illness cover records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| policy_type | enum | Yes | 'standalone', 'accelerated', 'additional' |
| provider | varchar(255) | Yes | Insurance company |
| policy_number | varchar(255) | No | Policy reference |
| sum_assured | decimal(15,2) | Yes | Cover amount |
| premium_amount | decimal(10,2) | Yes | Premium amount |
| premium_frequency | enum | Yes | 'monthly', 'quarterly', 'annually' |
| policy_start_date | date | No | Start date |
| policy_end_date | date | No | End date |
| policy_term_years | int | No | Term in years |
| conditions_covered | json | No | Covered conditions list |

---

### income_protection_policies

Income protection (permanent health insurance) records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| provider | varchar(255) | Yes | Insurance company |
| policy_number | varchar(255) | No | Policy reference |
| benefit_amount | decimal(10,2) | Yes | Monthly/weekly benefit |
| benefit_frequency | enum | Yes | 'monthly', 'weekly' |
| deferred_period_weeks | int | No | Waiting period |
| benefit_period_months | int | No | How long benefits paid |
| premium_amount | decimal(10,2) | Yes | Premium amount |
| premium_frequency | enum | Yes | 'monthly', 'quarterly', 'annually' |
| occupation_class | varchar(255) | No | Occupation classification |
| policy_start_date | date | No | Start date |
| policy_end_date | date | No | End date |

---

### disability_policies

Accident and disability cover records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| provider | varchar(255) | Yes | Insurance company |
| policy_number | varchar(255) | No | Policy reference |
| benefit_amount | decimal(10,2) | Yes | Benefit amount |
| benefit_frequency | enum | Yes | 'monthly', 'weekly' |
| deferred_period_weeks | int | No | Waiting period |
| benefit_period_months | int | No | Benefit duration |
| premium_amount | decimal(10,2) | Yes | Premium amount |
| premium_frequency | enum | Yes | 'monthly', 'quarterly', 'annually' |
| occupation_class | varchar(255) | No | Occupation class |
| policy_start_date | date | No | Start date |
| policy_end_date | date | No | End date |
| policy_term_years | int | No | Term in years |
| coverage_type | enum | Yes | 'accident_only', 'accident_and_sickness' |

---

### sickness_illness_policies

Short-term sickness and illness cover records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| provider | varchar(255) | Yes | Insurance company |
| policy_number | varchar(255) | No | Policy reference |
| benefit_amount | decimal(10,2) | Yes | Benefit amount |
| benefit_frequency | enum | Yes | 'monthly', 'weekly', 'lump_sum' |
| deferred_period_weeks | int | No | Waiting period |
| benefit_period_months | int | No | Benefit duration |
| premium_amount | decimal(10,2) | Yes | Premium amount |
| premium_frequency | enum | Yes | 'monthly', 'quarterly', 'annually' |
| policy_start_date | date | No | Start date |
| policy_end_date | date | No | End date |
| policy_term_years | int | No | Term in years |
| conditions_covered | json | No | Covered conditions |
| exclusions | text | No | Policy exclusions |

---

## Savings Module

### savings_accounts

Cash savings account records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner user ID |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| ownership_percentage | decimal(5,2) | Yes | Ownership % (default 100) |
| account_type | varchar(255) | Yes | Account type description |
| institution | varchar(255) | Yes | Bank/building society |
| account_number | varchar(255) | No | Account number |
| current_balance | decimal(15,2) | Yes | Current balance |
| interest_rate | decimal(5,4) | Yes | Interest rate (e.g., 0.0350 = 3.5%) |
| access_type | enum | Yes | 'immediate', 'notice', 'fixed' |
| notice_period_days | int | No | Notice period (if notice account) |
| maturity_date | date | No | Maturity date (if fixed term) |
| is_isa | tinyint(1) | Yes | Cash ISA flag |
| country | varchar(255) | Yes | Country (default UK) |
| is_emergency_fund | tinyint(1) | Yes | Emergency fund flag |
| isa_type | varchar(255) | No | ISA type if applicable |
| isa_subscription_year | varchar(255) | No | Tax year of subscription |
| isa_subscription_amount | decimal(15,2) | No | Amount subscribed this year |

**Form Input Mapping:**
- Account Name/Type field → `account_type`
- Institution field → `institution`
- Account Number field → `account_number`
- Balance field → `current_balance`
- Interest Rate field → `interest_rate` (stored as decimal, e.g., 3.5% = 0.0350)
- Access Type dropdown → `access_type`
- Notice Period field → `notice_period_days`
- Maturity Date picker → `maturity_date`
- Is ISA checkbox → `is_isa`
- Emergency Fund checkbox → `is_emergency_fund`
- Ownership Type dropdown → `ownership_type`

---

### savings_goals

Savings goal tracking.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| goal_name | varchar(255) | Yes | Goal name |
| target_amount | decimal(15,2) | Yes | Target amount |
| current_saved | decimal(15,2) | Yes | Amount saved so far |
| target_date | date | Yes | Target completion date |
| priority | enum | Yes | 'high', 'medium', 'low' |
| linked_account_id | bigint unsigned | No | Linked savings account |
| auto_transfer_amount | decimal(10,2) | No | Regular transfer amount |

---

### isa_allowance_tracking

Tracks ISA allowance usage across all ISA types.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| tax_year | varchar(255) | Yes | Tax year (e.g., '2024/25') |
| cash_isa_used | decimal(10,2) | Yes | Cash ISA subscriptions |
| stocks_shares_isa_used | decimal(10,2) | Yes | S&S ISA subscriptions |
| lisa_used | decimal(10,2) | Yes | LISA subscriptions |
| total_used | decimal(10,2) | Yes | Total ISA subscriptions |
| total_allowance | decimal(10,2) | Yes | Annual allowance (default 20000) |

---

### cash_accounts

Additional cash account tracking (current accounts, etc.).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| household_id | bigint unsigned | No | Household ID |
| trust_id | bigint unsigned | No | Trust ID |
| account_name | varchar(255) | Yes | Account name |
| institution_name | varchar(255) | Yes | Bank name |
| account_number | varchar(255) | No | Account number |
| sort_code | varchar(10) | No | Sort code |
| account_type | enum | Yes | 'current_account', 'savings_account', 'cash_isa', 'fixed_term_deposit', 'ns_and_i', 'other' |
| purpose | enum | No | 'emergency_fund', 'savings_goal', 'operating_cash', 'other' |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| country | varchar(255) | Yes | Country |
| ownership_percentage | decimal(5,2) | Yes | Ownership percentage |
| current_balance | decimal(15,2) | Yes | Current balance |
| interest_rate | decimal(5,4) | No | Interest rate |
| rate_valid_until | date | No | Rate expiry date |
| is_isa | tinyint(1) | Yes | ISA flag |
| isa_subscription_current_year | decimal(10,2) | Yes | This year's ISA contribution |
| tax_year | varchar(7) | No | Tax year |
| notes | text | No | Notes |

---

## Investment Module

### investment_accounts

Investment account records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner ID |
| household_id | bigint unsigned | No | Household ID |
| trust_id | bigint unsigned | No | Trust ID |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| ownership_percentage | decimal(5,2) | Yes | Ownership % |
| account_type | enum | Yes | 'isa', 'gia', 'nsi', 'onshore_bond', 'offshore_bond', 'vct', 'eis', 'other' |
| account_type_other | varchar(255) | No | Description if 'other' |
| country | varchar(255) | Yes | Country |
| provider | varchar(255) | Yes | Platform/provider |
| account_number | varchar(255) | No | Account number |
| platform | varchar(255) | No | Investment platform |
| current_value | decimal(15,2) | Yes | Current total value |
| contributions_ytd | decimal(15,2) | No | This year's contributions |
| tax_year | varchar(10) | Yes | Tax year |
| platform_fee_percent | decimal(5,4) | No | Platform fee (e.g., 0.0025 = 0.25%) |
| isa_type | varchar(50) | No | ISA type if applicable |
| isa_subscription_current_year | decimal(15,2) | No | ISA subscription this year |

**Form Input Mapping:**
- Account Type dropdown → `account_type`
- Provider field → `provider`
- Platform field → `platform`
- Account Number field → `account_number`
- Current Value field → `current_value`
- Platform Fee field → `platform_fee_percent` (stored as decimal)
- Ownership Type dropdown → `ownership_type`
- Joint Owner dropdown → `joint_owner_id`

---

### holdings

Individual investment holdings (polymorphic - can belong to investment accounts or DC pensions).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| holdable_id | bigint unsigned | Yes | Parent record ID |
| holdable_type | varchar(255) | Yes | 'App\Models\InvestmentAccount' or 'App\Models\DCPension' |
| asset_type | enum | Yes | 'equity', 'bond', 'fund', 'etf', 'alternative', 'uk_equity', 'us_equity', 'international_equity', 'cash', 'property' |
| allocation_percent | decimal(5,2) | No | Target allocation % |
| security_name | varchar(255) | Yes | Investment name |
| ticker | varchar(255) | No | Stock ticker |
| isin | varchar(255) | No | ISIN code |
| quantity | decimal(15,6) | No | Number of units |
| purchase_price | decimal(15,4) | No | Purchase price per unit |
| purchase_date | date | No | Purchase date |
| current_price | decimal(15,4) | No | Current price per unit |
| current_value | decimal(15,2) | Yes | Current total value |
| cost_basis | decimal(15,2) | No | Total cost basis |
| dividend_yield | decimal(5,4) | Yes | Dividend yield |
| ocf_percent | decimal(5,4) | Yes | Ongoing charges figure |

**Form Input Mapping:**
- Security Name field → `security_name`
- Asset Type dropdown → `asset_type`
- Ticker field → `ticker`
- ISIN field → `isin`
- Quantity field → `quantity`
- Purchase Price field → `purchase_price`
- Current Price field → `current_price`
- Current Value field → `current_value`
- Cost Basis field → `cost_basis`
- OCF field → `ocf_percent`

---

### investment_goals

Investment goal records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| goal_name | varchar(255) | Yes | Goal name |
| goal_type | enum | Yes | 'retirement', 'education', 'wealth', 'home' |
| target_amount | decimal(15,2) | Yes | Target amount |
| target_date | date | Yes | Target date |
| priority | enum | Yes | 'high', 'medium', 'low' |
| is_essential | tinyint(1) | Yes | Essential goal flag |
| linked_account_ids | json | No | Linked account IDs |

---

### risk_profiles

User investment risk profile.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| risk_tolerance | enum | Yes | 'cautious', 'balanced', 'adventurous' |
| capacity_for_loss_percent | decimal(5,2) | Yes | Maximum acceptable loss % |
| time_horizon_years | int | Yes | Investment time horizon |
| knowledge_level | enum | Yes | 'novice', 'intermediate', 'experienced' |
| attitude_to_volatility | varchar(255) | No | Volatility attitude |
| esg_preference | tinyint(1) | Yes | ESG/ethical preference |

---

### investment_plans

Generated investment plans.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| plan_version | varchar(20) | Yes | Plan version |
| plan_data | json | Yes | Full plan data |
| portfolio_health_score | int | Yes | Health score (0-100) |
| is_complete | tinyint(1) | Yes | Plan complete flag |
| completeness_score | int | No | Completeness % |
| generated_at | timestamp | Yes | Generation timestamp |

---

### investment_recommendations

Investment recommendations.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| investment_plan_id | bigint unsigned | No | Related plan ID |
| category | varchar(50) | Yes | Recommendation category |
| priority | int | Yes | Priority order |
| title | varchar(255) | Yes | Recommendation title |
| description | text | Yes | Full description |
| action_required | text | Yes | Required action |
| impact_level | varchar(20) | No | Impact level |
| potential_saving | decimal(10,2) | No | Potential savings |
| estimated_effort | varchar(20) | No | Effort required |
| status | varchar(20) | Yes | 'pending', etc. |
| due_date | date | No | Due date |
| completed_at | timestamp | No | Completion timestamp |
| dismissed_at | timestamp | No | Dismissal timestamp |
| dismissal_reason | text | No | Why dismissed |

---

### investment_scenarios

What-if scenario modeling.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| scenario_name | varchar(255) | Yes | Scenario name |
| description | text | No | Description |
| scenario_type | enum | Yes | 'custom', 'template', 'comparison' |
| template_name | varchar(255) | No | Template name if template |
| parameters | json | Yes | Scenario parameters |
| results | json | No | Calculation results |
| comparison_data | json | No | Comparison data |
| status | enum | Yes | 'draft', 'running', 'completed', 'failed' |
| is_saved | tinyint(1) | Yes | Saved scenario flag |
| monte_carlo_job_id | varchar(255) | No | Background job ID |
| completed_at | timestamp | No | Completion timestamp |

---

### portfolio_optimizations

Portfolio optimization results.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| optimization_date | date | Yes | Optimization date |
| optimization_type | varchar(255) | Yes | Type of optimization |
| current_allocation | json | Yes | Current allocation |
| optimal_allocation | json | Yes | Optimal allocation |
| rebalancing_actions | json | Yes | Required trades |
| constraints_used | json | Yes | Constraints applied |
| expected_return | decimal(6,4) | No | Expected return |
| expected_risk | decimal(6,4) | No | Expected risk |
| expected_sharpe | decimal(6,4) | No | Expected Sharpe ratio |
| improvement_vs_current | decimal(6,4) | No | Improvement % |
| status | varchar(255) | Yes | Status |
| executed_at | timestamp | No | Execution timestamp |

---

### efficient_frontier_calculations

Efficient frontier calculation results.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| calculation_date | date | Yes | Calculation date |
| holdings_snapshot | json | Yes | Holdings at time of calc |
| frontier_points | json | Yes | Frontier data points |
| tangency_portfolio | json | Yes | Tangency portfolio |
| min_variance_portfolio | json | Yes | Minimum variance portfolio |
| current_portfolio_position | json | Yes | Current portfolio position |
| risk_free_rate | decimal(5,4) | No | Risk-free rate used |

---

### risk_metrics

Portfolio risk metrics.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| calculation_date | date | Yes | Calculation date |
| portfolio_value | decimal(15,2) | Yes | Portfolio value |
| var_95_1month | decimal(15,2) | No | Value at Risk (95%, 1 month) |
| cvar_95_1month | decimal(15,2) | No | Conditional VaR |
| var_99_1month | decimal(15,2) | No | VaR (99%, 1 month) |
| cvar_99_1month | decimal(15,2) | No | CVaR (99%) |
| max_drawdown | decimal(5,2) | No | Maximum drawdown % |
| current_drawdown | decimal(5,2) | No | Current drawdown % |
| sharpe_ratio | decimal(6,4) | No | Sharpe ratio |
| sortino_ratio | decimal(6,4) | No | Sortino ratio |
| calmar_ratio | decimal(6,4) | No | Calmar ratio |
| information_ratio | decimal(6,4) | No | Information ratio |
| treynor_ratio | decimal(6,4) | No | Treynor ratio |

---

### factor_exposures

Factor exposure analysis.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| holding_id | bigint unsigned | No | Specific holding ID |
| analysis_date | date | Yes | Analysis date |
| market_beta | decimal(6,4) | No | Market beta |
| alpha | decimal(6,4) | No | Alpha |
| r_squared | decimal(5,4) | No | R-squared |
| value_factor | decimal(6,4) | No | Value factor exposure |
| size_factor | decimal(6,4) | No | Size factor exposure |
| momentum_factor | decimal(6,4) | No | Momentum factor |
| quality_factor | decimal(6,4) | No | Quality factor |
| low_vol_factor | decimal(6,4) | No | Low volatility factor |

---

### rebalancing_actions

Rebalancing trade recommendations.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| holding_id | bigint unsigned | No | Holding ID |
| investment_account_id | bigint unsigned | No | Account ID |
| action_type | enum | Yes | 'buy', 'sell' |
| security_name | varchar(255) | Yes | Security name |
| ticker | varchar(255) | No | Ticker |
| isin | varchar(255) | No | ISIN |
| shares_to_trade | decimal(15,6) | Yes | Shares to trade |
| trade_value | decimal(15,2) | Yes | Trade value |
| current_price | decimal(15,4) | Yes | Current price |
| current_holding | decimal(15,6) | Yes | Current holding |
| target_value | decimal(15,2) | Yes | Target value |
| target_weight | decimal(5,4) | Yes | Target weight |
| priority | int | Yes | Priority order |
| rationale | text | No | Why this trade |
| cgt_cost_basis | decimal(15,2) | No | CGT cost basis |
| cgt_gain_or_loss | decimal(15,2) | No | CGT gain/loss |
| cgt_liability | decimal(15,2) | No | CGT liability |
| status | enum | Yes | 'pending', 'executed', 'cancelled', 'expired' |
| executed_at | timestamp | No | Execution timestamp |
| executed_price | decimal(15,4) | No | Actual execution price |
| executed_shares | decimal(15,6) | No | Actual shares traded |
| notes | text | No | Notes |

---

## Retirement Module

### dc_pensions

Defined contribution pension records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| scheme_name | varchar(255) | No | Scheme name |
| scheme_type | enum | No | 'workplace', 'sipp', 'personal' (legacy) |
| provider | varchar(255) | No | Provider name |
| pension_type | enum | Yes | 'occupational', 'sipp', 'personal', 'stakeholder' |
| member_number | varchar(255) | No | Member/policy number |
| current_fund_value | decimal(15,2) | Yes | Current value |
| annual_salary | decimal(10,2) | No | Salary (for workplace pensions) |
| employee_contribution_percent | decimal(5,2) | No | Employee contribution % |
| employer_contribution_percent | decimal(5,2) | No | Employer contribution % |
| monthly_contribution_amount | decimal(10,2) | No | Monthly contribution (fixed) |
| lump_sum_contribution | decimal(15,2) | No | Lump sum contribution |
| investment_strategy | varchar(255) | No | Investment strategy |
| platform_fee_percent | decimal(5,4) | No | Platform fee |
| retirement_age | int | No | Target retirement age |
| expected_return_percent | decimal(5,2) | No | Expected return % |
| projected_value_at_retirement | decimal(15,2) | No | Projected value |

**Form Input Mapping:**
- Scheme Name field → `scheme_name`
- Pension Type dropdown → `pension_type`
- Provider field → `provider`
- Current Value field → `current_fund_value`
- Employee Contribution % field → `employee_contribution_percent`
- Employer Contribution % field → `employer_contribution_percent`
- Monthly Contribution field → `monthly_contribution_amount`
- Annual Salary field → `annual_salary`
- Retirement Age field → `retirement_age`

---

### db_pensions

Defined benefit pension records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| scheme_name | varchar(255) | Yes | Scheme name |
| scheme_type | enum | Yes | 'final_salary', 'career_average', 'public_sector' |
| accrued_annual_pension | decimal(10,2) | Yes | Annual pension accrued |
| pensionable_service_years | decimal(5,2) | No | Years of service |
| pensionable_salary | decimal(10,2) | No | Pensionable salary |
| normal_retirement_age | int | No | Normal retirement age |
| revaluation_method | varchar(255) | No | Revaluation method |
| spouse_pension_percent | decimal(5,2) | No | Spouse pension % |
| lump_sum_entitlement | decimal(15,2) | No | Lump sum option |
| inflation_protection | enum | Yes | 'cpi', 'rpi', 'fixed', 'none' |

**Form Input Mapping:**
- Scheme Name field → `scheme_name`
- Scheme Type dropdown → `scheme_type`
- Accrued Pension field → `accrued_annual_pension`
- Pensionable Salary field → `pensionable_salary`
- Service Years field → `pensionable_service_years`
- Normal Retirement Age field → `normal_retirement_age`
- Spouse Pension % field → `spouse_pension_percent`
- Inflation Protection dropdown → `inflation_protection`

---

### state_pensions

State pension records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| ni_years_completed | int | Yes | NI years completed |
| ni_years_required | int | Yes | Years required (default 35) |
| state_pension_forecast_annual | decimal(10,2) | No | Annual forecast amount |
| state_pension_age | int | No | State pension age |
| ni_gaps | json | No | NI contribution gaps |
| gap_fill_cost | decimal(10,2) | No | Cost to fill gaps |

**Form Input Mapping:**
- NI Years Completed field → `ni_years_completed`
- State Pension Age field → `state_pension_age`
- Annual Forecast field → `state_pension_forecast_annual`

---

### retirement_profiles

User retirement planning profile.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| current_age | int | Yes | Current age |
| target_retirement_age | int | Yes | Target retirement age |
| current_annual_salary | decimal(15,2) | No | Current salary |
| target_retirement_income | decimal(15,2) | No | Target income in retirement |
| essential_expenditure | decimal(10,2) | No | Essential monthly spending |
| lifestyle_expenditure | decimal(10,2) | No | Lifestyle spending |
| life_expectancy | int | No | Life expectancy assumption |
| spouse_life_expectancy | int | No | Spouse life expectancy |
| risk_tolerance | enum | Yes | 'cautious', 'balanced', 'adventurous' |

---

## Estate Planning Module

### iht_profiles

Inheritance Tax planning profile.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| marital_status | enum | Yes | 'single', 'married', 'widowed', 'divorced' |
| has_spouse | tinyint(1) | Yes | Has spouse flag |
| own_home | tinyint(1) | Yes | Owns main residence |
| home_value | decimal(15,2) | No | Main residence value |
| nrb_transferred_from_spouse | decimal(15,2) | Yes | NRB transferred from deceased spouse |
| charitable_giving_percent | decimal(5,2) | Yes | Charitable giving % (for reduced IHT rate) |

---

### iht_calculations

Cached IHT calculation results.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| user_gross_assets | decimal(15,2) | Yes | User's gross assets |
| spouse_gross_assets | decimal(15,2) | Yes | Spouse's gross assets |
| total_gross_assets | decimal(15,2) | Yes | Combined gross assets |
| user_total_liabilities | decimal(15,2) | Yes | User's liabilities |
| spouse_total_liabilities | decimal(15,2) | Yes | Spouse's liabilities |
| total_liabilities | decimal(15,2) | Yes | Combined liabilities |
| user_net_estate | decimal(15,2) | Yes | User's net estate |
| spouse_net_estate | decimal(15,2) | Yes | Spouse's net estate |
| total_net_estate | decimal(15,2) | Yes | Combined net estate |
| nrb_available | decimal(15,2) | Yes | NRB available |
| nrb_message | text | No | NRB explanation |
| rnrb_available | decimal(15,2) | Yes | RNRB available |
| rnrb_status | enum | Yes | 'full', 'tapered', 'none' |
| rnrb_message | text | No | RNRB explanation |
| total_allowances | decimal(15,2) | Yes | Total allowances |
| taxable_estate | decimal(15,2) | Yes | Taxable estate |
| iht_liability | decimal(15,2) | Yes | IHT liability |
| effective_rate | decimal(5,2) | Yes | Effective IHT rate |
| projected_gross_assets | decimal(15,2) | Yes | Projected assets |
| projected_liabilities | decimal(15,2) | Yes | Projected liabilities |
| projected_net_estate | decimal(15,2) | Yes | Projected net estate |
| projected_taxable_estate | decimal(15,2) | Yes | Projected taxable |
| projected_iht_liability | decimal(15,2) | Yes | Projected IHT |
| years_to_death | smallint unsigned | Yes | Years to life expectancy |
| estimated_age_at_death | tinyint unsigned | Yes | Life expectancy age |
| calculation_date | timestamp | Yes | Calculation timestamp |
| is_married | tinyint(1) | Yes | Married flag |
| data_sharing_enabled | tinyint(1) | Yes | Spouse data sharing |
| assets_hash | varchar(64) | No | Assets hash for caching |
| liabilities_hash | varchar(64) | No | Liabilities hash |

---

### assets

Estate planning asset records (for IHT calculations).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| asset_type | enum | Yes | 'property', 'pension', 'investment', 'business', 'other' |
| asset_name | varchar(255) | Yes | Asset name |
| current_value | decimal(15,2) | Yes | Current value |
| liquidity | enum | Yes | 'liquid', 'semi_liquid', 'illiquid' |
| is_giftable | tinyint(1) | Yes | Can be gifted |
| not_giftable_reason | varchar(255) | No | Why not giftable |
| is_main_residence | tinyint(1) | Yes | Main residence flag |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| beneficiary_designation | varchar(255) | No | Named beneficiary |
| is_iht_exempt | tinyint(1) | Yes | IHT exempt flag |
| exemption_reason | varchar(255) | No | Exemption reason |
| valuation_date | date | Yes | Valuation date |

---

### liabilities

Liability records (debts, loans, etc.).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| joint_owner_id | bigint unsigned | No | Joint owner ID |
| trust_id | bigint unsigned | No | Trust ID |
| liability_type | enum | Yes | 'mortgage', 'secured_loan', 'personal_loan', 'credit_card', 'overdraft', 'hire_purchase', 'student_loan', 'business_loan', 'other' |
| country | varchar(255) | Yes | Country |
| liability_name | varchar(255) | Yes | Liability description |
| current_balance | decimal(15,2) | Yes | Current balance |
| monthly_payment | decimal(10,2) | No | Monthly payment |
| interest_rate | decimal(5,2) | No | Interest rate % |
| maturity_date | date | No | End date |
| secured_against | varchar(255) | No | Security (if secured) |
| is_priority_debt | tinyint(1) | Yes | Priority debt flag |
| mortgage_type | varchar(50) | No | Mortgage type if mortgage |
| fixed_until | date | No | Fixed rate expiry |
| notes | text | No | Notes |

**Form Input Mapping:**
- Liability Type dropdown → `liability_type`
- Name/Description field → `liability_name`
- Current Balance field → `current_balance`
- Monthly Payment field → `monthly_payment`
- Interest Rate field → `interest_rate`
- Maturity Date picker → `maturity_date`
- Ownership Type dropdown → `ownership_type`

---

### wills

Will information.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| has_will | tinyint(1) | Yes | Has a will |
| death_scenario | enum | Yes | 'user_only', 'both_simultaneous' |
| spouse_primary_beneficiary | tinyint(1) | Yes | Spouse is primary beneficiary |
| spouse_bequest_percentage | decimal(5,2) | Yes | % left to spouse |
| executor_name | varchar(255) | No | Executor name |
| executor_notes | text | No | Executor notes |
| will_last_updated | date | No | Last update date |

**Form Input Mapping:**
- Has Will radio → `has_will`
- Death Scenario dropdown → `death_scenario`
- Spouse Bequest % slider → `spouse_bequest_percentage`
- Executor Name field → `executor_name`
- Will Last Updated picker → `will_last_updated`

---

### bequests

Specific bequests in will.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| will_id | bigint unsigned | Yes | Will ID |
| user_id | bigint unsigned | Yes | Owner user ID |
| beneficiary_name | varchar(255) | Yes | Beneficiary name |
| beneficiary_user_id | bigint unsigned | No | If beneficiary is a user |
| bequest_type | enum | Yes | 'percentage', 'specific_amount', 'specific_asset', 'residuary' |
| percentage_of_estate | decimal(5,2) | No | % of estate |
| specific_amount | decimal(15,2) | No | Fixed amount |
| specific_asset_description | varchar(255) | No | Asset description |
| asset_id | bigint unsigned | No | Asset ID if specific |
| priority_order | int | Yes | Order of precedence |
| conditions | text | No | Conditions attached |

---

### gifts

Gift records for IHT planning.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| gift_date | date | Yes | Date of gift |
| recipient | varchar(255) | Yes | Recipient name |
| gift_type | enum | Yes | 'pet', 'clt', 'exempt', 'small_gift', 'annual_exemption' |
| gift_value | decimal(15,2) | Yes | Gift value |
| status | enum | Yes | 'within_7_years', 'survived_7_years' |
| taper_relief_applicable | tinyint(1) | Yes | Taper relief applies |
| notes | text | No | Notes |

**Form Input Mapping:**
- Gift Date picker → `gift_date`
- Recipient field → `recipient`
- Gift Type dropdown → `gift_type`
- Gift Value field → `gift_value`
- Notes field → `notes`

---

### trusts

Trust records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| household_id | bigint unsigned | No | Household ID |
| trust_name | varchar(255) | Yes | Trust name |
| trust_type | enum | Yes | 'bare', 'interest_in_possession', 'discretionary', 'accumulation_maintenance', 'life_insurance', 'discounted_gift', 'loan', 'mixed', 'settlor_interested' |
| trust_creation_date | date | Yes | Creation date |
| initial_value | decimal(15,2) | Yes | Initial value |
| current_value | decimal(15,2) | Yes | Current value |
| last_valuation_date | date | No | Last valuation |
| discount_amount | decimal(15,2) | No | Discount (discounted gift trust) |
| retained_income_annual | decimal(15,2) | No | Annual retained income |
| loan_amount | decimal(15,2) | No | Loan amount (loan trust) |
| loan_interest_bearing | tinyint(1) | Yes | Loan bears interest |
| loan_interest_rate | decimal(5,4) | No | Loan interest rate |
| sum_assured | decimal(15,2) | No | Sum assured (life insurance trust) |
| annual_premium | decimal(15,2) | No | Annual premium |
| is_relevant_property_trust | tinyint(1) | Yes | Relevant property trust |
| last_periodic_charge_date | date | No | Last 10-year charge |
| last_periodic_charge_amount | decimal(15,2) | No | Last charge amount |
| next_tax_return_due | date | No | Tax return due date |
| total_asset_value | decimal(15,2) | No | Total assets in trust |
| beneficiaries | text | No | Beneficiary details |
| trustees | text | No | Trustee details |
| purpose | text | No | Trust purpose |
| notes | text | No | Notes |
| is_active | tinyint(1) | Yes | Trust is active |

**Form Input Mapping:**
- Trust Name field → `trust_name`
- Trust Type dropdown → `trust_type`
- Creation Date picker → `trust_creation_date`
- Initial Value field → `initial_value`
- Current Value field → `current_value`
- Beneficiaries field → `beneficiaries`
- Trustees field → `trustees`

---

## Net Worth & Properties

### properties

Property records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner ID |
| joint_owner_name | varchar(255) | No | Joint owner name |
| household_id | bigint unsigned | No | Household ID |
| trust_id | bigint unsigned | No | Trust ID |
| trust_name | varchar(255) | No | Trust name |
| property_type | varchar(255) | Yes | 'main_residence', 'secondary_residence', 'buy_to_let' |
| ownership_type | enum | No | 'individual', 'joint', 'tenants_in_common', 'trust' |
| joint_ownership_type | enum | No | 'joint_tenancy', 'tenants_in_common' |
| tenure_type | enum | Yes | 'freehold', 'leasehold' |
| lease_remaining_years | int unsigned | No | Leasehold years remaining |
| lease_expiry_date | date | No | Lease expiry date |
| country | varchar(255) | Yes | Country |
| ownership_percentage | decimal(5,2) | Yes | Ownership % |
| address_line_1 | varchar(255) | Yes | Street address |
| address_line_2 | varchar(255) | No | Address line 2 |
| city | varchar(255) | No | City |
| county | varchar(255) | No | County |
| postcode | varchar(10) | Yes | Postcode |
| purchase_date | date | No | Purchase date |
| purchase_price | decimal(15,2) | No | Purchase price |
| current_value | decimal(15,2) | Yes | Current value |
| valuation_date | date | No | Valuation date |
| sdlt_paid | decimal(15,2) | No | SDLT paid |
| monthly_rental_income | decimal(10,2) | No | Monthly rental (BTL) |
| outstanding_mortgage | decimal(15,2) | No | Outstanding mortgage |
| tenant_name | varchar(255) | No | Tenant name |
| tenant_email | varchar(255) | No | Tenant email |
| managing_agent_name | varchar(255) | No | Agent name |
| managing_agent_company | varchar(255) | No | Agent company |
| managing_agent_email | varchar(255) | No | Agent email |
| managing_agent_phone | varchar(255) | No | Agent phone |
| managing_agent_fee | decimal(10,2) | No | Management fee |
| lease_start_date | date | No | Lease start |
| lease_end_date | date | No | Lease end |

**Monthly Costs:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| monthly_council_tax | decimal(10,2) | No | Council tax |
| monthly_gas | decimal(10,2) | No | Gas |
| monthly_electricity | decimal(10,2) | No | Electricity |
| monthly_water | decimal(10,2) | No | Water |
| monthly_building_insurance | decimal(10,2) | No | Building insurance |
| monthly_contents_insurance | decimal(10,2) | No | Contents insurance |
| monthly_service_charge | decimal(10,2) | No | Service charge |
| monthly_maintenance_reserve | decimal(10,2) | No | Maintenance reserve |
| other_monthly_costs | decimal(10,2) | No | Other monthly costs |

**Annual Costs:**
| Column | Type | Required | Description |
|--------|------|----------|-------------|
| annual_service_charge | decimal(10,2) | No | Annual service charge |
| annual_ground_rent | decimal(10,2) | No | Ground rent |
| annual_insurance | decimal(10,2) | No | Annual insurance |
| annual_maintenance_reserve | decimal(10,2) | No | Annual maintenance |
| other_annual_costs | decimal(10,2) | No | Other annual costs |

**Form Input Mapping:**
- Property Type dropdown → `property_type`
- Address Line 1 field → `address_line_1`
- Address Line 2 field → `address_line_2`
- City field → `city`
- County field → `county`
- Postcode field → `postcode`
- Current Value field → `current_value`
- Purchase Price field → `purchase_price`
- Purchase Date picker → `purchase_date`
- Ownership Type dropdown → `ownership_type`
- Tenure Type dropdown → `tenure_type`
- Monthly Rental Income field → `monthly_rental_income`

---

### mortgages

Mortgage records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| property_id | bigint unsigned | Yes | Property ID |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner ID |
| joint_owner_name | varchar(255) | No | Joint owner name |
| country | varchar(255) | Yes | Country |
| lender_name | varchar(255) | No | Lender name |
| mortgage_account_number | varchar(255) | No | Account number |
| mortgage_type | enum | Yes | 'repayment', 'interest_only', 'mixed' |
| repayment_percentage | decimal(5,2) | No | Repayment % (if mixed) |
| interest_only_percentage | decimal(5,2) | No | Interest only % (if mixed) |
| original_loan_amount | decimal(15,2) | No | Original loan |
| outstanding_balance | decimal(15,2) | Yes | Current balance |
| interest_rate | decimal(5,2) | No | Interest rate |
| rate_type | enum | Yes | 'fixed', 'variable', 'tracker', 'discount', 'mixed' |
| fixed_rate_percentage | decimal(5,2) | No | Fixed % (if mixed rate) |
| variable_rate_percentage | decimal(5,2) | No | Variable % (if mixed rate) |
| fixed_interest_rate | decimal(5,4) | No | Fixed rate |
| variable_interest_rate | decimal(5,4) | No | Variable rate |
| rate_fix_end_date | date | No | Fixed rate end date |
| monthly_payment | decimal(10,2) | Yes | Monthly payment |
| start_date | date | No | Mortgage start date |
| maturity_date | date | No | Mortgage end date |
| remaining_term_months | int | Yes | Remaining term |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| notes | text | No | Notes |

**Form Input Mapping:**
- Lender Name field → `lender_name`
- Mortgage Type dropdown → `mortgage_type`
- Outstanding Balance field → `outstanding_balance`
- Interest Rate field → `interest_rate`
- Rate Type dropdown → `rate_type`
- Monthly Payment field → `monthly_payment`
- Start Date picker → `start_date`
- Maturity Date picker → `maturity_date`
- Remaining Term field → `remaining_term_months`

---

### business_interests

Business ownership records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner ID |
| household_id | bigint unsigned | No | Household ID |
| trust_id | bigint unsigned | No | Trust ID |
| business_name | varchar(255) | Yes | Business name |
| company_number | varchar(255) | No | Companies House number |
| business_type | enum | Yes | 'sole_trader', 'partnership', 'limited_company', 'llp', 'other' |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| ownership_percentage | decimal(5,2) | Yes | Ownership % |
| country | varchar(255) | Yes | Country |
| current_valuation | decimal(15,2) | Yes | Current value |
| valuation_date | date | Yes | Valuation date |
| valuation_method | varchar(255) | No | Valuation method |
| annual_revenue | decimal(15,2) | No | Annual revenue |
| annual_profit | decimal(15,2) | No | Annual profit |
| annual_dividend_income | decimal(15,2) | No | Dividend income |
| description | text | No | Description |
| notes | text | No | Notes |

---

### chattels

Chattel (valuable personal property) records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| joint_owner_id | bigint | No | Joint owner ID |
| household_id | bigint unsigned | No | Household ID |
| trust_id | bigint unsigned | No | Trust ID |
| chattel_type | enum | Yes | 'vehicle', 'art', 'antique', 'jewelry', 'collectible', 'other' |
| name | varchar(255) | Yes | Item name |
| description | text | No | Description |
| ownership_type | enum | Yes | 'individual', 'joint', 'trust' |
| country | varchar(255) | Yes | Country |
| ownership_percentage | decimal(5,2) | Yes | Ownership % |
| purchase_price | decimal(15,2) | No | Purchase price |
| purchase_date | date | No | Purchase date |
| current_value | decimal(15,2) | Yes | Current value |
| valuation_date | date | Yes | Valuation date |
| make | varchar(255) | No | Make (vehicles) |
| model | varchar(255) | No | Model (vehicles) |
| year | year | No | Year (vehicles) |
| registration_number | varchar(255) | No | Registration (vehicles) |
| notes | text | No | Notes |

---

### net_worth_statements

Historical net worth snapshots.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| statement_date | date | Yes | Statement date |
| total_assets | decimal(15,2) | Yes | Total assets |
| total_liabilities | decimal(15,2) | Yes | Total liabilities |
| net_worth | decimal(15,2) | Yes | Net worth |

---

### personal_accounts

Financial statement line items.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| account_type | enum | Yes | 'profit_and_loss', 'cashflow', 'balance_sheet' |
| period_start | date | Yes | Period start |
| period_end | date | Yes | Period end |
| line_item | varchar(255) | Yes | Line item description |
| category | enum | No | 'income', 'expense', 'asset', 'liability', 'equity', 'cash_inflow', 'cash_outflow' |
| amount | decimal(15,2) | Yes | Amount |
| notes | text | No | Notes |

---

### expenditure_profiles

Legacy expenditure profile (separate from users table).

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| monthly_housing | decimal(10,2) | Yes | Housing costs |
| monthly_utilities | decimal(10,2) | Yes | Utilities |
| monthly_food | decimal(10,2) | Yes | Food |
| monthly_transport | decimal(10,2) | Yes | Transport |
| monthly_insurance | decimal(10,2) | Yes | Insurance |
| monthly_loans | decimal(10,2) | Yes | Loan payments |
| monthly_discretionary | decimal(10,2) | Yes | Discretionary |
| total_monthly_expenditure | decimal(10,2) | Yes | Total monthly |

---

## Tax Configuration

### tax_configurations

UK tax rates and allowances by tax year.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| tax_year | varchar(10) | Yes | Tax year (e.g., '2024/25') |
| effective_from | date | Yes | Effective from date |
| effective_to | date | Yes | Effective to date |
| config_data | json | Yes | Full tax configuration |
| is_active | tinyint(1) | Yes | Active tax year flag |
| notes | text | No | Notes |

**config_data JSON structure includes:**
- `income_tax` - Personal allowance, bands, rates
- `national_insurance` - Thresholds and rates
- `capital_gains_tax` - Annual exemption, rates
- `dividend_tax` - Allowance and rates
- `isa_allowances` - Annual limits
- `pension_allowances` - Annual allowance, MPAA, taper
- `inheritance_tax` - NRB, RNRB, rates, gifting rules
- `stamp_duty` - SDLT bands and rates

---

### actuarial_life_tables

Life expectancy data for IHT projections.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| age | tinyint unsigned | Yes | Age |
| gender | enum | Yes | 'male', 'female' |
| life_expectancy_years | decimal(4,2) | Yes | Expected remaining years |
| probability_of_death | decimal(6,5) | Yes | 1-year death probability |
| table_year | varchar(10) | Yes | Table year |
| table_source | varchar(100) | Yes | Source (default: UK ONS) |

---

### uk_life_expectancy_tables

Alternative life expectancy data.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| age | int | Yes | Age |
| gender | enum | Yes | 'male', 'female' |
| life_expectancy_years | decimal(5,2) | Yes | Life expectancy |
| table_version | varchar(255) | Yes | Table version |
| data_year | year | Yes | Data year |

---

## Document Upload

### documents

Uploaded document records.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | Owner user ID |
| original_filename | varchar(255) | Yes | Original filename |
| stored_filename | varchar(255) | Yes | Storage filename |
| disk | varchar(255) | Yes | Storage disk |
| path | varchar(255) | Yes | File path |
| mime_type | varchar(255) | Yes | MIME type |
| file_size | bigint unsigned | Yes | File size in bytes |
| document_type | enum | Yes | 'pension_statement', 'insurance_policy', 'investment_statement', 'mortgage_statement', 'savings_statement', 'property_document', 'unknown' |
| detected_document_subtype | varchar(255) | No | AI-detected subtype |
| detection_confidence | decimal(5,4) | No | Detection confidence |
| status | enum | Yes | 'uploaded', 'processing', 'extracted', 'review_pending', 'confirmed', 'failed', 'archived' |
| error_message | text | No | Error message if failed |
| processed_at | timestamp | No | Processing timestamp |
| confirmed_at | timestamp | No | Confirmation timestamp |
| deleted_at | timestamp | No | Soft delete timestamp |

---

### document_extractions

AI extraction results.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| document_id | bigint unsigned | Yes | Document ID |
| extraction_version | int | Yes | Version number |
| model_used | varchar(255) | Yes | AI model used |
| input_tokens | int | No | Input token count |
| output_tokens | int | No | Output token count |
| raw_response | longtext | Yes | Raw AI response |
| extracted_fields | json | Yes | Extracted field values |
| field_confidence | json | Yes | Confidence per field |
| warnings | json | No | Extraction warnings |
| target_model | varchar(255) | No | Target model class |
| target_model_id | bigint unsigned | No | Target record ID |
| is_valid | tinyint(1) | Yes | Validation passed |
| validation_errors | json | No | Validation errors |

---

### document_extraction_logs

Audit log for document operations.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| document_id | bigint unsigned | Yes | Document ID |
| user_id | bigint unsigned | Yes | User ID |
| action | enum | Yes | 'uploaded', 'extraction_started', 'extraction_completed', 'extraction_failed', 'fields_modified', 'confirmed', 'saved_to_model', 'deleted' |
| metadata | json | No | Action metadata |
| ip_address | varchar(45) | No | User IP address |
| user_agent | varchar(255) | No | User agent string |

---

## System Tables

### jobs

Laravel queue jobs.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| queue | varchar(255) | Yes | Queue name |
| payload | longtext | Yes | Job payload |
| attempts | tinyint unsigned | Yes | Attempt count |
| reserved_at | int unsigned | No | Reservation timestamp |
| available_at | int unsigned | Yes | Available timestamp |
| created_at | int unsigned | Yes | Creation timestamp |

---

### failed_jobs

Failed queue jobs.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| uuid | varchar(255) | Yes | Unique ID |
| connection | text | Yes | Connection name |
| queue | text | Yes | Queue name |
| payload | longtext | Yes | Job payload |
| exception | longtext | Yes | Exception details |
| failed_at | timestamp | Yes | Failure timestamp |

---

### migrations

Database migration history.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | int unsigned | Yes | Primary key |
| migration | varchar(255) | Yes | Migration filename |
| batch | int | Yes | Batch number |

---

### onboarding_progress

Tracks user progress through onboarding wizard.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | User ID |
| focus_area | enum | Yes | 'estate', 'protection', 'retirement', 'investment', 'tax_optimisation' |
| step_name | varchar(255) | Yes | Step identifier |
| step_data | json | No | Step data entered |
| completed | tinyint(1) | Yes | Step completed |
| skipped | tinyint(1) | Yes | Step skipped |
| skip_reason_shown | tinyint(1) | Yes | Skip reason shown |
| completed_at | timestamp | No | Completion timestamp |

---

### recommendation_tracking

Tracks financial recommendations and their status.

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| id | bigint unsigned | Yes | Primary key |
| user_id | bigint unsigned | Yes | User ID |
| recommendation_id | varchar(255) | Yes | Unique recommendation ID |
| module | varchar(255) | Yes | Module (protection, savings, etc.) |
| recommendation_text | text | Yes | Recommendation text |
| priority_score | decimal(5,2) | Yes | Priority (0-100) |
| timeline | enum | Yes | 'immediate', 'short_term', 'medium_term', 'long_term' |
| status | enum | Yes | 'pending', 'in_progress', 'completed', 'dismissed' |
| completed_at | timestamp | No | Completion timestamp |
| notes | text | No | Notes |

---

## Form Input Mappings

### Registration Form
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Name | name | users |
| Email | email | users |
| Password | password | users |

### Personal Information Form
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Full Name | name | users |
| Date of Birth | date_of_birth | users |
| Gender | gender | users |
| Marital Status | marital_status | users |
| National Insurance Number | national_insurance_number | users |
| Phone | phone | users |
| Address Line 1 | address_line_1 | users |
| Address Line 2 | address_line_2 | users |
| City | city | users |
| County | county | users |
| Postcode | postcode | users |

### Domicile Information Form
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Domicile Status | domicile_status | users |
| Country of Birth | country_of_birth | users |
| UK Arrival Date | uk_arrival_date | users |
| Years UK Resident | years_uk_resident | users |
| Deemed Domicile Date | deemed_domicile_date | users |

### Health Information Form
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Health Status | health_status | users |
| Smoking Status | smoking_status | users |

### Income Form
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Employment Income | annual_employment_income | users |
| Self-Employment Income | annual_self_employment_income | users |
| Rental Income | annual_rental_income | users |
| Dividend Income | annual_dividend_income | users |
| Interest Income | annual_interest_income | users |
| Other Income | annual_other_income | users |
| Occupation | occupation | users |
| Employer | employer | users |
| Industry | industry | users |
| Employment Status | employment_status | users |

### Expenditure Form (Category Mode)
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Food & Groceries | food_groceries | users |
| Transport & Fuel | transport_fuel | users |
| Healthcare | healthcare_medical | users |
| Insurance | insurance | users |
| Mobile Phones | mobile_phones | users |
| Internet & TV | internet_tv | users |
| Subscriptions | subscriptions | users |
| Clothing | clothing_personal_care | users |
| Entertainment | entertainment_dining | users |
| Holidays | holidays_travel | users |
| Pets | pets | users |
| Childcare | childcare | users |
| School Fees | school_fees | users |
| School Lunches | school_lunches | users |
| School Extras | school_extras | users |
| University Fees | university_fees | users |
| Children Activities | children_activities | users |
| Gifts & Charity | gifts_charity | users |
| Regular Savings | regular_savings | users |
| Other | other_expenditure | users |

### Expenditure Form (Simple Mode)
| Form Field | Database Column | Table |
|------------|-----------------|-------|
| Monthly Total | monthly_expenditure | users |
| Annual Total | annual_expenditure | users |

---

## Enum Value Reference

### Ownership Types (All Modules)
- `individual` - Sole ownership
- `joint` - Joint ownership (50/50)
- `tenants_in_common` - Ownership by percentage
- `trust` - Held in trust

### Property Types
- `main_residence` - Main home
- `secondary_residence` - Second home
- `buy_to_let` - Investment property

### Liability Types
- `mortgage` - Property mortgage
- `secured_loan` - Secured loan
- `personal_loan` - Personal loan
- `credit_card` - Credit card debt
- `overdraft` - Bank overdraft
- `hire_purchase` - HP/car finance
- `student_loan` - Student loan
- `business_loan` - Business loan
- `other` - Other liability

### Investment Account Types
- `isa` - Stocks & Shares ISA
- `gia` - General Investment Account
- `nsi` - NS&I
- `onshore_bond` - Onshore Bond
- `offshore_bond` - Offshore Bond
- `vct` - Venture Capital Trust
- `eis` - Enterprise Investment Scheme
- `other` - Other

### Pension Types (DC)
- `occupational` - Workplace pension
- `sipp` - Self-Invested Personal Pension
- `personal` - Personal pension
- `stakeholder` - Stakeholder pension

### Pension Types (DB)
- `final_salary` - Final salary scheme
- `career_average` - Career average (CARE)
- `public_sector` - Public sector scheme

### Life Policy Types
- `term` - Term insurance
- `level_term` - Level term
- `decreasing_term` - Decreasing term
- `whole_of_life` - Whole of life
- `family_income_benefit` - Family income benefit

### Trust Types
- `bare` - Bare trust
- `interest_in_possession` - Interest in possession
- `discretionary` - Discretionary trust
- `accumulation_maintenance` - Accumulation & maintenance
- `life_insurance` - Life insurance trust
- `discounted_gift` - Discounted gift trust
- `loan` - Loan trust
- `mixed` - Mixed trust
- `settlor_interested` - Settlor-interested trust

### Gift Types
- `pet` - Potentially Exempt Transfer
- `clt` - Chargeable Lifetime Transfer
- `exempt` - Exempt gift
- `small_gift` - Small gift exemption
- `annual_exemption` - Annual exemption

---

**Document Version**: 1.0
**Generated**: December 10, 2025
**Total Tables**: 60
