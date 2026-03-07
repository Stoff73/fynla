# Design System Overhaul — Detailed Task List

**Plan:** `redesignPlan.md`
**Status:** COMPLETE (pending visual browser testing V.4-V.6)
**Last Updated:** 04 March 2026

Legend: `[ ]` = pending, `[x]` = done, `[~]` = in progress

---

## Phase 0: Fix fynlaDesignGuide.md Inconsistencies

> **Agent:** `Explore` agent to identify all old token refs
> **Agent:** `premium-ui-designer` to implement the token replacements (design system is its domain)

- [x] 0.1 `Explore` agent: Read full `fynlaDesignGuide.md` and catalogue all old token references (~85 instances found)
- [x] 0.2 `premium-ui-designer` agent: Replace all `bg-gray-*` → `bg-eggshell-*` or `bg-savannah-*`
- [x] 0.3 `premium-ui-designer` agent: Replace all `text-gray-*` → `text-horizon-*` or `text-neutral-*`
- [x] 0.4 `premium-ui-designer` agent: Replace all `border-gray-*` → `border-light-gray` or `border-horizon-*`
- [x] 0.5 `premium-ui-designer` agent: Replace all `primary-*` → `raspberry-*` or `horizon-*`
- [x] 0.6 `premium-ui-designer` agent: Replace all `bg-green-*`/`bg-blue-*`/`bg-red-*` → new semantic tokens
- [x] 0.7 `premium-ui-designer` agent: Fix Border Colors section (line ~405-406) to new tokens
- [x] 0.8 `premium-ui-designer` agent: Fix Login & Registration section (lines ~1293-1330) to new palette
- [x] 0.9 `premium-ui-designer` agent: Fix OTP section (lines ~1332-1376) to new palette
- [x] 0.10 `premium-ui-designer` agent: Fix Onboarding section (lines ~1400-1430) to new palette
- [x] 0.11 `premium-ui-designer` agent: Fix Currency/Percentage/Range/File Upload/Autocomplete sections
- [x] 0.12 `Grep` tool: Verify no old tokens remain — ~85 replacements across 28+ areas

---

## Phase 1: Infrastructure — Tailwind Config

> **Agent:** `premium-ui-designer` to implement all color/font/weight changes
> **Verify:** `./deploy/fynla-org/build.sh`

- [x] 1.1 `premium-ui-designer` agent: Add `raspberry` color family (50-900) to `tailwind.config.js`
- [x] 1.2 `premium-ui-designer` agent: Add `horizon` color family (50-900)
- [x] 1.3 `premium-ui-designer` agent: Add `spring` color family (50-900)
- [x] 1.4 `premium-ui-designer` agent: Add `violet` color family (50-900)
- [x] 1.5 `premium-ui-designer` agent: Add `savannah` color family (50-900)
- [x] 1.6 `premium-ui-designer` agent: Add `eggshell` color family (50, 100, 500, 900)
- [x] 1.7 `premium-ui-designer` agent: Add secondary palette: `neutral`, `light-gray`, `light-blue`, `light-pink`
- [x] 1.8 `premium-ui-designer` agent: Update `success` semantic colors to Spring Green values
- [x] 1.9 `premium-ui-designer` agent: Update `error` semantic colors to Raspberry values
- [x] 1.10 `premium-ui-designer` agent: Update `warning` semantic colors to Violet values
- [x] 1.11 `premium-ui-designer` agent: Update `info` semantic colors to Light Blue values
- [x] 1.12 `premium-ui-designer` agent: Update `chart` colors to new 8-color palette
- [x] 1.13 `premium-ui-designer` agent: Update `fontFamily.sans` to `['Segoe UI', 'Inter', ...]`
- [x] 1.14 `premium-ui-designer` agent: Update `fontFamily.display` to `['Segoe UI', 'Inter', ...]`
- [x] 1.15 `premium-ui-designer` agent: Update `fontSize` weights: Display/H1 → 900, H2-H5 → 700
- [x] 1.16 `premium-ui-designer` agent: Update safelist with new palette equivalents
- [x] 1.17 Run `./deploy/fynla-org/build.sh` — build passes ✓

