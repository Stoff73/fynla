# csjones.co Dev Reconciliation — Design Spec

**Date:** 2026-05-05
**Owner:** CSJ (`@Stoff73`)
**Status:** Approved (architecture + verification gate confirmed)
**Companion artefact:** `/Users/CSJ/Desktop/fynla/May/May5Updates/local-vs-dev-codebase-diff-2026-05-05.md` (initial diff report) — note its findings about "uncommitted server WIP" were superseded by deeper investigation; see Background below.

## Goal

Bring `csjones.co/fynla` (dev environment) into sync with `origin/dev` while preserving every commit in the `fix/persona-split-review-fixes` feature branch. End state:

- `origin/dev` contains all of persona-split's work (Eval framework, Tax Strategy framework, AI Audit/Idempotency, AdviceFyn, 25 migrations) merged with the recent `dev` work (CMS Upload PR #240, Onboarding Fyn PR #214).
- `csjones.co/fynla` runs the merged `dev` cleanly — no stale layered state.
- Local working tree (currently `onboardingFyn`) is synced to merged dev.
- No work is lost. Persona-split branch remains in `origin` as a safety net through Phase 7; deletion only after stability is verified.

Out of scope: production deploy to `fynla.org`. That remains the periodic manual `dev → main` release PR @Stoff73 opens.

## Background

The starting picture (from the diff report) showed csjones with ~125 "uncommitted" files and 138 content diffs against local `dev`. Investigation during brainstorming revealed:

