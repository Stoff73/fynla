# Fynla — Web Application Surface & API Map

**Generated:** 2026-06-05
**Scope:** Web application only (the iOS / Capacitor native build is excluded; the responsive `/m` mobile-web funnel served *through the browser* is included and labelled).
**Sources of truth:**
- Surfaces — `resources/js/router/index.js` (the single Vue SPA router; every route self-declares an access tier via `meta`).
- APIs — `php artisan route:list --json` → **707 registered routes** (627 under `/api`, 80 server-rendered web routes incl. Sanctum CSRF).

**How to read this document:** Section 1 is the surface taxonomy (everything a user can *see*). Section 2 is the API map (everything that makes those surfaces *work*), grouped by domain, each annotated with the surface(s) that consume it. Section 3 covers server-rendered web routes (Blade, feeds, lifecycle/email landing pages). Section 4 is cross-cutting infrastructure. Section 5 has the summary counts.

---

## Access-tier legend

| Tier | `meta` flag | Who sees it |
|------|-------------|-------------|
| **Public** | `public: true` | Anyone, unauthenticated (marketing, SEO, learn/insights) |
| **Guest-only** | `requiresGuest: true` | Unauthenticated only (login/register; authed users bounce to dashboard) |
| **Authenticated** | `requiresAuth: true` | Logged-in users (the actual product) |
| **Admin** | `requiresAuth + requiresAdmin` | Admin role only |
| **Advisor** | `requiresAuth + requiresAdvisor` | Advisor role only |
| **Preview** | `public + previewMode` | Unauthenticated persona-driven demo (read-only) |

Capability/tier gating (Free / Tier1 / Tier2) is enforced **server-side** as the primary gate; the router adds defence-in-depth via `capabilityForRoute()` / `isRouteGated()` redirecting gated routes to `/teaser`.

---

# 1. Surfaces (everything a user sees)

## 1.1 Public marketing & SEO surfaces (`PublicLayout`)

### Core marketing
| Path | Route name | View |
|------|-----------|------|
| `/` | Home | `Public/LandingPage.vue` |
| `/calculators` | Calculators | `Public/CalculatorsPage.vue` |
| `/security` | Security | `Public/SecurityPage.vue` |
| `/about` | About | `Public/AboutPage.vue` |
| `/pricing` | Pricing | `Public/PricingPage.vue` |
| `/features` | Features | `Public/FeaturesPage.vue` |
| `/faq` | FAQ | `Public/FaqPage.vue` |
| `/how-it-works` | HowItWorks | `Public/HowItWorksPage.vue` |
| `/advisors` | Advisors | `Public/AdvisorsPage.vue` |
| `/contact` | Contact | `Public/ContactPage.vue` |
| `/sitemap` | Sitemap | `Public/SitemapPage.vue` |
| `/quickstart` | QuickStart | `Public/QuickStartPage.vue` |

### Legal / policy
| Path | View |
|------|------|
| `/privacy` | `Public/PrivacyPolicyPage.vue` |
| `/terms` | `Public/TermsOfServicePage.vue` |
| `/editorial-policy` | `Public/EditorialPolicyPage.vue` |

### Campaign landing pages
| Path | View | Note |
|------|------|------|
| `/savetax` | `Public/SaveTaxCampaignPage.vue` | Tax-saving campaign |
| `/biggerpension` | `Public/CampaignPage.vue` | Shared campaign template |
| `/paymortgage` | `Public/CampaignPage.vue` | " |
| `/managedebt` | `Public/CampaignPage.vue` | " |
| `/wealth` | `Public/CampaignPage.vue` | " |

### Life-stage explainers
| Path | View |
|------|------|
| `/stage/starting-out` | `stages/StartingOutPage.vue` |
| `/stage/building-foundations` | `stages/BuildingFoundationsPage.vue` |
| `/stage/protecting-and-growing` | `stages/ProtectingAndGrowingPage.vue` |
| `/stage/planning-your-future` | `stages/PlanningYourFuturePage.vue` |
| `/stage/enjoying-your-wealth` | `stages/EnjoyingYourWealthPage.vue` |

### Feature deep-dives
| Path | View |
|------|------|
| `/features/net-worth-dashboard` | `features/NetWorthDashboardFeature.vue` |
| `/features/ice-letters` | `features/IceLettersFeature.vue` |
| `/features/protection-gap` | `features/ProtectionGapFeature.vue` |
| `/features/monte-carlo` | `features/MonteCarloFeature.vue` |
| `/features/when-can-i-retire` | `features/WhenCanIRetireFeature.vue` |
| `/features/pension-tracker` | `features/PensionTrackerFeature.vue` |
| `/features/iht-planning` | `features/IhtPlanningFeature.vue` |

