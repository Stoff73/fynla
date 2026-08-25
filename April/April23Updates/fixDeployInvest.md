# Deploy Guide — Investment 500 Fix + Session 67 Tech-Debt Remediation

**Status:** ✅ Localhost-verified (CSJ confirmed 23 April 2026 night — peak_earners persona, joint net-worth + investment pages render cleanly, no `_uid` flood, no 500).
**Target environment:** csjones.co/fynla (dev)
**Base commit on dev:** `a2757c9` (session 67 tech-debt report)
**Scope:** 1 PHP bug fix + 1 Vue 3 compat fix + 4 tech-debt refactors. Frontend-only to the SPA bundle except the single PHP file.

---

## What this ships

### Bug fixes (surfaced on localhost during session 67 tech-debt verification)

1. **`/api/investment/analyze` 500 → 200.** `Holding::$casts` marks `cost_basis` and `current_value` as `decimal:2`, which Laravel returns as **strings** (for precision). PHP 8's `round()` is strict — rejects strings with `TypeError: Argument #1 must be of type int|float, string given`. `CGTHarvestingCalculator` exposed the raw Eloquent values in its `opportunities[]` payload; downstream `TaxEfficiencyCalculator.php:107` blew up the moment it tried to round them. Fixed at the source with `(float)` casts so every downstream consumer gets floats.

2. **Vue `_uid` warning flood silenced.** `AssetAllocationDonut.vue:145` used `this._uid` — a Vue 2 internal that does not exist in Vue 3. Replaced with `this.$.uid` (Vue 3 options-API equivalent). The flood only became visible after session 67's joint-user donut layout (`eaf4552`) started rendering two donut instances per page.

### Session 67 tech-debt remediation (from `April/April23Updates/tech-debt-report.md`)

1. **Warning 1 resolved — hex literals in tooltip → design-system constants.** `AssetBreakdownBar.vue` custom tooltip now imports `PRIMARY_COLORS`, `TEXT_COLORS`, `SUCCESS_COLORS`, `WARNING_COLORS` from `designSystem.js` and interpolates them instead of hardcoded `#E83E6D` / `#1F2A44` / `#5854E6` / `#20B486`. Border `#E5E5E5` left untouched (pre-existing pattern, flagged as out-of-scope by the report).

2. **Suggestion 2 resolved — spouse-name fallback chain deduplication.** The `userProfile/spouse` getter's `withName` helper (added in `7e1739d`) already normalises `name` / `first_name` / `last_name` on every return path, so the three 8–18-line defensive fallback chains in `NetWorthWealthSummary.vue`, `PortfolioOverview.vue`, and `LetterToSpouse.vue` were redundant. Collapsed each to a single `store.getters['userProfile/spouse']?.name || 'Partner'` / `'Spouse'` / `first_name || null` expression. Net −32 LOC.

---

## Scope boundaries

- **No composer changes.** `composer.json` / `composer.lock` untouched.
- **No npm changes.** `package.json` / `package-lock.json` untouched.
- **No migrations.** `database/migrations/` untouched. No `php artisan migrate` required.
- **No seeders.** `database/seeders/` untouched. No `db:seed` required.
- **No `.env` changes.** No new env vars.
- **No `.htaccess` changes.** Keep the existing dev `.htaccess` as-is.
- **No cron / queue / infrastructure changes.**

---

## Files to upload (from `git diff origin/dev..HEAD --name-status`)

### Backend — upload source file directly (1)

- `app/Services/Investment/Tax/CGTHarvestingCalculator.php`

### Frontend — compile into `public/build/`, upload the whole directory (5 Vue source files are build-time inputs, not runtime assets)

- `resources/js/components/Investment/PortfolioOverview.vue`
- `resources/js/components/NetWorth/AssetAllocationDonut.vue`
- `resources/js/components/NetWorth/AssetBreakdownBar.vue`
- `resources/js/components/NetWorth/NetWorthWealthSummary.vue`
- `resources/js/components/UserProfile/LetterToSpouse.vue`

**Runtime upload targets:**

| Source | Uploads to |
|--------|------------|
| `app/Services/Investment/Tax/CGTHarvestingCalculator.php` | `~/www/csjones.co/fynla-app/app/Services/Investment/Tax/CGTHarvestingCalculator.php` |
| `public/build/` (whole directory, built locally) | `~/www/csjones.co/fynla-app/public/build/` (merged with `build.old/` per preserve-old-chunks pattern below) |

---

## Pre-flight: warn before rebuilding if anyone is on an active dev session

If CSJ (or a teammate) has an incognito session open on `csjones.co/fynla` right now, **rebuilding and replacing `public/build/` will invalidate their in-flight dynamic imports** (content-hashed chunk names change). Either pause until they're ready to refresh, or use the preserve-old-chunks pattern below so old hashes keep resolving alongside new ones.

---

## Build locally (csjones script, NOT the fynla.org one)

```bash
cd /Users/CSJ/Desktop/fynla
./deploy/csjones-fynla/build.sh
```

The csjones script bakes `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_REVOLUT_SANDBOX=true`, and the sandbox `VITE_REVOLUT_PUBLIC_KEY`. **Do not use `./deploy/fynla-org/build.sh`** — different base paths, will break routing on the dev URL.

---

## Upload

### 1. Backend — one file

Upload `app/Services/Investment/Tax/CGTHarvestingCalculator.php` to:

```text
~/www/csjones.co/fynla-app/app/Services/Investment/Tax/CGTHarvestingCalculator.php
```

(via SiteGround File Manager, or `scp` over SSH, or `rsync`).

### 2. Frontend build — preserve-old-chunks pattern

Upload the locally-built `public/build/` directory contents to `~/www/csjones.co/fynla-app/public/build/`, preserving old chunk files so any active in-flight session survives. From your local machine, the simplest path is a tar-pipe + server-side merge:

```bash
# --- on your local machine, from /Users/CSJ/Desktop/fynla ---
tar -cf - -C public/build . | ssh -p 18765 -i ~/.ssh/fynlaDev \
  u163-ptanegf9edny@ssh.csjones.co \
  "cd ~/www/csjones.co/fynla-app && \
   rm -rf public/build.old && \
   mv public/build public/build.old && \
   mkdir public/build && \
   tar -xf - -C public/build && \
   cp -rn public/build.old/. public/build/"
```

The `cp -rn` (no-clobber) keeps the new `manifest.json` / `app-*.js` / new chunks untouched. Missing old chunks get filled in from `build.old/`. New sessions pick up the new manifest; in-flight sessions keep resolving the old hashes.

If you'd rather go via SiteGround File Manager, upload `public/build/` to a temporary name (`public/build.new`), then SSH in and swap:

```bash
cd ~/www/csjones.co/fynla-app
rm -rf public/build.old
mv public/build public/build.old
mv public/build.new public/build
cp -rn public/build.old/. public/build/
```

---

## SSH + server finalisation

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
```

Once in:

```bash
cd ~/www/csjones.co/fynla-app
php artisan cache:clear      # critical — InvestmentAgent::analyze() caches for 24h
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

`cache:clear` is the important one: `InvestmentAgent->analyze()` wraps its result in `remember('investment_analysis_{userId}', 86400, …)`. If you skip this step, users with a cached pre-fix `analyze()` result (including the serialised error state) will keep hitting the old payload for up to 24h after deploy.

---

## Smoke tests (do these in order, in an incognito window so there's no stale Vuex state)

Log in to `https://csjones.co/fynla` as the peak_earners preview persona (David & Sarah Mitchell). Then:

1. **Investment dashboard — fix B verification.** Open DevTools → Network → XHR. Navigate to Investment. `GET /fynla/api/investment/analyze` should return **200**. Response JSON should contain `data.tax_efficiency.harvesting_opportunities[]` items whose `cost_basis` / `current_value` values are numbers (not quoted strings). No red 500 in the network pane.
2. **Investment dashboard — page renders.** Portfolio Overview card shows holdings, allocation chart, spouse block labelled with "Sarah Mitchell" (not "Spouse") — confirms the simplified `getSpouseName` still picks up the spouse from the `userProfile/spouse` getter.
3. **Net Worth dashboard — fix A verification.** Navigate to Net Worth. Open DevTools → Console. Scroll / hover the two asset-allocation donuts (David and Sarah). Expected: **no** `Property "_uid" was accessed during render` warnings. Confirms `this.$.uid` resolves and gradient IDs are unique per instance.
4. **Net Worth — Asset Breakdown bar chart tooltip.** Hover any bar on the combined bar chart. Tooltip should show: category label + total (raspberry if liability, horizon if asset), then "David Mitchell: £X" (violet dot) and "Sarah Mitchell: £Y" (spring dot). Colours unchanged — this just confirms the symbolic references resolve to the same palette hex.
5. **Net Worth — Wealth Summary donut title.** David's and Sarah's asset-allocation donuts should carry their real names in the title (e.g. "David Mitchell's Asset Allocation"). Empty titles would mean `userProfile/spouse?.name` returned undefined → investigate (should not happen for this persona).
6. **Estate → Letter to Spouse.** Open the Letter to Spouse page. Heading should read "Letter to David" / "Letter to Sarah" (whichever the current user is). Confirms `spouseNameForLetter` still returns `first_name` after the simplification.
7. **Single-user persona regression check.** Log out → log in as `young_saver` (John Morgan — no spouse). Net Worth page loads, no donut splits, no console errors, `spouseUserName` falls back to `'Partner'` where shown but NOT in user-visible output (single-user path skips the spouse block entirely).

If any of these fail, capture the console output + network response and report back — do NOT claim deployed until all 7 pass.

---

## Rollback

If something breaks after deploy:

### Frontend rollback (instant)

```bash
cd ~/www/csjones.co/fynla-app
rm -rf public/build
mv public/build.old public/build
```

This restores the pre-deploy chunks byte-for-byte. Then refresh incognito.

### Backend rollback (one file)

Revert `CGTHarvestingCalculator.php` to the pre-change version by re-uploading the `origin/dev` copy (or equivalent `git show origin/dev:app/Services/Investment/Tax/CGTHarvestingCalculator.php` output). Then:

```bash
cd ~/www/csjones.co/fynla-app
php artisan cache:clear
```

Both rollbacks are independent — you can revert the frontend alone (keeps the 500 fix live) or the backend alone (keeps the UI improvements).

---

## Post-deploy cleanup

**~24h after deploy** (or whenever you're confident no one is still on a pre-rebuild session), remove the `build.old/` directory:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
rm -rf public/build.old
```

Frees ~12 MB on the server.

---

## Promote to main (production — fynla.org)

Do **not** cut the `dev → main` PR solely for this fix. CSJTODO session 67 already lists an outstanding `dev → main` promotion that will ride PRs #225 + #226 across; this patch rides with them. Before the promotion PR is opened:

1. Complete the 7 smoke tests above on csjones.co/fynla.
2. Verify `deploy/fynla-org/build.sh` + production `.env` still hold the LIVE Revolut `pk` (not sandbox) — the session 67 CSJTODO flagged this as a prerequisite for any production rebuild.
3. Production build uses `./deploy/fynla-org/build.sh` (NOT the csjones script — different base paths).
4. Production upload target is `~/www/fynla.org/public_html/` (standard layout, NOT sibling-dir).
5. Production cache clear: `cd ~/www/fynla.org/public_html && php artisan cache:clear && php artisan optimize`.
