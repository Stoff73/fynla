# Deploy Guide — Session 27 (2 April 2026)

## Build: DONE (ready for upload)

---

## PHP Files (upload via SSH or SiteGround)

| File | Status | Change |
|------|--------|--------|
| `app/Constants/FinancialPlanningKnowledge.php` | **Deployed** | Pension PA reclaim prompt |
| `app/Services/Savings/ISATracker.php` | **Deployed** | ISA allowance query broadening |
| `database/seeders/ChrisUserSeeder.php` | **Deployed** | Production-matching chris user data |
| `database/seeders/DatabaseSeeder.php` | **Deployed** | Registered ChrisUserSeeder |
| `app/Observers/RecommendationCacheObserver.php` | **Deployed** | Invalidates agent caches on data changes |
| `app/Providers/EventServiceProvider.php` | **Deployed** | Registers observer on all financial models |

All PHP files are deployed. No further PHP uploads needed.

## Frontend Files (included in `public/build/`)

| File | Change |
|------|--------|
| `resources/css/app.css` | Card overflow fix (session 26) |
| `resources/js/components/Actions/DecisionTraceTimeline.vue` | Card overflow fix (session 26) |
| `resources/js/components/Actions/DecisionTreeDiagram.vue` | Card overflow fix (session 26) |
| `resources/js/components/Shared/AiChatPanel.vue` | Removed bouncing dots from Fyn streaming |
| `resources/js/views/Dashboard.vue` | Pension tapered tooltip + estate card age gate removed + empty state text fixed |
| `resources/js/views/Settings/SecuritySettings.vue` | Modals moved outside overflow container |
| `resources/js/store/modules/investment.js` | Recommendations refresh on create/update/delete |
| `resources/js/store/modules/savings.js` | Recommendations refresh on create/update/delete |
| `resources/js/store/modules/retirement.js` | Recommendations refresh on create/update/delete |
| `resources/js/store/modules/protection.js` | Recommendations refresh on create/update/delete |

## Deploy Steps

### 1. Upload `public/build/` via SiteGround File Manager
Upload to: `~/www/fynla.org/public_html/public/build/`

### 2. Clear caches on production (if not already done)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What This Deploy Fixes

1. **Recommendations not updating** — new observer invalidates agent caches when any financial record changes (policies, accounts, pensions, investments, estate data). 5 protection policy models that had zero observers now trigger cache invalidation. Frontend re-fetches recommendations after every CRUD operation.
2. **ISA allowance showing £0** — ISATracker now matches accounts by `account_type` when `isa_type` is null, with fallback chain for S&S ISA contributions.
3. **Estate Planning card showing "No estate details added yet"** — removed age <= 35 gate that hid estate data. Updated empty state text to explain that estate data is calculated from assets/liabilities, not manually added.
4. **Pension tapered badge** — hover tooltip explains why allowance is reduced (adjusted income, reduction amount, final allowance).
5. **Security settings modals** — change password / MFA modals now display as proper full-screen overlays (were clipped by `overflow:hidden` on parent).
6. **Fyn chat dots** — removed bouncing dots, kept rotating thinking text.
7. **Card text overflow** — global break-word + overflow-hidden fix from session 26.
8. **Fyn pension prompt** — Personal Allowance reclaim now covers incomes above £125,140 with worked example.

## Browser Verified (localhost)

- [x] Logged in as chris@fynla.org
- [x] Dashboard recommendations show "Improve Portfolio Diversification", "Add critical illness cover", "Add income protection" (no longer stale "no protection policies")
- [x] Protection card shows Total Coverage £700,000, 2 policies
- [x] Estate Planning card shows Taxable Estate £121,250, Inheritance Tax £48,500 (no longer hidden by age gate)
- [x] ISA Allowance card shows £5,460 of £20,000 (27%) — Stocks & Shares ISA tracked
- [x] Pension Annual Allowance shows £4,400 of £60,000 (7%)

## Commits (UI branch)

- `2212ba5` — ChrisUserSeeder + pension PA reclaim prompt
- `2addfdf` — ISA tracking fix, pension tapered tooltip, chat dots removed
- `6647b46` — Security modals fix + complete chris seeder
- `4a9e896` — Recommendation cache invalidation observer + frontend re-fetch
- (uncommitted) — Estate card age gate removed + empty state text
