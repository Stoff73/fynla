# 07 - Validation Rules

Fynla v0.7.0 uses Laravel Form Request classes to validate all inbound API data before it reaches controllers or services. Each Form Request class extends `Illuminate\Foundation\Http\FormRequest` and defines its rules in a `rules()` method. Failed validation returns a 422 response with field-level error messages.

This chapter documents every Form Request class, grouped by module. Each table lists the field name, its expected type, and the full set of validation rules applied.

---

## Authentication

### LoginRequest

Validates credentials submitted to the login endpoint.

| Field | Type | Rules |
|-------|------|-------|
| email | string | required, string, email |
| password | string | required, string, min:8 |

### RegisterRequest

Validates new user registration. The password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.

| Field | Type | Rules |
|-------|------|-------|
| first_name | string | required, string, max:255 |
| middle_name | string | nullable, string, max:255 |
| surname | string | required, string, max:255 |
| email | string | required, string, email, max:255, unique:users |
| password | string | required, string, min:8, confirmed, regex (uppercase + lowercase + digit + special character) |

---

## User Profile

### UpdatePersonalInfoRequest

Updates the authenticated user's personal details. The email uniqueness check excludes the current user's own record.

| Field | Type | Rules |
|-------|------|-------|
| first_name | string | required, string, max:255 |
| last_name | string | required, string, max:255 |
| email | string | required, email, unique:users (except own record) |
| phone_number | string | nullable, string |
| date_of_birth | date | nullable, date, before:today |
| gender | string | nullable, in:male,female,other |
| marital_status | string | nullable, in:single,married,divorced,widowed,civil_partnership |
| nationality | string | nullable, string |

### UpdateIncomeOccupationRequest

Updates employment status and income breakdown across all income categories.

| Field | Type | Rules |
|-------|------|-------|
| annual_employment_income | numeric | nullable, numeric, min:0 |
| annual_self_employment_income | numeric | nullable, numeric, min:0 |
| annual_rental_income | numeric | nullable, numeric, min:0 |
| annual_dividend_income | numeric | nullable, numeric, min:0 |
| annual_interest_income | numeric | nullable, numeric, min:0 |
| annual_other_income | numeric | nullable, numeric, min:0 |
| occupation | string | nullable, string, max:255 |
| employment_status | string | nullable, in:employed,self_employed,retired,student,unemployed |

### UpdateExpenditureRequest

Updates the user's monthly spending. Accepts both a total figure and itemised category breakdowns (housing, food, utilities, transport, childcare, and others).

| Field | Type | Rules |
|-------|------|-------|
| total_monthly_expenditure | numeric | nullable, numeric, min:0 |
| housing | numeric | nullable, numeric, min:0 |
| food | numeric | nullable, numeric, min:0 |
| utilities | numeric | nullable, numeric, min:0 |
| transport | numeric | nullable, numeric, min:0 |
| childcare | numeric | nullable, numeric, min:0 |
| (other categories) | numeric | nullable, numeric, min:0 |

### UpdateDomicileInfoRequest

Updates domicile and tax residency information for UK tax treatment calculations.

| Field | Type | Rules |
|-------|------|-------|
| domicile_status | string | nullable, in:uk_domiciled,non_uk_domiciled,deemed_uk_domiciled |
| country_of_residence | string | nullable, string |
| tax_residency_status | string | nullable, in:resident,non_resident,split_year |

---

## Property

### StorePropertyRequest

Creates or updates a property record. Covers address, valuation, mortgage summary, rental income, and monthly running costs. Uses the canonical ownership and property type enums.