---

## Phase 2A: Infrastructure — app.css

> **Agent:** `premium-ui-designer` to implement all CSS class updates
> **Verify:** `./deploy/fynla-org/build.sh`

- [x] 2A.1 `premium-ui-designer` agent: Update Google Fonts import — remove Plus Jakarta Sans, add Inter weight 900
- [x] 2A.2 `premium-ui-designer` agent: Update base `html, body` background to `#F7F6F4`
- [x] 2A.3 `premium-ui-designer` agent: Update base heading styles: `text-gray-*` → `text-horizon-500`
- [x] 2A.4 `premium-ui-designer` agent: Update `.btn-primary` to raspberry-500/600/700
- [x] 2A.5 `premium-ui-designer` agent: Update `.btn-secondary` to horizon-500, light-gray border, savannah hover
- [x] 2A.6 `premium-ui-designer` agent: Update `.btn-outline` to spring-500
- [x] 2A.7 `premium-ui-designer` agent: Update `.btn-danger` to raspberry-600/700/800
- [x] 2A.8 `premium-ui-designer` agent: Update `.card` and `.card-hover` borders and hover colors
- [x] 2A.9 `premium-ui-designer` agent: Update `.input-field` border, focus ring to violet
- [x] 2A.10 `premium-ui-designer` agent: Update `.label` and `.form-group label` to neutral-500
- [x] 2A.11 `premium-ui-designer` agent: Update all badge classes to new palette
- [x] 2A.12 `premium-ui-designer` agent: Update modal classes: overlay, header, footer, close-btn
- [x] 2A.13 `premium-ui-designer` agent: Update `.form-input`, `.form-hint`, `.code-input` to new palette
- [x] 2A.14 `premium-ui-designer` agent: Update `.error-message` to raspberry-500
- [x] 2A.15 `premium-ui-designer` agent: Update `.card-highlighted`, `.card-success`, `.card-warning`, `.card-error`
- [x] 2A.16 `premium-ui-designer` agent: Update `.detail-inline-back` to new palette
- [x] 2A.17 `premium-ui-designer` agent: Update `.chart-card`, `.chart-title`, `.chart-subtitle`
- [x] 2A.18 `premium-ui-designer` agent: Update `.focus-ring` and `.focus-ring-visible` to violet
- [x] 2A.19 `premium-ui-designer` agent: Update range slider styling to raspberry-500 thumb
- [x] 2A.20 `premium-ui-designer` agent: Update scrollbar-thin colors
- [x] 2A.21 `premium-ui-designer` agent: Update print styles for new background color
- [x] 2A.22 Run `./deploy/fynla-org/build.sh` — build passes ✓

---

## Phase 2B: Infrastructure — designSystem.js

> **Agent:** `premium-ui-designer` to implement all JS color constant updates
> **Verify:** `./deploy/fynla-org/build.sh`

