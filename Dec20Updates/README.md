# December 20, 2024 Updates

## Implementation Documentation

| File | Description |
|------|-------------|
| `RetirementFutureValue_Implementation.md` | Monte Carlo projections, income drawdown charts, and interactive strategy recommendations for the Retirement module |
| `DisposableIncome_Implementation.md` | Disposable income calculation fixes and consistency across Income & Occupation and Retirement Strategies tabs |

## Key Changes

### Retirement Module
- **Future Value Tab**: Monte Carlo pension pot projections with probability bands
- **Income Drawdown Chart**: Year-by-year income analysis from retirement to age 100
- **Strategies Tab**: Interactive strategy recommendations with sliders
- API endpoints: `/api/retirement/projections`, `/api/retirement/strategies`

### User Profile Module
- **Expenditure Calculation**: Fixed to respect `expenditure_entry_mode` (category vs simple)
- **Mortgage Ownership**: Applied ownership percentage to joint mortgage payments
- **Disposable Income**: Consistent calculation used across all modules

## Testing

```bash
# Get a token
TOKEN=$(curl -s -X POST "http://localhost:8000/api/preview/login/young_family" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

# Test projections
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/retirement/projections"

# Test strategies
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/retirement/strategies"

# Test user profile (includes disposable income)
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/user/profile"
```
