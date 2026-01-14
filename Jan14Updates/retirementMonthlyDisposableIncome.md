# Monthly Disposable Income - Data Flow Documentation

## Calculation Formula

```
Monthly Disposable Income = (Net Income - Annual Expenditure) / 12
```

**Source:** `app/Services/UserProfile/UserProfileService.php:378`

---

## Components

### 1. Net Income
This is the user's take-home pay after:
- Income tax
- National Insurance
- Pension contributions (salary sacrifice)

### 2. Annual Expenditure
Calculated as `(Monthly Manual Expenditure + Monthly Financial Commitments) × 12`

**Manual Expenditure** includes (based on `expenditure_entry_mode`):
- If `category` mode: Sum of all category fields (food, transport, healthcare, insurance, subscriptions, childcare, etc.)
- If `simple` mode: The single `monthly_expenditure` field

**Financial Commitments** include:
- DC Pension contributions
- Property expenses (mortgage payments, council tax, utilities, insurance)
- Investment regular contributions
- Protection policy premiums (life, CI, IP)
- Liability payments (loans, credit cards)

---

## Data Flow

```
Frontend (StrategiesTab.vue)
    strategies.affordability?.monthly_disposable
        ↓
Vuex Store (retirement.js)
    fetchStrategies action
        ↓
API Route
    /api/retirement/strategies
        ↓
RetirementStrategyService.php:58
    $affordability = $this->calculateAffordability($user)
        ↓
RetirementStrategyService.php:364-391
    Gets profile from UserProfileService, extracts:
    - incomeData['monthly_disposable']
        ↓
UserProfileService.php:378
    'monthly_disposable' => ($netIncome - $annualExpenditure) / 12
```

---

## Key Files

| File | Purpose |
|------|---------|
| `UserProfileService.php:378` | Final calculation |
| `UserProfileService.php:188-228` | `getExpenditureBreakdown()` - calculates annual expenditure |
| `UserProfileService.php:594+` | `getFinancialCommitments()` - calculates commitments |
| `RetirementStrategyService.php:364-391` | `calculateAffordability()` - wraps profile data for strategies |
| `StrategiesTab.vue:37` | Displays the value |

---

## Troubleshooting

If the figure looks wrong, check:

1. **Missing expenditure data** - User hasn't entered their expenditure in the profile
2. **Missing financial commitments** - Mortgage/loan payments not being calculated
3. **Net income incorrect** - Tax calculations might be off
4. **Expenditure entry mode** - Is user using `category` or `simple` mode?
