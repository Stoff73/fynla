# 5. Frontend Architecture

Fynla's frontend is a Vue.js 3 single-page application. A single Blade template bootstraps the Vue app; from there, Vue Router manages navigation, Vuex manages state, and Axios handles API communication.

---

## 5.1 App Entry Point

**File:** `resources/js/app.js`

The application boots in a specific sequence:

1. **Import `bootstrap.js`** -- configures the global Axios instance with CSRF token from the HTML meta tag, sets `withCredentials: true` for Sanctum cookie authentication, and detects whether the app runs on localhost or production to set the correct base URL.

2. **Start console capture** -- imports and activates `consoleCapture` immediately so any initialisation errors are logged before the app mounts.

3. **Create the Vue app** -- calls `createApp(App)` and installs three plugins:
   - **Vue Router** -- client-side routing with 80+ route definitions.
   - **Vuex Store** -- centralised state across 21 namespaced modules.
   - **VueApexCharts** -- charting library for portfolio visualisations, gauges, and projections.

4. **Register the `v-preview-disabled` directive** -- a custom directive that disables buttons and form submissions when the user is viewing the app in preview mode (unauthenticated demo).

5. **Restore preview mode** -- dispatches `preview/initFromStorage` to check whether the current session belongs to a preview user, allowing preview mode to survive page reloads.

6. **Initialise session lifecycle** -- calls `initSessionLifecycle(store, router)` which monitors browser/tab close events and inactivity timeouts, triggering logout when appropriate.

7. **Mount** -- attaches the app to the `#app` DOM element.

---

## 5.2 Build Configuration

**File:** `vite.config.js`

Fynla uses Vite with the Laravel Vite Plugin and `@vitejs/plugin-vue`.

| Setting | Value |
|---------|-------|
| Base path | Configurable via `VITE_BASE_PATH`. Development: `/`. Production root deployment: `/build/`. Subdirectory deployment: `/fynla/build/`. |
| Dev server | `127.0.0.1:5173`, strict port (fails if port is taken rather than incrementing) |
| Entry points | `resources/js/app.js` and `resources/css/app.css` |
| Path alias | `@` resolves to `resources/js` |
| Build output | `public/build/` with `manifest.json` at the build root |
| Refresh | Enabled -- Vite watches for file changes in development |

The build runs locally because the production server lacks sufficient memory for `npm run build`. Build scripts in `deploy/fynla-org/build.sh` and `deploy/csjones-fynla/build.sh` set the correct `VITE_BASE_PATH` for each deployment target before invoking Vite.

---

## 5.3 Router

**File:** `resources/js/router/index.js`

The router uses `createWebHistory` with a configurable base path (`VITE_ROUTER_BASE`). All route components load via dynamic imports (`() => import(...)`) for code splitting. The router defines 80+ routes across several categories.

### 5.3.1 Public Routes

| Path | Component | Purpose |
|------|-----------|---------|
| `/` | LandingPage | Marketing page with preview persona selector |
| `/calculators` | CalculatorsPage | Standalone financial calculators |
| `/learning-centre` | LearningCentre | Educational content |
| `/security` | SecurityPage | Security information |
| `/sitemap` | SitemapPage | Site map |

### 5.3.2 Auth Routes

These routes carry `meta: { requiresGuest: true }` -- authenticated users are redirected to the dashboard.

| Path | Component |
|------|-----------|
| `/login` | Login |
| `/register` | Register |

### 5.3.3 Authenticated Routes

All routes below require `meta: { requiresAuth: true }` unless otherwise noted.

**Core:**

| Path | Component |
|------|-----------|
| `/dashboard` | Dashboard |
| `/profile` | UserProfile |
| `/settings` | Settings |
| `/settings/security` | SecuritySettings |
| `/settings/privacy` | PrivacySettings |
| `/settings/assumptions` | AssumptionsSettings |
| `/valuable-info` | ValuableInfo |
| `/onboarding` (+ `:step` child) | OnboardingView |