### "Why Fynla"
| Path | View |
|------|------|
| `/why-fynla/our-approach` | `why-fynla/OurApproachPage.vue` |
| `/why-fynla/one-platform` | `why-fynla/OnePlatformPage.vue` |
| `/why-fynla/independent` | `why-fynla/IndependentPage.vue` |
| `/why-fynla/alternatives` | `why-fynla/AlternativesPage.vue` |

### Comparison pages
| Path | View |
|------|------|
| `/compare/fynla-vs-financial-planning-platform` | `compare/FynlaVsProjectionLabPage.vue` |
| `/compare/fynla-vs-financial-investment-platform` | `compare/FynlaVsVoyantPage.vue` |
| `/compare/fynla-vs-financial-centralisation-platform` | `compare/FynlaVsMoneyhubPage.vue` |
| `/compare/fynla-vs-spreadsheets` | `compare/FynlaVsSpreadsheetsPage.vue` |
| `/compare/best-financial-planning-tools-uk` | `compare/BestFinancialPlanningToolsPage.vue` |
| `/compare/fynla-vs-moneyhelper` | `compare/FynlaVsMoneyHelperPage.vue` |
| *(redirects)* `/compare/fynla-vs-projectionlab`, `/compare/fynla-vs-voyant`, `/compare/fynla-vs-moneyhub` | → new slugs |

### Learn hub (content marketing)
| Path | View |
|------|------|
| `/learn` | `learn/LearnHubPage.vue` |
| `/learn/glossary` | `learn/GlossaryPage.vue` |
| **Concept explainers:** `/learn/what-is-an-isa`, `/learn/what-is-drawdown`, `/learn/what-is-salary-sacrifice`, `/learn/what-is-an-lpa`, `/learn/what-is-a-sipp`, `/learn/what-is-inheritance-tax` | dedicated `learn/*` views |
| **Decision guides:** `/learn/should-i-overpay-my-mortgage`, `/learn/should-i-consolidate-pensions`, `/learn/should-i-use-a-lisa-or-isa`, `/learn/when-should-i-make-a-will`, `/learn/when-can-i-afford-to-retire` | dedicated `learn/*` views |
| **Life-stage guides:** `/learn/guide/starting-out`, `/learn/guide/building-foundations`, `/learn/guide/protecting-and-growing`, `/learn/guide/planning-your-future`, `/learn/guide/enjoying-your-wealth` | `learn/guide/*` views |
| **Tax & allowances:** `/learn/tax/pension-annual-allowance`, `/learn/tax/iht-thresholds`, `/learn/tax/capital-gains-tax`, `/learn/tax/tax-year-checklist`, `/learn/tax/isa-allowance` | `learn/tax/*` views |
| *(redirect)* `/learning-centre` → `/learn` | |

### Insights hub (DB-backed CMS, gated by `VITE_INSIGHTS_CMS_ENABLED`)
| Path | View |
|------|------|
| `/insights` | `insights/InsightsHubPage.vue` |
| `/insights/:slug` (catch-all, flag-gated) | `insights/InsightArticlePage.vue` |
| **Bespoke articles:** `/insights/pension-iht-changes-2027`, `/insights/isa-allowance-2025-26`, `/insights/inheritance-tax-uk`, `/insights/pension-contribution-limits-uk`, `/insights/isa-guide-uk`, `/insights/retirement-planning-uk`, `/insights/stocks-shares-isa-uk`, `/insights/how-much-to-retire-uk` | dedicated `insights/*` views |

### News (DB-backed announcements / press)
| Path | View |
|------|------|
| `/news` | `Public/NewsHubPage.vue` |
| `/news/:slug` | `Public/NewsArticlePage.vue` |

### Fallback
| Path | View |
|------|------|
| `/:pathMatch(.*)*` (404 catch-all) | `Public/NotFoundPage.vue` |

---

## 1.2 Authentication & onboarding surfaces

| Path | Route name | View | Tier |
|------|-----------|------|------|
| `/login` | Login | `Login.vue` | Guest |
| `/register` | Register | `Register.vue` | Guest |
| `/checkout` | Checkout | `Auth/CheckoutPage.vue` | Auth |
| `/onboarding/welcome` | OnboardingWelcome | `Onboarding/OnboardingView.vue` | Auth (navbar hidden) |
| `/onboarding` + `/onboarding/:step` | Onboarding / OnboardingStep | `Onboarding/OnboardingView.vue` | Auth |
| `/onboarding/journey/:journey` | OnboardingJourney | `OnboardingView.vue` | Auth |
| `/onboarding/full` | OnboardingFull | `Onboarding/OnboardingFullView.vue` | Auth |
| `/onboarding/{protection,estate,investments,pensions,family,savings}` | Onboarding*Module* | `Onboarding/OnboardingModuleView.vue` | Auth |

---

## 1.3 Authenticated application surfaces

