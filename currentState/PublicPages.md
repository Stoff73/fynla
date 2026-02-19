# Public Pages, Settings & Utility Views

> Module documentation for all public-facing marketing pages, settings views, and utility views in Fynla.

---

## 1. System Overview

### Purpose
The public pages serve as Fynla's marketing and informational front-end, providing unauthenticated visitors with product information, educational content, interactive calculators, and entry points into the application (registration, demo access). The settings views allow authenticated users to manage their account, security, privacy, and planning assumptions. Utility views provide version history, help documentation, environment debugging, and consolidated "valuable info" access.

### Page Categories

| Category | Layout | Auth Required | Pages |
|----------|--------|---------------|-------|
| Public Marketing | `PublicLayout` | No | Landing, Calculators, Learning Centre, Security, About, Pricing, Sitemap |
| Guest Only | `PublicLayout` | Must NOT be logged in | Login, Register |
| Settings | `AppLayout` | Yes | Settings Hub, Security, Privacy, Assumptions |
| Utility | `AppLayout` | Yes | Help, Version, Valuable Info |
| Debug | None (bare) | Yes (implied) | DebugEnv |

### Routing Architecture

All routes are defined in `/Users/Chris/Desktop/fynla/resources/js/router/index.js`. Components are lazy-loaded via dynamic imports.

**Route meta flags:**
- `meta: { public: true }` -- accessible without authentication
- `meta: { requiresGuest: true }` -- redirects authenticated users away (login, register)
- `meta: { requiresAuth: true }` -- requires authentication (default for most routes)
- `meta: { breadcrumb: [...] }` -- breadcrumb trail for nested settings routes
- `meta: { hideNavbar: true }` -- hides the navigation bar (onboarding)

**Public routes:**

| Path | Component | Meta |
|------|-----------|------|
| `/` | `LandingPage.vue` | `{ public: true }` |
| `/calculators` | `CalculatorsPage.vue` | `{ public: true }` |
| `/learning-centre` | `LearningCentre.vue` | `{ public: true }` |
| `/security` | `SecurityPage.vue` | `{ public: true }` |
| `/about` | `AboutPage.vue` | `{ public: true }` |
| `/pricing` | `PricingPage.vue` | `{ public: true }` |
| `/sitemap` | `SitemapPage.vue` | `{ public: true }` |

**Settings routes:**

| Path | Component | Breadcrumb |
|------|-----------|------------|
| `/settings` | `Settings.vue` | None (root) |
| `/settings/security` | `SecuritySettings.vue` | Settings > Security |
| `/settings/privacy` | `PrivacySettings.vue` | Settings > Privacy & Data |
| `/settings/assumptions` | `AssumptionsSettings.vue` | Settings > Planning Assumptions |

**Utility routes:**

| Path | Component | Notes |
|------|-----------|-------|
| `/help` | `Help.vue` | Authenticated |
| `/version` | `Version.vue` | Authenticated |
| `/valuable-info` | `ValuableInfo.vue` | Authenticated, breadcrumb |
| `/debug-env` | `DebugEnv.vue` | No layout wrapper |

---

## 2. Public Marketing Pages

### 2.1 Landing Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/LandingPage.vue`
**Route:** `/`
**Layout:** `PublicLayout`
**Lines:** 474

#### Component Structure

```
LandingPage
  +-- PublicLayout (wrapper)
  +-- Hero Section
  |     +-- Animated gradient background with floating orbs
  |     +-- "Interactive Demo - No Sign-up Required" badge
  |     +-- Two CTAs: "Try the Demo" / "Get Started Free"
  |     +-- "Already have an account? Sign In" link
  |     +-- "Join the Waitlist" external link (Google Forms)
  +-- Stats Cards Row
  +-- Dashboard Preview Section (GIF)
  +-- Planning Modules Grid (6 cards)
  +-- Key Features Section (3 cards)
  +-- Bottom CTA Section
  +-- PersonaSelectionModal (child component)
```

#### Hero Section
- Dark gradient background (`bg-gradient-to-br from-gray-900 via-primary-950 to-gray-900`)
- Three animated floating orbs using CSS `@keyframes float` with staggered delays
- Primary badge: "Interactive Demo - No Sign-up Required" with sparkle icon
- Both CTAs call `enterPreviewMode()` which opens the persona selection modal
- Sign In link navigates to `/login`
- Waitlist link opens external Google Forms URL in new tab

#### Stats Cards
Four stat cards displayed in a responsive grid:

| Stat | Value | Description |
|------|-------|-------------|
| 5 | Planning Modules | Comprehensive financial planning |
| UK Tax Optimised | -- | Based on current UK tax rules |
| 100% | Secure & Private | Bank-level encryption |
| Free | Demo Access | No sign-up required |

#### Dashboard Preview
- Shows a GIF of the dashboard at `/images/dashboard/fynla-dashboard-preview.gif`
- Wrapped in a rounded card with shadow and gradient border effect
- "See Fynla in Action" heading with descriptive text