| Field | Type | Rules |
|-------|------|-------|
| property_type | string | nullable, in:main_residence,secondary_residence,buy_to_let |
| ownership_type | string | nullable, in:individual,joint,tenants_in_common,trust |
| ownership_percentage | numeric | nullable, numeric, between:0,100 |
| joint_owner_id | integer | nullable, exists:users,id |
| tenure_type | string | nullable, in:freehold,leasehold |
| lease_remaining_years | integer | nullable, integer, between:0,999 |
| address_line_1 | string | nullable, string, max:255 |
| address_line_2 | string | nullable, string, max:255 |
| city | string | nullable, string, max:255 |
| county | string | nullable, string, max:255 |
| postcode | string | nullable, string, max:255 |
| purchase_date | date | nullable, date |
| purchase_price | numeric | nullable, numeric, min:0 |
| current_value | numeric | nullable, numeric, min:0 |
| valuation_date | date | nullable, date |
| sdlt_paid | numeric | nullable, numeric, min:0 |
| outstanding_mortgage | numeric | nullable, numeric, min:0 |
| mortgage_lender_name | string | nullable, string, max:255 |
| mortgage_type | string | nullable, in:repayment,interest_only,mixed |
| mortgage_monthly_payment | numeric | nullable, numeric, min:0 |
| mortgage_interest_rate | numeric | nullable, numeric, between:0,100 |
| mortgage_rate_type | string | nullable, in:fixed,variable,tracker,discount,mixed |
| mortgage_start_date | date | nullable, date |
| mortgage_maturity_date | date | nullable, date |
| rental_income | numeric | nullable, numeric, min:0 |
| monthly_rental_income | numeric | nullable, numeric, min:0 |
| managing_agent_fee | numeric | nullable, numeric, min:0 |
| council_tax | numeric | nullable, numeric, min:0 |
| gas | numeric | nullable, numeric, min:0 |
| electricity | numeric | nullable, numeric, min:0 |
| water | numeric | nullable, numeric, min:0 |
| building_insurance | numeric | nullable, numeric, min:0 |
| contents_insurance | numeric | nullable, numeric, min:0 |
| service_charge | numeric | nullable, numeric, min:0 |
| maintenance_reserve | numeric | nullable, numeric, min:0 |

### StoreMortgageRequest

Creates a standalone mortgage record linked to a property. The maturity date must fall after the start date.

| Field | Type | Rules |
|-------|------|-------|
| property_id | integer | required, exists:properties,id |
| lender_name | string | required, string, max:255 |
| mortgage_type | string | required, in:repayment,interest_only,mixed |
| original_loan_amount | numeric | required, numeric, min:0 |
| outstanding_balance | numeric | required, numeric, min:0 |
| interest_rate | numeric | required, numeric, between:0,100 |
| rate_type | string | nullable, in:fixed,variable,tracker |
| start_date | date | required, date |
| maturity_date | date | required, date, after:start_date |
| monthly_payment | numeric | nullable, numeric, min:0 |

---

## Business Interests

### StoreBusinessInterestRequest

Creates or updates a business interest record. Covers ownership stake, valuation, and exit planning.

| Field | Type | Rules |
|-------|------|-------|
| business_name | string | required, string, max:255 |
| business_type | string | nullable, in:sole_trader,partnership,limited_company,other |
| ownership_stake_percent | numeric | nullable, numeric, between:0,100 |
| current_valuation | numeric | nullable, numeric, min:0 |
| valuation_date | date | nullable, date |
| annual_turnover | numeric | nullable, numeric, min:0 |
| profit_after_tax | numeric | nullable, numeric |
| dividend_distribution | string | nullable, in:yes,no |
| exit_plan | string | nullable, in:none,soon,eventually,not_planning |

---

## Chattels

### StoreChattelRequest

Creates a chattel (tangible personal property) record. Used for jewellery, art, vehicles, and collectibles tracked for estate and insurance purposes.

| Field | Type | Rules |
|-------|------|-------|
| item_description | string | required, string, max:255 |
| item_category | string | nullable, in:jewellery,art,vehicles,collectibles,other |
| current_value | numeric | required, numeric, min:0 |
| valuation_date | date | nullable, date |
| insurance_value | numeric | nullable, numeric, min:0 |
| acquisition_date | date | nullable, date |
| acquisition_cost | numeric | nullable, numeric, min:0 |

