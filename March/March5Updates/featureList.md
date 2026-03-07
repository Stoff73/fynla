# Fynla Feature List — v0.8.3

**Date:** 5 March 2026
**Total:** 329 Vue components, 163 PHP services, 69 controllers, 74 models, 21 Vuex stores, 8 agents

---

## 1. Authentication & Security

### Description
Multi-step authentication system with email/password registration, two-factor authentication (TOTP), recovery codes, password reset flows, session management, and login attempt tracking with account lockout.

### Integration & Connection
- **Middleware chain**: `Authenticate`, `EnsureMFAVerified`, `CheckSubscription` gates all protected API routes
- **Session management**: Concurrent session tracking across tabs via `sessionLifecycleService.js`
- **Preview mode**: `PreviewWriteInterceptor` middleware intercepts all write operations for preview users, returning fake success responses while allowing read-only calculations
- **GDPR**: MFA verification required before data export or erasure requests
- **Vuex**: `auth` store triggers logout cascade across all 21 store modules (`resetState` dispatches)

---

## 2. Onboarding Wizard

### Description
Multi-step setup wizard for new users covering personal information, spouse details, planning assumptions, lifestyle details, and focus area selection. Users can skip steps and return later. Tracks onboarding progress and validates each step before advancing.

### Integration & Connection
- **Profile completeness**: `ProfileCompletenessChecker` assesses data gaps after onboarding, surfacing missing fields on the dashboard via `AreasToCompleteCard`
- **Module data requirements**: `ModuleDataRequirementsService` determines which fields each financial module needs, driving the onboarding step order
- **Focus areas**: Selected focus areas influence which dashboard cards and module recommendations appear first
- **Assumptions**: Planning assumptions (inflation rate, growth rates, life expectancy) set during onboarding flow into all projection calculations across every module

---

## 3. Dashboard (Main Hub)

### Description
Central financial overview displaying net worth summary, investment overview, goals progress, recommended actions, affordability assessment, tax optimisation opportunities, alerts, and areas requiring attention. Supports widget reordering and personalised layout.

### Integration & Connection
- **Aggregation**: `DashboardAggregator` service pulls data from all 7 module agents to compose the dashboard view
- **Net worth card**: Reads from `netWorth` Vuex store, which aggregates properties, investments, savings, pensions, business interests, and chattels
- **Actions card**: Pulls from `RecommendationsAggregatorService` which collects and ranks recommendations from Protection, Savings, Retirement, Investment, and Estate modules
- **Financial health**: `FinancialHealthScore` component synthesises module-level health into descriptive labels (Good/Fair/Needs attention) — no numeric scores shown per Rule 12
- **Tax card**: Reads ISA allowance remaining, pension annual allowance, and CGT allowance from `TaxConfigService`
- **Alerts**: Driven by profile completeness gaps, subscription status, and module-specific warnings

---

## 4. Net Worth Module

### Description
Comprehensive asset and liability tracking across all account types: properties, mortgages, investment accounts, savings accounts, pensions, business interests, personal chattels, and cash holdings. Provides wealth summary, joint account history, and detailed breakdowns by asset class.

### Integration & Connection
- **Central hub**: `netWorth/refreshNetWorth` Vuex action is dispatched by every module store (investment, savings, retirement, estate, business interests, chattels) whenever asset values change
- **Estate module**: Net worth data feeds directly into IHT liability calculations — the estate module aggregates all assets via `EstateAssetAggregatorService`
- **Joint ownership**: Uses single-record pattern with `joint_owner_id` and `ownership_percentage`; `CalculatesOwnershipShare` trait splits values between spouses
- **Query pattern**: All joint assets queried with `WHERE user_id = ? OR joint_owner_id = ?` via `HasJointOwnership` trait's `forUserOrJoint()` scope
- **Holistic planning**: `HolisticPlanner` calls `CrossModuleAssetAggregator` to build 20-year net worth projections

---

## 5. Protection Module (Insurance Planning)

### Description
Life insurance, critical illness, income protection, disability insurance, and sickness/illness policy management. Analyses coverage gaps against income replacement needs, generates protection recommendations, and builds what-if coverage scenarios. Supports five policy types with full CRUD.