#### Planning Modules Grid
Six module cards, each with an icon, title, and description:

| Module | Icon | Description |
|--------|------|-------------|
| Protection | ShieldCheckIcon | Life insurance, income protection, critical illness |
| Savings | BanknotesIcon | Emergency funds, ISAs, savings goals |
| Investment | ChartBarIcon | Portfolio analysis and growth tracking |
| Retirement | ClockIcon | Pension planning and retirement projections |
| Estate | HomeIcon | Will planning, IHT, and legacy |
| Net Worth | ScaleIcon | Complete financial overview and tracking |

#### Key Features Section
Three feature cards:

| Feature | Description |
|---------|-------------|
| Spouse Linking | Link accounts with your partner for joint planning |
| UK Tax Optimised | Calculations based on current UK tax rules |
| Letter to Spouse | Leave important financial information for your partner |

#### Preview Mode Integration
- **Vuex store:** `preview` module
- **Methods:**
  - `enterPreviewMode()` -- sets `showPersonaModal = true`
  - `handlePersonaSelect(persona)` -- dispatches `preview/loadPersona`, then navigates to `/dashboard`
  - `checkDemoParam()` -- watches for `?demo=true` query parameter; if present, auto-opens the persona modal
- **PersonaSelectionModal** component receives `availablePersonas` from Vuex getter
- **Lifecycle:** `onMounted` calls `checkDemoParam()`

#### Imports
- `PersonaSelectionModal` from `@/components/Preview/PersonaSelectionModal.vue`
- Vuex mapActions/mapGetters for `preview` store
- Heroicons: `ShieldCheckIcon`, `BanknotesIcon`, `ChartBarIcon`, `ClockIcon`, `HomeIcon`, `ScaleIcon`, `SparklesIcon`, `ArrowRightIcon`

---

### 2.2 Calculators Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/CalculatorsPage.vue`
**Route:** `/calculators`
**Layout:** `PublicLayout`
**Lines:** 959

#### Component Structure

```
CalculatorsPage
  +-- PublicLayout (wrapper)
  +-- Hero with tab-based calculator selector
  +-- Calculator Content (conditional rendering)
  |     +-- Income Tax Calculator
  |     +-- Mortgage Calculator
  |     +-- Loan Calculator
  |     +-- Emergency Fund Calculator
  |     +-- Pension Growth Calculator
  +-- CTA Section
```

#### Calculator Selection
- Tab-based navigation in the hero section
- Five tabs with icons, one active at a time
- `activeCalculator` ref controls which calculator renders
- All calculations are performed entirely client-side (no API calls)

#### Income Tax Calculator (2025/26 UK Tax Year)

**Inputs:**
- Annual salary (number)
- Pension contribution percentage (slider 0-40%, step 1%)

**Tax bands applied:**

| Band | Rate | Threshold |
|------|------|-----------|
| Personal Allowance | 0% | Up to 12,570 |
| Basic Rate | 20% | 12,571 - 50,270 |
| Higher Rate | 40% | 50,271 - 125,140 |
| Additional Rate | 45% | Above 125,140 |

**National Insurance rates:**
- 12% between 12,570 and 50,270
- 2% above 50,270

**Outputs:** Tax breakdown by band, NI amount, pension deduction, total deductions, take-home pay (monthly and annual), effective tax rate.

**Method:** `calculateIncomeTax()` -- applies pension deduction to gross salary, then calculates tax across bands sequentially.

#### Mortgage Calculator

**Inputs:**
- Annual income, partner income (optional)
- Monthly expenses
- Deposit amount
- Interest rate (default 5.25%)
- Mortgage term (years, default 25)

**Calculations:**
- Maximum borrowing: `(annualIncome + partnerIncome) * 4.5`
- Affordable property: `maxBorrow + deposit`
- Loan-to-Value (LTV): `((propertyValue - deposit) / propertyValue) * 100`
- Monthly payment: standard amortisation formula `P * [r(1+r)^n] / [(1+r)^n - 1]`
- Total repayment and total interest

**Method:** `calculateMortgage()` with `calculateMonthlyPayment(principal, annualRate, years)` helper.

#### Loan Calculator

**Inputs:**
- Loan amount
- Interest rate (annual)
- Loan term (years)

**Outputs:** Monthly payment, total repayment, total interest paid.

**Method:** `calculateLoan()` using the standard amortisation formula.

#### Emergency Fund Calculator

**Inputs:**
- Monthly essential expenses
- Current emergency savings

**Outputs:**
- Target amounts at 3, 6, 9, and 12 months
- Months of coverage (current savings / monthly expenses)
- Adequacy rating with colour coding

**Adequacy scoring:**

| Rating | Condition | Colour |
|--------|-----------|--------|
| Good | >= 6 months | Green |
| Adequate | >= 3 months | Blue |
| Low | < 3 months | Red |

