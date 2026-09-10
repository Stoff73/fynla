# CSJTODO — Fynla

*Last updated: 2026-09-09 session 1 — dev RELEASED to fynla.org twice (main 867fcf82b == dev 366a9f4ea): Fyn wiring Batches B–G closed, /m scaffold retired, legacy plan catalogue deleted.
Handover: `handover/September/09/handover-2026-09-09-session-1.md`*

## The board position

Computed from the 327 files, not from a register. `tasks.md` in the repo root is the
live checklist and is **generated** — regenerate it, never hand-edit the counts.

| | |
|---|---|
| items | 328 |
| **resolved** | **322** |
| **outstanding** | **6** — all `deferred-ios` |
| critical | **0** |
| high | **0** |
| medium / low in scope | **0** |

Every non-iOS item is closed. The rule is unchanged — **a citation is not a verification** —
and **verify the instrument before trusting the measurement**: four separate scans on
1 September reported defects that did not exist.

## Next session starts here — iOS (CSJ, 2026-09-09, session 2)

- [ ] **BLOCKED ON CSJ — what TestFlight build 9 shows on the phone.** Headline rows; an
      information-request row opens Fyn with "I can help you enter the information for …";
      the capture ends with Yes / No thanks; "No thanks" closes the cover and replaces the
      row. Backend proven on /m and web (fynla.org main `3edf8c39a`); native decode is
      `DashboardModels.swift:191`, routing `AppRootView.swift` `onFynCapture`.
- [ ] **BLOCKED ON CSJ — merge #773 after the device check.** Open, MERGEABLE, 49 behind
      dev with no `ios-native/` overlap. Settings → Plan and billing shows "Upgrade on the
      web" (Free) / "Manage billing on the web" (web-billed Premium). Then rebase,
      `ios-native/scripts/verify-project.sh`, merge `--admin`. Native-only, no csjones step.
- [ ] **Full `FynlaTests` on a simulator** — none could be opened on 2026-09-09 (Xcode
      access not granted; never `simctl boot`). The nine fixture-reading tests cannot run on
      a physical device (absolute Mac paths) — not a signal.
- [ ] **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 need Swift;
      W-0044 and W-0496 have the Swift landed and need an on-screen check. Fresh branch off
      dev. Parity ledger: `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.
- [ ] **Decide the shared recommendation id** — three "earns no interest" rows share
      `savings_zero_rate_account`, so marking one done completes all three; changing the id
      touches `recommendation_tracking` rows and the points dedup key.
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

## Settled by CSJ — do not re-raise

- **W-0144** — revocation of former wills is the law; the 28-day survivorship period is
  standard drafting. Defaults unchanged, no prompt needed.
- **W-0155** — consent is a single accept button. There is no withdrawal journey.
  `declineCookies()` is the banner's Decline path, not dead code.
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
  verification bridge on production. Deferred by CSJ; only matter if the Companies House /
  postcode lookups are used on production or native in-app purchase is ever sold.

## Known issues

- **`public/build/` and `public/m-build/` locally hold whichever build ran last** (csjones
  or prod base path). `./dev.sh` on 8000/5173 serves the web SPA through Vite; `/m` serves
  the built `public/m-build` — run `npm run build:mobile` before a `/m` check.
- **Persona passwords are `Password1!`**, not `password`. A 401 is probably not a bug.
- **A preview persona's `/m` token is rotated by the app's refresh flow** — mint a fresh
  one (`POST /api/preview/login/{persona}`) per browser session.
- The iOS `test-and-build` CI job is **not a release gate and must never be re-run
  unasked** (CSJ 2026-09-07).
- **Before any release, check what prod actually runs** (bundle hash, `migrate:status`,
  vendor mtime). Prod deploys must now rsync `fyn-memory/` and `resources/js/data/` too,
  and `rm` any deleted classes by hand (rsync never deletes).
- **Universal links on SiteGround are served from the site-root `.well-known/`**, not
  `public/.well-known/` — `deploy/DEPLOY.md` step 8.
- **fynla.org's 8 paying customers are on `premium`** since the 2026-09-08 collapse; the
  legacy slugs and the plan catalogue no longer exist anywhere.
- **Never run a targeted suite while the full suite is running** — same MySQL database.
- **Pest refuses a file and its parent directory in one invocation**; pass the directory.
- **Pint re-adds an import for a `{@see}` docblock class reference**, which
  `StoreBoundary` then rejects. Write the reference as plain text in backticks.
- **`./vendor/bin/pint app/` times out** at 2 minutes — format only changed files.
  **`pest --filter=""` matches nothing and exits 0**, which looks like a pass.

## Deploy state

- **fynla.org = main `3edf8c39a`** (tree identical to dev `745ebe74f`), three releases on
  2026-09-09 (PR #792 ~10:25, PR #796 ~11:00, PR #798 13:57 BST). Release 3 carried no
  migrations. Backups on the server: `~/release-backups/2026-09-09/`, `2026-09-09b/`
  (DB tables) and `2026-09-09c/` (bundle manifests). Notes: memory
  `project_release_2026_09_09`. **Never rsync `bootstrap/`** (deploy guide step 6).
- **csjones = dev `745ebe74f`**, both bundles built from it.
- **TestFlight "Fynla" 1.0 (9)** on the `org.fynla.app.dev` record, Production
  configuration reading fynla.org, VALID 2026-09-09 14:05 BST. The `org.fynla.app` record is
  "Fynla (legacy)" — never upload there unasked. #773 still awaits CSJ's phone check.
- **Fyn wiring artifact** (https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88):
  every finding closed or settled by CSJ; source `September/September8Updates/fyn-wiring-artifact.html`.
- **Dashboard recommendation routing** (2026-09-09 session 2): one home
  `app/Services/Mobile/RecommendationRouting.php`; plan and outcome in
  `September/September9Updates/ios-dashboard-jobs-plan.md`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

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
  `CoordinatingAgent` ~6,800 lines; `SavingsActionDefinitionService` ~3,850. `/m` views each
  carry a `formatCurrency` copy; `0.00005` and the flat `0.0400` benchmark block are unnamed.
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