### Integration & Connection
- **Agent**: `ProtectionAgent` orchestrates `CoverageGapAnalyzer`, `RecommendationEngine`, `AdequacyScorer`, and `ScenarioBuilder`
- **Cashflow impact**: Protection premiums reduce available monthly surplus in `CashFlowCoordinator.calculateCommittedContributions()`, affecting funding available for savings, investment, and goal contributions
- **Coordination conflicts**: `ConflictResolver` handles `protection_vs_savings_conflict` when protection premiums compete with emergency fund contributions
- **Estate integration**: `LifeCoverCalculator` in the Estate module recommends life cover amounts needed to pay projected IHT liability
- **Risk observers**: `FamilyMemberRiskObserver` triggers risk recalculation when dependents change, which cascades to protection adequacy reassessment
- **Plans**: `ProtectionPlanService` generates comprehensive protection plans with toggleable actions and what-if scenario projections
- **Admin**: `ProtectionActionDefinition` model stores recommendation templates managed via admin panel

---

## 6. Savings Module

### Description
Savings account and cash account tracking with ISA allowance monitoring (GBP 20,000/year), emergency fund adequacy analysis (3-6 months expenses), savings goal progress, market rate comparison, and liquidity analysis. Supports ISA, cash, and notice account types.

### Integration & Connection
- **Emergency fund priority**: Emergency fund adequacy is the highest-priority item in `CashFlowCoordinator.optimizeContributionAllocation()` — funded before protection, pensions, or investments
- **ISA conflict**: `ConflictResolver` handles `isa_allowance_conflict` when multiple modules (savings and investment) compete for the GBP 20,000 annual ISA allowance
- **Goal linking**: Savings accounts can be linked to goals via `linked_savings_account_id`; `SavingsAccountGoalObserver` auto-records contributions when balances increase
- **Tax awareness**: Interest income subject to Personal Savings Allowance (varies by marginal tax rate) via `TaxConfigService`
- **Net worth**: Balance changes trigger `netWorth/refreshNetWorth` dispatch
- **Risk cascade**: `SavingsAccountRiskObserver` triggers risk recalculation when emergency fund adequacy changes
- **Recommendations**: `RecommendationsAggregatorService` extracts emergency fund and ISA allowance recommendations from `SavingsAgent.analyze()`

---

## 7. Investment Module

### Description
Investment account management covering ISA, SIPP, GIA, CTF, and employee share schemes. Features include holdings management, portfolio analysis, asset allocation, diversification analysis, rebalancing calculations, fee analysis (OCF impact), tax optimisation (bed & ISA, CGT harvesting), asset location optimisation, efficient frontier calculations (Markowitz), Monte Carlo simulations, contribution planning, performance attribution (alpha/beta), benchmark comparison, and model portfolios.

### Integration & Connection
- **9 service subdirectories**: Analytics (efficient frontier, correlation matrix, covariance), Rebalancing (drift analysis, tax-aware rebalancing), AssetLocation (account type recommendations, tax drag), Tax (ISA optimisation, bed & ISA, CGT harvesting), Fees (OCF impact, platform comparison), Goals (progress analysis, probability, shortfall), Performance (attribution, alpha/beta, benchmark), ModelPortfolio, Utilities (matrix operations, statistical functions)
- **Risk profile**: `InvestmentAccountRiskObserver` triggers risk recalculation when portfolio values change; risk tolerance from `RiskProfile` model informs asset allocation targets
- **Goal tracking**: `InvestmentAccountGoalObserver` auto-records `GoalContribution` entries when linked account balances increase
- **ISA strategy**: `ISAAllowanceOptimizer` coordinates with savings module to maximise tax-free wrapper usage across both account types
- **Retirement cascade**: `RetirementAgent` analysis triggers `investment/fetchAccounts` dispatch when accumulation context is needed
- **Net worth**: Account value changes trigger `netWorth/refreshNetWorth`
- **Estate**: Investment accounts included in IHT calculations via `EstateAssetAggregatorService`
- **Plans**: `InvestmentPlanService` generates plans with account-level grouped actions and reactive what-if projections
- **Projection charts**: `InvestmentProjectionChart` uses `ERROR_COLORS` from `designSystem.js` for life event expense annotations

---

## 8. Retirement Module

### Description
Pension planning covering defined benefit (DB), defined contribution (DC), and state pension tracking. Features include retirement income projections, required capital calculations, annual allowance checking (GBP 60,000 with MPAA), drawdown simulations, decumulation planning, pension portfolio analysis, contribution optimisation, withdrawal strategies, and accumulation/depletion charts.

