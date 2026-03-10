# Fynla Mobile App Exploration

**Date:** 6 March 2026 | **Updated:** 10 March 2026
**Status:** Design exploration (pre-implementation)
**Current Version:** v0.8.3 (web)

---

## Executive Summary

This document explores building a Fynla mobile app from four perspectives: UX design, technical architecture, security/data protection, and a devil's advocate challenge. The analysis is grounded in the current codebase (403 Vue components, 1,074-line API routes file, 75 controllers, 9 agents, 78 models, 7 financial modules + AI chat) and the fynlaDesignGuide.md v1.2.0 design system.

**Core philosophy: Mobile as companion, web as command centre.** The mobile app stays uncluttered and clear by showing summaries, AI chat, education, and quick updates — while deep-linking to the web app for detailed views, complex data entry, and full financial plans. This gives users maximum value on mobile without recreating the full 403-component web experience.

**Recommendation:** A phased approach starting with a **Progressive Web App (PWA)** that evolves into a **Capacitor-wrapped hybrid app** if mobile adoption justifies it. The AI assistant (Fyn) becomes the primary mobile interaction model — users chat with Fyn to understand their finances, get guidance, and plan ahead rather than navigating complex module hierarchies.

---

## Current App State (as of v0.8.3)

### Codebase Metrics

| Category | Count |
|----------|-------|
| Vue Components | 403 (across 39 module directories) |
| PHP Services | 183 (across 38 directories) |
| Controllers | 75 |
| Database Models | 78 |
| Vuex Store Modules | 24 |
| Agents (Orchestrators) | 9 |
| API Services (JS) | 36 |
| Form Requests | 74 |
| API Routes | 1,074 lines |
| Test Files | 135 (940+ test cases) |
| Database Tables | 115 |
| Seeders | 17 |
| Observers | 11 |

### 7 Core Financial Modules

| Module | Components | Services | Key Features |
|--------|-----------|----------|-------------|
| **Protection** | 13 | 6 | Policy portfolio, coverage gap analysis, human capital calculation, premium affordability |
| **Savings** | 11 | 5 | Emergency fund calculator, ISA tracking, liquidity ladder, interest rate analysis |
| **Investment** | 60 | 45 | Portfolio tracking, asset allocation, rebalancing, fee analysis, tax efficiency, Monte Carlo |
| **Retirement** | 21 | 10 | DC/DB/State pension tracking, contribution optimisation, decumulation planning, Annual Allowance |
| **Estate** | 32 | 22 | IHT calculations, gifting strategy, trust management, will planning, business relief |
| **Goals** | 25 | 12 | Goal tracking with projections, life events, contribution streaks, milestone badges |
| **Coordination** | 8 services | 8 | Holistic plan synthesis, cross-module priority identification, cashflow aggregation |

### Existing AI Chat System

The app already has a full AI assistant ("Fyn"):
- **AiChatPanel** — persistent chat UI with conversation history, streaming responses (SSE), suggested prompts
- **17 financial analysis tools** available to the AI (tool-calling via backend)
- **Context-aware** — knows current route, user financial data, module state
- **AiConversation + AiMessage models** — persistent conversation history
- **Simulated AI** for preview personas (zero API cost demos)
- **Tiered model access** — model quality varies by subscription tier

### Existing Onboarding System (3 Modes)

1. **Quick Mode** (3 steps, ~5 minutes) — personal info, employment, financial overview
2. **Full Mode** (12+ steps, ~30 minutes) — comprehensive data collection with skip options
3. **Journey Mode** (modular) — user selects focus areas (budgeting, protection, investment, retirement, estate, family, business, goals), system generates dynamic steps per journey, dashboard shows progress per journey with nudges to start next

### Existing Education & Guidance Features

- **InfoGuidePanel** — right-side panel showing data completeness per module, links to missing fields
- **GuidanceWelcomeModal** — onboarding pitch with time estimates
- **GuidanceTooltip** — contextual field-level help
- **InfoTooltip** — hover explanations for financial concepts
- **Dashboard prompts** — contextual nudges ("Now that you've covered budgeting, consider protection...")
- **Plans system** — 51 components generating per-module financial plans with executive summaries, current situation analysis, recommendations, and what-if scenarios

### Existing Subscription System

- **Revolut CDN integration** for payments
- **3 tiers**: Student (£3.99/£30), Standard (£10.99/£100), Pro (£19.99/£200)
- **Trial system** with countdown banners
- **Data retention** policies with warning emails

### Preview Mode (7 Personas)