### Dashboard & global
| Path | Route name | View |
|------|-----------|------|
| `/dashboard` | Dashboard | `Dashboard.vue` |
| `/actions` | Actions | `Actions/ActionsDashboard.vue` |
| `/actions/:planType/:actionId` | ActionDetail | `Actions/ActionDetailView.vue` |
| `/holistic-plan` | HolisticPlan | `HolisticPlan.vue` |
| `/tax-strategy` | TaxStrategy | `TaxStrategy/TaxStrategyDashboard.vue` |
| `/valuable-info` | ValuableInfo | `ValuableInfo.vue` |
| `/teaser` | TierTeaser | `TierTeaserView.vue` (upgrade CTA) |
| `/help` | Help | `Help.vue` |
| `/version` | Version | `Version.vue` |
| `/debug-env` | DebugEnv | `DebugEnv.vue` (dev + admin only) |

### Settings & profile
| Path | View |
|------|------|
| `/settings` | `Settings.vue` |
| `/settings/security` | `Settings/SecuritySettings.vue` |
| `/settings/privacy` | `Settings/PrivacySettings.vue` |
| `/settings/assumptions` | `Settings/AssumptionsSettings.vue` |
| `/settings/notifications` | `Settings/NotificationSettings.vue` |
| `/settings/personal` | `Settings/PersonalSettings.vue` |
| `/settings/health` | `Settings/HealthSettings.vue` |
| `/settings/family` | `Settings/FamilySettings.vue` |
| `/settings/subscription` | `Settings/SubscriptionSettings.vue` |
| `/profile` | redirect → settings section |
| `/profile/notifications` | `UserProfile/NotificationPreferences.vue` |
| `/invoice/:id` | `InvoiceView.vue` |

### Net Worth module (parent `NetWorth/NetWorthDashboard.vue` with nested children)
| Path | Child component |
|------|----------------|
| `/net-worth` → `/net-worth/wealth-summary` | `NetWorthWealthSummary.vue` |
| `/net-worth/retirement` | `PensionList.vue` |
| `/net-worth/property` | `PropertyList.vue` |
| `/net-worth/investments` | `InvestmentList.vue` |
| `/net-worth/investment-detail` | `InvestmentProjections.vue` |
| `/net-worth/tax-efficiency` | `TaxEfficiencyDetail.vue` |
| `/net-worth/holdings-detail` | `HoldingsDetail.vue` |
| `/net-worth/fees-detail` | `FeesDetail.vue` |
| `/net-worth/strategy-detail` | `StrategyDetail.vue` |
| `/net-worth/cash` | `CashOverview.vue` |
| `/net-worth/business` | `BusinessInterestsList.vue` |
| `/net-worth/chattels` | `ChattelsList.vue` |
| `/net-worth/liabilities` | `LiabilitiesList.vue` |
| `/net-worth/joint-history` | `JointAccountHistory.vue` |
| `/investment` | redirect → `/net-worth/investments` |

### Retirement
| Path | View |
|------|------|
| `/pension/:type/:id` | `Retirement/PensionDetail.vue` |

### Protection
| Path | View |
|------|------|
| `/protection` | `Protection/ProtectionDashboard.vue` |
| `/protection/policy/:policyType/:id` | `Protection/PolicyDetail.vue` |

### Savings
| Path | View |
|------|------|
| `/savings` | `Savings/SavingsDashboard.vue` |
| `/savings/account/:id` | `Savings/SavingsAccountDetail.vue` |

### Goals
| Path | View |
|------|------|
| `/goals` | `Goals/GoalsDashboard.vue` |

### Risk profile (Investment sub-module)
| Path | View |
|------|------|
| `/risk-profile` | `Risk/RiskProfilePage.vue` |
| `/risk-profile/levels` | `Risk/RiskLevelsExplainedPage.vue` |
| `/risk-profile/factor/:factor` | `Risk/RiskFactorDetailPage.vue` |

### Estate planning
| Path | View | Note |
|------|------|------|
| `/estate` | `Estate/EstateDashboard.vue` | |
| `/estate/inheritance-tax` | `Estate/InheritanceTaxDetail.vue` | |
| `/estate/power-of-attorney` | `Estate/PowerOfAttorneyView.vue` | full-tier gated (`requireFullEstateAccess`) |
| `/estate/lpa/create/:type` | `Estate/LpaWizardView.vue` | full-tier gated |
| `/estate/will-builder` | `Estate/WillBuilderView.vue` | full-tier gated |

### Trusts
| Path | View |
|------|------|
| `/trusts` | `Trusts/TrustsDashboard.vue` |
| `/trusts/:id` | `Trusts/TrustDetailView.vue` |

### Planning & scenarios
| Path | View |
|------|------|
| `/planning/journeys` | `Planning/PlanningJourneys.vue` |
| `/planning/what-if` | `Planning/WhatIfDashboard.vue` |
| `/planning/what-if/death-of-spouse` | `Planning/WhatIfScenarios.vue` |
| `/planning/what-if/:id` | `Planning/WhatIfScenarioDetailView.vue` |