- [x] 2B.1 `premium-ui-designer` agent: Update file header to reference `fynlaDesignGuide.md v1.2.0`
- [x] 2B.2 `premium-ui-designer` agent: Update `PRIMARY_COLORS` to Raspberry scale
- [x] 2B.3 `premium-ui-designer` agent: Update `SECONDARY_COLORS` to Horizon scale
- [x] 2B.4 `premium-ui-designer` agent: Update `SUCCESS_COLORS` to Spring Green values
- [x] 2B.5 `premium-ui-designer` agent: Update `ERROR_COLORS` to Raspberry (danger) values
- [x] 2B.6 `premium-ui-designer` agent: Update `WARNING_COLORS` to Violet values
- [x] 2B.7 `premium-ui-designer` agent: Update `INFO_COLORS` to Light Blue values
- [x] 2B.8 `premium-ui-designer` agent: Update `CHART_COLORS` array to new 8-color palette
- [x] 2B.9 `premium-ui-designer` agent: Update `ASSET_COLORS` to new palette hex values
- [x] 2B.10 `premium-ui-designer` agent: Update `SPENDING_COLORS` to closest new palette equivalents
- [x] 2B.11 Review `RISK_COLORS` — kept unchanged (intentionally distinct) ✓
- [x] 2B.12 Review `RISK_TAILWIND_CLASSES` — kept unchanged ✓
- [x] 2B.13 `premium-ui-designer` agent: Update `TEXT_COLORS` to horizon-500, neutral-500, etc.
- [x] 2B.14 `premium-ui-designer` agent: Update `BG_COLORS` to eggshell-500, savannah-100
- [x] 2B.15 `premium-ui-designer` agent: Update `BORDER_COLORS` to light-gray, horizon-300, violet-500
- [x] 2B.16 `premium-ui-designer` agent: Update `CHART_DEFAULTS` fontFamily to `'Segoe UI, Inter, ...'`
- [x] 2B.17 Run `./deploy/fynla-org/build.sh` — build passes ✓

---

## Phase 3: Component Migration (per batch)

> **Agent:** `Explore` agent to identify all files per batch
> **Agent:** `premium-ui-designer` to implement all component color/font migrations
> **Skill:** `/feature-dev` for complex modules requiring architectural understanding
> **Verify:** `./deploy/fynla-org/build.sh` after each batch

### Batch 1: Layouts + Navigation (~10 files)

- [x] 3.1.1 `Explore` agent: Identified all layout/nav component files (10 files)
- [x] 3.1.2 `premium-ui-designer` agent: Migrated AppLayout, PublicLayout, SideMenu, SideMenuItem, SideMenuMobileToggle, SideMenuSection, Navbar, Footer, PrintHeader, PlanSectionHeader
- [x] 3.1.7 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 2: Auth + Onboarding (~24 files)

- [x] 3.2.1 `Explore` agent: Identified all auth/onboarding files (24 files)
- [x] 3.2.2 `premium-ui-designer` agent: Migrated Login, Register, 6 Auth modals, OnboardingWizard, 3 onboarding components, 11 onboarding steps
- [x] 3.2.7 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 3: Dashboard + Net Worth (~52 files)

- [x] 3.3.1 `Explore` agent: Identified all dashboard/plan/NetWorth files (52 files)
- [x] 3.3.2 `premium-ui-designer` agent: Migrated Dashboard view, 16 dashboard components, 28 NetWorth components, 5 Property components, 2 Estate NW components
- [x] 3.3.5 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 5: Investment (~72 files)

- [x] 3.5.1 `Explore` agent: Identified all Investment component files (72 files)
- [x] 3.5.2 `premium-ui-designer` agent: Migrated all Investment components, views, and plan sections
- [x] 3.5.5 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 6: Retirement (~30 files)

- [x] 3.6.1 `Explore` agent: Identified all Retirement component files (30 files)
- [x] 3.6.2 `premium-ui-designer` agent: Migrated 20 Retirement components, 4 views, 6 plan components
- [x] 3.6.6 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 7: Protection (~15 files)

- [x] 3.7.1 `Explore` agent: Identified all Protection component files (15 files)
- [x] 3.7.2 `premium-ui-designer` agent: Migrated 13 components + 2 views
- [x] 3.7.4 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 8: Savings (~14 files)

- [x] 3.8.1 `Explore` agent: Identified all Savings component files (14 files)
- [x] 3.8.2 `premium-ui-designer` agent: Migrated 11 components + 3 views
- [x] 3.8.4 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 9: Estate (~33 files)

- [x] 3.9.1 `Explore` agent: Identified all Estate component files (33 files)
- [x] 3.9.2 `premium-ui-designer` agent: Migrated 31 components + 2 views
- [x] 3.9.4 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 10: Goals & Life Events (~26 files)

- [x] 3.10.1 `Explore` agent: Identified all Goals/Life Events files (26 files)
- [x] 3.10.2 `premium-ui-designer` agent: Migrated 23 Goals components, 1 Actions component, 2 views
- [x] 3.10.4 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 11: Actions & Coordination (covered in Batch 10)

