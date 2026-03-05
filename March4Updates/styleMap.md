# Fynla Style Map

**Generated:** 04 March 2026
**Purpose:** Complete inventory of every style source in the Fynla codebase — CSS files, Tailwind config, JS design tokens, Vue component `<style>` blocks, inline styles, dynamic style bindings, Blade templates, and email styles.

---

## Table of Contents

1. [Style Architecture Overview](#1-style-architecture-overview)
2. [CSS Source Files](#2-css-source-files)
3. [Tailwind Configuration](#3-tailwind-configuration)
4. [PostCSS Configuration](#4-postcss-configuration)
5. [Vite Configuration (CSS)](#5-vite-configuration-css)
6. [JavaScript Design Tokens](#6-javascript-design-tokens)
7. [Font Loading](#7-font-loading)
8. [Blade Template Styles](#8-blade-template-styles)
9. [Email Template Styles](#9-email-template-styles)
10. [Vue Component Style Blocks](#10-vue-component-style-blocks)
11. [Inline style="" Attributes](#11-inline-style-attributes)
12. [Dynamic :style= Bindings](#12-dynamic-style-bindings)
13. [Build Output](#13-build-output)
14. [Issues & Inconsistencies](#14-issues--inconsistencies)

---

## 1. Style Architecture Overview

```
Google Fonts (CDN)
    |
    v
resources/css/app.css          <-- Main entry point (Tailwind directives + custom classes)
resources/css/badges.css       <-- Badge variant classes (@apply-based)
    |
    v
tailwind.config.js             <-- Custom theme (colors, fonts, spacing, shadows, radius)
postcss.config.js              <-- Tailwind + Autoprefixer
    |
    v
vite.config.js                 <-- Bundles CSS, code-splits per lazy-loaded route
    |
    v
public/build/assets/*.css      <-- 62 compiled CSS files (1 main + 61 component chunks)

resources/js/constants/designSystem.js   <-- JS-side color tokens for charts & programmatic use
resources/js/components/**/*.vue         <-- 207 files with <style> blocks (204 scoped, 3 global)
resources/views/emails/*.blade.php       <-- 13 email templates with inline CSS
```

| Source Type | Count | Location |
| --- | --- | --- |
| CSS files | 2 | `resources/css/` |
| Tailwind config | 1 | `tailwind.config.js` |
| PostCSS config | 1 | `postcss.config.js` |
| JS design tokens | 1 | `resources/js/constants/designSystem.js` |
| Vue `<style>` blocks | 207 | `resources/js/components/` and `resources/js/views/` |
| Vue `<style>` (unscoped/global) | 3 | GuidanceTooltip, LetterToSpouse, GiftingTimelineChart |
| Inline `style=""` | 84 files | Across components and views |
| Dynamic `:style=` | 70 files | Across components and views |
| Blade templates with styles | 14 | `resources/views/` |
| Email templates with inline CSS | 13 | `resources/views/emails/` |
| SCSS/SASS files | 0 | None exist |
| CSS-in-JS libraries | 0 | None used |

---

## 2. CSS Source Files

### 2.1 Main Stylesheet: `resources/css/app.css`

The single CSS entry point loaded by Vite. Contains all global styles.

**Imports:**
- Google Fonts: Inter (300-700), Plus Jakarta Sans (400-700), JetBrains Mono (400-500)
- `@tailwind base`, `@tailwind components`, `@tailwind utilities`

**`@layer base` — Global element styles:**

| Selector | Styles |
| --- | --- |
| `html, body` | `background-color: #F9FAFB !important` |
| `body` | `@apply font-sans text-gray-900` |
| `h1` | `@apply font-display text-h1` |
| `h2` | `@apply font-display text-h2` |
| `h3` | `@apply font-display text-h3` |
| `h4` | `@apply font-display text-h4` |
| `h5` | `@apply font-display text-h5` |

**`@layer components` — Reusable CSS classes:**

| Class | Description |
| --- | --- |
| `.btn-primary` | `bg-primary-600`, white text, `rounded-button`, shadow, hover/active states |
| `.btn-secondary` | White bg, gray-700 text, gray-300 border, shadow |
| `.btn-outline` | Transparent bg, `primary-600` text/border, hover `primary-50` |
| `.btn-danger` | `bg-error-600`, white text |
| `.btn-sm` | Size modifier: `px-3 py-1 text-xs` |
| `.btn:disabled` | `opacity-50 cursor-not-allowed` |
| `.card` | White bg, `rounded-card`, `border-gray-200`, `shadow-sm`, `p-6` |
| `.card.cursor-pointer` | Adds `hover:shadow-md hover:-translate-y-0.5 hover:border-primary-400` |
| `.card-hover` | Card with cursor-pointer and hover lift baked in |
| `.card-lg` | `rounded-xl p-8` variant |
| `.card-sm` | `rounded-lg p-4` variant |
| `.card-highlighted` | `bg-primary-50 border-primary-200` |
| `.card-success` | `bg-green-50 border-green-200` |
| `.card-warning` | `bg-blue-50 border-blue-200` |
| `.card-error` | `bg-red-50 border-red-200` |
| `.input-field` | Full width, `rounded-button`, `focus:ring-primary-500` |
| `.label` | `text-body-sm font-medium text-gray-700 mb-2` |
| `.form-group` | `mb-4` |
| `.form-input` | `border-gray-300 rounded-md`, focus ring `primary-500` |
| `.form-hint` | `text-xs text-gray-500 mt-1` |
| `.badge-success` | `bg-success-100 text-success-700`, pill shape |
| `.badge-warning` | `bg-blue-100 text-blue-700` (blue, not amber — by design) |
| `.badge-error` | `bg-error-100 text-error-700` |
| `.badge-info` | `bg-info-100 text-info-700` |
| `.modal-overlay` | Fixed, `bg-black/50`, flex center, `z-50` |
| `.modal` | White bg, `rounded-lg`, `max-w-md`, `shadow-xl` |
| `.modal-header` | Header with bottom border |
| `.modal-body` | Body padding |
| `.modal-footer` | Footer with top border |
| `.modal-content` | `rounded-card shadow-modal` |
| `.close-btn` | Bare button, `text-2xl`, hover darkens |
| `.detail-inline` | `animation: fadeIn 0.3s ease-out` |
| `.detail-inline-back` | Inline back-navigation button |
| `.chart-card` | Chart container, `cursor-pointer`, hover border `primary-500` |
| `.chart-title` | `text-lg font-semibold text-gray-900 mb-1` |
| `.chart-subtitle` | `text-sm text-gray-500 mb-4` |
| `.dropdown-menu` | `rounded-lg border-gray-200 shadow-lg py-1` |
| `.transition-standard` | `transition-all duration-200 ease-out` |
| `.transition-fast` | `transition-all duration-150 ease-out` |
| `.transition-slow` | `transition-all duration-300 ease-out` |
| `.focus-ring` | `focus:ring-2 focus:ring-primary-500 focus:ring-opacity-20` |
| `.focus-ring-visible` | `focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2` |
| `.code-input` | MFA input: `w-40 py-3 text-2xl text-center tracking-widest font-mono` |
| `.error-message` | `text-red-600 text-sm mb-4` |

**`@layer utilities`:**
- `.text-balance` — `text-wrap: balance`

**Keyframes:**
- `@keyframes fadeIn` — opacity 0 to 1

**Print styles (`@media print`):**
- Hides nav, footer, preview banners, side menu
- Removes shadows, forces white backgrounds
- A4 page layout with margins
- Prevents page breaks inside cards
- `print-color-adjust: exact` for background colour preservation

**Other:**
- `.pdf-header { display: none; }` — hidden on screen, shown in PDF print

---

### 2.2 Badge Stylesheet: `resources/css/badges.css`

Dedicated badge variants using `@apply` throughout. Imported into the build pipeline.

**Base sizing:**

| Class | Sizing |
| --- | --- |
| `.badge` | `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium` |
| `.badge-sm` | `px-2 py-0.5 text-xs` |
| `.badge-md` | `px-3 py-1 text-sm` |
| `.badge-lg` | `px-4 py-1.5 text-base` |

**Account type badges** (white bg, coloured border):

| Class | Colour |
| --- | --- |
| `.badge-isa` | blue-700 text, blue-500 border |
| `.badge-sipp` | purple-700 text, purple-500 border |
| `.badge-gia` | gray-700 text, gray-400 border |
| `.badge-pension`, `.badge-workplace-pension` | indigo-700 text, indigo-500 border |
| `.badge-nsi`, `.badge-premium-bonds`, `.badge-bond` | green-700 text, green-500 border |
| `.badge-vct`, `.badge-eis` | teal-700 text, teal-500 border |

**Ownership badges** (white bg, coloured border):

| Class | Colour |
| --- | --- |
| `.badge-individual` | blue-700 text, blue-500 border |
| `.badge-joint` | purple-700 text, purple-500 border |
| `.badge-trust` | indigo-700 text, indigo-500 border |

**Status badges** (solid bg, white text):

| Class | Background |
| --- | --- |
| `.badge-active` | green-600 |
| `.badge-pending` | blue-500 |
| `.badge-completed` | blue-600 |
| `.badge-expired` | red-600 |
| `.badge-draft` | gray-500 |

**Priority badges:**

| Class | Background |
| --- | --- |
| `.badge-high-priority` | red-600 |
| `.badge-medium-priority` | blue-500 |
| `.badge-low-priority` | green-600 |

**Risk level badges** (white bg, coloured border):

| Class | Colour |
| --- | --- |
| `.badge-risk-low` | green-700 text, green-500 border |
| `.badge-risk-medium` | blue-700 text, blue-500 border |
| `.badge-risk-high` | red-700 text, red-500 border |

**Tax efficiency badges** (white bg, coloured border):

| Class | Colour |
| --- | --- |
| `.badge-tax-free` | green-700 text, green-500 border |
| `.badge-taxable` | red-700 text, red-500 border |
| `.badge-tax-deferred` | blue-700 text, blue-500 border |

---

## 3. Tailwind Configuration

**File:** `tailwind.config.js`

**Content paths scanned:** `resources/**/*.blade.php`, `resources/**/*.js`, `resources/**/*.vue`

### Custom Colours

**`primary` (Trust Blue & Navy):**

| Token | Hex | Usage |
| --- | --- | --- |
| primary-50 | #FFFFFF | Light backgrounds |
| primary-100 | #F1F5F9 | Subtle backgrounds |
| primary-200 | #E2E8F0 | Borders |
| primary-300 | #CBD5E1 | Disabled |
| primary-400 | #94A3B8 | Placeholder |
| primary-500 | #3B82F6 | Accent Blue |
| primary-600 | #1257A0 | **Main Brand (Trust Blue)** |
| primary-700 | #0E3A66 | Deep Navy |
| primary-800 | #0B2C4F | Dark |
| primary-900 | #051B33 | Darkest |
| primary-950 | #020617 | Near-black |

**`secondary` (Slate neutrals):**

| Token | Hex |
| --- | --- |
| secondary-50 | #FFFFFF |
| secondary-100 | #F1F5F9 |
| secondary-200 | #E2E8F0 |
| secondary-500 | #64748B |
| secondary-600 | #475569 |
| secondary-700 | #334155 |
| secondary-800 | #1E293B |
| secondary-900 | #0F172A |

**Semantic colours:**

| Scale | 50 | 100 | 500 | 600 | 700+ |
| --- | --- | --- | --- | --- | --- |
| success | #FFFFFF | #F0FDF4 | #15803D | #166534 | #14532D |
| error | #FFFFFF | #FEF2F2 | #EF4444 | #B91C1C | #991B1B |
| warning | #EFF6FF | #DBEAFE | #3B82F6 | #2563EB | #1D4ED8 |
| info | #FFFFFF | #F0F9FF | #0EA5E9 | #0284C7 | #0369A1 |

**`chart` colours (data visualisation):**

| Token | Hex | Name |
| --- | --- | --- |
| chart-1 | #1257A0 | Trust Blue |
| chart-2 | #475569 | Slate |
| chart-3 | #15803D | Green |
| chart-4 | #60A5FA | Blue-neutral |
| chart-5 | #B91C1C | Red |
| chart-6 | #7C3AED | Purple |
| chart-7 | #3B82F6 | Blue-tertiary |
| chart-8 | #0F172A | Navy |

### Custom Font Families

| Token | Stack |
| --- | --- |
| `font-sans` | Inter, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif |
| `font-display` | Plus Jakarta Sans, Inter, sans-serif |
| `font-mono` | JetBrains Mono, Courier New, monospace |

### Custom Font Sizes

| Token | Size | Line Height | Letter Spacing | Weight |
| --- | --- | --- | --- | --- |
| `text-display` | 3.75rem | 1.1 | -0.02em | 700 |
| `text-h1` | 2.25rem | 1.2 | -0.01em | 700 |
| `text-h2` | 1.875rem | 1.3 | — | 600 |
| `text-h3` | 1.5rem | 1.4 | — | 600 |
| `text-h4` | 1.25rem | 1.5 | — | 600 |
| `text-h5` | 1rem | 1.5 | — | 600 |
| `text-body-lg` | 1.125rem | 1.7 | — | — |
| `text-body` | 1rem | 1.6 | — | — |
| `text-body-sm` | 0.875rem | 1.5 | — | — |
| `text-caption` | 0.75rem | 1.4 | — | — |

### Custom Spacing

| Token | Value |
| --- | --- |
| `128` | 32rem |
| `144` | 36rem |

### Custom Border Radius

| Token | Value |
| --- | --- |
| `rounded-card` | 0.75rem (12px) |
| `rounded-button` | 0.5rem (8px) |

### Custom Box Shadows

| Token | Value |
| --- | --- |
| `shadow-card` | `0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06)` |
| `shadow-card-hover` | `0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)` |
| `shadow-modal` | `0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)` |

### Safelist (PurgeCSS protection)

Dynamically-applied risk-level classes that must not be purged:
- Green: `bg-green-50/100/600`, `text-green-700/800`, `border-green-200`, `ring-green-400`
- Teal: `bg-teal-50/100/600`, `text-teal-700/800`, `border-teal-200`, `ring-teal-400`
- Blue: `bg-blue-50/100/600`, `text-blue-700/800`, `border-blue-200`, `ring-blue-400`
- Red: `bg-red-50/100/600`, `text-red-700/800`, `border-red-200`, `ring-red-400`
- Orange explicitly removed (comment: "Orange removed - use blue for warnings per design system")

### Plugins

None.

---

## 4. PostCSS Configuration

**File:** `postcss.config.js`

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

Standard Tailwind + Autoprefixer only. No custom PostCSS plugins.

---

## 5. Vite Configuration (CSS)

**File:** `vite.config.js`

- CSS entry point: `resources/css/app.css`
- JS entry point: `resources/js/app.js`
- Output directory: `public/build/`
- No explicit `css:` configuration block
- `sourcemap: false`
- CSS is code-split by Vite per lazy-loaded route/component
- Uses `@vitejs/plugin-vue` and `laravel-vite-plugin`

---

## 6. JavaScript Design Tokens

**File:** `resources/js/constants/designSystem.js`

The single source of truth for all programmatic style tokens used in Vue components (charts, risk indicators, dynamic colours). Imported by **60 Vue components**.

### Colour Exports

| Export | Description |
| --- | --- |
| `PRIMARY_COLORS` | Trust Blue/Deep Navy scale (50-900), key: `600: '#1257A0'` |
| `SECONDARY_COLORS` | Slate neutral scale |
| `SUCCESS_COLORS` | Green (`500: '#15803D'`) |
| `ERROR_COLORS` | Red (`500: '#EF4444'`, `600: '#B91C1C'`) |
| `WARNING_COLORS` | Blue, not amber (`500: '#3B82F6'`) |
| `INFO_COLORS` | Sky blue (`500: '#0EA5E9'`) |
| `CHART_COLORS` | 8-colour ordered array for multi-series charts |
| `ASSET_COLORS` | Keyed by type: pensions, property, investments, cash, business, chattels |
| `SPENDING_COLORS` | 16-colour array for expenditure donut chart |
| `RISK_COLORS` | Per-risk-level hex objects (bg, bgLight, border, borderLight, text) |
| `RISK_TAILWIND_CLASSES` | Per-risk-level Tailwind class objects (bg, text, border, combined) |
| `TEXT_COLORS` | Hex for primary/secondary/tertiary/muted/placeholder/disabled text |
| `BG_COLORS` | page, card, subtle, overlay |
| `BORDER_COLORS` | default, hover, focus, error, success |

### Non-Colour Tokens

| Export | Description |
| --- | --- |
| `RISK_DISPLAY_NAMES` | Human-readable risk level names |
| `RISK_ABBREVIATED_LABELS` | Short risk labels |
| `RISK_DESCRIPTIONS` | Risk level descriptions |
| `RISK_LEGACY_MAP` | Maps old cautious/balanced/adventurous to new system |
| `CHART_DEFAULTS` | Default ApexCharts config (Inter font, grid, axes) |
| `SPACING` | xs through 2xl rem values |
| `BORDER_RADIUS` | none/sm/md/button/card/lg/xl/full rem values |
| `ANIMATION` | Durations (fast 150ms, default 200ms, slow 300ms, slower 500ms) and easing |

### Helper Functions

| Function | Purpose |
| --- | --- |
| `getRiskClasses(level)` | Returns Tailwind class object for a risk level |
| `getRiskDisplayName(level)` | Returns human name for risk level |
| `normalizeRiskLevel(level)` | Maps legacy to new risk level names |
| `getColorByThreshold(value, thresholds)` | Returns success/warning/error colour by percentage |
| `getValueColor(value)` | Returns green/red/muted for positive/negative/zero |

### Other Constants Files (non-style)

| File | Content |
| --- | --- |
| `resources/js/constants/taxConfig.js` | UK tax figures, no visual tokens |
| `resources/js/constants/goalIcons.js` | Emoji icon mappings, no visual tokens |
| `resources/js/constants/eventIcons.js` | Life event icon names, no visual tokens |
| `resources/js/constants/eventIconSvgs.js` | Inline SVGs for event icons, no colour tokens |

---

## 7. Font Loading

| Source | Fonts | Loaded In |
| --- | --- | --- |
| Google Fonts CDN | Inter (300-700), Plus Jakarta Sans (400-700), JetBrains Mono (400-500) | `resources/css/app.css` `@import` |
| Bunny Fonts CDN | Figtree 400, 600 | `resources/views/welcome.blade.php` (unused Laravel placeholder) |
| System font stack | -apple-system, BlinkMacSystemFont, Segoe UI, Roboto | Email templates (inline CSS) |

No `@font-face` declarations or locally-hosted font files exist in the codebase.

---

## 8. Blade Template Styles

### 8.1 Main SPA Shell: `resources/views/app.blade.php`

- Loads CSS via `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- No `<style>` block
- `<body>` has `class="antialiased bg-gray-50"` and `style="background-color: #F9FAFB;"` (redundant safeguard)

### 8.2 Laravel Placeholder: `resources/views/welcome.blade.php`

- **Not part of the Fynla app** — default Laravel welcome page
- Loads Figtree font from Bunny Fonts CDN
- Contains massive inline `<style>` block with minified Tailwind v3.2.4 CSS
- Can be safely ignored

---

## 9. Email Template Styles

**Location:** `resources/views/emails/*.blade.php` (13 templates)

All email templates use inline `<style>` blocks for email-client compatibility.

| Template | Purpose |
| --- | --- |
| `verification-code.blade.php` | Email verification code |
| `password-reset-code.blade.php` | Password reset |
| `spouse-account-created.blade.php` | Spouse account creation |
| `spouse-account-linked.blade.php` | Spouse account linking |
| `deletion-verification-code.blade.php` | Data deletion verification |
| `data-deletion-confirmation.blade.php` | Data deletion confirmed |
| `data-retention-warning.blade.php` | Data retention warning |
| `trial-expiration-reminder.blade.php` | Trial expiry reminder |
| `bug-report.blade.php` | Bug report notification |
| `payment-confirmation.blade.php` | Payment confirmed |
| `subscription-cancellation.blade.php` | Subscription cancelled |
| `subscription-renewal-reminder.blade.php` | Subscription renewal |

**Shared email style characteristics:**
- Font stack: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif` (no web fonts)
- Colour tokens: `#3b82f6` (blue-500), `#1e40af` (blue-800), `#166534` (green-700), `#f0f9ff`, `#f0fdf4`, `#6b7280`, `#f9fafb`
- All consistent with the design system
- No Tailwind (email clients do not support it)

---

## 10. Vue Component Style Blocks

**207 files** contain `<style>` tags across the codebase. **204 are scoped**, **3 are unscoped (global)**.

### 10.1 Unscoped (Global) Style Blocks

These emit CSS that affects the entire page:

| File | Reason | Content |
| --- | --- | --- |
| `components/Guidance/GuidanceTooltip.vue` | Needs to highlight arbitrary DOM elements | `.guidance-highlight` class with `@keyframes guidance-pulse` animation |
| `components/UserProfile/LetterToSpouse.vue` | Styles injected into separate PDF print window | Full print layout: `@page`, `@media print`, `.header`, `.logo`, `.section`, `.card-grid`, `.badge-*`, `.footer` |
| `components/Estate/GiftingTimelineChart.vue` | ApexCharts injects tooltip HTML outside Vue scoping | `.custom-tooltip`, `.tooltip-header`, `.tooltip-body`, `.tooltip-row` |

### 10.2 Scoped Style Blocks by Module

#### Protection (13 files)

| File | CSS Summary |
| --- | --- |
| `Protection/CurrentSituation.vue` | Responsive grid gap at `max-width: 640px` |
| `Protection/PolicyDetail.vue` | `.back-button` with flex, hover effects using `@apply` |
| `Protection/PolicyCard.vue` | `.policy-card` transition; mobile column flip at 640px |
| `Protection/PremiumBreakdownChart.vue` | `.premium-breakdown-chart` width + min-height |
| `Protection/CoverageAdequacyGauge.vue` | `.coverage-adequacy-gauge` max-width + centring |
| `Protection/CoverageGapChart.vue` | `.coverage-gap-chart` width + min-height |
| `Protection/PolicyFormModal.vue` | Custom thin scrollbar (webkit + Firefox) |
| `Protection/RecommendationCard.vue` | Card transition; `.line-clamp-2`; mobile column layout |
| `Protection/ProtectionOverviewCard.vue` | Full card layout: `.card-header`, `.policy-sections`, badge classes, `.status-banner`, responsive |
| `Protection/WhatIfScenarios.vue` | Responsive grid gap at 640px |
| `Protection/GapAnalysis.vue` | Responsive grid gap at 640px |
| `Protection/ScenarioBuilder.vue` | Custom `input[type="range"]` slider (webkit + moz) |
| `Protection/CoverageTimelineChart.vue` | `.coverage-timeline-chart` width + min-height |

#### Retirement (22 files)

| File | CSS Summary |
| --- | --- |
| `views/Retirement/Recommendations.vue` | `@keyframes fadeIn` + staggered animation |
| `views/Retirement/Projections.vue` | Same fadeIn pattern |
| `views/Retirement/PensionDetail.vue` | Same fadeIn pattern |
| `views/Retirement/WhatIfScenarios.vue` | Custom range slider thumb; hardcoded `#4f46e5` |
| `Retirement/AccumulationChart.vue` | `.accumulation-chart` width |
| `Retirement/AnnualAllowanceTracker.vue` | Progress bar width/colour transition |
| `Retirement/CapitalAdequacyTab.vue` | `.back-button`, fadeIn, progress bar, formula grid, responsive |
| `Retirement/DBPensionForm.vue` | Minimal |
| `Retirement/DCPensionForm.vue` | Minimal |
| `Retirement/DrawdownSimulator.vue` | Minimal |
| `Retirement/FutureValueTab.vue` | Minimal |
| `Retirement/IncomeDrawdownChart.vue` | Chart + `:deep()` ApexCharts tooltip classes |
| `Retirement/IncomeProjectionChart.vue` | Chart sizing |
| `Retirement/IncomeSourceSlider.vue` | `.income-source-card` border/transition, `.source-badge` |
| `Retirement/PensionPotProjectionChart.vue` | Chart sizing |
| `Retirement/RequiredCapitalDetail.vue` | fadeIn, `.back-button`, progress bar, formula grid, responsive |
| `Retirement/RetirementIncomeTab.vue` | fadeIn, `.back-button`, income source cards, projection table, responsive |
| `Retirement/StatePensionForm.vue` | Minimal |
| `Retirement/StrategyCard.vue` | `.strategy-card` hover, coverage badges, slider track, responsive |
| `Retirement/StrategiesTab.vue` | Minimal |
| `Retirement/TargetIncomeDrawdownChart.vue` | Chart sizing |
| `Retirement/TaxBreakdownCard.vue` | Progress bar transitions for tax bands |

#### NetWorth (28 files)

| File | CSS Summary |
| --- | --- |
| `views/NetWorth/NetWorthDashboard.vue` | `.net-worth-dashboard` max-width/overflow |
| `views/NetWorth/CashOverview.vue` | `.account-card`, `.account-item`, `.add-account-btn`; hardcoded hex colours |
| `NetWorth/AssetAllocationDonut.vue` | Height/flex layout, responsive; uses `@apply` |
| `NetWorth/AssetBreakdownBar.vue` | `.chart-title`, `.no-data`, responsive; uses `@apply` |
| `NetWorth/BusinessInterestCard.vue` | Card hover, `.business-type-badge` classes, `.status-badge`, responsive |
| `NetWorth/BusinessInterestDetailInline.vue` | `.back-button` and `.badge` with `@apply` |
| `NetWorth/BusinessInterestsList.vue` | `.list-header`, `.add-button`, `.businesses-grid`; uses `@apply bg-fuchsia-700` |
| `NetWorth/ChattelCard.vue` | Card hover/transition |
| `NetWorth/ChattelDetailInline.vue` | Back button + fade animation |
| `NetWorth/ChattelsList.vue` | List layout |
| `NetWorth/FeesDetail.vue` | Minimal |
| `NetWorth/HoldingsDetail.vue` | Minimal |
| `NetWorth/InvestmentDetailInline.vue` | Back button + fade animation |
| `NetWorth/InvestmentList.vue` | Grid/card layout |
| `NetWorth/InvestmentProjections.vue` | Chart + detail layout |
| `NetWorth/JointAccountHistory.vue` | Table layout |
| `NetWorth/LiabilityCard.vue` | `.liability-card` hover, `.liability-type-badge` classes, `.priority-badge`, responsive |
| `NetWorth/LiabilityDetailInline.vue` | Back button + fade |
| `NetWorth/LiabilitiesList.vue` | List layout |
| `NetWorth/NetWorthOverview.vue` | Overview cards |
| `NetWorth/NetWorthWealthSummary.vue` | Summary layout |
| `NetWorth/PensionDetailInline.vue` | Inline detail layout |
| `NetWorth/PensionList.vue` | Pension list layout |
| `NetWorth/PropertyCard.vue` | Card hover |
| `NetWorth/PropertyList.vue` | List layout |
| `NetWorth/StrategyDetail.vue` | Minimal |
| `NetWorth/TaxEfficiencyDetail.vue` | Minimal |
| `NetWorth/WealthSummary.vue` | Summary layout (CSS Grid) |

#### Investment (39 files)

| File | CSS Summary |
| --- | --- |
| `Investment/AssetAllocationChart.vue` | `.chart-container` width |
| `Investment/AssetLocationOptimizer.vue` | Comment only |
| `Investment/BedAndISATransfers.vue` | Comment only |
| `Investment/BenchmarkComparison.vue` | Comment only |
| `Investment/CGTHarvestingOpportunities.vue` | Comment only |
| `Investment/ContributionPlanner.vue` | Comment only |
| `Investment/DiversificationTab.vue` | `.diversification-tab` width |
| `Investment/EfficientFrontier.vue` | `:deep()` custom ApexCharts tooltip |
| `Investment/FeeBreakdown.vue` | Comment only |
| `Investment/FeeSavingsCalculator.vue` | Comment only |
| `Investment/GeographicAllocationMap.vue` | `.chart-container` width |
| `Investment/GoalProjection.vue` | Comment only |
| `Investment/Goals.vue` | Comment only |
| `Investment/ISAOptimizationStrategy.vue` | Comment only |
| `Investment/InvestmentOverviewCard.vue` | Responsive min-widths |
| `Investment/InvestmentProjectionChart.vue` | `.chart-placeholder`, `.chart-footer` |
| `Investment/InvestmentRecommendationsTracker.vue` | Comment only |
| `Investment/Performance.vue` | `.chart-header`, `.risk-badge-corner`, `.period-selector`, responsive |
| `Investment/PerformanceAttribution.vue` | Comment only |
| `Investment/PerformanceLineChart.vue` | `.chart-container` width |
| `Investment/PortfolioOptimization.vue` | `@keyframes slideIn` for toast notification |
| `Investment/PortfolioOverview.vue` | `.add-account-btn`, `.accounts-grid`, `.account-card`, `.badge`, responsive |
| `Investment/RebalancingActions.vue` | Transition (has CSS typo: `background-colour`) |
| `Investment/RebalancingCalculator.vue` | `@keyframes spin` |
| `Investment/StrategyRecommendationCard.vue` | Border-bottom |
| `Investment/TaxEfficiencyPanel.vue` | Comment only |
| `Investment/TaxOptimization.vue` | Comment only |
| `Investment/TaxOptimizationOverview.vue` | Comment only |
| `Investment/TaxOptimizationRecommendations.vue` | Comment only |
| `Investment/WhatIfScenariosBuilder.vue` | Comment only |
| `Investment/WrapperOptimizer.vue` | Comment only |
| `Investment/AllocationComparison.vue` | Custom scrollbar; hardcoded `#888`, `#555` |
| `Investment/AccountStrategyCard.vue` | `.strategy-section`, `.strategy-grid`, responsive |
| `views/Investment/PortfolioStrategyPanel.vue` | Min-height |
| `views/Investment/AccountRebalancingPanel.vue` | Comment only |
| `views/Investment/AccountPerformancePanel.vue` | Large: sidebar layout, stacked bar, legend, tax status; hardcoded hex |
| `views/Investment/AccountFeesPanel.vue` | `.cost-row`, `.holdings-table`, `.impact-grid`; hardcoded hex |
| `views/Investment/AccountHoldingsPanel.vue` | `.holdings-table`, `.holding-row`, mobile card layout; hardcoded hex |
| `views/Investment/AccountSummaryPanel.vue` | fadeIn, details grid; hardcoded hex |

#### Estate (14 files)

| File | CSS Summary |
| --- | --- |
| `Estate/AssetForm.vue` | Full form layout: `.form-group`, `.form-control`, `.input-with-icon`, `.btn-primary/secondary` |
| `Estate/CashFlowProjectionChart.vue` | Chart layout, `.summary-card`, `.card-icon.positive/negative`, responsive |
| `Estate/EstateOverviewCard.vue` | Card layout, `.status-banner`, responsive min-widths |
| `Estate/GiftCard.vue` | `.gift-card` with status border colours, `.progress-bar` gradient variants, `.taper-relief` |
| `Estate/GiftForm.vue` | Form layout, `.exemption-info` |
| `Estate/GiftingTimelineChart.vue` | (Scoped) `.legend`, `.relief-table` |
| `Estate/IHTLiabilityGauge.vue` | `.status-indicator` with `.status-good/warning/critical` |
| `Estate/IntestacyRules.vue` | Comment only |
| `Estate/LiabilityForm.vue` | Form layout, `.repayment-projection` |
| `Estate/NRBRNRBTracker.vue` | Progress bar variants, `.badge-info/warning/error`, `.eligibility-notes` |
| `Estate/TrustForm.vue` | Comment only |
| `Estate/TrustPlanning.vue` | Comment only |
| `Estate/TrustPlanningStrategy.vue` | Comment only |
| `Estate/WillPlanning.vue` | Custom range slider thumb |

#### Goals (6 files)

| File | CSS Summary |
| --- | --- |
| `Goals/GoalContributionStreak.vue` | `@keyframes ping` |
| `Goals/EventIcon.vue` | `.event-icon` flex-shrink |
| `Goals/EventIconsOverlay.vue` | Fade transition |
| `Goals/EventTooltip.vue` | Fade transition |
| `Goals/GoalsProjectionChart.vue` | `:deep(.apexcharts-tooltip)` override |
| `views/Goals/GoalsDashboard.vue` | Mobile tab nav; scrollbar hide |

#### Savings (10 files)

| File | CSS Summary |
| --- | --- |
| `Savings/CurrentSituation.vue` | `.account-card`, badges, `.open-banking-promo` gradient, responsive |
| `Savings/EmergencyFund.vue` | Custom range slider |
| `Savings/EmergencyFundGauge.vue` | Max-width + centring |
| `Savings/ISAAllowanceTracker.vue` | Empty selector |
| `Savings/InterestRateComparisonChart.vue` | Width only |
| `Savings/SaveAccountModal.vue` | Custom thin scrollbar |
| `Savings/SaveGoalModal.vue` | Custom thin scrollbar |
| `Savings/SavingsOverviewCard.vue` | Responsive min-widths |
| `views/Savings/SavingsDashboard.vue` | Mobile tab nav + scrollbar hide |
| `views/Savings/SavingsAccountDetailInline.vue` | fadeIn, `.back-button`, scrollbar hide |

#### Dashboard (9 files)

| File | CSS Summary |
| --- | --- |
| `views/Dashboard.vue` | Comment only |
| `Dashboard/AlertsPanel.vue` | Responsive min-widths |
| `Dashboard/ActionsOverviewCard.vue` | `.priority-dot` with module colour variants |
| `Dashboard/DashboardCard.vue` | `.dashboard-card` flex column |
| `Dashboard/FinancialHealthScore.vue` | Responsive min-widths |
| `Dashboard/GoalsProjectionChartDashboard.vue` | ApexCharts tooltip |
| `Dashboard/GoalsProjectionChartMini.vue` | Width only |
| `Dashboard/NetWorthOverviewCard.vue` | `.skeleton-text` loading animation, `.retry-button`, responsive |
| `Dashboard/NetWorthSummary.vue` | Responsive min-widths |

#### Risk (4 files)

| File | CSS Summary |
| --- | --- |
| `Risk/CapacityForLossSection.vue` | Slide transition |
| `Risk/FactorBreakdownCard.vue` | `.line-clamp-2` |
| `Risk/InvestmentTypesAccordion.vue` | Accordion `max-height` animation |
| `Risk/TimeHorizonSection.vue` | Fade + translateY transition |

#### Shared (7 files)

| File | CSS Summary |
| --- | --- |
| `Shared/AiMessageContent.vue` | `:deep(ul/li/p)` spacing for AI markdown |
| `Shared/CountrySelector.vue` | Custom scrollbar |
| `Shared/ISAAllowanceSummary.vue` | Responsive min-widths |
| `Shared/ModuleGoalStrategies.vue` | Accordion transition (`max-height: 2000px`) |
| `Shared/ModuleLifeEvents.vue` | Accordion transition (`max-height: 1000px`) |
| `Shared/PostcodeLookup.vue` | `.uppercase` + `::placeholder` |
| `Shared/RiskLevelSelector.vue` | Fade-slide transition |

#### Cash (6 files)

| File | CSS Summary |
| --- | --- |
| `Cash/AccountGroupList.vue` | `.account-group`, `.account-item`, `.joint-badge`; uses `@apply text-purple-600` |
| `Cash/AccountSummaryPanel.vue` | `.flow-card`, `.flow-row` |
| `Cash/BalanceTrendChart.vue` | Card with hardcoded `border-radius: 12px`, `box-shadow` |
| `Cash/CashActionsPanel.vue` | `.action-card`, `.alert-dot`; uses `@apply bg-blue-500` |
| `Cash/CashInsightsPanel.vue` | Flex column only |
| `Cash/SpendingDonutChart.vue` | `.spending-chart` card; uses `@apply text-purple-600` |

#### Auth & Modals (6 files)

| File | CSS Summary |
| --- | --- |
| `Auth/ForgotPasswordModal.vue` | Hides webkit spin buttons |
| `Auth/LogoutSuccessModal.vue` | Modal fade transition |
| `Auth/MFASetupModal.vue` | `.modal`, `.qr-image`, `.secret-code`, `.recovery-codes` grid, `.success-icon` |
| `Auth/MFAVerifyModal.vue` | `.recovery-input` centred monospace; `.text-link` |
| `Auth/VerificationCodeModal.vue` | Hides webkit spin buttons |
| `views/Auth/CheckoutPage.vue` | `:deep(iframe)` clip hack for Revolut heading |

#### Trusts (4 files)

| File | CSS Summary |
| --- | --- |
| `Trusts/TrustCard.vue` | Card hover, `.card-icon.trusts` purple; `.badge` variants |
| `Trusts/TrustsOverviewCard.vue` | Overview layout, `.rpt-badge`, `.info-banner` |
| `views/Trusts/TrustDetailView.vue` | Full detail layout, spinner, status badges; hardcoded hex |
| `views/Trusts/TrustsDashboard.vue` | Full dashboard layout, grid, guide card, tax rates; hardcoded hex |

#### Plans & Holistic (3 files with CSS)

| File | CSS Summary |
| --- | --- |
| `Plans/Shared/PlanGoalSection.vue` | `@keyframes goalCardEnter`, `.goal-status-badge`, `prefers-reduced-motion` support |
| `Plans/Shared/PlanMissingDataPrompt.vue` | Completeness bar |
| `Plans/Shared/PlanDashboardCard.vue` | Completeness bar |

#### Other Views & Components (15 files)

| File | CSS Summary |
| --- | --- |
| `views/Help.vue` | `scroll-behavior: smooth`; `::v-deep mark` highlight (deprecated syntax) |
| `views/UserProfile.vue` | Scrollbar hide |
| `views/ValuableInfo.vue` | Scrollbar hide |
| `views/Admin/AdminPanel.vue` | Scrollbar hide + iOS smooth scrolling |
| `views/Actions/ActionsDashboard.vue` | Full CSS system with hardcoded hex; **uses amber `#f59e0b`** |
| `views/Settings/AssumptionsSettings.vue` | Settings form layout |
| `views/Settings/PrivacySettings.vue` | Settings layout |
| `views/Settings/SecuritySettings.vue` | Security panel layout |
| `views/Risk/RiskLevelsExplainedPage.vue` | Asset allocation bars |
| `views/Public/LearningCentre.vue` | Scrollbar hide; `@keyframes contentFadeIn` |
| `views/Public/SecurityPage.vue` | `@keyframes sectionFadeIn` with staggered delays |
| `views/Public/CalculatorsPage.vue` | `@keyframes calcFadeIn` |
| `components/SideMenu.vue` | Scrollbar hide |
| `components/Onboarding/OnboardingWizard.vue` | Wizard layout |
| `layouts/PublicLayout.vue` | `nav .router-link-active { @apply text-blue-600; }` |

### 10.3 Duplicated CSS Patterns

These patterns are independently redefined in multiple components instead of using a shared utility:

| Pattern | Files | Recommendation |
| --- | --- | --- |
| Scrollbar hide | SideMenu, UserProfile, ValuableInfo, AdminPanel, SavingsAccountDetailInline, LearningCentre, GoalsDashboard, SavingsDashboard (8+) | Create `.scrollbar-hide` global utility in `app.css` |
| `@keyframes fadeIn` | 10+ components | Already in `app.css` via `.detail-inline` — components should reuse |
| Custom thin scrollbar | PolicyFormModal, SaveAccountModal, SaveGoalModal, CountrySelector | Create `.scrollbar-thin` global utility |
| Webkit spin button removal | VerificationCodeModal, ForgotPasswordModal | Create `.no-spinners` global utility |
| `.back-button` styles | PolicyDetail, PropertyDetailInline, RetirementIncomeTab, CapitalAdequacyTab, RequiredCapitalDetail, SavingsAccountDetailInline | Already covered by `.detail-inline-back` in `app.css` — components should reuse |
| `.line-clamp-2` | RecommendationCard, FactorBreakdownCard, PersonaSelectionModal | Tailwind has `line-clamp-2` built-in (v3.3+) |
| Custom range slider thumb | ScenarioBuilder, EmergencyFund, WillPlanning, WhatIfScenarios | Create shared slider utility |

---

## 11. Inline `style=""` Attributes

**84 files** contain static inline `style=""` attributes. Grouped by pattern:

### Animation Delays (Public Pages)

| File | Style |
| --- | --- |
| `views/Public/LandingPage.vue` | `animation-delay: 1s` on decorative blur blob |
| `views/Public/PricingPage.vue` | `animation-delay: 1s` on decorative blur blob |
| `views/Public/AboutPage.vue` | `animation-delay: 1s` on decorative blob |
| `views/Public/LearningCentre.vue` | `animation-delay: 1s` |
| `views/Public/SecurityPage.vue` | `animation-delay: 1s` and `animation-delay: 2s` |
| `views/Public/CalculatorsPage.vue` | `animation-delay: 1s` |
| `Plans/Shared/PlanGoalSection.vue` | Staggered `animationDelay` on goal cards |
| `Holistic/GoalsSummarySection.vue` | Staggered `animationDelay` |
| `Holistic/EstateSummarySection.vue` | Staggered `animationDelay` |

### Hardcoded Background Colours

| File | Style | Issue |
| --- | --- | --- |
| `components/Navbar.vue` | `background-color: #F9FAFB` | Should use `bg-gray-50` class |
| `components/Navbar.vue` | `transition: transform 0.2s` on SVG chevron | Could use Tailwind `transition-transform duration-200` |

### ApexCharts Tooltip HTML (Inside JS Template Literals)

| File | Inline Styles |
| --- | --- |
| `Protection/CoverageTimelineChart.vue` | `padding: 8px 12px`, `font-weight: 600; margin-bottom: 4px`, `font-size: 12px` |

### Progress Bar Widths (Computed Percentages)

This is the dominant pattern — progress/gauge bars where width is set dynamically:

| File | What it renders |
| --- | --- |
| `views/Dashboard.vue` | ISA/LISA/pension allowance progress bars |
| `Dashboard/GoalsOverviewCard.vue` | Goal progress bars |
| `Dashboard/TaxOptimisationCard.vue` | ISA/pension/CGT/dividend allowance bars |
| `Goals/GoalCard.vue` | Goal progress bar |
| `Goals/GoalDetailInline.vue` | Goal progress bar |
| `Goals/GoalsOverview.vue` | Goal progress bar |
| `Goals/GoalsByModule.vue` | Progress bars |
| `Goals/GoalProgressBar.vue` | Progress bar + milestone markers |
| `Goals/GoalMilestoneTracker.vue` | Progress + milestone positions |
| `Goals/ContributionModal.vue` | Progress bar |
| `Goals/GoalsAnalysis.vue` | Asset allocation bars |
| `Savings/ISAAllowanceTracker.vue` | LISA/cash ISA/stocks ISA bars |
| `Savings/SavingsOverviewCard.vue` | Emergency runway bar |
| `Savings/EmergencyFund.vue` | Current amount bar |
| `Savings/SaveAccountModal.vue` | 4 ISA progress bars |
| `Savings/SavingsGoals.vue` | Goal progress bar |
| `Investment/Goals.vue` | Progress bar |
| `Investment/GoalCard.vue` | Progress + success probability bars |
| `Investment/ISAOptimizationStrategy.vue` | ISA utilisation bar |
| `Investment/MonteCarloResults.vue` | 3 probability bars |
| `Investment/TaxFees.vue` | Fee proportional + ISA bars |
| `Investment/TaxEfficiencyPanel.vue` | ISA utilisation bar |
| `Investment/TaxOptimizationOverview.vue` | ISA/CGT/dividend/location bars |
| `Investment/ContributionPlanner.vue` | ISA status bar |
| `Investment/StandardInvestmentFields.vue` | 4 ISA allocation bars |
| `Investment/DiversificationTab.vue` | Score + HHI + asset class bars |
| `Investment/WrapperOptimizer.vue` | Progress bars |
| `Investment/HoldingsTable.vue` | `backgroundColor: chartColours[index]` |
| `Retirement/TaxBreakdownCard.vue` | Tax band usage bars |
| `Retirement/AnnualAllowanceTracker.vue` | Annual allowance progress |
| `Retirement/CapitalAdequacyTab.vue` | Allowance + capital progress |
| `Retirement/RequiredCapitalDetail.vue` | Progress + forecasted bars |
| `Retirement/StrategiesTab.vue` | Capital position bar |
| `Estate/GiftCard.vue` | Survival percentage bar |
| `Estate/NRBRNRBTracker.vue` | NRB + RNRB percentage bars |
| `Estate/GiftingStrategy.vue` | Timeline progress + background colour |
| `Shared/ISAAllowanceSummary.vue` | ISA usage bar |
| `Shared/ModuleGoalStrategies.vue` | Overall + individual strategy bars |
| `Shared/ProcessingState.vue` | Processing progress bar |
| `Shared/ProfileCompletenessAlert.vue` | Profile completeness bar |
| `Plans/Shared/PlanMissingDataPrompt.vue` | Completeness bar |
| `Plans/Shared/PlanDashboardCard.vue` | Completeness bar |
| `Plans/Protection/ProtectionCurrentSituation.vue` | Coverage bars |
| `Plans/Goals/GoalCurrentSituation.vue` | Goal progress bar |
| `UserProfile/SubscriptionManagement.vue` | Trial progress bar |
| `Trial/TrialCountdownBanner.vue` | Trial progress bar |
| `views/Risk/RiskLevelsExplainedPage.vue` | Asset allocation bars |
| `views/Investment/AccountPerformancePanel.vue` | Target allocation markers + bars |
| `views/Investment/AccountHoldingsPanel.vue` | `backgroundColor: getAssetColor(type)` |
| `views/Investment/AccountRebalancingPanel.vue` | Target + current allocation bars |
| `views/Investment/EmployeeShareSchemeDetail.vue` | Vesting progress bar |

---

## 12. Dynamic `:style=` Bindings

**70 files** use `:style=` bindings. Grouped by pattern:

### Pattern A: Progress Bar Widths (Most Common)

```html
:style="{ width: Math.min(value, 100) + '%' }"
```

Used in nearly every progress/gauge component listed in Section 11. This is the standard pattern.

### Pattern B: Dynamic Background Colours

```html
:style="{ backgroundColor: getAssetColor(allocation.type) }"
:style="{ backgroundColor: chartColours[index % chartColours.length] }"
```

Files: `Investment/HoldingsTable.vue`, `views/Investment/AccountPerformancePanel.vue`, `views/Investment/AccountHoldingsPanel.vue`, `Goals/EventIcon.vue`, `Goals/EventIconsOverlay.vue`

### Pattern C: Tooltip / Floating Element Positioning

```html
:style="tooltipStyle"   <!-- computed top/left/transform -->
:style="arrowStyle"     <!-- computed arrow position -->
```

Files: `Guidance/GuidanceTooltip.vue`, `Goals/EventTooltip.vue`

### Pattern D: Animation Delays

```html
:style="{ animationDelay: `${(index + 1) * 60}ms` }"
```

Files: `Holistic/GoalsSummarySection.vue`, `Holistic/EstateSummarySection.vue`, `Plans/Shared/PlanGoalSection.vue`

### Pattern E: Position Markers (Left-side Indicators on Range Bars)

```html
:style="{ left: targetAllocation + '%' }"
:style="{ left: milestone + '%' }"
:style="{ left: `${event.position}%` }"
```

Files: `views/Investment/AccountPerformancePanel.vue`, `views/Investment/AccountRebalancingPanel.vue`, `Investment/DiversificationTab.vue`, `Goals/GoalProgressBar.vue`, `Goals/EventIconsOverlay.vue`, `Risk/CapacityForLossSection.vue`

### Pattern F: Fixed Layout Values

```html
:style="{ width: 'calc(100% - 2.5rem)' }"
```

Files: `NetWorth/BusinessInterestForm.vue`, `NetWorth/Property/PropertyForm.vue`

### Pattern G: Complex Chart Styles

```html
:style="{ left: ..., top: ..., transform: ... }"
```

Files: `Goals/GoalsProjectionChart.vue`, `Dashboard/GoalsProjectionChartDashboard.vue`

### Pattern H: Risk Level Selector Computed Styles

```html
:style="getButtonStyle(level.value)"
:style="{ height: asset.percentage + '%' }"
```

File: `Shared/RiskLevelSelector.vue`

---

## 13. Build Output

**Location:** `public/build/assets/`

- **62 CSS files** — Vite's code-split output
- **1 main stylesheet** (`css-DgwDRKxz.css`) — compiled `app.css` with all Tailwind utilities
- **61 component chunk CSS files** — scoped styles from lazy-loaded Vue components
- CSS is fingerprinted for cache busting (hash in filename)
- `sourcemap: false`

---

## 14. Issues & Inconsistencies

### 14.1 Design System Violations

| Issue | File | Detail |
| --- | --- | --- |
| **Amber colour used** | `views/Actions/ActionsDashboard.vue` | `color: #f59e0b` (amber-500) in `.card.medium .card-value` — violates ban on amber/orange |
| **Hardcoded indigo** | `views/Retirement/WhatIfScenarios.vue` | `#4f46e5` (indigo-600) instead of design system tokens |
| **Hardcoded hex colours** | `views/Investment/AccountPerformancePanel.vue` | `#111827`, `#e5e7eb`, `#6b7280` instead of Tailwind classes |
| **Hardcoded hex colours** | `views/Investment/AccountFeesPanel.vue` | Raw hex values throughout |
| **Hardcoded hex colours** | `views/Investment/AccountHoldingsPanel.vue` | Raw hex values throughout |
| **Hardcoded hex colours** | `views/Trusts/TrustDetailView.vue` | Raw hex values throughout |
| **Hardcoded hex colours** | `views/Trusts/TrustsDashboard.vue` | Raw hex values throughout |
| **Hardcoded hex colours** | `views/NetWorth/CashOverview.vue` | `#374151`, `#9ca3af` etc. |
| **Hardcoded scrollbar colours** | `Investment/AllocationComparison.vue` | `#888`, `#555` |
| **Fuchsia used** | `NetWorth/BusinessInterestsList.vue` | `@apply bg-fuchsia-700` — not in design system |
| **Hardcoded box-shadow** | `Cash/BalanceTrendChart.vue` | Inline `border-radius: 12px` and `box-shadow` instead of tokens |

### 14.2 CSS Bugs

| Issue | File | Detail |
| --- | --- | --- |
| **CSS property typo** | `Investment/RebalancingActions.vue` | `transition-property: background-colour` — CSS uses `background-color` (American) |
| **JS style typo** | `Estate/GiftingStrategy.vue` | `:style="{ backgroundColour: ... }"` — Vue expects `backgroundColor` (camelCase) |
| **Deprecated Vue syntax** | `views/Help.vue` | Uses `::v-deep mark` — should be `:deep(mark)` in Vue 3 |

### 14.3 Duplication

| Pattern | Duplicated In | Count |
| --- | --- | --- |
| Scrollbar hide | SideMenu, UserProfile, ValuableInfo, AdminPanel, SavingsAccountDetailInline, LearningCentre, GoalsDashboard, SavingsDashboard | 8+ |
| `@keyframes fadeIn` | 10+ components (already exists in `app.css`) | 10+ |
| Custom thin scrollbar | PolicyFormModal, SaveAccountModal, SaveGoalModal, CountrySelector | 4 |
| Webkit spin button removal | VerificationCodeModal, ForgotPasswordModal | 2 |
| `.back-button` styles | 6+ components (covered by `.detail-inline-back` in `app.css`) | 6+ |
| `.line-clamp-2` polyfill | 3 components (Tailwind v3.3+ has this built-in) | 3 |
| Custom range slider thumb | ScenarioBuilder, EmergencyFund, WillPlanning, WhatIfScenarios | 4 |

### 14.4 Potential Issue: `badges.css` Import

`resources/css/badges.css` exists and defines many badge classes using `@apply`, but there is no explicit `@import './badges.css'` in `app.css`. The file may be processed by Tailwind's content scanner for class detection, but the `@apply` rules themselves need to be imported to generate CSS output. Verify this file is actually included in the build.

### 14.5 Inconsistent Styling Approach

Some components use `@apply` with Tailwind classes in `<style scoped>`, while others use raw CSS properties with hardcoded values. The codebase has two coexisting styling approaches:

1. **Design System approach:** `@apply bg-primary-600 text-white rounded-button` (preferred)
2. **Raw CSS approach:** `background-color: #1257A0; color: #ffffff; border-radius: 0.5rem;` (should be migrated)

Components with the most raw CSS that should be migrated to `@apply`:
- `views/Actions/ActionsDashboard.vue`
- `views/Investment/AccountPerformancePanel.vue`
- `views/Investment/AccountFeesPanel.vue`
- `views/Investment/AccountHoldingsPanel.vue`
- `views/Trusts/TrustDetailView.vue`
- `views/Trusts/TrustsDashboard.vue`
- `views/NetWorth/CashOverview.vue`

---

*End of Style Map*
