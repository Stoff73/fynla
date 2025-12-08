import { createRouter, createWebHistory } from 'vue-router';
import store from '@/store';

// Lazy load components
// Public pages
const LandingPage = () => import('@/views/Public/LandingPage.vue');
const CalculatorsPage = () => import('@/views/Public/CalculatorsPage.vue');
const LearningCentre = () => import('@/views/Public/LearningCentre.vue');

// Auth pages
const Login = () => import('@/views/Login.vue');
const Register = () => import('@/views/Register.vue');
const Onboarding = () => import('@/views/Onboarding/OnboardingView.vue');

// Authenticated pages
const Dashboard = () => import('@/views/Dashboard.vue');
const Settings = () => import('@/views/Settings.vue');
const UserProfile = () => import('@/views/UserProfile.vue');
const NetWorthDashboard = () => import('@/views/NetWorth/NetWorthDashboard.vue');
const NetWorthOverview = () => import('@/components/NetWorth/NetWorthOverview.vue');
const PropertyList = () => import('@/components/NetWorth/PropertyList.vue');
const PropertyDetail = () => import('@/components/NetWorth/Property/PropertyDetail.vue');
const BusinessInterestsList = () => import('@/components/NetWorth/BusinessInterestsList.vue');
const ChattelsList = () => import('@/components/NetWorth/ChattelsList.vue');
const JointAccountHistory = () => import('@/components/NetWorth/JointAccountHistory.vue');
const ProtectionDashboard = () => import('@/views/Protection/ProtectionDashboard.vue');
const PolicyDetail = () => import('@/components/Protection/PolicyDetail.vue');
const ComprehensiveProtectionPlan = () => import('@/views/Protection/ComprehensiveProtectionPlan.vue');
const SavingsDashboard = () => import('@/views/Savings/SavingsDashboard.vue');
const SavingsAccountDetail = () => import('@/views/Savings/SavingsAccountDetail.vue');
const InvestmentDashboard = () => import('@/views/Investment/InvestmentDashboard.vue');
const RetirementDashboard = () => import('@/views/Retirement/RetirementDashboard.vue');
const PensionDetail = () => import('@/views/Retirement/PensionDetail.vue');
const EstateDashboard = () => import('@/views/Estate/EstateDashboard.vue');
const ComprehensiveEstatePlan = () => import('@/views/Estate/ComprehensiveEstatePlan.vue');
const TrustsDashboard = () => import('@/views/Trusts/TrustsDashboard.vue');
const HolisticPlan = () => import('@/views/HolisticPlan.vue');
const UKTaxesDashboard = () => import('@/views/UKTaxes/UKTaxesDashboard.vue');
const AdminPanel = () => import('@/views/Admin/AdminPanel.vue');
const Version = () => import('@/views/Version.vue');
const Help = () => import('@/views/Help.vue');
const DebugEnv = () => import('@/views/DebugEnv.vue');

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
        redirect: 'overview',
      },
      {
        path: 'overview',
        name: 'NetWorthOverview',
        component: NetWorthOverview,
      },
      {
        path: 'retirement',
        name: 'NetWorthRetirement',
        component: RetirementDashboard,
      },
      {
        path: 'property',
        name: 'NetWorthProperty',
        component: PropertyList,
      },
      {
        path: 'investments',
        name: 'NetWorthInvestments',
        component: InvestmentDashboard,
      },
      {
        path: 'cash',
        name: 'NetWorthCash',
        component: SavingsDashboard,
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
    name: 'Investment',
    component: InvestmentDashboard,
    meta: {
      requiresAuth: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Investment', path: '/investment' },
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
  {
    path: '/debug-env',
    name: 'DebugEnv',
    component: DebugEnv,
    meta: {
      public: true,
    },
  },
];

const router = createRouter({
  history: createWebHistory(import.meta.env.PROD ? '/tengo/' : '/'),
  routes,
});

// Navigation guards
router.beforeEach((to, from, next) => {
  const isAuthenticated = store.getters['auth/isAuthenticated'];
  const isAdmin = store.getters['auth/isAdmin'];

  if (to.meta.requiresAuth && !isAuthenticated) {
    // Redirect to login if route requires authentication
    next({ name: 'Login' });
  } else if (to.meta.requiresGuest && isAuthenticated) {
    // Redirect to dashboard if already authenticated
    next({ name: 'Dashboard' });
  } else if (to.meta.requiresAdmin && !isAdmin) {
    // Redirect to dashboard if route requires admin access
    next({ name: 'Dashboard' });
  } else {
    next();
  }
});

export default router;
