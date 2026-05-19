import { createRouter, createWebHistory } from 'vue-router';
import { store } from './store.js';
import Login from './views/Login.vue';
import Verify from './views/Verify.vue';
import Dashboard from './views/Dashboard.vue';

const router = createRouter({
  // Inner SPA lives under /m/app (the iframe src). Base must match.
  history: createWebHistory('/m/app/'),
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