**Method:** `calculateEmergencyFund()`

#### Pension Growth Calculator

**Inputs:**
- Current pension value
- Monthly contribution
- Years to retirement
- Expected annual growth rate (default 5%)

**Outputs:**
- Future pension value (compound growth formula)
- Estimated annual retirement income (4% withdrawal rate)
- Total contributions made
- Investment growth (future value minus total contributions minus current value)

**Formula:** Future Value = `currentValue * (1 + monthlyRate)^totalMonths + monthlyContribution * [((1 + monthlyRate)^totalMonths - 1) / monthlyRate]`

**Method:** `calculatePensionGrowth()`

#### Mixins
- `currencyMixin` for all monetary formatting

#### CTA Section
- Links to `/register` ("Start Your Free Plan") and `/login` ("Sign In")

---

### 2.3 Learning Centre

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/LearningCentre.vue`
**Route:** `/learning-centre`
**Layout:** `PublicLayout`
**Lines:** 993

#### Component Structure

```
LearningCentre
  +-- PublicLayout (wrapper)
  +-- Hero with category tab navigation
  +-- Category Content (conditional rendering)
  |     +-- Financial Planning Basics
  |     +-- Protection Planning
  |     +-- Retirement Planning
  |     +-- Estate Planning
  |     +-- Investment Planning
  |     +-- Tax Planning
  +-- CTA Section
```

#### Categories and Content

**6 educational categories** with tab-based navigation:

| Category ID | Label | Icon |
|-------------|-------|------|
| `basics` | Financial Planning | AcademicCapIcon |
| `protection` | Protection | ShieldCheckIcon |
| `retirement` | Retirement | ClockIcon |
| `estate` | Estate | HomeIcon |
| `investment` | Investment | ChartBarIcon |
| `tax` | Tax | CalculatorIcon |

**Content summaries by category:**

1. **Financial Planning Basics** -- Introduction to the six key areas (Protection, Savings, Investment, Retirement, Estate, Tax). Explains why financial planning matters and how Fynla helps.

2. **Protection Planning** -- Types of cover: Life Insurance, Critical Illness, Income Protection. Explains when and why each is needed, and how Fynla's Protection module analyses gaps.

3. **Retirement Planning** -- UK pension system overview: State Pension, Workplace Pensions, Personal Pensions (SIPPs). Covers pension tax relief, annual allowance, lifetime considerations.

4. **Estate Planning** -- IHT basics: 40% rate above nil-rate band (325,000), residence nil-rate band (175,000). Covers Wills, trusts, gifting strategies, and how Fynla calculates potential IHT liability.

5. **Investment Planning** -- Risk vs. return principles, asset classes, diversification. ISA allowance (20,000/year), General Investment Accounts, fund types.

6. **Tax Planning** -- UK income tax bands and rates, National Insurance, tax-efficient wrappers. Includes detailed Section 24 mortgage interest tax relief guide for landlords (finance cost restriction, basic rate tax credit calculation).

#### CTA Section
- "Start Planning Today" link to `/register`

---

### 2.4 Security Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/SecurityPage.vue`
**Route:** `/security`
**Layout:** `PublicLayout`
**Lines:** 517

#### Component Structure

```
SecurityPage
  +-- PublicLayout (wrapper)
  +-- Hero with 3 security highlight cards
  +-- Detailed Security Sections (7 sections)
  +-- Important Notice Disclaimer
  +-- Contact Section
```

#### Hero Security Cards

| Card | Icon | Detail |
|------|------|--------|
| Encrypted Connections | LockClosedIcon | TLS 1.3 encryption |
| UK Data Residency | ServerIcon | UK-based data centres |
| Your Data, Your Control | UserIcon | Full data ownership |

#### Security Sections (7)

1. **Authentication & Account Security**
   - Multi-factor authentication (TOTP)
   - Session management with device tracking
   - Brute-force protection (rate limiting)
   - Password security (hashing, complexity requirements)

2. **Data Protection & Encryption**
   - AES-256 encryption at rest
   - Key management and rotation
   - Encrypted backups
   - Secrets management (environment variables, not code)

3. **Access Control**
   - Role-based access control (RBAC)
   - Principle of least privilege
   - Internal access logging

4. **Auditability & Monitoring**
   - Comprehensive audit logs
   - Immutable log entries
   - Suspicious activity alerts

5. **GDPR & Privacy Compliance**
   - Right to erasure (data deletion)
   - Data minimisation
   - Consent tracking
   - Data export (JSON/CSV)

6. **API & Application Security**
   - Rate limiting on all endpoints
   - Token-based authentication (Laravel Sanctum)
   - Permission scoping

7. **Business Continuity**
   - Disaster recovery procedures
   - Uptime monitoring

#### Important Notice
Disclaimer stating Fynla is not regulated financial advice and users should consult qualified advisers.