**Net Worth Module** -- `/net-worth` is a parent route using `NetWorthDashboard` as the shell. It redirects to `/net-worth/wealth-summary` by default. Child routes:

| Child Path | Component |
|------------|-----------|
| `wealth-summary` | NetWorthWealthSummary |
| `retirement` | PensionList |
| `property` | PropertyList |
| `investments` | InvestmentList |
| `investment-detail` | InvestmentProjections |
| `tax-efficiency` | TaxEfficiencyDetail |
| `holdings-detail` | HoldingsDetail |
| `fees-detail` | FeesDetail |
| `strategy-detail` | StrategyDetail |
| `cash` | CashOverview |
| `business` | BusinessInterestsList |
| `chattels` | ChattelsList |
| `joint-history` | JointAccountHistory |

**Pension Detail** (standalone): `/pension/:type/:id` -- renders PensionDetail with dynamic type and ID params.

**Module Dashboards:**

| Path | Component |
|------|-----------|
| `/protection` | ProtectionDashboard |
| `/protection/policy/:policyType/:id` | PolicyDetail |
| `/protection-plan` | ComprehensiveProtectionPlan |
| `/savings` | SavingsDashboard |
| `/savings/account/:id` | SavingsAccountDetail |
| `/goals` | GoalsDashboard |
| `/risk-profile` | RiskProfilePage |
| `/risk-profile/levels` | RiskLevelsExplainedPage |
| `/risk-profile/factor/:factor` | RiskFactorDetailPage |
| `/estate` | EstateDashboard |
| `/estate-plan` | ComprehensiveEstatePlan |
| `/trusts` | TrustsDashboard |
| `/trusts/:id` | TrustDetailView |

**Planning:**

| Path | Component |
|------|-----------|
| `/actions` | ActionsDashboard |
| `/holistic-plan` | HolisticPlan |
| `/plans` | PlansDashboard |
| `/plans/investment-savings` | InvestmentSavingsPlan |

**Admin** (requires `requiresAuth` + `requiresAdmin`):

| Path | Component |
|------|-----------|
| `/uk-taxes` | UKTaxesDashboard |
| `/admin` | AdminPanel |

**Utility:**

| Path | Notes |
|------|-------|
| `/version` | Public, no auth required |
| `/help` | Public, no auth required |
| `/debug-env` | Admin only, blocked in production via `beforeEnter` guard |
| `/investment` | Redirect to `/net-worth/investments` |

### 5.3.4 Preview Routes

Preview routes mirror the authenticated routes but carry `meta: { public: true, previewMode: true }`. They load the same components. If an authenticated user navigates to a preview route, the router strips the `/preview` prefix and redirects to the authenticated version.

The preview dashboard (`/preview`) has a `beforeEnter` guard that reads the `?persona=` query parameter (defaulting to `young_family`) and dispatches `preview/loadPersona` to log in as the appropriate preview user.

Preview child routes exist for: `/preview/net-worth/*`, `/preview/protection`, `/preview/savings`, `/preview/goals`, `/preview/estate`, `/preview/profile`. Preview redirects exist for `/preview/investment` and `/preview/retirement`.

### 5.3.5 Navigation Guards

**`beforeEach`** runs on every navigation and enforces four rules:

1. **Preview routes**: if the user is authenticated, redirect from `/preview/...` to the equivalent authenticated path. If a `?persona=` query parameter is present, dispatch `preview/enterPreviewMode` to log in as that persona.
2. **Auth-required routes**: if not authenticated and not in preview mode, redirect to `/login`.
3. **Guest-only routes**: if authenticated (and not in preview mode), redirect away from `/login` and `/register` to `/dashboard`.
4. **Admin routes**: if the user lacks admin privileges, redirect to `/dashboard`.