| Persona | Users | Net Worth | Focus |
|---------|-------|-----------|-------|
| young_family | James & Emily Carter | ~£100k | Mortgage, workplace pensions |
| peak_earners | David & Sarah Mitchell | ~£2.3m | Multiple properties, SIPP, NHS pension |
| widow | Margaret Thompson | ~£2.2m | Estate planning |
| entrepreneur | Alex Chen | ~£550k | SIPP, business interests |
| young_saver | John Morgan | ~£25k | Emergency fund, first-time savings |
| retired_couple | Patricia & Harold Bennett | ~£1.8m | Decumulation, estate planning |
| student | Janice Taylor | ~-£33k | Student debt, LISA |

---

## 1. UX Analysis

### 1.1 Mobile-First Philosophy: Companion, Not Clone

The mobile app is NOT a shrunk-down version of the web app. It is a **companion** that provides:

1. **At-a-glance summaries** — net worth, goal progress, protection status, pension projections
2. **AI conversations** — chat with Fyn to understand, plan, and learn
3. **Education and guidance** — contextual financial literacy content woven throughout
4. **Quick updates** — add a contribution, log a payment, note a change
5. **Notifications** — policy renewals, goal milestones, market alerts, AI insights
6. **Deep links to web** — "View full detail on web" for complex views, data entry, and plans

**What stays on web only:**
- Full financial plan generation (51 components)
- Complex data entry forms (property details, pension transfers, trust structures)
- Multi-column comparison tables
- Full chart interactivity (Monte Carlo simulations, what-if scenarios)
- Admin features
- Document management
- Print/PDF export

### 1.2 Navigation Architecture

**Recommended: Bottom Tab Bar with AI-First Design**

| Tab | Content | Icon | Purpose |
|-----|---------|------|---------|
| Home | Dashboard summaries, journey progress, alerts | Home | Glanceable overview |
| Fyn | AI chat assistant (primary interaction) | Chat bubble | Ask questions, get guidance, plan ahead |
| Learn | Education hub, financial literacy, guides | Book | Browse topics, understand concepts |
| Goals | Goal progress, milestones, streaks | Target | High-engagement tracking |
| More | Module summaries, settings, profile, web links | Menu | Everything else, links to full web views |

The **Fyn tab is central** — it's the primary way users interact on mobile. Instead of navigating through 7 modules, users ask Fyn:
- "How much life insurance do I need?"
- "Am I on track for retirement?"
- "What's the most tax-efficient way to save?"
- "Explain my inheritance tax position"

Fyn responds with personalised answers using the 17 financial tools, links to relevant education content, and offers to open the detailed web view if needed.

### 1.3 User Journeys — Mobile Onboarding

Mobile onboarding is conversational, not form-based. Fyn guides the user through setup:

#### Journey 1: First Launch (New User)

```
1. Welcome screen — "I'm Fyn, your financial planning companion"
2. "What brings you here today?" — select focus areas
   (Same journey selection as web: budgeting, protection, investment, retirement, estate, family, business, goals)
3. Quick profile — name, age, employment (3 fields, not 12)
4. "Great! Let's start with your [selected focus]. I'll ask a few questions..."
5. Conversational data collection via Fyn chat:
   - Fyn asks questions one at a time
   - User responds in natural language or taps quick-reply buttons
   - Fyn explains why each question matters (education woven in)
   - "That's enough for now! Here's what I can already tell you..."
6. Dashboard with initial insights + "Add more detail on web" prompts
```

#### Journey 2: Returning User (Daily Check-In)

```
1. Biometric unlock (Face ID / Touch ID)
2. Home tab shows:
   - Net worth change since last visit
   - Goal progress updates
   - Any alerts (policy renewal, contribution reminder)
   - Fyn's daily insight ("Your emergency fund now covers 4.2 months")
3. Tap any card → summary with "View full detail" deep link to web
4. Tap Fyn → continue previous conversation or start new one
```

#### Journey 3: Notification-Driven (Prompted)

```
1. Push notification: "Your life insurance policy renews in 30 days"
2. Tap → opens Protection summary card on mobile
3. Shows: policy details, premium amount, coverage analysis
4. "Ask Fyn about this" button → opens chat with context pre-loaded
5. Fyn: "Your life insurance renews on [date] at £X/month. Based on your current
   situation, your coverage gap is £Y. Would you like me to explain your options?"
6. "View full protection analysis" → deep link to web /protection
```

#### Journey 4: Learning-Driven (Education)

