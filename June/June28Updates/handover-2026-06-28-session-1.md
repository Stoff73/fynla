---
type: handover
mode: end-of-day
date: 2026-06-28
session: 1
branch: main (work on worktree feature branches off dev)
previous_session: 2026-06-27 session 1
---

# Handover — 2026-06-28, Session 1

> Work performed 2026-06-27. A very long, productive run: **Batches 5, 6 and 7 of the `/m` fixes programme all shipped to dev + verified on csjones. The entire 7-batch programme is now COMPLETE.** Prod (fynla.org) UNTOUCHED throughout.

## Where we left off
The `/m` fixes programme (`June/June25Updates/m-fixes-plan.md`) is **finished** — Batches 1–4 landed before this session, Batches 5/6/7 landed today. All merged to `dev` (`267f35f`), deployed to csjones, browser/API-verified. Nothing in the programme remains. The standing open item is the **dev→prod release of Batches 1–7** (CSJ's call — never recommended by Claude).

## What shipped today (all on dev, NOT prod)
- **Batch 5 — Freemium (PR #578, dev):** account caps + Upgrade link on Savings (3) / Investment (2) / Pension (5) screens (`account_count`+`account_limit` per module payload; `SavingsStore::countForUser` single source; shared `upgradeMixin` → iframe break-out to `/settings?tab=subscription`); Holistic Plan gated to Tier 2+ (`EnsureFullHolisticAccess` middleware, `holistic.full` alias, structured `upgrade_required` 403; `/m` view renders Upgrade prompt); Fyn at-cap fix (savings had NO `TierLimitExceededException` catch — added) + accounts-left note (`tierCapNote`). **Protection dropped from 5.1 (no tier cap — CSJ).** New `FreemiumCapsTest`. Live-verified on /m: caps 1/3, 0/2, 0/5 + Holistic gate + Upgrade→settings.
- **Batch 6 — bugs (PR #579, dev):** 6.1 SaveTax reg continuity (`PendingRegistration::createOrUpdate` no longer nulls `funnel_answers`/`signup_source` on re-register); 6.2 bank/current account stored as `current_account` not coerced to `easy_access` (added to `SAVINGS_ACCOUNT_TYPES`, removed the coercion, "bank"/"current" synonyms → `current_account`); 6.4 Goals "Add goal" + per-goal "Edit" buttons (open Fyn via `openFynWith`). Tests: `SavingsCurrentAccountFynCaptureTest` + a `FunnelAnswersCaptureTest` re-registration case. 6.1/6.2 unit-verified; 6.4 buttons render live + wiring fires.
- **Batch 7 — final (PR #580, dev):** 7-C "partner"→"spouse" sweep — live funnel `savetax.php` ("Do you have a spouse?") + ~34 spousal SPA usages; **KEPT the 8 non-spouse traps per CSJ** ("unmarried partner" in will/intestacy copy, the intestacy "leave your partner" line, the Family Members distinct "Partner" option) + "civil partner" + code/business "partner". 7-D page/data-specific edit-details opener (`editPrompt` prop + `buildEditPrompt` util on Savings/Investment/Retirement/Protection/Goals). Live-verified: funnel copy (guest curl), SPA copy (deployed bundle), 7-D message ("I'd like to update my savings. I currently have: Cash ISA.").

Full suite **5131 passed / 0 failed** at each batch. Architecture green.

## What's in flight (NOT done)
- **Nothing in the `/m` programme** — all 7 batches complete.
- **dev→prod release of Batches 1–7** — pending, CSJ's decision.
- **Deferred from the plan (not blocking):** the 6.4/7-D "Add goal"/"Edit"/"Edit details" buttons route mid-onboarding users into the onboarding flow (pre-existing `openFynWith` behavior, same as everywhere on /m); for onboarded users they open advice Fyn cleanly (7-D verified live). Not a bug.

## Deploy status
**Batches 5/6/7 deployed to csjones (dev) only. Prod (fynla.org) UNTOUCHED.** csjones at `267f35f` (dev): per batch `git pull origin dev` + `./deploy/csjones-fynla/build.sh` + rsync `public/build/`+`public/m-build/` + cache chain (no route:cache). The prod release of Batches 1–7 is a future `dev → main` PR (CSJ's call).

## Tech debt found this session
Net clean — every batch went through the full suite (5131 green) + targeted unit tests + live verification. New code is lean: `EnsureFullHolisticAccess` (mirrors `EnsureFullEstateAccess`), `upgradeMixin`, `editPrompt.js` util, `SavingsStore::countForUser` (DRYs the gate count). The 7-C sweep is text-only. No tech-debt-session report written (work shipped + tested + verified).

## Known issues / blockers
**Nothing broken.** Two recurring TEST-ENV gotchas (not bugs), both hit again this session: (1) Pint strips a freshly-added `use` import while it's momentarily unused — re-add AFTER the usage exists (bit me on the TierGate imports + the Kernel `EnsureFullHolisticAccess` import, which caused a `App\Http\EnsureFullHolisticAccess` not-found until re-added). (2) The desktop→/m token bridge does NOT fire on a cold Playwright nav, and breaking out to `/fynla/settings` clears the /m session — re-login at `/m/app/login` + MFA via SSH tinker each time.

## Cleanup left behind (csjones test data)
- **Goal id=291 ("House deposit") on the `savetaxb2test` (Hawkeye) test user** — created to verify the 6.4 per-goal Edit button. Harmless dev data, NOT part of the documented persona. Needs a DB delete to remove (CSJ to OK — Claude's no-DB-workaround boundary blocked a password reset this session, correctly).
- A Sanctum token "batch5-verify" minted on Hawkeye (Batch 5 API verification) — harmless, can be left.

## Rules reinforced this session
- **Rule 16 (don't invent/substitute):** the partner→spouse sweep is the canonical case — a blanket regex would have created "spouse or spouse" / "unmarried spouse" oxymorons + duplicate Family-Members options. Mapped every occurrence with context, asked CSJ on the 8 traps, kept them.
- **No-DB-workaround boundary** held: the auto-mode classifier blocked a test-user password reset; respected it and verified 7-D via network capture instead.

## Next session should
1. **The `/m` fixes programme is done** — no programme work remains. If CSJ wants, the next move is the **dev→prod release of Batches 1–7** (a `dev → main` PR + prod deploy: build with `./deploy/fynla-org/build.sh`, upload `public/build/`+`public/m-build/`+changed PHP, `migrate --force` if any, cache clears, watch the log). **CSJ decides if/when.**
2. If releasing: reconcile prod drift (`reference_prod_accumulated_deploy_drift` — full rsync + `dump-autoload -o` + `migrate:status`). Note Batch 5 added a migration? NO — Batch 5/6/7 added NO migrations (middleware/controller/view/copy only). Confirm with `git diff --name-only aa9c345..267f35f -- database/migrations` before releasing.
3. Optional: remove Goal 291 from Hawkeye (DB delete, CSJ to OK).
4. Otherwise: await CSJ's next direction (new feature / fix).

## Context hints
- Active branch type: **mixed** (main dir on `main` @ `aa9c345`; all feature work merged to `dev` @ `267f35f`).
- main is behind origin/dev by all the Batch 1–7 commits (the pending dev→prod release).
- Uncommitted (main dir): only long-standing untracked carry-overs (June15/June19/docs/ — deliberately left, not this session's work).
- Worktree ALIVE: `fynla-m-funnel` (currently on `m-sweep`, merged; can be re-pointed to a new branch off dev for the next feature, or left). `fynla-coala` (separate programme — keep).
- Three feature branches merged + closed this session: `m-freemium` (#578), `m-bugs` (#579), `m-sweep` (#580).
- Last dev commit: `267f35f` Merge PR #580 (Batch 7).
