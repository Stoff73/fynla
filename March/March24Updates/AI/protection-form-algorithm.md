# Protection Policy Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Source:** `resources/js/components/Protection/PolicyFormModal.vue`

## Form Structure

Single-step modal form. Opens on `/protection` page. AI fill watchers already implemented.

## Policy Type Hierarchy

The form has TWO levels of type selection:

### Level 1: policyType (main dropdown)
| Value | Label | Shows |
|-------|-------|-------|
| `life` | Life Insurance | life_policy_type sub-dropdown, in_trust checkbox, mortgage protection |
| `criticalIllness` | Critical Illness | term_years, CI-specific fields |
| `incomeProtection` | Income Protection | benefit amount (not sum assured), deferred period |
| `disability` | Disability | |
| `sicknessIllness` | Sickness/Illness | |

### Level 2: life_policy_type (only when policyType = 'life')
| Value | Label | Shows |
|-------|-------|-------|
| `decreasing_term` | Decreasing Life Policy | start_value, decreasing_rate fields |
| `level_term` | Level Term Life Policy | term_years |
| `whole_of_life` | Whole of Life Policy | NO term_years (lifetime) |

### AI tool policy_type → form mapping
| AI `policy_type` | Form `policyType` | Form `life_policy_type` |
|---|---|---|
| `level_term` | `life` | `level_term` |
| `term` | `life` | `level_term` (mapped as generic term) |
| `whole_of_life` | `life` | `whole_of_life` |
| `decreasing_term` | `life` | `decreasing_term` |
| `family_income_benefit` | `life` | `family_income_benefit` |
| `standalone_ci` | `criticalIllness` | — |
| `accelerated_ci` | `criticalIllness` | — |
| `income_protection` | `incomeProtection` | — |

## Form Fields

### Always Visible
| Field | v-model | Type | ai-fill-highlight |
|-------|---------|------|------------------|
| Policy Type | `formData.policyType` | `<select>` | YES |
| Provider | `formData.provider` | text | YES |
| Policy Number | `formData.policy_number` | text | No |
| Coverage Amount / Benefit | `formData.coverage_amount` | number | YES |
| Premium Amount | `formData.premium_amount` | number | YES |
| Premium Frequency | `formData.premium_frequency` | `<select>` (monthly/annual) | No |

### Conditional: Life Insurance (policyType = 'life')
| Field | v-model | Condition |
|-------|---------|-----------|
| Life Policy Type | `formData.life_policy_type` | Always when life |
| Start Date | `formData.start_date` | Always when life |
| Term Years | `formData.term_years` | level_term, decreasing_term, family_income_benefit (NOT whole_of_life) |
| End Date | `formData.end_date` | Optional |
| In Trust | `formData.in_trust` | checkbox |
| Mortgage Protection | `formData.is_mortgage_protection` | checkbox |

### Conditional: Decreasing Life (life_policy_type = 'decreasing_term')
| Field | v-model |
|-------|---------|
| Start Value | `formData.start_value` |
| Decreasing Rate | `formData.decreasing_rate` |

### Conditional: Critical Illness (policyType = 'criticalIllness')
| Field | v-model |
|-------|---------|
| Term Years | `formData.term_years` |

### Conditional: Income Protection (policyType = 'incomeProtection')
| Field | Notes |
|-------|-------|
| coverage_amount is "Monthly Benefit Amount" | Label changes |
| Deferred Period | `formData.deferred_period` |

## Coverage Amount Logic

- **Income Protection**: `coverage_amount` = monthly benefit (e.g. £2,500/month)
- **Family Income Benefit**: `coverage_amount` = monthly benefit (e.g. £3,000/month)
- **All other types**: `coverage_amount` = lump sum assured (e.g. £500,000)

## Validation (handleSubmit)

No blocking validation — the form submits freely. Errors are tracked but don't prevent save.

## AI Fill Flow (already implemented)

### pendingFill watcher:
1. Pre-sets `policyType` and `life_policy_type` before sequence
2. Dispatches field sequence

### highlightedField watcher:
Catch-all: `this.formData[fieldKey] = value`

### filling watcher:
Auto-submits after 500ms with `$nextTick`. Reports validation errors to chat.

## Test Scenarios

### Scenario 1: Level Term Life
"I have level term life insurance with Aviva for £500,000, paying £45 a month for 25 years, held in trust"

### Scenario 2: Decreasing Term Life
"I have decreasing term life cover with Legal & General for £350,000, £28 a month for 20 years"

### Scenario 3: Whole of Life
"I have whole of life insurance with Royal London for £200,000, paying £85 a month, in trust"

### Scenario 4: Family Income Benefit
"I have family income benefit with Zurich, £3,000 a month benefit, paying £55 a month for 18 years"

### Scenario 5: Standalone Critical Illness
"I have standalone critical illness cover with Vitality for £150,000, paying £62 a month for 20 years"

### Scenario 6: Accelerated Critical Illness
"I have accelerated critical illness with Scottish Widows for £250,000, paying £78 a month for 25 years"

### Scenario 7: Income Protection
"I have income protection with LV= paying £2,500 a month benefit, premium £42 a month"

### Scenario 8: Term Life (generic)
"I have term life insurance with AIG for £300,000, £35 a month for 15 years"