```
1. User opens Learn tab
2. Browse by topic: Tax, Pensions, Protection, Investing, Estate, Budgeting
3. Each topic has:
   - "What you need to know" — plain-English explainer
   - "Your situation" — personalised summary using their data
   - "What Fyn recommends" — contextual guidance
   - "Common questions" — FAQ with Fyn-powered answers
   - "Go deeper" → deep link to web module
4. User taps a question → Fyn opens with that context and answers conversationally
```

### 1.4 AI-Powered Interactions Throughout

Fyn is not just a chat panel — it's woven into every screen:

| Screen | Fyn Integration |
|--------|----------------|
| **Home dashboard** | Daily insight card ("Your pension is growing faster than inflation this quarter") |
| **Goal progress** | Milestone celebration + "Ask Fyn how to reach your goal faster" |
| **Module summaries** | "Fyn's take" section with 1-2 sentence personalised commentary |
| **Education articles** | "Ask Fyn about this" button on every article |
| **Notifications** | Each notification includes "Discuss with Fyn" action |
| **Empty states** | Fyn explains what the module does and why it matters |
| **Settings** | "Ask Fyn to review my plan" action |

**Chat capabilities on mobile (existing 17 tools):**
- Analyse portfolio allocation and fees
- Calculate pension projections
- Assess protection coverage gaps
- Estimate inheritance tax liability
- Track goal progress and contributions
- Generate tax optimisation suggestions
- Compare scenarios (what-if)
- Explain financial concepts in plain English

### 1.5 Education & Informational Links

Every screen includes contextual education. Content is organised in three tiers:

**Tier 1: Inline Context (always visible)**
- Tooltip icons next to financial terms (e.g., "Nil Rate Band (?)" → brief explanation)
- Colour-coded status indicators with plain-English labels (not scores)
- Currency values always shown with context ("£325,000 Nil Rate Band — the amount you can pass on tax-free")

**Tier 2: Learn Cards (one tap away)**
- Each module summary includes a "Learn about [topic]" card
- Cards are 2-3 paragraphs, written in plain English, UK-specific
- End with "Ask Fyn to explain further" and "Read more on web"

**Tier 3: Deep Dive (links to web)**
- Full educational articles accessible via web deep links
- Financial plan documents (PDF export on web)
- Detailed calculators and scenario modelling

**Education Topic Map:**

| Module | Mobile Education Content |
|--------|------------------------|
| Protection | Types of cover, how much you need, when to review, critical illness vs life insurance |
| Savings | Emergency fund basics, ISA types explained, interest rate comparison, savings vs investing |
| Investment | Risk explained, diversification, fees matter, tax wrappers (ISA vs GIA vs SIPP) |
| Retirement | State Pension explained, workplace vs personal pensions, Annual Allowance, tax relief |
| Estate | Inheritance Tax basics, nil rate bands explained, gifting rules, trusts simplified, wills |
| Goals | Goal-setting strategies, compound growth, contribution consistency, life event planning |
| Tax | Income tax bands, Capital Gains Tax, tax-free allowances, National Insurance |

### 1.6 Deep Linking Strategy (Mobile to Web)

Every mobile summary screen includes clear pathways to the full web experience:

```
Mobile Summary Card
├── Key metrics (net worth, coverage gap, pension projection)
├── Fyn's insight (1-2 sentences)
├── "Ask Fyn" button → opens chat with context
├── "Learn more" → education card
└── "View full detail" → opens https://fynla.org/[module] in browser
```

**Deep link patterns:**

| Mobile Screen | Web Deep Link | What Opens |
|---------------|--------------|-----------|
| Net worth summary | `/net-worth` | Full waterfall chart, asset breakdown, joint view |
| Protection summary | `/protection` | Policy portfolio, coverage gap analysis, recommendations |
| Pension summary | `/retirement` | Pension inventory, projections, contribution optimiser |
| Investment summary | `/investments` | Portfolio detail, holdings, rebalancing, fee analysis |
| Goal detail | `/goals` | Full goal dashboard, life events, projections |
| Estate summary | `/estate` | IHT calculations, gifting strategy, trust details |
| Savings summary | `/savings` | Account details, ISA tracking, liquidity ladder |
| Holistic plan | `/plans/holistic` | Full 6-section financial plan |
| Tax overview | `/tax` | Income tax breakdown, optimisation recommendations |

**Implementation:** Universal Links (iOS) / App Links (Android) for seamless web-to-app and app-to-web transitions. When the app is installed, web links open in-app where a mobile view exists; otherwise they open in the browser.

### 1.7 Touch-First Interactions

