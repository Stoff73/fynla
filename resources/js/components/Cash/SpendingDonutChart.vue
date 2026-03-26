<template>
  <div class="spending-chart">
    <h3 class="chart-title">Spending ({{ dateRangeLabel }})</h3>

    <template v-if="hasExpenditureData">
      <div class="chart-container">
        <div class="relative mx-auto" style="width: 260px; height: 260px;">
          <svg viewBox="0 0 220 220" width="260" height="260">
            <defs>
              <linearGradient
                v-for="(seg, idx) in donutSegments"
                :key="'grad-' + idx"
                :id="'spend-grad-' + idx"
                x1="0%" y1="0%" x2="100%" y2="0%"
              >
                <stop offset="0%" :stop-color="seg.color" />
                <stop offset="100%" :stop-color="seg.colorLight" />
              </linearGradient>
            </defs>
            <circle
              v-for="(seg, idx) in donutSegments"
              :key="'seg-' + idx"
              cx="110" cy="110" r="75"
              fill="none"
              :stroke="'url(#spend-grad-' + idx + ')'"
              stroke-width="40"
              stroke-linecap="round"
              :stroke-dasharray="seg.arcLength + ' ' + 471.2"
              :stroke-dashoffset="-seg.offset"
              transform="rotate(-90 110 110)"
              class="cursor-pointer"
              @mouseenter="hoveredIndex = idx"
              @mouseleave="hoveredIndex = null"
            />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
            <template v-if="hoveredIndex !== null && hoveredIndex < labels.length">
              <span class="text-[10px] font-semibold text-horizon-400">{{ labels[hoveredIndex] }}</span>
              <span class="text-lg font-bold text-horizon-700">{{ formatSpending(series[hoveredIndex]) }}</span>
            </template>
            <template v-else>
              <span class="text-[10px] font-semibold text-horizon-400">Total Spent</span>
              <span class="text-lg font-bold text-horizon-700">{{ formatSpending(totalSpending) }}</span>
            </template>
          </div>
        </div>
        <!-- Legend -->
        <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1.5 max-w-xs mx-auto">
          <div
            v-for="(label, idx) in labels"
            :key="label"
            class="flex items-center gap-2"
          >
            <span
              class="w-2.5 h-2.5 rounded-full flex-shrink-0"
              :style="{ backgroundColor: spendingColors[idx % spendingColors.length] }"
            ></span>
            <span class="text-xs text-neutral-500 truncate">{{ label }}</span>
          </div>
        </div>
      </div>
    </template>
    <template v-else>
      <p class="empty-prompt">Add your expenditure details to see your spending breakdown.</p>
      <router-link to="/profile" class="add-info-link">Add expenditure info</router-link>
    </template>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import { currencyMixin } from '@/mixins/currencyMixin';
import { SPENDING_COLORS } from '@/constants/designSystem';

