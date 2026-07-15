---
name: release
description: Canonical Fynla release flow — feature → dev (csjones-verified) → main (fynla.org). Codifies the branch-protection gate, the deploy-feature-branch-BEFORE-admin-merge order, csjones git-pull deploy mechanics, and the prod release procedure. Use when CSJ says "release", "ship this", "merge to dev", "promote to main", "deploy the PR", or asks how a change gets from branch to production. Never self-trigger — CSJ decides when to ship.
disable-model-invocation: true
---

# Release — feature → dev → main

The gate exists so every change is **verified working before it lands**. Never invert the order. Never skip csjones. Nothing reaches `main` without first being committed to `dev`, deployed to csjones, and browser-tested there — the only exception is CSJ explicitly saying so **in the current turn**.

## Flow A: feature branch → dev (the standard PR)

1. **Open the PR targeting `dev`** (never `main` directly). Contributor branches need their `feature/icecube/*` / `feature/phailanx/*` prefix.
2. **Wait for CSJ's explicit go-ahead** ("merge it", "ship it"). Never extrapolate a previous session's approval.
3. **Deploy the FEATURE branch to csjones — BEFORE any merge:**
   ```bash
   # Local build (never raw vite/npm)
   ./deploy/csjones-fynla/build.sh

   # csjones is a real git checkout and can check out ANY remote branch.
   # SSH: check `ssh-add -l` first — if fynlaDev isn't listed, ASK CSJ for the
   # passphrase (never probe, never ssh-keygen). NEVER the ssh-fynla MCP (that's PROD).
   ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
   cd ~/www/csjones.co/fynla-app      # Laravel root — NOT public_html/fynla (symlink)
   git fetch origin && git checkout <feature-branch> && git pull origin <feature-branch>
   php artisan migrate --force
   php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
   php artisan config:cache           # config STAYS cached; routes stay UNCACHED
   ```
   Upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/`. If CSJ is mid-test in a browser, **warn before replacing the bundle** and merge old chunks alongside new (`cp -rn build.old/. build/`) so in-flight sessions survive.

   **NEVER `php artisan optimize` or `route:cache`** — the compiled matcher lets the SPA catch-all shadow `/` and the `/m` landing regresses (the prod-guard/dangerous-command-guard hooks also block this).
4. **Browser-verify the actual change on `https://csjones.co/fynla`** — click, fill, submit, per the browser-testing law. `/m` changes verify per the `verify-m` skill.
5. **Only now merge.** Solo-author PRs sit `REVIEW_REQUIRED, BLOCKED` (GitHub disallows self-approval): `gh pr merge <N> --merge --admin`. The `--admin` flag is the merge *mechanism* after the gate — never a licence to skip steps 2–4. External contributor PRs get real review, no `--admin`.
6. **Return csjones to dev:** `git checkout dev && git pull origin dev`.

## Flow B: dev → main (production release — CSJ opens this PR)

1. Pre-merge verification = "the dev tip is browser-verified GREEN on csjones" + "the main↔dev diff contains nothing that wasn't on csjones".
2. After merge, deploy to fynla.org (manual upload — prod is NOT a git checkout):
   - Build: `./deploy/fynla-org/build.sh` (never mix with the csjones build — different `VITE_BASE_PATH`).
   - Upload `public/build/` + changed PHP. Prod accumulates drift — each release do a full rsync reconcile + `composer dump-autoload -o` + `migrate:status` check.
   - `php artisan migrate --force`, cache clears ending `config:cache` (routes uncached).
   - Monitor `storage/logs/laravel.log` for 10–15 min.
3. Browser-verify on fynla.org (MFA code: ask CSJ), then it's done. Follow `deploy-checklist` / `deploy-notes` skills for the file-level checklist.

## The three-question check before any `--admin` merge

1. Has CSJ seen the diff or said "merge" since the PR opened?
2. Is the change deployed to the relevant environment (csjones for dev PRs; fynla.org for landed release PRs)?
3. Is it browser-verified working there (or genuinely no runtime surface)?

Any "no" → do not merge. Deploy, verify, or ask.
