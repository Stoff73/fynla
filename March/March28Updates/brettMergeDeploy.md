# Deploy Guide — brett-v1 Merge (28 March 2026)

## Summary

PR #169 merged (`brett-v1` branch). Full platform buildout: public pages (learning centre, comparisons, features, life stages, insights, FAQ, contact, how it works, why-fynla), calculator page redesign, dashboard restructure, and minor fixes to Investment/Protection components.

**Frontend-only deploy** — no PHP, migration, or seeder changes.

## Pre-Deploy: Build

```bash
./deploy/fynla-org/build.sh
```

## Upload

Upload the entire `public/build/` directory to production:

```
~/www/fynla.org/public_html/public/build/
```

This is the only upload needed. All 64 changed Vue/JS files are compiled into the Vite build output.

### What changed (64 frontend files)

**Modified (existing):**
- `resources/js/components/Dashboard/DashboardCard.vue`
- `resources/js/components/Investment/AccountForm.vue`
- `resources/js/components/Investment/InlineHoldingsEditor.vue`
- `resources/js/components/NetWorth/InvestmentProjections.vue`
- `resources/js/layouts/PublicLayout.vue`
- `resources/js/router/index.js`
- `resources/js/store/modules/preview.js`
- `resources/js/views/Dashboard.vue`
- `resources/js/views/Protection/ProtectionDashboard.vue`
- `resources/js/views/Public/CalculatorsPage.vue`
- `resources/js/views/Public/LearningCentre.vue`

**New public pages (53 files):**
- `resources/js/components/Public/CalculatorCard.vue`
- `resources/js/components/Public/FeaturePageLayout.vue`
- `resources/js/views/Public/ContactPage.vue`
- `resources/js/views/Public/FaqPage.vue`
- `resources/js/views/Public/HowItWorksPage.vue`
- `resources/js/views/Public/compare/` (6 comparison pages)
- `resources/js/views/Public/features/` (7 feature pages)
- `resources/js/views/Public/insights/` (3 insight pages)
- `resources/js/views/Public/learn/` (12 learn articles + 5 guides + 5 tax pages)
- `resources/js/views/Public/stages/` (5 life stage pages)
- `resources/js/views/Public/why-fynla/` (4 pages)

## Post-Deploy: SSH

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Post-Deploy: Verify

1. Visit https://fynla.org — landing page loads
2. Check new public pages:
   - `/how-it-works`
   - `/faq`
   - `/contact`
   - `/calculators`
   - `/learn` (learning hub)
   - `/learn/glossary`
   - `/insights`
   - `/compare` (comparison pages)
   - `/why-fynla/our-approach`
   - `/stages/starting-out`
3. Log in as a preview persona — dashboard loads correctly
4. Check Investment projections page — no duplicate charts or broken layout
5. Check Protection dashboard — clean layout

## No Backend Changes

- No PHP files changed
- No migrations needed
- No seeders needed
