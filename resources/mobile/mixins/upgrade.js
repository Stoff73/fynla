// Shared "upgrade your plan" break-out for the /m freemium nudges (Batch 5).
//
// The /m SPA runs inside an iframe; the subscription/upgrade UI lives on the
// desktop web SPA, so an upgrade tap must navigate the PARENT frame — it cannot
// route inside /m/app. This mirrors MobileChrome.gotoAdmin: bridge the auth
// token into the parent's sessionStorage, then hard-navigate the top frame to a
// base-aware URL. Target matches the canonical web LimitReachedModal
// (/settings?tab=subscription).
import { store } from '../store.js';

export const upgradeMixin = {
  methods: {
    goUpgrade() {
      const url = (import.meta.env.VITE_ROUTER_BASE || '/') + 'settings?tab=subscription';
      try {
        const token = store.token || localStorage.getItem('m_scaffold_token');
        if (token && window.top && window.top !== window) {
          window.top.sessionStorage.setItem('auth_token', token);
        }
      } catch (e) { /* iOS partitioned storage — desktop boot bridge covers it */ }
      (window.top || window).location.href = url;
    },
  },
};
