<template>
  <div class="emergency-fund">
    <!-- Emergency Fund Gauge -->
    <div class="mb-8">
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4 text-center">
          Emergency Fund Status
        </h3>
        <EmergencyFundGauge
          :runway-months="emergencyFundRunway"
          :target-months="targetMonths"
        />
        <p class="text-center text-sm text-neutral-500 mt-4">
          {{ statusMessage }}
        </p>
      </div>
    </div>

    <!-- Monthly Expenditure & Target -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Monthly Expenditure Breakdown -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-horizon-500">Monthly Expenditure</h3>
          <button
            v-if="!hasExpenditure"
            @click="navigateToAddExpenditure"
            class="px-4 py-2 bg-raspberry-500 text-white text-sm font-medium rounded-button hover:bg-raspberry-600 transition-colors"
          >
            Add Expenditure
          </button>
        </div>

        <!-- Show message if no expenditure data -->
        <div v-if="!hasExpenditure" class="text-center py-8">
          <div class="mb-4">
            <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
          <p class="text-neutral-500 mb-2">No monthly expenditure data</p>
          <p class="text-sm text-neutral-500">Add your monthly expenditure to calculate emergency fund runway</p>
        </div>

        <!-- Show total expenditure if data exists -->
        <div v-else class="text-center py-8">
          <p class="text-sm text-neutral-500 mb-2">Total Monthly Expenditure</p>
          <p class="text-3xl font-bold text-horizon-500">{{ formatCurrency(monthlyTotal) }}</p>
          <p class="text-sm text-neutral-500 mt-2">
            <router-link to="/profile" class="text-violet-600 hover:text-violet-700">Update in User Profile</router-link>
          </p>
        </div>
      </div>

      <!-- Target vs Actual -->
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4">Target vs Actual</h3>
        <div class="space-y-4">
          <div>
            <div class="flex justify-between mb-1">
              <span class="text-sm text-neutral-500">Target Fund</span>
              <span class="text-sm font-semibold">{{ formatCurrency(targetAmount) }}</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-2">
              <div
                class="h-2 rounded-full bg-raspberry-500"
                style="width: 100%"
              ></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between mb-1">
              <span class="text-sm text-neutral-500">Current Fund</span>
              <span class="text-sm font-semibold">{{ formatCurrency(currentAmount) }}</span>
            </div>
            <div class="w-full bg-savannah-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="currentAmountBarColour"
                :style="{ width: currentAmountPercentage + '%' }"
              ></div>
            </div>
          </div>

          <div class="mt-6 p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm font-medium text-violet-900">
              <span v-if="shortfall > 0">
                Top up needed: {{ formatCurrency(shortfall) }}
              </span>
              <span v-else>
                Emergency fund target achieved!
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Adjust Target -->
    <div class="bg-white rounded-lg border border-light-gray p-6">
      <h3 class="text-lg font-semibold text-horizon-500 mb-4">Adjust Target</h3>
      <div class="mb-4">
        <label class="block text-sm font-medium text-neutral-500 mb-2">
          Target Months of Expenses
        </label>
        <input
          v-model.number="targetMonths"
          type="range"
          min="3"
          max="12"
          step="1"
          class="w-full"
        />
        <div class="flex justify-between text-sm text-neutral-500 mt-1">
          <span>3 months</span>
          <span class="font-semibold text-horizon-500">{{ targetMonths }} months</span>
          <span>12 months</span>
        </div>
      </div>
      <div class="p-4 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500">
          With {{ targetMonths }} months of expenses, your target emergency fund would be
          <span class="font-semibold">{{ formatCurrency(targetAmount) }}</span>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters } from 'vuex';
import EmergencyFundGauge from './EmergencyFundGauge.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EmergencyFund',
  mixins: [currencyMixin],

  components: {
    EmergencyFundGauge,
  },

  data() {
    return {
      targetMonths: 6,
    };
  },

  computed: {
    ...mapState('savings', ['expenditureProfile', 'analysis', 'accounts']),
    ...mapGetters('savings', ['emergencyFundRunway', 'monthlyExpenditure', 'emergencyFundTotal']),

    expenditure() {
      if (!this.expenditureProfile) {
        return {
          housing: 0,
          food: 0,
          utilities: 0,
          other: 0,
        };
      }

      return {
        housing: parseFloat(this.expenditureProfile.monthly_housing) || 0,
        food: parseFloat(this.expenditureProfile.monthly_food) || 0,
        utilities: parseFloat(this.expenditureProfile.monthly_utilities) || 0,
        other: (parseFloat(this.expenditureProfile.monthly_transport) || 0) +
               (parseFloat(this.expenditureProfile.monthly_insurance) || 0) +
               (parseFloat(this.expenditureProfile.monthly_loans) || 0) +
               (parseFloat(this.expenditureProfile.monthly_discretionary) || 0),
      };
    },

    monthlyTotal() {
      // Use total_monthly_expenditure directly since we don't have breakdown
      if (this.expenditureProfile?.total_monthly_expenditure) {
        return parseFloat(this.expenditureProfile.total_monthly_expenditure) || 0;
      }
      // Fallback to summing breakdown if it exists
      return Object.values(this.expenditure).reduce((sum, val) => sum + val, 0);
    },

    targetAmount() {
      return this.monthlyTotal * this.targetMonths;
    },

    currentAmount() {
      return this.emergencyFundTotal;
    },

    shortfall() {
      return Math.max(0, this.targetAmount - this.currentAmount);
    },

    currentAmountPercentage() {
      if (this.targetAmount === 0) return 0;
      return Math.min((this.currentAmount / this.targetAmount) * 100, 100);
    },

    currentAmountBarColour() {
      if (this.currentAmountPercentage >= 100) return 'bg-spring-600';
      if (this.currentAmountPercentage >= 50) return 'bg-raspberry-500';
      return 'bg-raspberry-600';
    },

    hasExpenditure() {
      return this.monthlyTotal > 0;
    },

    statusMessage() {
      if (!this.hasExpenditure) {
        return 'Please add your monthly expenditure to calculate emergency fund runway.';
      }
      if (this.emergencyFundRunway >= 6) {
        return 'Excellent! Your emergency fund exceeds the recommended 6-month target.';
      } else if (this.emergencyFundRunway >= 3) {
        return 'Good progress. Consider building up to 6 months of expenses.';
      } else {
        return 'Priority: Build your emergency fund to at least 3-6 months of expenses.';
      }
    },
  },

  methods: {
    navigateToAddExpenditure() {
      // Navigate to User Profile page with cashflow tab
      this.$router.push({ path: '/profile', query: { tab: 'cashflow' } });
    },
  },
};
</script>

