import { createRouter, createWebHistory } from 'vue-router';
import store from '@/store';
import api from '@/services/api';
import analyticsService from '@/services/analyticsService';
import { capabilityForRoute, isRouteGated } from '@/constants/tierAccess';
import { getTokenSync } from '@/services/tokenStorage';
import { hasConsent } from '@/utils/cookieConsent';
import { shouldLoadAwin, loadMasterTag as loadAwinMasterTag, unloadMasterTag as unloadAwinMasterTag } from '@/utils/awinTracking';

// Lazy load components
// Public pages
const LandingPage = () => import('@/views/Public/LandingPage.vue');
const CalculatorsPage = () => import('@/views/Public/CalculatorsPage.vue');
const SecurityPage = () => import('@/views/Public/SecurityPage.vue');
const AboutPage = () => import('@/views/Public/AboutPage.vue');
const PricingPage = () => import('@/views/Public/PricingPage.vue');
const SitemapPage = () => import('@/views/Public/SitemapPage.vue');
const PrivacyPolicyPage = () => import('@/views/Public/PrivacyPolicyPage.vue');
const TermsOfServicePage = () => import('@/views/Public/TermsOfServicePage.vue');
const EditorialPolicyPage = () => import('@/views/Public/EditorialPolicyPage.vue');
const HowItWorksPage = () => import('@/views/Public/HowItWorksPage.vue');
const AdvisorsPage = () => import('@/views/Public/AdvisorsPage.vue');
const FeaturesPage = () => import('@/views/Public/FeaturesPage.vue');
const FaqPage = () => import('@/views/Public/FaqPage.vue');
const QuickStartPage = () => import('@/views/Public/QuickStartPage.vue');
const CampaignPage = () => import('@/views/Public/CampaignPage.vue');
const SaveTaxCampaignPage = () => import('@/views/Public/SaveTaxCampaignPage.vue');
const NotFoundPage = () => import('@/views/Public/NotFoundPage.vue');
const StartingOutPage = () => import('@/views/Public/stages/StartingOutPage.vue');
const BuildingFoundationsPage = () => import('@/views/Public/stages/BuildingFoundationsPage.vue');
const ProtectingAndGrowingPage = () => import('@/views/Public/stages/ProtectingAndGrowingPage.vue');
const PlanningYourFuturePage = () => import('@/views/Public/stages/PlanningYourFuturePage.vue');
const EnjoyingYourWealthPage = () => import('@/views/Public/stages/EnjoyingYourWealthPage.vue');
// Why Fynla
const OurApproachPage = () => import('@/views/Public/why-fynla/OurApproachPage.vue');
const OnePlatformPage = () => import('@/views/Public/why-fynla/OnePlatformPage.vue');
const IndependentPage = () => import('@/views/Public/why-fynla/IndependentPage.vue');
const AlternativesPage = () => import('@/views/Public/why-fynla/AlternativesPage.vue');
// Learn
const LearnHubPage = () => import('@/views/Public/learn/LearnHubPage.vue');
const WhatIsAnIsaPage = () => import('@/views/Public/learn/WhatIsAnIsaPage.vue');
const WhatIsDrawdownPage = () => import('@/views/Public/learn/WhatIsDrawdownPage.vue');
const ShouldIOverpayMortgagePage = () => import('@/views/Public/learn/ShouldIOverpayMortgagePage.vue');
const ShouldIConsolidatePensionsPage = () => import('@/views/Public/learn/ShouldIConsolidatePensionsPage.vue');
const StartingOutGuidePage = () => import('@/views/Public/learn/guide/StartingOutGuidePage.vue');
const GlossaryPage = () => import('@/views/Public/learn/GlossaryPage.vue');
// Insights
const InsightsHubPage = () => import('@/views/Public/insights/InsightsHubPage.vue');
const NewsHubPage = () => import('@/views/Public/NewsHubPage.vue');
const NewsArticlePage = () => import('@/views/Public/NewsArticlePage.vue');
const PensionIhtChanges2027Page = () => import('@/views/Public/insights/PensionIhtChanges2027Page.vue');
const IsaAllowance202526Page = () => import('@/views/Public/insights/IsaAllowance202526Page.vue');
const InheritanceTaxExplainedPage = () => import('@/views/Public/insights/InheritanceTaxExplainedPage.vue');
const PensionContributionLimitsPage = () => import('@/views/Public/insights/PensionContributionLimitsPage.vue');
const IsaGuideUkPage = () => import('@/views/Public/insights/IsaGuideUkPage.vue');
const RetirementPlanningUkPage = () => import('@/views/Public/insights/RetirementPlanningUkPage.vue');
const StocksSharesIsaUkPage = () => import('@/views/Public/insights/StocksSharesIsaUkPage.vue');
const HowMuchToRetireUkPage = () => import('@/views/Public/insights/HowMuchToRetireUkPage.vue');
const InsightArticlePage = () => import('@/views/Public/insights/InsightArticlePage.vue');
const WhatIsSalarySacrificePage = () => import('@/views/Public/learn/WhatIsSalarySacrificePage.vue');
const WhatIsAnLpaPage = () => import('@/views/Public/learn/WhatIsAnLpaPage.vue');
const WhatIsASippPage = () => import('@/views/Public/learn/WhatIsASippPage.vue');
const WhatIsInheritanceTaxPage = () => import('@/views/Public/learn/WhatIsInheritanceTaxPage.vue');
const WhenShouldIMakeAWillPage = () => import('@/views/Public/learn/WhenShouldIMakeAWillPage.vue');
const ShouldIUseALisaOrIsaPage = () => import('@/views/Public/learn/ShouldIUseALisaOrIsaPage.vue');
const WhenCanIAffordToRetirePage = () => import('@/views/Public/learn/WhenCanIAffordToRetirePage.vue');
const BuildingFoundationsGuidePage = () => import('@/views/Public/learn/guide/BuildingFoundationsGuidePage.vue');
const ProtectingAndGrowingGuidePage = () => import('@/views/Public/learn/guide/ProtectingAndGrowingGuidePage.vue');
const PlanningYourFutureGuidePage = () => import('@/views/Public/learn/guide/PlanningYourFutureGuidePage.vue');
const EnjoyingYourWealthGuidePage = () => import('@/views/Public/learn/guide/EnjoyingYourWealthGuidePage.vue');
const PensionAnnualAllowancePage = () => import('@/views/Public/learn/tax/PensionAnnualAllowancePage.vue');
const IhtThresholdsPage = () => import('@/views/Public/learn/tax/IhtThresholdsPage.vue');
const CapitalGainsTaxPage = () => import('@/views/Public/learn/tax/CapitalGainsTaxPage.vue');
const TaxYearChecklistPage = () => import('@/views/Public/learn/tax/TaxYearChecklistPage.vue');
const IsaAllowanceTaxPage = () => import('@/views/Public/learn/tax/IsaAllowanceTaxPage.vue');
const FynlaVsProjectionLabPage = () => import('@/views/Public/compare/FynlaVsProjectionLabPage.vue');
const FynlaVsVoyantPage = () => import('@/views/Public/compare/FynlaVsVoyantPage.vue');
const FynlaVsMoneyhubPage = () => import('@/views/Public/compare/FynlaVsMoneyhubPage.vue');
const FynlaVsSpreadsheetsPage = () => import('@/views/Public/compare/FynlaVsSpreadsheetsPage.vue');
const BestFinancialPlanningToolsPage = () => import('@/views/Public/compare/BestFinancialPlanningToolsPage.vue');
const ContactPage = () => import('@/views/Public/ContactPage.vue');
const FynlaVsMoneyHelperPage = () => import('@/views/Public/compare/FynlaVsMoneyHelperPage.vue');

