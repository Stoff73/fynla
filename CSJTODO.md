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

## Next session starts here — iOS (CSJ, 2026-09-09)

- [ ] **BLOCKED ON CSJ — merge #773 after the device check.** The phone runs #773 + #781
      against fynla.org. Still to see: Settings → Plan and billing shows "Upgrade on the
      web" (Free) / "Manage billing on the web" (web-billed Premium), and a Fyn turn. Then
      rebase onto dev (2 ahead, 48 behind; docs conflicts resolve to the fynla.org wording),
      `ios-native/scripts/verify-project.sh`, merge `--admin`. Native-only, no csjones step.
- [ ] **The six `deferred-ios` items** — W-0044, W-0090, W-0243, W-0311, W-0416, W-0496.
      Both schemes read fynla.org; production serves the native routes; every registered
      tester account is a real fynla.org account. Parity ledger:
      `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.
- [ ] **iOS harness debt**: the shared UI-test typing helper cannot clear the email keyboard
      off the password field on an iPhone 11 (registration regression test is simulator/CI
      only); the 6 red `Local StoreKit configuration` tests are a real signal (no IAP
      products in App Store Connect).

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

- **fynla.org = main `867fcf82b`** (tree identical to dev `366a9f4ea`), two releases on
  2026-09-09 (PR #792 ~10:25 BST, PR #796 ~11:00 BST). Migrations run: expenditure
  categories nullable, `subscription_plans` dropped. Personas reseeded. Backups on the
  server: `~/release-backups/2026-09-09/` and `2026-09-09b/`. Notes: memory
  `project_release_2026_09_09`.
- **csjones = dev `366a9f4ea`**, both bundles built from it, same migrations.
- **TestFlight "Fynla" 1.0 (8)** on the `org.fynla.app.dev` record, Production
  configuration (fynla.org). The `org.fynla.app` record is "Fynla (legacy)" — never upload
  there unasked. #773 (native billing on the web) still awaits CSJ's check on the phone.
- **Fyn wiring artifact** (https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88):
  every finding closed or settled by CSJ; source `September/September8Updates/fyn-wiring-artifact.html`;
  evidence per batch in `September/September9Updates/`.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

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
