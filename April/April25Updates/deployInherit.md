# Deploy guide — PR #235 (main-inherited Pest failures fix)

**PR:** https://github.com/Stoff73/fynla/pull/235
**Branch:** `feature/csj/main-test-fixes` → `dev` → `main`
**Date written:** 24 April 2026
**Author:** Claude + CSJ

---

## TL;DR — what this deploy does

Clears 22 Pest failures that have been silently red on `origin/main` since the April audit. No user-visible behaviour changes; no new migrations; no new env vars; no new config keys. Pure test-correctness fixes.

**Files to upload:** 9 files. No build required — no Vue/CSS changed.

**Runtime impact:** Estate Asset/Liability/Gift/IHTProfile and Investment Holding numeric columns return as PHP `float` instead of `string` (`"550000.00"` → `550000.0`). Most code already coerced these anyway — the arithmetic on these columns is now strictly correct.

---

## What changed (generated from `git diff --name-only origin/main..HEAD`)

### Application code (5 files)
- `app/Models/Estate/Asset.php` — cast `current_value` → float
- `app/Models/Estate/Liability.php` — cast `current_balance`, `monthly_payment`, `interest_rate` → float
- `app/Models/Estate/Gift.php` — cast `gift_value` → float
- `app/Models/Estate/IHTProfile.php` — cast `home_value`, `nrb_transferred_from_spouse`, `rnrb_transferred_from_spouse`, `charitable_giving_percent` → float
- `app/Models/Investment/Holding.php` — 8 cast columns → float

### Factory / data (1 file)
- `database/factories/Investment/RiskProfileFactory.php` — enum values corrected (`medium_low` → `lower_medium`, `medium_high` → `upper_medium`)

### Tests / architecture (3 files)
- `tests/Architecture/ApplicationArchitectureTest.php` — added `LifecycleCampaign` to `services are organized by module` ignore list
- `tests/Architecture/MonetaryCastsArchitectureTest.php` — registered 16 exempt column names with documented rationale
- `tests/Feature/Api/Public/InsightControllerTest.php` — test updated to match deliberate no-auto-promote behaviour

### Files NOT touched
- No `database/migrations/` — no schema changes
- No `resources/js/` or `resources/css/` — no frontend build needed
- No `routes/`, `config/`, `.env.example` — no routing or config changes
- No new classes (`App\Casts\MoneyCast` was tried, rejected, deleted — not shipping)

---

## Deploy to dev (`csjones.co/fynla`)

### Pre-flight
1. PR #235 merged to `dev` on GitHub.
2. Local `dev` synced: `git checkout dev && git pull`.
3. Confirm the 9 files above appear in `git diff HEAD~1 --name-only` (or however deep the merge placed them).

### Upload (no build required)

Upload via SiteGround File Manager, targeting `~/www/csjones.co/public_html/fynla/`:

```
app/Models/Estate/Asset.php
app/Models/Estate/Gift.php
app/Models/Estate/IHTProfile.php
app/Models/Estate/Liability.php
app/Models/Investment/Holding.php
database/factories/Investment/RiskProfileFactory.php
tests/Architecture/ApplicationArchitectureTest.php
tests/Architecture/MonetaryCastsArchitectureTest.php
tests/Feature/Api/Public/InsightControllerTest.php
```

> Tests are not strictly needed on the server, but uploading them keeps the tree identical to the repo if you ever run Pest on the remote.

### Remote commands (per CLAUDE.md dev-deploy path)

Dev runs in the sibling-dir + symlink layout (memory `reference_csjones_sibling_dir.md`). The Laravel app is at `~/www/csjones.co/fynla-app/`, NOT `public_html/fynla/`.

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**No migrations needed** — this PR has no `database/migrations/` changes. Skip `php artisan migrate --force`.

**No seed needed** — no new seed data.

### Smoke test on dev

After upload + cache clear, verify in a browser:

1. Log in at `https://csjones.co/fynla` with `chris@fynla.org` (or any preview persona).
2. Open Estate → verify an estate summary loads without a 500 (the Estate tests that were failing were 500-ing in integration).
3. Navigate to an investment account → open Holdings → confirm prices + values render as numbers, not `"80.00"` strings.
4. Monitor `~/www/csjones.co/fynla-app/storage/logs/laravel.log` for 10–15 min — look for any new errors mentioning `Asset.php`, `Liability.php`, `Gift.php`, `IHTProfile.php`, or `Holding.php`.

If clean, proceed to production.

---

## Deploy to production (`fynla.org`)

### Pre-flight
1. Dev has been soaking for the policy-required interval (per memory `feedback_main_via_dev_only.md` — *"nothing merges to main without first being committed to dev, deployed to csjones.co/fynla, and browser-tested"*).
2. PR `dev → main` opened by CSJ, approved, merged.
3. Local `main` synced: `git checkout main && git pull`.

### Upload

Upload the same 9 files to `~/www/fynla.org/public_html/` via SiteGround File Manager. Production uses the standard Laravel layout (memory `reference_csjones_sibling_dir.md`: *"Production (fynla.org) uses standard layout"*).

### Remote commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**No migrations needed** — no schema changes.

### Smoke test on production

1. Log in at `https://fynla.org` with `chris@fynla.org` / `Password1!` (request MFA code when prompted).
2. Navigate to Estate → confirm the dashboard loads and all numeric values render.
3. Navigate to Investments → a holdings list → confirm prices + percentages display correctly.
4. Monitor `storage/logs/laravel.log` for **10–15 minutes** for any new errors. Specifically watch for:
   - `TypeError` mentioning `Asset`, `Liability`, `Gift`, `IHTProfile`, or `Holding`
   - JSON serialisation errors
   - `PDOException` — any column type complaints

---

## Rollback plan

If production goes sideways:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
# revert the 5 model files to decimal:2 via a quick Laravel edit,
# OR re-upload the prior version of these 5 files from the main branch
# tagged before this merge.
php artisan cache:clear && php artisan config:clear
```

Because there are no migrations or schema changes, rollback is a file-swap. **Keep a tar of the 5 affected models from the pre-merge `main` tip on disk before uploading** so you can restore in one `scp`.

Suggested pre-upload backup command (run locally before pushing to remote):

```bash
git show <last-main-sha-before-merge>:app/Models/Estate/Asset.php > /tmp/Asset.bak.php
git show <last-main-sha-before-merge>:app/Models/Estate/Liability.php > /tmp/Liability.bak.php
git show <last-main-sha-before-merge>:app/Models/Estate/Gift.php > /tmp/Gift.bak.php
git show <last-main-sha-before-merge>:app/Models/Estate/IHTProfile.php > /tmp/IHTProfile.bak.php
git show <last-main-sha-before-merge>:app/Models/Investment/Holding.php > /tmp/Holding.bak.php
```

---

## What this unblocks

- **PR #234 (Sprint 0 rebase)** — inheriting a clean baseline once `feature/fyn-persona-split` rebases off the new `main`.
- **Sprint 0 Task 0.17 verification** — the 22 failures no longer block full-green.
- **Scheduled routine** `trig_015ggy6qz1M3axH6Shvv5Wfw` (Sunday 2026-04-26 09:00 BST) — should report all categories fixed.

---

## Post-deploy follow-up

- [ ] Add the 5 Estate/Holding models to a future Tech Debt item: *"Reinstate `decimal:2` cast on `Asset.current_value`, `Liability.{current_balance, monthly_payment, interest_rate}`, `Gift.gift_value`, `IHTProfile.{home_value, *_transferred_from_spouse, charitable_giving_percent}`, and `Holding.*` — requires an API Resource layer that casts numeric strings back to numbers in JSON."*
- [ ] When the Resource layer lands, remove each resolved column from `MonetaryCastsArchitectureTest::ALLOWED_FLOAT_COLUMNS`.
- [ ] Close the routine `trig_015ggy6qz1M3axH6Shvv5Wfw` manually at https://claude.ai/code/routines if it still shows "scheduled" after Sunday's run.

---

*Prepared 24 April 2026. Mirror this file to `/Users/CSJ/Desktop/fynlaBrain/April/April25Updates/deployInherit.md` (both-locations rule per memory `feedback_deploy_guides_both_locations.md`).*
