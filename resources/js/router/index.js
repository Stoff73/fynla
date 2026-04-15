import { createRouter, createWebHistory } from 'vue-router';
import store from '@/store';
import analyticsService from '@/services/analyticsService';
import { platform } from '@/utils/platform';
import { getRequiredTier, hasFeatureAccess } from '@/constants/featureGating';
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
const PensionIhtChanges2027Page = () => import('@/views/Public/insights/PensionIhtChanges2027Page.vue');
const IsaAllowance202526Page = () => import('@/views/Public/insights/IsaAllowance202526Page.vue');
const InheritanceTaxExplainedPage = () => import('@/views/Public/insights/InheritanceTaxExplainedPage.vue');
const PensionContributionLimitsPage = () => import('@/views/Public/insights/PensionContributionLimitsPage.vue');
const IsaGuideUkPage = () => import('@/views/Public/insights/IsaGuideUkPage.vue');
const RetirementPlanningUkPage = () => import('@/views/Public/insights/RetirementPlanningUkPage.vue');
const StocksSharesIsaUkPage = () => import('@/views/Public/insights/StocksSharesIsaUkPage.vue');
const HowMuchToRetireUkPage = () => import('@/views/Public/insights/HowMuchToRetireUkPage.vue');
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
const UserProfile = () => import('@/views/UserProfile.vue');
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
const Version = () => import('@/views/Version.vue');
const Help = () => import('@/views/Help.vue');
const DebugEnv = () => import('@/views/DebugEnv.vue');
const ValuableInfo = () => import('@/views/ValuableInfo.vue');