1. The bulk of csjones's "WIP" matches the `fix/persona-split-review-fixes` feature branch byte-for-byte (verified: `app/Services/AI/AdviceFyn.php` is 563 lines on both csjones and `fix/persona-split-review-fixes`, with identical md5).
2. csjones additionally received later partial deploys from `dev` (CMS Upload PR #240, possibly Onboarding Fyn PR #214) layered on top via rsync without `--delete`. Verified by `app/Http/Middleware/SanitizeInput.php` (146 lines) being byte-identical between csjones and `dev`, while persona-split has the older 139-line version.
3. A small subset (~79 files) on csjones matches neither branch's tip — these are STALE persona-split snapshots from before recent commits (e.g., csjones still has `'current_value' => 'decimal:2'` on Estate models because it never received commit `06ae203 fix(casts): revert decimal:2 to float`).

**Conclusion:** csjones state = stale `fix/persona-split-review-fixes` (frozen ~2026-05-01) + selective newer `dev` deploys layered on top. Every byte on csjones is reachable from git on one branch or another. There is **no work to rescue from the server** — only branches to merge.

## Approach (decided)

- **(A) Forward-merge in a worktree, then PR.**  
  Create a worktree of `fix/persona-split-review-fixes`, `git merge origin/dev` to bring dev's recent work in, resolve conflicts privately, run a full verification gate, deploy to csjones, smoke in browser, then push and open a single PR `fix/persona-split-review-fixes → dev`.

- **(C) Full verification gate.**  
  Pint + pest + build + local smoke + **deploy to csjones + browser smoke on csjones** before opening the PR. The point of dev is to test in something that resembles production; doing it before the PR catches problems while the branch is still iterable.

## Architecture

```
┌────────────────────────────────────────────────────────────────┐
│ Phase 0: Pre-flight                                            │
│   Tag pre-recon/dev, pre-recon/persona-split                   │
│   Verify no in-flight PRs / co-dev activity                    │
│   Apply pending local migration                                │
├────────────────────────────────────────────────────────────────┤
│ Phase 1: Worktree + forward-merge                              │
│   Worktree at /tmp/fynla-merge                                 │
│   git merge origin/dev into fix/persona-split-review-fixes     │
├────────────────────────────────────────────────────────────────┤
│ Phase 2: Conflict resolution                                   │
│   Onboarding services, Estate cast revert, layouts, configs    │
├────────────────────────────────────────────────────────────────┤
│ Phase 3: Local verification                                    │
│   pint, fresh migrate, pest green, build.sh succeeds           │
├────────────────────────────────────────────────────────────────┤
│ Phase 4: csjones deploy of merged branch                       │
│   Rsync code with --delete, rsync build, migrate, optimize     │
├────────────────────────────────────────────────────────────────┤
│ Phase 5: csjones browser smoke                                 │
│   Drive Fyn onboarding, AI chat, Tax Strategy, CMS, dashboards │
├────────────────────────────────────────────────────────────────┤
│ Phase 6: PR + merge                                            │
│   Push, open PR, self-approve, squash-merge                    │
├────────────────────────────────────────────────────────────────┤
│ Phase 7: Local sync                                            │
│   git checkout dev && git pull, migrate, db:seed               │
├────────────────────────────────────────────────────────────────┤
│ Phase 8: Cleanup + handover                                    │
│   Update CSJTODO, optional branch deletion, vault sync         │
└────────────────────────────────────────────────────────────────┘
```

**Total estimate:** 5–8 hrs end-to-end. Phase 2 is the variable.

## Phase detail

### Phase 0 — Pre-flight (15 min)

Tags pushed to origin so they survive worktree cleanup:

```bash
git tag pre-recon/dev origin/dev
git tag pre-recon/persona-split fix/persona-split-review-fixes
git push origin pre-recon/dev pre-recon/persona-split
```

State capture (saved to `/tmp/fynla-recon/state.txt`):

- `git rev-parse origin/dev fix/persona-split-review-fixes onboardingFyn`
- csjones HEAD-equivalent: snapshot of `composer.lock` md5 + db migration count via SSH
- csjones DB applied migrations (`SELECT migration FROM migrations`)

Co-dev safety:

- `gh pr list --state open --base dev` → expect zero
- `gh pr list --state open --base fix/persona-split-review-fixes` → expect zero
- Inspect `git log origin/fix/persona-split-review-fixes --since='3 days ago'` for unexpected commits

Local DB current:

- Apply pending migration `2026_04_15_090000_add_onboarding_fyn_state_to_users` so local schema matches dev tip
- `php artisan db:seed --force`

### Phase 1 — Worktree + forward-merge (30 min – 2 hrs)

Worktree separate from `/tmp/fynla-personasplit` (already used for diff analysis):

```bash
git worktree add /tmp/fynla-merge fix/persona-split-review-fixes
cd /tmp/fynla-merge
git pull origin fix/persona-split-review-fixes
git merge origin/dev --no-ff -m "merge: bring dev into persona-split for reconciliation"
```

Conflicts will appear; do not commit until resolved.

### Phase 2 — Conflict resolution (overlaps Phase 1)

| Risk | Area | Resolution approach |
|---|---|---|
| **High** | `app/Services/Onboarding/*` | Persona-split adds `OnboardingFactExtractor`, `HouseholdProvisioner`, `AssetCaptureEntityExtractor`. Dev changed `OnboardingChatDirector`, `OnboardingPromptBuilder`, `OnboardingStateMachine`, `OnboardingValueInterpreter`, `JourneyFieldResolver`, `SpouseLinkingService`. Likely no textual conflict; if there is, take dev's wiring (state-machine-driven flow) and re-thread persona-split's extractors as called services. |
| **High** | `app/Models/User.php` | Take union of casts/relations/scopes; resolve any duplicate property/method names by merging logic. |
| **High** | `app/Agents/CoordinatingAgent.php` | Take persona-split's version (+1342 lines); verify dev-side onboarding hooks still fire. |
| Medium | `app/Models/Estate/{Asset,Gift,IHTProfile,Liability,Will}.php`, `app/Models/Investment/Holding.php` | Persona-split has the `06ae203 fix(casts): revert decimal:2 to float` revert; take persona-split's version. |
| Medium | `routes/api.php` | Take union — both add new routes. |
| Medium | `config/{app,services,lifecycle,onboarding}.php` | Merge keys. |
| Medium | `resources/js/layouts/{AppLayout,PublicLayout}.vue` | Inspect; resolve nav/sidebar additions from both sides. |
| Low | `CLAUDE.md` and module-specific `CLAUDE.md`s | Take dev's (newest, includes Rule #14 for AppLayout). |
| Low | `package.json` / `package-lock.json` / `composer.json` / `composer.lock` | Take union of deps; regenerate lock files. |
| Low | `.gitignore`, `phpunit.xml`, `tailwind.config.js` | Take union. |

After all resolved: `./vendor/bin/pint`, `git add`, `git commit`.

### Phase 3 — Local verification (1 hr)

```bash
cd /tmp/fynla-merge
./vendor/bin/pint
php artisan migrate --force                                  # against worktree's DB connection
./vendor/bin/pest
./vendor/bin/pest --testsuite=Architecture
./deploy/csjones-fynla/build.sh
```

Stop and investigate any failures. Do not advance to Phase 4 until pest is green and `public/build/manifest.json` is non-empty.

### Phase 4 — csjones deploy of merged branch (1 hr)

```bash
# Push merge commit
cd /tmp/fynla-merge && git push origin fix/persona-split-review-fixes

# Rsync code (NOT public/build — that uploads next)
rsync -avz --delete \
  --exclude='/.git/' --exclude='/vendor/' --exclude='/node_modules/' \
  --exclude='/storage/' --exclude='/bootstrap/cache/' \
  --exclude='/.env' --exclude='/public/build/' --exclude='/public/build.old/' \
  --exclude='/public/hot' --exclude='/public/storage' \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  /tmp/fynla-merge/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/

# Rsync built assets
rsync -avz --delete \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  /tmp/fynla-merge/public/build/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/build/

# Server finalisation
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

`--delete` on the code rsync is safe because investigation proved csjones has no untracked work. `build.old/` is preserved by the exclude.

### Phase 5 — csjones browser smoke (1.5 hrs)

| Persona | Critical flows |
|---|---|
| `chris@fynla.org` (admin) | Admin panel, Eval recording (admin), CMS Upload, Insights admin, AiAudit dashboard |
| Preview persona `young_family` | Onboarding Fyn (full state machine), AI chat (Advice mode), dashboard, Goals, Retirement |
| Preview persona `peak_earners` | Tax Strategy (any UI surface), Investment, Estate, AI chat (Advice + handoff to capture) |

Specific assertions:

- Onboarding Fyn `journey/grouped_extract` produces no console errors (FR-M9–M15 from session 2 history)
- AI chat distinguishes Advice (read-only) from Capture (writes via `delegate_to_capture`) — see memory `feedback_advice_fyn_is_read_only.md`
- CMS articles render through `/insights/{slug}` with full PublicLayout chrome
- All routed views show top nav + sidebar + footer (CLAUDE.md Rule #14)
- No raw verification codes / token leaks in chat
- Zero unresolved console errors network-wide

If smoke fails: fix on merge branch, re-rsync, re-smoke. Do **not** advance to Phase 6 with red smoke.

### Phase 6 — PR + merge (15 min)

```bash
gh pr create \
  --base dev \
  --head fix/persona-split-review-fixes \
  --title "merge: persona-split (Eval + Tax Strategy + AI Audit) into dev" \
  --body "$(cat <<'EOF'
## Summary
Reconciles `fix/persona-split-review-fixes` with `dev` after both branches accumulated work in parallel.
- Brings Eval framework, Tax Strategy framework, AI Audit/Idempotency, AdviceFyn, and 25 migrations into `dev`.
- Picks up dev's recent CMS Upload (PR #240) and Onboarding Fyn (PR #214) work via the merge commit.
- csjones.co already runs this merged state — verified via browser smoke.

## Test plan
- [x] pest green
- [x] pint clean
- [x] csjones browser smoke (chris admin, young_family, peak_earners)
- [x] No console errors on critical flows
- [x] All migrations applied cleanly on csjones DB

## Rollback
Pre-merge tags: `pre-recon/dev`, `pre-recon/persona-split`. csjones can be reverted via `pre-recon/dev` deploy in <10 min.
EOF
)"

