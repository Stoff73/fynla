<template>
  <div class="balance-history module-gradient">
    <div class="history-heading">
      <div>
        <button type="button" class="detail-inline-back" @click="goBack">Back to net worth</button>
        <h1>Balance history</h1>
        <p>See how your recorded assets, liabilities and net worth have changed over time.</p>
      </div>
      <p v-if="history" class="visibility-label">{{ history.visibility.label }}</p>
    </div>

    <div v-if="loading" class="history-state">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      <p>Loading your balance history...</p>
    </div>

    <div v-else-if="error" class="history-state history-error">
      <p>{{ error }}</p>
      <button type="button" class="btn-primary" @click="load">Try again</button>
    </div>

    <template v-else-if="history">
      <div v-if="isFreeWindow" class="premium-window">
        <div>
          <h2>90 days of history are included on Free.</h2>
          <p>Premium includes your full available retained history and the financial adviser export pack.</p>
        </div>
        <button data-testid="history-upgrade" type="button" class="btn-primary" @click="goUpgrade">
          See plans and upgrade
        </button>
      </div>

      <template v-if="hasHistory">
        <div class="metric-grid">
          <div class="card-sm">
            <p class="metric-label">Latest net worth</p>
            <p class="metric-value">{{ formatCurrency(latest.net_worth) }}</p>
          </div>
          <div class="card-sm">
            <p class="metric-label">Latest assets</p>
            <p class="metric-value">{{ formatCurrency(latest.assets) }}</p>
          </div>
          <div class="card-sm">
            <p class="metric-label">Latest liabilities</p>
            <p class="metric-value liability-value">{{ formatCurrency(latest.liabilities) }}</p>
          </div>
        </div>

        <div class="card-lg chart-card">
          <div class="chart-heading">
            <div>
              <h2>Recorded balances</h2>
              <p>{{ effectiveRangeLabel }}</p>
            </div>
            <div v-if="history.year_on_year" class="year-change">
              <p class="metric-label">Year-on-year change</p>
              <p :class="history.year_on_year.net_worth_change >= 0 ? 'positive-value' : 'negative-value'">
                {{ signedCurrency(history.year_on_year.net_worth_change) }}
                <span v-if="history.year_on_year.percentage_change !== null">
                  ({{ signedPercentage(history.year_on_year.percentage_change) }})
                </span>
              </p>
            </div>
          </div>
          <apexchart type="line" height="360" :options="chartOptions" :series="chartSeries" />
        </div>
      </template>

      <div v-else class="card-lg history-state empty-state">
        <h2>No balance history yet</h2>
        <p>Your balance history will appear after account values change.</p>
      </div>

      <div v-if="!isFreeWindow" class="card-lg export-card">
        <div>
          <h2>Financial adviser export pack</h2>
          <p>Download a dated summary of your profile, balances, goals, risk profile, assumptions and module plans.</p>
        </div>
        <button type="button" class="btn-secondary" :disabled="exporting" @click="downloadExport">
          {{ exporting ? 'Preparing export...' : 'Download adviser export pack' }}
        </button>
      </div>
      <p v-if="exportError" class="export-error">{{ exportError }}</p>
    </template>
  </div>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';
import netWorthService from '@/services/netWorthService';
import {
  CHART_COLORS,
  CHART_DEFAULTS,
  TEXT_COLORS,
} from '@/constants/designSystem';
import logger from '@/utils/logger';