#### Contact
- Email: info@fynla.org

---

### 2.5 About Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/AboutPage.vue`
**Route:** `/about`
**Layout:** `PublicLayout`
**Lines:** 143

#### Component Structure

```
AboutPage
  +-- PublicLayout (wrapper)
  +-- Hero ("About Fynla")
  +-- "Why We Built This" narrative section
  +-- Founder Profiles (2 cards)
  +-- "About Fynla" summary section
```

#### Founder Profiles

| Name | Title | Credentials | Portrait |
|------|-------|-------------|----------|
| Chris Slater-Jones | Co-Founder | DipPFS, 20+ years financial services | `/images/portraits/csj.png` |
| Brett Isenberg | Co-Founder | FCA (Fellow ICAEW), former Big 4 | `/images/portraits/brett.png` |

#### Sections
- **Why We Built This** -- Narrative explaining the motivation for creating Fynla
- **Founder cards** -- Side-by-side cards with portrait images, name, credentials, and bio
- **About Fynla** -- Summary of the platform's mission and approach

---

### 2.6 Pricing Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/PricingPage.vue`
**Route:** `/pricing`
**Layout:** `PublicLayout`
**Lines:** 268

#### Component Structure

```
PricingPage
  +-- PublicLayout (wrapper)
  +-- Hero with billing toggle
  +-- Pricing Cards Grid (3 plans)
```

#### Billing Toggle
- Monthly / Yearly toggle switch
- Default: `isYearly: true`
- Yearly prices shown with percentage savings badge

#### Pricing Plans

| Plan | Monthly | Yearly | Savings | Badge |
|------|---------|--------|---------|-------|
| Student | 3.99 | 30/yr | 37% | -- |
| Standard | 10.99 | 100/yr | 24% | "Most Popular" |
| Pro | 19.99 | 200/yr | 17% | -- |

All plans include a "7-day free trial" badge.

**Feature comparison:**

| Feature | Student | Standard | Pro |
|---------|---------|----------|-----|
| Budgeting tools | Yes | Yes | Yes |
| Debt tracking | Yes | Yes | Yes |
| Basic investment tracking | Yes | Yes | Yes |
| Goal setting | Yes | Yes | Yes |
| All platform capabilities | -- | Yes | Yes |
| Protection/Savings/Investments | -- | Yes | Yes |
| Retirement & Estate | -- | Yes | Yes |
| Spouse linking | -- | Yes | Yes |
| Document uploads | -- | 1/day, 5/month max | Unlimited |
| Priority support | -- | -- | Yes |

#### CTA Behaviour
- `startTrial(plan)` navigates to `/register` with query parameters: `?plan={planName}&billing={monthly|yearly}`
- Each plan card has a "Start Free Trial" button

---

### 2.7 Sitemap Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Public/SitemapPage.vue`
**Route:** `/sitemap`
**Layout:** `PublicLayout`
**Lines:** 198

#### Component Structure

```
SitemapPage
  +-- PublicLayout (wrapper)
  +-- Hero ("Sitemap")
  +-- 4-column grid of link sections
  +-- XML sitemap reference
```

**Note:** Uses `bg-gray-50` background, unlike the dark theme of other public pages.

#### Link Sections

| Section | Links |
|---------|-------|
| Main Pages | Home (`/`), Calculators (`/calculators`), Learning Centre (`/learning-centre`), Security (`/security`), Version History (`/version`), Help (`/help`) |
| Account | Log In (`/login`), Register (`/register`), Forgot Password (`/forgot-password`) |
| Planning Modules | Dashboard, Net Worth, Protection, Savings, Investment, Retirement, Estate (text only -- requires login) |
| Calculators | Income Tax, Retirement, IHT, CGT, Pension Contribution (all link to `/calculators`) |

#### XML Sitemap
- Links to `/sitemap.xml` for search engine crawlers

---

## 3. Settings Views

### 3.1 Settings Hub

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Settings.vue`
**Route:** `/settings`
**Layout:** `AppLayout`
**Lines:** 216
**API:** Composition API with `setup()`

#### Component Structure

```
Settings
  +-- AppLayout (wrapper)
  +-- User Info Card
  |     +-- Name, Email, DOB, Gender, Marital Status, Account Created
  +-- Account Actions List
        +-- Security Settings (router-link)
        +-- Privacy & Data (router-link)
        +-- Planning Assumptions (router-link)
        +-- Email Notifications (disabled, "Coming Soon")
        +-- Sign Out (action)
