---
type: handover
mode: context-clear
date: 2026-05-19
session: 1
branch: fynPromptRework
trigger: context-handover skill (context tripwire ~300k / >97.5% of 250k budget)
---

# Context Clear Handover — 2026-05-19, Session 1

## Immediate state

C1 prompt-optimisation COMPLETE, verified, committed (`ceb6c81`) and
pushed to `origin/fynPromptRework`. CSJ's last instruction (received at
the tripwire): **deploy this branch to dev AND production — dev first
(PR → dev), then PR dev → main (production); create a deploy doc in
`May/May19Updates/`.** No deploy work started yet (tripwire fired first).

## The thread

- Task: map the unified Fyn AI implementation against
  `docs/superpowers/plans/2026-05-16-fyn-prompt-rework.md`, verify
  correctness, then optimise prompt waste. Deliver to `May/May19Updates/`.
- Audit: all 10 plan tasks implemented CORRECTLY, 0 defects. 6 deliberate
  documented deviations, all improvements (incl. Task 6 `$orchestrateAnalysis`
  param fixing a latent plan bug). 30/30 unified tests green; parity record
  cross-checked.
- Measured waste: static prompt 3,706 tok (cached); per-turn block
  ~1,233–1,289 tok (uncached). Prime waste = `<data_completeness>` ~695
  tok/turn, of which ~595 tok is byte-identical STATIC rule text in the
  uncached per-turn channel.
- Proposed ranked C1–C4 plan via AskUserQuestion → **CSJ chose C1 only**.
- C1 executed: relocated NAVIGATION / BLOCKED-MODULE / MODULE-DEPENDENCY
  rules verbatim into cached `FynSystemPrompt` (`<data_completeness_rules>`);
  new `AdvicePromptBuilder::buildPrerequisiteStateContextLean()`; assembler
  switched to it. Legacy path byte-identical (parity preserved by design).
- Verified: 31/31 unified tests green; both-flag parity EXACT
  (`1f/1s/624p` identical — the 1 failure is the pre-existing orthogonal
  `CassetteModelProvenanceTest:77`, NOT a C1 regression); Rule #15 browser
  GREEN under unified (advice + navigation journeys, john@example.com).
- Per-turn block: ~1,233 → 674 tok (−45%). Committed as `ceb6c81`
  (proper feature commit, not WIP — work is done & tested).
- Rejected: C2 (behavioural, deferred), C3 (compliance-eval project,
  deferred). Both remain open scoped workstreams per the report.

## Files touched this session

Code (in `ceb6c81`, pushed):
- `app/Services/AI/Fyn/FynSystemPrompt.php` — +`<data_completeness_rules>`
- `app/Services/AI/AdvicePromptBuilder.php` — +`buildPrerequisiteStateContextLean()`
- `app/Services/AI/Fyn/FynContextAssembler.php` — READINESS → lean method
- `docs/superpowers/specs/fyn-system-prompt.snapshot.txt` — regenerated
- `tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php` — +tag + C1 test

Docs (untracked, this handover commit will add `May/May19Updates/`):
- `May/May19Updates/unified-fyn-audit-and-prompt-optimisation.md` (the deliverable)
- `May/May19Updates/{task_plan,findings,progress}.md` (session scratch — moved here)

## WIP commit

- None. C1 is a proper feature commit `ceb6c81`, pushed. Tree clean except
  the untracked `May/May19Updates/` docs (added by this handover commit).

## Open decisions

1. **PROD DEPLOY vs the freemium gate (MUST surface to CSJ before
   PR-ing dev → main).** Memory `project_pr317_gated_on_freemium_refactor`
   says PR #317 (release dev → main) is **parked on purpose** and does NOT
   ship to main until the freemium refactor (sub-project 2) is built and
   merged to dev (CSJ decision 2026-05-16). CSJ's 2026-05-19 instruction
   ("deploy to production as well as dev") is the most recent and directly
   conflicts. **Default (most-recent direction-of-travel): proceed with the
   dev deploy unconditionally; for prod, PR dev → main per the new
   instruction BUT flag the freemium-gate conflict to CSJ and get explicit
   confirmation before merging dev → main.** Do the dev side fully; pause
   at the prod merge for CSJ's yes/no on overriding the freemium gate.
2. PR #335 (`fynPromptRework → dev`) is already OPEN per handover-12. The
   new C1 commit `ceb6c81` is now on that same branch → it rides PR #335.
   Decide: reuse/refresh PR #335 as the "→ dev" PR, or note it already
   covers this. Likely just verify PR #335 picks up `ceb6c81`.

## Pick up from here (auto-continue contract)

CSJ: "deploy this branch to dev AND production, dev first, PR to dev then
PR to production; create a deploy doc in the may 19 folder." Execute:

1. **Read first:** `project_pr317_gated_on_freemium_refactor.md`,
   `feedback_main_via_dev_only.md`, `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`,
   `feedback_csjones_deploy_via_git_pull.md`, `feedback_deploy_gate_csjones_before_admin_merge.md`,
   and CLAUDE.md "Deployment" section in full.
2. **Dev PR:** confirm PR #335 (`fynPromptRework → dev`) is open and now
   contains `ceb6c81` (`gh pr view 335`). If clean, admin-merge per the
   solo-reviewer pattern (`gh pr merge 335 --merge --admin`) — CSJ is
   author + sole protected reviewer; do NOT self-approve via review, use
   `--admin`. If PR #335 is stale/wrong, open a fresh `fynPromptRework → dev`.
3. **Deploy dev (csjones.co/fynla):** `git checkout dev && git pull`,
   `./deploy/csjones-fynla/build.sh`, upload `public/build/` to
   `~/www/csjones.co/fynla-app/public/build/`, SSH (`~/.ssh/fynlaDev`,
   `u163-ptanegf9edny@ssh.csjones.co:18765`), `git pull origin dev`,
   `php artisan migrate --force` + cache/config/view/route clear +
   `composer dump-autoload -o` + `php artisan optimize`. Smoke-test
   `https://csjones.co/fynla` — esp. a Fyn advice + navigation turn
   (C1 surface). `FYN_PROMPT_ARCH` defaults to `unified` — confirm csjones
   `.env` does not pin `legacy`.
4. **Prod PR — PAUSE FOR CSJ:** surface Open-Decision #1 (freemium gate vs
   the prod-deploy instruction). On CSJ's go: open + admin-merge PR
   `dev → main` (this is PR #317's lane — check whether to reuse #317 or
   open new), then deploy `fynla.org` per CLAUDE.md (build with
   `./deploy/fynla-org/build.sh`, upload `public/build/` + changed PHP,
   SSH `~/.ssh/production` `u2783-hrf1k8bpfg02@ssh.fynla.org:18765`,
   migrate/clear/optimize, smoke-test, watch `storage/logs/laravel.log`
   10–15 min).
5. **Create the deploy doc** `May/May19Updates/deploy-2026-05-19.md`
   generated from `git diff --name-only` (NOT memory — see
   `feedback_deploy_guide_completeness`): list every changed PHP/JS/.htaccess
   file to upload, the build command per env, the SSH finalise block, and
   the smoke checklist. C1's delta is small (5 code files) — but the dev→main
   release will also carry everything from handover-12 (PR #335 billing fix
   `8bc5f6d` etc.) since dev is behind those. Diff `main...fynPromptRework`
   for the full prod upload list.

## What the next Claude needs to know

- C1 is DONE and browser-verified GREEN this session — do NOT re-verify or
  re-test it. Evidence: `ceb6c81` + the report. Just deploy it.
- `FYN_PROMPT_ARCH` default is `unified` (config/fyn.php). Production must
  run unified — that is the whole point of shipping this. Confirm neither
  server `.env` pins `legacy`.
- The lone failing test `CassetteModelProvenanceTest:77` is the
  pre-existing stranded-cassette tech-debt item from handover-12 (11
  cassettes under `xai/grok-4-1-fast-reasoning/` vs config `grok-4.3`).
  It is NOT a deploy blocker and NOT caused by C1 (fails identically under
  legacy). Mention it in the deploy doc as known-RED-but-orthogonal; do not
  let it block the deploy. (Handover-12's C1 tech-debt fix — re-record vs
  delete — is still unactioned and still needs a CSJ call, but separately.)
- Branch workflow is `feature → dev → main`, never skip dev. Both `dev`
  and `main` are GH-protected; CSJ is author + sole reviewer →
  `gh pr merge <N> --merge --admin` is the established legitimate pattern
  (`feedback_admin_merge_pattern_for_solo_reviewer_prs`). Do NOT fake a
  review/self-approve (`feedback_no_self_approval`).
- csjones is a real git checkout tracking origin/dev — deploy via
  `git pull origin dev`, upload only `public/build/`
  (`feedback_csjones_deploy_via_git_pull`). Never raw `vite build` — use
  the per-env `deploy/*/build.sh`. Never `migrate:fresh`.
- Dev servers on :8000 (Laravel) + :5173 (Vite) were started this session
  and are still up. Do NOT `pkill -f vite` (kills sibling project).
- Logged in as `john@example.com` in the live Playwright browser this
  session — leave the browser open (`feedback_never_close_browser`).
- The two stale worktrees (`tender-bassi-375ee8` = freemium,
  `silly-dubinsky-f02c05`) are not ours — leave them.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0 · Ahead of origin: 0 (C1 `ceb6c81` pushed; this
  handover commit will be +1 until its own push in Phase 7)
- Deploy status: **Not deployed.** PR #335 (`fynPromptRework → dev`) OPEN,
  now includes C1 `ceb6c81`. PR #317 (`dev → main`) parked on freemium
  gate — see Open-Decision #1. Nothing built/uploaded this session.
