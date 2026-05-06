---
type: handover
mode: context-clear
date: 2026-05-06
session: 5
branch: dev
previous_session: 2026-05-06-session-4 (context-clear)
---

# Context Clear Handover — 2026-05-06, Session 5

## Immediate state

**PR #245 (`dev → main` release) is OPEN, MERGEABLE, REVIEW_REQUIRED, BLOCKED.** No source changes this session — the entire session was: open the production release PR for CSJ to review. Next session's goal: get **local + dev + production all in sync** by merging PR #245 and executing the production deploy in `May/May6Updates/deploy-2026-05-06-session-4-prod.md`.

## The thread

- Session-start verified clean state: branch `dev` in sync with `origin/dev` (0/0), DB seeded, dev server running on :8000 + :5173, no worktrees, no conflict markers, no pending migrations. Read session-4 handover in full and surfaced the production-deploy decision waiting on CSJ.
- CSJ asked: "create the dev to production pr for review."
- Verified no existing `dev → main` PR open. `gh auth` good. `composer.lock` HAS changed → flagged in PR body for `composer install` on prod.
- Opened **PR #245**: `Release: dev → main — May 6 release (insights cache fix, /storage route, csjones git checkout)`. 60 commits, 179 541 additions, 5 733 deletions, 34 migrations.
- PR body contains: highlights, the 2 destructive migration flags (`drop_is_eval_user_from_users`, `rename_eval_user_id_to_preview_user_id`), `composer.lock` flag, selective-seeder allowlist (4 only — never test/preview/admin seeders), full smoke checklist, rollback plan, pre-recon tag references.
- Did **not** merge — only `@Stoff73` does that, per CLAUDE.md branch protection.

## Files touched (uncommitted or recently committed)

### Committed this session
None. PR creation is GitHub state, not local git state. The `dev` branch is unchanged at `53e1cea` (= `origin/dev`).

### Created (will be committed in session-end Phase 10)
- `May/May6Updates/handover-2026-05-06-session-5-clear.md` — this file
- `CSJTODO.md` — updated with session-5 status

### GitHub state
- PR #245: `https://github.com/Stoff73/fynla/pull/245`
- State: OPEN · Mergeable: MERGEABLE · Review: REVIEW_REQUIRED · Merge state: BLOCKED (on review)
- Base: `main` · Head: `dev` · 60 commits ahead

## What the next Claude needs to know

1. **PR #245 is the entire production release.** Don't open another PR. Don't push more commits to `dev` before the merge unless CSJ explicitly says so — every new commit to `dev` widens the diff that's about to merge to `main`.

2. **Only `@Stoff73` (CSJ) can merge to `main`.** Branch protection enforces it. The next Claude does NOT merge — wait for CSJ to do it, then proceed with the deploy steps.

3. **The deploy spec is the canonical script for next session.** Path: `May/May6Updates/deploy-2026-05-06-session-4-prod.md`. Do NOT improvise — every irreversible action (DB snapshot, migration order, seeder selection) is in there for a reason.

4. **Local + dev are byte-identical from session 4.** Production is the only one out of sync. The next session's whole job is closing that gap. After deploy + soak, the optional follow-up is converting production to a git checkout (same recipe as `deploy/csjones-fynla/BOOTSTRAP.md` §12) so all three environments deploy the same way.

5. **`composer.lock` changed in this release** — first prod step after pulling `main` is `composer install --no-dev --optimize-autoloader --no-interaction` BEFORE `php artisan migrate`. Otherwise some migrations may fail to load classes.

6. **Take a SiteGround DB snapshot before `migrate --force` on prod.** Two destructive migrations (`drop_is_eval_user_from_users`, `rename_eval_user_id_to_preview_user_id`). The deploy spec's rollback plan assumes you have one.

7. **Selective seeders only on prod** (4 allowed): `TaxConfigurationSeeder`, `DiscountCodeSeeder`, `SavingsActionDefinitionSeeder`, `NewsArticleSeeder`. NEVER `TestUsersSeeder`, `ChrisUserSeeder`, `PreviewUserSeeder`, `LifecycleTestSeeder`, `AdminUserSeeder` — they create test/preview accounts.

8. **Vault sync flagged minor drift** (advisory, non-blocking):
   - `CLAUDE.md` Vue Components: 726 documented vs 722 actual (-4). Update opportunistically.
   - `Current State/DeploymentBuild.md` last updated 2026-04-14 — should reflect csjones git-pull flow finalised in session 4. Refresh after the production deploy is green.

## Pick up from here

**CSJ's stated next-session goal: "have local, dev and production all synced and inline."** Concretely:

1. **Confirm PR #245 review state** — `gh pr view 245`. If still REVIEW_REQUIRED, prompt CSJ. If APPROVED + MERGEABLE, prompt CSJ to merge.
2. After CSJ merges: `git checkout main && git pull origin main && git log -1 --oneline` (should be the merge commit).
3. **Build production SPA bundle:** `./deploy/fynla-org/build.sh`. Verify `public/build/manifest.json` paths start with `/build/` (not `/fynla/build/`).
4. **CSJ takes SiteGround DB snapshot** (Site Tools → MySQL → Backups) before any destructive migration runs.
5. **Upload** `public/build/` + production `public/.htaccess` + rsync `app/` / `routes/` / `config/` / `database/` to `~/www/fynla.org/public_html/`.
6. **SSH to prod:** `composer install --no-dev --optimize-autoloader --no-interaction && composer dump-autoload -o && php artisan migrate --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`
7. **Run the 4 selective seeders** listed above.
8. **Smoke test** per the deploy spec checklist:
   - `curl -sI https://fynla.org/api/insights | grep -i cache-control` → `no-store, no-cache, private, must-revalidate, max-age=0`
   - Login `chris@fynla.org` (CSJ provides verification code), dashboard renders, `/insights` renders cover images (no 403s), no JS console errors
   - `app()->environment()` = `production`, `config('services.revolut.sandbox')` = false, `LIFECYCLE_TEST_RECIPIENT` unset
   - Tail `storage/logs/laravel.log` 10–15 min for new errors
9. **Optional follow-up (after ~24h soak):** convert production fynla.org to a git checkout tracking `origin/main` using the same recipe as csjones (`deploy/csjones-fynla/BOOTSTRAP.md` §12, with `branch=main` and no `skip-worktree` on `public/.htaccess` since prod uses the canonical root template). After that, all three environments — local, dev, production — deploy via `git pull`. Goal achieved.

## Outstanding (carries from session 4 + earlier)

### NEXT SESSION (production deploy)
- [ ] Confirm PR #245 review state, CSJ merges, execute deploy spec end-to-end
- [ ] SiteGround DB snapshot before `migrate --force`
- [ ] `composer install --no-dev` BEFORE migrate (composer.lock changed)
- [ ] Selective seeders only (4)
- [ ] Smoke test full checklist
- [ ] Optional: convert production to git checkout post-soak

### Lower priority (carries)
- [ ] **Legacy CDN entry on csjones for bare `/api/insights`** — one-time SiteGround Site Tools → Speed → Caching → Dynamic Cache → Purge. After purge, the SPA cachebuster `_t=Date.now()` line in `resources/js/services/insightsService.js` can be reverted (one-line removal).
- [ ] **`appMapping/currentState/*.md` refresh** — 26 docs stale at 2026-03-02/12 mtime. Surgical edits in repo only, never via vault.
- [ ] **`ProtectionDashboard.vue`** — 7 Vue render warnings (`Failed to resolve component: ProfileCompletenessAlert`, etc.). Pre-existing one-file PR.
- [ ] **CLAUDE.md metric drift** — Vue Components 722 actual vs 726 documented (-4). Vault-sync confirmed both this session and session 4. Update opportunistically.
- [ ] **`Current State/DeploymentBuild.md` refresh** — last touched 2026-04-14, should reflect csjones git-pull flow + (post-deploy) production git checkout. Update after production deploy is green and soaked.

## Tech debt found this session

None. No source files changed — only PR creation. Tech-debt audit deferred to next session post-deploy.

## Untracked at session end (carried, intentional)

```
FCA-Supercharged-Sandbox-Application-Draft.md
FCA/
FCAsuperchargeApp.md
Fynla-Narrative-Memo-Template.docx
May/May1Updates/deployFynFix.md
campaigns/   fyn/   personas/   prompts/   tools/
```

May 1 Fyn AI prompt-engineering scratch dirs and FCA application drafts. Not part of any current work.

## Context hints

- Active branch type: mainline (on `dev`)
- `dev` HEAD: `53e1cea` (= `origin/dev`)
- `dev` ahead of `main`: 60 commits (PR #245)
- `main` HEAD: `fe77a774` (last release)
- Last commit on `dev`: `53e1cea` `docs(session): context-clear handover 2026-05-06-session-4 + production deploy spec`
- csjones HEAD (live): `bb6458a` — next `git pull origin dev` brings it to `53e1cea` (no functional code change vs current; only docs commits since)
- csjones `public/.htaccess`: `skip-worktree` flag set, holds dev `/fynla/` rewrite-base template (`deploy/csjones-fynla/.htaccess`)
- csjones backup dir: `~/sync-backup-20260506-091422/{.env, public.htaccess, .git.broken}`
- Local build hash: `app-DvOc0GPe.js` (matches csjones live)
- Pre-recon rollback tags on origin: `pre-recon/dev` (`dc335b3`), `pre-recon/persona-split` (`1bf89e8`)
- SSH key for csjones: `~/.ssh/fynlaDev` (passphrase, requires `ssh-add` per session)
- SSH key for production: `~/.ssh/production` (per CLAUDE.md deploy section)
- Dev servers running: Laravel `:8000` + Vite `:5173`
- PR #245 URL: `https://github.com/Stoff73/fynla/pull/245`
