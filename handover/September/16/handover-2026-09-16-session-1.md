---
type: handover
mode: session-end
date: 2026-09-16
session: 1
repo: fynla
branch: dev (main checkout); worktree at /private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint (may not survive)
---

# Session Handover — 2026-09-16, Session 1

## Where things stand

The Save Tax property capture form is live on fynla.org: the property step is a structured form on web and `/m` (Home / Second home / Buy to let), Fyn asks "Do you have another property to add?" after Save, the form-capable prompt is just "Now your property.", native keeps the typed prompt. Four releases went out today (PRs #859/#860, #861/#862, #863/#864, #865/#866; main `ac40f1dd3` == dev `7d44954c4` plus the spec commit). Every ruling and every review finding is in `handover/September/16/sdd-property-form/progress.md`. The next programme — the same treatment for ISAs, bank/savings and investment accounts — is fully specced and committed on dev; nothing of it is built. CSJ tested prod personally from a fresh `c.jones@csjones.co` registration and found two display gaps (rent on `/m` detail and on the web card), both shipped.

## Priorities for the next session

1. **BLOCKED ON CSJ — Part F decisions for the account forms** (`docs/superpowers/specs/2026-09-16-savetax-account-capture-forms-design.md`, Part F): which kinds each form offers; bank joint accounts fixed at 50/50 with no share field; interest rate required on savings kinds, optional on current accounts; whether the ISA form asks "paid in this tax year"; bonds/VCT/share schemes stay on the typed path. Ask these first thing; the spec's proposals are reasonable defaults if CSJ says "go with the spec".
2. **Build the account forms per the spec.** Part A first as its own PR (tool and entity type per schema kind in `CaptureForms` and `handleFormTurn`; `toolInputs`/`summarise` split by schema; `text` field type and bounded `percent` in the shared mixin and both renderers). Then the three schemas, the three `_more` states, the short lead-ins, tests, csjones gate, release. Do NOT use subagent-driven development for this unless CSJ asks — see Lessons.
3. **Parked by CSJ, raise only if asked:** the eleven mapping PRs #829–#839 and the 55 mapping bugs (`CSJTODO.md`); the native iOS renderer for the form; the iPhone registration bounce; the web Retirement actions block.
4. **Adjacent observations for CSJ to decide** (in the PR #859 body and CSJTODO): after a partial refusal the saved kind stays editable; a form at a non-form state returns a friendly message not a 422; the web SPA hung after opening Chat with Fyn in a phone-width desktop Chrome window (three times on csjones, full width fine) — not investigated.

## Context to load

- `docs/superpowers/specs/2026-09-16-savetax-account-capture-forms-design.md` — the next programme, with file:line pointers for every mechanism, the exact corpus/PHP edits, tests to mirror, the csjones gate, and the decisions to ask CSJ. Read it in full before anything else.
- `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md` — the architecture the account forms extend (header contract, event contract, one schema home, two renderers, one mixin).
- `handover/September/16/sdd-property-form/progress.md` — the ledger: Rulings 1–22 with reasons and costs, every review finding, the deferred minors, the live-walk evidence, and the release records. Do not re-litigate a ruling; extend it.
- `app/Services/Onboarding/CaptureForms.php` — the one schema home; `property()` ~line 163 is the shape to copy; `toolInputs()` ~91 and `summarise()` ~132 are the property-shaped bodies Part A splits.
- `app/Services/Onboarding/OnboardingChatDirector.php:3676` — `handleFormTurn`, with `create_property` hardcoded at ~3735/3741/3746/3749 (Part A1).
- `app/Services/Onboarding/OnboardingStateMachine.php:1274` — `nextFromEmploymentMore`, and `nextFromPropertyMore` further down: the `_more` loop pattern to copy three times.
- `docs/tech-debt-report.md` — today's audit (0 critical, 4 warnings, 8 suggestions).
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_small_changes_direct_no_delegation.md` — the process lesson from today, in CSJ's words.

## Completed this session

- Tasks 6–12 of the property form plan via subagent-driven development (Tasks 1–5 were yesterday): controller flag on send/queue/stream/start/action (`d3bd4b114`), web store and API (`414350f70`), web renderer with the shared mixin (`eb1ce1e59`), `/m` transport and mixin (`2fc53e5e5`), `/m` renderer (`2e2fe17e2`), final review fix wave (`0794aa8d0`: native keeps the typed prompt via `form_prompt_text`, extractor skipped on form turns, panel tests, queued round-trip test), live-walk fixes (`bfacfaf33`, `650b599b5`: prompt text on the live web form, `/m` heading/hint/error styling, "?." copy, distinct row ids).
- CSJ additions: Second home kind (`3b409e3cd`), form prompt shortened to "Now your property." (`3d40fb64a`), the another-property question (`25325d530`, corpus state `campaign_property_more` + `nextFromPropertyMore`), `/m` property detail rent rows (`37e24a3a5`), web property card rent row (`132be0aee`).
- Spec for the account forms (`fb58522f9`, PR #867).
- Prod: four deploys; backup `~/release-backups/2026-09-16a/pre-release.tgz` before the first; `c.jones@csjones.co` (user 721) purged at 09:05 after CSJ asked; CSJ re-registered as user 722 and that account is still there with two properties.
- Memory: `project_release_2026_09_15.md` extended with all four releases; new `feedback_small_changes_direct_no_delegation.md` indexed in `MEMORY.md`.

## Verification state

- Pest across Onboarding, Unit Onboarding, AI, Unit AI, Property, Tiers, Architecture: 2438 passed / 0 failed at `2e2fe17e2`; the fix waves re-ran Onboarding + Unit Onboarding + AI: 1607 passed. The three CSJ additions ran focused files only (162, 683, 694 passed) at CSJ's instruction — no full pass since `0794aa8d0`.
- Vitest: 1355 passed at `0794aa8d0`; later changes ran focused directories (all green).
- Live on csjones in CSJ's Chrome (web fresh, resume, refusal, history; `/m` fresh, refusal, second home) and via the API (Free cap refusal; the another-property loop; native-style no-header request). Prod: home, `/m`, login 200 after each deploy; `fyn:procedural:validate` active; laravel.log clean (last error 2026-09-15 07:56).
- NOT verified in a browser: the another-property bubbles rendering (Chrome extension stopped responding; server behaviour API-proven), the Free-cap refusal rendering on web (API-proven; the same render path was verified on the duplicate refusal), the three-box form on web at phone width.

## Decisions and dead ends

- CSJ 2026-09-15 17:01: free-text onboarding capture replaced by structured forms; property first. CSJ 2026-09-16: add Second home (Ruling 20); the form prompt is just the lead-in and future form steps follow the same pattern (Ruling 21); ask "another property?" after Save (Ruling 22); no full test pass for small changes, browser test then ship.
- Rulings 1–19 (yesterday and overnight) are in the ledger; the ones a successor most needs: one mixin for both renderers (1); a refused form re-opens with its errors on both surfaces (13); after a reload, Continue re-emits a fresh form and the refused one in history stays locked (14); `form_prompt_text` is a corpus DATA key and native's typed prompt is byte-identical to pre-branch (17); the fact extractor never runs on a form turn (18); the live `capture_form` event renders its prompt text as its own assistant row (19).
- Dead ends: the Free cap cannot be exercised with test user 396 on csjones (it resolves to Premium via entitlements); use a freshly created user. The desktop-to-`/m` token bridge never fires on an automated navigation; inject `m_scaffold_token` directly. Registration and password entry are prohibited for the Chrome tool; mint Sanctum tokens in tinker instead.

## Lessons learnt (CSJ's frustrations today — read before choosing a process)

- **A 30-line change took an hour because I delegated it.** The another-property question is one corpus state and one branch method. I dispatched an implementer and told it to run a full Pest pass; the run hit the 120-second tool timeout and was backgrounded, the agent killed it to avoid a DB collision, restarted focused files, and idled between every step waiting for notifications. CSJ: "this has taken an hour, for a 5 minute change, why?" and "no full pass, just test in browser, these are small changes". Rule now in memory: changes under ~50 lines with a clear design are done directly in the worktree, focused test files only, browser or API check on csjones, then PR, merge, release without pausing. Subagent-driven development is for multi-task plans only.
- **Never ask an agent to run a multi-minute command in the foreground.** It will be auto-backgrounded, the agent will go idle, and you will be nudging it. If a long run is needed, the controller runs it in the background itself.
- **Serial release steps cost minutes each.** Merge, csjones checkout, release PR, merge, maintenance, rsync, up: seventeen minutes for a "merge". Batch what can be batched (open the release PR while the csjones pull runs; build prod in the main checkout while csjones builds in the worktree), and tell CSJ the ETA before starting.
- **The Chrome extension dies under load.** Three separate tabs hung after opening the chat panel in a phone-width window, and once the whole extension stopped answering. Do not loop on retries: two failures, then fall back to the API (`curl` the action/messages endpoints with and without `X-Fynla-Forms: 1`) and say plainly what was not browser-tested. Also: a stale token in any open tab triggers a revoke-all on load, so close old tabs before minting a new token.
- **Answer "why is this slow" with the mechanism, not reassurance.** CSJ asked three times. The honest answer each time was a process choice of mine; saying so and changing the process is what unblocked the session.
- **Purge c.jones without being asked after every prod fix.** It did not exist at the first release, CSJ registered it during testing, and I had to be asked. The memory rule already said to check and purge before reporting.
- **CSJ's data model questions are usually right.** "The buy to let does not show the income" was not a form bug: the row was correct, the web detail had always shown rent, and the `/m` detail never had. Check every surface's display of a field before touching the capture path.

## Things that will bite you

- The worktree may be gone; recreate with `git worktree add <path> dev` from the main checkout (never switch the main checkout off `dev`), copy `.env`, symlink `vendor/` and `node_modules/`. The SDD workspace for the property plan was deleted per the skill; its records are in `handover/September/16/sdd-property-form/`.
- The main checkout's `public/build` and `public/m-build` hold fynla.org-base builds (used for the last prod upload). Vite via `./dev.sh` serves locally as normal; do not serve the built files locally without rebuilding with the csjones script.
- csjones is on `dev` `7d44954c4` with dev bundles. Test users on csjones: 397 `formwalk-0915@example.com` (2 properties, Free, parked at `campaign_property`), 398 `formwalk-m-0915@example.com` (1 property, Free, past the property step), 396 (Premium via entitlements). All `MapTest2!`; tokens minted in tinker.
- The `/release` skill is user-invocation only; the model cannot call it. CSJ types `/release`, then the model runs it. Prod procedure: `deploy/DEPLOY.md` lines 86–130 (maintenance on, rsync `app config database routes resources/views fyn-memory resources/js/data public/pages` + both bundles, never `bootstrap/`, dump-autoload, migrate, seed, caches ending `config:cache`, up, validate, log watch).
- A guard hook blocks any Bash command whose text contains the forbidden artisan words, even inside a heredoc.
- The pre-existing dirty files in the main checkout (two excalidraw diagrams, `workforce/ops/log/*`, six `workforce/ops/reports/brief-*.md`, `handover/September/15/handover-2026-09-15-session-1.md`) are not from these sessions and were left untouched.

## Tech debt deferred

From `docs/tech-debt-report.md` (0 critical): four routers in `resources/js/store/modules/aiChat.js` duplicate their event switch; `handleFormTurn` hardcodes `create_property` (Part A1 of the spec fixes it); the `/m` message loop is duplicated in `Dashboard.vue:321` and `MobileChrome.vue:141`; `CaptureForms::toolInputs`/`summarise` are property-shaped (Part A2). Suggestions: `setChoice` hardcodes `ownership_percentage`; no watcher on `values` in the mixin; `streamNextQueued`'s `form_received` no-op; unbounded forward scan in `loadTranscript`; `_form` error key has no renderer; choice-field `<label for>` nit. `vault-sync` and a full Pest pass were not run after the last three commits.

## Branch and deploy state

- Branch: dev at `7d44954c4` (== origin/dev); main at `ac40f1dd3` (== dev minus the spec commit).
- Unpushed commits: none.
- Deploy status: prod (fynla.org) at main `ac40f1dd3` content, all four releases live; csjones at dev `7d44954c4`.