---

## Family Members

### StoreFamilyMemberRequest

Adds a family member to the user's household. Used by Protection and Estate modules to determine dependants and beneficiaries.

| Field | Type | Rules |
|-------|------|-------|
| relationship | string | required, in:child,parent,sibling,grandchild,other |
| first_name | string | required, string, max:255 |
| last_name | string | required, string, max:255 |
| date_of_birth | date | nullable, date |
| gender | string | nullable, in:male,female,other |

---

## Protection

### StoreProtectionProfileRequest

Stores the user's protection needs assessment profile. Drives gap analysis calculations.

| Field | Type | Rules |
|-------|------|-------|
| has_dependents | boolean | nullable, boolean |
| number_of_dependents | integer | nullable, integer, min:0 |
| total_liabilities | numeric | nullable, numeric, min:0 |
| annual_income_replacement_percent | numeric | nullable, numeric, between:0,100 |

### StoreLifePolicyRequest

Creates a life insurance policy record. Supports term, whole of life, decreasing term, family income benefit, and level term variants. The indexation rate is expressed as a decimal (e.g. 0.03 for 3%).

| Field | Type | Rules |
|-------|------|-------|
| policy_type | string | nullable, in:term,whole_of_life,decreasing_term,family_income_benefit,level_term |
| provider | string | nullable, string, max:255 |
| policy_number | string | nullable, string, max:255 |
| sum_assured | numeric | nullable, numeric, min:0 |
| premium_amount | numeric | nullable, numeric, min:0 |
| premium_frequency | string | nullable, in:monthly,quarterly,annually |
| policy_start_date | date | nullable, date, before_or_equal:today |
| policy_end_date | date | nullable, date, after:today |
| policy_term_years | integer | nullable, integer, between:1,50 |
| in_trust | boolean | nullable, boolean |
| is_mortgage_protection | boolean | nullable, boolean |
| indexation_rate | numeric | nullable, numeric, between:0,0.10 |

### StoreCriticalIllnessPolicyRequest

Creates a critical illness cover record. Follows the same field patterns as life policies.

| Field | Type | Rules |
|-------|------|-------|
| provider | string | nullable, string, max:255 |
| policy_number | string | nullable, string, max:255 |
| sum_assured | numeric | nullable, numeric, min:0 |
| premium_amount | numeric | nullable, numeric, min:0 |
| premium_frequency | string | nullable, in:monthly,quarterly,annually |
| policy_end_date | date | nullable, date, after:today |
| policy_term_years | integer | nullable, integer, between:1,50 |

### StoreIncomeProtectionPolicyRequest

Creates an income protection policy record. Includes deferred period and benefit amount fields specific to income replacement cover.

| Field | Type | Rules |
|-------|------|-------|
| provider | string | nullable, string, max:255 |
| policy_number | string | nullable, string, max:255 |
| monthly_benefit_amount | numeric | nullable, numeric, min:0 |
| deferred_period_days | integer | nullable, integer |
| coverage_percent | numeric | nullable, numeric, between:0,100 |
| premium_amount | numeric | nullable, numeric, min:0 |
| premium_frequency | string | nullable, in:monthly,quarterly,annually |
| policy_end_date | date | nullable, date, after:today |

### StoreDisabilityPolicyRequest

Creates a disability cover record. Shares the income protection structure with benefit amount and deferred period fields.

| Field | Type | Rules |
|-------|------|-------|
| provider | string | nullable, string, max:255 |
| policy_number | string | nullable, string, max:255 |
| monthly_benefit_amount | numeric | nullable, numeric, min:0 |
| deferred_period_days | integer | nullable, integer |
| coverage_percent | numeric | nullable, numeric, between:0,100 |
| premium_amount | numeric | nullable, numeric, min:0 |
| premium_frequency | string | nullable, in:monthly,quarterly,annually |
| policy_end_date | date | nullable, date, after:today |

