<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-4 sm:py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-h2 font-display text-gray-900">Valuable Information</h1>
        <p class="mt-2 text-body-base text-gray-600">
          Important documents and information for your loved ones
        </p>
      </div>

      <!-- Tab Navigation -->
      <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex overflow-x-auto scrollbar-hide px-3" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                activeTab === tab.id
                  ? 'border-primary-600 text-primary-700'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                'whitespace-nowrap py-3 px-2 sm:px-3 border-b-2 font-medium text-xs sm:text-sm transition-colors flex-shrink-0',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Loading State -->
          <div v-if="loading" class="flex justify-center items-center py-12">
            <div class="text-center">
              <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
              <p class="mt-4 text-body-base text-gray-600">Loading...</p>
            </div>
          </div>

          <!-- Tab Content Components -->
          <div v-else>
            <LetterToSpouse v-if="activeTab === 'letter'" />
            <WillPlanning v-if="activeTab === 'will'" />
            <BalanceSheetTab v-if="activeTab === 'balance_sheet'" />
            <IncomeStatementTab v-if="activeTab === 'income_statement'" />
            <CashFlowTab v-if="activeTab === 'cash_flow'" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import LetterToSpouse from '@/components/UserProfile/LetterToSpouse.vue';
import WillPlanning from '@/components/Estate/WillPlanning.vue';
import BalanceSheetTab from '@/components/UserProfile/BalanceSheetTab.vue';
import IncomeStatementTab from '@/components/UserProfile/IncomeStatementTab.vue';
import CashFlowTab from '@/components/UserProfile/CashFlowTab.vue';

export default {
  name: 'ValuableInfo',

  components: {
    AppLayout,
    LetterToSpouse,
    WillPlanning,
    BalanceSheetTab,
    IncomeStatementTab,
    CashFlowTab,
  },

  setup() {
    const store = useStore();
    const activeTab = ref('letter');
    const loading = ref(false);

    const user = computed(() => store.getters['userProfile/user']);

    // Marital statuses that show "Expression of Wishes" instead of "Letter to Spouse"
    const expressionOfWishesStatuses = ['single', 'widowed', 'divorced'];

    // Tabs with dynamic label for Letter/Expression based on marital status
    const tabs = computed(() => {
      const maritalStatus = user.value?.marital_status;
      const isExpressionOfWishes = expressionOfWishesStatuses.includes(maritalStatus);

      return [
        { id: 'letter', label: isExpressionOfWishes ? 'Expression of Wishes' : 'Letter to Spouse' },
        { id: 'will', label: 'Will' },
        { id: 'balance_sheet', label: 'Balance Sheet' },
        { id: 'income_statement', label: 'Income Statement' },
        { id: 'cash_flow', label: 'Cash Flow' },
      ];
    });

    const loadProfile = async () => {
      loading.value = true;
      try {
        await store.dispatch('userProfile/fetchProfile');
      } catch (err) {
        console.error('Failed to load profile:', err);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      loadProfile();

      // Check for section query parameter and set active tab
      const urlParams = new URLSearchParams(window.location.search);
      const section = urlParams.get('section');
      if (section) {
        const validTabIds = ['letter', 'will', 'balance_sheet', 'income_statement', 'cash_flow'];
        if (validTabIds.includes(section)) {
          activeTab.value = section;
        }
      }
    });

    return {
      activeTab,
      tabs,
      loading,
      user,
    };
  },
};
</script>

<style scoped>
/* Hide scrollbar for horizontal tab navigation */
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
