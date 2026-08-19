---
type: handover
mode: session-end
date: 2026-08-19
session: 1
repo: fynla
branch: fyn/charitable-giving-and-link-layer
---

# Session Handover — 2026-08-19, Session 1

## Where things stand

Two related pieces of work, both driven by one CSJ goal stated mid-session:
**any record the database models should be writable by Fyn, and every write
should hand the user a page to confirm it on.**

The first landed and is merged (**PR #703**, `dev` at `7d3693824`, deployed to
csjones and verified on web, `/m` and the iOS simulator): advice mode could not
pass a *non-asset* record to the Fyn that writes it. The second is committed and
pushed but **NOT merged** (**PR #704**, branch
`fyn/charitable-giving-and-link-layer`): charitable giving had no column to live
in, and field writes had no link back to a page.

Everything claimed below was verified in a live browser with database and audit
evidence, except where the Verification section says otherwise. PR #704 carries a
migration, so merging it reaches csjones.

## Priorities for the next session

1. **PR #704 needs CSJ's merge decision** — BLOCKED ON CSJ. It contains a
   migration (`users.charitable_donations`) that reaches csjones on merge. The
   work is verified live on web but the `/m` bundle has not been rebuilt, so the
   new Expenditure category is not yet visible there. Ask before merging or
   rebuilding.

2. **Fyn bypasses the Premium capability gate** — BLOCKED ON CSJ (tier policy,
   not engineering). `set_expenditure` and `capture_charitable_giving` write
   detailed expenditure categories straight through the model, while
   `UserProfileController` returns `capability_denied` to Free users for the
   exact same fields (`DETAILED_EXPENDITURE_FIELDS`). Either Fyn should respect
   the gate or the gate is wrong; both are CSJ's call.

3. **`/m` bundle rebuild** — the new "Charitable donations" category is in
   `resources/mobile/views/Expenditure.vue` and in the profile payload, but `/m`
   serves a built bundle. Needs `public/m-build/` rebuilt and uploaded. CSJ has
   asked to be warned before any rebuild.

4. **Dependants on `/m` personal-information** — CSJ decided (2026-08-19) to
   extend the existing `/m` Personal Information page rather than build a new
   family screen. It already shows spouse; dependants are missing. `GateRoutes`
   already points `FAMILY_DETAILS` at `/personal-information` for mobile, so the
   link works and lands on a page that does not yet show what was recorded.

5. **`UserProfileController` zeroes all seven `ExpenditureProfile` category
   columns** on every expenditure save (lines ~206 and ~442, user and spouse).
   Harmless today only because those columns are already vestigial — 0 of 4 local
   rows carry a value. Decide whether to populate them or drop them.

6. **Models Fyn still cannot write** — all live, all with controllers and pages:
   `DisabilityPolicy`, `SicknessIllnessPolicy`, `PersonalAccount`, `CashAccount`,
   `LetterToSpouse`, `SavingsGoal`. CSJ scoped the goal to "user financial and
   household records", which includes these. Not started.

7. **`/m` renders "now.Recorded"** — the pre-tool and post-tool text concatenate
   without a space on `/m` only. The stored content has the space, so it is a
   `/m` rendering artifact. Cosmetic, seen live on csjones.

8. **The `fyn.reply.view_record` link is asset-only on native.** A non-asset
   capture (a donation) shows the confirmation with nothing to tap, while a
   pension gets a View link. `FynCaptureLinkTests` fails on a non-asset message
   for this reason — that failure is correct behaviour, not a regression. PR #704
   fixes the server side; native was last exercised before it.

## Context to load

- `April/April24Updates/spec/00-canonical.md` — the two-write-states Fyn contract.
  Everything this session touched sits on the advice → capture handoff it defines.
- `app/Services/AI/WriteIntentClassifier.php` — the deterministic write route.
  Its entity vocabulary is what makes asset writes reliable and is where the
  household record types were added. Read the comments: they record three
  previous occurrences of the same bug.
- `app/Constants/GateRoutes.php` — the one route table. `ENTITY_DESTINATIONS`
  (records with an id) and the new `FIELD_GROUP_DESTINATIONS` (writes without
  one) both live here deliberately; a second table elsewhere is the Rule 20
  failure its own docblock names.
- `app/Traits/HasAiChat.php` around the `ENTITY_EVENTS` loop — where a write
  becomes an SSE event, and where a write with no record id now resolves a
  destination instead of being dropped.
- `tests/Feature/UserProfile/CharitableGivingStorageTest.php` — the seven guards
  for the storage change, including the one asserting no frontend code writes the
  derived annual column by hand.