- **Touch targets:** Minimum 44x44pt (Apple HIG) / 48x48dp (Material)
- **Swipe gestures:** Swipe between module summaries, swipe to dismiss notifications
- **Pull-to-refresh:** On dashboard, goal progress, module summaries
- **Long-press:** Quick actions (ask Fyn, open on web, share)
- **Haptic feedback:** Goal milestones, successful saves, chat message sent

### 1.8 Data Density on Small Screens

The mobile app embraces summary over detail:

- **Summary cards with drill-down:** Dashboard shows 1-2 key metrics per module. Tap to expand
- **Progressive disclosure:** Show headline number first, supporting detail on tap
- **Charts simplified:** Donut charts for allocation, sparklines for trends. Full interactive charts on web only
- **No tables on mobile:** Use stacked cards instead. Tables available via "View on web" deep link
- **Fyn explains the numbers:** Every metric card has "What does this mean?" → Fyn explains in context

### 1.9 Fyn Mascot on Mobile

The springbok character "Fyn" is the face of the mobile experience:

- **Chat avatar:** Fyn avatar in all AI chat messages
- **Onboarding guide:** Fyn walks users through setup conversationally
- **Achievement celebrations:** Animated Fyn for goal milestones (25%, 50%, 75%, 100%)
- **Empty states:** Fyn with contextual message and "Let me help you get started"
- **Daily insights:** Fyn's face next to the daily insight card on the dashboard
- **Loading states:** Subtle Fyn animation for longer operations
- Keep Fyn small and friendly — avatar in chat bubbles and cards, not full-screen takeovers

### 1.10 Offline Experience

- **Read-only dashboard:** Cache last-known summaries (net worth, goals, alerts)
- **AI chat history:** Previous conversations viewable offline
- **Education content:** Core articles cached for offline reading
- **Queued actions:** Notes, contribution logs saved offline, sync when connected
- **Clear offline indicator:** "Last updated [time]" with subtle banner

---

## 2. Technical Architecture

### 2.1 Framework Comparison

| Criteria | PWA (Vue.js) | Capacitor + Vue | React Native | Flutter | Native |
|----------|-------------|-----------------|--------------|---------|--------|
| Code reuse with web | 100% | 90-95% | 10-20% | 0% | 0% |
| Native device access | Limited | Full (plugins) | Full | Full | Full |
| App store distribution | No | Yes | Yes | Yes | Yes |
| Learning curve | None | Low | Medium | High | High |
| Performance | Good | Good | Good | Excellent | Excellent |
| Team skill match | Perfect | Perfect | JS not Vue | Dart | New |
| AI chat integration | Existing | Existing | Rebuild | Rebuild | Rebuild |
| Maintenance burden | Minimal | Low-medium | High | High | Very high |
| Estimated effort | 2-4 weeks | 6-10 weeks | 16-24 weeks | 16-24 weeks | 24-40 weeks |

**Recommended: Capacitor + Vue.js 3** (Phase 2, after PWA proves demand)

### 2.2 API Strategy

The existing Laravel API (1,074 lines in `routes/api.php`, 75 controllers) is well-structured for mobile:

- **RESTful endpoints** with Sanctum token auth
- **Resource classes** for consistent JSON responses (`{ success, message, data }`)
- **AI chat endpoints** with SSE streaming (already mobile-ready)
- **Form request validation** (74 classes) handles server-side validation

**Mobile-specific additions:**

| Addition | Purpose |
|----------|---------|
| API versioning (`/api/v1/`) | Allow breaking changes without affecting older app versions |
| Response compression (gzip/brotli) | Reduce payload 60-80% for mobile bandwidth |
| ETags / conditional requests | Cache tax configs, market rates — reduce redundant fetches |
| Summary endpoints | New `/api/v1/mobile/dashboard` returning all module summaries in one call |
| Deep link metadata | Endpoints return `web_url` field for "View on web" links |
| Push notification registration | Device token management for FCM/APNs |

**New mobile-specific endpoints needed:**

```
GET  /api/v1/mobile/dashboard       → Aggregated summary (all modules, one request)
GET  /api/v1/mobile/insights        → Fyn's daily insights and alerts
POST /api/v1/mobile/devices         → Register device for push notifications
DELETE /api/v1/mobile/devices/{id}  → Unregister device
GET  /api/v1/mobile/education/{topic} → Education content for Learn tab
POST /api/v1/ai/chat                → Existing AI chat (already mobile-ready)
GET  /api/v1/ai/conversations       → Existing conversation list
```

### 2.3 AI Chat Architecture for Mobile

The existing AI system maps directly to mobile:

```
Mobile App (AiChatPanel → existing component, responsive)
  ↓
POST /api/v1/ai/chat (SSE streaming)
  ↓
AiChatController → AiChatService
  ↓
AiContextBuilder (injects user financial data + current screen context)
  ↓
AiToolDefinitions (17 financial tools)
  ↓
AiToolExecutor (executes tool calls against user data)
  ↓
Response streamed back via SSE → displayed token-by-token
```

**Mobile enhancements:**
- **Voice input:** Microphone button → speech-to-text → send to Fyn
- **Quick-reply buttons:** Suggested follow-up questions after each response
- **Context injection:** Mobile passes current screen (e.g., "user is viewing Protection summary") so Fyn knows what to discuss
- **Conversation continuity:** Same conversation history across mobile and web
- **Offline fallback:** Cache last conversation, show "Fyn is offline" state with option to queue message

### 2.4 Authentication Flow for Mobile

Current flow: email + password → verification code → session token.

**Mobile-enhanced flow:**
1. First login: email + password → verification code → Sanctum token stored in Keychain/Keystore
2. Subsequent logins: biometric unlock → stored token refresh
3. Token refresh: automatic before expiry (30-day tokens, refresh at 25 days)
4. Session timeout: 15-minute inactivity → biometric re-auth (not full login)
5. Device registration: track device IDs, allow remote revocation

### 2.5 Offline Data Architecture

```
Mobile App
  |-- Local Cache (SQLite via Capacitor)
  |     |-- Dashboard summary (all modules, cached per sync)
  |     |-- Goal progress snapshots
  |     |-- Education content (articles, guides)
  |     |-- AI conversation history
  |     |-- Pending offline actions queue
  |     |-- Tax reference data (yearly update)
  |
  |-- Sync Manager
  |     |-- Background sync when connectivity restored
  |     |-- Conflict resolution (server wins for calculations)
  |     |-- Delta sync (only changed records since last timestamp)
  |
  |-- API Layer (existing services/ — reused)
        |-- Axios interceptor adds offline queue support
        |-- Retry logic exists (exponential backoff)
```

### 2.6 State Management

The existing 24 Vuex modules can be reused directly. Add:

- **Persistence plugin:** State persisted to Capacitor Storage (encrypted)
- **Sync status tracking:** Each module tracks `lastSynced` timestamp
- **Mobile-specific module:** `mobileDashboard` store for aggregated summary data
- **Optimistic updates:** Apply locally, sync to server, rollback on failure

### 2.7 Deep Link Implementation

```javascript
// Universal Links (iOS) / App Links (Android)
// apple-app-site-association + assetlinks.json on fynla.org

const deepLinkRoutes = {
  '/net-worth':    { mobile: 'NetWorthSummary',    web: true },
  '/protection':   { mobile: 'ProtectionSummary',  web: true },
  '/investments':  { mobile: 'InvestmentSummary',  web: true },
  '/retirement':   { mobile: 'RetirementSummary',  web: true },
  '/estate':       { mobile: 'EstateSummary',      web: true },
  '/goals':        { mobile: 'GoalsDashboard',     web: true },
  '/savings':      { mobile: 'SavingsSummary',     web: true },
  '/plans/holistic': { mobile: null,               web: true }, // Web only
  '/tax':          { mobile: 'TaxSummary',         web: true },
};

// "View on web" button handler
function openOnWeb(path) {
  if (Capacitor.isNativePlatform()) {
    Browser.open({ url: `https://fynla.org${path}` });
  } else {
    window.open(`https://fynla.org${path}`, '_blank');
  }
}
```

### 2.8 Build & Deploy Pipeline

| Stage | Tool | Notes |
|-------|------|-------|
| Local build | Existing Vite config | Add Capacitor build target |
| iOS build | Xcode (via Capacitor CLI) | Apple Developer account (£79/year) |
| Android build | Android Studio (via Capacitor CLI) | Free |
| CI/CD | GitHub Actions | Automate builds on tagged releases |
| iOS distribution | TestFlight → App Store | 1-7 day review for financial apps |
| Android distribution | Google Play Console | 1-3 day review |
| OTA updates | Capgo or Appflow | Push web layer updates without store review |

### 2.9 Project Structure (Capacitor)

```
fynla/
  |-- resources/js/                (existing Vue.js app — shared)
  |-- resources/js/mobile/         (mobile-specific views)
  |     |-- MobileDashboard.vue
  |     |-- ModuleSummary.vue
  |     |-- LearnHub.vue
  |     |-- LearnTopic.vue
  |-- mobile/
  |     |-- ios/                   (Xcode project, auto-generated)
  |     |-- android/               (Android Studio project, auto-generated)
  |     |-- capacitor.config.ts
  |     |-- plugins/
  |           |-- biometrics.ts
  |           |-- push-notifications.ts
  |           |-- haptics.ts
  |           |-- deep-links.ts
  |-- resources/js/utils/
        |-- platform.js            (detect web vs mobile, expose device APIs)
        |-- deepLinks.js           (handle incoming/outgoing deep links)