**`afterEach`** maps the current route path to a module name (protection, savings, investment, retirement, estate, net_worth, or dashboard) and dispatches `infoGuide/fetchRequirements` for that module. This populates contextual help content based on the current page.

**Scroll behaviour**: restores saved scroll position on back/forward navigation. Smooth-scrolls to hash targets. Otherwise scrolls to the top of the page.

---

## 5.4 Vuex Store

**File:** `resources/js/store/index.js`

The store contains 21 namespaced modules. The store enables strict mode outside production to catch direct state mutations during development.

### 5.4.1 auth

Manages authentication tokens and user identity.

- **State:** `token`, `user`, `loading`, `error`
- **Getters:** `isAuthenticated` (checks token presence), `currentUser`, `isAdmin` (checks `is_admin` flag)
- **Actions:** `register`, `login`, `logout`, `fetchUser`, `fetchUserById`

Security principle: user data is never cached. Every `fetchUser` call hits the API. The token is stored in `sessionStorage` (with a legacy `localStorage` fallback for migration). On logout, the store dispatches `clearAuth` and resets related module states (`userProfile`, `netWorth`) to prevent data leakage between sessions.

Both `register` and `login` clear all existing auth state before proceeding. If the previous session was a preview user, storage tokens are explicitly removed. After receiving a token, both actions call `fetchUser` to load user data fresh from the API rather than trusting the login/register response payload.

### 5.4.2 preview

Manages the preview (demo) mode where unauthenticated visitors explore the app using seeded test personas.

- **State:** `loading`, `error`
- **Getters:** `isPreviewMode` (checks `rootState.auth.user.is_preview_user`), `currentPersonaId`, `currentPersona`, `availablePersonas`, `effectivePersonaData`, `hasSpouse`, `isViewingAsSpouse`, `toggleTargetName`, `currentViewerName`, `basePersonaId`, `spouseFirstName`, `primaryFirstName`

Six personas are available: `young_family` (James & Emily Carter), `peak_earners` (David & Sarah Mitchell), `widow` (Margaret Thompson), `entrepreneur` (Alex Chen), `young_saver` (John Morgan), `retired_couple` (Robert & Patricia Williams). Full persona data is imported from JSON files under `resources/js/data/personas/`.

**Key actions:**

- `enterPreviewMode(personaId)` -- clears all auth and module state, calls `POST /preview/login/{personaId}`, stores the returned Sanctum token, and reloads the page to ensure fresh state.
- `switchPersona(personaId)` -- calls `POST /preview/switch/{personaId}` and reloads the page.
- `toggleSpouseView` -- switches between primary and spouse views for personas that have spouse accounts (young_family, peak_earners, retired_couple).
- `exitPreview` -- calls `POST /preview/exit`, clears tokens, and redirects to the landing page.

### 5.4.3 dashboard

- **State:** `overviewData`, `financialHealthScore`, `alerts`, `loading`, `error`
- **Getters:** `criticalAlerts`, `importantAlerts`, `infoAlerts`, `totalAlerts`
- **Actions:** `fetchDashboardData`, `fetchFinancialHealthScore`, `fetchAlerts`, `dismissAlert`, `fetchAllDashboardData` (calls all three fetches in parallel with graceful error handling -- partial failures set individual error messages rather than failing entirely)

### 5.4.4 netWorth

- **State:** `overview` (totalAssets, totalLiabilities, netWorth, breakdown), `spouseOverview`, `trend`, `assetsSummary`, `assetsSummaryDetailed` (includes individual asset items), `jointAssets`, `properties`, `mortgages`
- **Actions:** `fetchOverview`, `fetchSpouseOverview`, `fetchTrend`, `fetchAssetsSummary`, `fetchAssetsSummaryDetailed`, property CRUD (`createProperty`, `updateProperty`, `deleteProperty`), `resetState`

### 5.4.5 investment

The largest store module at over 1,300 lines.