### StoreSicknessIllnessPolicyRequest

Creates a sickness and illness cover record. Follows the same structure as disability and income protection policies.

| Field | Type | Rules |
|-------|------|-------|
| provider | string | nullable, string, max:255 |
| policy_number | string | nullable, string, max:255 |
| monthly_benefit_amount | numeric | nullable, numeric, min:0 |
| deferred_period_days | integer | nullable, integer |
| coverage_percent | numeric | nullable, numeric, between:0,100 |
| premium_amount | numeric | nullable, numeric, min:0 |
| premium_frequency | string | nullable, in:monthly,quarterly,annually |
| policy_end_date | date | nullable, date, after:today |

---

## Savings

### StoreSavingsAccountRequest

Creates a savings account record. Covers cash savings, notice accounts, premium bonds, cash ISAs, and Lifetime ISAs. The interest rate cap of 10% prevents data entry errors.

| Field | Type | Rules |
|-------|------|-------|
| account_type | string | required, in:cash_savings,notice,premium_bonds,cash_isa,lifetime_isa |
| account_name | string | nullable, string, max:255 |
| provider | string | nullable, string, max:255 |
| current_balance | numeric | required, numeric, min:0 |
| interest_rate | numeric | nullable, numeric, between:0,10 |
| is_isa | boolean | nullable, boolean |
| isa_type | string | nullable, in:cash,stocks_shares,LISA,innovative_finance |
| isa_subscription_year | string | nullable, string |
| isa_subscription_amount | numeric | nullable, numeric, min:0 |
| opening_date | date | nullable, date |
| notice_period_days | integer | nullable, integer |

### StoreSavingsGoalRequest

Creates a savings goal with a target amount and date. The target date must be in the future.

| Field | Type | Rules |
|-------|------|-------|
| goal_name | string | required, string, max:255 |
| target_amount | numeric | required, numeric, min:0 |
| current_amount | numeric | nullable, numeric, min:0 |
| target_date | date | required, date, after:today |
| priority | string | nullable, in:low,medium,high |
| purpose | string | nullable, string, max:1000 |

---

## Investment

### StoreInvestmentAccountRequest

Creates an investment account (wrapper) record. Supports stocks and shares ISAs, general investment accounts, trading accounts, and SIPPs. The platform fee is capped at 2% to catch entry errors.

| Field | Type | Rules |
|-------|------|-------|
| account_type | string | required, in:stocks_and_shares_isa,general_investment_account,trading_account,sipp |
| account_name | string | nullable, string, max:255 |
| provider | string | nullable, string, max:255 |
| current_value | numeric | required, numeric, min:0 |
| platform_fee_percent | numeric | nullable, numeric, between:0,2 |
| tax_year | string | nullable, string |
| isa_subscription_current_year | numeric | nullable, numeric, min:0 |
| opening_date | date | nullable, date |

### StoreHoldingRequest

Creates a holding within an investment account. The investment account must already exist. All monetary fields use min:0 to prevent negative values.

| Field | Type | Rules |
|-------|------|-------|
| investment_account_id | integer | required, exists:investment_accounts,id |
| security_name | string | required, string, max:255 |
| isin | string | nullable, string, max:12 |
| security_type | string | nullable, in:equity,bond,fund,cash,other |
| quantity | numeric | required, numeric, min:0 |
| cost_per_unit | numeric | nullable, numeric, min:0 |
| current_price | numeric | required, numeric, min:0 |
| current_value | numeric | required, numeric, min:0 |
| date_purchased | date | required, date |
| acquisition_cost | numeric | nullable, numeric, min:0 |

### StoreRiskProfileRequest

Stores the user's investment risk profile. The five levels range from very cautious (level_1) to adventurous (level_5).

| Field | Type | Rules |
|-------|------|-------|
| risk_level | string | required, in:level_1,level_2,level_3,level_4,level_5 |
| justification | string | nullable, string, max:1000 |

