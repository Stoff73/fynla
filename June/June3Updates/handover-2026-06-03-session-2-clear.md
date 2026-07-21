---
type: handover
mode: context-clear
date: 2026-06-03
session: 2
branch: dev
trigger: context-handover skill (tripwire ~880k tokens)
---

# Context Clear Handover — 2026-06-03, Session 2

## Immediate state

Just finished deploying **PR #448** (mobile dashboard redesign + savetax/homepage) to **dev/csjones** and verified it GREEN in a Chromium mobile viewport. CSJ said **"yes"** to two follow-ups, then reported a **NEW LIVE BUG** (see ⚠️ below) right as the second tripwire fired — uninvestigated, handed off as the top priority.

## ⚠️ NEW BUG — TOP PRIORITY (uninvestigated, CSJ-reported 2026-06-03 ~13:25)

**CSJ's words:** "I have just gone onto csjones.co/fynla on my mobile phone and it still defaults to the login screen, and fails to load. This PR [#448] should have changed it to use the landing page?"

- **Symptom:** On a REAL mobile phone (not Chromium emulation), `csjones.co/fynla` → defaults to the **login screen** AND **fails to load**. CSJ expected #448 to route the mobile entry to a **landing page** first, not login.
- **What I verified in Chromium (390×844) earlier this session:** `/fynla/m/app/dashboard` (unauthed) → redirects to `/fynla/m/app/login`; after login the dashboard loads fine. So the SPA's unauthed-redirect-to-login is "working as coded" — but that is NOT what CSJ wants for the mobile **entry point**, and a real phone is "failing to load" (my Chromium test did NOT reproduce the load failure — likely a real-device-only issue: phone-UA redirect chain, iframe shell, CSP, or asset/MIME).
- **Likely area (DO NOT trust without checking):** the SP3 phone-UA redirect chain (phone-UA → `/m` → iframe shell → `/m/landing` HTML → `/m/app` Vue SPA) — see memory `project_sp3_iframe_scaffold_live_on_prod.md`. Either (a) #448 was supposed to add/route to a mobile **landing** page and it's going straight to `/m/app/login` instead, or (b) the landing/iframe shell fails to load on a real device. Check `routes/web.php` mobile/phone-UA redirect logic, `resources/views/mobile-app.blade.php`, the `/m` and `/m/landing` routes, and whether #448's `public/pages/*` changes were meant to include a mobile landing.
- **Reproduce:** real phone (or true mobile UA) against `https://csjones.co/fynla` — Chromium desktop resize did NOT reproduce the load failure, so use a real device UA / check server logs + the actual redirect chain. CSJ can re-test on their phone.
- **First moves next session:** (1) `curl -A "<iPhone UA>" -sI https://csjones.co/fynla` and follow redirects to see where the phone-UA chain lands; (2) read the mobile entry routes in `routes/web.php`; (3) diff what #448 actually changed for the mobile entry vs the landing-page expectation; (4) check csjones `storage/logs/laravel.log`. Then loop-until-correct per Rule #15.

CSJ said **"yes"** to two follow-ups that were NOT yet done when the first tripwire fired: (1) delete the two merged feature branches, (2) fold the pending **Rule #17–19** into dev's canonical CLAUDE.md. Both are captured under "Pick up from here" (now SECONDARY to the bug above).

## The thread

- **Session started** as a corrections-mining task (mined 50 sessions via a Workflow → drafted Rules #17–19; CSJ approved "Add as written"). Those rules were edited into a coala-based CLAUDE.md and **stashed** (`stash@{0}`), never committed anywhere.
- **Merged the CoALA 4-phase stack** #440→#446 into `coala` (had to recreate #441 as **#447** after `--delete-branch` auto-closed it — lesson: never `--delete-branch` when the deleted branch is another open PR's base; retarget-to-base first). Deleted 6 stack branches + 4 Phase-5-era merged branches. Ran full Pest suite on `coala` = **4592 passed, 29 skipped, 0 failures**.
- **Merged PR #448** (Phailanx, branch `quick-registration`) to `dev` — flagged: gamification/icons (Rule #13/#16), branch-prefix deviation, not-yet-browser-tested.
- CSJ authorised **amending Rule #13 (scores) + Rule #16 (icons)** with a "by design, CSJ-specified, not LLM/agent-chosen" carve-out → **PR #449, merged to dev**.
- **Deployed dev → csjones**: built both bundles, rsync'd (no `--delete`), `git pull` on server, no migrations, caches cleared.
- **Smoke test caught a real bug**: csjones mobile login 404'd (`csjones.co/api/...` instead of `/fynla/api/...`). Root cause `resources/mobile/api.js:14` (bare relative `/api/*` ignores subdirectory). Fixed by deriving web base from `VITE_ROUTER_BASE` (same as `router.js:11`) → **PR #450, merged to dev**. Rebuilt mobile, redeployed, **re-tested live = GREEN** (Chris's real data £626,595).

## Files touched this session

