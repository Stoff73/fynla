<template>
  <MobileChrome
    title="Subscription"
    subtitle="Compare your Fynla plan"
    :loading="loading"
    loading-label="your subscription"
  >
    <div v-if="error" class="m-card m-state" role="alert">
      <p class="m-err">{{ error }}</p>
      <button type="button" class="m-btn" data-testid="subscription-retry" @click="load">Try again</button>
    </div>

    <template v-else>
      <section class="m-card subscription-current" aria-labelledby="subscription-current-heading">
        <p id="subscription-current-heading" class="m-sub m-label">Your current plan</p>
        <p class="m-metric">{{ status?.tier_display_name || currentTier?.display_name || '—' }}</p>
      </section>

      <div v-if="handoffError" class="m-card m-state" role="alert">
        <p class="m-err">{{ handoffError }}</p>
        <button type="button" class="m-btn" :disabled="handoffBusy" @click="upgrade">Try again</button>
      </div>

      <p v-if="paymentUnavailable" class="m-card subscription-notice">
        Upgrades are temporarily unavailable. Your current plan remains active.
      </p>

      <section
        v-for="tier in tiers"
        :key="tier.tier"
        class="m-card subscription-tier"
        :class="{ 'is-current': tier.tier === status?.tier }"
      >
        <div class="subscription-tier__heading">
          <div>
            <h2>{{ tier.display_name }}</h2>
            <p v-if="tier.tier === status?.tier" class="subscription-badge">Current plan</p>
          </div>
          <p class="subscription-price">{{ price(tier) }}</p>
        </div>
        <ul class="subscription-features">
          <li
            v-for="feature in tier.features || []"
            :key="feature.key"
            :class="{ 'is-unavailable': !feature.included }"
          >
            <span aria-hidden="true">{{ feature.included ? '✓' : '—' }}</span>
            <span>{{ feature.label }}</span>
          </li>
        </ul>
      </section>

      <button
        v-if="canUpgrade"
        type="button"
        class="m-btn subscription-upgrade"
        data-testid="subscription-upgrade"
        :disabled="handoffBusy"
        @click="upgrade"
      >Compare and upgrade</button>
    </template>
  </MobileChrome>
</template>

<script>
import { apiGet } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { issueWebHandoff } from '../navigation/webHandoff.js';
import { store } from '../store.js';

export default {
  name: 'MobileSubscription',
  components: { MobileChrome },
  data: () => ({
    loading: true,
    error: '',
    handoffError: '',
    handoffBusy: false,
    status: null,
    tiers: [],
  }),
  computed: {
    currentTier() {
      return this.tiers.find((tier) => tier.tier === this.status?.tier) || null;
    },
    canUpgrade() {
      return this.status?.tier === 'free' && this.status?.payment_enabled === true;
    },
    paymentUnavailable() {
      return this.status?.tier === 'free' && this.status?.payment_enabled !== true;
    },
  },
  created() {
    this.load();
  },
  methods: {
    price(tier) {
      const pence = Number(tier?.price_monthly_pence);
      if (!pence) return 'Free';
      return `${new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(pence / 100)} / month`;
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const [statusResponse, pricingResponse] = await Promise.all([
          apiGet('/api/payment/subscription-status', store.token),
          apiGet('/api/pricing-config', store.token),
        ]);
        if (handleAuthExpiry(statusResponse, this.$router)) return;
        if (!statusResponse.ok || !pricingResponse.ok) {
          this.error = 'We could not load your subscription. Please try again.';
          return;
        }
        this.status = statusResponse.data?.data?.tier
          ? { ...statusResponse.data, ...statusResponse.data.data }
          : statusResponse.data;
        this.tiers = pricingResponse.data?.data || [];
        store.subscriptionStatus = this.status;
      } catch {
        this.error = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async upgrade() {
      this.handoffBusy = true;
      this.handoffError = '';
      try {
        await issueWebHandoff('subscription');
      } catch {
        this.handoffError = 'Subscription options are temporarily unavailable. Please try again.';
      } finally {
        this.handoffBusy = false;
      }
    },
  },
};
</script>

<style scoped>
.subscription-current, .subscription-tier, .subscription-notice { margin-bottom: 12px; }
.subscription-current .m-label { margin: 0 0 4px; }
.subscription-current .m-metric { margin: 0; }
.subscription-notice { color: var(--neutral-600); font-size: 13px; line-height: 1.5; }
.subscription-tier.is-current { border: 2px solid var(--spring-500); }
.subscription-tier__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.subscription-tier__heading h2 { margin: 0; color: var(--horizon-500); font-size: 18px; }
.subscription-badge { display: inline-block; margin: 5px 0 0; color: var(--spring-700); font-size: 11px; font-weight: 800; text-transform: uppercase; }
.subscription-price { margin: 0; color: var(--horizon-500); font-size: 13px; font-weight: 800; text-align: right; }
.subscription-features { display: grid; gap: 9px; margin: 16px 0 0; padding: 0; list-style: none; }
.subscription-features li { display: flex; gap: 8px; color: var(--neutral-600); font-size: 13px; }
.subscription-features li.is-unavailable { color: var(--neutral-400); }
.subscription-upgrade { width: 100%; }
</style>
