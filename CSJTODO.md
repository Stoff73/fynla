# CSJTODO — Fynla

*Last updated: 2026-09-14 session 3 — application mapping programme: overview, 17 (emails), 01 (auth), 02a (onboarding) and 02b (campaigns) mapped; 55 mapping bugs raised (none fixed, none decided). CSJ's focus for the next session: fix the mapping bugs; mapping paused at section 03. Commit 86ef97b41 on dev. Handover: `handover/September/14/handover-2026-09-14-session-3.md`*

## The board position

Computed from the board files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 338 |
| **resolved** | **332** — W-0541–W-0550 closed 2026-09-10 (W-0548 already resolved, W-0549 folded) |
| **outstanding** | **6** — all `deferred-ios` |
| critical | **0** |
| high | **0** |
| medium / low in scope | **0** |

Every non-iOS item is closed. The rule is unchanged — **a citation is not a verification**
— and **verify the instrument before trusting the measurement**: CSJ's 2026-09-10 order to
re-check the nine items before fixing found one already resolved and one overstated.

## Application mapping programme (CSJ, 2026-09-14) — in progress

Evidence-only maps in `docs/app-map/` via the `app-map` skill; index at `docs/app-map/INDEX.md`;
bugs raised (never fixed inside a run) in `September/September14Updates/mappingBugs2026-09-14.md`.

- [x] Overview baseline, 17 emails and push, 01 auth and registration, 02a onboarding (Fyn flow,
      legacy wizard, journeys, life stage), 02b Save Tax and Pension Check campaigns (funnels,
      hand-off, both walks, re-entry, Tax Strategy page; driven live on web and `/m`).
- [ ] **BLOCKED ON CSJ — mapping-bug decisions** (register at the top of the bugs file). Live
      user-facing first: MB-23 (paused user's next message gets no reply), MB-44 (Save Tax has no
      way back after "No thanks"), MB-37 (web savings verify page shows no accounts — which page),
      MB-45 (Tax Strategy: which list is canonical), MB-25 (paused walk can never resume), MB-38
      (Pension Check hardcoded bands, no cross-check), MB-54 (Save Tax drops the pension pot),
      MB-53 (re-entry re-asks pensions), MB-49 (natural correction produced no write). Dead code
      and copies: MB-01 + MB-09 + MB-29/30/31, MB-39, MB-41, MB-42, MB-43, MB-08, MB-10, MB-11,
      MB-06. Data and copy: MB-32, MB-34, MB-40, MB-14/15, MB-21.
- [ ] **NEXT — merge today's eleven PRs to dev in order, rebasing each onto dev after the one
      before (every branch edits the bugs register on adjacent lines; keep both sides), csjones gate
      per the admin-merge memory (four PRs change `resources/mobile/`: #831, #834, #835, #836 need
      `build:mobile`), then `/release` dev → main (CSJ types it). Order: #829 → #832 (stacked on
      #829) → #830 → #831 → #834 → #835 → #836 → #837 → #838 → #839 → #833 (the `bugsFixed.md`
      ledger — after merging, flip its rows from "Fixed, PR open" to "Fixed" with SHAs and make sure
      it is on dev, main and in the local root). Handover session 4 has the detail.