- **State:** `accounts`, `goals`, `riskProfile`, `analysis`, `recommendations`, `monteCarloResults` (keyed by job ID), `monteCarloStatus` (keyed by job ID), `optimizationResult`, `scenarios`, `portfolioProjections`, `feeAnalysis`, `selectedProjectionPeriod` (default: 10 years)
- **Key getters (40+):** `totalPortfolioValue` (applies joint ownership weighting), `ytdReturn`, `assetAllocation`, `totalFees`, `feeDragPercent`, `unrealisedGains`, `taxEfficiencyScore`, `diversificationScore`, `riskLevel`, `isaAccounts`, `totalISAValue`, `allHoldings`, `holdingsCount`
- **Key actions:** `fetchInvestmentData`, `analyzeInvestment`, `startMonteCarlo` (launches async job), `getMonteCarloResults` (polls for completion), account and holding CRUD, plus 40+ additional actions for analysis, optimisation, and scenario modelling

### 5.4.6 retirement

- **State:** `dcPensions`, `dbPensions`, `statePension`, `profile`, `analysis`, `recommendations`, `annualAllowance`, `projections`, `strategies`, `strategyImpact`, `requiredCapital`, `retirementIncome`, `incomeAccounts`, `incomeAllocations`, `includeSpouseAssets`, `customTargetIncome`, `activeTab`
- **Actions:** `fetchDCPensions`, `fetchDBPensions`, `fetchStatePension`, `analyzeRetirement`, DC/DB pension CRUD, `fetchRequiredCapital`, `fetchRetirementIncome`, `updateIncomeAllocation`

### 5.4.7 goals

- **State:** `goals`, `summary` (total_goals, on_track_count, total_target, total_current, overall_progress), `topGoals`, `byModule` (savings, investment, property, retirement), `bestStreak`, `lifeEvents`, `projectionData`, `chartView` (net_worth / cash_flow / asset_breakdown), `viewMode` (individual / household)
- **Getters:** `activeGoals`, `goalsForModule(module)`, `goalsOnTrack`, `goalsBehind`, `completedGoals`, `totalTargetAmount`, `goalsByPriority`, `activeLifeEvents`, `incomeEvents`, `expenseEvents`

### 5.4.8 estate

Over 600 lines. Handles IHT calculations, gifting, trusts, and probate readiness.

- **State:** `assets`, `investmentAccounts`, `liabilities`, `gifts`, `trusts`, `ihtProfile`, `netWorth`, `analysis`, `recommendations`, `secondDeathPlanning`
- **Key getters (20+):** `allAssets` (combines manual assets and investment accounts), `totalAssets`, `totalLiabilities`, `ihtLiability`, `giftsWithin7Years`, `assetsByType`, `ihtExemptAssets`, `probateReadiness` (0--100 score), `taxableEstate`

### 5.4.9 protection

- **State:** `profile`, `policies` (life, criticalIllness, incomeProtection, disability, sicknessIllness), `analysis`, `recommendations`
- **Getters:** `adequacyScore` (0--100), `totalMonthlyPremium`, `allPolicies`, `activePolicies`

### 5.4.10 savings

- **State:** `accounts`, `goals`, `expenditureProfile`, `isaAllowance`
- **Getters:** `totalSavings` (applies joint ownership weighting), `emergencyFundTotal`, `emergencyFundRunway`, `isaAllowanceRemaining`, `isaUsagePercent`, `monthlyExpenditure`

### 5.4.11 Other Modules

| Module | Purpose |
|--------|---------|
| `user` | Current user data getters (lightweight accessor over auth state) |
| `holistic` | Aggregated cross-module financial data for the holistic plan view |
| `userProfile` | Personal details, spouse data, family members, banking details, planning assumptions. Has a `resetState` mutation used during logout and persona switching. |
| `trusts` | Trust CRUD operations |
| `businessInterests` | Business interest CRUD operations |
| `chattels` | Chattel (valuable personal possessions) CRUD operations |
| `recommendations` | Cross-module aggregated recommendations |
| `spousePermission` | Spouse data sharing permission management |
| `onboarding` | Wizard step tracking and progress persistence |
| `guidance` | Help text and contextual guidance content |
| `infoGuide` | Module-specific info guides and data requirements, updated on each route change |

