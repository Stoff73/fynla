---
type: handover
mode: context-clear
date: 2026-05-08
session: 3
branch: dev
trigger: context-handover skill (tripwire ~216k / >97.5% of 200k budget)
previous_session: 2026-05-08 session 2 (smoke of all account-deletion fixes GREEN end-to-end on csjones, but post-restore redirect landed on public /pricing — instruction was to land users inside the app on the pricing modal)
---

# Context Clear Handover — 2026-05-08, Session 3

## Immediate state

PR #254 (dev → main release for the account deletion rework) is **OPEN, MERGEABLE, blocked on review only** — admin-merge bypasses that. CSJ has explicitly stated next session must **test on production after the merge**. The post-restore in-app pricing modal change shipped successfully via PR #253 (`bf6ae98`); the dev → main release PR is the last gate before fynla.org gets the account-deletion rework + restore-redirect + lifecycle emails.

## The thread

- Resumed from session-2 handover. Default direction-of-travel was option (a): land restored users on `/dashboard?openPricing=1` and have AppLayout pop the existing dismissable PlanSelectionModal. Implemented exactly that.
- 4-file commit `bf6ae98` on `accountDeletionRework`: `RestoreAccountController.php` (`redirect_to=/dashboard?openPricing=1`), `AppLayout.vue` (`maybeOpenPricingFromQuery()` watcher + mount hook calling existing `handleSubscribeFromOverlay()` then stripping query via `$router.replace`), `Login.vue:436` + `Register.vue:441` fallback aligned to the same path.
- Discovered local `public/hot` was missing — Vite running on :5173 but the trigger file Laravel uses to detect dev mode wasn't there. Restored as `http://localhost:5173` (NOT `http://[::1]:5173` — CSP only allows localhost/127.0.0.1, not IPv6 form). ~3 min of investigation.
- Smoke test on local (user #260 j2@fynla.org, set Password1!): login → RestoreAccountModal → click Restore → /dashboard → "Choose Your Plan" modal visible → Close dismisses → DB shows trashed=no, purge_eligible_at=null → audit chain has `account_restored @ #8390`. ALL GREEN.
- Built csjones bundle (`./deploy/csjones-fynla/build.sh`), rotated `public/build` → `public/build.old` on csjones, rsynced new build, merged old chunks via `cp -rn`, `git pull origin accountDeletionRework`, full cache:clear bundle. csjones at `bf6ae988`.
- Smoke test on csjones (user #26 slaterjoneschris@gmail.com / Password1!): same flow, all GREEN. Modal "Choose Your Plan" with 3 plan cards (Standard/Family/Pro), URL clean (?openPricing=1 stripped), Close button present, audit `account_restored @ #2053`.
- PR #253 admin-merged at 2026-05-08 11:26Z (merge commit `883c084`). csjones switched back to `dev` and pulled (52 commits fast-forward). Migrations idempotent ("Nothing to migrate"). Caches cleared. Smoke `https://csjones.co/fynla/login` 200, `/api/health` 200.
- CSJ asked me to open the dev → main release PR. PR #254 created, body lists all 43 commits / 92 files / +7538/-1150 / 5 migrations / lifecycle emails / cron commands / restore endpoints / RestoreAccountModal + ScheduledDeletionBanner / and the 4 follow-up redirect/throttle fixes from this session and session-2.
- PR #254 came back **CONFLICTING** on `CSJTODO.md` only. main had session-2 wrap (branch cleanup) and session-6 deploy notes appended directly; dev had session-3 / session-4 (account deletion rework) entries. Both narratives legitimate.
- CSJ chose option (1): manual merge on a small branch. Created `csjtodo-merge-main-into-dev` off `dev`, ran `git merge origin/main --no-commit --no-ff`, resolved CSJTODO.md by keeping dev's "Last updated" header + appending main's full session-2 + session-6 narratives above dev's existing session-4/3/5 entries. Two May7 handover docs (handover-2026-05-07-session-1.md, handover-2026-05-07-session-2-clear.md) also came in via the merge.
- PR #255 opened (csjtodo-merge → dev), admin-merged. PR #254 status flipped to MERGEABLE.
- Cleanup: switched local back to `dev`, fast-forwarded (43 commits), deleted `csjtodo-merge-main-into-dev` locally and on origin.

## Files touched this session

```
# Commit bf6ae98 on accountDeletionRework (merged into dev via PR #253):
app/Http/Controllers/Api/Auth/RestoreAccountController.php   (+5/-5  redirect_to → /dashboard?openPricing=1)
resources/js/layouts/AppLayout.vue                           (+31/-0 maybeOpenPricingFromQuery + watcher)
resources/js/views/Login.vue                                 (+1/-1  fallback alignment)
resources/js/views/Register.vue                              (+1/-1  fallback alignment)

# Commit a738fe5 on csjtodo-merge-main-into-dev (merged into dev via PR #255):
CSJTODO.md                                                   (resolved doc-only conflict)
May/May7Updates/handover-2026-05-07-session-1.md             (added — was on main only)
May/May7Updates/handover-2026-05-07-session-2-clear.md       (added — was on main only)
```

## WIP commit

- **No WIP commit produced** — working tree was already clean at handover time. All session work is in proper feature commits already merged into `dev`.
- Carry-over untracked files (intentional, same as previous sessions): `FCA/`, `FCAsuperchargeApp.md`, `FCA-Supercharged-Sandbox-Application-Draft.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`.

## Open decisions

**None.** PR #254 is open, mergeable, with a complete test plan in the body. Next session's job is to ship it and verify on prod.

## Pick up from here (auto-continue contract)

**CSJ's literal instruction for next session: "make sure we test on production at next session".**

Concrete steps to follow:

1. **Confirm PR #254 is still mergeable**: `gh pr view 254 --json state,mergeable,mergeStateStatus`. Should be `OPEN / MERGEABLE / BLOCKED` (BLOCKED is review-only — admin-merge bypasses).
2. **Admin-merge PR #254**: `gh pr merge 254 --merge --admin`. Confirm via `gh pr view 254 --json state,mergedAt,mergeCommit`.
3. **Pull main locally**: `git checkout main && git pull origin main`.
4. **Production database snapshot FIRST**: SSH `ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org`, then `mysqldump ... | gzip > ~/db-snapshot-pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz`. (Pattern from May 6 prod deploy.)
5. **Build for production**: `./deploy/fynla-org/build.sh` (NOT csjones-fynla — different VITE_BASE_PATH and RewriteBase).
6. **Upload `public/build/`** + changed PHP files (everything in the 92-file diff that's PHP, not JS) to `~/www/fynla.org/public_html/`. Use rsync with the production SSH alias. Preserve old chunks via `cp -rn build.old/. build/` pattern same as csjones.
7. **SSH in to fynla.org**: `cd ~/www/fynla.org/public_html && composer install --no-dev 2>&1 | tail -5 && composer dump-autoload -o && php artisan migrate --force` (5 new migrations: `add_deletion_tracking_columns_to_users`, `fix_life_events_joint_owner_id_fk`, `backfill_legacy_purged_users`, `create_account_deletion_reminder_log_table`, `make_scrubbed_columns_nullable`). Then `php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear && php artisan optimize`.
8. **Selective seeders if needed** — TaxConfigurationSeeder, PreviewUserSeeder, etc. only if data went missing. Not expected for this release (no seeder changes).
9. **Browser smoke on https://fynla.org via Playwright**:
   - Login as `chris@fynla.org` / `Password1!` — **CSJ MUST PROVIDE THE VERIFICATION CODE** (production sends it via email, NOT the local DB shortcut). Ask CSJ when the verification screen appears.
   - Verify dashboard renders with all module cards, Net Worth shows real value, Tax 2026/27 active, Profile completeness reasonable.
   - Verify Settings → Privacy renders the new state-aware PrivacySettings.vue (active or scheduled state).
   - **Test the new restore flow on a fresh test account on prod** (CSJ to confirm which test account or whether to use a chris@fynla.org variant): schedule deletion → cancel → banner clears; then schedule deletion → wait → log out → log back in → RestoreAccountModal pops → click Restore → land on `/dashboard` (NOT `/fynla/dashboard` on prod — base is `/`) with PlanSelectionModal "Choose Your Plan" auto-open → Close dismisses.
   - Verify zero JS console errors.
10. **Tail laravel.log for 10–15 min** post-deploy: `tail -f ~/www/fynla.org/public_html/storage/logs/laravel.log`.
11. **AFTER prod is GREEN**: small follow-ups in the PR body's "Tech debt deferred" section can be opened as separate PRs at CSJ's discretion (preview.js:269 hardcoded redirect, RetentionPurgeService schema-coupling test, legacy GDPR routes cleanup, PWA SW skipWaiting config, Auth.md vault doc refresh).

## What the next Claude needs to know

- **PR #254 is the dev → main release PR** for the account deletion rework. 43 commits, 92 files, +7538/-1150. Body has the full test plan. Don't open a new PR — admin-merge this one.
- **CSJ's SSH agent has the fynlaDev key loaded** (`ssh-add -l` shows `Fynla Dev (ED25519)`). For production, the key is `~/.ssh/production` (different file). Both are in the agent OR CSJ's session — use them directly without re-loading.
- **Production = fynla.org, root deployment**, NOT subdirectory. `VITE_BASE_PATH=/build/`, `VITE_ROUTER_BASE=/`. The csjones-fynla build script is for `/fynla/` subdir — DO NOT use it for prod. Use `./deploy/fynla-org/build.sh`.
- **Local dev `public/hot` was missing this session**. If next session hits 404s on Vite assets locally, check `public/hot` exists and contains `http://localhost:5173` (NOT IPv6 form — CSP blocks `[::1]`).
- **Migration safety**: ALL 5 migrations are forward-only (additive columns + FK ALTER + new table + nullable column relaxations). Idempotent. The csjones smoke ran them clean (`Nothing to migrate` after PR #253 merge because csjones already ran them on the feature branch).
- **The 401-interceptor router-base race fix from session 2** (`a4635d0`) is in this release — it removes a stale `/fps/` legacy check from `services/api.js`. If anything weird happens with auth redirects on prod, that's where to look.
- **DataRetentionOverlay sits underneath the new auto-popped modal** — that's by design. The modal is `dismissable=true`, so the user can close it and fall back to the overlay's "Subscribe Now" / "Delete All Data" choices. Don't be confused if a closed modal reveals the overlay countdown banner (e.g. "29 days 21 hours 48 minutes" for grace-period users).
- **CLAUDE.md Rule #15 LOOP UNTIL CORRECT applies to prod testing** — if the prod smoke surfaces a regression (mobile smoke broken, restore flow broken, login broken), diagnose with file:line evidence and fix immediately. Don't hand back. Don't write a "report" mid-loop.
- **Memory `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`** establishes that `gh pr merge --merge --admin` is the standard pattern for solo-reviewer CSJ-authored PRs — don't ask permission per merge once the PR body's test plan has been verified.
- **Memory `feedback_siteground_prod_vhost_no_conditionals.md`** — fynla.org prod vhost silently drops conditional Apache directives. csjones DOES support conditionals. If header logic doesn't fire on prod but works on csjones, it's the vhost difference. Per-route header logic on prod must live in Laravel middleware (the `ApiCacheHeaders` middleware is the canonical example).

## Branch / deploy state

- Branch: `dev` (currently `2153fb2`, just fast-forwarded after PR #255 merge)
- Behind `origin/dev`: 0
- Ahead of `origin/dev`: 0
- PR #253 (accountDeletionRework → dev): **MERGED** at `883c084`
- PR #254 (dev → main release): **OPEN, MERGEABLE, BLOCKED on review** — admin-merge ready
- PR #255 (csjtodo-merge → dev): **MERGED** at `2153fb2`
- Deploy state: csjones.co/fynla on `dev` at `2153fb2`, all caches cleared, healthy. fynla.org production untouched (still on previous main HEAD `3c69ecd`).

## Untracked carry-over (intentional, same as session 1+2)

- `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCAsuperchargeApp.md`, `FCA/`
- `Fynla-Narrative-Memo-Template.docx`
- `May/May1Updates/deployFynFix.md`
- `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`
