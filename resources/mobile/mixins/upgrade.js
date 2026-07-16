// Shared "upgrade your plan" break-out for the /m freemium nudges (Batch 5).
//
// The /m SPA runs inside an iframe; the subscription/upgrade UI lives on the
// desktop web SPA, so an upgrade tap must navigate the PARENT frame — it cannot
// route inside /m/app. This mirrors MobileChrome.gotoAdmin: bridge the auth
// token into the parent's sessionStorage, then hard-navigate the top frame to a
// base-aware URL. Target matches the canonical web LimitReachedModal.
import { store } from '../store.js';
import { apiGet } from '../api.js';

let subscriptionStatusRequest = null;

export function shouldShowMobileUpgrade(subscriptionStatus) {
  return Boolean(
    subscriptionStatus
    && subscriptionStatus.tier === 'free'
    && subscriptionStatus.payment_enabled === true
  );
}

export async function loadMobileSubscriptionStatus() {
  if (store.subscriptionStatus) return store.subscriptionStatus;
  if (!store.token) return null;
  if (!subscriptionStatusRequest) {
    subscriptionStatusRequest = apiGet('/api/payment/subscription-status', store.token)
      .then(({ ok, data }) => {
        store.subscriptionStatus = ok ? data : null;
        return store.subscriptionStatus;
      })
      .finally(() => { subscriptionStatusRequest = null; });
  }
  return subscriptionStatusRequest;
}

export function subscriptionOptionsUrl(basePath = import.meta.env.VITE_ROUTER_BASE || '/') {
  return `${basePath.replace(/\/?$/, '/')}settings/subscription?openPricing=1`;
}

export function openSubscriptionOptions() {
  const url = subscriptionOptionsUrl();
  try {
    const token = store.token || localStorage.getItem('m_scaffold_token');
    if (token && window.top && window.top !== window) {
      window.top.sessionStorage.setItem('auth_token', token);
    }
  } catch { /* iOS partitioned storage — desktop boot bridge covers it */ }
  (window.top || window).location.href = url;
}

export const upgradeMixin = {
  created() {
    loadMobileSubscriptionStatus();
  },
  computed: {
    paidUpgradeAvailable() {
      return shouldShowMobileUpgrade(store.subscriptionStatus);
    },
  },
  methods: {
    goUpgrade() {
      if (!this.paidUpgradeAvailable) return;
      openSubscriptionOptions();
    },
  },
};
