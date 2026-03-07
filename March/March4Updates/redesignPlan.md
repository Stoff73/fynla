# Plan: Design System Overhaul — fynlaDesignGuide.md v1.2.0

## Context

Fynla is moving from `designStyle.md` (v1.1.0, Jan 2026) to `fynlaDesignGuide.md` (v1.2.0, Mar 2026) as the **single source of truth** for all design decisions. This is a complete visual rebrand:

- **Colors**: Trust Blue → Raspberry (CTAs), Horizon Blue (text/nav), Spring Green (success), Violet (warnings/focus), Savannah Sand (hover/subtle), Eggshell (page bg)
- **Typography**: Inter → Segoe UI (primary) with Inter as fallback; Plus Jakarta Sans removed; Planet Estyle logo-only
- **Semantic remapping**: Warning = Violet (was blue), Error = Raspberry (was red), Focus = Violet (was primary)
- **Page bg**: `#F9FAFB` (gray-50) → `#F7F6F4` (eggshell-500)
- **Nav**: Trust Blue → Horizon-500 (#1F2A44 dark navy)

### User Decisions
1. **Font**: Segoe UI as primary with Inter as fallback
2. **Auth pages**: Fix guide — use new palette everywhere (no gray exceptions)
3. **designStyle.md**: Keep as `designStyle-legacy.md` archive
4. **Approach**: Infrastructure first, then components module-by-module (old tokens coexist during migration, removed at end)

### Scale
- ~350+ Vue files use Tailwind color classes
- 57 files import from `designSystem.js`
- 52 chart fontFamily references to update
- `app.css` has ~90 color references to update
- `tailwind.config.js` needs complete color overhaul

---

## Phase 0: Fix fynlaDesignGuide.md Inconsistencies (~50 old token refs)

The design guide itself still references old color tokens in ~50 places. Fix these **before** implementing, so the guide is clean and accurate.

### Files
- `fynlaDesignGuide.md`

### Changes
Replace all old tokens with new equivalents:

| Old Token | New Token | Sections Affected |
|-----------|-----------|-------------------|
| `bg-gray-50` | `bg-eggshell-500` | Login, Registration, Auth pages |
| `text-gray-900` | `text-horizon-500` | Login, Onboarding headings |
| `text-gray-500/600/700` | `text-neutral-500` or `text-horizon-*` | Currency, Range, File Upload, Autocomplete, OTP |
| `border-gray-200/300` | `border-light-gray` or `border-horizon-200` | Borders section, Forms, OTP, Onboarding |
| `bg-primary-*`, `text-primary-*` | `bg-raspberry-*`/`bg-horizon-*` | Welcome, File upload, Registration links |
| `bg-green-*`, `border-green-*` | `bg-spring-*`, `border-spring-*` | Auth banners |
| `bg-blue-*`, `border-blue-*` | `bg-violet-*`/`bg-horizon-*` | Auth banners, File upload drag state |
| `bg-red-*`, `border-red-*` | `border-error-*`, `bg-error-*` | OTP error, Auth error banner |
| `hover:bg-gray-50` | `hover:bg-savannah-100` | Autocomplete, Button group |

Also fix Border Colors section (line ~405-406): `border-gray-200` → `border-light-gray`, `border-primary-400` → `border-raspberry-300`

---

## Phase 1: Infrastructure — Tailwind Config (`tailwind.config.js`)

Add ALL new color families **alongside** existing ones (coexistence during migration). Old tokens remain functional so components don't break mid-migration.

### New Color Families to Add

```js
// PRIMARY PALETTE
raspberry: {
  50: '#FDF2F8', 100: '#FCE7F3', 200: '#F9A8D4', 300: '#F472B6',
  400: '#EC4899', 500: '#E83E6D', 600: '#DB2777', 700: '#BE185D',
  800: '#9D174D', 900: '#831843',
},
horizon: {
  50: '#F8FAFC', 100: '#F1F5F9', 200: '#E2E8F0', 300: '#CBD5E1',
  400: '#94A3B8', 500: '#1F2A44', 600: '#0F172A', 700: '#020617',
  800: '#0A0E1A', 900: '#03060D',
},
spring: {
  50: '#F0FDF9', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7',
  400: '#34D399', 500: '#20B486', 600: '#059669', 700: '#047857',
  800: '#065F46', 900: '#064E3B',
},
violet: {
  50: '#F5F3FF', 100: '#EDE9FE', 200: '#DDD6FE', 300: '#C4B5FD',
  400: '#A78BFA', 500: '#5854E6', 600: '#7C3AED', 700: '#6D28D9',
  800: '#581C87', 900: '#4C1D5F',
},
savannah: {
  50: '#FEFCFB', 100: '#FDFAF7', 200: '#FAF5F0', 300: '#F5EDE5',
  400: '#EFDCD1', 500: '#E6C9A8', 600: '#D1B08C', 700: '#A88E6E',
  800: '#8A7359', 900: '#6B5845',
},
eggshell: {
  50: '#FFFFFF', 100: '#FEFEFE', 500: '#F7F6F4', 900: '#E7E5E2',
},

// SECONDARY PALETTE
neutral: { 500: '#717171' },
'light-gray': '#EEEEEE',
'light-blue': { 100: '#DDE2EF', 500: '#6C83BC' },
'light-pink': { 100: '#FAD6E0', 400: '#EF7598' },
```

### Update Semantic Colors

```js
success: {
  100: '#D1FAE5', 500: '#20B486', 600: '#059669', 700: '#047857',
},
error: {
  100: '#FCE7F3', 500: '#E83E6D', 600: '#DB2777', 700: '#BE185D',
},
warning: {
  100: '#EDE9FE', 500: '#5854E6', 600: '#7C3AED', 700: '#6D28D9',
},
info: {
  100: '#DDE2EF', 500: '#6C83BC', 600: '#5A6FA3', 700: '#4C5D8A',
},
```

### Update Chart Colors

```js
chart: {
  1: '#1F2A44', 2: '#20B486', 3: '#5854E6', 4: '#E83E6D',
  5: '#E6C9A8', 6: '#6C83BC', 7: '#717171', 8: '#0F172A',
},
```

### Update Font Family

```js
fontFamily: {
  sans: ['Segoe UI', 'Inter', '-apple-system', 'BlinkMacSystemFont', 'Roboto', 'sans-serif'],
  display: ['Segoe UI', 'Inter', 'sans-serif'],
  mono: ['JetBrains Mono', 'Courier New', 'monospace'],
},
```

### Update Type Scale Weights
Per guide: Display/H1 = 900 (Black), H2-H5 = 700 (Bold) — current has 600 for H2-H5.

### Update Safelist
Replace old safelist entries with new palette equivalents for risk level colors.

---

## Phase 2: Infrastructure — `app.css` + `designSystem.js`

### 2A. Update `app.css`

**Font import** (line 1):
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&family=JetBrains+Mono:wght@400;500&display=swap');
```
- Remove Plus Jakarta Sans
- Add weight 900 for Inter (fallback for Segoe UI Black)
- Note: Segoe UI is a system font, no import needed

**Base styles** (lines 10-38):
- `background-color: #F9FAFB` → `background-color: #F7F6F4` (eggshell-500)
- `text-gray-900` → `text-horizon-500`
- `text-gray-800` → `text-horizon-500`
- `text-gray-700` → `text-horizon-500` (headings) or `text-neutral-500` (body)
- `text-gray-600` → `text-neutral-500`

**Component classes** (lines 41-318):
- `.btn-primary`: `bg-primary-600` → `bg-raspberry-500`, hover/active → `raspberry-600/700`
- `.btn-secondary`: `text-gray-700 border-gray-300 hover:bg-gray-50` → `text-horizon-500 border-light-gray hover:bg-savannah-100`
- `.btn-outline`: `text-primary-600 border-primary-600` → `text-spring-500 border-spring-500`
- `.btn-danger`: `bg-error-600` → `bg-raspberry-600`
- `.card`: `border-gray-200` → `border-light-gray`
- `.card.cursor-pointer`: `hover:border-primary-400` → `hover:border-raspberry-300`
- `.card-hover`: `hover:border-primary-400` → `hover:border-raspberry-300`
- `.input-field`: `border-gray-300`, `focus:ring-primary-500`, `focus:border-primary-600` → `border-light-gray`, `focus:ring-violet-500`, `focus:border-violet-500`
- `.label`: `text-gray-700` → `text-neutral-500`
- `.badge-warning`: `bg-blue-100 text-blue-700` → `bg-violet-100 text-violet-700`
- `.modal-overlay`: `bg-black bg-opacity-50` → `bg-horizon-500/75`
- `.modal-header`: `border-gray-200` → `border-light-gray`; h3: `text-gray-900` → `text-horizon-500`
- `.modal-footer`: `border-gray-200` → `border-light-gray`
- `.close-btn`: `text-gray-500 hover:text-gray-900` → `text-neutral-500 hover:text-horizon-500`
- `.form-group label`: `text-gray-700` → `text-neutral-500`
- `.form-input`: `border-gray-300`, `focus:ring-primary-500`, `focus:border-primary-500` → `border-light-gray`, `focus:ring-violet-500`, `focus:border-violet-500`
- `.form-hint`: `text-gray-500` → `text-neutral-500`
- `.code-input`: `border-gray-200`, `focus:border-primary-500` → `border-horizon-200`, `focus:border-violet-500`
- `.error-message`: `text-red-600` → `text-raspberry-500`
- All `.card-*` variants: Update border/bg colors to new palette
- `.detail-inline-back`: `border-gray-200 text-gray-700 hover:bg-gray-50 hover:border-gray-300` → `border-light-gray text-horizon-500 hover:bg-savannah-100 hover:border-horizon-300`
- `.chart-card`: `border-gray-200` → `border-light-gray`; hover border: `border-primary-500` → `border-raspberry-300`
- `.chart-title`: `text-gray-900` → `text-horizon-500`
- `.chart-subtitle`: `text-gray-500` → `text-neutral-500`
- `.focus-ring`: `ring-primary-500`, `border-primary-600` → `ring-violet-500`, `border-violet-500`
- `.focus-ring-visible`: `ring-primary-500` → `ring-violet-500`
- All badge classes: Update to new palette colors
- Range slider: `bg-gray-200` → `bg-horizon-200`; thumb: `bg-primary-600` → `bg-raspberry-500`
- Scrollbar thin: `gray.300`/`gray.50` → `horizon.300`/`savannah.100`
- Print styles: `bg-gray-50` → `bg-eggshell-500`

### 2B. Update `designSystem.js`

**Header**: Change reference from `designStyle.md v1.0.0` to `fynlaDesignGuide.md v1.2.0`

**Color constants** — map every export to new palette values:

| Constant | Old Source | New Source |
|----------|-----------|-----------|
| `PRIMARY_COLORS` | Trust Blue scale | Raspberry scale (CTA color) |
| `SECONDARY_COLORS` | Slate scale | Horizon scale (text/nav color) |
| `SUCCESS_COLORS` | Green | Spring Green |
| `ERROR_COLORS` | Red | Raspberry (danger variant) |
| `WARNING_COLORS` | Blue | Violet |
| `INFO_COLORS` | Sky Blue | Light Blue |
| `CHART_COLORS` | Old 8-color array | New 8-color array per guide |
| `ASSET_COLORS` | Old hex values | Map to new palette hex values |
| `SPENDING_COLORS` | 16 old hex values | Update to closest new palette equivalents |
| `RISK_COLORS` | Old risk hex | Keep current (risk colors are intentionally distinct) |
| `RISK_TAILWIND_CLASSES` | Old Tailwind classes | Keep current (risk badge styling unchanged) |
| `TEXT_COLORS` | gray-900/700/600/500/400/300 | horizon-500, neutral-500, horizon-300/400 |
| `BG_COLORS` | gray-50, gray-100 | eggshell-500, savannah-100 |
| `BORDER_COLORS` | gray-200/300, primary-600 | light-gray (#EEEEEE), horizon-300, violet-500 |
| `CHART_DEFAULTS` | fontFamily: 'Inter, ...' | fontFamily: 'Segoe UI, Inter, ...' |

---

## Phase 3: Component Migration (Module by Module)

After infrastructure (Phases 1-2), old Tailwind tokens still work because we kept them in config. Now migrate components, verifying each module visually.

### Migration Pattern (per file)
1. Replace `text-gray-900` → `text-horizon-500` (headings)
2. Replace `text-gray-700/600` → `text-neutral-500` or `text-horizon-500` (body/labels)
3. Replace `text-gray-500` → `text-neutral-500` (muted/captions)
4. Replace `text-gray-400` → `text-horizon-400` (placeholder)
5. Replace `bg-gray-50` → `bg-eggshell-500` (page bg) or `bg-savannah-100` (subtle)
6. Replace `bg-gray-100` → `bg-savannah-100`
7. Replace `border-gray-200` → `border-light-gray`
8. Replace `border-gray-300` → `border-horizon-300`
9. Replace `bg-primary-*`/`text-primary-*` → `bg-raspberry-*`/`text-raspberry-*` (CTAs) or `bg-horizon-*`/`text-horizon-*` (nav/text)
10. Replace `focus:ring-primary-*`/`focus:border-primary-*` → `focus:ring-violet-*`/`focus:border-violet-*`
11. Replace `hover:bg-gray-50` → `hover:bg-savannah-100`
12. Replace `bg-blue-*` (warning) → `bg-violet-*`
13. Replace `text-red-*` (error) → `text-raspberry-*`
14. Replace `bg-green-*` (success) → `bg-spring-*`
15. Replace hardcoded hex in `<style scoped>` with `@apply` using new tokens
16. Replace chart fontFamily strings: `'Inter, ...'` → `'Segoe UI, Inter, system-ui, sans-serif'`

### Module Order (by dependency and visual impact)

| Batch | Module | ~Files | Notes |
|-------|--------|--------|-------|
| 1 | Layouts + Navigation | 5 | `AppLayout`, `SideMenu`, `Navbar`, `PublicLayout`, `Footer` — highest visual impact, sets tone |
| 2 | Auth + Onboarding | 8 | Login, Register, OTP, Onboarding wizard, Welcome |
| 3 | Dashboard | 10 | Main dashboard, Plan pages, Net Worth overview |
| 4 | Net Worth | 40 | Property, Pensions, Investments, Cash, Business, Chattels detail views |
| 5 | Investment | 30 | Account panels, charts, holdings, rebalancing |
| 6 | Retirement | 25 | Income, projections, drawdown, Monte Carlo |
| 7 | Protection | 20 | Policies, coverage, forms |
| 8 | Savings | 15 | Accounts, goals, emergency fund |
| 9 | Estate | 15 | Wills, trusts, gifting, IHT |
| 10 | Goals & Life Events | 15 | Goal cards, life events, scenarios |
| 11 | Actions & Coordination | 10 | Actions dashboard, recommendations |
| 12 | Settings & Admin | 15 | User settings, admin panel, billing |
| 13 | Public Pages | 10 | Landing, pricing, calculators |
| 14 | Shared Components | 30 | Modals, forms, charts, shared UI |

**After each batch**: Run `./deploy/fynla-org/build.sh` to verify no build errors.

### Chart Components (~57 files)
These import from `designSystem.js` so most color changes happen automatically via Phase 2B. Manual work:
- Update any hardcoded `fontFamily: 'Inter, ...'` strings (52 instances across chart files)
- Update any hardcoded hex colors not sourced from designSystem.js (30 files)

---

## Phase 4: Cleanup — Remove Old Tokens

After ALL components are migrated:

1. **Remove old color families from `tailwind.config.js`**: `primary`, `secondary` (old slate), old `success/error/warning/info` values
2. **Update safelist**: Remove old safelist entries, add any new ones needed
3. **Verify build**: `./deploy/fynla-org/build.sh` — if build succeeds with no missing class warnings, old tokens are fully removed
4. **Search for orphaned references**: `grep -r "primary-" resources/js/` — should return zero hits (except legitimate `raspberry-` etc.)

---

## Phase 5: Documentation Updates

### 5A. Rename `designStyle.md` → `designStyle-legacy.md`
Add header: `> **ARCHIVED**: This is the v1.1.0 design system (January 2026). The current design system is in [fynlaDesignGuide.md](fynlaDesignGuide.md).`

### 5B. Update `CLAUDE.md`
- Rule #9: Update from "No Amber or Orange Color" → "No Amber, Orange, or non-palette colors. Use violet for warnings, raspberry for errors."
- Rule #11: Change `designStyle.md` reference → `fynlaDesignGuide.md`
- Add to Rule #12 CSS Governance: "All colors must come from the palette defined in fynlaDesignGuide.md v1.2.0. Use Tailwind tokens (raspberry-*, horizon-*, spring-*, violet-*, savannah-*, eggshell-*) — never hardcode hex."

### 5C. Update `resources/js/CLAUDE.md`
- Update `designSystem.js` description to reference new color families
- Update constants table to reflect new palette names

### 5D. Update `MEMORY.md`
- Add note about new design system: fynlaDesignGuide.md v1.2.0 is the single source of truth
- Note font stack: Segoe UI (primary), Inter (fallback)
- Note key color mappings for quick reference

### 5E. Update `fynlaDesignGuide.md` header
- Confirm "Global CSS Utilities" section (added in CSS centralisation) is still accurate after color updates

---

## Key Files

| File | Role | Phase |
|------|------|-------|
| `fynlaDesignGuide.md` | Design system (single source of truth) | 0, 5E |
| `designStyle.md` → `designStyle-legacy.md` | Archive of old system | 5A |
| `tailwind.config.js` | Tailwind color/font/size tokens | 1 |
| `resources/js/constants/designSystem.js` | JS color/chart constants | 2B |
| `resources/css/app.css` | Global CSS classes | 2A |
| `CLAUDE.md` | Project rules | 5B |
| `resources/js/CLAUDE.md` | Frontend conventions | 5C |
| `MEMORY.md` | Persistent memory | 5D |

---

## Verification

After each phase:
1. `./deploy/fynla-org/build.sh` — must pass with zero errors
2. `./vendor/bin/pest` — all tests pass
3. `php artisan db:seed` — reseed before any visual testing

After full migration:
4. Login as each preview persona, navigate all modules
5. Verify: No old blue (Trust Blue #1257A0) visible on any CTA
6. Verify: Page backgrounds are warm eggshell (#F7F6F4), not cool gray (#F9FAFB)
7. Verify: Nav is dark navy horizon (#1F2A44)
8. Verify: Buttons are raspberry (#E83E6D)
9. Verify: Focus rings are violet on all form inputs
10. Verify: Warnings use violet, errors use raspberry, success uses spring green
11. Verify: Charts render with new color palette
12. Verify: No amber/orange anywhere
13. `grep -r "primary-" resources/js/ resources/css/` — zero hits for old primary token
