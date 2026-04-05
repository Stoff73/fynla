# Deploy Guide — Tax Year 2026/27 + Dashboard Reactivity + Fyn Icon Fix

**Branch:** `tax` (commits `f646f88`, `441eb07`, `19f7d23`)
**Date prepared:** 5 April 2026
**Tax year switch-over:** 2026/27 becomes active (was 2025/26)

---

## What this deploy does

1. **Adds UK tax year 2026/27 configuration** to the database with every documented rate change, and makes it the active year.
2. **Fixes pre-existing bugs** in the 2025/26 seeder data (BPA, SSP, PIP, APR/BPR notes, IHT freeze date) per `taxError.md`.
3. **Adds a quick-switch dropdown** in the admin Tax Settings header for one-click year switching.
4. **Fixes app-wide tax year reactivity** — dashboard allowances and tax-year labels now update whenever the admin changes the active year (previously the frontend read the calendar, not the DB).
5. **Flushes cached agent analyses** on year switch so every user sees new rates immediately.
6. **Fixes broken Fyn icons** in the nav, dashboard hero, and docked chat panel (6 `<img src>` references pointing at a file that was never added to the repo).

---

## Files to upload

### PHP (5 files)

```
app/Constants/TaxDefaults.php
app/Http/Controllers/Api/TaxSettingsController.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Savings/ISATracker.php
database/seeders/TaxConfigurationSeeder.php
routes/api.php
```

### Frontend (rebuilt `public/build/` directory)

Upload the entire contents of `public/build/` — the built assets include every changed Vue/JS file below:

```
resources/js/App.vue
resources/js/components/Admin/TaxSettings.vue
resources/js/components/Journey/JourneyProgressHero.vue
resources/js/components/Navbar.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/constants/taxConfig.js
resources/js/layouts/AppLayout.vue
resources/js/store/index.js
resources/js/store/modules/auth.js
resources/js/store/modules/taxConfig.js        ← new file
resources/js/utils/dateFormatter.js
```

**Do NOT need to upload:** source `.vue`/`.js` files individually — only the compiled `public/build/` output.

---

## Pre-upload: build locally

```bash
cd /Users/CSJ/Desktop/fynla
git checkout tax
./deploy/fynla-org/build.sh
```

Verify `public/build/` built successfully. Expected size ~7 MB, ~297 precached entries.

---

## Upload sequence

1. **Upload PHP files** (6 files above) via SiteGround File Manager to matching paths under `~/www/fynla.org/public_html/`.

2. **Upload `public/build/`** directory to `~/www/fynla.org/public_html/public/build/` (replace existing).

3. **SSH to server and run tax year seeder + clear caches:**

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Seed the 2026/27 tax year and make it active
php artisan db:seed --class=TaxConfigurationSeeder --force

# Clear caches (Cache::flush also happens automatically on admin year switch)
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

---

## Verification steps

### 1. Database state

```bash
php artisan tinker --execute="foreach(\App\Models\TaxConfiguration::orderBy('tax_year')->get(['tax_year','is_active']) as \$tc) { echo \$tc->tax_year.' | active='.(\$tc->is_active?'Y':'N').PHP_EOL; }"
```

Expected:
```
2021/22 | active=N
2022/23 | active=N
2023/24 | active=N
2024/25 | active=N
2025/26 | active=N
2026/27 | active=Y
```

### 2. Active year via API

```bash
# Log in as chris@fynla.org, capture Bearer token, then:
curl -H "Authorization: Bearer $TOKEN" https://fynla.org/api/tax-year/current
```

Expected: `{"success":true,"data":{"tax_year":"2026/27","effective_from":"2026-04-06","effective_to":"2027-04-05"}}`

### 3. Dashboard allowances

1. Log in to fynla.org as `chris@fynla.org`.
2. Open the dashboard.
3. Check the **Allowances** card:
   - Heading: `Tax year 2026/27` ✓
   - **ISA Allowance:** £0 of £20,000 (0%), £20,000 remaining ✓
   - **Pension Annual Allowance:** £0 of £60,000 (0%), £60,000 remaining ✓
4. Check the nav bar: **Fyn icon** (goat) is visible next to "Chat with Fyn" button.
5. Check the right sidebar: **Fyn icon** visible in the docked chat strip.
6. Click the hero's "Got a question? Ask Fyn" link — Fyn icon visible.

### 4. Admin dropdown

1. Go to Admin Panel → Tax Settings (requires admin user).
2. Verify the new **Active Year** dropdown appears in the header next to "Create New Tax Year".
3. Switch to `2025/26` → confirm → dashboard should now show:
   - Tax year 2025/26
   - ISA £5,460 / £20,000 used (or whatever historical figure)
   - Pension £4,400 / £60,000 used
4. Switch back to `2026/27` → dashboard resets to £0 used.

---

## Rollback plan

If something breaks:

1. **Revert to 2025/26 via admin dropdown** (no code rollback needed for year switch alone).
2. **Full code rollback:** checkout the previous main commit and redeploy PHP files + `public/build/`:
   ```bash
   git checkout main  # pre-deploy state
   ./deploy/fynla-org/build.sh
   # re-upload PHP files + public/build/ + run cache:clear
   ```

---

## Post-deploy notes

- **Cache flush behaviour:** The `setActive` endpoint now calls `Cache::flush()` when the admin changes the active year. This invalidates every user's cached analyses so they pick up the new tax rates immediately on their next request. Rate limiters reset as a side-effect — users may need to wait a moment if they were mid-flow.

- **Frontend reactivity flow:** Login/page-load → `GET /api/tax-year/current` → taxConfig Vuex store → `dateFormatter._activeTaxYearFromBackend` → every component's `getCurrentTaxYear()` call returns the backend value. Previously components computed tax year from the calendar (April 6 boundary), which disagreed with the DB when the admin manually selected a year or on April 5-6 transition days.

- **Pre-existing test failures fixed:** 25 flaky/stale tests now pass (Retirement, GDPR, UserDomicile pollution, ProfileCompleteness cache TTL). Full suite: 2191 passed / 0 failed.

- **Data correctness:** Historical tax years (2021/22 – 2024/25) retain their own BPA, SSP, and PIP values via explicit overrides in their seeder methods. Switching to a past year shows that year's historical allowance usage.

---

## Commits

| Commit | Summary |
|---|---|
| `f646f88` | feat(tax): add UK tax year 2026/27 config + admin dropdown |
| `441eb07` | fix(tax): propagate active tax year to allowances + UI labels |
| `19f7d23` | fix: broken Fyn icon image references |

Branch: https://github.com/Stoff73/fynla/tree/tax
