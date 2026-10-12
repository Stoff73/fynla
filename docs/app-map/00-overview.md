# Fynla — application map: overview (baseline)

| | |
|---|---|
| Scope | The whole application at inventory depth: every client, every route file, every backend layer, every background process, every test suite. Per-screen forms, field tables and CRUD tables are deferred to the section maps listed in `INDEX.md`. |
| Commit | `f00076906` on `dev` (refreshed from `28194e804`) |
| Mapped on | 2026-09-14; refreshed 2026-10-12 |
| Mapped by | Claude Code session, `app-map` skill, first run |
| Supersedes | `docs/reference/System Map.md` (vault `appMapping/currentState/*`) as the entry point; those documents were not used as evidence |
| Issues raised | MB-01 to MB-13 in `September/September14Updates/mappingBugs2026-09-14.md` (MB-07 withdrawn 2026-10-12: it was wrong when raised); MB-60 in `October/October12Updates/mappingBugs2026-10-12.md` |

Every statement below cites a file and line read in this run, a command run in this run, or a browser interaction performed in this run. Where a claim could not be checked it says "I COULD NOT VERIFY".

**Refresh 2026-10-12.** Every `file:line` citation was tracked from `28194e804` to `f00076906` with a line diff of the cited file. Citations whose lines had only moved were re-pointed, and the seven whose lines changed were re-read and rewritten. Every inventory figure was recounted at `f00076906`. Where the 2026-09-14 counting method could be reproduced at `28194e804`, it was used, so the two figures compare like for like. Where it could not, the new figure states its own method. The web reachability sweep was re-run with a corrected method (§ 2.6). The route table is `php artisan route:list --json` (810 routes). Not repeated in the refresh: the browser walks (statuses that say "driven" date from 2026-09-14), the full Architecture and Vitest runs (CSJ 2026-09-27: no full or directory-wide suites locally), and the client-to-route path matching for `/m` and iOS.

## 1. Overview

### In plain English

Fynla is a website and phone app that helps a UK household see all of its money in one place and work out what to do next. A person types in (or tells the assistant, Fyn) what they own, owe, earn and spend, and Fynla turns that into a net worth picture, a check on their insurance cover, their pension outlook, their inheritance tax exposure, their goals, and a ranked list of actions. A free tier lets a household record a limited number of accounts; a paid tier removes those limits and unlocks the deeper planning tools. The same information is shown on the desktop website, on a mobile web version, and in a native iPhone app, all fed by one shared server.

### How it fits together

There is one Laravel backend and three user-facing clients. The desktop site is a Vue single-page application. The mobile web version is a separate Vue bundle served under `/m`. The iPhone app is SwiftUI and talks to the same server at fynla.org. Public marketing pages are rendered on the server before the single-page application is reached. Every client authenticates with a bearer token and calls the same `/api` routes; a small `/api/v1` set exists for mobile and native-only concerns such as push devices and Apple purchases. Behind the routes sit controllers, then module "agents" that orchestrate calculation services, then a store layer that owns writes, then the database. A scheduler runs alerts, reminders, subscription housekeeping, the Fyn memory hygiene tasks and a marketing content pipeline.

### Flow diagrams

- `docs/diagrams/map-overview-whole-app-flow.excalidraw` — clients, middleware, routes, controllers, agents and services, Fyn, stores and models, background machinery and external services, with the arrows that actually exist in code.
- `docs/diagrams/map-overview-module-graph.excalidraw` — the twenty user-facing and supporting areas and the agent or service group behind each.
- `docs/diagrams/map-overview-cross-module-dependencies.excalidraw` — which service groups import which, counted from `use App\Services\<Group>\` statements.

Vault copies are in `fynlaBrain/Diagrams/` and listed in the Diagrams Index.

### What it looks like

The screens below were driven in Playwright this run against the local build (`http://localhost:8000`, user `john@example.com`).

![Server-rendered public homepage](screenshots/overview/web-public-homepage.png)
*The server-rendered homepage, captured after signing out. Loading `/` while signed in redirected to `/dashboard` (`redirect.authed`, `routes/web.php:98`), which was also observed this run.*

![Web login, verification code step](screenshots/overview/web-login-verification-code.png)
*Web login: after email and password, a six-digit code is required. The code was read from the database with the tinker command in `CLAUDE.md` and entered; the browser landed on `/dashboard`.*

![Web dashboard after login, full page](screenshots/overview/web-dashboard-full.png)
*Web dashboard: two-factor prompt, the level card ("2 of 4 actions to your next level"), focus areas, and the "Your finances" tiles.*

![Web side navigation, all groups expanded](screenshots/overview/web-side-nav-expanded.png)
*The side navigation with every group opened by clicking it.*

![Web dashboard with the Fyn panel open](screenshots/overview/web-dashboard-fyn-panel-open.png)
*Clicking "Tax Strategy" for a user who has not finished onboarding opens Fyn with a "Welcome back" resumption prompt instead of navigating. That is deliberate: `resources/js/components/SideMenu.vue:457-466`.*

![Mobile web dashboard](screenshots/overview/m-dashboard-full.png)
*`/m` after the same login flow at 390 pixels wide: level card, next milestone, focus areas, today's insight, finance tiles, and the Fyn dock.*

![Mobile web menu](screenshots/overview/m-menu-open.png)
*The `/m` menu, opened by clicking the hamburger.*

### Surfaces

Status is at the surface level only. "Working" means it was driven in the browser this run. "Present" means the route or feature folder exists in that client's code and was read; it was not executed. "Absent" means no route or feature exists in that client.

