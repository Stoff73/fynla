# Mobile Module Detail Pages — Design Spec

**Date:** 2026-03-12
**Branch:** `mobileImprovement`
**Scope:** Read-only data views for all 7 financial modules in the Capacitor iOS mobile app

---

## Goal

Replace the current mobile module summary stubs (which show a single metric and a "View full detail on web" button) with full read-only detail pages that display all data available on the web app. Remove all references to viewing data on the website.

## Approach: Progressive Depth (Phase 1 — Read-Only)

- Full read-only views for all 7 modules
- Card-based accordion layout for section organisation
- Mix of charts (high-impact visuals) and formatted data rows (everything else)
- Data loaded from existing web Vuex stores — no new API endpoints
- Forms/editing and analysis tools deferred to a future phase

---

## Architecture

### Data Flow

```
Dashboard card tap / More menu tap
  → /m/module/:module
    → Route resolves to e.g. ProtectionDetail.vue
      → Dispatches existing Vuex store action (e.g. protection/fetchPolicies)
        → Renders accordion sections with loaded data
```

Each detail page checks if its Vuex store is populated. If not (direct navigation), it dispatches the fetch action before rendering. This reuses all existing API services and store modules — no backend changes needed.

### Individual Module Detail Pages

Each module gets its own view because data shapes differ significantly. A shared `MobileAccordionSection` component provides consistent expand/collapse behaviour.

### New Files

```
resources/js/mobile/views/
  ProtectionDetail.vue
  SavingsDetail.vue
  InvestmentDetail.vue
  RetirementDetail.vue
  EstateDetail.vue
  GoalsDetail.vue
  CoordinationDetail.vue

resources/js/mobile/components/
  MobileAccordionSection.vue     — Shared expand/collapse card
  MobileDataRow.vue              — Label + value row (currency/percentage/text formatting)
  MobileAllocationChart.vue      — Donut chart (Investment allocation, Estate asset mix)
  MobileProjectionChart.vue      — Line chart (Retirement projection, Investment growth)
  MobilePolicyCard.vue           — Protection policy display card
  MobileAccountCard.vue          — Savings/Investment account display card
  MobilePensionCard.vue          — DC/DB/State pension display card
  MobileHoldingRow.vue           — Investment holding table row
  MobileEstateAssetCard.vue      — Estate asset display card
  MobileTrustCard.vue            — Trust display card
  MobileGiftCard.vue             — Gift history display card
```

### Modified Files

```
resources/js/router/index.js                — Add /m/module/:module routes, remove /m/more/summary/:module
resources/js/mobile/views/MobileDashboard.vue — ModuleSummaryCard links to /m/module/:module
resources/js/mobile/views/MoreMenu.vue        — Module grid links to /m/module/:module, remove "Open full web app"
resources/js/mobile/views/MobileGoalsList.vue — Update empty state text
```

### Removed Files

```
resources/js/mobile/views/ModuleSummary.vue  — Replaced by individual detail pages
```

---

## Navigation

- **Dashboard module cards** → `/m/module/protection`, `/m/module/savings`, etc.
- **More menu module grid** → Same routes
- **Back button** → Returns to previous page (dashboard or More menu)
- Settings links (Account, Security, Subscription, Privacy, Help, About) remain as external web links — these are admin functions, not data views

---

## Module Detail Pages

### Shared Structure

Every detail page follows this pattern:

```
┌─────────────────────────┐
│  ← Back     Module Name │  (header)
├─────────────────────────┤
│  🛡️                     │
│  Module Label           │  (hero card)
│  Hero Metric (large)    │
│  Subtitle / status      │
├─────────────────────────┤
│  Fyn summary card       │  (horizon-500 bg, white text)
├─────────────────────────┤
│  ▼ Section 1            │  (accordion, open by default)
│    Card / row content   │
├─────────────────────────┤
│  ▶ Section 2            │  (accordion, collapsed)
├─────────────────────────┤
│  ▶ Section 3            │  (accordion, collapsed)
└─────────────────────────┘
```

First accordion section opens by default. Others collapsed.

### Protection Detail

**Hero:** Total coverage value + gaps count badge
**Fyn:** Dynamic summary from store (e.g., "You have 2 protection gaps that may need attention.")

