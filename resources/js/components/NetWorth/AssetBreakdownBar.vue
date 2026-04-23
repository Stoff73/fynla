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
    // Joint-user props. When all four are provided the chart renders a custom
    // tooltip that breaks each category total down by person.
    userBreakdown: {
      type: Object,
      default: null,
    },
    spouseBreakdown: {
      type: Object,
      default: null,
    },
    userLiabilities: {
      type: Object,
      default: null,
    },
    spouseLiabilities: {
      type: Object,
      default: null,
    },
    userName: {
      type: String,
      default: '',
    },
    spouseName: {
      type: String,
      default: '',
    },
  },

  computed: {
    chartKey() {
      return `bar-${this.totalAssets}-${this.totalLiabilities}-${this.isJoint ? 'j' : 's'}`;
    },

    // True when the parent has passed per-user breakdowns alongside the combined
    // ones. In that case the default tooltip is swapped for a per-user split.
    isJoint() {
      return this.userBreakdown !== null && this.spouseBreakdown !== null;
    },

    // Map from the human-readable category label (which ApexCharts shows on the
    // x-axis) to the user/spouse values for that same category. Used by the
    // custom tooltip.
    categoryLookup() {
      const u = this.userBreakdown || {};
      const s = this.spouseBreakdown || {};
      const uL = this.userLiabilities || {};
      const sL = this.spouseLiabilities || {};
      return {
        'Pensions': { user: u.pensions || 0, spouse: s.pensions || 0 },
        'Property': { user: u.property || 0, spouse: s.property || 0 },
        'Investments': { user: u.investments || 0, spouse: s.investments || 0 },
        'Cash': { user: u.cash || 0, spouse: s.cash || 0 },
        'Business': { user: u.business || 0, spouse: s.business || 0 },
        'Valuables': { user: u.chattels || 0, spouse: s.chattels || 0 },
        'Mortgages': { user: uL.mortgages || 0, spouse: sL.mortgages || 0 },
        'Loans': { user: uL.loans || 0, spouse: sL.loans || 0 },
        'Credit Cards': { user: uL.credit_cards || 0, spouse: sL.credit_cards || 0 },
        'Other Debt': { user: uL.other || 0, spouse: sL.other || 0 },
      };
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
        tooltip: this.isJoint
          ? {
            custom: ({ dataPointIndex, w }) => {
              const label = w.globals.labels[dataPointIndex];
              const total = w.globals.series[0][dataPointIndex];
              const split = vm.categoryLookup[label] || { user: 0, spouse: 0 };
              const isLiability = total < 0;
              const totalDisplay = vm.formatCurrency(Math.abs(total));
              const userDisplay = vm.formatCurrency(split.user);
              const spouseDisplay = vm.formatCurrency(split.spouse);
              const color = isLiability ? '#E83E6D' : '#1F2A44';
              return `
                <div style="padding: 8px 12px; background: #ffffff; border: 1px solid #E5E5E5; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); font-family: 'Segoe UI', Inter, system-ui, sans-serif;">
                  <div style="font-weight: 700; color: ${color}; margin-bottom: 6px; font-size: 13px;">${label}: ${totalDisplay}</div>
                  <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #1F2A44;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #5854E6;"></span>
                    <span style="flex: 1;">${vm.userName || 'You'}:</span>
                    <span style="font-weight: 600;">${userDisplay}</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #1F2A44; margin-top: 2px;">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #20B486;"></span>
                    <span style="flex: 1;">${vm.spouseName || 'Partner'}:</span>
                    <span style="font-weight: 600;">${spouseDisplay}</span>
                  </div>
                </div>
              `;
            },
          }
          : {
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