| Feature | Web | `/m` | iOS | Notes |
|---|---|---|---|---|
| Login with email, password, six-digit code | Working | Working | Present (`ios-native/Fynla/Features/Authentication`) | Web `/login` route `resources/js/router/index.js:444`; `/m` `resources/mobile/router.js:58`; iOS calls `api/auth/verify-code` and `resend-code` |
| Dashboard with level card and finance tiles | Working | Working | Present (`Features/Dashboard`) | Web composes from stores (`views/Dashboard.vue:1300-1360`); `/m` and iOS call `api/v1/mobile/dashboard` (`routes/api_v1.php:118`) |
| Fyn chat with resumption prompt | Working (panel opened, "Welcome back" shown) | Working (dock shown with same prompt) | Present (`Features/Fyn`, `Core/Streaming`) | One endpoint for all: `POST api/ai-chat/conversations/{id}/messages` (`routes/api.php:1517`) |
| Net worth and its categories | Present (`/net-worth/*`, 15 child routes) | Present (`/net-worth`, `/net-worth/:category`, property, mortgage, liability detail) | Present (`Features/NetWorth`) | |
| Protection | Present (`/protection`, policy detail) | Present | Present | |
| Savings (Bank Accounts) | Present | Present | Present | |
| Investment | Present (`/net-worth/investments` and four detail children) | Present (`/investment`, account detail) | Present | |
| Retirement | Present (`/net-worth/retirement`, `/pension/:type/:id`) | Present | Present | |
| Estate: IHT, Will builder, Power of Attorney, Trusts | Present (`/estate`, `/estate/inheritance-tax`, `/estate/will-builder`, `/estate/lpa/create/:type`, `/trusts`) | Present (`/estate`, `/estate/bequests`); will builder and LPA wizard absent from the mobile router | Present (`Features/Estate`) | Section map must confirm what `/m` and iOS show for wills and LPAs |
| Goals and life events | Present (`/goals`) | Present (`/goals`, `/goals/:id`) | Present (`Features/Goals`) | |
| Tax strategy | Present (`/tax-strategy`) | Present | Present (`Features/TaxStrategy`) | |
| Holistic plan | Present (`/holistic-plan`) | Present | Present (`Features/HolisticPlan`) | |
| Income and expenditure | Present (`/valuable-info?section=…`) | Present (`/income`, `/income/:owner/:source`, `/expenditure`) | Present (`Features/Income`, `Features/Expenditure`) | |
| Risk profile | Present (`/risk-profile` and two children) | Absent (`app/Constants/GateRoutes.php` maps `RISK_PROFILE` mobile to `null`) | Absent (no feature folder; `/m` test `InvestmentRiskProfileHandoff.spec.js` implies a hand-off to web) | |
| Plans, Journeys, What-if, Actions | Present (`/plans/*`, `/planning/journeys`, `/planning/what-if/*`, `/actions/*`) | Actions present since the stamp (`/actions`, `/actions/:id`, `resources/mobile/router.js:72-74`); Plans, Journeys and What-if absent | Actions present (`Features/Actions`, five files); the rest absent | Plans, Journeys and What-if are web only |
| Achievements and conversation history pages | Absent as routes (level card on dashboard; history via the Fyn panel "History" button) | Present (`/achievements`, `/conversation-history`) | Present (`Features/Achievements`) | |
| Settings, personal information, family, spouse sharing | Present (`/settings/*`) | Present (`/personal-information`, `/settings`, `/spouse-sharing`, `/notifications`) | Present (`Features/Settings`, `Features/Profile`, `Features/Privacy`) | |
| Subscription and checkout | Present (`/checkout`, `/teaser`, settings) | Present (`/subscription`) | Present (`Features/Subscription`, StoreKit) via `api/v1/native/*` | iOS purchases have no products in App Store Connect (see memory); I COULD NOT VERIFY the paywall this run |
| Admin, advisor, insights CMS, marketing pipeline | Present (`/admin/*` 20 routes, `/advisor`) | Absent | Absent | Web only by design |
| Preview personas | Present (`/preview/*` 20 routes, `public: true, previewMode: true`) | Absent | Absent | |
| Public marketing and learn pages | Working: server-rendered PHP (`public/pages/`, Azlan Raj's set); the 56 Vue copies are blocked by the router guard and never render (MB-09, Appendix E) | `/m/landing` blade | n/a | Help link from the signed-in footer drove a full load to the PHP help page this run |

### Depends on / depended on by

Counted from `use App\Services\<Group>\` and `use App\Agents\` imports across `app/Services`, `app/Agents` and `app/Http/Controllers` this run.

| Direction | Group | What crosses the boundary | Evidence |
|---|---|---|---|
| Everything consumes | `Stores` | The write boundary: 25 service groups import a `*Store` | Import count, `app/Services/Stores/` |
| Agents consume | Investment (14), AI (12), Estate (11), Retirement (9), Savings (9), Coordination (8), Protection (7), Goals (5) | Module calculators | `app/Agents/*.php` imports |
| AI consumes | Coordination (20), Stores (11), Onboarding (7), CoordinatingAgent | Recommendations and write handlers for Fyn | `app/Services/AI/**` imports |
| Onboarding consumes | AI (24), Stores (16) | Fyn onboarding director writes through stores | `app/Services/Onboarding/**` imports |
| Coordination consumes | Stores (12), Tax, Protection, Plans, Goals, Estate, and five module agents | Cross-module plan composition | `app/Services/Coordination/**` |
| Plans consume | Investment (12), Stores (7), Estate, Goals, and six agents | Per-module plan documents | `app/Services/Plans/**` |
| Mobile consumes | Gamification (4), Coordination (3), six agents, Dashboard | Mobile dashboard aggregation | `app/Services/Mobile/MobileDashboardAggregator.php` |
| Estate consumes | Stores (18), Shared (5), Retirement (5), Settings (5), Goals (3) | Pension and household data for IHT | `app/Services/Estate/**` |
| Retirement consumes | Investment (5), Risk (2) | Portfolio exposure for pensions | `app/Services/Retirement/**` |
| Tiers consume | Stores (7), Payment (2), Estate, NetWorth, Billing | Entitlement resolution | `app/Services/Tiers/**` |
| Controllers consume | Eight agents directly | `Controllers/Api` import every agent | `app/Http/Controllers/Api/*.php` |

## 2. Detailed sections

### 2.1 Entry points and public pages

**In plain English.** A visitor first meets pages that the server builds and sends as finished HTML: the homepage, pricing, features, the learn articles, the Save Tax campaign. Once they log in, everything else is one page that rewrites itself as they click. On a phone, a separate lighter version lives under `/m`.

**Status:** Working for the homepage and login (driven this run). Duplicate for the marketing pages (MB-09). Does not make sense for six mockup routes (MB-10).

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Web routes | `routes/web.php:98-472` | 60 closure routes wrapped in `redirect.authed` include `public/pages/*.php` and return HTML with a 5-minute cache header. Signed-in users are bounced to the dashboard by the middleware. |
| 2 | Web routes | `routes/web.php:30-33` | `/insights/{slug}` returns the SPA shell after `InsightsSeoMetaInjector` writes meta tags. |
| 3 | Web routes | `routes/web.php:37-51`, `:58-77` | Lifecycle magic links (`signed`), web hand-off consume, RSS feeds, newsletter confirm and unsubscribe. |
| 4 | Web routes | `routes/web.php:79-87` | `/storage/{path}` streams the public disk; refuses `..`. |
| 5 | Web routes | `routes/web.php:662-702`, `:752` | Save Tax mockups and dashboard mockups (MB-10, still open). |
| 6 | Web routes | `routes/web.php:553-560` | `/m` → `mobile-host` blade, `/m/landing` → `mobile-landing`, `/m/app/{any?}` → `mobile-app` (the built `/m` bundle). |
| 7 | Web routes | `routes/web.php:602-604` | `/{any}` catch-all returns `resources/views/app.blade.php`, which loads `resources/css/app.css` and `resources/js/app.js` (`app.blade.php:93`). |
| 8 | SPA router | `resources/js/router/index.js` | 164 `path:` entries: 132 top-level and 32 children (161 at the stamp, counted the same way). Guard at `:1827-1879`: `requiresAuth` sends guests to `Login`; `public` routes send signed-in users to `Dashboard`; `requiresAdmin` checks `auth/isAdmin`; tier-gated routes redirect to `/teaser` with the capability name. |
| 9 | Mobile router | `resources/mobile/router.js` | 39 entries (36 at the stamp; new: `/actions`, `/actions/:id`, `/net-worth/history`) under base `<VITE_ROUTER_BASE>m/app/`; `meta.auth` routes require `store.token` (`:115-120`). |

**Tests:** `tests/E2E/public/*.spec.js` (3), `tests/E2E/smoke/desktop.spec.js`, `mobile.spec.js`, `tests/frontend/router/publicRoutePolicy.test.js`, `tests/frontend/public/pricing.test.js`. Run this session: the Vitest suite (see § 2.11).

### 2.2 Request pipeline, authentication and gates

**In plain English.** Every request passes a fixed set of checks: security headers, input cleaning, who you are, whether you are a demo persona (writes are blocked), whether an adviser is acting for you, and whether your subscription allows the feature. Only then does the request reach the code that does the work.

**Status:** Unverified except login, which was driven this run on both web and `/m`.

**How it works**

| Step | Layer | File | What happens |
|---|---|---|---|
| 1 | Global middleware | `app/Http/Kernel.php:96-107` | `TrustHosts`, `TrustProxies`, CORS, maintenance, post size, `CaptureNativeDeviceLabel`, trim, empty-to-null, `SecurityHeaders`, `CaptureAwcCookie` (affiliate cookie). |
| 2 | `web` group | `Kernel.php:115-124` | Cookies, session, CSRF, `RedirectPhoneToMobile`, `RebasePublicPageUrls`. |
| 3 | `api` group | `Kernel.php:126-136` | `ApiCacheHeaders` (no-store), Sanctum stateful, throttle `api`, `SanitizeInput`, `TouchSessionActivity`, `AdvisorImpersonationMiddleware`, `PreviewWriteInterceptor`, `CheckSubscription`. |
| 4 | Priority | `Kernel.php:72-88` | Native client identification runs before Sanctum so malformed native requests get a stable client error. |
| 5 | Preview | `app/Http/Middleware/PreviewWriteInterceptor.php:49-86` | `EXCLUDED_ROUTES` lists the writes a preview persona may still perform (login, register, verification, password reset, onboarding, document upload, AI chat, device registration, StoreKit, advisor enter and exit, bug report, and since the stamp `api/recommendations/*/mark-done`, kept on the visitor's token only). Everything else is intercepted for preview users. Rule 7 in `CLAUDE.md`. |
| 6 | Subscription | `app/Http/Middleware/CheckSubscription.php:20-55` | Skipped entirely when `config('app.payment_enabled')` is false (`.env` has `PAYMENT_ENABLED=true` locally). Always-allowed prefixes: payment, auth, webhooks, preview, onboarding, bug-report, gdpr, admin, advisor. Read-only for expired users: `api/user/`, `api/settings/`. `CAPABILITY_ROUTE_MAP` ties eight route prefixes to capabilities such as `holistic_plan` and `what_if`. |
| 7 | Tier data | `database/seeders/TierConfigurationSeeder.php:27-113` | Two tiers. Free: prices 0, count caps savings 2, investment 2, pension 2, property 2 (1 at the stamp, `:55`), mortgage 10, goal 2, life event 1, estate `teaser`, Fyn 100k tokens a week. Premium: 699p a month or 5999p a year, no caps, everything `full`, Fyn 500k tokens a week. Resolved at runtime by `app/Services/Tiers/TierResolver.php` and `app/Services/Stores/TierGate.php`. |
| 8 | Route-level gates | `routes/api.php:919`, `:1099` | `estate.full` (48 routes) and `holistic.full` (10 routes) middleware. `permission:admin.access` guards 143 admin routes; `permission:admin.tax_config` 8; `advisor` 14. |
| 9 | Auth routes | `routes/api.php:154-233` | Register, registration hand-off, spouse-invitation prefill (new, `:161`), login, verify code, resend, restore soft-deleted account, beacon logout, MFA verify and recovery, password reset (six steps), then authenticated: logout, user, change password, MFA management (5), sessions (3), GDPR consents, export and erasure (13). Each unauthenticated step has its own named throttle (`auth-3`, `auth-5`, `auth-10`). |
| 10 | Route loading | `app/Providers/RouteServiceProvider.php:174-184` | `api.php` under `api`, `api_v1.php` under `api/v1`, and `e2e.php` under `__e2e` only when `APP_ENV=e2e`. |

**Unused registrations:** aliases `admin`, `role`, `mfa.verified` are registered but no route uses them (MB-13).

**Tests:** `tests/Architecture/GateRoutesTest.php`, `PreviewBlockSitesCheckBypassTest.php`, `PreviewModeToolCatalogueTest.php`; `tests/E2E/auth/registration.spec.js`; `tests/E2E/freemium/*` (2); Feature tests under `tests/Feature/Auth` (not enumerated this run).

### 2.3 Routes and controllers

**In plain English.** The server exposes about eight hundred addresses. Roughly seven hundred are the JSON API the apps use; the rest are the public pages. Each API address is handled by one of 148 controller classes, and every controller on disk is wired to at least one address except the abstract base and a test helper.

**Status:** Unverified (inventory read, not executed, except the routes hit during login and dashboard load).

**How it works**

| Fact | Value | Evidence |
|---|---|---|
| Effective routes | 810 (805 at the stamp) | `php artisan route:list --json` this run |
| Under the `api` middleware group | 719 (715) | same, middleware column |
| Sanctum-protected | 681 (678) | same |
| Under `web` group | 88 (87) | same |
| Closure routes | 76 (75), all public pages or health checks | same, action `Closure` |
| Controllers on disk | 148; 146 routed (147; 145) | `git ls-files app/Http/Controllers` compared with route actions |
| Not routed | `Controller.php` (abstract base), `TestSupport/E2EController.php` (loaded only under `APP_ENV=e2e`, `RouteServiceProvider.php:181`) | same |
| Form Requests | 105 (103) | `git ls-files app/Http/Requests` |
| API Resources | 36 | `git ls-files app/Http/Resources` |
| Largest route groups (2026-09-14 count, not recounted) | admin pipeline 29, `learn` pages 23, admin insights 18, GDPR 15, protection policies 15, investment rebalancing 14, mobile v1 14, retirement pensions 12 | route prefix count this run |

The full group layout of `routes/api.php` with line numbers is in Appendix D.

**Routes with no caller in any client** are listed in MB-08 and Appendix C.

### 2.4 Agents and services

**In plain English.** Each money topic has an "agent" that answers "analyse this household" by calling the right calculators. A coordinating agent combines them into cross-topic advice. Calculations never write to the database directly; a separate store layer owns writes and keeps derived figures consistent.

**Status:** Unverified (read; the architecture suite that asserts these boundaries passed this run, see § 2.11).

**How it works**

| Piece | Files | What the code shows |
|---|---|---|
| Agents | `app/Agents/` 9 files: `BaseAgent`, `Protection`, `Savings`, `Investment`, `Retirement`, `Estate`, `Goals`, `TaxOptimisation`, `Coordinating` | Controllers under `app/Http/Controllers/Api` import all eight concrete agents. Agents import `Stores` 22 times (21 at the stamp) and the module service groups. |
| Services | 596 files in 49 groups under `app/Services/` (542 in 47 at the stamp, every file counted) | Largest by files at any depth: AI 98, Investment 59, Tax 49, Stores 46, Estate 34, Pipeline 30, Coordination 27, Onboarding 25, Documents 23, Retirement 19. The 2026-09-14 list (Estate 34, Investment 27, …) could not be reproduced by any counting method at the stamp and is replaced. |
| Stores | `app/Services/Stores/` | `*Store.php` for actuarial tables, currency rates, goals, investment accounts, liabilities, life events, mortgages, pensions, property, savings market rates, savings, retirement profile, tax config, tier configuration; plus `TierGate`, `IngestSource`, `Normalisers/`, `Recalc/`, `ReferenceData/`, `Snapshots/`. Eight `.md` files sit beside the stores as documentation. |
| Dashboard aggregation | `app/Services/Dashboard/DashboardAggregator.php:17-22` | Injects five agents (Protection, Savings, Investment, Retirement, Estate) and calls `analyze()` on each inside a try/catch. Served by `GET api/dashboard`, which no client calls (MB-08). |
| Mobile aggregation | `app/Services/Mobile/MobileDashboardAggregator.php` | Imports six agents, Gamification, Coordination and Dashboard; serves `api/v1/mobile/dashboard`, which `/m` and iOS call. |
| Coordination | `app/Services/Coordination/` 27 files at any depth, 14 directly in the folder | `RecommendationsAggregatorService`, `PriorityRanker`, `HolisticPlanner`, `CompositePlanService`, `ComposedTaxPlanService`, `CrossModuleStrategyService`, `HouseholdFinancialContext`, `RecommendationPersonaliser`, `RecommendationCompletionService`, `ConflictResolver`, `CashFlowCoordinator`, `StrategyPlanComposer`, `PlanSources/`. |
| Tax | `app/Services/Tax/` 49 files at any depth, 14 directly in the folder; `TaxConfigService` is the single source of tax values (Rule 2). `tests/Architecture/HardcodedValuesArchitectureTest.php` enforces it and passed this run. | |
| Test-only services | `Marketing/PensionEstimateService.php`, `Pipeline/CaptionBuilder.php`, `Stores/CurrencyDisplayService.php` | No production caller (MB-12). |

### 2.5 Data

**In plain English.** The database holds 172 tables. Most map one-to-one to a model class; the rest are framework tables, snapshots of past values, and audit or log tables.

**Status:** Unverified (schema read from the live local database, not exercised).

| Fact | Value | Evidence |
|---|---|---|
| Tables in the local database | 172 (170) | `SHOW TABLES` via tinker this run |
| Models | 152, 150 at the stamp (`app/Models`, including `Estate/`, `Investment/`, `Insights/`, `News/`, `Pipeline/` sub-namespaces and two `Concerns` traits) | `git ls-files app/Models` |
| Migrations | 353 (335; 0 pending locally on 2026-10-12, `php artisan migrate:status`) | `git ls-files database/migrations` |
| Seeders | 30 (29), including `TaxConfigurationSeeder`, `TaxProductReferenceSeeder`, `TierConfigurationSeeder`, `PreviewUserSeeder`, `PremiumTestPersonaSeeder`, `ActuarialLifeTablesSeeder`, `SavingsMarketRatesSeeder`, five `*ActionDefinitionSeeder` | `git ls-files database/seeders` |
| Value snapshots | `db_pension`, `dc_pension`, `investment_account`, `liability`, `mortgage`, `property`, `savings_account`, `state_pension` `_value_snapshots` tables | `SHOW TABLES` |
| Observers | 20 on disk (19 at the stamp); 13 registered in `app/Providers/EventServiceProvider.php`, 6 in `AppServiceProvider.php` (`DocumentArticle`, `InsightArticle`, `RecommendationTracking`, `SpouseEstimateStatus` (new), `SurvivingSpouseExpenditure`, `UserOnboardingStep`). `RiskRecalculationObserver` is the base class of the seven registered `*RiskObserver`s (`EventServiceProvider.php:107-125`) and is reached through them. The 2026-09-14 row called it registered nowhere; MB-07 is withdrawn. | grep this run |
| `UserDataCacheObserver` | attached to 26 models (25 at the stamp) so any financial write invalidates the user's cached aggregates | `EventServiceProvider.php:113-139` |
| Entity events | 27 `Created/Updated/Deleted/Restored` events for investment accounts, mortgages, DB and DC pensions, state pension, property, savings; mortgage events recalculate the property's outstanding balance, property events sync the owner's rental income | `EventServiceProvider.php:72-106`, `app/Events/` |
| Broadcast channel | one private channel `App.Models.User.{id}` | `routes/channels.php:16` |
| Existing ERDs | 15 (13) `docs/diagrams/erd-*.excalidraw` | `ls docs/diagrams` |

### 2.6 Web single-page application

**In plain English.** The desktop site is one Vue application. It loads screens on demand, keeps a copy of the user's data in memory (Vuex), and talks to the server through 62 small service files.

**Status:** Working for login, dashboard and side navigation (driven 2026-09-14). Dead for 150 files (MB-01, recounted 2026-10-12).

| Fact | Value | Evidence |
|---|---|---|
| Entry | `resources/js/app.js` via `app.blade.php:93` | read |
| Router entries | 164 (161) | `grep -c "path: '" resources/js/router/index.js` |
| Layouts | `AppLayout.vue`, `PublicLayout.vue`, `AdvisorLayout.vue` | `ls resources/js/layouts` |
| Views | 169 files (168) | `git ls-files resources/js/views` |
| Components | 557 files (546) | `git ls-files resources/js/components` |
| Services | 62 files; axios base is `<origin><routerBase>/api` | `resources/js/services/api.js:62-64` |
| Store modules | 35 | `ls resources/js/store/modules` |
| Reachable from entry | 713 of 863 non-test `.vue`/`.js` files | reachability script, 2026-10-12: imports followed by resolved path, plus component tags, with comments stripped. The 2026-09-14 script matched imports by file name, so a live `views/Planning/WhatIfScenarios.vue` hid the dead `components/Protection/WhatIfScenarios.vue`, and `Estate/NetWorth.vue` was hidden the same way (with their children, 5 files). Re-run at the stamp, the corrected script gives 697 of 855. |
| Unreachable | 150 files (158 at the stamp by the corrected method; 153 reported then), Appendix A | same |
| Client calls with no route | `/estate/analyze`, `/savings/goals`, `/savings/expenditure-profile`, `/user/spouse`, `/investment/goal-progress/*`, `/investment/performance-attribution/*` | MB-02 to MB-05 |
| Side navigation, as rendered | Dashboard, Net Worth; Cash Management (Bank Accounts, Income, Expenditure); Finances (Investments, Retirement, Property, Liabilities, Personal Valuables, Risk Profile, Business); Personal Affairs (Protection, Will, Expression of Wishes, Trusts, Estate Planning, Power of Attorney); Planning (Holistic Plan, Tax Strategy, Plans, Journeys, What If Scenarios, Goals, Life Events, Actions); Account, Sign Out | Playwright snapshot this run; `resources/js/components/SideMenu.vue` |

### 2.7 Mobile web (`/m`)

**In plain English.** The phone version is a separate, smaller build of the same ideas. It is served as pre-built files, so a change to the desktop code does not reach it until the mobile bundle is rebuilt.

**Status:** Working for login, verification, dashboard and menu (driven 2026-09-14 at 390 pixels).

| Fact | Value | Evidence |
|---|---|---|
| Source | `resources/mobile/` 115 files (95 at the stamp); entry `main.js`; own `api.js`, `router.js`, `store.js`, `tokens.js` | `ls resources/mobile` |
| Build | `vite.mobile.config.js:36` → `public/m-build/` (present locally: `assets/`, `manifest.json`) | read |
| Router | 39 entries (36); `/module/:slug` redirects to the real module view (retired scaffold, comment at `router.js:59-61`) | `resources/mobile/router.js` |
| Views | 18 top-level plus `views/modules/` (NetWorth, NetWorthCategory, PropertyDetail, MortgageDetail, LiabilityDetail, Protection, ProtectionPolicy, Savings, SavingsAccount, Retirement, RetirementPensionDetail, Investment, InvestmentAccountDetail, Estate, EstateBequests, Goals, GoalDetail) | `ls resources/mobile/views` |
| Reachability | 64 of 66 non-test files reachable from `main.js` (58 of 58 at the stamp). `tokens.js` has had no importer since `997653dd7` (2026-09-17). `utils/lifeEvents.js` is imported only by web components (`resources/js/components/Goals/LifeEventCard.vue:77`, `LifeEventDetailInline.vue:261`). Both are MB-60. | same script, entry `resources/mobile/main.js` |
| API paths called | 63 distinct on 2026-09-14, all matching a route (two parameterised: `spouse-permission/${action}`, `share/${shareType}`); not re-matched in the refresh | script, 2026-09-14 |
| Menu, as rendered | Overview (Dashboard, Achievements, Conversation History); Cash Management (Income, Expenditure); Finances (Net Worth, Bank Accounts, Investments, Retirement); Family (Protection, Estate Planning); Planning (Goals, Tax Strategy, Holistic Plan); Account (Personal Information, Subscription, Settings); Share Fynla, Sign out | Playwright snapshot this run |
| Shared with web | `CLAUDE.md` names only `store/modules/auth.js`. The refresh found a second shared file, in the other direction: web imports `resources/mobile/utils/lifeEvents.js` (MB-60). The `auth.js` import itself: I COULD NOT VERIFY. | grep, 2026-10-12 |

### 2.8 Native iOS

**In plain English.** The iPhone app is written in Swift. It uses the same server as the website, at the live address, even for testing.

**Status:** Unverified. Not built or run this run.

| Fact | Value | Evidence |
|---|---|---|
| Files | 440 under `ios-native/` (423 at the stamp) | `git ls-files ios-native` |
| Server | both `Production.xcconfig` and `Staging.xcconfig` set `FYNLA_API_BASE_URL = https://fynla.org` | `ios-native/Configurations/*.xcconfig:3-4` |
| Feature folders | Achievements, Actions (new since the stamp), Authentication, BugReport, Dashboard, Estate, Expenditure, Fyn, Gamification, Goals, HolisticPlan, Income, Investment, Navigation, NetWorth, Privacy, Profile, Protection, Retirement, Savings, Settings, Subscription, TaxStrategy | `ls ios-native/Fynla/Features` |
| Core folders | API, Authentication, Biometrics, Components, DeepLinks, DesignSystem, Diagnostics, Formatting, FynEditing, Keychain, Migration, Navigation, Push, Sharing, StoreKit, Streaming, VersionPolicy | `ls ios-native/Fynla/Core` |
| API paths called | 95 distinct `path: "…"` literals on 2026-10-12 (85 on 2026-09-14, when all matched a route); the 95 were not re-matched in the refresh | script |
| Native-only routes | `routes/api_v1.php:44-116`: health, session exchange and refresh, StoreKit account token, purchase authorisation, entitlement, transactions, reconcile, status; guarded by `native.client`, `native.version`, `native.session` | read |
| Mobile v1 routes used by both `/m` and iOS | dashboard, achievements (v1, v2, milestones, completed), devices, notification preferences, share, web hand-offs, token refresh | `routes/api_v1.php:118-179` |

### 2.9 Fyn, the AI assistant

**In plain English.** Fyn is the chat assistant. It has one door for every client. Behind the door it is either in "onboarding" mode, where it can enter and change the user's information, or in "advice" mode, where it can only read and explain. The user never sees the switch.

**Status:** Working for opening the panel and receiving the resumption prompt on web and `/m` (driven 2026-09-14). The turn itself was not sent (no message was posted to the model).

| Piece | File | What the code shows |
|---|---|---|
| One endpoint | `routes/api.php:1510-1533` | `ai-chat` group: token usage, conversations list, create, contextual create, show, delete, send message (`throttle:ai-chat`, `idempotent`), stream queued message, cancel queued, resumption get and clear, action, onboarding status and start. |
| Dispatch predicate | `app/Services/AI/ContextualConversation/ConversationModeResolver.php:12-35` | Onboarding mode when `onboarding.fyn_flow_enabled` is true and the conversation source is not `surface_action`. For a `fyn_onboarding` conversation, only while the user has a step (`:23-31`, since `fe1fddee7` 2026-09-19; at the stamp, any `fyn_onboarding` conversation went to the director, MB-23). Otherwise when (`onboarding_completed === false` or `active_campaign !== null`) and `onboarding_fyn_step !== null` (`:33-34`). Called from `AiChatController.php:287` (send message), `:397` (stream a queued message; a submitted form also forces onboarding) and `:833` (action). |
| Two states | `AiChatController.php:298-299` | `OnboardingChatDirector::handleUserMessage` or `AdviceFyn::handle`; the same generator loop streams either. |
| Consent | `AiChatController.php:945-954` | Consent is rechecked at most every `ai_chat.consent_recheck_interval_seconds` during a stream. |
| Provider | `.env` `AI_PROVIDER=xai`; `app/Services/AI/XaiClient.php` | Tool catalogue is assembled from the procedural corpus (`XaiToolDefinitions.php:14-20`, `:30-59`): navigation, analysis, tax, plan generation, billing tools always; what-if, creation, modification, profile and Save Tax campaign tools only outside preview; pointer `fetch_*` tools always. An Anthropic-format catalogue `AiToolDefinitions.php` (480 lines) exists beside it; `tests/Architecture/ToolCatalogueParityTest.php` asserts parity and passed this run. |
| Prompt | `app/Services/AI/Fyn/FynSystemPrompt.php`, `FynContextAssembler.php`, `app/Services/AI/Prompts/` (ComplianceRules, CoreIdentity, EmptyDataGuard, FcaProcessInstructions, QueryKnowledge, UserContentSanitiser) | read at directory level; contents not mapped this run |
| Memory | `app/Services/AI/Memory/` (Episodic, Procedural, Recall, Semantic), corpus in `fyn-memory/{episodic,procedural,semantic}` | directory listing |
| Loop | `app/Services/AI/Loop/` (`FynLoop`, `Planner`, `ConcurrentTurnQueue`, `ResumptionService`, `SessionMode`), `Ground/GroundGate` | directory listing |
| Audit | `ai_audit_events` hash chain, `AuditChainService`, `ai:audit:verify-chain` weekly | `app/Console/Kernel.php:53`, `SHOW TABLES` |
| Background | `ai:conversations:summarise-stale` every 3 minutes (pause) and every 30 minutes; `fyn:episodic:reconcile` daily, `cold-archive` weekly; `AiIdempotencyCleanupJob` daily; `AiAuditRetentionJob` weekly | `app/Console/Kernel.php:33-70` |
| Frontend | web `store/modules/aiChat.js` (SSE consumer), `/m` `resources/mobile/fyn/contextualConversation.js`, iOS `Features/Fyn` and `Core/Streaming` | file listing |

The canonical contract is `.claude/skills/fyn-architecture/SKILL.md`; this run confirmed the predicate and the two-state dispatch in code as cited above.

### 2.10 Background machinery

**In plain English.** Every day the server sends reminders and alerts, expires lapsed subscriptions, deletes accounts whose owners asked for deletion, tidies old data, and moves marketing content through a publishing pipeline. Some of this runs every few minutes.

**Status:** Unverified (schedule read; nothing was triggered).

| Kind | Count | Where |
|---|---|---|
| Artisan commands | 67 on disk; 36 scheduled; 31 manual (2026-09-14). On 2026-10-12: still 67 on disk, and 37 distinct `$schedule->command()` names at both the stamp and `f00076906`, so the schedule is unchanged by name | `git ls-files app/Console/Commands`, `app/Console/Kernel.php:20-107` |
| Scheduled jobs | `PublishScheduledInsightsJob` every 5 minutes; `AiIdempotencyCleanupJob` daily 03:30; `AiAuditRetentionJob` weekly | `Kernel.php:56-63` |
| Daily alerts | daily insight 08:00, lifecycle 08:30, policy renewals 09:00, protection 09:15, mortgage rate 09:30, savings 10:00, estate 10:30, business filing 10:45 | `Kernel.php:37-47` |
| Subscription and account housekeeping | renewal reminders 09:00, expire 00:05, scheduled deletions 00:10, grace deletions 00:15, deletion reminders 00:20, purge monthly, check-overdue 01:00, reconcile pending payments every 10 minutes, Apple notification recovery every 10 minutes | `Kernel.php:20-26`, `:48-53` |
| Marketing pipeline | four detectors every `pipeline.poll_frequency_minutes` (default 5), drive watch daily, clip auto-approve, schedule posts hourly, optimal times weekly, weekly report, quarterly video audit | `Kernel.php:72-107` |
| Jobs | 14 (`app/Jobs`): six pipeline jobs, `ConversationSummariserJob`, `FireAwinConversionJob`, `ProcessAppleNotification`, `RunMonteCarloSimulation`, `RecalculateRiskProfileJob` (live: dispatched by the seven risk observers through `RiskRecalculationObserver::dispatchRecalculation()`, `RiskRecalculationObserver.php:18-36`; MB-07 withdrawn), three schedule-only jobs | dispatch-site scan this run |
| Mail | 38 mailables; every one has a send site except the seven lifecycle mails (MB-06) and, found in the refresh, `SpouseAccountCreated` and `SpouseAccountLinked` (MB-59) | scan this run; `git grep` 2026-10-12 |
| Notifications | 9; all sent from the alert commands or spouse permission | scan this run |
| Lifecycle engine | `config/lifecycle.php`, `app/Services/Lifecycle/LifecycleEngine.php`, two campaigns (churned, lapsed) | read |
| Queue | `.env` `QUEUE_CONNECTION=sync` locally, so every job runs inline | read |

### 2.11 Tests

**In plain English.** The code has several test suites. Two of them were run for this map: the architecture suite, which checks that the code keeps to the rules above, and the frontend unit suite.

| Suite | Files | Run this session | Result |
|---|---|---|---|
| `tests/Architecture` (Pest) | 48 (48 on 2026-10-12) | yes (2026-09-14) | `./vendor/bin/pest tests/Architecture` → 153 passed, 1 skipped, 30 deprecated, 4304 assertions, 106s |
| Vitest (`resources/**/__tests__`, `tests/frontend`) | 143 files (188 on 2026-10-12) | yes (2026-09-14) | `npx vitest run` → 143 files passed, 1329 tests passed, 223s |
| `tests/Unit` | 475 (560 on 2026-10-12) | no | |
| `tests/Feature` | 535 (+10 under `Feature/Fyn/Eval` in the `Eval` suite); 599 `*Test.php` files on 2026-10-12, 5 of them under `Feature/Fyn/Eval` | no | |
| `tests/Integration` | 4 | no | |
| `tests/Browser/scenarios` (BS-NN, the Rule 14 contract) | 24 | no | |
| `tests/E2E` (Playwright) | 24 specs; base URL `http://127.0.0.1:8000` | no | `playwright.config.js:3` |
| iOS | `FynlaTests`, `FynlaUITests` | no | |

Tests written in this run: none. The overview has no single contract to test beyond the architecture suite, which already exists. Refresh 2026-10-12: the full Architecture and Vitest runs were not repeated (CSJ 2026-09-27: no full or directory-wide suites locally; CI runs them). The section maps run their own scoped files.

## 3. Findings

| Id | Status | Where | What is wrong |
|---|---|---|---|
| MB-01 | Dead | 150 files under `resources/js` on 2026-10-12 (153 reported on 2026-09-14; Appendix A is the September list) | Unreachable from the app entry |
| MB-02 | Dead | `store/modules/estate.js:282`, `services/estateService.js:22` | Calls `POST api/estate/analyze`, which does not exist; never dispatched |
| MB-03 | Dead | `services/savingsService.js:115-135`, `store/modules/savings.js:296-302` | Three methods for two routes that do not exist |
| MB-04 | Dead | `services/userProfileService.js:195` | `GET api/user/spouse` does not exist; no caller |
| MB-05 | Dead + Dead end | `components/Investment/GoalProjection.vue`, `PerformanceAttribution.vue`, `BenchmarkComparison.vue` | Dead components calling unregistered API prefixes |
| MB-06 | Dead | `app/Mail/Lifecycle/` seven mailables, `config/lifecycle.php:12-15` | No campaign sends them |
| MB-07 | Withdrawn 2026-10-12 | `app/Observers/RiskRecalculationObserver.php`, `app/Jobs/RecalculateRiskProfileJob.php` | Wrong when raised: the class is the base of seven registered risk observers, which dispatch the job; `app/Services/CLAUDE.md` is correct |
| MB-08 | Dead (route side) | about 35 routes, Appendix C; the dossier `reports/mb-08-orphan-routes.md` settled on 25 rows | No caller in any client, including `GET api/dashboard` and its aggregator. Corrected 2026-10-12: `GET api/household/death-scenario` has a caller on a page no link reaches (`/planning/what-if/death-of-spouse`); all 25 rows are still registered |
| MB-09 | Duplicate | `routes/web.php:98-472` and the SPA public routes | Marketing pages rendered two ways |
| MB-10 | Does not make sense | `routes/web.php:662-704`, `:752-757` | Six mockups live without an environment guard |
| MB-11 | Does not make sense | Appendix B | Four unreferenced commands; twelve one-off migrations still registered |
| MB-12 | Dead | three services | Referenced only from tests |
| MB-13 | Dead | `app/Http/Kernel.php` aliases `admin`, `role`, `mfa.verified` | No route uses them |
| MB-60 | Dead + Does not make sense | `resources/mobile/tokens.js`, `resources/mobile/utils/lifeEvents.js` | Raised 2026-10-12: one `/m` file with no importer, and one `/m` file used only by web, against `CLAUDE.md`'s "only shared file" |

Categories that came up empty this run: Broken (no failing test, no failed interaction, no runtime error seen).

## 4. Coverage and gaps

| Area | Checked | I COULD NOT VERIFY |
|---|---|---|
| Route files | all six read; effective table dumped | Behaviour of any controller beyond login, verification and dashboard load |
| Controllers, services, models | inventoried and reverse-swept by class name | Function bodies; that is section-map work |
| Frontend reachability | web and `/m` computed from imports plus component tags with comments stripped | Components registered by a string that is not their file basename; none were found but the method cannot rule them out |
| Client-to-route matching | web, `/m`, iOS and public pages | Paths built by string concatenation that my regexes did not capture (the web service scan found 444 literal paths) |
| Playwright | web: homepage, login, code, dashboard, all nav groups, Tax Strategy click, Fyn panel; `/m`: login, code, dashboard, menu | Every other screen; no form was filled beyond login; no Fyn turn was sent |
| Tests | Architecture and Vitest run (2026-09-14) | Unit, Feature, Integration, Browser, E2E, iOS not run; nothing re-run for the overview in the 2026-10-12 refresh |
| Refresh 2026-10-12 | citations line-tracked; inventory recounted; web and `/m` reachability re-run | Browser walks, `/m` and iOS path matching, route-group sizes |
| iOS | configuration and API paths read | Nothing built or launched |
| Vault documents | not used as evidence | |

## 5. Glossary

| Term | Meaning in this document |
|---|---|
| Agent | A PHP class in `app/Agents` that orchestrates one module's calculations; not an AI agent |
| Fyn | The chat assistant; one endpoint, two internal modes |
| `/m` | The mobile web version, a separate Vue build served under `/m/app` |
| Native | The SwiftUI iPhone app in `ios-native/` |
| SPA | Single-page application: the desktop site, which loads once and rewrites itself |
| Preview persona | A seeded demo household visitors can explore without an account; its writes are blocked |
| Tier | Free or Premium; decides count caps and which planning tools open |
| Store | A class in `app/Services/Stores` that owns writes for one entity type |
| Observer | A class that runs when a model is saved or deleted, used here to keep cached and derived figures fresh |
| SSE | Server-sent events, the streaming format Fyn replies in |
| MFA | Multi-factor authentication, the optional authenticator-app step |
| IHT | Inheritance tax |
| LPA | Lasting power of attorney |
| Dead | Code no reachable path leads to (this run's evidence standard in `.claude/skills/app-map/SKILL.md`) |

## Appendix A — 150 unreachable web SPA files (MB-01), 2026-10-12

Computed at `f00076906` from `resources/js/app.js`, `router/index.js` and `App.vue`, following imports by resolved path and component tags, with HTML and JS comments stripped. Test files are excluded. This replaces the 2026-09-14 list of 153: re-run at the stamp, the corrected method finds 158 (§ 2.6 explains the 5 the first method missed).

- `components/Actions/ActionSummaryCard.vue`
- `components/Actions/RecommendationFilters.vue`
- `components/Admin/AdminInvestmentActions.vue`
- `components/Admin/AdminProtectionActions.vue`
- `components/Admin/AdminRetirementActions.vue`
- `components/Admin/InvestmentActionModal.vue`
- `components/Admin/ProtectionActionModal.vue`
- `components/Admin/RetirementActionModal.vue`
- `components/Dashboard/ActionsOverviewCard.vue`
- `components/Dashboard/AffordabilityOverviewCard.vue`
- `components/Dashboard/AlertsPanel.vue`
- `components/Dashboard/AreasToCompleteCard.vue`
- `components/Dashboard/AreasToConsiderCard.vue`
- `components/Dashboard/CrossModuleInsights.vue`
- `components/Dashboard/DashboardCard.vue`
- `components/Dashboard/DashboardSparkline.vue`
- `components/Dashboard/EmptyDashboard.vue`
- `components/Dashboard/GoalsCard.vue`
- `components/Dashboard/GoalsOverviewCard.vue`
- `components/Dashboard/GoalsProjectionChartDashboard.vue`
- `components/Dashboard/GoalsProjectionChartMini.vue`
- `components/Dashboard/HouseholdNetWorth.vue`
- `components/Dashboard/InvestmentsOverviewCard.vue`
- `components/Dashboard/LifeTimelineCard.vue`
- `components/Dashboard/NetWorthOverviewCard.vue`
- `components/Dashboard/NetWorthSummary.vue`
- `components/Dashboard/ProfileCompletionCards.vue`
- `components/Dashboard/SpousalOptimisations.vue`
- `components/Dashboard/TaxOptimisationCard.vue`
- `components/Estate/AssetForm.vue`
- `components/Estate/AssetsLiabilities.vue`
- `components/Estate/CashFlow.vue`
- `components/Estate/CashFlowProjectionChart.vue`
- `components/Estate/EstateProjectionComparison.vue`
- `components/Estate/GiftCard.vue`
- `components/Estate/IHTLiabilityGauge.vue`
- `components/Estate/NRBRNRBTracker.vue`
- `components/Estate/NetWorth.vue`
- `components/Estate/NetWorthWaterfallChart.vue`
- `components/Estate/PensionAmendmentBanner.vue`
- `components/Estate/TrustForm.vue`
- `components/Estate/TrustPlanning.vue`
- `components/Estate/TrustPlanningStrategy.vue`
- `components/Gamification/LevelCard.vue`
- `components/Goals/ChartTypeToggle.vue`
- `components/Goals/EventIconsOverlay.vue`
- `components/Goals/EventTooltip.vue`
- `components/Goals/GoalCard.vue`
- `components/Goals/GoalContributionStreak.vue`
- `components/Goals/GoalCountdown.vue`
- `components/Goals/GoalMilestoneTracker.vue`
- `components/Goals/GoalsAnalysis.vue`
- `components/Goals/GoalsByModule.vue`
- `components/Goals/GoalsList.vue`
- `components/Insights/InsightCtaPanel.vue`
- `components/Investment/AccountStrategyCard.vue`
- `components/Investment/AllocationComparison.vue`
- `components/Investment/AssetAllocationChart.vue`
- `components/Investment/AssetLocationOptimizer.vue`
- `components/Investment/BedAndISATransfers.vue`
- `components/Investment/BenchmarkComparison.vue`
- `components/Investment/BondWrapperInfoModal.vue`
- `components/Investment/CGTHarvestingOpportunities.vue`
- `components/Investment/ContributionPlanner.vue`
- `components/Investment/CorrelationMatrix.vue`
- `components/Investment/EfficientFrontier.vue`
- `components/Investment/FeeSavingsCalculator.vue`
- `components/Investment/GeographicAllocationMap.vue`
- `components/Investment/GoalForm.vue`
- `components/Investment/GoalProjection.vue`
- `components/Investment/ISAOptimizationStrategy.vue`
- `components/Investment/InvestmentOverviewCard.vue`
- `components/Investment/InvestmentReadinessGate.vue`
- `components/Investment/MonteCarloResults.vue`
- `components/Investment/PerformanceAttribution.vue`
- `components/Investment/PerformanceLineChart.vue`
- `components/Investment/PlanSections/ActionPlanSection.vue`
- `components/Investment/PlanSections/CurrentSituationSection.vue`
- `components/Investment/PlanSections/FeeAnalysisSection.vue`
- `components/Investment/PlanSections/GoalProgressSection.vue`
- `components/Investment/PlanSections/RecommendationsSection.vue`
- `components/Investment/PlanSections/RiskAnalysisSection.vue`
- `components/Investment/PlanSections/TaxStrategySection.vue`
- `components/Investment/PortfolioOptimization.vue`
- `components/Investment/PortfolioOptimizer.vue`
- `components/Investment/PortfolioOverview.vue`
- `components/Investment/RebalancingActions.vue`
- `components/Investment/RebalancingCalculator.vue`
- `components/Investment/StrategyRecommendationCard.vue`
- `components/Investment/TaxFees.vue`
- `components/Investment/TaxOptimization.vue`
- `components/Investment/TaxOptimizationOverview.vue`
- `components/Investment/TaxOptimizationRecommendations.vue`
- `components/Investment/WhatIfScenariosBuilder.vue`
- `components/Investment/WrapperOptimizer.vue`
- `components/Journey/JourneyProgressHero.vue`
- `components/Legal/StrategyDisclaimer.vue`
- `components/NetWorth/NetWorthOverview.vue`
- `components/NetWorth/Property/AmortizationScheduleView.vue`
- `components/NetWorth/Property/PropertyTaxCalculator.vue`
- `components/Onboarding/FocusAreaGrid.vue`
- `components/Onboarding/JourneyPreview.vue`
- `components/Onboarding/ProfileReviewPanel.vue`
- `components/Plans/Estate/EstateJointView.vue`
- `components/Plans/Investment/AccountFeeProjectionChart.vue`
- `components/Preview/KeepDataOrFreshModal.vue`
- `components/Protection/CoverageGapChart.vue`
- `components/Protection/CoverageTimelineChart.vue`
- `components/Protection/PremiumBreakdownChart.vue`
- `components/Public/CalculatorCard.vue`
- `components/Retirement/AccumulationChart.vue`
- `components/Retirement/AnnualAllowanceTracker.vue`
- `components/Retirement/DrawdownSimulator.vue`
- `components/Retirement/IncomeProjectionChart.vue`
- `components/Retirement/RequiredCapitalDetail.vue`
- `components/Retirement/SalarySacrificeDisplay.vue`
- `components/Retirement/StrategiesTab.vue`
- `components/Retirement/StrategyCard.vue`
- `components/Retirement/TaxBreakdownCard.vue`
- `components/Risk/CapacityForLossSection.vue`
- `components/Risk/RiskFactorsPanel.vue`
- `components/Risk/TimeHorizonSection.vue`
- `components/Savings/InterestRateComparisonChart.vue`
- `components/Savings/MissingDataCard.vue`
- `components/Savings/SaveGoalModal.vue`
- `components/Savings/SavingsDecisionPath.vue`
- `components/Savings/SavingsGoals.vue`
- `components/Shared/ISAAllowanceSummary.vue`
- `components/Shared/InfoGuideButton.vue`
- `components/Shared/InfoTooltip.vue`
- `components/Shared/PostcodeLookup.vue`
- `components/UserProfile/AssetsOverview.vue`
- `components/UserProfile/BalanceSheetTab.vue`
- `components/UserProfile/BalanceSheetView.vue`
- `components/UserProfile/CashFlowTab.vue`
- `components/UserProfile/CashflowView.vue`
- `components/UserProfile/IncomeStatementTab.vue`
- `components/UserProfile/LiabilitiesOverview.vue`
- `components/UserProfile/PersonalAccounts.vue`
- `components/UserProfile/ProfitAndLossView.vue`
- `components/UserProfile/TaxSummaryCard.vue`
- `components/WhatIf/ScenarioDetail.vue`
- `composables/useLifeStageFields.js`
- `constants/featureGating.js`
- `services/dashboardService.js`
- `services/portfolioOptimizationService.js`
- `store/utils/crudActionFactory.js`
- `utils/emergencyRunway.js` (new since the stamp)
- `views/Investment/AccountPerformancePanel.vue`
- `views/Investment/PortfolioStrategyPanel.vue`

On the stamp's corrected list but not on this one:

- `components/Estate/GiftForm.vue` — now reachable
- `components/Estate/GiftingStrategy.vue` — now reachable
- `components/Estate/LifePolicyStrategy.vue` — now reachable
- `components/Protection/GapAnalysis.vue` — deleted
- `components/Protection/ProtectionOverviewCard.vue` — deleted
- `components/Protection/RecommendationCard.vue` — deleted
- `components/Protection/ScenarioBuilder.vue` — deleted
- `components/Protection/WhatIfScenarios.vue` — deleted
- `components/UserProfile/DomicileInformation.vue` — deleted

## Appendix B — artisan commands not on the schedule (MB-11)

| Signature | Class | References outside its own file |
|---|---|---|
| `migrate:estate-to-networth` | `MigrateEstateToNetWorth.php` | 2 |
| `migrate:verify` | `VerifyDataMigration.php` | 2 |
| `data:encrypt` | `EncryptExistingData.php` | 2 |
| `gamification:backfill` | `GamificationBackfill.php` | 2 |
| `tier:sync-revolut` | `SyncRevolutTierVariations.php` | 1 |
| `family:reconcile-spouse-links` | `ReconcileSpouseFamilyLinks.php` | 0 |
| `pipeline:process` | `PipelineProcess.php` | 1 |
| `pipeline:google-preflight` | `Pipeline/GooglePreflight.php` | 4 |
| `pipeline:setup-tracker` | `Pipeline/SetupTracker.php` | 3 |
| `apple-store:bridge-health` | `AppleStoreBridgeHealth.php` | 2 |
| `subscriptions:audit-tier-collapse` | `AuditTierCollapse.php` | 1 |
| `estate:backfill-mirror-parties` | `BackfillMirrorWillParties.php` | 1 |
| `estate:backfill-bequests` | `BackfillWillBequests.php` | 0 |
| `mortgages:backfill-derived-columns` | `BackfillMortgageDerivedColumns.php` | 1 |
| `pensions:backfill-derived` | `BackfillPensionDerivedColumns.php` | 1 |
| `properties:backfill-derived-columns` | `BackfillPropertyDerivedColumns.php` | 2 |
| `properties:backfill-outstanding-mortgage` | `BackfillPropertyOutstandingMortgage.php` | 1 |
| `savings:backfill-derived` | `BackfillSavingsDerivedColumns.php` | 1 |
| `ai:usage:backfill` | `BackfillAiDailyUsage.php` | 1 |
| `fyn:episodic:backfill-blobs` | `FynEpisodicBackfillBlobs.php` | 5 |
| `fyn:episodic:purge` | `FynEpisodicPurge.php` | 3 |
| `fyn:pointers:reindex` | `FynPointersReindex.php` | 10 |
| `fyn:procedural:validate` | `FynProceduralValidate.php` | 18 |
| `fyn:semantic:promote` | `FynSemanticPromote.php` | 2 |
| `fyn:semantic:reindex` | `FynSemanticReindex.php` | 13 |
| `fyn:user:erase` | `FynUserErase.php` | 10 |
| `preview:reset` | `ResetPreviewData.php` | 4 |
| `eval:record` | `EvalRecordCommand.php` | 3 |
| `eval:show` | `EvalShowCommand.php` | 0 |
| `eval:purge` | `EvalPurgeCommand.php` | 0 |
| `eval:setup-azlan` | `EvalSetupAzlanCommand.php` | 2 |

## Appendix C — API routes with no caller found (MB-08)

Superseded on 2026-09-14 by the route-by-route dossier `docs/app-map/reports/mb-08-orphan-routes.md` (25 rows: 22 delete, 1 live after all, 2 for decision). See MB-08 for the grouped list with the dead-only callers noted. Method: every string literal passed to an HTTP call in reachable web files, all `/m` files, `public/pages` and every Swift `path:` literal, normalised and compared with `php artisan route:list`. Webhooks, health checks, eval-only and admin backup routes were excluded from the comparison.

## Appendix D — group layout of `routes/api.php`

```
153  Route::prefix('auth')->group(function () {
174      Route::prefix('password-reset')->group(function () {
183      Route::middleware('auth:sanctum')->group(function () {
189          Route::prefix('mfa')->group(function () {
198          Route::prefix('sessions')->group(function () {
205          Route::prefix('gdpr')->group(function () {
233  Route::prefix('insights')->group(function () {
254  Route::prefix('news')->group(function () {
263  Route::prefix('preview')->group(function () {
269      Route::middleware('auth:sanctum')->group(function () {
276  Route::middleware('auth:sanctum')->prefix('onboarding')->group(function () {
291  Route::middleware('auth:sanctum')->prefix('journeys')->group(function () {
303  Route::middleware('auth:sanctum')->prefix('life-stage')->group(function () {
311  Route::middleware('auth:sanctum')->prefix('user')->group(function () {
332      Route::prefix('family-members')->group(function () {
344      Route::prefix('personal-accounts')->group(function () {
359  Route::middleware('auth:sanctum')->prefix('info-guide')->group(function () {
366  Route::middleware('auth:sanctum')->prefix('spouse-permission')->group(function () {
375  Route::middleware('auth:sanctum')->group(function () {
381  Route::middleware('auth:sanctum')->prefix('tax-strategy')->group(function () {
387  Route::middleware('auth:sanctum')->prefix('net-worth')->group(function () {
400  Route::middleware('auth:sanctum')->prefix('joint-account-logs')->group(function () {
406  Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
414  Route::middleware(['auth:sanctum'])->prefix('properties')->group(function () {
428      Route::prefix('{propertyId}/mortgages')->group(function () {
438  Route::middleware(['auth:sanctum'])->prefix('mortgages')->group(function () {
447  Route::middleware(['auth:sanctum'])->prefix('business-interests')->group(function () {
459  Route::middleware(['auth:sanctum'])->prefix('chattels')->group(function () {
469  Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
477  Route::middleware('auth:sanctum')->prefix('protection')->group(function () {
489      Route::prefix('policies/life')->group(function () {
496      Route::prefix('policies/critical-illness')->group(function () {
503      Route::prefix('policies/income-protection')->group(function () {
510      Route::prefix('policies/disability')->group(function () {
517      Route::prefix('policies/sickness-illness')->group(function () {
525  Route::middleware('auth:sanctum')->prefix('savings')->group(function () {
536      Route::prefix('accounts')->group(function () {
550  Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
587  Route::middleware('auth:sanctum')->prefix('life-events')->group(function () {
604  Route::middleware('auth:sanctum')->prefix('investment')->group(function () {
623      Route::prefix('accounts')->group(function () {
635      Route::prefix('holdings')->group(function () {
649      Route::prefix('optimization')->middleware('throttle:10,1')->group(function () {
668      Route::prefix('rebalancing')->group(function () {
695      Route::prefix('contribution')->group(function () {
707      Route::prefix('tax-optimization')->group(function () {
734      Route::prefix('asset-location')->group(function () {
755      Route::prefix('performance')->group(function () {
773      Route::prefix('goals')->group(function () {
796      Route::prefix('fees')->group(function () {
815      Route::prefix('risk')->group(function () {
837      Route::prefix('model-portfolio')->group(function () {
853      Route::prefix('efficient-frontier')->group(function () {
872      Route::prefix('scenarios')->group(function () {
897  Route::middleware(['auth:sanctum'])->prefix('estate/liabilities')->group(function () {
907  Route::middleware(['auth:sanctum'])->prefix('estate')->group(function () {
915  Route::middleware(['auth:sanctum', 'estate.full'])->prefix('estate')->group(function () {
921      Route::prefix('assets')->group(function () {
928      Route::prefix('gifts')->group(function () {
941      Route::prefix('trusts')->group(function () {
962      Route::middleware('estate.full')->group(function () {
965          Route::prefix('will-builder')->group(function () {
983          Route::prefix('bequests')->group(function () {
991          Route::prefix('lpa')->group(function () {
1011  Route::middleware('auth:sanctum')->prefix('retirement')->group(function () {
1041      Route::prefix('pensions/dc')->group(function () {
1056      Route::prefix('pensions/db')->group(function () {
1071  Route::middleware('auth:sanctum')->prefix('plans')->group(function () {
1088  Route::middleware('auth:sanctum')->prefix('household')->group(function () {
1095  Route::middleware(['auth:sanctum', 'holistic.full'])->prefix('holistic')->group(function () {
1114  Route::middleware('auth:sanctum')->prefix('recommendations')->group(function () {
1131  Route::middleware('auth:sanctum')->prefix('gamification')->group(function () {
1139  Route::middleware('auth:sanctum')->prefix('tax-info')->group(function () {
1146  Route::middleware('auth:sanctum')->prefix('tax')->group(function () {
1153  Route::prefix('payment')->group(function () {
1163  Route::middleware('auth:sanctum')->prefix('payment')->group(function () {
1177  Route::middleware('auth:sanctum')->prefix('referral')->group(function () {
1187  Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
1194  Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
1205      Route::middleware('permission:users.edit')->group(function () {
1225      Route::prefix('ai-audit')->group(function () {
1252      Route::middleware(['permission:admin.backup'])->group(function () {
1257      Route::middleware(['permission:admin.backup', 'throttle:3,1'])->group(function () {
1270      Route::prefix('eval-recordings')->group(function () {
1310  Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin/insights')->group(function () {
1334  Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin/documents')->group(function () {
1350  Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
1362  Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin/pipeline')->group(function () {
1400  Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/retirement-actions')->group(function () {
1410  Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/investment-actions')->group(function () {
1420  Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/protection-actions')->group(function () {
1457  Route::middleware(['auth:sanctum', 'permission:admin.tax_config'])->prefix('tax-settings')->group(function () {
1469  Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('documents')->group(function () {
1491  Route::middleware(['auth:sanctum'])->prefix('what-if-scenarios')->group(function () {
1501  Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('ai-chat')->group(function () {
1565      Route::middleware(['throttle:20,1'])->prefix('eval')->group(function () {
1567          Route::middleware('auth:sanctum')->group(function () {
```

## Appendix E — Vue public page routes the router guard never lets render (MB-09)

Paths in `isServerRenderedPage` (`resources/js/router/index.js:1708-1722`) are always served by `public/pages/*.php`; the Vue component named for each is dead.

| Route | Vue component |
|---|---|
| `/` | `resources/js/views/Public/LandingPage.vue` |
| `/calculators` | `resources/js/views/Public/CalculatorsPage.vue` |
| `/security` | `resources/js/views/Public/SecurityPage.vue` |
| `/about` | `resources/js/views/Public/AboutPage.vue` |
| `/pricing` | `resources/js/views/Public/PricingPage.vue` |
| `/how-it-works` | `resources/js/views/Public/HowItWorksPage.vue` |
| `/features` | `resources/js/views/Public/FeaturesPage.vue` |
| `/faq` | `resources/js/views/Public/FaqPage.vue` |
| `/stage/starting-out` | `resources/js/views/Public/stages/StartingOutPage.vue` |
| `/stage/building-foundations` | `resources/js/views/Public/stages/BuildingFoundationsPage.vue` |
| `/stage/protecting-and-growing` | `resources/js/views/Public/stages/ProtectingAndGrowingPage.vue` |
| `/stage/planning-your-future` | `resources/js/views/Public/stages/PlanningYourFuturePage.vue` |
| `/stage/enjoying-your-wealth` | `resources/js/views/Public/stages/EnjoyingYourWealthPage.vue` |
| `/features/net-worth-dashboard` | `resources/js/views/Public/features/NetWorthDashboardFeature.vue` |
| `/features/ice-letters` | `resources/js/views/Public/features/IceLettersFeature.vue` |
| `/features/protection-gap` | `resources/js/views/Public/features/ProtectionGapFeature.vue` |
| `/features/monte-carlo` | `resources/js/views/Public/features/MonteCarloFeature.vue` |
| `/features/when-can-i-retire` | `resources/js/views/Public/features/WhenCanIRetireFeature.vue` |
| `/features/pension-tracker` | `resources/js/views/Public/features/PensionTrackerFeature.vue` |
| `/features/iht-planning` | `resources/js/views/Public/features/IhtPlanningFeature.vue` |
| `/why-fynla/our-approach` | `resources/js/views/Public/why-fynla/OurApproachPage.vue` |
| `/why-fynla/one-platform` | `resources/js/views/Public/why-fynla/OnePlatformPage.vue` |
| `/why-fynla/independent` | `resources/js/views/Public/why-fynla/IndependentPage.vue` |
| `/why-fynla/alternatives` | `resources/js/views/Public/why-fynla/AlternativesPage.vue` |
| `/learn` | `resources/js/views/Public/learn/LearnHubPage.vue` |
| `/learn/what-is-an-isa` | `resources/js/views/Public/learn/WhatIsAnIsaPage.vue` |
| `/learn/what-is-drawdown` | `resources/js/views/Public/learn/WhatIsDrawdownPage.vue` |
| `/learn/should-i-overpay-my-mortgage` | `resources/js/views/Public/learn/ShouldIOverpayMortgagePage.vue` |
| `/learn/should-i-consolidate-pensions` | `resources/js/views/Public/learn/ShouldIConsolidatePensionsPage.vue` |
| `/learn/guide/starting-out` | `resources/js/views/Public/learn/guide/StartingOutGuidePage.vue` |
| `/learn/glossary` | `resources/js/views/Public/learn/GlossaryPage.vue` |
| `/learn/what-is-salary-sacrifice` | `resources/js/views/Public/learn/WhatIsSalarySacrificePage.vue` |
| `/learn/what-is-an-lpa` | `resources/js/views/Public/learn/WhatIsAnLpaPage.vue` |
| `/learn/what-is-a-sipp` | `resources/js/views/Public/learn/WhatIsASippPage.vue` |
| `/learn/what-is-inheritance-tax` | `resources/js/views/Public/learn/WhatIsInheritanceTaxPage.vue` |
| `/learn/when-should-i-make-a-will` | `resources/js/views/Public/learn/WhenShouldIMakeAWillPage.vue` |
| `/learn/should-i-use-a-lisa-or-isa` | `resources/js/views/Public/learn/ShouldIUseALisaOrIsaPage.vue` |
| `/learn/when-can-i-afford-to-retire` | `resources/js/views/Public/learn/WhenCanIAffordToRetirePage.vue` |
| `/learn/guide/building-foundations` | `resources/js/views/Public/learn/guide/BuildingFoundationsGuidePage.vue` |
| `/learn/guide/protecting-and-growing` | `resources/js/views/Public/learn/guide/ProtectingAndGrowingGuidePage.vue` |
| `/learn/guide/planning-your-future` | `resources/js/views/Public/learn/guide/PlanningYourFutureGuidePage.vue` |
| `/learn/guide/enjoying-your-wealth` | `resources/js/views/Public/learn/guide/EnjoyingYourWealthGuidePage.vue` |
| `/learn/tax/pension-annual-allowance` | `resources/js/views/Public/learn/tax/PensionAnnualAllowancePage.vue` |
| `/learn/tax/iht-thresholds` | `resources/js/views/Public/learn/tax/IhtThresholdsPage.vue` |
| `/learn/tax/capital-gains-tax` | `resources/js/views/Public/learn/tax/CapitalGainsTaxPage.vue` |
| `/learn/tax/tax-year-checklist` | `resources/js/views/Public/learn/tax/TaxYearChecklistPage.vue` |
| `/learn/tax/isa-allowance` | `resources/js/views/Public/learn/tax/IsaAllowanceTaxPage.vue` |
| `/compare/fynla-vs-financial-planning-platform` | `resources/js/views/Public/compare/FynlaVsProjectionLabPage.vue` |
| `/compare/fynla-vs-financial-investment-platform` | `resources/js/views/Public/compare/FynlaVsVoyantPage.vue` |
| `/compare/fynla-vs-financial-centralisation-platform` | `resources/js/views/Public/compare/FynlaVsMoneyhubPage.vue` |
| `/compare/fynla-vs-spreadsheets` | `resources/js/views/Public/compare/FynlaVsSpreadsheetsPage.vue` |
| `/compare/best-financial-planning-tools-uk` | `resources/js/views/Public/compare/BestFinancialPlanningToolsPage.vue` |
| `/compare/fynla-vs-moneyhelper` | `resources/js/views/Public/compare/FynlaVsMoneyHelperPage.vue` |
| `/advisors` | `resources/js/views/Public/AdvisorsPage.vue` |
| `/contact` | `resources/js/views/Public/ContactPage.vue` |
| `/help` | `resources/js/views/Help.vue` (deleted since the stamp; 55 of the 56 remain on 2026-10-12) |