### Integration & Connection
- **Agent**: `RetirementAgent` orchestrates `PensionProjector`, `RetirementIncomeService`, `RequiredCapitalCalculator`, `DecumulationPlanner`, `AnnualAllowanceChecker`, and `ContributionOptimizer`
- **Cross-module cascade**: Retirement analysis triggers both `investment/fetchAccounts` and `savings/fetchSavingsData` dispatches to assess total accumulation capacity
- **Tax integration**: Pension contributions receive tax relief calculated via `TaxConfigService`; withdrawals taxed via `UKTaxCalculator` (25% tax-free, remainder at marginal rate)
- **Risk cascade**: `DCPensionRiskObserver` triggers risk recalculation when pension values or contributions change
- **Cashflow**: Pension contributions are committed costs in `CashFlowCoordinator`, reducing surplus available for other modules
- **Estate**: Pension pots included in estate net worth; DC pensions typically outside IHT but DB pensions may be included
- **Plans**: `RetirementPlanService` generates plans with per-pension grouped actions and contribution increase scenarios
- **Monte Carlo**: `MonteCarloSimulator` provides probability-based projections factoring in life events via `LifeEventMonteCarloObserver`
- **Admin**: `RetirementActionDefinition` model stores recommendation templates

---

## 9. Estate Planning Module

### Description
Inheritance tax (IHT) liability calculation, will management, bequest planning, gifting strategy optimisation (annual exemptions, PETs, CLTs), trust management (creation, periodic charges, tax returns), life cover recommendations for IHT, intestacy calculation, asset liquidity analysis, cash flow projection, and net worth analysis for estate purposes. Supports dual-spouse estate planning with NRB/RNRB transfer tracking.

### Integration & Connection
- **Estate as aggregation hub**: `EstateAssetAggregatorService` pulls assets from ALL modules — properties, investments, savings, pensions, business interests, chattels, trusts — to calculate total estate value
- **IHT engine**: `IHTCalculationService` applies NRB (GBP 325,000), RNRB (GBP 175,000 for main residence), taper rules, charitable rate (36%), and spouse exemptions via `TaxConfigService`
- **Net worth dependency**: Estate IHT calculations update whenever `netWorth/refreshNetWorth` fires, which happens on any asset change in any module
- **Gifting strategies**: `GiftingStrategyOptimizer` and `PersonalizedGiftingStrategyService` generate tax-efficient gifting plans using annual exemption, small gifts, and PET taper rules
- **Trust planning**: `TrustValuationService` calculates 10-yearly periodic charges and exit charges; `PersonalizedTrustStrategyService` recommends trust structures
- **Life cover**: `LifeCoverCalculator` determines insurance needed to cover projected IHT liability, feeding recommendations back to Protection module
- **Will analysis**: `WillAnalysisService` checks will provisions; `IntestacyCalculator` shows distribution under intestacy rules
- **Plans**: `EstatePlanService` generates IHT mitigation plans with toggleable strategies and what-if projections
- **Recommendations**: `RecommendationsAggregatorService` extracts estate actions from `implementation_timeline` with `is_numeric()` guards on `iht_saving` values
- **Joint ownership**: All joint assets appear in both spouses' estate calculations using `CalculatesOwnershipShare` trait

---

## 10. Goals & Life Events Module

### Description
Financial goal tracking (education, wedding, home purchase, retirement, custom goals) with milestone progress, contribution tracking, streak monitoring, and affordability analysis. Life events planning (marriage, children, career changes, property purchase) with allocation to goals, cash flow impact assessment, and integration with projection charts as SVG icon overlays.

### Integration & Connection
- **Goal-account linking**: Goals link to savings accounts (`linked_savings_account_id`) and investment accounts (`linked_investment_account_id`); auto-contribution tracking via `SavingsAccountGoalObserver` and `InvestmentAccountGoalObserver`
- **Affordability cascade**: `GoalAffordabilityService` uses `CashFlowCoordinator` surplus data to determine if goals are achievable; when protection premiums increase, goal affordability decreases
- **Monte Carlo**: `LifeEventMonteCarloObserver` triggers Monte Carlo simulations when life events change, recalculating goal probability
- **Estate integration**: Life events (having children) trigger estate recommendations (will provisions, guardianship, trusts for minors)
- **Projection charts**: Life event SVG icons rendered on `InvestmentProjectionChart` and `PensionPotProjectionChart` via `EventIconsOverlay`
- **Plans**: `GoalPlanService` generates goal-specific plans with tax-aware funding source dropdowns
- **Cashflow priority**: Goals are the lowest priority in `CashFlowCoordinator.optimizeContributionAllocation()` — funded after emergency fund, protection, pension, investment, and estate

