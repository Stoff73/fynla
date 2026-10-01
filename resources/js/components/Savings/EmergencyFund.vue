<template>
  <div class="emergency-fund">
    <!-- Emergency Fund Gauge -->
    <div class="mb-8">
      <div class="bg-white rounded-lg border border-light-gray p-6">
        <h3 class="text-lg font-semibold text-horizon-500 mb-4 text-center">
          Emergency Fund Status
        </h3>
        <EmergencyFundGauge
          v-if="emergencyFundRunway !== null"
          :runway-months="emergencyFundRunway"
          :percent-of-target="percentOfTarget"
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
          <p class="text-3xl font-bold text-horizon-500">{{ formatCurrency(monthlyExpenditure) }}</p>
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
                :style="{ width: currentAmountBarWidth + '%' }"
              ></div>
            </div>
          </div>

          <div class="mt-6 p-4 bg-eggshell-500 rounded-lg">
            <p class="text-sm font-medium text-violet-900">
              <span v-if="shortfall > 0">
                Top up needed: {{ formatCurrency(shortfall) }}
              </span>
              <span v-else-if="shortfall === 0">
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
          v-model.number="whatIfMonths"
          type="range"
          :min="whatIfMin"
          :max="whatIfMax"
          step="1"
          class="w-full"
        />
        <div class="flex justify-between text-sm text-neutral-500 mt-1">
          <span>{{ whatIfMin }} months</span>
          <span class="font-semibold text-horizon-500">{{ whatIfMonths }} months</span>
          <span>{{ whatIfMax }} months</span>
        </div>
      </div>
      <div v-if="whatIfAmount !== null" class="p-4 bg-eggshell-500 rounded-lg">
        <p class="text-sm text-neutral-500">
          With {{ whatIfMonths }} months of expenses, your target emergency fund would be
          <span class="font-semibold">{{ formatCurrency(whatIfAmount) }}</span>
        </p>
      </div>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import EmergencyFundGauge from './EmergencyFundGauge.vue';
import { currencyMixin } from '@/mixins/currencyMixin';
import { RUNWAY_UNAVAILABLE_HINT } from '@/utils/emergencyRunway';

/**
 * The emergency fund tab. Every figure is `SavingsAgent`'s, read from
 * `analysis.emergency_fund` as sent (Rule 20; 2026-10-01 one-figure audit items
 * 22 and 23): the target, its months, the cash held, the percentage of target,
 * the shortfall and the runway. The same block feeds `/m`, iOS and the
 * dashboard.
 *
 * The "Adjust target" slider is a what-if. It starts at the server's target
 * months and shows the server's target for the months chosen
 * (`target_amount_by_months`); it never changes the target above it. The page
 * used to multiply the raw spending column by the slider here, and let the
 * slider rewrite the target and the top-up figure, so the tab disagreed with
 * every other surface the moment it was touched.
 */
export default {
  name: 'EmergencyFund',
  mixins: [currencyMixin],

  components: {
    EmergencyFundGauge,
  },

  data() {
    return {
      // Null until the user moves the slider; until then it sits on the
      // server's target months.
      chosenMonths: null,
    };
  },

  computed: {
    ...mapGetters('savings', ['emergencyFund', 'emergencyFundRunway', 'monthlyExpenditure']),

    targetAmount() {
      return this.emergencyFund?.target?.target_amount ?? null;
    },

    currentAmount() {
      return this.emergencyFund?.current_amount ?? null;
    },

    percentOfTarget() {
      return this.emergencyFund?.percent_of_target ?? null;
    },

    shortfall() {
      return this.emergencyFund?.shortfall ?? null;
    },

    // Presentation only: the bar cannot be drawn past its track.
    currentAmountBarWidth() {
      if (this.percentOfTarget === null) return 0;
      return Math.min(Math.max(this.percentOfTarget, 0), 100);
    },

    currentAmountBarColour() {
      if (this.percentOfTarget >= 100) return 'bg-spring-600';
      if (this.percentOfTarget >= 50) return 'bg-raspberry-500';
      return 'bg-raspberry-600';
    },

    hasExpenditure() {
      return Number(this.monthlyExpenditure) > 0;
    },

    statusMessage() {
      if (!this.hasExpenditure) {
        return RUNWAY_UNAVAILABLE_HINT;
      }
      return this.emergencyFund?.recommendation ?? '';
    },

    whatIfOptions() {
      return Object.keys(this.emergencyFund?.target_amount_by_months || {}).map(Number).sort((a, b) => a - b);
    },

    whatIfMin() {
      return this.whatIfOptions[0] ?? null;
    },

    whatIfMax() {
      return this.whatIfOptions[this.whatIfOptions.length - 1] ?? null;
    },

    whatIfMonths: {
      get() {
        return this.chosenMonths ?? this.emergencyFund?.target_months ?? null;
      },
      set(months) {
        this.chosenMonths = months;
      },
    },

    whatIfAmount() {
      if (this.whatIfMonths === null) return null;
      return this.emergencyFund?.target_amount_by_months?.[String(this.whatIfMonths)] ?? null;
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