```

#### User Info Display

| Field | Source |
|-------|--------|
| Name | `user.name` |
| Email | `user.email` |
| Date of Birth | `user.date_of_birth` (formatted) |
| Gender | `user.gender` |
| Marital Status | `user.marital_status` |
| Account Created | `user.created_at` (formatted) |

#### Account Actions

| Action | Route | Status |
|--------|-------|--------|
| Security Settings | `/settings/security` | Active |
| Privacy & Data | `/settings/privacy` | Active |
| Planning Assumptions | `/settings/assumptions` | Active |
| Email Notifications | -- | Disabled ("Coming Soon") |
| Sign Out | -- | Action button |

#### Sign Out
- `handleSignOut()` dispatches `auth/logout` Vuex action
- Navigates to `/login` after logout

#### Vuex Integration
- Store: `auth`
- Getter: `auth/user` (computed)

---

### 3.2 Security Settings

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Settings/SecuritySettings.vue`
**Route:** `/settings/security`
**Layout:** `AppLayout`
**Lines:** 558
**API:** Options API

#### Component Structure

```
SecuritySettings
  +-- AppLayout (wrapper)
  +-- Breadcrumb (Settings > Security)
  +-- Two-Factor Authentication Section
  |     +-- Status display (enabled/disabled)
  |     +-- Enable/Disable toggle
  |     +-- MFASetupModal (child component)
  +-- Active Sessions Section
  |     +-- Session list (device, IP, last active)
  |     +-- Revoke individual session
  |     +-- Revoke all other sessions
  +-- Change Password Section
  |     +-- Password change modal
  |     +-- Validation rules display
  +-- Security Tips Section
```

#### Two-Factor Authentication

**API endpoints:**
- `GET /auth/mfa/status` -- check current MFA status
- `POST /auth/mfa/disable` -- disable MFA

**Child component:** `MFASetupModal` from `@/components/Auth/MFASetupModal.vue`

**Flow:**
1. Check MFA status on mount
2. If disabled: show "Enable" button, opens `MFASetupModal`
3. If enabled: show "Disable" button with confirmation
4. MFASetupModal handles the TOTP setup flow (QR code, verification)

#### Active Sessions

**API endpoints:**
- `GET /auth/sessions` -- list all active sessions
- `DELETE /auth/sessions/{id}` -- revoke specific session
- `DELETE /auth/sessions/others/all` -- revoke all sessions except current

**Session data displayed:**
- Device/browser information
- IP address
- Last active timestamp
- Current session indicator

#### Password Change

**API endpoint:** `POST /auth/change-password`

Password complexity rules are enforced (see **auth.md Section 13** for details).

**Fields:** Current password, New password, Confirm new password

#### Security Tips
Static informational section with best practice advice for account security.

#### Scoped Styles
Custom card and modal styling via scoped CSS.

---

### 3.3 Privacy Settings
**Route:** `/settings/privacy` | **Component:** `Settings/PrivacySettings.vue`

Manages GDPR rights: consent preferences, data export (JSON/CSV), and account deletion (3-step wizard with email verification code). See **GDPR.md Section 10** for the full component documentation including all API endpoints, data flows, and deletion wizard states.

---

### 3.4 Assumptions Settings

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Settings/AssumptionsSettings.vue`
**Route:** `/settings/assumptions`
**Layout:** `AppLayout`
**Lines:** 912
**API:** Options API with `currencyMixin`

#### Component Structure

```
AssumptionsSettings
  +-- AppLayout (wrapper)
  +-- Breadcrumb (Settings > Planning Assumptions)
  +-- Pension Projections Section
  +-- Investment Projections Section
  +-- Estate Planning Projections Section
  +-- "About These Assumptions" Info Section
```

#### Pension Projections

| Field | Type | Editable | Notes |
|-------|------|----------|-------|
| Inflation Rate | Percentage | Yes | Applied to future value calculations |
| Expected Return | Percentage | Yes | Based on user's risk profile |
| Compound Periods | Number | Yes | Frequency of compounding per year |
| Weighted Average Fees | Percentage | Read-only | Calculated from pension accounts |

**Summary shown:** Years to Retirement, Total Pension Value

#### Investment Projections

| Field | Type | Editable | Notes |
|-------|------|----------|-------|
| Inflation Rate | Percentage | Yes | Applied to future value calculations |
| Expected Return | Percentage | Yes | Based on user's risk profile |
| Compound Periods | Number | Yes | Frequency of compounding per year |
| Weighted Average Fees | Percentage | Read-only | Calculated from investment accounts |

#### Estate Planning Projections

| Field | Type | Editable | Notes |
|-------|------|----------|-------|
| Inflation Rate | Percentage | Yes | General inflation assumption |
| Property Growth Rate | Percentage | Yes | Annual property value growth |
| Investment Growth Method | Select | Yes | Monte Carlo (80% confidence) or Custom rate |
| Custom Growth Rate | Percentage | Yes | Only shown when method is Custom |

#### Section Controls
Each section has:
- **Save** button (enabled only when changes detected)
- **Reset** button (reverts to last saved values)
- Change detection via computed property comparing current vs. original values

#### API Integration
- Uses `assumptionsService` from `@/services/assumptionsService`
- Loads assumptions on mount
- Saves per-section (pension, investment, estate independently)

#### About These Assumptions
Informational expandable section explaining what each assumption means and how it affects projections.

---

## 4. Utility Views

### 4.1 Help Page

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Help.vue`
**Route:** `/help`
**Layout:** `AppLayout`
**Lines:** 837
**API:** Composition API with `setup()`