---

## 11. Coordination & Holistic Planning

### Description
Cross-module analysis orchestration, recommendation aggregation, conflict resolution, priority ranking, cashflow coordination, and holistic financial plan generation. The coordinating agent synthesises all module analyses into a unified financial picture with executive summary, net worth projections, prioritised actions, and resource allocation.

### Integration & Connection
- **Apex orchestrator**: `CoordinatingAgent` calls all 6 module agents' `analyze()` methods with error-safe wrapping, collecting scores, recommendations, and full analysis from each
- **Cashflow optimisation**: `CashFlowCoordinator.optimizeContributionAllocation()` distributes monthly surplus across modules in priority order: Emergency Fund > Protection > Pension > Investment > Estate > Goals
- **Conflict resolution**: `ConflictResolver` handles three conflict types: `protection_vs_savings_conflict`, `cashflow_conflict` (demands exceed surplus), `isa_allowance_conflict` (multiple modules competing for GBP 20,000 ISA limit)
- **Priority ranking**: `PriorityRanker` scores recommendations by urgency and user preferences
- **Holistic plan**: `HolisticPlanner.createHolisticPlan()` generates executive summary, net worth projections, risk assessment, and ranked recommendations
- **Recommendation tracking**: `RecommendationTracking` model persists recommendation status (pending/in-progress/done/dismissed) with notes
- **Plans system**: All plan services (`InvestmentPlanService`, `RetirementPlanService`, etc.) feed into holistic plan via `HolisticPlanContent.vue`

---

## 12. Plans System (Financial Plans)

### Description
Comprehensive, multi-section financial plans for each module (Investment, Protection, Retirement, Estate, Goals) plus a holistic plan aggregating all modules. Each plan features: executive summary with personalised narrative, current situation analysis, toggleable action items with reactive what-if projections, scenario comparison charts, dynamic conclusion, and PDF export. Database-driven action definitions with admin CRUD interfaces.

### Integration & Connection
- **What-if engine**: `WhatIfCalculator` recalculates projections when users enable/disable actions, showing precise impact of each recommendation
- **Per-module plans**: Each plan service (`InvestmentPlanService`, `RetirementPlanService`, `ProtectionPlanService`, `EstatePlanService`, `GoalPlanService`) generates structured data with executive summary, current situation, actions, and conclusion
- **Holistic plan**: `HolisticPlanContent.vue` aggregates all module plans with priority allocation across modules
- **Action definitions**: `RetirementActionDefinition`, `InvestmentActionDefinition`, `ProtectionActionDefinition` models store recommendation templates; admin panel provides CRUD interfaces
- **Plan configuration**: `PlanConfigService` with `PlanConfiguration` model stores per-plan settings
- **Funding sources**: `PlanActionFundingSelection` model tracks which accounts fund each action; tax-aware funding source dropdowns in goal plans
- **PDF export**: `planPrintMixin` handles print layout for all plan types with correct charts and headers
- **Shared components**: `PlanSectionHeader`, `PlanActionCard`, `PlanActionsList`, `PlanWhatIfComparison`, `PlanConclusion`, `PlanMissingDataPrompt`, `PlanErrorState`, `PlanGoalSection`

---

## 13. Risk Profiling

### Description
Investment risk assessment via questionnaire-based risk tolerance evaluation, risk capacity analysis (capacity for loss, time horizon), risk factor breakdown, and auto-risk calculation based on age and financial circumstances. Risk levels explained with educational content.

### Integration & Connection
- **Reactive cascades**: 6 risk observers (`UserRiskObserver`, `PropertyRiskObserver`, `InvestmentAccountRiskObserver`, `SavingsAccountRiskObserver`, `DCPensionRiskObserver`, `FamilyMemberRiskObserver`) trigger `RecalculateRiskProfileJob` when relevant data changes
- **Debouncing**: Cache-based 5-second debounce prevents duplicate recalculation jobs during rapid data entry
- **Investment impact**: `RiskProfile` model informs asset allocation targets, rebalancing thresholds, and investment recommendations
- **Portfolio alignment**: Investment module uses risk level to recommend appropriate asset allocation and flag misaligned portfolios
- **All modules affected**: Any user profile, income, family, or asset change triggers risk recalculation, which cascades to protection adequacy, investment suitability, retirement projections, and goal affordability

