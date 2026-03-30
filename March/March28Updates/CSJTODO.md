# CSJTODO — Fynla

*Last updated: 28 March 2026 — session 16*

---

## Session 16 (28 March) — PR #168 Build Fix + Deploy Prep

### Completed
- [x] Pulled PR #168 (dashboard branch merge — 167 files, major UI overhaul)
- [x] Fixed 3 build-breaking merge artifacts:
  - ProtectionDashboard.vue: removed 2 stray `<div>` tags (empty wrapper + duplicate container)
  - InvestmentProjections.vue: consolidated duplicate `CHART_COLORS` import
  - InvestmentProjections.vue: added missing `},` between computed properties
  - InvestmentProjections.vue: removed dead `goBack()` method
  - InvestmentProjections.vue: removed orphaned apexchart donut code (replaced by SVG donut)
- [x] Production build succeeds (`./deploy/fynla-org/build.sh`)
- [x] Eval-reviewed (3 cycles, all PASS)
- [x] Deploy guide confirmed: only `public/build/` + `resources/views/app.blade.php` need uploading

### Deploy Guide — PR #168

**Files to upload:**
1. `public/build/` → `~/www/fynla.org/public_html/public/build/`
2. `resources/views/app.blade.php` → `~/www/fynla.org/public_html/resources/views/` (Google Analytics tag added)

**Post-upload:**
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**What PR #168 contains:**
- Dashboard redesign with SubNavBar, ModuleStatusBar, DashboardSparkline
- Navbar restructure + SideMenu updates
- NetWorth sub-pages: donut charts → SVG rings, bar charts, layout restructures
- Investment holdings detail card-based layout
- Actions dashboard redesign with carousel arrows
- Settings page restructure with SettingsTabBar
- JourneyProgressHero improvements
- Google Analytics added to app.blade.php
- app.css + tailwind.config.js updates
- New files: SubNavBar.vue, ModuleStatusBar.vue, DashboardSparkline.vue, SettingsTabBar.vue, subNavConfig.js, subNav.js store, moduleMap.js

---

## Outstanding Items

### Tier 3 (Future — from feesMap.md)
- [ ] Standardise all fee thresholds into PlanConfigService
- [ ] Cross-module fee dashboard

### Next Priority Tasks
- [ ] Test expenditure AI fill on production
- [ ] Verify admin AI Provider panel shows Anthropic/xAI toggle cards

### Tech Debt
- [ ] OnboardingWizard.vue: Vue warn about failed component resolution
- [ ] LiabilitiesStep.vue: DEPRECATED comment
- [ ] IncomeStatementTab.vue: orphaned
- [ ] DB enum missing step_child/partner
- [ ] ProfileCompletenessAlert.vue: orphaned component (also referenced but not imported in ProtectionDashboard.vue — from PR #168)
- [ ] AccountForm.vue: pre-existing console.log in AI Fill watcher
- [ ] InvestmentProjections.vue: dead `allocationChartOptions` computed property (apexchart code now unused after SVG donut replacement)
- [ ] InvestmentProjections.vue: unused imports `ASSET_COLORS`, `CHART_DEFAULTS`

### Known Issues
- [ ] DB pension field mapping mismatch (employer_name vs scheme_name)
- [ ] Expenditure form fill doesn't animate through form

### Stale Branches
- `investmentUI` (local) — may be superseded by PR #168 dashboard work
- `content` (new remote branch) — purpose unknown

## Context for Next Session

PR #168 was a massive dashboard branch merge (167 files). It introduced 3 build-breaking merge artifacts that were fixed this session. The build succeeds and is ready for production upload. No backend PHP changes in this PR except the Google Analytics blade template update.

Key finding: ProfileCompletenessAlert is referenced in ProtectionDashboard.vue template but never imported/registered — added by PR #168, guarded by `v-if` on undefined props so no runtime error, but should be cleaned up.