- [x] 3.11.1 Covered in Batch 10 ✓

### Batch 12: Settings & Admin (~19 files)

- [x] 3.12.1 `Explore` agent: Identified all Settings/Admin files (19 files)
- [x] 3.12.2 `premium-ui-designer` agent: Migrated 11 Admin components, 3 Settings views, 1 Admin view, 2 UserProfile, 2 Payment
- [x] 3.12.5 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 13: Public Pages (~9 files)

- [x] 3.13.1 `Explore` agent: Identified all Public page files (9 files)
- [x] 3.13.2 `premium-ui-designer` agent: Migrated Landing, About, Pricing, Calculators, Security, Terms, Privacy, Sitemap, Learning Centre
- [x] 3.13.5 Run `./deploy/fynla-org/build.sh` — build passes ✓

### Batch 14: Shared Components (~30 files)

- [x] 3.14.1 `Explore` agent: Identified all shared/common components (30 files)
- [x] 3.14.2 `premium-ui-designer` agent: Migrated ConfirmDialog, TaxStatusPanel, InfoGuide, CurrencyInputField, PostcodeLookup, ProcessingState, ViewToggle, DocumentUploadModal, AiChat, Preview components, TrialCountdownBanner, etc.
- [x] 3.14.5 Run `./deploy/fynla-org/build.sh` — build passes ✓ (fixed savannah-1000 typo in StrategyCard.vue)

---

## Phase 4: Cleanup — Remove Old Tokens

> **Agent:** `premium-ui-designer` to remove deprecated token definitions
> **Tool:** `Grep` for orphan verification
> **Verify:** `./deploy/fynla-org/build.sh`

- [x] 4.1 Removed old `primary` color family from `tailwind.config.js`
- [x] 4.2 Removed old `secondary` (slate) color family from `tailwind.config.js`
- [x] 4.3 Safelist already updated in Phase 1 — new palette entries present, old entries were risk-level colors (kept) ✓
- [x] 4.4 Run `./deploy/fynla-org/build.sh` — build passes ✓ (fixed theme() refs in CapitalAdequacyTab, scrollbar refs in 3 modals)
- [x] 4.5 `Grep`: `primary-\d` in resources/ — **0 matches** ✓
- [x] 4.6 `Grep`: `text-gray-` in resources/js + resources/css — **0 matches** ✓
- [x] 4.7 `Grep`: `bg-gray-` in resources/js + resources/css — **0 matches** ✓
- [x] 4.8 `Grep`: `border-gray-` in resources/js + resources/css — **0 matches** ✓
- [x] 4.9 (bonus) Fixed 11 hardcoded `#1257A0` (Trust Blue) hex refs across 8 files → raspberry/horizon
- [x] 4.10 (bonus) Fixed 3 `#F59E0B` (amber) hex refs in eventIcons.js → violet
- [x] 4.11 (bonus) Fixed `#0E3A66` hover hex refs → raspberry-600

---

## Phase 5: Documentation Updates

> **Skill:** `/revise-claude-md` for CLAUDE.md updates
> **Tool:** Direct file edits for other docs
> **Final:** `php artisan db:seed`

- [x] 5.1 Renamed `designStyle.md` → `designStyle-legacy.md`, added archive header
- [x] 5.2 Updated `CLAUDE.md` Rule #9 — new forbidden/palette colors (violet for warnings, raspberry for errors, spring for success)
- [x] 5.3 Updated `CLAUDE.md` Rule #11 — references `fynlaDesignGuide.md` v1.2.0
- [x] 5.4 Updated `CLAUDE.md` Rule #12 — color governance, spinner example updated
- [x] 5.5 Updated `resources/js/CLAUDE.md` — designSystem.js description updated for new palette
- [x] 5.6 Updated `MEMORY.md` — design system section with font stack, color mappings, banned tokens
- [x] 5.7 Verified `fynlaDesignGuide.md` Global CSS Utilities section — accurate (horizon-300, raspberry-500, etc.) ✓
- [x] 5.8 Run `php artisan db:seed` — final reseed ✓