- `August/August18Updates/REPORT-2026-08-18-capture-tool-coverage.md` — yesterday's
  three dead ends. Today's five layers are the same disease one level up; that
  report is the shortest way to recognise the pattern.

## Completed this session

**PR #703 — merged to `dev` (`c7e0f7c9e`, merge `7d3693824`)**

Advice Fyn could not hand a non-asset record to the Fyn that writes it. Five
layers were independently blocking, each the same shape as the budgeting alias
fixed on 18 August — a turn that asks for something it cannot record:

1. `Planner` routed "record this fact about me" to **`learn`** — write to
   *memory*, not the user's data — then re-planned into the same decision until
   the 8-cycle cap and emitted the defer message. **Nothing persisted, not even
   the user's own message.**
2. `WriteIntentClassifier`'s vocabulary was assets-only, so the deterministic
   route that makes asset writes reliable never fired for a donation, spouse,
   dependant, spending, work details or State Pension.
3. `delegate_to_capture`'s schema told the model to capture "dc_pension,
   savings_account, property, etc.", so the LLM recovery path could not name
   anything else. Both provider variants, v1 → v2.
4. `<handoff_guidance>` omitted every household fact **and existed in two copies**
   (`FynSystemPrompt`, `AdvicePromptBuilder`) that had drifted. Now one
   `WRITABLE_RECORD_TYPES` both compose from.
5. The capture turn framed unmapped entity types as the **savings** focus ("not
   in scope for this Cash & Savings turn") while carrying the full write
   catalogue underneath, and told the model to call the appropriate **`create_`**
   tool when donations use `capture_charitable_giving`.

Also collapsed `OnboardingPromptBuilder`'s duplicate capture template into
`FynCaptureTurnInstructions`. The copies had already drifted: the legacy one had
never gained the INTENT EXCEPTION block, so under the legacy prompt architecture
nothing stopped the model inventing a provider or value to satisfy a tool call.

Verified live on all three surfaces, each with audit chain and database state:
web (`1800.00`), `/m` on csjones (`2400.00`), iOS simulator (`3600.00`).

**PR #704 — pushed, not merged (`a80d53fd8`)**

- `users.charitable_donations` monthly column, backfilled from the annual figure.
  The annual figure is derived in one place (a `User` mutator) because IHT
  planning, `ResolvesIncome` and `PersonalAccountsService` read it.
- `UserProfileController` now validates and stores the field it had been silently
  discarding, on both the user and spouse blocks.
- `GateRoutes::forFieldGroup()` — all ten capture field groups resolve to a page.
- Writes with no record id emit the write event clients already render, carrying
  a destination. No client changed; `/m` and native already handled it.
- `PERSONAL_DETAILS` / `FAMILY_DETAILS` `mobile => null` fixed; Architecture test
  updated deliberately.
- `AiChatPanel` refreshes the cached user before navigating to confirm.
- Charitable donations added to `/m` Expenditure and the shared profile payload.

## Verification state

- **PR #703**: 2,070 passed / 0 failed (Fyn, AI, onboarding) at `c7e0f7c9e`.
  Live on web, `/m` (csjones), and iOS simulator (iPhone 16e, iOS 18.6,
  `Fynla-Staging`). Native regression run of `FynCaptureLinkTests` with its
  default asset message: **passed in 54s**, including the View link and
  navigation.
- **PR #704**: 1,420 passed / 0 failed (profile, onboarding, Fyn, agents, tiers,
  architecture, AI) at `a80d53fd8`. Live on web end to end.
- **Full suite earlier today**: 6,838 passed / 22 failed at the pre-#703 tree.
  All 22 unrelated — `NativeSessionApiTest` passes 33/33 in isolation
  (cross-test pollution), and the 19 `WordDocxIngestor*` failures need
  `composer install` for `phpoffice/phpword`, which is absent from `vendor/`.
- **Not verified**: PR #704 on `/m` (bundle not rebuilt) or native (not rebuilt
  since #703). Full suite not re-run against `a80d53fd8`. The `ExpenditureForm`
  wipe fix has no frontend unit test — it is covered by the live browser walk and
  by the architectural guard that no frontend code writes the derived column.

## Decisions and dead ends

- **CSJ decided (2026-08-19)**: charitable giving gets a **monthly column with
  the annual derived**, not a fix to the annual field alone and not folded into
  `gifts_charity`. Do not re-litigate.
- **CSJ decided**: dependants go on the **existing `/m` personal-information
  page**, not a new `/m` family screen.
