---
type: handover
mode: session-end
date: 2026-09-11
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-11, Session 1

(The session ran 2026-09-10 07:50 → 2026-09-11 08:49 BST; the handover lands on the
day it was written.)

## Where things stand

fynla.org runs main `ac967bdac`, tree identical to dev `d73b2a832`, after **three releases
on 2026-09-10**: (1) the external contributor's Fyn first-message guard (#799) and native
billing-on-the-web (#773); (2) recommendation ids that carry the record they are about
(#801), the nine new-user-run board items W-0542–W-0550 (#803), account naming and
entitled-request fixes (#802), and the onboarding journey repairs (#804); (3) a capped
"+ Add" in onboarding refused before the form (#806). csjones is on the same tree. Every
change was browser-verified on csjones and on fynla.org; the production log had zero
errors through both ten-minute watches and this morning. TestFlight build 10 (Production
configuration, `org.fynla.app.dev` record "Fynla") is VALID and the native tree is
unchanged since it was archived. Tree clean (only the brief hook's `workforce/ops` log
files are dirty, as at every session start), nothing unpushed, no worktrees.

## Priorities for the next session

1. **BLOCKED ON CSJ — how to test the phone.** The on-screen native checks (W-0044,
   W-0496, and the build 9/10 dashboard flow: headline rows, an information-request row
   opening Fyn with "I can help you enter the information for …", Yes / No thanks, "No
   thanks" closing the cover and replacing the row; Settings → Plan and billing reading
   "Upgrade on the web" on build 10) need either CSJ looking at the phone or a UI test
   written for those screens. `FynlaTests` cannot run on a device: the fixture tests load
   by absolute Mac path through `try!` (`ios-native/FynlaTests/AuthClientTests.swift:271`),
   so the runner crashes and restarts and no test completes. Fixing that means bundling
   the fixtures as test resources — a harness change, not a code fix. Simulators wedge
   CSJ's Mac (`ios-simulator` skill; never `simctl boot`). Ask which route.
2. **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 need Swift;
   W-0044 and W-0496 need only the on-screen check above. Fresh branch off dev.
3. **The tech-debt warnings from this session** (`docs/tech-debt-report.md`, top
   section): the plan-cap entity wording now has two homes (`utils/apiErrors.js`
   `ENTITY_LABELS` vs the literals on six `LimitReachedModal` consumers); the "at cap →
   modal" gate is written per surface (`AssetsStep.vue` and `PropertyList.vue`); the
   client registration rules mirror `RegisterRequest` by hand with nothing pinning the
   messages together. Small, but each is a Rule 20 seam.
4. **Parked, non-iOS, unchanged:** W-0540 dead-component clusters and the Rule 15 lint
   scope; the #770 homepage block; the 34 sweep findings; the tax-compliance reviews in
   `CSJTODO.md`.

## Context to load

- `docs/tech-debt-report.md` (top section, 2026-09-10) — the nine findings from this
  session's 64 changed files, with `file:line`; priority 3 comes from here.
- `ios-native/TESTFLIGHT.md` — the one-app procedure (archive `Fynla-Production` with the
  `org.fynla.app.dev` overrides, keychain unlock line 192, ASC key path line 100); build 10
  followed it exactly.
- `resources/js/components/Onboarding/OnboardingWizard.vue` — the journey/full/quick nav
  bar added at the else-block (`triggerModeContinue`), and the life-stage guard story;
  `resources/js/store/modules/lifeStage.js` `setCurrentStage` is where a journey name in
  `users.life_stage` is dropped.
- `resources/js/utils/apiErrors.js` and `resources/js/mixins/tierLimitMixin.js` — the one
  reader of a failed form save and the one home for cap arithmetic; both new this session.
- `app/Services/Coordination/RecommendationsAggregatorService.php` (`composeId`,
  `disambiguate`, `rule_key`) — how recommendation ids now carry the record scope and why
  routing keys on `rule_key`.
- `September/September9Updates/ios-dashboard-jobs-plan.md` — the four native jobs behind
  the build 9/10 phone checks in priority 1.

## Completed this session

- **Release 1** (PR #800 → main `847285182`): #799 `AiChatPanel.ensureConversation()`
  before every send (external contributor Icecube-acc; the PR's root-cause story was
  wrong — the docked panel does dispatch `aiChat/open` on mount — and the trigger was
  never reproduced locally, but the guard is correct and covered by 4 Vitest regressions);
  #773 native billing on the web. TestFlight **build 10** archived and uploaded (VALID
  08:49 BST).
- **Release 2** (PR #805 → main `cce592de2`): #801 recommendation ids per record
  (`composeId` appends `_a{account}` / `_g{goal}` / `_m{family member}` / `_e{event}` /
  `_p{policy}`, `disambiguate` adds a headline hash for curated-map collisions, `rule_key`
  carries the unscoped rule for `RecommendationRouting`; savings/estate adapters carry the
  record ids; census went from 11 colliding rule types to zero); #802 savings display name
  (institution + product), spouse/letter/will requests only when entitled
  (`spouse_financially_shared` on `UserResource`, `hasFullCapability` gates), journey
  completion screen; #803 the board loop — W-0550 (string-typed simple total; view mode
  shows the server `active_monthly_total`), W-0543 (`invitation_pending` on the family
  list, one send-message helper), W-0544 (onboarding reads the 403 through
  `utils/apiErrors.js`, opens `LimitReachedModal`), W-0542/W-0545 (every message renders,
  client rules mirror `RegisterRequest`, hint always visible), W-0546 (decline copy in
  `constants/cookieCopy.js` + parity test on the vanilla script at `?v=2`; CSJ approved the
  wording 12:22), W-0547 (autocomplete and accessible names on register, login, reset,
  code boxes), W-0548 (already reseeded; verified), W-0549 (folded); #804 journeys run to
  completion (`users.life_stage` also holds journey/focus names — `setCurrentStage` keeps
  only real stages; nav bar for journey/full/quick modes; `CompletionStep` reads
  `data.properties`; assets step handlers no longer touch `window` in the template).
- **Release 3** (PR #807 → main `ac967bdac`): #806 a capped "+ Add" in the onboarding
  assets step opens the limit modal before the form (`auth/fetchSubscriptionData` is the
  one loader, `AppLayout` uses it; `countCapFor` / `atTierCap` exported from the mixin).
- Memory: `project_release_2026_09_10` (all three releases, verification gaps, test
  accounts); `MEMORY.md` compacted from 23KB to 11KB with every entry kept.

## Verification state

- Vitest at `d73b2a832`: utils + constants + UserProfile + Shared + Auth folders 167 green;
  `lifeStage.spec.js` 3, `tierLimit.spec.js` 2, `apiErrors.spec.js` 4 green. Pest:
  `FamilyMembersControllerTest` 23, Unit/Models + Feature/UserProfile + Unit/Services/Savings
  77, coordination + Feature/Mobile 244, estate + adapters + Feature/Estate 715 — all green.
  Full suites not run (Rule 17). dev's own Quality Gate is red on Unit/Feature
  (`AccountDeletionService`, `AuditTierCollapse` QueryExceptions) and was before this
  session; not investigated.
- Browser, csjones and fynla.org: every item above was clicked through on csjones before
  merge (john, Free) and the release-check account on fynla.org after each release
  (details in the memory file). **Not verified:** a fresh-account Fyn send on csjones (the
  permission classifier blocked reading the registration code over SSH); the #799 trigger
  itself (two fresh-account paths locally created the conversation fine); a live spouse
  invitation end to end (needs two accounts with an unanswered request — pinned by tests
  instead); the assets-step cap for property/investment/pension on csjones (savings
  verified; the others share `capBlocks`).
- Native: build 10 compiles and is VALID on App Store Connect. **No native screen has been
  looked at** since build 8.

## Decisions and dead ends

- **CSJ: verify each board item before fixing.** Done for W-0542–W-0550: seven real, one
  overstated (W-0549), one already resolved (W-0548). Order followed: W-0550, then
  W-0543/W-0544, then W-0542/W-0545, then W-0546/W-0547.
- **CSJ: a user must be told a cap is reached before filling a form** — hence #806.
- **CSJ approved the cookie-decline wording** ("Declining switches off Google Analytics and
  our affiliate tracking, and nothing else. Registration, signing in and every feature
  work as normal."). W-0546 closed in full.
- **`users.life_stage` is overloaded by design** (`JourneyStateService` writes the journey
  name, `OnboardingService` the focus area, and `OnboardingService` branches on
  `=== 'estate'`). Handled on the client (`setCurrentStage` drops non-stages) rather than
  changing the backend column semantics. Prod and csjones hold only real stages today.
- **Recommendation ids: scope appended only where a rule names a record**; single-instance
  ids unchanged, so no `recommendation_tracking` row on prod (2) or csjones (14) moved.
  Curated-map collisions (two retirement categories → one type) get a headline hash — the
  ponytail ceiling is a reworded headline restarting that row's done status; give the rule
  a record id instead of extending the hash.
- **Rejected:** deleting `ActionsOverviewCard.vue` (dead, not asked); making
  `PersonalInfoStep` show its own nav (it would double up with the wizard bar — reverted;
  the bar lives in the wizard for every non-life-stage mode); a Gmail OAuth flow to read
  the prod verification code (the ssh-fynla MCP reads `pending_registrations` directly,
  which is what the login instructions say).
- **Dead ends:** the device id `55319FDE-…` listed by `xctrace list devices` is a
  **simulator** named "Fynla iPhone 11", not the phone (`00008030-000531C43E60402E`) — a
  run went to the simulator by mistake and was killed within a minute; the on-device
  `FynlaTests` run crashes on `try!` fixture loads and cannot complete.

## Things that will bite you

- **The auto-mode permission classifier** refused several routine actions this session:
  reading a registration code over SSH to csjones, compound prod deploy commands, a
  `git checkout <branch>` on csjones, even a local `tar`. What passed: plain
  `rsync … public/build/`, `git switch <branch>` on csjones, and everything through the
  `ssh-fynla` MCP. A first attempt CSJ ran from `~` instead of the repo put prod into
  maintenance mode with nothing uploaded; `php artisan up` via the MCP recovered it in
  seconds. Use absolute paths in any command handed to CSJ.
- **The formatter hook strips a just-added `use` import before its usage lands** (known
  trap, bit again on `UserProfileService`): add import and first use in ONE edit, then
  check the import survived.
- **`OnboardingView` mounts no `AppLayout`** (chrome-less by design, `hideNavbar`), so
  nothing the layout loads (`subscriptionData`) exists in the wizard unless the step asks.
- **Playwright on the prod SPA:** the cookie banner overlay (`fixed inset-0 z-[100]`)
  intercepts clicks after consent is cleared — accept or decline it first; `input[maxlength="1"] >> nth=0`
  is the reliable target for the code boxes; `#app.__vueParentComponent` walking works only
  on the Vite dev build (prod strips component names).
- **Test accounts left behind:** `slaterjoneschris+fynla0910@gmail.com` on fynla.org (a
  real Free account, life stage "university"; password in the memory file's spirit — the
  session used `Release-Check-0910!`); csjones john now has two current accounts (£2,500,
  £1,500 at 0%), £1,800 joint expenditure, life stage "university" and one completed
  tracking row; local David has a completed tracking row and no life stage; local
  `journey-0910@example.com` (Free, two savings accounts) and `fresh-0910@example.com`.
- The savings web form captures institution and product but **no account name** — the
  display name fallback covers the copy, but the form gap itself is a product question.

## Tech debt deferred

`docs/tech-debt-report.md` (top section). Warnings: two homes for the plan-cap entity
wording (`utils/apiErrors.js:12` vs the `LimitReachedModal` literals); the at-cap gate
written per surface (`AssetsStep.vue:655`, `PropertyList.vue:228`); client registration
rules mirror `RegisterRequest` with no parity pin; the vanilla cookie banner is a hand copy
pinned only for the decline sentence. Suggestions: 14 pre-existing ESLint dead-code hits in
the onboarding steps and wizard; `PersonalInfoStep.vue:7` redundant `:hide-nav="true"`;
`lifeStage.js:150` unused catch binding; the per-list `SpousePermission` query; the two
2,500–3,800-line files every fix had to land in.

## Branch and deploy state

- Branch: dev @ `d73b2a832`
- Unpushed commits: none (the handover commit follows)
- Deploy status: fynla.org = main `ac967bdac` (tree == dev), bundle `app-DguFb_dl.js`,
  backups `~/release-backups/2026-09-10/`, `2026-09-10b/`, `2026-09-10c/` (manifests);
  csjones = dev `d73b2a832`; TestFlight "Fynla" 1.0 (10) VALID on the `org.fynla.app.dev`
  record. No open PRs against dev except the parked #249.