---

## 5.5 Components

The application contains 313 Vue single-file components under `resources/js/components/`, plus additional view components under `resources/js/views/`.

### 5.5.1 Distribution by Module

| Module | Count | Notable Components |
|--------|-------|--------------------|
| Investment | 53 | AccountForm, AccountStrategyCard, HoldingsTable, AssetAllocationChart, FeeBreakdown, FeeSavingsCalculator, MonteCarloResults, EfficientFrontier, PortfolioOptimizer, WhatIfScenariosBuilder, BedAndISAWizardModal, CGTHarvestingOpportunities, TaxOptimizationOverview |
| Estate | 30 | IHTLiabilityGauge, NRBRNRBTracker, AssetForm, GiftCard, GiftingTimelineChart, DualGiftingTimeline, TrustForm, WillPlanning, IHTCalculationTable, IHTMitigationStrategies |
| NetWorth | 27 | NetWorthWealthSummary, PropertyList, PropertyCard, PensionList, InvestmentList, BusinessInterestsList, ChattelsList, NetWorthTrendChart, AssetAllocationDonut |
| UserProfile | 26 | Personal details forms, spouse management, family member forms |
| Goals | 22 | Goal cards, projection charts, life event management |
| Retirement | 20 | Pension forms, projection charts, income allocation |
| Shared | 16 | Cross-cutting UI components |
| Protection | 15 | Policy forms, adequacy indicators, plan views |
| Dashboard | 15 | Overview cards, health score display, alert panels |
| Savings | 12 | Account forms, ISA tracking, emergency fund indicators |
| Risk | 6 | Risk profile questionnaire, factor details |
| Holistic | 6 | Aggregated plan views |
| Cash | 6 | Cash flow views |
| Auth | 6 | Login, register, verification forms |
| Preview | 5 | Persona selector, preview banner |
| Admin | 5 | Admin panel, tax management |
| Onboarding | 4 | Wizard steps |
| Common | 4 | Generic reusable elements |
| Trusts | 3 | Trust list, detail, form |

### 5.5.2 Component Patterns

**Form Modals** use a two-part event pattern to prevent double submission:

1. The form element uses `@submit.prevent="handleSubmit"` to prevent page reload.
2. The `handleSubmit` method validates the form and calls `this.$emit('save', formData)`. The event name is always `save`, never `submit`.
3. The parent component listens with `@save="handleAccountSave"`.

```vue
<!-- Inside the modal component -->
<form @submit.prevent="handleSubmit">
  <!-- fields -->
</form>

<script>
methods: {
  handleSubmit() {
    this.$emit('save', this.formData);
  }
}
</script>

<!-- Parent component -->
<AccountForm @save="handleAccountSave" @close="closeModal" />
```

**Data Cards** follow a consistent props/events interface:
- Props: `data` (the record), `editable` (boolean), `loading` (boolean)
- Events: `edit`, `delete`

**Store Integration** uses Vuex helpers: `mapGetters`, `mapState`, `mapActions`. Components never access the store directly via `$store.state` -- they always use mapped getters.

**Currency Formatting** always uses `currencyMixin`. Components import the mixin and access `this.formatCurrency()`, `this.formatCurrencyCompact()`, etc. Defining local `formatCurrency()` methods is prohibited to maintain consistency.

### 5.5.3 Layouts

| File | Purpose |
|------|---------|
| `resources/js/layouts/AppLayout.vue` | Main authenticated layout. Wraps all dashboard and module views. Includes the navbar, sidebar navigation, and content area. |
| `resources/js/layouts/PublicLayout.vue` | Public page layout. Responsive navbar with links to Home, Calculators, Learning Centre, Security, Login, and Get Started. Used for the landing page and marketing content. |
| `resources/js/components/Navbar.vue` | Top navigation bar. Shows user menu, module navigation links, breadcrumbs (from route meta), and a preview mode indicator when active. |

