<template>
  <div class="spending-chart">
    <h3 class="chart-title">Spending ({{ dateRangeLabel }})</h3>

    <template v-if="hasExpenditureData">
      <div v-if="chartReady" class="chart-container">
        <apexchart
          type="donut"
          :options="chartOptions"
          :series="series"
          height="320"
        />
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
import VueApexCharts from 'vue3-apexcharts';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'SpendingDonutChart',

  mixins: [currencyMixin],

  components: {
    apexchart: VueApexCharts,
  },

  props: {
    financialCommitments: {
      type: Object,
      default: () => ({}),
    },
  },

  data() {
    return {
      chartReady: false,
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
      // Show chart if there's expenditure data OR financial commitments
      const hasExpenditure = this.expenditureProfile && this.discretionaryTotal > 0;
      const hasCommitments = Object.keys(this.financialCommitments).length > 0;
      return hasExpenditure || hasCommitments;
    },

    // Sum of all discretionary expenditure categories (monthly total)
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

    // Build spending categories from user's expenditure profile
    // Apply pro-rata for month-to-date spending
    expenditureCategories() {
      if (!this.expenditureProfile) return {};

      const categories = {};
      const profile = this.expenditureProfile;
      const proRata = this.monthProRata;

      // Apply pro-rata to each discretionary category
      if (profile.food_groceries > 0) {
        categories['Food & Groceries'] = profile.food_groceries * proRata;
      }
      if (profile.transport_fuel > 0) {
        categories['Transport'] = profile.transport_fuel * proRata;
      }
      if (profile.healthcare_medical > 0) {
        categories['Healthcare'] = profile.healthcare_medical * proRata;
      }
      if (profile.insurance > 0) {
        categories['Insurance'] = profile.insurance * proRata;
      }
      if (profile.clothing_personal_care > 0) {
        categories['Clothing & Personal'] = profile.clothing_personal_care * proRata;
      }
      if (profile.entertainment_dining > 0) {
        categories['Entertainment'] = profile.entertainment_dining * proRata;
      }
      if (profile.childcare > 0) {
        categories['Childcare'] = profile.childcare * proRata;
      }
      if (profile.school_fees > 0) {
        categories['School Fees'] = profile.school_fees * proRata;
      }
      if (profile.holidays_travel > 0) {
        categories['Holidays'] = profile.holidays_travel * proRata;
      }
      if (profile.other_expenditure > 0) {
        categories['Other'] = profile.other_expenditure * proRata;
      }

      return categories;
    },

    // Combine all spending into one dataset
    // Financial commitments: full monthly (assumed already paid)
    // Discretionary: pro-rata based on day of month
    combinedSpendingData() {
      const data = {};

      // Add financial commitments first (full monthly amount)
      // These are: Property Expenses, Pension Contributions, Protection Premiums, Loan Payments
      Object.entries(this.financialCommitments).forEach(([key, value]) => {
        if (value > 0) {
          data[key] = value;
        }
      });

      // Add discretionary expenditure categories (pro-rata applied in expenditureCategories)
      Object.entries(this.expenditureCategories).forEach(([key, value]) => {
        if (value > 0) {
          data[key] = value;
        }
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

    chartOptions() {
      const total = this.totalSpending;
      return {
        chart: {
          type: 'donut',
          fontFamily: 'Inter, system-ui, sans-serif',
          toolbar: { show: false },
        },
        labels: this.labels,
        colors: [
          '#7c3aed', // Purple - Mortgage Payments
          '#2563eb', // Blue - Loan Payments
          '#0891b2', // Cyan - Pension Contributions
          '#dc2626', // Red - Protection Premiums
          '#16a34a', // Green - Food & Groceries
          '#f59e0b', // Amber - Transport
          '#ec4899', // Pink - Healthcare
          '#6366f1', // Indigo - Insurance
          '#8b5cf6', // Violet - Clothing & Personal
          '#f97316', // Orange - Entertainment
          '#14b8a6', // Teal - Childcare
          '#84cc16', // Lime - School Fees
          '#06b6d4', // Sky - Holidays
          '#64748b', // Slate - Other
          '#059669', // Emerald - Savings Deposits
          '#be123c', // Rose - Credit Card Spending
        ],
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '14px',
                  fontWeight: 500,
                  color: '#6b7280',
                  offsetY: -10,
                },
                value: {
                  show: true,
                  fontSize: '24px',
                  fontWeight: 700,
                  color: '#111827',
                  offsetY: 5,
                  formatter: (val) => `£${parseFloat(val).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                },
                total: {
                  show: true,
                  showAlways: true,
                  label: 'Total Spent',
                  fontSize: '14px',
                  fontWeight: 500,
                  color: '#6b7280',
                  formatter: () => `£${total.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                },
              },
            },
          },
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: {
          enabled: true,
          y: {
            formatter: (val) => `£${val.toFixed(2)}`,
          },
        },
        responsive: [
          {
            breakpoint: 768,
            options: {
              chart: { height: 260 },
            },
          },
        ],
      };
    },
  },

  mounted() {
    this.$nextTick(() => {
      setTimeout(() => {
        this.chartReady = true;
      }, 100);
    });
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
  color: #111827;
  margin: 0 0 16px 0;
}

.chart-container {
  width: 100%;
}

.empty-prompt {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
  margin: 0 0 12px 0;
}

.add-info-link {
  font-size: 13px;
  color: #7c3aed;
  font-weight: 500;
  text-decoration: none;
}

.add-info-link:hover {
  text-decoration: underline;
}
</style>
