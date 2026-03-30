<template>
  <div>
    <div class="mb-6">
      <h2 class="text-h4 font-semibold text-horizon-500">Personal Accounts</h2>
      <p class="mt-1 text-body-sm text-neutral-500">
        Auto-calculated financial statements from your profile data
      </p>
    </div>

    <!-- Period Selector -->
    <div class="card p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="start_date" class="block text-body-sm font-medium text-neutral-500 mb-1">
            Start Date
          </label>
          <input
            id="start_date"
            v-model="period.start_date"
            type="date"
            class="input-field"
            :max="today"
            @change="calculateAccounts"
          />
        </div>
        <div>
          <label for="end_date" class="block text-body-sm font-medium text-neutral-500 mb-1">
            End Date
          </label>
          <input
            id="end_date"
            v-model="period.end_date"
            type="date"
            class="input-field"
            :max="today"
            @change="calculateAccounts"
          />
        </div>
        <div>
          <label for="as_of_date" class="block text-body-sm font-medium text-neutral-500 mb-1">
            Balance Sheet As Of
          </label>
          <input
            id="as_of_date"
            v-model="period.as_of_date"
            type="date"
            class="input-field"
            :max="today"
            @change="calculateAccounts"
          />
        </div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-light-gray mb-6">
      <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        <button
          @click="activeTab = 'balance_sheet'"
          :class="[
            activeTab === 'balance_sheet'
              ? 'border-raspberry-500 text-raspberry-700'
              : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-body-sm transition-colors',
          ]"
        >
          Balance Sheet
        </button>
        <button
          @click="activeTab = 'cashflow'"
          :class="[
            activeTab === 'cashflow'
              ? 'border-raspberry-500 text-raspberry-700'
              : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-body-sm transition-colors',
          ]"
        >
          Cashflow
        </button>
        <button
          @click="activeTab = 'profit_loss'"
          :class="[
            activeTab === 'profit_loss'
              ? 'border-raspberry-500 text-raspberry-700'
              : 'border-transparent text-neutral-500 hover:text-neutral-500 hover:border-horizon-300',
            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-body-sm transition-colors',
          ]"
        >
          Profit & Loss
        </button>
      </nav>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-raspberry-500 mx-auto"></div>
        <p class="mt-4 text-body-base text-neutral-500">Calculating accounts...</p>
      </div>
    </div>

    <!-- Tab Content -->
    <div v-else>
      <!-- Balance Sheet -->
      <div v-show="activeTab === 'balance_sheet'">
        <BalanceSheetView
          :data="personalAccounts?.balanceSheet"
          :spouse-data="spouseAccounts?.balanceSheet"
        />
      </div>

      <!-- Cashflow -->
      <div v-show="activeTab === 'cashflow'">
        <CashflowView
          :data="personalAccounts?.cashflow"
          :spouse-data="spouseAccounts?.cashflow"
        />
      </div>

      <!-- Profit & Loss -->
      <div v-show="activeTab === 'profit_loss'">
        <ProfitAndLossView
          :data="personalAccounts?.profitAndLoss"
          :spouse-data="spouseAccounts?.profitAndLoss"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import ProfitAndLossView from './ProfitAndLossView.vue';
import storage from '@/utils/storage';
import CashflowView from './CashflowView.vue';
import BalanceSheetView from './BalanceSheetView.vue';

import logger from '@/utils/logger';
export default {
  name: 'PersonalAccounts',

  components: {
    ProfitAndLossView,
    CashflowView,
    BalanceSheetView,
  },

  setup() {
    const store = useStore();
    const activeTab = ref('balance_sheet');
    const loading = computed(() => store.getters['userProfile/loading']);

    // Today's date for max date validation (prevents future dates)
    const today = computed(() => new Date().toISOString().split('T')[0]);

    const personalAccounts = computed(() => store.getters['userProfile/personalAccounts']);
    const spouseAccounts = computed(() => store.getters['userProfile/spouseAccounts']);

    // Initialize period to current tax year (6 April to 5 April)
    const getCurrentTaxYear = () => {
      const today = new Date();
      const currentYear = today.getFullYear();
      const taxYearStart = new Date(currentYear, 3, 6); // April 6

      if (today < taxYearStart) {
        // Current tax year started last year
        return {
          start_date: `${currentYear - 1}-04-06`,
          end_date: `${currentYear}-04-05`,
          as_of_date: today.toISOString().split('T')[0],
        };
      } else {
        // Current tax year started this year
        return {
          start_date: `${currentYear}-04-06`,
          end_date: `${currentYear + 1}-04-05`,
          as_of_date: today.toISOString().split('T')[0],
        };
      }
    };

    const period = ref(getCurrentTaxYear());

    const calculateAccounts = async () => {
      try {
        await store.dispatch('userProfile/calculatePersonalAccounts', period.value);
        // Save period to localStorage after successful calculation
        storage.set('personalAccounts_period', JSON.stringify(period.value));
      } catch (error) {
        logger.error('Failed to calculate personal accounts:', error);
      }
    };

    onMounted(async () => {
      // Restore saved period from localStorage if available
      const savedPeriod = storage.get('personalAccounts_period');
      if (savedPeriod) {
        try {
          period.value = JSON.parse(savedPeriod);
        } catch (error) {
          logger.error('Failed to restore saved period:', error);
        }
      }
      // Auto-calculate on mount
      await calculateAccounts();
    });

    return {
      activeTab,
      period,
      loading,
      today,
      personalAccounts,
      spouseAccounts,
      calculateAccounts,
    };
  },
};
</script>