const NetWorthDashboardFeature = () => import('@/views/Public/features/NetWorthDashboardFeature.vue');
const IceLettersFeature = () => import('@/views/Public/features/IceLettersFeature.vue');
const ProtectionGapFeature = () => import('@/views/Public/features/ProtectionGapFeature.vue');
const MonteCarloFeature = () => import('@/views/Public/features/MonteCarloFeature.vue');
const WhenCanIRetireFeature = () => import('@/views/Public/features/WhenCanIRetireFeature.vue');
const PensionTrackerFeature = () => import('@/views/Public/features/PensionTrackerFeature.vue');
const IhtPlanningFeature = () => import('@/views/Public/features/IhtPlanningFeature.vue');

// Auth pages
const Login = () => import('@/views/Login.vue');
const Register = () => import('@/views/Register.vue');
const Onboarding = () => import('@/views/Onboarding/OnboardingView.vue');

// Authenticated pages
const Dashboard = () => import('@/views/Dashboard.vue');
const Settings = () => import('@/views/Settings.vue');
const SecuritySettings = () => import('@/views/Settings/SecuritySettings.vue');
const PrivacySettings = () => import('@/views/Settings/PrivacySettings.vue');
const AssumptionsSettings = () => import('@/views/Settings/AssumptionsSettings.vue');
const NotificationsSettings = () => import('@/views/Settings/NotificationSettings.vue');
const PersonalSettings = () => import('@/views/Settings/PersonalSettings.vue');
const HealthSettings = () => import('@/views/Settings/HealthSettings.vue');
const FamilySettings = () => import('@/views/Settings/FamilySettings.vue');
const SubscriptionSettingsPage = () => import('@/views/Settings/SubscriptionSettings.vue');
const UserProfile = () => import('@/views/UserProfile.vue');
const InvoiceView = () => import('@/views/InvoiceView.vue');
const NetWorthDashboard = () => import('@/views/NetWorth/NetWorthDashboard.vue');
const NetWorthWealthSummary = () => import('@/components/NetWorth/NetWorthWealthSummary.vue');
const PropertyList = () => import('@/components/NetWorth/PropertyList.vue');
const PensionList = () => import('@/components/NetWorth/PensionList.vue');
const InvestmentList = () => import('@/components/NetWorth/InvestmentList.vue');
const BusinessInterestsList = () => import('@/components/NetWorth/BusinessInterestsList.vue');
const ChattelsList = () => import('@/components/NetWorth/ChattelsList.vue');
const LiabilitiesList = () => import('@/components/NetWorth/LiabilitiesList.vue');
const JointAccountHistory = () => import('@/components/NetWorth/JointAccountHistory.vue');
const ProtectionDashboard = () => import('@/views/Protection/ProtectionDashboard.vue');
const PolicyDetail = () => import('@/components/Protection/PolicyDetail.vue');
const SavingsDashboard = () => import('@/views/Savings/SavingsDashboard.vue');
const SavingsAccountDetail = () => import('@/views/Savings/SavingsAccountDetail.vue');
const GoalsDashboard = () => import('@/views/Goals/GoalsDashboard.vue');
const CashOverview = () => import('@/views/NetWorth/CashOverview.vue');
const RiskProfilePage = () => import('@/views/Risk/RiskProfilePage.vue');
const RiskLevelsExplainedPage = () => import('@/views/Risk/RiskLevelsExplainedPage.vue');
const RiskFactorDetailPage = () => import('@/views/Risk/RiskFactorDetailPage.vue');
const PensionDetail = () => import('@/views/Retirement/PensionDetail.vue');
const EstateDashboard = () => import('@/views/Estate/EstateDashboard.vue');
const TrustsDashboard = () => import('@/views/Trusts/TrustsDashboard.vue');
const TrustDetailView = () => import('@/views/Trusts/TrustDetailView.vue');
const HolisticPlan = () => import('@/views/HolisticPlan.vue');
const AdminPanel = () => import('@/views/Admin/AdminPanel.vue');
const AiCostDashboard = () => import('@/views/Admin/AiCostDashboard.vue');
const EpisodicComplianceLog = () => import('@/views/Admin/EpisodicComplianceLog.vue');
const ProceduralCorpusViewer = () => import('@/views/Admin/ProceduralCorpusViewer.vue');
const ProposedSemanticFactsViewer = () => import('@/views/Admin/ProposedSemanticFactsViewer.vue');
const ProposedProcedureAmendmentsViewer = () => import('@/views/Admin/ProposedProcedureAmendmentsViewer.vue');
const InsightsArticleListPage = () => import('@/views/Admin/Insights/ArticleListPage.vue');
const InsightsArticleEditor = () => import('@/views/Admin/Insights/ArticleEditor.vue');
const InsightsTemplateListPage = () => import('@/views/Admin/Insights/TemplateListPage.vue');
const NewsSubscribersPage = () => import('@/views/Admin/NewsSubscribersPage.vue');
const Version = () => import('@/views/Version.vue');
const Help = () => import('@/views/Help.vue');
const DebugEnv = () => import('@/views/DebugEnv.vue');
const ValuableInfo = () => import('@/views/ValuableInfo.vue');

/**
 * Route guard for full-Estate sub-routes (spec §10.2 / SP2 PR7).
 *
 * Will Builder and Power of Attorney are Estate-module routes — there is no
 * separate will/POA capability key in the SP2 matrix, so they fall under
 * "Estate planning" (teaser for Free/Tier1). Teaser-tier users are redirected
 * to the canonical Estate teaser (upgrade CTA) rather than the creation
 * wizard. The estate store `mode` is sourced from /api/estate via the same
 * canonical TeaserGate the backend enforces — NOT the legacy plan map, which
 * lets grandfathered legacy-paid subs through incorrectly. The backend 403 is
 * the authoritative gate; this is defence-in-depth UX.
 */
async function requireFullEstateAccess(to, from, next) {
  let mode = store.getters['estate/mode'];
  if (mode === null) {
    try {
      await store.dispatch('estate/fetchEstateData');
      mode = store.getters['estate/mode'];
    } catch {
      // Transient fetch failure — let the view/backend handle it (backend
      // remains the authoritative 403 gate).
    }
  }
  if (mode === 'teaser') {
    next({ name: 'Estate' });
  } else {
    next();
  }
}

