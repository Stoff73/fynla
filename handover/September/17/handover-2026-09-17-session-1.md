---
type: handover
mode: session-end
date: 2026-09-17
session: 1
repo: fynla
branch: dev (main checkout, clean apart from the long-standing untracked workforce/diagram files)
---

# Session Handover — 2026-09-17, Session 1

## Where things stand

One release went to production at 04:30 (PR #895, main `94ad79c33`): every Fyn onboarding capture step is now a structured form on web and `/m`, on all three entry points, plus the spouse holding transfer and its migration. It was verified live on fynla.org by a fresh Save Tax registration and every row checked, then the test account and `c.jones` purged. **After that release, three more pieces landed on dev and are NOT on production**: the unlinked-spouse action CSJ asked for (#896), the two routing fixes underneath it, and the `/m` all-actions list (#897). Dev is `034b593ac`, nine commits ahead of main, all browser-gated on csjones, no migration in the range. **CSJ's closing instruction: releasing that work is the next session's first job.**

## Priorities for the next session

1. **Release dev → main and deploy to fynla.org.** This is CSJ's explicit instruction for tomorrow. `/release` is user-invoked only — the model cannot launch it, so **ask CSJ to type `/release`** and then drive Flow B. Contents: PRs #896 and #897 (the unlinked-spouse action, the navigate-routing fixes, the canonical `spouse_sharing` destination, and the `/m` all-actions screen). **No migration in this range.** Both SPA bundles must be rebuilt with `./deploy/fynla-org/build.sh` — web (`semanticDestinations.js`, `ActionsDashboard.vue`, `GamifiedDashboard.vue`) and `/m` (`semanticDestinations.js`, `router.js`, `Actions.vue`, `Dashboard.vue`) all changed. Follow the same chain that worked at 04:30: backup → `down` → full-tree `rsync -azRc` of `app/ config/ database/ routes/ resources/views/ fyn-memory/ resources/js/data/ public/pages/ public/build/ public/m-build/` (never `bootstrap/`) → `composer dump-autoload -o` → `migrate --force` (no-op here) → cache clears ending `config:cache` → `up` → `fyn:procedural:validate` → smoke the routes → browser-verify. Then purge any test account and `c.jones` so CSJ re-tests from registration.
2. **Browser-verify the released work on fynla.org.** The forms release was verified there; #896/#897 have only been verified on csjones. After the deploy, check on production: a married user with an unlinked spouse sees "Link <name>'s Fynla account" in their actions, it navigates to `/settings/family` on web and `/spouse-sharing` on `/m`, and the `/m` dashboard's "See all actions" lands on the new list.
3. **Tech debt from today, if CSJ wants it cleared.** `docs/tech-debt-report.md` has 6 items, 0 critical. The two worth doing first: `CaptureForms.php` is now 1,284 lines and wants splitting per form family, and the module label vocabulary now exists in three places (PHP plus both clients) — the server should send the label.
4. **Parked, raise only if asked:** mapping PRs #829–#839 and the 55 mapping bugs (`CSJTODO.md`); the native iOS renderer for the capture forms; the iPhone registration bounce; the web Retirement actions block.

## Context to load

- `handover/September/16/sdd-account-forms/progress.md` — the rulings ledger, now 42–52 with the reasons behind every decision of the last two days. Extend it, never re-litigate it.
- `docs/tech-debt-report.md` — today's audit, the source for priority 3.
- `app/Services/Mobile/NextActionsService.php` — the one actions model. `openItems()` (the single merge both lists read), `spouseLinkItems()` (the new action) and `focusAreas()` (what `/m` reads) are the parts that changed.
- `app/Services/Onboarding/SpouseHoldingTransfer.php` — the new service that copies the spouse facts onto the linked account; small and self-contained, read it before touching linking.
- `app/Services/Onboarding/CaptureForms.php` — the one schema home for all twelve capture forms; read `pension()` / `spouseHousehold()` / `expenditureDetailed()` for the three shapes before adding another.
- `deploy/DEPLOY.md` — the production file-level checklist for priority 1.

## Completed this session

**Released to production (PR #895, main `94ad79c33`, deployed 04:30):**
- Journey path forms: personal details, spouse details (keeping its skip link), dependants with its own "another?" loop, work and income (#886, #887).
- Campaign forms: date of birth, personal-pension-only at the contributions step (#889); expenditure — one box for everyone, the web page's five category groups on Premium with the household question first when a spouse is on file (#892).
- Journey "Anything else" focuses now open on the same forms as the Save Tax walk, plus a new protection form with its own loop (#893).
- Pension check spouses take the Save Tax spouse forms instead of creating a pension on the user's own account (#892); the contributions step is retired once the pension form has been shown (#890).
- `SpouseHoldingTransfer` (#894, migration `2026_09_16_220000`): on accept, the spouse facts given during onboarding are copied onto the spouse's account once — date of birth, employment status, income, savings, a Stocks and Shares ISA with its provider, investments, and the pension pot with its contribution.
- Fixes found by six end-to-end walks from registration: typed "I don't have any/a/an" is a completion declaration that skips its loop question; no phantom £0 pension from a bare "pension" mention; the recapture guard ignores the provider name inside a record name; a "no" never completes a refused attempt; retired funnel arrivals enter at the retirement-date step with the recap; a resume greeting is not a delivered turn; `/m` `/net-worth/retirement` redirects to pensions; pension check verify announces name their pages.

**On dev, NOT released:**
- `#896` — the unlinked-spouse action: one item covering accept / link / add, value 70, quiet mid-walk, gone the moment the link exists. With it, two real gaps the browser found: `focusAreas()` and `rankAll()` each merged their own item set (so anything added to one was missing from `/m`) — now one `openItems()`; and a navigate action was swallowed by the unlock branch on the web dashboard while the actions page routed by module only, ignoring the server destination. Adds the canonical `GateRoutes::SPOUSE_SHARING` (web `/settings/family`, `/m` `/spouse-sharing`).
- `#897` — the `/m` all-actions list (`resources/mobile/views/Actions.vue` at `/actions`). The dashboard's "See all actions" had been hardcoded to `/achievements`, so every action below the four dashboard slots was invisible on `/m`.

## Verification state

- Production, fynla.org: a fresh Save Tax registration walked end to end at 04:40 — work form, ISA form, a typed "I don't have a bank account to add" that moved on, the date-of-birth form, the pension form, the contributions step skipped, the spouse form, the one-box expenditure form, the plan and the terminal. Every row checked by tinker; zero new log errors; home, `/m`, login and register all 200. Test user 735 and `c.jones` (734) purged afterwards.
- csjones, #896: web `/actions` shows "Link Robin's Fynla account — Household" and clicking it lands on `/settings/family`; `/m` Top actions shows it and tapping lands on `/spouse-sharing` with the pending state.
- csjones, #897: "See all actions" lands on the new list showing all 18 open actions; the spouse row navigates; "Mark as done" moved an item into Done with today's date and dropped the count to 17; an unlock row opened Fyn and sent "Help me complete my tax strategy details".
- Pest, last full pass at `28b91b2ae`: 231 across the mobile suites, 253 across the form/state-machine/journey/campaign/pension-check suites, plus the tool-schema golden master, preview catalogue and write-handler receipts. Vitest: 218 mobile specs, 22 destination specs.
- **Not verified:** #896 and #897 on production (they have only ever run on csjones). The native iOS renderer still has no forms — every capture form is web and `/m` only. No full Pest suite was run today, focused files only, per the standing instruction.

## Decisions and dead ends

- **CSJ's rulings today** (all in the ledger, 42–52): forms on every entry point, not just Save Tax; expenditure is one box for everyone with category entry for Premium; the pension check takes the Save Tax spouse forms; every spouse fact is copied onto the spouse's account on linking, with the ISA assumed to be Stocks and Shares; registration does **not** auto-claim a pending invitation — instead there is an action while the accounts are unlinked; and `/m` gets the desktop all-actions list.
- **The spouse-link action's weight stays at 70.** It was tempting to inflate it so it reached `/m`'s four slots, but that would rank a linking prompt above a missing will. The right fix was giving `/m` the full list (#897); for a freshly-onboarded household — CSJ's actual case — it ranks first anyway.
- **Do not add a GateRoutes entry casually.** `tests/Architecture/GateRoutesTest.php` asserts every canonical route is declared in both the web and mobile routers, and both frontends keep their own screen→path map. A new destination needs four edits: `GateRoutes::MAP`, the web `semanticDestinations.js`, the mobile `navigation/semanticDestinations.js`, and a route that really exists on both surfaces.
- **Dead end:** pointing the spouse-link action at `GateRoutes::FAMILY_DETAILS` looked right but resolves to `/personal-information` on `/m`, which is the family-details screen, not the sharing panel where accepting and linking happen. Hence the separate `SPOUSE_SHARING` destination.
- **Dead end:** the corpus state table must be in the **same order** as the in-code table. Inserting `journey_protection` before `add_more` in the corpus while the PHP table had it after threw "the shipped corpus file is missing, malformed, or its state set does not match the in-code table" and every state-machine test failed at once.

## Things that will bite you

- **`/release` cannot be launched by the model.** The skill is user-invocation only; asking CSJ to type it is the only route. Everything after the merge (build, rsync, artisan, verification) is ours to drive.
- **Production writes:** plain Bash `ssh` to fynla.org is blocked by the auto-mode classifier. Use the `ssh-fynla` MCP (`ssh_exec`) for artisan and Bash `rsync -azRc` for uploads — that combination worked all through the 04:30 release. csjones is the opposite: plain `ssh -p 18765 -i ~/.ssh/fynlaDev`, never the MCP.
- **The `/m` token is revoked whenever a web session for the same user is created.** Minting a web token invalidated the `/m` one twice today and the `/m` app silently bounced to login. Mint one token per surface, in the order you will use them.
- **Set a browser token on the right origin.** `sessionStorage.setItem` while the page is still on fynla.org does nothing for csjones. Navigate first, then set, then reload.
- **The `/m` dashboard reads `focusAreas()`, not `build()`.** They now share `openItems()`, but anything added to one path historically missed the other — check both when you add an action.
- Backups from today's release: `~/release-backups/2026-09-17a/` (database dump, app tarball, migrate status).
- csjones test users: 399 `formwalk-accounts-0916@example.com` (Premium subscription granted today for the expenditure category form; its synthetic spouse card and the test tracking row were cleaned up), 400–406 from the six end-to-end walks, 407 purged.
- The level-up celebration overlay still blocks Playwright clicks after a save on both surfaces; dismiss it with a JS click on `div.celebrate[role=dialog]` first.

## Tech debt deferred

From today's `docs/tech-debt-report.md` (0 critical, 4 warnings, 2 suggestions):
- `app/Services/Onboarding/CaptureForms.php` — 1,284 lines, split per form family.
- `pounds()` duplicated at `CaptureForms.php:612` and `OnboardingChatDirector.php:6306`.
- Module label vocabulary in three places: `NextActionsService::moduleLabel()`, `ActionsDashboard.vue:125`, `resources/mobile/views/Actions.vue:68` — the server should send the label.
- `NextActionsService::focusAreas()` — 86 lines; extract the module-card assembly.
- `CaptureForms::spouseInputs()` now serves seven single-write schemas; rename to `singleWriteInputs()`.
- `ActionsDashboard.vue:115` `MODULE_ROUTES` is now only a fallback behind the server destination.

Still open from yesterday: "Free plan" hardcoded in the cap statement (`OnboardingChatDirector.php:1105`); `handleFormTurn` at 142 lines; `sections()`/`blocks()` overlap in the capture-form mixin; spouse schemas use tool field names as form keys (deliberate).

## Branch and deploy state

- Branch: `dev` at `034b593ac` (== `origin/dev`). Working tree clean apart from the long-standing untracked `workforce/` and `docs/diagrams/` files inherited from earlier sessions.
- Unpushed commits: none.
- Deploy status: production (fynla.org) at main `94ad79c33` — the forms release, deployed and verified 04:30–04:50. csjones on `dev` `034b593ac` with matching bundles. **Dev is nine commits ahead of main; releasing them is priority 1.**
