import { createRouter, createWebHistory } from 'vue-router';
import { store } from './store.js';
import Login from './views/Login.vue';
import Verify from './views/Verify.vue';
import Dashboard from './views/Dashboard.vue';

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
  ],
});

router.beforeEach((to) => {
  if (to.meta.auth && !store.token) return { name: 'login' };
  if (to.name === 'login' && store.token) return { name: 'dashboard' };
  return true;
});

export default router;
