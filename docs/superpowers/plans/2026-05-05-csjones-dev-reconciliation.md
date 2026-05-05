# csjones.co Dev Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring `csjones.co/fynla` into sync with `origin/dev` by merging `fix/persona-split-review-fixes` into `dev` via a forward-merged feature branch, fully verified on csjones in browser before opening the PR — no work loss, no production impact.

**Architecture:** Forward-merge `origin/dev` into `fix/persona-split-review-fixes` in a worktree, resolve conflicts privately, run pint+pest+build, deploy to csjones, smoke in browser, then PR `fix/persona-split-review-fixes → dev` and squash-merge.

**Tech Stack:** Laravel 10, PHP 8.2, Vue 3, Vite, MySQL 8, Pest, rsync over SSH, Capacitor (out of scope), Playwright (smoke).

**Spec:** `docs/superpowers/specs/2026-05-05-csjones-dev-reconciliation-design.md`
**Companion:** `May/May5Updates/local-vs-dev-codebase-diff-2026-05-05.md`

**Pre-conditions** (verified during brainstorming):
- Local branch is `onboardingFyn`, functionally equivalent to `origin/dev` (only `CSJTODO.md` + `handover-2026-05-05-session-2-clear.md` differ).
- csjones content is `fix/persona-split-review-fixes` base + later partial dev deploys, no untracked work.
- SSH key `~/.ssh/fynlaDev` is loaded in agent (`ssh-add -l` shows it).
- 1 pending local migration: `2026_04_15_090000_add_onboarding_fyn_state_to_users`.

---

## Task 1: Pre-flight — tags, state capture, safety checks (15 min)

**Files:**
- Create: `/tmp/fynla-recon/state.txt` (captured pre-state for rollback evidence)

- [ ] **Step 1.1: Create scratch dir for reconciliation evidence**

```bash
mkdir -p /tmp/fynla-recon
```

Expected: silent success.

- [ ] **Step 1.2: Tag both branches at their pre-merge tips**

```bash
cd /Users/CSJ/Desktop/fynla
git fetch origin --tags
git tag pre-recon/dev origin/dev
git tag pre-recon/persona-split origin/fix/persona-split-review-fixes
git push origin pre-recon/dev pre-recon/persona-split
```

Expected: two new tags pushed; `git tag -l 'pre-recon/*'` lists both.

- [ ] **Step 1.3: Capture state of every relevant ref**

```bash
{
  echo "Date: $(date)"
  echo "Local HEAD: $(git rev-parse HEAD) on $(git rev-parse --abbrev-ref HEAD)"
  echo "origin/dev: $(git rev-parse origin/dev)"
  echo "origin/fix/persona-split-review-fixes: $(git rev-parse origin/fix/persona-split-review-fixes)"
  echo "origin/feature/fyn-persona-split: $(git rev-parse origin/feature/fyn-persona-split)"
  echo "Tag pre-recon/dev: $(git rev-parse pre-recon/dev)"
  echo "Tag pre-recon/persona-split: $(git rev-parse pre-recon/persona-split)"
} > /tmp/fynla-recon/state.txt
cat /tmp/fynla-recon/state.txt
```

Expected: 7-line file, every SHA non-empty.

