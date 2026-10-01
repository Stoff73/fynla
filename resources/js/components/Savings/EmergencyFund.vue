<template>
  <div class="emergency-fund">
    <!-- Emergency Fund Gauge -->
    <div class="mb-8">
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4 text-center">
          Emergency Fund Status
        </h3>
        <EmergencyFundGauge
          :percent="fund.covered_percent || 0"
          :status="fund.status"
          :figure="fund.runway_figure || ''"
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
              <span class="text-sm text-neutral-500">Target Fund ({{ targetMonths }} {{ targetMonths === 1 ? 'month' : 'months' }})</span>
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
          <p v-if="rationale" class="text-sm text-neutral-500">{{ rationale }}</p>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import EmergencyFundGauge from './EmergencyFundGauge.vue';
import { currencyMixin } from '@/mixins/currencyMixin';

export default {
  name: 'EmergencyFund',
  mixins: [currencyMixin],

  components: {
    EmergencyFundGauge,
  },

  computed: {
    ...mapGetters('savings', ['emergencyFund', 'monthlyExpenditure', 'emergencyFundTotal']),

    // Every figure is the server's (SavingsPosition, CSJ 2026-10-01): the target
    // from the user's own employment, the share covered and what is still
    // needed. The page's own six-month slider worked a second target out in the
    // browser and is gone.
    fund() {
      return this.emergencyFund || {};
    },

    monthlyTotal() {
      return this.monthlyExpenditure;
    },

    targetMonths() {
      return Number(this.fund.target_months) || 0;
    },

    targetAmount() {
      return Number(this.fund.target_amount) || 0;
    },

    currentAmount() {
      return this.emergencyFundTotal;
    },

    shortfall() {
      return Number(this.fund.shortfall) || 0;
    },

    currentAmountPercentage() {
      return Number(this.fund.covered_percent) || 0;
    },

    currentAmountBarColour() {
      return { on_track: 'bg-spring-600', part: 'bg-violet-500', low: 'bg-raspberry-600' }[this.fund.status] || 'bg-spring-600';
    },

    hasExpenditure() {
      return this.monthlyTotal > 0;
    },

    statusMessage() {
      if (!this.hasExpenditure) {
        return this.fund.runway_hint || '';
      }
      return [this.fund.runway_label, this.fund.covered_label].filter(Boolean).join(', ');
    },

    rationale() {
      return this.fund.rationale || '';
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

