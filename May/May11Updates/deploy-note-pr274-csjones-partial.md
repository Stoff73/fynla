---
type: deploy-note
date: 2026-05-11
session: 7 (post-handover auto-resume)
pr: 274
branch: fix/settings-dedup-and-family-gating
target: csjones (https://csjones.co/fynla)
outcome: partial GREEN — CSJ accepted, admin-merged
---

# Deploy Note — PR #274 csjones smoke (partial GREEN, accepted)

## What was deployed

- Branch `fix/settings-dedup-and-family-gating` at `2b18a4636` (code commit `ada5af2c0`)
- Built locally via `./deploy/csjones-fynla/build.sh` (1m 33s, 8.9M bundle, app.js 1.21 MB / gzip 335 KB, 344 PWA precache entries)
- Uploaded `public/build/` to `~/www/csjones.co/fynla-app/public/build/` via scp, rotated previous build to `public/build.old.session6`, merged old chunks with `cp -rn` to preserve in-flight sessions
- SSH csjones: `git fetch + git checkout fix/settings-dedup-and-family-gating`, cache:clear / config:clear / view:clear / route:clear, composer dump-autoload -o, php artisan optimize — clean

## Browser smoke (`john@example.com`, csjones id=11, MFA `781722`)

### Verified GREEN

- **Scenario 1 — trial+standard.** `john@example.com` on csjones is `status=trialing, plan=standard, trial_ends_at=2027-05-05`.
  - `/settings` shows 9 tabs incl. **Family** ✅
  - Left sidebar (`SideMenu.vue`) has NO "Choose a Plan" / "Upgrade Now" button — only Account + Sign Out at bottom ✅ (dedup confirmed)
  - `/settings/family` renders Jane Smith with "Account Linked" badge ✅
- **Scenario 3 — top-nav trial banner.** Banner "Free trial ends in 359 days" + "Choose a Plan" CTA still render top-right of Settings header ✅. `/settings` General-tab Account Status row "Free Trial (359 days remaining)" + "Choose a Plan" button still renders ✅.

### Not exercised on csjones

- **Scenario 2 — active+standard.** Required `Subscription::where('user_id',11)->update(['status'=>'active'])` on csjones. Claude Code auto-classifier blocked the staging-DB write as an unauthorised shared-resource modification. CSJ accepted the partial result on the basis that:
  - The active-path `effectivePlan === 'standard'` branch is the same `hasFeatureAccess(effectivePlan, 'family')` code path that was browser-verified across all four states in session 6 on local DB
  - Risk delta is low — the SettingsTabBar filter + FamilySettings route-guard backstop were both visually inspected at deploy time and the source is identical between local and csjones
- **Scenario 4 — revert to trialing baseline.** Not needed because scenario 2 never wrote.

## csjones schema note

`subscriptions` table column is `plan` (not `plan_tier`). john's row:
```
plan=standard, billing_cycle=monthly, status=trialing,
trial_started_at=2026-05-05, trial_ends_at=2027-05-05,
current_period_start=2026-05-05, current_period_end=2027-05-05
```

Saved here for the next person who tries to flip a csjones subscription via tinker — don't assume local schema parity.

## Post-smoke

CSJ accepted partial GREEN. Admin-merging PR #274 → dev now, then syncing csjones back to dev.

After merge, dev contains: PR #272 (settings hub unification) + PR #273 (family-members linked-spouse fix) + PR #274 (dedup + family-gating restore).