// Mobile views
const MobileLoginScreen = () => import('@/mobile/views/MobileLoginScreen.vue');
const VerificationCodeScreen = () => import('@/mobile/views/VerificationCodeScreen.vue');
const BiometricPrompt = () => import('@/mobile/BiometricPrompt.vue');
const MobileLayout = () => import('@/mobile/layouts/MobileLayout.vue');
const MobileDashboard = () => import('@/mobile/views/MobileDashboard.vue');
const MobileFynChat = () => import('@/mobile/views/MobileFynChat.vue');
const LearnHub = () => import('@/mobile/views/LearnHub.vue');
const LearnTopicDetail = () => import('@/mobile/views/LearnTopicDetail.vue');
const MobileGoalsList = () => import('@/mobile/views/MobileGoalsList.vue');
const MobileGoalDetail = () => import('@/mobile/views/MobileGoalDetail.vue');
const MoreMenu = () => import('@/mobile/views/MoreMenu.vue');
const NotificationSettings = () => import('@/mobile/views/NotificationSettings.vue');
const ProtectionDetail = () => import('@/mobile/views/ProtectionDetail.vue');
const SavingsDetail = () => import('@/mobile/views/SavingsDetail.vue');
const InvestmentDetail = () => import('@/mobile/views/InvestmentDetail.vue');
const RetirementDetail = () => import('@/mobile/views/RetirementDetail.vue');
const EstateDetail = () => import('@/mobile/views/EstateDetail.vue');
const GoalsDetail = () => import('@/mobile/views/GoalsDetail.vue');
const CoordinationDetail = () => import('@/mobile/views/CoordinationDetail.vue');

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
    component: UserProfile,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Profile', path: '/profile' },
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

  // Mobile auth routes (no layout)
  {
    path: '/m/login',
    name: 'MobileLogin',
    component: MobileLoginScreen,
    meta: { public: true },
  },
  {
    path: '/m/verify',
    name: 'MobileVerify',
    component: VerificationCodeScreen,
    meta: { public: true },
  },
  {
    path: '/m/biometric-setup',
    name: 'BiometricSetup',
    component: BiometricPrompt,
    meta: { requiresAuth: true },
  },

  // Mobile app routes (with MobileLayout)
  {
    path: '/m',
    component: MobileLayout,
    meta: { requiresAuth: true },
    children: [
      { path: 'home', name: 'MobileHome', component: MobileDashboard, meta: { title: 'Home' } },
      { path: 'fyn', name: 'MobileFyn', component: MobileFynChat, meta: { title: 'Fyn' } },
      { path: 'learn', name: 'MobileLearn', component: LearnHub, meta: { title: 'Learn' } },
      { path: 'learn/:topic', name: 'MobileLearnTopic', component: LearnTopicDetail, meta: { title: 'Learn' } },
      { path: 'goals', name: 'MobileGoals', component: MobileGoalsList, meta: { title: 'Goals' } },
      { path: 'goals/:id', name: 'MobileGoalDetail', component: MobileGoalDetail, meta: { title: 'Goal' } },
      { path: 'more', name: 'MobileMore', component: MoreMenu, meta: { title: 'More' } },
      { path: 'more/notifications', name: 'MobileNotificationSettings', component: NotificationSettings, meta: { title: 'Notifications' } },
      { path: 'module/protection', name: 'MobileProtectionDetail', component: ProtectionDetail, meta: { title: 'Protection' } },
      { path: 'module/savings', name: 'MobileSavingsDetail', component: SavingsDetail, meta: { title: 'Savings' } },
      { path: 'module/investment', name: 'MobileInvestmentDetail', component: InvestmentDetail, meta: { title: 'Investment' } },
      { path: 'module/retirement', name: 'MobileRetirementDetail', component: RetirementDetail, meta: { title: 'Retirement' } },
      { path: 'module/estate', name: 'MobileEstateDetail', component: EstateDetail, meta: { title: 'Estate Planning' } },
      { path: 'module/goals', name: 'MobileGoalsDetail', component: GoalsDetail, meta: { title: 'Goals' } },
      { path: 'module/coordination', name: 'MobileCoordinationDetail', component: CoordinationDetail, meta: { title: 'Coordination' } },
    ],
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
  const isAuthenticated = store.getters['auth/isAuthenticated'];
  const isAdmin = store.getters['auth/isAdmin'];
  const isPreviewMode = store.getters['preview/isPreviewMode'];
  const isPreviewRoute = to.meta.previewMode || to.path.startsWith('/preview');

  // Debug logging
  if (import.meta.env.DEV) {
    console.log('[Router Guard]', {
      to: to.path,
      requiresAuth: to.meta.requiresAuth,
      isAuthenticated,
      isPreviewMode,
      isPreviewRoute,
    });
  }

  // Redirect native app users to mobile routes
  if (platform.isNative() && !to.path.startsWith('/m/')) {
    if (to.path === '/' && !isAuthenticated) {
      next('/m/login');
      return;
    }
    if (to.path === '/' && isAuthenticated) {
      next('/m/home');
      return;
    }
    if (to.path === '/dashboard') {
      next('/m/home');
      return;
    }
    if (to.path === '/login') {
      next('/m/login');
      return;
    }
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

  // Allow access to authenticated routes when in preview mode
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  if (requiresAuth && !isAuthenticated && !isPreviewMode) {
    // Redirect to login if route requires authentication and not in preview mode
    if (platform.isNative()) {
      next({ name: 'MobileLogin' });
    } else {
      next({ name: 'Login' });
    }
  } else if (to.meta.requiresGuest && isAuthenticated && !isPreviewMode) {
    // Redirect to dashboard if already authenticated (but allow preview users to register)
    next({ name: 'Dashboard' });
  } else if (to.meta.requiresAdmin && !isAdmin) {
    // Redirect to dashboard if route requires admin access (preview mode cannot access admin)
    next({ name: 'Dashboard' });
  } else if (to.matched.some(r => r.meta.requiresAdvisor) && !store.getters['auth/isAdvisor']) {
    // Redirect to dashboard if route requires advisor access
    next({ name: 'Dashboard' });
  } else {
    // Feature gating: redirect to dashboard if user navigates to a gated route via URL
    if (requiresAuth && isAuthenticated && !isPreviewMode) {
      const requiredTier = getRequiredTier(to.path, to.query);
      if (requiredTier) {
        // Subscription data may not be in Vuex — this is defence-in-depth (backend is primary enforcement)
        const subscriptionData = store.state.auth?.subscriptionData;
        if (subscriptionData && subscriptionData.status !== 'trialing') {
          const userPlan = subscriptionData.plan || 'student';
          if (!hasFeatureAccess(userPlan, requiredTier)) {
            next({ name: 'Dashboard' });
            return;
          }
        }
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