export default {
  name: 'SpendingDonutChart',

  mixins: [currencyMixin],

  props: {
    financialCommitments: {
      type: Object,
      default: () => ({}),
    },
  },

  data() {
    return {
      hoveredIndex: null,
      spendingColors: SPENDING_COLORS,
    };
  },

  computed: {
    ...mapState('savings', ['expenditureProfile']),

    // Pro-rata factor for current month (day of month / days in month)
    monthProRata() {
      const today = new Date();
      const day = today.getDate();
      const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
      return day / daysInMonth;
    },

    dateRangeLabel() {
      const today = new Date();
      const day = today.getDate();
      const month = today.toLocaleString('en-GB', { month: 'long' });
      return `1 - ${day} ${month}`;
    },

    hasExpenditureData() {
      const hasExpenditure = this.expenditureProfile && this.discretionaryTotal > 0;
      const hasCommitments = Object.keys(this.financialCommitments).length > 0;
      return hasExpenditure || hasCommitments;
    },

    discretionaryTotal() {
      if (!this.expenditureProfile) return 0;
      const profile = this.expenditureProfile;

      return (
        (parseFloat(profile.food_groceries) || 0) +
        (parseFloat(profile.transport_fuel) || 0) +
        (parseFloat(profile.healthcare_medical) || 0) +
        (parseFloat(profile.insurance) || 0) +
        (parseFloat(profile.mobile_phones) || 0) +
        (parseFloat(profile.internet_tv) || 0) +
        (parseFloat(profile.subscriptions) || 0) +
        (parseFloat(profile.clothing_personal_care) || 0) +
        (parseFloat(profile.entertainment_dining) || 0) +
        (parseFloat(profile.holidays_travel) || 0) +
        (parseFloat(profile.pets) || 0) +
        (parseFloat(profile.childcare) || 0) +
        (parseFloat(profile.school_fees) || 0) +
        (parseFloat(profile.children_activities) || 0) +
        (parseFloat(profile.gifts_charity) || 0) +
        (parseFloat(profile.other_expenditure) || 0)
      );
    },

    expenditureCategories() {
      if (!this.expenditureProfile) return {};

      const categories = {};
      const profile = this.expenditureProfile;
      const proRata = this.monthProRata;

      if (profile.food_groceries > 0) categories['Food & Groceries'] = profile.food_groceries * proRata;
      if (profile.transport_fuel > 0) categories['Transport'] = profile.transport_fuel * proRata;
      if (profile.healthcare_medical > 0) categories['Healthcare'] = profile.healthcare_medical * proRata;
      if (profile.insurance > 0) categories['Insurance'] = profile.insurance * proRata;
      if (profile.clothing_personal_care > 0) categories['Clothing & Personal'] = profile.clothing_personal_care * proRata;
      if (profile.entertainment_dining > 0) categories['Entertainment'] = profile.entertainment_dining * proRata;
      if (profile.childcare > 0) categories['Childcare'] = profile.childcare * proRata;
      if (profile.school_fees > 0) categories['School Fees'] = profile.school_fees * proRata;
      if (profile.holidays_travel > 0) categories['Holidays'] = profile.holidays_travel * proRata;
      if (profile.other_expenditure > 0) categories['Other'] = profile.other_expenditure * proRata;

      return categories;
    },

    combinedSpendingData() {
      const data = {};

      Object.entries(this.financialCommitments).forEach(([key, value]) => {
        if (value > 0) data[key] = value;
      });

      Object.entries(this.expenditureCategories).forEach(([key, value]) => {
        if (value > 0) data[key] = value;
      });

      return data;
    },

    series() {
      return Object.values(this.combinedSpendingData);
    },

    labels() {
      return Object.keys(this.combinedSpendingData);
    },

    totalSpending() {
      return this.series.reduce((sum, val) => sum + val, 0);
    },

    donutSegments() {
      if (this.totalSpending === 0) return [];

      const circumference = 471.2;
      const gap = 3;
      let offset = 0;
      return this.series.map((value, idx) => {
        const proportion = value / this.totalSpending;
        const arcLength = Math.max(proportion * circumference - gap, 2);
        const color = SPENDING_COLORS[idx % SPENDING_COLORS.length];
        const seg = {
          color,
          colorLight: this.lightenColor(color, 0.35),
          arcLength,
          offset,
        };
        offset += proportion * circumference;
        return seg;
      });
    },
  },

  methods: {
    lightenColor(hex, amount) {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      const lighten = (c) => Math.min(255, Math.round(c + (255 - c) * amount));
      return `#${lighten(r).toString(16).padStart(2, '0')}${lighten(g).toString(16).padStart(2, '0')}${lighten(b).toString(16).padStart(2, '0')}`;
    },

    formatSpending(val) {
      return `£${parseFloat(val).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },
  },
};
</script>

<style scoped>
.spending-chart {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.chart-title {
  font-size: 16px;
  font-weight: 600;
  @apply text-horizon-500;
  margin: 0 0 16px 0;
}

.chart-container {
  width: 100%;
}

.empty-prompt {
  font-size: 13px;
  @apply text-neutral-500;
  line-height: 1.5;
  margin: 0 0 12px 0;
}

.add-info-link {
  font-size: 13px;
  @apply text-violet-600;
  font-weight: 500;
  text-decoration: none;
}

.add-info-link:hover {
  text-decoration: underline;
}
</style>
