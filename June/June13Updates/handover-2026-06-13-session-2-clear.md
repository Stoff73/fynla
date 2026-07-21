---
type: handover
mode: context-clear
date: 2026-06-13
session: 2
branch: dev
---

# Context Clear Handover — 2026-06-13, Session 2

## Immediate state
Just finished a full production end-to-end click-through on fynla.org (desktop web **and** `/m`) — everything green. The `dev → main` release (#549, 342 commits, 5 migrations) is deployed to prod and verified. **Nothing in flight; no open PRs (except the long-parked #249).**

## The thread
- Deployed `dev → csjones` to catch up the #538 mobile pages, then built + verified the **gamified web `/dashboard` parity** (note #1 all-module tabs, note #2 unlock-row capture pre-seed) → PR **#546** (merged + deployed).
- Re-tested the **Azlan savetax journey on csjones** (GREEN; `ai_messages` verbatim recording verified — system_prompt/assembled_context/tool_calls present) — the carried "acceptance moment", now done.
- Found + fixed an **ISA allowance bug**: the calc ignored Stocks & Shares ISA subscriptions (→ PR **#547**), the Fyn tool couldn't capture them, and advice-Fyn deflected capture requests (→ PR **#548**).
- Cut the **`dev → main` release (#549)** and deployed to **fynla.org**: rsync code+bundles (14 MB, no `.env`/`storage`/`vendor`), `migrate --force` (5 migrations), 6 catalogue seeders, `config:cache`.
- **Verified prod end-to-end on BOTH surfaces** — web (login, dashboard/gamification/7-tab parity, sidebar Tax Strategy, `/tax-strategy`, Fyn read, savetax funnel→plan) and `/m` (login, dashboard/gamification, mobile Tax Strategy, Fyn dock). Numbers internally consistent (£1,720 saving, £55,600 unused AA, £14,540 unused ISA).

## Files touched (all committed + merged + deployed)
Today's commits: `be03c7f` + `7531091` (#546 dashboard parity + sidebar), `bde8e9b` (#547 ISA calc), `cf37336` + `6f86da6` (#548 ISA tool capture + Fyn deflection carve-out). Working tree clean (only the 2 long-standing untracked docs: `docs/mobile/designer-brief.pdf`, `docs/security/security-review-2026-06-09.md`).

## What the next Claude needs to know
- **The #2 Fyn deflection fix is PARTIAL — do NOT claim it's fully fixed.** The security-rule-6 carve-out (in `FynSystemPrompt`/`CoreIdentity`) reduces but does not eliminate advice-Fyn mis-classifying legitimate "add my X" capture requests as prompt-injection. It's non-deterministic *per phrasing*: on prod, "add my Fidelity Stocks and Shares ISA" deflected 2/2, while "Lifetime ISA" and "help me add my pension" captured fine. Real fix = eval-driven prompt tuning and/or a deterministic capture-routing layer (detect intent before the model). See `feedback_advice_fyn_capture_deflection_partial.md`.
- **Everything is merged + deployed.** `origin/main` = `2905c62` (#549 merge), dev-ahead 0. fynla.org **and** csjones are both current with dev. No pending deploy.
- **Local `public/build/` is PROD-configured right now** (last build was `./deploy/fynla-org/build.sh`). Rebuild with `./deploy/csjones-fynla/build.sh` before any csjones deploy, or you'll push prod base paths to dev.
- **Non-tax module catalogue metadata (`required_data`/`sequencing`) is null BY DESIGN** on prod+dev — only the tax catalogue is authored (insight-quality Track 1 was tax-focused). Confirmed prod == csjones. Not a bug.
- Pre-existing **May-29 `CollisionServiceProvider` error** in prod `laravel.log` (a dev dependency referenced by some cron/artisan path) — 0 occurrences today. Minor tidy-up someday, unrelated to this release.
- Only **reads** were tested on prod Fyn (no writes/register) to avoid polluting the real `chris@fynla.org` account. Prod MFA codes for chris come from CSJ (asked twice this session).

## Pick up from here
Nothing is in flight — all shipped + verified. Fresh start. Candidate next work for CSJ to choose:
1. **The #2 deflection fix, properly** — eval-driven prompt tuning (the golden-eval harness exists) or a deterministic capture-routing layer. The current partial carve-out is live on dev + prod.
2. **Author non-tax module catalogue sequencing metadata** (`required_data`/`sequencing` for savings/retirement/investment/protection/estate) if module-level sequencing is wanted beyond tax.
3. **The coala→dev landing programme** (deferred earlier today — its own spec/plan per Track 2 spec §7; worktree at `~/Desktop/fynla-coala`; 224 commits ahead).
4. Carried/closed items: gamified-dashboard design notes were addressed (built + merged); insights "featured" was answered = **keep the May fallback** (no change).