---

## 14. Actions & Recommendations

### Description
Consolidated action items and recommendations aggregated from all financial modules, presented in a prioritised dashboard view with filtering by module, priority, and timeline. Users can mark actions as done, in-progress, or dismissed, and add notes.

### Integration & Connection
- **Aggregation**: `RecommendationsAggregatorService` collects recommendations from Protection (`['data']['recommendations']` + gaps), Savings (`['emergency_fund']` + `['isa_allowance']`), Retirement (`['data']['recommendations']` + `['data']['summary']` shortfall), and Estate (`['implementation_timeline']`)
- **Priority sorting**: All recommendations sorted by `priority_score` descending (90 = critical emergency fund, 80 = income shortfall, 70 = coverage gaps, 55 = ISA headroom)
- **Status tracking**: `RecommendationTracking` model persists per-user recommendation status
- **Summary statistics**: `getSummary()` provides counts by priority, module, timeline, plus total potential benefit and estimated cost
- **Dashboard integration**: `ActionsOverviewCard` on main dashboard shows top recommendations
- **Plans integration**: Plan action items link back to recommendation definitions

---

## 15. Property & Mortgage Management

### Description
Property portfolio management supporting main residence, secondary residence, and buy-to-let properties. Features include property valuation and growth projection, mortgage management (repayment, interest-only, mixed), amortisation schedules, Stamp Duty Land Tax (SDLT) calculation, Capital Gains Tax (CGT) on disposal, rental income tax calculation, and joint ownership support.

### Integration & Connection
- **Net worth**: Property values and mortgage balances feed into net worth calculations; changes trigger `netWorth/refreshNetWorth`
- **Estate**: Properties are the primary asset class for IHT; main residence qualifies for RNRB (GBP 175,000); `EstateAssetAggregatorService` includes all properties
- **Risk cascade**: `PropertyRiskObserver` triggers risk recalculation when property values change
- **Tax calculations**: SDLT, CGT, and rental income tax use `TaxConfigService` and `UKTaxCalculator`; Section 24 mortgage interest restriction applied to rental properties
- **Joint ownership**: Properties use single-record pattern with `joint_owner_id`; each spouse's share calculated via `CalculatesOwnershipShare`
- **Goals**: Property purchase goals link to projected deposit amounts and mortgage affordability

---

## 16. Business Interests & Chattels

### Description
Business interest tracking (unquoted company shares, partnerships) with valuation, tax deadline tracking, exit calculations, and CGT computation. Personal chattel management (art, jewellery, antiques, vehicles) with valuation and CGT calculation for high-value items. Both support joint ownership.

### Integration & Connection
- **Net worth**: Business interests and chattels included in total net worth via `CrossModuleAssetAggregator`
- **Estate**: Both asset types included in IHT calculations; Business Property Relief (BPR) may reduce taxable value; chattels subject to special CGT rules (chattel exemption for items under GBP 6,000)
- **Joint ownership**: Both models support `joint_owner_id` with `HasJointOwnership` trait
- **Tax**: CGT calculations via `ChattelCGTService` and `BusinessInterestService` using `TaxConfigService` rates

---

## 17. Tax Configuration & Calculations

### Description
Centralised UK tax configuration management covering income tax bands and allowances, National Insurance rates, dividend and savings allowances, IHT thresholds, ISA limits, pension annual allowance, CGT rates, and SDLT bands. Supports multiple tax years with version control and audit trail. Admin interface for creating, editing, activating, and duplicating tax configurations.

### Integration & Connection
- **Single source of truth**: `TaxConfigService` is a request-scoped singleton used by every module — never hardcode tax values
- **Used by**: Estate (IHT NRB/RNRB), Savings (ISA allowance), Retirement (pension AA/MPAA), Investment (CGT rates, dividend allowance), Property (SDLT, rental income), Protection (premium deductibility)
- **UKTaxCalculator**: Primary tax engine calculating income tax + NI across employment, self-employment, rental, pension, interest, dividends, and trust income
- **Fallback**: `TaxDefaults` constants provide fallback values when `TaxConfigService` is unavailable
- **Admin**: Tax configuration CRUD with activation, duplication, and version audit trail via `TaxConfigurationAudit` model
- **Tax product info**: `TaxProductInfoService` provides product-specific tax information (ISA rules, SIPP rules) displayed in the frontend