const routes = [
  // Public routes
  {
    path: '/',
    name: 'Home',
    component: LandingPage,
    meta: { public: true },
  },
  {
    path: '/calculators',
    name: 'Calculators',
    component: CalculatorsPage,
    meta: { public: true },
  },
  {
    path: '/learning-centre',
    redirect: '/learn',
  },
  {
    path: '/security',
    name: 'Security',
    component: SecurityPage,
    meta: { public: true },
  },
  {
    path: '/about',
    name: 'About',
    component: AboutPage,
    meta: { public: true },
  },
  {
    path: '/pricing',
    name: 'Pricing',
    component: PricingPage,
    meta: { public: true },
  },
  {
    path: '/sitemap',
    name: 'Sitemap',
    component: SitemapPage,
    meta: { public: true },
  },
  {
    path: '/privacy',
    name: 'PrivacyPolicy',
    component: PrivacyPolicyPage,
    meta: { public: true },
  },
  {
    path: '/terms',
    name: 'TermsOfService',
    component: TermsOfServicePage,
    meta: { public: true },
  },
  {
    path: '/editorial-policy',
    name: 'EditorialPolicy',
    component: EditorialPolicyPage,
    meta: { public: true },
  },
  {
    path: '/how-it-works',
    name: 'HowItWorks',
    component: HowItWorksPage,
    meta: { public: true },
  },
  {
    path: '/quickstart',
    name: 'QuickStart',
    component: QuickStartPage,
    meta: { public: true },
  },
  {
    path: '/savetax',
    name: 'CampaignSaveTax',
    component: SaveTaxCampaignPage,
    meta: { public: true },
  },
  {
    path: '/biggerpension',
    name: 'CampaignBiggerPension',
    component: CampaignPage,
    meta: { public: true },
  },
  {
    path: '/paymortgage',
    name: 'CampaignPayMortgage',
    component: CampaignPage,
    meta: { public: true },
  },
  {
    path: '/managedebt',
    name: 'CampaignManageDebt',
    component: CampaignPage,
    meta: { public: true },
  },
  {
    path: '/wealth',
    name: 'CampaignWealth',
    component: CampaignPage,
    meta: { public: true },
  },
  {
    path: '/features',
    name: 'Features',
    component: FeaturesPage,
    meta: { public: true },
  },
  {
    path: '/faq',
    name: 'FAQ',
    component: FaqPage,
    meta: { public: true },
  },
  {
    path: '/stage/starting-out',
    name: 'StageStartingOut',
    component: StartingOutPage,
    meta: { public: true },
  },
  {
    path: '/stage/building-foundations',
    name: 'StageBuildingFoundations',
    component: BuildingFoundationsPage,
    meta: { public: true },
  },
  {
    path: '/stage/protecting-and-growing',
    name: 'StageProtectingAndGrowing',
    component: ProtectingAndGrowingPage,
    meta: { public: true },
  },
  {
    path: '/stage/planning-your-future',
    name: 'StagePlanningYourFuture',
    component: PlanningYourFuturePage,
    meta: { public: true },
  },
  {
    path: '/stage/enjoying-your-wealth',
    name: 'StageEnjoyingYourWealth',
    component: EnjoyingYourWealthPage,
    meta: { public: true },
  },
  {
    path: '/features/net-worth-dashboard',
    name: 'FeatureNetWorth',
    component: NetWorthDashboardFeature,
    meta: { public: true },
  },
  {
    path: '/features/ice-letters',
    name: 'FeatureIceLetters',
    component: IceLettersFeature,
    meta: { public: true },
  },
  {
    path: '/features/protection-gap',
    name: 'FeatureProtectionGap',
    component: ProtectionGapFeature,
    meta: { public: true },
  },
  {
    path: '/features/monte-carlo',
    name: 'FeatureMonteCarlo',
    component: MonteCarloFeature,
    meta: { public: true },
  },
  {
    path: '/features/when-can-i-retire',
    name: 'FeatureWhenCanIRetire',
    component: WhenCanIRetireFeature,
    meta: { public: true },
  },
  {
    path: '/features/pension-tracker',
    name: 'FeaturePensionTracker',
    component: PensionTrackerFeature,
    meta: { public: true },
  },
  {
    path: '/features/iht-planning',
    name: 'FeatureIhtPlanning',
    component: IhtPlanningFeature,
    meta: { public: true },
  },
  // Why Fynla
  { path: '/why-fynla/our-approach', name: 'WhyOurApproach', component: OurApproachPage, meta: { public: true } },
  { path: '/why-fynla/one-platform', name: 'WhyOnePlatform', component: OnePlatformPage, meta: { public: true } },
  { path: '/why-fynla/independent', name: 'WhyIndependent', component: IndependentPage, meta: { public: true } },
  { path: '/why-fynla/alternatives', name: 'WhyAlternatives', component: AlternativesPage, meta: { public: true } },
  // Learn
  { path: '/learn', name: 'LearnHub', component: LearnHubPage, meta: { public: true } },
  { path: '/learn/what-is-an-isa', name: 'LearnISA', component: WhatIsAnIsaPage, meta: { public: true } },
  { path: '/learn/what-is-drawdown', name: 'LearnDrawdown', component: WhatIsDrawdownPage, meta: { public: true } },
  { path: '/learn/should-i-overpay-my-mortgage', name: 'LearnOverpayMortgage', component: ShouldIOverpayMortgagePage, meta: { public: true } },
  { path: '/learn/should-i-consolidate-pensions', name: 'LearnConsolidatePensions', component: ShouldIConsolidatePensionsPage, meta: { public: true } },
  { path: '/learn/guide/starting-out', name: 'LearnGuideStartingOut', component: StartingOutGuidePage, meta: { public: true } },
  { path: '/learn/glossary', name: 'LearnGlossary', component: GlossaryPage, meta: { public: true } },
  // Insights
  { path: '/insights', name: 'InsightsHub', component: InsightsHubPage, meta: { public: true } },
  { path: '/insights/pension-iht-changes-2027', name: 'InsightPensionIHT', component: PensionIhtChanges2027Page, meta: { public: true } },
  { path: '/insights/isa-allowance-2025-26', name: 'InsightISAAllowance', component: IsaAllowance202526Page, meta: { public: true } },
  { path: '/insights/inheritance-tax-uk', name: 'InsightIHT', component: InheritanceTaxExplainedPage, meta: { public: true } },
  { path: '/insights/pension-contribution-limits-uk', name: 'InsightPensionLimits', component: PensionContributionLimitsPage, meta: { public: true } },
  { path: '/insights/isa-guide-uk', name: 'InsightIsaGuide', component: IsaGuideUkPage, meta: { public: true } },
  { path: '/insights/retirement-planning-uk', name: 'InsightRetirementPlanning', component: RetirementPlanningUkPage, meta: { public: true } },
  { path: '/insights/stocks-shares-isa-uk', name: 'InsightStocksSharesIsa', component: StocksSharesIsaUkPage, meta: { public: true } },
  { path: '/insights/how-much-to-retire-uk', name: 'InsightHowMuchToRetire', component: HowMuchToRetireUkPage, meta: { public: true } },
  // IMPORTANT: /insights/:slug catch-all MUST come AFTER all named insight routes
  // so bespoke Vue articles take precedence. Enforced by an architecture test in Phase 6.
  // Gated by VITE_INSIGHTS_CMS_ENABLED so production can ship backend-only builds
  // first and flip the flag after smoke-testing the API.
  ...(import.meta.env.VITE_INSIGHTS_CMS_ENABLED === 'true'
    ? [{ path: '/insights/:slug', name: 'InsightArticle', component: InsightArticlePage, meta: { public: true } }]
    : []),
  // News (DB-backed announcements / product updates / press)
  { path: '/news', name: 'NewsHub', component: NewsHubPage, meta: { public: true } },
  { path: '/news/:slug', name: 'NewsArticle', component: NewsArticlePage, meta: { public: true } },
  // Learn — Concept Explainers
  { path: '/learn/what-is-salary-sacrifice', name: 'LearnSalarySacrifice', component: WhatIsSalarySacrificePage, meta: { public: true } },
  { path: '/learn/what-is-an-lpa', name: 'LearnLPA', component: WhatIsAnLpaPage, meta: { public: true } },
  { path: '/learn/what-is-a-sipp', name: 'LearnSIPP', component: WhatIsASippPage, meta: { public: true } },
  { path: '/learn/what-is-inheritance-tax', name: 'LearnIHT', component: WhatIsInheritanceTaxPage, meta: { public: true } },
  // Learn — Decision Guides
  { path: '/learn/when-should-i-make-a-will', name: 'LearnMakeAWill', component: WhenShouldIMakeAWillPage, meta: { public: true } },
  { path: '/learn/should-i-use-a-lisa-or-isa', name: 'LearnLISAvsISA', component: ShouldIUseALisaOrIsaPage, meta: { public: true } },
  { path: '/learn/when-can-i-afford-to-retire', name: 'LearnAffordRetire', component: WhenCanIAffordToRetirePage, meta: { public: true } },
  // Learn — Life Stage Guides
  { path: '/learn/guide/building-foundations', name: 'LearnGuideBuildingFoundations', component: BuildingFoundationsGuidePage, meta: { public: true } },
  { path: '/learn/guide/protecting-and-growing', name: 'LearnGuideProtecting', component: ProtectingAndGrowingGuidePage, meta: { public: true } },
  { path: '/learn/guide/planning-your-future', name: 'LearnGuidePlanning', component: PlanningYourFutureGuidePage, meta: { public: true } },
  { path: '/learn/guide/enjoying-your-wealth', name: 'LearnGuideEnjoying', component: EnjoyingYourWealthGuidePage, meta: { public: true } },
  // Learn — Tax & Allowances
  { path: '/learn/tax/pension-annual-allowance', name: 'LearnTaxPensionAA', component: PensionAnnualAllowancePage, meta: { public: true } },
  { path: '/learn/tax/iht-thresholds', name: 'LearnTaxIHT', component: IhtThresholdsPage, meta: { public: true } },
  { path: '/learn/tax/capital-gains-tax', name: 'LearnTaxCGT', component: CapitalGainsTaxPage, meta: { public: true } },
  { path: '/learn/tax/tax-year-checklist', name: 'LearnTaxChecklist', component: TaxYearChecklistPage, meta: { public: true } },
  { path: '/learn/tax/isa-allowance', name: 'LearnTaxISA', component: IsaAllowanceTaxPage, meta: { public: true } },
  // Compare
  { path: '/compare/fynla-vs-financial-planning-platform', name: 'CompareProjectionLab', component: FynlaVsProjectionLabPage, meta: { public: true } },
  { path: '/compare/fynla-vs-financial-investment-platform', name: 'CompareVoyant', component: FynlaVsVoyantPage, meta: { public: true } },
  { path: '/compare/fynla-vs-financial-centralisation-platform', name: 'CompareMoneyhub', component: FynlaVsMoneyhubPage, meta: { public: true } },
  // Old comparison slugs → redirects
  { path: '/compare/fynla-vs-projectionlab', redirect: '/compare/fynla-vs-financial-planning-platform' },
  { path: '/compare/fynla-vs-voyant', redirect: '/compare/fynla-vs-financial-investment-platform' },
  { path: '/compare/fynla-vs-moneyhub', redirect: '/compare/fynla-vs-financial-centralisation-platform' },
  { path: '/compare/fynla-vs-spreadsheets', name: 'CompareSpreadsheets', component: FynlaVsSpreadsheetsPage, meta: { public: true } },
  { path: '/compare/best-financial-planning-tools-uk', name: 'CompareBest', component: BestFinancialPlanningToolsPage, meta: { public: true } },
  { path: '/compare/fynla-vs-moneyhelper', name: 'CompareMoneyHelper', component: FynlaVsMoneyHelperPage, meta: { public: true } },
  // Advisors
  { path: '/advisors', name: 'Advisors', component: AdvisorsPage, meta: { public: true } },
  // Contact
  { path: '/contact', name: 'Contact', component: ContactPage, meta: { public: true } },

  // Auth routes
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresGuest: true },
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { requiresGuest: true },
  },
  {
    path: '/onboarding/welcome',
    name: 'OnboardingWelcome',
    component: Onboarding,
    meta: { requiresAuth: true, hideNavbar: true },
  },
  {
    path: '/onboarding/journey/:journey',
    name: 'OnboardingJourney',
    component: Onboarding,
    meta: { requiresAuth: true, hideNavbar: true },
    props: route => ({ mode: 'journey', journeyName: route.params.journey }),
  },
  {
    path: '/onboarding',
    name: 'Onboarding',
    component: Onboarding,
    meta: { requiresAuth: true, hideNavbar: true },
    children: [
      {
        path: ':step',
        name: 'OnboardingStep',
        component: Onboarding,
      },
    ],
  },
  {
    path: '/onboarding/full',
    name: 'OnboardingFull',
    component: () => import('@/views/Onboarding/OnboardingFullView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
  },
  {
    path: '/onboarding/protection',
    name: 'OnboardingProtection',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'protection' },
  },
  {
    path: '/onboarding/estate',
    name: 'OnboardingEstate',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'estate' },
  },
  {
    path: '/onboarding/investments',
    name: 'OnboardingInvestments',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'investments' },
  },
  {
    path: '/onboarding/pensions',
    name: 'OnboardingPensions',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'pensions' },
  },
  {
    path: '/onboarding/family',
    name: 'OnboardingFamily',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'family' },
  },
  {
    path: '/onboarding/savings',
    name: 'OnboardingSavings',
    component: () => import('@/views/Onboarding/OnboardingModuleView.vue'),
    meta: { requiresAuth: true, hideNavbar: true },
    props: { moduleName: 'savings' },
  },
  {
    path: '/checkout',
    name: 'Checkout',
    component: () => import('@/views/Auth/CheckoutPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'Settings',
    component: Settings,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/security',
    name: 'SecuritySettings',
    component: SecuritySettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Security', path: '/settings/security' },
      ],
    },
  },
  {
    path: '/settings/privacy',
    name: 'PrivacySettings',
    component: PrivacySettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Privacy & Data', path: '/settings/privacy' },
      ],
    },
  },
  {
    path: '/settings/assumptions',
    name: 'AssumptionsSettings',
    component: AssumptionsSettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Planning Assumptions', path: '/settings/assumptions' },
      ],
    },
  },
  {
    path: '/settings/notifications',
    name: 'NotificationsSettings',
    component: NotificationsSettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Notifications', path: '/settings/notifications' },
      ],
    },
  },
  {
    path: '/settings/personal',
    name: 'PersonalSettings',
    component: PersonalSettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Personal Info', path: '/settings/personal' },
      ],
    },
  },
  {
    path: '/settings/health',
    name: 'HealthSettings',
    component: HealthSettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Health', path: '/settings/health' },
      ],
    },
  },
  {
    path: '/settings/family',
    name: 'FamilySettings',
    component: FamilySettings,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Family', path: '/settings/family' },
      ],
    },
  },
  {
    // S0.5.u (BS-16): canonical billing entry point. Chat-emitted navigation
    // events from get_subscription_status tool result land here.
    path: '/settings/subscription',
    name: 'SubscriptionSettings',
    component: SubscriptionSettingsPage,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Settings', path: '/settings' },
        { label: 'Subscription', path: '/settings/subscription' },
      ],
    },
  },
  {
    path: '/valuable-info',
    name: 'ValuableInfo',
    component: ValuableInfo,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Valuable Info', path: '/valuable-info' },
      ],
    },
  },
  {
    path: '/profile',
    name: 'UserProfile',
    redirect: (to) => {
      const section = to.query.section;
      const sectionMap = {
        personal: '/settings/personal',
        health: '/settings/health',
        family: '/settings/family',
        subscription: '/settings/subscription',
      };
      return { path: sectionMap[section] || '/settings/personal' };
    },
    meta: { requiresAuth: true },
  },
  {
    path: '/invoice/:id',
    name: 'InvoiceView',
    component: InvoiceView,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Profile', path: '/profile' },
        { label: 'Invoice', path: '' },
      ],
    },
  },
  {
    path: '/profile/notifications',
    name: 'NotificationPreferences',
    component: () => import('@/components/UserProfile/NotificationPreferences.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Profile', path: '/profile' },
        { label: 'Notifications', path: '/profile/notifications' },
      ],
    },
  },
  {
    path: '/net-worth',
    component: NetWorthDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Net Worth', path: '/net-worth' },
      ],
    },
    children: [
      {
        path: '',
        redirect: 'wealth-summary',
      },
      {
        path: 'overview',
        redirect: 'wealth-summary',
      },
      {
        path: 'wealth-summary',
        name: 'NetWorthWealthSummary',
        component: NetWorthWealthSummary,
      },
      {
        path: 'retirement',
        name: 'NetWorthRetirement',
        component: PensionList,
      },
      {
        path: 'property',
        name: 'NetWorthProperty',
        component: PropertyList,
      },
      {
        path: 'investments',
        name: 'NetWorthInvestments',
        component: InvestmentList,
      },
      {
        path: 'investment-detail',
        name: 'InvestmentDetail',
        component: () => import('@/components/NetWorth/InvestmentProjections.vue'),
      },
      {
        path: 'tax-efficiency',
        name: 'TaxEfficiencyDetail',
        component: () => import('@/components/NetWorth/TaxEfficiencyDetail.vue'),
      },
      {
        path: 'holdings-detail',
        name: 'HoldingsDetail',
        component: () => import('@/components/NetWorth/HoldingsDetail.vue'),
      },
      {
        path: 'fees-detail',
        name: 'FeesDetail',
        component: () => import('@/components/NetWorth/FeesDetail.vue'),
      },
      {
        path: 'strategy-detail',
        name: 'StrategyDetail',
        component: () => import('@/components/NetWorth/StrategyDetail.vue'),
      },
      {
        path: 'cash',
        name: 'NetWorthCash',
        component: CashOverview,
      },
      {
        path: 'business',
        name: 'NetWorthBusiness',
        component: BusinessInterestsList,
      },
      {
        path: 'chattels',
        name: 'NetWorthChattels',
        component: ChattelsList,
      },
      {
        path: 'liabilities',
        name: 'NetWorthLiabilities',
        component: LiabilitiesList,
      },
      {
        path: 'joint-history',
        name: 'JointAccountHistory',
        component: JointAccountHistory,
      },
    ],
  },
  {
    path: '/pension/:type/:id',
    name: 'PensionDetail',
    component: PensionDetail,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Retirement', path: '/net-worth/retirement' },
        { label: 'Pension Details', path: '' },
      ],
    },
  },
  {
    path: '/protection',
    name: 'Protection',
    component: ProtectionDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Protection', path: '/protection' },
      ],
    },
  },
  {
    path: '/protection/policy/:policyType/:id',
    name: 'PolicyDetail',
    component: PolicyDetail,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Protection', path: '/protection' },
        { label: 'Policy Details', path: '' },
      ],
    },
  },
  {
    path: '/savings',
    name: 'Savings',
    component: SavingsDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Savings', path: '/savings' },
      ],
    },
  },
  {
    path: '/savings/account/:id',
    name: 'SavingsAccountDetail',
    component: SavingsAccountDetail,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Savings', path: '/savings' },
        { label: 'Account', path: '' },
      ],
    },
  },
  {
    path: '/goals',
    name: 'Goals',
    component: GoalsDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Goals', path: '/goals' },
      ],
    },
  },
  {
    path: '/investment',
    redirect: '/net-worth/investments',
  },
  {
    // The pensioncheck onboarding terminal (and any Fyn navigation) targets the
    // surface-agnostic '/retirement' route the /m app defines natively. The web
    // SPA houses the retirement module under /net-worth/retirement, so without
    // this redirect $router.push('/retirement') fell through to the NotFound
    // catch-all and the terminal "Take me to my retirement plan" button went
    // nowhere. Mirrors the '/investment' → '/net-worth/investments' redirect.
    path: '/retirement',
    redirect: '/net-worth/retirement',
  },
  {
    path: '/risk-profile',
    name: 'RiskProfile',
    component: RiskProfilePage,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Investment', path: '/investment' },
        { label: 'Risk Profile', path: '/risk-profile' },
      ],
    },
  },
  {
    path: '/risk-profile/levels',
    name: 'RiskLevelsExplained',
    component: RiskLevelsExplainedPage,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Risk Profile', path: '/risk-profile' },
        { label: 'Risk Levels Explained', path: '/risk-profile/levels' },
      ],
    },
  },
  {
    path: '/risk-profile/factor/:factor',
    name: 'RiskFactorDetail',
    component: RiskFactorDetailPage,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Risk Profile', path: '/risk-profile' },
        { label: 'Factor Details', path: '' },
      ],
    },
  },
  {
    path: '/estate',
    name: 'Estate',
    component: EstateDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Estate Planning', path: '/estate' },
      ],
    },
  },
  {
    path: '/estate/inheritance-tax',
    name: 'InheritanceTaxDetail',
    component: () => import('@/views/Estate/InheritanceTaxDetail.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Estate Planning', path: '/estate' },
        { label: 'Inheritance Tax', path: '/estate/inheritance-tax' },
      ],
    },
  },
  {
    path: '/estate/power-of-attorney',
    name: 'PowerOfAttorney',
    component: () => import('@/views/Estate/PowerOfAttorneyView.vue'),
    beforeEnter: requireFullEstateAccess,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Estate Planning', path: '/estate' },
        { label: 'Power of Attorney', path: '/estate/power-of-attorney' },
      ],
    },
  },
  {
    path: '/estate/lpa/create/:type',
    name: 'CreateLpa',
    component: () => import('@/views/Estate/LpaWizardView.vue'),
    beforeEnter: requireFullEstateAccess,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Estate Planning', path: '/estate' },
        { label: 'Lasting Power of Attorney', path: '' },
      ],
    },
  },
  {
    path: '/estate/will-builder',
    name: 'WillBuilder',
    component: () => import('@/views/Estate/WillBuilderView.vue'),
    beforeEnter: requireFullEstateAccess,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Estate Planning', path: '/estate' },
        { label: 'Will Builder', path: '' },
      ],
    },
  },
  {
    path: '/trusts',
    name: 'Trusts',
    component: TrustsDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Trusts', path: '/trusts' },
      ],
    },
  },
  {
    path: '/trusts/:id',
    name: 'TrustDetail',
    component: TrustDetailView,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Trusts', path: '/trusts' },
        { label: 'Trust Details', path: '' },
      ],
    },
  },
  {
    path: '/actions',
    name: 'Actions',
    component: () => import('@/views/Actions/ActionsDashboard.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Actions & Recommendations', path: '/actions' },
      ],
    },
  },
  {
    path: '/tax-strategy',
    name: 'TaxStrategy',
    component: () => import('@/views/TaxStrategy/TaxStrategyDashboard.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Tax Strategy', path: '/tax-strategy' },
      ],
    },
  },
  {
    path: '/actions/:planType/:actionId',
    name: 'ActionDetail',
    component: () => import('@/views/Actions/ActionDetailView.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Dashboard', path: '/dashboard' },
        { label: 'Actions', path: '/actions' },
        { label: 'Detail', path: '' },
      ],
    },
  },
  {
    path: '/holistic-plan',
    name: 'HolisticPlan',
    component: HolisticPlan,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Holistic Financial Plan', path: '/holistic-plan' },
      ],
    },
  },
  {
    path: '/teaser',
    name: 'TierTeaser',
    component: () => import('@/views/TierTeaserView.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Upgrade', path: '/teaser' },
      ],
    },
  },
  {
    path: '/planning/journeys',
    name: 'PlanningJourneys',
    component: () => import('@/views/Planning/PlanningJourneys.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Journeys', path: '/planning/journeys' },
      ],
    },
  },
  {
    path: '/planning/what-if',
    name: 'WhatIfDashboard',
    component: () => import('@/views/Planning/WhatIfDashboard.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'What If Scenarios', path: '/planning/what-if' },
      ],
    },
  },
  {
    path: '/planning/what-if/death-of-spouse',
    name: 'DeathOfSpouseScenario',
    component: () => import('@/views/Planning/WhatIfScenarios.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'What If Scenarios', path: '/planning/what-if' },
        { label: 'Death of Spouse', path: '/planning/what-if/death-of-spouse' },
      ],
    },
  },
  {
    path: '/planning/what-if/:id',
    name: 'WhatIfScenarioDetail',
    component: () => import('@/views/Planning/WhatIfScenarioDetailView.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'What If Scenarios', path: '/planning/what-if' },
        { label: 'Scenario Detail' },
      ],
    },
  },
  {
    path: '/plans',
    name: 'Plans',
    component: () => import('@/views/Plans/PlansDashboard.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
      ],
    },
  },
  {
    path: '/plans/investment',
    name: 'InvestmentPlan',
    component: () => import('@/views/Plans/InvestmentPlan.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Investment Plan', path: '/plans/investment' },
      ],
    },
  },
  {
    path: '/plans/protection',
    name: 'ProtectionPlan',
    component: () => import('@/views/Plans/ProtectionPlan.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Protection Plan', path: '/plans/protection' },
      ],
    },
  },
  {
    path: '/plans/retirement',
    name: 'RetirementPlan',
    component: () => import('@/views/Plans/RetirementPlan.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Retirement Plan', path: '/plans/retirement' },
      ],
    },
  },
  {
    path: '/plans/estate',
    name: 'EstatePlan',
    component: () => import('@/views/Plans/EstatePlan.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Estate Plan', path: '/plans/estate' },
      ],
    },
  },
  {
    path: '/plans/goal/:goalId',
    name: 'GoalPlan',
    component: () => import('@/views/Plans/GoalPlan.vue'),
    props: true,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Goal Plan', path: '' },
      ],
    },
  },
  {
    path: '/admin',
    name: 'AdminPanel',
    component: AdminPanel,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
      ],
    },
  },
  {
    path: '/admin/ai-cost',
    name: 'AiCostDashboard',
    component: AiCostDashboard,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'AI cost', path: '/admin/ai-cost' },
      ],
    },
  },
  {
    path: '/admin/episodic-compliance',
    name: 'EpisodicComplianceLog',
    component: EpisodicComplianceLog,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'Episodic compliance', path: '/admin/episodic-compliance' },
      ],
    },
  },
  {
    path: '/admin/procedural-corpus',
    name: 'ProceduralCorpusViewer',
    component: ProceduralCorpusViewer,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'Procedural memory', path: '/admin/procedural-corpus' },
      ],
    },
  },
  {
    path: '/admin/proposed-facts',
    name: 'ProposedSemanticFactsViewer',
    component: ProposedSemanticFactsViewer,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'Proposed facts', path: '/admin/proposed-facts' },
      ],
    },
  },
  {
    path: '/admin/proposed-amendments',
    name: 'ProposedProcedureAmendmentsViewer',
    component: ProposedProcedureAmendmentsViewer,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'Proposed amendments', path: '/admin/proposed-amendments' },
      ],
    },
  },
  {
    path: '/admin/insights',
    name: 'AdminInsights',
    component: InsightsArticleListPage,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/insights/new',
    name: 'AdminInsightNew',
    component: InsightsArticleEditor,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/insights/templates',
    name: 'AdminInsightTemplates',
    component: InsightsTemplateListPage,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/insights/:id/edit',
    name: 'AdminInsightEdit',
    component: InsightsArticleEditor,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/documents',
    name: 'admin.documents.index',
    component: () => import('@/views/Admin/Documents/DocumentListPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/documents/:id/edit',
    name: 'admin.documents.edit',
    component: () => import('@/views/Admin/Documents/DocumentEditor.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/news-subscribers',
    name: 'AdminNewsSubscribers',
    component: NewsSubscribersPage,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/version',
    name: 'Version',
    component: Version,
    meta: {
      requiresAuth: false,
    },
  },
  {
    path: '/help',
    name: 'Help',
    component: Help,
    meta: {
      requiresAuth: false,
    },
  },
  // SECURITY: Debug route restricted to development environment and admin users only
  {
    path: '/debug-env',
    name: 'DebugEnv',
    component: DebugEnv,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      devOnly: true, // Additional flag for extra protection
    },
    beforeEnter: (to, from, next) => {
      // Block access in production even if someone bypasses route guards
      if (import.meta.env.PROD) {
        console.warn('[Security] Debug route blocked in production');
        next({ name: 'Dashboard' });
        return;
      }
      next();
    },
  },

  // ===========================
  // Advisor Routes
  // ===========================
  {
    path: '/advisor',
    component: () => import('../layouts/AdvisorLayout.vue'),
    meta: { requiresAuth: true, requiresAdvisor: true },
    children: [
      { path: '', name: 'AdvisorDashboard', component: () => import('../views/Advisor/AdvisorDashboard.vue') },
      { path: 'clients', name: 'AdvisorClients', component: () => import('../views/Advisor/AdvisorClientList.vue') },
      { path: 'clients/:id', name: 'AdvisorClientDetail', component: () => import('../views/Advisor/AdvisorClientDetail.vue') },
      { path: 'activities', name: 'AdvisorActivities', component: () => import('../views/Advisor/AdvisorActivityLog.vue') },
      { path: 'reviews', name: 'AdvisorReviews', component: () => import('../views/Advisor/AdvisorReviewsDue.vue') },
      { path: 'reports', name: 'AdvisorReports', component: () => import('../views/Advisor/AdvisorReports.vue') },
    ],
  },

  // Preview routes - accessible without authentication
  // These routes load the same components as authenticated routes but in preview mode
  {
    path: '/preview',
    name: 'PreviewDashboard',
    component: Dashboard,
    meta: { public: true, previewMode: true },
    beforeEnter: async (to, from, next) => {
      // Load persona from query param or default to young_family
      const personaId = to.query.persona || 'young_family';
      try {
        await store.dispatch('preview/loadPersona', personaId);
        next();
      } catch (error) {
        console.error('Failed to load preview persona:', error);
        next('/');
      }
    },
  },
  {
    path: '/preview/net-worth',
    component: NetWorthDashboard,
    meta: { public: true, previewMode: true },
    children: [
      {
        path: '',
        name: 'PreviewNetWorth',
        redirect: 'wealth-summary',
      },
      {
        path: 'overview',
        redirect: 'wealth-summary',
      },
      {
        path: 'wealth-summary',
        name: 'PreviewNetWorthWealthSummary',
        component: NetWorthWealthSummary,
      },
      {
        path: 'retirement',
        name: 'PreviewNetWorthRetirement',
        component: PensionList,
      },
      {
        path: 'property',
        name: 'PreviewNetWorthProperty',
        component: PropertyList,
      },
      {
        path: 'cash',
        name: 'PreviewNetWorthCash',
        component: CashOverview,
      },
      {
        path: 'investments',
        name: 'PreviewNetWorthInvestments',
        component: InvestmentList,
      },
      {
        path: 'investment-detail',
        name: 'PreviewInvestmentDetail',
        component: () => import('@/components/NetWorth/InvestmentProjections.vue'),
      },
      {
        path: 'tax-efficiency',
        name: 'PreviewTaxEfficiencyDetail',
        component: () => import('@/components/NetWorth/TaxEfficiencyDetail.vue'),
      },
      {
        path: 'holdings-detail',
        name: 'PreviewHoldingsDetail',
        component: () => import('@/components/NetWorth/HoldingsDetail.vue'),
      },
      {
        path: 'fees-detail',
        name: 'PreviewFeesDetail',
        component: () => import('@/components/NetWorth/FeesDetail.vue'),
      },
      {
        path: 'strategy-detail',
        name: 'PreviewStrategyDetail',
        component: () => import('@/components/NetWorth/StrategyDetail.vue'),
      },
      {
        path: 'liabilities',
        name: 'PreviewNetWorthLiabilities',
        component: LiabilitiesList,
      },
    ],
  },
  {
    path: '/preview/protection',
    name: 'PreviewProtection',
    component: ProtectionDashboard,
    meta: { public: true, previewMode: true },
  },
  {
    path: '/preview/savings',
    name: 'PreviewSavings',
    component: SavingsDashboard,
    meta: { public: true, previewMode: true },
  },
  {
    path: '/preview/goals',
    name: 'PreviewGoals',
    component: GoalsDashboard,
    meta: { public: true, previewMode: true },
  },
  {
    path: '/preview/investment',
    redirect: '/preview/net-worth/investments',
  },
  {
    path: '/preview/retirement',
    redirect: '/preview/net-worth/retirement',
  },
  {
    path: '/preview/estate',
    name: 'PreviewEstate',
    component: EstateDashboard,
    meta: { public: true, previewMode: true },
  },
  {
    path: '/preview/estate/power-of-attorney',
    name: 'PreviewPowerOfAttorney',
    component: () => import('@/views/Estate/PowerOfAttorneyView.vue'),
    meta: { public: true, previewMode: true },
  },
  {
    path: '/preview/profile',
    name: 'PreviewProfile',
    component: UserProfile,
    meta: { public: true, previewMode: true },
  },

  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: NotFoundPage,
    meta: { public: true },
  },
];

