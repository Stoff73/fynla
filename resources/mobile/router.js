import { createRouter, createWebHistory } from 'vue-router';
import { store } from './store.js';
import Login from './views/Login.vue';
import Verify from './views/Verify.vue';
import Dashboard from './views/Dashboard.vue';
import ModuleDetail from './views/ModuleDetail.vue';
import TaxStrategy from './views/TaxStrategy.vue';
import MobileNetWorth from './views/modules/NetWorth.vue';
import MobileNetWorthCategory from './views/modules/NetWorthCategory.vue';
import MobileProtection from './views/modules/Protection.vue';
import MobileProtectionPolicy from './views/modules/ProtectionPolicy.vue';
import MobileSavings from './views/modules/Savings.vue';
import MobileSavingsAccount from './views/modules/SavingsAccount.vue';
import MobileRetirement from './views/modules/Retirement.vue';
import MobileRetirementPensionDetail from './views/modules/RetirementPensionDetail.vue';
import MobileInvestment from './views/modules/Investment.vue';
import MobileInvestmentAccountDetail from './views/modules/InvestmentAccountDetail.vue';

// Inner SPA lives under /m/app — but on subdirectory deploys (csjones serves the
// whole app at /fynla/) the actual URL is /fynla/m/app/. Derive from VITE_ROUTER_BASE
// (the same var the parent SPA's router uses). Defaults to '/' for iOS / unset.
const MOBILE_ROUTER_BASE = (import.meta.env.VITE_ROUTER_BASE || '/') + 'm/app/';

const router = createRouter({
  history: createWebHistory(MOBILE_ROUTER_BASE),
  routes: [
    { path: '/', redirect: '/login' },
    { path: '/login', name: 'login', component: Login },
    { path: '/verify', name: 'verify', component: Verify },
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { auth: true } },
    { path: '/module/:slug', name: 'module-detail', component: ModuleDetail, props: true, meta: { auth: true } },
    { path: '/tax-strategy', name: 'tax-strategy', component: TaxStrategy, meta: { auth: true } },
    { path: '/net-worth', name: 'm-net-worth', component: MobileNetWorth, meta: { auth: true } },
    { path: '/net-worth/:category', name: 'm-net-worth-category', component: MobileNetWorthCategory, props: true, meta: { auth: true } },
    { path: '/protection', name: 'm-protection', component: MobileProtection, meta: { auth: true } },
    { path: '/protection/policy/:policyType/:id', name: 'm-protection-policy', component: MobileProtectionPolicy, props: true, meta: { auth: true } },
    { path: '/savings', name: 'm-savings', component: MobileSavings, meta: { auth: true } },
    { path: '/savings/account/:id', name: 'm-savings-account', component: MobileSavingsAccount, meta: { auth: true } },
    { path: '/retirement', name: 'm-retirement', component: MobileRetirement, meta: { auth: true } },
    { path: '/retirement/pension/:type/:id', name: 'm-retirement-pension', component: MobileRetirementPensionDetail, props: true, meta: { auth: true } },
    { path: '/investment', name: 'm-investment', component: MobileInvestment, meta: { auth: true } },
    { path: '/investment/account/:id', name: 'm-investment-account', component: MobileInvestmentAccountDetail, meta: { auth: true } },
  ],
});

router.beforeEach((to) => {
  if (to.meta.auth && !store.token) return { name: 'login' };
  if (to.name === 'login' && store.token) return { name: 'dashboard' };
  return true;
});

export default router;
