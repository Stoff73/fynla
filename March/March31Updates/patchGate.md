# Production Patches — Feature Gating

**Date:** 31 March 2026
**Server:** fynla.org (SiteGround)

All patches applied via SSH during the feature gating deployment.

---

## Patch 1: Fix nested build directory

**When:** Immediately after frontend build upload
**Problem:** `scp -r public/build/ target:public/build/` created `public/build/build/` instead of replacing `public/build/`
**Impact:** Production served old frontend — no feature gating visible

**Commands run on production:**
```bash
cd ~/www/fynla.org/public_html

# Remove old build assets
rm -rf public/build/assets public/build/manifest.json public/build/manifest.webmanifest public/build/registerSW.js public/build/sw.js

# Move new build from nested directory to correct location
mv public/build/build/* public/build/

# Clean up empty nested directory
rm -rf public/build/build

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

**Verified:** `grep -o "isLocked\|requiredPlan" public/build/assets/AppLayout*.js | head -5` returned matches.

---

## Patch 2: Temporary subscription change for testing

**When:** During production browser testing
**Problem:** chris@fynla.org was on `pro/active` — needed a lower tier to verify gating works
**Impact:** None (temporary, restored immediately)

**Commands run on production:**
```bash
# Set to student for testing
php artisan tinker --execute="
\$u = \App\Models\User::where('email','chris@fynla.org')->first();
\$s = \$u->subscription;
\$s->update(['plan' => 'student']);
echo 'Changed to: ' . \$s->fresh()->plan . '/' . \$s->fresh()->status;
"
# Output: Changed to: student/active

# Browser tested: 11 items greyed, tooltip working, upgrade link visible

# Restored to pro immediately after testing
php artisan tinker --execute="
\$u = \App\Models\User::where('email','chris@fynla.org')->first();
\$s = \$u->subscription;
\$s->update(['plan' => 'pro']);
echo 'Restored to: ' . \$s->fresh()->plan . '/' . \$s->fresh()->status;
"
# Output: Restored to: pro/active
```

**Verified:** Refreshed dashboard — all sidebar items accessible again for pro tier.

---

## No Other Patches

No database migrations, no .env changes, no config changes, no seeder runs. All patches were either fixing a deployment issue (build directory) or temporary test data changes (restored immediately).