---

## 18. Payments & Subscriptions

### Description
Subscription plan management with three tiers (Student GBP 3.99/GBP 30, Standard GBP 10.99/GBP 100, Pro GBP 19.99/GBP 200). Payment processing via Revolut SDK (CDN-loaded `embeddedCheckout()`), trial period activation and expiry, billing history, subscription cancellation with data purge option, and webhook handling for payment confirmation.

### Integration & Connection
- **Revolut integration**: `RevolutService` handles order creation and confirmation; webhook handler processes payment events
- **Trial management**: `TrialService` manages trial activation, expiry tracking, and countdown banner display
- **Subscription gating**: `CheckSubscription` middleware enforces feature access based on subscription tier
- **Data purge**: `DataPurgeService` handles complete user data deletion on subscription cancellation
- **Frontend**: `CheckoutPage.vue` with `PlanSelectionModal.vue` for plan selection; `SubscriptionManagement.vue` for billing and cancellation
- **Preview mode**: Payment routes excluded from `PreviewWriteInterceptor` so checkout flow works in demo mode

---

## 19. Document Upload & AI Extraction

### Description
Document upload system with type detection for pension statements, insurance policies, investment reports, and bank statements. AI-powered field extraction maps document data to database fields. Supports images, PDFs, and Excel files with automatic resizing and processing. Users can confirm, reject, or reprocess extractions.

### Integration & Connection
- **Field mappers**: Specialised mappers for DB pensions (`DBPensionMapper`), DC pensions (`DCPensionMapper`), investment accounts (`InvestmentAccountMapper`), and life insurance (`LifeInsuranceMapper`)
- **Module integration**: Extracted data populates pension, investment, and insurance records, reducing manual data entry
- **AI service**: `AIExtractionService` handles document analysis; `DocumentTypeDetector` identifies document category
- **Processing pipeline**: `DocumentProcessor` orchestrates upload, type detection, extraction, and field mapping

---

## 20. AI Chat Assistant

### Description
Conversational AI assistant for financial planning questions. Context-aware chat interface that understands the user's financial situation, provides personalised guidance, and can execute financial tools. Supports conversation history and intent matching.

### Integration & Connection
- **Context building**: `AiContextBuilder` constructs financial context from all modules for AI responses
- **Intent matching**: `AiIntentMatcher` detects user intent and routes to appropriate tools
- **Tool execution**: `AiToolExecutor` with `AiToolDefinitions` enables AI to query financial data
- **Preview mode**: `AiSimulatedService` provides simulated responses for preview users
- **Frontend**: `AiChatPanel` with `AiChatButton` launcher; `aiChat` Vuex store manages conversation state
- **Info guide**: Opening AI chat dispatches `infoGuide/close` to avoid UI conflict

---

## 21. User Profile & Settings

### Description
Personal information management (name, DOB, marital status, nationality, UK residency), income and occupation tracking, expenditure profiling, domicile information (deemed domicile calculation), family member management, spouse data sharing with permission system, planning assumptions (inflation, growth, life expectancy), privacy controls, security settings (MFA, sessions, passwords), and subscription management.

### Integration & Connection
- **Income/expenditure**: User income and expenditure fields are used by `CashFlowCoordinator` to calculate monthly surplus; changes trigger risk recalculation via `UserRiskObserver`
- **Family members**: `FamilyMemberRiskObserver` triggers risk recalculation when dependents change; family data feeds into protection adequacy (income replacement for dependents) and estate planning (beneficiary provisions)
- **Spouse permissions**: `SpousePermissionController` manages joint account access; `Household` model links spouses
- **Assumptions**: User assumptions (inflation rate, investment returns, life expectancy) flow into all projection services across every module
- **Profile completeness**: `ProfileCompletenessChecker` identifies missing fields; gaps displayed on dashboard and influence module recommendations
- **Expenditure**: `ResolvesExpenditure` trait provides priority-based expenditure resolution used by emergency fund calculator and cashflow coordinator
- **Income**: `ResolvesIncome` trait provides income resolution for tax calculations and affordability analysis

---