#### Component Structure

```
Help
  +-- AppLayout (wrapper)
  +-- Search bar
  +-- Two-column layout
        +-- Sticky Sidebar (Table of Contents)
        +-- Main Content Area (12 sections)
```

#### Search Functionality
- Text input with real-time filtering
- Searches across: section titles, keyword arrays, and DOM text content
- Matching sections remain visible; non-matching sections are hidden
- Keyword-based matching ensures relevant results even when search term does not appear in the visible text

#### Help Sections (12)

| Section ID | Title | Keywords (sample) |
|-----------|-------|-------------------|
| `getting-started` | Getting Started | getting started, setup, first steps |
| `dashboard` | Dashboard | dashboard, overview, summary |
| `user-profile` | User Profile | profile, personal details, marital status |
| `protection` | Protection | protection, life insurance, income protection |
| `estate` | Estate Planning | estate, will, inheritance, IHT |
| `retirement` | Retirement | retirement, pension, SIPP, state pension |
| `investment-savings` | Investment & Savings | investment, savings, ISA, portfolio |
| `family-spouse` | Family & Spouse | spouse, family, joint, linking |
| `onboarding` | Onboarding | onboarding, wizard, setup |
| `faqs` | FAQs | frequently asked, common questions |
| `troubleshooting` | Troubleshooting | troubleshooting, error, problem |
| `contact` | Contact Support | contact, support, help, email |

#### Sidebar Navigation
- Sticky positioning (stays visible while scrolling)
- Active section tracking via `IntersectionObserver` or scroll position
- Click-to-scroll to section anchors

#### Contact Support
- Email: support@fynla.com
- Response time: Within 24 hours
- Hours: Monday-Friday, 9am-5pm GMT

---

### 4.2 Version History

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/Version.vue`
**Route:** `/version`
**Layout:** `AppLayout`
**Lines:** Large file (partially read -- first 200 lines)

#### Component Structure

```
Version
  +-- AppLayout (wrapper)
  +-- Version Header (current version info)
  +-- Changelog Sections
```

#### Current Version Info

| Field | Value |
|-------|-------|
| Version | v0.7.0 |
| Release Date | 6 February 2026 |
| Status | Production Ready |

#### Changelog Categories
The changelog is organised into themed sections:

1. **Laravel Best Practices Audit** -- Backend architecture improvements
2. **Goals & Life Events Improvements** -- Goals module enhancements
3. **Retirement Planner Enhancements** -- Retirement module updates
4. **Net Worth & Wealth Summary** -- Net worth tracking improvements
5. **Bug Fixes & Data Accuracy** -- Corrections and data integrity fixes
6. **Other Changes** -- Miscellaneous updates

Each section contains bulleted lists of specific changes.

---

### 4.3 Valuable Info

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/ValuableInfo.vue`
**Route:** `/valuable-info`
**Layout:** `AppLayout`
**Lines:** 157
**API:** Composition API with `setup()`

#### Component Structure

```
ValuableInfo
  +-- AppLayout (wrapper)
  +-- Tab Navigation (5 tabs)
  +-- Tab Content (conditional rendering)
        +-- LetterToSpouse component
        +-- WillPlanning component
        +-- IncomeOccupation component
        +-- ExpenditureOverview component
        +-- RiskProfileSummary component
```

#### Tab Configuration

| Tab ID | Default Label | Dynamic Label Logic |
|--------|---------------|---------------------|
| `letter` | Letter to Spouse | See below |
| `will` | Will | Static |
| `income` | Income | Static |
| `expenditure` | Expenditure | Static |
| `risk` | Risk Profile | Static |

#### Dynamic Letter Tab Label

The first tab label changes based on the user's marital status:

| Marital Status | Tab Label |
|----------------|-----------|
| `single` | Expression of Wishes |
| `widowed` | Expression of Wishes |
| `divorced` | Expression of Wishes |
| Married (with spouse name) | Letter to {SpouseFirstName} |
| Married (no spouse name) | Letter to Spouse |

**Logic:** `expressionOfWishesStatuses = ['single', 'widowed', 'divorced']`. If marital status is in that array, use "Expression of Wishes". Otherwise, extract the spouse's first name from `user.spouse.name` via `split(' ')[0]`.

#### URL Query Parameter
- `?section=letter|will|income|expenditure|risk` sets the active tab on mount
- Tab changes update the URL via `router.replace({ query: { section: newTab } })`
- Enables deep linking and proper back-button navigation

#### Child Components

