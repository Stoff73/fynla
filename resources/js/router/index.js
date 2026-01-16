import { createRouter, createWebHistory } from 'vue-router';
import store from '@/store';

// Lazy load components
// Public pages
const LandingPage = () => import('@/views/Public/LandingPage.vue');
const CalculatorsPage = () => import('@/views/Public/CalculatorsPage.vue');
const LearningCentre = () => import('@/views/Public/LearningCentre.vue');
const SecurityPage = () => import('@/views/Public/SecurityPage.vue');
const SitemapPage = () => import('@/views/Public/SitemapPage.vue');

// Auth pages
const Login = () => import('@/views/Login.vue');
const Register = () => import('@/views/Register.vue');
const Onboarding = () => import('@/views/Onboarding/OnboardingView.vue');

// Authenticated pages
const Dashboard = () => import('@/views/Dashboard.vue');
const Settings = () => import('@/views/Settings.vue');
const UserProfile = () => import('@/views/UserProfile.vue');
const NetWorthDashboard = () => import('@/views/NetWorth/NetWorthDashboard.vue');
const NetWorthWealthSummary = () => import('@/components/NetWorth/NetWorthWealthSummary.vue');
const PropertyList = () => import('@/components/NetWorth/PropertyList.vue');
const PensionList = () => import('@/components/NetWorth/PensionList.vue');
const InvestmentList = () => import('@/components/NetWorth/InvestmentList.vue');
const PropertyDetail = () => import('@/components/NetWorth/Property/PropertyDetail.vue');
const BusinessInterestsList = () => import('@/components/NetWorth/BusinessInterestsList.vue');
const ChattelsList = () => import('@/components/NetWorth/ChattelsList.vue');
const JointAccountHistory = () => import('@/components/NetWorth/JointAccountHistory.vue');
const ProtectionDashboard = () => import('@/views/Protection/ProtectionDashboard.vue');
const PolicyDetail = () => import('@/components/Protection/PolicyDetail.vue');
const ComprehensiveProtectionPlan = () => import('@/views/Protection/ComprehensiveProtectionPlan.vue');
const SavingsDashboard = () => import('@/views/Savings/SavingsDashboard.vue');
const SavingsAccountDetail = () => import('@/views/Savings/SavingsAccountDetail.vue');
const CashOverview = () => import('@/views/NetWorth/CashOverview.vue');
const RiskProfilePage = () => import('@/views/Risk/RiskProfilePage.vue');
const RiskLevelsExplainedPage = () => import('@/views/Risk/RiskLevelsExplainedPage.vue');
const RiskFactorDetailPage = () => import('@/views/Risk/RiskFactorDetailPage.vue');
const PensionDetail = () => import('@/views/Retirement/PensionDetail.vue');
const EstateDashboard = () => import('@/views/Estate/EstateDashboard.vue');
const ComprehensiveEstatePlan = () => import('@/views/Estate/ComprehensiveEstatePlan.vue');
const TrustsDashboard = () => import('@/views/Trusts/TrustsDashboard.vue');
const TrustDetailView = () => import('@/views/Trusts/TrustDetailView.vue');
const HolisticPlan = () => import('@/views/HolisticPlan.vue');
const UKTaxesDashboard = () => import('@/views/UKTaxes/UKTaxesDashboard.vue');
const AdminPanel = () => import('@/views/Admin/AdminPanel.vue');
const Version = () => import('@/views/Version.vue');
const Help = () => import('@/views/Help.vue');
const DebugEnv = () => import('@/views/DebugEnv.vue');
const ValuableInfo = () => import('@/views/ValuableInfo.vue');

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
    name: 'LearningCentre',
    component: LearningCentre,
    meta: { public: true },
  },
  {
    path: '/security',
    name: 'Security',
    component: SecurityPage,
    meta: { public: true },
  },
  {
    path: '/sitemap',
    name: 'Sitemap',
    component: SitemapPage,
    meta: { public: true },
  },

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
    path: '/onboarding',
    name: 'Onboarding',
    component: Onboarding,
    meta: { requiresAuth: true, hideNavbar: true },
    children: [
      {
        path: ':step',
        name: 'OnboardingStep',
        component: Onboarding, // Re-use the same component as it handles step rendering internally via store
      },
    ],
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
        path: 'joint-history',
        name: 'JointAccountHistory',
        component: JointAccountHistory,
      },
    ],
  },
  {
    path: '/property/:id',
    name: 'PropertyDetail',
    component: PropertyDetail,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Net Worth', path: '/net-worth' },
        { label: 'Property', path: '/property/:id' },
      ],
    },
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
    path: '/protection-plan',
    name: 'ComprehensiveProtectionPlan',
    component: ComprehensiveProtectionPlan,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Comprehensive Protection Plan', path: '/protection-plan' },
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
    path: '/estate-plan',
    name: 'ComprehensiveEstatePlan',
    component: ComprehensiveEstatePlan,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Comprehensive Estate Plan', path: '/estate-plan' },
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
    path: '/holistic-plan',
    name: 'HolisticPlan',
    component: HolisticPlan,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Holistic Plan', path: '/holistic-plan' },
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
    path: '/plans/investment-savings',
    name: 'InvestmentSavingsPlan',
    component: () => import('@/views/Plans/InvestmentSavingsPlan.vue'),
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Plans', path: '/plans' },
        { label: 'Investment & Savings Plan', path: '/plans/investment-savings' },
      ],
    },
  },
  {
    path: '/uk-taxes',
    name: 'UKTaxes',
    component: UKTaxesDashboard,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'UK Taxes & Allowances', path: '/uk-taxes' },
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
    path: '/preview/profile',
    name: 'PreviewProfile',
    component: UserProfile,
    meta: { public: true, previewMode: true },
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
  if (to.meta.requiresAuth && !isAuthenticated && !isPreviewMode) {
    // Redirect to login if route requires authentication and not in preview mode
    next({ name: 'Login' });
  } else if (to.meta.requiresGuest && isAuthenticated && !isPreviewMode) {
    // Redirect to dashboard if already authenticated (but allow preview users to register)
    next({ name: 'Dashboard' });
  } else if (to.meta.requiresAdmin && !isAdmin) {
    // Redirect to dashboard if route requires admin access (preview mode cannot access admin)
    next({ name: 'Dashboard' });
  } else {
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
  const publicRoutes = ['/login', '/register', '/', '/calculators', '/learning-centre'];
  if (publicRoutes.some(route => to.path === route || to.path.startsWith('/forgot') || to.path.startsWith('/reset'))) {
    return;
  }

  // Map route to module
  const moduleMap = {
    '/protection': 'protection',
    '/savings': 'savings',
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

export default router;