---

## 5.6 Services

The `resources/js/services/` directory contains 35 service files that wrap API calls and provide business logic helpers.

### 5.6.1 api.js -- Core HTTP Client

The central Axios instance that all other services use.

**Base URL detection:** checks `window.location.hostname` -- if localhost or 127.0.0.1, uses `http://{hostname}:8000/api`. Otherwise uses the `VITE_API_BASE_URL` environment variable or falls back to `http://localhost:8000/api`.

**Request interceptor:** reads the auth token from `sessionStorage` (with `localStorage` fallback) and attaches it as a `Bearer` token in the `Authorization` header.

**Response interceptor (error handling):**
- **401 Unauthorized:** clears tokens from both storage types and redirects to `/login`. Exceptions: auth endpoints (login/register) return the error to the component. Preview mode silently rejects without redirect.
- **422 Validation:** logs as info (not error), rejects with structured `{ message, errors, status }` for form-level display.
- **Other errors:** rejects with structured error object.
- **Network errors:** rejects with a generic network error message.

**Retry interceptor:** a second response interceptor handles transient failures with exponential backoff:
- Retries on 5xx server errors, network failures, and 429 rate limiting.
- Maximum 3 retries with base delay of 1 second (2 seconds for 429), max delay of 10 seconds.
- Only retries idempotent methods (GET, HEAD, OPTIONS, PUT, DELETE). POST requests are not retried to avoid duplicate side effects.
- Adds random jitter (0--30%) to prevent thundering herd.

### 5.6.2 authService.js

Handles registration, login, logout, user retrieval, token management, verification code flow, and password reset.

- `register(userData)` -- clears existing auth, posts to `/auth/register`, stores token.
- `login(credentials)` -- clears existing auth, posts to `/auth/login`, stores token.
- `logout()` -- posts to `/auth/logout`, clears all stored auth data.
- `getUser()` -- fetches from `/auth/user`. Never caches the response.
- `setToken(token)` / `getToken()` / `clearAuth()` -- manage `sessionStorage` token.
- `verifyCode(code)`, `resendCode()` -- two-factor verification flow.
- Password reset flow: `forgotPassword(email)`, `resetPassword(data)`.

### 5.6.3 Module Services

| Service | Scope |
|---------|-------|
| `investmentService.js` | Largest service at 1,500+ lines. Full investment API: accounts, holdings, analysis, Monte Carlo simulation, rebalancing, tax optimisation, efficient frontier, model portfolios, plans. |
| `retirementService.js` | DC/DB pension CRUD, state pension queries, retirement projections, required capital, income configuration. |
| `savingsService.js` | Savings account CRUD, ISA allowance queries, emergency fund analysis. |
| `protectionService.js` | Protection policy CRUD, adequacy analysis. |
| `estateService.js` | Estate asset management, IHT calculation, second death planning, probate readiness. |
| `goalsService.js` | Goals CRUD, projections, life events. |
| `dashboardService.js` | Dashboard overview, financial health score, alerts. `fetchAllDashboardData` calls all three endpoints in parallel. |
| `netWorthService.js` | Net worth aggregation across all asset types. |
| `userProfileService.js` | Personal details, spouse data, family members, banking, planning assumptions. |

### 5.6.4 Specialised Services

