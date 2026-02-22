# Quick Reference - Test Users

## Seed Command

```bash
php artisan db:seed --class=TestUserSeeder
```

## Test Accounts

### Chris Jones (Primary - ADMIN)
```
Email:    chris@fynla.org
Password: Password1!
Admin:    YES
Onboarding: INCOMPLETE
```

### Angela Jones (Spouse)
```
Email:    c.jones@csjones.co
Password: Password1!
Admin:    NO
Onboarding: INCOMPLETE
```

## Key Stats

- **Combined Income:** £310,000/year
- **Monthly Surplus:** High (low expenditure)
- **Properties:** 5 (worth £2.55m)
- **Mortgages:** 5 (£718k outstanding)
- **Savings:** £170,000
- **Investments:** £338,000
- **Pensions:** £600,000+
- **Children:** Oliver (16), Sophie (13)

## What Makes This Different

✓ **Real data** (not preview mode)
✓ **Admin access** (Chris only)
✓ **Onboarding incomplete** (tests setup flow)
✓ **High surplus income** (tests investment scenarios)
✓ **Multiple properties** (5 vs 3 in Mitchells)
✓ **Can be reseeded** anytime

## Testing Scenarios

- Admin features (Chris account)
- Onboarding completion flow
- High-value portfolio management
- Multiple BTL properties
- Complex ownership structures
- Pension maximization
- Estate planning with trusts
- Household surplus optimization
