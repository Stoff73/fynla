# Deploy Guide — UI Fixes (PR #226, branch `genUIFixes`)

**Status:** ✅ DEPLOYED + browser-tested on csjones.co/fynla — 23 April 2026 (per CSJ)
**Merged to dev:** merge commit `416e770`
**Target environment:** csjones.co/fynla (dev)
**PR:** <https://github.com/Stoff73/fynla/pull/226>
**Branch:** `genUIFixes` → `dev`

> ⚠️ **Before you rebuild**, if you have an incognito session open on csjones.co/fynla for any other testing, read "Warn before rebuilding" at the bottom of this guide. Use the preserve-old-chunks upload pattern.

---

## What this ships

1. Logout redirects straight to `/login` — the success modal is gone.
2. Dashboard progress hero shows for every user (skip-to-dashboard / Fyn-onboarded users no longer see a blank top of page). Journey users unchanged.
3. Pension and Investment Add/Edit forms collapse advanced fields (fees, expected return, planned lump sum, country, platform, beneficiaries, holdings) behind a single "Additional information" link. Auto-expands in edit mode when those fields already have a value.
4. Net Worth wealth summary for joint users: two per-person donuts inline, combined bar chart below with a per-user tooltip split.
5. Root-cause fix for the recurring "Partner" / "Spouse" regression — `userProfile/spouse` getter now returns a normalised `name` field on every code path.
6. Local `.claude` skill config flip — **not deployed**.

---

## Scope boundaries

- **No backend changes.** No controllers, services, migrations, seeders, or routes modified.
- **No Composer changes.** `composer.json` / `composer.lock` unchanged.
- **No npm changes.** `package.json` / `package-lock.json` unchanged.
- **No `.env` changes.** No new env vars.
- **No artisan commands required** beyond the standard cache clears.
- **No `.htaccess` changes.**
- **No cron / queue / infrastructure changes.**

---

## Files changed (from `git diff origin/dev..genUIFixes --name-status`)

All changes are frontend-only Vue / JS / Vuex. The only surface that needs to be uploaded is the compiled `public/build/` directory — the `.vue` source files are build-time inputs, not runtime assets.

### Modified (11 files that compile into `public/build/`)

| File | Change |
|---|---|
| `resources/js/views/Dashboard.vue` | Hero wrapper `v-if` removed so it renders for all users |
| `resources/js/components/AppNavbar.vue` | Logout redirects immediately; `LogoutSuccessModal` import, registration, template usage, `showLogoutModal` ref, and `handleLogoutModalClose` handler removed |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Scenario column hidden when no currentStage; adds matching left + right margin spacers; collapsed bar shows overall profile % when no journey; mobile carousel recount |
| `resources/js/components/Retirement/DCPensionForm.vue` | `showAdditionalInfo` state + toggle; wraps Lump Sum / Expected Return / Platform Fee / Advisor Fee / Beneficiary / Holdings sections; null-on-save when collapsed; `expected_return_percent` default `5.0` → `null`; `hasAdditionalInfoData()` auto-expand for edit mode |
| `resources/js/components/Investment/AccountForm.vue` | `showAdditionalInfo` state + toggle; wraps Holdings editor; passes `showAdditionalInfo` prop to `StandardInvestmentFields`; null-on-save for Country / Platform / Planned Lump Sum / Platform Fee / Holdings when collapsed; auto-expand for edit mode |
| `resources/js/components/Investment/StandardInvestmentFields.vue` | Accepts `showAdditionalInfo` prop; wraps Country Selector, Platform input, Planned Lump Sum (non-ISA variant), Platform Fee section, ISA Planned Lump Sum variant |
| `resources/js/components/Investment/PortfolioOverview.vue` | `getSpouseName` now reads from `userProfile/spouse`, falls back to auth inline spouse, "Spouse" as last resort |
| `resources/js/components/NetWorth/NetWorthWealthSummary.vue` | Joint users branch: two donut grid + full-width bar chart below, combined-household props fed to bar chart; added `combinedLiabilitiesBreakdown` / `combinedTotalAssets` / `combinedTotalLiabilities` computeds; `spouseUserName` reads `userProfile/spouse` first |
| `resources/js/components/NetWorth/AssetBreakdownBar.vue` | Optional `userBreakdown` / `spouseBreakdown` / `userLiabilities` / `spouseLiabilities` / `userName` / `spouseName` props; custom tooltip renderer for joint mode; single-user tooltip unchanged |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | `spouseNameForLetter` now reads from `userProfile/spouse` |
| `resources/js/store/modules/userProfile.js` | `spouse` getter: every return path normalised through a `withName` helper so `name` is always resolved from `first_name` + `last_name` when the underlying record doesn't already have one |