| Service | Purpose |
|---------|---------|
| `holisticService.js` | Cross-module aggregated financial data. |
| `rebalancingService.js` | Portfolio rebalancing recommendations. |
| `riskService.js` | Risk profile questionnaire and scoring. |
| `portfolioOptimizationService.js` | Efficient frontier, optimisation targets. |
| `diversificationService.js` | Portfolio diversification analysis. |
| `dcPensionHoldingsService.js` | Holdings within DC pension accounts. |
| `documentService.js` | File uploads (documents attached to records). |
| `postcodeService.js` | UK address lookup from postcode. |
| `occupationService.js` | SOC (Standard Occupational Classification) search for protection underwriting. |
| `consoleCapture.js` | Captures `console.error` and `console.warn` calls for error reporting. Started before the Vue app mounts. |
| `sessionLifecycleService.js` | Monitors browser/tab close and inactivity. Triggers logout on timeout or close. |
| `bugReportService.js` | Submits bug reports with captured console logs. |
| `adminService.js` | Admin panel operations. |
| `assumptionsService.js` | User planning assumptions (inflation rate, growth rates). |
| `propertyService.js` | Property CRUD. |
| `mortgageService.js` | Mortgage CRUD. |
| `businessInterestService.js` | Business interest CRUD. |
| `chattelService.js` | Chattel CRUD. |
| `familyMembersService.js` | Family member (dependant) management. |
| `spousePermissionService.js` | Spouse data sharing permissions. |
| `taxInfoService.js` | Tax information display data. |
| `taxSettingsService.js` | Admin tax configuration. |
| `plansService.js` | Financial plan management. |
| `onboardingService.js` | Onboarding wizard progress. |
| `guidanceService.js` | Contextual help content retrieval. |

---

## 5.7 Mixins

### 5.7.1 currencyMixin.js

The single source of truth for currency and type formatting across all Vue components. Wraps standalone functions from `utils/currency.js` into Vue mixin methods.

| Method | Output Example | Description |
|--------|----------------|-------------|
| `formatCurrency(value)` | £1,234 | GBP, no decimals |
| `formatCurrencyWithPence(value)` | £1,234.56 | GBP, 2 decimal places |
| `formatCurrencyCompact(value)` | £1.2M | Compact for large values |
| `parseCurrency(string)` | 1234.56 | Parse formatted string to number |
| `formatPercentage(value, options)` | 5.00% | Percentage with configurable decimals |
| `formatAccountType(type)` | S&S ISA | Maps codes like `stocks_shares_isa` to display names |
| `formatOwnershipType(type)` | Tenants in Common | Maps `tenants_in_common` to display name |
| `formatSavingsAccountType(type)` | Easy Access | Maps savings account type codes |
| `formatPropertyType(type)` | Buy to Let | Maps property type codes |
| `formatMortgageType(type)` | Interest Only | Maps mortgage type codes |
| `formatNumber(value)` | 1,234 | Number with commas, no currency symbol |
| `formatLiability(value)` | -£1,234 | Negative-prefixed currency |

Usage in components:

```javascript
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  mixins: [currencyMixin],
  // this.formatCurrency() is now available in templates and methods
}
```

### 5.7.2 previewModeMixin.js

Provides preview mode state checks and utility methods to components that need to conditionally alter behaviour in preview mode.

---

## 5.8 Utilities

Seven utility files under `resources/js/utils/`:

### 5.8.1 currency.js

Standalone formatting functions (not Vue-specific) that `currencyMixin` wraps. Can be imported directly in services or computed properties where the mixin pattern does not apply.

### 5.8.2 dateFormatter.js

UK-format date handling:

| Function | Input | Output |
|----------|-------|--------|
| `formatDate(date)` | Date or string | `15/01/2025` (DD/MM/YYYY) |
| `formatDateForInput(date)` | Date or string | `2025-01-15` (YYYY-MM-DD for HTML inputs) |
| `parseDate(dateString)` | `15/01/2025` or `2025-01-15` | `Date` object or `null` |
| `formatDateLong(date, shortMonth)` | Date, boolean | `15 January 2025` or `15 Jan 2025` |
| `calculateAge(dob, asOfDate)` | Date, Date | Number (years) |
| `getRelativeTime(date)` | Date | `yesterday`, `in 3 months`, `2 years ago` |