// Router base path is configurable via environment variable
// Development: '/' (default)
// Production fynla.org (root): '/'
// Production csjones.co/fynla (subdirectory): '/fynla/'
const routerBase = import.meta.env.VITE_ROUTER_BASE || '/';

const router = createRouter({
  history: createWebHistory(routerBase),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' };
    }
    return { top: 0 };
  },
});

// Navigation guards
router.beforeEach(async (to, from, next) => {
  // Mobile-view handoff: the /m mobile view hosts the responsive funnel in a
  // same-origin iframe. Once the user is authenticated and lands on an app
  // (requiresAuth) route, swap the whole frame to the isolated mobile SPA
  // (/m/app) instead of showing the desktop SPA inside the frame. Only the
  // mobile view ever frames this app, so desktop is unaffected. `self !== top`
  // is the cross-origin-safe "am I framed?" check (window.frameElement throws
  // on a cross-origin parent; this never does).
  let inMobileFrame = false;
  try { inMobileFrame = window.self !== window.top; } catch { inMobileFrame = true; }
  if (inMobileFrame
      && store.getters['auth/isAuthenticated']
      && to.matched.some(r => r.meta.requiresAuth)) {
    // Bridge auth across the two same-origin SPAs: the desktop app keeps its
    // Sanctum bearer token in sessionStorage('auth_token'); the isolated mobile
    // SPA reads localStorage('m_scaffold_token'). Copy it so /m/app lands
    // authenticated instead of bouncing to the mobile login. localStorage is
    // shared same-origin; both are the same Sanctum bearer token.
    const token = getTokenSync();
    if (token) {
      try { localStorage.setItem('m_scaffold_token', token); } catch { /* private mode */ }
    }
    const mobileQuery = new URLSearchParams();
    if (typeof to.query.from === 'string' && to.query.from) {
      mobileQuery.set('from', to.query.from);
    }
    const mobileUrl = routerBase + 'm/app' + (mobileQuery.size ? `?${mobileQuery.toString()}` : '');
    window.__fynlaMobileHandoffPending = true;
    window.location.replace(mobileUrl);
    return next(false); // cancel the in-frame SPA nav; the frame is reloading into /m/app
  }

  // ── Server-rendered marketing pages ──────────────────────────────────────
  // The marketing site is served as static PHP pages (public/pages/*.php), but
  // the SPA still carries Vue routes for some of them (e.g. '/' -> LandingPage).
  // A client-side nav from a SPA page (e.g. /insights) would render the STALE
  // Vue component instead of the live PHP page. Force a full document load so
  // the server serves the current page. /insights* is genuinely SPA (excluded).
  // Only intercept real in-app navigations, never the initial boot.
  const isServerRenderedPage = (path) => {
    if (path === '/insights' || path.startsWith('/insights/')) return false;
    const exact = ['/', '/how-it-works', '/features', '/pricing', '/about',
      '/advisors', '/security', '/faq', '/contact', '/help', '/calculators',
      '/learn'];
    if (exact.includes(path)) return true;
    return ['/why-fynla/', '/stage/', '/compare/', '/features/', '/learn/']
      .some((p) => path.startsWith(p));
  };
  if (from.name != null && to.path !== from.path && isServerRenderedPage(to.path)) {
    window.location.assign(routerBase.replace(/\/$/, '') + to.fullPath);
    return next(false); // cancel SPA nav; the browser is loading the PHP page
  }

  const isAuthenticated = store.getters['auth/isAuthenticated'];
  const isPreviewMode = store.getters['preview/isPreviewMode'];
  // Use to.matched.some() rather than to.meta — child routes do NOT inherit
  // parent meta in Vue Router. /preview/net-worth has nested children that
  // would otherwise miss the previewMode flag and be treated as auth routes.
  // REVIEW.md §4 High #27.
  const isPreviewRoute = to.matched.some(r => r.meta.previewMode) || to.path.startsWith('/preview');

  // Debug logging
  if (import.meta.env.DEV) {
    console.log('[Router Guard]', {
      to: to.path,
      requiresAuth: to.matched.some(r => r.meta.requiresAuth),
      isAuthenticated,
      isPreviewMode,
      isPreviewRoute,
    });
  }

  // Handle preview route access
  if (isPreviewRoute) {
    // If authenticated user tries to access preview, redirect to authenticated version
    if (isAuthenticated) {
      const authenticatedPath = to.path.replace('/preview', '');
      next(authenticatedPath || '/dashboard');
      return;
    }

    // Handle persona from query param - redirect to login as that persona
    if (to.query.persona && !to.meta._personaLoaded) {
      try {
        await store.dispatch('preview/enterPreviewMode', to.query.persona);
        // Mark that we've handled the persona to prevent loops
        to.meta._personaLoaded = true;
      } catch (error) {
        console.error('Failed to load persona from URL:', error);
      }
    }

    next();
    return;
  }

  // Cold-boot role hydration. The user-derived getters (isAdmin/isAdvisor) read
  // state.auth.user, which is populated either synchronously from persisted
  // state (vuex-persistedstate path 'auth.user') or asynchronously by App.vue's
  // fetchUser on mount. On a first-ever boot of the desktop SPA in a context
  // that has a bearer token but no persisted user — e.g. tapping "Admin Panel"
  // in the /m drawer, which does a top-window full-page load of /admin where
  // only the token is bridged — neither source has populated the user yet when
  // this guard first runs, so isAdmin is falsely false and an admin is bounced
  // to /dashboard on the first hop (reachable only on the next nav). If a token
  // is present but the user hasn't hydrated and this route gates on a
  // user-derived role, fetch the user once before deciding. Mirrors the
  // capability-matrix hydration below; runs at most once per page load.
  if (isAuthenticated && !isPreviewMode && !store.state.auth.user
      && to.matched.some(r => r.meta.requiresAdmin || r.meta.requiresAdvisor)) {
    try {
      await store.dispatch('auth/fetchUser');
    } catch {
      // Token invalid/expired — leave state as-is; the requiresAuth / requiresAdmin
      // branches below still apply (and API 401s force a re-login).
    }
  }

  // Authenticated users never see the public marketing / landing pages — those
  // exist only to convert guests; the user lives behind the auth wall in the
  // app. Bounce them to the dashboard. Preview personas are exempt so they can
  // still reach the landing-page persona selector. Mirrors the server-side
  // `redirect.authed` middleware on the equivalent server-rendered PHP routes.
  if (to.matched.some(r => r.meta.public) && isAuthenticated && !isPreviewMode) {
    next({ name: 'Dashboard' });
    return;
  }

  // Allow access to authenticated routes when in preview mode
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  if (requiresAuth && !isAuthenticated && !isPreviewMode) {
    // Redirect to login if route requires authentication and not in preview mode
    next({ name: 'Login' });
  } else if (to.matched.some(r => r.meta.requiresGuest) && isAuthenticated && !isPreviewMode) {
    // Redirect to dashboard if already authenticated (but allow preview users to register).
    // Use to.matched.some() not to.meta — child routes don't inherit parent meta in Vue Router.
    // REVIEW.md §4 High #27.
    next({ name: 'Dashboard' });
  } else if (to.matched.some(r => r.meta.requiresAdmin) && !store.getters['auth/isAdmin']) {
    // Redirect to dashboard if route requires admin access (preview mode cannot access admin).
    // Use to.matched.some() not to.meta — child routes don't inherit parent meta in Vue Router.
    // REVIEW.md §4 High #27.
    next({ name: 'Dashboard' });
  } else if (to.matched.some(r => r.meta.requiresAdvisor) && !store.getters['auth/isAdvisor']) {
    // Redirect to dashboard if route requires advisor access
    next({ name: 'Dashboard' });
  } else {
    // Feature gating (capability-matrix model): a route the user's tier can't
    // access (verb teaser/none) shows the teaser/upgrade page rather than being
    // blocked. Everything else is accessible. Defence-in-depth — the backend is
    // the primary enforcement; if the matrix isn't loaded yet, allow through.
    if (requiresAuth && isAuthenticated && !isPreviewMode && to.path !== '/teaser' && capabilityForRoute(to.path, to.query)) {
      let matrix = store.state.auth?.subscriptionData?.capability_matrix;
      // On a fresh full-page load the trial-status hasn't been fetched yet, so
      // the matrix is missing — fetch it once before deciding, otherwise a
      // gated URL opened directly (or refreshed) would skip the teaser.
      if (!matrix) {
        try {
          const resp = await api.get('/payment/trial-status');
          store.commit('auth/setSubscriptionData', resp.data);
          matrix = resp.data?.capability_matrix;
        } catch {
          // Allow through — the backend is the primary enforcement.
        }
      }
      if (matrix && isRouteGated(to.path, to.query, matrix)) {
        next({ path: '/teaser', query: { module: capabilityForRoute(to.path, to.query) } });
        return;
      }
    }
    next();
  }
});