### Plans
| Path | View |
|------|------|
| `/plans` | `Plans/PlansDashboard.vue` |
| `/plans/investment` | `Plans/InvestmentPlan.vue` |
| `/plans/protection` | `Plans/ProtectionPlan.vue` |
| `/plans/retirement` | `Plans/RetirementPlan.vue` |
| `/plans/estate` | `Plans/EstatePlan.vue` |
| `/plans/goal/:goalId` | `Plans/GoalPlan.vue` |

---

## 1.4 Admin surfaces (`requiresAdmin`)

| Path | View |
|------|------|
| `/admin` | `Admin/AdminPanel.vue` |
| `/admin/insights` | `Admin/Insights/ArticleListPage.vue` |
| `/admin/insights/new` | `Admin/Insights/ArticleEditor.vue` |
| `/admin/insights/templates` | `Admin/Insights/TemplateListPage.vue` |
| `/admin/insights/:id/edit` | `Admin/Insights/ArticleEditor.vue` |
| `/admin/documents` | `Admin/Documents/DocumentListPage.vue` |
| `/admin/documents/:id/edit` | `Admin/Documents/DocumentEditor.vue` |
| `/admin/news-subscribers` | `Admin/NewsSubscribersPage.vue` |

---

## 1.5 Advisor surfaces (`requiresAdvisor`, `AdvisorLayout`)

| Path | View |
|------|------|
| `/advisor` | `Advisor/AdvisorDashboard.vue` |
| `/advisor/clients` | `Advisor/AdvisorClientList.vue` |
| `/advisor/clients/:id` | `Advisor/AdvisorClientDetail.vue` |
| `/advisor/activities` | `Advisor/AdvisorActivityLog.vue` |
| `/advisor/reviews` | `Advisor/AdvisorReviewsDue.vue` |
| `/advisor/reports` | `Advisor/AdvisorReports.vue` |

---

## 1.6 Preview (persona demo, read-only, unauthenticated)

These reuse the *authenticated* components in preview mode. Persona is loaded via `?persona=` query param; an authenticated user hitting `/preview/*` is redirected to the real authed path.

| Path | Reuses |
|------|--------|
| `/preview` | `Dashboard.vue` |
| `/preview/net-worth` + nested (`wealth-summary`, `retirement`, `property`, `cash`, `investments`, `investment-detail`, `tax-efficiency`, `holdings-detail`, `fees-detail`, `strategy-detail`, `liabilities`) | NetWorth components |
| `/preview/protection` | `ProtectionDashboard.vue` |
| `/preview/savings` | `SavingsDashboard.vue` |
| `/preview/goals` | `GoalsDashboard.vue` |
| `/preview/estate` | `EstateDashboard.vue` |
| `/preview/estate/power-of-attorney` | `PowerOfAttorneyView.vue` |
| `/preview/profile` | `UserProfile.vue` |
| `/preview/investment` → `/preview/net-worth/investments`, `/preview/retirement` → `/preview/net-worth/retirement` | redirects |

---

## 1.7 Mobile-web funnel (`/m`, served via browser — NOT the iOS build)

Phones are routed to `/m`, which hosts the responsive funnel in a same-origin iframe and bridges the auth token into the isolated mobile SPA (`/m/app`). These are server-rendered Blade closures (see §3), not Vue router entries:

| Path | Purpose |
|------|---------|
| `/m` | Mobile entry (iframes responsive funnel) |
| `/m/landing` | Mobile landing HTML |
| `/m/app/{any?}` | Isolated mobile SPA shell |
| `/m-mockup/dashboard` | Mockup/preview |

> The `/api/v1/mobile/*` endpoints (§2.20) primarily serve the native iOS app **and** this mobile-web SPA. They are listed for completeness but are mobile-surface APIs.

---

# 2. API map (everything that powers the surfaces)

All `/api/*` routes are JSON, behind Sanctum auth unless noted. Grouped by domain; each group notes its consuming surface(s).

## 2.1 Authentication & account — `/api/auth/*` (41 routes)
**Consumed by:** `/login`, `/register`, `/settings/security`, `/settings/privacy`, MFA flows.

- Core: `POST login`, `POST logout`, `POST logout-beacon`, `POST register`, `POST verify-code`, `POST resend-code`, `GET user`, `POST change-password`
- MFA (`/api/auth/mfa/*`): `setup`, `verify-setup`, `verify`, `status`, `disable`, `recovery`, `recovery-codes`
- Password reset (`/api/auth/password-reset/*`): `request`, `resend-code`, `verify-email`, `verify-mfa`, `mfa-recovery`, `reset`
- Sessions (`/api/auth/sessions/*`): `GET index`, `DELETE {id}`, `DELETE others/all`
- Account restore (`/api/auth/restore`, `/restore/check`)
- **GDPR** (`/api/auth/gdpr/*`): consents (`GET/PUT`, `history`), erasure (`initiate`/`verify`/`execute`/`resend-code`/`status`/`cancel-scheduled` + `{id}/confirm`/`{id}/cancel`), export (`request`/`status`/`{id}/download`)
- Infra: `GET /sanctum/csrf-cookie`