- **CSJ decided**: scope is **user financial and household records** — excludes
  module profiles, settings, assumptions, notification preferences, document
  uploads.
- **CSJ decided**: `ExpenditureProfile` is canonical *for the total*. I had
  framed it as holding the categories and was wrong — its seven category columns
  are vestigial (0 of 4 local rows populated) and the real categories are ~20
  columns on `users`. `ResolvesExpenditure` reads the profile total first, which
  is what makes that decision correct as stated.
- **I was wrong that Fyn could not record per-category spending.**
  `set_expenditure` already handles 22 categories, preserves omitted ones and
  recalculates the total. Do not rebuild it.
- **`settings.json` PreToolUse guard removal is settled** — CSJ confirmed
  `fbbc3a1f3` is intended. Only the Bash dangerous-command guard remains. Do not
  raise it again.
- **Rejected: adding tool names to `captureToolSet` alone.** Four of the six
  grouped-extract schemas live outside `getTools()` for the token budget, and
  `allowedToolsOverride` *narrows* `getTools()` — so naming them would have been
  a silent no-op. The pool had to be widened first.
- **Rejected: emitting `entity_created` for field writes.** Semantically wrong
  for an update to `users`, and native would render "Saved X". `entity_updated`
  with a null id was already handled by every client.
- **Rejected: re-syncing the two capture templates in lockstep.** Rule 20 says
  consolidating is part of the fix; the lockstep test correctly caught the
  one-sided edit.
- **The prompt was not the lever for the LLM refusal.** Widening
  `delegate_to_capture` and `<handoff_guidance>` reached the model (confirmed in
  the episodic blob) and it still refused. The deterministic classifier is what
  made it work — that is why assets were reliable and nothing else was.

## Things that will bite you

- **Test users hit a daily Fyn usage cap.** `john@example.com` produced
  `token_limit` after ~15 turns and the symptom is a turn with **no assistant
  reply and no audit rows** — indistinguishable from a code bug until you drive
  `handleInlineCapture` directly and see the event. Use a fresh user, do not
  reset counters.
- **A capture can fire the level-up celebration**, whose modal swallows clicks
  aimed at the Fyn dock. Any browser harness must dismiss it first.
- **`api/user/profile/*` returns 404 in tests** without
  `config(['app.payment_enabled' => true])` plus `TierConfigurationSeeder` and
  `RolesPermissionsSeeder`. It reads like a missing route and is not.
- **Editing one tool-schema description regenerates four artefacts**: three
  golden-master fixture sets (`CAPTURE_TOOL_SCHEMA_GOLDEN`,
  `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN`, `CAPTURE_PROMPT_OVERLAY_GOLDEN`) and
  `docs/superpowers/specs/fyn-system-prompt.snapshot.txt`. Nothing tells you.
- **The Chrome extension never connected this session** — `list_connected_browsers`
  returned empty even after CSJ restarted it. All browser work went through
  Playwright driving real Chrome; the harness is at
  `<scratchpad>/fyn-scenario.js` and handles the cookie banner, six-box MFA, the
  Fyn dock, the celebration modal, and following the View link.
- **`./vendor/bin/pest --parallel` fails to start** — a `use RuntimeException;`
  notice in `ProceduralCorpusLoaderTest.php` breaks paratest's argument parsing.
  Run sequentially.
- **`phpoffice/phpword` is in `composer.json` but not in `vendor/`**, so 19
  `WordDocxIngestor*` tests fail locally on any full run.
- **Two test users left behind**: `fyntest-0819@example.com` (csjones, id 325,
  password `Password1!`) and `fyntest-local@example.com` (local, id 1298,
  password `password`). Both Premium, both carry a donation. Remove when done.

## Branch and deploy state

- Branch: `fyn/charitable-giving-and-link-layer`, pushed, **PR #704 open against
  `dev`, not merged**.
- Unpushed commits: none.
- `dev` at `7d3693824` (includes PR #703). `main` untouched and still far behind.
- csjones: on `dev` at `7d3693824`, caches rebuilt, `/m` returning 200. It does
  **not** have PR #704.
- Production (`fynla.org`): nothing deployed today.
- TestFlight: build 7, unchanged. Nothing this session needs a build — #703 is
  server-side, and #704 has not reached csjones.
- Untracked and deliberately left alone: `docs/diagrams/*.excalidraw`,
  `docs/diagrams/how-fyn-thinks.html`, `docs/mobile/designer-brief.pdf`,
  `.claude/skills/excalidraw/scripts/__pycache__/`. None are this session's work.