| Component | Import Path |
|-----------|-------------|
| `LetterToSpouse` | `@/components/UserProfile/LetterToSpouse.vue` |
| `WillPlanning` | `@/components/Estate/WillPlanning.vue` |
| `IncomeOccupation` | `@/components/UserProfile/IncomeOccupation.vue` |
| `ExpenditureOverview` | `@/components/UserProfile/ExpenditureOverview.vue` |
| `RiskProfileSummary` | `@/components/Risk/RiskProfileSummary.vue` |

#### Vuex Integration
- Store: `userProfile`
- Action: `userProfile/fetchProfile` (dispatched on mount)
- Getter: `userProfile/user` (used for marital status and spouse name)

---

### 4.4 Debug Environment

**File:** `/Users/Chris/Desktop/fynla/resources/js/views/DebugEnv.vue`
**Route:** `/debug-env`
**Layout:** None (bare `<div>` with padding)
**Lines:** 67

#### Component Structure

```
DebugEnv (no layout wrapper)
  +-- Vite Environment Variables
  +-- Axios Config
  +-- API Instance Config
  +-- Window Location
```

#### Information Displayed

| Section | Values Shown |
|---------|-------------|
| Vite Environment Variables | `VITE_API_BASE_URL`, `VITE_APP_NAME`, `PROD`, `DEV`, `MODE` |
| Axios Config | `baseURL`, `withCredentials` |
| API Instance Config | `baseURL`, `withCredentials` |
| Window Location | `origin`, `href`, `pathname` |

#### Imports
- `api` from `@/services/api` -- the configured Axios instance

**Purpose:** Developer debugging tool for verifying environment configuration, API base URLs, and build mode. Not intended for end users.

---

## 5. Frontend Routing

### Route Definitions

All routes in `/Users/Chris/Desktop/fynla/resources/js/router/index.js` use lazy-loaded components:

```javascript
component: () => import('@/views/Public/LandingPage.vue')
```

### Navigation Guards

The router uses `beforeEach` guards to enforce:

1. **Public routes** (`meta.public: true`) -- accessible by anyone, no redirect
2. **Guest routes** (`meta.requiresGuest: true`) -- redirects authenticated users to `/dashboard`
3. **Auth routes** (default) -- redirects unauthenticated users to `/login`

### Breadcrumb System

Settings sub-pages define breadcrumb arrays in route meta:

```javascript
meta: {
  requiresAuth: true,
  breadcrumb: [
    { name: 'Settings', path: '/settings' },
    { name: 'Security' }
  ]
}
```

Components can read `route.meta.breadcrumb` to render breadcrumb navigation.

### Complete Route Table

| Path | Component | Auth | Layout | Breadcrumb |
|------|-----------|------|--------|------------|
| `/` | LandingPage | Public | PublicLayout | -- |
| `/calculators` | CalculatorsPage | Public | PublicLayout | -- |
| `/learning-centre` | LearningCentre | Public | PublicLayout | -- |
| `/security` | SecurityPage | Public | PublicLayout | -- |
| `/about` | AboutPage | Public | PublicLayout | -- |
| `/pricing` | PricingPage | Public | PublicLayout | -- |
| `/sitemap` | SitemapPage | Public | PublicLayout | -- |
| `/login` | Login | Guest | PublicLayout | -- |
| `/register` | Register | Guest | PublicLayout | -- |
| `/settings` | Settings | Auth | AppLayout | -- |
| `/settings/security` | SecuritySettings | Auth | AppLayout | Settings > Security |
| `/settings/privacy` | PrivacySettings | Auth | AppLayout | Settings > Privacy & Data |
| `/settings/assumptions` | AssumptionsSettings | Auth | AppLayout | Settings > Planning Assumptions |
| `/help` | Help | Auth | AppLayout | -- |
| `/version` | Version | Auth | AppLayout | -- |
| `/valuable-info` | ValuableInfo | Auth | AppLayout | Settings > Valuable Info |
| `/debug-env` | DebugEnv | Auth | None | -- |

---

## 6. Design & Layout Patterns

### Layout Components

| Layout | Usage | Features |
|--------|-------|----------|
| `PublicLayout` | All `/public/*` routes | Marketing header/footer, dark theme hero sections, CTA sections |
| `AppLayout` | All authenticated routes | Sidebar navigation, top bar with user info, responsive collapse |

### Visual Patterns

**Public pages (dark theme):**
- Hero sections with `bg-gradient-to-br from-gray-900 via-primary-950 to-gray-900`
- Glassmorphism cards: `backdrop-blur`, `bg-white/10`, `border-white/20`
- Animated floating orbs (CSS keyframe animations)
- White/light text on dark backgrounds
- Exception: SitemapPage uses `bg-gray-50` (light background)

**Authenticated pages (light theme):**
- White cards with `shadow-sm` and `rounded-lg`
- `bg-gray-50` or `bg-gray-100` page backgrounds
- Standard Tailwind form styling
- Consistent spacing with `p-6` card padding

