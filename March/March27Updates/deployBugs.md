# Deploy Guide — Bug Fixes (27 March 2026)

## Summary

10 production bug fixes from user reports. All browser tested locally.

## Pre-Deploy

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

### PHP Files (upload to `~/www/fynla.org/public_html/`)

| File | Bug(s) | Change |
|------|--------|--------|
| `app/Services/LifeStage/LifeStageService.php` | #1, #2 | Added expenditure + liabilities steps to 4 journey builders |
| `app/Http/Controllers/Api/RetirementController.php` | #4 | Retirement age fallback from user profile when no RetirementProfile |
| `app/Services/Savings/ISATracker.php` | #5 | Estimate S&S ISA usage from monthly contributions |
| `app/Services/Estate/IHTCalculationService.php` | #7 | Fixed `end_date` → `maturity_date`, added payoff estimation |
| `app/Traits/HasAiChat.php` | #9 | Chatbot gets net worth from NetWorthService, not estate module |
| `app/Services/Goals/GoalsProjectionService.php` | #10 | Fixed first-year snapshot, income crediting, non-mortgage liabilities |

### Frontend Files (built into `public/build/`)

| File | Bug(s) | Change |
|------|--------|--------|
| `resources/js/components/Onboarding/steps/AssetsStep.vue` | #3, #4 | Pension monthly contribution calc + retirement age fallback |
| `resources/js/components/Estate/IHTCalculationTable.vue` | #6 | Exposed `IHT_NIL_RATE_BAND` constant to template via `data()` |
| `resources/js/components/UserProfile/TaxIncomeCard.vue` | #8 | `formatPercent` preserves decimals (33.75% not 34%) |

## Upload Sequence

1. Upload `public/build/` directory (frontend assets)
2. Upload all 6 PHP files to their respective paths
3. SSH and clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Post-Deploy Verification

1. Log in as (chris@fynla.org) and a peak-stage persona
2. Check onboarding progress bar — Spending and Debts steps should show correct icons
3. Check dashboard step count matches actual completion
4. Check pension card monthly contribution is not £0
5. Check retirement age shows user's target, not 67
6. Check ISA allowance shows usage from contributions
7. Expand IHT allowances breakdown — NRB should show £325,000 per person
8. Check estate projected liabilities — credit cards should project to £0 after payoff
9. Check income page dividend rate label shows 33.75% not 34%
10. Ask Fyn "What is my net worth?" — should match dashboard figure
11. Check Goals page net worth matches dashboard
