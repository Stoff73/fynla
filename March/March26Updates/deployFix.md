# Deploy Guide — 26 March 2026 (Session 14) — Pension & Investment Edit Fixes

## Summary

Fixed pension and investment edit flows — holdings sync, beneficiary/policy number persistence, Monte Carlo projection £0 bug.

### Bugs Fixed

1. **Monte Carlo £0 projection** — `PensionProjector.getUserAge()` defaulted to age 67 when no RetirementProfile existed, making `yearsToRetirement = 0`. Now falls back to `User.date_of_birth`.
2. **Pension edit always created duplicates** — `PensionList.vue` always called `createDCPension` on save, never `updateDCPension`. Now routes correctly.
3. **Pension edit didn't sync holdings** — `RetirementController.updateDCPension()` ignored holdings array. Now deletes + recreates holdings on update.
4. **Investment edit didn't sync holdings** — `InvestmentController.updateAccount()` same issue. Now syncs holdings on update.
5. **Beneficiary not persisted** — `StoreDCPensionRequest` missing `beneficiary_id` and `beneficiary_name` validation rules. Added.
6. **Policy number not persisted** — Frontend sent `policy_number`, backend expects `member_number`. Added mapping in `DCPensionForm.vue`.
7. **Policy number not displayed** — `PensionDetailInline.vue` read `policy_number` instead of `member_number`. Fixed.
8. **Investment update missing holdings validation** — `UpdateInvestmentAccountRequest` had no holdings rules. Added.

## PHP Files to Upload

```
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Requests/Retirement/StoreDCPensionRequest.php
app/Http/Requests/UpdateInvestmentAccountRequest.php
app/Services/Retirement/PensionProjector.php
```

## Frontend (included in build)

```
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Retirement/DCPensionForm.vue
```

## Build Required

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory after building.

## SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No migrations or seeders required for this deploy.

## Browser Testing Performed

### Investment (Playwright)
- Created ISA with 2 holdings + OCF (0.23%, 0.07%) — saved, verified in DB
- Edited: changed allocations, added 3rd holding (HSBC FTSE 250 ETF, OCF 0.35%) — saved, verified 3 holdings in DB
- Detail view shows correct weighted OCF (0.22%), total fees (0.37%), annual cost (£92/yr)

### Pension (Playwright)
- Created SIPP with 1 holding + OCF (0.22%) — saved, verified in DB
- Monte Carlo projection: £199,429 at 80% probability (was £0 before fix)
- Edited: set policy number (SIPP-001), beneficiary (Jane Smith), changed OCF to 0.35% — all saved, verified in DB
- Added 2nd holding during edit (Vanguard UK Govt Bond ETF, OCF 0.12%) — saved, verified 2 holdings in DB
- Detail view shows: Policy Number SIPP-001, Beneficiary Jane Smith, OCF 0.35%, Total Cost 1.00%
- Re-opened edit: all fields loaded correctly (policy number, beneficiary selected, both holdings with OCF)