- [x] Fixed 2026-09-14, PRs open: MB-27+47 (#829), MB-48 (#830), MB-24 (#831), MB-56/57/58 (#832),
      MB-18/19/20 (#834), MB-50 (#835), MB-26 (#836), MB-28 (#837), MB-33 (#838), MB-35 (#839).
      MB-54's cause corrected (deterministic backstop after a model refusal; decision still open).
- [ ] **Remaining no-decision MBs** after the release, one per branch, test red first, live on web
      and `/m`: MB-17, MB-22, MB-16, MB-46, MB-51, MB-52, MB-55 (subject to MB-44). Unskip
      `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php` with MB-23.
- [ ] Mapping paused at section 03 (dashboard) until CSJ restarts it; index rows are ready.

## Next session starts here — property capture form (CSJ, 2026-09-15)

CSJ's decision 2026-09-15 17:01: onboarding stops parsing free text for property; a structured
form in the chat (Home / Buy to let), same property store, no model. Spec
`docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md`, plan
`docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md` (12 tasks), branch
`feat/savetax-property-capture-form` at `c7216000a` — Tasks 1–5 done and reviewed, resume at
Task 6 via subagent-driven development with the ledger in
`handover/September/15/sdd-property-form/`. Scope: Save Tax `campaign_property` only; web + `/m`;
native untouched (`X-Fynla-Forms` header). CSJ: verify in their Chrome, never headless; check
every subagent's work personally. Handover: `handover/September/15/handover-2026-09-15-session-2.md`.

## Next session starts here — iOS (CSJ, 2026-09-09; still open 2026-09-12)

- [ ] **BLOCKED ON CSJ — how the phone gets tested.** Build 10 (Production configuration,
      "Fynla" record, VALID 2026-09-10 08:49 BST) carries #773 and all of build 9. On-screen
      checks owed: headline rows; an information-request row opens Fyn with "I can help you
      enter the information for …"; Yes / No thanks; "No thanks" closes the cover and
      replaces the row; Settings → Plan and billing reads "Upgrade on the web" (Free).
      `FynlaTests` cannot run on the device — fixture tests load by absolute Mac path
      through `try!` (`FynlaTests/AuthClientTests.swift:271`), the runner crashes and
      restarts. Either bundle the fixtures as test resources (harness change) or write a UI
      test for the two screens; simulators wedge the Mac. CSJ decides.
- [ ] **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 need Swift;
      W-0044 and W-0496 have the Swift landed and need the on-screen check. Fresh branch
      off dev. Parity ledger: `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.
- [ ] **iOS harness debt**: the shared UI-test typing helper cannot clear the email keyboard
      off the password field on an iPhone 11; the 6 red `Local StoreKit configuration`
      tests are a real signal (no IAP products in App Store Connect).

## Parked, non-iOS — need CSJ, do not start unasked

- W-0540 dead-component clusters and the Rule 15 lint scope (carried since 5 September;
  now measured as MB-01 (153 files) + MB-09 (56 guard-blocked Vue public pages) in the
  mapping bugs file — one decision covers all three).
- The unstyled homepage pension-check block parked in PR #770's description.
- The 34 remaining sweep findings — decide, do not chase.
- Tax-compliance review — W-0367, W-0514, W-0508, W-0338, W-0470, W-0518, W-0498;
  design-lead / quality-lead on W-0497; chief-of-staff on W-0506.
- The savings web form captures no account name (institution + product only); the
  display-name fallback covers the recommendation copy, the form gap is a product call.
- **One page per module for dashboard routes** — `GamifiedDashboard.webRouteFor` (`/net-worth/cash`)
  vs `semanticDestinations.overviewPaths` / `GateRoutes::MAP` (`/savings`). Product call (PR #818).

## Settled by CSJ — do not re-raise

- **W-0144** — revocation of former wills is the law; the 28-day survivorship period is
  standard drafting. Defaults unchanged, no prompt needed.
- **W-0155** — consent is a single accept button. There is no withdrawal journey.
  `declineCookies()` is the banner's Decline path, not dead code.
- **W-0546 (2026-09-10)** — the decline copy is "Declining switches off Google Analytics and
  our affiliate tracking, and nothing else. Registration, signing in and every feature
  work as normal." Approved by CSJ; one home `resources/js/constants/cookieCopy.js`.
- **W-0544 (2026-09-10)** — a user is told a plan cap is reached BEFORE filling a form.
- **Recommendation ids (2026-09-10)** — a per-record rule gets one id per record
  (`_a{account}` etc.); single-instance ids unchanged. Never re-add a shared id.
- **W-0524** — agricultural relief is a property-type design decision, deferred.
- **One PR, not split.** **No parallel agents, of any kind, for any purpose.**
- **The board loop is web and `/m` ONLY.** Every iOS item defers, marked `deferred-ios`.
- **The board-loop skill is gospel, not guidance.**
- **(2026-09-09)** The seeded priority wins the ranking; the corpus workflow file is the one
  home for the onboarding table; the SaveTax consent gate is re-wired, not deleted
  ("No thanks"); the young family Junior ISAs stay; the ten phantom triggers alias real
  rules; months of runway replace the emergency-fund grade; the protection
  `adequacy_score` contract stays; the `/m` Fyn panel over the focus cards is not an issue.
- **(2026-09-11/12)** The savings_account cap is "bank accounts" (the page is "Bank Accounts"
  everywhere; the Cash page cards are account types; "Cash Management" is the nav section).
  Spouse figures from the SaveTax step show provider names like every record. The post-plan
  spouse invitation copy is approved verbatim (PR #826); pensioncheck gets the same state.
  The ownership loop was NOT a regression — the July fixes relied on the model re-calling.
- **(2026-09-09) Closed for handover purposes:** the production `.env` keys
  `COMPANIES_HOUSE_API_KEY` / `GETADDRESS_API_KEY`, and the unconfigured Apple
  verification bridge on production.

## Known issues

- **`users.life_stage` is overloaded by design** — it also holds the journey or focus area
  last started (`JourneyStateService`, `OnboardingService`). The client keeps only real
  stages (`lifeStage.js` `setCurrentStage`); do not "fix" the backend column.
- **The auto-mode permission classifier** refuses some routine deploy/SSH commands
  (compound prod deploys, `git checkout` on csjones, reading codes over SSH). `rsync` of
  `public/build/`, `git switch` on csjones and the `ssh-fynla` MCP pass. Any command
  handed to CSJ must use absolute paths — one run from `~` put prod into maintenance mode.
- **The formatter hook strips a just-added `use` import before its usage lands** — add
  import and first use in ONE edit, then check it survived.
- **`OnboardingView` mounts no `AppLayout`** — nothing the layout loads exists in the
  wizard unless the step asks (`auth/fetchSubscriptionData` is the pattern).
- **`public/build/` and `public/m-build/` locally hold whichever build ran last** (csjones
  or prod base path). `./dev.sh` on 8000/5173 serves the web SPA through Vite; `/m` serves
  the built `public/m-build` — run `npm run build:mobile` before a `/m` check.
- **Persona passwords are `Password1!`**, not `password`. A 401 is probably not a bug.
- **A preview persona's `/m` token is rotated by the app's refresh flow** — mint a fresh
  one (`POST /api/preview/login/{persona}`) per browser session.
- The iOS `test-and-build` CI job is **not a release gate and must never be re-run
  unasked** (CSJ 2026-09-07). dev's own Quality Gate is red on Unit/Feature
  (`AccountDeletionService`, `AuditTierCollapse` QueryExceptions) — pre-existing, not
  investigated.
- **Before any release, check what prod actually runs** (bundle hash, `migrate:status`,
  vendor mtime). Prod deploys rsync `app config database routes resources/views public/pages
  public/build` (+ `fyn-memory/` and `resources/js/data/` when they change) and `rm` any
  deleted classes by hand. **Never rsync `bootstrap/`.**
- **Universal links on SiteGround are served from the site-root `.well-known/`**, not
  `public/.well-known/` — `deploy/DEPLOY.md` step 8.
- **fynla.org's 8 paying customers are on `premium`** since the 2026-09-08 collapse.
- **Never run a targeted suite while the full suite is running** — same MySQL database.
- **Pest refuses a file and its parent directory in one invocation**; pass the directory.
- **`./vendor/bin/pint app/` times out** at 2 minutes — format only changed files.
  **`pest --filter=""` matches nothing and exits 0**, which looks like a pass.
- **Test accounts:** fynla.org `slaterjoneschris+fynla0910@gmail.com` (real Free account, one
  property added and removed 2026-09-11); csjones john (two 0% current accounts, £1,800 joint
  expenditure, life stage "university") and `savetax-0911a…f` / `savetax-0912a/b@example.com`
  (0912b holds a pending spouse invitation); local David and `journey-0910@example.com`.
- **Playwright's main tab can stop delivering input** while scripts still run — drive a second
  browser context from `browser_run_code_unsafe` (re-find it each call); never `browser_close`.
- **Prod deploys with a migration** rsync `database/migrations/` first; corpus changes rsync
  `fyn-memory/procedural/` and run `fyn:procedural:validate` on prod.
- **Adding a corpus workflow state:** constants + `inCodeStates()` + corpus in the same ORDER;
  guard `nextFrom…('')` (skip rules pass an empty answer; `matchBubble('')` hits the first bubble).

## Deploy state

- **fynla.org = main `357e44e4b`** (tree identical to dev `e4afd0d1c`), two releases on
  2026-09-15 (#856, #858) after six PHP-only fixes (#840–#853). Migrations `2026_09_15_140000`
  (dividend column) and `2026_09_15_170000` (Free property cap → 2) ran on prod with full dumps
  first. No bundle change today. Backups `~/release-backups/2026-09-15/`…`h/`. Notes: memory
  `project_release_2026_09_15`.
- **csjones = dev `e4afd0d1c`**, bundles unchanged. Eleven mapping PRs (#829–#839) still open and
  unmerged (rebased stack, parked on CSJ). Nothing from `feat/savetax-property-capture-form` is
  deployed anywhere.
- **TestFlight "Fynla" 1.0 (10)** on the `org.fynla.app.dev` record, Production
  configuration reading fynla.org, VALID 2026-09-10 08:49 BST; native tree unchanged since.
  The `org.fynla.app` record is "Fynla (legacy)" — never upload there unasked.
- **Dashboard recommendation routing**: one home `app/Services/Mobile/RecommendationRouting.php`
  (keys on `rule_key` since 2026-09-10); ids composed in
  `RecommendationsAggregatorService::composeId` / `disambiguate`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **(2026-09-11/12)** The spouse "What you told Fyn" row mapping is written once per surface
  (`resources/mobile/views/Income.vue:79`, `IncomeOccupation.vue:538`); five homes for the
  `capture_spouse_household_data` field list (handler allowlist + rules, model fillable, both
  schema mds); two canned-refusal recognisers (`HasAiChat.php:1757`, director `:4491`); the
  invite outcome copy lives in PHP (`OnboardingChatDirector.php:2141`); two stop-word lists
  (`SpouseHouseholdPhrasings:61`, `OnboardingFactExtractor:38`); the same email regex twice;
  `OnboardingChatDirector` 7,668 and `CoordinatingAgent` 6,827 lines; `IncomeOccupation.vue` 854.
- **(2026-09-10, still open)** the vanilla cookie banner is a hand copy pinned only for the
  decline sentence; `ExpenditureForm.vue` ≈2,550 and `SavingsActionDefinitionService.php`
  ≈3,780 lines.
- **(2026-09-09 pm, still open)** the web path map exists twice (`semanticDestinations.js:10`,
  `GamifiedDashboard.vue:365`) — product call; two /m contextual-open methods
  (`Dashboard.vue:781`, `MobileChrome.vue:344`) — share `createContextualConversation` already;
  `ContextualConversationService::recommendationFor` runs a full aggregation per tap;
  `HolisticPlanningController::markRecommendationDone` is a second completion path;
  `ActionsOverviewCard.vue` imported nowhere.
- **(2026-09-09)** `RecommendationsAggregatorService::aggregateRecommendations` is 175 lines
  (five raw-path rollback blocks behind `coordination.composed_module_plans`; retire the flag
  and they go). `PriorityRanker::MODULE_WEIGHTS` carries both `tax_optimisation` and `tax`.
  `StrategyPriority` has no `Critical` case (adapters carry `extra['seeded_priority']`).
  `0.00005` and the flat `0.0400` benchmark block are unnamed.
- **(2026-09-07)** `SubscriptionManagementView.swift` writes the web-handoff button + error
  block twice; `TierCollapsePreflight.php` re-derives "plans that confer premium" instead
  of one `TierConfigurationStore::plansConferringPremium()`; `WillFactory.php` repeats the
  unnamed column default `100.00`.
- **Two mechanisms answer "what does this user owe"** — `NetWorthService:155` and
  `CrossModuleAssetAggregator:404`. Parity held by a test, not by construction.
- **The debt protection panel exists twice** — the canonical service `/m` consumes, and
  the web `/protection` page's own component.
- **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
  `&& ! $hasCashHolding` (`:439`), update does not (`:587`). Same asymmetry as W-0321.
- **No UI field for `lpa_attorneys.is_bankrupt` (W-0105) or the professional
  certificate-provider details (W-0106).** Column, validation and check exist; nothing asks.
- **`TaxConfigService::hasSurvivorshipRights()` and `allowsWillOverride()` have zero callers
  BY DESIGN** (`:828-846`, W-0498). Listed so a dead-code sweep does not delete them.
- **`RetirementProjectionService` takes nine constructor arguments**; two test files
  construct it by hand.
- **`GiftAnnualExemption` does not model s20, s21 or s22** — s21 is W-0525's remaining half.
- 52 unused private injections outside the TaxConfigService cluster.
- `database/schema/mysql-schema.sql` is stale. Wrong, not harmful.
- The gifting UI still offers edit/delete on a trust-owned gift (422); needs `trust_id` on
  `GiftResource`.
- Spouse WRITES require reciprocity but not consent — deliberate, open to challenge.
- **W-0351 acceptance 3 NOT done** — the sweep for other `v-if`s gating on fields their
  Resource never returns.
- **Three compliance-lead copy reviews outstanding** (W-0108, W-0152, W-0153).
- `CanonicalPortfolio.vue:23` prints "OCF" unexpanded on `/m` (Rule 9). Pre-existing.