### StartMonteCarloRequest

Initiates a Monte Carlo simulation for portfolio projection. The confidence level determines the percentile reported (e.g. 0.95 for 95th percentile).

| Field | Type | Rules |
|-------|------|-------|
| years | integer | required, integer, between:1,40 |
| iterations | integer | nullable, integer, between:100,10000 (default:1000) |
| include_spouse | boolean | nullable, boolean |
| confidence_level | numeric | nullable, numeric, between:0.5,0.99 |

---

## Retirement

### StoreDCPensionRequest

Creates a defined contribution pension record. Covers workplace, SIPP, and personal pension schemes. Contribution fields accept percentages of salary or fixed monthly amounts. The platform fee cap of 10% accommodates older legacy schemes.

| Field | Type | Rules |
|-------|------|-------|
| scheme_name | string | nullable, string, max:255 |
| scheme_type | string | nullable, in:workplace,sipp,personal |
| pension_type | string | nullable, in:occupational,sipp,personal,stakeholder |
| provider | string | nullable, string, max:255 |
| member_number | string | nullable, string, max:255 |
| current_fund_value | numeric | nullable, numeric, min:0 |
| annual_salary | numeric | nullable, numeric, min:0 |
| employee_contribution_percent | numeric | nullable, numeric, between:0,100 |
| employer_contribution_percent | numeric | nullable, numeric, between:0,100 |
| monthly_contribution_amount | numeric | nullable, numeric, min:0 |
| platform_fee_percent | numeric | nullable, numeric, between:0,10 |
| retirement_age | integer | nullable, integer, between:55,75 |
| projected_value_at_retirement | numeric | nullable, numeric, min:0 |

### StoreDBPensionRequest

Creates a defined benefit (final salary or CARE) pension record. Requires scheme name, provider, and annual pension amount. The escalation rate determines annual increases to the pension in payment.

| Field | Type | Rules |
|-------|------|-------|
| scheme_name | string | required, string, max:255 |
| provider | string | required, string, max:255 |
| member_number | string | nullable, string, max:255 |
| annual_pension_amount | numeric | required, numeric, min:0 |
| pension_start_date | date | required, date |
| escalation_rate | numeric | nullable, numeric, between:0,10 |
| lump_sum_available | numeric | nullable, numeric, min:0 |
| death_benefits_spouse_percent | numeric | nullable, numeric, between:0,100 |
| cpi_linked | boolean | nullable, boolean |

### UpdateStatePensionRequest

Updates the user's State Pension forecast. Both fields are required because the State Pension amount and receipt age drive retirement income projections.

| Field | Type | Rules |
|-------|------|-------|
| estimated_annual_amount | numeric | required, numeric, min:0 |
| expected_age_at_receipt | integer | required, integer, between:55,75 |

---

## Estate

### StoreWillRequest

Creates or updates the user's will record. The executor name is required; solicitor details are optional.

| Field | Type | Rules |
|-------|------|-------|
| will_date | date | nullable, date |
| executor_name | string | required, string, max:255 |
| executor_email | string | nullable, email |
| solicitor_name | string | nullable, string, max:255 |
| solicitor_email | string | nullable, email |

### StoreBequestRequest

Creates a bequest (gift in a will). The `value_or_percentage` field holds either a fixed amount or a percentage of the estate, determined by the `is_percentage` flag.

| Field | Type | Rules |
|-------|------|-------|
| beneficiary_name | string | required, string, max:255 |
| asset_description | string | nullable, string, max:1000 |
| value_or_percentage | numeric | required, numeric |
| is_percentage | boolean | nullable, boolean |

### CalculateIntestacyRequest

Provides inputs for the intestacy distribution calculator. Determines how an estate would be divided under the UK intestacy rules if no valid will exists.

| Field | Type | Rules |
|-------|------|-------|
| marital_status | string | required, in:married,unmarried,widowed,divorced |
| number_of_children | integer | required, integer, min:0 |
| estate_value | numeric | required, numeric, min:0 |

