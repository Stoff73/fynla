# Consolidated Deployment Guide — v0.8.3

**Date:** 5 March 2026
**Branches:** `centraliseUI` (merged into `uiUpdates`), `uiUpdates`
**Total scope:** 480+ files changed across all patches

---

## Deployment Order

These deployments must be applied in sequence. Each builds on the previous.

| Step | Patch | Database Migration | Source |
|:----:|-------|:------------------:|--------|
| 1 | Plans Feature Foundation | No | `Feb28Updates/deployPlans.md` |
| 2 | Investment Plan Account-Level Actions | No | `Feb28Updates/deployInvestPlans.md` |
| 3 | Retirement Plan Per-Pension Actions | No | `Feb28Updates/deployRetirePlan.md` |
| 4 | Estate Plan IHT Mitigation | **Yes** | `Feb28Updates/deployEstatePlan.md` |
| 5 | Retirement Plan Overhaul (DB-driven) | **Yes** | `March2Update/deployRetirementPlan.md` |
| 6 | Investment Plan Overhaul (DB-driven) | **Yes** | `March2Update/deployInvestSaveplan.md` |
| 7 | Protection Plan Overhaul (DB-driven) | **Yes** | `March3Updates/deployProtectionUpdate.md` |
| 8 | Estate Plan Rewrite | No | `March3Updates/deployEstate.md` |
| 9 | Print/Save PDF for All Plans | No | `March3Updates/deployPDF.md` |
| 10 | CSS Centralisation | No | `March4Updates/deployCSS.md` |
| 11 | Design System Overhaul v1.2.0 + UI Updates | No | `March4Updates/deployRedesign.md` |
| 12 | Code Review Fixes | No | `March5Updates/codeReview.md` |

---

## Simplified All-in-One Deployment

Since all patches are being deployed together, the simplest approach is to upload everything at once rather than file-by-file per patch.

### Pre-Deployment Checklist

- [ ] `./deploy/fynla-org/build.sh` passes
- [ ] `./vendor/bin/pest` — all tests pass
- [ ] `php artisan db:seed` runs cleanly
- [ ] Code review fixes committed (34 files from Issue 1-9)

### Step 1: Build Locally

```bash
./deploy/fynla-org/build.sh
```

### Step 2: Upload Files via SiteGround File Manager

#### 2a. Frontend Build (required)
Upload entire directory:
```
public/build/ --> ~/www/fynla.org/public_html/public/build/
```

#### 2b. Tailwind Config
```
tailwind.config.js --> ~/www/fynla.org/public_html/tailwind.config.js
```

#### 2c. CSS
```
resources/css/app.css --> ~/www/fynla.org/public_html/resources/css/app.css
```

#### 2d. JavaScript Constants
```
resources/js/constants/designSystem.js
resources/js/constants/eventIcons.js
resources/js/constants/eventIconSvgs.js
```

#### 2e. All Vue Components + Views + Store + Services
Due to the scale (400+ files changed), upload the entire directory:
```
resources/js/ --> ~/www/fynla.org/public_html/resources/js/
```

#### 2f. Logo Assets (new consolidated directory)
```
public/images/logos/ --> ~/www/fynla.org/public_html/public/images/logos/
```
Contains: `LogoHiResFynlaDark.png`, `LogoHiResFynlaLight.png`, `logoTransparent.png`, `logoMain.png`, `favicon.png`, `favicon.ico`

#### 2g. Blade Templates
```
resources/views/app.blade.php
resources/views/emails/*.blade.php  (all 11 email templates)
```

#### 2h. All Backend PHP

**New PHP files to create on server:**

Plans Services (7 new):
```
app/Services/Plans/BasePlanService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/RetirementPlanService.php
app/Services/Plans/ProtectionPlanService.php
app/Services/Plans/EstatePlanService.php
app/Services/Plans/GoalPlanService.php
app/Services/Plans/PlanConfigService.php
```

Plans Controller + Routes:
```
app/Http/Controllers/Api/Plans/PlanController.php
```

Action Definition Models + Admin:
```
app/Models/RetirementActionDefinition.php
app/Models/InvestmentActionDefinition.php
app/Models/ProtectionActionDefinition.php
app/Http/Controllers/Api/Admin/RetirementActionDefinitionsController.php
app/Http/Controllers/Api/Admin/InvestmentActionDefinitionsController.php
app/Http/Controllers/Api/Admin/ProtectionActionDefinitionsController.php
app/Http/Requests/RetirementActionDefinitionRequest.php
app/Http/Requests/InvestmentActionDefinitionRequest.php
app/Http/Requests/ProtectionActionDefinitionRequest.php
```

Migrations (5):
```
database/migrations/xxxx_create_retirement_action_definitions_table.php
database/migrations/xxxx_create_investment_action_definitions_table.php
database/migrations/xxxx_create_protection_action_definitions_table.php
database/migrations/xxxx_add_estate_plan_fields_to_users_table.php
database/migrations/xxxx_create_plan_configurations_table.php
```

Seeders (4):
```
database/seeders/RetirementActionDefinitionSeeder.php
database/seeders/InvestmentActionDefinitionSeeder.php
database/seeders/ProtectionActionDefinitionSeeder.php
database/seeders/PlanConfigurationSeeder.php
```

**Modified PHP files to update on server:**

Agents (5):
```
app/Agents/RetirementAgent.php
app/Agents/InvestmentAgent.php
app/Agents/EstateAgent.php
app/Agents/ProtectionAgent.php
app/Agents/CoordinatingAgent.php
```

Services (6):
```
app/Services/Coordination/RecommendationsAggregatorService.php
app/Services/Coordination/WhatIfCalculatorService.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Protection/CoverageGapAnalyzer.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/ComprehensiveEstatePlanService.php
```