## 22. GDPR & Data Privacy

### Description
GDPR compliance features including consent management (marketing, analytics), consent history tracking, data export (CSV/JSON), and data erasure with multi-step verification (email code + MFA). Full audit trail of consent changes.

### Integration & Connection
- **Consent tracking**: `ConsentService` manages consent records with history; `UserConsent` model stores per-user consents
- **Data export**: `DataExportService` generates comprehensive export of all user data across all modules
- **Data erasure**: `DataErasureService` performs complete data deletion with verification; requires MFA confirmation for security
- **Audit**: All consent changes recorded in audit trail
- **Privacy settings**: `PrivacySettings.vue` provides user-facing controls for consents, export, and erasure

---

## 23. Preview Mode (Demo Testing)

### Description
Anonymous testing system with 7 pre-populated personas (young family, peak earners, widow, entrepreneur, young saver, retired couple, student). Users can explore all features without registration. Preview users see realistic financial data but cannot persist changes.

### Integration & Connection
- **Persona data**: `PreviewUserSeeder` creates complete financial profiles for each persona with properties, pensions, investments, savings, insurance, goals, and family members
- **Write interception**: `PreviewWriteInterceptor` middleware returns fake success responses for all POST/PUT/DELETE requests from preview users, except excluded routes (auth, preview switching, calculations)
- **Frontend directive**: `v-preview-disabled` directive blocks interactive elements with tooltip explanation
- **Mixin**: `previewModeMixin` provides `isPreviewMode` computed property and `previewGuard()` method for conditional logic
- **Landing page**: Persona selector on `LandingPage.vue` allows instant access to demo accounts

---

## 24. Admin Panel

### Description
Administration dashboard with user management (CRUD), role and permission management, database backup/restore, subscription statistics, tax configuration management, and action definition management for protection, investment, and retirement recommendation templates.

### Integration & Connection
- **User management**: Create, edit, delete users with role assignment; view subscription stats
- **Action definitions**: Admin-managed templates for module recommendations; `ProtectionActionDefinition`, `InvestmentActionDefinition`, `RetirementActionDefinition` models with enable/disable toggle
- **Tax settings**: Create, edit, activate, and duplicate tax configurations across tax years
- **Database operations**: Backup and restore functionality for data management
- **Role-based access**: `HasRole` and `HasPermission` middleware gates admin routes; permissions include `admin`, `tax_config`, `users.edit`

---

## 25. Guidance & Help System

### Description
Contextual in-app guidance with tooltips, welcome modals, side panel information guides, and educational content. Help page with FAQ and support resources. Learning centre with financial education articles.

### Integration & Connection
- **Info guide panel**: `InfoGuidePanel` sidebar provides contextual help for the current page; `infoGuide` Vuex store manages visibility
- **Guidance system**: `guidance` Vuex store tracks which guidance has been shown; `GuidanceWelcomeModal` shows first-visit guidance
- **Module requirements**: `ModuleDataRequirementsService` determines what info to show based on missing data
- **Public pages**: `LearningCentre.vue`, `Help.vue`, `CalculatorsPage.vue` provide standalone educational content

---

## 26. Public Pages & Marketing

### Description
Landing page with persona selector and feature overview, pricing page with subscription tiers, financial calculators, learning centre, about page, security information, privacy policy, terms of service, and sitemap.

### Integration & Connection
- **Landing page**: Entry point for preview mode; persona selector triggers `PreviewController.login()`
- **Pricing**: Links to `CheckoutPage.vue` for subscription purchase; displays `SubscriptionPlan` data from database
- **Calculators**: Standalone financial calculators accessible without authentication
- **SEO**: `SitemapPage.vue` provides crawlable page listing
- **Layout**: All public pages use `PublicLayout` with navigation and footer; authenticated pages use `AppLayout`

---

## 27. Audit Logging

### Description
Comprehensive audit trail for sensitive operations across all modules. Tracks who performed what action, when, and on which record. Supports audit log purge for data retention compliance.

### Integration & Connection
- **Auditable trait**: Applied to models needing change tracking; auto-records create/update/delete operations via observers
- **Audit service**: `AuditService` handles audit log creation and retrieval
- **Purge command**: `php artisan audit:purge` artisan command removes old audit entries
- **GDPR compliance**: Audit logs included in data export and subject to erasure requests
- **Tax config**: `TaxConfigurationAudit` provides separate audit trail for tax configuration changes