```

---

## 3. Security & Data Protection

### 3.1 Regulatory Landscape

| Regulation | Requirement | Impact on Mobile |
|------------|-------------|-----------------|
| **UK GDPR / DPA 2018** | Data minimisation, right to erasure | Handle data deletion on uninstall/account deletion |
| **FCA (if applicable)** | Financial promotions rules | App store descriptions must comply |
| **ICO Mobile App Guidance** | Privacy by design | Privacy policy before first data entry |
| **PCI DSS** | Card data protection | Revolut SDK handles PCI scope — never store cards locally |
| **Apple App Store** | 4.2 (financial), 5.1 (privacy) | Declare all data collection in privacy nutrition labels |
| **Google Play** | Financial services policy | Complete data safety form accurately |
| **AI-specific** | Transparency on AI-generated content | Clear "AI-generated" labelling on Fyn's responses |

### 3.2 Authentication Security

| Control | Implementation | Priority |
|---------|---------------|----------|
| Biometric auth | Face ID / Touch ID for returning-user unlock, NOT sole auth | Critical |
| Secure token storage | iOS: Keychain. Android: EncryptedSharedPreferences | Critical |
| Certificate pinning | Pin API domain public key (not cert) | Critical |
| Session timeout | 15-min inactivity → biometric re-auth. 30-day full re-login | High |
| Device binding | Register device IDs, alert on new device, allow remote revocation | High |
| Step-up auth | Verification code for: password change, payment methods, data export, account deletion | High |
| Jailbreak/root detection | Warn users, log, restrict sensitive operations. Don't block entirely | Medium |

### 3.3 Data Protection on Device

**Store locally (encrypted):**
- Authentication tokens (Keychain/Keystore)
- Cached dashboard summary
- AI conversation history (encrypted)
- Education content (non-sensitive)
- Offline action queue
- User preferences

**NEVER store locally:**
- Raw financial data (full account details, balances, transaction history)
- Tax calculations or projections
- Personal documents
- Payment credentials
- Passwords or verification codes

**Device-level protections:**

| Protection | iOS | Android |
|------------|-----|---------|
| Encrypted storage | Keychain Services | EncryptedSharedPreferences |
| Screenshot prevention | `isCaptured` detection + overlay | `FLAG_SECURE` |
| Clipboard timeout | Clear after 30 seconds | Same |
| Backup exclusion | `isExcludedFromBackup = true` | `allowBackup="false"` |
| App backgrounding | Blur overlay | Same |
| Idle data purge | Clear after 7 days without use | Same |

### 3.4 AI Chat Security

| Concern | Mitigation |
|---------|-----------|
| AI hallucination risk | Clear disclaimer: "Fyn provides guidance, not financial advice. Always verify." |
| Sensitive data in prompts | AI context uses server-side injection — user data never sent from device |
| Conversation storage | Encrypted in transit (TLS 1.3), at rest (AES-256), user can delete conversations |
| AI model access | Tiered by subscription (higher tiers get better models) |
| Rate limiting | AI chat rate-limited per user (existing backend limits) |

### 3.5 Network Security

- **TLS 1.3 minimum** with ATS (iOS) / Network Security Config (Android)
- **Certificate pinning** on all API requests (public key hash)
- **Request signing:** HMAC on sensitive writes
- **No sensitive data in URLs:** Financial data in POST bodies only
- **SSE security:** AI chat streaming authenticated per-connection

### 3.6 Audit & Monitoring

- **Device-aware audit logs:** Extend `Auditable` trait with device type, OS version, app version
- **Anomaly detection:** New device, unusual location, bulk data access
- **Crash reporting:** Sentry with PII scrubbing — never include financial data
- **Remote wipe:** Server flag forces token revocation + local data wipe on next open

### 3.7 GDPR Right to Erasure on Mobile

When a user deletes their account (existing `GDPRController` + `DataPurgeService`):
1. Server deletes all user data (existing 13 cleanup commands)
2. Push notification to all registered devices: trigger local data wipe
3. Revoke all device tokens
4. Clear Keychain/Keystore entries
5. Delete all cached AI conversations
6. App shows "Account deleted" screen on next open

---

## 4. Devil's Advocate

### 4.1 Is a Mobile App Even Needed?

**Challenge:** Financial planning is a sit-down activity. Nobody plans their pension on the bus. The web app has 403 components — trying to make that work on mobile is insanity.

**Counter-argument (updated for AI-first mobile):**
The mobile app is NOT trying to replicate the web experience. It's a different product:
- **AI chat hub** — users talk to Fyn about their finances, anytime, anywhere
- **Monitoring dashboard** — quick-glance net worth, goal progress, alerts
- **Education platform** — bite-sized financial literacy content
- **Notification receiver** — policy renewals, contribution reminders
- **Document scanner** — camera capture of statements for later processing on web

The 403 components stay on web. Mobile has ~20-30 focused components.

**Verdict:** An AI-first mobile companion adds genuine value that the web cannot — conversational interaction, push notifications, on-the-go monitoring. It's not trying to be the web app.

### 4.2 The v0.8.3 Problem

**Challenge:** Pre-1.0, building mobile means two platforms to maintain during stabilisation.

**Updated assessment:** The mobile app shares 90-95% of backend code (API, services, agents, AI). The mobile-specific code is ~20-30 new Vue components (summary views, Learn hub, mobile nav). The AI chat system already exists and is responsive.

**Recommendation:** The PWA approach (Phase 1) adds almost zero maintenance burden — it's the same codebase with a service worker and manifest. Capacitor (Phase 2) adds moderate burden but reuses existing code.

### 4.3 Cost-Benefit Reality (Updated)

| Item | Estimated Cost |
|------|---------------|
| Apple Developer Programme | £79/year |
| Google Play Developer | £20 (one-time) |
| PWA phase (Service Worker + manifest) | 2-4 weeks |
| Capacitor setup + mobile views | 6-10 weeks |
| App store review compliance | 2-4 weeks |
| Ongoing maintenance | 10-15% additional (lower than before — mobile is read-focused) |
| Push notification infrastructure | £0-50/month (Firebase free tier) |

**Break-even:** At £10.99/month (Standard), ~10-15 mobile-only subscribers covers costs. But the real value is **retention** — users who check their finances daily via mobile churn less. Even if mobile drives zero new signups, it could reduce churn by 20-30%.

### 4.4 The PWA Alternative (Still Recommended Phase 1)

| Feature | PWA | Capacitor |
|---------|-----|-----------|
| Home screen icon | Yes | Yes |
| Offline caching | Yes | Yes |
| Push notifications | Yes (Web Push) | Yes (native) |
| AI chat | Yes (existing) | Yes (existing) |
| Biometric login | Limited | Yes |
| Camera access | Yes | Yes (better) |
| App store presence | No | Yes |
| Deep links to web | Yes (just URLs) | Yes (Universal Links) |
| Haptics | No | Yes |
| Widgets | No | Yes |

### 4.5 AI Chat Risk Assessment

**Challenge:** Giving users an AI to discuss their finances is risky. What if Fyn gives bad advice?

**Mitigations already in place:**
- Fyn uses 17 defined tools — it can only analyse real data, not hallucinate numbers
- Clear disclaimer: "Fyn provides guidance, not regulated financial advice"
- Simulated AI for preview users (zero hallucination risk in demos)
- Tiered model access (better models for paying users)
- All conversations logged for audit

**Additional mobile mitigations:**
- "Verify with an adviser" CTA after every recommendation
- Link to relevant regulatory guidance (FCA, HMRC)
- Rate limiting on AI requests per day
- Human escalation path ("Talk to support" button)

### 4.6 App Store Risks for Financial Apps

- **Apple review:** Enhanced review for financial apps. Expect 1-2 rejections. AI chat adds complexity — must declare AI use clearly
- **Apple's 30% cut:** Route subscriptions through web (Revolut) — use "reader app" exemption or link-out
- **AI disclosure:** Both stores increasingly require AI usage disclosure. Fyn must be clearly labelled
- **Rating pressure:** A 3-star financial app loses trust. Launch polished or not at all

### 4.7 When WOULD a Mobile App Make Sense?

A Capacitor app is justified when:

1. **PWA metrics show daily mobile engagement** (users checking dashboard via PWA)
2. **AI chat usage exceeds 3 conversations/user/week** (proves mobile AI value)
3. **Push notification opt-in exceeds 40%** (proves notification value)
4. **User research confirms biometric login is top request**
5. **Web app reaches v1.0+** with stable features
6. **Revenue supports it:** 200+ paying subscribers minimum

---

## 5. Recommended Phased Approach

### Phase 1: PWA + AI Focus (Weeks 1-4) — LOW RISK

- Add Service Worker for offline caching (dashboard summaries, education content, AI conversation history)
- Add Web App Manifest for home screen installation
- Implement Web Push notifications (goal milestones, policy renewals, AI insights)
- Create mobile-optimised summary views for each module (cards, not tables)
- Optimise existing AI chat panel for mobile (full-screen on small screens, voice input)
- Create Learn tab with education content organised by financial topic
- Add "View on web" deep links from every summary view
- Add daily insight generation (Fyn's daily card on dashboard)
- **Measure:** PWA install rate, mobile AI chat usage, notification opt-in, deep link clicks

### Phase 2: Capacitor Hybrid + Enhanced AI (Weeks 5-14) — MEDIUM RISK

*Only if Phase 1 metrics justify it:*

- Wrap Vue.js app with Capacitor
- Add biometric authentication (Face ID / Touch ID)
- Add native push notifications (FCM/APNs)
- Add haptic feedback (goal milestones, chat sends, saves)
- Implement Universal Links / App Links for deep linking
- Add voice input to Fyn chat (speech-to-text → AI)
- Add conversation continuity (same threads on mobile and web)
- Submit to App Store and Google Play
- **Measure:** Downloads, retention, biometric usage, AI engagement, ratings

### Phase 3: Enhanced Native + AI Features (Weeks 15-24) — OPTIONAL

*Only if Phase 2 shows strong adoption:*

- Home screen widgets (net worth, goal progress, Fyn's daily insight)
- Quick Actions (3D Touch: "Ask Fyn", "Check Goals")
- Siri/Google Assistant: "Hey Siri, ask Fyn what my net worth is"
- Document scanning with AI-powered data extraction
- Proactive AI notifications ("Your pension contributions this year are below your Annual Allowance — you have £X remaining")
- Apple Watch / Wear OS companion (net worth, goal progress, alerts)
- Advanced offline AI (small local model for basic questions)

---

## 6. Design System Application to Mobile

All mobile UI follows `fynlaDesignGuide.md` v1.2.0:

### Colour Palette (unchanged)
- CTAs/buttons: `raspberry-500` (#E83E6D)
- Text/navigation: `horizon-500` (#1F2A44)
- Success/growth: `spring-500` (#20B486)
- Warnings/focus: `violet-500` (#5854E6)
- Hover/subtle: `savannah-100` (#FDFAF7)
- Backgrounds: `eggshell-500` (#F7F6F4)
- Banned: amber, orange, mustard, neons, pure black

### Typography
- iOS: SF Pro (system) with Fynla weight mapping (900 display, 700 headings)
- Android: Roboto (system) with same weight mapping
- Web views within app: Segoe UI / Inter (existing)
- Minimum body text: 16px (prevents iOS auto-zoom on inputs)

### Mobile-Specific Patterns
- Bottom tab bar: `bg-white border-t border-light-gray`, active: `raspberry-500`, inactive: `neutral-500`
- AI chat: `eggshell-500` background, user bubbles `raspberry-50`, Fyn bubbles `white`
- Summary cards: `bg-white rounded-xl shadow-sm`, key metric `text-3xl font-black text-horizon-500`
- Pull-to-refresh: `raspberry-500` spinner
- Toast notifications: Top-positioned (avoid bottom bar overlap)
- Education cards: `bg-savannah-100 rounded-lg`, book icon in `violet-500`
- Deep link buttons: `text-raspberry-500 underline` with external link icon

---

## 7. Key Decisions Needed

1. **PWA first?** Recommendation: Yes — proves demand at minimal cost, AI chat already works on mobile
2. **AI as primary mobile interaction?** Recommendation: Yes — conversational finance is the mobile differentiator
3. **Biometric priority:** Important but not essential for PWA phase. Critical for Capacitor phase
4. **Education content:** Build in-house or use AI-generated content? Recommendation: AI-generated from user context, reviewed for accuracy
5. **Payment strategy:** Keep Revolut for web, use "reader app" exemption for app store. No in-app purchases
6. **v1.0 gate:** PWA can launch now (low risk). Capacitor should wait for v1.0
7. **Voice input:** Add to Fyn chat? High engagement potential on mobile, moderate development effort
8. **Daily insights:** AI-generated daily card? Requires backend cron job + AI call per active user
9. **Document scanning:** Build OCR pipeline for pension statements? High value, high complexity — defer to Phase 3
10. **Widget strategy:** Net worth widget is highest value. Goal progress second. Fyn insight third
