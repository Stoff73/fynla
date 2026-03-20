# TODO — Fynla

*Last updated: 20 March 2026 by session 3 (production testing + hotfixes)*

## Outstanding from This Session

### Needs Upload to Production
- [ ] `app/Observers/NetWorthCacheObserver.php` (NEW) — auto-invalidates net worth cache on asset/liability changes
- [ ] `app/Providers/EventServiceProvider.php` — registers NetWorthCacheObserver on all asset/liability models
- [ ] `app/Http/Controllers/Api/MortgageController.php` — reverted manual cache invalidation (observer handles it)
- [ ] `app/Http/Controllers/Api/PropertyController.php` — reverted manual cache invalidation (observer handles it)
- [ ] Run `composer dump-autoload` on server after upload (new observer class)

### Known Issues
- [ ] PropertyForm edit 422 — editing a property via the UI form returns 422 validation error. Direct API call with same data succeeds. Needs investigation of what extra fields the form sends.
- [ ] Income page: "Other Income" not shown as line item in main breakdown (j1 testing)
- [ ] Goals page: Goals from onboarding not visible on dedicated Goals page (j1 testing)
- [ ] Sidebar journey %: intermittently shows 0% on some pages (race condition with life-stage/progress fetch)

### Tech Debt
- [ ] OnboardingWizard.vue: dynamic and static imports warning for 8 step components
- [ ] PropertyForm sends all form fields including empty strings for nullable fields — clean up before submission

## Context for Next Session

Seven production hotfixes were deployed during browser testing of 5 onboarding journeys. The critical mortgage bug (property ID extraction at `data.property.id` vs `data.id` in AssetsStep) was the root cause of Net Worth always showing Liabilities £0. The fix is deployed in the build. A `NetWorthCacheObserver` was created to auto-invalidate net worth cache on any asset/liability model change — this needs uploading to production.

All 5 journey types have been tested end-to-end with screenshots in `March/March20Updates/testFix/`.

## Files to Review
- `app/Observers/NetWorthCacheObserver.php` — new file, needs production upload
- `app/Providers/EventServiceProvider.php` — observer registration, needs production upload