## 2.2 Dashboard — `/api/dashboard/*` (4)
**Consumed by:** `/dashboard`, `/preview`.
`GET index`, `GET alerts`, `POST alerts/{id}/dismiss`, `POST invalidate-cache`

## 2.3 AI Chat (Fyn) — `/api/ai-chat/*` (9)
**Consumed by:** the Fyn chat surface (global, embedded in `AppLayout`) + onboarding.
`GET/POST conversations`, `GET/DELETE conversations/{id}`, `POST conversations/{id}/messages` (the single read/write dispatch endpoint — both web & `/m`), `POST conversations/{id}/action`, `POST onboarding/start`, `GET onboarding/status`, `GET token-usage`

## 2.4 Net Worth — `/api/net-worth/*` (6)
**Consumed by:** `/net-worth/*`, `/dashboard`.
`GET overview`, `GET breakdown`, `GET assets-summary`, `GET assets-summary-detailed`, `GET joint-assets`, `POST refresh`

## 2.5 Household — `/api/household/*` (3)
**Consumed by:** `/dashboard`, death-scenario / what-if surfaces.
`GET net-worth`, `GET death-scenario`, `GET optimisations`

## 2.6 Savings — `/api/savings/*` (10)
**Consumed by:** `/savings`, `/savings/account/:id`, `/preview/savings`.
`GET index`, `POST/GET/PUT/DELETE accounts`, `PATCH accounts/{id}/toggle-retirement`, `POST analyze`, `GET isa-allowance/{taxYear}`, `GET recommendations`, `POST scenarios`

## 2.7 Investment — `/api/investment/*` (113 — the largest domain)
**Consumed by:** `/net-worth/investments`, `/net-worth/{investment-detail,tax-efficiency,holdings-detail,fees-detail,strategy-detail}`, `/plans/investment`, `/risk-profile/*`.
Sub-areas:
- **Accounts & holdings:** CRUD `accounts`, `holdings`, `accounts/{id}/{diversification,projections}`, toggle-retirement
- **Analysis:** `POST analyze`, `recommendations`, `POST projections`, `POST/GET monte-carlo` + `monte-carlo/{jobId}`
- **Risk profile** (`/risk/*` + `risk-profile`): `levels`, `allowed-levels`, `config/{level}`, `GET/POST profile`, `recalculate`, `validate-product-level`
- **Asset location** (`/asset-location/*`): analyze, recommendations, tax-drag, compare-accounts, optimization-score, clear-cache
- **Fees** (`/fees/*`): analyze, holdings, active-vs-passive, compare-platforms, compare-specific, ocf-impact, alternatives/{holdingId}
- **Efficient frontier** (`/efficient-frontier/*`): calculate, compare, default, optimal-by-{risk,return}, statistics, analyze-current
- **Portfolio optimization** (`/optimization/*`): efficient-frontier, maximize-sharpe, minimize-variance, risk-parity, target-return, correlation-matrix, current-position
- **Model portfolio** (`/model-portfolio/*`): all, compare, funds, glide-path, optimize-by-{age,horizon}, {riskLevel}
- **Rebalancing** (`/rebalancing/*` + `accounts/{id}/rebalancing*`): actions CRUD, analyze-drift, calculate, compare-cgt, evaluate-strategies, {calendar,opportunistic,threshold}-strategy, recommend-frequency, within-cgt-allowance, save
- **Tax optimization** (`/tax-optimization/*`): analyze, bed-and-isa, cgt-harvesting, isa-strategy, efficiency-score, recommendations, calculate-savings
- **Performance** (`/performance/*`): analyze, benchmark, multi-benchmark, risk-metrics
- **Goal progress** (`/goals/*`): progress/all, {goalId}/{progress,shortfall,what-if}, calculate-probability, required-contribution, glide-path
- **Contribution** (`/contribution/*`): optimize, affordability, lump-sum-vs-dca
- **Scenarios** (`/scenarios/*`): CRUD, compare, templates, {id}/{run,results,save,unsave}
- **Portfolio strategy** (`/portfolio-strategy*`): index, account/{accountId}

## 2.8 Retirement — `/api/retirement/*` (29)
**Consumed by:** `/net-worth/retirement`, `/pension/:type/:id`, `/plans/retirement`.
`GET index`, `POST analyze`, DB pensions CRUD (`pensions/db*`), DC pensions CRUD (`pensions/dc*`) + DC holdings (`pensions/dc/{id}/holdings*`, bulk-update), `state-pension`, `income` (+`accounts`, `calculate`), `projections`, `dc-pensions/{id}/projections`, `portfolio-analysis` (+`{dcPensionId}`), `decumulation-analysis`, `required-capital`, `annual-allowance/{taxYear}`, `recommendations`, `scenarios`, `strategies` (+`impact`), `getDCPensionDiversification`