| Section | Content | Component |
|---------|---------|-----------|
| Policies | Card per policy: type icon, provider, coverage amount, monthly premium, expiry date, status badge | `MobilePolicyCard` |
| Coverage Analysis | Data rows: income multiple covered, recommended multiple, shortfall amount, income protection status | `MobileDataRow` |
| Gaps & Recommendations | List of identified gaps with priority badges (high/medium/low), recommendation text | `MobileDataRow` |

**Store:** `protection` — actions: `fetchPolicies`, `fetchAnalysis`

### Savings Detail

**Hero:** Total savings + account count
**Fyn:** Dynamic summary (e.g., "Your emergency fund covers 2.3 months — building towards 3-6 months is recommended.")

| Section | Content | Component |
|---------|---------|-----------|
| Accounts | Card per account: provider, balance, account type badge (ISA/GIA/etc.), interest rate, ownership type | `MobileAccountCard` |
| Emergency Fund | Data rows: total emergency savings, months covered, target months, status, adequacy | `MobileDataRow` |
| ISA Allowance | Data rows: current year contributions, remaining allowance (£20,000 limit), breakdown by account | `MobileDataRow` |

**Store:** `savings` — actions: `fetchAccounts`, `fetchAnalysis`

### Investment Detail

**Hero:** Portfolio value + YTD return percentage
**Fyn:** Dynamic summary

| Section | Content | Component |
|---------|---------|-----------|
| Accounts | Card per account: platform/provider, value, type badge (ISA/SIPP/GIA), risk profile, holdings count | `MobileAccountCard` |
| Holdings | Row per holding: security name, value, allocation %, return %. Grouped by account | `MobileHoldingRow` |
| Allocation | Donut chart showing asset class split + breakdown rows (Equities, Fixed Income, Alternatives, Cash, etc.) | `MobileAllocationChart` + `MobileDataRow` |
| Performance | Data rows: total return (£ and %), annualised return, benchmark comparison, YTD return | `MobileDataRow` |
| Fees | Data rows: total annual fees, fee drag %, per-account fee breakdown | `MobileDataRow` |

**Store:** `investment` — actions: `fetchAccounts`, `fetchAnalysis`

### Retirement Detail

**Hero:** Projected retirement income + years to retirement
**Fyn:** Dynamic summary (e.g., "Your projected retirement income is £8,000 below your target.")

| Section | Content | Component |
|---------|---------|-----------|
| DC Pensions | Card per pension: scheme name, provider, fund value, monthly contribution (employee + employer), projected value at retirement | `MobilePensionCard` |
| DB Pensions | Card per pension: scheme name, employer, annual income, accrual rate, normal retirement age, spouse benefit | `MobilePensionCard` |
| State Pension | Data rows: weekly forecast, annual equivalent, qualifying years (X of 35), state pension age | `MobileDataRow` |
| Projections | Line chart (income by source over time) + key metrics: projected annual income, income replacement %, target income, income gap | `MobileProjectionChart` + `MobileDataRow` |
| Annual Allowance | Data rows: current usage, remaining allowance, standard vs tapered, carry forward available | `MobileDataRow` |

**Store:** `retirement` — actions: `fetchPensions`, `fetchProjections`, `fetchAnalysis`

### Estate Detail

**Hero:** Net estate value + IHT liability
**Fyn:** Dynamic summary (e.g., "Your estate has an estimated inheritance tax liability of £45,000.")

| Section | Content | Component |
|---------|---------|-----------|
| Estate Assets | Card per asset: type (property/collectible/business), description, value, ownership | `MobileEstateAssetCard` |
| IHT Analysis | Data rows: gross estate, NRB (£325k), RNRB (£175k), taxable estate, IHT liability, effective tax rate | `MobileDataRow` |
| Gifts | Card per gift: recipient, value, date, type (PET/exempt), years since gift, taper relief % | `MobileGiftCard` |
| Trusts | Card per trust: name, type, value, settlor, trustees, beneficiaries | `MobileTrustCard` |

**Store:** `estate` — actions: `fetchAssets`, `fetchAnalysis`, `fetchGifts`, `fetchTrusts`

### Goals Detail

**Hero:** Goals completed (X of Y) + total saved
**Fyn:** Dynamic summary