---

## Goals

### StoreGoalRequest

Creates a financial goal. Supports education, property purchase, retirement, and other goal types. The risk level determines the assumed growth rate used in projections to the target date.

| Field | Type | Rules |
|-------|------|-------|
| goal_type | string | required, in:education,property_purchase,retirement,holiday,car,wedding,emergency_fund,debt_repayment,starting_business,other |
| goal_name | string | required, string, max:255 |
| target_amount | numeric | required, numeric, min:0 |
| current_amount | numeric | nullable, numeric, min:0 |
| target_date | date | required, date, after:today |
| priority | string | nullable, in:low,medium,high,critical |
| description | string | nullable, string, max:1000 |
| risk_level | string | nullable, in:very_conservative,conservative,moderate,growth,aggressive |
| inflation_adjustment | boolean | nullable, boolean |
| annual_contribution | numeric | nullable, numeric, min:0 |

---

## Life Events

### StoreLifeEventRequest

Records a life event that has financial implications. Events can be past (completed) or future (planned). The financial impact field accepts both positive and negative values to represent windfalls or costs.

| Field | Type | Rules |
|-------|------|-------|
| event_type | string | required, in:birth,marriage,divorce,house_purchase,house_sale,inheritance,job_change,retirement,death,illness,graduation |
| event_name | string | nullable, string, max:255 |
| event_date | date | required, date |
| financial_impact | numeric | nullable, numeric |
| description | string | nullable, string, max:1000 |
| is_completed | boolean | nullable, boolean |

---

## Documents

### UploadDocumentRequest

Handles file uploads for supporting documents. The file size limit is 10 MB. Accepted document types cover the main financial planning document categories.

| Field | Type | Rules |
|-------|------|-------|
| document_type | string | required, in:pension_statement,mortgage_document,investment_statement,property_deed,will,insurance_policy,other |
| document_file | file | required, file, max:10240 (10 MB) |
| document_date | date | nullable, date |
| description | string | nullable, string, max:1000 |

---

## Admin

### StoreTaxConfigurationRequest

Creates or updates a tax year configuration. Restricted to admin users. The JSON fields contain nested objects with tax band thresholds and rates. The tax year string follows the YYYY/YY format (e.g. 2025/26).

| Field | Type | Rules |
|-------|------|-------|
| tax_year | string | required, string, format:YYYY/YY |
| effective_from | date | required, date |
| effective_to | date | required, date |
| income_tax | json | required, valid JSON |
| national_insurance | json | required, valid JSON |
| capital_gains_tax | json | required, valid JSON |
| inheritance_tax | json | required, valid JSON |
| isa_allowances | json | required, valid JSON |
| pension_annual_allowance | numeric | required, numeric |

---

## Validation Behaviour

### Error Response Format

When validation fails, Laravel returns a 422 Unprocessable Entity response with the following JSON structure:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": [
            "The field name is required.",
            "The field name must be a valid email address."
        ]
    }
}
```

Each field may have multiple error messages if it fails more than one rule.

### Authorisation

Each Form Request class includes an `authorize()` method that returns `true` for authenticated users. Some requests (such as `StoreTaxConfigurationRequest`) add additional admin role checks. Unauthorised requests receive a 403 Forbidden response before validation runs.

### Nullable vs Required Fields

The validation rules follow a deliberate pattern:

- **Required fields** represent data essential for the record to function (e.g. `account_type` on savings accounts, `scheme_name` on DB pensions). These must be present on every create or update request.
- **Nullable fields** represent optional data that enriches the record but is not essential for core calculations. These can be omitted or sent as `null`. This allows users to save partial records and return later to complete them.

### Enum Values

String fields validated with `in:` rules correspond to fixed sets of allowed values. These values match the database column constraints and the frontend dropdown options. Using a value outside the allowed set returns a validation error. The canonical enum values are documented in the project coding standards.