All landed via merged PRs (#447 coala 4b, #449 carve-outs, #450 mobile api fix) + #448 (Phailanx). Working tree on `dev` is clean except untracked NOT-MINE `docs/mobile/designer-brief.pdf` (leave it). No WIP commit needed — nothing of mine uncommitted.

## WIP commit

- **None.** No uncommitted tracked changes of mine. The only pending artefact is `stash@{0}` (Rule #17–19, coala-based) — preserved below verbatim in case the stash is lost.

## Open decisions

- None blocking. CSJ already answered the two follow-ups with "yes" (see Pick up from here).
- `coala` (full CoALA framework, suite-green) has **never been deployed past the `coala` integration branch** — the `coala → dev → main` run is the real next milestone (needs eval rebaseline + cache-telemetry baseline + deploy note). Not started; separate from the dev-line work above.

## Pick up from here (auto-continue contract)

0. **FIRST: the ⚠️ NEW BUG above** — mobile entry on `csjones.co/fynla` defaults to login + fails to load on a real phone; CSJ expected a landing page. Investigate the phone-UA redirect chain / `/m` routes before anything else. Loop-until-correct (Rule #15) and re-verify on a real mobile UA. This outranks the two follow-ups below.
1. **Delete the two merged branches** (CSJ said yes):
   ```bash
   for b in claude-md-design-carveouts fix/mobile-api-base-subdirectory; do
     git push origin --delete "$b"; git branch -D "$b"
   done
   ```
2. **Fold Rule #17–19 into dev's canonical CLAUDE.md** (CSJ said yes). The text is in `stash@{0}` but it was based on **coala's** CLAUDE.md (344/119 metrics), so do NOT `git stash pop` onto dev (base mismatch). Instead, branch off `dev`, append the three rules verbatim AFTER Rule #16's `**Ownership:**` line (the carve-out from #449 is just above it), commit, PR → dev, admin-merge. **Rule #17–19 text to insert** (preserve exactly):

   ```markdown
   ### 17. Build to the Agreed Spec — Never Invent or Substitute

   When a feature has been specced, planned, and agreed, implement exactly that. Do not invent design decisions that were never agreed (e.g. an "upgrade" CTA in the side menu, greying-out nav items). Do not substitute a cheaper approximation for the agreed approach (e.g. an iframe shell where a real UI with working drill-downs was specced). Do not change which behaviours or tiers were agreed. If you believe the spec is wrong or a deviation is warranted, STOP and ask CSJ before deviating — never ship the deviation and explain afterwards. Before claiming a spec change is done (screens removed, flows gated), verify it is actually reflected in the live UI.

   ### 18. Lean PR / Test Cadence

   Don't run the full test suite or full process ceremony after every single PR when CSJ has signalled "lean" or is iterating on prompts, evals, or a multi-PR refactor — queue several PRs and do one consolidated test pass. This is a speed concession for low-risk iteration only. It does **not** weaken Rule #15: every BS-NN browser scenario, and any change CSJ has pointed at and said "make this work", still requires the full diagnose → fix → live-browser-verify loop per its plan before it is called done. When unsure whether a change is "lean-eligible" or needs full per-change verification, ASK CSJ.

   ### 19. Internalise Agreed Plans — Don't Make CSJ Re-Explain

   When CSJ has explained an architecture or plan, or explicitly deferred an issue ("we'll do this after the refactor", "this doesn't need to come up every time"), internalise it and act on it. Do not re-raise a settled or deferred decision on every turn, and do not make CSJ re-explain the same already-agreed design repeatedly. If a detail is genuinely unclear, re-read the spec, the canonical contract, and the relevant memory files first; only ask once you've exhausted those and the question is one they have not already answered.
   ```
   After landing, `git stash drop stash@{0}` (its NOT-MINE logo deletions are net-zero; the rule text is now committed). Also mirror the three as one-line pointers in MEMORY.md "Top laws" (already done locally earlier? verify — the mining session added them to the memory MEMORY.md, not the repo CLAUDE.md).

3. Optional: the `coala → dev → main` deploy prep (separate, larger).

## What the next Claude needs to know

- **csjones is at `dev` tip `9824cab`** (includes #448 + #449 + #450). Live + verified.
- **Mobile build for csjones**: `./deploy/csjones-fynla/build.sh` rebuilds BOTH main SPA + `m-build` (it exports `VITE_ROUTER_BASE=/fynla/` and `VITE_MOBILE_BASE_PATH=/fynla/m-build/`). For **localhost** mobile, use `bash scripts/build-mobile.sh` (default base). My local `public/build` + `public/m-build` currently hold **csjones-base** artifacts — rebuild for localhost if needed (dev server uses Vite HMR so localhost SPA is unaffected).
- **csjones SSH**: key IS loaded in the agent this session (`ssh-add ~/.ssh/fynlaDev`); may need re-adding after reboot. `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`, app at `~/www/csjones.co/fynla-app`.
- **The build guard hook** blocks any command containing the literal `vite build` / `npm run build` (even in a grep pattern) — use the sanctioned `deploy/*/build.sh` scripts, or `bash scripts/build-mobile.sh`, or grep with a different pattern.
- **Mobile web API base** is now subdirectory-aware via `VITE_ROUTER_BASE` (PR #450) — unchanged on fynla.org/localhost/iOS.
- Dev server (`./dev.sh`) is running in the background (`:8000` Laravel, `:5173` Vite) — may still be alive after clear.
- A `wip` stash collision: `stash@{1}` is an OLD unrelated `feat/property-store-pr5a` stash — leave it.

## Branch / deploy state

- Branch: `dev` (HEAD `2fabb98`), in sync with origin/dev (0 ahead, 0 behind).
- Deploy status: **#448 + #449 + #450 deployed to dev (csjones), verified live.** Nothing to main/prod.
- **#451 merged to dev (`2fabb98`)**: cherry-picked the context-watch tripwire removal (`2115ecc`+`c34e5b2`) from coala onto dev — the tripwire was firing because the removal had only landed on `coala`, never `dev`/`main`. Now gone on dev (script deleted, settings cleaned, post-compact vault-sync injector added). **NOTE: `main` STILL has the tripwire** — fold in on the next dev→main release. This running session keeps firing it (hooks cached at startup); a fresh session on dev will not.
- Branches safe to delete (merged): `claude-md-design-carveouts`, `fix/mobile-api-base-subdirectory`, `chore/drop-context-watch-tripwire-dev`.
- `coala` branch: full CoALA stack merged + suite-green, undeployed (separate milestone).