# Self-approve, squash-merge
gh pr merge <PR_NUMBER> --squash --admin --delete-branch=false
```

Don't `--delete-branch` yet — keep `fix/persona-split-review-fixes` survivable until Phase 8.

### Phase 7 — Local sync (15 min)

```bash
cd /Users/CSJ/Desktop/fynla
git fetch origin
git checkout dev
git pull origin dev
php artisan migrate --force
php artisan db:seed --force
./vendor/bin/pest --testsuite=Unit
```

`onboardingFyn` branch can stay around as a graveyard.

### Phase 8 — Cleanup + handover (10 min)

- Update `CSJTODO.md` with reconciliation summary, dev tip after merge, csjones deploy commit, smoke evidence, links to spec + diff report.
- Optional branch deletion (defer until Phase 7 verification proves dev is stable):
  - `git push origin --delete fix/persona-split-review-fixes feature/fyn-persona-split`
  - `git branch -D fix/persona-split-review-fixes feature/fyn-persona-split backup/fyn-persona-split-pre-merge`
- Worktree cleanup: `git worktree remove /tmp/fynla-merge`, `git worktree remove /tmp/fynla-personasplit`
- Run `vault-sync` skill to mirror handover to fynlaBrain.

## Risk register

| Risk | Likelihood | Mitigation | Recovery |
|---|---|---|---|
| Phase 2 conflicts more complex than budgeted | Medium | Onboarding subsystem reasoning is the time sink; budget 2 hrs not 30 min | If Phase 2 spills past 4 hrs, pause and reassess scope |
| Pest fails after merge | Low | Architecture tests catch most drift early | Fix on merge commit; re-run; do not push until green |
| csjones build fails | Low | `./deploy/csjones-fynla/build.sh` catches Vite config drift | Inspect, fix in worktree, rebuild |
| csjones migration fails (column conflict) | Medium | Some persona-split migrations may already be applied to csjones DB | `php artisan migrate:status` first; mark already-applied rows in `migrations` table to skip |
| csjones smoke reveals runtime regression | Medium | Full smoke catches this BEFORE PR | Fix on merge branch, re-rsync, re-smoke. No PR until green. |
| Mid-deploy outage on csjones | Low | `build.old/` preserved | Rename `public/build` → `public/build.broken`, `public/build.old` → `public/build`, restore from pre-recon tag if needed |
| PR auto-merges before review | Very low | Self-approve only after smoke is green | `gh pr revert` |

**Master rollback (post-merge):** if merged dev is broken after Phase 6:

1. `gh pr revert <PR>` (creates revert PR)
2. Merge revert PR
3. Redeploy csjones from `pre-recon/dev` tag
4. Persona-split branch is unaffected — work is preserved

## Success criteria

- `origin/dev` contains a merge commit (or squash-merge) bringing all persona-split work in
- csjones.co/fynla runs merged dev with no console errors on smoked flows
- Local working tree on `dev`, all migrations applied, pest green
- Pre-merge tags `pre-recon/dev` and `pre-recon/persona-split` exist on origin
- Persona-split branches still exist on origin (deletion deferred)
- CSJTODO.md updated with reconciliation note

## Self-review check

- No "TBD" or placeholders
- Phase 0 verifies pre-conditions before any destructive step
- Phase 4's `--delete` rsync is justified by investigation findings (no untracked work on csjones)
- Phase 5 smoke gates the PR — failures block advance
- Master rollback path doesn't depend on persona-split branch surviving (only on the pre-merge tag)
- Branch deletion is explicitly deferred to last phase, with optional flag
- Out-of-scope (production) is called out so it doesn't creep