### Deleted (1 file)

| File | Notes |
|---|---|
| `resources/js/components/Auth/LogoutSuccessModal.vue` | Orphan after the logout-redirect change in `AppNavbar.vue`. Not referenced anywhere else in the codebase. No server-side implication — Laravel serves `public/build/` not the Vue source. |

### Not deployed

| File | Reason |
|---|---|
| `.claude/skills/vault-sync/SKILL.md` | Local tooling (Claude Code skill config). Never uploaded to servers. |

---

## Deployment steps

### 1. Pre-flight — confirm which branch the dev server is actually running

Per `feedback_dev_server_is_separate.md`, csjones.co/fynla may be running a feature branch, not `dev`. Ask CSJ before uploading. The rest of this guide assumes the intent is to deploy `dev` (after PR #226 is merged).

### 2. Merge PR #226 to `dev`

Via GitHub UI — admin-merge as `@Stoff73` since both branches are protected.

### 3. Build locally on your machine

```bash
git checkout dev
git pull origin dev
./deploy/csjones-fynla/build.sh
```

**Do not** use `npx vite build` or `npm run build` — the dev-environment build script sets `VITE_BASE_PATH=/fynla/build/` and `VITE_ROUTER_BASE=/fynla/` which are critical for the csjones subdirectory deployment.

After the build completes, verify:

```bash
ls public/build/assets/ | head -5
cat public/build/manifest.json | head -20
```

### 4. Upload `public/build/` preserving old chunks

**Read the "Warn before rebuilding" section at the bottom first if anyone is mid-session on csjones.co/fynla.**

SSH access to csjones dev (per `reference_csjones_ssh_access.md`):

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
```

Once on the server (per `reference_csjones_sibling_dir.md` — Laravel app is in the sibling dir, NOT `public_html/fynla` which is a symlink):

```bash
cd ~/www/csjones.co/fynla-app
# Preserve-old-chunks pattern so any in-flight incognito sessions survive
rm -rf public/build.old
mv public/build public/build.old
```

Back on your machine, upload the new `public/build/` to `~/www/csjones.co/fynla-app/public/build/` via SiteGround File Manager or rsync.

Back on the server, merge old chunks alongside new ones:

```bash
cd ~/www/csjones.co/fynla-app
cp -rn public/build.old/. public/build/
```

`cp -rn` (no-clobber) keeps the new `manifest.json` / new `app-*.js` / new chunk hashes intact. Missing old chunks referenced by in-flight sessions get filled in from `.old/`. New sessions load the new manifest and new chunks.

### 5. Finalise on the server

```bash
cd ~/www/csjones.co/fynla-app
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

**No migrations needed.** There are no database schema changes in this PR.

### 6. Smoke test on <https://csjones.co/fynla>

Open a fresh incognito window. Log in as `chris@fynla.org` / `Password1!` (ask for the verification code if it prompts) or the David & Sarah Mitchell preview persona from the landing page.

Run through each fix:

1. **Logout** — click Sign Out in the top-right user dropdown. Expected: immediate redirect to `/login`, no success modal.
2. **Dashboard progress hero** — with a user that has no active life stage (e.g. `chris@fynla.org`), load `/dashboard`. Expected: pink "Good evening, Name" hero with Profile Completeness + Fyn's Recommended Actions side by side, matching empty left and right margins.
3. **Pension Add form** — `/net-worth/retirement` → Add Pension → select SIPP. Expected: short form (no fees, no expected return, no lump sum, no beneficiary, no holdings). Click "Show additional information". Expected: the five hidden sections appear. Click again to collapse.
4. **Investment Add form** — `/net-worth/investments` → Add Account → select GIA. Expected: no Country, no Platform, no Planned Lump Sum, no Platform Fee. Expand. Expected: all four appear. Switch type to ISA. Expected: ISA Planned Lump Sum is collapsed; expand it to confirm it reveals.
5. **Joint Net Worth** — log in as David Mitchell preview persona → `/net-worth/wealth-summary`. Expected: two donuts inline ("David Mitchell's Asset Allocation" / "Sarah Mitchell's Asset Allocation"), then full-width "Assets & Liabilities" bar chart below. Hover the Property bar. Expected tooltip:
   - `Property: £1,393,000`
   - `David Mitchell: £755,500`
   - `Sarah Mitchell: £637,500`
6. **Spouse name regression** — verify on the same joint view that every surface reads "Sarah Mitchell" (not "Partner"): donut title, Wealth Summary table column header, bar chart tooltip. Then check `/protection` and `/net-worth/investments` for any remaining "Spouse" / "Partner" fallbacks.

### 7. Clean up `public/build.old/` after 24h

Once you're satisfied no one is still on a pre-rebuild session:

```bash
cd ~/www/csjones.co/fynla-app
rm -rf public/build.old
```

---

## Rollback

This is a frontend-only change with no DB mutations. To revert, redeploy the previous `public/build/` (the `public/build.old/` kept from step 4 is the quickest way, provided you haven't deleted it yet):

```bash
cd ~/www/csjones.co/fynla-app
rm -rf public/build.new
mv public/build public/build.new
mv public/build.old public/build
php artisan cache:clear
```

If you've already pruned `public/build.old`, rebuild the previous commit locally (`git checkout <previous-dev-sha> && ./deploy/csjones-fynla/build.sh`) and re-upload.

---

## Verification after deploy

Run these quick checks from your own machine:

```bash
# 1. HTML served OK
curl -I https://csjones.co/fynla/ | head -5

# 2. App bundle loads with expected base path
curl -s https://csjones.co/fynla/build/manifest.json | head -5

# 3. No regressed CSP blocking asset loads (per feedback_htaccess_vs_middleware_headers)
curl -sI https://csjones.co/fynla/ | grep -i content-security-policy
```

---

## Warn before rebuilding

Rebuilding `public/build/` while a user has an incognito session open invalidates in-flight dynamic imports (chunk hashes change). If CSJ or anyone else is mid-flow on csjones.co/fynla when you deploy:

> "Rebuilding and redeploying `public/build/` will invalidate your current incognito session's dynamic imports — post-navigation will break mid-flow. Want me to pause until you're ready to refresh, or proceed and preserve old chunks so most routes keep working?"

The preserve-old-chunks upload pattern in step 4 lets most routes keep resolving for in-flight sessions. Any route whose compiled code changed in this PR (Dashboard, DCPensionForm, AccountForm, NetWorthWealthSummary) will still need a page reload to pick up the new bundle.

---

## Not exercised end-to-end before this deploy

- **Collapsed-form submit → DB verification.** The null-on-save code path in DCPensionForm `buildDCPayload()` and AccountForm `submitForm()` is reviewed in diff but not exercised by actually saving a collapsed form and then inspecting the DB row.
- **Edit-mode auto-expand** on an existing account/pension that has hidden-field values populated. The `hasAdditionalInfoData()` computed logic is reviewed in diff only.
- **Onboarding path** for both the pension and investment forms. Both accept `isOnboarding` prop but only the standalone modal path was browser-tested.

Worth exercising during dev smoke test before promoting to `main`.

---

## Promoting to production (fynla.org)

**Do not deploy this directly to fynla.org.** Per `feedback_main_via_dev_only.md`, nothing reaches main without first being tested on dev.

After dev testing passes:

1. Open PR `dev → main` (CSJ opens and approves this)
2. Merge
3. `git checkout main && git pull`
4. `./deploy/fynla-org/build.sh` (NOT the csjones build script — base paths differ)
5. Upload `public/build/` to `~/www/fynla.org/public_html/` using the same preserve-old-chunks pattern
6. SSH and run `php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`
7. Smoke test <https://fynla.org>
8. Monitor `storage/logs/laravel.log` for 10–15 minutes

Production SSH (per CLAUDE.md): `ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org`, path `~/www/fynla.org/public_html/` (standard layout, not sibling-dir).