| Section | Content | Component |
|---------|---------|-----------|
| Active Goals | Card per goal: progress ring, name, current/target amounts, deadline, status badge, linked module | Existing `MobileGoalCard` |
| Completed Goals | Same card style, completed state | Existing `MobileGoalCard` |
| Life Events | Card per event: type icon, name, date, priority badge, description, linked goals count | New event card rows |

**Store:** `goals` — actions: `fetchGoals`, `fetchLifeEvents`

**Note:** Tapping a goal card navigates to the existing `MobileGoalDetail` page (with contributions and milestones). This existing page stays as-is.

### Coordination Detail

**Hero:** Net worth + overall health status
**Fyn:** Dynamic summary

| Section | Content | Component |
|---------|---------|-----------|
| Financial Plans | Card per plan: plan name, type, completeness bar, last updated | Data rows with progress indicator |
| Cross-Module Insights | List of key recommendations spanning multiple modules, priority-ordered | `MobileDataRow` |
| Net Worth Breakdown | Asset/liability breakdown rows: property, pensions, investments, savings, liabilities | `MobileDataRow` |

**Store:** `coordination` (if exists) or aggregate from other stores

---

## Charts

Only 2 chart components, used sparingly:

### MobileAllocationChart
- Small donut chart (200px diameter)
- Used by: Investment (asset allocation), Estate (asset mix)
- Legend below chart as coloured rows
- Uses `designSystem.js` `CHART_COLORS`
- Library: VueApexCharts (already in project)

### MobileProjectionChart
- Line chart (full width, ~200px height)
- Used by: Retirement (income projection by source)
- Simplified axes, touch-friendly tooltips
- Library: VueApexCharts (already in project)

Everything else uses `MobileDataRow` formatted values.

---

## Shared Components

### MobileAccordionSection
```
Props:
  - title: String (section heading)
  - icon: String (optional emoji)
  - defaultOpen: Boolean (default: false)
  - badge: String/Number (optional count badge)

Slots:
  - default (section content)

Behaviour:
  - Tap header to expand/collapse
  - Smooth height transition (use global .expand-* classes from app.css)
  - Chevron rotates on expand
```

### MobileDataRow
```
Props:
  - label: String
  - value: String/Number
  - type: 'currency' | 'percentage' | 'text' | 'status' (default: 'text')
  - status: 'good' | 'warning' | 'danger' (optional, adds colour to value)

Renders: Label left-aligned, value right-aligned, single row
Uses currencyMixin for formatting
```

---

## Removals

| Item | Location | Action |
|------|----------|--------|
| `ModuleSummary.vue` | `mobile/views/` | Delete file |
| `/m/more/summary/:module` route | `router/index.js` | Remove route |
| "View full detail on web" button | Was in `ModuleSummary.vue` | Gone with file deletion |
| "Open full web app" button | `MoreMenu.vue` | Remove |
| "add your financial details on the web app" | `MobileDashboard.vue` empty state | Replace with "No data yet" |
| "Set up your financial goals on the web app" | `MobileGoalsList.vue` empty state | Replace with "No goals yet" |
| `Browser.open()` calls for module paths | `ModuleSummary.vue`, `MoreMenu.vue` | Remove |

Settings links in `SettingsList.vue` (Account, Security, Subscription, Privacy, Help, About) **stay as web links** — these are admin functions.

---

## Empty States

When a module has no data, show:
- Module icon (large)
- "No [policies/accounts/pensions/etc.] added yet"
- No reference to the web app

---

## Design System Compliance

- Colours: `raspberry-500` (CTAs), `horizon-500` (text/headers), `spring-500` (good status), `violet-500` (warning), `neutral-500` (muted text), `light-gray` (borders), `eggshell-500` (backgrounds)
- Typography: Segoe UI, font-weight 900 for hero metrics, 700 for section headers
- Cards: `bg-white rounded-xl border border-light-gray`
- Fyn card: `bg-horizon-500 rounded-xl` with white text
- Status badges: `spring-500` (good), `violet-500` (warning), `raspberry-500` (action needed)
- Accordion transitions: Use global `.expand-*` classes
- No amber/orange colours
- No scores in UI
- British spelling in user-facing text