---

## Final Verification

> **Tool:** Browser testing via `mcp__playwright` or `mcp__claude-in-chrome`
> **Agent:** `premium-ui-designer` for visual quality assessment across all modules
> **Verify:** `./deploy/fynla-org/build.sh`, `./vendor/bin/pest`, `php artisan db:seed`

- [x] V.1 Run `./deploy/fynla-org/build.sh` — final build ✓ (6.7M)
- [x] V.2 Run `./vendor/bin/pest` — **1603 tests passed (7043 assertions)** ✓
- [x] V.3 Run `php artisan db:seed` ✓
- [ ] V.4 `mcp__claude-in-chrome`: Login as `young_family` persona — verify full module navigation
- [ ] V.5 `mcp__claude-in-chrome`: Login as `peak_earners` persona — verify Investment/Retirement
- [ ] V.6 `mcp__claude-in-chrome`: Login as `widow` persona — verify Estate module
- [x] V.7 Grep: **0 matches** for `#1257A0` in resources/ — no Trust Blue anywhere ✓
- [x] V.8 Code verified: `html, body { background-color: #F7F6F4 }` in app.css ✓
- [x] V.9 Code verified: nav uses `bg-horizon-500`/`bg-horizon-600` (Horizon = #1F2A44) ✓
- [x] V.10 Code verified: `.btn-primary { bg-raspberry-500 }`, all CTA buttons use raspberry ✓
- [x] V.11 Code verified: `.input-field`, `.form-input` use `focus:ring-violet-500 focus:border-violet-500` ✓
- [x] V.12 Code verified: warning=violet, error=raspberry, success=spring in semantic colors & badges ✓
- [x] V.13 Code verified: `CHART_COLORS` and `CHART_DEFAULTS` updated in designSystem.js ✓
- [x] V.14 Grep: **0 matches** for `amber-`/`orange-` in resources/; **0 matches** for `#F59E0B` ✓
- [x] V.15 Created deployment guide: `March4Updates/deployRedesign.md` ✓

---

## Agent & Tool Summary

| Phase | Agent / Tool / Skill | Purpose |
| ----- | -------------------- | ------- |
| 0 | `Explore` agent | Find all old token refs in design guide |
| 0 | `premium-ui-designer` agent | Implement token replacements in guide |
| 1 | `premium-ui-designer` agent | Add new color families, fonts, weights to Tailwind |
| 2A | `premium-ui-designer` agent | Update all CSS component classes to new palette |
| 2B | `premium-ui-designer` agent | Update all JS design constants to new palette |
| 3 (each batch) | `Explore` agent | Identify all files per module |
| 3 (each batch) | `premium-ui-designer` agent | Migrate component colors, fonts, focus states |
| 3 (complex) | `/feature-dev` skill | Guided migration for architecturally complex modules |
| 4 | `premium-ui-designer` agent | Remove deprecated token definitions |
| 4 | `Grep` tool | Verify zero orphaned old token refs |
| 5 | `/revise-claude-md` skill | Update CLAUDE.md with new design rules |
| 5 | `premium-ui-designer` agent | Verify design guide CSS utilities section |
| V | `mcp__claude-in-chrome` | Browser-based visual testing per persona |
| V | `premium-ui-designer` agent | Visual quality assessment across all modules |
| V | `./vendor/bin/pest` | Test suite verification |
| V | `php artisan db:seed` | Final data reseed |

---

## Total Task Count

| Phase        | Tasks |
| ------------ | ----- |
| Phase 0      | 12    |
| Phase 1      | 17    |
| Phase 2A     | 22    |
| Phase 2B     | 17    |
| Phase 3      | 59    |
| Phase 4      | 8     |
| Phase 5      | 8     |
| Verification | 15    |
| **Total**    | **158** |
