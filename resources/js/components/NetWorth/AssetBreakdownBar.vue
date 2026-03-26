<template>
  <div class="asset-breakdown-bar module-gradient">
    <h3 class="chart-title">Assets & Liabilities</h3>
    <div v-if="hasData" class="chart-container">
      <apexchart
        :key="chartKey"
        type="bar"
        :options="chartOptions"
        :series="chartSeries"
        height="320"
      />
    </div>
    <div v-else class="no-data">
      <p>No wealth data available</p>
    </div>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';
import { ASSET_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'AssetBreakdownBar',
  mixins: [currencyMixin],

  props: {
    breakdown: {
      type: Object,
      required: true,
      default: () => ({}),
    },
    liabilitiesBreakdown: {
      type: Object,
      default: () => ({}),
    },
    totalAssets: {
      type: Number,
      default: 0,
    },
    totalLiabilities: {
      type: Number,
      default: 0,
    },
  },

  computed: {
    chartKey() {
      return `bar-${this.totalAssets}-${this.totalLiabilities}`;
    },

    hasData() {
      return this.totalAssets > 0 || this.totalLiabilities > 0;
    },

    categories() {
      const cats = [];
      if (this.breakdown.pensions > 0) cats.push({ label: 'Pensions', value: this.breakdown.pensions, color: ASSET_COLORS.pensions });
      if (this.breakdown.property > 0) cats.push({ label: 'Property', value: this.breakdown.property, color: ASSET_COLORS.property });
      if (this.breakdown.investments > 0) cats.push({ label: 'Investments', value: this.breakdown.investments, color: ASSET_COLORS.investments });
      if (this.breakdown.cash > 0) cats.push({ label: 'Cash', value: this.breakdown.cash, color: ASSET_COLORS.cash });
      if (this.breakdown.business > 0) cats.push({ label: 'Business', value: this.breakdown.business, color: ASSET_COLORS.business });
      if (this.breakdown.chattels > 0) cats.push({ label: 'Valuables', value: this.breakdown.chattels, color: ASSET_COLORS.chattels });

      // Liabilities as negative
      const liab = this.liabilitiesBreakdown || {};
      if (liab.mortgages > 0) cats.push({ label: 'Mortgages', value: -liab.mortgages, color: '#E83E6D' });
      if (liab.loans > 0) cats.push({ label: 'Loans', value: -liab.loans, color: '#DB2777' });
      if (liab.credit_cards > 0) cats.push({ label: 'Credit Cards', value: -liab.credit_cards, color: '#F472B6' });
      if (liab.other > 0) cats.push({ label: 'Other Debt', value: -liab.other, color: '#FDA4AF' });

      return cats;
    },

    chartSeries() {
      return [{
        name: 'Value',
        data: this.categories.map(c => c.value),
      }];
    },

    chartOptions() {
      const vm = this;
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'bar',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            distributed: true,
            columnWidth: '65%',
            borderRadius: 4,
            colors: {
              ranges: [
                { from: -999999999, to: 0, color: '#E83E6D' },
                { from: 0, to: 999999999, color: '#20B486' },
              ],
            },
          },
        },
        colors: this.categories.map(c => c.color),
        xaxis: {
          categories: this.categories.map(c => c.label),
          labels: {
            style: {
              fontSize: '11px',
              colors: TEXT_COLORS.muted,
            },
            rotate: -45,
            rotateAlways: this.categories.length > 6,
          },
          axisBorder: { show: true, color: '#E5E5E5' },
        },
        yaxis: {
          labels: {
            formatter: (val) => vm.formatCurrencyCompact(Math.abs(val)),
            style: {
              fontSize: '11px',
              colors: TEXT_COLORS.muted,
            },
          },
        },
        grid: {
          borderColor: '#E5E5E5',
          strokeDashArray: 3,
        },
        dataLabels: { enabled: false },
        tooltip: {
          y: {
            formatter: (val) => vm.formatCurrency(val),
          },
        },
        legend: { show: false },
      };
    },
  },

  methods: {
    formatCurrencyCompact(val) {
      if (val >= 1000000) return `£${(val / 1000000).toFixed(1)}M`;
      if (val >= 1000) return `£${(val / 1000).toFixed(0)}K`;
      return `£${val}`;
    },
  },
};
</script>

<style scoped>
.asset-breakdown-bar {
  @apply bg-white rounded-card p-6 shadow-sm border border-light-gray transition-all duration-200;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  @apply text-horizon-500 mb-4;
}

.chart-container {
  @apply w-full;
}

.no-data {
  @apply text-center py-12 px-5 text-horizon-400;
}

.no-data p {
  @apply m-0 text-sm;
}

@media (max-width: 768px) {
  .asset-breakdown-bar {
    @apply p-4;
  }
}
</style>
