# Deploy — Pension Projection Fix + Unified Pension Form + Nav Refresh

**Branch:** `feature/csj/pension-fix`
**PR:** [#225 → dev](https://github.com/Stoff73/fynla/pull/225)
**Target first:** `csjones.co/fynla` (dev / staging)
**Target next (after dev browser-test):** `fynla.org` (production)

## What's in this deploy

Eight commits, zero migrations, zero composer changes, zero new env vars.

| Commit | Summary |
|---|---|
| `a6cfa5a` | fix: Monte Carlo cache key now includes simulation-input hash — pension projection no longer returns stale zeros after a user adds their first pension |
| `5a7ecec` | feat: unified Add Pension form — dropdown covers Occupational / SIPP / Personal / Stakeholder / Final Salary / State Pension. Tile picker removed. `db_pensions` + `state_pensions` records stay clean. |
| `618e0ba` | feat: Retirement Add Pension + Upload Statement CTAs moved inline (under pension cards, right-aligned next to projection chart) |
| `88af49a` | feat: global SubNavBar hidden (`v-if="false"` in `AppLayout.vue`). Per-page CTAs moved inline on Investments, Property, Liabilities, Personal Valuables, Business, Trusts, Goals. GoalsOverview duplicate Add Goal row removed. |
| `5231e33` | docs: CSJTODO ticks the in-flight checkout test line from session 65b |
| `2901b30` | feat: sticky top nav — AppNavbar stays pinned to the viewport while dashboards scroll underneath. Offsets below AdvisorBanner when impersonating. |
| `f2ba360` | fix: restore missing `investmentService.getAccountProjections()` method — investment account detail page Monte Carlo chart now loads (was showing "Failed to load projection data") |
| `e653180` | fix: browser tab title always shows "Fynla" in-app — no more stale "Sign In — Fynla" sticking around after login |

## Files to upload — 16 code files

Generated from `git diff --name-only dev..HEAD`:

### Backend — 1 file

```
app/Services/Retirement/RetirementProjectionService.php
```

### Frontend Vue / JS — 15 files (all compile into `public/build/`)

```
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/NetWorth/BusinessInterestsList.vue
resources/js/components/NetWorth/ChattelsList.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/LiabilitiesList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/UnifiedPensionForm.vue
resources/js/constants/subNavConfig.js
resources/js/layouts/AppLayout.vue
resources/js/router/index.js
resources/js/services/investmentService.js
resources/js/views/Goals/GoalsDashboard.vue
resources/js/views/Login.vue
resources/js/views/Trusts/TrustsDashboard.vue
```

### Docs (repo-only, not deployed)

```
CSJTODO.md  (not uploaded — repo-only)
```

## Build

Locally (do NOT build on the server — `feedback_never_raw_vite_build.md`):

### For dev (csjones.co/fynla)

```bash
git checkout dev
git pull
git merge --no-ff feature/csj/pension-fix   # or merge via GitHub UI after PR approval
./deploy/csjones-fynla/build.sh
```

This sets `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_REVOLUT_SANDBOX=true`, and writes to `public/build/`.

### For production (fynla.org) — only after dev browser-test passes

```bash
git checkout main
git pull
git merge --no-ff dev   # or merge via GitHub UI
./deploy/fynla-org/build.sh
```

## Upload

### 1. Back up the current build (both environments)

```bash
cd ~/www/csjones.co/fynla-app/public/     # or ~/www/fynla.org/public_html/ for prod
mv build build.old-$(date +%Y%m%d-%H%M%S)
```

### 2. Upload `public/build/` (whole directory)

Upload to:
- **Dev:** `~/www/csjones.co/fynla-app/public/build/`
- **Prod:** `~/www/fynla.org/public_html/build/`

Vite-hashed filenames are different from what was there before, so the old `build.old-*` directory can sit alongside without conflicts. The active `build/manifest.json` references only the new filenames.

### 3. Merge-copy the old build chunks (per `feedback_warn_before_spa_rebuild.md`)

If anyone is mid-session in a browser when you upload, their in-flight dynamic imports reference the OLD hashed filenames. Copy the old chunks alongside the new ones so live sessions survive the rebuild:

```bash
cp -rn build.old-*/. build/   # -n = no overwrite; only copies missing files
```

### 4. Upload the one changed PHP file

```
app/Services/Retirement/RetirementProjectionService.php
```

Path on dev: `~/www/csjones.co/fynla-app/app/Services/Retirement/RetirementProjectionService.php`
Path on prod: `~/www/fynla.org/public_html/app/Services/Retirement/RetirementProjectionService.php`

## Server commands (SSH)

### Dev — csjones.co/fynla

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app

# No migrations needed — but verify:
php artisan migrate:status | tail -5

# Clear caches (required — PHP opcache picks up the new service class)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

### Production — fynla.org

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize
```

## Optional — purge stale Monte Carlo cache

The backend fix makes the cache key content-addressed, so stale rows age out on their own within 24h. If you want the fix to take effect immediately for users who currently have a zero-state cache row (rather than them waiting up to 24h for it to expire), purge the legacy-format keys:

```sql
-- Only removes legacy keys (those without the new `_i<hash>` suffix).
-- New-format rows keep their data — these are correct and shouldn't be touched.
DELETE FROM monte_carlo_cache
WHERE cache_key LIKE '%pension_pot_%'
  AND cache_key NOT LIKE '%_i%';
```

Run once on each environment after the PHP file is uploaded + caches cleared.

## Smoke tests (in order)

### 1. Sticky nav

- Load `/net-worth/retirement`, `/net-worth/property`, `/goals`
- Scroll each page → verify the "Finances" / "Planning" header bar stays pinned to the top
- Left sidebar should still be present and usable

### 2. SubNavBar gone

- On every `/net-worth/*`, `/protection`, `/trusts`, `/goals` — the old tab row underneath the top nav should be absent
- Each page's Add / Upload buttons should render inline (top-right on property-type pages, below the list on Retirement + Investments)

### 3. Add Pension flow (the headline fix)

Best tested as a user with no pension data — pick `sarah@example.com` on local dev for the cleanest reproduction, or follow the equivalent on csjones.co/fynla.

1. Click **Add Pension** (inline, under the pension cards) — should go STRAIGHT to the form, no three-tile modal
2. Dropdown should show all 6 options: Occupational, SIPP, Personal, Stakeholder, **Final Salary (Defined Benefit)**, **State Pension**
3. Pick **Final Salary** → fields swap to DB-specific (Employer/Scheme Name, Scheme Status, Annual Income, Service Years, Pensionable Salary, Accrual Rate, Revaluation Rate, PCLS). Save.
   - Verify in DB: `SELECT scheme_name, scheme_type, accrued_annual_pension FROM db_pensions WHERE user_id = ?`
4. Pick **State Pension** → fields swap (Forecast Weekly Amount, Qualifying Years, Forecast Date, NI gaps checkbox). Save.
   - Verify in DB: `SELECT ni_years_completed, state_pension_forecast_annual FROM state_pensions WHERE user_id = ?`
5. Pick **Personal Pension** → fields swap to DC (Current Fund Value, Monthly Contribution, Platform Fee, Advisor Fee, Risk Level, Holdings). Save.
   - Verify in DB: `SELECT pension_type, current_fund_value FROM dc_pensions WHERE user_id = ?`
   - **CRITICAL:** On the retirement dashboard after saving the DC pension, the **Pension Pot Projection chart** should show real Monte Carlo bands (growing from current value to ~4–10× at retirement) — NOT flat zeros. This is the regression the cache-key fix resolves.
6. Verify dashboard cards show the three pensions with correct badges (Personal / Final Salary / State Pension) and amounts.

### 4. Inline CTAs on other modules

- `/net-worth/investments` (non-empty) — Add Account + Upload Statement at the bottom-right of the accounts column
- `/net-worth/property` (non-empty) — Add Property top-right
- `/net-worth/liabilities` (non-empty) — Add Liability top-right
- `/net-worth/chattels` (non-empty) — Add Valuable + Import top-right
- `/net-worth/business` (non-empty) — Add Business top-right
- `/trusts` (non-empty) — Add Trust + Upload Document top-right
- `/goals` — Add Goal in the tab header (right of the Overview / Life Events tabs)
- `/net-worth/cash` — Add Account button inside the page as before (the SubNavBar one is gone, but it already had an inline equivalent)
- `/protection` — Add Policy button inside the page as before (the SubNavBar one is gone, but CurrentSituation already had an inline equivalent)

### 5. Edit flow (regression check)

- Click an existing DC pension → the edit form should open as before (DCPensionForm)
- Click an existing DB pension → opens the legacy DBPensionForm (edit path untouched)
- Click the State Pension card → opens StatePensionForm (edit path untouched)

### 6. Onboarding (regression check)

If you have a test user mid-onboarding: when the DC pension step renders, the dropdown should show ONLY the four DC sub-types (Occupational / SIPP / Personal / Stakeholder). Final Salary + State Pension are hidden with `v-if="!isOnboarding"` to keep the onboarding flow scoped.

### 7. Investment account detail projection (new fix in f2ba360)

- `/net-worth/investments` → click any investment account card
- The detail view's "Account Projection" card should render a full Monte Carlo chart (probability bands + Current Value / Projected Value (80%) summary)
- Before the fix: this showed "Failed to load projection data" with a `TypeError: investmentService.getAccountProjections is not a function` in the console
- After the fix: real projection renders. Verify the 5/10/20/30-year selector switches horizons without errors.

## Rollback

If anything breaks on dev:

```bash
cd ~/www/csjones.co/fynla-app/public/
rm -rf build
mv build.old-<timestamp> build
cd ..
# Restore the previous service file from your local git checkout:
#   git show origin/dev:app/Services/Retirement/RetirementProjectionService.php > /tmp/prev.php
#   then upload /tmp/prev.php
php artisan cache:clear && php artisan config:clear && php artisan optimize
```

The `v-if="false"` on SubNavBar is a one-character revert if it needs to come back:

```vue
<SubNavBar v-if="false" />   <!-- change to v-if="true" -->
```

## Production cut-over (dev → main PR)

After dev is browser-tested and green:

1. Open PR `dev → main` (@Stoff73 only)
2. Ensure the next dev→main PR also carries PR #224 (intervention/image downgrade) — verified by running `composer show intervention/image` on dev before the cut, should report `3.11.7`
3. Build with `./deploy/fynla-org/build.sh` (uses `VITE_REVOLUT_SANDBOX=false` and the LIVE Revolut pk)
4. Upload + SSH commands as above but on `ssh.fynla.org`
5. Smoke-test on `https://fynla.org`
6. Tail `storage/logs/laravel.log` for 10–15 minutes

## Not included (carried forward to future deploys)

From the session-start handover (CSJTODO — session 65/65b open items):

- Clean up `public/build.old/` + `public/build.old2/` on the dev server once ~24h since the session 65b rebuild
- Verify `deploy/fynla-org/build.sh` + prod `.env` have the LIVE Revolut pk before next `dev → main` PR
- Verify SG Site Tools crontab for csjones.co lifecycle daily cron
- End-to-end lifecycle engine test (`php artisan lifecycle:e2e-test`)
- PR #214 (onboardingFyn) — still CONFLICTING, coupled with `feature/fyn-persona-split`
