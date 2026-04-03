# Deploy Guide — Bug Report Part 2 Fixes

**Date:** 2 April 2026
**Branch:** bugs

---

## PHP Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/EstateController.php` | Added ownership_type + ownership_percentage to mortgage liabilities |

## Frontend Files (included in `public/build/`)

| File | Change |
|------|--------|
| `resources/js/views/NetWorth/CashOverview.vue` | Teleport modal to body + title tooltips on account names |
| `resources/js/components/Savings/SaveAccountModal.vue` | Premium Bonds £50k validation + error display |
| `resources/js/components/NetWorth/LiabilityCard.vue` | Joint ownership display + clickable mortgage cards |
| `resources/js/views/Dashboard.vue` | Pie chart click-through to module pages |
| `resources/js/components/NetWorth/InvestmentProjections.vue` | Null account guard + redirect |

## Deploy Steps

### 1. Upload `public/build/` via SiteGround File Manager
Upload to: `~/www/fynla.org/public_html/public/build/`

### 2. Upload PHP file
Upload `app/Http/Controllers/Api/EstateController.php` to matching path on server.

### 3. Clear caches
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Browser Verified (localhost)

- [x] Bug 1: Add Account modal — centred overlay, visible at top:0
- [x] Bug 2: Institution name tooltip — `title` attribute present on account names
- [x] Bug 3: Premium Bonds £50k — warning shown, submission blocked for £75,000
- [x] Bug 4: Mortgage joint ownership — "Joint (50.00% yours)" + "Your Share: £1,750"
- [x] Bug 5: Mortgage card clickable — navigates to /net-worth/property
- [x] Bug 6: Pie chart clickable — segment 0 → retirement, segment 2 → investments
- [x] Bug 7+8: Investment detail redirect — /investment-detail → /investments, 0 errors