export default {
  name: 'BalanceHistory',
  components: { apexchart: VueApexCharts },
  mixins: [currencyMixin],
  data() {
    return {
      loading: true,
      error: '',
      history: null,
      exporting: false,
      exportError: '',
    };
  },
  computed: {
    hasHistory() {
      return (this.history?.totals?.length || 0) > 0;
    },
    isFreeWindow() {
      return this.history?.visibility?.window_days === 90;
    },
    latest() {
      return this.hasHistory ? this.history.totals[this.history.totals.length - 1] : {};
    },
    effectiveRangeLabel() {
      const range = this.history?.effective_range;
      if (!range?.from || !range?.to) return '';
      return `${this.formatDate(range.from)} to ${this.formatDate(range.to)}`;
    },
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
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
        },
        colors: [CHART_COLORS[0], CHART_COLORS[1], CHART_COLORS[3]],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        xaxis: {
          ...CHART_DEFAULTS.xaxis,
          type: 'datetime',
          labels: {
            ...CHART_DEFAULTS.xaxis.labels,
            datetimeUTC: false,
          },
        },
        yaxis: {
          ...CHART_DEFAULTS.yaxis,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '11px' },
            formatter: value => this.formatCurrencyCompact(value),
          },
        },
        tooltip: {
          ...CHART_DEFAULTS.tooltip,
          x: { format: 'dd MMM yyyy' },
          y: { formatter: value => this.formatCurrency(value) },
        },
      };
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await netWorthService.getBalanceHistory();
        this.history = response.data;
      } catch (error) {
        logger.error('Failed to load balance history', error);
        this.error = 'We could not load your balance history. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    goBack() {
      const preview = this.$route?.path?.startsWith('/preview/');
      this.$router.push(preview ? '/preview/net-worth/wealth-summary' : '/net-worth/wealth-summary');
    },
    goUpgrade() {
      this.$router.push('/settings?tab=subscription');
    },
    formatDate(value) {
      return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    },
    signedCurrency(value) {
      const amount = Number(value || 0);
      return `${amount > 0 ? '+' : amount < 0 ? '-' : ''}${this.formatCurrency(Math.abs(amount))}`;
    },
    signedPercentage(value) {
      const amount = Number(value || 0);
      return `${amount > 0 ? '+' : ''}${amount.toFixed(1)}%`;
    },
    async downloadExport() {
      this.exporting = true;
      this.exportError = '';
      try {
        const response = await netWorthService.downloadAdviserExportPack();
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = `fynla-adviser-export-${new Date().toISOString().slice(0, 10)}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      } catch (error) {
        logger.error('Failed to download adviser export pack', error);
        this.exportError = 'We could not prepare your adviser export pack. Please try again.';
      } finally {
        this.exporting = false;
      }
    },
  },
};
</script>

<style scoped>
.balance-history { display: flex; flex-direction: column; gap: 24px; }
.history-heading, .premium-window, .chart-heading, .export-card { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; }
.history-heading h1 { @apply text-3xl font-black text-horizon-500 mt-3; }
.history-heading p, .chart-heading p, .export-card p { @apply text-neutral-500 mt-2; }
.visibility-label { @apply px-3 py-2 rounded-card bg-savannah-100 text-horizon-500 font-bold whitespace-nowrap; }
.history-state { @apply flex flex-col items-center justify-center text-center py-16 text-neutral-500 gap-4; }
.history-error, .export-error { @apply text-raspberry-600; }
.premium-window { @apply rounded-card border border-violet-500 bg-violet-50 p-5; }
.premium-window h2, .chart-heading h2, .export-card h2, .empty-state h2 { @apply text-xl font-bold text-horizon-500; }
.premium-window p { @apply text-neutral-600 mt-1; }
.btn-primary { @apply inline-flex justify-center rounded-button px-4 py-2 text-sm font-bold text-white bg-raspberry-600 hover:bg-raspberry-700 disabled:opacity-50 whitespace-nowrap; }
.btn-secondary { @apply inline-flex justify-center rounded-button border border-horizon-300 px-4 py-2 text-sm font-bold text-horizon-500 bg-white hover:bg-savannah-100 disabled:opacity-50 whitespace-nowrap; }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.metric-label { @apply text-sm text-neutral-500; }
.metric-value { @apply text-2xl font-black text-horizon-500 mt-1; }
.liability-value, .negative-value { @apply text-raspberry-600; }
.positive-value { @apply text-spring-600; }
.chart-card { overflow: hidden; }
.year-change { text-align: right; }
.year-change > p:last-child { @apply text-lg font-bold mt-1; }
.empty-state { @apply py-16; }
.export-error { @apply text-sm -mt-4; }
@media (max-width: 768px) {
  .history-heading, .premium-window, .chart-heading, .export-card { flex-direction: column; }
  .metric-grid { grid-template-columns: 1fr; }
  .visibility-label { white-space: normal; }
  .year-change { text-align: left; }
}
</style>
