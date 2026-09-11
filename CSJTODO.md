# CSJTODO — Fynla

*Last updated: 2026-09-11 session 1 (end of the 2026-09-10 session) — dev RELEASED to
fynla.org three times on 2026-09-10 (main ac967bdac == dev d73b2a832): Fyn first-message
guard + native billing on the web; recommendation ids per record + the nine new-user-run
board items + onboarding journeys; capped "+ Add" refused before the form.
Handover: `handover/September/11/handover-2026-09-11-session-1.md`*

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

## Next session starts here — iOS (CSJ, 2026-09-09; still open 2026-09-11)

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
  `GiftingStrategy.vue`, `TrustPlanningStrategy.vue`, `IHTPlanning.vue` are unreachable).
- The unstyled homepage pension-check block parked in PR #770's description.
- The 34 remaining sweep findings — decide, do not chase.
- Tax-compliance review — W-0367, W-0514, W-0508, W-0338, W-0470, W-0518, W-0498;
  design-lead / quality-lead on W-0497; chief-of-staff on W-0506.
- The savings web form captures no account name (institution + product only); the
  display-name fallback covers the recommendation copy, the form gap is a product call.

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
- **Test accounts from 2026-09-10:** `slaterjoneschris+fynla0910@gmail.com` on fynla.org
  (real Free account); csjones john has two 0% current accounts, £1,800 joint expenditure
  and life stage "university"; local David and `journey-0910@example.com` carry test rows.

## Deploy state

- **fynla.org = main `ac967bdac`** (tree identical to dev `d73b2a832`), three releases on
  2026-09-10 (PR #800 ~08:45, PR #805 ~12:10, PR #807 ~12:40 BST). No migrations in any.
  Bundle `app-DguFb_dl.js`. Backups on the server: `~/release-backups/2026-09-10/`,
  `2026-09-10b/`, `2026-09-10c/` (bundle manifests). Notes: memory
  `project_release_2026_09_10`.
- **csjones = dev `d73b2a832`**, web bundle built from it (`/m` bundle unchanged since
  2026-09-09).
- **TestFlight "Fynla" 1.0 (10)** on the `org.fynla.app.dev` record, Production
  configuration reading fynla.org, VALID 2026-09-10 08:49 BST; native tree unchanged since.
  The `org.fynla.app` record is "Fynla (legacy)" — never upload there unasked.
- **Dashboard recommendation routing**: one home `app/Services/Mobile/RecommendationRouting.php`
  (keys on `rule_key` since 2026-09-10); ids composed in
  `RecommendationsAggregatorService::composeId` / `disambiguate`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- **(2026-09-10)** Two homes for the plan-cap entity wording (`utils/apiErrors.js:12`
  `ENTITY_LABELS` vs the literals on six `LimitReachedModal` consumers); the at-cap gate is
  written per surface (`AssetsStep.vue:655`, `PropertyList.vue:228`) though the arithmetic
  is shared; `utils/registrationRules.js` mirrors `RegisterRequest` by hand with no parity
  pin; the vanilla cookie banner is a hand copy pinned only for the decline sentence; 14
  pre-existing ESLint dead-code hits in the onboarding steps and wizard;
  `PersonalInfoStep.vue:7` redundant `:hide-nav="true"`; `lifeStage.js:150` unused catch
  binding; `ExpenditureForm.vue` ≈2,550 and `SavingsActionDefinitionService.php` ≈3,780 lines.
- **(2026-09-09 pm)** Two Yes/No vocabularies inside `AdviceFyn` (`:229` follow-up, `:340`
  deferred answer); the web path map exists twice (`resources/js/utils/semanticDestinations.js:10`,
  `GamifiedDashboard.vue:365`) where the server could emit a `web` path; two /m
  contextual-open methods (`Dashboard.vue:781`, `MobileChrome.vue:344`);
  `ContextualConversationService::recommendationFor` runs a full aggregation per tap;
  `HolisticPlanningController::markRecommendationDone` is a second completion path;
  four SSE-frame parsers across `tests/Feature/AI`; `ActionsOverviewCard.vue` imported nowhere.
- **(2026-09-09)** `RecommendationsAggregatorService::aggregateRecommendations` is 175 lines
  (five raw-path rollback blocks behind `coordination.composed_module_plans`; retire the flag
  and they go). `PriorityRanker::MODULE_WEIGHTS` carries both `tax_optimisation` and `tax`.
  `StrategyPriority` has no `Critical` case (adapters carry `extra['seeded_priority']`).
  `CoordinatingAgent` ~6,800 lines. `/m` views each carry a `formatCurrency` copy;
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
