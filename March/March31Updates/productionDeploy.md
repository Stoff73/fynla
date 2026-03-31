# Production Deploy — Session 20 (31 March 2026)

**Deployed by:** Claude Code via SSH MCP
**Date:** 31 March 2026
**Target:** fynla.org (production)

---

## What Was Deployed

### Frontend Build
- Built locally with `./deploy/fynla-org/build.sh` (289 files, 6.4MB)
- Uploaded to production via `rsync` over SSH
- Path: `~/www/fynla.org/public_html/public/build/`

### PHP Files Changed
| File | Change |
|------|--------|
| `app/Http/Controllers/Api/PaymentController.php` | Added `PLAN_ORDER` constant, `upgradeSubscription()` method, patched `confirmPayment()` with `$isUpgrade` logic |
| `app/Models/Payment.php` | Added `upgrade_from_plan` to `$fillable` |
| `app/Models/Subscription.php` | Added `status` and `revolut_order_id` to `$fillable` |
| `routes/api.php` | Added `POST /payment/upgrade` route |

### Database Migration
| Migration | Action |
|-----------|--------|
| `2026_03_31_144649_add_upgrade_from_plan_to_payments_table` | Added `upgrade_from_plan` nullable string column to `payments` table |

### Server Commands Run
```bash
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
php artisan db:seed --force
```

---

## Deployment Method

The `mcp__ssh-fynla__ssh_upload_file` tool silently failed to persist PHP files (reported success but files were not written). All PHP changes were made via:

1. **`sed` commands** for small insertions (route line, fillable fields, constant)
2. **Heredoc + `cat >` via `ssh_exec`** for the migration file
3. **Python patch script via `ssh_exec`** for the `confirmPayment()` upgrade logic
4. **`rsync` via local Bash** for the frontend build (too large for SSH upload tool)

See `mcpFix.md` for details on the tool failures.

---

## Production Testing Results

| Test | Result |
|------|--------|
| Landing page loads | PASS |
| Login with chris@fynla.org + verification code | PASS |
| Dashboard renders (Net Worth, Protection, Savings, etc.) | PASS |
| Expired trial shows "Your Trial Has Ended" modal | PASS |
| Modal shows all 4 plans with launch pricing | PASS |
| Active Student subscriber sees "Upgrade Now" in navbar + sidebar | PASS |
| Upgrade modal filters to higher tiers only | PASS |
| Checkout page shows correct proration (Student to Family, 9 months = £90.00) | PASS |
| Revolut payment widget loads (Revolut Pay, Card, Google Pay) | PASS |
| Route `POST /api/payment/upgrade` registered and working | PASS |

**Not tested:** Actual payment completion (would charge real money on sandbox/production).

---

## Post-Deploy Account Fix

`chris@fynla.org` subscription was set to `pro` + `active` + `yearly`, valid until 31 March 2027.

---

## Files NOT Deployed (no changes needed on server)

- `.claude/` config files (local dev only)
- `.gitignore`, `.mcp.json` (local dev only)
- `CLAUDE.md`, `CSJTODO.md` (documentation only)
- `tests/Feature/Payment/UpgradeSubscriptionTest.php` (test file, not needed on production)
- `March/March31Updates/*.md` (vault docs)