### 5.8.3 poller.js

Generic polling mechanism with exponential backoff. Used primarily for Monte Carlo simulation results, where the backend runs an async job and the frontend polls until completion.

- `poll(fetchFunction, options)` -- polls the given async function at a configurable interval (default: 2 seconds) for up to a maximum number of attempts (default: 60). Accepts a `shouldContinue` predicate and an `onProgress` callback.
- `pollMonteCarloJob(jobId)` -- specialised wrapper that polls `investmentService.getMonteCarloResults(jobId)` and resolves when the job status is no longer `running`.

### 5.8.4 ownership.js

Joint ownership calculation helpers. Computes ownership multipliers based on whether the querying user is the primary owner or the joint owner, applying the stored `ownership_percentage`.

### 5.8.5 dates.js

Tax year and retirement date calculations. Determines which UK tax year a given date falls in (April 6 to April 5) and computes years to retirement.

### 5.8.6 logger.js

Structured logging utility with severity levels. Used for development debugging and error tracking.

### 5.8.7 asyncAction.js

Debouncing and action status tracking. Prevents duplicate API calls when users trigger the same action multiple times in quick succession.

---

## 5.9 Directives

### v-preview-disabled

**File:** `resources/js/directives/previewDisabled.js`

A custom Vue directive that disables interactive elements when the app is in preview mode.

**Behaviour when preview mode is active:**
- Sets the `disabled` HTML attribute on the element.
- Adds the `preview-disabled` CSS class.
- Changes the cursor to `not-allowed` and reduces opacity to 0.6.
- Captures and blocks all click events (using `stopImmediatePropagation` on the capture phase).
- Shows a tooltip on hover with a contextual message like "Register to add data" or "Register to edit data".

**Usage:**

```vue
<button v-preview-disabled>Add Policy</button>
<button v-preview-disabled="'edit'">Edit</button>
<button v-preview-disabled="'upload'">Upload Document</button>
```

The directive value sets the tooltip action type: `add`, `edit`, `delete`, `upload`, `save`, or `default`. Each maps to a specific message encouraging the user to register.

The directive watches the Vuex store for changes to `preview/isPreviewMode` and updates the element state reactively. When the user exits preview mode (by registering or logging in), all `v-preview-disabled` elements automatically re-enable without a page reload.

**Implementation:** uses a shared tooltip DOM element positioned with `position: fixed`, styled with a dark background and arrow pointer. The tooltip appears immediately on hover (no delay) and disappears on mouse leave.

---

## 5.10 Data Files

**Directory:** `resources/js/data/personas/`

Six JSON files define the complete data sets for preview mode personas:

| File | Persona | Users |
|------|---------|-------|
| `young_family.json` | Young family | James & Emily Carter |
| `peak_earners.json` | Peak earners | David & Sarah Mitchell |
| `widow.json` | Widow | Margaret Thompson |
| `entrepreneur.json` | Entrepreneur | Alex Chen |
| `young_saver.json` | Young saver | John Morgan |
| `retired_couple.json` | Retired couple | Robert & Patricia Williams |

Each JSON file contains:
- **Persona metadata:** id, name, tagline, description, net worth range, focus areas
- **User details:** personal information (name, date of birth, employment, income)
- **Spouse details:** where applicable (young_family, peak_earners, retired_couple)
- **Assets:** properties, investments, savings accounts, pensions, business interests, chattels
- **Liabilities:** mortgages, loans
- **Goals:** financial targets with progress
- **Life events:** planned future events (retirement, property purchase, education costs)
- **Risk profile:** investment risk tolerance assessment
- **Assumptions:** inflation rate, growth expectations, retirement age

These JSON files are the single source of truth for persona display data in the frontend. The preview store imports them directly and uses the metadata for the persona selector UI. The actual financial data is loaded from the database via normal API calls -- the JSON files serve as documentation of what each persona represents and provide metadata for the landing page persona cards.