Controllers (2):
```
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Plans/PlanController.php
```

Models (1):
```
app/Models/User.php
```

Routes (1):
```
routes/api.php
```

#### 2i. Documentation (optional)
```
CLAUDE.md
README.md
fynlaDesignGuide.md
```

### Step 3: Delete Old Files from Server

```
public/favicon.png                              (moved to public/images/logos/)
public/favicon.ico                              (moved to public/images/logos/)
public/images/logoMain.png                      (moved to public/images/logos/)
resources/js/components/Holistic/               (entire directory — replaced by Plans/Holistic/)
resources/js/services/holisticService.js        (replaced by module plan services)
resources/js/store/modules/holistic.js          (removed from Vuex store)
resources/css/badges.css                        (consolidated into app.css)
```

### Step 4: SSH — Run Migrations and Seeders

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (5 new tables)
php artisan migrate

# Seed new data
php artisan db:seed --class=RetirementActionDefinitionSeeder --force
php artisan db:seed --class=InvestmentActionDefinitionSeeder --force
php artisan db:seed --class=ProtectionActionDefinitionSeeder --force
php artisan db:seed --class=PlanConfigurationSeeder --force

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed preview personas (ensures they have correct data for new features)
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## What Changed — Summary by Area

### Financial Plans System (Steps 1-9)
- 5 plan types: Investment, Protection, Retirement, Estate, Goal
- Consistent 6-section structure: executive summary, current situation, toggleable actions, what-if scenarios, dynamic conclusion, PDF export
- Database-driven action definitions with admin CRUD interfaces
- Per-account/per-pension grouped actions with reactive projection charts
- Structured executive summaries with personalised narrative text
- Tax-aware funding source dropdowns for goal completion actions
- Holistic plan aggregating all module plans with priority allocation

### Design System Overhaul v1.2.0 (Steps 10-12)
- Complete visual rebrand: Raspberry CTAs, Horizon text, Spring success, Violet warnings, Eggshell backgrounds
- Font: Segoe UI (primary), Inter (fallback)
- CSS centralisation: -1,110 lines of duplicated CSS
- Logo consolidation to `public/images/logos/`
- All banned colour tokens (blue-\*, green-\*, red-\*, purple-\*, teal-\*, gray-\*) migrated to palette tokens
- 26+ hardcoded hex values in SecuritySettings.vue replaced with @apply directives

### UI Updates (Step 11)
- Removed ModuleLifeEvents from Retirement, Investment, Estate dashboards
- Removed Recommended Strategies cards from Retirement and Investment
- Reorganised side menu (Current/Planning/Family sections)
- Life event SVG icons on Monte Carlo projection charts
- Rebuilt Actions dashboard (fixed /api double prefix, proper AppLayout)
- IHT summary card fix (aligned with calculation table values)
- Version documentation updated to v0.8.3

### Code Review Fixes (Step 12)
- **34 Vue + 2 PHP files** — fixes for all 9 issues from code review
- Removed dev placeholder text from PlanActionCard
- Fixed `update-funding-source` event forwarding in PlanActionsList
- FinancialHealthScore rewritten to comply with Rule 12 (no numeric scores)
- IHTController no longer overwrites service's projected IHT values
- RecommendationsAggregatorService guarded against TypeError on non-numeric `iht_saving`
- Chart components use `ERROR_COLORS[500]` instead of hardcoded `#EF4444`

---

## Post-Deployment Verification

### Critical Checks
1. Login and Register pages show new hi-res logo and raspberry CTAs
2. Page background is warm eggshell (#F7F6F4), not cool gray
3. Navigation bar uses dark logo, footer uses light logo
4. Form inputs have violet focus rings
5. Success messages use spring green
6. No amber, orange, blue, green, red, purple, or teal colours visible in Plans

### Plans System
7. Plans dashboard shows all 5 plan types with correct palette cards
8. Each plan generates with executive summary, current situation, actions, what-if, conclusion
9. Toggle actions on/off and verify what-if scenario updates reactively
10. PDF export works for all plan types with correct charts and headers
11. Admin panel: action definitions CRUD works for all 3 modules

### Module Dashboards
12. Retirement dashboard: no life events card, no recommended strategies
13. Investment dashboard: no life events card, no strategy card
14. Estate dashboard: no ModuleLifeEvents card, spring/raspberry colours only
15. Actions dashboard loads recommendations from all modules (no 404s)

### Code Review Fixes
16. PlanActionCard: no dev placeholder text visible
17. FinancialHealthScore: shows "Good"/"Needs attention" labels, no /100 scores
18. SecuritySettings: no visible colour differences (tokens match design system)
19. Chart life event expense annotations use raspberry, not red

### Preview Personas
20. All 7 preview personas load correctly
21. Plans generate for each persona without errors

---

## Rollback

If rollback needed, revert to the previous `public/build/` directory and source files. The database migrations add new tables only (no destructive changes to existing tables), so they can remain in place.

**Database is backwards-compatible** — the new action_definitions and plan_configurations tables are only used by the new Plans system. Existing functionality is unaffected.

---

## Files Summary

| Category | Count |
|----------|-------|
| New Backend PHP | ~25 files |
| Modified Backend PHP | ~15 files |
| New Frontend Vue | ~30 files |
| Modified Frontend Vue | ~400+ files |
| Database Migrations | 5 |
| Seeders | 4 |
| Build Output | `public/build/` |
| Config | `tailwind.config.js` |
| CSS | `resources/css/app.css` |
| Blade Templates | 12 |
| Logo Assets | 6 files |
| **Total** | **480+ files** |
