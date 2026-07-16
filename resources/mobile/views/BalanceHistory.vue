<template>
  <MobileChrome title="Balance history" subtitle="How your recorded balances have changed" :loading="loading" loading-label="your balance history">
    <div v-if="loading" class="m-card m-state">
      <p class="m-sub">Loading your balance history...</p>
    </div>

    <div v-else-if="error" class="m-card m-state">
      <p class="m-err">{{ error }}</p>
      <button type="button" class="m-btn" @click="load">Try again</button>
    </div>

    <template v-else-if="history">
      <p class="mbh-visibility">{{ history.visibility.label }}</p>

      <div v-if="isFreeWindow" class="m-card mbh-premium">
        <p class="mbh-title">90 days of history are included on Free.</p>
        <p class="m-sub">Premium includes your full available retained history and the financial adviser export pack.</p>
        <button v-if="paidUpgradeAvailable" type="button" class="m-btn" @click="goUpgrade">See plans and upgrade</button>
      </div>

      <template v-if="hasHistory">
        <div class="m-card m-hero">
          <p class="m-sub m-label">Latest net worth</p>
          <p class="m-metric">{{ formatCurrency(latest.net_worth) }}</p>
          <p class="m-hero-sub">{{ formatCurrency(latest.assets) }} in assets, less {{ formatCurrency(latest.liabilities) }} of liabilities.</p>
        </div>

        <div v-if="history.year_on_year" class="m-card mbh-change">
          <p class="m-section-label">Year-on-year change</p>
          <p class="mbh-change__value" :class="history.year_on_year.net_worth_change >= 0 ? 'mbh-positive' : 'mbh-negative'">
            {{ signedCurrency(history.year_on_year.net_worth_change) }}
            <span v-if="history.year_on_year.percentage_change !== null">({{ signedPercentage(history.year_on_year.percentage_change) }})</span>
          </p>
          <p class="m-sub">Compared with {{ formatDate(history.year_on_year.compared_with) }}</p>
        </div>

        <div class="m-card mbh-chart">
          <p class="m-section-label">Recorded balances</p>
          <apexchart type="line" height="280" :options="chartOptions" :series="chartSeries" />
        </div>
      </template>

      <div v-else class="m-card m-state">
        <p class="mbh-title">No balance history yet</p>
        <p class="m-sub">Your balance history will appear after account values change.</p>
      </div>

      <div v-if="!isFreeWindow" class="m-card">
        <p class="mbh-title">Financial adviser export pack</p>
        <p class="m-sub">Download a dated summary of your financial information and module plans.</p>
        <button type="button" class="m-btn" :disabled="exporting" @click="downloadExport">
          {{ exporting ? 'Preparing export...' : 'Download adviser export pack' }}
        </button>
        <p v-if="exportError" class="m-err">{{ exportError }}</p>
      </div>
    </template>
  </MobileChrome>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { apiDownload, apiGet } from '../api.js';
import MobileChrome from '../components/MobileChrome.vue';
import { store } from '../store.js';
import { upgradeMixin } from '../mixins/upgrade.js';
import { formatCurrency, formatCurrencyCompact } from '../../js/utils/currency.js';
import {
  CHART_COLORS,
  CHART_DEFAULTS,
  TEXT_COLORS,
} from '../../js/constants/designSystem.js';

export default {
  name: 'MobileBalanceHistory',
  components: { MobileChrome, apexchart: VueApexCharts },
  mixins: [upgradeMixin],
  data: () => ({
    loading: true,
    error: '',
    history: null,
    exporting: false,
    exportError: '',
  }),
  computed: {
    isFreeWindow() { return this.history?.visibility?.window_days === 90; },
    hasHistory() { return (this.history?.totals?.length || 0) > 0; },
    latest() { return this.hasHistory ? this.history.totals[this.history.totals.length - 1] : {}; },
    chartSeries() {
      const totals = this.history?.totals || [];
      return [
        { name: 'Net worth', data: totals.map(point => [new Date(point.date).getTime(), point.net_worth]) },
        { name: 'Assets', data: totals.map(point => [new Date(point.date).getTime(), point.assets]) },
        { name: 'Liabilities', data: totals.map(point => [new Date(point.date).getTime(), point.liabilities]) },
      ];
    },
    chartOptions() {
      return {
        ...CHART_DEFAULTS,
        chart: { ...CHART_DEFAULTS.chart, type: 'line' },
        colors: [CHART_COLORS[0], CHART_COLORS[1], CHART_COLORS[3]],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        legend: { ...CHART_DEFAULTS.legend, position: 'bottom' },
        xaxis: {
          ...CHART_DEFAULTS.xaxis,
          type: 'datetime',
          tickAmount: 4,
          labels: { ...CHART_DEFAULTS.xaxis.labels, datetimeUTC: false },
        },
        yaxis: {
          ...CHART_DEFAULTS.yaxis,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '10px' },
            formatter: value => formatCurrencyCompact(value),
          },
        },
        tooltip: {
          ...CHART_DEFAULTS.tooltip,
          x: { format: 'dd MMM yyyy' },
          y: { formatter: value => formatCurrency(value) },
        },
      };
    },
  },
  created() { this.load(); },
  methods: {
    formatCurrency,
    async load() {
      this.loading = true;
      this.error = '';
      const response = await apiGet('/api/balance-history', store.token).catch(() => null);
      if (response?.ok) {
        this.history = response.data?.data || response.data || {};
      } else {
        this.error = response?.data?.message || 'We could not load your balance history.';
      }
      this.loading = false;
    },
    formatDate(value) {
      return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
      });
    },
    signedCurrency(value) {
      const amount = Number(value || 0);
      return `${amount > 0 ? '+' : amount < 0 ? '-' : ''}${formatCurrency(Math.abs(amount))}`;
    },
    signedPercentage(value) {
      const amount = Number(value || 0);
      return `${amount > 0 ? '+' : ''}${amount.toFixed(1)}%`;
    },
    async downloadExport() {
      this.exporting = true;
      this.exportError = '';
      const response = await apiDownload('/api/plans/adviser-export-pack', store.token).catch(() => null);
      if (!response?.ok) {
        this.exportError = 'We could not prepare your adviser export pack. Please try again.';
        this.exporting = false;
        return;
      }

      const url = URL.createObjectURL(response.blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `fynla-adviser-export-${new Date().toISOString().slice(0, 10)}.pdf`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      this.exporting = false;
    },
  },
};
</script>

<style scoped>
.mbh-premium { border-color: var(--violet-500); background: var(--savannah-100); }
.mbh-visibility { color: var(--neutral-500); font-size: 13px; font-weight: 700; margin: 0 4px 12px; }
.mbh-title { color: var(--horizon-500); font-size: 17px; font-weight: 700; margin-bottom: 6px; }
.mbh-change .m-section-label, .mbh-chart .m-section-label { margin: 0 0 8px; }
.mbh-change__value { font-size: 24px; font-weight: 900; margin-bottom: 4px; }
.mbh-positive { color: var(--spring-600); }
.mbh-negative { color: var(--raspberry-600); }
.mbh-chart { overflow: hidden; }
</style>