## 2.9 Protection — `/api/protection/*` (21)
**Consumed by:** `/protection`, `/protection/policy/:type/:id`, `/plans/protection`, `/preview/protection`.
`GET index`, `POST analyze`, `POST profile`, `PATCH profile/has-no-policies`, `recommendations`, `scenarios`, and per-type policy CRUD for **life / critical-illness / income-protection / disability / sickness-illness** (`policies/{type}` POST + `{id}` PUT/DELETE)

## 2.10 Estate & Trusts — `/api/estate/*` (54)
**Consumed by:** `/estate`, `/estate/inheritance-tax`, `/estate/power-of-attorney`, `/estate/lpa/*`, `/estate/will-builder`, `/trusts`, `/trusts/:id`, `/plans/estate`, `/preview/estate*`.
- Estate core: `GET index`, assets CRUD, gifts CRUD, liabilities CRUD, `net-worth`, `cash-flow`
- IHT (`Estate\IHTController`): `POST calculate-iht`, `POST profile`
- Gifting (`Estate\GiftingController`): `calculate-discount`, `gifts/{personalized,planned}-strategy`, `gifts/trust-strategy`
- Wills (`Estate\WillController`): `GET/POST will`, bequests CRUD, `calculate-intestacy`
- Will Builder (`Estate\WillDocumentController`): CRUD `will-builder`, `pre-populate`, `{id}/{complete,mirror,validate}`
- LPA (`Estate\LpaController`): CRUD `lpa`, `donor-defaults`, `upload`, `{id}/{compliance,register}`
- Trusts (`Estate\TrustController`): `GET/POST trusts`, `{id}` PUT/DELETE, `{id}/{analyze,assets,calculate-iht-impact}`, `trust-recommendations`, `upcoming-tax-returns`
- Life policy strategy, letter-validation (`letter-validation`)

## 2.11 Goals & Life Events — `/api/goals/*` (20) + `/api/life-events/*` (8) + `/api/life-stage/*` (4)
**Consumed by:** `/goals`, `/plans/goal/:goalId`, `/preview/goals`, dashboard life-stage prompts.
- Goals: CRUD, `analysis`, `dashboard-overview`, `financial-forecast`, `household-summary`, `projection`, `risk-levels`, `types`, `calculate-property-costs`, `{id}/{contribution,contributions,dependencies,projections,scenarios}`
- Life events: CRUD, `by-age`, `types`, `{id}/complete`
- Life stage: `set`, `progress`, `completeness`, `complete-step`

## 2.12 Plans — `/api/plans/*` (7)
**Consumed by:** `/plans`, `/plans/{type}`, `/plans/goal/:goalId`.
`GET {type}`, `POST {type}/recalculate`, `DELETE {type}/clear-cache`, `PUT {type}/funding-source`, `GET goal/{goalId}`, `POST goal/{goalId}/recalculate`, `GET statuses`

## 2.13 Holistic planning — `/api/holistic/*` (9)
**Consumed by:** `/holistic-plan`, `/dashboard` recommendations.
`POST analyze`, `POST plan`, `GET cash-flow-analysis`, `GET recommendations` (+`completed`), `recommendations/{id}/{dismiss,in-progress,mark-done,notes}`

## 2.14 Recommendations & Actions — `/api/recommendations/*` (8)
**Consumed by:** `/actions`, `/actions/:planType/:actionId`, `/dashboard`.
`GET index`, `top`, `summary`, `completed`, `{id}/{dismiss,in-progress,mark-done,notes}`

## 2.15 Tax — `/api/tax/*`, `/api/tax-*` (incl. settings, info, strategy, year) (~21)
**Consumed by:** `/tax-strategy`, Tax Status tab, settings/assumptions, public tax pages.
- `/api/tax/*`: `config`, `income-definitions`, `optimisation-analysis`, `strategies`
- `/api/tax-strategy`: `show`, `POST calculate` — powers `/tax-strategy`
- `/api/tax-info/*`: `investment/{accountType}`, `savings/{accountType}`, `summary`
- `/api/tax-settings/*` (admin-ish config): `all`, `current`, `calculations`, `create`, `{id}` PUT/DELETE, `{id}/{activate,duplicate}`
- `/api/tax-year/current`
- `/api/public/{tax-allowances,tax-config}` — unauthenticated, power public calculators/learn pages

