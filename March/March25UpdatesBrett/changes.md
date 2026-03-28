# March 25 Updates — Brett v1 Branch

## Summary
Complete public website buildout — 45+ new pages covering Learn, Insights, Why Fynla, Compare, Calculators redesign, Life Stage pages, and contextual feature storytelling.

---

## New Pages Created

### Learn — Concept Explainers (7 pages)
- `/learn/what-is-an-isa` — WhatIsAnIsaPage.vue
- `/learn/what-is-drawdown` — WhatIsDrawdownPage.vue
- `/learn/what-is-salary-sacrifice` — WhatIsSalarySacrificePage.vue
- `/learn/what-is-an-lpa` — WhatIsAnLpaPage.vue
- `/learn/what-is-a-sipp` — WhatIsASippPage.vue
- `/learn/what-is-inheritance-tax` — WhatIsInheritanceTaxPage.vue
- `/learning-centre` — Rewritten as "What is Financial Planning?" explainer

### Learn — Decision Guides (5 pages)
- `/learn/should-i-overpay-my-mortgage` — ShouldIOverpayMortgagePage.vue
- `/learn/should-i-consolidate-pensions` — ShouldIConsolidatePensionsPage.vue
- `/learn/when-should-i-make-a-will` — WhenShouldIMakeAWillPage.vue
- `/learn/should-i-use-a-lisa-or-isa` — ShouldIUseALisaOrIsaPage.vue
- `/learn/when-can-i-afford-to-retire` — WhenCanIAffordToRetirePage.vue

### Learn — Life Stage Guides (5 pages)
- `/learn/guide/starting-out` — StartingOutGuidePage.vue
- `/learn/guide/building-foundations` — BuildingFoundationsGuidePage.vue
- `/learn/guide/protecting-and-growing` — ProtectingAndGrowingGuidePage.vue
- `/learn/guide/planning-your-future` — PlanningYourFutureGuidePage.vue
- `/learn/guide/enjoying-your-wealth` — EnjoyingYourWealthGuidePage.vue

### Learn — Tax & Allowances (5 pages)
- `/learn/tax/isa-allowance` — IsaAllowanceTaxPage.vue
- `/learn/tax/pension-annual-allowance` — PensionAnnualAllowancePage.vue
- `/learn/tax/iht-thresholds` — IhtThresholdsPage.vue
- `/learn/tax/capital-gains-tax` — CapitalGainsTaxPage.vue
- `/learn/tax/tax-year-checklist` — TaxYearChecklistPage.vue

### Learn — Other
- `/learn` — LearnHubPage.vue (redesigned with 4-column content type grid)
- `/learn/glossary` — GlossaryPage.vue (37 terms A-Z with anchor navigation)

### Insights (3 pages)
- `/insights` — InsightsHubPage.vue
- `/insights/pension-iht-changes-2027` — PensionIhtChanges2027Page.vue
- `/insights/isa-allowance-2025-26` — IsaAllowance202526Page.vue

### Why Fynla (4 pages)
- `/why-fynla/our-approach` — OurApproachPage.vue
- `/why-fynla/one-platform` — OnePlatformPage.vue
- `/why-fynla/independent` — IndependentPage.vue
- `/why-fynla/alternatives` — AlternativesPage.vue

### Compare Pages (6 pages — off-nav, SEO-driven)
- `/compare/fynla-vs-projectionlab` — FynlaVsProjectionLabPage.vue
- `/compare/fynla-vs-voyant` — FynlaVsVoyantPage.vue (stresses Voyant is adviser-only)
- `/compare/fynla-vs-moneyhub` — FynlaVsMoneyhubPage.vue
- `/compare/fynla-vs-spreadsheets` — FynlaVsSpreadsheetsPage.vue
- `/compare/fynla-vs-moneyhelper` — FynlaVsMoneyHelperPage.vue (SEO: "MoneyHelper alternative")
- `/compare/best-financial-planning-tools-uk` — BestFinancialPlanningToolsPage.vue

### Contact
- `/contact` — ContactPage.vue

---

## Modified Pages

### How It Works (`/how-it-works`)
- Added "What Fynla Does Differently" section with 5 story cards:
  - Mortgage rate alert (saved thousands)
  - ICE letter (everything in one document)
  - Protection gap (cover you didn't know you were missing)
  - Pension consolidation (6 jobs, 6 forgotten pots)
  - Will guidance (54% of UK adults don't have one)

### Stage Pages (all 5)
- Added contextual "moment" cards to each stage page (2 per page)
- Starting Out: emergency fund, employer pension match
- Building Foundations: mortgage rate alert, ISA allowance tracking
- Protecting and Growing: ICE letter, protection gap
- Planning Your Future: retire 3 years early, pension consolidation
- Enjoying Your Wealth: IHT surprise, will guidance

### Learn Hub (`/learn`)
- Redesigned: nice intro paragraph, 4-column content grid, no "Type N" labels, no italics
- Cards for Glossary, Insights, FAQ below the grid
- Gradient CTA block

### Learning Centre (`/learning-centre`)
- Completely rewritten as "What is Financial Planning?" explainer
- Covers the 6 areas of financial planning

### Navigation (PublicLayout.vue)
- Learn dropdown: Guides & Explainers, Glossary A-Z, Latest Insights, FAQ
- Why Fynla dropdown: Our Approach, One Platform Story, Not Tied to an Adviser, Security & Privacy
- Removed "Fynla vs Alternatives" from nav (off-nav per architecture)
- Removed "Your stage" from footer
- Footer: added "Guides & Explainers" and "What is Financial Planning?" links

### Router (index.js)
- Added 23 new public routes with lazy loading

---

## SEO
- All pages have document.title set with target keywords
- Meta descriptions set programmatically
- Target keywords woven into body text
- Clean URL slugs matching search intent
- Internal linking between related pages

## Design
- All pages use Fynla design tokens (no amber/orange)
- Compact styling: text-sm body, text-xs supporting, py-10 sections
- Hero gradient: from-horizon-500 to-raspberry-500
- British spelling throughout, acronyms spelled out (ISA exception)
