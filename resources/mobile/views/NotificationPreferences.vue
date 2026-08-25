<template>
  <MobileChrome
    title="Notifications"
    subtitle="Choose which Fynla updates you receive"
    :loading="loading"
    loading-label="your notification preferences"
    back
    @back="$router.push('/settings')"
  >
    <div v-if="loadError" class="m-card m-state" role="alert">
      <p class="m-err">{{ loadError }}</p>
      <button type="button" class="m-btn" data-testid="notifications-load-retry" @click="load">Try again</button>
    </div>

    <template v-else>
      <div v-if="saveError" class="m-card notification-error" role="alert">
        <p class="m-err">{{ saveError }}</p>
        <button type="button" class="m-btn" data-testid="notification-retry" :disabled="retrying" @click="retrySave">Try again</button>
      </div>

      <section v-for="section in sections" :key="section.title" class="m-card notification-group">
        <h2 class="m-section-label">{{ section.title }}</h2>
        <div v-for="item in section.items" :key="item.key" class="notification-row">
          <span class="notification-copy">
            <strong>{{ item.label }}</strong>
            <small>{{ item.description }}</small>
          </span>
          <button
            type="button"
            class="notification-toggle"
            :class="{ 'is-on': preferences[item.key] }"
            :aria-label="item.label"
            :aria-pressed="preferences[item.key] ? 'true' : 'false'"
            :data-testid="`notification-${item.key}`"
            :disabled="pendingKeys.includes(item.key)"
            @click="savePreference(item.key, !preferences[item.key])"
          ><span aria-hidden="true"></span></button>
        </div>
      </section>
    </template>
  </MobileChrome>
</template>

<script>
import { apiGet, apiPut } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import MobileChrome from '../components/MobileChrome.vue';
import { store } from '../store.js';

const SECTIONS = [
  {
    title: 'Account',
    items: [
      { key: 'security_alerts', label: 'Security Alerts', description: 'Login attempts and security changes' },
      { key: 'payment_alerts', label: 'Payment Alerts', description: 'Subscription payment confirmations' },
    ],
  },
  {
    title: 'Financial updates',
    items: [
      { key: 'policy_renewals', label: 'Policy Renewals', description: 'Reminders when policies are due for renewal' },
      { key: 'goal_milestones', label: 'Goal Milestones', description: 'Celebrations when you hit goal milestones' },
      { key: 'contribution_reminders', label: 'Contribution Reminders', description: 'Reminders to make regular contributions' },
      { key: 'market_updates', label: 'Market Updates', description: 'Notable changes in your investments' },
      { key: 'fyn_daily_insight', label: 'Fyn Daily Insight', description: 'A daily financial tip from Fyn' },
      { key: 'mortgage_rate_alerts', label: 'Mortgage Rate Alerts', description: 'Warnings when fixed rates are expiring' },
      { key: 'estate_alerts', label: 'Estate Alerts', description: 'Gift exemption and trust anniversary reminders' },
    ],
  },
];

export default {
  name: 'MobileNotificationPreferences',
  components: { MobileChrome },
  data: () => ({
    sections: SECTIONS,
    preferences: {},
    loading: true,
    loadError: '',
    saveError: '',
    failedChange: null,
    pendingKeys: [],
    retrying: false,
  }),
  created() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.loadError = '';
      try {
        const { ok, status, data } = await apiGet('/api/v1/mobile/notifications/preferences', store.token);
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (ok) this.preferences = data?.data || data || {};
        else this.loadError = 'We could not load your notification preferences.';
      } catch {
        this.loadError = 'Network error. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    async savePreference(key, value) {
      this.saveError = '';
      this.pendingKeys = [...this.pendingKeys, key];
      try {
        const { ok, status } = await apiPut(
          '/api/v1/mobile/notifications/preferences',
          { [key]: value },
          store.token,
        );
        if (handleAuthExpiry({ status }, this.$router)) return;
        if (!ok) throw new Error('preference_unavailable');
        this.preferences = { ...this.preferences, [key]: value };
        this.failedChange = null;
      } catch {
        this.failedChange = { key, value };
        this.saveError = 'We could not save that preference. Please try again.';
      } finally {
        this.pendingKeys = this.pendingKeys.filter((pendingKey) => pendingKey !== key);
      }
    },
    async retrySave() {
      if (!this.failedChange) return;
      this.retrying = true;
      const { key, value } = this.failedChange;
      await this.savePreference(key, value);
      this.retrying = false;
    },
  },
};
</script>

<style scoped>
.notification-error, .notification-group { margin-bottom: 12px; }
.notification-group .m-section-label { margin-top: 0; }
.notification-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 13px 0; border-top: 1px solid var(--horizon-100); }
.notification-copy { display: flex; flex-direction: column; gap: 3px; }
.notification-copy strong { color: var(--horizon-500); font-size: 14px; }
.notification-copy small { color: var(--neutral-500); font-size: 12px; }
.notification-toggle { position: relative; flex: 0 0 auto; width: 44px; height: 26px; padding: 0; border: 0; border-radius: 999px; background: var(--neutral-300); transition: background-color .2s ease; }
.notification-toggle span { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: white; box-shadow: 0 1px 3px rgb(0 0 0 / 20%); transition: transform .2s ease; }
.notification-toggle.is-on { background: var(--spring-500); }
.notification-toggle.is-on span { transform: translateX(18px); }
.notification-toggle:disabled { opacity: .6; }
</style>