## 2.16 Properties, Mortgages, Business, Chattels, Liabilities
**Consumed by:** `/net-worth/{property,business,chattels,liabilities}`.
- Properties `/api/properties/*` (12): CRUD, `calculate-sdlt`, `{id}/{calculate-cgt,rental-income-tax}`, nested `{propertyId}/mortgages*`
- Mortgages `/api/mortgages/*` (5): `calculate-payment`, `{id}` show/PUT/DELETE, `{id}/amortization-schedule`
- Business interests `/api/business-interests/*` (7): CRUD, `{id}/{exit-calculation,tax-deadlines}`
- Chattels `/api/chattels/*` (6): CRUD, `{id}/calculate-cgt`
- Joint account logs `/api/joint-account-logs` (1) → `/net-worth/joint-history`

## 2.17 What-If Scenarios — `/api/what-if-scenarios/*` (6)
**Consumed by:** `/planning/what-if`, `/planning/what-if/:id`, `/planning/what-if/death-of-spouse`.
CRUD, `count`. *(Note: scenario creation is a WRITE tool — barred from read-only Advice Fyn per canonical contract.)*

## 2.18 Journeys — `/api/journeys/*` (8)
**Consumed by:** `/planning/journeys`, `/onboarding/journey/:journey`, dashboard prompts.
`dashboard-prompts`, `dismiss-prompt`, `preview`, `GET/POST selections`, `{journey}/{steps,start,complete}`

## 2.19 Onboarding — `/api/onboarding/*` (11)
**Consumed by:** `/onboarding*`.
`status`, `steps`, `step/{step}` (GET) + `POST step`, `complete`, `complete-quick`, `focus-area`, `restart`, `skip-step`, `skip-to-dashboard`, `skip-reason/{step}`

## 2.20 User profile & household — `/api/user/*` (26) + `/api/users/{userId}` (2) + `/api/household` + `/api/spouse-permission/*` (5)
**Consumed by:** `/settings/*`, `/profile/*`, `/valuable-info`, spouse-linking flows.
- Profile: `GET profile` (+`completeness`), `PUT profile/{personal,domicile,expenditure,income-occupation}`, `dashboard-widget-order`, `financial-commitments`, `spouse/financial-commitments`
- Family members `/api/user/family-members/*`: CRUD
- Personal accounts `/api/user/personal-accounts/*`: index, calculate, line-item CRUD
- Letter to spouse `/api/user/letter-to-spouse*`: show/update/exists/spouse → `/valuable-info`
- Guidance status, seed-persona-data
- Spouse permission: request/accept/reject/revoke/status
- `/api/users/{userId}` + `{userId}/expenditure` (spouse data)

## 2.21 Settings & assumptions — `/api/settings/*` (2) + `/api/info-guide/*` (3) + `/api/notifications/*` (2)
**Consumed by:** `/settings/assumptions`, `/settings/notifications`, in-app info guide panel.
- `GET assumptions`, `PUT assumptions/{type}`
- Info guide: `GET/PUT preference`, `GET requirements` (driven by router `afterEach` module mapping)
- Notification preferences: `GET/PUT preferences`

## 2.22 Payments & subscriptions — `/api/payment/*` (11) + `/api/pricing-config` + `/api/webhooks/revolut`
**Consumed by:** `/checkout`, `/settings/subscription`, `/pricing`, `/teaser`, `/invoice/:id`.
`plans`, `trial-status`, `create-order`, `confirm`, `upgrade`, `cancel-subscription`, `validate-discount`, `billing-history`, `invoices/{invoice}` (+`/download`), `delete-all-data`; `GET pricing-config`; `POST webhooks/revolut` (Revolut callback)

## 2.23 Documents (user uploads / AI extraction) — `/api/documents/*` (10)
**Consumed by:** document upload modals across modules.
`index`, `types`, `upload`, `upload-only`, `{id}` show/DELETE, `{id}/{confirm,confirm-excel,extraction,reprocess}`

## 2.24 Referral — `/api/referral/*` (3)
**Consumed by:** referral/invite UI.
`code`, `invite`, `list`

## 2.25 Preview personas — `/api/preview/*` (4) + `/api/eval/*` (2)
**Consumed by:** `/preview/*` surfaces (persona login/switch/exit/personas); eval harness (login/reset/{personaId}).

## 2.26 Public/unauthenticated APIs
**Consumed by:** public marketing/SEO surfaces.
- `/api/insights` (+`featured`, `{slug}`) → `/insights*`
- `/api/news` (+`{slug}`, `subscribe`) → `/news*`
- `/api/contact` → `/contact`
- `/api/bug-report` → in-app bug report
- `/api/postcode-lookup/{postcode}`, `/api/occupations/search` → form autocompletes
- `/api/public/{tax-allowances,tax-config}` → calculators

