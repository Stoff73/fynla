# Cash/Savings Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Source:** `resources/js/components/Savings/SaveAccountModal.vue`

## Form Structure

Single-step modal form. Opens when "Add Account" is clicked on `/net-worth/cash` page. No multi-step wizard.

The `SaveAccountModal` is rendered by `CashSavingsView.vue` which watches `pendingFill` for entity_type `savings_account` and auto-opens the modal.

## Product Types (account_type)

| Value | Label | Section on Page | Notes |
|-------|-------|----------------|-------|
| `savings_account` | Savings Account | Savings Accounts | |
| `current_account` | Current Account | Current Accounts | |
| `easy_access` | Easy Access | Savings Accounts | |
| `instant_access` | Instant Access | Savings Accounts | |
| `notice` | Notice Account | Savings Accounts | Shows notice_period_days field |
| `fixed` | Fixed Term | Savings Accounts | Shows maturity_date field |
| `cash_isa` | Cash ISA | Cash ISAs | Auto-sets is_isa=true, shows ISA fields |
| `junior_isa` | Junior ISA | Cash ISAs | Shows beneficiary fields |
| `premium_bonds` | Premium Bonds | NS&I | Auto-sets NS&I fields |
| `nsi` | NS&I Savings | NS&I | Auto-sets NS&I fields |

## Form Fields

### Always Visible
| Field | v-model | Type | Required | Notes |
|-------|---------|------|----------|-------|
| Institution | `formData.institution` | text | Recommended | Bank/building society name |
| Product Type | `formData.account_type` | `<select>` | Recommended | See product types above |
| Current Balance | `formData.current_balance` | number | YES | |

### Visible Unless NS&I Product
| Field | v-model | Type | Notes |
|-------|---------|------|-------|
| Interest Rate | `formData.interest_rate` | number (%) | Max 20% |
| Access Type | `formData.access_type` | `<select>` | immediate/notice/fixed |
| Emergency Fund | `formData.is_emergency_fund` | checkbox | |
| ISA checkbox | `formData.is_isa` | checkbox | Hidden if already ISA product type |
| Country | `formData.country` | CountrySelector | Hidden if ISA |
| Ownership Type | `formData.ownership_type` | `<select>` | individual/joint |
| Account Number | `formData.account_number` | text | Last 4 digits, optional |

### Conditional: Notice Account (access_type === 'notice')
| Field | v-model | Type |
|-------|---------|------|
| Notice Period | `formData.notice_period_days` | number (days) |

### Conditional: Fixed Term (access_type === 'fixed')
| Field | v-model | Type |
|-------|---------|------|
| Maturity Date | `formData.maturity_date` | date |

### Conditional: ISA (is_isa or ISA product type)
| Field | v-model | Type | Notes |
|-------|---------|------|-------|
| Tax Year | `formData.isa_subscription_year` | `<select>` | 2025/26, 2024/25, 2023/24 |
| Already Subscribed | `formData.isa_subscription_amount` | number | Amount already contributed this year |
| Regular Contribution | `formData.regular_contribution_amount` | number | With frequency selector |
| Contribution Frequency | `formData.contribution_frequency` | `<select>` | monthly/quarterly/annually |
| Planned Lump Sum | `formData.planned_lump_sum_amount` | number | One-off amount |
| Planned Lump Sum Date | `formData.planned_lump_sum_date` | date | |

### Conditional: Junior ISA
| Field | v-model | Type |
|-------|---------|------|
| Beneficiary (Child) | `formData.beneficiary_id` | `<select>` |
| Beneficiary Name | `formData.beneficiary_name` | text (if "Other") |
| Beneficiary DOB | `formData.beneficiary_dob` | date (if "Other") |

## Watchers on account_type

When `account_type` changes:
- **ISA types** (`cash_isa`, `junior_isa`): auto-sets `is_isa = true`, `country = 'United Kingdom'`, `ownership_type = 'individual'`
- **NS&I types** (`premium_bonds`, `nsi`): auto-sets `country = 'United Kingdom'`, `ownership_type = 'individual'`, hides interest rate, access type, emergency fund, ISA checkbox

## AI Fill Flow (existing — working)

### pendingFill watcher (line 863):
1. Pre-sets `institution` and `account_type` before field sequence
2. Builds field order from non-null fields
3. Dispatches `beginFieldSequence`

### highlightedField watcher (line 880):
Catch-all: `this.formData[fieldKey] = value`

### filling watcher (line 889):
Auto-submits via `handleSubmit()` after 250ms

### handleSubmit (line 981):
1. Validates ISA allowance (if ISA)
2. Calls `prepareAccountData()` to build clean payload
3. Emits `save` to parent

## Validation

- No hard validation failures that would block submit
- ISA allowance check warns but doesn't block
- `current_balance` can be 0

## Backend Handler: handleCreateSavingsAccount

1. Validates `account_name`, `current_balance`, `account_type`, `interest_rate`
2. Checks for duplicate by `account_name`
3. Maps AI `account_type` to form-compatible value (`fixed_term` → `fixed`, `regular_saver` → `easy_access`)
4. If ISA, sets `account_type = 'cash_isa'`
5. Returns `fill_form` with entity_type `savings_account`, route `/net-worth/cash`

## Test Scenarios

### Scenario 1: Easy Access Savings
"I have a savings account with Marcus earning 4.5% with £15,000"
Expected: institution=Marcus, account_type=easy_access, balance=15000, interest_rate=4.5

### Scenario 2: Cash ISA
"I have a Cash ISA with Nationwide worth £18,500 earning 4.1%, I put in £500 a month"
Expected: account_type=cash_isa, is_isa=true, balance=18500, rate=4.1, regular_contribution=500

### Scenario 3: Current Account
"I have a current account with HSBC with £3,200"
Expected: account_type=current_account, balance=3200

### Scenario 4: Premium Bonds
"I have £5,000 in premium bonds"
Expected: account_type=premium_bonds, institution=NS&I, balance=5000

### Scenario 5: Emergency Fund
"I have an emergency fund of £10,000 in a Barclays easy access account earning 3.8%"
Expected: account_type=easy_access, is_emergency_fund=true, balance=10000, rate=3.8