- [ ] **Step 1.4: Capture csjones server snapshot for rollback evidence**

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && {
  echo 'composer.lock md5:'; md5sum composer.lock
  echo 'app/ tree md5:'; find app -type f -name '*.php' -exec md5sum {} \\; | sort -k2 | md5sum
  echo 'migrations table:'; php artisan tinker --execute=\"echo \\App\\Models\\User::count();\" 2>&1 | tail -3
  php artisan migrate:status 2>&1 | tail -50
}" >> /tmp/fynla-recon/state.txt
tail -60 /tmp/fynla-recon/state.txt
```

Expected: composer.lock md5 captured, app/ tree md5 captured, migrate:status output present.

- [ ] **Step 1.5: Verify zero in-flight PRs against either branch**

```bash
gh pr list --state open --base dev --json number,title,headRefName | tee -a /tmp/fynla-recon/state.txt
gh pr list --state open --base fix/persona-split-review-fixes --json number,title,headRefName | tee -a /tmp/fynla-recon/state.txt
```

Expected: both `[]` (empty arrays). If non-empty, **STOP** and surface the open PR — don't proceed; coordinate with whoever opened it.

- [ ] **Step 1.6: Verify no co-dev activity in last 3 days**

```bash
git log origin/fix/persona-split-review-fixes --since='3 days ago' --oneline --pretty='%h %an %s' | tee -a /tmp/fynla-recon/state.txt
git log origin/dev --since='3 days ago' --oneline --pretty='%h %an %s' | tee -a /tmp/fynla-recon/state.txt
```

Expected: only commits authored by `Stoff73` (or empty). Any commit by `icecube-acc` or `Phailanx` → **STOP**, message them before proceeding.

- [ ] **Step 1.7: Apply pending local migration so local DB matches dev tip**

```bash
cd /Users/CSJ/Desktop/fynla
php artisan migrate --force
php artisan migrate:status | grep -i pending | head
```

Expected: 1 migration ran (`2026_04_15_090000_add_onboarding_fyn_state_to_users`), `Pending` count is now 0.

- [ ] **Step 1.8: Reseed local DB to align preview personas with new schema**

```bash
php artisan db:seed --force 2>&1 | tail -5
```

Expected: seeders complete with no errors. (Some `updateOrCreate` warnings are normal.)

- [ ] **Step 1.9: Commit reconciliation kickoff state to onboardingFyn (not for merge — just bookkeeping)**

```bash
git status --short
```

Expected: clean working tree (no changes from migrations/seeds — they only touch DB). Skip the commit if nothing to commit. If unexpectedly dirty, **STOP** and investigate.

---

## Task 2: Worktree setup + start merge (5 min — before conflicts)

**Files:**
- Create: `/tmp/fynla-merge/` (new worktree)

- [ ] **Step 2.1: Remove the diff-analysis worktree from earlier exploration**

```bash
cd /Users/CSJ/Desktop/fynla
git worktree list
git worktree remove /tmp/fynla-personasplit --force 2>/dev/null || true
git worktree list
```

Expected: `/tmp/fynla-personasplit` no longer in the list.

- [ ] **Step 2.2: Create the merge worktree on the persona-split tip**

```bash
git worktree add /tmp/fynla-merge fix/persona-split-review-fixes
git worktree list
```

Expected: `/tmp/fynla-merge` listed, branch shown as `fix/persona-split-review-fixes`.

- [ ] **Step 2.3: Pull latest persona-split into the worktree**

```bash
cd /tmp/fynla-merge
git pull origin fix/persona-split-review-fixes
git log -1 --oneline
```

Expected: HEAD matches `pre-recon/persona-split` from Task 1 (or newer if origin moved — if it moved, **STOP** and re-tag).

- [ ] **Step 2.4: Start the merge**

```bash
git merge origin/dev --no-ff -m "merge: bring origin/dev into persona-split for reconciliation"
```

Expected: either "Merge made by the 'ort' strategy" with conflict count, OR a clean fast-forward (very unlikely given 630-file divergence). Note conflict count from output.

- [ ] **Step 2.5: List conflicted files for triage**

```bash
git status --short | grep '^UU\|^AA\|^DU\|^UD' | tee /tmp/fynla-recon/conflicts.txt
wc -l /tmp/fynla-recon/conflicts.txt
```

Expected: a list of conflicted paths. Save the count — this drives Phase 2 budgeting.

---

## Task 3: Conflict resolution — Onboarding services (high-risk) (30–60 min)

**Files (probable):**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php`
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`
- Modify: `app/Services/Onboarding/OnboardingValueInterpreter.php`
- Modify: `app/Services/Onboarding/JourneyFieldResolver.php`
- Modify: `app/Services/Onboarding/SpouseLinkingService.php`
- New (from persona-split): `app/Services/Onboarding/OnboardingFactExtractor.php`
- New (from persona-split): `app/Services/Onboarding/HouseholdProvisioner.php`
- New (from persona-split): `app/Services/Onboarding/AssetCaptureEntityExtractor.php`

- [ ] **Step 3.1: Read both branches' onboarding service catalog before resolving**

```bash
cd /tmp/fynla-merge
git show origin/dev:app/Services/Onboarding/OnboardingChatDirector.php | wc -l
git show pre-recon/persona-split:app/Services/Onboarding/OnboardingChatDirector.php 2>/dev/null | wc -l || echo "Not on persona-split"
git show origin/dev -- 'app/Services/Onboarding/' --name-only --diff-filter=AM | head -20
git show pre-recon/persona-split -- 'app/Services/Onboarding/' --name-only --diff-filter=AM 2>/dev/null | head -20
```

Expected: dev has the state-machine flow (~6 files); persona-split adds the 3 extractor/provisioner files. Confirm understanding before editing.

- [ ] **Step 3.2: Filter conflicts list to onboarding only**

```bash
grep -i 'onboarding' /tmp/fynla-recon/conflicts.txt
```

Expected: list of conflicted Onboarding files.

- [ ] **Step 3.3: For each conflicted Onboarding file, open and resolve**

For each file `f` from Step 3.2:

1. Read the file with `Read /tmp/fynla-merge/<f>` to see conflict markers.
2. Run `git show origin/dev:<f>` to see dev's view.
3. Run `git show pre-recon/persona-split:<f>` to see persona-split's view.
4. Decide: take dev's wiring (state-machine-driven flow is newer and is the deployed onboarding UX), then re-thread persona-split's extractors into the methods that need them. The extractors are pure helpers — they should slot in without restructuring.
5. Apply edit; remove conflict markers; save.
6. `git add <f>`.

Expected after each: file has no `<<<<<<<` / `=======` / `>>>>>>>` markers.

- [ ] **Step 3.4: Verify the 3 persona-split-only extractors arrived in the merge**

```bash
ls app/Services/Onboarding/OnboardingFactExtractor.php app/Services/Onboarding/HouseholdProvisioner.php app/Services/Onboarding/AssetCaptureEntityExtractor.php
```

Expected: all three exist (they should have come in cleanly since they're new files persona-split added — not conflicting with dev).

- [ ] **Step 3.5: Quick syntax check on resolved files**

```bash
for f in app/Services/Onboarding/*.php; do php -l "$f" 2>&1 | grep -v "No syntax errors"; done
```

Expected: zero output (no syntax errors). If errors → return to Step 3.3 for the offending file.

---

## Task 4: Conflict resolution — Models, Agents, casts (30–45 min)

**Files (probable):**
- Modify: `app/Models/User.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Modify: `app/Agents/{Estate,Goals,Investment,Protection,Retirement,Savings}Agent.php`
- Modify: `app/Models/Estate/{Asset,Gift,IHTProfile,Liability,Will}.php`
- Modify: `app/Models/Investment/Holding.php`
- Modify: `app/Models/{AiConversation,AiMessage,DCPension,FamilyMember,PendingRegistration,UserConsent}.php`

- [ ] **Step 4.1: Resolve `app/Models/User.php` — take union of casts/relations/scopes**

```bash
cd /tmp/fynla-merge
git show origin/dev:app/Models/User.php > /tmp/fynla-recon/user-dev.php
git show pre-recon/persona-split:app/Models/User.php > /tmp/fynla-recon/user-ps.php
diff /tmp/fynla-recon/user-dev.php /tmp/fynla-recon/user-ps.php | head -100
```

Then:

1. Open `app/Models/User.php`.
2. For each conflict block, take both sides' additions (typically they touch different parts of the `$casts`, `$fillable`, or relation methods). Where the same property/method appears on both, merge the bodies (combine `protected $casts` keys; merge query scope conditions).
3. Save; remove markers; `git add app/Models/User.php`.

Expected: file ~785 lines (≈ dev 759 + persona-split additions), syntax-clean.

- [ ] **Step 4.2: Resolve `app/Agents/CoordinatingAgent.php` — take persona-split as base, restore dev hooks**

```bash
git show origin/dev:app/Agents/CoordinatingAgent.php | wc -l        # ~3004
git show pre-recon/persona-split:app/Agents/CoordinatingAgent.php | wc -l  # ~4346
```

1. Take persona-split's version as the base (it has +1342 lines of orchestration).
2. Diff the dev-side delta (`git diff $(git merge-base origin/dev pre-recon/persona-split) origin/dev -- app/Agents/CoordinatingAgent.php`) and re-apply any onboarding hook calls that persona-split's base didn't see.
3. Save; `git add`.

Expected: CoordinatingAgent.php is ~4346 lines + dev's onboarding-hook delta.

- [ ] **Step 4.3: Resolve the other 6 Agents — take persona-split (orchestration changes there)**

```bash
for agent in EstateAgent GoalsAgent InvestmentAgent ProtectionAgent RetirementAgent SavingsAgent; do
  git checkout --theirs app/Agents/$agent.php   # "theirs" in a merge = origin/dev being merged in; we want OURS (persona-split)
done
```

**Stop and verify:** in `git merge`, "ours" = current branch (persona-split), "theirs" = the branch being merged (origin/dev). Persona-split is correct here.

```bash
for agent in EstateAgent GoalsAgent InvestmentAgent ProtectionAgent RetirementAgent SavingsAgent; do
  git checkout --ours app/Agents/$agent.php
  git add app/Agents/$agent.php
done
git status --short | grep app/Agents
```

Expected: zero `UU` lines for `app/Agents/`.

- [ ] **Step 4.4: Resolve Estate models — take persona-split (it has the cast revert)**

```bash
for m in Asset Gift IHTProfile Liability Will; do
  git checkout --ours app/Models/Estate/$m.php
  git add app/Models/Estate/$m.php
done
git checkout --ours app/Models/Investment/Holding.php
git add app/Models/Investment/Holding.php
git status --short | grep -E 'app/Models/(Estate|Investment)'
```

Expected: zero `UU` lines for Estate/Investment models.

- [ ] **Step 4.5: Resolve remaining model conflicts (manual union)**

For each of `app/Models/{AiConversation,AiMessage,DCPension,FamilyMember,PendingRegistration,UserConsent}.php`:

1. Open file.
2. Take union of fillables/casts/relations.
3. Save; `git add`.

Expected: zero `UU` lines for `app/Models/`.

- [ ] **Step 4.6: Syntax-check all resolved Models and Agents**

```bash
for f in app/Models/*.php app/Models/Estate/*.php app/Models/Investment/*.php app/Agents/*.php; do
  php -l "$f" 2>&1 | grep -v "No syntax errors"
done
```

Expected: zero output.

---

## Task 5: Conflict resolution — Routes, config, frontend layouts (30 min)

**Files (probable):**
- Modify: `routes/api.php`
- Modify: `config/{app,services,lifecycle,onboarding}.php`
- Modify: `resources/js/layouts/{AppLayout,PublicLayout}.vue`
- Modify: `resources/js/router/index.js`
- Modify: `resources/js/store/index.js`
- Modify: `resources/js/store/modules/{aiChat,aiFormFill,userProfile}.js`

- [ ] **Step 5.1: Resolve `routes/api.php` — take union (both add new routes)**

```bash
cd /tmp/fynla-merge
```

1. Open `routes/api.php`.
2. For each conflict marker, keep BOTH sides' route definitions — they're additive. Order doesn't matter for routes.
3. Save; `git add routes/api.php`.

Expected: file ~1,290–1,400 lines (dev 1,290 + persona-split additions), syntax-clean.

- [ ] **Step 5.2: Resolve config files — merge keys**

For each of `config/app.php`, `config/services.php`, `config/lifecycle.php`, `config/onboarding.php`:

1. Open file.
2. Each side adds new array keys. Take union — keep both sets.
3. Save; `git add`.

Verify:
```bash
for f in config/*.php; do php -l "$f" 2>&1 | grep -v "No syntax errors"; done
```

Expected: zero output.

- [ ] **Step 5.3: Resolve layouts**

For `resources/js/layouts/AppLayout.vue` and `PublicLayout.vue`:

1. Open file.
2. Read carefully — these have nav/sidebar additions on both sides. Take union of `<template>` slots, `<script>` data, and `<style>` rules.
3. Critical check: CLAUDE.md Rule #14 (every routed view wraps in AppLayout) must hold — the layout components themselves don't need wrapping.
4. Save; `git add`.

- [ ] **Step 5.4: Resolve router + store**

1. `resources/js/router/index.js` — take union of routes.
2. `resources/js/store/index.js` — take union of registered modules.
3. `resources/js/store/modules/aiChat.js`, `aiFormFill.js`, `userProfile.js` — open each, take union of state/getters/mutations/actions.
4. `git add` each.

- [ ] **Step 5.5: Resolve the remaining 30+ Vue components from the diff list**

```bash
git status --short | grep '^UU.*\.vue$' | awk '{print $2}' | tee /tmp/fynla-recon/vue-conflicts.txt
wc -l /tmp/fynla-recon/vue-conflicts.txt
```

For each file (most should be small overlaps — both branches added imports / refactored sections):

1. Open, read both versions, resolve manually.
2. `git add`.

If any file is purely additive on both sides → take both. If renames are involved (e.g., `Navbar.vue` → `AppNavbar.vue`), keep dev's renamed version; persona-split's `Navbar.vue` will be deleted by the merge.

---

## Task 6: Conflict resolution — Docs, lock files, tail-end (15 min)

**Files (probable):**
- Modify: `CLAUDE.md`
- Modify: `app/Services/CLAUDE.md`, `database/CLAUDE.md`, `resources/js/CLAUDE.md`
- Modify: `package.json`, `package-lock.json`, `composer.json`, `composer.lock`
- Modify: `.gitignore`, `phpunit.xml`, `tailwind.config.js`
- Modify: `deploy/csjones-fynla/{build.sh,.htaccess}`, `deploy/fynla-org/{build.sh,.htaccess}`, `public/.htaccess`

- [ ] **Step 6.1: Take dev's version of all CLAUDE.md files (newest, includes Rule #14)**

```bash
cd /tmp/fynla-merge
for f in CLAUDE.md app/Services/CLAUDE.md database/CLAUDE.md resources/js/CLAUDE.md; do
  git checkout --theirs "$f"   # theirs = origin/dev
  git add "$f"
done
git status --short | grep CLAUDE.md
```

Expected: zero `UU` lines for CLAUDE.md files.

- [ ] **Step 6.2: Resolve `composer.json` and `package.json` — take union of dependencies**

1. Open `composer.json`. For each conflict, keep both dependency entries.
2. Same for `package.json`.
3. Save; `git add`.

- [ ] **Step 6.3: Regenerate lock files**

```bash
rm -f composer.lock package-lock.json
composer update --lock --no-scripts 2>&1 | tail -10
npm install --package-lock-only 2>&1 | tail -10
git add composer.lock package-lock.json
```

Expected: both lock files regenerated; `composer.lock` has new content hash; `package-lock.json` reflects merged deps.

- [ ] **Step 6.4: Resolve deploy scripts and .htaccess files — take dev's**

The deploy scripts and .htaccess have to match the current routing reality. Dev's are the most recent.

```bash
for f in deploy/csjones-fynla/build.sh deploy/csjones-fynla/.htaccess deploy/fynla-org/build.sh deploy/fynla-org/.htaccess public/.htaccess; do
  if git status --short "$f" | grep -q '^UU'; then
    git checkout --theirs "$f"
    git add "$f"
  fi
done
git status --short | grep -E 'deploy/|public/.htaccess'
```

Expected: zero `UU` lines.

- [ ] **Step 6.5: Resolve `.gitignore`, `phpunit.xml`, `tailwind.config.js` — take union**

1. Open each; take union of patterns/sections/keys.
2. `git add`.

- [ ] **Step 6.6: Confirm zero remaining conflicts**

```bash
git status --short | grep '^UU\|^AA\|^DU\|^UD' || echo "ALL CONFLICTS RESOLVED"
```

Expected: "ALL CONFLICTS RESOLVED".

- [ ] **Step 6.7: Run pint to normalise PHP formatting**

```bash
./vendor/bin/pint 2>&1 | tail -10
git status --short
```

Expected: pint reports formatted files (or "Nothing to fix"). Add any pint changes:

```bash
git add -u
```

- [ ] **Step 6.8: Complete the merge commit**

```bash
git commit --no-edit
git log -1 --oneline
```

Expected: a merge commit on top of `fix/persona-split-review-fixes`. Message: "merge: bring origin/dev into persona-split for reconciliation".

---

## Task 7: Local verification — pint, migrate, pest, build (1 hr)

- [ ] **Step 7.1: Pint check (already done in Task 6, re-verify)**

```bash
cd /tmp/fynla-merge
./vendor/bin/pint --test 2>&1 | tail -5
```

Expected: "Nothing to fix" or "PASS". If issues → `./vendor/bin/pint`, commit.

- [ ] **Step 7.2: Migration sequence verification (against worktree's DB connection — but use a test DB)**

The worktree uses the same MySQL instance as the main project. Use a separate test DB:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS fynla_recon_test;"
DB_DATABASE=fynla_recon_test php artisan migrate --force 2>&1 | tail -20
```

Expected: all migrations run in order, no errors. **STOP** if any migration fails.

- [ ] **Step 7.3: Reseed verification**

```bash
DB_DATABASE=fynla_recon_test php artisan db:seed --force 2>&1 | tail -20
```

Expected: seeders complete with no errors.

- [ ] **Step 7.4: Run full pest suite**

```bash
./vendor/bin/pest 2>&1 | tail -30
```

Expected: all tests pass. Note: pest uses `RefreshDatabase` so it doesn't use the test DB above — it uses its own. **STOP** if any test fails — fix on the merge branch before continuing.

- [ ] **Step 7.5: Run architecture tests separately (catches drift)**

```bash
./vendor/bin/pest --testsuite=Architecture 2>&1 | tail -15
```

Expected: pass.

- [ ] **Step 7.6: Build for csjones (subdirectory deploy with `/fynla/` base path)**

```bash
./deploy/csjones-fynla/build.sh 2>&1 | tail -30
ls -la public/build/manifest.json
```

Expected: build succeeds, `public/build/manifest.json` exists and is non-empty.

- [ ] **Step 7.7: Drop the test DB**

```bash
mysql -u root -e "DROP DATABASE fynla_recon_test;"
```

Expected: silent success.

- [ ] **Step 7.8: Push merge commit to origin**

```bash
git push origin fix/persona-split-review-fixes 2>&1 | tail -5
```

Expected: push succeeds. Branch on origin now has the merge commit.

---

## Task 8: csjones deploy of merged branch (1 hr)

- [ ] **Step 8.1: Capture pre-deploy csjones state for rollback**

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && {
  ls -la public/build.old | head -3
  ls -la public/build | head -3
  php artisan migrate:status | tail -10
}" >> /tmp/fynla-recon/state.txt
```

Expected: state captured, file appended.

- [ ] **Step 8.2: Server-side build rotation (preserve current build as a fallback)**

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && {
  rm -rf public/build.broken 2>/dev/null
  cp -r public/build public/build.broken
  ls -d public/build*
}"
```

Expected: `public/build.broken` exists alongside `public/build` and `public/build.old`.

- [ ] **Step 8.3: Rsync code to csjones with `--delete`**

```bash
cd /tmp/fynla-merge
rsync -avz --delete \
  --exclude='/.git/' --exclude='/.github/' --exclude='/.claude/' \
  --exclude='/vendor/' --exclude='/node_modules/' --exclude='/storage/' \
  --exclude='/bootstrap/cache/' \
  --exclude='/.env' --exclude='/.env.*' \
  --exclude='/public/build/' --exclude='/public/build.old/' \
  --exclude='/public/build.broken/' \
  --exclude='/public/hot' --exclude='/public/storage' \
  --exclude='/May/' --exclude='/April/' --exclude='/March/' \
  --exclude='/fynlaBrain/' --exclude='/CSJTODO.md' --exclude='/CSJTODO.local.md' \
  --exclude='/ios/' --exclude='*.log' --exclude='.DS_Store' \
  --exclude='/tests/coverage/' --exclude='/coverage/' \
  --exclude='/.phpunit.result.cache' --exclude='/dev.sh' \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  /tmp/fynla-merge/ u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/ 2>&1 | tail -20
```

Expected: rsync completes; deletion summary at end shows only stale persona-split files (the ones flagged earlier as csjones-only excluding build.old) being removed.

- [ ] **Step 8.4: Rsync built assets to csjones**

```bash
rsync -avz --delete \
  -e "ssh -p 18765 -i ~/.ssh/fynlaDev" \
  /tmp/fynla-merge/public/build/ \
  u163-ptanegf9edny@ssh.csjones.co:~/www/csjones.co/fynla-app/public/build/ 2>&1 | tail -10
```

Expected: build assets uploaded.

- [ ] **Step 8.5: Server finalisation — composer, migrate, optimize**

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && {
  composer install --no-dev --optimize-autoloader 2>&1 | tail -10
  php artisan migrate --force 2>&1 | tail -20
  php artisan db:seed --force 2>&1 | tail -10
  php artisan cache:clear
  php artisan config:clear
  php artisan view:clear
  php artisan route:clear
  php artisan optimize 2>&1 | tail -10
}"
```

Expected:
- composer install: no errors
- migrate: any pending migrations run cleanly. If a migration fails because column already exists (some persona-split migrations may have been applied by an earlier deploy), **stop and inspect** — see Step 8.6.
- db:seed: completes
- cache clears: silent
- optimize: succeeds

- [ ] **Step 8.6: If migrations fail with "column already exists" errors**

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && php artisan migrate:status | tail -30"
```

Identify which already-applied migrations Laravel doesn't know about. Then mark them as applied without running:

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co "cd ~/www/csjones.co/fynla-app && php artisan tinker --execute=\"
  DB::table('migrations')->insert([
    ['migration' => '<missing_migration_name>', 'batch' => DB::table('migrations')->max('batch') + 1],
  ]);
\""
```

Repeat for each. Then re-run `php artisan migrate --force`.

- [ ] **Step 8.7: Quick HTTP smoke**

```bash
curl -sI https://csjones.co/fynla/ | head -5
curl -s https://csjones.co/fynla/ | grep -oE 'app[^"]*\.js' | head -3
```

Expected: HTTP 200; the JS asset hash matches a file in the just-deployed `public/build/`.

---

## Task 9: csjones browser smoke — admin (chris@fynla.org) (30 min)

Smoke is via Playwright MCP. CSJ supplies the verification code when the login screen prompts for it (per CLAUDE.md auth section).

- [ ] **Step 9.1: Navigate to csjones and log in as chris**

Use Playwright:

1. `browser_navigate` to `https://csjones.co/fynla/login`
2. `browser_fill_form` with `chris@fynla.org` / `Password1!`
3. `browser_click` Login
4. When 2FA screen appears: **ASK CSJ for the code**
5. Enter code, submit.
6. Verify dashboard loads.

Expected: Lands on `/dashboard` (or admin landing). Top nav, sidebar, footer all visible. Zero console errors.

- [ ] **Step 9.2: Drive Admin panel surfaces**

1. Navigate to `/admin`. Verify AppLayout chrome.
2. Click each admin tab in turn: User Management, AI Audit, Eval Recording, CMS Upload, Insights admin.
3. For each: `browser_console_messages` to check errors; `browser_snapshot` for evidence.

Expected: every tab loads, no console errors.

- [ ] **Step 9.3: CMS Upload + Insights smoke**

1. Navigate to `/admin/documents`. Verify DropZone visible.
2. (Don't actually upload — that creates server side state.) Verify the page chrome.
3. Navigate to `/insights`. Verify hub list includes both bespoke articles AND any uploaded doc articles.
4. Click into one article. Verify PublicLayout chrome.

Expected: layouts correct, no 403s on hero images (other than the pre-existing 8 flagged in CSJTODO).

- [ ] **Step 9.4: Capture screenshots into `/tmp/fynla-recon/smoke-admin/`**

```bash
mkdir -p /tmp/fynla-recon/smoke-admin
```

Then via Playwright `browser_take_screenshot` for each surface visited above. Save with descriptive names.

- [ ] **Step 9.5: Log out via mobile-safe path**

`browser_click` Logout (uses `auth/logout` here — admin web context, OK).

Expected: redirects to landing.

---

## Task 10: csjones browser smoke — `young_family` persona (30 min)

- [ ] **Step 10.1: Reset preview persona state via landing page**

1. Navigate to `https://csjones.co/fynla/`.
2. Click the persona selector → "young_family" (James & Emily Carter).
3. Wait for redirect to dashboard.

Expected: lands on dashboard as preview user.

- [ ] **Step 10.2: Drive Onboarding Fyn (full state machine)**

1. Open the Fyn chat panel.
2. Trigger journey via `?openFyn=journey` or the entry CTA.
3. Walk through journey turns: introduction → base personal → income → expenditure → assets → liabilities → goals.
4. Verify each turn's grouped_extract response renders without errors.
5. Verify `delegate_to_capture` happens silently when persona-split's AdviceFyn detects a write intent.
6. Verify NO raw tool metadata leaks into chat (memory `project_advanced_chat_model_branch.md`).
7. Verify FR-M9–M15 still work (memory `project_eval_http_driven_rewrite_branch.md`).

Expected: full journey completes, dashboard now shows entered data.

- [ ] **Step 10.3: Drive AI chat in Advice mode**

1. Open AI chat (post-onboarding context).
2. Ask a read-only question (e.g. "what's my net worth?").
3. Verify response references actual user data.
4. Verify memory `feedback_advice_fyn_is_read_only.md`: NO write tools fire in Advice mode.

Expected: response renders, no console errors, no write actions.

- [ ] **Step 10.4: Walk module dashboards**

Goals → Retirement → Savings → Protection → Investment → Estate. For each:
1. Verify AppLayout chrome.
2. Verify charts render (canonical `designSystem.js` constants).
3. `browser_console_messages` after each.

Expected: zero unresolved console errors.

- [ ] **Step 10.5: Capture screenshots to `/tmp/fynla-recon/smoke-young-family/`**

---

## Task 11: csjones browser smoke — `peak_earners` persona (30 min)

- [ ] **Step 11.1: Reset and select peak_earners persona**

Repeat Step 10.1 with "peak_earners" (David & Sarah Mitchell).

- [ ] **Step 11.2: Drive Tax Strategy surfaces (persona-split feature)**

1. Navigate to wherever Tax Strategy is exposed in the UI (check `routes/api.php` for `/api/tax-strategy/*` endpoints; check Vue routes for the matching page).
2. If there's a UI (most likely under Investment, Retirement, or a "Tax" tab): drive each strategy tile (Carry Forward, Bed & ISA, Salary Sacrifice, Cross-Spouse Bundle, etc.).
3. Verify rate computation, eligibility gates, and recommendations render.
4. `browser_console_messages` after each.

Expected: strategy panels render with persona's data; no console errors.

- [ ] **Step 11.3: Drive Investment + Estate dashboards**

Cover features peak_earners has: multiple properties, SIPP + NHS pension, IHT exposure.

Expected: all module dashboards render, zero console errors.

- [ ] **Step 11.4: AI chat — Advice + handoff to Capture**

1. Ask Advice a question that requires writing data (e.g. "I'd like to add a £50k SIPP contribution this year").
2. Verify the chat surfaces the `delegate_to_capture` handoff (CSJ confirms a single inline form prompt, not a chained tool error).
3. Confirm form completion writes via `handleInlineCapture` → onboardingFyn capture path.

Expected: handoff is silent; no UI of "tool call" or raw JSON leaks.

- [ ] **Step 11.5: Capture screenshots to `/tmp/fynla-recon/smoke-peak-earners/`**

- [ ] **Step 11.6: Tally smoke result**

Write `/tmp/fynla-recon/smoke-result.md` with:
- Personas tested: chris, young_family, peak_earners
- Surfaces tested: list
- Console errors: count + summary
- Screenshots: paths
- PASS / FAIL verdict

If FAIL: **STOP**. Fix on the merge branch (Tasks 3–6 inputs may need revisit), redeploy via Task 8, re-run Tasks 9–11.

---

## Task 12: PR open + self-merge (15 min)

- [ ] **Step 12.1: Final pre-PR check — pest still green on merged branch**

```bash
cd /tmp/fynla-merge
./vendor/bin/pest 2>&1 | tail -5
```

Expected: pass.

- [ ] **Step 12.2: Open the PR**

```bash
gh pr create \
  --base dev \
  --head fix/persona-split-review-fixes \
  --title "merge: persona-split (Eval + Tax Strategy + AI Audit) into dev" \
  --body "$(cat <<'EOF'
## Summary
Reconciles `fix/persona-split-review-fixes` with `dev` after both branches accumulated work in parallel.

- Brings Eval framework, Tax Strategy framework, AI Audit/Idempotency, AdviceFyn, and 25 migrations into `dev`
- Picks up dev's recent CMS Upload (PR #240) and Onboarding Fyn (PR #214) work via the merge commit
- csjones.co/fynla already runs this merged state — verified via browser smoke

## Test plan
- [x] pest green
- [x] pint clean
- [x] csjones browser smoke (chris admin, young_family, peak_earners)
- [x] No console errors on critical flows
- [x] All migrations applied cleanly on csjones DB

## Rollback
Pre-merge tags: `pre-recon/dev`, `pre-recon/persona-split`. csjones can be reverted via `pre-recon/dev` deploy in <10 min.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)" 2>&1 | tail -3
```

Expected: PR URL printed.

- [ ] **Step 12.3: Capture PR number**

```bash
gh pr view --json number,url -q '.number'
```

Save the number — let's call it `$PR`.

- [ ] **Step 12.4: Self-approve and squash-merge (sole CODEOWNER)**

```bash
gh pr merge $PR --squash --admin --delete-branch=false 2>&1 | tail -3
```

Expected: "Successfully squashed and merged". Branch survives because of `--delete-branch=false`.

- [ ] **Step 12.5: Confirm dev advanced**

```bash
git fetch origin
git rev-parse origin/dev
git log origin/dev -1 --oneline
```

Expected: new SHA at origin/dev tip; squash-merge commit message visible.

---

## Task 13: Local sync to merged dev (15 min)

- [ ] **Step 13.1: Switch local working tree to dev**

```bash
cd /Users/CSJ/Desktop/fynla
git fetch origin
git checkout dev
git pull origin dev
git log -1 --oneline
```

Expected: local on `dev`, HEAD = squash-merge commit from Task 12.

- [ ] **Step 13.2: Apply any new migrations locally**

```bash
php artisan migrate --force 2>&1 | tail -10
```

Expected: persona-split's 25 migrations run cleanly. (They include `2026_05_06_000001_drop_is_eval_user_from_users` and `rename_eval_user_id_to_preview_user_id` — these may matter for memory `feedback_eval_canonical_contract.md`.)

- [ ] **Step 13.3: Reseed local DB**

```bash
php artisan db:seed --force 2>&1 | tail -5
```

Expected: seeders complete.

- [ ] **Step 13.4: Quick local sanity check**

```bash
./vendor/bin/pest --testsuite=Unit 2>&1 | tail -5
```

Expected: pass. (Full suite already verified pre-merge in Task 7.)

- [ ] **Step 13.5: Restart dev server with the new code**

```bash
lsof -i :8000 -t | xargs -r kill 2>/dev/null
lsof -i :5173 -t | xargs -r kill 2>/dev/null
./dev.sh &
sleep 5
curl -s http://localhost:8000 | head -1
```

Expected: dev server responds.

---

## Task 14: Cleanup + handover (10 min)

- [ ] **Step 14.1: Append reconciliation summary to CSJTODO.md**

Open `CSJTODO.md`, prepend a new section:

```markdown
## Session 3 (5 May 2026, autonomous reconciliation) — csjones dev sync

**Branch:** `dev` (merged-clean)
**PRs merged this session:** [#XXX](https://github.com/Stoff73/fynla/pull/XXX) (`fix/persona-split-review-fixes → dev`, squash <SHA>)
**Plan:** `docs/superpowers/plans/2026-05-05-csjones-dev-reconciliation.md`
**Spec:** `docs/superpowers/specs/2026-05-05-csjones-dev-reconciliation-design.md`
**Diff report:** `May/May5Updates/local-vs-dev-codebase-diff-2026-05-05.md`
**Rollback tags on origin:** `pre-recon/dev`, `pre-recon/persona-split`

### Done

- [x] Tagged both branches before merge (`pre-recon/dev`, `pre-recon/persona-split`)
- [x] Forward-merged `origin/dev` into `fix/persona-split-review-fixes` in `/tmp/fynla-merge` worktree
- [x] Resolved conflicts: Onboarding services, User/Agents/Estate models, routes, configs, layouts, lock files
- [x] pint + pest + architecture tests green
- [x] csjones built with `./deploy/csjones-fynla/build.sh` and rsynced with `--delete`
- [x] Browser smoke on csjones: chris admin + young_family + peak_earners — all green
- [x] PR opened, squash-merged to `dev`
- [x] Local checked out `dev`, migrations + seed run clean

### Outstanding (awaiting CSJ direction)

- [ ] Decision: delete `fix/persona-split-review-fixes`, `feature/fyn-persona-split`, `backup/fyn-persona-split-pre-merge` branches? (Code is in dev; branches retained for now as belt-and-braces.)
- [ ] Decision: when to open `dev → main` release PR for production. dev is now ~Y commits ahead of main with a much larger surface (Eval + Tax Strategy + AI Audit + Onboarding Fyn + CMS).
- [ ] Verify on csjones over 24 hrs that nothing regresses under real (preview-mode) user activity.
```

Then commit:

```bash
git add CSJTODO.md
git commit -m "docs(session): csjones dev reconciliation handover

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push origin dev 2>&1 | tail -3
```

Expected: commit + push success.

- [ ] **Step 14.2: Worktree cleanup**

```bash
cd /Users/CSJ/Desktop/fynla
git worktree remove /tmp/fynla-merge
git worktree list
```

Expected: only main worktree remains.

- [ ] **Step 14.3: Optional — delete merged branches (DEFER until 24-hr csjones soak)**

Not yet. Surface as an outstanding decision in CSJTODO. The branches survive on origin until CSJ explicitly asks to delete them.

- [ ] **Step 14.4: Run vault-sync skill to mirror handover**

Invoke the `vault-sync` skill (per CLAUDE.md). Expected: handover mirrored to fynlaBrain vault under `${MONTH_NAME}/${MONTH_NAME}5Updates/`.

- [ ] **Step 14.5: Final state verification**

```bash
git rev-parse origin/dev
git rev-parse pre-recon/dev
git log --oneline pre-recon/dev..origin/dev | head -5
```

Expected: origin/dev advanced from pre-recon/dev by exactly the merge commits + the handover commit.

---

## Self-review against spec

**Coverage check:**
- Phase 0 (Pre-flight) → Task 1 ✓
- Phase 1 (Worktree + merge) → Task 2 ✓
- Phase 2 (Conflict resolution) → Tasks 3–6 ✓
- Phase 3 (Local verification) → Task 7 ✓
- Phase 4 (csjones deploy) → Task 8 ✓
- Phase 5 (Browser smoke) → Tasks 9–11 ✓
- Phase 6 (PR + merge) → Task 12 ✓
- Phase 7 (Local sync) → Task 13 ✓
- Phase 8 (Cleanup) → Task 14 ✓

**Risks from spec are addressed:**
- Phase 2 conflict overrun → tasks 3–6 each have hard time budgets, halt-condition steps
- Migration column-already-exists → Step 8.6 handles it explicitly
- csjones build failure → Step 7.6 catches before deploy
- Smoke regression → Step 11.6 explicitly says STOP, fix, redeploy, re-smoke
- Mid-deploy outage → Step 8.2 makes a `public/build.broken` fallback BEFORE rsync
- Master rollback path → preserved via `pre-recon/dev` and `pre-recon/persona-split` tags pushed in Task 1

**Placeholder scan:**
- No "TBD" / "TODO" / vague directives.
- Every conflict-resolution step names the strategy (take-ours, take-theirs, manual union) explicitly.
- Migration error path is concrete (insert into `migrations` table, re-run).
- Smoke checklist names specific assertions (no console errors, layouts wrap correctly, no tool metadata leak, etc.).

**Identifier consistency:**
- Tag names `pre-recon/dev` and `pre-recon/persona-split` used consistently throughout.
- Worktree path `/tmp/fynla-merge` used consistently.
- Smoke evidence dir `/tmp/fynla-recon/smoke-*` named consistently.
- Branch names match between Task 1 (`fix/persona-split-review-fixes`) and Task 12 PR base/head.