### Responsive Design
- All pages use responsive Tailwind breakpoints (`sm:`, `md:`, `lg:`)
- Tab navigation uses `overflow-x-auto` with hidden scrollbar for horizontal scrolling on mobile
- Grid layouts collapse from multi-column to single-column on small screens
- Font sizes scale: `text-xs sm:text-sm` pattern for tab labels

### Common UI Components
- **Tab navigation:** Horizontal tabs with bottom border indicator, used in Calculators, Learning Centre, Valuable Info
- **CTA sections:** Consistent pattern with heading, description, and primary/secondary buttons linking to `/register` and `/login`
- **Stat cards:** Grid of metric cards with icon, value, and label (Landing Page)
- **Feature cards:** Icon + title + description pattern (Landing Page, Security Page)

---

## 7. Cross-Module Integration

### Vuex Store Dependencies

| View | Store Module | Actions/Getters Used |
|------|-------------|---------------------|
| LandingPage | `preview` | `loadPersona`, `availablePersonas` |
| Settings | `auth` | `auth/user`, `auth/logout` |
| ValuableInfo | `userProfile` | `userProfile/fetchProfile`, `userProfile/user` |
| SecuritySettings | -- | Direct API calls (no Vuex) |
| PrivacySettings | -- | Direct API calls (no Vuex) |
| AssumptionsSettings | -- | Uses `assumptionsService` |

### API Service Dependencies

| View | Service | Endpoints |
|------|---------|-----------|
| SecuritySettings | Direct `api` calls | `/auth/mfa/*`, `/auth/sessions/*`, `/auth/change-password` |
| PrivacySettings | Direct `api` calls | `/auth/gdpr/*` (consents, export, erasure) |
| AssumptionsSettings | `assumptionsService` | Assumptions CRUD |
| DebugEnv | `api` (import only) | Reads config, no API calls |
| Calculators | None | All client-side calculations |

### Shared Mixins

| Mixin | Used By |
|-------|---------|
| `currencyMixin` | CalculatorsPage, AssumptionsSettings |

### Cross-Page Navigation Patterns

| From | To | Mechanism |
|------|----|-----------|
| LandingPage | `/dashboard` | After persona selection (preview mode) |
| LandingPage | `/login` | "Sign In" link |
| LandingPage | `/register` | Waitlist/CTA (external or register) |
| PricingPage | `/register?plan=X&billing=Y` | "Start Free Trial" with query params |
| CalculatorsPage | `/register`, `/login` | CTA section links |
| LearningCentre | `/register` | CTA section link |
| Settings | `/settings/security` | Router-link |
| Settings | `/settings/privacy` | Router-link |
| Settings | `/settings/assumptions` | Router-link |
| Settings | `/login` | After sign out |
| SitemapPage | Various | Internal links throughout |

---

## 8. Known Issues and Limitations

### Current Limitations

1. **Version.vue file size** -- The Version.vue file is very large (exceeds typical component size) due to inline changelog content. Consider extracting version history to a data file or API endpoint.

2. **Email Notifications** -- The Settings hub shows "Email Notifications" as "Coming Soon" (disabled). The feature is defined in the UI but not yet implemented.

3. **Calculator limitations** -- All calculators use 2025/26 UK tax year hardcoded values. These are not sourced from `TaxConfigService` like the rest of the application. If tax bands change, both the calculators and the backend must be updated independently.

4. **Sitemap inconsistencies** -- The Sitemap page lists some calculators (IHT, CGT, Pension Contribution) that do not have dedicated pages; they all link to `/calculators`. The Calculators page itself only contains Income Tax, Mortgage, Loan, Emergency Fund, and Pension Growth calculators.

5. **Help contact email mismatch** -- The Help page shows `support@fynla.com` while the Security page shows `info@fynla.org` and the Privacy page shows `privacy@fynla.org`. The domain inconsistency (`.com` vs `.org`) may be intentional (different mailboxes) or an error.

6. **DebugEnv has no layout** -- The DebugEnv view renders as a bare page with no `AppLayout` wrapper. It has no explicit access restriction in the component itself (relies only on router auth guard).

7. **Pricing page is static** -- Plan details (features, pricing) are hardcoded in the component. There is no backend API for plan management or dynamic pricing.

8. **No SEO meta tags** -- Public marketing pages do not appear to set dynamic `<title>` or `<meta>` tags for SEO. The SitemapPage references `/sitemap.xml` but meta tag management per page is not visible in the components.

### Architecture Notes

- Public pages are entirely self-contained with no backend API dependencies (except LandingPage's preview mode which uses the Vuex preview store)
- Settings views mix direct API calls (Security, Privacy) with service abstractions (Assumptions). Consider standardising on one approach.
- The `currencyMixin` is used in CalculatorsPage and AssumptionsSettings. Other views that display monetary values should also use it for consistency.
