<template>
  <div>
    <div class="m-card m-welcome">
      <h1 class="m-h1">{{ greeting }}</h1>
      <p class="m-sub">{{ subtitle }}</p>
      <button class="m-btn-ghost" @click="logout">Sign out</button>
    </div>

    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your overview…</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else>
      <div class="m-card m-net-worth">
        <p class="m-sub m-label">Estimated net worth</p>
        <p class="m-metric">{{ netWorthFormatted }}</p>
        <p v-if="fynInsight" class="m-insight">{{ fynInsight }}</p>
      </div>

      <div class="m-section-label">Your modules</div>
      <router-link
        v-for="m in moduleCards"
        :key="m.key"
        :to="{ name: 'module-detail', params: { slug: m.key } }"
        class="m-card m-module m-module-link"
      >
        <div class="m-module-row">
          <div>
            <p class="m-module-name">{{ m.label }}</p>
            <p class="m-module-status">{{ m.status }}</p>
          </div>
          <div class="m-module-right">
            <p class="m-module-metric">{{ m.metric }}</p>
            <p class="m-module-view">View</p>
          </div>
        </div>
      </router-link>

      <div class="m-card m-state">
        <span class="m-tag">Scaffold — full redesign pending</span>
        <p class="m-sub">This dashboard is a placeholder. The redesigned mobile UI replaces these cards with the production module surface.</p>
      </div>
    </template>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';

const MODULE_META = [
  { key: 'protection', label: 'Protection', metric: (m) => formatCurrency(m?.total_coverage), statusFor: (m) => {
      if (!m || m.status === 'unavailable') return 'Not available';
      const gaps = m.critical_gaps ?? 0;
      return gaps > 0 ? `${gaps} gap${gaps > 1 ? 's' : ''} to review` : 'Cover in place';
    } },
  { key: 'savings', label: 'Savings', metric: (m) => m?.emergency_fund_months != null ? `${m.emergency_fund_months} mo runway` : '—', statusFor: (m) => m?.emergency_fund_status || (m?.status === 'unavailable' ? 'Not available' : 'No data yet') },
  { key: 'investment', label: 'Investment', metric: (m) => formatCurrency(m?.portfolio_value), statusFor: (m) => m?.status === 'unavailable' ? 'Not available' : 'Portfolio overview' },
  { key: 'retirement', label: 'Retirement', metric: (m) => m?.income_gap != null && m.income_gap !== 0 ? `${formatCurrency(Math.abs(m.income_gap))} ${m.income_gap > 0 ? 'short' : 'surplus'}` : '—', statusFor: (m) => m?.status === 'unavailable' ? 'Not available' : 'Income projection' },
  { key: 'estate', label: 'Estate', metric: (m) => formatCurrency(m?.iht_liability), statusFor: (m) => m?.status === 'unavailable' ? 'Not available' : 'Inheritance tax exposure' },
  { key: 'goals', label: 'Goals & life events', metric: (m) => m?.total_goals != null ? `${m.completed_goals ?? 0}/${m.total_goals} on track` : '—', statusFor: (m) => m?.status === 'unavailable' ? 'Not available' : 'Progress overview' },
];

function formatCurrency(value) {
  if (value == null || value === '' || isNaN(Number(value))) return '—';
  const n = Number(value);
  return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(n);
}

export default {
  name: 'MobileDashboard',
  data: () => ({ loading: true, error: '', payload: null }),
  computed: {
    greeting() {
      const u = store.user;
      const name = u?.first_name || u?.name || (u?.email ? u.email.split('@')[0] : 'there');
      return `Welcome, ${name}.`;
    },
    subtitle() {
      const now = new Date();
      return now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
    },
    netWorthFormatted() {
      const nw = this.payload?.net_worth;
      const value = nw?.total ?? nw?.net_worth ?? nw?.amount;
      return formatCurrency(value);
    },
    fynInsight() {
      return this.payload?.fyn_insight || '';
    },
    moduleCards() {
      const modules = this.payload?.modules || {};
      return MODULE_META.map((meta) => ({
        key: meta.key,
        label: meta.label,
        metric: meta.metric(modules[meta.key]),
        status: meta.statusFor(modules[meta.key]),
      }));
    },
  },
  async created() {
    await this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const { ok, data } = await apiGet('/api/v1/mobile/dashboard', store.token);
        if (ok) this.payload = data?.data ?? data;
        else this.error = data?.message || 'We could not load your overview.';
      } catch (e) {
        this.error = 'Network error. Pull down to refresh or try again.';
      } finally {
        this.loading = false;
      }
    },
    logout() {
      store.logout();
      this.$router.push({ name: 'login' });
    },
  },
};
</script>
