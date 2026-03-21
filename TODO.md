# TODO — Fynla

*Last updated: 21 March 2026 — session 4 (income fix)*

## Completed This Session

### Income Statement Fix
- [x] Added Interest Income, Pension Income, Trust Income to P&L and cashflow (was only 5 of 8 types)
- [x] Replaced hardcoded frontend tax calculator with backend `UKTaxCalculator` using `TaxConfigService`
- [x] All 20 PersonalAccountsService tests passing (57 assertions)

### Uploads from Previous Session (confirmed by user)
- [x] `app/Observers/NetWorthCacheObserver.php`
- [x] `app/Providers/EventServiceProvider.php`
- [x] `app/Http/Controllers/Api/MortgageController.php`
- [x] `app/Http/Controllers/Api/PropertyController.php`
- [x] `composer dump-autoload` on server

## Needs Upload to Production
- [ ] `app/Services/UserProfile/PersonalAccountsService.php` — 3 new income types + tax from backend
- [ ] Rebuild frontend (`./deploy/fynla-org/build.sh`) and upload `public/build/`
- [ ] Clear caches on server

## Known Issues
- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error. Direct API call with same data succeeds. Needs investigation of what extra fields the form sends.
- [ ] Goals page: Goals from onboarding not visible on dedicated Goals page (j1 testing)
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition with life-stage/progress fetch)

## Tech Debt
- [ ] OnboardingWizard.vue: dynamic and static imports warning for 8 step components
- [ ] PropertyForm sends all form fields including empty strings for nullable fields — clean up before submission

## Context for Next Session

Income statement now shows all 8 income types as separate line items and uses the backend `UKTaxCalculator` (via `TaxConfigService`) for tax estimates instead of hardcoded frontend values. This ensures tax figures stay correct across tax year changes without code changes.

Deploy guide: `March/March21Updates/deployFix21.md`
Detailed fix notes: `March/March21Updates/incomeFix.md`