---

## 28. Bug Reporting

### Description
In-app bug reporting allowing users to submit bug reports with context about their current state. Reports capture user information and environment details.

### Integration & Connection
- **Frontend**: `BugReportModal.vue` provides submission form accessible from navigation
- **Backend**: `BugReportController` handles report creation and storage
- **Context**: Bug reports include user ID, browser info, and current page context

---

## 29. Spouse Data Sharing & Household Management

### Description
Spouse permission system enabling couples to share financial data for joint planning. Includes permission request/accept/reject/revoke workflow, household linking, and spouse financial commitment visibility.

### Integration & Connection
- **Permission flow**: `SpousePermissionController` manages request → accept → linked state
- **Household model**: Links spouse accounts for joint data access
- **Joint assets**: Once linked, joint assets visible to both spouses via `HasJointOwnership` queries
- **Estate planning**: Critical for dual-spouse IHT calculations with NRB/RNRB transfer
- **Letter to spouse**: `LetterToSpouseService` generates estate guidance documents for the surviving spouse
- **Frontend**: `SpouseDataSharing.vue` component in user profile; `SpouseSuccessModal.vue` confirms linking

---

## 30. Cash Management

### Description
Cash account tracking with balance trends, spending analysis by category, banking insights, and cash-related action recommendations. Provides a consolidated view of all cash holdings across accounts.

### Integration & Connection
- **Net worth**: Cash balances included in total net worth calculations
- **Emergency fund**: Cash accounts feed into emergency fund adequacy assessment in `EmergencyFundCalculator`
- **Liquidity**: `LiquidityAnalyzer` in savings module assesses overall liquidity including cash holdings
- **Dashboard**: `CashOverview.vue` provides detailed cash breakdown with charts
- **Estate**: Cash holdings included in estate asset aggregation for IHT

---

## Cross-Cutting Architecture

### Shared Traits
| Trait | Purpose | Used By |
|-------|---------|---------|
| `Auditable` | Auto-audit create/update/delete | Models needing change tracking |
| `HasJointOwnership` | Query scopes for joint assets | Property, SavingsAccount, InvestmentAccount, Goal, BusinessInterest, Chattel |
| `CalculatesOwnershipShare` | Calculate user's share of joint assets | Net worth, estate, and coordination services |
| `FormatsCurrency` | Currency formatting helpers | All agents and services returning formatted output |
| `StructuredLogging` | Contextual logging | Services and controllers |
| `ResolvesExpenditure` | Priority-based expenditure resolution | Emergency fund, cashflow, affordability services |
| `ResolvesIncome` | Income resolution for calculations | Tax, cashflow, and affordability services |
| `TracksGoalContributions` | Auto-record goal contributions | Investment and savings account observers |
| `PolicyCRUDTrait` | Insurance policy CRUD operations | ProtectionController |

### Observer System (Reactive Data Flow)
| Observer | Trigger | Effect |
|----------|---------|--------|
| `UserRiskObserver` | Income, age, retirement age change | Risk recalculation across all modules |
| `PropertyRiskObserver` | Property value change | Risk recalculation |
| `InvestmentAccountRiskObserver` | Portfolio value change | Risk recalculation |
| `SavingsAccountRiskObserver` | Emergency fund adequacy change | Risk recalculation |
| `DCPensionRiskObserver` | Pension value/contribution change | Risk recalculation |
| `FamilyMemberRiskObserver` | Dependents change | Risk + protection recalculation |
| `InvestmentAccountGoalObserver` | Account balance increase | Auto goal contribution record |
| `SavingsAccountGoalObserver` | Account balance increase | Auto goal contribution record |
| `LifeEventMonteCarloObserver` | Life event created/updated | Monte Carlo simulation trigger |

All risk observers use 5-second cache-based debouncing to batch rapid changes into single recalculation jobs.

### Cashflow Priority Order
```
1. Emergency Fund    (urgency >= 80)
2. Protection        (urgency >= 80)
3. Pension           (standard priority)
4. Investment        (standard priority)
5. Estate            (standard priority)
6. Goals             (lowest priority)
```

### Frontend Store Dispatch Chain
Every module store dispatches `netWorth/refreshNetWorth` on asset changes, which cascades to estate IHT recalculation, dashboard refresh, and holistic plan updates. Logout triggers reset cascade across all 21 store modules.
