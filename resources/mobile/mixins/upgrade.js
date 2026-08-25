// Shared "upgrade your plan" behavior for the /m freemium nudges. Every entry
// point first opens the in-app comparison; only that screen may issue the
// short-lived web checkout handoff.
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
      this.$router.push('/subscription');
    },
  },
};