// After each navigation, update info guide module context
router.afterEach((to) => {
  // Only fetch for authenticated users or preview mode
  const isAuthenticated = store.getters['auth/isAuthenticated'];
  const isPreviewMode = store.getters['preview/isPreviewMode'];

  if (!isAuthenticated && !isPreviewMode) {
    return;
  }

  // Skip for public/auth pages
  const publicRoutes = ['/login', '/register', '/', '/calculators', '/learn', '/about', '/pricing'];
  if (publicRoutes.some(route => to.path === route || to.path.startsWith('/forgot') || to.path.startsWith('/reset'))) {
    return;
  }

  // Map route to module
  const moduleMap = {
    '/protection': 'protection',
    '/savings': 'savings',
    '/goals': 'goals',
    '/investment': 'investment',
    '/net-worth/investments': 'investment',
    '/net-worth/retirement': 'retirement',
    '/retirement': 'retirement',
    '/pension': 'retirement',
    '/estate': 'estate',
    '/trusts': 'estate',
    '/net-worth': 'net_worth',
    '/dashboard': 'dashboard',
    '/preview': 'dashboard',
    '/profile': 'dashboard',
  };

  // Find matching module
  let module = 'dashboard';
  for (const [prefix, mod] of Object.entries(moduleMap)) {
    if (to.path.startsWith(prefix)) {
      module = mod;
      break;
    }
  }

  // Fetch requirements for this module
  store.dispatch('infoGuide/fetchRequirements', module);
});

// Keep the browser tab title as a simple "Fynla" on every SPA navigation.
// The base blade template still ships the long marketing title for SEO
// crawlers that don't execute JS — this hook only overrides what users see
// in their tab once Vue has hydrated.
router.afterEach(() => {
  document.title = 'Fynla';
});

// Analytics: track page views on every route change
router.afterEach((to) => {
  analyticsService.trackPageView(to.name, to.path);
});

// Awin MasterTag: respect route exclusions on every navigation. The tag
// must not load on checkout pages per Awin's own guidance. Cookie consent
// is still required — declined users never load the tag at all.
router.afterEach((to) => {
  if (!hasConsent()) return;
  if (shouldLoadAwin(to.name)) {
    loadAwinMasterTag();
  } else {
    unloadAwinMasterTag();
  }
});

export default router;