## 2.27 Admin APIs — `/api/admin/*` (100)
**Consumed by:** `/admin*` surfaces.
Major groups: `dashboard`, users CRUD (+`module-status`), roles, subscriptions/stats, discount-codes CRUD (+toggle), tier-configurations, user-metrics (`activity`/`engagement`/`plans`/`snapshot`), backups (create/list/restore/delete), AI provider (get/set), AI audit (`chain`/`chain/verify`/users/conversations/messages), eval-recordings, insights articles/templates/images (full CMS), documents (CMS), news-subscribers (+export), reference-data editors (actuarial-life-tables, currency-rates, savings-market-rates), and per-module action-definition editors (action-definitions, investment-actions, protection-actions, retirement-actions, decision-matrix)

## 2.28 Advisor APIs — `/api/advisor/*` (11)
**Consumed by:** `/advisor/*` surfaces.
`dashboard`, `clients` (+`{id}`, `{id}/enter`, `{id}/modules`), `exit`, `activities` (GET/POST/PUT), `reviews-due`, `reports`

## 2.29 Mobile-web/native APIs — `/api/v1/*` (11)
**Consumed by:** `/m/app` mobile-web SPA and the iOS app (iOS excluded from scope; listed for completeness).
`v1/health`, `v1/auth/refresh-token`, `v1/mobile/dashboard`, `v1/mobile/devices` (GET/POST/DELETE), `v1/mobile/insights/daily`, `v1/mobile/modules/{module}`, `v1/mobile/notifications/preferences` (GET/PUT), `v1/mobile/share/{type}/{id?}`

---

# 3. Server-rendered web routes (80)

Not SPA surfaces — these are Laravel-served (Blade closures, SEO HTML, feeds, email/lifecycle landing pages):

- **SPA bootstrap:** `/` and `/{any}` catch-all serve the Vue app shell. The many `GET` closures for `/about`, `/pricing`, `/learn/*`, `/compare/*`, `/features/*`, `/why-fynla/*`, `/stage/*`, `/insights/{slug}`, `/savetax*` are **SEO pre-render / crawler-facing HTML** for the same paths the SPA also handles client-side.
- **Mobile-web (browser):** `/m`, `/m/landing`, `/m/app/{any?}`, `/m-mockup/dashboard`
- **savetax funnel variants:** `/savetax`, `/savetax/v2`, `/savetax/plan`, `/savetax/plan/v2`, `/savetax/plan/v3`, `/savetax/plan/v4`
- **RSS/XML feeds:** `/feed/insights.xml` (`FeedController@insights`), `/feed/news.xml` (`FeedController@news`)
- **Lifecycle email landing pages** (`Lifecycle\LifecycleActionController`): `/lifecycle/apply-discount`, `/lifecycle/feedback`, `POST /lifecycle/feedback-text`, `/lifecycle/update-payment`
- **Newsletter** (`NewsletterActionController`): `/subscribe/news/confirm/{token}`, `/unsubscribe/news/{token}`
- **File serving:** `/storage/{path}`
- **Dev only:** `/_ignition/*` (Spatie Ignition error pages)

---

# 4. Cross-cutting infrastructure

| Concern | Mechanism |
|---------|-----------|
| **Auth** | Laravel Sanctum bearer token (sessionStorage `auth_token`); `GET /sanctum/csrf-cookie` for CSRF |
| **Preview isolation** | `previewMode` routes reuse authed components read-only; `PreviewWriteInterceptor` middleware blocks writes server-side |
| **Tier/capability gating** | Server-side primary gate + router `capabilityForRoute()`/`isRouteGated()` → redirect to `/teaser` |
| **AI write-safety** | `AdviceFyn::WRITE_TOOLS` stripped from catalogue; write intents route via `delegate_to_capture` handoff → `OnboardingChatDirector::handleInlineCapture` |
| **Single AI endpoint** | Both web & `/m` post to `POST /api/ai-chat/conversations/{id}/messages` (server decides read vs write state) |
| **Analytics** | Router `afterEach` → `analyticsService.trackPageView`; Awin MasterTag (consent-gated, excluded on checkout) |
| **Info guide** | Router `afterEach` maps route→module → `GET /api/info-guide/requirements` |

---

# 5. Summary counts

| Category | Count |
|----------|-------|
| **Total registered backend routes** | **707** |
| API routes (`/api/*` + sanctum) | 627 |
| Server-rendered web routes | 80 |
| **SPA surfaces (router entries, incl. children/redirects)** | ~190 |
| Public marketing/SEO surfaces | ~85 |
| Authenticated app surfaces | ~70 |
| Admin surfaces | 8 |
| Advisor surfaces | 6 |
| Preview surfaces | ~22 |
| Onboarding surfaces | ~12 |

### Largest API domains (by route count)
| Domain | Routes |
|--------|--------|
| Investment | 113 |
| Admin | 100 |
| Estate | 54 |
| Auth (+GDPR/MFA/sessions) | 41 |
| Retirement | 29 |
| User profile | 26 |
| Protection | 21 |
| Goals | 20 |

---

*End of map. Surfaces sourced from `resources/js/router/index.js`; APIs from `php artisan route:list` (707 routes) on branch `dev` at 2026-06-05.*
