# Wireframe Generation — March 9, Session 4

## Summary

Generated a complete set of browser-viewable HTML wireframes for the entire Fynla v0.8.3 application, covering all 8 modules, 58 views, 45+ modals, and the full navigation structure.

---

## What Was Built

14 files (4,370 lines) saved to `appMapping/wireframe/`:

| File | Content |
|------|---------|
| `index.html` | Master overview — stats, user flow diagram, architecture, route map, navigation links |
| `styles.css` | Shared wireframe styles using Fynla design tokens (Raspberry, Horizon, Spring, Violet, Savannah, Eggshell) |
| `01-public.html` | Landing page (with persona selector), pricing (3 tiers), calculators, learning centre, legal pages |
| `02-auth.html` | Login, register, onboarding wizard (6 steps), focus area selection, 6 auth modals (MFA, verification, password reset, persona intro) |
| `03-dashboard.html` | Main dashboard (24 widget cards), AI chat panel, info guide panel, empty state, preview mode variant |
| `04-net-worth.html` | Wealth summary, property list, pension list, investment list, cash overview, business interests, chattels, liabilities |
| `05-protection.html` | Dashboard (3 tabs: current/gap/what-if), gap analysis table, scenario builder, policy detail, policy form modal |
| `06-savings.html` | Dashboard (3 tabs), emergency fund gauge, ISA tracker, account detail, rate comparison, account form modal |
| `07-investments.html` | Portfolio overview, projections (Monte Carlo), tax efficiency (Bed & ISA, CGT harvesting, asset location), holdings, fees, risk profile, 2 modals |
| `08-retirement.html` | Pension detail (4 tabs: capital adequacy/future value/strategies/income), income gap analysis, decumulation comparison, DC + DB pension forms |
| `09-estate.html` | IHT dashboard with calculation table, NRB/RNRB tracker, mitigation strategies, gifting timeline, trusts dashboard, estate projection comparison, gift + trust modals |
| `10-goals.html` | Goals dashboard (11 types with progress/streaks), life events timeline, net worth projection (3 chart types), goal + contribution modals |
| `11-plans.html` | Plans dashboard, full module plan layout (executive summary, current situation, grouped actions, what-if comparison, conclusion), holistic plan, actions dashboard |
| `12-profile.html` | Personal info, family members, income & tax, expenditure breakdown, personal accounts (P&L/cash flow/balance sheet), settings, checkout (Revolut embed), shared modals |

---

## Data Source

Wireframes were generated from the v0.8.3 application mapping documentation at `appMapping/v083/`:

| Document Used | Content Extracted |
|---------------|-------------------|
| `00-TABLE-OF-CONTENTS.md` | Document structure, component counts |
| `01-OVERVIEW.md` | Tech stack, data flow, modules, personas |
| `05-FRONTEND-ARCHITECTURE.md` | Routes, layouts, navigation, component hierarchy (460 components) |
| `06-API-REFERENCE.md` | API endpoint groupings (~550 routes) |
| `09-MODULES.md` | Module features, screens, calculations |

---

## Design Approach

- **Pure HTML + CSS** — no JavaScript dependencies, opens in any browser
- **Fynla design tokens** — uses actual colour palette (Raspberry CTAs, Horizon text, Spring success, Violet warnings)
- **Wireframe aesthetic** — grey placeholders for charts/images, dashed borders for interactive areas
- **Annotated** — each screen labelled with component name and route path
- **Interlinked** — index page links to all 12 section pages, each page has prev/next navigation
- **Responsive grid** — 2/3/4 column grids matching actual app layout patterns

---

## Coverage

| Area | Screens | Modals |
|------|---------|--------|
| Public | 11 | — |
| Auth & Onboarding | 4 | 6 |
| Dashboard | 3 (main, empty, preview) | — |
| Net Worth | 8 sub-views | — |
| Protection | 4 | 1 |
| Savings | 4 | 1 |
| Investments | 6 | 2 |
| Retirement | 4 | 2 |
| Estate | 5 | 2 |
| Goals & Life Events | 4 | 2 |
| Plans & Actions | 4 | — |
| Profile & Settings | 6 | 2 |
| **Total** | **~63** | **~18** |

Plus 2 slide-out panels (AI Chat, Info Guide).

---

## How to View

Open `appMapping/wireframe/index.html` in any browser. Navigate via the linked grid on the index page or use prev/next links at the bottom of each page.

---

## No Production Deployment Required

These are static HTML documentation files in the gitignored `appMapping/` directory. No application code was changed.
